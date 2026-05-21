<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local template variables, not globals.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$o = SFORGE_Settings::get();
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only flash flag after admin-post redirect; no state change.
$msg = isset( $_GET['sforge_msg'] ) ? sanitize_key( wp_unslash( $_GET['sforge_msg'] ) ) : '';
$help_url = admin_url( 'admin.php?page=sforge-help' );
$is_unconfigured = empty( $o['account_id'] ) || empty( $o['api_token'] ) || empty( $o['project_name'] );
$injector = new SFORGE_Seo_Injector();
$has_general_seo = $injector->general_seo_plugin_active();
$has_schema_seo  = $injector->schema_plugin_active();
?>
<div class="wrap sforge-wrap">

	<div class="sforge-hero">
		<div class="sforge-hero-main">
			<div class="sforge-hero-icon"><span class="dashicons dashicons-cloud-upload"></span></div>
			<div>
				<h1 class="sforge-hero-title">StaticForge for Cloudflare Pages
					<span class="sforge-version-pill">v<?php echo esc_html( SFORGE_VERSION ); ?></span>
				</h1>
				<p class="sforge-hero-sub">
					Auto-export your WordPress site as static HTML &mdash; deploy to Cloudflare Pages on every publish.
				</p>
			</div>
		</div>
		<div class="sforge-hero-actions">
			<a href="<?php echo esc_url( $help_url ); ?>" class="button button-secondary">
				<span class="dashicons dashicons-book-alt"></span> Setup Guide
			</a>
		</div>
	</div>

	<?php if ( $msg === 'test_ok' ) : ?>
		<div class="notice notice-success is-dismissible sforge-notice"><p><strong>Connection OK.</strong> Cloudflare Pages project is reachable.</p></div>
	<?php elseif ( $msg === 'test_fail' ) : ?>
		<div class="notice notice-error is-dismissible sforge-notice"><p><strong>Connection failed.</strong> See the activity log below for details.</p></div>
	<?php elseif ( $msg === 'rebuild_scheduled' ) : ?>
		<div class="notice notice-success is-dismissible sforge-notice"><p><strong>Full rebuild queued.</strong> The activity log refreshes live &mdash; watch for progress in the next few seconds.</p></div>
	<?php endif; ?>

	<?php if ( $is_unconfigured ) : ?>
		<div class="sforge-cta">
			<div class="sforge-cta-icon"><span class="dashicons dashicons-info-outline"></span></div>
			<div class="sforge-cta-body">
				<strong>First time? Read the Setup Guide.</strong>
				<span>Walk-through for creating a Cloudflare Pages project, generating an API token, and configuring this plugin.</span>
			</div>
			<a href="<?php echo esc_url( $help_url ); ?>" class="button button-primary">Open Setup Guide</a>
		</div>
	<?php endif; ?>

	<form method="post" action="options.php" class="sforge-form">
		<?php settings_fields( 'sforge_group' ); ?>

		<section class="sforge-section">
			<header class="sforge-section-head">
				<span class="sforge-section-icon sforge-section-icon-blue"><span class="dashicons dashicons-cloud"></span></span>
				<div>
					<h2>Cloudflare</h2>
					<p>Connect to your Cloudflare Pages project via the Direct Upload API.</p>
				</div>
			</header>
			<div class="sforge-section-body">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="sforge_account_id">Account ID</label></th>
						<td>
							<input type="text" id="sforge_account_id" name="<?php echo esc_attr( SFORGE_OPT ); ?>[account_id]" value="<?php echo esc_attr( $o['account_id'] ?? '' ); ?>" class="regular-text code" autocomplete="off" placeholder="e.g. a2709493ed708e84df53c91fa354c230" />
							<p class="description">Cloudflare Dashboard &rarr; right sidebar of any zone or Workers &amp; Pages overview.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sforge_api_token">API Token</label></th>
						<td>
							<input type="password" id="sforge_api_token" name="<?php echo esc_attr( SFORGE_OPT ); ?>[api_token]" value="<?php echo esc_attr( $o['api_token'] ?? '' ); ?>" class="regular-text code" autocomplete="new-password" />
							<p class="description">Create at <code>My Profile &rarr; API Tokens</code>. Required permission: <code>Account &middot; Cloudflare Pages &middot; Edit</code>.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sforge_project_name">Pages Project</label></th>
						<td>
							<input type="text" id="sforge_project_name" name="<?php echo esc_attr( SFORGE_OPT ); ?>[project_name]" value="<?php echo esc_attr( $o['project_name'] ?? '' ); ?>" class="regular-text code" placeholder="my-site" />
							<p class="description">The slug only (e.g. <code>my-site</code>), <strong>not</strong> the <code>.pages.dev</code> URL. Create in the CF dashboard with the Direct Upload option.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sforge_branch">Branch</label></th>
						<td>
							<input type="text" id="sforge_branch" name="<?php echo esc_attr( SFORGE_OPT ); ?>[branch]" value="<?php echo esc_attr( $o['branch'] ?? 'main' ); ?>" class="small-text code" />
							<p class="description"><code>main</code> = production deployment. Anything else creates a preview deployment.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sforge_cf_pages_url">Public Site URL</label></th>
						<td>
							<input type="url" id="sforge_cf_pages_url" name="<?php echo esc_attr( SFORGE_OPT ); ?>[cf_pages_url]" value="<?php echo esc_attr( $o['cf_pages_url'] ?? '' ); ?>" class="regular-text" placeholder="https://my-site.pages.dev or https://www.example.com" />
							<p class="description">Where the static site lives publicly. Used to rewrite WP origin URLs in exported HTML and sitemap entries.</p>
						</td>
					</tr>
				</table>
			</div>
		</section>

		<section class="sforge-section">
			<header class="sforge-section-head">
				<span class="sforge-section-icon sforge-section-icon-green"><span class="dashicons dashicons-filter"></span></span>
				<div>
					<h2>Export Scope</h2>
					<p>Choose which post types and archives to export.</p>
				</div>
			</header>
			<div class="sforge-section-body">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Post Types</th>
						<td>
							<div class="sforge-cb-grid">
								<?php
								$types = get_post_types( [ 'public' => true ], 'objects' );
								$sel   = (array) ( $o['post_types'] ?? [] );
								foreach ( $types as $pt ) {
									printf(
										'<label class="sforge-cb"><input type="checkbox" name="%s[post_types][]" value="%s" %s> <strong>%s</strong> <code>%s</code></label>',
										esc_attr( SFORGE_OPT ),
										esc_attr( $pt->name ),
										in_array( $pt->name, $sel, true ) ? 'checked' : '',
										esc_html( $pt->label ),
										esc_html( $pt->name )
									);
								}
								?>
							</div>
							<p class="description">All published items of selected types are exported.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Include</th>
						<td>
							<div class="sforge-cb-grid">
								<label class="sforge-cb"><input type="checkbox" name="<?php echo esc_attr( SFORGE_OPT ); ?>[include_homepage]" value="1" <?php checked( ! empty( $o['include_homepage'] ) ); ?>> <strong>Homepage</strong></label>
								<label class="sforge-cb"><input type="checkbox" name="<?php echo esc_attr( SFORGE_OPT ); ?>[include_taxonomies]" value="1" <?php checked( ! empty( $o['include_taxonomies'] ) ); ?>> <strong>Taxonomy archives</strong> <code>cat / tag / custom</code></label>
								<label class="sforge-cb"><input type="checkbox" name="<?php echo esc_attr( SFORGE_OPT ); ?>[include_authors]" value="1" <?php checked( ! empty( $o['include_authors'] ) ); ?>> <strong>Author archives</strong></label>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row">Inline CSS</th>
						<td>
							<label class="sforge-cb"><input type="checkbox" name="<?php echo esc_attr( SFORGE_OPT ); ?>[inline_css]" value="1" <?php checked( ! empty( $o['inline_css'] ) ); ?>> Embed all linked stylesheets into each page</label>
							<p class="description">Self-contained pages, no external CSS requests.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Redirect <code>*.pages.dev</code> to live host</th>
						<td>
							<label class="sforge-cb"><input type="checkbox" name="<?php echo esc_attr( SFORGE_OPT ); ?>[redirect_pages_dev]" value="1" <?php checked( ! empty( $o['redirect_pages_dev'] ) ); ?>> 301-redirect any request hitting <code>&lt;project&gt;.pages.dev</code> to the canonical Public Site URL</label>
							<p class="description">
								Adds a Cloudflare Pages Function (<code>functions/_middleware.js</code>) to the deploy that intercepts requests with a <code>.pages.dev</code> hostname and 301-redirects them to your <strong>Public Site URL</strong> (preserving path + query string). Stops Google from indexing the preview URL alongside your real domain.
								Automatically skipped when Public Site URL itself is a <code>.pages.dev</code> URL (e.g. while you're still testing pre-DNS cutover).
								Counts as a Cloudflare Workers request &mdash; free tier includes 100k/day, sparse <code>.pages.dev</code> traffic costs effectively nothing.
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Rewrite <code>/wp-content/</code> URLs</th>
						<td>
							<label class="sforge-cb sforge-cb-danger"><input type="checkbox" name="<?php echo esc_attr( SFORGE_OPT ); ?>[rewrite_wpcontent]" value="1" <?php checked( ! empty( $o['rewrite_wpcontent'] ) ); ?>> Also rewrite <code>/wp-content/</code> URLs (uploads, themes, plugin assets) to the live host</label>
							<p class="description">
								<strong>Default: OFF.</strong> By default, <code>&lt;origin&gt;/wp-content/...</code> URLs are kept pointing at your WordPress origin so media, theme CSS/JS, and plugin assets keep working without bundling gigabytes of files in every deploy.<br>
								<strong>Turn ON only if you've arranged your own proxy / mirror / CDN for <code>/wp-content/*</code> on the live host</strong> &mdash; e.g. a Cloudflare Worker rewriting <code>https://example.com/wp-content/*</code> &rarr; <code>https://dashboard.example.com/wp-content/*</code>, or an Nginx reverse-proxy, or a separate CDN domain. Otherwise images, theme styles and scripts will 404 on the deployed site.<br>
								When ON, schema URLs (<code>og:image</code>, JSON-LD <code>image</code>/<code>logo</code>, <code>thumbnailUrl</code>) and HTML <code>src</code>/<code>srcset</code> point to the live host instead of the dashboard.
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Bundle <code>/wp-content/uploads/</code> into deploy</th>
						<td>
							<label class="sforge-cb"><input type="checkbox" name="<?php echo esc_attr( SFORGE_OPT ); ?>[bundle_uploads]" value="1" <?php checked( ! empty( $o['bundle_uploads'] ) ); ?>> Fetch every <code>/wp-content/uploads/</code> file referenced by the rendered HTML and ship it alongside the static pages</label>
							<p class="description">
								<strong>Default: OFF.</strong> Softer alternative to the option above &mdash; only media files (uploads) get rewritten to the live host and bundled into the CF Pages deploy. Theme &amp; plugin assets (CSS/JS/fonts) still load from the WordPress origin.<br>
								Use this when your origin can't be reached from Cloudflare Workers / proxies (shared hosting firewalls, IP allow-lists, etc.). Each rebuild downloads only files referenced from the rendered pages, so cost scales with what's actually used, not the full media library.<br>
								Ignored when "Rewrite <code>/wp-content/</code> URLs" above is ON (that setting already rewrites everything).
							</p>
						</td>
					</tr>
				</table>
			</div>
		</section>

		<section class="sforge-section">
			<header class="sforge-section-head">
				<span class="sforge-section-icon sforge-section-icon-teal"><span class="dashicons dashicons-performance"></span></span>
				<div>
					<h2>Performance</h2>
					<p>Core Web Vitals tweaks applied to the rendered HTML.</p>
				</div>
			</header>
			<div class="sforge-section-body">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Featured image priority</th>
						<td>
							<label class="sforge-cb"><input type="checkbox" name="<?php echo esc_attr( SFORGE_OPT ); ?>[featured_image_priority]" value="1" <?php checked( ! empty( $o['featured_image_priority'] ) ); ?>> Add <code>fetchpriority="high"</code> to the post's featured image</label>
							<p class="description">
								Also sets <code>loading="eager"</code> and <code>decoding="async"</code>. Helps the browser identify the LCP element earlier &mdash; improves Core Web Vitals.
								Works with any theme that uses <code>the_post_thumbnail()</code> or <code>get_the_post_thumbnail()</code>.
							</p>
						</td>
					</tr>
				</table>
			</div>
		</section>

		<section class="sforge-section">
			<header class="sforge-section-head">
				<span class="sforge-section-icon sforge-section-icon-purple"><span class="dashicons dashicons-search"></span></span>
				<div>
					<h2>SEO Metadata</h2>
					<p>Auto-emit meta tags, Open Graph, Twitter Card, and rich JSON-LD schemas.</p>
				</div>
			</header>
			<div class="sforge-section-body">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Inject SEO meta</th>
						<td>
							<label class="sforge-cb"><input type="checkbox" name="<?php echo esc_attr( SFORGE_OPT ); ?>[seo_inject]" value="1" <?php checked( ! empty( $o['seo_inject'] ) ); ?>> Add baseline SEO tags + JSON-LD schemas to <code>&lt;head&gt;</code></label>

							<div class="sforge-feature-grid">
								<div class="sforge-feature">
									<span class="sforge-feature-icon">📝</span>
									<strong>Meta + Social</strong>
									<small><code>description</code>, <code>robots</code>, <code>canonical</code>, Open Graph (<code>og:type</code>, <code>article:*</code>, <code>profile:*</code>), Twitter Card.</small>
								</div>
								<div class="sforge-feature">
									<span class="sforge-feature-icon">📜</span>
									<strong>Core Schemas</strong>
									<small><code>WebSite</code> + <code>SearchAction</code>, <code>Organization</code>, <code>Article</code>, <code>WebPage</code>, <code>BreadcrumbList</code>, <code>CollectionPage</code>.</small>
								</div>
								<div class="sforge-feature">
									<span class="sforge-feature-icon">👤</span>
									<strong>Author Schema</strong>
									<small><code>Person</code> + <code>ProfilePage</code> on author archives — avatar, bio, sameAs social links.</small>
								</div>
								<div class="sforge-feature">
									<span class="sforge-feature-icon">❓</span>
									<strong>FAQ &amp; HowTo</strong>
									<small>Auto-detected from FAQ / HowTo blocks (Yoast, Rank Math, SEOPress) or HTML5 <code>&lt;details&gt;</code> markup.</small>
								</div>
							</div>

							<?php if ( $has_general_seo ) : ?>
								<div class="sforge-callout sforge-callout-warn">
									<strong>General SEO plugin detected</strong>
									<span>Yoast / Rank Math / AIO SEO / SEOPress / SEO Framework / etc. emits its own complete SEO stack. <strong>All our injection is paused</strong> to avoid duplicate tags.</span>
								</div>
							<?php elseif ( $has_schema_seo ) : ?>
								<div class="sforge-callout sforge-callout-info">
									<strong>Schema-only plugin detected</strong>
									<span>Schema &amp; Structured Data for WP &amp; AMP / Schema Pro / WPSSO / etc. handles JSON-LD. We will <strong>still emit</strong> meta description, robots, canonical, Open Graph, and Twitter Card &mdash; but skip our JSON-LD block to avoid schema duplication.</span>
								</div>
							<?php else : ?>
								<div class="sforge-callout sforge-callout-success">
									<strong>No SEO plugin detected</strong>
									<span>Full injection enabled &mdash; meta, Open Graph, Twitter Card, and all JSON-LD schemas will be emitted.</span>
								</div>
							<?php endif; ?>

							<?php if ( $has_general_seo || $has_schema_seo ) : ?>
								<label class="sforge-cb sforge-cb-danger"><input type="checkbox" name="<?php echo esc_attr( SFORGE_OPT ); ?>[seo_inject_force]" value="1" <?php checked( ! empty( $o['seo_inject_force'] ) ); ?>> <strong>Force full injection anyway</strong> <small>(may produce duplicate tags &mdash; only enable if you've configured the other plugin to skip)</small></label>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row">Profile schema (author pages)</th>
						<td>
							<label class="sforge-cb"><input type="checkbox" name="<?php echo esc_attr( SFORGE_OPT ); ?>[profile_schema]" value="1" <?php checked( ! empty( $o['profile_schema'] ) ); ?>> Emit rich <code>Person</code> + <code>ProfilePage</code> JSON-LD on author archives even when an SEO plugin is active</label>
							<p class="description">
								Adds a fuller author graph with <code>sameAs</code> social URLs (Twitter / X, LinkedIn, Facebook, Instagram, YouTube, GitHub, Pinterest, TikTok, Threads, Medium, Mastodon, Bluesky &mdash; pulled from <code>user_url</code> + matching <code>user_meta</code> keys),
								<code>givenName</code>, <code>familyName</code>, <code>description</code>, avatar <code>ImageObject</code>, and optional <code>jobTitle</code>/<code>worksFor</code> from custom user meta.
								Skipped automatically when this plugin's own SEO injector is already covering author pages (no SEO plugin detected and "Inject SEO meta" is on). Distinct <code>@id</code> suffix prevents conflict with Yoast/Rank Math/etc.
							</p>
						</td>
					</tr>
				</table>
			</div>
		</section>

		<section class="sforge-section">
			<header class="sforge-section-head">
				<span class="sforge-section-icon sforge-section-icon-orange"><span class="dashicons dashicons-admin-network"></span></span>
				<div>
					<h2>robots.txt &amp; Dashboard Indexing</h2>
					<p>Live site robots.txt + dashboard search-engine block.</p>
				</div>
			</header>
			<div class="sforge-section-body">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Block dashboard from search engines</th>
						<td>
							<label class="sforge-cb"><input type="checkbox" name="<?php echo esc_attr( SFORGE_OPT ); ?>[dashboard_block]" value="1" <?php checked( ! empty( $o['dashboard_block'] ) ); ?>> Force <code>noindex</code> on this WordPress install (recommended)</label>
							<p class="description">
								Writes a <code>Disallow: /</code> robots.txt at the webroot (existing file backed up to <code>robots.txt.sforge-backup</code>),
								adds a <code>noindex,nofollow</code> meta robots tag, and emits an <code>X-Robots-Tag</code> HTTP header on every response.
								Plugin's own export fetches are exempt &mdash; deployed pages remain fully indexable.
								Auto-restores on plugin deactivation.
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sforge_robots_txt">Live site robots.txt</label></th>
						<td>
							<textarea id="sforge_robots_txt" name="<?php echo esc_attr( SFORGE_OPT ); ?>[robots_txt]" rows="8" class="large-text code" placeholder="<?php echo esc_attr( SFORGE_Seo::preview_default_robots() ); ?>"><?php echo esc_textarea( $o['robots_txt'] ?? '' ); ?></textarea>
							<p class="description">
								Independent of the dashboard robots.txt above. Leave blank to auto-generate (placeholder shows the default).
								If filled, your <code>Allow:</code> / <code>Disallow:</code> rules are kept verbatim &mdash; the <code>Sitemap:</code> line is always
								<strong>auto-managed</strong> to match the actual deployed sitemap path
								(<code>sitemap.xml</code>, <code>sitemap_index.xml</code>, <code>wp-sitemap.xml</code>, etc.) so the URL in robots.txt always works.
								Any <code>Sitemap:</code> lines you type are stripped and replaced.
								Deployed to <code>&lt;cf-pages-url&gt;/robots.txt</code> on next rebuild.
							</p>
						</td>
					</tr>
				</table>
			</div>
		</section>

		<section class="sforge-section">
			<header class="sforge-section-head">
				<span class="sforge-section-icon sforge-section-icon-green"><span class="dashicons dashicons-networking"></span></span>
				<div>
					<h2>Sitemap Generator</h2>
					<p>What to include when the plugin generates <code>sitemap.xml</code> (origin sitemap missing).</p>
				</div>
			</header>
			<div class="sforge-section-body">
				<p class="description" style="margin-top:8px;">
					If your origin already exposes a sitemap (Yoast / Rank Math / WP core <code>/wp-sitemap.xml</code>), the plugin <em>mirrors</em> it and these settings are ignored.
					Otherwise the plugin builds <code>sitemap.xml</code> itself from the options below.
				</p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Post Types</th>
						<td>
							<div class="sforge-cb-grid">
								<?php
								$smap_types = get_post_types( [ 'public' => true ], 'objects' );
								$smap_sel   = (array) ( $o['sitemap_post_types'] ?? [ 'post', 'page' ] );
								foreach ( $smap_types as $pt ) {
									printf(
										'<label class="sforge-cb"><input type="checkbox" name="%s[sitemap_post_types][]" value="%s" %s> <strong>%s</strong> <code>%s</code></label>',
										esc_attr( SFORGE_OPT ),
										esc_attr( $pt->name ),
										in_array( $pt->name, $smap_sel, true ) ? 'checked' : '',
										esc_html( $pt->label ),
										esc_html( $pt->name )
									);
								}
								?>
							</div>
							<p class="description">All published items of selected types are listed in the sitemap, plus their post-type archive URL where applicable.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Include</th>
						<td>
							<div class="sforge-cb-grid">
								<label class="sforge-cb"><input type="checkbox" name="<?php echo esc_attr( SFORGE_OPT ); ?>[sitemap_homepage]" value="1" <?php checked( ! empty( $o['sitemap_homepage'] ) ); ?>> <strong>Homepage</strong></label>
								<label class="sforge-cb"><input type="checkbox" name="<?php echo esc_attr( SFORGE_OPT ); ?>[sitemap_taxonomies]" value="1" <?php checked( ! empty( $o['sitemap_taxonomies'] ) ); ?>> <strong>Taxonomy archives</strong> <code>cat / tag / custom</code></label>
								<label class="sforge-cb"><input type="checkbox" name="<?php echo esc_attr( SFORGE_OPT ); ?>[sitemap_authors]" value="1" <?php checked( ! empty( $o['sitemap_authors'] ) ); ?>> <strong>Author archives</strong></label>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row">Split into multiple files</th>
						<td>
							<label class="sforge-cb"><input type="checkbox" name="<?php echo esc_attr( SFORGE_OPT ); ?>[sitemap_split]" value="1" <?php checked( ! empty( $o['sitemap_split'] ) ); ?>> Generate a sitemap-index pointing to per-type sub-sitemaps</label>
							<p class="description">
								<strong>Off</strong> (default) &rarr; single <code>sitemap.xml</code> with every URL.<br>
								<strong>On</strong> &rarr; <code>sitemap.xml</code> becomes a <code>&lt;sitemapindex&gt;</code> referencing
								<code>sitemap-post.xml</code>, <code>sitemap-page.xml</code>, <code>sitemap-taxonomy-category.xml</code>,
								<code>sitemap-authors.xml</code>, etc. Cleaner for large sites and better understood by Search Console.
							</p>
						</td>
					</tr>
				</table>
			</div>
		</section>

		<section class="sforge-section">
			<header class="sforge-section-head">
				<span class="sforge-section-icon sforge-section-icon-pink"><span class="dashicons dashicons-update"></span></span>
				<div>
					<h2>Deployment Behaviour</h2>
					<p>When and how the plugin re-deploys on changes.</p>
				</div>
			</header>
			<div class="sforge-section-body">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Auto-deploy</th>
						<td>
							<label class="sforge-cb"><input type="checkbox" name="<?php echo esc_attr( SFORGE_OPT ); ?>[auto_deploy]" value="1" <?php checked( ! empty( $o['auto_deploy'] ) ); ?>> Re-deploy site on publish/update of selected post types</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sforge_debounce">Debounce</label></th>
						<td>
							<input type="number" id="sforge_debounce" name="<?php echo esc_attr( SFORGE_OPT ); ?>[debounce]" min="10" max="3600" value="<?php echo esc_attr( $o['debounce'] ?? 120 ); ?>" class="small-text" /> <span class="sforge-unit">seconds</span>
							<p class="description">Rapid edits within this window collapse into a single deploy. Default <strong>120s</strong> &mdash; comfortable margin under CF Pages free-tier soft cap of ~100 deploys/day.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sforge_export_dir">Local Export Folder</label></th>
						<td>
							<input type="text" id="sforge_export_dir" name="<?php echo esc_attr( SFORGE_OPT ); ?>[export_dir]" value="<?php echo esc_attr( $o['export_dir'] ?? 'sforge-export' ); ?>" class="regular-text code" />
							<p class="description">Inside <code><?php echo esc_html( wp_upload_dir()['basedir'] ); ?>/</code>. HTML files are also kept on disk for inspection.</p>
						</td>
					</tr>
				</table>
			</div>
		</section>

		<div class="sforge-form-foot">
			<?php submit_button( 'Save Settings', 'primary large', 'submit', false ); ?>
		</div>
	</form>

	<section class="sforge-section sforge-section-actions">
		<header class="sforge-section-head">
			<span class="sforge-section-icon sforge-section-icon-teal"><span class="dashicons dashicons-controls-play"></span></span>
			<div>
				<h2>Actions</h2>
				<p>Verify the connection and push a fresh full deploy.</p>
			</div>
		</header>
		<div class="sforge-section-body sforge-actions">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="sforge_test_connection">
				<?php wp_nonce_field( 'sforge_action' ); ?>
				<button type="submit" class="button button-secondary button-large"><span class="dashicons dashicons-yes-alt"></span> Test Connection</button>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="sforge_full_rebuild">
				<?php wp_nonce_field( 'sforge_action' ); ?>
				<button type="submit" class="button button-primary button-large"><span class="dashicons dashicons-cloud-upload"></span> Rebuild + Deploy Now</button>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="sforge_clear_log">
				<?php wp_nonce_field( 'sforge_action' ); ?>
				<button type="submit" class="button button-link-delete button-large"><span class="dashicons dashicons-trash"></span> Clear Log</button>
			</form>
			<a href="mailto:hello@gunjanjaswal.me" class="button button-secondary button-large"><span class="dashicons dashicons-email-alt"></span> <?php esc_html_e( 'Contact Developer', 'staticforge-for-cloudflare-pages' ); ?></a>
		</div>
	</section>

	<section class="sforge-section sforge-section-support">
		<header class="sforge-section-head">
			<span class="sforge-section-icon sforge-section-icon-pink"><span class="dashicons dashicons-heart"></span></span>
			<div>
				<h2><?php esc_html_e( 'Support the developer', 'staticforge-for-cloudflare-pages' ); ?></h2>
				<p><?php esc_html_e( 'Plugin saving you time or money? Back development on Ko-fi.', 'staticforge-for-cloudflare-pages' ); ?></p>
			</div>
		</header>
		<div class="sforge-section-body sforge-support">
			<a href="https://ko-fi.com/gunjanjaswal" target="_blank" rel="noopener noreferrer" class="button button-primary button-large sforge-kofi-btn"><span class="dashicons dashicons-coffee"></span> <?php esc_html_e( 'Support on Ko-fi', 'staticforge-for-cloudflare-pages' ); ?></a>
			<a href="https://wordpress.org/support/plugin/staticforge-for-cloudflare-pages/" target="_blank" rel="noopener noreferrer" class="button button-secondary button-large"><span class="dashicons dashicons-sos"></span> <?php esc_html_e( 'Plugin Support Forum', 'staticforge-for-cloudflare-pages' ); ?></a>
		</div>
	</section>

	<section class="sforge-section" id="sforge-activity-log">
		<header class="sforge-section-head">
			<span class="sforge-section-icon sforge-section-icon-indigo"><span class="dashicons dashicons-list-view"></span></span>
			<div>
				<h2>Activity Log
					<span class="sforge-status sforge-status-idle"><span class="sforge-dot sforge-dot-idle"></span><span>Idle</span></span>
				</h2>
				<p>Auto-refreshes every 4 seconds. The status pill above shows the live deploy state.</p>
			</div>
		</header>
		<div class="sforge-log">
			<?php $log = SFORGE_Logger::get(); ?>
			<?php if ( empty( $log ) ) : ?>
				<p class="sforge-log-empty"><em>No activity yet. Click <strong>Test Connection</strong> or <strong>Rebuild + Deploy Now</strong> to see entries.</em></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead><tr><th class="sforge-col-time">Time</th><th class="sforge-col-level">Level</th><th>Message</th></tr></thead>
					<tbody>
					<?php foreach ( $log as $e ) : ?>
						<tr class="sforge-row sforge-level-<?php echo esc_attr( $e['level'] ); ?>">
							<td><?php echo esc_html( $e['time'] ); ?></td>
							<td><?php echo esc_html( $e['level'] ); ?></td>
							<td><?php echo wp_kses_post( $e['msg'] ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	</section>

	<footer class="sforge-foot">
		<div>
			<strong>StaticForge for Cloudflare Pages</strong> &mdash; v<?php echo esc_html( SFORGE_VERSION ); ?>
		</div>
		<div>
			Built by <a href="https://www.gunjanjaswal.me" target="_blank" rel="noopener">Gunjan Jaswal</a> &middot;
			<a href="mailto:hello@gunjanjaswal.me">hello@gunjanjaswal.me</a>
		</div>
	</footer>
</div>
