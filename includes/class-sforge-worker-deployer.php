<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cloudflare Workers Script API client for the form handler.
 *
 * The static site itself is deployed via the Pages Direct Upload API, which
 * serves everything as a static asset and never executes Functions or a
 * `_worker.js` (see SFORGE_Rebuild::execute()). A form that emails submissions
 * needs server-side code so the email-provider API key is never exposed to the
 * browser, so the handler is deployed as a **standalone Worker** on the same
 * account, using the documented, stable Workers Script upload endpoint rather
 * than reverse-engineering the Pages Functions bundle format.
 *
 * Flow:
 *   1. PUT  /accounts/{a}/workers/scripts/{name}   multipart: module + secret bindings
 *   2. POST /accounts/{a}/workers/scripts/{name}/subdomain  { enabled: true }
 *   3. GET  /accounts/{a}/workers/subdomain        -> the account's workers.dev subdomain
 *   -> the form posts to https://{name}.{subdomain}.workers.dev
 *
 * Secrets (the email-provider key, and the Turnstile secret when enabled) are
 * sent inline as `secret_text` bindings on every upload. They are held in
 * WordPress only for the length of the deploy request and never written to the
 * options table — the operator re-enters the key to redeploy (clear-after-deploy).
 */
class SFORGE_Worker_Deployer {

	const API         = 'https://api.cloudflare.com/client/v4';
	const COMPAT_DATE = '2024-11-01';

	protected $subdomain = null;

	public function account_id() { return trim( (string) SFORGE_Settings::get( 'account_id', '' ) ); }
	public function api_token()  { return trim( (string) SFORGE_Settings::get( 'api_token', '' ) ); }

	/**
	 * The Worker script name. Defaults to "<project>-forms"; falls back to a
	 * generic name when no project slug is set. Lowercased to a safe script name.
	 */
	public function worker_name() {
		$name = trim( (string) SFORGE_Settings::get( 'form_worker_name', '' ) );
		if ( $name === '' ) {
			$project = sanitize_key( (string) SFORGE_Settings::get( 'project_name', '' ) );
			$name    = ( $project !== '' ) ? $project . '-forms' : 'staticforge-forms';
		}
		// Worker script names allow lowercase alphanumerics, hyphen and underscore.
		$name = strtolower( preg_replace( '/[^a-zA-Z0-9_-]+/', '-', $name ) );
		return trim( $name, '-_' ) ?: 'staticforge-forms';
	}

	/**
	 * Deploy (create or overwrite) the Worker and expose it on workers.dev.
	 *
	 * @param string $script  The generated ES-module source.
	 * @param array  $secrets [ SECRET_NAME => value ]; empty values are skipped.
	 * @return array|WP_Error [ 'url' => string, 'name' => string ] on success.
	 */
	public function deploy( $script, array $secrets ) {
		if ( $this->account_id() === '' || $this->api_token() === '' ) {
			return new WP_Error( 'sforge_worker_creds', 'Account ID and API token are required before deploying the form handler.' );
		}

		$put = $this->put_script( $script, $secrets );
		if ( is_wp_error( $put ) ) {
			return $put;
		}

		$sub = $this->enable_subdomain();
		if ( is_wp_error( $sub ) ) {
			return $sub;
		}

		$url = $this->worker_url();
		if ( is_wp_error( $url ) ) {
			return $url;
		}

		return [ 'url' => $url, 'name' => $this->worker_name() ];
	}

	/**
	 * PUT the module worker with its secret bindings. secret_text bindings carry
	 * the value inline, so this both uploads the code and sets the secrets in one
	 * request — no separate secrets call, nothing persisted on our side.
	 */
	protected function put_script( $script, array $secrets ) {
		$bindings = [];
		foreach ( $secrets as $name => $text ) {
			if ( (string) $text === '' ) {
				continue;
			}
			$bindings[] = [ 'type' => 'secret_text', 'name' => $name, 'text' => (string) $text ];
		}

		$metadata = [
			'main_module'        => 'worker.js',
			'compatibility_date' => self::COMPAT_DATE,
			'bindings'           => $bindings,
		];

		$boundary = 'sforge' . wp_generate_password( 24, false );
		$body     = $this->multipart_body( [
			[ 'name' => 'metadata', 'type' => 'application/json', 'content' => wp_json_encode( $metadata ) ],
			[ 'name' => 'worker.js', 'filename' => 'worker.js', 'type' => 'application/javascript+module', 'content' => $script ],
		], $boundary );

		$url  = self::API . '/accounts/' . rawurlencode( $this->account_id() ) . '/workers/scripts/' . rawurlencode( $this->worker_name() );
		$resp = wp_remote_request( $url, [
			'method'  => 'PUT',
			'timeout' => 30,
			'headers' => [
				'Authorization' => 'Bearer ' . $this->api_token(),
				'Content-Type'  => 'multipart/form-data; boundary=' . $boundary,
			],
			'body'    => $body,
		] );

		return $this->interpret( $resp, 'Form handler upload' );
	}

