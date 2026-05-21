<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generates SEO support files for the deployed site:
 *  - robots.txt  (always emitted, indexable, links to the active sitemap)
 *  - sitemap.xml + any child sitemaps:
 *      1. Mirrored from the SEO plugin / WP core if discovered on the origin.
 *      2. Falls back to a self-generated sitemap.xml built from the plugin's
 *         own crawled URL list when nothing is found at the origin (e.g. small
 *         sites, sites with no SEO plugin and core sitemap disabled, etc.).
 *
 * Origin host inside XML payloads is rewritten to the configured CF Pages URL so
 * search engines see canonical live URLs.
 */
class SFORGE_Seo {

	/**
	 * @param array $url_list ignored — kept for backwards compat with the old hooks call.
	 *                        Sitemap groups are now built independently from settings.
	 * @return array<string,string> [ relative_path => contents ]
	 */
	public function collect( $url_list = [] ) {
		$files    = [];
		$sitemaps = $this->discover_sitemaps();
		$primary  = '';
		$mirrored_paths = [];

		foreach ( $sitemaps as $url => $body ) {
			if ( $body === '' ) {
				$body = $this->fetch( $url );
				if ( $body === '' ) {
					continue;
				}
			}
			$path = ltrim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
			if ( $path === '' ) {
				continue;
			}
			$files[ $path ] = $this->rewrite_xml( $body );
			$mirrored_paths[] = $path;
			if ( $primary === '' ) {
				$primary = $path;
			}
		}

		if ( ! empty( $mirrored_paths ) ) {
			SFORGE_Logger::log( 'Sitemap mirrored from origin: ' . implode( ', ', $mirrored_paths ) );
		}

		// Fallback: build sitemap(s) from native WP data using sitemap-specific settings.
		if ( $primary === '' ) {
			$groups = $this->build_url_groups();
			if ( empty( $groups ) ) {
				SFORGE_Logger::log( 'Sitemap fallback skipped: no post types / archives selected in Sitemap Generator settings.', 'warn' );
			} else {
				$total = 0;
				foreach ( $groups as $items ) { $total += count( $items ); }

				$generated = (int) SFORGE_Settings::get( 'sitemap_split', 0 )
					? $this->build_split_sitemaps( $groups )
					: $this->build_single_sitemap( $groups );

				if ( ! empty( $generated ) ) {
					foreach ( $generated as $path => $xml ) {
						$files[ $path ] = $xml;
					}
					$primary = 'sitemap.xml';
					$mode    = (int) SFORGE_Settings::get( 'sitemap_split', 0 ) ? 'split' : 'single';
					SFORGE_Logger::log( sprintf(
						'Sitemap generated locally (%s mode): %d URLs across %d groups, %d file(s).',
						$mode, $total, count( $groups ), count( $generated )
					) );
				} else {
					SFORGE_Logger::log( 'Sitemap fallback returned no files.', 'warn' );
				}
			}
		}

		$files['robots.txt'] = $this->build_robots( $primary );
		return $files;
	}

	/**
	 * Build URL groups based on sitemap_* settings (independent of export scope).
	 * @return array<string, array<int,array{url:string,lastmod:string}>>
	 *         Group key => list of [ 'url' => permalink, 'lastmod' => ISO 8601 ]
	 */
	protected function build_url_groups() {
		$groups   = [];

		if ( SFORGE_Settings::get( 'sitemap_homepage', 1 ) ) {
			$groups['homepage'][] = [ 'url' => home_url( '/' ), 'lastmod' => '' ];
			$blog_id = (int) get_option( 'page_for_posts' );
			if ( $blog_id ) {
				$link = get_permalink( $blog_id );
				if ( $link ) {
					$groups['homepage'][] = [ 'url' => $link, 'lastmod' => (string) get_post_modified_time( 'c', false, $blog_id ) ];
				}
			}
		}

		$post_types = (array) SFORGE_Settings::get( 'sitemap_post_types', [ 'post', 'page' ] );
		foreach ( $post_types as $pt ) {
			$q = new WP_Query( [
				'post_type'      => $pt,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			] );
			foreach ( $q->posts as $pid ) {
				$link = get_permalink( $pid );
				if ( ! $link ) {
					continue;
				}
				$groups[ $pt ][] = [
					'url'     => $link,
					'lastmod' => (string) get_post_modified_time( 'c', false, $pid ),
				];
			}
			$archive = get_post_type_archive_link( $pt );
			if ( $archive ) {
				$groups[ $pt ][] = [ 'url' => $archive, 'lastmod' => '' ];
			}
		}

		if ( SFORGE_Settings::get( 'sitemap_taxonomies', 1 ) ) {
			$taxonomies = get_taxonomies( [ 'public' => true ], 'names' );
			foreach ( $taxonomies as $tax ) {
				$terms = get_terms( [ 'taxonomy' => $tax, 'hide_empty' => true ] );
				if ( is_wp_error( $terms ) ) {
					continue;
				}
				foreach ( $terms as $term ) {
					$link = get_term_link( $term );
					if ( ! is_wp_error( $link ) && $link ) {
						$groups[ 'taxonomy_' . $tax ][] = [ 'url' => $link, 'lastmod' => '' ];
					}
				}
			}
		}

		if ( SFORGE_Settings::get( 'sitemap_authors', 1 ) ) {
			$authors = get_users( [ 'has_published_posts' => true, 'fields' => [ 'ID' ] ] );
			foreach ( $authors as $u ) {
				$link = get_author_posts_url( $u->ID );
				if ( $link ) {
					$groups['authors'][] = [ 'url' => $link, 'lastmod' => '' ];
				}
			}
		}

		return apply_filters( 'sforge_sitemap_groups', $groups );
	}

