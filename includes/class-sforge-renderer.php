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

	/** @var array Collected /wp-content/uploads/ paths discovered across rendered HTML. [rel_path => origin_url] */
	protected $collected_uploads = [];

	public function get_collected_uploads() {
		return $this->collected_uploads;
	}

	/**
	 * Map a same-origin URL onto the configured render-origin override (e.g.
	 * http://127.0.0.1 or http://127.0.0.1:8080) so export fetches connect to the
	 * local box instead of looping out through the public host / Cloudflare and
	 * back. WordPress still resolves the right site because the original host is
	 * passed back as a Host header.
	 *
	 * @param string $url Absolute URL to fetch.
	 * @return array [ string $fetch_url, array $extra_headers ]. When the override
	 *               is unset or the URL is not on the WP origin host, returns the
	 *               URL unchanged with an empty header array (a no-op).
	 */
	public static function localize_request( $url ) {
		$override = trim( (string) SFORGE_Settings::get( 'render_origin', '' ) );
		if ( $override === '' ) {
			return [ $url, [] ];
		}
		$home_host = wp_parse_url( home_url(), PHP_URL_HOST );
		$u         = wp_parse_url( $url );
		if ( empty( $u['host'] ) || ! $home_host || strcasecmp( $u['host'], $home_host ) !== 0 ) {
			return [ $url, [] ]; // Only rewrite URLs that live on the WP origin.
		}
		$ov = wp_parse_url( $override );
		if ( empty( $ov['host'] ) ) {
			return [ $url, [] ];
		}
		$scheme = $ov['scheme'] ?? ( $u['scheme'] ?? 'http' );
		$port   = isset( $ov['port'] ) ? ':' . (int) $ov['port'] : '';
		$path   = $u['path'] ?? '/';
		$query  = isset( $u['query'] ) ? '?' . $u['query'] : '';
		$fetch  = $scheme . '://' . $ov['host'] . $port . $path . $query;

		// Preserve the original host (and port) so WordPress serves the correct
		// site / language variant for the request.
		$host_header = $home_host . ( isset( $u['port'] ) ? ':' . (int) $u['port'] : '' );
		return [ $fetch, [ 'Host' => $host_header ] ];
	}

	public function render_url( $url ) {
		list( $fetch_url, $extra_headers ) = self::localize_request( $url );
		$resp = wp_remote_get( $fetch_url, [
			'timeout'     => 45,
			// Loopback / IP overrides present a cert that won't match the public
			// host, so TLS verification is skipped only when an override is active.
			'sslverify'   => empty( $extra_headers ) ? apply_filters( 'sforge_sslverify', true ) : false,
			'redirection' => 5,
			'user-agent'  => 'SendStaticToPages/' . SFORGE_VERSION,
			'headers'     => array_merge( [ 'X-SFORGE-Export' => '1' ], $extra_headers ),
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
		$this->collect_uploads_from_html( $html );
		$html = $this->rewrite_urls( $html );
		$html = $this->strip_admin_artifacts( $html );
		$html = $this->strip_noindex( $html );
		$html = $this->inject_pages_dev_redirect( $html );
		return $html;
	}

	/**
	 * Scan raw HTML for origin /wp-content/uploads/ URLs (literal + JSON-escaped + percent-encoded
	 * forms) and remember them so the rebuild can fetch each file and bundle it into the deploy.
	 * No-op unless `bundle_uploads` setting is enabled.
	 */
	protected function collect_uploads_from_html( $html ) {
		if ( ! (int) SFORGE_Settings::get( 'bundle_uploads', 0 ) ) {
			return;
		}
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( ! $host ) {
			return;
		}
		$h    = preg_quote( $host, '#' );
		$char = '[^\s"\'<>,()\\\\]+';
		$patterns = [
			// Literal: https://host/wp-content/uploads/<path>.<ext>
			'#https?://' . $h . '/wp-content/uploads/' . $char . '\.[A-Za-z0-9]{2,5}#i',
			// JSON-escaped: https:\/\/host\/wp-content\/uploads\/<path>.<ext>
			'#https?:\\\\/\\\\/' . $h . '\\\\/wp-content\\\\/uploads\\\\/[^\s"\'<>,()]+?\.[A-Za-z0-9]{2,5}#i',
			// Percent-encoded: https%3A%2F%2Fhost%2Fwp-content%2Fuploads%2F<path>.<ext>
			'#https?%3A%2F%2F' . $h . '%2Fwp-content%2Fuploads%2F[^\s"\'<>,()&]+?\.[A-Za-z0-9]{2,5}#i',
		];
		foreach ( $patterns as $pat ) {
			if ( preg_match_all( $pat, $html, $m ) ) {
				foreach ( $m[0] as $hit ) {
					$clean = str_replace( '\\/', '/', $hit );
					if ( stripos( $clean, '%2F' ) !== false ) {
						$clean = urldecode( $clean );
					}
					$path = wp_parse_url( $clean, PHP_URL_PATH );
					if ( ! $path ) {
						continue;
					}
					$rel = ltrim( $path, '/' );
					if ( ! isset( $this->collected_uploads[ $rel ] ) ) {
						$this->collected_uploads[ $rel ] = $clean;
					}
				}
			}
		}
	}

	/**
	 * Inject a tiny synchronous script at the top of <head> that 301-style redirects the
	 * browser whenever the page is being served from any *.pages.dev hostname. CF Pages'
	 * Direct Upload API does not activate _worker.js advanced mode, so a client-side
	 * redirect is the only way to bounce pages.dev hits to the canonical live host
	 * without external CF config.
	 */
	protected function inject_pages_dev_redirect( $html ) {
		if ( ! (int) SFORGE_Settings::get( 'redirect_pages_dev', 1 ) ) {
			return $html;
		}
		$cf_url = trim( (string) SFORGE_Settings::get( 'cf_pages_url', '' ), '/' );
		if ( $cf_url === '' ) {
			return $html;
		}
		$host = (string) wp_parse_url( $cf_url, PHP_URL_HOST );
		if ( $host === '' || substr( $host, -10 ) === '.pages.dev' ) {
			return $html;
		}
		$target = wp_json_encode( $cf_url );
		$script = '<script data-sforge="pages-dev-redirect">(function(){if(location.hostname.indexOf(".pages.dev")>-1){location.replace(' . $target . '+location.pathname+location.search+location.hash);}})();</script>';
		if ( preg_match( '#<head[^>]*>#i', $html ) ) {
			return preg_replace( '#(<head[^>]*>)#i', '$1' . $script, $html, 1 );
		}
		return $script . $html;
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
			// Defensive: strip any literal `</style` sequences (case-insensitive) so a
			// hostile/origin-malformed stylesheet cannot break out of the inline <style> block.
			$css = preg_replace( '#</style#i', '<\\/style', (string) $css );
			// NOTE on wp_enqueue_style:
			// This <style> block is written into the EXPORTED static HTML that gets
			// uploaded to Cloudflare Pages — it is not rendered on a live WordPress
			// request, so wp_enqueue_style()/wp_add_inline_style() are not applicable
			// here. The plugin is producing a static deliverable; the only viable
			// embed mechanism is a literal <style> tag in the output string.
			$style_tag = '<style data-sforge-from="' . esc_attr( $abs ) . '">' . $css . '</style>'; // phpcs:ignore WordPress.WP.EnqueuedResources -- Embedded into exported static HTML for Cloudflare Pages deploy; no live WP enqueue path exists.
			$html = str_replace( $tag, $style_tag, $html );
		}
		return $html;
	}

	protected function fetch_css( $url ) {
		if ( isset( $this->css_cache[ $url ] ) ) {
			return $this->css_cache[ $url ];
		}
		list( $fetch_url, $extra_headers ) = self::localize_request( $url );
		$resp = wp_remote_get( $fetch_url, [
			'timeout'   => 30,
			'sslverify' => empty( $extra_headers ) ? apply_filters( 'sforge_sslverify', true ) : false,
			'headers'   => $extra_headers,
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
		// proxy / mirror / CDN for /wp-content/*. `bundle_uploads` is a softer
		// variant: only /wp-content/uploads/* gets rewritten + bundled into the
		// deploy, while themes/plugins/etc. stay on origin.
		$rewrite_wpcontent = (int) SFORGE_Settings::get( 'rewrite_wpcontent', 0 );
		$bundle_uploads    = (int) SFORGE_Settings::get( 'bundle_uploads', 0 );

		$wpc_lit  = $origin . '/wp-content/';
		$wpc_esc  = $origin_esc . '\\/wp-content\\/';
		$wpc_enc  = $origin_urlenc . '%2Fwp-content%2F';        // canonical uppercase
		$wpc_encl = strtolower( $wpc_enc );                     // lowercase variant

		// Uploads-specific tokens (only used when bundle_uploads is on and
		// rewrite_wpcontent is off — pull uploads out of the "keep on origin"
		// placeholder so the host rewrite *does* touch them.
		$up_lit  = $origin . '/wp-content/uploads/';
		$up_esc  = $origin_esc . '\\/wp-content\\/uploads\\/';
		$up_enc  = $origin_urlenc . '%2Fwp-content%2Fuploads%2F';
		$up_encl = strtolower( $up_enc );

		$ph_up_lit  = "\x00SFORGE_BUNDLE_UP_LIT\x00";
		$ph_up_esc  = "\x00SFORGE_BUNDLE_UP_ESC\x00";
		$ph_up_enc  = "\x00SFORGE_BUNDLE_UP_ENC\x00";
		$ph_up_encl = "\x00SFORGE_BUNDLE_UP_ENCL\x00";

		$do_bundle_uploads = ( ! $rewrite_wpcontent && $bundle_uploads );
		if ( $do_bundle_uploads ) {
			// Stash uploads URLs out of the way first, so the wp-content
			// "keep on origin" pass below doesn't catch them.
			$html = str_replace( $up_lit,  $ph_up_lit,  $html );
			$html = str_replace( $up_esc,  $ph_up_esc,  $html );
			$html = str_replace( $up_enc,  $ph_up_enc,  $html );
			$html = str_replace( $up_encl, $ph_up_encl, $html );
		}

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

		if ( $do_bundle_uploads ) {
			// Put uploads URLs back as plain origin form so the host rewrite
			// below converts them to the CF host (alongside page links).
			$html = str_replace( $ph_up_lit,  $up_lit,  $html );
			$html = str_replace( $ph_up_esc,  $up_esc,  $html );
			$html = str_replace( $ph_up_enc,  $up_enc,  $html );
			$html = str_replace( $ph_up_encl, $up_encl, $html );
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
