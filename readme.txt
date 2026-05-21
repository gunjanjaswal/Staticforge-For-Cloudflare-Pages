=== StaticForge for Cloudflare Pages ===
Contributors: gunjanjaswal
Donate link: https://ko-fi.com/gunjanjaswal
Tags: cloudflare, static-site, deploy, seo, sitemap
Requires at least: 5.8
Tested up to: 7.0
Stable tag: 1.1.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Auto-export WordPress as static HTML to Cloudflare Pages on publish — with SEO meta, JSON-LD schemas, sitemaps, and robots.txt.

== Description ==

**StaticForge for Cloudflare Pages** turns your WordPress site into a static site that lives on Cloudflare Pages, automatically. On every publish or update of any selected post type, the plugin renders your whole site to static HTML, injects a complete SEO metadata baseline (when no other SEO plugin is present), inlines all linked CSS so pages are self-contained, mirrors your sitemap structure, ships an editable `robots.txt`, and pushes everything to Cloudflare Pages via the Direct Upload API.

The WordPress install (your "dashboard") becomes the editor only. Public visitors hit the static deployment on Cloudflare's edge — fast, free, and resilient.

= Key features =

* **Whole-site export** — homepage, posts, pages, custom post types, taxonomy archives, author archives.
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

Files under `/wp-content/uploads/`, theme assets, plugin assets, and fonts under `/wp-content/` are kept pointing at your WordPress origin so they keep working without re-uploading multi-gigabyte media folders. Make sure your origin is reachable over HTTPS (proxy through Cloudflare if your origin's SSL cert is fragile).

= Why this plugin =

* Simpler scope — Cloudflare Pages only, Direct Upload only.
* Built-in setup walkthrough, no docs hunting.
* Live progress UI with granular per-batch logging.
* Free tier compatible — no build minutes consumed.
* SEO baseline included — no extra plugin needed for tags + JSON-LD.
* No external dependencies, no SaaS, no premium tier.

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

== Screenshots ==

1. Settings page with all configuration fields, SEO injection toggles, and live activity log.
2. Built-in Setup Guide with colour-coded step-by-step walk-through.
3. Activity log with status pill, render progress, batch-by-batch upload telemetry.
4. Sample JSON-LD output: Article with linked author Person + Organization publisher.
5. Sample author archive: Person + ProfilePage schema with sameAs social links.

== Changelog ==

= 1.1.0 =
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

= 1.1.0 =
Plugin renamed from "Send Static to Pages" to "StaticForge for Cloudflare Pages". Settings, logs, and scheduled deploy jobs auto-migrate on first load. WP 7.0 tested.

= 1.0.1 =
Recommended update. Adds standalone Person + ProfilePage schema on author pages, fallback sitemap generation, granular sitemap settings, dashboard auto-noindex, featured-image LCP boost, escaped-slash URL rewriting for JSON-LD, and an important fatal-error fix from a malformed line comment in 1.0.0.

= 1.0.0 =
First public release.

== About ==

Built by [Gunjan Jaswal](https://www.gunjanjaswal.me). Bug reports, feedback: hello@gunjanjaswal.me.
