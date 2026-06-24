<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variables, not globals.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$settings_url = admin_url( 'admin.php?page=sforge' );
?>
<div class="wrap sforge-help">
	<h1>
		<span class="dashicons dashicons-cloud-upload" aria-hidden="true"></span>
		StaticForge for Cloudflare Pages — Setup Guide
	</h1>
	<p class="sforge-help-lead">
		Auto-export your WordPress site as static HTML and deploy to Cloudflare Pages on every publish or update.
		Follow the steps below in order — first run takes about 10 minutes.
	</p>

	<div class="sforge-help-toc">
		<strong>On this page:</strong>
		<a href="#sforge-step1">1. Cloudflare Pages project</a>
		<a href="#sforge-step2">2. API token</a>
		<a href="#sforge-step3">3. Account ID</a>
		<a href="#sforge-step4">4. Plugin settings</a>
		<a href="#sforge-step5">5. First deploy</a>
		<a href="#sforge-step6">6. DNS cutover</a>
		<a href="#sforge-wpcontent">Clean /wp-content/ URLs (advanced)</a>
		<a href="#sforge-bundle-uploads">Bundle uploads (shared hosting)</a>
		<a href="#sforge-trouble">Troubleshooting</a>
	</div>

	<section class="sforge-card sforge-card-blue" id="sforge-step1">
		<h2><span class="sforge-num">1</span> Create a Cloudflare Pages project (Direct Upload mode)</h2>
		<ol>
			<li>Login to <a href="https://dash.cloudflare.com" target="_blank" rel="noopener">dash.cloudflare.com</a>.</li>
			<li>Sidebar &rarr; <strong>Workers &amp; Pages</strong> &rarr; click <strong>Create application</strong> (top right).</li>
			<li>Switch to the <strong>Pages</strong> tab on the next screen.</li>
			<li>Click <strong>Upload assets</strong> (NOT "Connect to Git").</li>
			<li><strong>Project name:</strong> use a short lowercase slug, e.g. <code>mysite</code>. This becomes <code>https://&lt;name&gt;.pages.dev</code>.</li>
			<li>Drag-drop any tiny placeholder file (a one-line <code>index.html</code> works) just to seed the project. The plugin will overwrite it on first real deploy.</li>
			<li>Click <strong>Deploy site</strong>. Once it lands, copy the project URL — that's your <em>Public Site URL</em> for testing.</li>
		</ol>
	</section>

	<section class="sforge-card sforge-card-purple" id="sforge-step2">
		<h2><span class="sforge-num">2</span> Create an API Token</h2>
		<ol>
			<li>Top-right avatar &rarr; <strong>My Profile</strong> &rarr; <strong>API Tokens</strong>.</li>
			<li>Click <strong>Create Token</strong>.</li>
			<li>Scroll down &rarr; under <em>Custom token</em> click <strong>Get started</strong>.</li>
			<li>Token name: <code>StaticForge for Cloudflare Pages</code>.</li>
			<li>Permissions: <strong>Account &middot; Cloudflare Pages &middot; Edit</strong></li>
			<li>Account Resources: <strong>Include &rarr; &lt;your account&gt;</strong>.</li>
			<li>Leave Client IP filter and TTL blank.</li>
			<li>Click <strong>Continue to summary</strong> &rarr; <strong>Create Token</strong>.</li>
			<li><strong>Copy the token now</strong> — Cloudflare shows it only once.</li>
		</ol>
		<p class="sforge-callout sforge-callout-warn">
			<strong>Security:</strong> rotate the token if it ever leaks. Never paste it into public chats, screenshots, or commits.
		</p>
	</section>

	<section class="sforge-card sforge-card-green" id="sforge-step3">
		<h2><span class="sforge-num">3</span> Find your Account ID</h2>
		<ol>
			<li>Cloudflare Dashboard &rarr; <strong>Workers &amp; Pages</strong> overview, OR any zone's overview.</li>
			<li>Right sidebar &rarr; <strong>API</strong> section &rarr; copy <strong>Account ID</strong>.</li>
		</ol>
		<p>Format: 32-character hex string.</p>
	</section>

	<section class="sforge-card sforge-card-orange" id="sforge-step4">
		<h2><span class="sforge-num">4</span> Configure the plugin</h2>
		<p>Open <a href="<?php echo esc_url( $settings_url ); ?>">StaticForge for Cloudflare Pages &rarr; Settings</a> and fill in:</p>
		<table class="sforge-help-table">
			<tr><th>Field</th><th>Value</th></tr>
			<tr><td>Account ID</td><td>From step 3</td></tr>
			<tr><td>API Token</td><td>From step 2</td></tr>
			<tr><td>Pages Project</td><td>The slug only, e.g. <code>mysite</code> (NOT the .pages.dev URL)</td></tr>
			<tr><td>Branch</td><td><code>main</code> for production. Anything else creates a preview deployment.</td></tr>
			<tr><td>Public Site URL</td><td><code>https://&lt;name&gt;.pages.dev</code> while testing. Switch to <code>https://yourdomain.com</code> after DNS cutover.</td></tr>
			<tr><td>Post Types</td><td>Tick the types to export. <code>post</code> + <code>page</code> + any custom types.</td></tr>
			<tr><td>Include</td><td>Tick all three: Homepage, Taxonomies, Authors.</td></tr>
			<tr><td>Inline CSS</td><td>Tick &mdash; embeds linked stylesheets so each page is self-contained.</td></tr>
			<tr><td>Auto-deploy</td><td>Tick to redeploy on publish/update.</td></tr>
			<tr><td>Debounce</td><td><code>120</code> seconds. Rapid edits collapse into one deploy.</td></tr>
			<tr><td>robots.txt</td><td>Leave blank to auto-generate, or paste a custom version. <code>Sitemap:</code> line is appended automatically.</td></tr>
		</table>
		<p>Save Settings &rarr; click <strong>Test Connection</strong>. Expect "Connection OK" notice and a log entry within a few seconds.</p>
	</section>

	<section class="sforge-card sforge-card-pink" id="sforge-step5">
		<h2><span class="sforge-num">5</span> First deploy</h2>
		<ol>
			<li>Click <strong>Rebuild + Deploy Now</strong>.</li>
			<li>The activity log refreshes live. Expected sequence:
				<ul>
					<li><code>Full rebuild started</code></li>
					<li><code>Crawling N URLs</code></li>
					<li><code>Render progress: X / N (Y%)</code> every 10%</li>
					<li><code>Render complete: X ok, Y failed</code></li>
					<li><code>SEO files: sitemap.xml, ..., robots.txt</code></li>
					<li><code>Requesting upload token from Cloudflare</code></li>
					<li><code>Manifest: F files, N new, C cached</code></li>
					<li><code>Uploading batch 1 (100 files, 12 MB)...</code> &rarr; <code>Batch 1 done in 4s. Total uploaded: 100 / N</code></li>
					<li><code>Creating Cloudflare Pages deployment...</code></li>
					<li><code>Deploy OK [&lt;id&gt;]</code> with link to the deployment URL.</li>
				</ul>
			</li>
			<li>Open the deployment link &rarr; verify homepage, a post, sitemap.xml, robots.txt all look right.</li>
		</ol>
		<p class="sforge-callout sforge-callout-info">
			<strong>Note:</strong> media files under <code>/wp-content/uploads/</code> are NOT bundled in the deploy. Image URLs are kept pointing to your WordPress origin — they keep working as long as origin is reachable.
		</p>
	</section>

	<section class="sforge-card sforge-card-teal" id="sforge-step6">
		<h2><span class="sforge-num">6</span> DNS cutover (optional, when ready)</h2>
		<ol>
			<li>Add your apex domain (e.g. <code>example.com</code>) to Cloudflare. Update registrar nameservers.</li>
			<li>In your CF Pages project &rarr; <strong>Custom Domains</strong> &rarr; add <code>example.com</code> + <code>www.example.com</code>. CF auto-creates the right DNS records and provisions an edge SSL cert.</li>
			<li>Recommended DNS layout:
				<table class="sforge-help-table">
					<tr><th>Record</th><th>Name</th><th>Target</th><th>Proxy</th></tr>
					<tr><td>CNAME</td><td>@ (apex)</td><td><code>&lt;project&gt;.pages.dev</code></td><td>orange</td></tr>
					<tr><td>CNAME</td><td>www</td><td><code>&lt;project&gt;.pages.dev</code></td><td>orange</td></tr>
					<tr><td>A</td><td>dashboard</td><td>your origin server IP</td><td>orange</td></tr>
				</table>
			</li>
			<li>Add a CF Bulk Redirect <code>www.example.com/*</code> &rarr; <code>https://example.com/$1</code> 301.</li>
			<li>Update plugin's <em>Public Site URL</em> to the final domain &rarr; Save &rarr; <strong>Rebuild + Deploy Now</strong> &mdash; canonicals + sitemap entries now point to the live domain.</li>
		</ol>
	</section>

	<section class="sforge-card sforge-card-purple" id="sforge-wpcontent">
		<h2><span class="sforge-num">+</span> Clean <code>/wp-content/</code> URLs (advanced)</h2>
		<p>
			By default the plugin leaves <code>&lt;origin&gt;/wp-content/...</code> URLs alone
			(theme CSS/JS, plugin assets, media uploads) so they keep working without bundling
			multi-gigabyte folders into every deploy. That means structured data such as
			<code>og:image</code>, JSON-LD <code>image</code>/<code>logo</code>/<code>thumbnailUrl</code>,
			and HTML <code>&lt;img src&gt;</code>/<code>srcset</code> will still reference your
			<strong>dashboard host</strong> on the deployed site.
		</p>
		<p>
			If you want fully clean URLs on the live site, set up a proxy and turn on
			<strong>Export Scope &rarr; Rewrite <code>/wp-content/</code> URLs</strong>.
		</p>

		<h3>Option A &mdash; Cloudflare Worker (recommended)</h3>
		<ol>
			<li>Cloudflare Dashboard &rarr; <strong>Workers &amp; Pages</strong> &rarr; <strong>Create application</strong> &rarr; <strong>Workers</strong> &rarr; <strong>Hello World</strong> template.</li>
			<li>Name it <code>wp-content-proxy</code> &rarr; Deploy &rarr; <strong>Edit code</strong>.</li>
			<li>Replace the default code with:
<pre><code>export default {
  async fetch(request) {
    const url = new URL(request.url);
    if (url.pathname.startsWith('/wp-content/')) {
      // Point /wp-content/* to your WordPress origin
      url.hostname = 'dashboard.example.com';
      return fetch(url, request);
    }
    return fetch(request);
  }
};</code></pre>
				Replace <code>dashboard.example.com</code> with your actual dashboard host.
			</li>
			<li>Click <strong>Deploy</strong>.</li>
			<li>Worker &rarr; <strong>Settings</strong> &rarr; <strong>Triggers</strong> &rarr; <strong>Add route</strong>: <code>example.com/wp-content/*</code> (and add a second route for <code>www.example.com/wp-content/*</code> if you serve both).</li>
			<li>In the plugin: tick <strong>Export Scope &rarr; Rewrite <code>/wp-content/</code> URLs</strong> &rarr; Save &rarr; <strong>Rebuild + Deploy Now</strong>.</li>
		</ol>

		<h3>Option B &mdash; Nginx / Apache reverse proxy</h3>
		<p>If your live site is served from your own server:</p>
		<pre><code># Nginx
location /wp-content/ {
    proxy_pass         https://dashboard.example.com;
    proxy_set_header   Host dashboard.example.com;
    proxy_ssl_server_name on;
}

# Apache
&lt;Location "/wp-content/"&gt;
    ProxyPass         "https://dashboard.example.com/wp-content/"
    ProxyPassReverse  "https://dashboard.example.com/wp-content/"
&lt;/Location&gt;</code></pre>

		<h3>Verify</h3>
		<ol>
			<li><code>curl -I https://example.com/wp-content/uploads/<em>any-file</em>.jpg</code> &rarr; expect HTTP 200 with image content-type.</li>
			<li>Rebuild + Deploy Now.</li>
			<li>View source of any deployed post &rarr; <code>og:image</code>, JSON-LD <code>image</code>/<code>logo</code>, and <code>&lt;img src&gt;</code> all reference your live host (<code>example.com</code>), not the dashboard.</li>
		</ol>

		<p class="sforge-callout sforge-callout-warn">
			<strong>If the toggle is on but proxy is NOT set up:</strong> every image / theme stylesheet / plugin script on the live site will return 404. Verify the curl test above before flipping the toggle on a production site.
		</p>
	</section>

	<section class="sforge-card sforge-card-green" id="sforge-bundle-uploads">
		<h2><span class="sforge-num">+</span> Bundle <code>/wp-content/uploads/</code> into deploy (shared hosting)</h2>
		<p>
			Use this when the Worker / Nginx proxy approach above doesn't work because your
			origin's firewall blocks Cloudflare. Common on shared cPanel hosts (HostArmada,
			SiteGround, GoDaddy, Bluehost, etc.) where you can't whitelist Cloudflare's edge IPs.
			Symptoms: <code>curl -I https://example.com/wp-content/uploads/file.jpg</code> returns
			<code>HTTP/1.1 520</code> or <code>522</code> while the direct dashboard URL works.
		</p>

		<h3>What it does</h3>
		<p>
			Tick <strong>Export Scope &rarr; Bundle <code>/wp-content/uploads/</code> into deploy</strong>
			(leave <strong>Rewrite <code>/wp-content/</code> URLs</strong> off). On the next rebuild
			the plugin:
		</p>
		<ol>
			<li>Scans every rendered page for <code>/wp-content/uploads/...</code> references &mdash;
				<code>&lt;img src&gt;</code>, <code>srcset</code>, <code>og:image</code>, JSON-LD
				<code>image</code>/<code>logo</code>/<code>thumbnailUrl</code>, inline CSS
				<code>url(...)</code>, oEmbed thumbnails (literal, JSON-escaped, and percent-encoded forms).</li>
			<li>Fetches each unique file from the WordPress origin during rebuild.</li>
			<li>Uploads them inside the Cloudflare Pages deploy at their original
				<code>/wp-content/uploads/...</code> paths.</li>
			<li>Rewrites image URLs in the rendered HTML / JSON-LD to the live host so the static
				site is fully self-contained.</li>
		</ol>

		<h3>What still loads from origin</h3>
		<p>
			Theme CSS/JS and plugin assets (everything under <code>/wp-content/themes/</code> and
			<code>/wp-content/plugins/</code>) keep loading from the WordPress dashboard host as
			before. Those rarely cause shared-hosting firewall issues, and bundling them on every
			deploy would balloon the upload size for no SEO benefit.
		</p>

		<h3>Cost / size considerations</h3>
		<p>
			Only files <em>referenced</em> from exported pages get bundled &mdash; not the entire
			media library. Cloudflare's <code>check-missing</code> API deduplicates unchanged files
			by content hash, so subsequent rebuilds only upload new or modified images. If your
			origin uses
			<a href="https://wordpress.org/plugins/webp-express/" target="_blank" rel="noopener">WebP Express</a>
			or similar (serving <code>.webp</code> via headers), the plugin captures whichever
			extension your origin actually returns in the rendered HTML.
		</p>

		<h3>Verify after deploy</h3>
		<ol>
			<li>Watch the activity log &mdash; expect <code>Bundling N /wp-content/uploads/ file(s) into deploy...</code>
				then <code>Asset bundle done: X ok, Y failed, Z MB total</code>.</li>
			<li><code>curl -I https://example.com/wp-content/uploads/<em>any-file</em>.jpg</code>
				&rarr; expect <code>HTTP/1.1 200 OK</code> with <code>Server: cloudflare</code> and
				a <code>cf-ray</code> header (served by CF Pages directly, not your origin).</li>
			<li>View any deployed post source &rarr; <code>og:image</code>, JSON-LD image fields,
				and <code>&lt;img src&gt;</code> / <code>srcset</code> all point at the live host.</li>
		</ol>

		<p class="sforge-callout sforge-callout-info">
			<strong>Which option do I pick?</strong> If your origin proxies cleanly through Cloudflare
			(VPS / dedicated / fully managed with Worker route accepting CF subrequests), use the
			Worker/Nginx proxy above &mdash; uploads stay on the dashboard, deploys stay tiny. If your
			origin is shared hosting and you keep getting 520/522 from the Worker route, use this
			bundle option instead.
		</p>

		<p class="sforge-callout sforge-callout-warn">
			<strong>Ignored when "Rewrite <code>/wp-content/</code> URLs" is on.</strong> That setting
			rewrites everything (themes, plugins, uploads) and assumes you have a full proxy. The
			bundle option only matters when the broader rewrite toggle is off.
		</p>
	</section>

	<section class="sforge-card sforge-card-red" id="sforge-trouble">
		<h2>Troubleshooting</h2>
		<p class="sforge-help-lead" style="margin-top:0">Grouped by where it happens. The <code>code-styled</code> phrases are the exact <strong>Activity Log</strong> messages, so you can match what you see.</p>

		<h3>Login &amp; DNS cutover</h3>
		<dl class="sforge-faq">
			<dt>After DNS cutover, wp-admin bounces to the live site — can't log in or redeploy</dt>
			<dd>
				<strong>Symptom:</strong> opening wp-admin throws you to the <em>public</em> host's login, e.g.
				<code>https://example.com/wp-login.php?redirect_to=https%3A%2F%2Fdashboard.example.com%2Fwp-admin%2F...</code>
				— but <code>example.com</code> is now the static Cloudflare site with no WordPress on it, so login fails
				and you can't reach this page to redeploy.<br><br>
				<strong>Cause:</strong> WordPress's own <strong>WP Address</strong> (<code>siteurl</code>) / <strong>Site Address</strong>
				(<code>home</code>) still point at the public host instead of your dashboard host. WordPress builds the login URL
				from <code>siteurl</code>, so it sends you to the static site.<br><br>
				<strong>Fix:</strong> pin both to the dashboard host in <code>wp-config.php</code> (add just above
				<code>/* That's all, stop editing! Happy publishing. */</code>):
