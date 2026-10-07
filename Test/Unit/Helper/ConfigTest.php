<?php
declare(strict_types=1);

namespace Panth\SearchAutocomplete\Test\Unit\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Panth\SearchAutocomplete\Helper\Config;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    private function config(array $values): Config
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            static fn(string $path) => $values[$path] ?? null
        );
        return new Config($scopeConfig);
    }

    public function testDefaultsApplyWhenNothingIsConfigured(): void
    {
        $config = $this->config([]);

        $this->assertTrue($config->isEnabled());
        $this->assertSame(2, $config->getMinQueryLength());
        $this->assertSame(64, $config->getMaxQueryLength());
        $this->assertSame(200, $config->getDebounceMs());
        $this->assertSame(6, $config->getProductsLimit());
        $this->assertSame(5, $config->getCategoriesLimit());
        $this->assertSame(3, $config->getPagesLimit());
        $this->assertSame(5, $config->getPopularLimit());
        $this->assertSame(300, $config->getCacheTtl());
        $this->assertSame(60, $config->getRateLimitPerMinute());
        $this->assertSame(4096, $config->getMaxBodyBytes());
    }

    public function testFlagsDefaultToTrueExceptKeystrokePopularity(): void
    {
        $config = $this->config([]);

        $this->assertTrue($config->showPrice());
        $this->assertTrue($config->showImage());
        $this->assertTrue($config->showCategories());
        $this->assertTrue($config->showPages());
        $this->assertTrue($config->showPopular());
        $this->assertTrue($config->isCacheEnabled());
        $this->assertTrue($config->requireFormKey());
        $this->assertTrue($config->blockEmptyUserAgent());
        $this->assertTrue($config->blockBotUserAgent());
        $this->assertTrue($config->isHoneypotEnabled());
        $this->assertTrue($config->requireAjaxHeader());
        $this->assertTrue($config->requireSameOrigin());
        $this->assertFalse($config->recordKeystrokePopularity());
    }

    public function testZeroStringTurnsFlagsOff(): void
    {
        $config = $this->config([
            Config::XML_ENABLED => '0',
            Config::XML_SHOW_PRICE => '0',
            Config::XML_SHOW_IMAGE => '0',
            Config::XML_REQUIRE_FORM_KEY => '0',
            Config::XML_CACHE_ENABLED => '0',
            Config::XML_BLOCK_BOT_UA => '0',
        ]);

        $this->assertFalse($config->isEnabled());
        $this->assertFalse($config->showPrice());
        $this->assertFalse($config->showImage());
        $this->assertFalse($config->requireFormKey());
        $this->assertFalse($config->isCacheEnabled());
        $this->assertFalse($config->blockBotUserAgent());
    }

    public function testEmptyStringFallsBackToTheDefault(): void
    {
        $config = $this->config([
            Config::XML_ENABLED => '',
            Config::XML_RECORD_KEYSTROKE_POPULARITY => '',
            Config::XML_MIN_QUERY_LENGTH => '',
        ]);

        $this->assertTrue($config->isEnabled());
        $this->assertFalse($config->recordKeystrokePopularity());
        $this->assertSame(2, $config->getMinQueryLength());
    }

    public function testKeystrokePopularityCanBeEnabled(): void
    {
        $this->assertTrue(
            $this->config([Config::XML_RECORD_KEYSTROKE_POPULARITY => '1'])->recordKeystrokePopularity()
        );
    }

    public static function clampProvider(): array
    {
        return [
            'min length floor'    => [Config::XML_MIN_QUERY_LENGTH, '0', 'getMinQueryLength', 1],
            'min length passthru' => [Config::XML_MIN_QUERY_LENGTH, '4', 'getMinQueryLength', 4],
            'max length floor'    => [Config::XML_MAX_QUERY_LENGTH, '3', 'getMaxQueryLength', 8],
            'max length ceiling'  => [Config::XML_MAX_QUERY_LENGTH, '9999', 'getMaxQueryLength', 256],
            'max length passthru' => [Config::XML_MAX_QUERY_LENGTH, '100', 'getMaxQueryLength', 100],
            'debounce floor'      => [Config::XML_DEBOUNCE_MS, '10', 'getDebounceMs', 50],
            'debounce passthru'   => [Config::XML_DEBOUNCE_MS, '750', 'getDebounceMs', 750],
            'products floor'      => [Config::XML_PRODUCTS_LIMIT, '0', 'getProductsLimit', 1],
            'products ceiling'    => [Config::XML_PRODUCTS_LIMIT, '50', 'getProductsLimit', 20],
            'categories zero'     => [Config::XML_CATEGORIES_LIMIT, '0', 'getCategoriesLimit', 0],
            'categories negative' => [Config::XML_CATEGORIES_LIMIT, '-4', 'getCategoriesLimit', 0],
            'categories ceiling'  => [Config::XML_CATEGORIES_LIMIT, '40', 'getCategoriesLimit', 15],
            'pages ceiling'       => [Config::XML_PAGES_LIMIT, '16', 'getPagesLimit', 15],
            'pages zero'          => [Config::XML_PAGES_LIMIT, '0', 'getPagesLimit', 0],
            'popular ceiling'     => [Config::XML_POPULAR_LIMIT, '99', 'getPopularLimit', 15],
            'popular passthru'    => [Config::XML_POPULAR_LIMIT, '7', 'getPopularLimit', 7],
            'ttl floor'           => [Config::XML_CACHE_TTL, '5', 'getCacheTtl', 30],
            'ttl passthru'        => [Config::XML_CACHE_TTL, '900', 'getCacheTtl', 900],
            'rate floor'          => [Config::XML_RATE_LIMIT_PER_MINUTE, '0', 'getRateLimitPerMinute', 10],
            'rate passthru'       => [Config::XML_RATE_LIMIT_PER_MINUTE, '120', 'getRateLimitPerMinute', 120],
            'body floor'          => [Config::XML_MAX_BODY_BYTES, '10', 'getMaxBodyBytes', 1024],
            'body ceiling'        => [Config::XML_MAX_BODY_BYTES, '999999', 'getMaxBodyBytes', 65536],
            'body passthru'       => [Config::XML_MAX_BODY_BYTES, '8192', 'getMaxBodyBytes', 8192],
        ];
    }

    #[DataProvider('clampProvider')]
    public function testNumericValuesAreClamped(string $path, string $raw, string $method, int $expected): void
    {
        $this->assertSame($expected, $this->config([$path => $raw])->{$method}());
    }

    public function testValuesAreReadAtStoreScope(): void
    {
        $scopes = [];
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            static function (string $path, string $scope) use (&$scopes) {
                $scopes[] = $scope;
                return null;
            }
        );
        $config = new Config($scopeConfig);
        $config->isEnabled();
        $config->getProductsLimit();

        $this->assertSame([ScopeInterface::SCOPE_STORE, ScopeInterface::SCOPE_STORE], $scopes);
    }
}
