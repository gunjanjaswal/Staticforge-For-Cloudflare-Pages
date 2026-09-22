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
		<?php esc_html_e( 'StaticForge for Cloudflare Pages — Setup Guide', 'staticforge-for-cloudflare-pages' ); ?>
	</h1>
	<p class="sforge-help-lead">
		<?php esc_html_e( 'Auto-export your WordPress site as static HTML and deploy to Cloudflare Pages on every publish or update. Follow the steps below in order — first run takes about 10 minutes.', 'staticforge-for-cloudflare-pages' ); ?>
	</p>

	<div class="sforge-help-layout">
		<nav class="sforge-help-nav" aria-label="<?php esc_attr_e( 'Setup guide sections', 'staticforge-for-cloudflare-pages' ); ?>">
			<div class="sforge-help-nav-inner">
				<p class="sforge-help-nav-title"><?php esc_html_e( 'On this page', 'staticforge-for-cloudflare-pages' ); ?></p>
				<span class="sforge-help-nav-group"><?php esc_html_e( 'Get started', 'staticforge-for-cloudflare-pages' ); ?></span>
				<a href="#sforge-step1"><span class="sforge-nav-badge">1</span> <?php esc_html_e( 'Cloudflare Pages project', 'staticforge-for-cloudflare-pages' ); ?></a>
				<a href="#sforge-step2"><span class="sforge-nav-badge">2</span> <?php esc_html_e( 'API token', 'staticforge-for-cloudflare-pages' ); ?></a>
				<a href="#sforge-step3"><span class="sforge-nav-badge">3</span> <?php esc_html_e( 'Account ID', 'staticforge-for-cloudflare-pages' ); ?></a>
				<a href="#sforge-step4"><span class="sforge-nav-badge">4</span> <?php esc_html_e( 'Plugin settings', 'staticforge-for-cloudflare-pages' ); ?></a>
				<a href="#sforge-step5"><span class="sforge-nav-badge">5</span> <?php esc_html_e( 'First deploy', 'staticforge-for-cloudflare-pages' ); ?></a>
				<a href="#sforge-step6"><span class="sforge-nav-badge">6</span> <?php esc_html_e( 'DNS cutover', 'staticforge-for-cloudflare-pages' ); ?></a>
				<span class="sforge-help-nav-group"><?php esc_html_e( 'Add-ons', 'staticforge-for-cloudflare-pages' ); ?></span>
				<a href="#sforge-forms"><span class="sforge-nav-badge sforge-nav-badge-plus">+</span> <?php esc_html_e( 'Forms (email on submit)', 'staticforge-for-cloudflare-pages' ); ?></a>
				<a href="#sforge-wpcontent"><span class="sforge-nav-badge sforge-nav-badge-plus">+</span> <?php esc_html_e( 'Clean /wp-content/ URLs', 'staticforge-for-cloudflare-pages' ); ?></a>
				<a href="#sforge-bundle-uploads"><span class="sforge-nav-badge sforge-nav-badge-plus">+</span> <?php esc_html_e( 'Bundle uploads (shared hosting)', 'staticforge-for-cloudflare-pages' ); ?></a>
				<span class="sforge-help-nav-group"><?php esc_html_e( 'Help', 'staticforge-for-cloudflare-pages' ); ?></span>
				<a href="#sforge-trouble"><span class="sforge-nav-badge sforge-nav-badge-plus">?</span> <?php esc_html_e( 'Troubleshooting', 'staticforge-for-cloudflare-pages' ); ?></a>
			</div>
		</nav>

		<div class="sforge-help-content">

	<section class="sforge-card sforge-card-blue" id="sforge-step1">
		<h2><span class="sforge-num">1</span> <?php esc_html_e( 'Create a Cloudflare Pages project (Direct Upload mode)', 'staticforge-for-cloudflare-pages' ); ?></h2>
		<ol>
			<li><?php echo wp_kses_post( __( 'Login to <a href="https://dash.cloudflare.com" target="_blank" rel="noopener">dash.cloudflare.com</a>.', 'staticforge-for-cloudflare-pages' ) ); ?></li>
			<li><?php echo wp_kses_post( __( 'Sidebar &rarr; <strong>Workers &amp; Pages</strong> &rarr; click <strong>Create application</strong> (top right).', 'staticforge-for-cloudflare-pages' ) ); ?></li>
			<li><?php echo wp_kses_post( __( 'Switch to the <strong>Pages</strong> tab on the next screen.', 'staticforge-for-cloudflare-pages' ) ); ?></li>
			<li><?php echo wp_kses_post( __( 'Click <strong>Upload assets</strong> (NOT "Connect to Git").', 'staticforge-for-cloudflare-pages' ) ); ?></li>
			<li><?php echo wp_kses_post( __( '<strong>Project name:</strong> use a short lowercase slug, e.g. <code>mysite</code>. This becomes <code>https://&lt;name&gt;.pages.dev</code>.', 'staticforge-for-cloudflare-pages' ) ); ?></li>
			<li><?php echo wp_kses_post( __( 'Drag-drop any tiny placeholder file (a one-line <code>index.html</code> works) just to seed the project. The plugin will overwrite it on first real deploy.', 'staticforge-for-cloudflare-pages' ) ); ?></li>
			<li><?php echo wp_kses_post( __( 'Click <strong>Deploy site</strong>. Once it lands, copy the project URL — that\'s your <em>Public Site URL</em> for testing.', 'staticforge-for-cloudflare-pages' ) ); ?></li>
		</ol>
	</section>

	<section class="sforge-card sforge-card-purple" id="sforge-step2">
		<h2><span class="sforge-num">2</span> <?php esc_html_e( 'Create an API Token', 'staticforge-for-cloudflare-pages' ); ?></h2>
		<ol>
			<li><?php echo wp_kses_post( __( 'Top-right avatar &rarr; <strong>My Profile</strong> &rarr; <strong>API Tokens</strong>.', 'staticforge-for-cloudflare-pages' ) ); ?></li>
			<li><?php echo wp_kses_post( __( 'Click <strong>Create Token</strong>.', 'staticforge-for-cloudflare-pages' ) ); ?></li>
			<li><?php echo wp_kses_post( __( 'Scroll down &rarr; under <em>Custom token</em> click <strong>Get started</strong>.', 'staticforge-for-cloudflare-pages' ) ); ?></li>
			<li><?php echo wp_kses_post( __( 'Token name: <code>StaticForge for Cloudflare Pages</code>.', 'staticforge-for-cloudflare-pages' ) ); ?></li>
			<li><?php echo wp_kses_post( __( 'Permissions: <strong>Account &middot; Cloudflare Pages &middot; Edit</strong>', 'staticforge-for-cloudflare-pages' ) ); ?></li>
			<li><?php echo wp_kses_post( __( 'Account Resources: <strong>Include &rarr; &lt;your account&gt;</strong>.', 'staticforge-for-cloudflare-pages' ) ); ?></li>
			<li><?php esc_html_e( 'Leave Client IP filter and TTL blank.', 'staticforge-for-cloudflare-pages' ); ?></li>
			<li><?php echo wp_kses_post( __( 'Click <strong>Continue to summary</strong> &rarr; <strong>Create Token</strong>.', 'staticforge-for-cloudflare-pages' ) ); ?></li>
			<li><?php echo wp_kses_post( __( '<strong>Copy the token now</strong> — Cloudflare shows it only once.', 'staticforge-for-cloudflare-pages' ) ); ?></li>
		</ol>
		<p class="sforge-callout sforge-callout-warn">
			<?php echo wp_kses_post( __( '<strong>Security:</strong> rotate the token if it ever leaks. Never paste it into public chats, screenshots, or commits.', 'staticforge-for-cloudflare-pages' ) ); ?>
		</p>
	</section>

	<section class="sforge-card sforge-card-green" id="sforge-step3">
		<h2><span class="sforge-num">3</span> <?php esc_html_e( 'Find your Account ID', 'staticforge-for-cloudflare-pages' ); ?></h2>
		<ol>
			<li><?php echo wp_kses_post( __( 'Cloudflare Dashboard &rarr; <strong>Workers &amp; Pages</strong> overview, OR any zone\'s overview.', 'staticforge-for-cloudflare-pages' ) ); ?></li>
			<li><?php echo wp_kses_post( __( 'Right sidebar &rarr; <strong>API</strong> section &rarr; copy <strong>Account ID</strong>.', 'staticforge-for-cloudflare-pages' ) ); ?></li>
		</ol>
		<p><?php esc_html_e( 'Format: 32-character hex string.', 'staticforge-for-cloudflare-pages' ); ?></p>
	</section>

	<section class="sforge-card sforge-card-orange" id="sforge-step4">
		<h2><span class="sforge-num">4</span> <?php esc_html_e( 'Configure the plugin', 'staticforge-for-cloudflare-pages' ); ?></h2>
		<p>
			<?php
			/* translators: %s: link to the plugin Settings page. */
			printf( wp_kses_post( __( 'Open %s and fill in:', 'staticforge-for-cloudflare-pages' ) ), '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'StaticForge for Cloudflare Pages → Settings', 'staticforge-for-cloudflare-pages' ) . '</a>' );
			?>
		</p>
		<table class="sforge-help-table">
			<tr><th><?php esc_html_e( 'Field', 'staticforge-for-cloudflare-pages' ); ?></th><th><?php esc_html_e( 'Value', 'staticforge-for-cloudflare-pages' ); ?></th></tr>
			<tr><td><?php esc_html_e( 'Account ID', 'staticforge-for-cloudflare-pages' ); ?></td><td><?php esc_html_e( 'From step 3', 'staticforge-for-cloudflare-pages' ); ?></td></tr>
			<tr><td><?php esc_html_e( 'API Token', 'staticforge-for-cloudflare-pages' ); ?></td><td><?php esc_html_e( 'From step 2', 'staticforge-for-cloudflare-pages' ); ?></td></tr>
			<tr><td><?php esc_html_e( 'Pages Project', 'staticforge-for-cloudflare-pages' ); ?></td><td><?php echo wp_kses_post( __( 'The slug only, e.g. <code>mysite</code> (NOT the .pages.dev URL)', 'staticforge-for-cloudflare-pages' ) ); ?></td></tr>
			<tr><td><?php esc_html_e( 'Branch', 'staticforge-for-cloudflare-pages' ); ?></td><td><?php echo wp_kses_post( __( '<code>main</code> for production. Anything else creates a preview deployment.', 'staticforge-for-cloudflare-pages' ) ); ?></td></tr>
			<tr><td><?php esc_html_e( 'Public Site URL', 'staticforge-for-cloudflare-pages' ); ?></td><td><?php echo wp_kses_post( __( '<code>https://&lt;name&gt;.pages.dev</code> while testing. Switch to <code>https://yourdomain.com</code> after DNS cutover.', 'staticforge-for-cloudflare-pages' ) ); ?></td></tr>
			<tr><td><?php esc_html_e( 'Post Types', 'staticforge-for-cloudflare-pages' ); ?></td><td><?php echo wp_kses_post( __( 'Tick the types to export. <code>post</code> + <code>page</code> + any custom types.', 'staticforge-for-cloudflare-pages' ) ); ?></td></tr>
			<tr><td><?php esc_html_e( 'Include', 'staticforge-for-cloudflare-pages' ); ?></td><td><?php esc_html_e( 'Tick all three: Homepage, Taxonomies, Authors.', 'staticforge-for-cloudflare-pages' ); ?></td></tr>
			<tr><td><?php esc_html_e( 'Inline CSS', 'staticforge-for-cloudflare-pages' ); ?></td><td><?php echo wp_kses_post( __( 'Tick &mdash; embeds linked stylesheets so each page is self-contained.', 'staticforge-for-cloudflare-pages' ) ); ?></td></tr>
			<tr><td><?php esc_html_e( 'Auto-deploy', 'staticforge-for-cloudflare-pages' ); ?></td><td><?php esc_html_e( 'Tick to redeploy on publish/update.', 'staticforge-for-cloudflare-pages' ); ?></td></tr>
			<tr><td><?php esc_html_e( 'Debounce', 'staticforge-for-cloudflare-pages' ); ?></td><td><?php echo wp_kses_post( __( '<code>120</code> seconds. Rapid edits collapse into one deploy.', 'staticforge-for-cloudflare-pages' ) ); ?></td></tr>
			<tr><td><?php esc_html_e( 'robots.txt', 'staticforge-for-cloudflare-pages' ); ?></td><td><?php echo wp_kses_post( __( 'Leave blank to auto-generate, or paste a custom version. <code>Sitemap:</code> line is appended automatically.', 'staticforge-for-cloudflare-pages' ) ); ?></td></tr>
		</table>
		<p><?php echo wp_kses_post( __( 'Save Settings &rarr; click <strong>Test Connection</strong>. Expect "Connection OK" notice and a log entry within a few seconds.', 'staticforge-for-cloudflare-pages' ) ); ?></p>
	</section>

	<section class="sforge-card sforge-card-pink" id="sforge-step5">
		<h2><span class="sforge-num">5</span> <?php esc_html_e( 'First deploy', 'staticforge-for-cloudflare-pages' ); ?></h2>
		<ol>
			<li><?php echo wp_kses_post( __( 'Click <strong>Rebuild + Deploy Now</strong>.', 'staticforge-for-cloudflare-pages' ) ); ?></li>
			<li><?php esc_html_e( 'The activity log refreshes live. Expected sequence:', 'staticforge-for-cloudflare-pages' ); ?>
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
					<li><?php echo wp_kses_post( __( '<code>Deploy OK [&lt;id&gt;]</code> with link to the deployment URL.', 'staticforge-for-cloudflare-pages' ) ); ?></li>
				</ul>
			</li>
			<li><?php echo wp_kses_post( __( 'Open the deployment link &rarr; verify homepage, a post, sitemap.xml, robots.txt all look right.', 'staticforge-for-cloudflare-pages' ) ); ?></li>
		</ol>
		<p class="sforge-callout sforge-callout-info">
			<?php echo wp_kses_post( __( '<strong>Note:</strong> media files under <code>/wp-content/uploads/</code> are NOT bundled in the deploy. Image URLs are kept pointing to your WordPress origin — they keep working as long as origin is reachable.', 'staticforge-for-cloudflare-pages' ) ); ?>
		</p>
	</section>

	<section class="sforge-card sforge-card-teal" id="sforge-step6">
		<h2><span class="sforge-num">6</span> <?php esc_html_e( 'DNS cutover (optional, when ready)', 'staticforge-for-cloudflare-pages' ); ?></h2>
		<ol>
			<li><?php echo wp_kses_post( __( 'Add your apex domain (e.g. <code>example.com</code>) to Cloudflare. Update registrar nameservers.', 'staticforge-for-cloudflare-pages' ) ); ?></li>
			<li><?php echo wp_kses_post( __( 'In your CF Pages project &rarr; <strong>Custom Domains</strong> &rarr; add <code>example.com</code> + <code>www.example.com</code>. CF auto-creates the right DNS records and provisions an edge SSL cert.', 'staticforge-for-cloudflare-pages' ) ); ?></li>
			<li><?php esc_html_e( 'Recommended DNS layout:', 'staticforge-for-cloudflare-pages' ); ?>
				<table class="sforge-help-table">
					<tr><th><?php esc_html_e( 'Record', 'staticforge-for-cloudflare-pages' ); ?></th><th><?php esc_html_e( 'Name', 'staticforge-for-cloudflare-pages' ); ?></th><th><?php esc_html_e( 'Target', 'staticforge-for-cloudflare-pages' ); ?></th><th><?php esc_html_e( 'Proxy', 'staticforge-for-cloudflare-pages' ); ?></th></tr>
					<tr><td>CNAME</td><td>@ (apex)</td><td><code>&lt;project&gt;.pages.dev</code></td><td><?php esc_html_e( 'orange', 'staticforge-for-cloudflare-pages' ); ?></td></tr>
					<tr><td>CNAME</td><td>www</td><td><code>&lt;project&gt;.pages.dev</code></td><td><?php esc_html_e( 'orange', 'staticforge-for-cloudflare-pages' ); ?></td></tr>
					<tr><td>A</td><td>dashboard</td><td><?php esc_html_e( 'your origin server IP', 'staticforge-for-cloudflare-pages' ); ?></td><td><?php esc_html_e( 'orange', 'staticforge-for-cloudflare-pages' ); ?></td></tr>
				</table>
			</li>
			<li><?php echo wp_kses_post( __( 'Add a CF Bulk Redirect <code>www.example.com/*</code> &rarr; <code>https://example.com/$1</code> 301.', 'staticforge-for-cloudflare-pages' ) ); ?></li>
			<li><?php echo wp_kses_post( __( 'Update plugin\'s <em>Public Site URL</em> to the final domain &rarr; Save &rarr; <strong>Rebuild + Deploy Now</strong> &mdash; canonicals + sitemap entries now point to the live domain.', 'staticforge-for-cloudflare-pages' ) ); ?></li>
		</ol>
	</section>

	<section class="sforge-card sforge-card-purple" id="sforge-wpcontent">
		<h2><span class="sforge-num">+</span> <?php echo wp_kses_post( __( 'Clean <code>/wp-content/</code> URLs (advanced)', 'staticforge-for-cloudflare-pages' ) ); ?></h2>
		<p>
			<?php echo wp_kses_post( __( 'By default the plugin leaves <code>&lt;origin&gt;/wp-content/...</code> URLs alone (theme CSS/JS, plugin assets, media uploads) so they keep working without bundling multi-gigabyte folders into every deploy. That means structured data such as <code>og:image</code>, JSON-LD <code>image</code>/<code>logo</code>/<code>thumbnailUrl</code>, and HTML <code>&lt;img src&gt;</code>/<code>srcset</code> will still reference your <strong>dashboard host</strong> on the deployed site.', 'staticforge-for-cloudflare-pages' ) ); ?>
		</p>
		<p>
			<?php echo wp_kses_post( __( 'If you want fully clean URLs on the live site, set up a proxy and turn on <strong>Export Scope &rarr; Rewrite <code>/wp-content/</code> URLs</strong>.', 'staticforge-for-cloudflare-pages' ) ); ?>
		</p>

		<h3><?php echo wp_kses_post( __( 'Option A &mdash; Cloudflare Worker (recommended)', 'staticforge-for-cloudflare-pages' ) ); ?></h3>
		<ol>
			<li><?php echo wp_kses_post( __( 'Cloudflare Dashboard &rarr; <strong>Workers &amp; Pages</strong> &rarr; <strong>Create application</strong> &rarr; <strong>Workers</strong> &rarr; <strong>Hello World</strong> template.', 'staticforge-for-cloudflare-pages' ) ); ?></li>
			<li><?php echo wp_kses_post( __( 'Name it <code>wp-content-proxy</code> &rarr; Deploy &rarr; <strong>Edit code</strong>.', 'staticforge-for-cloudflare-pages' ) ); ?></li>
			<li><?php esc_html_e( 'Replace the default code with:', 'staticforge-for-cloudflare-pages' ); ?>
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
				<?php echo wp_kses_post( __( 'Replace <code>dashboard.example.com</code> with your actual dashboard host.', 'staticforge-for-cloudflare-pages' ) ); ?>
			</li>
			<li><?php echo wp_kses_post( __( 'Click <strong>Deploy</strong>.', 'staticforge-for-cloudflare-pages' ) ); ?></li>
			<li><?php echo wp_kses_post( __( 'Worker &rarr; <strong>Settings</strong> &rarr; <strong>Triggers</strong> &rarr; <strong>Add route</strong>: <code>example.com/wp-content/*</code> (and add a second route for <code>www.example.com/wp-content/*</code> if you serve both).', 'staticforge-for-cloudflare-pages' ) ); ?></li>
			<li><?php echo wp_kses_post( __( 'In the plugin: tick <strong>Export Scope &rarr; Rewrite <code>/wp-content/</code> URLs</strong> &rarr; Save &rarr; <strong>Rebuild + Deploy Now</strong>.', 'staticforge-for-cloudflare-pages' ) ); ?></li>
		</ol>

		<h3><?php echo wp_kses_post( __( 'Option B &mdash; Nginx / Apache reverse proxy', 'staticforge-for-cloudflare-pages' ) ); ?></h3>
		<p><?php esc_html_e( 'If your live site is served from your own server:', 'staticforge-for-cloudflare-pages' ); ?></p>
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

		<h3><?php esc_html_e( 'Verify', 'staticforge-for-cloudflare-pages' ); ?></h3>
		<ol>
			<li><?php echo wp_kses_post( __( '<code>curl -I https://example.com/wp-content/uploads/<em>any-file</em>.jpg</code> &rarr; expect HTTP 200 with image content-type.', 'staticforge-for-cloudflare-pages' ) ); ?></li>
			<li><?php esc_html_e( 'Rebuild + Deploy Now.', 'staticforge-for-cloudflare-pages' ); ?></li>
			<li><?php echo wp_kses_post( __( 'View source of any deployed post &rarr; <code>og:image</code>, JSON-LD <code>image</code>/<code>logo</code>, and <code>&lt;img src&gt;</code> all reference your live host (<code>example.com</code>), not the dashboard.', 'staticforge-for-cloudflare-pages' ) ); ?></li>
		</ol>

		<p class="sforge-callout sforge-callout-warn">
			<?php echo wp_kses_post( __( '<strong>If the toggle is on but proxy is NOT set up:</strong> every image / theme stylesheet / plugin script on the live site will return 404. Verify the curl test above before flipping the toggle on a production site.', 'staticforge-for-cloudflare-pages' ) ); ?>
		</p>
	</section>

	<section class="sforge-card sforge-card-green" id="sforge-bundle-uploads">
		<h2><span class="sforge-num">+</span> <?php echo wp_kses_post( __( 'Bundle <code>/wp-content/uploads/</code> into deploy (shared hosting)', 'staticforge-for-cloudflare-pages' ) ); ?></h2>
		<p>
			<?php echo wp_kses_post( __( 'Use this when the Worker / Nginx proxy approach above doesn\'t work because your origin\'s firewall blocks Cloudflare. Common on shared cPanel hosts (HostArmada, SiteGround, GoDaddy, Bluehost, etc.) where you can\'t whitelist Cloudflare\'s edge IPs. Symptoms: <code>curl -I https://example.com/wp-content/uploads/file.jpg</code> returns <code>HTTP/1.1 520</code> or <code>522</code> while the direct dashboard URL works.', 'staticforge-for-cloudflare-pages' ) ); ?>
		</p>

		<h3><?php esc_html_e( 'What it does', 'staticforge-for-cloudflare-pages' ); ?></h3>
		<p>
			<?php echo wp_kses_post( __( 'Tick <strong>Export Scope &rarr; Bundle <code>/wp-content/uploads/</code> into deploy</strong> (leave <strong>Rewrite <code>/wp-content/</code> URLs</strong> off). On the next rebuild the plugin:', 'staticforge-for-cloudflare-pages' ) ); ?>
		</p>
		<ol>
			<li><?php echo wp_kses_post( __( 'Scans every rendered page for <code>/wp-content/uploads/...</code> references &mdash; <code>&lt;img src&gt;</code>, <code>srcset</code>, <code>og:image</code>, JSON-LD <code>image</code>/<code>logo</code>/<code>thumbnailUrl</code>, inline CSS <code>url(...)</code>, oEmbed thumbnails (literal, JSON-escaped, and percent-encoded forms).', 'staticforge-for-cloudflare-pages' ) ); ?></li>
			<li><?php esc_html_e( 'Fetches each unique file from the WordPress origin during rebuild.', 'staticforge-for-cloudflare-pages' ); ?></li>
			<li><?php echo wp_kses_post( __( 'Uploads them inside the Cloudflare Pages deploy at their original <code>/wp-content/uploads/...</code> paths.', 'staticforge-for-cloudflare-pages' ) ); ?></li>
			<li><?php esc_html_e( 'Rewrites image URLs in the rendered HTML / JSON-LD to the live host so the static site is fully self-contained.', 'staticforge-for-cloudflare-pages' ); ?></li>
		</ol>

		<h3><?php esc_html_e( 'What still loads from origin', 'staticforge-for-cloudflare-pages' ); ?></h3>
		<p>
			<?php echo wp_kses_post( __( 'Theme CSS/JS and plugin assets (everything under <code>/wp-content/themes/</code> and <code>/wp-content/plugins/</code>) keep loading from the WordPress dashboard host as before. Those rarely cause shared-hosting firewall issues, and bundling them on every deploy would balloon the upload size for no SEO benefit.', 'staticforge-for-cloudflare-pages' ) ); ?>
		</p>

		<h3><?php esc_html_e( 'Cost / size considerations', 'staticforge-for-cloudflare-pages' ); ?></h3>
		<p>
			<?php echo wp_kses_post( __( 'Only files <em>referenced</em> from exported pages get bundled &mdash; not the entire media library. Cloudflare\'s <code>check-missing</code> API deduplicates unchanged files by content hash, so subsequent rebuilds only upload new or modified images. If your origin uses <a href="https://wordpress.org/plugins/webp-express/" target="_blank" rel="noopener">WebP Express</a> or similar (serving <code>.webp</code> via headers), the plugin captures whichever extension your origin actually returns in the rendered HTML.', 'staticforge-for-cloudflare-pages' ) ); ?>
		</p>

		<h3><?php esc_html_e( 'Verify after deploy', 'staticforge-for-cloudflare-pages' ); ?></h3>
		<ol>
			<li><?php echo wp_kses_post( __( 'Watch the activity log &mdash; expect <code>Bundling N /wp-content/uploads/ file(s) into deploy...</code> then <code>Asset bundle done: X ok, Y failed, Z MB total</code>.', 'staticforge-for-cloudflare-pages' ) ); ?></li>
			<li><?php echo wp_kses_post( __( '<code>curl -I https://example.com/wp-content/uploads/<em>any-file</em>.jpg</code> &rarr; expect <code>HTTP/1.1 200 OK</code> with <code>Server: cloudflare</code> and a <code>cf-ray</code> header (served by CF Pages directly, not your origin).', 'staticforge-for-cloudflare-pages' ) ); ?></li>
			<li><?php echo wp_kses_post( __( 'View any deployed post source &rarr; <code>og:image</code>, JSON-LD image fields, and <code>&lt;img src&gt;</code> / <code>srcset</code> all point at the live host.', 'staticforge-for-cloudflare-pages' ) ); ?></li>
		</ol>

		<p class="sforge-callout sforge-callout-info">
			<?php echo wp_kses_post( __( '<strong>Which option do I pick?</strong> If your origin proxies cleanly through Cloudflare (VPS / dedicated / fully managed with Worker route accepting CF subrequests), use the Worker/Nginx proxy above &mdash; uploads stay on the dashboard, deploys stay tiny. If your origin is shared hosting and you keep getting 520/522 from the Worker route, use this bundle option instead.', 'staticforge-for-cloudflare-pages' ) ); ?>
		</p>

		<p class="sforge-callout sforge-callout-warn">
			<?php echo wp_kses_post( __( '<strong>Ignored when "Rewrite <code>/wp-content/</code> URLs" is on.</strong> That setting rewrites everything (themes, plugins, uploads) and assumes you have a full proxy. The bundle option only matters when the broader rewrite toggle is off.', 'staticforge-for-cloudflare-pages' ) ); ?>
		</p>
	</section>

	<section class="sforge-card sforge-card-blue" id="sforge-forms">
		<h2><span class="sforge-num">+</span> <?php esc_html_e( 'Forms that email you on submit', 'staticforge-for-cloudflare-pages' ); ?></h2>
		<p>
			<?php echo wp_kses_post( __( 'A static site can\'t run PHP, and the Direct Upload deploy can\'t run Cloudflare Functions &mdash; so StaticForge deploys a tiny <strong>standalone Cloudflare Worker</strong> that takes the submitted fields and hands them to <em>your own</em> email API. Your API key is stored as a Worker secret, never written into the page or sent to the browser.', 'staticforge-for-cloudflare-pages' ) ); ?>
		</p>
		<p class="sforge-callout sforge-callout-warn">
			<?php echo wp_kses_post( __( '<strong>One-time setup:</strong> your Cloudflare API token needs the <strong>Account &middot; Workers Scripts &middot; Edit</strong> permission (in addition to Cloudflare Pages &middot; Edit), and the account needs a free <code>workers.dev</code> subdomain &mdash; claimed once by opening <strong>Workers &amp; Pages</strong> in the Cloudflare dashboard.', 'staticforge-for-cloudflare-pages' ) ); ?>
		</p>
		<ol>
			<li><?php echo wp_kses_post( __( 'Sign up with an email provider and create an API key &mdash; <a href="https://resend.com" target="_blank" rel="noopener">Resend</a> is the quickest, and SendGrid, Postmark and Mailgun work too. Verify your sending domain in their dashboard.', 'staticforge-for-cloudflare-pages' ) ); ?></li>
			<li><?php echo wp_kses_post( __( 'In <strong>Settings &rarr; Forms</strong>, tick <strong>Enable forms</strong> and pick your provider &mdash; the endpoint, auth header and a request-body template are filled in for you.', 'staticforge-for-cloudflare-pages' ) ); ?></li>
			<li><?php echo wp_kses_post( __( 'Edit the body template so <code>to</code> / <code>from</code> are your real addresses. Tokens like <code>{{name}}</code>, <code>{{email}}</code>, <code>{{message}}</code> are filled from the submission; <code>{{all_fields}}</code> expands to the whole message.', 'staticforge-for-cloudflare-pages' ) ); ?></li>
			<li><?php echo wp_kses_post( __( '(Optional) tick <strong>Turnstile</strong> and paste the site key for a real bot check. A honeypot field is always on regardless.', 'staticforge-for-cloudflare-pages' ) ); ?></li>
			<li><?php echo wp_kses_post( __( 'Save, then in <strong>Form Handler Deployment</strong> paste your API key and click <strong>Deploy form handler</strong>. The Worker URL appears once it lands.', 'staticforge-for-cloudflare-pages' ) ); ?></li>
			<li><?php echo wp_kses_post( __( 'Drop <code>[sforge_form]</code> into any page or post, then run a rebuild so the page ships. Optional attributes: <code>[sforge_form button="Send" success="Thanks!"]</code>.', 'staticforge-for-cloudflare-pages' ) ); ?></li>
		</ol>
		<p class="sforge-callout sforge-callout-info">
			<?php echo wp_kses_post( __( '<strong>Prefer not to widen the token?</strong> You can skip the Worker entirely and point a plain HTML form at an outside service (Formspree, Web3Forms, Basin) instead &mdash; a form posting to a different host is left untouched on the static site. The built-in handler is the tidier option when you want to keep everything on your own Cloudflare account and email API.', 'staticforge-for-cloudflare-pages' ) ); ?>
		</p>
	</section>

	<section class="sforge-card sforge-card-red" id="sforge-trouble">
		<h2><?php esc_html_e( 'Troubleshooting', 'staticforge-for-cloudflare-pages' ); ?></h2>
		<p class="sforge-help-lead" style="margin-top:0"><?php echo wp_kses_post( __( 'Grouped by where it happens. The <code>code-styled</code> phrases are the exact <strong>Activity Log</strong> messages, so you can match what you see.', 'staticforge-for-cloudflare-pages' ) ); ?></p>

		<h3><?php echo wp_kses_post( __( 'Login &amp; DNS cutover', 'staticforge-for-cloudflare-pages' ) ); ?></h3>
		<dl class="sforge-faq">
			<dt><?php esc_html_e( 'After DNS cutover, wp-admin bounces to the live site — can\'t log in or redeploy', 'staticforge-for-cloudflare-pages' ); ?></dt>
			<dd>
				<?php
				/* translators: %s: example encoded wp-login redirect URL, shown as code. */
				printf( wp_kses_post( __( '<strong>Symptom:</strong> opening wp-admin throws you to the <em>public</em> host\'s login, e.g. %s — but <code>example.com</code> is now the static Cloudflare site with no WordPress on it, so login fails and you can\'t reach this page to redeploy.', 'staticforge-for-cloudflare-pages' ) ), '<code>https://example.com/wp-login.php?redirect_to=https%3A%2F%2Fdashboard.example.com%2Fwp-admin%2F...</code>' );
				?><br><br>
				<?php echo wp_kses_post( __( '<strong>Cause:</strong> WordPress\'s own <strong>WP Address</strong> (<code>siteurl</code>) / <strong>Site Address</strong> (<code>home</code>) still point at the public host instead of your dashboard host. WordPress builds the login URL from <code>siteurl</code>, so it sends you to the static site.', 'staticforge-for-cloudflare-pages' ) ); ?><br><br>
				<?php echo wp_kses_post( __( '<strong>Fix:</strong> pin both to the dashboard host in <code>wp-config.php</code> (add just above <code>/* That\'s all, stop editing! Happy publishing. */</code>):', 'staticforge-for-cloudflare-pages' ) ); ?>
<pre><code>define( 'WP_HOME',    'https://dashboard.example.com' );
define( 'WP_SITEURL', 'https://dashboard.example.com' );</code></pre>
				<?php echo wp_kses_post( __( 'Save, then log in from a private/incognito window at <code>https://dashboard.example.com/wp-admin</code>.', 'staticforge-for-cloudflare-pages' ) ); ?>
				<br><br>
				<?php echo wp_kses_post( __( '<strong>Keep them separate:</strong> WordPress lives on <code>dashboard.example.com</code>; the plugin\'s <strong>Public Site URL</strong> stays the public host (<code>https://example.com</code>). The renderer rewrites the dashboard host &rarr; Public Site URL during export, so the static site still shows clean public links. Only WordPress\'s own two addresses move to the dashboard subdomain — never the plugin\'s Public Site URL.', 'staticforge-for-cloudflare-pages' ) ); ?>
			</dd>

		</dl>

		<h3><?php echo wp_kses_post( __( 'Setup &amp; connection (Test Connection)', 'staticforge-for-cloudflare-pages' ) ); ?></h3>
		<dl class="sforge-faq">
			<dt><code>Test FAIL: Account ID, API token and project name are required</code></dt>
			<dd><?php esc_html_e( 'One of the three credential fields is blank. Fill Account ID, API Token, and Pages Project, Save, then test again.', 'staticforge-for-cloudflare-pages' ); ?></dd>

			<dt><code>Test FAIL: Project not found</code></dt>
			<dd><?php echo wp_kses_post( __( 'The <strong>Pages Project</strong> field must be the project <em>slug</em> only (e.g. <code>mysite</code>), never the full <code>mysite.pages.dev</code> URL. Also confirm the project lives in the <em>same</em> Cloudflare account whose Account ID you pasted.', 'staticforge-for-cloudflare-pages' ) ); ?></dd>

			<dt><?php echo wp_kses_post( __( '<code>Test FAIL</code> with an authentication / HTTP 403 message', 'staticforge-for-cloudflare-pages' ) ); ?></dt>
			<dd><?php echo wp_kses_post( __( 'The API token is wrong, expired, or under-scoped. Create one with exactly <strong>Account &middot; Cloudflare Pages &middot; Edit</strong> and <em>Account Resources</em> including the right account, re-paste it (it\'s shown only once at creation), and Save.', 'staticforge-for-cloudflare-pages' ) ); ?></dd>

			<dt><code>Upload token request failed: ...</code></dt>
			<dd><?php echo wp_kses_post( __( 'The connection can test OK with a read-only token, but deploying needs write access. Recreate the token with <strong>Cloudflare Pages &middot; Edit</strong> (not Read).', 'staticforge-for-cloudflare-pages' ) ); ?></dd>
		</dl>

		<h3><?php esc_html_e( 'Rebuild won\'t start or won\'t finish', 'staticforge-for-cloudflare-pages' ); ?></h3>
		<dl class="sforge-faq">
			<dt><?php esc_html_e( '"Rebuild + Deploy Now" or auto-deploy does nothing — no new log lines', 'staticforge-for-cloudflare-pages' ); ?></dt>
			<dd><?php echo wp_kses_post( __( 'The rebuild runs on a WordPress scheduled event a few seconds after you click, so it depends on <strong>WP-Cron</strong>. If <code>DISABLE_WP_CRON</code> is defined, or the site gets almost no traffic, the event may never fire — you\'ll see <code>Full rebuild queued (manual).</code> but never <code>Full rebuild started.</code> Fixes: load any front-end page to nudge WP-Cron, or run a real system cron hitting <code>wp-cron.php</code> every minute.', 'staticforge-for-cloudflare-pages' ) ); ?></dd>

			<dt><code>No URLs to export. Check post type / scope settings.</code></dt>
			<dd><?php echo wp_kses_post( __( 'No post types are ticked under <strong>Export Scope</strong>, or nothing is published in the selected types. Tick at least one post type (and/or Homepage / Taxonomies / Authors) and confirm you have published content.', 'staticforge-for-cloudflare-pages' ) ); ?></dd>

			<dt><code>Render fail &lt;url&gt;: HTTP 401 / 403 / 5xx ...</code></dt>
			<dd><?php echo wp_kses_post( __( 'The plugin fetches your own URLs via <code>wp_remote_get</code>. A handful of failures is usually harmless; many means the site is blocking itself — HTTP basic auth, an IP allow-list, an aggressive WAF, Cloudflare "Under Attack" mode, or a coming-soon / maintenance plugin. Let the origin fetch itself (or pause the blocker during deploys). For an invalid / self-signed origin cert during migration, add <code>add_filter( \'sforge_sslverify\', \'__return_false\' );</code>.', 'staticforge-for-cloudflare-pages' ) ); ?></dd>

			<dt><code>Nothing rendered, deploy skipped.</code></dt>
			<dd><?php echo wp_kses_post( __( 'Every page failed to render, so there was nothing to upload — almost always the same self-fetch block as above. Check the <code>Render fail</code> lines just above this one for the HTTP code.', 'staticforge-for-cloudflare-pages' ) ); ?></dd>

			<dt><?php echo wp_kses_post( __( 'Stuck on <code>Hashing files...</code> / <code>Manifest: ... files</code>', 'staticforge-for-cloudflare-pages' ) ); ?></dt>
			<dd><?php echo wp_kses_post( __( 'PHP ran out of memory or hit the time limit while encoding the upload. The plugin already requests <code>set_time_limit(0)</code> and <code>memory_limit 512M</code>, but shared hosts can override that. Raise <code>memory_limit</code> (256&ndash;512MB) and <code>max_execution_time</code> via <code>php.ini</code> or your host panel.', 'staticforge-for-cloudflare-pages' ) ); ?></dd>
		</dl>

		<h3><?php esc_html_e( 'Deploy step errors (Cloudflare API)', 'staticforge-for-cloudflare-pages' ); ?></h3>
		<dl class="sforge-faq">
			<dt><code>Deploy FAIL: Request body is incorrect</code></dt>
			<dd><?php echo wp_kses_post( __( 'An old build sent the deployment as URL-encoded form data. v1.0.0+ sends <code>multipart/form-data</code>, which Cloudflare requires — update the plugin.', 'staticforge-for-cloudflare-pages' ) ); ?></dd>

			<dt><code>Skipping "&lt;file&gt;" (NN MB): exceeds Cloudflare's 25 MiB per-file limit</code></dt>
			<dd><?php echo wp_kses_post( __( 'Cloudflare Pages rejects any single file above <strong>25&nbsp;MiB</strong>. The plugin now catches these before the upload, names the file in the log, leaves it out, and deploys everything else. Host the file externally (Cloudflare R2, an object store, a CDN) and link to it, or shrink it below 25&nbsp;MiB.', 'staticforge-for-cloudflare-pages' ) ); ?></dd>

			<dt><code>Asset upload failed: HTTP 4xx/5xx ...</code></dt>
			<dd><?php echo wp_kses_post( __( 'Cloudflare rejected the upload. The log quotes the HTTP status when Cloudflare returns no structured error (older builds showed a bare <code>unknown</code>). <code>HTTP 413</code> means a file too large got through; a 5xx or a timeout on a big batch is usually transient &mdash; re-run <strong>Rebuild + Deploy Now</strong>.', 'staticforge-for-cloudflare-pages' ) ); ?></dd>

			<dt><code>Deployment failed: ...</code></dt>
			<dd><?php echo wp_kses_post( __( 'A Cloudflare-side rejection; the exact reason is quoted in the log. Common cause: more than <strong>20,000 files</strong> in one deployment (CF Pages free-tier limit). Trim Export Scope, or split a very large site.', 'staticforge-for-cloudflare-pages' ) ); ?></dd>

			<dt><code>check-missing failed: ...</code></dt>
			<dd><?php echo wp_kses_post( __( 'A transient Cloudflare API hiccup or a token problem mid-deploy. Re-run <strong>Rebuild + Deploy Now</strong>; if it persists, re-test the connection — the token may have been revoked.', 'staticforge-for-cloudflare-pages' ) ); ?></dd>

			<dt><?php echo wp_kses_post( __( 'A page is huge (tens of MB) and blows past the 25&nbsp;MiB limit &mdash; Elementor especially', 'staticforge-for-cloudflare-pages' ) ); ?></dt>
			<dd><?php echo wp_kses_post( __( 'Almost always a missing stylesheet that <strong>soft-404s</strong>: instead of a real 404, the server returns the homepage with a <code>200 OK</code> status. With <strong>Inline CSS</strong> on, the plugin asks for that file expecting CSS, gets a whole HTML page, and embeds it into a <code>&lt;style&gt;</code> block &mdash; a few of those and the page balloons into the tens of MB. The usual culprit is Elementor\'s cached Google Fonts (<code>wp-content/uploads/elementor/google-fonts/css/roboto-&lt;host&gt;.css</code>), which goes missing when that cache was generated under a different hostname (common after a migration or when the dashboard is on a subdomain). Since 1.8.4 the plugin refuses to inline a response that comes back as HTML, so the bloat is fixed. To also restore the fonts, go to <strong>Elementor &rarr; Tools &rarr; Regenerate CSS &amp; Data</strong>, then <strong>Rebuild + Deploy Now</strong>.', 'staticforge-for-cloudflare-pages' ) ); ?></dd>
		</dl>

		<h3><?php esc_html_e( 'Live site looks wrong', 'staticforge-for-cloudflare-pages' ); ?></h3>
		<dl class="sforge-faq">
			<dt><?php echo wp_kses_post( __( 'Images broken on the live site (or only <em>some</em> show)', 'staticforge-for-cloudflare-pages' ) ); ?></dt>
			<dd><?php echo wp_kses_post( __( 'By default <code>/wp-content/*</code> URLs (including uploads) keep pointing at your WordPress origin, so the origin must be reachable over HTTPS with a valid cert — proxy that subdomain through Cloudflare (orange cloud) so CF serves a fresh edge cert. If your host blocks Cloudflare (<code>520</code> / <code>522</code> on uploads), tick <strong>Bundle <code>/wp-content/uploads/</code> into deploy</strong> so images ship inside the deploy. If only <em>some</em> broke right after a cutover, it\'s usually a stale deploy plus a wrong <strong>Site Address</strong> (see <em>Login &amp; DNS cutover</em> above) — fix that, then Rebuild + Deploy Now.', 'staticforge-for-cloudflare-pages' ) ); ?></dd>

			<dt><?php echo wp_kses_post( __( '<code>&lt;project&gt;.pages.dev</code> doesn\'t redirect to my domain', 'staticforge-for-cloudflare-pages' ) ); ?></dt>
			<dd><?php echo wp_kses_post( __( 'The redirect is a client-side JS snippet (the Direct Upload API can\'t run <code>_worker.js</code> / Functions), so <code>curl -I</code> won\'t show it — test in a real browser. It only fires when <strong>Public Site URL</strong> is a real domain (not a <code>.pages.dev</code> URL) and the <strong>Redirect *.pages.dev to live host</strong> toggle is on.', 'staticforge-for-cloudflare-pages' ) ); ?></dd>

			<dt><?php echo wp_kses_post( __( 'Live site shows <code>noindex</code>', 'staticforge-for-cloudflare-pages' ) ); ?></dt>
			<dd><?php echo wp_kses_post( __( 'WordPress &rarr; Settings &rarr; Reading: leave <strong>"Discourage search engines"</strong> UNCHECKED on the dashboard. The plugin scrubs <code>noindex</code> / <code>nofollow</code> meta during render, but that toggle also changes how SEO plugins build the sitemap.', 'staticforge-for-cloudflare-pages' ) ); ?></dd>

			<dt><?php esc_html_e( 'Contact forms don\'t send on the live site', 'staticforge-for-cloudflare-pages' ); ?></dt>
			<dd><?php echo wp_kses_post( __( 'Cloudflare Pages is static — no PHP / WordPress runtime — so anything posting to <code>admin-ajax.php</code> or <code>/wp-json/</code> (Contact Form 7, WPForms, Gravity Forms) silently fails. Use the built-in handler instead: <strong>Settings &rarr; Forms</strong> deploys a small Worker and gives you the <code>[sforge_form]</code> shortcode (see <em>Forms that email you on submit</em> above). A form posting to a <em>different</em> host (Formspree, Basin, Web3Forms) is left untouched and keeps working.', 'staticforge-for-cloudflare-pages' ) ); ?></dd>

			<dt><?php echo wp_kses_post( __( 'Duplicate SEO meta or schema in <code>&lt;head&gt;</code>', 'staticforge-for-cloudflare-pages' ) ); ?></dt>
			<dd><?php echo wp_kses_post( __( 'An SEO plugin we don\'t auto-detect is also injecting tags. Two-tier dedup covers Yoast, Rank Math, AIO SEO, SEOPress, The SEO Framework, Slim SEO, Squirrly, SmartCrawl, WP Meta SEO (general SEO plugins → all injection paused) and Schema &amp; Structured Data for WP &amp; AMP, Schema Pro, WPSSO, Schema by Hesham, Schema App, Magazine3 Schema (schema-only plugins → only JSON-LD paused). For niche plugins, extend detection via the <code>sforge_seo_competing_plugin</code> or <code>sforge_schema_competing_plugin</code> filter, or simply untick <strong>Inject SEO meta</strong>.', 'staticforge-for-cloudflare-pages' ) ); ?></dd>

			<dt><?php esc_html_e( 'FAQ / HowTo schema not appearing', 'staticforge-for-cloudflare-pages' ); ?></dt>
			<dd><?php echo wp_kses_post( __( 'FAQ schema needs Yoast / Rank Math / SEOPress FAQ blocks OR <code>&lt;details&gt;&lt;summary&gt;Question&lt;/summary&gt;Answer&lt;/details&gt;</code> markup in the content. HowTo needs a Yoast or Rank Math HowTo block, OR a title starting with "How to" + a numbered list with 3+ items. Use the <code>sforge_faq_items</code> / <code>sforge_howto_data</code> filters to inject manually.', 'staticforge-for-cloudflare-pages' ) ); ?></dd>
		</dl>

		<h3><?php echo wp_kses_post( __( 'Sitemaps &amp; multilingual', 'staticforge-for-cloudflare-pages' ) ); ?></h3>
		<dl class="sforge-faq">
			<dt><?php esc_html_e( 'Sub-sitemaps missing on deploy', 'staticforge-for-cloudflare-pages' ); ?></dt>
			<dd><?php echo wp_kses_post( __( 'v1.0.0+ handles CDATA-wrapped <code>&lt;loc&gt;</code> entries in sitemap-index files. Earlier builds skipped them — update and redeploy.', 'staticforge-for-cloudflare-pages' ) ); ?></dd>

			<dt><code>Sitemap fallback skipped: no post types / archives selected ...</code></dt>
			<dd><?php echo wp_kses_post( __( 'Your origin exposes no sitemap, so the plugin tried to generate one — but everything is unticked under <strong>Sitemap Generator</strong>. Tick at least one post type / Homepage / Taxonomies / Authors.', 'staticforge-for-cloudflare-pages' ) ); ?></dd>

			<dt><code>Sitemap fallback returned no files.</code></dt>
			<dd><?php esc_html_e( 'The generator ran but matched no published URLs. Confirm you have published content in the selected sitemap post types.', 'staticforge-for-cloudflare-pages' ); ?></dd>

			<dt><?php esc_html_e( 'TranslatePress languages aren\'t on the live site', 'staticforge-for-cloudflare-pages' ); ?></dt>
			<dd><?php echo wp_kses_post( __( 'If the log notes that secondary languages are on <strong>separate subdomains / domains</strong>, that\'s expected — one Cloudflare Pages project serves one hostname. Switch TranslatePress to <strong>subdirectory</strong> mode (<code>/fr/</code>, <code>/de/</code>) and Rebuild; all languages then ship in the one deploy.', 'staticforge-for-cloudflare-pages' ) ); ?></dd>

			<dt><code>Bundle uploads enabled, but no /wp-content/uploads/ references found ...</code></dt>
			<dd><?php echo wp_kses_post( __( 'An image optimiser is swapping image URLs with JavaScript (e.g. EWWW <strong>Lazy Load</strong> or <strong>Easy IO</strong>), so the real URLs aren\'t in the rendered HTML for the bundler to find. Turn off the JS lazy-load / Easy-IO feature (keep the compression) and Rebuild.', 'staticforge-for-cloudflare-pages' ) ); ?></dd>
		</dl>

		<h3><?php echo wp_kses_post( __( 'Limits &amp; frequency', 'staticforge-for-cloudflare-pages' ) ); ?></h3>
		<dl class="sforge-faq">
			<dt><?php esc_html_e( 'Hit ~100 deployments per day', 'staticforge-for-cloudflare-pages' ); ?></dt>
			<dd><?php echo wp_kses_post( __( 'Free Cloudflare Pages tier soft cap. Raise the plugin\'s <em>Debounce</em> setting from 120s to e.g. 600s so rapid bulk edits collapse into fewer deploys.', 'staticforge-for-cloudflare-pages' ) ); ?></dd>
		</dl>
	</section>

	<p class="sforge-help-credits">
		<?php
		/* translators: 1: plugin version, 2: author name linked to their website. */
		printf( wp_kses_post( __( '<strong>StaticForge for Cloudflare Pages</strong> v%1$s &mdash; built by %2$s', 'staticforge-for-cloudflare-pages' ) ), esc_html( SFORGE_VERSION ), '<a href="https://www.gunjanjaswal.me" target="_blank" rel="noopener">Gunjan Jaswal</a>' );
		?> &middot;
		<a href="mailto:hello@gunjanjaswal.me">hello@gunjanjaswal.me</a>
	</p>

		</div><!-- .sforge-help-content -->
	</div><!-- .sforge-help-layout -->
</div>
