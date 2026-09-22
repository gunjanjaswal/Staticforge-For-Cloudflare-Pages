=== StaticForge for Cloudflare Pages ===
Contributors: gunjanjaswal
Donate link: https://ko-fi.com/gunjanjaswal
Tags: cloudflare, static-site, deploy, seo, sitemap
Requires at least: 5.8
Tested up to: 7.1
Stable tag: 1.8.4
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Auto-export WordPress as static HTML to Cloudflare Pages on publish — with SEO meta, JSON-LD schemas, sitemaps, and robots.txt.

== Description ==

**StaticForge for Cloudflare Pages** turns your WordPress site into a static site that lives on Cloudflare Pages, automatically. On every publish or update of any selected post type, the plugin renders your whole site to static HTML, injects a complete SEO metadata baseline (when no other SEO plugin is present), inlines all linked CSS so pages are self-contained, mirrors your sitemap structure, ships an editable `robots.txt`, and pushes everything to Cloudflare Pages via the Direct Upload API.

The WordPress install (your "dashboard") becomes the editor only. Public visitors hit the static deployment on Cloudflare's edge — fast, free, and resilient.

= Key features =

* **Whole-site export** — homepage, posts, pages, custom post types, taxonomy archives, author archives.
* **TranslatePress multilingual export** — auto-detects TranslatePress and adds every secondary-language URL to the export so each language renders to static HTML in the deploy. Works with subdirectory mode (`/fr/`, `/de/`); subdomain/separate-domain language URLs are skipped (one Cloudflare Pages project serves one host). Auto-on when detected; opt out via the `sforge_translatepress_export` filter.
* **Theme-independent** — works with any theme. Renders pages exactly as a real visitor would see them.
* **Inlined CSS** — all `<link rel="stylesheet">` tags are fetched and embedded as `<style>` blocks. Each deployed page is fully self-contained.
* **Featured image LCP boost** — auto-adds `fetchpriority="high"`, `loading="eager"`, `decoding="async"` to the post's featured image so the browser prioritises it as the LCP candidate. Improves Core Web Vitals on every theme that uses `the_post_thumbnail()` or `get_the_post_thumbnail()`.
* **Built-in SEO metadata injection** — when no other SEO plugin is detected, automatically emits a full baseline:
  * `<meta description>` — smart fallback chain (excerpt → trimmed content → user bio → term description → site tagline).
  * `<meta robots>` with `index, follow, max-image-preview:large` and friends.
  * `<link rel="canonical">`.
  * **Open Graph**: `og:type`, `og:title`, `og:description`, `og:url`, `og:site_name`, `og:locale`, `og:image` with dimensions and alt; `article:published_time`, `article:modified_time`, `article:author`, `article:section`, `article:tag` on posts; `profile:first_name`, `profile:last_name`, `profile:username` on author pages.
  * **Twitter Card** — `summary_large_image` when an image is available, otherwise `summary`; title, description, image, creator.
* **Rich JSON-LD schemas** — auto-emitted in `<head>`:
  * `WebSite` + `SearchAction`, `Organization` on every page.
  * `Article` with linked `author` Person, `publisher` Organization, image, dates, articleSection, keywords on single posts.
  * `WebPage` with `primaryImageOfPage` on pages and custom post types.
  * **`Person` + `ProfilePage` schema on author archives** — display name, URL, bio, avatar `ImageObject` (256×256), `sameAs` social links pulled from `user_url` and Twitter/Facebook/LinkedIn/Instagram/YouTube/GitHub user meta.
  * `CollectionPage` for taxonomy and term archives.
  * `BreadcrumbList` on all singulars and archives.
  * **Auto-detected `FAQPage`** — extracts Q/A pairs from Yoast / Rank Math / SEOPress FAQ blocks, OR native HTML5 `<details><summary>` markup.
  * **Auto-detected `HowTo`** — extracts steps from Yoast / Rank Math HowTo blocks, OR posts whose title starts with "How to" + has an ordered list with 3+ items.
* **Two-tier dedup safety** — auto-disables to avoid duplicates:
  * **General SEO plugins** (skip ALL our injection): Yoast, Rank Math, All in One SEO Pack (v4+ & legacy), SEOPress, The SEO Framework, Slim SEO, Squirrly, SmartCrawl, WP Meta SEO.
  * **Schema-only plugins** (skip ONLY our JSON-LD; meta + og still emit): Schema & Structured Data for WP & AMP (saswp by Magazine3), Schema Pro by Brainstorm Force, WPSSO Core, Schema (by Hesham), Schema App, and Magazine3 Schema variants.
  * Override via setting or filters (`sforge_seo_competing_plugin`, `sforge_schema_competing_plugin`).
* **Sitemap mirroring + fallback generation** — discovers `/sitemap.xml`, `/sitemap_index.xml`, `/wp-sitemap.xml`, follows index files, fetches child sitemaps, handles CDATA-wrapped `<loc>` entries, rewrites origin URLs to your live domain (including protocol-relative `//host` variants), and strips `<?xml-stylesheet ... ?>` directives so the dashboard host doesn't leak into browser-rendered sitemap views. Bundles them all in the deploy. **When the origin exposes no sitemap** (no SEO plugin, WP core sitemap disabled, sub-directory install with non-standard paths, etc.), the plugin builds a standards-compliant `<urlset>` `sitemap.xml` itself from the crawled URL list — with `<lastmod>` resolved from `get_post_modified_time()`, `<changefreq>weekly</changefreq>`, and `<priority>` (1.0 home / 0.7 elsewhere). Live site always ships a sitemap.
* **Self-hosted font bundling (fixes font CORS errors)** — a `@font-face` font still pointing at your WordPress host is a cross-origin request once the page is served from `*.pages.dev`, and browsers block it because the origin sends no `Access-Control-Allow-Origin` header. The plugin scans each rendered page for `.woff2 / .woff / .ttf / .otf / .eot` files on your own host, ships them inside the deploy, and rewrites their URLs to your Public Site URL so they load same-origin. Covers theme webfonts, icon fonts, and Astra's local Google Fonts (`wp-content/astra-local-fonts/`). Third-party fonts (Google Fonts on `fonts.gstatic.com`, etc.) already send CORS headers and are left alone. On by default.
* **Extra paths to include** — bundle files and folders the crawler never sees in the rendered HTML: a plugin's icon font (e.g. Elementor's Font Awesome), a webfont directory, a downloadable PDF. List them one per line relative to your WordPress root and they're copied straight off local disk into the deploy, with their `/wp-content/*` URLs pointed at the live host so the deployed page loads the bundled copy. A bare path is also looked up under `wp-content/` if it isn't found at the root. Folders recurse, single files copy as-is, trailing wildcards (`*.pdf`) expand. Confined to the WordPress root; capped at 5,000 files / 200 MB per rebuild.
* **Granular sitemap generator settings** — when the fallback runs, you control exactly what gets listed: per-public-post-type checkboxes, include/exclude homepage, taxonomy archives, author archives, and an option to split the output into a `<sitemapindex>` referencing per-type sub-sitemaps (`sitemap-post.xml`, `sitemap-page.xml`, `sitemap-authors.xml`, `sitemap-taxonomy-category.xml`, etc.) for cleaner Search Console submission. Independent of Export Scope. Filter `sforge_sitemap_groups` to mutate the URL list.
* **Editable robots.txt for the live site with auto-managed Sitemap: line** — leave blank to auto-generate, or paste your own `Allow:` / `Disallow:` rules. Any `Sitemap:` directive you type is stripped and replaced with the URL of the actually-deployed sitemap (`sitemap.xml` / `sitemap_index.xml` / `wp-sitemap.xml` / etc.) so robots.txt never points at a dead URL. Independent of the dashboard's own robots.txt.
* **Dashboard auto-noindex on activation (social-aware)** — when the plugin activates it locks the WordPress install out of search engines (so editors only ever appear via the static deployment). Social/messaging/preview scrapers (Facebook, LinkedIn, Twitter/X, Pinterest, WhatsApp, Slack, Discord, Telegram, Applebot, Reddit, Tumblr, Mastodon, Bluesky, iframely, Embedly) are explicitly allowed `/wp-content/uploads/` so og:image previews and oEmbed thumbnails still resolve when a post is shared. Four enforcement layers, all bypassed when the plugin's own renderer fetches a page (detected via `X-SFORGE-Export` header), and additionally bypassed for social-scraper user agents and `/wp-content/uploads/` requests:
  1. Physical `robots.txt` at webroot with `Disallow: /` (any existing file is backed up to `robots.txt.sforge-backup` and restored on deactivation).
  2. `robots_txt` WordPress filter for the dynamic fallback.
  3. `wp_robots` filter adding `noindex,nofollow` to the meta robots tag.
  4. `send_headers` action emitting `X-Robots-Tag: noindex, nofollow, noarchive, nosnippet` HTTP header on every response.
  Toggle via the **Block dashboard from search engines** setting (default on); flipping the toggle applies/restores the physical robots.txt instantly.
