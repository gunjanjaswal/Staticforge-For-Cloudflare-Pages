<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Form handling for the static site.
 *
 * A static site can't process a POST on its own, and the plugin's Pages Direct
 * Upload deploy can't run Functions (see SFORGE_Worker_Deployer). So a form that
 * emails its fields is served by a small standalone Cloudflare Worker this class
 * generates from settings and SFORGE_Worker_Deployer uploads.
 *
 * The design is email-API-agnostic: the operator picks a provider preset (or
 * "custom") which fills an editable endpoint + auth header + JSON body template.
 * The Worker fills `{{field}}` tokens from the submission and forwards the result
 * to the provider, with the API key injected from a Worker secret — never baked
 * into the script and never shipped to the browser.
 *
 * This class owns three things:
 *   - the provider presets and the default settings,
 *   - the generated Worker source (a fixed runtime + a CONFIG object), and
 *   - the [sforge_form] shortcode that renders the front-end form.
 */
class SFORGE_Forms {

	public function __construct() {
		add_shortcode( 'sforge_form', [ $this, 'shortcode' ] );
	}

	const SECRET_KEY       = 'SFORGE_FORM_KEY';
	const SECRET_TURNSTILE = 'TURNSTILE_SECRET';

	/**
	 * Provider presets. Selecting one fills the endpoint / auth / body-template
	 * fields; the operator can still edit any of them. "custom" ships blank so
	 * any other email API (or a self-hosted relay) can be wired up by hand.
	 *
	 * `${SFORGE_FORM_KEY}` in an auth template resolves to the Worker secret at
	 * runtime. `{{name}}`, `{{email}}`, … resolve to submitted fields;
	 * `{{all_fields}}` expands to every submitted field as `Label: value` lines.
	 *
	 * @return array<string,array>
	 */
	public static function presets() {
		return [
			'resend' => [
				'label'            => 'Resend',
				'endpoint'         => 'https://api.resend.com/emails',
				'auth_header_name' => 'Authorization',
				'auth_header_tpl'  => 'Bearer ${SFORGE_FORM_KEY}',
				'content_type'     => 'json',
				'body_template'    => "{\n  \"from\": \"Website <onboarding@resend.dev>\",\n  \"to\": [\"you@example.com\"],\n  \"reply_to\": \"{{email}}\",\n  \"subject\": \"New enquiry from {{name}}\",\n  \"text\": \"{{all_fields}}\"\n}",
				'key_hint'         => 'Your Resend API key (starts with re_). Create one at resend.com → API Keys.',
			],
			'sendgrid' => [
				'label'            => 'SendGrid',
				'endpoint'         => 'https://api.sendgrid.com/v3/mail/send',
				'auth_header_name' => 'Authorization',
				'auth_header_tpl'  => 'Bearer ${SFORGE_FORM_KEY}',
				'content_type'     => 'json',
				'body_template'    => "{\n  \"personalizations\": [{ \"to\": [{ \"email\": \"you@example.com\" }] }],\n  \"from\": { \"email\": \"forms@example.com\", \"name\": \"Website\" },\n  \"reply_to\": { \"email\": \"{{email}}\" },\n  \"subject\": \"New enquiry from {{name}}\",\n  \"content\": [{ \"type\": \"text/plain\", \"value\": \"{{all_fields}}\" }]\n}",
				'key_hint'         => 'Your SendGrid API key. Create one under Settings → API Keys with Mail Send permission.',
			],
			'postmark' => [
				'label'            => 'Postmark',
				'endpoint'         => 'https://api.postmarkapp.com/email',
				'auth_header_name' => 'X-Postmark-Server-Token',
				'auth_header_tpl'  => '${SFORGE_FORM_KEY}',
				'content_type'     => 'json',
				'body_template'    => "{\n  \"From\": \"forms@example.com\",\n  \"To\": \"you@example.com\",\n  \"ReplyTo\": \"{{email}}\",\n  \"Subject\": \"New enquiry from {{name}}\",\n  \"TextBody\": \"{{all_fields}}\",\n  \"MessageStream\": \"outbound\"\n}",
				'key_hint'         => 'Your Postmark Server API Token (Server → API Tokens).',
			],
			'mailgun' => [
				'label'            => 'Mailgun',
				'endpoint'         => 'https://api.mailgun.net/v3/YOUR_DOMAIN/messages',
				'auth_header_name' => 'Authorization',
				'auth_header_tpl'  => 'Basic ${SFORGE_FORM_KEY}',
				'content_type'     => 'form',
				'body_template'    => "{\n  \"from\": \"Website <forms@example.com>\",\n  \"to\": \"you@example.com\",\n  \"h:Reply-To\": \"{{email}}\",\n  \"subject\": \"New enquiry from {{name}}\",\n  \"text\": \"{{all_fields}}\"\n}",
				'key_hint'         => 'Base64 of "api:YOUR_MAILGUN_KEY". Also replace YOUR_DOMAIN in the endpoint with your Mailgun sending domain.',
			],
			'custom' => [
				'label'            => 'Custom / other',
				'endpoint'         => '',
				'auth_header_name' => 'Authorization',
				'auth_header_tpl'  => 'Bearer ${SFORGE_FORM_KEY}',
				'content_type'     => 'json',
				'body_template'    => "{\n  \"to\": \"you@example.com\",\n  \"subject\": \"New enquiry from {{name}}\",\n  \"text\": \"{{all_fields}}\"\n}",
				'key_hint'         => 'Whatever secret your endpoint expects in the auth header.',
			],
		];
	}

