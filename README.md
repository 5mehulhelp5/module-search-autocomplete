<!-- SEO Meta -->
<!--
  Title: Panth Search Autocomplete for Magento 2 | Instant AJAX Search, Product Suggestions & SKU Search | Panth Infotech
  Description: Panth Search Autocomplete is an intelligent instant search extension for Magento 2 with product suggestions, SKU search, description matching, layered navigation, AJAX live results, configurable result count and full Hyva + Luma theme compatibility. Engine-agnostic (Elasticsearch, OpenSearch, MySQL). Built by a Top Rated Plus Magento developer.
  Keywords: magento 2 search autocomplete, instant search, ajax search, search suggestions, product autocomplete, magento 2 live search, sku search, elasticsearch autocomplete, hyva search, magento 2 search extension
  Author: Kishan Savaliya (Panth Infotech)
  Canonical: https://github.com/mage2sk/module-search-autocomplete
-->

# Panth Search Autocomplete — Intelligent Instant Search & AJAX Product Suggestions for Magento 2

[![Magento 2.4.4 - 2.4.8](https://img.shields.io/badge/Magento-2.4.4%20--%202.4.8-orange?logo=magento&logoColor=white)](https://magento.com)
[![PHP 8.1 - 8.4](https://img.shields.io/badge/PHP-8.1%20--%208.4-blue?logo=php&logoColor=white)](https://php.net)
[![Hyva + Luma](https://img.shields.io/badge/Themes-Hyva%20%2B%20Luma-7c3aed)]()
[![Packagist](https://img.shields.io/badge/Packagist-mage2kishan%2Fmodule--search--autocomplete-orange?logo=packagist&logoColor=white)](https://packagist.org/packages/mage2kishan/module-search-autocomplete)
[![GitHub](https://img.shields.io/badge/GitHub-mage2sk%2Fmodule--search--autocomplete-181717?logo=github&logoColor=white)](https://github.com/mage2sk/module-search-autocomplete)
[![Upwork Top Rated Plus](https://img.shields.io/badge/Upwork-Top%20Rated%20Plus-14a800?logo=upwork&logoColor=white)](https://www.upwork.com/freelancers/~016dd1767321100e21)
[![Panth Infotech Agency](https://img.shields.io/badge/Agency-Panth%20Infotech-14a800?logo=upwork&logoColor=white)](https://www.upwork.com/agencies/1881421506131960778/)
[![Get a Quote](https://img.shields.io/badge/Get%20a%20Quote-Free%20Estimate-DC2626)](https://kishansavaliya.com/get-quote)

> **Intelligent search autocomplete for Magento 2** — instant AJAX results with product suggestions, SKU search, description matching, category & CMS page results, layered navigation of result sections, and fully configurable result counts. Works flawlessly on both **Hyva** and **Luma** themes with engine-agnostic support for Elasticsearch 7/8, OpenSearch, and the MySQL fallback search engine.

**Panth Search Autocomplete** turns the sluggish stock Magento search box into a lightning-fast, conversion-focused shopping assistant. As shoppers type, the dropdown instantly surfaces matching products (with images and prices), related categories, CMS pages, and popular searches — all via debounced AJAX calls that bypass full page loads. Every result section can be toggled, limited, and styled independently, giving merchandisers complete control over what shoppers see first.

The module searches across product **name, SKU, short description, and full description** using Magento's configured search engine, so results stay consistent with the on-site search results page. A dedicated cache type, 9-layer bot protection (form-key validation, per-IP rate limiting, query length caps, honeypots), and full keyboard & ARIA accessibility keep it fast, secure, and inclusive. Whether you run a high-traffic Luma storefront or a modern Hyva + Alpine.js build, Panth Search Autocomplete drops in with zero frontend conflicts.

---

## 🚀 Need Custom Magento 2 Development?

> **Get a free quote for your project in 24 hours** — custom modules, Hyva themes, performance optimization, M1→M2 migrations, and Adobe Commerce Cloud.

<p align="center">
  <a href="https://kishansavaliya.com/get-quote">
    <img src="https://img.shields.io/badge/Get%20a%20Free%20Quote%20%E2%86%92-Reply%20within%2024%20hours-DC2626?style=for-the-badge" alt="Get a Free Quote" />
  </a>
</p>

<table>
<tr>
<td width="50%" align="center">

### 🏆 Kishan Savaliya
**Top Rated Plus on Upwork**

[![Hire on Upwork](https://img.shields.io/badge/Hire%20on%20Upwork-Top%20Rated%20Plus-14a800?style=for-the-badge&logo=upwork&logoColor=white)](https://www.upwork.com/freelancers/~016dd1767321100e21)

100% Job Success • 10+ Years Magento Experience
Adobe Certified • Hyva Specialist

</td>
<td width="50%" align="center">

### 🏢 Panth Infotech Agency
**Magento Development Team**

[![Visit Agency](https://img.shields.io/badge/Visit%20Agency-Panth%20Infotech-14a800?style=for-the-badge&logo=upwork&logoColor=white)](https://www.upwork.com/agencies/1881421506131960778/)

Custom Modules • Theme Design • Migrations
Performance • SEO • Adobe Commerce Cloud

</td>
</tr>
</table>

**Visit our website:** [kishansavaliya.com](https://kishansavaliya.com) &nbsp;|&nbsp; **Get a quote:** [kishansavaliya.com/get-quote](https://kishansavaliya.com/get-quote)

---

## Table of Contents

- [Key Features](#key-features)
- [How It Works](#how-it-works)
- [Compatibility](#compatibility)
- [Installation](#installation)
- [Configuration](#configuration)
- [Search Fields & Matching](#search-fields--matching)
- [Bot & Abuse Protection](#bot--abuse-protection)
- [Performance & Caching](#performance--caching)
- [Hyva & Luma Theme Support](#hyva--luma-theme-support)
- [Troubleshooting](#troubleshooting)
- [FAQ](#faq)
- [Support](#support)
- [About Panth Infotech](#about-panth-infotech)
- [Quick Links](#quick-links)

---

## Key Features

### Instant AJAX Autocomplete

- **Live results as you type** — debounced AJAX requests (default 200ms) fire after the minimum query length is met
- **Product suggestions with images & prices** — show thumbnail, name, price, SKU and short description per hit
- **SKU search** — match exact or partial SKUs so B2B customers can find products by code
- **Description matching** — searches short and long descriptions, not just the product name
- **Category suggestions** — surface matching category links so shoppers can jump directly to listings
- **CMS page suggestions** — show matching pages (about, shipping, FAQ) right inside the dropdown
- **Popular & recent searches** — display trending queries when the input is empty or too short
- **Layered result navigation** — each result section (products / categories / pages / popular) is an independent block with its own limit and visibility toggle

### Merchant Controls

- **Configurable result count** — independent `products_limit`, `categories_limit`, `pages_limit`, and `popular_limit`
- **Min / max query length** — default 2–64 characters (hard cap prevents DoS via huge queries)
- **Debounce interval** — tune request frequency between responsiveness and server load
- **Show / hide** product images, prices, category section, CMS section, popular section — all per store view
- **Engine-agnostic** — uses whatever search engine Magento is configured with (Elasticsearch 7, 8, OpenSearch, or MySQL fallback)

### Security & Performance

- **9 layers of bot protection** — form-key validation, per-IP rate limiting, query length caps, honeypot fields, referer checks, and more
- **Dedicated cache type** — `panth_search_autocomplete` cache is auto-invalidated on catalog / CMS changes
- **Lightweight frontend** — no jQuery dependency on Hyva, Alpine.js-powered; graceful Luma KnockoutJS implementation
- **ARIA-accessible markup** — full keyboard navigation (arrow keys, Enter, Escape) and screen-reader support

### Developer-Friendly

- **MEQP compliant** — passes Adobe's Magento Extension Quality Program
- **PSR-12 code style** — clean, documented, fully typed
- **Composer-installable** — no manual file copying
- **Zero third-party dependencies** — uses only Magento framework classes

---

## How It Works

1. Shopper types in the search box.
2. JavaScript debounces input (configurable, default 200ms) and validates minimum length.
3. AJAX request hits `/panth_searchautocomplete/ajax/index` with the form key.
4. Controller validates form key, checks per-IP rate limit, and normalises the query.
5. Cache layer is checked — if hit, response is returned in <5ms.
6. On cache miss, the module queries Magento's configured search engine across name, SKU, short description and description fields.
7. Results are assembled into sections (products, categories, CMS pages, popular searches), each respecting its own limit.
8. JSON response is cached and rendered into the dropdown via Alpine.js (Hyva) or Knockout (Luma).

---

## Compatibility

| Requirement | Versions Supported |
|---|---|
| Magento Open Source | 2.4.4, 2.4.5, 2.4.6, 2.4.7, 2.4.8 |
| Adobe Commerce | 2.4.4, 2.4.5, 2.4.6, 2.4.7, 2.4.8 |
| Adobe Commerce Cloud | 2.4.4 — 2.4.8 |
| PHP | 8.1.x, 8.2.x, 8.3.x, 8.4.x |
| Search Engine | Elasticsearch 7.x, 8.x, OpenSearch 1.x/2.x, MySQL fallback |
| Hyva Theme | 1.0+ (Alpine.js powered dropdown) |
| Luma Theme | Native support |
| Panth Core | `^1.0` (auto-installed via Composer) |

---

## Installation

### Composer Installation (Recommended)

```bash
composer require mage2kishan/module-search-autocomplete
bin/magento module:enable Panth_Core Panth_SearchAutocomplete
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
bin/magento cache:flush
```

### Manual Installation via ZIP

1. Download the latest release from [Packagist](https://packagist.org/packages/mage2kishan/module-search-autocomplete) or [GitHub](https://github.com/mage2sk/module-search-autocomplete).
2. Extract to `app/code/Panth/SearchAutocomplete/`.
3. Ensure `Panth_Core` is also present in `app/code/Panth/Core/` or installed via Composer.
4. Run the enable / upgrade commands above.

### Verify Installation

```bash
bin/magento module:status Panth_SearchAutocomplete
# Expected output: Module is enabled
```

After installation, navigate to:

```
Admin → Stores → Configuration → Panth Extensions → Search Autocomplete
```

---

## Configuration

All settings live at **Stores → Configuration → Panth Extensions → Search Autocomplete**.

### General

| Setting | Default | Description |
|---|---|---|
| Enable Module | Yes | Master toggle for the autocomplete dropdown. |
| Minimum Query Length | 2 | Shorter queries never hit the backend. |
| Maximum Query Length | 64 | Hard cap to prevent CPU DoS via huge queries. |
| Input Debounce (ms) | 200 | Lower = more requests, higher = laggier UX. |

### Result Sections

| Setting | Default | Description |
|---|---|---|
| Max Products | 8 | Number of product hits shown. |
| Max Categories | 4 | Number of matching categories. |
| Max CMS Pages | 3 | Number of matching CMS pages. |
| Max Popular Searches | 5 | Number of trending queries when input is short. |
| Show Product Images | Yes | Toggle thumbnails in dropdown. |
| Show Product Prices | Yes | Toggle prices in dropdown. |
| Show Categories Section | Yes | Toggle entire categories block. |
| Show CMS Pages Section | Yes | Toggle entire CMS pages block. |
| Show Popular Searches Section | Yes | Toggle popular-searches block. |

### Caching

| Setting | Default | Description |
|---|---|---|
| Enable Result Cache | Yes | Dedicated cache type `panth_search_autocomplete`. |
| Cache TTL (seconds) | 300 | Auto-invalidated when products / categories / CMS pages change. |

### Bot & Abuse Prevention

| Setting | Default | Description |
|---|---|---|
| Require Form Key | Yes | Rejects requests without a valid Magento form_key. |
| Rate Limit (req / min / IP) | 60 | Cache-served responses do not count. |

---

## Search Fields & Matching

Panth Search Autocomplete queries the following product attributes through Magento's configured search engine:

- **Name** — primary match
- **SKU** — exact and partial matches (critical for B2B stores)
- **Short Description** — keyword matching
- **Description** — full-text matching
- **URL Key** — fallback for slug-based queries

Category suggestions match against **category name** and **description**. CMS page suggestions match **title** and **content heading**. All matching honours store view scope, website scope, and visibility / status flags — out-of-stock and disabled products never appear.

---

## Bot & Abuse Protection

Search endpoints are a favourite target for scrapers and DoS attackers. Panth Search Autocomplete ships with 9 defensive layers enabled by default:

1. **Form-key validation** — rejects requests without a valid Magento session form_key.
2. **Per-IP rate limiting** — configurable requests/minute, backed by Magento cache.
3. **Query length cap** — `max_query_length` rejects oversized payloads.
4. **Minimum query length** — blocks 1-character fishing queries.
5. **Honeypot field** — hidden input that bots fill in but humans cannot.
6. **Referer validation** — rejects cross-origin requests from non-storefront referers.
7. **User-agent heuristics** — flags known scraper signatures.
8. **Response caching** — repeated identical queries never re-hit the search engine.
9. **Query normalisation** — strips control characters, collapses whitespace, lowercases.

---

## Performance & Caching

A dedicated cache type keeps autocomplete responses blazingly fast:

- **Cache key** — derived from `store_id + query_hash + config_hash`
- **Auto-invalidation** — on product save, category save, CMS page save, and config change
- **Cache type** — `panth_search_autocomplete` (listed in Admin → System → Cache Management)
- **Typical response time** — <5ms on cache hit, 30–80ms on Elasticsearch miss

To manually flush:

```bash
bin/magento cache:clean panth_search_autocomplete
```

---

## Hyva & Luma Theme Support

### Hyva Theme

- **Alpine.js + Tailwind CSS** — no jQuery, no KnockoutJS
- **Zero CLS** — dropdown uses absolute positioning with reserved dimensions
- **Works with Hyva Checkout** — no conflicts with the Hyva ecosystem

### Luma Theme

- **Native KnockoutJS integration** — matches stock Magento component style
- **LESS theming** — inherits your theme colours via CSS variables
- **Magento UI Library** — uses standard `_ui.less` mixins

Both implementations share the same backend controller and respond to the same configuration options, so merchants can switch themes without retraining.

---

## Troubleshooting

| Issue | Cause | Resolution |
|---|---|---|
| Dropdown never appears | Module disabled or cache stale | Enable in config, run `bin/magento cache:flush`. |
| "Invalid form key" in console | Aggressive caching / Varnish | Ensure form_key is dynamic; whitelist the AJAX URL in your ESI rules. |
| No results but search page works | Search engine not reindexed | Run `bin/magento indexer:reindex catalogsearch_fulltext`. |
| Rate-limit errors on shared IP | Office NAT or corporate proxy | Raise `Rate Limit (req / min / IP)` to 120+. |
| Styles broken on Hyva | Static content not deployed | `bin/magento setup:static-content:deploy -f -t Hyva/default`. |
| Slow response on large catalogues | MySQL fallback engine | Switch to Elasticsearch 8 or OpenSearch — 10× faster. |

---

## FAQ

### Does Panth Search Autocomplete work with Elasticsearch and OpenSearch?

Yes. The module is **engine-agnostic** — it uses whatever Magento is configured with (Elasticsearch 7, Elasticsearch 8, OpenSearch 1.x/2.x, or the MySQL fallback). No extra setup required.

### Does it search by SKU?

Yes. SKU is a first-class search field. Both exact and partial SKU matches are returned, which is essential for B2B and wholesale storefronts.

### Can I limit how many products, categories, or CMS pages are shown?

Yes. Each result section has an independent limit in the admin. Set any section's limit to 0 (and its visibility toggle to "No") to hide it entirely.

### Does it work on Hyva?

Yes — the module ships with a dedicated Hyva implementation using Alpine.js and Tailwind CSS. No jQuery, no KnockoutJS, zero Hyva compatibility issues.

### How does the rate limiting work?

Requests are counted per IP per minute, stored in Magento's default cache. Cache-served responses do not count against the limit, so frequent queries from a legitimate shopper stay fast. Abusive bots are blocked with a 429 response.

### Does it slow down my store?

No. The dedicated cache, debouncing, and lightweight frontend mean typical response times stay under 10ms for cached queries. The dropdown adds no weight to initial page load — all assets are lazy-loaded when the search input is focused.

### Is Panth Core required?

Yes. Panth Core is the free foundation module for all Panth extensions. Composer installs it automatically.

### Does it support multi-store / multi-website setups?

Yes. All settings respect Magento's standard scope hierarchy (default → website → store view). Each store view can have its own limits, debounce, and visibility toggles.

---

## Support

| Channel | Contact |
|---|---|
| Email | kishansavaliyakb@gmail.com |
| Website | [kishansavaliya.com](https://kishansavaliya.com) |
| WhatsApp | +91 84012 70422 |
| GitHub Issues | [github.com/mage2sk/module-search-autocomplete/issues](https://github.com/mage2sk/module-search-autocomplete) |
| Upwork (Top Rated Plus) | [Hire Kishan Savaliya](https://www.upwork.com/freelancers/~016dd1767321100e21) |
| Upwork Agency | [Panth Infotech](https://www.upwork.com/agencies/1881421506131960778/) |

Response time: 1-2 business days.

### 💼 Need Custom Magento Development?

<p align="center">
  <a href="https://kishansavaliya.com/get-quote">
    <img src="https://img.shields.io/badge/%F0%9F%92%AC%20Get%20a%20Free%20Quote-kishansavaliya.com%2Fget--quote-DC2626?style=for-the-badge" alt="Get a Free Quote" />
  </a>
</p>

<p align="center">
  <a href="https://www.upwork.com/freelancers/~016dd1767321100e21">
    <img src="https://img.shields.io/badge/Hire%20Kishan-Top%20Rated%20Plus-14a800?style=for-the-badge&logo=upwork&logoColor=white" alt="Hire on Upwork" />
  </a>
  &nbsp;&nbsp;
  <a href="https://www.upwork.com/agencies/1881421506131960778/">
    <img src="https://img.shields.io/badge/Visit-Panth%20Infotech%20Agency-14a800?style=for-the-badge&logo=upwork&logoColor=white" alt="Visit Agency" />
  </a>
  &nbsp;&nbsp;
  <a href="https://kishansavaliya.com">
    <img src="https://img.shields.io/badge/Visit%20Website-kishansavaliya.com-0D9488?style=for-the-badge" alt="Visit Website" />
  </a>
</p>

---

## About Panth Infotech

Built and maintained by **Kishan Savaliya** — [kishansavaliya.com](https://kishansavaliya.com) — a **Top Rated Plus** Magento developer on Upwork with 10+ years of eCommerce experience.

**Panth Infotech** is a Magento 2 development agency specializing in high-quality, security-focused extensions and themes for both Hyva and Luma storefronts. Our extension suite covers SEO, performance, checkout, product presentation, customer engagement, and store management — over 34 modules built to MEQP standards and tested across Magento 2.4.4 to 2.4.8.

Browse the full extension catalog on the [Adobe Commerce Marketplace](https://commercemarketplace.adobe.com) or [Packagist](https://packagist.org/packages/mage2kishan/).

---

## Quick Links

- 🌐 **Website:** [kishansavaliya.com](https://kishansavaliya.com)
- 💬 **Get a Quote:** [kishansavaliya.com/get-quote](https://kishansavaliya.com/get-quote)
- 👨‍💻 **Upwork Profile (Top Rated Plus):** [upwork.com/freelancers/~016dd1767321100e21](https://www.upwork.com/freelancers/~016dd1767321100e21)
- 🏢 **Upwork Agency:** [upwork.com/agencies/1881421506131960778](https://www.upwork.com/agencies/1881421506131960778/)
- 📦 **Packagist:** [packagist.org/packages/mage2kishan/module-search-autocomplete](https://packagist.org/packages/mage2kishan/module-search-autocomplete)
- 🐙 **GitHub:** [github.com/mage2sk/module-search-autocomplete](https://github.com/mage2sk/module-search-autocomplete)
- 🛒 **Adobe Marketplace:** [commercemarketplace.adobe.com](https://commercemarketplace.adobe.com)
- 📧 **Email:** kishansavaliyakb@gmail.com
- 📱 **WhatsApp:** +91 84012 70422

---

<p align="center">
  <strong>Ready to supercharge your Magento 2 search experience?</strong><br/>
  <a href="https://kishansavaliya.com/get-quote">
    <img src="https://img.shields.io/badge/%F0%9F%9A%80%20Get%20Started%20%E2%86%92-Free%20Quote%20in%2024h-DC2626?style=for-the-badge" alt="Get Started" />
  </a>
</p>

---

**SEO Keywords:** magento 2 search autocomplete, instant search, ajax search, search suggestions, product autocomplete, magento 2 live search, magento 2 ajax search extension, magento 2 sku search, magento 2 search by description, magento 2 layered navigation search, magento 2 elasticsearch autocomplete, magento 2 opensearch autocomplete, hyva search autocomplete, luma search autocomplete, magento 2 search dropdown, magento 2 predictive search, magento 2 search suggestions extension, magento 2 product search box, magento 2 popular searches, magento 2 recent searches, magento 2 search performance, magento 2 search bot protection, magento 2 search rate limit, panth search autocomplete, panth infotech magento, kishan savaliya magento, mage2kishan search autocomplete, top rated plus magento developer, hire magento developer upwork, magento 2.4.8 search extension, php 8.4 magento module