	protected function build_single_sitemap( $groups ) {
		$flat = [];
		foreach ( $groups as $items ) {
			foreach ( $items as $it ) {
				$flat[ $it['url'] ] = $it; // de-dupe by URL
			}
		}
		return [ 'sitemap.xml' => $this->urlset_xml( array_values( $flat ) ) ];
	}

	protected function build_split_sitemaps( $groups ) {
		$cf       = trim( (string) SFORGE_Settings::get( 'cf_pages_url', '' ), '/' );
		$base     = $cf !== '' ? $cf : trim( home_url(), '/' );
		$out      = [];
		$index    = [];

		foreach ( $groups as $key => $items ) {
			if ( empty( $items ) ) {
				continue;
			}
			$slug = sanitize_title( $key );
			$path = 'sitemap-' . $slug . '.xml';
			$out[ $path ] = $this->urlset_xml( $items );
			$index[] = [
				'loc'     => $base . '/' . $path,
				'lastmod' => current_time( 'c' ),
			];
		}
		if ( empty( $index ) ) {
			return [];
		}

		$x  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$x .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
		foreach ( $index as $entry ) {
			$x .= "\t<sitemap>\n";
			$x .= "\t\t<loc>" . esc_url( $entry['loc'] ) . "</loc>\n";
			$x .= "\t\t<lastmod>" . esc_html( $entry['lastmod'] ) . "</lastmod>\n";
			$x .= "\t</sitemap>\n";
		}
		$x .= '</sitemapindex>' . "\n";

		$out['sitemap.xml'] = $x;
		return $out;
	}

	protected function urlset_xml( $items ) {
		$cf     = trim( (string) SFORGE_Settings::get( 'cf_pages_url', '' ), '/' );
		$origin = trim( home_url(), '/' );
		$home   = home_url( '/' );

		$x  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$x .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
		foreach ( $items as $it ) {
			$url     = $it['url'];
			$lastmod = $it['lastmod'] ?? '';
			$public  = ( $cf !== '' && $origin !== '' && $origin !== $cf ) ? str_replace( $origin, $cf, $url ) : $url;

			$x .= "\t<url>\n";
			$x .= "\t\t<loc>" . esc_url( $public ) . "</loc>\n";
			if ( $lastmod !== '' ) {
				$x .= "\t\t<lastmod>" . esc_html( $lastmod ) . "</lastmod>\n";
			}
			$x .= "\t\t<changefreq>weekly</changefreq>\n";
			$x .= "\t\t<priority>" . ( $url === $home ? '1.0' : '0.7' ) . "</priority>\n";
			$x .= "\t</url>\n";
		}
		$x .= '</urlset>' . "\n";
		return $x;
	}

	protected function build_robots( $primary_sitemap_path ) {
		$cf       = trim( (string) SFORGE_Settings::get( 'cf_pages_url', '' ), '/' );
		$base     = $cf !== '' ? $cf : trim( home_url(), '/' );
		$sitemap  = $primary_sitemap_path !== '' ? ( $base . '/' . $primary_sitemap_path ) : '';
		$override = trim( (string) SFORGE_Settings::get( 'robots_txt', '' ) );

		// User-customised robots.txt — keep their Allow/Disallow rules as-is,
		// but always replace any Sitemap: lines with the path the plugin is
		// actually deploying (sitemap.xml vs sitemap_index.xml vs wp-sitemap.xml etc.).
		if ( $override !== '' ) {
			if ( $sitemap !== '' ) {
				// Strip every existing Sitemap: directive (case-insensitive) so we
				// don't leave stale paths behind.
				$override = preg_replace( '/^[ \t]*Sitemap:[ \t]*\S+[ \t]*\r?\n?/mi', '', $override );
				$override = rtrim( $override );
				$override .= "\n\nSitemap: " . $sitemap;
			}
			return $override . "\n";
		}

		// Default auto-generated robots.txt.
		$lines   = [];
		$lines[] = 'User-agent: *';
		$lines[] = 'Allow: /';
		$lines[] = '';
		if ( $sitemap !== '' ) {
			$lines[] = 'Sitemap: ' . $sitemap;
		}
		$lines[] = '';
		$lines[] = '# Generated by StaticForge for Cloudflare Pages';
		return implode( "\n", $lines );
	}

