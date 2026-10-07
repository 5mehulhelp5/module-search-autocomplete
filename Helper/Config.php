<?php
declare(strict_types=1);

namespace Panth\SearchAutocomplete\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    public const XML_ENABLED               = 'panth_searchautocomplete/general/enabled';
    public const XML_MIN_QUERY_LENGTH      = 'panth_searchautocomplete/general/min_query_length';
    public const XML_MAX_QUERY_LENGTH      = 'panth_searchautocomplete/general/max_query_length';
    public const XML_DEBOUNCE_MS           = 'panth_searchautocomplete/general/debounce_ms';

    public const XML_PRODUCTS_LIMIT        = 'panth_searchautocomplete/results/products_limit';
    public const XML_CATEGORIES_LIMIT      = 'panth_searchautocomplete/results/categories_limit';
    public const XML_PAGES_LIMIT           = 'panth_searchautocomplete/results/pages_limit';
    public const XML_POPULAR_LIMIT         = 'panth_searchautocomplete/results/popular_limit';
    public const XML_SHOW_PRICE            = 'panth_searchautocomplete/results/show_price';
    public const XML_SHOW_IMAGE            = 'panth_searchautocomplete/results/show_image';
    public const XML_SHOW_CATEGORIES       = 'panth_searchautocomplete/results/show_categories';
    public const XML_SHOW_PAGES            = 'panth_searchautocomplete/results/show_pages';
    public const XML_SHOW_POPULAR          = 'panth_searchautocomplete/results/show_popular';
    public const XML_RECORD_KEYSTROKE_POPULARITY = 'panth_searchautocomplete/results/record_keystroke_popularity';

    public const XML_CACHE_ENABLED         = 'panth_searchautocomplete/cache/enabled';
    public const XML_CACHE_TTL             = 'panth_searchautocomplete/cache/ttl_seconds';

    public const XML_REQUIRE_FORM_KEY      = 'panth_searchautocomplete/security/require_form_key';
    public const XML_RATE_LIMIT_PER_MINUTE = 'panth_searchautocomplete/security/rate_limit_per_minute';
    public const XML_BLOCK_EMPTY_UA        = 'panth_searchautocomplete/security/block_empty_ua';
    public const XML_BLOCK_BOT_UA          = 'panth_searchautocomplete/security/block_bot_ua';
    public const XML_HONEYPOT_ENABLED      = 'panth_searchautocomplete/security/honeypot_enabled';
    public const XML_REQUIRE_AJAX_HEADER   = 'panth_searchautocomplete/security/require_ajax_header';
    public const XML_REQUIRE_SAME_ORIGIN   = 'panth_searchautocomplete/security/require_same_origin';
    public const XML_MAX_BODY_BYTES        = 'panth_searchautocomplete/security/max_body_bytes';

    private ScopeConfigInterface $scopeConfig;

    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    public function isEnabled(): bool
    {
        return (bool) $this->flag(self::XML_ENABLED, true);
    }

    public function getMinQueryLength(): int
    {
        return max(1, (int) $this->value(self::XML_MIN_QUERY_LENGTH, 2));
    }

    public function getMaxQueryLength(): int
    {
        return min(256, max(8, (int) $this->value(self::XML_MAX_QUERY_LENGTH, 64)));
    }

    public function getDebounceMs(): int
    {
        return max(50, (int) $this->value(self::XML_DEBOUNCE_MS, 200));
    }

    public function getProductsLimit(): int
    {
        return max(1, min(20, (int) $this->value(self::XML_PRODUCTS_LIMIT, 6)));
    }

    public function getCategoriesLimit(): int
    {
        return max(0, min(15, (int) $this->value(self::XML_CATEGORIES_LIMIT, 5)));
    }

    public function getPagesLimit(): int
    {
        return max(0, min(15, (int) $this->value(self::XML_PAGES_LIMIT, 3)));
    }

    public function getPopularLimit(): int
    {
        return max(0, min(15, (int) $this->value(self::XML_POPULAR_LIMIT, 5)));
    }

    public function showPrice(): bool        { return $this->flag(self::XML_SHOW_PRICE,       true); }
    public function showImage(): bool        { return $this->flag(self::XML_SHOW_IMAGE,       true); }
    public function showCategories(): bool   { return $this->flag(self::XML_SHOW_CATEGORIES,  true); }
    public function showPages(): bool        { return $this->flag(self::XML_SHOW_PAGES,       true); }
    public function showPopular(): bool      { return $this->flag(self::XML_SHOW_POPULAR,     true); }

    public function recordKeystrokePopularity(): bool
    {
        return $this->flag(self::XML_RECORD_KEYSTROKE_POPULARITY, false);
    }

    public function isCacheEnabled(): bool   { return $this->flag(self::XML_CACHE_ENABLED,    true); }
    public function getCacheTtl(): int       { return max(30, (int) $this->value(self::XML_CACHE_TTL, 300)); }

    public function requireFormKey(): bool   { return $this->flag(self::XML_REQUIRE_FORM_KEY, true); }
    public function getRateLimitPerMinute(): int { return max(10, (int) $this->value(self::XML_RATE_LIMIT_PER_MINUTE, 60)); }
    public function blockEmptyUserAgent(): bool { return $this->flag(self::XML_BLOCK_EMPTY_UA, true); }
    public function blockBotUserAgent(): bool { return $this->flag(self::XML_BLOCK_BOT_UA, true); }
    public function isHoneypotEnabled(): bool { return $this->flag(self::XML_HONEYPOT_ENABLED, true); }
    public function requireAjaxHeader(): bool { return $this->flag(self::XML_REQUIRE_AJAX_HEADER, true); }
    public function requireSameOrigin(): bool { return $this->flag(self::XML_REQUIRE_SAME_ORIGIN, true); }
    public function getMaxBodyBytes(): int { return max(1024, min(65536, (int) $this->value(self::XML_MAX_BODY_BYTES, 4096))); }

    private function value(string $path, $default)
    {
        $v = $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE);
        return $v === null || $v === '' ? $default : $v;
    }

    private function flag(string $path, bool $default): bool
    {
        $v = $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE);
        if ($v === null || $v === '') {
            return $default;
        }
        return (bool) $v;
    }
}