	/** Turn on the workers.dev route for this script. */
	protected function enable_subdomain() {
		$url  = self::API . '/accounts/' . rawurlencode( $this->account_id() ) . '/workers/scripts/' . rawurlencode( $this->worker_name() ) . '/subdomain';
		$resp = wp_remote_post( $url, [
			'timeout' => 25,
			'headers' => [
				'Authorization' => 'Bearer ' . $this->api_token(),
				'Content-Type'  => 'application/json',
			],
			'body'    => wp_json_encode( [ 'enabled' => true ] ),
		] );
		return $this->interpret( $resp, 'Enabling the workers.dev route' );
	}

	/**
	 * The account's workers.dev subdomain, cached per request.
	 *
	 * @return string|WP_Error
	 */
	public function subdomain() {
		if ( $this->subdomain !== null ) {
			return $this->subdomain;
		}
		$url  = self::API . '/accounts/' . rawurlencode( $this->account_id() ) . '/workers/subdomain';
		$resp = wp_remote_get( $url, [
			'timeout' => 25,
			'headers' => [ 'Authorization' => 'Bearer ' . $this->api_token() ],
		] );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$body = json_decode( wp_remote_retrieve_body( $resp ), true );
		$sub  = isset( $body['result']['subdomain'] ) ? trim( (string) $body['result']['subdomain'] ) : '';
		if ( empty( $body['success'] ) || $sub === '' ) {
			return new WP_Error(
				'sforge_no_subdomain',
				'No workers.dev subdomain is registered on this account. Open Cloudflare → Workers & Pages once to claim your free <name>.workers.dev subdomain, then deploy again.'
			);
		}
		$this->subdomain = $sub;
		return $sub;
	}

	/**
	 * Public URL the deployed form handler answers on.
	 *
	 * @return string|WP_Error
	 */
	public function worker_url() {
		$sub = $this->subdomain();
		if ( is_wp_error( $sub ) ) {
			return $sub;
		}
		return 'https://' . $this->worker_name() . '.' . $sub . '.workers.dev';
	}

	/** Remove the Worker entirely (used by "Remove form handler"). */
	public function delete() {
		if ( $this->account_id() === '' || $this->api_token() === '' ) {
			return new WP_Error( 'sforge_worker_creds', 'Account ID and API token are required.' );
		}
		$url  = self::API . '/accounts/' . rawurlencode( $this->account_id() ) . '/workers/scripts/' . rawurlencode( $this->worker_name() );
		$resp = wp_remote_request( $url, [
			'method'  => 'DELETE',
			'timeout' => 25,
			'headers' => [ 'Authorization' => 'Bearer ' . $this->api_token() ],
		] );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$code = wp_remote_retrieve_response_code( $resp );
		// A already-absent script (404) is a success for teardown purposes.
		if ( $code === 200 || $code === 404 ) {
			return true;
		}
		$body = json_decode( wp_remote_retrieve_body( $resp ), true );
		return new WP_Error( 'sforge_worker_delete', $this->error_message( $resp, $body, 'Removing the form handler' ) );
	}

	/**
	 * Shared success/failure read for the PUT and subdomain calls.
	 *
	 * @return true|WP_Error
	 */
	protected function interpret( $resp, $label ) {
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$body = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( wp_remote_retrieve_response_code( $resp ) >= 300 || empty( $body['success'] ) ) {
			return new WP_Error( 'sforge_worker_api', $this->error_message( $resp, $body, $label ) );
		}
		return true;
	}

	/** Best-effort human message from a Cloudflare error envelope. */
	protected function error_message( $resp, $body, $label ) {
		$msg  = $body['errors'][0]['message'] ?? '';
		$code = isset( $body['errors'][0]['code'] ) ? (int) $body['errors'][0]['code'] : 0;
		if ( $msg === '' ) {
			$msg = 'HTTP ' . wp_remote_retrieve_response_code( $resp );
		}
		// Point the operator at the most common cause: a token without Workers scope.
		if ( $code === 10000 || wp_remote_retrieve_response_code( $resp ) === 403 ) {
			$msg .= ' — check the API token also has the "Account · Workers Scripts · Edit" permission.';
		}
		return $label . ' failed: ' . $msg;
	}

	/**
	 * Build a multipart/form-data body from parts. WordPress has no helper for
	 * custom multipart parts, and the Workers upload needs a named module part
	 * plus a JSON metadata part, so we assemble it by hand.
	 *
	 * @param array  $parts    Each: [ 'name', 'content', optional 'filename', 'type' ].
	 * @param string $boundary Boundary token, already unique.
	 */
	protected function multipart_body( array $parts, $boundary ) {
		$out = '';
		foreach ( $parts as $p ) {
			$disposition = 'Content-Disposition: form-data; name="' . $p['name'] . '"';
			if ( isset( $p['filename'] ) ) {
				$disposition .= '; filename="' . $p['filename'] . '"';
			}
			$out .= '--' . $boundary . "\r\n" . $disposition . "\r\n";
			if ( isset( $p['type'] ) ) {
				$out .= 'Content-Type: ' . $p['type'] . "\r\n";
			}
			$out .= "\r\n" . $p['content'] . "\r\n";
		}
		$out .= '--' . $boundary . "--\r\n";
		return $out;
	}
}