<pre><code>define( 'WP_HOME',    'https://dashboard.example.com' );
define( 'WP_SITEURL', 'https://dashboard.example.com' );</code></pre>
				Save, then log in from a private/incognito window at <code>https://dashboard.example.com/wp-admin</code>.
				<br><br>
				<strong>Keep them separate:</strong> WordPress lives on <code>dashboard.example.com</code>; the plugin's
				<strong>Public Site URL</strong> stays the public host (<code>https://example.com</code>). The renderer rewrites
				the dashboard host &rarr; Public Site URL during export, so the static site still shows clean public links. Only
				WordPress's own two addresses move to the dashboard subdomain — never the plugin's Public Site URL.
			</dd>

		</dl>

		<h3>Setup &amp; connection (Test Connection)</h3>
		<dl class="sforge-faq">
			<dt><code>Test FAIL: Account ID, API token and project name are required</code></dt>
			<dd>One of the three credential fields is blank. Fill Account ID, API Token, and Pages Project, Save, then test again.</dd>

			<dt><code>Test FAIL: Project not found</code></dt>
			<dd>The <strong>Pages Project</strong> field must be the project <em>slug</em> only (e.g. <code>mysite</code>), never the full <code>mysite.pages.dev</code> URL. Also confirm the project lives in the <em>same</em> Cloudflare account whose Account ID you pasted.</dd>

			<dt><code>Test FAIL</code> with an authentication / HTTP 403 message</dt>
			<dd>The API token is wrong, expired, or under-scoped. Create one with exactly <strong>Account &middot; Cloudflare Pages &middot; Edit</strong> and <em>Account Resources</em> including the right account, re-paste it (it's shown only once at creation), and Save.</dd>

			<dt><code>Upload token request failed: ...</code></dt>
			<dd>The connection can test OK with a read-only token, but deploying needs write access. Recreate the token with <strong>Cloudflare Pages &middot; Edit</strong> (not Read).</dd>
		</dl>

		<h3>Rebuild won't start or won't finish</h3>
		<dl class="sforge-faq">
			<dt>"Rebuild + Deploy Now" or auto-deploy does nothing — no new log lines</dt>
			<dd>The rebuild runs on a WordPress scheduled event a few seconds after you click, so it depends on <strong>WP-Cron</strong>. If <code>DISABLE_WP_CRON</code> is defined, or the site gets almost no traffic, the event may never fire — you'll see <code>Full rebuild queued (manual).</code> but never <code>Full rebuild started.</code> Fixes: load any front-end page to nudge WP-Cron, or run a real system cron hitting <code>wp-cron.php</code> every minute.</dd>

			<dt><code>No URLs to export. Check post type / scope settings.</code></dt>
			<dd>No post types are ticked under <strong>Export Scope</strong>, or nothing is published in the selected types. Tick at least one post type (and/or Homepage / Taxonomies / Authors) and confirm you have published content.</dd>

			<dt><code>Render fail &lt;url&gt;: HTTP 401 / 403 / 5xx ...</code></dt>
			<dd>The plugin fetches your own URLs via <code>wp_remote_get</code>. A handful of failures is usually harmless; many means the site is blocking itself — HTTP basic auth, an IP allow-list, an aggressive WAF, Cloudflare "Under Attack" mode, or a coming-soon / maintenance plugin. Let the origin fetch itself (or pause the blocker during deploys). For an invalid / self-signed origin cert during migration, add <code>add_filter( 'sforge_sslverify', '__return_false' );</code>.</dd>

			<dt><code>Nothing rendered, deploy skipped.</code></dt>
			<dd>Every page failed to render, so there was nothing to upload — almost always the same self-fetch block as above. Check the <code>Render fail</code> lines just above this one for the HTTP code.</dd>

			<dt>Stuck on <code>Hashing files...</code> / <code>Manifest: ... files</code></dt>
			<dd>PHP ran out of memory or hit the time limit while encoding the upload. The plugin already requests <code>set_time_limit(0)</code> and <code>memory_limit 512M</code>, but shared hosts can override that. Raise <code>memory_limit</code> (256&ndash;512MB) and <code>max_execution_time</code> via <code>php.ini</code> or your host panel.</dd>
		</dl>

		<h3>Deploy step errors (Cloudflare API)</h3>
		<dl class="sforge-faq">
			<dt><code>Deploy FAIL: Request body is incorrect</code></dt>
			<dd>An old build sent the deployment as URL-encoded form data. v1.0.0+ sends <code>multipart/form-data</code>, which Cloudflare requires — update the plugin.</dd>

			<dt><code>Asset upload failed: ...</code></dt>
			<dd>Usually a single file over Cloudflare Pages' <strong>25&nbsp;MiB</strong> per-file limit (a large video / PDF in uploads), or a network timeout on a big batch. Remove or relocate oversized media and host it elsewhere.</dd>

			<dt><code>Deployment failed: ...</code></dt>
			<dd>A Cloudflare-side rejection; the exact reason is quoted in the log. Common cause: more than <strong>20,000 files</strong> in one deployment (CF Pages free-tier limit). Trim Export Scope, or split a very large site.</dd>

			<dt><code>check-missing failed: ...</code></dt>
			<dd>A transient Cloudflare API hiccup or a token problem mid-deploy. Re-run <strong>Rebuild + Deploy Now</strong>; if it persists, re-test the connection — the token may have been revoked.</dd>
		</dl>

		<h3>Live site looks wrong</h3>
		<dl class="sforge-faq">
			<dt>Images broken on the live site (or only <em>some</em> show)</dt>
			<dd>By default <code>/wp-content/*</code> URLs (including uploads) keep pointing at your WordPress origin, so the origin must be reachable over HTTPS with a valid cert — proxy that subdomain through Cloudflare (orange cloud) so CF serves a fresh edge cert. If your host blocks Cloudflare (<code>520</code> / <code>522</code> on uploads), tick <strong>Bundle <code>/wp-content/uploads/</code> into deploy</strong> so images ship inside the deploy. If only <em>some</em> broke right after a cutover, it's usually a stale deploy plus a wrong <strong>Site Address</strong> (see <em>Login &amp; DNS cutover</em> above) — fix that, then Rebuild + Deploy Now.</dd>

			<dt><code>&lt;project&gt;.pages.dev</code> doesn't redirect to my domain</dt>
			<dd>The redirect is a client-side JS snippet (the Direct Upload API can't run <code>_worker.js</code> / Functions), so <code>curl -I</code> won't show it — test in a real browser. It only fires when <strong>Public Site URL</strong> is a real domain (not a <code>.pages.dev</code> URL) and the <strong>Redirect *.pages.dev to live host</strong> toggle is on.</dd>

			<dt>Live site shows <code>noindex</code></dt>
			<dd>WordPress &rarr; Settings &rarr; Reading: leave <strong>"Discourage search engines"</strong> UNCHECKED on the dashboard. The plugin scrubs <code>noindex</code> / <code>nofollow</code> meta during render, but that toggle also changes how SEO plugins build the sitemap.</dd>

			<dt>Contact forms don't send on the live site</dt>
			<dd>Cloudflare Pages is static — no PHP / WordPress runtime — so anything posting to <code>admin-ajax.php</code> or <code>/wp-json/</code> (Contact Form 7, WPForms, Gravity Forms) silently fails. Point the form at a static-friendly endpoint (a Cloudflare Pages Function / Worker, Formspree, Basin, Web3Forms). A form posting to a <em>different</em> host is left untouched and keeps working.</dd>

			<dt>Duplicate SEO meta or schema in <code>&lt;head&gt;</code></dt>
			<dd>An SEO plugin we don't auto-detect is also injecting tags. Two-tier dedup covers Yoast, Rank Math, AIO SEO, SEOPress, The SEO Framework, Slim SEO, Squirrly, SmartCrawl, WP Meta SEO (general SEO plugins → all injection paused) and Schema &amp; Structured Data for WP &amp; AMP, Schema Pro, WPSSO, Schema by Hesham, Schema App, Magazine3 Schema (schema-only plugins → only JSON-LD paused). For niche plugins, extend detection via the <code>sforge_seo_competing_plugin</code> or <code>sforge_schema_competing_plugin</code> filter, or simply untick <strong>Inject SEO meta</strong>.</dd>

			<dt>FAQ / HowTo schema not appearing</dt>
			<dd>FAQ schema needs Yoast / Rank Math / SEOPress FAQ blocks OR <code>&lt;details&gt;&lt;summary&gt;Question&lt;/summary&gt;Answer&lt;/details&gt;</code> markup in the content. HowTo needs a Yoast or Rank Math HowTo block, OR a title starting with "How to" + a numbered list with 3+ items. Use the <code>sforge_faq_items</code> / <code>sforge_howto_data</code> filters to inject manually.</dd>
		</dl>

		<h3>Sitemaps &amp; multilingual</h3>
		<dl class="sforge-faq">
			<dt>Sub-sitemaps missing on deploy</dt>
			<dd>v1.0.0+ handles CDATA-wrapped <code>&lt;loc&gt;</code> entries in sitemap-index files. Earlier builds skipped them — update and redeploy.</dd>

			<dt><code>Sitemap fallback skipped: no post types / archives selected ...</code></dt>
			<dd>Your origin exposes no sitemap, so the plugin tried to generate one — but everything is unticked under <strong>Sitemap Generator</strong>. Tick at least one post type / Homepage / Taxonomies / Authors.</dd>

			<dt><code>Sitemap fallback returned no files.</code></dt>
			<dd>The generator ran but matched no published URLs. Confirm you have published content in the selected sitemap post types.</dd>

			<dt>TranslatePress languages aren't on the live site</dt>
			<dd>If the log notes that secondary languages are on <strong>separate subdomains / domains</strong>, that's expected — one Cloudflare Pages project serves one hostname. Switch TranslatePress to <strong>subdirectory</strong> mode (<code>/fr/</code>, <code>/de/</code>) and Rebuild; all languages then ship in the one deploy.</dd>

			<dt><code>Bundle uploads enabled, but no /wp-content/uploads/ references found ...</code></dt>
			<dd>An image optimiser is swapping image URLs with JavaScript (e.g. EWWW <strong>Lazy Load</strong> or <strong>Easy IO</strong>), so the real URLs aren't in the rendered HTML for the bundler to find. Turn off the JS lazy-load / Easy-IO feature (keep the compression) and Rebuild.</dd>
		</dl>

		<h3>Limits &amp; frequency</h3>
		<dl class="sforge-faq">
			<dt>Hit ~100 deployments per day</dt>
			<dd>Free Cloudflare Pages tier soft cap. Raise the plugin's <em>Debounce</em> setting from 120s to e.g. 600s so rapid bulk edits collapse into fewer deploys.</dd>
		</dl>
	</section>

	<p class="sforge-help-credits">
		<strong>StaticForge for Cloudflare Pages</strong> v<?php echo esc_html( SFORGE_VERSION ); ?> &mdash;
		built by <a href="https://www.gunjanjaswal.me" target="_blank" rel="noopener">Gunjan Jaswal</a> &middot;
		<a href="mailto:hello@gunjanjaswal.me">hello@gunjanjaswal.me</a>
	</p>
</div>
