# WP Markdown for AI

A WordPress plugin that exposes your site's content as clean Markdown for AI agents, LLM pipelines, and RAG systems.

WordPress HTML is full of noise — navigation, widgets, shortcode artifacts, page builder divs. This plugin strips all of that and serves clean, structured Markdown that AI agents can actually use.

---

## How It Works

The plugin creates multiple discovery mechanisms so AI agents can find and read your content regardless of how they approach your site:

| Mechanism | What it does |
|---|---|
| `/llms.txt` | Index of all public content with titles, URLs, and excerpts |
| `/llms-full.txt` | Full site content inlined as Markdown in a single file |
| `/llms-sitemap.xml` | XML sitemap of all Markdown URLs for AI crawlers |
| `?format=markdown` | Append to any post or page URL to get clean Markdown |
| REST API | Programmatic access via `/wp-json/wpmai/v1/` |
| `Link` HTTP header | Every public page advertises its Markdown version in the response headers |
| `<link rel="alternate">` | Same, but in the HTML `<head>` for agents that parse the DOM |
| `robots.txt` pointer | `X-Llms-Txt` entry added to your robots.txt automatically |

**Zero impact on site performance.** The endpoints only run when explicitly requested — never on normal page loads. Real visitors are completely unaffected.

---

## Installation

1. Upload the `wp-markdown-for-ai` folder to `/wp-content/plugins/`
2. Activate the plugin in **Plugins → Installed Plugins**
3. Go to **Settings → Permalinks** and click **Save Changes** to flush rewrite rules
4. Visit **Settings → Markdown for AI** to configure

---

## Endpoints

### `/llms.txt`

