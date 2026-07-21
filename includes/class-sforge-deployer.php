<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cloudflare Pages Direct Upload API client.
 *
 * Flow:
 *   1. GET  /accounts/{a}/pages/projects/{p}/upload-token   -> JWT
 *   2. POST /pages/assets/check-missing  with hashes        -> list of missing hashes
 *   3. POST /pages/assets/upload         with new assets    (batched)
 *   4. POST /accounts/{a}/pages/projects/{p}/deployments    with manifest + branch
 */
class SFORGE_Deployer {

	const API         = 'https://api.cloudflare.com/client/v4';
	const BATCH_FILES = 100;        // smaller batches => visible progress + low PHP memory.
	const BATCH_BYTES = 26214400;   // 25 MiB per batch.

	protected $jwt = null;

	public function account_id() { return trim( (string) SFORGE_Settings::get( 'account_id', '' ) ); }
	public function api_token()  { return trim( (string) SFORGE_Settings::get( 'api_token', '' ) ); }
	public function project()    { return trim( (string) SFORGE_Settings::get( 'project_name', '' ) ); }
	public function branch()     { return trim( (string) SFORGE_Settings::get( 'branch', 'main' ) ); }

	public function test_connection() {
		if ( $this->account_id() === '' || $this->api_token() === '' || $this->project() === '' ) {
			return new WP_Error( 'sforge_missing', 'Account ID, API token and project name are required.' );
		}
		$url = self::API . '/accounts/' . rawurlencode( $this->account_id() ) . '/pages/projects/' . rawurlencode( $this->project() );
		$resp = wp_remote_get( $url, [
			'timeout' => 25,
			'headers' => [ 'Authorization' => 'Bearer ' . $this->api_token() ],
		] );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$body = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( wp_remote_retrieve_response_code( $resp ) !== 200 || empty( $body['success'] ) ) {
			$err = $body['errors'][0]['message'] ?? ( 'HTTP ' . wp_remote_retrieve_response_code( $resp ) );
			return new WP_Error( 'sforge_cf_api', $err );
		}
		return $body['result'] ?? true;
	}

	protected function fetch_jwt() {
		if ( $this->jwt !== null ) {
			return $this->jwt;
		}
		$url = self::API . '/accounts/' . rawurlencode( $this->account_id() ) . '/pages/projects/' . rawurlencode( $this->project() ) . '/upload-token';
		$resp = wp_remote_get( $url, [
			'timeout' => 25,
			'headers' => [ 'Authorization' => 'Bearer ' . $this->api_token() ],
		] );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$body = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( empty( $body['success'] ) || empty( $body['result']['jwt'] ) ) {
			$msg = $body['errors'][0]['message'] ?? 'unknown';
			return new WP_Error( 'sforge_cf_jwt', 'Upload token request failed: ' . $msg );
		}
		$this->jwt = $body['result']['jwt'];
		return $this->jwt;
	}

	/**
	 * Hash key for an asset. CF Pages accepts an arbitrary 32-char hex content key
	 * as long as we use the same value across check-missing, upload, and manifest.
	 */
	public static function hash_asset( $content, $extension = '' ) {
		return substr( hash( 'sha256', $extension . "\0" . $content ), 0, 32 );
	}

	/**
	 * Same hash as hash_asset(), computed by streaming the file rather than
	 * holding it in memory. Feeding the extension prefix and then the file body
	 * into one context produces an identical digest to hashing the concatenated
	 * string, so assets already cached at Cloudflare from an earlier in-memory
	 * deploy stay cached after the switch to disk-backed deploys.
	 *
	 * @return string|false 32-char hex key, or false if the file is unreadable.
	 */
	public static function hash_file( $path, $extension = '' ) {
		$ctx = hash_init( 'sha256' );
		hash_update( $ctx, $extension . "\0" );
		if ( ! @hash_update_file( $ctx, $path ) ) {
			return false;
		}
		return substr( hash_final( $ctx ), 0, 32 );
	}

	/**
	 * Deploy a map of [ relative_path => file_contents ].
	 * @return array|WP_Error deployment result on success.
	 */
	public function deploy( array $files ) {
		if ( empty( $files ) ) {
			return new WP_Error( 'sforge_no_files', 'No files to deploy.' );
		}

		$manifest = [];
		$assets   = [];
		foreach ( $files as $path => $content ) {
			$rel  = '/' . ltrim( str_replace( '\\', '/', $path ), '/' );
			$ext  = strtolower( pathinfo( $rel, PATHINFO_EXTENSION ) );
			$hash = self::hash_asset( $content, $ext );
			$manifest[ $rel ] = $hash;
			if ( ! isset( $assets[ $hash ] ) ) {
				$assets[ $hash ] = [ 'content' => $content, 'ext' => $ext ];
			}
		}
		return $this->push( $manifest, $assets );
	}