	public static function preview_default_robots() {
		$cf = trim( (string) SFORGE_Settings::get( 'cf_pages_url', '' ), '/' );
		$base = $cf !== '' ? $cf : trim( home_url(), '/' );
		return "User-agent: *\nAllow: /\n\nSitemap: {$base}/sitemap.xml\n\n# Generated by StaticForge for Cloudflare Pages";
	}

	/**
	 * Try common sitemap locations; for sitemap-index responses, follow child sitemap URLs.
	 */
	protected function discover_sitemaps() {
		$candidates = [
			home_url( '/sitemap.xml' ),
			home_url( '/sitemap_index.xml' ),
			home_url( '/wp-sitemap.xml' ),
		];
		$candidates = apply_filters( 'sforge_sitemap_candidates', $candidates );

		$reachable = [];
		foreach ( $candidates as $url ) {
			$body = $this->fetch( $url );
			if ( $body === '' ) {
				continue;
			}
			if ( ! $this->looks_like_xml( $body ) ) {
				continue;
			}
			$reachable[ $url ] = $body;
		}

		if ( empty( $reachable ) ) {
			return [];
		}

		// Pick reachable entries; expand sitemap-index references into child sitemaps.
		// Handles both plain <loc>URL</loc> and CDATA-wrapped <loc><![CDATA[URL]]></loc>.
		$expanded = [];
		foreach ( $reachable as $url => $body ) {
			$expanded[ $url ] = $body;
			if ( preg_match_all( '#<loc>(.*?)</loc>#is', $body, $m ) ) {
				foreach ( $m[1] as $raw ) {
					$child = trim( $raw );
					// Strip CDATA wrapper if present.
					if ( preg_match( '#^<!\[CDATA\[(.+?)\]\]>$#s', $child, $cm ) ) {
						$child = trim( $cm[1] );
					}
					if ( $child === '' || substr( $child, -4 ) !== '.xml' ) {
						continue;
					}
					if ( ! isset( $expanded[ $child ] ) ) {
						$expanded[ $child ] = $this->fetch( $child );
					}
				}
			}
		}
		return $expanded;
	}

	protected function fetch( $url ) {
		$resp = wp_remote_get( $url, [
			'timeout'     => 30,
			'sslverify'   => apply_filters( 'sforge_sslverify', true ),
			'redirection' => 5,
			'user-agent'  => 'SendStaticToPages/' . SFORGE_VERSION,
		] );
		if ( is_wp_error( $resp ) ) {
			return '';
		}
		if ( wp_remote_retrieve_response_code( $resp ) !== 200 ) {
			return '';
		}
		return (string) wp_remote_retrieve_body( $resp );
	}

	protected function looks_like_xml( $body ) {
		$head = ltrim( substr( $body, 0, 200 ) );
		if ( $head === '' ) {
			return false;
		}
		return ( strpos( $head, '<?xml' ) === 0 ) || ( strpos( $head, '<urlset' ) !== false ) || ( strpos( $head, '<sitemapindex' ) !== false );
	}

	protected function rewrite_xml( $xml ) {
		/*
		 * Strip XML stylesheet processing instructions. Their href usually points to
		 * a /wp-content/ asset that lives only on the origin and would leak the
		 * dashboard host into the public sitemap. Search engines ignore XSL anyway;
		 * only browsers viewing the file directly render it.
		 *
		 * Note: block comments are used here because line comments ending with the
		 * PHP close tag inside them would terminate the PHP block prematurely.
		 */
		$xml = preg_replace( '#<\?xml-stylesheet\b[^?]*\?>#i', '', (string) $xml );

		$cf = trim( (string) SFORGE_Settings::get( 'cf_pages_url', '' ), '/' );
		$origin = trim( home_url(), '/' );
		if ( $cf === '' || $origin === '' || $origin === $cf ) {
			return $xml;
		}

		// Replace full origin URLs (https://host or http://host).
		$xml = str_replace( $origin, $cf, $xml );

		// Replace protocol-relative variants (//host) so legacy or third-party
		// SEO plugins that emit `//dashboard.example.com/...` get rewritten too.
		$origin_host = wp_parse_url( $origin, PHP_URL_HOST );
		$cf_host     = wp_parse_url( $cf,     PHP_URL_HOST );
		if ( $origin_host && $cf_host && $origin_host !== $cf_host ) {
			$xml = str_replace( '//' . $origin_host, '//' . $cf_host, $xml );
		}
		return $xml;
	}
}
