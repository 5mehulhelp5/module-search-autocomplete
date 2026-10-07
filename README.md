# Magento 2 Search Autocomplete

Panth Search Autocomplete replaces the storefront search box with one that shows suggestions while the shopper types. A single JSON endpoint returns matching products (with image, SKU and price), categories, CMS pages and popular search terms, and the dropdown is rendered by a small vanilla JavaScript bundle without jQuery, RequireJS or Knockout. Products are found through Magento's catalog search layer, so the results follow whichever search engine the store is configured with.

The module is used by merchants who want faster product discovery from the header search and who need the suggestion endpoint protected from scripted abuse. It ships templates for both the Hyva theme and the stock Luma theme.

Product page: [kishansavaliya.com/magento-2-search-autocomplete.html](https://kishansavaliya.com/magento-2-search-autocomplete.html)

## Features

- Suggestion dropdown with four result sections: products, categories, CMS pages and popular searches. Each section has its own result limit and on/off switch.
- Product rows show name, SKU, final price and, when the product is on special, the regular price. Product images can be switched off.
- Products are searched through a fresh quick-search fulltext collection (`addSearchFilter`, same ranking as the search results page), which delegates to the configured Magento search engine. When fewer products than the limit are found, a direct `sku LIKE` lookup is added, followed by a query expansion step that adds similar words taken from the store's product names (substring, edit distance and metaphone matching).
- Categories are matched on name, then on description, restricted to the current store's root category and to active categories. Product counts are returned per category.
- CMS pages are matched on title, meta keywords, meta description, content heading, content and identifier, restricted to active pages assigned to the current store. The system pages `no-route`, `enable-cookies`, `home` and `privacy-policy-cookie-restriction-mode` are excluded.
- Popular searches are read from Magento's `search_query` table (terms with results, ordered by popularity). Only searches submitted to Magento's search results page raise a term's popularity; suggestion requests are not recorded unless "Count Typed Queries as Searches" is set to Yes.
- Recent searches are kept in the browser (`localStorage` key `psac_recent`, last 6 terms) and shown when the input is empty. A Clear link next to the heading forgets them.
- When neither the search engine nor the SKU lookup finds a product for the typed words but the similar-word expansion does, the response carries `did_you_mean` (for example `backpack` for `bakpack`). The dropdown shows "Showing results for ...", and both "See all results" and pressing Enter search the corrected term.
- When two matching categories share a name, each row also shows its parent path (for example "Men / Tops").
- Typed text is highlighted inside product, category and page names. Arrow keys move through product, category and page results, Enter opens the highlighted result, Escape closes the dropdown, and a "See all results" link leads to the normal search results page. The input is marked up as an ARIA combobox (`aria-expanded`, `aria-controls`, `aria-activedescendant`) and result rows as listbox options.
- Out-of-stock products are hidden unless "Display Out of Stock Products" is enabled in Magento's inventory settings. Only enabled products visible in search are returned.
- Dedicated cache type `panth_search_autocomplete` ("Panth Search Autocomplete" in Cache Management). Cached result sets are keyed by store, customer group and lower-cased query, tagged with the product, category and CMS page cache tags plus the module's own tag, and expire after the configured TTL.
- Request validation on the endpoint: form key check, per-IP rate limit, honeypot field, empty and known-bot User-Agent blocking, `X-Requested-With` header requirement, same-origin check, POST body size cap and query length bounds.
- Response headers on the endpoint: `Cache-Control: private, no-store, no-cache, must-revalidate`, `X-Content-Type-Options: nosniff`, `X-Robots-Tag: noindex, nofollow, nosnippet`, `Referrer-Policy: strict-origin-when-cross-origin`.
- Admin menu entry "Search Autocomplete" under "Panth Extensions" with an in-admin "Documentation" page and a shortcut to the configuration section.
- Storefront labels use `__()` and can be translated.

## Compatibility

| Platform | Versions |
|---|---|
| Magento Open Source | 2.4.4 to 2.4.8 (as published on the product page) |
| Adobe Commerce | 2.4.4 to 2.4.8 (as published on the product page) |
| PHP | 8.1, 8.2, 8.3, 8.4 (`~8.1.0||~8.2.0||~8.3.0||~8.4.0`) |
| Themes | Hyva, Luma |

Composer constraints on Magento packages: `magento/framework ^103.0`, `magento/module-backend ^102.0`, `magento/module-catalog ^104.0`, `magento/module-catalog-search ^102.0`, `magento/module-catalog-inventory ^100.4`, `magento/module-cms ^104.0`, `magento/module-customer ^103.0`, `magento/module-search ^101.1`, `magento/module-store ^101.1`, `magento/module-config ^101.2`.

## Requirements

- Magento Open Source or Adobe Commerce 2.4.4 to 2.4.8
- PHP 8.1, 8.2, 8.3 or 8.4
- `mage2kishan/module-core` `^1.0` (module `Panth_Core`; provides the "Panth Extensions" admin menu parent and is loaded before this module)
- The Magento packages listed under Compatibility
- A working catalog search index. The module queries the search engine set in `catalog/search/engine` through Magento's own catalog search layer; it does not talk to Elasticsearch or OpenSearch directly and needs no extra search service.

## Installation

```bash
composer require mage2kishan/module-search-autocomplete
bin/magento module:enable Panth_Core Panth_SearchAutocomplete
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
bin/magento cache:flush
```

`setup:di:compile` is only needed in production mode. `setup:static-content:deploy` is needed because the module ships a stylesheet and JavaScript files under `view/frontend/web`.

Check the module is enabled:

```bash
bin/magento module:status Panth_SearchAutocomplete
```

## Configuration

Admin path: Stores > Configuration > Panth Extensions > Search Autocomplete. All fields can be set at default, website and store view scope. All values below are the defaults from `etc/config.xml`; with the defaults the module is active and all protections are on as soon as it is installed.

### General

| Setting | Default | What it does |
|---|---|---|
| Enable Module | Yes | Renders the search box templates and serves the endpoint. When set to No, the theme's own search form is shown instead (the Hyva header search form or the Luma mini search form) and the endpoint answers `{"enabled": false}` with empty lists. |
| Minimum Query Length | 2 | Shorter queries are not sent by the browser and are rejected by the endpoint. An empty query returns popular searches only. |
| Maximum Query Length | 64 | Longer queries are rejected. The code clamps this value to the range 8 to 256. |
| Input Debounce (ms) | 200 | Delay between the last keystroke and the request. The code enforces a minimum of 50 ms. |

### Result Sections

| Setting | Default | What it does |
|---|---|---|
| Max Products | 6 | Number of product rows (code clamps to 1 to 20). |
| Max Categories | 5 | Number of category rows (0 to 15; 0 hides the section). |
| Max CMS Pages | 3 | Number of CMS page rows (0 to 15; 0 hides the section). |
| Max Popular Searches | 5 | Number of popular search chips (0 to 15; 0 hides the section). |
| Show Product Images | Yes | Includes a 120x120 `product_small_image` URL in each product row. |
| Show Product Prices | Yes | Includes final and regular price in each product row. |
| Show Categories Section | Yes | Enables the category lookup and section. |
| Show CMS Pages Section | Yes | Enables the CMS page lookup and section. |
| Show Popular Searches Section | Yes | Enables the popular searches lookup and section. |
| Count Typed Queries as Searches | No | When Yes, every uncached suggestion request writes the typed text to Magento's `search_query` table and raises its popularity through `saveIncrementalPopularity()`, which lets anyone seed Popular Searches with arbitrary terms. When No, popularity comes only from searches submitted to the search results page. Path: `panth_searchautocomplete/results/record_keystroke_popularity`. |

### Caching

| Setting | Default | What it does |
|---|---|---|
| Enable Result Cache | Yes | Stores each result set in the `panth_search_autocomplete` cache type. |
| Cache TTL (seconds) | 300 | Lifetime of a cached result set (code enforces a minimum of 30). Entries are also invalidated by the product, category and CMS page cache tags. |

### Bot & Abuse Prevention

| Setting | Default | What it does |
|---|---|---|
| Require Form Key | Yes | Requests without a valid Magento `form_key` are rejected. The bundled scripts send the value of the `form_key` cookie when it exists (full page cache), otherwise the form's hidden `form_key` input. |
| Rate Limit (req / min / IP) | 60 | Sliding 60-second window per client IP address and store, stored in the Magento cache. The client IP comes from Magento's `RemoteAddress` service, so a forwarded-for header is only used when it is configured as an alternative header for that service. Over the limit the endpoint returns HTTP 429. The code enforces a minimum of 10. |
| Block Empty User-Agent | Yes | Requests with no User-Agent header are rejected. |
| Block Bot User-Agent Patterns | Yes | Requests whose User-Agent contains one of 22 known client names (curl, wget, python-requests, scrapy, headlesschrome, puppeteer, playwright, sqlmap, nikto and others) are rejected. |
| Enable Honeypot Field | Yes | A hidden input named `website` must be empty. |
| Require X-Requested-With Header | Yes | The header must equal `XMLHttpRequest`; both bundled templates send it. |
| Require Same-Origin Request | Yes | When an Origin or Referer header is present, its host must match the store base URL host. |
| Max POST Body (bytes) | 4096 | POST requests with a larger body are rejected (code clamps to 1024 to 65536). |

Config paths:

```
panth_searchautocomplete/general/enabled
panth_searchautocomplete/general/min_query_length
panth_searchautocomplete/general/max_query_length
panth_searchautocomplete/general/debounce_ms
panth_searchautocomplete/results/products_limit
panth_searchautocomplete/results/categories_limit
panth_searchautocomplete/results/pages_limit
panth_searchautocomplete/results/popular_limit
panth_searchautocomplete/results/show_image
panth_searchautocomplete/results/show_price
panth_searchautocomplete/results/show_categories
panth_searchautocomplete/results/show_pages
panth_searchautocomplete/results/show_popular
panth_searchautocomplete/cache/enabled
panth_searchautocomplete/cache/ttl_seconds
panth_searchautocomplete/security/require_form_key
panth_searchautocomplete/security/rate_limit_per_minute
panth_searchautocomplete/security/block_empty_ua
panth_searchautocomplete/security/block_bot_ua
panth_searchautocomplete/security/honeypot_enabled
panth_searchautocomplete/security/require_ajax_header
panth_searchautocomplete/security/require_same_origin
panth_searchautocomplete/security/max_body_bytes
```

## Usage

### Storefront

On Luma, `view/frontend/layout/default.xml` removes the native `top.search` block and adds the module's search box to the `header.panel` container. The Luma template renders a search icon that opens an overlay with the input and dropdown. If the `Panth_ThemeCustomizer` module's header search input (`#panth-search-input`) is present on the page, `attach-to-themecustomizer.js` wires the dropdown to that input instead and hides the standalone overlay. The native `top.search` block is kept in the layout with a module template that renders nothing while the module is enabled and the native `Magento_Search::form.mini.phtml` form when it is disabled. On Hyva the `header-search` block renders the native `Magento_Theme::html/header/search-form.phtml` form when the module is disabled.

On Hyva, `view/frontend/layout/hyva_default.xml` removes the Luma block and replaces the template of Hyva's `header-search` block, so the search box opens inside Hyva's existing search overlay. Hyva's `searchOpen` Alpine state controls visibility; the suggestion behaviour itself comes from the same `autocomplete.js` bundle used on Luma.

The dropdown fetches after the configured debounce once the input reaches the minimum length. Focusing the empty input requests the popular searches, and the clear button brings them back. On the search results page the input is prefilled with the searched term. On Hyva the input is focused as soon as the search panel opens. Responses are also memoised per query in the browser for the life of the page.

### Endpoint

`GET` or `POST` `/searchautocomplete/ajax/suggest` with parameters `q`, `form_key` and the honeypot field `website` (empty). The response is JSON:

- Success: `enabled`, `query`, `products[]` (`id`, `name` with HTML entities such as `&trade;` decoded to characters, `sku`, `url`, `image`, `price{regular, final, has_special}`), `categories[]` (`id`, `name`, `url`, `count`), `pages[]` (`id`, `title`, `url`); category names and page titles are entity-decoded too, and the bundled scripts escape every value once before inserting it, `popular[]` (`text`, `results`), `view_all` (URL of the search results page for the query), `cache` (`hit` or `miss`), `took_ms`.
- Empty query (after sanitising): `enabled`, `query` (empty) and `popular[]`, with empty product, category and page lists. Nothing is written to `search_query`.
- Validation failure: HTTP 200 with `rejected: true` and empty lists.
- Rate limit exceeded: HTTP 429 with `throttled: true` and empty lists.
- Module disabled: `enabled: false` and empty lists.

Only GET and POST are accepted. The controller implements `CsrfAwareActionInterface` and does its own form key validation, controlled by "Require Form Key".

### Caching

Result sets are cached per store, customer group and lower-cased query. The query expansion vocabulary (words taken from product names) is cached in the same cache type for one hour per store. Flush or manage the cache type with:

```bash
bin/magento cache:clean panth_search_autocomplete
bin/magento cache:enable panth_search_autocomplete
bin/magento cache:disable panth_search_autocomplete
```

### Admin

The admin menu "Panth Extensions > Search Autocomplete" contains "Documentation" (route `panth_searchautocomplete/docs/index`, a static reference page) and "Configuration" (opens the configuration section).

### Templates

- `view/frontend/templates/searchbox.phtml` (Luma; block `panth.searchautocomplete.luma`)
- `view/frontend/templates/hyva/searchbox.phtml` (Hyva; set on block `header-search`)
- `view/adminhtml/templates/docs/index.phtml` (admin documentation page)
- Web assets: `view/frontend/web/css/autocomplete.css`, `view/frontend/web/js/autocomplete.js`, `view/frontend/web/js/attach-to-themecustomizer.js`

Both storefront templates receive the view model `Panth\SearchAutocomplete\ViewModel\SearchBox` as the `view_model` argument and read their settings from its `jsConfig()` JSON.

## Developer Notes

- Module name: `Panth_SearchAutocomplete`
- Composer package: `mage2kishan/module-search-autocomplete`
- Namespace: `Panth\SearchAutocomplete`
- Depends on (module sequence): `Panth_Core`, `Magento_Catalog`, `Magento_CatalogSearch`, `Magento_Search`, `Magento_Cms`, `Magento_Store`

Key classes:

- `Controller\Ajax\Suggest`: the JSON endpoint; runs validation, rate limiting, cache lookup, the four providers and the cache write.
- `Model\Security\RequestValidator`: `validate(RequestInterface): ?string` returns the sanitised query or `null`; `sanitiseQuery(string): string` strips control characters, quotes and angle brackets. Constant `HONEYPOT_FIELD = 'website'`.
- `Model\Security\RateLimiter`: `allow(RequestInterface, int $storeId): bool`.
- `Model\Suggestion\ProductProvider`, `CategoryProvider`, `CmsPageProvider`, `PopularProvider`: each exposes `search(string $query): array`. To add another result type, add a provider with the same signature, inject it into the controller and render the new section in both templates.
- `Model\Vocabulary\VocabularyProvider`: `getVocabulary(int $storeId): array` and `findSimilar(string $token, int $storeId, int $limit = 5): array` for query expansion.
- `Model\Cache\Type`: cache type `panth_search_autocomplete`, tag `PANTH_SEARCH_AUTOCOMPLETE`.
- `Helper\Config`: typed getters for every setting, with the `XML_*` path constants.
- `ViewModel\SearchBox`: `getEndpointUrl()`, `getViewAllSearchUrl()`, `getFormKey()`, `getHoneypotName()`, `jsConfig()` and the show/limit getters used by the templates.

Other facts:

- `etc/di.xml` only maps the cache type to the `default` cache frontend. There are no plugins, preferences, observers, console commands, cron jobs, web API routes or database tables.
- ACL resources: `Panth_SearchAutocomplete::root` ("Search Autocomplete"), `Panth_SearchAutocomplete::docs` ("Documentation"), `Panth_SearchAutocomplete::config` ("Configuration").
- Frontend route `searchautocomplete`; admin route `panth_searchautocomplete`.
- When "Count Typed Queries as Searches" is Yes, the controller writes to Magento's `search_query` table through the standard `Magento\Search\Model\Query` model; the module creates no tables of its own.

## Uninstallation

```bash
bin/magento module:disable Panth_SearchAutocomplete
composer remove mage2kishan/module-search-autocomplete
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

The module creates no database tables. Values saved under `panth_searchautocomplete/*` in `core_config_data` remain after removal and can be deleted manually. Popularity counts written to Magento's `search_query` table are part of Magento's own search data and remain. Terms recorded by earlier versions from typed suggestion requests can be reviewed and deleted under Marketing > SEO & Search > Search Terms.

## Support

- Product page: [kishansavaliya.com/magento-2-search-autocomplete.html](https://kishansavaliya.com/magento-2-search-autocomplete.html)
- Contact: [kishansavaliya.com/contact](https://kishansavaliya.com/contact)
- Email: kishansavaliyakb@gmail.com
- GitHub issues: [github.com/mage2sk/module-search-autocomplete/issues](https://github.com/mage2sk/module-search-autocomplete/issues)

## Documentation

[USER_GUIDE.md](USER_GUIDE.md) is written for store administrators and covers installation, verifying the extension is active, every configuration group, cache settings, bot and abuse prevention, the in-admin documentation page, setting up search synonyms, adding searchable attributes, troubleshooting and a CLI reference.

## License

Commercial software license. See [LICENSE.txt](LICENSE.txt) in this repository.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Links

- Website: [kishansavaliya.com](https://kishansavaliya.com)
- All extensions: [kishansavaliya.com/magento-extensions.html](https://kishansavaliya.com/magento-extensions.html)
- GitHub: [github.com/mage2sk/module-search-autocomplete](https://github.com/mage2sk/module-search-autocomplete)
- Packagist: [packagist.org/packages/mage2kishan/module-search-autocomplete](https://packagist.org/packages/mage2kishan/module-search-autocomplete)