	/**
	 * Deploy the on-disk export mirror.
	 *
	 * Preferred over deploy() for whole-site deploys: file bodies are streamed
	 * from disk for hashing and read back only for the assets Cloudflare reports
	 * as new, so peak memory tracks the upload batch size rather than the size of
	 * the site. A 1,400-page export costs the same RAM as a 10-page one.
	 *
	 * @return array|WP_Error deployment result on success.
	 */
	public function deploy_dir( SFORGE_Export_Store $store ) {
		$files = $store->scan();
		if ( empty( $files ) ) {
			return new WP_Error( 'sforge_no_files', 'Export directory is empty, nothing to deploy.' );
		}

		$manifest = [];
		$assets   = [];
		$unreadable = 0;
		foreach ( $files as $rel => $abs ) {
			$ext  = strtolower( pathinfo( $rel, PATHINFO_EXTENSION ) );
			$hash = self::hash_file( $abs, $ext );
			if ( $hash === false ) {
				$unreadable++;
				continue;
			}
			$manifest[ '/' . $rel ] = $hash;
			if ( ! isset( $assets[ $hash ] ) ) {
				$assets[ $hash ] = [ 'path' => $abs, 'ext' => $ext ];
			}
		}
		if ( $unreadable ) {
			SFORGE_Logger::log( sprintf( '%d export file(s) could not be read and were left out of the deploy.', $unreadable ), 'warn' );
		}
		if ( empty( $manifest ) ) {
			return new WP_Error( 'sforge_no_files', 'No readable files in the export directory.' );
		}
		return $this->push( $manifest, $assets );
	}

	/**
	 * Shared tail for both deploy paths: token, check-missing, upload, deployment.
	 *
	 * @param array $manifest [ "/path" => hash ] — every file in the site.
	 * @param array $assets   [ hash => [ 'ext' => string, 'path'|'content' => string ] ]
	 */
	protected function push( array $manifest, array $assets ) {
		SFORGE_Logger::log( 'Requesting upload token from Cloudflare...' );
		$jwt = $this->fetch_jwt();
		if ( is_wp_error( $jwt ) ) {
			SFORGE_Logger::log( 'Upload token error: ' . $jwt->get_error_message(), 'error' );
			return $jwt;
		}
		SFORGE_Logger::log( sprintf( 'Upload token OK. Hashed %d files into %d unique assets. Asking CF which are new...', count( $manifest ), count( $assets ) ) );

		$missing = $this->check_missing( $jwt, array_keys( $assets ) );
		if ( is_wp_error( $missing ) ) {
			SFORGE_Logger::log( 'check-missing error: ' . $missing->get_error_message(), 'error' );
			return $missing;
		}

		SFORGE_Logger::log( sprintf( 'Manifest: %d files, %d new, %d cached', count( $manifest ), count( $missing ), count( $assets ) - count( $missing ) ) );

		if ( ! empty( $missing ) ) {
			$r = $this->upload_assets( $jwt, $missing, $assets );
			if ( is_wp_error( $r ) ) {
				SFORGE_Logger::log( 'Asset upload error: ' . $r->get_error_message(), 'error' );
				return $r;
			}
		}

		SFORGE_Logger::log( 'Creating Cloudflare Pages deployment...' );
		$result = $this->create_deployment( $manifest );
		if ( is_wp_error( $result ) ) {
			SFORGE_Logger::log( 'Deployment error: ' . $result->get_error_message(), 'error' );
			return $result;
		}
		return $result;
	}

	protected function check_missing( $jwt, array $hashes ) {
		if ( empty( $hashes ) ) {
			return [];
		}
		$resp = wp_remote_post( self::API . '/pages/assets/check-missing', [
			'timeout' => 30,
			'headers' => [
				'Authorization' => 'Bearer ' . $jwt,
				'Content-Type'  => 'application/json',
			],
			'body'    => wp_json_encode( [ 'hashes' => array_values( $hashes ) ] ),
		] );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$body = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( empty( $body['success'] ) ) {
			$msg = $body['errors'][0]['message'] ?? 'unknown';
			return new WP_Error( 'sforge_check_missing', 'check-missing failed: ' . $msg );
		}
		return is_array( $body['result'] ?? null ) ? $body['result'] : [];
	}