	/**
	 * Default form settings, merged into options on activation and backfilled on
	 * upgrade. Secrets are never stored here — only whether a key has been set.
	 */
	public static function default_settings() {
		$resend = self::presets()['resend'];
		return [
			'form_enabled'         => 0,
			'form_worker_name'     => '',
			'form_worker_url'      => '',
			'form_key_set'         => 0,
			'form_provider'        => 'resend',
			'form_endpoint'        => $resend['endpoint'],
			'form_auth_header_name'=> $resend['auth_header_name'],
			'form_auth_header_tpl' => $resend['auth_header_tpl'],
			'form_content_type'    => $resend['content_type'],
			'form_body_template'   => $resend['body_template'],
			'form_required_fields' => [ 'name', 'email', 'message' ],
			'form_turnstile'       => 0,
			'form_turnstile_site'  => '',
		];
	}

	/**
	 * Assemble the CONFIG object baked into the generated Worker from settings.
	 * `allowedOrigins` locks CORS to the public site (and its .pages.dev twin) so
	 * the endpoint isn't a wide-open mailer.
	 */
	public static function worker_config() {
		$origins = [];
		$pub = (string) SFORGE_Settings::get( 'cf_pages_url', '' );
		if ( $pub !== '' ) {
			$o = self::origin_of( $pub );
			if ( $o !== '' ) {
				$origins[] = $o;
			}
		}
		$project = sanitize_key( (string) SFORGE_Settings::get( 'project_name', '' ) );
		if ( $project !== '' ) {
			$origins[] = 'https://' . $project . '.pages.dev';
		}
		$origins = array_values( array_unique( $origins ) );

		$required = (array) SFORGE_Settings::get( 'form_required_fields', [ 'name', 'email', 'message' ] );

		return [
			'allowedOrigins'     => $origins,
			'endpoint'           => (string) SFORGE_Settings::get( 'form_endpoint', '' ),
			'authHeaderName'     => (string) SFORGE_Settings::get( 'form_auth_header_name', 'Authorization' ),
			'authHeaderTemplate' => (string) SFORGE_Settings::get( 'form_auth_header_tpl', 'Bearer ${SFORGE_FORM_KEY}' ),
			'contentType'        => SFORGE_Settings::get( 'form_content_type', 'json' ) === 'form' ? 'form' : 'json',
			'bodyTemplate'       => (string) SFORGE_Settings::get( 'form_body_template', '' ),
			'requiredFields'     => array_values( array_filter( array_map( 'sanitize_key', $required ) ) ),
			'turnstile'          => (bool) SFORGE_Settings::get( 'form_turnstile', 0 ),
		];
	}

	/** scheme://host[:port] of a URL, or '' if it can't be parsed. */
	protected static function origin_of( $url ) {
		$p = wp_parse_url( $url );
		if ( empty( $p['host'] ) ) {
			return '';
		}
		$scheme = ( isset( $p['scheme'] ) && strtolower( $p['scheme'] ) === 'http' ) ? 'http' : 'https';
		$port   = isset( $p['port'] ) ? ':' . (int) $p['port'] : '';
		return $scheme . '://' . strtolower( $p['host'] ) . $port;
	}

