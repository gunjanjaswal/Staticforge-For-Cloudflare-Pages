<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Injects baseline SEO metadata into wp_head so the plugin's exported HTML
 * always carries:
 *   - <meta name="description">
 *   - <meta name="robots"> (index, follow)
 *   - <link rel="canonical">
 *   - Open Graph tags (og:type, og:title, og:description, og:url, og:image,
 *                     og:site_name, og:locale, article:* / profile:*)
 *   - Twitter Card tags
 *   - JSON-LD: WebSite + SearchAction (front), Article / WebPage,
 *              Person + ProfilePage (author archives), CollectionPage
 *              (taxonomy archives), BreadcrumbList on all singulars/archives.
 *
 * Auto-disables itself when a major SEO plugin is active (Yoast, Rank Math,
 * AIO SEO, SEOPress, The SEO Framework). Toggle + override via settings.
 */
class SFORGE_Seo_Injector {

	public function __construct() {
		add_action( 'wp_head', [ $this, 'maybe_output' ], 1 );
	}

	public function maybe_output() {
		if ( ! (int) SFORGE_Settings::get( 'seo_inject', 1 ) ) {
			return;
		}
		if ( is_feed() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		$force = (int) SFORGE_Settings::get( 'seo_inject_force', 0 );
		// General-purpose SEO plugin handles meta+og+schema entirely → skip everything (unless forced).
		if ( $this->general_seo_plugin_active() && ! $force ) {
			return;
		}
		if ( ! apply_filters( 'sforge_seo_inject', true ) ) {
			return;
		}

		echo "\n<!-- StaticForge for Cloudflare Pages: SEO -->\n";
		$this->emit_robots();
		$this->emit_description();
		$this->emit_canonical();
		$this->emit_open_graph();
		$this->emit_twitter_card();

		// Schema-only plugin handles JSON-LD → skip just the schema block (still emit meta + og).
		$skip_schema = $this->schema_plugin_active() && ! $force;
		if ( ! $skip_schema && apply_filters( 'sforge_seo_inject_schema', true ) ) {
			$this->emit_json_ld();
		}
		echo "<!-- /StaticForge for Cloudflare Pages: SEO -->\n";
	}

	/**
	 * Backwards-compat alias retained so existing settings UI / docs keep working.
	 * Returns true if either a general SEO plugin OR a schema-only plugin is active.
	 */
	public function competing_plugin_active() {
		return $this->general_seo_plugin_active() || $this->schema_plugin_active();
	}

	/**
	 * Detect plugins that emit the FULL SEO stack (meta + Open Graph + Twitter Card + JSON-LD).
	 * When one of these is active we skip all our injection by default.
	 */
	public function general_seo_plugin_active() {
		$detected =
			   defined( 'WPSEO_VERSION' )                          // Yoast SEO
			|| class_exists( 'WPSEO_Frontend' )                    // Yoast (older)
			|| class_exists( 'RankMath' )                          // Rank Math
			|| class_exists( 'RankMath\\Helper' )                  // Rank Math (newer)
			|| function_exists( 'rank_math' )
			|| defined( 'AIOSEO_VERSION' )                         // All in One SEO Pack v4+
			|| class_exists( 'AIOSEOP_Plugin' )                    // All in One SEO Pack legacy
			|| defined( 'SEOPRESS_VERSION' )                       // SEOPress
			|| class_exists( '\\The_SEO_Framework\\Load' )         // The SEO Framework
			|| defined( 'SLIM_SEO_VERSION' )                       // Slim SEO
			|| defined( 'SQUIRRLY_PLUGIN_VERSION' )                // Squirrly SEO
			|| class_exists( 'SmartCrawl_SEO_Init' )               // SmartCrawl
			|| class_exists( 'WP_Meta_SEO' )                       // WP Meta SEO
			|| defined( 'WPSEO_PREMIUM_FILE' );                    // Yoast Premium
		return (bool) apply_filters( 'sforge_seo_competing_plugin', $detected );
	}

	/**
	 * Detect plugins whose primary job is JSON-LD / structured data only.
	 * When one of these is active we suppress only our schema block — meta + og still emit.
	 */
	public function schema_plugin_active() {
		$detected =
			   defined( 'SAS_PLUGIN_VERSION' )                     // Schema & Structured Data for WP & AMP (saswp)
			|| class_exists( 'Saswp_Schema_Output' )
			|| function_exists( 'saswp_get_settings' )
			|| defined( 'SCHEMA_PRO_FILE' )                        // Schema Pro by Brainstorm Force
			|| class_exists( 'BSF_AIO_Schema' )
			|| defined( 'WPSSO_VERSION' )                          // WPSSO Core (does both, treat conservative)
			|| class_exists( 'WpssoCorePlugin' )
			|| defined( 'SCHEMA_WP_FILE' )                         // Schema (by Hesham)
			|| class_exists( 'Schema_WP' )
			|| defined( 'SCHEMAAPP_VERSION' )                      // Schema App
			|| class_exists( 'Schema_App_For_WordPress' )
			|| defined( 'WPSCHEMA_VERSION' )                       // WP Schema Pro variants
			|| class_exists( 'Magazine3\\Schema' );
		return (bool) apply_filters( 'sforge_schema_competing_plugin', $detected );
	}

	/* ------------------------------------------------------------------ *
	 * Tag emitters
	 * ------------------------------------------------------------------ */

	protected function emit_robots() {
		// Always index/follow on the static deploy. The renderer also strips
		// any leftover noindex directives defensively.
		echo '<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">' . "\n";
	}

	protected function emit_description() {
		$desc = $this->get_description();
		if ( $desc !== '' ) {
			printf( '<meta name="description" content="%s">' . "\n", esc_attr( $desc ) );
		}
	}

	protected function emit_canonical() {
		$url = $this->get_canonical_url();
		if ( $url !== '' ) {
			printf( '<link rel="canonical" href="%s">' . "\n", esc_url( $url ) );
		}
	}

	protected function emit_open_graph() {
		$type   = $this->get_og_type();
		$title  = $this->get_title();
		$desc   = $this->get_description();
		$url    = $this->get_canonical_url();
		$site   = get_bloginfo( 'name' );
		$locale = str_replace( '-', '_', get_locale() );
		$image  = $this->get_image();

		printf( '<meta property="og:type" content="%s">' . "\n",        esc_attr( $type ) );
		printf( '<meta property="og:site_name" content="%s">' . "\n",   esc_attr( $site ) );
		printf( '<meta property="og:locale" content="%s">' . "\n",      esc_attr( $locale ) );
		if ( $title !== '' ) {
			printf( '<meta property="og:title" content="%s">' . "\n",   esc_attr( $title ) );
		}
		if ( $desc !== '' ) {
			printf( '<meta property="og:description" content="%s">' . "\n", esc_attr( $desc ) );
		}
		if ( $url !== '' ) {
			printf( '<meta property="og:url" content="%s">' . "\n",     esc_url( $url ) );
		}
		if ( $image['url'] ) {
			printf( '<meta property="og:image" content="%s">' . "\n",   esc_url( $image['url'] ) );
			if ( $image['width'] && $image['height'] ) {
				printf( '<meta property="og:image:width" content="%d">' . "\n",  (int) $image['width'] );
				printf( '<meta property="og:image:height" content="%d">' . "\n", (int) $image['height'] );
			}
			if ( ! empty( $image['alt'] ) ) {
				printf( '<meta property="og:image:alt" content="%s">' . "\n",    esc_attr( $image['alt'] ) );
			}
		}

		// Type-specific extras.
		if ( $type === 'article' && is_singular( [ 'post' ] ) ) {
			$post = get_post();
			if ( $post ) {
				printf( '<meta property="article:published_time" content="%s">' . "\n", esc_attr( get_the_date( 'c', $post ) ) );
				printf( '<meta property="article:modified_time" content="%s">' . "\n",  esc_attr( get_the_modified_date( 'c', $post ) ) );
				$author = get_userdata( (int) $post->post_author );
				if ( $author ) {
					printf( '<meta property="article:author" content="%s">' . "\n", esc_attr( $author->display_name ) );
				}
				$cats = get_the_category( $post->ID );
				if ( $cats && ! is_wp_error( $cats ) ) {
					foreach ( $cats as $c ) {
						printf( '<meta property="article:section" content="%s">' . "\n", esc_attr( $c->name ) );
					}
				}
				$tags = get_the_tags( $post->ID );
				if ( $tags && ! is_wp_error( $tags ) ) {
					foreach ( $tags as $t ) {
						printf( '<meta property="article:tag" content="%s">' . "\n", esc_attr( $t->name ) );
					}
				}
			}
		}

		if ( $type === 'profile' && is_author() ) {
			$user = $this->current_author();
			if ( $user ) {
				$first = get_user_meta( $user->ID, 'first_name', true );
				$last  = get_user_meta( $user->ID, 'last_name',  true );
				if ( $first ) {
					printf( '<meta property="profile:first_name" content="%s">' . "\n", esc_attr( $first ) );
				}
				if ( $last ) {
					printf( '<meta property="profile:last_name" content="%s">' . "\n",  esc_attr( $last ) );
				}
				printf( '<meta property="profile:username" content="%s">' . "\n",       esc_attr( $user->user_login ) );
			}
		}
	}

	protected function emit_twitter_card() {
		$image = $this->get_image();
		$card  = ! empty( $image['url'] ) ? 'summary_large_image' : 'summary';
		printf( '<meta name="twitter:card" content="%s">' . "\n", esc_attr( $card ) );
		printf( '<meta name="twitter:title" content="%s">' . "\n", esc_attr( $this->get_title() ) );
		$desc = $this->get_description();
		if ( $desc !== '' ) {
			printf( '<meta name="twitter:description" content="%s">' . "\n", esc_attr( $desc ) );
		}
		if ( $image['url'] ) {
			printf( '<meta name="twitter:image" content="%s">' . "\n", esc_url( $image['url'] ) );
		}
		// Optional twitter:creator from user meta.
		if ( is_singular() ) {
			$post = get_post();
			if ( $post ) {
				$twitter = get_user_meta( (int) $post->post_author, 'twitter', true );
				if ( $twitter !== '' ) {
					$handle = '@' . ltrim( trim( $twitter ), '@' );
					printf( '<meta name="twitter:creator" content="%s">' . "\n", esc_attr( $handle ) );
				}
			}
		}
	}

	protected function emit_json_ld() {
		$blocks = [];
		$blocks[] = $this->ld_website();
		$blocks[] = $this->ld_organization();

		if ( is_front_page() || is_home() ) {
			// Already covered by website + org above.
		} elseif ( is_singular( 'post' ) ) {
			$blocks[] = $this->ld_article();
			$blocks[] = $this->ld_breadcrumbs();
			$blocks[] = $this->ld_faq();
			$blocks[] = $this->ld_howto();
		} elseif ( is_singular() ) {
			$blocks[] = $this->ld_webpage();
			$blocks[] = $this->ld_breadcrumbs();
			$blocks[] = $this->ld_faq();
			$blocks[] = $this->ld_howto();
		} elseif ( is_author() ) {
			$blocks[] = $this->ld_person_and_profile();
			$blocks[] = $this->ld_breadcrumbs();
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$blocks[] = $this->ld_collection_page();
			$blocks[] = $this->ld_breadcrumbs();
		} elseif ( is_archive() ) {
			$blocks[] = $this->ld_collection_page();
			$blocks[] = $this->ld_breadcrumbs();
		}

		$blocks = array_values( array_filter( $blocks ) );
		foreach ( $blocks as $b ) {
			// JSON_HEX_TAG escapes `<` / `>` to `<` / `>` so user-controlled
			// content inside the JSON-LD graph cannot break out of the <script> tag.
			$json = wp_json_encode( $b, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			if ( false === $json ) {
				continue;
			}
			echo '<script type="application/ld+json">' . $json . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $json is JSON_HEX_TAG-encoded so `<`/`>` cannot appear literally; safe in a <script> body.
		}
	}

	/* ------------------------------------------------------------------ *
	 * JSON-LD builders
	 * ------------------------------------------------------------------ */

	protected function ld_website() {
		$home = home_url( '/' );
		$site = get_bloginfo( 'name' );
		return [
			'@context'        => 'https://schema.org',
			'@type'           => 'WebSite',
			'@id'             => $home . '#website',
			'url'             => $home,
			'name'            => $site,
			'description'     => get_bloginfo( 'description' ),
			'inLanguage'      => get_bloginfo( 'language' ),
			'potentialAction' => [
				'@type'       => 'SearchAction',
				'target'      => [
					'@type'       => 'EntryPoint',
					'urlTemplate' => $home . '?s={search_term_string}',
				],
				'query-input' => 'required name=search_term_string',
			],
		];
	}

	protected function ld_organization() {
		$home = home_url( '/' );
		$logo = $this->site_logo_url();
		$org  = [
			'@context'   => 'https://schema.org',
			'@type'      => 'Organization',
			'@id'        => $home . '#organization',
			'name'       => get_bloginfo( 'name' ),
			'url'        => $home,
			'inLanguage' => get_bloginfo( 'language' ),
		];
		if ( $logo ) {
			$org['logo'] = [
				'@type' => 'ImageObject',
				'url'   => $logo,
			];
		}
		return $org;
	}

	protected function ld_article() {
		$post = get_post();
		if ( ! $post ) {
			return null;
		}
		$author = get_userdata( (int) $post->post_author );
		$image  = $this->get_image();
		$home   = home_url( '/' );
		$canon  = $this->get_canonical_url();

		$data = [
			'@context'         => 'https://schema.org',
			'@type'            => 'Article',
			'@id'              => $canon . '#article',
			'mainEntityOfPage' => $canon,
			'headline'         => $this->get_title(),
			'description'      => $this->get_description(),
			'datePublished'    => get_the_date( 'c', $post ),
			'dateModified'     => get_the_modified_date( 'c', $post ),
			'inLanguage'       => get_bloginfo( 'language' ),
			'isPartOf'         => [ '@id' => $home . '#website' ],
			'publisher'        => [ '@id' => $home . '#organization' ],
		];
		if ( $author ) {
			$data['author'] = [
				'@type' => 'Person',
				'@id'   => get_author_posts_url( $author->ID ) . '#author',
				'name'  => $author->display_name,
				'url'   => get_author_posts_url( $author->ID ),
			];
		}
		if ( $image['url'] ) {
			$img = [
				'@type' => 'ImageObject',
				'url'   => $image['url'],
			];
			if ( $image['width'] )  { $img['width']  = (int) $image['width']; }
			if ( $image['height'] ) { $img['height'] = (int) $image['height']; }
			$data['image'] = $img;
		}
		$cats = get_the_category( $post->ID );
		if ( $cats && ! is_wp_error( $cats ) && ! empty( $cats ) ) {
			$data['articleSection'] = wp_list_pluck( $cats, 'name' );
		}
		$tags = get_the_tags( $post->ID );
		if ( $tags && ! is_wp_error( $tags ) && ! empty( $tags ) ) {
			$data['keywords'] = implode( ', ', wp_list_pluck( $tags, 'name' ) );
		}
		return $data;
	}

	protected function ld_webpage() {
		$canon = $this->get_canonical_url();
		$home  = home_url( '/' );
		$image = $this->get_image();
		$data  = [
			'@context'         => 'https://schema.org',
			'@type'            => 'WebPage',
			'@id'              => $canon . '#webpage',
			'url'              => $canon,
			'name'             => $this->get_title(),
			'description'      => $this->get_description(),
			'inLanguage'       => get_bloginfo( 'language' ),
			'isPartOf'         => [ '@id' => $home . '#website' ],
		];
		if ( $image['url'] ) {
			$data['primaryImageOfPage'] = [
				'@type' => 'ImageObject',
				'url'   => $image['url'],
			];
		}
		$post = get_post();
		if ( $post ) {
			$data['datePublished'] = get_the_date( 'c', $post );
			$data['dateModified']  = get_the_modified_date( 'c', $post );
		}
		return $data;
	}

	protected function ld_person_and_profile() {
		$user = $this->current_author();
		if ( ! $user ) {
			return null;
		}
		$author_url = get_author_posts_url( $user->ID );
		$home       = home_url( '/' );
		$avatar     = get_avatar_url( $user->ID, [ 'size' => 256 ] );
		$bio        = get_user_meta( $user->ID, 'description', true );

		$same_as = [];
		foreach ( [ 'user_url', 'twitter', 'facebook', 'linkedin', 'instagram', 'youtube', 'github' ] as $key ) {
			$val = $key === 'user_url' ? $user->user_url : get_user_meta( $user->ID, $key, true );
			if ( ! is_string( $val ) || $val === '' ) {
				continue;
			}
			if ( strpos( $val, 'http' ) !== 0 ) {
				// Common case: twitter handle stored as @name or name only.
				if ( $key === 'twitter' ) {
					$val = 'https://twitter.com/' . ltrim( $val, '@' );
				} elseif ( $key === 'github' ) {
					$val = 'https://github.com/' . ltrim( $val, '@' );
				} elseif ( $key === 'instagram' ) {
					$val = 'https://instagram.com/' . ltrim( $val, '@' );
				} else {
					continue;
				}
			}
			$same_as[] = $val;
		}

		$person = [
			'@context'    => 'https://schema.org',
			'@type'       => 'Person',
			'@id'         => $author_url . '#author',
			'name'        => $user->display_name,
			'url'         => $author_url,
			'description' => $bio,
		];
		if ( $avatar ) {
			$person['image'] = [
				'@type' => 'ImageObject',
				'url'   => $avatar,
				'width' => 256,
				'height'=> 256,
			];
		}
		if ( $same_as ) {
			$person['sameAs'] = array_values( array_unique( $same_as ) );
		}

		$profile = [
			'@context'   => 'https://schema.org',
			'@type'      => 'ProfilePage',
			'@id'        => $author_url . '#profilepage',
			'url'        => $author_url,
			'name'       => $user->display_name . ' — ' . get_bloginfo( 'name' ),
			'inLanguage' => get_bloginfo( 'language' ),
			'isPartOf'   => [ '@id' => $home . '#website' ],
			'mainEntity' => [ '@id' => $author_url . '#author' ],
		];

		return [ '@graph' => [ $person, $profile ] ] + [ '@context' => 'https://schema.org' ];
	}

	protected function ld_collection_page() {
		$canon = $this->get_canonical_url();
		$home  = home_url( '/' );
		return [
			'@context'   => 'https://schema.org',
			'@type'      => 'CollectionPage',
			'@id'        => $canon . '#collectionpage',
			'url'        => $canon,
			'name'       => $this->get_title(),
			'description'=> $this->get_description(),
			'inLanguage' => get_bloginfo( 'language' ),
			'isPartOf'   => [ '@id' => $home . '#website' ],
		];
	}

	/**
	 * FAQ schema — auto-generated when the post contains:
	 *   - a Yoast / Rank Math / SEOPress FAQ block, OR
	 *   - HTML5 <details><summary>Q</summary>A</details> blocks (semantic FAQ).
	 */
	protected function ld_faq() {
		$post = get_post();
		if ( ! $post ) {
			return null;
		}
		$items = $this->detect_faq_items( $post );
		$items = apply_filters( 'sforge_faq_items', $items, $post );
		if ( count( $items ) < 1 ) {
			return null;
		}
		$main = [];
		foreach ( $items as $i ) {
			$q = trim( (string) ( $i['question'] ?? '' ) );
			$a = trim( (string) ( $i['answer'] ?? '' ) );
			if ( $q === '' || $a === '' ) {
				continue;
			}
			$main[] = [
				'@type'          => 'Question',
				'name'           => $q,
				'acceptedAnswer' => [
					'@type' => 'Answer',
					'text'  => $a,
				],
			];
		}
		if ( empty( $main ) ) {
			return null;
		}
		return [
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'@id'        => $this->get_canonical_url() . '#faq',
			'mainEntity' => $main,
		];
	}

	/**
	 * HowTo schema — auto-generated when the post contains a known HowTo block
	 * (Yoast / Rank Math / SEOPress) OR the title starts with "How to" + has a
	 * numbered/ordered list with 3+ steps.
	 */
	protected function ld_howto() {
		$post = get_post();
		if ( ! $post ) {
			return null;
		}
		$howto = $this->detect_howto( $post );
		$howto = apply_filters( 'sforge_howto_data', $howto, $post );
		if ( empty( $howto['steps'] ) || count( $howto['steps'] ) < 2 ) {
			return null;
		}
		$steps = [];
		foreach ( $howto['steps'] as $i => $s ) {
			$text = is_array( $s ) ? trim( (string) ( $s['text'] ?? '' ) ) : trim( (string) $s );
			if ( $text === '' ) {
				continue;
			}
			$steps[] = [
				'@type'    => 'HowToStep',
				'position' => $i + 1,
				'name'     => is_array( $s ) && ! empty( $s['name'] ) ? wp_strip_all_tags( $s['name'] ) : 'Step ' . ( $i + 1 ),
				'text'     => wp_strip_all_tags( $text ),
			];
		}
		if ( count( $steps ) < 2 ) {
			return null;
		}
		$image = $this->get_image();
		$out   = [
			'@context'         => 'https://schema.org',
			'@type'            => 'HowTo',
			'@id'              => $this->get_canonical_url() . '#howto',
			'name'             => $howto['name'] !== '' ? $howto['name'] : $this->get_title(),
			'description'      => $this->get_description(),
			'inLanguage'       => get_bloginfo( 'language' ),
			'step'             => $steps,
		];
		if ( $image['url'] ) {
			$out['image'] = [ '@type' => 'ImageObject', 'url' => $image['url'] ];
		}
		return $out;
	}

	/**
	 * @return array list of [ 'question' => '...', 'answer' => '...' ]
	 */
	protected function detect_faq_items( $post ) {
		$faqs = [];

		if ( function_exists( 'parse_blocks' ) && has_blocks( $post->post_content ) ) {
			$walk = function ( $blocks ) use ( &$walk, &$faqs ) {
				foreach ( $blocks as $b ) {
					$name = (string) ( $b['blockName'] ?? '' );
					if ( $name === 'yoast/faq-block' ) {
						$qs = $b['attrs']['questions'] ?? [];
						foreach ( $qs as $q ) {
							$faqs[] = [
								'question' => wp_strip_all_tags( (string) ( $q['jsonQuestion'] ?? $q['question'] ?? '' ) ),
								'answer'   => wp_strip_all_tags( (string) ( $q['jsonAnswer']   ?? $q['answer']   ?? '' ) ),
							];
						}
					} elseif ( $name === 'rank-math/faq-block' ) {
						$html = (string) ( $b['innerHTML'] ?? '' );
						if ( preg_match_all( '#<strong[^>]*class="[^"]*rank-math-question[^"]*"[^>]*>(.*?)</strong>\s*<p[^>]*>(.*?)</p>#is', $html, $m, PREG_SET_ORDER ) ) {
							foreach ( $m as $row ) {
								$faqs[] = [
									'question' => wp_strip_all_tags( $row[1] ),
									'answer'   => wp_strip_all_tags( $row[2] ),
								];
							}
						}
					} elseif ( $name === 'wp-seopress/faq-block' || $name === 'seopress/faq-block' ) {
						$html = (string) ( $b['innerHTML'] ?? '' );
						if ( preg_match_all( '#<dt[^>]*>(.*?)</dt>\s*<dd[^>]*>(.*?)</dd>#is', $html, $m, PREG_SET_ORDER ) ) {
							foreach ( $m as $row ) {
								$faqs[] = [
									'question' => wp_strip_all_tags( $row[1] ),
									'answer'   => wp_strip_all_tags( $row[2] ),
								];
							}
						}
					}
					if ( ! empty( $b['innerBlocks'] ) ) {
						$walk( $b['innerBlocks'] );
					}
				}
			};
			$walk( parse_blocks( $post->post_content ) );
		}

		// Fallback: native HTML5 <details><summary> pattern.
		if ( empty( $faqs ) ) {
			$content = (string) $post->post_content;
			if ( preg_match_all( '#<details[^>]*>\s*<summary[^>]*>(.*?)</summary>(.*?)</details>#is', $content, $m, PREG_SET_ORDER ) ) {
				foreach ( $m as $row ) {
					$q = wp_strip_all_tags( $row[1] );
					$a = wp_strip_all_tags( $row[2] );
					if ( $q !== '' && $a !== '' ) {
						$faqs[] = [ 'question' => $q, 'answer' => $a ];
					}
				}
			}
		}
		return $faqs;
	}

	/**
	 * @return array{ name:string, steps: array }
	 */
	protected function detect_howto( $post ) {
		$out = [ 'name' => '', 'steps' => [] ];
		if ( function_exists( 'parse_blocks' ) && has_blocks( $post->post_content ) ) {
			$walk = function ( $blocks ) use ( &$walk, &$out ) {
				foreach ( $blocks as $b ) {
					$name = (string) ( $b['blockName'] ?? '' );
					if ( $name === 'yoast/how-to-block' ) {
						$attrs = $b['attrs'] ?? [];
						$out['name'] = wp_strip_all_tags( (string) ( $attrs['jsonTitle'] ?? '' ) );
						foreach ( (array) ( $attrs['steps'] ?? [] ) as $s ) {
							$out['steps'][] = [
								'name' => wp_strip_all_tags( (string) ( $s['jsonName'] ?? '' ) ),
								'text' => wp_strip_all_tags( (string) ( $s['jsonText'] ?? '' ) ),
							];
						}
					} elseif ( $name === 'rank-math/howto-block' ) {
						$html = (string) ( $b['innerHTML'] ?? '' );
						if ( preg_match_all( '#<div[^>]*class="[^"]*rank-math-step[^"]*"[^>]*>(.*?)</div>#is', $html, $m ) ) {
							foreach ( $m[1] as $step_html ) {
								$step_name = '';
								$step_text = '';
								if ( preg_match( '#<h[1-6][^>]*>(.*?)</h[1-6]>#is', $step_html, $hm ) ) {
									$step_name = wp_strip_all_tags( $hm[1] );
								}
								$step_text = wp_strip_all_tags( $step_html );
								$out['steps'][] = [ 'name' => $step_name, 'text' => $step_text ];
							}
						}
					}
					if ( ! empty( $b['innerBlocks'] ) ) {
						$walk( $b['innerBlocks'] );
					}
				}
			};
			$walk( parse_blocks( $post->post_content ) );
		}

		// Heuristic fallback: title starts with "How to" AND there's an <ol> with 3+ <li>.
		if ( empty( $out['steps'] ) ) {
			$title = (string) get_the_title( $post );
			if ( $title !== '' && preg_match( '#^how[\s-]+to\b#i', $title ) ) {
				if ( preg_match( '#<ol[^>]*>(.*?)</ol>#is', (string) $post->post_content, $om ) ) {
					if ( preg_match_all( '#<li[^>]*>(.*?)</li>#is', $om[1], $lm ) && count( $lm[1] ) >= 3 ) {
						$out['name'] = $title;
						foreach ( $lm[1] as $li ) {
							$out['steps'][] = [ 'name' => '', 'text' => wp_strip_all_tags( $li ) ];
						}
					}
				}
			}
		}
		return $out;
	}

	protected function ld_breadcrumbs() {
		$items = $this->breadcrumb_items();
		if ( count( $items ) < 2 ) {
			return null;
		}
		$list = [];
		foreach ( $items as $i => $item ) {
			$list[] = [
				'@type'    => 'ListItem',
				'position' => $i + 1,
				'name'     => $item['name'],
				'item'     => $item['url'],
			];
		}
		return [
			'@context'        => 'https://schema.org',
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $list,
		];
	}

	protected function breadcrumb_items() {
		$crumbs = [
			[ 'name' => __( 'Home', 'staticforge-for-cloudflare-pages' ), 'url' => home_url( '/' ) ],
		];
		if ( is_singular( 'post' ) ) {
			$cats = get_the_category();
			if ( $cats && ! is_wp_error( $cats ) ) {
				$primary = $cats[0];
				$crumbs[] = [
					'name' => $primary->name,
					'url'  => get_category_link( $primary ),
				];
			}
			$crumbs[] = [
				'name' => get_the_title(),
				'url'  => $this->get_canonical_url(),
			];
		} elseif ( is_singular() ) {
			$crumbs[] = [
				'name' => get_the_title(),
				'url'  => $this->get_canonical_url(),
			];
		} elseif ( is_author() ) {
			$user = $this->current_author();
			$crumbs[] = [
				'name' => __( 'Authors', 'staticforge-for-cloudflare-pages' ),
				'url'  => home_url( '/' ),
			];
			if ( $user ) {
				$crumbs[] = [
					'name' => $user->display_name,
					'url'  => get_author_posts_url( $user->ID ),
				];
			}
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term && isset( $term->name ) ) {
				$crumbs[] = [
					'name' => $term->name,
					'url'  => $this->get_canonical_url(),
				];
			}
		}
		return $crumbs;
	}

	/* ------------------------------------------------------------------ *
	 * Helpers
	 * ------------------------------------------------------------------ */

	protected function get_title() {
		if ( is_singular() ) {
			$t = get_the_title();
		} elseif ( is_author() ) {
			$user = $this->current_author();
			$t = $user ? $user->display_name : '';
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$t = single_term_title( '', false );
		} elseif ( is_post_type_archive() ) {
			$t = post_type_archive_title( '', false );
		} else {
			$t = get_bloginfo( 'name' );
		}
		$t = wp_strip_all_tags( (string) $t );
		return trim( $t );
	}

	protected function get_description() {
		$desc = '';
		if ( is_singular() ) {
			$post = get_post();
			if ( $post ) {
				if ( $post->post_excerpt ) {
					$desc = $post->post_excerpt;
				} else {
					$desc = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
				}
			}
		} elseif ( is_author() ) {
			$user = $this->current_author();
			if ( $user ) {
				$desc = (string) get_user_meta( $user->ID, 'description', true );
			}
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term && isset( $term->description ) ) {
				$desc = $term->description;
			}
		}
		if ( $desc === '' ) {
			$desc = (string) get_bloginfo( 'description' );
		}
		$desc = wp_strip_all_tags( (string) $desc );
		$desc = preg_replace( '/\s+/', ' ', $desc );
		$desc = trim( $desc );
		if ( strlen( $desc ) > 300 ) {
			$desc = mb_substr( $desc, 0, 297 ) . '…';
		}
		return $desc;
	}

