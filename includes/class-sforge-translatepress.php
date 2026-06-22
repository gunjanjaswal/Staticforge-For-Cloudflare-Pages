<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TranslatePress multilingual integration.
 *
 * The crawler only enumerates default-language permalinks. TranslatePress stores
 * its translations in the database and renders them server-side on each request,
 * so a translated page is only captured if its language-specific URL is actually
 * fetched. This class expands the export URL list with every secondary-language
 * URL TranslatePress would serve, so each language is rendered to static HTML and
 * shipped in the deploy. The "translations live in the DB" detail is irrelevant
 * once a page is frozen — the served HTML is already fully translated.
 *
 * Scope: only URLs that resolve to the SAME host as home_url() are added. That
 * matches TranslatePress subdirectory mode (example.com/fr/, example.com/de/),
 * the only multilingual layout a single Cloudflare Pages project can serve.
 * Subdomain / separate-domain modes place each language on its own host, which
 * one deploy cannot serve, so those URLs are skipped on purpose.
 *
 * Opt out entirely:
 *   add_filter( 'sforge_translatepress_export', '__return_false' );
 */
class SFORGE_TranslatePress {

	public function __construct() {
		// Priority 20 so URLs added by other `sforge_url_list` filters (default 10)
		// are expanded into their language variants too.
		add_filter( 'sforge_url_list', [ $this, 'add_language_urls' ], 20 );
	}

	/**
	 * Whether an active TranslatePress install is present.
	 */
	public static function is_active() {
		return class_exists( 'TRP_Translate_Press' );
	}

	/**
	 * Expand the export URL list with TranslatePress secondary-language URLs.
	 *
	 * @param array $urls Default-language URLs from the crawler.
	 * @return array
	 */
	public function add_language_urls( $urls ) {
		if ( ! is_array( $urls ) || empty( $urls ) ) {
			return $urls;
		}
		if ( ! self::is_active() ) {
			return $urls;
		}
		if ( ! apply_filters( 'sforge_translatepress_export', true ) ) {
			return $urls;
		}

		$settings = get_option( 'trp_settings' );
		if ( empty( $settings['translation-languages'] ) ) {
			return $urls;
		}

		$languages = (array) $settings['translation-languages'];
		$default   = isset( $settings['default-language'] ) ? $settings['default-language'] : '';
		$slugs     = isset( $settings['url-slugs'] ) ? (array) $settings['url-slugs'] : [];

		$converter = $this->get_url_converter();
		$home_host = wp_parse_url( home_url(), PHP_URL_HOST );

		$added         = [];
		$skipped_host  = false;
		foreach ( $urls as $url ) {
			foreach ( $languages as $lang ) {
				if ( $lang === '' || $lang === $default ) {
					continue;
				}

				$translated = $this->url_for_language( $converter, $lang, $url, $slugs );
				if ( ! $translated ) {
					continue;
				}

				// One Cloudflare Pages project serves one hostname. Skip any URL on a
				// different host (TranslatePress subdomain / domain mode) — it cannot be
				// served from this deploy.
				if ( wp_parse_url( $translated, PHP_URL_HOST ) !== $home_host ) {
					$skipped_host = true;
					continue;
				}

				$added[] = $translated;
			}
		}

		if ( empty( $added ) ) {
			if ( $skipped_host && class_exists( 'SFORGE_Logger' ) ) {
				SFORGE_Logger::log( 'TranslatePress: secondary languages are on separate subdomains/domains, which a single Cloudflare Pages deploy cannot serve. Switch TranslatePress to subdirectory mode (e.g. /fr/) to export them. No language URLs added.', 'warning' );
			}
			return $urls;
		}

		$merged = array_values( array_unique( array_filter( array_merge( $urls, $added ) ) ) );

		if ( class_exists( 'SFORGE_Logger' ) ) {
			SFORGE_Logger::log( sprintf(
				'TranslatePress: added %d secondary-language URL(s) across %d language(s) to the export.',
				count( $merged ) - count( $urls ),
				max( 0, count( $languages ) - 1 )
			) );
		}

		return $merged;
	}

	/**
	 * TranslatePress's own URL converter component, if reachable. It builds the
	 * correct per-language URL for whatever permalink mode TP is configured in.
	 *
	 * @return object|null
	 */
	protected function get_url_converter() {
		if ( ! class_exists( 'TRP_Translate_Press' ) || ! method_exists( 'TRP_Translate_Press', 'get_trp_instance' ) ) {
			return null;
		}
		$trp = TRP_Translate_Press::get_trp_instance();
		if ( ! is_object( $trp ) || ! method_exists( $trp, 'get_component' ) ) {
			return null;
		}
		$converter = $trp->get_component( 'url_converter' );
		return is_object( $converter ) ? $converter : null;
	}

	/**
	 * Resolve the URL TranslatePress would serve for $url in $lang.
	 *
	 * Prefers TranslatePress's own converter (mode-agnostic). Falls back to manual
	 * subdirectory-slug insertion when the converter is unavailable.
	 *
	 * @return string Empty string when no URL could be built.
	 */
	protected function url_for_language( $converter, $lang, $url, $slugs ) {
		if ( $converter && method_exists( $converter, 'get_url_for_language' ) ) {
			$translated = $converter->get_url_for_language( $lang, $url );
			if ( is_string( $translated ) && $translated !== '' ) {
				// Strip a TranslatePress "already processed" marker if one leaked in.
				$translated = preg_replace( '/#TRP\w*$/', '', $translated );
				return $translated;
			}
		}

		// Manual fallback — subdirectory mode, root installs.
		if ( ! empty( $slugs[ $lang ] ) ) {
			$path = (string) wp_parse_url( $url, PHP_URL_PATH );
			if ( $path === '' ) {
				$path = '/';
			}
			return home_url( '/' . $slugs[ $lang ] . $path );
		}

		return '';
	}
}
