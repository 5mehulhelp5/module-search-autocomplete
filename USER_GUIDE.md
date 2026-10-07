# Panth Search Autocomplete - User Guide

This guide walks a Magento store administrator through every screen and
setting of the Panth Search Autocomplete extension. No coding required.

---

## Table of contents

1. [Installation](#1-installation)
2. [Verifying the extension is active](#2-verifying-the-extension-is-active)
3. [Configuration screens](#3-configuration-screens)
4. [Result-section settings](#4-result-section-settings)
5. [Cache settings](#5-cache-settings)
6. [Bot & abuse prevention](#6-bot--abuse-prevention)
7. [In-admin documentation page](#7-in-admin-documentation-page)
8. [Setting up search synonyms (catalog-specific)](#8-setting-up-search-synonyms-catalog-specific)
9. [Adding searchable attributes](#9-adding-searchable-attributes)
10. [Troubleshooting](#10-troubleshooting)
11. [CLI reference](#11-cli-reference)

---

## 1. Installation

### Composer (recommended)

```bash
composer require mage2kishan/module-search-autocomplete
bin/magento module:enable Panth_Core Panth_SearchAutocomplete
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
bin/magento cache:flush
```

### Manual zip

1. Download the extension package zip
2. Extract to `app/code/Panth/SearchAutocomplete`
3. Make sure `app/code/Panth/Core` is also present (this extension depends on it)
4. Run the same `module:enable ... cache:flush` commands above

### Confirm

```bash
bin/magento module:status Panth_SearchAutocomplete
# Module is enabled
```

---

## 2. Verifying the extension is active

1. Open your storefront and look at the header
2. Click the search icon
3. Type at least 2 characters
4. The autocomplete dropdown should appear with up to 6 product matches,
   plus categories, CMS pages, and popular searches

If nothing happens, see the [Troubleshooting](#10-troubleshooting) section.

---

## 3. Configuration screens

Navigate to **Stores -> Configuration -> Panth Extensions -> Search Autocomplete**.

You will see four groups: General, Result Sections, Caching, and
Bot & Abuse Prevention.

### General group

| Setting | Default | What it does |
|---|---|---|
| **Enable Module** | Yes | Master kill switch. Set to No to disable the autocomplete entirely. |
| **Minimum Query Length** | 2 | Queries shorter than this never reach the backend. Use 3 if your catalog is very large and you want to reduce server load. |
| **Maximum Query Length** | 64 | Hard cap on query length. Defeats CPU-DoS via huge queries. |
| **Input Debounce (ms)** | 200 | How long the JS waits after the customer stops typing before firing the request. Lower = more requests + more responsive UI. Higher = laggier UX but fewer requests. |

---

## 4. Result-section settings

| Setting | Default | What it does |
|---|---|---|
| **Max Products** | 6 | Maximum number of products shown in the dropdown. |
| **Max Categories** | 5 | Maximum categories. Set to **0** to hide the category section entirely. |
| **Max CMS Pages** | 3 | Maximum pages. Set to **0** to hide the CMS section entirely. |
| **Max Popular Searches** | 5 | Maximum popular-search chips. Set to **0** to hide the chip row. |
| **Show Product Images** | Yes | Toggle thumbnail rendering. Disable for fastest possible perceived speed. |
| **Show Product Prices** | Yes | Toggle price column. |
| **Show Categories Section** | Yes | Independent toggle. |
| **Show CMS Pages Section** | Yes | Independent toggle. |
| **Show Popular Searches Section** | Yes | Independent toggle. |
| **Count Typed Queries as Searches** | No | Yes writes every uncached typed query to Magento's search terms and raises its popularity, which lets anyone seed Popular Searches. With No, only searches submitted to the results page count. |

---

## 5. Cache settings

The extension ships with its own dedicated cache type:
**`panth_search_autocomplete`**.

| Setting | Default | What it does |
|---|---|---|
| **Enable Result Cache** | Yes | Master cache toggle. Always leave this on in production. |
| **Cache TTL (seconds)** | 300 | How long a cached row lives before being refreshed. The cache is also auto-invalidated whenever a product, category, or CMS page is saved (via Magento's standard cache tags). |

To manually flush the cache type:

```bash
bin/magento cache:clean panth_search_autocomplete
```

Or in admin: **System -> Cache Management** -> check "Panth Search
Autocomplete" -> Submit -> Refresh.

---

## 6. Bot & abuse prevention

The endpoint that powers the autocomplete (`/searchautocomplete/ajax/suggest`) is a public, frequent-use API. The extension ships with **9 layers** of protection:

| Setting | Default | What it does |
|---|---|---|
| **Require Form Key** | Yes | Rejects requests without a valid Magento `form_key`. This is a session-bound rotating CSRF token - the bundled JS sends it automatically. |
| **Rate Limit (req / min / IP)** | 60 | Sliding 60-second window per (client IP + store); the client IP comes from Magento's RemoteAddress service. Cache-served responses do not count, so legitimate users are never throttled. |
| **Block Empty User-Agent** | Yes | Headless probes routinely omit the UA. |
| **Block Bot User-Agent Patterns** | Yes | Blocks 22 known patterns: curl, wget, python-requests, scrapy, headlesschrome, sqlmap, nikto, masscan, etc. |
| **Enable Honeypot Field** | Yes | Hidden `website` input that bots fill but humans never see. |
| **Require X-Requested-With** | Yes | Both bundled clients send `XMLHttpRequest`; direct browser navigation does not. |
| **Require Same-Origin** | Yes | Validates Origin / Referer header against the store base URL. |
| **Max POST Body (bytes)** | 4096 | Hard cap on POST body size. |

> **Recommended setting in production:** leave all defaults at `Yes`.

---

## 7. In-admin documentation page

Navigate to **Stores -> Panth Infotech -> Search Autocomplete -> Documentation**.

This is a complete in-admin reference covering:

- Architecture diagram
- Engine compatibility (ES7 / ES8 / OpenSearch / MySQL)
- Per-section filter rules (visibility, status, OOS, store scope)
- Security model - what each layer blocks
- Performance and caching internals
- Theme integration (Hyva + Luma)
- Extending - adding new content types
- Troubleshooting matrix
- CLI reference

---

## 8. Setting up search synonyms (catalog-specific)

If your customers search with terms that don't appear literally in your
product names ("phone" when you sell "smartphones", "tee" when you list
"t-shirts", "kids" when you list "children"), set up Magento's built-in
synonym table:

1. Open **Marketing -> SEO & Search -> Search Synonyms**
2. Click **New Synonym Group**
3. Pick the store view
4. Enter comma-separated terms (e.g. `tshirt,tee,top,shirt`)
5. Save

The Magento search engine reads these synonyms when building queries,
so this autocomplete picks them up automatically with no code changes.

> **No hard-coded vocabulary** - this extension is catalog-agnostic
> by design. Whether you sell jewelry, electronics, furniture, or
> apparel, the synonyms are entirely under your control via the
> Magento admin.

---

## 9. Adding searchable attributes

To make a custom product attribute searchable in the autocomplete:

1. **Stores -> Attributes -> Product**
2. Find your attribute (e.g. `brand`, `material`, `collection`)
3. Open the **Storefront Properties** tab
4. Set **Use in Search** = `Yes`
5. Optionally raise **Search Weight** (5 = name-level priority)
6. Save the attribute
7. Reindex catalog search:

```bash
bin/magento indexer:reindex catalogsearch_fulltext
bin/magento cache:clean panth_search_autocomplete
```

The attribute is now searchable. The autocomplete picks it up
automatically - **no code change needed**.

---

## 10. Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| Dropdown never opens | Module disabled, or no `form_key` cookie | Verify Configuration -> Enable Module = Yes; reload the page; check browser console |
| Zero product results despite typing real product names | Search index empty / stale | `bin/magento indexer:reindex catalogsearch_fulltext` |
| `"rejected": true` in JSON response | Form key expired, bot UA, missing X-Requested-With, cross-origin | Reload the page to refresh `form_key`; check the headers your client sends |
| HTTP 429 throttled | Hit per-IP rate limit | Wait 60 seconds, or raise the limit under Configuration -> Bot & Abuse Prevention |
| Stale results after editing a product | Cache not flushed | `bin/magento cache:clean panth_search_autocomplete` |
| Dropdown shows wrong currency | Customer-group cache key | Cache rows are scoped per (store, customer group, query) - log out and back in |
| New custom attribute not appearing in results | Attribute not searchable, or index stale | See [Adding searchable attributes](#9-adding-searchable-attributes) above |

---

## 11. CLI reference

```bash
# Manually flush the autocomplete cache type
bin/magento cache:clean panth_search_autocomplete

# Disable / re-enable the cache type independently of FPC
bin/magento cache:disable panth_search_autocomplete
bin/magento cache:enable  panth_search_autocomplete

# Reindex the catalog search index that the providers query
bin/magento indexer:reindex catalogsearch_fulltext

# Verify the engine in use
bin/magento config:show catalog/search/engine

# Verify the module is enabled
bin/magento module:status Panth_SearchAutocomplete
```

---

## Support

For all questions, bug reports, or feature requests:

- **Email:** kishansavaliyakb@gmail.com
- **Website:** https://kishansavaliya.com
- **WhatsApp:** +91 84012 70422

Response time: 1-2 business days for paid licenses.
