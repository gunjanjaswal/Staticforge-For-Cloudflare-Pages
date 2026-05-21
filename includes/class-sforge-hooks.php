<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SFORGE_Hooks {

	public function __construct() {
		add_action( 'transition_post_status', [ $this, 'on_transition' ], 10, 3 );
		add_action( 'before_delete_post',     [ $this, 'on_delete' ], 10, 1 );
		add_action( 'wp_trash_post',          [ $this, 'on_delete' ], 10, 1 );

		add_action( 'sforge_full_rebuild', [ $this, 'run_full_rebuild' ] );
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
		$this->queue_rebuild( "Post {$post->ID} ({$post->post_type}) {$old} -> {$new}" );
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
		$this->queue_rebuild( "Post {$post_id} deleted/trashed" );
	}

	protected function queue_rebuild( $reason ) {
		$debounce = max( 10, (int) SFORGE_Settings::get( 'debounce', 60 ) );
		// Replace any pending rebuild so rapid edits coalesce into one deploy.
		wp_clear_scheduled_hook( 'sforge_full_rebuild' );
		wp_schedule_single_event( time() + $debounce, 'sforge_full_rebuild' );
		SFORGE_Logger::log( "Rebuild queued in {$debounce}s — {$reason}" );
	}

	public function run_full_rebuild() {
		// Long-running export needs more time & memory than PHP defaults; rebuild may render hundreds of pages.
		// phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged
		@set_time_limit( 0 );
		// phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged,WordPress.PHP.IniSet.Risky
		@ini_set( 'memory_limit', '512M' );
		SFORGE_Logger::log( 'Full rebuild started.' );

		$crawler  = new SFORGE_Crawler();
		$renderer = new SFORGE_Renderer();
		$deployer = new SFORGE_Deployer();

		$urls = $crawler->build_url_list();
		if ( empty( $urls ) ) {
			SFORGE_Logger::log( 'No URLs to export. Check post type / scope settings.', 'warn' );
			return;
		}
		$total = count( $urls );
		SFORGE_Logger::log( sprintf( 'Crawling %d URLs...', $total ) );

		$upload  = wp_upload_dir();
		$basedir = trailingslashit( $upload['basedir'] ) . trim( (string) SFORGE_Settings::get( 'export_dir', 'sforge-export' ), '/\\' );

		$files      = [];
		$ok_count   = 0;
		$fail_count = 0;
		$progress_step = max( 1, (int) round( $total / 10 ) );

		foreach ( $urls as $i => $url ) {
			$html = $renderer->render_url( $url );
			if ( is_wp_error( $html ) ) {
				$fail_count++;
				SFORGE_Logger::log( 'Render fail ' . esc_url( $url ) . ': ' . $html->get_error_message(), 'warn' );
				continue;
			}
			$rel = $renderer->url_to_path( $url );
			$files[ $rel ] = $html;

			$full = trailingslashit( $basedir ) . $rel;
			wp_mkdir_p( dirname( $full ) );
			@file_put_contents( $full, $html );
			$ok_count++;

			if ( ( ( $i + 1 ) % $progress_step ) === 0 ) {
				SFORGE_Logger::log( sprintf( 'Render progress: %d / %d (%d%%)', $i + 1, $total, (int) round( ( $i + 1 ) * 100 / $total ) ) );
			}
		}

		SFORGE_Logger::log( "Render complete: {$ok_count} ok, {$fail_count} failed." );

		// Bundle /wp-content/uploads/* into the deploy (opt-in setting). Renderer
		// collected the URLs while it rewrote each page; fetch each one now and
		// add it to the deploy map under its original relative path so the static
		// host serves it directly (no proxy / origin dependency at runtime).
		if ( (int) SFORGE_Settings::get( 'bundle_uploads', 0 ) && ! (int) SFORGE_Settings::get( 'rewrite_wpcontent', 0 ) ) {
			$uploads = $renderer->get_collected_uploads();
			if ( ! empty( $uploads ) ) {
				$bundler = new SFORGE_Assets_Bundler();
				$blobs   = $bundler->fetch( $uploads );
				foreach ( $blobs as $rel => $body ) {
					$files[ $rel ] = $body;
					$full = trailingslashit( $basedir ) . $rel;
					wp_mkdir_p( dirname( $full ) );
					@file_put_contents( $full, $body );
				}
			} else {
				SFORGE_Logger::log( 'Bundle uploads enabled, but no /wp-content/uploads/ references found in rendered HTML.' );
			}
		}

		// SEO support files (robots.txt + sitemaps mirrored from origin, OR a
		// self-generated sitemap.xml built from $urls when origin has none).
		$seo  = new SFORGE_Seo();
		$seo_files = $seo->collect( $urls );
		foreach ( $seo_files as $path => $content ) {
			$files[ $path ] = $content;
			$full = trailingslashit( $basedir ) . $path;
			wp_mkdir_p( dirname( $full ) );
			@file_put_contents( $full, $content );
		}
		if ( ! empty( $seo_files ) ) {
			SFORGE_Logger::log( 'SEO files: ' . implode( ', ', array_keys( $seo_files ) ) );
		}

		// pages.dev → canonical-host redirect is handled by a small JS snippet
		// injected into every page by SFORGE_Renderer::inject_pages_dev_redirect().
		// Server-side approaches (`functions/_middleware.js`, `_worker.js` advanced
		// mode) do NOT work via the Direct Upload API — the files land as static
		// assets and never execute. Client-side redirect is the only deploy-time-only
		// path that actually fires.

		if ( empty( $files ) ) {
			SFORGE_Logger::log( 'Nothing rendered, deploy skipped.', 'warn' );
			return;
		}

		$result = $deployer->deploy( $files );
		if ( is_wp_error( $result ) ) {
			SFORGE_Logger::log( 'Deploy FAIL: ' . $result->get_error_message(), 'error' );
			return;
		}

		$deploy_url = $result['url'] ?? '';
		$deploy_id  = $result['id']  ?? '';
		SFORGE_Logger::log( sprintf(
			'Deploy OK [%s] <a href="%s" target="_blank" rel="noopener">%s</a>',
			esc_html( $deploy_id ),
			esc_url( $deploy_url ),
			esc_html( $deploy_url )
		) );
	}
}
