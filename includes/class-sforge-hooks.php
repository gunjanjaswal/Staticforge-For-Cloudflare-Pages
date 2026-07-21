<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Watches content changes and queues the rebuild that covers them.
 *
 * The work itself lives in SFORGE_Rebuild; this class only decides *what*
 * changed and hands over the affected post IDs.
 */
class SFORGE_Hooks {

	public function __construct() {
		add_action( 'transition_post_status', [ $this, 'on_transition' ], 10, 3 );
		add_action( 'before_delete_post',     [ $this, 'on_delete' ], 10, 1 );
		add_action( 'wp_trash_post',          [ $this, 'on_delete' ], 10, 1 );

		add_action( SFORGE_Rebuild::HOOK_FULL,    [ $this, 'run_full_rebuild' ] );
		add_action( SFORGE_Rebuild::HOOK_PARTIAL, [ $this, 'run_partial_rebuild' ] );
	}

	public function on_transition( $new, $old, $post ) {
		if ( ! SFORGE_Settings::get( 'auto_deploy' ) ) {
			return;
		}
		if ( ! is_object( $post ) || empty( $post->ID ) ) {
			return;
		}
		if ( wp_is_post_revision( $post->ID ) || wp_is_post_autosave( $post->ID ) ) {
			return;
		}
		$allowed = (array) SFORGE_Settings::get( 'post_types', [] );
		if ( ! in_array( $post->post_type, $allowed, true ) ) {
			return;
		}
		// Trigger when transitioning to publish, or any change to an already-published post.
		if ( $new !== 'publish' && $old !== 'publish' ) {
			return;
		}
		SFORGE_Rebuild::schedule(
			SFORGE_Rebuild::MODE_PARTIAL,
			[ $post->ID ],
			"Post {$post->ID} ({$post->post_type}) {$old} -> {$new}"
		);
	}

	public function on_delete( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}
		$allowed = (array) SFORGE_Settings::get( 'post_types', [] );
		if ( ! in_array( $post->post_type, $allowed, true ) ) {
			return;
		}
		// Both hooks fire before the post is actually removed, so its permalink,
		// terms and author are still readable when the dirty set is worked out. The
		// post itself drops out of the export list by the time the rebuild runs, and
		// the prune step deletes its exported page.
		SFORGE_Rebuild::schedule(
			SFORGE_Rebuild::MODE_PARTIAL,
			[ $post_id ],
			"Post {$post_id} deleted/trashed"
		);
	}

	public function run_full_rebuild() {
		SFORGE_Rebuild::run_full();
	}

	public function run_partial_rebuild() {
		SFORGE_Rebuild::run_partial();
	}
}
