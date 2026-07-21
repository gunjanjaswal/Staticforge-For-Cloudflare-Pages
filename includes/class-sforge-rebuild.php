<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rebuild orchestration.
 *
 * Two modes, one pipeline. A full rebuild re-renders every URL in the export
 * list; a partial rebuild re-renders only the pages an edit actually
 * invalidated and leaves the rest of the export mirror untouched. Both then
 * deploy the whole mirror, because a Cloudflare Pages deployment is a complete
 * site snapshot either way.
 *
 * The saving is in the render loop, which is where the wall-clock time goes:
 * each page costs one HTTP round-trip to the origin, so a 1,400-page site pays
 * 1,400 sequential requests on every publish under the old always-full model.
 * Editing one post now touches roughly half a dozen URLs instead.
 */
class SFORGE_Rebuild {

	const MODE_FULL    = 'full';
	const MODE_PARTIAL = 'partial';

	/**
	 * URLs awaiting a partial rebuild, carried across requests to the cron run.
	 *
	 * Deliberately URLs rather than post IDs. Deletion hooks fire while the post
	 * still exists, but the rebuild runs later from cron, by which point the post
	 * is gone and its terms and author are no longer reachable — so the archives
	 * that were listing it would never be re-rendered and would keep listing it.
	 * Resolving to URLs at hook time captures them while they can still be read.
	 */
	const PENDING_OPT = 'sforge_pending_urls';

	const HOOK_FULL    = 'sforge_full_rebuild';
	const HOOK_PARTIAL = 'sforge_partial_rebuild';

	/**
	 * Schedule a rebuild after the debounce window, merging rapid edits into one run.
	 *
	 * @param string $mode     self::MODE_FULL or self::MODE_PARTIAL.
	 * @param array  $post_ids Posts whose edits triggered this, for partial runs.
	 * @param string $reason   Human-readable line for the activity log.
	 * @param int    $delay    Seconds to wait; defaults to the debounce setting.
	 */
	public static function schedule( $mode, array $post_ids, $reason, $delay = null ) {
		if ( ! empty( $post_ids ) ) {
			// Resolve to URLs now, while the posts are still readable — see PENDING_OPT.
			$seeds = [];
			foreach ( $post_ids as $post_id ) {
				$seeds = array_merge( $seeds, self::seeds_for_post( $post_id ) );
			}
			if ( ! empty( $seeds ) ) {
				$pending = (array) get_option( self::PENDING_OPT, [] );
				$pending = array_values( array_unique( array_merge( $pending, $seeds ) ) );
				update_option( self::PENDING_OPT, $pending, false );
			}
		}

		$delay = ( $delay === null ) ? max( 10, (int) SFORGE_Settings::get( 'debounce', 60 ) ) : max( 0, (int) $delay );

		// A pending full rebuild already covers everything a partial one would do,
		// so leave it alone rather than downgrading the queued work.
		if ( $mode === self::MODE_PARTIAL && wp_next_scheduled( self::HOOK_FULL ) ) {
			SFORGE_Logger::log( "Changes noted for the queued full rebuild — {$reason}" );
			return;
		}

		wp_clear_scheduled_hook( self::HOOK_FULL );
		wp_clear_scheduled_hook( self::HOOK_PARTIAL );
		$hook = ( $mode === self::MODE_FULL ) ? self::HOOK_FULL : self::HOOK_PARTIAL;
		wp_schedule_single_event( time() + $delay, $hook );

		$label = ( $mode === self::MODE_FULL ) ? 'Full rebuild' : 'Partial rebuild';
		SFORGE_Logger::log( "{$label} queued in {$delay}s — {$reason}" );
	}

	public static function run_full() {
		delete_option( self::PENDING_OPT );
		( new self() )->execute( self::MODE_FULL, [] );
	}

	public static function run_partial() {
		$pending = (array) get_option( self::PENDING_OPT, [] );
		delete_option( self::PENDING_OPT );
		( new self() )->execute( self::MODE_PARTIAL, $pending );
	}

	/**
	 * The URLs an edit to one post invalidates, resolved while the post is still
	 * readable. Called from the content hooks, not from the rebuild itself.
	 *
	 * Conservative about what it can know: a post's current terms are reachable,
	 * but a term it was just *removed* from is not, so that archive keeps its
	 * previous copy until the next full rebuild.
	 *
	 * @return array Absolute URLs.
	 */
	public static function seeds_for_post( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return [];
		}

		$seeds = [];

		$permalink = get_permalink( $post_id );
		if ( $permalink ) {
			$seeds[] = $permalink;
		}

		$archive = get_post_type_archive_link( $post->post_type );
		if ( $archive ) {
			$seeds[] = $archive;
		}

