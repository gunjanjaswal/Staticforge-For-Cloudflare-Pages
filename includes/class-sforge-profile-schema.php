<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Emits rich `Person` + `ProfilePage` JSON-LD on author archive pages, regardless of
 * whether a third-party SEO plugin is active. Yoast / Rank Math / AIO SEO usually
 * emit a thin Person node (name + url + image) without `sameAs`, `description`,
 * `jobTitle`, `worksFor`, etc. This module supplements that with a fuller graph
 * built from native WP user meta.
 *
 * Skipped when our own SFORGE_Seo_Injector is the active SEO emitter for this
 * request (it already includes the same data via ld_person_and_profile()).
 *
 * Uses a distinct `@id` suffix (`#sforge-author`, `#sforge-profilepage`) so it never
 * collides with the SEO plugin's own Person node — search engines treat them as
 * separate entities and reconcile by URL.
 */
class SFORGE_Profile_Schema {

	public function __construct() {
		if ( ! (int) SFORGE_Settings::get( 'profile_schema', 1 ) ) {
			return;
		}
		add_action( 'wp_head', [ $this, 'maybe_emit' ], 5 );
	}

	public function maybe_emit() {
		if ( ! is_author() ) {
			return;
		}
		if ( is_feed() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		// Skip when our own SEO injector is actively emitting — it already covers Person+ProfilePage.
		if ( class_exists( 'SFORGE_Seo_Injector' ) ) {
			$inj = new SFORGE_Seo_Injector();
			if ( (int) SFORGE_Settings::get( 'seo_inject', 1 )
				&& ! $inj->general_seo_plugin_active()
				&& apply_filters( 'sforge_seo_inject', true ) ) {
				return;
			}
		}
		if ( ! apply_filters( 'sforge_profile_schema_emit', true ) ) {
			return;
		}

		$user = get_queried_object();
		if ( ! $user || empty( $user->ID ) ) {
			return;
		}

		$author_url = get_author_posts_url( $user->ID );
		$home       = home_url( '/' );
		$avatar     = get_avatar_url( $user->ID, [ 'size' => 512 ] );
		$bio        = (string) get_user_meta( $user->ID, 'description', true );
		$first      = (string) get_user_meta( $user->ID, 'first_name', true );
		$last       = (string) get_user_meta( $user->ID, 'last_name', true );

		$same_as = $this->collect_same_as( $user );

		$person = [
			'@type'    => 'Person',
			'@id'      => $author_url . '#sforge-author',
			'name'     => $user->display_name,
			'url'      => $author_url,
			'mainEntityOfPage' => $author_url . '#sforge-profilepage',
		];
		if ( $first ) $person['givenName']  = $first;
		if ( $last )  $person['familyName'] = $last;
		if ( $bio !== '' ) {
			$person['description'] = wp_strip_all_tags( $bio );
		}
		if ( $avatar ) {
			$person['image'] = [
				'@type'  => 'ImageObject',
				'url'    => $avatar,
				'width'  => 512,
				'height' => 512,
				'caption'=> $user->display_name,
			];
		}
		if ( ! empty( $same_as ) ) {
			$person['sameAs'] = array_values( array_unique( $same_as ) );
		}

		// Optional: jobTitle / worksFor from user meta (set via custom profile fields).
		$job_title = (string) get_user_meta( $user->ID, 'job_title', true );
		if ( $job_title ) {
			$person['jobTitle'] = $job_title;
		}
		$company = (string) get_user_meta( $user->ID, 'company', true );
		if ( $company ) {
			$person['worksFor'] = [ '@type' => 'Organization', 'name' => $company ];
		}

		$profile = [
			'@type'      => 'ProfilePage',
			'@id'        => $author_url . '#sforge-profilepage',
			'url'        => $author_url,
			'name'       => $user->display_name . ' — ' . get_bloginfo( 'name' ),
			'inLanguage' => get_bloginfo( 'language' ),
			'isPartOf'   => [ '@id' => $home ],
			'mainEntity' => [ '@id' => $author_url . '#sforge-author' ],
		];

		$graph = [
			'@context' => 'https://schema.org',
			'@graph'   => [ $person, $profile ],
		];
		$graph = apply_filters( 'sforge_profile_schema_data', $graph, $user );

		// JSON_HEX_TAG escapes `<` and `>` to `<` / `>` so user-controlled
		// data inside the JSON-LD graph cannot break out of the <script> tag.
		$json = wp_json_encode( $graph, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false === $json ) {
			return;
		}
		echo "\n<!-- StaticForge for Cloudflare Pages: ProfilePage schema -->\n";
		echo '<script type="application/ld+json" data-sforge-profile="1">' . $json . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $json is JSON_HEX_TAG-encoded so `<`/`>` cannot appear literally; safe in a <script> body.
		echo "<!-- /StaticForge for Cloudflare Pages: ProfilePage schema -->\n";
	}

	/**
	 * Collect social profile URLs for sameAs. Supports user_url plus a wide range
	 * of common user_meta keys used by SEO/profile plugins. Bare handles are
	 * normalised to full URLs where the platform is well-known.
	 */
	protected function collect_same_as( $user ) {
		$same_as = [];

		$user_url = trim( (string) $user->user_url );
		if ( $user_url !== '' && preg_match( '#^https?://#i', $user_url ) ) {
			$same_as[] = $user_url;
		}

		$map = [
			'twitter'    => 'https://twitter.com/',
			'x'          => 'https://twitter.com/',
			'facebook'   => 'https://facebook.com/',
			'linkedin'   => 'https://www.linkedin.com/in/',
			'instagram'  => 'https://instagram.com/',
			'youtube'    => 'https://youtube.com/@',
			'github'     => 'https://github.com/',
			'pinterest'  => 'https://pinterest.com/',
			'tiktok'     => 'https://tiktok.com/@',
			'mastodon'   => '',
			'threads'    => 'https://threads.net/@',
			'bluesky'    => '',
			'medium'     => 'https://medium.com/@',
		];

		foreach ( $map as $key => $prefix ) {
			$val = (string) get_user_meta( $user->ID, $key, true );
			if ( $val === '' ) {
				continue;
			}
			$val = trim( $val );
			if ( preg_match( '#^https?://#i', $val ) ) {
				$same_as[] = $val;
			} elseif ( $prefix !== '' ) {
				$same_as[] = $prefix . ltrim( $val, '@/' );
			}
		}

		return apply_filters( 'sforge_profile_same_as', $same_as, $user );
	}
}