* **Defensive noindex stripping** — removes `noindex` / `nofollow` / `noarchive` directives from rendered HTML before deploy, so your live site stays indexable even if the source dashboard is locked down.
* **Auto-deploy on publish/update** — debounced (default 120s) so rapid edit clusters collapse into one deploy.
* **Cloudflare Pages Direct Upload** — no Git integration required. Uses the official content-addressable upload API: only changed assets are re-uploaded across deploys.
* **Live progress UI** — activity log auto-refreshes every 4 seconds with batch-by-batch upload progress, render percentages, and a status pill (Idle / Queued / Working).
* **Setup Guide built in** — full walk-through for Cloudflare Pages project creation, API token setup, and plugin configuration, all inside WP admin.

= How it works =

1. On publish/update, plugin queues a full-site rebuild via `wp_schedule_single_event`.
2. Crawler builds URL list (homepage + all published posts/pages of selected types + taxonomy term archives + author archives).
3. SEO injector hooks into `wp_head` and emits meta + JSON-LD for the rendering page (skipped if another SEO plugin is active).
4. Renderer fetches each URL via `wp_remote_get`, inlines CSS, rewrites origin URLs to your live domain, strips defensive noindex meta and admin-bar artefacts.
5. SEO module discovers and mirrors `/sitemap.xml`, `/sitemap_index.xml`, `/wp-sitemap.xml` and any child sitemaps; if none found, auto-generates `sitemap.xml` from the crawled URL list. Emits the configured `robots.txt`.
6. Deployer hashes each file, asks Cloudflare which assets are new, uploads only the new ones in batches (100 files / 25 MiB each), then creates a deployment via multipart/form-data.
7. Result: a new Cloudflare Pages deployment URL, logged with a clickable link.

= What is NOT bundled =