		if ( SFORGE_Settings::get( 'include_taxonomies' ) ) {
			foreach ( get_object_taxonomies( $post->post_type, 'names' ) as $tax ) {
				$terms = get_the_terms( $post_id, $tax );
				if ( ! is_array( $terms ) ) {
					continue;
				}
				foreach ( $terms as $term ) {
					$link = get_term_link( $term );
					if ( ! is_wp_error( $link ) && $link ) {
						$seeds[] = $link;
					}
				}
			}
		}

		if ( SFORGE_Settings::get( 'include_authors' ) && $post->post_author ) {
			$author = get_author_posts_url( $post->post_author );
			if ( $author ) {
				$seeds[] = $author;
			}
		}

		return array_values( array_unique( array_filter( $seeds ) ) );
	}

	/**
	 * @param string $mode  self::MODE_FULL or self::MODE_PARTIAL.
	 * @param array  $seeds URLs invalidated since the last deploy, partial mode only.
	 */
	public function execute( $mode, array $seeds ) {
		// Rendering is a long sequential loop of HTTP fetches; PHP defaults will cut
		// it short on any site of size. Memory is no longer proportional to the site
		// now the deploy streams from disk, but sitemap generation can still spike.
		// phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged
		@set_time_limit( 0 );
		// phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged,WordPress.PHP.IniSet.Risky
		@ini_set( 'memory_limit', '512M' );

		$full = ( $mode === self::MODE_FULL );
		SFORGE_Logger::log( $full ? 'Full rebuild started.' : 'Partial rebuild started.' );

		$store    = new SFORGE_Export_Store();
		$crawler  = new SFORGE_Crawler();
		$renderer = new SFORGE_Renderer();
		$deployer = new SFORGE_Deployer();

		// Built on every run, in both modes. It is pure database work — no HTTP — so
		// it costs almost nothing, and having the authoritative list is what lets a
		// partial run prune deleted pages and spot pages missing from the mirror.
		$urls = $crawler->build_url_list();
		if ( empty( $urls ) ) {
			SFORGE_Logger::log( 'No URLs to export. Check post type / scope settings.', 'warn' );
			return;
		}

		$expected = [];
		foreach ( $urls as $url ) {
			$expected[ $renderer->url_to_path( $url ) ] = $url;
		}

		$targets = $full ? $urls : $this->dirty_urls( $seeds, $urls );

		// A partial run also has to render any URL with no file in the mirror: first
		// run after install, or a page whose render failed last time. A full run
		// already covers every URL, so there is nothing to add.
		if ( ! $full ) {
			$missing = 0;
			foreach ( $expected as $path => $url ) {
				if ( ! $store->exists( $path ) ) {
					$targets[] = $url;
					$missing++;
				}
			}
			if ( $missing ) {
				SFORGE_Logger::log( sprintf( '%d page(s) missing from the export cache added to this rebuild.', $missing ) );
			}
		}

		$targets = array_values( array_unique( $targets ) );
		if ( empty( $targets ) ) {
			// Still worth continuing: the sitemap may have moved on, a post may have
			// been deleted, and the deploy itself is what publishes the prune.
			SFORGE_Logger::log( 'Nothing to re-render — every affected page is already current.' );
		} else {
			$this->render( $targets, count( $urls ), $store, $renderer, $full );
			// Uploads are discovered by scanning rendered HTML, so this only has
			// anything to say when pages were actually rendered.
			$this->bundle_uploads( $store, $renderer );
		}

		$this->write_seo_files( $store, $urls );

		$store->prune_html( array_keys( $expected ), $full );

		// pages.dev → canonical-host redirect is handled by a small JS snippet
		// injected into every page by SFORGE_Renderer::inject_pages_dev_redirect().
		// Server-side approaches (`functions/_middleware.js`, `_worker.js` advanced
		// mode) do NOT work via the Direct Upload API — the files land as static
		// assets and never execute. Client-side redirect is the only deploy-time-only
		// path that actually fires.

		$result = $deployer->deploy_dir( $store );
		if ( is_wp_error( $result ) ) {
			SFORGE_Logger::log( 'Deploy FAIL: ' . $result->get_error_message(), 'error' );
			return;
		}

		SFORGE_Logger::log( sprintf(
			'Deploy OK [%s] <a href="%s" target="_blank" rel="noopener">%s</a>',
			esc_html( $result['id'] ?? '' ),
			esc_url( $result['url'] ?? '' ),
			esc_html( $result['url'] ?? '' )
		) );
	}

	/**
	 * Fetch each target URL and write the rendered HTML into the mirror.
	 */
	protected function render( array $targets, $site_total, SFORGE_Export_Store $store, SFORGE_Renderer $renderer, $full ) {
		$total = count( $targets );
		if ( $full ) {
			SFORGE_Logger::log( sprintf( 'Crawling %d URLs...', $total ) );
		} else {
			SFORGE_Logger::log( sprintf( 'Rendering %d of %d pages — the rest are reused from the export cache.', $total, $site_total ) );
		}

		$ok   = 0;
		$fail = 0;
		$step = max( 1, (int) round( $total / 10 ) );

		foreach ( $targets as $i => $url ) {
			$html = $renderer->render_url( $url );
			if ( is_wp_error( $html ) ) {
				$fail++;
				// The previously exported copy stays in the mirror, so a transient
				// failure serves a stale page rather than a missing one.
				SFORGE_Logger::log( 'Render fail ' . esc_url( $url ) . ': ' . $html->get_error_message(), 'warn' );
				continue;
			}
			if ( $store->write( $renderer->url_to_path( $url ), $html ) ) {
				$ok++;
			} else {
				$fail++;
				SFORGE_Logger::log( 'Could not write export file for ' . esc_url( $url ), 'warn' );
			}
			unset( $html );

			if ( ( ( $i + 1 ) % $step ) === 0 ) {
				SFORGE_Logger::log( sprintf( 'Render progress: %d / %d (%d%%)', $i + 1, $total, (int) round( ( $i + 1 ) * 100 / $total ) ) );
			}
		}

		SFORGE_Logger::log( "Render complete: {$ok} ok, {$fail} failed." );
	}

	/**
	 * Turn the seed URLs collected at edit time into the render list for this run.
	 *
	 * @param array $seeds    URLs from seeds_for_post(), gathered since the last deploy.
	 * @param array $all_urls The authoritative export list to intersect against.
	 */
	protected function dirty_urls( array $seeds, array $all_urls ) {
		// Any change to any post can reorder the front page and the posts page, so
		// they are dirty on every partial run regardless of what was edited.
		if ( SFORGE_Settings::get( 'include_homepage' ) ) {
			$seeds[] = home_url( '/' );
			$blog_id = (int) get_option( 'page_for_posts' );
			if ( $blog_id ) {
				$seeds[] = get_permalink( $blog_id );
			}
		}

		$seeds = array_values( array_unique( array_filter( $seeds ) ) );
		if ( empty( $seeds ) ) {
			return [];
		}

		// Run the dirty seeds through the same filter the crawler uses, so
		// TranslatePress (and anything else hooking sforge_url_list) contributes the
		// language variants of each changed URL instead of only the default one.
		$seeds = (array) apply_filters( 'sforge_url_list', $seeds );

		// Intersect with the authoritative list. A filter that appends fixed URLs
		// runs on both lists, and this is what stops those extras leaking into a
		// partial render as pages the crawler never sanctioned.
		$allowed = array_fill_keys( $all_urls, true );
		$out     = [];
		foreach ( $seeds as $url ) {
			if ( isset( $allowed[ $url ] ) ) {
				$out[] = $url;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Fetch /wp-content/uploads/* referenced by the pages rendered this run and add
	 * them to the mirror. Files already mirrored are skipped — uploads are
	 * effectively immutable (WordPress writes a new filename on re-upload), so
	 * re-fetching them every rebuild is pure waste.
	 */
	protected function bundle_uploads( SFORGE_Export_Store $store, SFORGE_Renderer $renderer ) {
		if ( ! (int) SFORGE_Settings::get( 'bundle_uploads', 0 ) || (int) SFORGE_Settings::get( 'rewrite_wpcontent', 0 ) ) {
			return;
		}

		$uploads = $renderer->get_collected_uploads();
		if ( empty( $uploads ) ) {
			SFORGE_Logger::log( 'Bundle uploads enabled, but no /wp-content/uploads/ references found in the pages rendered this run.' );
			return;
		}

		$needed  = [];
		$cached  = 0;
		foreach ( $uploads as $rel => $url ) {
			if ( $store->exists( $rel ) ) {
				$cached++;
				continue;
			}
			$needed[ $rel ] = $url;
		}
		if ( $cached ) {
			SFORGE_Logger::log( sprintf( '%d upload(s) already in the export cache, skipped.', $cached ) );
		}
		if ( empty( $needed ) ) {
			return;
		}

		$bundler = new SFORGE_Assets_Bundler();
		foreach ( $bundler->fetch( $needed ) as $rel => $body ) {
			$store->write( $rel, $body );
		}
	}

	/**
	 * robots.txt plus mirrored or generated sitemaps. Regenerated every run — they
	 * are a handful of files and they reflect the whole site, so they go stale on
	 * any change.
	 */
	protected function write_seo_files( SFORGE_Export_Store $store, array $urls ) {
		$seo   = new SFORGE_Seo();
		$files = $seo->collect( $urls );
		foreach ( $files as $path => $content ) {
			$store->write( $path, $content );
		}
		if ( ! empty( $files ) ) {
			SFORGE_Logger::log( 'SEO files: ' . implode( ', ', array_keys( $files ) ) );
		}
	}
}
