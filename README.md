# StaticForge for Cloudflare Pages

> Auto-export your WordPress site as static HTML and deploy to Cloudflare Pages on every publish/update.

[![WordPress](https://img.shields.io/badge/WordPress-5.8%E2%80%937.0-21759b?logo=wordpress&logoColor=white)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4?logo=php&logoColor=white)](https://www.php.net)
[![Cloudflare Pages](https://img.shields.io/badge/Cloudflare%20Pages-Direct%20Upload-f38020?logo=cloudflare&logoColor=white)](https://pages.cloudflare.com)
[![License](https://img.shields.io/badge/License-GPL--2.0%2B-success)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Version](https://img.shields.io/badge/version-1.1.1-blue)](https://github.com/gunjanjaswal/staticforge-for-cloudflare-pages/releases)
[![Author](https://img.shields.io/badge/by-Gunjan%20Jaswal-9333ea)](https://www.gunjanjaswal.me)
[![Support on Ko-fi](https://img.shields.io/badge/Ko--fi-Support-FF5E5B?logo=ko-fi&logoColor=white)](https://ko-fi.com/gunjanjaswal)

A WordPress plugin that turns your install into a headless dashboard: editors keep using WordPress, but public visitors hit a static site living on Cloudflare's edge — fast, free, indestructible.

---

## ✨ Features

| Feature | What it means |
|---|---|
| 🌐 **Whole-site export** | Homepage, posts, pages, custom post types, taxonomy archives, author archives — all rendered to static HTML on every publish/update. |
| 🎨 **Inlined CSS** | Every linked stylesheet is fetched and embedded inline. Each deployed page is self-contained — no external CSS round-trips. |
| ⚡ **Featured image fetchpriority** | Auto-marks the post's featured image with `fetchpriority="high"`, `loading="eager"`, `decoding="async"` so the browser prioritises the LCP candidate. Improves Core Web Vitals. Works with any theme using `the_post_thumbnail()`. |
| 🧠 **Built-in SEO metadata injection** | Auto-emits `<meta description>`, `robots`, `canonical`, full Open Graph (`og:type`, `og:title`, `og:description`, `og:url`, `og:image`, `article:*`, `profile:*`), Twitter Card, and rich JSON-LD schemas — all from native WP data. Auto-disables when Yoast, Rank Math, AIO SEO, SEOPress, or The SEO Framework is detected, to prevent duplicate tags. |
| 📜 **Rich JSON-LD schemas** | `WebSite` + `SearchAction`, `Organization`, `Article` (with author Person + publisher), `WebPage`, `BreadcrumbList`, `CollectionPage` for taxonomy archives, **`Person` + `ProfilePage` schema for author archives** (with avatar, bio, sameAs social links), **auto-generated `FAQPage`** from FAQ blocks or `<details><summary>`, **auto-generated `HowTo`** from HowTo blocks or "How to..." titles with ordered lists. |
| 🗺️ **Sitemap mirroring + fallback generation** | Auto-discovers `/sitemap.xml`, `/sitemap_index.xml`, `/wp-sitemap.xml`, follows children, handles CDATA-wrapped `<loc>` entries, rewrites origin URLs to your live domain, and bundles all child sitemaps in the deploy. **If origin has none**, generates a valid `<urlset>` `sitemap.xml` from the crawled URL list (with `<lastmod>`, `<changefreq>`, `<priority>`) so the live site always ships a sitemap. |
| 📝 **Editable robots.txt with auto-managed Sitemap line** | In-admin textarea. Leave blank to auto-generate, or paste your own `Allow:` / `Disallow:` rules. The `Sitemap:` line is **always auto-managed** — any directive you type is stripped and replaced with the URL that points to the actual deployed sitemap path (`sitemap.xml`, `sitemap_index.xml`, `wp-sitemap.xml`, etc.) so robots.txt never points at a dead URL. |
| 🚧 **Dashboard auto-noindex (social-aware)** | On activation, blocks the WordPress install from search engines via 4-layer enforcement: physical `Disallow: /` robots.txt at webroot (existing file backed up), `wp_robots` filter adding `noindex,nofollow` meta, `X-Robots-Tag` HTTP header on every response, and a `robots_txt` filter for the dynamic fallback. **Social/messaging scrapers** (Facebook, LinkedIn, Twitter/X, Pinterest, WhatsApp, Slack, Discord, Telegram, Apple, Reddit, Tumblr, Mastodon, Bluesky, iframely, Embedly) are explicitly allowed `/wp-content/uploads/` so og:image previews still render when shared. Plugin's own export fetches are exempt — deployed pages remain fully indexable. Auto-restores backup robots.txt on plugin deactivation. |
| 🛡️ **noindex stripping** | Defensive removal of `noindex` / `nofollow` / `noarchive` directives from rendered HTML — your live site stays indexable even if the dashboard is locked down. |
| ⏱️ **Debounced auto-deploy** | Rapid edit clusters collapse into one deploy. Configurable 10s–3600s. |
| ☁️ **Cloudflare Direct Upload** | Content-addressable upload API: only changed assets re-uploaded. No Git integration needed. No build minutes consumed. |
| 📊 **Live progress UI** | Activity log auto-refreshes every 4 seconds with batch-by-batch telemetry, render percentages, and a status pill (Idle / Queued / Working). |
| 📚 **Built-in Setup Guide** | Full colour-coded walk-through inside WP admin — no docs hunting. |

---

## 🧱 How it works

```
   WordPress (your dashboard)                    Cloudflare (your live site)
   ┌────────────────────────────────┐            ┌──────────────────────┐
   │  Editor publishes/updates post │            │                      │
   │  ↓                             │            │   Cloudflare Pages   │
   │  on:transition_post_status     │            │   ┌──────────────┐   │
   │  ↓                             │            │   │ /            │   │
   │  wp_schedule_single_event      │            │   │ /post-slug/  │   │
   │  (debounce 120s)               │            │   │ /category/.. │   │
   │  ↓                             │            │   │ /author/..   │   │
   │  Crawl URL list                │            │   │ /sitemap.xml │   │
   │  ↓                             │            │   │ /robots.txt  │   │
   │  Render via wp_remote_get      │  Direct    │   └──────────────┘   │
   │  ↓ (inject SEO + schemas)      │  Upload    │                      │
   │  ↓ (inline CSS, rewrite URLs)  │  API       │                      │
   │  ↓ (strip noindex)             │            │                      │
   │  Mirror sitemap & robots.txt   │            │   ① upload-token     │
   │  ↓                             │ ─────────► │   ② check-missing    │
   │  Hash each file (SHA-256)      │            │   ③ assets/upload    │
   │  ↓                             │            │   ④ deployments      │
   │  POST manifest + branch        │            │   (multipart)        │
   └────────────────────────────────┘            └──────────────────────┘
```

---

## 📦 Installation

### From source

```bash
cd wp-content/plugins
git clone https://github.com/gunjanjaswal/staticforge-for-cloudflare-pages.git
```

### Or via WP Admin

1. Download/clone, zip the `staticforge-for-cloudflare-pages/` folder.
2. WP Admin → **Plugins → Add New → Upload Plugin** → choose zip.
3. **Activate**.

---

## 🚀 Quick Start

> Open the in-plugin **Setup Guide** (`StaticForge for Cloudflare Pages → Setup Guide`) for a richer walk-through with colour-coded sections. Six steps below are the executive summary.

### 1️⃣ Create a Cloudflare Pages project (Direct Upload)

- [dash.cloudflare.com](https://dash.cloudflare.com) → **Workers & Pages** → **Create application** → **Pages** tab → **Upload assets**.
- Project name: lowercase slug (e.g. `mysite`). Becomes `https://mysite.pages.dev`.
- Drag-drop a placeholder `index.html` to seed it. Plugin overwrites on first real deploy.

### 2️⃣ Create an API Token

- Top-right avatar → **My Profile → API Tokens** → **Create Token** → custom token.
- Permission: `Account → Cloudflare Pages → Edit`.
- Account Resources: include your account.
- **Copy the token** — shown once.

### 3️⃣ Find your Account ID

- CF Dashboard → Workers & Pages overview → right sidebar → **Account ID** (32-char hex).

### 4️⃣ Configure the plugin

| Field | Value |
|---|---|
| **Account ID** | from step 3 |
| **API Token** | from step 2 |
| **Pages Project** | slug only (`mysite`), NOT `mysite.pages.dev` |
| **Branch** | `main` for production |
| **Public Site URL** | `https://mysite.pages.dev` (testing) → `https://example.com` (after DNS cutover) |
| **Post Types** | `post`, `page`, your CPTs |
| **Inline CSS** | ☑ |
| **Inject SEO meta** | ☑ (auto-disables if SEO plugin detected) |
| **Auto-deploy** | ☑ |
| **Debounce** | `120` |

### 5️⃣ First deploy

Click **Test Connection** → expect ✅ → click **Rebuild + Deploy Now** → watch live log.

Final entry:
```
Deploy OK [<deployment-id>]  https://<id>.<project>.pages.dev
```

### 6️⃣ DNS cutover (optional, when verified)

```
@      CNAME  <project>.pages.dev   (proxied)
www    CNAME  <project>.pages.dev   (proxied)
dashboard A   <origin-ip>            (proxied; CF terminates SSL)
```

Then update the plugin's **Public Site URL** to your final domain → **Save** → **Rebuild + Deploy Now** so canonicals + sitemap entries point to the live host.

---

## 🧠 SEO injection details

When no third-party SEO plugin is detected, **StaticForge for Cloudflare Pages** automatically emits a complete SEO baseline into every page's `<head>`:

### Standard meta + social
- `<meta name="description">` — derived from `post_excerpt` → trimmed `post_content` → bio for authors → term description for taxonomies → site tagline as fallback
- `<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">`
- `<link rel="canonical">`
- **Open Graph**: `og:type`, `og:title`, `og:description`, `og:url`, `og:site_name`, `og:locale`, `og:image` (with width/height/alt), `article:published_time`, `article:modified_time`, `article:author`, `article:section`, `article:tag`, `profile:first_name`, `profile:last_name`, `profile:username`
- **Twitter Card**: `summary_large_image` when image available, `twitter:title`, `twitter:description`, `twitter:image`, `twitter:creator`

### JSON-LD schemas

| Page type | Schemas emitted |
|---|---|
| Homepage / Front page | `WebSite` + `SearchAction`, `Organization` |
| Single post | `Article` (with `author` Person, `publisher` Organization, image, dates, articleSection, keywords), `BreadcrumbList`, **`FAQPage`** (auto), **`HowTo`** (auto) |
| Static page / Custom post type | `WebPage` (with `primaryImageOfPage`, dates), `BreadcrumbList`, **`FAQPage`** (auto), **`HowTo`** (auto) |
| **Author archive** | **`Person`** (display name, URL, bio, avatar `ImageObject` 256×256, `sameAs` social links from user_url + twitter/facebook/linkedin/instagram/youtube/github user meta) **+ `ProfilePage`** (linked via `mainEntity` to the Person), `BreadcrumbList` |
| Taxonomy / Category / Tag archive | `CollectionPage`, `BreadcrumbList` |

### Auto-detected FAQ + HowTo

* **FAQPage** is emitted when the post contains:
  * a Yoast FAQ block (`yoast/faq-block`),
  * a Rank Math FAQ block (`rank-math/faq-block`),
  * a SEOPress FAQ block (`seopress/faq-block`),
  * **OR** native HTML5 `<details><summary>Question</summary>Answer</details>` blocks anywhere in the content.
* **HowTo** is emitted when the post contains:
  * a Yoast HowTo block (`yoast/how-to-block`),
  * a Rank Math HowTo block (`rank-math/howto-block`),
  * **OR** the title starts with "How to" / "How To" + the post has a `<ol>` with 3+ list items.

Filter `sforge_faq_items` / `sforge_howto_data` to override the auto-detection results.

### Auto-disable safety (two-tier dedup)

We split detection into **general SEO plugins** (handle everything: meta + og + schema) and **schema-only plugins** (handle only JSON-LD).

**Tier 1 — General SEO plugin detected → ALL our injection paused** (would duplicate everything):

- Yoast SEO (free + Premium, new + legacy)
- Rank Math (multiple class checks)
- All in One SEO Pack (v4+ and legacy)
- SEOPress
- The SEO Framework
- Slim SEO
- Squirrly SEO
- SmartCrawl
- WP Meta SEO

**Tier 2 — Schema-only plugin detected → only our JSON-LD paused, meta+og+twitter still emit**:

- Schema & Structured Data for WP & AMP (saswp, by Magazine3)
- Schema Pro (by Brainstorm Force)
- WPSSO Core
- Schema (by Hesham)
- Schema App
- Magazine3 Schema variants

Override via the **"Force full injection anyway"** checkbox in settings — useful if you want only some of our tags but understand you may need to disable parts of the other plugin to avoid duplicates.

Extend detection via filters: `sforge_seo_competing_plugin` (general SEO) or `sforge_schema_competing_plugin` (schema-only).

---

## 🗺️ Sitemap generator (controls)

The plugin handles sitemaps two ways and you have full control:

### When origin already has a sitemap

If `sitemap.xml`, `sitemap_index.xml`, or `wp-sitemap.xml` is reachable on your origin, the plugin **mirrors** it: fetches each XML file (including child sitemaps from sitemap-index entries, with CDATA-wrapped `<loc>` support), rewrites WP origin URLs to your Public Site URL, and bundles all of them in the deploy at their original paths.

The mirror also scrubs leftover dashboard references:

* `<?xml-stylesheet ... ?>` directives are removed (Yoast/Rank Math typically point them at `/wp-content/plugins/.../*.xsl` on the origin — search engines ignore XSL anyway, and stripping it stops the dashboard host from appearing in browser-rendered sitemaps).
* Both `https://dashboard.example.com` **and** protocol-relative `//dashboard.example.com` host references inside the XML are rewritten to your live host.

Activity log entry: `Sitemap mirrored from origin: sitemap.xml, sitemap-pages.xml, ...`

### When origin has no sitemap

The plugin **generates** `sitemap.xml` from your settings — independently of Export Scope, so you can list URLs in the sitemap that aren't exported and vice versa. Configure at **StaticForge for Cloudflare Pages → Settings → Sitemap Generator**:

| Setting | Effect |
|---|---|
| **Post Types** | Multi-checkbox per public post type. Default `post` + `page`. All published items + the post type archive URL get listed. |
| **Include Homepage** | Adds `home_url('/')` and the posts page (if separate) with priority `1.0`. |
| **Include Taxonomy archives** | All public taxonomies' term archives — categories, tags, custom taxonomies. |
| **Include Author archives** | Authors with at least one published post. |
| **Split into multiple files** | When ON, `sitemap.xml` becomes a `<sitemapindex>` referencing per-type sub-sitemaps (`sitemap-post.xml`, `sitemap-page.xml`, `sitemap-authors.xml`, `sitemap-taxonomy-category.xml`, etc.). Cleaner for large sites. |

Each `<url>` entry carries `<loc>` (rewritten to public URL), `<lastmod>` (resolved via `get_post_modified_time` for permalinks), `<changefreq>weekly</changefreq>`, `<priority>` (`1.0` home / `0.7` other).

Activity log entries:
- `Sitemap generated locally (single mode): 449 URLs across 4 groups, 1 file(s).`
- `Sitemap generated locally (split mode): 449 URLs across 4 groups, 5 file(s).`
- `Sitemap fallback skipped: no post types / archives selected in Sitemap Generator settings.` (warning if you've unticked everything)

### Filter

`sforge_sitemap_groups` exposes the grouped URL list:

```php
add_filter( 'sforge_sitemap_groups', function ( $groups ) {
    $groups['custom'][] = [ 'url' => 'https://example.com/landing/', 'lastmod' => '' ];
    return $groups;
} );
```

---

## 🔌 Clean `/wp-content/` URLs (advanced)

By default the plugin keeps `<origin>/wp-content/...` URLs (theme CSS/JS, plugin assets, uploads) pointing at your WordPress origin so those assets keep working without bundling multi-gigabyte folders in every deploy. That means structured data such as `og:image`, JSON-LD `image` / `logo` / `thumbnailUrl`, and HTML `<img src>` / `srcset` will still reference your **dashboard host** on the deployed site.

If you want fully clean URLs on the live site:

1. Set up a proxy that forwards `/wp-content/*` requests from your live host to the dashboard.
2. Toggle **Export Scope → Rewrite `/wp-content/` URLs** in the plugin settings.

### Option A — Cloudflare Worker (recommended)

Cloudflare Dashboard → **Workers & Pages → Create application → Workers** → Hello World template → deploy → **Edit code**:

```js
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
```

Replace `dashboard.example.com` with your actual WordPress host.

Worker → **Settings → Triggers → Add route**:
```
example.com/wp-content/*
www.example.com/wp-content/*
```

Tick **Rewrite `/wp-content/` URLs** in plugin settings → **Rebuild + Deploy Now**.

### Option B — Nginx / Apache reverse proxy

```nginx
# Nginx — on the live host
location /wp-content/ {
    proxy_pass         https://dashboard.example.com;
    proxy_set_header   Host dashboard.example.com;
    proxy_ssl_server_name on;
}
```

```apache
# Apache — on the live host
<Location "/wp-content/">
    ProxyPass         "https://dashboard.example.com/wp-content/"
    ProxyPassReverse  "https://dashboard.example.com/wp-content/"
</Location>
```

### Verify before flipping the toggle

```bash
curl -I https://example.com/wp-content/uploads/some-file.jpg
# Expect HTTP/2 200 with image content-type
```

If that returns 200, tick the toggle, redeploy, view source of any post — `og:image`, JSON-LD `image`/`logo`/`thumbnailUrl`, and every `<img src>` will reference your live host instead of the dashboard.

> ⚠ **If the toggle is on but no proxy is set up:** every image / theme stylesheet / plugin script on the live site will 404. Run the curl test first.

---

## 📜 robots.txt for the live site

The plugin ships its own `robots.txt` to your Cloudflare Pages site root, **independent** of your dashboard's robots.txt (which can stay locked down):

- **Auto-generated default**: `User-agent: * / Allow: / / Sitemap: https://example.com/<active-sitemap-path>`
- **Editable**: in-admin textarea on the settings page. Leave blank for default, or paste your own `Allow:` / `Disallow:` rules.
- **Auto-managed Sitemap line**: any `Sitemap:` directive you type into the textarea is stripped and replaced with the URL pointing at the actually-deployed sitemap path. So if Yoast emits `/sitemap_index.xml`, your robots.txt always says `Sitemap: https://example.com/sitemap_index.xml` even if you typed `/sitemap.xml`. Guarantees the link is never broken.

```
# Your custom rules — preserved verbatim
User-agent: *
Disallow: /preview/
Disallow: /staging/

User-agent: GPTBot
Disallow: /

# Plugin auto-appends the correct one based on the deployed primary sitemap:
Sitemap: https://example.com/sitemap_index.xml
```

---

## 🗺️ Sitemap mirroring

On every deploy, the plugin:

1. Tries `/sitemap.xml`, `/sitemap_index.xml`, `/wp-sitemap.xml` against your origin.
2. For each that returns valid XML, parses `<loc>` entries — including those wrapped in `<![CDATA[...]]>`.
3. Follows sitemap-index references → fetches every child sitemap.
4. Rewrites origin URLs inside the XML payloads to your Public Site URL.
5. Bundles them all in the deploy at their original paths.

Search engines crawling your live site see correctly-formed canonical URLs.

---

## ⚙️ Configuration reference

| Setting | Default | Notes |
|---|---|---|
| Account ID | — | 32-char hex from Cloudflare. |
| API Token | — | Permission: `Account · Cloudflare Pages · Edit`. |
| Pages Project | — | Slug, not the URL. |
| Branch | `main` | `main` = production, anything else = preview. |
| Public Site URL | — | Used for URL rewriting in HTML/XML. |
| Post Types | `post`, `page` | All public types selectable. |
| Include Homepage | ☑ | |
| Include Taxonomies | ☑ | All public taxonomies + their term archives. |
| Include Authors | ☑ | Authors with at least one published post. |
| Inline CSS | ☑ | Embeds external `<link>` stylesheets as `<style>`. |
| Inject SEO meta | ☑ | Auto-disabled when an SEO plugin is detected. |
| Force SEO injection | ☐ | Force-emit even with SEO plugin active. |
| Auto-deploy | ☑ | On publish/update of selected types. |
| Debounce | `120` sec | Min 10, max 3600. |
| Export Folder | `sforge-export` | Inside `wp-content/uploads/`. |
| robots.txt | auto | Custom textarea; `Sitemap:` auto-appended. |

---

## 🪝 Filters

| Filter | Purpose |
|---|---|
| `sforge_url_list` | Modify the list of URLs to export. Receives an array of absolute URLs. |
| `sforge_sitemap_candidates` | Modify the sitemap discovery URL list. |
| `sforge_seo_inject` | Final say on whether our SEO tags emit on a given request. |
| `sforge_seo_inject_schema` | Final say on whether our JSON-LD block emits (meta+og still emit independently). |
| `sforge_seo_competing_plugin` | Add custom detection for general SEO plugins → skips ALL our injection. |
| `sforge_schema_competing_plugin` | Add custom detection for schema-only plugins → skips only our JSON-LD. |
| `sforge_faq_items` | Override the auto-detected FAQ items (`[ ['question'=>'…','answer'=>'…'], … ]`). |
| `sforge_howto_data` | Override the auto-detected HowTo data (`['name'=>'…','steps'=>[ ['name'=>'…','text'=>'…'], … ]]`). |
| `sforge_sslverify` | Disable SSL verification for self-fetch (`__return_false`). Useful if origin cert is invalid during migration. |

```php
add_filter( 'sforge_url_list', function ( $urls ) {
    $urls[] = home_url( '/landing/' );
    return $urls;
} );

// Treat your own bespoke SEO plugin as a competitor to skip our injection on its pages.
add_filter( 'sforge_seo_competing_plugin', function ( $detected ) {
    return $detected || class_exists( 'My_Custom_SEO' );
} );
```

---

## 🛠 Troubleshooting

<details>
<summary><b><code>Project not found</code></b></summary>

You typed the full `name.pages.dev` URL into the **Pages Project** field. Use the slug only (e.g. `mysite`).
</details>

<details>
<summary><b><code>Deployment failed: Request body is incorrect</code></b></summary>

Older plugin builds sent the deployment POST as URL-encoded form. v1.0.0+ sends `multipart/form-data` which Cloudflare requires. Update the plugin.
</details>

<details>
<summary><b>Stuck on <code>Manifest: ... files</code></b></summary>

Upload step ran out of PHP memory or hit max execution time. Plugin already requests `set_time_limit(0)` and 512 MB but shared hosts may override. Raise `php.ini` `memory_limit` and `max_execution_time`. Plugin uses 100-file / 25 MiB batches by default.
</details>

<details>
<summary><b>Sub-sitemaps missing on deploy</b></summary>

v1.0.0+ handles CDATA-wrapped `<loc>` URLs inside sitemap-index files. Earlier builds skipped them. Update + redeploy.
</details>

<details>
<summary><b>Duplicate SEO meta or schema in HTML</b></summary>

Means an SEO plugin we don't auto-detect is also injecting tags. Either: (a) untick **Inject SEO meta** in settings, or (b) extend detection via the `sforge_seo_competing_plugin` filter.
</details>

<details>
<summary><b>Images broken on deployed site</b></summary>

Plugin keeps `/wp-content/*` URLs pointing at your origin. Ensure the origin's SSL cert is valid — or proxy that subdomain through Cloudflare so CF terminates a fresh edge cert.
</details>

<details>
<summary><b>Live site shows <code>noindex</code></b></summary>

Don't tick **Settings → Reading → "Discourage search engines"** on the dashboard. It causes SEO plugins to also flag the sitemap. Plugin defensively strips noindex meta during export, but turn the toggle off to be safe.
</details>

<details>
<summary><b>Hit ~100 deployments per day</b></summary>

Free tier soft cap. Raise the **Debounce** setting from 120 to 600+ so bulk edits collapse into fewer deploys.
</details>

---

## 🧪 Limits

| Resource | Limit |
|---|---|
| Files per deployment | 20,000 (Cloudflare Pages free tier) |
| Single file size | 25 MiB (Cloudflare Pages) |
| Deployments per day | ~100 soft cap (Cloudflare Pages free tier) |
| Bandwidth / requests | unlimited |

---

## 📝 Changelog

### 1.1.1
- Fix: removed the plugin's own injected "View details" row-meta link to prevent a duplicate entry, since WordPress now auto-injects "View details" for wp.org-hosted plugins. Row meta is now `View details | Plugin Support | Contact Developer`.

### 1.1.0
- **Rename:** plugin renamed from "Send Static to Pages" to "StaticForge for Cloudflare Pages". Folder slug, main file (`staticforge-for-cloudflare-pages.php`), text domain, all class/constant/function/option prefixes (`SSTP_`/`sstp_` → `SFORGE_`/`sforge_`), and the `sstp_full_rebuild` cron hook moved over together.
- **Auto-migration:** on `plugins_loaded` (priority 1) the plugin copies legacy `sstp_settings` and `sstp_log` to the new option keys and reschedules any pending `sstp_full_rebuild` cron event to `sforge_full_rebuild`. Guarded by a one-shot `sforge_migrated_from_sstp` flag so it runs at most once per install.
- **Uninstall** now also deletes legacy `sstp_*` options and clears the `sstp_full_rebuild` cron hook so leftover state is fully removed.
- **WordPress 7.0 tested.** Audited for deprecated APIs — all good (uses modern `wp_remote_*`, `register_setting`, etc.). No iframed-editor impact: the plugin only renders an admin settings page in the parent admin chrome.
- **WordPress 7.0 Connectors API:** registers a `deployment_target` connector (`sforge-cloudflare-pages`) on the `wp_connectors_init` action so the plugin appears on the central Connections screen. The Cloudflare API token lives inside the `sforge_settings` array, which does not fit the Connectors API single-value `api_key` shape, so the connector is registered with `method: none` and `credentials_url` linking back to the StaticForge settings screen for credential management. Falls back silently on WP < 7.0.
- **Plugin row links:** added `Settings` and `Support on Ko-fi` next to Deactivate, plus `Plugin Support` (WordPress.org forum) and `Contact Developer` row meta.
- **Donate link** moved to Ko-fi: [ko-fi.com/gunjanjaswal](https://ko-fi.com/gunjanjaswal).

### 1.0.1
- Social-aware dashboard noindex — robots.txt explicitly allows `/wp-content/uploads/` for facebookexternalhit / Twitterbot / LinkedInBot / Pinterestbot / WhatsApp / Slackbot / Discordbot / TelegramBot / Applebot / redditbot / Tumblr / iframely / Embedly / Mastodon / Bluesky / meta-externalagent. `X-Robots-Tag` skipped for media paths and social-scraper user agents so og:image previews resolve.
- `*.pages.dev` 301-redirect middleware — emits `functions/_middleware.js` into the deploy when Public Site URL is a custom domain.
- Opt-in **Rewrite `/wp-content/` URLs** setting + Setup Guide instructions for Cloudflare Worker / Nginx / Apache reverse proxies.
- Standalone `Person` + `ProfilePage` JSON-LD module for author archives (sameAs auto-collected from user_meta).
- Dashboard auto-noindex on activation — physical robots.txt at webroot (backed up to `robots.txt.sforge-backup`), `wp_robots` filter, `X-Robots-Tag` header. Plugin's own export fetches exempt via `X-SFORGE-Export` header. Restored on deactivation.
- Fallback `sitemap.xml` builder when origin has none — standards-compliant `<urlset>` from native WP data.
- Granular sitemap generator settings — per-post-type checkboxes, include/exclude homepage / taxonomies / authors, split-mode toggle emitting `<sitemapindex>`. Filter: `sforge_sitemap_groups`.
- Featured-image LCP boost — `fetchpriority="high"`, `loading="eager"`, `decoding="async"`.
- Activity-log visibility for sitemap mirror-vs-generate decisions.
- Auto-backfill new option defaults on `plugins_loaded`.
- URL rewriter now also handles escaped forward slashes (`https:\/\/origin\/...`) for JSON-LD / REST embeds.
- Mirrored sitemaps strip `<?xml-stylesheet ... ?>` directives + rewrite protocol-relative `//host` references.
- `Sitemap:` line in custom robots.txt is auto-managed so it always points at the actually-deployed sitemap path.
- Multipart/form-data deployment POST (was URL-encoded — Cloudflare rejected as "Request body is incorrect").
- Fix: fatal parse error from `?>` inside a `//` line comment terminating `<?php` block.
- Fix: CDATA-wrapped `<loc>` entries in sitemap-index files now expand correctly.

### 1.0.0
- Initial public release.
- Cloudflare Pages Direct Upload API client (multipart/form-data deployment).
- Whole-site crawl: posts, pages, custom post types, taxonomy and author archives, homepage.
- CSS inlining for self-contained pages.
- Featured image LCP boost.

---

## 🤝 Contributing

Issues & PRs welcome. Contact: [hello@gunjanjaswal.me](mailto:hello@gunjanjaswal.me)

---

## ☕ Support

If StaticForge saves you time or money, consider supporting the development on Ko-fi:

[![Support on Ko-fi](https://img.shields.io/badge/Ko--fi-Support-FF5E5B?style=for-the-badge&logo=ko-fi&logoColor=white)](https://ko-fi.com/gunjanjaswal)

---

## 📄 License

GPL-2.0-or-later. Same as WordPress.

---

## 👤 Author

**Gunjan Jaswal** — [www.gunjanjaswal.me](https://www.gunjanjaswal.me) — [hello@gunjanjaswal.me](mailto:hello@gunjanjaswal.me) — [ko-fi.com/gunjanjaswal](https://ko-fi.com/gunjanjaswal)