	/**
	 * The full Worker source: a CONFIG object generated from settings, followed by
	 * a fixed runtime. wp_json_encode produces a valid JS object literal (JSON is
	 * a subset of JS), so no manual escaping of config values is needed.
	 */
	public static function build_worker_script( array $config ) {
		$json = wp_json_encode( $config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		return 'const CONFIG = ' . $json . ";\n\n" . self::runtime_js();
	}

	/**
	 * The fixed part of the Worker — everything that reads CONFIG and the two
	 * secret bindings (env.SFORGE_FORM_KEY, env.TURNSTILE_SECRET). Nowdoc so the
	 * JS `${...}` and backticks stay literal.
	 */
	protected static function runtime_js() {
		return <<<'JS'
function corsHeaders(origin) {
  const list = CONFIG.allowedOrigins || [];
  const allow = list.includes(origin) ? origin : (list[0] || "*");
  return {
    "Access-Control-Allow-Origin": allow,
    "Access-Control-Allow-Methods": "POST, OPTIONS",
    "Access-Control-Allow-Headers": "Content-Type",
    "Vary": "Origin",
  };
}

function json(status, obj, headers) {
  return new Response(JSON.stringify(obj), {
    status,
    headers: { "Content-Type": "application/json", ...headers },
  });
}

async function readData(request) {
  const ct = request.headers.get("content-type") || "";
  const data = {};
  if (ct.includes("application/json")) {
    Object.assign(data, await request.json().catch(() => ({})));
  } else {
    const form = await request.formData().catch(() => null);
    if (form) {
      for (const [k, v] of form.entries()) data[k] = typeof v === "string" ? v : "";
    }
  }
  return data;
}

// JSON-escape a value for insertion inside a JSON string literal (no quotes).
function esc(v) {
  const s = JSON.stringify(v == null ? "" : String(v));
  return s.slice(1, -1);
}

function allFields(data) {
  const skip = new Set(["_hp", "cf-turnstile-response"]);
  return Object.keys(data)
    .filter((k) => !skip.has(k))
    .map((k) => `${k}: ${data[k]}`)
    .join("\n");
}

function fillTemplate(tpl, data) {
  return tpl.replace(/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/g, (m, key) => {
    if (key === "all_fields") return esc(allFields(data));
    return esc(data[key] !== undefined ? data[key] : "");
  });
}

async function verifyTurnstile(token, ip, secret) {
  if (!token || !secret) return false;
  const body = new URLSearchParams();
  body.set("secret", secret);
  body.set("response", token);
  if (ip) body.set("remoteip", ip);
  const r = await fetch("https://challenges.cloudflare.com/turnstile/v0/siteverify", {
    method: "POST",
    body,
  }).catch(() => null);
  if (!r) return false;
  const out = await r.json().catch(() => ({ success: false }));
  return !!out.success;
}

export default {
  async fetch(request, env) {
    const origin = request.headers.get("Origin") || "";
    const cors = corsHeaders(origin);

    if (request.method === "OPTIONS") return new Response(null, { status: 204, headers: cors });
    if (request.method !== "POST") return json(405, { ok: false, error: "Method not allowed" }, cors);

    const data = await readData(request);

    // Honeypot: real users never fill this. Accept silently so bots learn nothing.
    if (data._hp) return json(200, { ok: true }, cors);

    if (CONFIG.turnstile) {
      const ok = await verifyTurnstile(
        data["cf-turnstile-response"],
        request.headers.get("CF-Connecting-IP"),
        env.TURNSTILE_SECRET
      );
      if (!ok) return json(400, { ok: false, error: "Verification failed. Please try again." }, cors);
    }

    for (const f of CONFIG.requiredFields || []) {
      if (!data[f] || !String(data[f]).trim()) {
        return json(400, { ok: false, error: "Please fill in all required fields." }, cors);
      }
    }

    const payload = fillTemplate(CONFIG.bodyTemplate, data);
    const headers = {
      [CONFIG.authHeaderName]: CONFIG.authHeaderTemplate.replace("${SFORGE_FORM_KEY}", env.SFORGE_FORM_KEY || ""),
    };

    let outBody;
    if (CONFIG.contentType === "form") {
      let obj;
      try { obj = JSON.parse(payload); } catch (e) {
        return json(500, { ok: false, error: "Form handler misconfigured." }, cors);
      }
      const usp = new URLSearchParams();
      for (const k of Object.keys(obj)) {
        usp.set(k, typeof obj[k] === "string" ? obj[k] : JSON.stringify(obj[k]));
      }
      outBody = usp; // URLSearchParams sets its own content-type
    } else {
      headers["Content-Type"] = "application/json";
      outBody = payload;
    }

    let res;
    try {
      res = await fetch(CONFIG.endpoint, { method: "POST", headers, body: outBody });
    } catch (e) {
      return json(502, { ok: false, error: "Could not reach the email service." }, cors);
    }
    if (!res.ok) {
      return json(502, { ok: false, error: "The email service rejected the message." }, cors);
    }
    return json(200, { ok: true }, cors);
  },
};
JS;
	}

	/**
	 * [sforge_form] — a name / email / message form wired to the deployed Worker.
	 *
	 * Includes a hidden honeypot and, when enabled, the Turnstile widget. A small
	 * inline script submits via fetch and swaps in a success / error message; with
	 * JavaScript off it still POSTs normally to the Worker.
	 */
	public function shortcode( $atts ) {
		$atts = shortcode_atts( [
			'button'  => __( 'Send message', 'staticforge-for-cloudflare-pages' ),
			'success' => __( 'Thanks — your message has been sent.', 'staticforge-for-cloudflare-pages' ),
			'error'   => __( 'Something went wrong. Please try again.', 'staticforge-for-cloudflare-pages' ),
		], $atts, 'sforge_form' );

		$url = (string) SFORGE_Settings::get( 'form_worker_url', '' );
		if ( ! SFORGE_Settings::get( 'form_enabled' ) || $url === '' ) {
			// Only nudge logged-in admins; visitors see nothing.
			return current_user_can( 'manage_options' )
				? '<p><em>' . esc_html__( 'StaticForge: the form handler has not been deployed yet. Configure and deploy it under StaticForge → Settings → Forms.', 'staticforge-for-cloudflare-pages' ) . '</em></p>'
				: '';
		}

		$turnstile = (bool) SFORGE_Settings::get( 'form_turnstile', 0 );
		$site_key  = (string) SFORGE_Settings::get( 'form_turnstile_site', '' );
		$uid       = 'sforge-form-' . wp_generate_password( 6, false, false );

		if ( $turnstile && $site_key !== '' ) {
			// Turnstile's widget script must load from Cloudflare's own domain — that is
			// how the bot check works — and a third-party endpoint carries no version to
			// pin. This is an opt-in feature the operator enables deliberately.
			// phpcs:ignore PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent, WordPress.WP.EnqueuedResourceParameters.MissingVersion
			wp_enqueue_script( 'sforge-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', [], null, true );
		}

		ob_start();
		?>
		<form class="sforge-form-public" id="<?php echo esc_attr( $uid ); ?>" method="post" action="<?php echo esc_url( $url ); ?>" novalidate>
			<p class="sforge-field">
				<label for="<?php echo esc_attr( $uid ); ?>-name"><?php esc_html_e( 'Name', 'staticforge-for-cloudflare-pages' ); ?></label>
				<input type="text" id="<?php echo esc_attr( $uid ); ?>-name" name="name" required>
			</p>
			<p class="sforge-field">
				<label for="<?php echo esc_attr( $uid ); ?>-email"><?php esc_html_e( 'Email', 'staticforge-for-cloudflare-pages' ); ?></label>
				<input type="email" id="<?php echo esc_attr( $uid ); ?>-email" name="email" required>
			</p>
			<p class="sforge-field">
				<label for="<?php echo esc_attr( $uid ); ?>-message"><?php esc_html_e( 'Message', 'staticforge-for-cloudflare-pages' ); ?></label>
				<textarea id="<?php echo esc_attr( $uid ); ?>-message" name="message" rows="5" required></textarea>
			</p>
			<?php // Honeypot: hidden from users, tempting to bots. ?>
			<div style="position:absolute;left:-5000px;" aria-hidden="true">
				<input type="text" name="_hp" tabindex="-1" autocomplete="off">
			</div>
			<?php if ( $turnstile && $site_key !== '' ) : ?>
				<div class="cf-turnstile" data-sitekey="<?php echo esc_attr( $site_key ); ?>"></div>
			<?php endif; ?>
			<p class="sforge-actions">
				<button type="submit"><?php echo esc_html( $atts['button'] ); ?></button>
			</p>
			<p class="sforge-form-status" role="status" aria-live="polite" hidden></p>
		</form>
		<script>
		(function () {
			var form = document.getElementById(<?php echo wp_json_encode( $uid ); ?>);
			if (!form) return;
			var status = form.querySelector('.sforge-form-status');
			var okMsg = <?php echo wp_json_encode( $atts['success'] ); ?>;
			var errMsg = <?php echo wp_json_encode( $atts['error'] ); ?>;
			form.addEventListener('submit', function (e) {
				e.preventDefault();
				var btn = form.querySelector('button[type=submit]');
				if (btn) btn.disabled = true;
				status.hidden = false;
				status.textContent = <?php echo wp_json_encode( __( 'Sending…', 'staticforge-for-cloudflare-pages' ) ); ?>;
				fetch(form.action, { method: 'POST', body: new FormData(form) })
					.then(function (r) { return r.json().catch(function () { return { ok: r.ok }; }); })
					.then(function (out) {
						if (out && out.ok) {
							form.reset();
							status.textContent = okMsg;
						} else {
							status.textContent = (out && out.error) ? out.error : errMsg;
						}
					})
					.catch(function () { status.textContent = errMsg; })
					.then(function () { if (btn) btn.disabled = false; });
			});
		})();
		</script>
		<?php
		return ob_get_clean();
	}
}
