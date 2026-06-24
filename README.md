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
| `?format=markdown` | Append to any post or page URL to get clean Markdown |
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

Developers can hook into `wpmai_is_post_indexable` to apply custom indexability logic:

```php
add_filter( 'wpmai_is_post_indexable', function( $indexable, $post ) {
    // Exclude posts from a specific category.
    if ( has_term( 'internal', 'category', $post ) ) {
        return false;
    }
    return $indexable;
}, 10, 2 );
```

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

The converter handles all standard Gutenberg blocks and common HTML:

- Headings, bold, italic, inline code, code blocks
- Ordered and unordered lists
- Blockquotes
- Links with anchor text preserved
- Images — decorative icons (no alt text or alt text ending in "Icon") are stripped; content images are kept with alt text
- Horizontal rules
- YAML frontmatter with title, URL, date, modified, author, post type, categories, and tags
- HTML entities decoded to plain text

---

## Performance

Built with larger sites in mind:

- **Zero impact on normal page loads** — endpoints only run when explicitly requested by an agent
- All Markdown output is cached in WordPress transients
- Cache is invalidated automatically on `save_post`, `delete_post`, and `wp_update_term`
- `WP_Query` calls use `no_found_rows`, `update_post_meta_cache => false`, and `update_post_term_cache => false`
- `llms-full.txt` has a configurable post limit per type (default 200) to prevent memory exhaustion
- Per-post Markdown is cached individually and reused across both the `?format=markdown` endpoint and `llms-full.txt`
- The first request after a cache clear triggers a rebuild; every subsequent request reads from the transient cache

---

## Requirements

- WordPress 6.0+
- PHP 8.0+

## Optional integrations

- **The SEO Framework / Yoast / RankMath / AIOSEO** — noindex posts automatically excluded
- **Polylang** — language filtering for multilingual sites
- **WooCommerce** — Cart, Checkout, and My Account pages auto-detected as recommended exclusions

---

## License

GPL-2.0-or-later — see [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).
