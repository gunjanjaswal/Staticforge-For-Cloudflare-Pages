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

	<section class="sforge-card sforge-card-red" id="sforge-trouble">
		<h2>Troubleshooting</h2>
		<dl class="sforge-faq">
			<dt>Duplicate SEO meta or schema in <code>&lt;head&gt;</code></dt>
			<dd>An SEO plugin we don't auto-detect is also injecting tags. Two-tier dedup covers Yoast, Rank Math, AIO SEO, SEOPress, The SEO Framework, Slim SEO, Squirrly, SmartCrawl, WP Meta SEO (general SEO plugins → all injection paused) and Schema &amp; Structured Data for WP &amp; AMP, Schema Pro, WPSSO, Schema by Hesham, Schema App, Magazine3 Schema (schema-only plugins → only JSON-LD paused). For niche plugins, extend detection via <code>sforge_seo_competing_plugin</code> or <code>sforge_schema_competing_plugin</code> filter, or simply untick <strong>Inject SEO meta</strong>.</dd>

			<dt>FAQ / HowTo schema not appearing</dt>
			<dd>FAQ schema needs Yoast / Rank Math / SEOPress FAQ blocks OR <code>&lt;details&gt;&lt;summary&gt;Question&lt;/summary&gt;Answer&lt;/details&gt;</code> markup in post content. HowTo needs a Yoast or Rank Math HowTo block, OR a title starting with "How to" + a numbered list with 3+ items. Use the <code>sforge_faq_items</code> / <code>sforge_howto_data</code> filters to inject manually.</dd>

			<dt><code>Test FAIL: Project not found</code></dt>
			<dd>You typed the full <code>name.pages.dev</code> URL into the Pages Project field. Use the slug only, e.g. <code>mysite</code>.</dd>

			<dt><code>Deploy FAIL: Request body is incorrect</code></dt>
			<dd>Update the plugin to v1.0.0+ &mdash; older builds sent the deployment POST as URL-encoded form. v1.0.0 sends multipart/form-data, which Cloudflare requires.</dd>

			<dt>Deploy gets stuck on <code>Manifest: ... files</code></dt>
			<dd>The upload step is hanging. Check PHP <code>memory_limit</code> (need 256MB+) and <code>max_execution_time</code> (need 0 or large value). The plugin already requests <code>set_time_limit(0)</code> and 512MB but shared hosts may override.</dd>

			<dt>Render fails with HTTP 5xx for self-fetch</dt>
			<dd>Plugin fetches your own URLs via <code>wp_remote_get</code>. If the site sits behind basic auth, IP whitelist, or aggressive WAF, allow your origin IP back to itself, or filter <code>sforge_sslverify</code>.</dd>

			<dt>Sub-sitemaps missing on deploy</dt>
			<dd>v1.0.0+ handles CDATA-wrapped <code>&lt;loc&gt;</code> entries. Earlier builds skipped them. Update + redeploy.</dd>

			<dt>Images broken on deployed site</dt>
			<dd>Plugin keeps <code>/wp-content/*</code> URLs pointing at your origin host. Ensure the origin's SSL cert is valid (or proxy that subdomain through Cloudflare so CF terminates a fresh edge cert).</dd>

			<dt>Live site shows <code>noindex</code></dt>
			<dd>WordPress &rarr; Settings &rarr; Reading: leave "Discourage search engines" UNCHECKED on the dashboard. Plugin scrubs any <code>noindex</code>/<code>nofollow</code> meta directives during render, but turning the WP toggle on can also affect SEO plugins' sitemap behaviour.</dd>

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