	protected function get_canonical_url() {
		$url = function_exists( 'wp_get_canonical_url' ) ? wp_get_canonical_url() : '';
		if ( ! $url ) {
			if ( is_singular() ) {
				$url = (string) get_permalink();
			} elseif ( is_author() ) {
				$user = $this->current_author();
				$url  = $user ? get_author_posts_url( $user->ID ) : '';
			} elseif ( is_category() || is_tag() || is_tax() ) {
				$term = get_queried_object();
				if ( $term ) {
					$link = get_term_link( $term );
					if ( ! is_wp_error( $link ) ) {
						$url = $link;
					}
				}
			} elseif ( is_post_type_archive() ) {
				$pt  = get_query_var( 'post_type' );
				$url = (string) get_post_type_archive_link( is_array( $pt ) ? reset( $pt ) : $pt );
			} else {
				$url = home_url( '/' );
			}
		}
		return (string) $url;
	}

	protected function get_og_type() {
		if ( is_singular( 'post' ) ) {
			return 'article';
		}
		if ( is_author() ) {
			return 'profile';
		}
		return 'website';
	}

	protected function get_image() {
		$out = [ 'url' => '', 'width' => 0, 'height' => 0, 'alt' => '' ];
		if ( is_singular() && has_post_thumbnail() ) {
			$id  = get_post_thumbnail_id();
			$src = wp_get_attachment_image_src( $id, 'full' );
			if ( $src && ! empty( $src[0] ) ) {
				$out['url']    = $src[0];
				$out['width']  = (int) ( $src[1] ?? 0 );
				$out['height'] = (int) ( $src[2] ?? 0 );
				$out['alt']    = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
				return $out;
			}
		}
		if ( is_author() ) {
			$user = $this->current_author();
			if ( $user ) {
				$out['url']    = get_avatar_url( $user->ID, [ 'size' => 512 ] );
				$out['width']  = 512;
				$out['height'] = 512;
				$out['alt']    = $user->display_name;
				return $out;
			}
		}
		$logo = $this->site_logo_url();
		if ( $logo ) {
			$out['url'] = $logo;
		}
		return $out;
	}

	protected function site_logo_url() {
		$logo_id = (int) get_option( 'site_logo' );
		if ( ! $logo_id ) {
			$logo_id = (int) get_theme_mod( 'custom_logo' );
		}
		if ( $logo_id ) {
			$src = wp_get_attachment_image_src( $logo_id, 'full' );
			if ( $src && ! empty( $src[0] ) ) {
				return $src[0];
			}
		}
		$icon = (int) get_option( 'site_icon' );
		if ( $icon ) {
			$src = wp_get_attachment_image_src( $icon, 'full' );
			if ( $src && ! empty( $src[0] ) ) {
				return $src[0];
			}
		}
		return '';
	}

	protected function current_author() {
		if ( ! is_author() ) {
			return null;
		}
		$obj = get_queried_object();
		if ( ! $obj || ! isset( $obj->ID ) ) {
			return null;
		}
		return $obj;
	}
}