By default, files under `/wp-content/uploads/`, theme assets, plugin assets, and fonts under `/wp-content/` are kept pointing at your WordPress origin so they keep working without re-uploading multi-gigabyte media folders. Make sure your origin is reachable over HTTPS (proxy through Cloudflare if your origin's SSL cert is fragile).

If your origin can't be reached from Cloudflare (shared hosting firewall, IP allow-list, no proxy option), enable **Export Scope → Bundle `/wp-content/uploads/` into deploy**. The plugin then fetches every uploads URL referenced in the rendered HTML and ships those files inside the CF Pages deploy itself — no origin dependency at runtime. Theme/plugin assets still load from origin. See the **Bundle `/wp-content/uploads/` (recommended for shared hosting)** section below.

= Why this plugin =

* Simpler scope — Cloudflare Pages only, Direct Upload only.
* Built-in setup walkthrough, no docs hunting.
* Live progress UI with granular per-batch logging.
* Free tier compatible — no build minutes consumed.
* SEO baseline included — no extra plugin needed for tags + JSON-LD.
* No external dependencies, no SaaS, no premium tier.

== External services ==

This plugin connects to the **Cloudflare Pages API** (`https://api.cloudflare.com/client/v4`) to deploy your exported static site. This is required core functionality — without it the plugin cannot upload your site to Cloudflare.

**What the service is and what it is used for:**
Cloudflare Pages is a static site hosting platform operated by Cloudflare, Inc. The plugin uses the Cloudflare Pages Direct Upload API to publish your statically rendered WordPress site to a Cloudflare Pages project that you create and own.

**What data is sent, and when:**
The plugin contacts the Cloudflare Pages API on these occasions:

* When you click **Test Connection** in the plugin settings. Sent: your Cloudflare API token (in the `Authorization` header), your Account ID, and your Pages project slug. Used to verify the project exists and the token has access.
* When you click **Rebuild + Deploy Now**, or after any post/page/CPT publish/update if **Auto-deploy** is enabled (debounced). The plugin: (1) requests a short-lived upload JWT from `/pages/projects/{project}/upload-token`; (2) sends a list of SHA-256 hashes of the files in the export to `/pages/assets/check-missing` to find which files Cloudflare does not already have; (3) uploads only the missing assets (HTML, CSS, JS, images, sitemap.xml, robots.txt) to `/pages/assets/upload`; (4) POSTs a final deployment manifest + branch name to `/pages/projects/{project}/deployments`. All requests include your API token in the `Authorization: Bearer` header.

**What is NOT sent:** the plugin never sends WordPress database credentials, user passwords, post drafts, private content, settings beyond the four Cloudflare credentials, or any analytics/telemetry beacons. Only the rendered public HTML/CSS/JS/asset files that already make up your site are uploaded — the same content visitors would see.

**Cloudflare Pages service links:**

* Cloudflare Pages product page: [https://pages.cloudflare.com/](https://pages.cloudflare.com/)
* Cloudflare API documentation: [https://developers.cloudflare.com/api/](https://developers.cloudflare.com/api/)
* Cloudflare Terms of Service: [https://www.cloudflare.com/website-terms/](https://www.cloudflare.com/website-terms/)
* Cloudflare Self-Service Subscription Agreement (covers Workers & Pages): [https://www.cloudflare.com/terms/](https://www.cloudflare.com/terms/)
* Cloudflare Privacy Policy: [https://www.cloudflare.com/privacypolicy/](https://www.cloudflare.com/privacypolicy/)

You retain full ownership and control of your Cloudflare account, Pages project, API token, and deployed content. To stop using the service, revoke the API token in your Cloudflare dashboard and deactivate the plugin.

== Installation ==

1. Upload the `staticforge-for-cloudflare-pages` folder to `/wp-content/plugins/`, OR install the zip via Plugins → Add New → Upload Plugin.
2. Activate **StaticForge for Cloudflare Pages** through the Plugins menu.
3. Go to **StaticForge for Cloudflare Pages** in the admin sidebar.
4. Open the **Setup Guide** (linked at the top of the settings page) and follow the 6 steps:
  * Create a Cloudflare Pages project in *Direct Upload* mode.
  * Create an API Token with `Account → Cloudflare Pages → Edit` permission.
  * Copy your Cloudflare Account ID.
  * Paste those values + project slug + `main` branch + your `<project>.pages.dev` URL into the plugin settings.
  * Save → Test Connection → Rebuild + Deploy Now.

== Frequently Asked Questions ==

= Does this work with the free Cloudflare Pages tier? =

Yes. Direct Upload deployments don't consume build minutes. There's a soft cap of about 100 deployments per day per project — the plugin's debounce setting (default 120 seconds) keeps you well under that for normal editorial workflows.

= Does the SEO injection conflict with Yoast / Rank Math / AIO SEO / Schema plugins? =

No — two-tier dedup is built in.

**General SEO plugins** (handle everything: meta + og + schema) — when any of these is detected, ALL our injection is skipped: Yoast SEO (free or Premium), Rank Math, All in One SEO Pack (v4+ and legacy), SEOPress, The SEO Framework, Slim SEO, Squirrly, SmartCrawl, WP Meta SEO.

**Schema-only plugins** (handle only JSON-LD) — when any of these is detected, ONLY our JSON-LD is skipped, but our meta description, robots, canonical, Open Graph, and Twitter Card tags still emit so you don't lose social previews: Schema & Structured Data for WP & AMP (saswp by Magazine3), Schema Pro, WPSSO Core, Schema (by Hesham), Schema App, Magazine3 Schema variants.

Niche plugin not in either list? Extend detection via the `sforge_seo_competing_plugin` filter (general) or `sforge_schema_competing_plugin` filter (schema-only), or simply untick the "Inject SEO meta" setting.

= How does the sitemap generator decide what to include? =

If the plugin can reach `sitemap.xml`, `sitemap_index.xml`, or `wp-sitemap.xml` on your origin, it mirrors that as-is (including all child sitemaps for sitemap-index files).

If none are reachable, the plugin generates `sitemap.xml` itself from the **Sitemap Generator** settings (StaticForge for Cloudflare Pages → Settings → Sitemap Generator):

* **Post Types** — multi-checkbox of public post types. Default: post + page.
* **Include Homepage** — adds the front page and posts page (if separate).
* **Include Taxonomy archives** — all public taxonomy term archives.
* **Include Author archives** — authors with at least one published post.
* **Split into multiple files** — off (single `sitemap.xml`) or on (a `<sitemapindex>` referencing per-type sub-sitemaps such as `sitemap-post.xml`, `sitemap-page.xml`, `sitemap-authors.xml`, `sitemap-taxonomy-category.xml`).

Sitemap inclusion is independent of Export Scope, so you can export a CPT without listing it in the sitemap and vice versa. Filter `sforge_sitemap_groups` exposes the grouped URL list for custom modification.

The activity log shows which path was taken: `Sitemap mirrored from origin: ...` vs `Sitemap generated locally (single mode|split mode): N URLs ...`.

= How do I get clean /wp-content/ URLs on the live site? =

By default the plugin keeps `/wp-content/*` URLs (theme CSS/JS, plugin assets, uploads) pointing at the WordPress origin so those assets keep working without bundling multi-gigabyte folders in every deploy. Structured data (`og:image`, JSON-LD `image`/`logo`/`thumbnailUrl`) and `<img src>` will therefore reference your dashboard host on the deployed site.

To make every URL on the deployed site reference the live host, you need a proxy on the live host that forwards `/wp-content/*` requests back to the WordPress dashboard, and then tick **Export Scope → Rewrite `/wp-content/` URLs** in the plugin settings.

**Cloudflare Worker (recommended).** Create a Worker (`Workers & Pages → Create application → Workers → Hello World`) with this code:

  export default {
    async fetch(request) {
      const url = new URL(request.url);
      if (url.pathname.startsWith('/wp-content/')) {
        url.hostname = 'dashboard.example.com';
        return fetch(url, request);
      }
      return fetch(request);
    }
  };

Replace `dashboard.example.com` with your actual dashboard host. Deploy, then add routes `example.com/wp-content/*` and `www.example.com/wp-content/*` under the Worker's Triggers tab.

**Nginx / Apache reverse proxy.** On the live host:

  # Nginx
  location /wp-content/ {
      proxy_pass         https://dashboard.example.com;
      proxy_set_header   Host dashboard.example.com;
      proxy_ssl_server_name on;
  }

Verify with `curl -I https://example.com/wp-content/uploads/some-file.jpg` — it should return HTTP 200 with the image content-type. Only then tick the **Rewrite `/wp-content/` URLs** setting and **Rebuild + Deploy Now**.

If the toggle is on but no proxy is set up, every image, theme stylesheet, and plugin script on the deployed site will return 404.

The full step-by-step is also available inside the plugin: **StaticForge for Cloudflare Pages → Setup Guide → Clean /wp-content/ URLs (advanced)**.

= How do I serve images when my origin firewall blocks Cloudflare? =

Shared-hosting servers (HostArmada, SiteGround, GoDaddy, etc.) often block traffic from Cloudflare Workers / proxy IPs. Symptoms: the proxy approach above returns `522` or `520` for `/wp-content/uploads/*`, while a direct browser request to `dashboard.example.com` works fine. You usually cannot whitelist Cloudflare's edge IPs on shared hosting.

Solution: tick **Export Scope → Bundle `/wp-content/uploads/` into deploy** (leave **Rewrite `/wp-content/` URLs** off). The plugin then:

1. Scans every rendered page for `/wp-content/uploads/...` references (`<img src>`, `srcset`, `og:image`, JSON-LD `image`/`logo`/`thumbnailUrl`, inline CSS `url()`, oEmbed thumbnails).
2. Fetches each unique file from your origin during rebuild.
3. Uploads them into the Cloudflare Pages deploy at their original `/wp-content/uploads/...` paths.
4. Rewrites image URLs in HTML/JSON-LD to the live host so the static site is fully self-contained.

Theme CSS/JS and plugin assets still load from the origin (those rarely cause shared-hosting firewall issues). Cost scales with what's actually referenced — only files used by exported pages get bundled, not the entire media library. Cloudflare's `check-missing` API deduplicates unchanged files between deploys so subsequent rebuilds only upload new images.

Verify after deploy: `curl -I https://example.com/wp-content/uploads/<any-image>.jpg` should return `HTTP 200` with `Server: cloudflare` (served by CF Pages, not your origin).

= How do I ship a BIMI logo or other file that isn't linked from any page? =

The crawler finds files by following the links in your rendered pages, so anything nothing links to never gets discovered. A BIMI logo is the classic case: your DNS record points straight at it, no page references it, and so it's missing from the deploy. The same goes for `ads.txt`, an `apple-app-site-association` file, a domain-verification file, `security.txt`, or a PDF you only hand out by direct link.

Use **Extra paths to include** in the plugin settings (a text box, one path per line, relative to your WordPress root). Everything you list there is copied straight off disk into the deploy whether or not a page links to it.

For a BIMI image, put the SVG somewhere inside your WordPress install and list its path. If you want a tidy URL like `/.well-known/bimi/logo.svg`, create a `.well-known/bimi/` folder in your WordPress root, drop the file in, and add:

  .well-known/bimi/logo.svg

If the exact URL doesn't matter (BIMI lets your DNS record point at any URL), the uploads folder is fine:

  wp-content/uploads/bimi/logo.svg

You can also list a whole folder (`wp-content/uploads/bimi`) or a wildcard (`wp-content/uploads/bimi/*.svg`) instead of naming each file. Rebuild + Deploy, and the file lands at the matching path on the live site. Then set your BIMI DNS record's location to that final URL.

Two BIMI-specific notes the plugin can't do for you: the SVG must be the "SVG Tiny Portable/Secure" profile that mailbox providers require, and if you use a VMC certificate its URL goes in the `a=` tag of the same record. The plugin's job here is just making sure the file is actually there to be fetched.

= After DNS cutover I can't log in to wp-admin — it bounces me to the live site. =

This is the most common cutover mistake, and it also blocks redeploys. **Symptom:** clicking into wp-admin sends you to the *public* host's login, e.g. `https://example.com/wp-login.php?redirect_to=https%3A%2F%2Fdashboard.example.com%2Fwp-admin%2F...`. But `example.com` is now the static Cloudflare Pages site with no WordPress on it, so login fails — and you can't reach the plugin to redeploy.

**Cause:** WordPress's own **WP Address** (`siteurl`) and **Site Address** (`home`) are still set to the public host (`example.com`). WordPress builds the login URL from `siteurl`, so it sends you to the static site instead of your dashboard.

**Fix:** point WordPress's two addresses at your dashboard host. The reliable way (works even when you're locked out of wp-admin) is to add these to `wp-config.php`, just above the line `/* That's all, stop editing! Happy publishing. */`:

  define( 'WP_HOME',    'https://dashboard.example.com' );
  define( 'WP_SITEURL', 'https://dashboard.example.com' );

Save, then log in from a private/incognito window (so stale cookies don't interfere) at `https://dashboard.example.com/wp-admin`.

**Important — keep the two settings separate.** WordPress itself lives on `dashboard.example.com`; the plugin's **Public Site URL** stays `https://example.com`. The renderer rewrites the dashboard host to the Public Site URL during export, so the deployed static site still shows clean `example.com` links. Only WordPress's own WP Address / Site Address move to the dashboard subdomain — never the plugin's Public Site URL.

= Why does nothing happen when I click "Rebuild + Deploy Now"? =

The rebuild runs on a WordPress scheduled event a few seconds after you click, so it depends on WP-Cron. You'll see `Full rebuild queued (manual).` in the log immediately, but if `DISABLE_WP_CRON` is defined or the site gets almost no traffic, the event may never fire and you won't see `Full rebuild started.`. Load any front-end page to nudge WP-Cron, or set up a real system cron hitting `wp-cron.php` every minute.

= What do the "Test FAIL" connection errors mean? =

* `Account ID, API token and project name are required` — one of the three fields is blank. Fill all three and Save before testing.
* `Project not found` — the Pages Project field must be the project slug only (e.g. `mysite`), never the full `mysite.pages.dev` URL. Also confirm the project is in the same Cloudflare account whose Account ID you pasted.
* An authentication / HTTP 403 message — the API token is wrong, expired, or under-scoped. Recreate it with exactly `Account · Cloudflare Pages · Edit` and Account Resources including the right account.
* `Upload token request failed` — the token tested OK but lacks write access; recreate it with the Edit (not Read) permission.

= A deploy started but failed — what do the upload / deploy errors mean? =

* `Deploy FAIL: Request body is incorrect` — an old plugin build. v1.0.0+ sends multipart/form-data; update the plugin.
* `Skipping "<file>" (NN MB): exceeds Cloudflare's 25 MiB per-file limit` — Cloudflare Pages rejects any single file above 25 MiB. The plugin now catches these before the upload, names the file in the log, leaves it out, and deploys the rest. Host the file externally (Cloudflare R2, an object store, a CDN) and link to it, or shrink it under 25 MiB (re-encode the video, compress the PDF, downscale the image).
* `Asset upload failed: HTTP 4xx/5xx ...` — Cloudflare rejected the upload. The log now quotes the HTTP status when Cloudflare doesn't return a structured error (older builds showed a bare `unknown` here). `HTTP 413` means a file too large slipped through; a 5xx or a timeout is usually transient, so re-run the deploy.
* `Deployment failed` — a Cloudflare-side rejection (the reason is quoted in the log); the common cause is more than 20,000 files in one deployment (CF Pages free-tier limit). Trim Export Scope.
* `check-missing failed` — a transient API / token hiccup; re-run the deploy, and re-test the connection if it persists.

= Some pages didn't render ("Render fail ... HTTP" / "Nothing rendered"). =

The plugin fetches your own URLs via `wp_remote_get`. A few failures are harmless; many of them (or `Nothing rendered, deploy skipped.`) mean the site is blocking itself — HTTP basic auth, an IP allow-list, an aggressive WAF, Cloudflare "Under Attack" mode, or a coming-soon / maintenance plugin. Let the origin fetch itself, or pause the blocker during deploys. For an invalid / self-signed origin certificate during migration, add `add_filter( 'sforge_sslverify', '__return_false' );`.

= My .pages.dev URL doesn't redirect to my domain. =

The redirect is a client-side JavaScript snippet (the Direct Upload API can't run `_worker.js` / Functions), so `curl -I` won't reveal it — test in a real browser. It only fires when Public Site URL is a real domain (not a `.pages.dev` URL) and the "Redirect *.pages.dev to live host" toggle is on.

= "Sitemap fallback skipped" or "returned no files" in the log. =

Your origin exposes no sitemap, so the plugin tried to generate one. `Sitemap fallback skipped: no post types / archives selected` means everything is unticked under Sitemap Generator — tick at least one post type / Homepage / Taxonomies / Authors. `Sitemap fallback returned no files` means nothing published matched the selection — confirm you have published content in those types.

= "Bundle uploads enabled, but no /wp-content/uploads/ references found". =

An image optimiser is swapping image URLs with JavaScript (e.g. EWWW Lazy Load or Easy IO), so the real URLs aren't in the rendered HTML for the bundler to find. Turn off the JS lazy-load / Easy-IO feature (keep the compression) and rebuild.

= How is FAQ schema auto-detected? =

The plugin scans your post for any of: Yoast FAQ blocks, Rank Math FAQ blocks, SEOPress FAQ blocks, OR native HTML5 `<details><summary>Question</summary>Answer</details>` markup. If found, a `FAQPage` schema with `Question` / `Answer` items is emitted.

= How is HowTo schema auto-detected? =

The plugin scans your post for: Yoast HowTo blocks, Rank Math HowTo blocks. As a fallback, posts whose title starts with "How to" / "How To" and that contain an ordered list (`<ol>`) with 3 or more items are also recognised — each list item becomes a `HowToStep`.

= Is the JSON-LD output search-engine-valid? =

Yes — the schemas follow schema.org spec with proper `@id` linking between Article ↔ Author ↔ Organization, `mainEntity` linking on ProfilePage, `BreadcrumbList` with `position` indexing, and `EntryPoint` for the homepage SearchAction. Validate with Google's Rich Results Test or schema.org Validator.

= What does the author page schema look like? =

A `Person` node (display name, URL, bio from user description, avatar 256×256 ImageObject, `sameAs` array of social URLs from `user_url` + Twitter/Facebook/LinkedIn/Instagram/YouTube/GitHub user meta) plus a `ProfilePage` node that links to the Person via `mainEntity`. Both inside a single `@graph` so search engines see them as a unit.

= Are images and uploads bundled in the deploy? =

No. The plugin keeps URLs under `/wp-content/*` pointing at your WordPress origin host. This avoids re-uploading gigabytes of media on every deploy. Make sure your origin is reachable over HTTPS.

= How is the editable robots.txt different from the dashboard's robots.txt? =

The plugin deploys a separate `robots.txt` to your Cloudflare Pages site root — that's the one search engines see when crawling your live domain. Your WordPress dashboard's own `robots.txt` (which usually says `Disallow: /` to keep the backend out of search) stays put on the dashboard and is unaffected.

= How long does the first deploy take? =

A site with ~450 pages typically takes 2–3 minutes to render and 30–90 seconds to upload, on a moderately spec'd shared host. Subsequent deploys are much faster: only changed pages get re-uploaded, thanks to content-addressable hashing.

= Can I customise the URL list? =

Yes — filter `sforge_url_list` to add or remove URLs. Filter `sforge_sitemap_candidates` to add custom sitemap locations.

= Does it work with TranslatePress / multilingual sites? =

Yes, for content. TranslatePress stores translations in the database but renders them server-side, so when StaticForge fetches a page it captures the already-translated HTML and freezes it — the database isn't needed on the live static site. As of 1.2.0 the plugin auto-detects TranslatePress and adds every secondary-language URL to the export automatically, so each language is rendered and deployed. No code required.

This works with TranslatePress in **subdirectory** mode (`example.com/fr/`, `example.com/de/`), which maps cleanly onto a single Cloudflare Pages deploy. If TranslatePress is set to **subdomain** or **separate-domain** mode (`fr.example.com`), those URLs are skipped — one Cloudflare Pages project serves a single hostname, so per-subdomain content can't come from one deploy. Switch TranslatePress to subdirectory mode, or run a separate export + separate Pages project per subdomain. Turn the integration off entirely with `add_filter( 'sforge_translatepress_export', '__return_false' )`.

= Will contact forms work on the static site? =

The form's HTML is exported and looks identical, but **submissions won't work out of the box.** Cloudflare Pages is static hosting — there's no PHP or WordPress runtime — so anything that posts to `admin-ajax.php` or `/wp-json/` (Contact Form 7, WPForms, Gravity Forms, etc.) has no backend on the live site and will silently fail to send.

To make forms work, point them at a static-friendly endpoint: a Cloudflare Pages Function / Worker, or a hosted service (Formspree, Basin, Web3Forms). StaticForge only rewrites your exact WordPress host in the exported HTML, so a form that submits to a *different* hostname (your form service or a dedicated endpoint) is left untouched and keeps working.

== Screenshots ==

1. Settings page with all configuration fields, SEO injection toggles, and live activity log.
2. Built-in Setup Guide with colour-coded step-by-step walk-through.
3. Activity log with status pill, render progress, batch-by-batch upload telemetry.
4. Sample JSON-LD output: Article with linked author Person + Organization publisher.
5. Sample author archive: Person + ProfilePage schema with sameAs social links.

== Changelog ==

= 1.8.4 =
* Fix: the **Inline CSS** option now refuses to inline a stylesheet URL that returns HTML instead of CSS. A missing stylesheet often "soft-404s" — the server answers with a 200 status and a full HTML page rather than a real 404. The classic case is Elementor's cached Google-fonts CSS (`.../uploads/elementor/google-fonts/css/roboto-<host>.css`): when that cache is regenerated under a different hostname the old file no longer exists, so the site returns the homepage for it. The plugin was embedding that entire HTML page into a `<style>` block as if it were CSS, and with several such files on a page the exported HTML ballooned to tens of megabytes — well past Cloudflare's 25 MiB per-file limit. Fetched stylesheets are now validated by content type and content, and anything that is actually HTML is skipped (the original `<link>` is left in place). If you hit this, also regenerate Elementor's font cache (Elementor → Tools → Regenerate CSS & Data) so the missing file comes back.

= 1.8.3 =
* Fix: the **Inline CSS** option no longer inlines the same stylesheet more than once. Page builders such as Elementor emit the same `<link rel="stylesheet">` many times over, and the plugin was embedding the full CSS body into every occurrence — inlining, for example, WordPress core's 131 KB block-library stylesheet 15 times on a single page, ballooning the HTML into megabytes and, on heavy pages, past Cloudflare's 25 MiB per-file limit. Each unique stylesheet is now inlined once and the duplicate `<link>` tags are dropped. Rendered pages look identical; on a real Elementor homepage this cut the exported HTML by roughly 5x.

= 1.8.2 =
* Fix: oversized files no longer break a deploy with a cryptic "Asset upload failed: unknown". Cloudflare Pages rejects any single file above 25 MiB; the plugin now checks file sizes before uploading, names the offending file and its size in the activity log ("Skipping ... exceeds Cloudflare's 25 MiB per-file limit"), skips it, and deploys everything else instead of failing the whole run.
* Improved: when Cloudflare returns an error without its usual JSON body (a 413, a 5xx, a gateway timeout), the log now shows the HTTP status — e.g. "Asset upload failed: HTTP 413 Payload Too Large" — instead of a bare "unknown", so the real cause is visible.
* Docs: updated the deploy-error FAQ (readme + in-plugin Help) to cover the new messages and where to host files that are too big for Cloudflare Pages.

= 1.8.1 =
* Docs: added an FAQ on shipping files that aren't linked from any page — a BIMI logo, `ads.txt`, `security.txt`, `apple-app-site-association`, a domain-verification file — using the existing **Extra paths to include** setting. Covers where to place a BIMI SVG for a `/.well-known/bimi/` URL and the SVG-profile / VMC gotchas that are on you rather than the plugin. Documentation only; no code changes.

= 1.8.0 =
* New: **Forms that email you on submit.** A static site can't process a POST, and the Direct Upload deploy can't run Cloudflare Functions, so a contact form has always meant reaching for an outside service. StaticForge now deploys a tiny standalone Cloudflare Worker for you that takes the submitted fields and hands them to your own email API. It's email-API-agnostic: pick a preset for Resend, SendGrid, Postmark or Mailgun (or wire up any other endpoint under "Custom"), and it fills in the endpoint, auth header and a request-body template you can edit. Your API key is stored as a Worker secret, never written into the page or sent to the browser. Drop `[sforge_form]` into any page to render a name/email/message form pointed at the handler. A honeypot is always on, and Cloudflare Turnstile is a one-tick add for real spam protection. New classes `SFORGE_Forms` and `SFORGE_Worker_Deployer`; new Forms settings section.
* Note: deploying the form handler needs your Cloudflare API token to also carry the `Account · Workers Scripts · Edit` permission, plus a free `workers.dev` subdomain on the account (claimed once from Workers & Pages). The rest of the plugin is unchanged and needs neither.

= 1.7.0 =
* New: **Translation-ready.** The admin UI, the Setup Guide, the contextual Help tabs, and the on-screen labels and descriptions are now wrapped in WordPress i18n functions against the `staticforge-for-cloudflare-pages` text domain, and a `languages/staticforge-for-cloudflare-pages.pot` template ships with the plugin. Translations can now be contributed at https://translate.wordpress.org/projects/wp-plugins/staticforge-for-cloudflare-pages/. Nothing changes for English installs.
* Fix: **More post links get rewritten to your Public Site URL.** The URL rewriter used to match only your site's exact home URL, so a link an editor had pasted as `http://` when the site is `https://`, or with a `www.` that the site doesn't use (or the other way round), or as a protocol-relative `//your-site/...`, was left pointing at the WordPress origin. Those near-miss spellings of your own host are now folded onto the canonical form first, so they rewrite along with everything else. Third-party links are untouched, and `/wp-content/` assets still follow the bundle / keep-on-origin rules as before.

= 1.6.0 =
* New: **Self-hosted fonts are bundled automatically.** A font whose `@font-face src` still points at your WordPress host is a cross-origin request once the page is served from `*.pages.dev`. Browsers fetch fonts in CORS mode, your origin doesn't send an `Access-Control-Allow-Origin` header, and the font gets blocked — the console fills with "blocked by CORS policy" errors and the page falls back to a system typeface. StaticForge now scans each rendered page for `.woff2 / .woff / .ttf / .otf / .eot` files served from your own host, ships them inside the deploy, and rewrites their URLs to your Public Site URL so they load same-origin. This covers theme webfonts, icon fonts, and Astra's local Google Fonts at `wp-content/astra-local-fonts/`. Third-party fonts (Google Fonts on `fonts.gstatic.com`, etc.) already send CORS headers and are left alone. On by default; new setting `bundle_fonts`. Ignored when "Rewrite `/wp-content/` URLs" is on, since that already rewrites fonts. Run one Full Rebuild after updating so every page picks it up.
* Improved: **Extra paths to include** now accepts a path relative to `wp-content/`. If an entry isn't found at the WordPress root, the plugin looks under `wp-content/` before giving up — so `astra-local-fonts` resolves the same as `wp-content/astra-local-fonts`, and both the copy and the URL rewrite act on the real files. This removes the most common reason a bundled asset kept pointing at the old domain: the rewrite hinges on the `wp-content/` prefix, and a bare path used to silently miss it.

= 1.5.0 =
* New: **Extra paths to include** (Export Scope settings). List files or folders, one path per line relative to your WordPress root, and the plugin copies them straight off local disk into the Cloudflare Pages deploy. It's for assets the crawler never sees in the rendered HTML — a plugin's icon font (Elementor's Font Awesome at `wp-content/plugins/elementor/assets/lib/font-awesome`, for example), a webfont folder, a downloadable PDF. Whole folders are pulled in recursively, a single file is taken as-is, and a trailing wildcard (`wp-content/uploads/2025/*.pdf`) is expanded. Bundled files under `/wp-content/` get their URLs pointed at the live host so the deployed page loads the bundled copy, while everything else under `/wp-content/` stays on origin as before. Because the files are read locally, there's no origin firewall in the way. Paths are confined to the WordPress root — anything escaping it via `..` or a symlink is skipped and logged — and each rebuild is capped at 5,000 files / 200 MB. New setting `extra_paths`, new class `SFORGE_Extra_Assets`.

= 1.4.1 =
* Fix: corrected a phpcs suppression that named a sniff which does not exist (`directory_rmdir` instead of `file_system_operations_rmdir`), so the suppression silently did nothing and Plugin Check reported an error on the export mirror cleanup.
* Fix: annotated two read-only `$_GET` reads in the post-list Rebuild notice that Plugin Check flagged for nonce verification. The state change behind them was already nonce-verified; the notice only displays.
* Fix: shortened the 1.4.0 upgrade notice, which exceeded the 300-character limit.
* No functional change.

= 1.4.0 =
* New: **Partial rebuilds.** Publishing a post used to re-render the whole site. Each page costs one HTTP round-trip to the origin and they run sequentially, so a 1,400-page site paid 1,400 fetches to fix a typo. The plugin now re-renders only the pages an edit invalidates — the post, its term archives, its author archive, its post type archive, the homepage and posts page, plus their translations — and reuses the export cache for the rest.
* New: **The export directory is now the deploy source.** Rendered pages were always written to `wp-content/uploads/sforge-export/`, but the deploy was built from an in-memory copy and that directory was never read back. It is now the source of truth for the manifest, which is what makes partial rebuilds possible: a Cloudflare Pages deployment is a whole-site snapshot, so the manifest must list every file even when only a few changed.
* Improved: **Memory no longer scales with site size.** Files are streamed from disk for hashing and read back only for assets Cloudflare reports as new, so peak memory tracks the 25 MB upload batch rather than the whole site. Helps rebuilds that stalled at the Manifest step on memory-limited hosts.
* New: **Stale pages are pruned.** Deleting or unpublishing a post removes its exported HTML so it stops being served. Pruning is scoped to HTML and refuses to run when a partial rebuild's URL list looks implausibly short, logging a warning instead of deleting most of the live site.
* New: **Two rebuild buttons.** "Rebuild Changed + Deploy" renders only what changed; "Full Rebuild + Deploy" re-renders everything and reconciles the export cache. Auto-deploy uses the partial path.
* New: **Per-post Rebuild action** on post and page list tables, for pushing one page live without waiting out the debounce.
* Improved: bundled uploads already in the export cache are no longer re-fetched from the origin on every rebuild.
* Improved: a failed render now keeps the previously exported page instead of dropping it from the deploy.
* Internals: new `SFORGE_Export_Store`, `SFORGE_Rebuild`, `SFORGE_Post_Actions`; new `SFORGE_Deployer::deploy_dir()` / `hash_file()`; new `sforge_partial_rebuild` cron hook. Asset hashing is unchanged, so assets already cached at Cloudflare stay cached.

= 1.3.1 =
* Fix: removed a UTF-8 byte order mark (BOM) that was saved into the main plugin file during the 1.3.0 release. Those three bytes sit before the opening `<?php` tag, so PHP emitted them as output on every request. Two visible symptoms: WordPress reported "The plugin generated 3 characters of unexpected output during activation" on the Plugins screen, and Site Health failed with "The REST API did not process the `context` query parameter correctly" because the stray bytes were prepended to every REST response and broke JSON parsing. Anyone on 1.3.0 should update. No functional change otherwise — 1.3.0's render origin override is untouched.

= 1.3.0 =
* New: **Render origin override** (Performance settings). The export renders each page by fetching it over HTTP from the site's own URL, one request at a time. When the domain runs behind a CDN/proxy (e.g. Cloudflare), every one of those requests leaves the server and comes back through the edge — on a site with hundreds or thousands of pages (large multilingual sites especially) that round-trip dominates the rebuild time. Set this field to a host that reaches WordPress directly on the same box (usually `http://127.0.0.1`, or `http://127.0.0.1:8080` if PHP listens on another port) and the whole crawl stays local. The plugin keeps your real domain in the `Host` header so WordPress still serves the correct site/language variant, and TLS verification is skipped for the override only (a loopback certificate won't match the public host). Applies uniformly to page renders, inlined CSS fetches, mirrored sitemaps, and bundled uploads; only URLs on the origin host are redirected, everything else is fetched unchanged. Leave blank to keep the previous behaviour. New setting `render_origin`, new helper `SFORGE_Renderer::localize_request()`.

= 1.2.1 =
* Docs: added a troubleshooting/FAQ entry for the most common DNS-cutover mistake — leaving WordPress's own **WP Address** (`siteurl`) / **Site Address** (`home`) on the public host after the apex is pointed at Cloudflare Pages. WordPress then builds the login URL against the static site (`https://example.com/wp-login.php?redirect_to=https://dashboard.example.com/...`), so you can't log in or trigger a redeploy. Fix documented in the README, the in-plugin Setup Guide, and this FAQ: pin `WP_HOME` + `WP_SITEURL` to the dashboard host in `wp-config.php` while keeping the plugin's **Public Site URL** on the public host.
* Docs: comprehensive troubleshooting on every surface. Reorganised Troubleshooting into grouped sections (Login & DNS cutover, Setup & connection, Rebuild won't start or finish, Deploy step errors, Live site looks wrong, Sitemaps & multilingual, Limits & frequency) — 26 entries keyed to the exact Activity Log messages, including the WP-Cron case where a queued rebuild never starts. The same coverage now lives in all three places: this readme/FAQ, the GitHub README, and the in-plugin Setup Guide.
* Documentation only — no functional code change.

= 1.2.0 =
* New: **TranslatePress multilingual export.** The plugin now auto-detects an active TranslatePress install and expands the export URL list with every secondary-language URL, using TranslatePress's own URL converter so the configured permalink mode is honoured. Each language is rendered to static HTML and shipped in the deploy. Translations are stored in the WordPress database but rendered server-side, so the frozen HTML is already fully translated — no runtime database dependency on the live site. Supports TranslatePress **subdirectory** mode (`/fr/`, `/de/`); secondary-language URLs on a different host (TranslatePress **subdomain / separate-domain** mode) are skipped — a single Cloudflare Pages project serves one hostname — and a notice is written to the activity log. Auto-on when TranslatePress is detected; opt out with `add_filter( 'sforge_translatepress_export', '__return_false' )`. New class `SFORGE_TranslatePress`, new filter `sforge_translatepress_export`.

= 1.1.1 =
* Fix: removed the plugin's own injected "View details" row-meta link to prevent a duplicate entry, since WordPress now auto-injects "View details" for wp.org-hosted plugins. Row meta is now `View details | Plugin Support | Contact Developer`.

= 1.1.0 =
* New: **Bundle `/wp-content/uploads/` into deploy** setting — when ticked, the plugin scans every rendered page for uploads URLs (`<img src>`, `srcset`, `og:image`, JSON-LD `image`/`logo`/`thumbnailUrl`, inline CSS `url()`, oEmbed thumbnails), fetches each file from the origin during rebuild, and ships them inside the Cloudflare Pages deploy at their original paths. Designed for shared-hosting origins (HostArmada, SiteGround, etc.) whose firewall blocks Cloudflare Worker / proxy IPs, making the standard `/wp-content/*` proxy approach return 520/522. Theme/plugin assets still load from origin; uploads cost scales with files actually referenced (CF dedupes unchanged hashes between deploys).
* Fix: `*.pages.dev` 301 redirect is now a client-side JS snippet injected into every page rather than a `functions/_middleware.js` Pages Function. The Direct Upload API does not compile a `functions/` directory or activate `_worker.js` advanced mode — those files are stored as static assets and never execute — so the previous server-side approach silently did nothing. The new JS redirect runs synchronously before any paint, preserves `path + query + hash`, and works on every deploy regardless of upload method. Canonical / og:url / JSON-LD continue to point at the live host so SEO consolidation remains correct.
* Plugin renamed from "Send Static to Pages" to "StaticForge for Cloudflare Pages". Folder slug, main file, text domain, all class/constant/function/option prefixes (`SSTP_`/`sstp_` → `SFORGE_`/`sforge_`), and the `sstp_full_rebuild` cron hook moved over together.
* One-time migration on `plugins_loaded` (priority 1): legacy `sstp_settings`, `sstp_log`, and any pending `sstp_full_rebuild` cron event are copied/rescheduled to the new keys/hook so existing installs upgrade without losing configuration. Guarded by a `sforge_migrated_from_sstp` flag.
* `uninstall.php` now also removes legacy `sstp_*` keys and clears the `sstp_full_rebuild` cron hook.
* WordPress 7.0 tested and audited — no deprecated API usage; admin-only integration so the iframed editor in WP 7.0 has no functional impact.
* WordPress 7.0 Connectors API integration: registers a `deployment_target` connector (`sforge-cloudflare-pages`) on the `wp_connectors_init` action so the plugin appears on the central Connections screen and links back to the StaticForge settings page for credential management. Falls back silently on WP < 7.0.
* Added plugin action links — `Settings` and `Support on Ko-fi` next to Deactivate.
* Added plugin row meta — `Plugin Support` (WordPress.org forum) and `Contact Developer`.
* Donate link moved to Ko-fi (https://ko-fi.com/gunjanjaswal).

= 1.0.1 =
* New: social-aware dashboard noindex — robots.txt now explicitly allows `/wp-content/uploads/` for facebookexternalhit / facebookcatalog / Twitterbot / LinkedInBot / Pinterestbot / WhatsApp / Slackbot / Discordbot / TelegramBot / Applebot / redditbot / Tumblr / iframely / Embedly / Mastodon / Bluesky / meta-externalagent. The `X-Robots-Tag` HTTP header is also skipped for media paths and social-scraper user agents so og:image previews resolve correctly when posts are shared.
* New: `*.pages.dev` 301-redirect middleware — when the configured Public Site URL is a custom domain, the plugin emits `functions/_middleware.js` into the deploy that intercepts requests to `<project>.pages.dev` and permanently redirects them to the canonical live host. Auto-skipped when Public Site URL is itself a `.pages.dev` URL.
* New: opt-in **Rewrite `/wp-content/` URLs** setting plus a "Clean /wp-content/ URLs (advanced)" section in the in-plugin Setup Guide and README covering Cloudflare Worker and Nginx/Apache reverse-proxy setups for fully clean live URLs (og:image, JSON-LD image/logo, srcset).
* New: standalone Person + ProfilePage JSON-LD module for author archives (emits even when an SEO plugin is active, with distinct `@id` suffix). sameAs auto-collected from user_url + user_meta for Twitter/X, Facebook, LinkedIn, Instagram, YouTube, GitHub, Pinterest, TikTok, Threads, Medium, Mastodon, Bluesky. Optional `jobTitle` / `worksFor` from custom meta.
* New: dashboard auto-noindex on activation — physical `Disallow: /` robots.txt at webroot (existing file backed up to `robots.txt.sforge-backup`), `wp_robots` filter, `X-Robots-Tag` HTTP header, `robots_txt` filter. Plugin's own export fetches are exempt via `X-SFORGE-Export` header. Restored on deactivation.
* New: fallback sitemap.xml — when origin has no sitemap, plugin builds a standards-compliant `<urlset>` from native WP data (homepage + selected post types + taxonomy term archives + author archives).
* New: granular sitemap generator settings — per-post-type checkboxes, include/exclude homepage / taxonomies / authors, and a split-mode toggle that emits a `<sitemapindex>` referencing per-type sub-sitemaps. Filter: `sforge_sitemap_groups`.
* New: featured image LCP boost — auto-adds `fetchpriority="high"`, `loading="eager"`, `decoding="async"` on the post's featured image. Works on any theme using `the_post_thumbnail()` / `get_the_post_thumbnail()`.
* New: activity-log visibility for sitemap decisions — distinguishes "mirrored from origin" vs "generated locally (single|split mode)" vs explicit warnings when fallback yields no groups.
* New: auto-backfill new option defaults on `plugins_loaded` so existing installs pick up new settings without a deactivate/reactivate.
* Improved: URL rewriter now also handles escaped forward slashes (`https:\/\/origin\/...`) so JSON-LD, REST embeds, and inline JSON payloads get rewritten to the public host. `/wp-content/` skip preserved in both literal and escaped forms.
* Improved: mirrored sitemaps now strip `<?xml-stylesheet ... ?>` directives and rewrite protocol-relative `//host` references so the dashboard host doesn't leak into the public sitemap.
* Improved: `Sitemap:` line in custom robots.txt is auto-managed — any user-typed directive is stripped and replaced with the actual deployed sitemap path (sitemap.xml / sitemap_index.xml / wp-sitemap.xml / etc.) so the URL is never broken.
* Improved: settings page redesign — colour-coded section cards, hero header, status pill, live activity log, "Setup Guide" link.
* Improved: filemtime-based cache busting on plugin admin CSS/JS so settings UI updates show immediately.
* Improved: deploy log now reports per-batch upload progress (count + size + duration + cumulative total).
* Improved: bumped PHP memory limit to 512 MB during full rebuild.
* Improved: multipart/form-data deployment POST (was URL-encoded — Cloudflare rejected as "Request body is incorrect").
* Improved: Dashicons explicitly enqueued as a stylesheet dependency on plugin admin pages.
* Improved: "Rebuild + Deploy Now" / "Test Connection" / "Clear Log" now scroll to and briefly highlight the Activity Log section.
* Fix: fatal parse error caused by `?>` inside a `//` line comment terminating the `<?php` block. Replaced with block comment.
* Fix: CDATA-wrapped `<loc>` entries in sitemap-index files now expand correctly to child sitemap URLs.

= 1.0.0 =
* Initial public release.
* Cloudflare Pages Direct Upload API client (multipart/form-data deployment).
* Whole-site crawl: posts, pages, custom post types, taxonomy and author archives, homepage.
* CSS inlining for self-contained pages.
* Featured image LCP boost: `fetchpriority="high"`, `loading="eager"`, `decoding="async"`.
* Built-in SEO metadata injection: meta description, robots, canonical, Open Graph, Twitter Card.
* JSON-LD schemas: WebSite + SearchAction, Organization, Article, WebPage, Person + ProfilePage on author archives, CollectionPage on taxonomy archives, BreadcrumbList, auto-detected FAQPage and HowTo.
* Two-tier dedup: auto-pause all injection on general SEO plugins (Yoast, Rank Math, AIO SEO, SEOPress, The SEO Framework, Slim SEO, Squirrly, SmartCrawl, WP Meta SEO); pause only JSON-LD on schema-only plugins (saswp, Schema Pro, WPSSO, Schema by Hesham, Schema App, Magazine3 Schema variants).
* Sitemap mirroring with CDATA-wrapped `<loc>` support, `<?xml-stylesheet ?>` strip, and protocol-relative URL rewriting.
* Fallback sitemap.xml auto-generated from the crawled URL list when origin exposes none.
* Granular sitemap generator settings: per-post-type, homepage, taxonomies, authors, single vs split (sitemapindex + per-type sub-sitemaps).
* Editable robots.txt for the live site with auto-managed `Sitemap:` directive matching the actually-deployed sitemap path.
* Auto-backfill of new option keys on plugin update so existing installs pick up new defaults without deactivate/reactivate.
* Dashboard auto-noindex on activation (physical robots.txt + filters + X-Robots-Tag header), restored on deactivation.
* Defensive noindex stripping on export.
* Auto-deploy on publish/update with configurable debounce.
* Live activity log with auto-refresh and status indicator.
* Built-in Setup Guide page and WordPress contextual Help tabs.

== Upgrade Notice ==

= 1.8.0 =
Adds optional form handling: a form on your static site that emails submissions via your own email API (Resend, SendGrid, Postmark, Mailgun or custom), through a small Cloudflare Worker the plugin deploys. Honeypot plus optional Turnstile. Nothing changes unless you set it up.

= 1.7.0 =
Plugin is now translation-ready (text domain + .pot), and the URL rewriter catches more in-post links: http/https, www/non-www, and protocol-relative spellings of your own host now rewrite to the Public Site URL. Run a Full Rebuild to apply.

= 1.6.0 =
Fixes self-hosted fonts being blocked by CORS on the deployed site. Fonts served from your WordPress host are now bundled into the deploy and rewritten to your Public Site URL automatically. Run one Full Rebuild after updating.

= 1.5.0 =
Adds an "Extra paths to include" setting to bundle files and folders the crawler never sees in the HTML — plugin icon fonts, webfonts, PDFs — into the deploy from local disk. Optional; nothing changes unless you set it.

= 1.4.1 =
Housekeeping only: fixes a Plugin Check error and two warnings introduced in 1.4.0. No functional change.

= 1.4.0 =
Large sites should update. Editing a post now re-renders only the pages that edit affects, cutting rebuild time dramatically. Deploy memory no longer scales with site size, and deleted posts are removed from the live site. Run one Full Rebuild after updating.

= 1.3.1 =
Recommended for anyone on 1.3.0. Fixes a stray byte order mark in the main plugin file that made WordPress report "3 characters of unexpected output" on activation and broke the REST API check in Site Health.

= 1.3.0 =
Adds a Render origin override (Performance settings) for slow rebuilds on CDN-fronted sites: point page fetches at `http://127.0.0.1` so the crawl stays on the server instead of looping out through Cloudflare and back. Optional — leave blank to keep current behaviour.

= 1.2.1 =
Documentation update: adds a fix for the common post-cutover lockout where wp-admin bounces to the live static site (WP Address / Site Address left on the public host). No code change.

= 1.2.0 =
Adds automatic TranslatePress multilingual export — secondary-language pages (subdirectory mode) are now detected and deployed with no code. Opt out via the `sforge_translatepress_export` filter.

= 1.1.1 =
Fixes duplicate "View details" entry on the Plugins screen.

= 1.1.0 =
Renamed from "Send Static to Pages". Settings/logs/cron auto-migrate. WP 7.0 tested. Adds **Bundle `/wp-content/uploads/` into deploy** for shared-hosting origins blocked by CF proxies, and fixes `*.pages.dev` redirect (now JS-based — old `_middleware.js` never ran under Direct Upload).

= 1.0.1 =
Recommended update. Adds standalone Person + ProfilePage schema on author pages, fallback sitemap generation, granular sitemap settings, dashboard auto-noindex, featured-image LCP boost, escaped-slash URL rewriting for JSON-LD, and an important fatal-error fix from a malformed line comment in 1.0.0.

= 1.0.0 =
First public release.

== About ==

Built by [Gunjan Jaswal](https://www.gunjanjaswal.me). Bug reports, feedback: hello@gunjanjaswal.me.
