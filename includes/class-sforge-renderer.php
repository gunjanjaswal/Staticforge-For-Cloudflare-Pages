<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fetches a URL via wp_remote_get, returns rendered HTML with optional CSS inlining
 * and origin -> CF-Pages URL rewriting.
 */
class SFORGE_Renderer {

	/** @var array Cache of fetched CSS file bodies keyed by URL within a single rebuild. */
	protected $css_cache = [];

	public function render_url( $url ) {
		$resp = wp_remote_get( $url, [
			'timeout'     => 45,
			'sslverify'   => apply_filters( 'sforge_sslverify', true ),
			'redirection' => 5,
			'user-agent'  => 'SendStaticToPages/' . SFORGE_VERSION,
			'headers'     => [
				'X-SFORGE-Export' => '1',
			],
		] );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$code = wp_remote_retrieve_response_code( $resp );
		if ( $code !== 200 ) {
			return new WP_Error( 'sforge_http_' . $code, 'HTTP ' . $code . ' for ' . $url );
		}
		$html = wp_remote_retrieve_body( $resp );
		if ( SFORGE_Settings::get( 'inline_css' ) ) {
			$html = $this->inline_stylesheets( $html, $url );
		}
		$html = $this->rewrite_urls( $html );
		$html = $this->strip_admin_artifacts( $html );
		$html = $this->strip_noindex( $html );
		return $html;
	}

	/**
	 * Remove noindex / nofollow / noarchive / nosnippet directives from any robots-family meta
	 * tag, plus dashboard-only X-Robots headers that may have leaked into HTML, plus drop the
	 * dashboard /robots.txt Disallow rules. This ensures the deployed live site is fully indexable
	 * even if the source dashboard is configured to discourage indexing.
	 */
	protected function strip_noindex( $html ) {
		// Match <meta name="robots|googlebot|bingbot|..." content="...">  case-insensitive.
		$html = preg_replace_callback(
			'#<meta\s+[^>]*name=([\'"])(robots|googlebot|bingbot|slurp|duckduckbot|baiduspider|yandex)\1[^>]*>#i',
			function( $m ) {
				$tag = $m[0];
				if ( ! preg_match( '#content=([\'"])([^\'"]*)\1#i', $tag, $cm ) ) {
					return $tag;
				}
				$directives = preg_split( '#[\s,]+#', strtolower( trim( $cm[2] ) ) );
				$bad = [ 'noindex', 'nofollow', 'noarchive', 'nosnippet', 'noimageindex', 'none' ];
				$kept = array_values( array_diff( $directives, $bad ) );
				if ( empty( $kept ) ) {
					return ''; // drop the meta entirely
				}
				$new_content = implode( ', ', $kept );
				return preg_replace( '#content=([\'"])[^\'"]*\1#i', 'content="' . $new_content . '"', $tag );
			},
			$html
		);
		return $html;
	}

	/**
	 * Find <link rel="stylesheet" href="..."> tags in <head>, fetch each href, inline as <style>.
	 */
	protected function inline_stylesheets( $html, $base_url ) {
		if ( ! preg_match_all( '#<link[^>]+rel=([\'"])stylesheet\1[^>]*?>#i', $html, $matches, PREG_SET_ORDER ) ) {
			return $html;
		}
		foreach ( $matches as $m ) {
			$tag = $m[0];
			if ( ! preg_match( '#href=([\'"])([^\'"]+)\1#i', $tag, $hm ) ) {
				continue;
			}
			$href = html_entity_decode( $hm[2], ENT_QUOTES );
			$abs  = $this->absolute_url( $href, $base_url );
			$css  = $this->fetch_css( $abs );
			if ( is_wp_error( $css ) || $css === '' ) {
				continue;
			}
			// Resolve url() refs inside CSS to absolute URLs against the CSS file's location.
			$css = $this->rewrite_css_urls( $css, $abs );
			$style_tag = '<style data-sforge-from="' . esc_attr( $abs ) . '">' . $css . '</style>';
			$html = str_replace( $tag, $style_tag, $html );
		}
		return $html;
	}

	protected function fetch_css( $url ) {
		if ( isset( $this->css_cache[ $url ] ) ) {
			return $this->css_cache[ $url ];
		}
		$resp = wp_remote_get( $url, [
			'timeout'   => 30,
			'sslverify' => apply_filters( 'sforge_sslverify', true ),
		] );
		if ( is_wp_error( $resp ) ) {
			$this->css_cache[ $url ] = '';
			return '';
		}
		if ( wp_remote_retrieve_response_code( $resp ) !== 200 ) {
			$this->css_cache[ $url ] = '';
			return '';
		}
		$body = wp_remote_retrieve_body( $resp );
		$this->css_cache[ $url ] = $body;
		return $body;
	}

	protected function rewrite_css_urls( $css, $css_url ) {
		return preg_replace_callback( '#url\(\s*([\'"]?)([^\'")]+)\1\s*\)#i', function( $m ) use ( $css_url ) {
			$ref = trim( $m[2] );
			if ( $ref === '' || strpos( $ref, 'data:' ) === 0 ) {
				return $m[0];
			}
			$abs = $this->absolute_url( $ref, $css_url );
			return 'url("' . $abs . '")';
		}, $css );
	}

