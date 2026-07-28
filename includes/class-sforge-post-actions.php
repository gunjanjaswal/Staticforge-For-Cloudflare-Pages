<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A "Rebuild" row action on post list tables, for pushing one page to the live
 * site without waiting out the debounce or rebuilding anything else.
 *
 * Useful when a change did not go through the publish flow at all — an edited
 * menu, a widget, a template tweak, a translation saved in place — so nothing
 * queued a rebuild but a specific page is nonetheless out of date.
 */
class SFORGE_Post_Actions {

	const ACTION = 'sforge_rebuild_post';

	public function __construct() {
		add_filter( 'post_row_actions',      [ $this, 'row_action' ], 10, 2 );
		add_filter( 'page_row_actions',      [ $this, 'row_action' ], 10, 2 );
		add_action( 'admin_post_' . self::ACTION, [ $this, 'handle' ] );
		add_action( 'admin_notices',         [ $this, 'notice' ] );
	}

	public function row_action( $actions, $post ) {
		if ( ! is_object( $post ) || empty( $post->ID ) ) {
			return $actions;
		}
		if ( $post->post_status !== 'publish' ) {
			return $actions;
		}
		$allowed = (array) SFORGE_Settings::get( 'post_types', [] );
		if ( ! in_array( $post->post_type, $allowed, true ) ) {
			return $actions;
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}

		$url = wp_nonce_url(
			add_query_arg(
				[ 'action' => self::ACTION, 'post' => $post->ID ],
				admin_url( 'admin-post.php' )
			),
			self::ACTION . '_' . $post->ID
		);

		$actions['sforge_rebuild'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $url ),
			esc_html__( 'Rebuild', 'staticforge-for-cloudflare-pages' )
		);
		return $actions;
	}

	public function handle() {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		if ( ! $post_id ) {
			wp_die( esc_html__( 'Missing post.', 'staticforge-for-cloudflare-pages' ) );
		}
		check_admin_referer( self::ACTION . '_' . $post_id );

		$post = get_post( $post_id );
		if ( ! $post || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'Forbidden', 'staticforge-for-cloudflare-pages' ) );
		}
		$allowed = (array) SFORGE_Settings::get( 'post_types', [] );
		if ( ! in_array( $post->post_type, $allowed, true ) ) {
			wp_die( esc_html__( 'This post type is not part of the static export.', 'staticforge-for-cloudflare-pages' ) );
		}

		SFORGE_Rebuild::schedule(
			SFORGE_Rebuild::MODE_PARTIAL,
			[ $post_id ],
			sprintf( 'Manual rebuild of post %d (%s)', $post_id, $post->post_type ),
			5
		);

		$back = wp_get_referer();
		if ( ! $back ) {
			$back = admin_url( 'edit.php?post_type=' . rawurlencode( $post->post_type ) );
		}
		wp_safe_redirect( add_query_arg( 'sforge_queued', $post_id, $back ) );
		exit;
	}

	public function notice() {
		// Read-only display of a redirect flag set by handle() after its own nonce
		// check. Nothing is processed or persisted here, so there is no form data
		// to verify.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only; state change already happened behind a verified nonce.
		if ( empty( $_GET['sforge_queued'] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only; see above.
		$post_id = absint( $_GET['sforge_queued'] );
		$title   = $post_id ? get_the_title( $post_id ) : '';
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html( sprintf(
				/* translators: %s: post title */
				__( 'Rebuild queued for "%s". Watch the StaticForge activity log for the deploy.', 'staticforge-for-cloudflare-pages' ),
				$title
			) )
		);
	}
}
