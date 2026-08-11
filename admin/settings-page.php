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
					<?php echo wp_kses_post( __( 'Auto-export your WordPress site as static HTML &mdash; deploy to Cloudflare Pages on every publish.', 'staticforge-for-cloudflare-pages' ) ); ?>
				</p>
			</div>
		</div>
		<div class="sforge-hero-actions">
			<a href="<?php echo esc_url( $help_url ); ?>" class="button button-secondary">
				<span class="dashicons dashicons-book-alt"></span> <?php esc_html_e( 'Setup Guide', 'staticforge-for-cloudflare-pages' ); ?>
			</a>
		</div>
	</div>

	<?php if ( $msg === 'test_ok' ) : ?>
		<div class="notice notice-success is-dismissible sforge-notice"><p><?php echo wp_kses_post( __( '<strong>Connection OK.</strong> Cloudflare Pages project is reachable.', 'staticforge-for-cloudflare-pages' ) ); ?></p></div>
	<?php elseif ( $msg === 'test_fail' ) : ?>
		<div class="notice notice-error is-dismissible sforge-notice"><p><?php echo wp_kses_post( __( '<strong>Connection failed.</strong> See the activity log below for details.', 'staticforge-for-cloudflare-pages' ) ); ?></p></div>
	<?php elseif ( $msg === 'rebuild_scheduled' ) : ?>
		<div class="notice notice-success is-dismissible sforge-notice"><p><?php echo wp_kses_post( __( '<strong>Full rebuild queued.</strong> The activity log refreshes live &mdash; watch for progress in the next few seconds.', 'staticforge-for-cloudflare-pages' ) ); ?></p></div>
		<?php elseif ( $msg === 'form_deployed' ) : ?>
			<div class="notice notice-success is-dismissible sforge-notice"><p><?php echo wp_kses_post( __( '<strong>Form handler deployed.</strong> The Worker URL is shown in the Forms section below &mdash; drop the <code>[sforge_form]</code> shortcode into a page and rebuild.', 'staticforge-for-cloudflare-pages' ) ); ?></p></div>
		<?php elseif ( $msg === 'form_removed' ) : ?>
			<div class="notice notice-success is-dismissible sforge-notice"><p><?php echo wp_kses_post( __( '<strong>Form handler removed.</strong> The Worker has been deleted from your Cloudflare account.', 'staticforge-for-cloudflare-pages' ) ); ?></p></div>
		<?php elseif ( $msg === 'form_fail' ) : ?>
			<div class="notice notice-error is-dismissible sforge-notice"><p><?php echo wp_kses_post( __( '<strong>Form handler action failed.</strong> See the activity log below for the exact error.', 'staticforge-for-cloudflare-pages' ) ); ?></p></div>
	<?php endif; ?>

	<?php if ( $is_unconfigured ) : ?>
		<div class="sforge-cta">
			<div class="sforge-cta-icon"><span class="dashicons dashicons-info-outline"></span></div>
			<div class="sforge-cta-body">
				<strong><?php esc_html_e( 'First time? Read the Setup Guide.', 'staticforge-for-cloudflare-pages' ); ?></strong>
				<span><?php esc_html_e( 'Walk-through for creating a Cloudflare Pages project, generating an API token, and configuring this plugin.', 'staticforge-for-cloudflare-pages' ); ?></span>
			</div>
			<a href="<?php echo esc_url( $help_url ); ?>" class="button button-primary"><?php esc_html_e( 'Open Setup Guide', 'staticforge-for-cloudflare-pages' ); ?></a>
		</div>
	<?php endif; ?>

	<form method="post" action="options.php" class="sforge-form">
		<?php settings_fields( 'sforge_group' ); ?>

		<section class="sforge-section">
			<header class="sforge-section-head">
				<span class="sforge-section-icon sforge-section-icon-blue"><span class="dashicons dashicons-cloud"></span></span>
				<div>
					<h2><?php esc_html_e( 'Cloudflare', 'staticforge-for-cloudflare-pages' ); ?></h2>
					<p><?php esc_html_e( 'Connect to your Cloudflare Pages project via the Direct Upload API.', 'staticforge-for-cloudflare-pages' ); ?></p>
				</div>
			</header>
			<div class="sforge-section-body">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="sforge_account_id"><?php esc_html_e( 'Account ID', 'staticforge-for-cloudflare-pages' ); ?></label></th>
						<td>
							<input type="text" id="sforge_account_id" name="<?php echo esc_attr( SFORGE_OPT ); ?>[account_id]" value="<?php echo esc_attr( $o['account_id'] ?? '' ); ?>" class="regular-text code" autocomplete="off" placeholder="<?php esc_attr_e( 'e.g. a2709493ed708e84df53c91fa354c230', 'staticforge-for-cloudflare-pages' ); ?>" />
							<p class="description"><?php echo wp_kses_post( __( 'Cloudflare Dashboard &rarr; right sidebar of any zone or Workers &amp; Pages overview.', 'staticforge-for-cloudflare-pages' ) ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sforge_api_token"><?php esc_html_e( 'API Token', 'staticforge-for-cloudflare-pages' ); ?></label></th>
						<td>
							<input type="password" id="sforge_api_token" name="<?php echo esc_attr( SFORGE_OPT ); ?>[api_token]" value="<?php echo esc_attr( $o['api_token'] ?? '' ); ?>" class="regular-text code" autocomplete="new-password" />
							<p class="description"><?php echo wp_kses_post( __( 'Create at <code>My Profile &rarr; API Tokens</code>. Required permission: <code>Account &middot; Cloudflare Pages &middot; Edit</code>.', 'staticforge-for-cloudflare-pages' ) ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sforge_project_name"><?php esc_html_e( 'Pages Project', 'staticforge-for-cloudflare-pages' ); ?></label></th>
						<td>
							<input type="text" id="sforge_project_name" name="<?php echo esc_attr( SFORGE_OPT ); ?>[project_name]" value="<?php echo esc_attr( $o['project_name'] ?? '' ); ?>" class="regular-text code" placeholder="my-site" />
							<p class="description"><?php echo wp_kses_post( __( 'The slug only (e.g. <code>my-site</code>), <strong>not</strong> the <code>.pages.dev</code> URL. Create in the CF dashboard with the Direct Upload option.', 'staticforge-for-cloudflare-pages' ) ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sforge_branch"><?php esc_html_e( 'Branch', 'staticforge-for-cloudflare-pages' ); ?></label></th>
						<td>
							<input type="text" id="sforge_branch" name="<?php echo esc_attr( SFORGE_OPT ); ?>[branch]" value="<?php echo esc_attr( $o['branch'] ?? 'main' ); ?>" class="small-text code" />
							<p class="description"><?php echo wp_kses_post( __( '<code>main</code> = production deployment. Anything else creates a preview deployment.', 'staticforge-for-cloudflare-pages' ) ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sforge_cf_pages_url"><?php esc_html_e( 'Public Site URL', 'staticforge-for-cloudflare-pages' ); ?></label></th>
						<td>
							<input type="url" id="sforge_cf_pages_url" name="<?php echo esc_attr( SFORGE_OPT ); ?>[cf_pages_url]" value="<?php echo esc_attr( $o['cf_pages_url'] ?? '' ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'https://my-site.pages.dev or https://www.example.com', 'staticforge-for-cloudflare-pages' ); ?>" />
							<p class="description"><?php esc_html_e( 'Where the static site lives publicly. Used to rewrite WP origin URLs in exported HTML and sitemap entries.', 'staticforge-for-cloudflare-pages' ); ?></p>
						</td>
					</tr>
				</table>
			</div>
		</section>

		<section class="sforge-section">
			<header class="sforge-section-head">
				<span class="sforge-section-icon sforge-section-icon-green"><span class="dashicons dashicons-filter"></span></span>
				<div>
					<h2><?php esc_html_e( 'Export Scope', 'staticforge-for-cloudflare-pages' ); ?></h2>
					<p><?php esc_html_e( 'Choose which post types and archives to export.', 'staticforge-for-cloudflare-pages' ); ?></p>
				</div>
			</header>
			<div class="sforge-section-body">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Post Types', 'staticforge-for-cloudflare-pages' ); ?></th>
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
							<p class="description"><?php esc_html_e( 'All published items of selected types are exported.', 'staticforge-for-cloudflare-pages' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Include', 'staticforge-for-cloudflare-pages' ); ?></th>
						<td>
							<div class="sforge-cb-grid">
								<label class="sforge-cb"><input type="checkbox" name="<?php echo esc_attr( SFORGE_OPT ); ?>[include_homepage]" value="1" <?php checked( ! empty( $o['include_homepage'] ) ); ?>> <strong><?php esc_html_e( 'Homepage', 'staticforge-for-cloudflare-pages' ); ?></strong></label>
								<label class="sforge-cb"><input type="checkbox" name="<?php echo esc_attr( SFORGE_OPT ); ?>[include_taxonomies]" value="1" <?php checked( ! empty( $o['include_taxonomies'] ) ); ?>> <strong><?php esc_html_e( 'Taxonomy archives', 'staticforge-for-cloudflare-pages' ); ?></strong> <code>cat / tag / custom</code></label>
								<label class="sforge-cb"><input type="checkbox" name="<?php echo esc_attr( SFORGE_OPT ); ?>[include_authors]" value="1" <?php checked( ! empty( $o['include_authors'] ) ); ?>> <strong><?php esc_html_e( 'Author archives', 'staticforge-for-cloudflare-pages' ); ?></strong></label>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Inline CSS', 'staticforge-for-cloudflare-pages' ); ?></th>
						<td>
							<label class="sforge-cb"><input type="checkbox" name="<?php echo esc_attr( SFORGE_OPT ); ?>[inline_css]" value="1" <?php checked( ! empty( $o['inline_css'] ) ); ?>> <?php esc_html_e( 'Embed all linked stylesheets into each page', 'staticforge-for-cloudflare-pages' ); ?></label>
							<p class="description"><?php esc_html_e( 'Self-contained pages, no external CSS requests.', 'staticforge-for-cloudflare-pages' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo wp_kses_post( __( 'Redirect <code>*.pages.dev</code> to live host', 'staticforge-for-cloudflare-pages' ) ); ?></th>
						<td>
							<label class="sforge-cb"><input type="checkbox" name="<?php echo esc_attr( SFORGE_OPT ); ?>[redirect_pages_dev]" value="1" <?php checked( ! empty( $o['redirect_pages_dev'] ) ); ?>> <?php echo wp_kses_post( __( '301-redirect any request hitting <code>&lt;project&gt;.pages.dev</code> to the canonical Public Site URL', 'staticforge-for-cloudflare-pages' ) ); ?></label>
							<p class="description">
								<?php echo wp_kses_post( __( 'Injects a small client-side JavaScript snippet into every exported page that redirects any request landing on a <code>.pages.dev</code> hostname to your <strong>Public Site URL</strong> (preserving path + query string). Runs client-side because the Direct Upload API serves everything as static assets and never executes <code>_worker.js</code> / Functions. Stops Google from indexing the preview URL alongside your real domain.', 'staticforge-for-cloudflare-pages' ) ); ?>
								<?php echo wp_kses_post( __( 'Automatically skipped when Public Site URL itself is a <code>.pages.dev</code> URL (e.g. while you\'re still testing pre-DNS cutover).', 'staticforge-for-cloudflare-pages' ) ); ?>
								<?php echo wp_kses_post( __( 'No Workers, no Functions, no extra cost &mdash; it is plain JavaScript in the page, so it works on every deploy regardless of upload method.', 'staticforge-for-cloudflare-pages' ) ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo wp_kses_post( __( 'Rewrite <code>/wp-content/</code> URLs', 'staticforge-for-cloudflare-pages' ) ); ?></th>
						<td>
							<label class="sforge-cb sforge-cb-danger"><input type="checkbox" name="<?php echo esc_attr( SFORGE_OPT ); ?>[rewrite_wpcontent]" value="1" <?php checked( ! empty( $o['rewrite_wpcontent'] ) ); ?>> <?php echo wp_kses_post( __( 'Also rewrite <code>/wp-content/</code> URLs (uploads, themes, plugin assets) to the live host', 'staticforge-for-cloudflare-pages' ) ); ?></label>
							<p class="description">
								<?php echo wp_kses_post( __( '<strong>Default: OFF.</strong> By default, <code>&lt;origin&gt;/wp-content/...</code> URLs are kept pointing at your WordPress origin so media, theme CSS/JS, and plugin assets keep working without bundling gigabytes of files in every deploy.', 'staticforge-for-cloudflare-pages' ) ); ?><br>
								<?php echo wp_kses_post( __( '<strong>Turn ON only if you\'ve arranged your own proxy / mirror / CDN for <code>/wp-content/*</code> on the live host</strong> &mdash; e.g. a Cloudflare Worker rewriting <code>https://example.com/wp-content/*</code> &rarr; <code>https://dashboard.example.com/wp-content/*</code>, or an Nginx reverse-proxy, or a separate CDN domain. Otherwise images, theme styles and scripts will 404 on the deployed site.', 'staticforge-for-cloudflare-pages' ) ); ?><br>
								<?php echo wp_kses_post( __( 'When ON, schema URLs (<code>og:image</code>, JSON-LD <code>image</code>/<code>logo</code>, <code>thumbnailUrl</code>) and HTML <code>src</code>/<code>srcset</code> point to the live host instead of the dashboard.', 'staticforge-for-cloudflare-pages' ) ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo wp_kses_post( __( 'Bundle <code>/wp-content/uploads/</code> into deploy', 'staticforge-for-cloudflare-pages' ) ); ?></th>
						<td>
							<label class="sforge-cb"><input type="checkbox" name="<?php echo esc_attr( SFORGE_OPT ); ?>[bundle_uploads]" value="1" <?php checked( ! empty( $o['bundle_uploads'] ) ); ?>> <?php echo wp_kses_post( __( 'Fetch every <code>/wp-content/uploads/</code> file referenced by the rendered HTML and ship it alongside the static pages', 'staticforge-for-cloudflare-pages' ) ); ?></label>
							<p class="description">
								<?php echo wp_kses_post( __( '<strong>Default: OFF.</strong> Softer alternative to the option above &mdash; only media files (uploads) get rewritten to the live host and bundled into the CF Pages deploy. Theme &amp; plugin assets (CSS/JS/fonts) still load from the WordPress origin.', 'staticforge-for-cloudflare-pages' ) ); ?><br>
								<?php echo wp_kses_post( __( 'Use this when your origin can\'t be reached from Cloudflare Workers / proxies (shared hosting firewalls, IP allow-lists, etc.). Each rebuild downloads only files referenced from the rendered pages, so cost scales with what\'s actually used, not the full media library.', 'staticforge-for-cloudflare-pages' ) ); ?><br>
								<?php echo wp_kses_post( __( 'Ignored when "Rewrite <code>/wp-content/</code> URLs" above is ON (that setting already rewrites everything).', 'staticforge-for-cloudflare-pages' ) ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Bundle self-hosted fonts', 'staticforge-for-cloudflare-pages' ); ?></th>
						<td>
							<label class="sforge-cb"><input type="checkbox" name="<?php echo esc_attr( SFORGE_OPT ); ?>[bundle_fonts]" value="1" <?php checked( ! empty( $o['bundle_fonts'] ) ); ?>> <?php echo wp_kses_post( __( 'Ship <code>@font-face</code> fonts served from your WordPress host alongside the static pages and point their URLs at the live host', 'staticforge-for-cloudflare-pages' ) ); ?></label>
							<p class="description">
								<?php echo wp_kses_post( __( '<strong>Default: ON.</strong> A self-hosted font (a theme webfont, Astra\'s local Google Fonts at <code>wp-content/astra-local-fonts/</code>, an icon font) whose <code>src</code> still points at your WordPress origin is a cross-origin request once the page is served from <code>*.pages.dev</code>. Browsers fetch fonts in CORS mode, and the origin doesn\'t send an <code>Access-Control-Allow-Origin</code> header, so the font is blocked and you get a fallback typeface.', 'staticforge-for-cloudflare-pages' ) ); ?><br>
								<?php echo wp_kses_post( __( 'With this on, every <code>.woff2 / .woff / .ttf / .otf / .eot</code> file referenced from the rendered pages and served from your own host is fetched, bundled into the deploy, and its URL rewritten to the Public Site URL &mdash; so it loads same-origin and the CORS error disappears. Third-party fonts (Google Fonts on <code>fonts.gstatic.com</code>, etc.) already send CORS headers and are left untouched.', 'staticforge-for-cloudflare-pages' ) ); ?><br>
								<?php echo wp_kses_post( __( 'Ignored when "Rewrite <code>/wp-content/</code> URLs" above is ON (that already rewrites fonts along with everything else).', 'staticforge-for-cloudflare-pages' ) ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sforge_extra_paths"><?php esc_html_e( 'Extra paths to include', 'staticforge-for-cloudflare-pages' ); ?></label></th>
						<td>
							<?php
							$extra_paths_val = SFORGE_Settings::get( 'extra_paths', [] );
							if ( is_array( $extra_paths_val ) ) {
								$extra_paths_val = implode( "\n", $extra_paths_val );
							}
							?>
							<textarea id="sforge_extra_paths" name="<?php echo esc_attr( SFORGE_OPT ); ?>[extra_paths]" rows="4" class="large-text code" placeholder="wp-content/plugins/elementor/assets/lib/font-awesome"><?php echo esc_textarea( (string) $extra_paths_val ); ?></textarea>
							<p class="description">
								<?php echo wp_kses_post( __( '<strong>One path per line, relative to your WordPress root.</strong> Point this at files or folders that should be shipped inside the Cloudflare Pages deploy even though the crawler never sees them in the rendered HTML &mdash; a plugin\'s icon font (e.g. Elementor\'s Font Awesome at <code>wp-content/plugins/elementor/assets/lib/font-awesome</code>), a webfont directory, a downloadable PDF.', 'staticforge-for-cloudflare-pages' ) ); ?><br>
								<?php echo wp_kses_post( __( 'A whole folder is copied recursively; a single file is copied as-is; a trailing wildcard (<code>wp-content/uploads/2025/*.pdf</code>) is expanded. Bundled files under <code>/wp-content/</code> get their URLs pointed at the live host so the deployed page loads the bundled copy; everything else stays on origin.', 'staticforge-for-cloudflare-pages' ) ); ?><br>
								<?php echo wp_kses_post( __( 'You can enter a path relative to <code>wp-content/</code> too (<code>astra-local-fonts</code> instead of <code>wp-content/astra-local-fonts</code>) &mdash; if it isn\'t found at the WordPress root, the plugin looks under <code>wp-content/</code> automatically.', 'staticforge-for-cloudflare-pages' ) ); ?><br>
								<?php echo wp_kses_post( __( 'Read straight off local disk, so no origin firewall to worry about. Paths are confined to the WordPress root; anything escaping it via <code>..</code> or a symlink is skipped and logged. Capped at 5,000 files / 200&nbsp;MB per rebuild.', 'staticforge-for-cloudflare-pages' ) ); ?>
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
					<h2><?php esc_html_e( 'Performance', 'staticforge-for-cloudflare-pages' ); ?></h2>
					<p><?php esc_html_e( 'Core Web Vitals tweaks applied to the rendered HTML.', 'staticforge-for-cloudflare-pages' ); ?></p>
				</div>
			</header>
			<div class="sforge-section-body">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Featured image priority', 'staticforge-for-cloudflare-pages' ); ?></th>
						<td>
							<label class="sforge-cb"><input type="checkbox" name="<?php echo esc_attr( SFORGE_OPT ); ?>[featured_image_priority]" value="1" <?php checked( ! empty( $o['featured_image_priority'] ) ); ?>> <?php echo wp_kses_post( __( 'Add <code>fetchpriority="high"</code> to the post\'s featured image', 'staticforge-for-cloudflare-pages' ) ); ?></label>
							<p class="description">
								<?php echo wp_kses_post( __( 'Also sets <code>loading="eager"</code> and <code>decoding="async"</code>. Helps the browser identify the LCP element earlier &mdash; improves Core Web Vitals. Works with any theme that uses <code>the_post_thumbnail()</code> or <code>get_the_post_thumbnail()</code>.', 'staticforge-for-cloudflare-pages' ) ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sforge_render_origin"><?php esc_html_e( 'Render origin override', 'staticforge-for-cloudflare-pages' ); ?></label></th>
						<td>
							<input type="text" id="sforge_render_origin" name="<?php echo esc_attr( SFORGE_OPT ); ?>[render_origin]" value="<?php echo esc_attr( $o['render_origin'] ?? '' ); ?>" class="regular-text code" placeholder="http://127.0.0.1" autocomplete="off" />
							<p class="description">
								<?php echo wp_kses_post( __( '<strong>Leave blank unless rebuilds are slow.</strong> The export fetches every page over HTTP from your site\'s own URL. When your domain runs behind a CDN/proxy (e.g. Cloudflare), each of those requests leaves the server and comes back &mdash; with hundreds of pages that round-trip dominates the rebuild time.', 'staticforge-for-cloudflare-pages' ) ); ?><br>
								<?php echo wp_kses_post( __( 'Set this to a host that points at WordPress <em>directly on this server</em> (usually <code>http://127.0.0.1</code>, or <code>http://127.0.0.1:8080</code> if PHP listens on another port) and the crawl stays on the box. The plugin keeps your real domain in the <code>Host</code> header, so WordPress still serves the correct site/language. TLS verification is skipped for the override only (a loopback cert won\'t match the public host).', 'staticforge-for-cloudflare-pages' ) ); ?><br>
								<?php echo wp_kses_post( __( 'Applies to page renders, inlined CSS, mirrored sitemaps, and bundled uploads. Only URLs on your origin host are redirected; everything else is fetched unchanged.', 'staticforge-for-cloudflare-pages' ) ); ?>
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
					<h2><?php esc_html_e( 'SEO Metadata', 'staticforge-for-cloudflare-pages' ); ?></h2>
					<p><?php esc_html_e( 'Auto-emit meta tags, Open Graph, Twitter Card, and rich JSON-LD schemas.', 'staticforge-for-cloudflare-pages' ); ?></p>
				</div>
			</header>
			<div class="sforge-section-body">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Inject SEO meta', 'staticforge-for-cloudflare-pages' ); ?></th>
						<td>
							<label class="sforge-cb"><input type="checkbox" name="<?php echo esc_attr( SFORGE_OPT ); ?>[seo_inject]" value="1" <?php checked( ! empty( $o['seo_inject'] ) ); ?>> <?php echo wp_kses_post( __( 'Add baseline SEO tags + JSON-LD schemas to <code>&lt;head&gt;</code>', 'staticforge-for-cloudflare-pages' ) ); ?></label>

							<div class="sforge-feature-grid">
								<div class="sforge-feature">
									<span class="sforge-feature-icon">📝</span>
									<strong><?php esc_html_e( 'Meta + Social', 'staticforge-for-cloudflare-pages' ); ?></strong>
									<small><?php echo wp_kses_post( __( '<code>description</code>, <code>robots</code>, <code>canonical</code>, Open Graph (<code>og:type</code>, <code>article:*</code>, <code>profile:*</code>), Twitter Card.', 'staticforge-for-cloudflare-pages' ) ); ?></small>
								</div>
								<div class="sforge-feature">
									<span class="sforge-feature-icon">📜</span>
									<strong><?php esc_html_e( 'Core Schemas', 'staticforge-for-cloudflare-pages' ); ?></strong>
									<small><?php echo wp_kses_post( __( '<code>WebSite</code> + <code>SearchAction</code>, <code>Organization</code>, <code>Article</code>, <code>WebPage</code>, <code>BreadcrumbList</code>, <code>CollectionPage</code>.', 'staticforge-for-cloudflare-pages' ) ); ?></small>
								</div>
								<div class="sforge-feature">
									<span class="sforge-feature-icon">👤</span>
									<strong><?php esc_html_e( 'Author Schema', 'staticforge-for-cloudflare-pages' ); ?></strong>
									<small><?php echo wp_kses_post( __( '<code>Person</code> + <code>ProfilePage</code> on author archives &mdash; avatar, bio, sameAs social links.', 'staticforge-for-cloudflare-pages' ) ); ?></small>
								</div>
								<div class="sforge-feature">
									<span class="sforge-feature-icon">❓</span>
									<strong><?php echo wp_kses_post( __( 'FAQ &amp; HowTo', 'staticforge-for-cloudflare-pages' ) ); ?></strong>
									<small><?php echo wp_kses_post( __( 'Auto-detected from FAQ / HowTo blocks (Yoast, Rank Math, SEOPress) or HTML5 <code>&lt;details&gt;</code> markup.', 'staticforge-for-cloudflare-pages' ) ); ?></small>
								</div>
							</div>

							<?php if ( $has_general_seo ) : ?>
								<div class="sforge-callout sforge-callout-warn">
									<strong><?php esc_html_e( 'General SEO plugin detected', 'staticforge-for-cloudflare-pages' ); ?></strong>
									<span><?php echo wp_kses_post( __( 'Yoast / Rank Math / AIO SEO / SEOPress / SEO Framework / etc. emits its own complete SEO stack. <strong>All our injection is paused</strong> to avoid duplicate tags.', 'staticforge-for-cloudflare-pages' ) ); ?></span>
								</div>
							<?php elseif ( $has_schema_seo ) : ?>
								<div class="sforge-callout sforge-callout-info">
									<strong><?php esc_html_e( 'Schema-only plugin detected', 'staticforge-for-cloudflare-pages' ); ?></strong>
									<span><?php echo wp_kses_post( __( 'Schema &amp; Structured Data for WP &amp; AMP / Schema Pro / WPSSO / etc. handles JSON-LD. We will <strong>still emit</strong> meta description, robots, canonical, Open Graph, and Twitter Card &mdash; but skip our JSON-LD block to avoid schema duplication.', 'staticforge-for-cloudflare-pages' ) ); ?></span>
								</div>
							<?php else : ?>
								<div class="sforge-callout sforge-callout-success">
									<strong><?php esc_html_e( 'No SEO plugin detected', 'staticforge-for-cloudflare-pages' ); ?></strong>
									<span><?php esc_html_e( 'Full injection enabled — meta, Open Graph, Twitter Card, and all JSON-LD schemas will be emitted.', 'staticforge-for-cloudflare-pages' ); ?></span>
								</div>
							<?php endif; ?>

							<?php if ( $has_general_seo || $has_schema_seo ) : ?>
								<label class="sforge-cb sforge-cb-danger"><input type="checkbox" name="<?php echo esc_attr( SFORGE_OPT ); ?>[seo_inject_force]" value="1" <?php checked( ! empty( $o['seo_inject_force'] ) ); ?>> <strong><?php esc_html_e( 'Force full injection anyway', 'staticforge-for-cloudflare-pages' ); ?></strong> <small><?php echo wp_kses_post( __( '(may produce duplicate tags &mdash; only enable if you\'ve configured the other plugin to skip)', 'staticforge-for-cloudflare-pages' ) ); ?></small></label>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Profile schema (author pages)', 'staticforge-for-cloudflare-pages' ); ?></th>
						<td>
							<label class="sforge-cb"><input type="checkbox" name="<?php echo esc_attr( SFORGE_OPT ); ?>[profile_schema]" value="1" <?php checked( ! empty( $o['profile_schema'] ) ); ?>> <?php echo wp_kses_post( __( 'Emit rich <code>Person</code> + <code>ProfilePage</code> JSON-LD on author archives even when an SEO plugin is active', 'staticforge-for-cloudflare-pages' ) ); ?></label>
							<p class="description">
								<?php echo wp_kses_post( __( 'Adds a fuller author graph with <code>sameAs</code> social URLs (Twitter / X, LinkedIn, Facebook, Instagram, YouTube, GitHub, Pinterest, TikTok, Threads, Medium, Mastodon, Bluesky &mdash; pulled from <code>user_url</code> + matching <code>user_meta</code> keys), <code>givenName</code>, <code>familyName</code>, <code>description</code>, avatar <code>ImageObject</code>, and optional <code>jobTitle</code>/<code>worksFor</code> from custom user meta.', 'staticforge-for-cloudflare-pages' ) ); ?>
								<?php echo wp_kses_post( __( 'Skipped automatically when this plugin\'s own SEO injector is already covering author pages (no SEO plugin detected and "Inject SEO meta" is on). Distinct <code>@id</code> suffix prevents conflict with Yoast/Rank Math/etc.', 'staticforge-for-cloudflare-pages' ) ); ?>
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
					<h2><?php echo wp_kses_post( __( 'robots.txt &amp; Dashboard Indexing', 'staticforge-for-cloudflare-pages' ) ); ?></h2>
					<p><?php esc_html_e( 'Live site robots.txt + dashboard search-engine block.', 'staticforge-for-cloudflare-pages' ); ?></p>
				</div>
			</header>
			<div class="sforge-section-body">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Block dashboard from search engines', 'staticforge-for-cloudflare-pages' ); ?></th>
						<td>
							<label class="sforge-cb"><input type="checkbox" name="<?php echo esc_attr( SFORGE_OPT ); ?>[dashboard_block]" value="1" <?php checked( ! empty( $o['dashboard_block'] ) ); ?>> <?php echo wp_kses_post( __( 'Force <code>noindex</code> on this WordPress install (recommended)', 'staticforge-for-cloudflare-pages' ) ); ?></label>
							<p class="description">
								<?php echo wp_kses_post( __( 'Writes a <code>Disallow: /</code> robots.txt at the webroot (existing file backed up to <code>robots.txt.sforge-backup</code>), adds a <code>noindex,nofollow</code> meta robots tag, and emits an <code>X-Robots-Tag</code> HTTP header on every response. Plugin\'s own export fetches are exempt &mdash; deployed pages remain fully indexable. Auto-restores on plugin deactivation.', 'staticforge-for-cloudflare-pages' ) ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sforge_robots_txt"><?php esc_html_e( 'Live site robots.txt', 'staticforge-for-cloudflare-pages' ); ?></label></th>
						<td>
							<textarea id="sforge_robots_txt" name="<?php echo esc_attr( SFORGE_OPT ); ?>[robots_txt]" rows="8" class="large-text code" placeholder="<?php echo esc_attr( SFORGE_Seo::preview_default_robots() ); ?>"><?php echo esc_textarea( $o['robots_txt'] ?? '' ); ?></textarea>
							<p class="description">
								<?php echo wp_kses_post( __( 'Independent of the dashboard robots.txt above. Leave blank to auto-generate (placeholder shows the default). If filled, your <code>Allow:</code> / <code>Disallow:</code> rules are kept verbatim &mdash; the <code>Sitemap:</code> line is always <strong>auto-managed</strong> to match the actual deployed sitemap path (<code>sitemap.xml</code>, <code>sitemap_index.xml</code>, <code>wp-sitemap.xml</code>, etc.) so the URL in robots.txt always works. Any <code>Sitemap:</code> lines you type are stripped and replaced. Deployed to <code>&lt;cf-pages-url&gt;/robots.txt</code> on next rebuild.', 'staticforge-for-cloudflare-pages' ) ); ?>
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
					<h2><?php esc_html_e( 'Sitemap Generator', 'staticforge-for-cloudflare-pages' ); ?></h2>
					<p><?php echo wp_kses_post( __( 'What to include when the plugin generates <code>sitemap.xml</code> (origin sitemap missing).', 'staticforge-for-cloudflare-pages' ) ); ?></p>
				</div>
			</header>
			<div class="sforge-section-body">
				<p class="description" style="margin-top:8px;">
					<?php echo wp_kses_post( __( 'If your origin already exposes a sitemap (Yoast / Rank Math / WP core <code>/wp-sitemap.xml</code>), the plugin <em>mirrors</em> it and these settings are ignored. Otherwise the plugin builds <code>sitemap.xml</code> itself from the options below.', 'staticforge-for-cloudflare-pages' ) ); ?>
				</p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Post Types', 'staticforge-for-cloudflare-pages' ); ?></th>
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
							<p class="description"><?php esc_html_e( 'All published items of selected types are listed in the sitemap, plus their post-type archive URL where applicable.', 'staticforge-for-cloudflare-pages' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Include', 'staticforge-for-cloudflare-pages' ); ?></th>
						<td>
							<div class="sforge-cb-grid">
								<label class="sforge-cb"><input type="checkbox" name="<?php echo esc_attr( SFORGE_OPT ); ?>[sitemap_homepage]" value="1" <?php checked( ! empty( $o['sitemap_homepage'] ) ); ?>> <strong><?php esc_html_e( 'Homepage', 'staticforge-for-cloudflare-pages' ); ?></strong></label>
								<label class="sforge-cb"><input type="checkbox" name="<?php echo esc_attr( SFORGE_OPT ); ?>[sitemap_taxonomies]" value="1" <?php checked( ! empty( $o['sitemap_taxonomies'] ) ); ?>> <strong><?php esc_html_e( 'Taxonomy archives', 'staticforge-for-cloudflare-pages' ); ?></strong> <code>cat / tag / custom</code></label>
								<label class="sforge-cb"><input type="checkbox" name="<?php echo esc_attr( SFORGE_OPT ); ?>[sitemap_authors]" value="1" <?php checked( ! empty( $o['sitemap_authors'] ) ); ?>> <strong><?php esc_html_e( 'Author archives', 'staticforge-for-cloudflare-pages' ); ?></strong></label>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Split into multiple files', 'staticforge-for-cloudflare-pages' ); ?></th>
						<td>
							<label class="sforge-cb"><input type="checkbox" name="<?php echo esc_attr( SFORGE_OPT ); ?>[sitemap_split]" value="1" <?php checked( ! empty( $o['sitemap_split'] ) ); ?>> <?php esc_html_e( 'Generate a sitemap-index pointing to per-type sub-sitemaps', 'staticforge-for-cloudflare-pages' ); ?></label>
							<p class="description">
								<?php echo wp_kses_post( __( '<strong>Off</strong> (default) &rarr; single <code>sitemap.xml</code> with every URL.', 'staticforge-for-cloudflare-pages' ) ); ?><br>
								<?php echo wp_kses_post( __( '<strong>On</strong> &rarr; <code>sitemap.xml</code> becomes a <code>&lt;sitemapindex&gt;</code> referencing <code>sitemap-post.xml</code>, <code>sitemap-page.xml</code>, <code>sitemap-taxonomy-category.xml</code>, <code>sitemap-authors.xml</code>, etc. Cleaner for large sites and better understood by Search Console.', 'staticforge-for-cloudflare-pages' ) ); ?>
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
					<h2><?php esc_html_e( 'Deployment Behaviour', 'staticforge-for-cloudflare-pages' ); ?></h2>
					<p><?php esc_html_e( 'When and how the plugin re-deploys on changes.', 'staticforge-for-cloudflare-pages' ); ?></p>
				</div>
			</header>
			<div class="sforge-section-body">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Auto-deploy', 'staticforge-for-cloudflare-pages' ); ?></th>
						<td>
							<label class="sforge-cb"><input type="checkbox" name="<?php echo esc_attr( SFORGE_OPT ); ?>[auto_deploy]" value="1" <?php checked( ! empty( $o['auto_deploy'] ) ); ?>> <?php esc_html_e( 'Re-deploy site on publish/update of selected post types', 'staticforge-for-cloudflare-pages' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sforge_debounce"><?php esc_html_e( 'Debounce', 'staticforge-for-cloudflare-pages' ); ?></label></th>
						<td>
							<input type="number" id="sforge_debounce" name="<?php echo esc_attr( SFORGE_OPT ); ?>[debounce]" min="10" max="3600" value="<?php echo esc_attr( $o['debounce'] ?? 120 ); ?>" class="small-text" /> <span class="sforge-unit"><?php esc_html_e( 'seconds', 'staticforge-for-cloudflare-pages' ); ?></span>
							<p class="description"><?php echo wp_kses_post( __( 'Rapid edits within this window collapse into a single deploy. Default <strong>120s</strong> &mdash; comfortable margin under CF Pages free-tier soft cap of ~100 deploys/day.', 'staticforge-for-cloudflare-pages' ) ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sforge_export_dir"><?php esc_html_e( 'Local Export Folder', 'staticforge-for-cloudflare-pages' ); ?></label></th>
						<td>
							<input type="text" id="sforge_export_dir" name="<?php echo esc_attr( SFORGE_OPT ); ?>[export_dir]" value="<?php echo esc_attr( $o['export_dir'] ?? 'sforge-export' ); ?>" class="regular-text code" />
							<p class="description">
								<?php
								/* translators: %s: absolute filesystem path to the WordPress uploads directory. */
								printf( wp_kses_post( __( 'Inside <code>%s/</code>. HTML files are also kept on disk for inspection.', 'staticforge-for-cloudflare-pages' ) ), esc_html( wp_upload_dir()['basedir'] ) );
								?>
							</p>
						</td>
					</tr>
				</table>
			</div>
		</section>

		<section class="sforge-section">
			<header class="sforge-section-head">
				<span class="sforge-section-icon sforge-section-icon-blue"><span class="dashicons dashicons-email"></span></span>
				<div>
					<h2><?php esc_html_e( 'Forms (email on submit)', 'staticforge-for-cloudflare-pages' ); ?></h2>
					<p><?php esc_html_e( 'Accept form submissions on the static site and email them, using your own email API.', 'staticforge-for-cloudflare-pages' ); ?></p>
				</div>
			</header>
			<div class="sforge-section-body">
				<p class="description" style="margin-top:8px;">
					<?php echo wp_kses_post( __( 'A static site can\'t process a POST, and the Direct Upload deploy can\'t run Cloudflare Functions. So the form is handled by a tiny <strong>standalone Cloudflare Worker</strong> this plugin generates and deploys for you. Your email-provider API key is stored as a Worker secret &mdash; never in the page, never sent to the browser.', 'staticforge-for-cloudflare-pages' ) ); ?><br>
					<?php echo wp_kses_post( __( 'This needs your API token to also carry the <code>Account &middot; Workers Scripts &middot; Edit</code> permission, plus a free <code>workers.dev</code> subdomain on the account.', 'staticforge-for-cloudflare-pages' ) ); ?>
				</p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable forms', 'staticforge-for-cloudflare-pages' ); ?></th>
						<td>
							<label class="sforge-cb"><input type="checkbox" name="<?php echo esc_attr( SFORGE_OPT ); ?>[form_enabled]" value="1" <?php checked( ! empty( $o['form_enabled'] ) ); ?>> <?php echo wp_kses_post( __( 'Render the <code>[sforge_form]</code> shortcode and route it to the deployed handler', 'staticforge-for-cloudflare-pages' ) ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sforge_form_provider"><?php esc_html_e( 'Email provider', 'staticforge-for-cloudflare-pages' ); ?></label></th>
						<td>
							<select id="sforge_form_provider" name="<?php echo esc_attr( SFORGE_OPT ); ?>[form_provider]">
								<?php
								$cur_provider = $o['form_provider'] ?? 'resend';
								foreach ( SFORGE_Forms::presets() as $pkey => $preset ) {
									printf( '<option value="%s" %s>%s</option>', esc_attr( $pkey ), selected( $cur_provider, $pkey, false ), esc_html( $preset['label'] ) );
								}
								?>
							</select>
							<p class="description sforge-provider-hint"><?php esc_html_e( 'Pick your provider to prefill the fields below, then paste your key in the deploy box. Choose “Custom / other” to wire up any other email API by hand.', 'staticforge-for-cloudflare-pages' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sforge_form_endpoint"><?php esc_html_e( 'API endpoint', 'staticforge-for-cloudflare-pages' ); ?></label></th>
						<td>
							<input type="url" id="sforge_form_endpoint" name="<?php echo esc_attr( SFORGE_OPT ); ?>[form_endpoint]" value="<?php echo esc_attr( $o['form_endpoint'] ?? '' ); ?>" class="regular-text code" placeholder="https://api.resend.com/emails">
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Auth header', 'staticforge-for-cloudflare-pages' ); ?></th>
						<td>
							<input type="text" id="sforge_form_auth_name" name="<?php echo esc_attr( SFORGE_OPT ); ?>[form_auth_header_name]" value="<?php echo esc_attr( $o['form_auth_header_name'] ?? 'Authorization' ); ?>" class="small-text code" style="width:12em" placeholder="Authorization">
							<code>:</code>
							<input type="text" id="sforge_form_auth_tpl" name="<?php echo esc_attr( SFORGE_OPT ); ?>[form_auth_header_tpl]" value="<?php echo esc_attr( $o['form_auth_header_tpl'] ?? 'Bearer ${SFORGE_FORM_KEY}' ); ?>" class="regular-text code" placeholder="Bearer ${SFORGE_FORM_KEY}">
							<p class="description"><?php echo wp_kses_post( __( 'Use <code>${SFORGE_FORM_KEY}</code> where your API key goes &mdash; it is swapped in from the Worker secret at runtime and never stored here.', 'staticforge-for-cloudflare-pages' ) ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sforge_form_content_type"><?php esc_html_e( 'Request format', 'staticforge-for-cloudflare-pages' ); ?></label></th>
						<td>
							<?php $ctype = $o['form_content_type'] ?? 'json'; ?>
							<select id="sforge_form_content_type" name="<?php echo esc_attr( SFORGE_OPT ); ?>[form_content_type]">
								<option value="json" <?php selected( $ctype, 'json' ); ?>><?php esc_html_e( 'JSON (Resend, SendGrid, Postmark, most APIs)', 'staticforge-for-cloudflare-pages' ); ?></option>
								<option value="form" <?php selected( $ctype, 'form' ); ?>><?php esc_html_e( 'Form-encoded (Mailgun)', 'staticforge-for-cloudflare-pages' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sforge_form_body_template"><?php esc_html_e( 'Request body template', 'staticforge-for-cloudflare-pages' ); ?></label></th>
						<td>
							<textarea id="sforge_form_body_template" name="<?php echo esc_attr( SFORGE_OPT ); ?>[form_body_template]" rows="9" class="large-text code"><?php echo esc_textarea( $o['form_body_template'] ?? '' ); ?></textarea>
							<p class="description">
								<?php echo wp_kses_post( __( 'JSON sent to your provider. <code>{{name}}</code>, <code>{{email}}</code>, <code>{{message}}</code> (any submitted field) are filled in; <code>{{all_fields}}</code> expands to every field as <code>Label: value</code> lines. Set your real <code>to:</code> / <code>from:</code> address here.', 'staticforge-for-cloudflare-pages' ) ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sforge_form_required"><?php esc_html_e( 'Required fields', 'staticforge-for-cloudflare-pages' ); ?></label></th>
						<td>
							<?php
							$req_val = SFORGE_Settings::get( 'form_required_fields', [ 'name', 'email', 'message' ] );
							if ( is_array( $req_val ) ) {
								$req_val = implode( ', ', $req_val );
							}
							?>
							<input type="text" id="sforge_form_required" name="<?php echo esc_attr( SFORGE_OPT ); ?>[form_required_fields]" value="<?php echo esc_attr( $req_val ); ?>" class="regular-text code" placeholder="name, email, message">
							<p class="description"><?php esc_html_e( 'Comma-separated field names the Worker rejects the submission without.', 'staticforge-for-cloudflare-pages' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Spam protection (Turnstile)', 'staticforge-for-cloudflare-pages' ); ?></th>
						<td>
							<label class="sforge-cb"><input type="checkbox" name="<?php echo esc_attr( SFORGE_OPT ); ?>[form_turnstile]" value="1" <?php checked( ! empty( $o['form_turnstile'] ) ); ?>> <?php echo wp_kses_post( __( 'Verify submissions with Cloudflare Turnstile', 'staticforge-for-cloudflare-pages' ) ); ?></label>
							<p style="margin:8px 0 4px"><label for="sforge_form_turnstile_site"><?php esc_html_e( 'Turnstile site key (public)', 'staticforge-for-cloudflare-pages' ); ?></label></p>
							<input type="text" id="sforge_form_turnstile_site" name="<?php echo esc_attr( SFORGE_OPT ); ?>[form_turnstile_site]" value="<?php echo esc_attr( $o['form_turnstile_site'] ?? '' ); ?>" class="regular-text code" placeholder="0x4AAAAAAA...">
							<p class="description"><?php echo wp_kses_post( __( 'A honeypot is always on. Turnstile adds a real bot check &mdash; create a widget at Cloudflare &rarr; Turnstile, paste the <strong>site key</strong> here, and the <strong>secret key</strong> in the deploy box below.', 'staticforge-for-cloudflare-pages' ) ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sforge_form_worker_name"><?php esc_html_e( 'Worker name', 'staticforge-for-cloudflare-pages' ); ?></label></th>
						<td>
							<input type="text" id="sforge_form_worker_name" name="<?php echo esc_attr( SFORGE_OPT ); ?>[form_worker_name]" value="<?php echo esc_attr( $o['form_worker_name'] ?? '' ); ?>" class="regular-text code" placeholder="<?php echo esc_attr( ( $o['project_name'] ?? 'staticforge' ) . '-forms' ); ?>">
							<p class="description"><?php esc_html_e( 'Optional. The Cloudflare Worker script name. Leave blank to derive it from the project slug.', 'staticforge-for-cloudflare-pages' ); ?></p>
						</td>
					</tr>
				</table>
			</div>
		</section>

		<div class="sforge-form-foot">
			<?php submit_button( __( 'Save Settings', 'staticforge-for-cloudflare-pages' ), 'primary large', 'submit', false ); ?>
		</div>
	</form>

	<section class="sforge-section" id="sforge-forms">
		<header class="sforge-section-head">
			<span class="sforge-section-icon sforge-section-icon-teal"><span class="dashicons dashicons-cloud-upload"></span></span>
			<div>
				<h2><?php esc_html_e( 'Form Handler Deployment', 'staticforge-for-cloudflare-pages' ); ?></h2>
				<p><?php esc_html_e( 'Deploy (or update) the Worker that emails your submissions. Save your Forms settings above first.', 'staticforge-for-cloudflare-pages' ); ?></p>
			</div>
		</header>
		<div class="sforge-section-body">
			<?php $worker_url = (string) ( $o['form_worker_url'] ?? '' ); ?>
			<?php if ( $worker_url !== '' ) : ?>
				<div class="sforge-callout sforge-callout-success">
					<strong><?php esc_html_e( 'Handler deployed', 'staticforge-for-cloudflare-pages' ); ?></strong>
					<span>
						<?php echo wp_kses_post( __( 'Submissions post to:', 'staticforge-for-cloudflare-pages' ) ); ?>
						<code><?php echo esc_html( $worker_url ); ?></code>
					</span>
				</div>
				<p class="description">
					<?php echo wp_kses_post( __( 'Add the form to any page with the shortcode <code>[sforge_form]</code>, then run a rebuild so the page ships. Optional attributes: <code>[sforge_form button="Send" success="Thanks!"]</code>.', 'staticforge-for-cloudflare-pages' ) ); ?>
				</p>
			<?php else : ?>
				<div class="sforge-callout sforge-callout-info">
					<strong><?php esc_html_e( 'Not deployed yet', 'staticforge-for-cloudflare-pages' ); ?></strong>
					<span><?php esc_html_e( 'Fill in the Forms settings above, save, then paste your API key and deploy.', 'staticforge-for-cloudflare-pages' ); ?></span>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sforge-deploy-form" autocomplete="off">
				<input type="hidden" name="action" value="sforge_deploy_form">
				<?php wp_nonce_field( 'sforge_action' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="sforge_form_key"><?php esc_html_e( 'Email API key', 'staticforge-for-cloudflare-pages' ); ?></label></th>
						<td>
							<input type="password" id="sforge_form_key" name="sforge_form_key" value="" class="regular-text code" autocomplete="new-password" placeholder="<?php echo ! empty( $o['form_key_set'] ) ? esc_attr__( 'Already set — re-enter to redeploy', 'staticforge-for-cloudflare-pages' ) : ''; ?>">
							<p class="description"><?php esc_html_e( 'Pushed to the Worker as a secret on deploy. Never saved in WordPress, so re-enter it whenever you redeploy.', 'staticforge-for-cloudflare-pages' ); ?></p>
						</td>
					</tr>
					<?php if ( ! empty( $o['form_turnstile'] ) ) : ?>
					<tr>
						<th scope="row"><label for="sforge_turnstile_secret"><?php esc_html_e( 'Turnstile secret key', 'staticforge-for-cloudflare-pages' ); ?></label></th>
						<td>
							<input type="password" id="sforge_turnstile_secret" name="sforge_turnstile_secret" value="" class="regular-text code" autocomplete="new-password">
							<p class="description"><?php esc_html_e( 'Required while Turnstile is enabled. Also set on every deploy, never stored.', 'staticforge-for-cloudflare-pages' ); ?></p>
						</td>
					</tr>
					<?php endif; ?>
				</table>
				<button type="submit" class="button button-primary button-large"><span class="dashicons dashicons-cloud-upload"></span> <?php echo $worker_url !== '' ? esc_html__( 'Redeploy form handler', 'staticforge-for-cloudflare-pages' ) : esc_html__( 'Deploy form handler', 'staticforge-for-cloudflare-pages' ); ?></button>
			</form>

			<?php if ( $worker_url !== '' ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:12px" onsubmit="return confirm(<?php echo esc_attr( wp_json_encode( __( 'Delete the form handler Worker from Cloudflare? The form will stop working until you deploy again.', 'staticforge-for-cloudflare-pages' ) ) ); ?>);">
					<input type="hidden" name="action" value="sforge_remove_form">
					<?php wp_nonce_field( 'sforge_action' ); ?>
					<button type="submit" class="button button-link-delete"><span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Remove form handler', 'staticforge-for-cloudflare-pages' ); ?></button>
				</form>
			<?php endif; ?>
		</div>
	</section>

	<script>
	(function () {
		var presets = <?php echo wp_json_encode( SFORGE_Forms::presets() ); ?>;
		var sel = document.getElementById('sforge_form_provider');
		if (!sel) return;
		sel.addEventListener('change', function () {
			var p = presets[sel.value];
			if (!p) return;
			var set = function (id, val) { var el = document.getElementById(id); if (el) el.value = val; };
			set('sforge_form_endpoint', p.endpoint);
			set('sforge_form_auth_name', p.auth_header_name);
			set('sforge_form_auth_tpl', p.auth_header_tpl);
			set('sforge_form_body_template', p.body_template);
			var ct = document.getElementById('sforge_form_content_type');
			if (ct) ct.value = p.content_type;
			var hint = document.querySelector('.sforge-provider-hint');
			if (hint && p.key_hint) hint.textContent = p.key_hint;
		});
	})();
	</script>

	<section class="sforge-section sforge-section-actions">
		<header class="sforge-section-head">
			<span class="sforge-section-icon sforge-section-icon-teal"><span class="dashicons dashicons-controls-play"></span></span>
			<div>
				<h2><?php esc_html_e( 'Actions', 'staticforge-for-cloudflare-pages' ); ?></h2>
				<p><?php esc_html_e( 'Verify the connection and push a deploy.', 'staticforge-for-cloudflare-pages' ); ?></p>
			</div>
		</header>
		<div class="sforge-section-body sforge-actions">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="sforge_test_connection">
				<?php wp_nonce_field( 'sforge_action' ); ?>
				<button type="submit" class="button button-secondary button-large"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Test Connection', 'staticforge-for-cloudflare-pages' ); ?></button>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="sforge_partial_rebuild">
				<?php wp_nonce_field( 'sforge_action' ); ?>
				<button type="submit" class="button button-primary button-large" title="<?php esc_attr_e( 'Re-renders only the pages affected by recent edits, reusing the export cache for everything else.', 'staticforge-for-cloudflare-pages' ); ?>"><span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Rebuild Changed + Deploy', 'staticforge-for-cloudflare-pages' ); ?></button>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="sforge_full_rebuild">
				<?php wp_nonce_field( 'sforge_action' ); ?>
				<button type="submit" class="button button-secondary button-large" title="<?php esc_attr_e( 'Re-renders every page on the site. Slower, but reconciles the export cache from scratch.', 'staticforge-for-cloudflare-pages' ); ?>"><span class="dashicons dashicons-cloud-upload"></span> <?php esc_html_e( 'Full Rebuild + Deploy', 'staticforge-for-cloudflare-pages' ); ?></button>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="sforge_clear_log">
				<?php wp_nonce_field( 'sforge_action' ); ?>
				<button type="submit" class="button button-link-delete button-large"><span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Clear Log', 'staticforge-for-cloudflare-pages' ); ?></button>
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
				<h2><?php esc_html_e( 'Activity Log', 'staticforge-for-cloudflare-pages' ); ?>
					<span class="sforge-status sforge-status-idle"><span class="sforge-dot sforge-dot-idle"></span><span><?php esc_html_e( 'Idle', 'staticforge-for-cloudflare-pages' ); ?></span></span>
				</h2>
				<p><?php esc_html_e( 'Auto-refreshes every 4 seconds. The status pill above shows the live deploy state.', 'staticforge-for-cloudflare-pages' ); ?></p>
			</div>
		</header>
		<div class="sforge-log">
			<?php $log = SFORGE_Logger::get(); ?>
			<?php if ( empty( $log ) ) : ?>
				<p class="sforge-log-empty"><em><?php echo wp_kses_post( __( 'No activity yet. Click <strong>Test Connection</strong> or <strong>Rebuild + Deploy Now</strong> to see entries.', 'staticforge-for-cloudflare-pages' ) ); ?></em></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead><tr><th class="sforge-col-time"><?php esc_html_e( 'Time', 'staticforge-for-cloudflare-pages' ); ?></th><th class="sforge-col-level"><?php esc_html_e( 'Level', 'staticforge-for-cloudflare-pages' ); ?></th><th><?php esc_html_e( 'Message', 'staticforge-for-cloudflare-pages' ); ?></th></tr></thead>
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
			<?php
			/* translators: %s: author name, linked to their website. */
			printf( wp_kses_post( __( 'Built by %s', 'staticforge-for-cloudflare-pages' ) ), '<a href="https://www.gunjanjaswal.me" target="_blank" rel="noopener">Gunjan Jaswal</a>' );
			?> &middot;
			<a href="mailto:hello@gunjanjaswal.me">hello@gunjanjaswal.me</a>
		</div>
	</footer>
</div>