	protected function absolute_url( $href, $base ) {
		if ( preg_match( '#^https?://#i', $href ) ) {
			return $href;
		}
		if ( strpos( $href, '//' ) === 0 ) {
			$scheme = wp_parse_url( $base, PHP_URL_SCHEME );
			return ( $scheme ?: 'https' ) . ':' . $href;
		}
		$parts  = wp_parse_url( $base );
		$scheme = $parts['scheme'] ?? 'https';
		$host   = $parts['host'] ?? '';
		$port   = isset( $parts['port'] ) ? ':' . $parts['port'] : '';
		$origin = $scheme . '://' . $host . $port;
		if ( strpos( $href, '/' ) === 0 ) {
			return $origin . $href;
		}
		$dir = isset( $parts['path'] ) ? rtrim( dirname( $parts['path'] ), '/' ) : '';
		return $origin . $dir . '/' . $href;
	}

	/**
	 * Replace WP origin host with CF Pages host so canonical / page links resolve to the deployed
	 * static site, BUT keep `/wp-content/*` paths (uploads, theme assets, plugin assets, fonts)
	 * pointing at the origin so media/static files served by WordPress keep working — those files
	 * are not bundled in the static deploy.
	 */
	public function rewrite_urls( $html ) {
		$cf = trim( (string) SFORGE_Settings::get( 'cf_pages_url', '' ), '/' );
		if ( $cf === '' ) {
			return $html;
		}
		$origin = trim( home_url(), '/' );
		if ( $origin === '' || $origin === $cf ) {
			return $html;
		}

		// JSON-LD and inline JSON often serialise URLs with escaped forward slashes
		// (https:\/\/origin\/...). oEmbed / REST query strings serialise them
		// percent-encoded (https%3A%2F%2Forigin%2F...). Build literal, JSON-escaped
		// and URL-encoded variants so every form gets rewritten in lockstep.
		$origin_esc    = str_replace( '/', '\\/', $origin );
		$cf_esc        = str_replace( '/', '\\/', $cf );
		$origin_urlenc = rawurlencode( $origin );           // https%3A%2F%2Fdashboard.example.com
		$cf_urlenc     = rawurlencode( $cf );

		// /wp-content/ URLs: by default kept on origin so theme/plugin/upload
		// assets keep working (CF Pages doesn't have those files). User can opt
		// in to rewriting them to the live host if they've arranged their own
		// proxy / mirror / CDN for /wp-content/*.
		$rewrite_wpcontent = (int) SFORGE_Settings::get( 'rewrite_wpcontent', 0 );

		$wpc_lit  = $origin . '/wp-content/';
		$wpc_esc  = $origin_esc . '\\/wp-content\\/';
		$wpc_enc  = $origin_urlenc . '%2Fwp-content%2F';        // canonical uppercase
		$wpc_encl = strtolower( $wpc_enc );                     // lowercase variant

		if ( ! $rewrite_wpcontent ) {
			$ph_lit  = "\x00SFORGE_KEEP_ORIGIN_LIT\x00";
			$ph_esc  = "\x00SFORGE_KEEP_ORIGIN_ESC\x00";
			$ph_enc  = "\x00SFORGE_KEEP_ORIGIN_ENC\x00";
			$ph_encl = "\x00SFORGE_KEEP_ORIGIN_ENCL\x00";
			$html = str_replace( $wpc_lit,  $ph_lit,  $html );
			$html = str_replace( $wpc_esc,  $ph_esc,  $html );
			$html = str_replace( $wpc_enc,  $ph_enc,  $html );
			$html = str_replace( $wpc_encl, $ph_encl, $html );
		}

		// Literal-form replacements (page links, canonicals, og:url, etc.).
		$html = str_replace( $origin . '/',  $cf . '/',  $html );
		$html = str_replace( $origin . '"',  $cf . '"',  $html );
		$html = str_replace( $origin . "'",  $cf . "'",  $html );
		$html = str_replace( $origin,        $cf,        $html );

		// Escaped-form replacements (JSON-LD, JSON in <script>, REST embeds, etc.).
		$html = str_replace( $origin_esc, $cf_esc, $html );

		// Percent-encoded replacements (oEmbed alternate URLs, REST endpoints
		// with ?url= query strings, etc.). Case-insensitive so %3A and %3a
		// variants both match.
		$html = str_ireplace( $origin_urlenc, $cf_urlenc, $html );

		if ( ! $rewrite_wpcontent ) {
			$html = str_replace( $ph_lit,  $wpc_lit,  $html );
			$html = str_replace( $ph_esc,  $wpc_esc,  $html );
			$html = str_replace( $ph_enc,  $wpc_enc,  $html );
			$html = str_replace( $ph_encl, $wpc_encl, $html );
		}
		return $html;
	}

	protected function strip_admin_artifacts( $html ) {
		$html = preg_replace( '#<div[^>]+id=([\'"])wpadminbar\1[^>]*>.*?</div>\s*#is', '', $html );
		$html = preg_replace( '#<link[^>]+id=([\'"])admin-bar[^"\']*\1[^>]*>#i', '', $html );
		$html = preg_replace( '#<style[^>]+id=([\'"])admin-bar[^"\']*\1[^>]*>.*?</style>#is', '', $html );
		return $html;
	}

	/**
	 * Convert URL to a relative file path inside the deployment.
	 *  https://site/about/  -> about/index.html
	 *  https://site/        -> index.html
	 *  https://site/sitemap.xml -> sitemap.xml
	 */
	public function url_to_path( $url ) {
		$path = wp_parse_url( $url, PHP_URL_PATH );
		if ( ! $path || $path === '/' ) {
			return 'index.html';
		}
		$path = trim( $path, '/' );
		$ext  = pathinfo( $path, PATHINFO_EXTENSION );
		if ( $ext === '' ) {
			$path .= '/index.html';
		}
		return $path;
	}
}