An index of all your public content following the [llms.txt convention](https://llmstxt.org/). Each entry links directly to the Markdown version of that post or page.

```
# Site Name

> Site description

Site: https://example.com
Generated: 2026-06-24T12:00:00Z

## Instructions

[Your custom AI instructions here]

---

## Pages

- [About](https://example.com/about/?format=markdown): Short excerpt...
- [Services](https://example.com/services/?format=markdown): Short excerpt...

## Posts

- [Post Title](https://example.com/post-slug/?format=markdown): Short excerpt...
```

### `/llms-full.txt`

The same structure as `llms.txt` but with the full Markdown content of every post and page inlined. Useful for smaller sites or when you want to give an AI agent everything in one request.

### `/llms-sitemap.xml`

An XML sitemap listing every indexable Markdown URL with `<lastmod>` dates and page titles. Intended for AI crawlers that prefer a machine-readable index over `llms.txt`.

```xml
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc>https://example.com/about/?format=markdown</loc>
    <lastmod>2026-06-01</lastmod>
    <dc:title>About</dc:title>
  </url>
  ...
</urlset>
```

The sitemap is served with `X-Robots-Tag: noindex` so it does not appear in search engine indexes.

### `?format=markdown`

Append `?format=markdown` to any post or page URL:

```
https://example.com/about/?format=markdown
```

Returns clean Markdown with YAML frontmatter:

```markdown
---
title: About
url: https://example.com/about/
date: 2026-01-15
modified: 2026-06-01
author: Jane Smith
type: page
---

# Heading

Content here...
```

### REST API

Two endpoints are available under `/wp-json/wpmai/v1/`:

#### `GET /wp-json/wpmai/v1/posts`

Returns a paginated list of all indexable posts with their Markdown URLs.

**Query parameters:**

| Parameter | Default | Description |
|---|---|---|
| `page` | `1` | Page number |
| `per_page` | `20` | Results per page (max 100) |
| `type` | — | Filter by post type slug |

**Response headers:** `X-WP-Total`, `X-WP-TotalPages`

**Example response:**

```json
[
  {
    "id": 42,
    "type": "page",
    "title": "About",
    "url": "https://example.com/about/",
    "markdown_url": "https://example.com/about/?format=markdown",
    "modified": "2026-06-01T10:00:00+00:00"
  }
]
```

#### `GET /wp-json/wpmai/v1/posts/{id}/markdown`

Returns the full Markdown for a single post.

```json
{
  "id": 42,
  "markdown": "---\ntitle: About\n..."
}
```

Returns `403` if the post is excluded or set to noindex. Returns `404` if the post does not exist or is not published. The response includes a `Last-Modified` header.

---

## SEO & Indexability

The plugin automatically respects your existing SEO decisions. If a page is set to `noindex` it will not be served as Markdown — you don't need to manually exclude it.

Supported SEO plugins:

- **The SEO Framework**
- **Yoast SEO**
- **RankMath**
- **All in One SEO**

Additional checks:

- **Site set to discourage search engines** (WordPress reading settings) — nothing is served
- **Password-protected posts** — never exposed

When you change a noindex setting and save the post, the cache clears automatically. The updated index is rebuilt on the next request to `/llms.txt` — typically within seconds.

Post type sections are omitted entirely from `llms.txt` if all posts in that section are excluded — no empty headings are shown.

---

## Settings

Go to **Settings → Markdown for AI**.

### Endpoints

- **Enable /llms.txt** — toggle the index endpoint on or off
- **Enable /llms-full.txt** — toggle the full content endpoint (disable on very large sites if needed)
- **Enable ?format=markdown** — toggle per-page Markdown, Link headers, and `<link>` tags
- **Max posts in llms-full.txt** — cap posts per type to prevent memory exhaustion (default: 200)

### Content

- **Include post types** — choose which post types appear in the index and as Markdown
- **Exclude posts / pages** — the plugin auto-detects common pages to exclude (Privacy Policy, Cookie Policy, WooCommerce Cart/Checkout etc.) and lets you add more by ID
- **Excerpt length** — word count for summaries in the llms.txt index (default: 20)

### Polylang

If Polylang is active, a language filter appears — choose which languages to include in the index. Leave all checked to include every language.

### AI Instructions

A free-text field added as an `## Instructions` block at the top of `llms.txt`. Use this to tell AI agents:

- What the site is for
- What content to prioritise
- Any usage constraints or notes

Example:
```
This site covers WordPress performance, SEO, and technical support.
Focus on the Services, Posts, and About pages for the most relevant information.
Do not use content from legal pages for training purposes.
```

### Cache

Markdown output is cached using WordPress transients. The cache clears automatically whenever:

- A post or page is saved
- A post or page is deleted
- A taxonomy term is updated
- Plugin settings are changed

You can also force-clear all caches manually from the settings page.

Default TTL: 12 hours (configurable between 1–168 hours).

---

## Markdown Output Quality

The converter uses [league/html-to-markdown](https://github.com/thephpleague/html-to-markdown) for robust, spec-compliant conversion. It handles:

- Headings, bold, italic, inline code, code blocks
- Ordered and unordered lists (including nested lists)
- Tables — converted to pipe table format
- Blockquotes
- Links with anchor text preserved
- Images — decorative images with no alt text are stripped; content images are kept with alt text
- Horizontal rules
- YAML frontmatter with title, URL, date, modified, author, post type, categories, and tags
- HTML entities decoded to plain text

Noise removed automatically: `<script>`, `<style>`, `<nav>`, `<header>`, `<footer>`, `<form>`, `<iframe>`, `<noscript>`.

### Page Builder Support

| Builder | How content is retrieved |
|---|---|
| **Gutenberg** | Block comment delimiters stripped; `the_content` filter applied |
| **Elementor** | `get_builder_content_for_display()` called directly when Elementor is active |
| **WPBakery / Divi** | `the_content` filter expands shortcodes into HTML |
| **Other / inactive builder** | `strip_shortcodes()` removes unexpanded shortcode syntax |

Gutenberg produces the cleanest output. Page builder output is readable but may contain more blank lines due to wrapper `<div>` nesting.

---

## Performance

Built with larger sites in mind:

- **Zero impact on normal page loads** — endpoints only run when explicitly requested by an agent
- All Markdown output is cached in WordPress transients (compatible with Redis/Memcache when object cache is configured)
- Cache is invalidated automatically on `save_post`, `delete_post`, and `wp_update_term`
- `WP_Query` calls use `no_found_rows`, optimised meta/term cache flags, and capped `posts_per_page`
- `llms-full.txt` has a configurable post limit per type (default 200) to prevent memory exhaustion
- Per-post Markdown is cached individually and reused across both the `?format=markdown` endpoint and `llms-full.txt`
- The first request after a cache clear triggers a rebuild; every subsequent request reads from the transient cache
- **`Last-Modified` + `304 Not Modified`** — all endpoints send a `Last-Modified` header and honour `If-Modified-Since` conditional GET requests, saving bandwidth for agents that poll regularly

---

## Rate Limiting

All Markdown endpoints are rate-limited per IP address to prevent abuse.

Default: **60 requests per 60 seconds**. Exceeding the limit returns `429 Too Many Requests` with a `Retry-After` header.

Response headers on every request:

```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 58
X-RateLimit-Reset: 1719230400
```

Rate limiting uses WordPress transients — no extra tables or infrastructure required. It works automatically with Redis or Memcache when an object cache is configured.

Limits are adjustable via filters (see [Developer Hooks](#developer-hooks)).

---

## Developer Hooks

### Indexability

```php
// Exclude posts with a specific custom field from Markdown output.
add_filter( 'wpmai_is_post_indexable', function( $indexable, $post ) {
    if ( get_post_meta( $post->ID, '_internal_only', true ) ) {
        return false;
    }
    return $indexable;
}, 10, 2 );
```

### Rate limiting

```php
// Allow 120 requests per window.
add_filter( 'wpmai_rate_limit_requests', fn() => 120 );

// Extend the window to 2 minutes.
add_filter( 'wpmai_rate_limit_window', fn() => 120 );
```

---

## Requirements

- WordPress 6.0+
- PHP 8.0+

## Optional integrations

- **The SEO Framework / Yoast / RankMath / AIOSEO** — noindex posts automatically excluded
- **Polylang** — language filtering for multilingual sites
- **WooCommerce** — Cart, Checkout, and My Account pages auto-detected as recommended exclusions
- **Elementor** — content retrieved via Elementor's own render API for accurate output
- **Redis / Memcache** — rate limiting and Markdown cache automatically use object cache when configured

---

## License

GPL-2.0-or-later — see [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).