	protected function upload_assets( $jwt, array $hashes, array $assets ) {
		$total       = count( $hashes );
		$batch       = [];
		$batch_bytes = 0;
		$uploaded    = 0;
		$batch_idx   = 0;

		$flush = function() use ( &$batch, &$batch_bytes, &$uploaded, &$batch_idx, $jwt, $total ) {
			if ( empty( $batch ) ) {
				return true;
			}
			$batch_idx++;
			$count = count( $batch );
			$mb    = round( $batch_bytes / 1048576, 1 );
			SFORGE_Logger::log( sprintf( 'Uploading batch %d (%d files, %s MB)...', $batch_idx, $count, $mb ) );
			$start = microtime( true );
			$r = $this->upload_batch( $jwt, $batch );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
			$elapsed = round( microtime( true ) - $start, 1 );
			$uploaded += $count;
			SFORGE_Logger::log( sprintf( 'Batch %d done in %ss. Total uploaded: %d / %d', $batch_idx, $elapsed, $uploaded, $total ) );
			$batch       = [];
			$batch_bytes = 0;
			return true;
		};

		foreach ( $hashes as $hash ) {
			if ( ! isset( $assets[ $hash ] ) ) {
				continue;
			}
			$a    = $assets[ $hash ];
			$body = $this->asset_body( $a );
			if ( $body === false ) {
				SFORGE_Logger::log( 'Skipping unreadable asset: ' . esc_html( $a['path'] ?? $hash ), 'warn' );
				continue;
			}
			$b64  = base64_encode( $body );
			$size = strlen( $b64 );
			unset( $body );

			if ( ( count( $batch ) >= self::BATCH_FILES ) || ( $batch_bytes + $size ) > self::BATCH_BYTES ) {
				$r = $flush();
				if ( is_wp_error( $r ) ) {
					return $r;
				}
			}

			$batch[] = [
				'key'      => $hash,
				'value'    => $b64,
				'metadata' => [ 'contentType' => $this->mime( $a['ext'] ) ],
				'base64'   => true,
			];
			$batch_bytes += $size;
		}

		$r = $flush();
		if ( is_wp_error( $r ) ) {
			return $r;
		}

		SFORGE_Logger::log( sprintf( 'All %d new assets uploaded.', $uploaded ) );
		return true;
	}

	protected function upload_batch( $jwt, array $batch ) {
		$resp = wp_remote_post( self::API . '/pages/assets/upload', [
			'timeout' => 180,
			'headers' => [
				'Authorization' => 'Bearer ' . $jwt,
				'Content-Type'  => 'application/json',
			],
			'body'    => wp_json_encode( $batch ),
		] );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$body = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( empty( $body['success'] ) ) {
			$msg = $body['errors'][0]['message'] ?? 'unknown';
			return new WP_Error( 'sforge_upload', 'Asset upload failed: ' . $msg );
		}
		return true;
	}

	protected function create_deployment( array $manifest ) {
		$url      = self::API . '/accounts/' . rawurlencode( $this->account_id() ) . '/pages/projects/' . rawurlencode( $this->project() ) . '/deployments';
		$boundary = '----SFORGE' . wp_generate_password( 24, false, false );

		// CF Pages /deployments expects multipart/form-data with two form fields:
		//   - manifest: JSON string mapping each "/path" to its asset hash
		//   - branch:   target branch name (main = production, anything else = preview)
		$parts  = '';
		$parts .= '--' . $boundary . "\r\n";
		$parts .= 'Content-Disposition: form-data; name="manifest"' . "\r\n";
		$parts .= 'Content-Type: application/json' . "\r\n\r\n";
		$parts .= wp_json_encode( $manifest ) . "\r\n";
		$parts .= '--' . $boundary . "\r\n";
		$parts .= 'Content-Disposition: form-data; name="branch"' . "\r\n\r\n";
		$parts .= $this->branch() . "\r\n";
		$parts .= '--' . $boundary . '--' . "\r\n";

		$resp = wp_remote_post( $url, [
			'timeout' => 120,
			'headers' => [
				'Authorization' => 'Bearer ' . $this->api_token(),
				'Content-Type'  => 'multipart/form-data; boundary=' . $boundary,
			],
			'body'    => $parts,
		] );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$body = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( empty( $body['success'] ) ) {
			$msg = $body['errors'][0]['message'] ?? ( 'HTTP ' . wp_remote_retrieve_response_code( $resp ) );
			return new WP_Error( 'sforge_deploy', 'Deployment failed: ' . $msg );
		}
		return $body['result'];
	}

	/**
	 * Bytes for an asset, read from disk only at the moment they are needed so a
	 * disk-backed deploy never holds more than the current batch in memory.
	 *
	 * @return string|false
	 */
	protected function asset_body( array $asset ) {
		if ( isset( $asset['content'] ) ) {
			return $asset['content'];
		}
		if ( empty( $asset['path'] ) ) {
			return false;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents -- Reading the local export mirror one batch at a time; WP_Filesystem would force the whole site into memory.
		return @file_get_contents( $asset['path'] );
	}

	protected function mime( $ext ) {
		$map = [
			'html' => 'text/html; charset=utf-8',
			'htm'  => 'text/html; charset=utf-8',
			'css'  => 'text/css; charset=utf-8',
			'js'   => 'application/javascript; charset=utf-8',
			'json' => 'application/json; charset=utf-8',
			'xml'  => 'application/xml; charset=utf-8',
			'txt'  => 'text/plain; charset=utf-8',
			'svg'  => 'image/svg+xml',
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'gif'  => 'image/gif',
			'webp' => 'image/webp',
			'avif' => 'image/avif',
			'ico'  => 'image/x-icon',
			'pdf'  => 'application/pdf',
			'woff' => 'font/woff',
			'woff2'=> 'font/woff2',
			'ttf'  => 'font/ttf',
			'eot'  => 'application/vnd.ms-fontobject',
		];
		return $map[ $ext ] ?? 'application/octet-stream';
	}
}
