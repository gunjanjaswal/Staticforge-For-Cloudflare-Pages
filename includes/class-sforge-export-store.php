<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The on-disk export mirror.
 *
 * Every rendered page, bundled upload and SEO file lands in this directory, and
 * the deploy manifest is built by reading it back. That is what makes partial
 * rebuilds possible: a Cloudflare Pages deployment is a whole-site snapshot, so
 * the manifest must list every file, but only the files whose source content
 * actually changed need re-rendering. Everything else is already sitting here
 * from the previous run.
 *
 * Because the mirror outlives any single rebuild, it also has to be pruned —
 * otherwise a deleted post's HTML stays on disk, stays in the manifest, and
 * stays live forever. See prune_html().
 */
class SFORGE_Export_Store {

	/** @var string Absolute path to the mirror root, no trailing slash. */
	protected $basedir;

	public function __construct( $basedir = null ) {
		if ( $basedir === null ) {
			$upload  = wp_upload_dir();
			$sub     = trim( (string) SFORGE_Settings::get( 'export_dir', 'sforge-export' ), '/\\' );
			$basedir = trailingslashit( $upload['basedir'] ) . $sub;
		}
		$this->basedir = untrailingslashit( $basedir );
	}

	public function basedir() {
		return $this->basedir;
	}

	/**
	 * Absolute path for a mirror-relative path, or '' if the path escapes the
	 * mirror root. Rendered paths come from url_to_path() on crawled URLs, so a
	 * hostile permalink is the one way traversal segments could reach here.
	 */
	public function path_for( $rel ) {
		$rel = ltrim( str_replace( '\\', '/', (string) $rel ), '/' );
		if ( $rel === '' ) {
			return '';
		}
		foreach ( explode( '/', $rel ) as $segment ) {
			if ( $segment === '.' || $segment === '..' ) {
				return '';
			}
		}
		return $this->basedir . '/' . $rel;
	}

	public function exists( $rel ) {
		$full = $this->path_for( $rel );
		return $full !== '' && file_exists( $full );
	}

	/**
	 * @return bool True when the bytes are on disk.
	 */
	public function write( $rel, $content ) {
		$full = $this->path_for( $rel );
		if ( $full === '' ) {
			return false;
		}
		wp_mkdir_p( dirname( $full ) );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Writing the static export mirror; WP_Filesystem adds no value for a local scratch directory written thousands of times per rebuild.
		return false !== @file_put_contents( $full, $content );
	}

	public function delete( $rel ) {
		$full = $this->path_for( $rel );
		if ( $full === '' || ! file_exists( $full ) ) {
			return false;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Local export mirror cleanup.
		return @unlink( $full );
	}

	/**
	 * Every file in the mirror as [ relative_path => absolute_path ].
	 */
	public function scan() {
		$out = [];
		if ( ! is_dir( $this->basedir ) ) {
			return $out;
		}
		$it = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $this->basedir, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::LEAVES_ONLY
		);
		$prefix = strlen( $this->basedir ) + 1;
		foreach ( $it as $file ) {
			if ( ! $file->isFile() ) {
				continue;
			}
			$abs = $file->getPathname();
			$rel = ltrim( str_replace( '\\', '/', substr( $abs, $prefix ) ), '/' );
			if ( $rel !== '' ) {
				$out[ $rel ] = $abs;
			}
		}
		ksort( $out );
		return $out;
	}

	/**
	 * Delete HTML files that the current URL list no longer accounts for, so
	 * deleted and unpublished posts stop being served.
	 *
	 * Scoped to HTML on purpose. Bundled uploads are only discovered by scanning
	 * rendered HTML, so a partial rebuild sees only the uploads referenced by the
	 * handful of pages it re-rendered — pruning against that set would wipe every
	 * other asset in the mirror.
	 *
	 * @param array $keep     Mirror-relative paths the current URL list expects.
	 * @param bool  $complete True when every URL was just re-rendered, so the keep
	 *                        set is known-complete and the size guard is redundant.
	 * @return int Number of files deleted.
	 */
	public function prune_html( array $keep, $complete = false ) {
		$keep_map = array_fill_keys( array_map( function( $p ) {
			return ltrim( str_replace( '\\', '/', (string) $p ), '/' );
		}, $keep ), true );

		$stale = [];
		$html_total = 0;
		foreach ( array_keys( $this->scan() ) as $rel ) {
			if ( ! preg_match( '#\.html?$#i', $rel ) ) {
				continue;
			}
			$html_total++;
			if ( ! isset( $keep_map[ $rel ] ) ) {
				$stale[] = $rel;
			}
		}

		if ( empty( $stale ) ) {
			return 0;
		}

		// A partial rebuild trusts a URL list it did not fully verify by rendering.
		// If that list came back short — a failed query, a plugin that filters
		// sforge_url_list and bailed, a half-loaded multilingual setup — pruning
		// would quietly delete most of the live site. Refuse, and say so.
		if ( ! $complete && $html_total > 0 && ( count( $stale ) / $html_total ) > 0.25 ) {
			SFORGE_Logger::log( sprintf(
				'Prune skipped: %d of %d exported pages looked stale, which is too much to remove on a partial rebuild. Run a full rebuild to reconcile.',
				count( $stale ), $html_total
			), 'warn' );
			return 0;
		}

		$deleted = 0;
		foreach ( $stale as $rel ) {
			if ( $this->delete( $rel ) ) {
				$deleted++;
				$this->prune_empty_dirs( dirname( $this->path_for( $rel ) ) );
			}
		}
		if ( $deleted ) {
			SFORGE_Logger::log( sprintf( 'Pruned %d page(s) no longer in the export list.', $deleted ) );
		}
		return $deleted;
	}

	/**
	 * Walk up from a just-emptied directory removing empty parents, stopping at
	 * the mirror root so the root itself always survives.
	 */
	protected function prune_empty_dirs( $dir ) {
		$root = $this->basedir;
		while ( $dir && $dir !== $root && strpos( $dir, $root . '/' ) === 0 ) {
			$entries = @scandir( $dir );
			if ( $entries === false || count( array_diff( $entries, [ '.', '..' ] ) ) > 0 ) {
				return;
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Local export mirror cleanup.
			if ( ! @rmdir( $dir ) ) {
				return;
			}
			$dir = dirname( $dir );
		}
	}
}
