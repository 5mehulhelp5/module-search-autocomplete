<?php
declare(strict_types=1);

namespace Panth\SearchAutocomplete\Test\Unit\Controller\Ajax;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\UrlInterface;
use Magento\Search\Model\QueryFactory;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Panth\SearchAutocomplete\Controller\Ajax\Suggest;
use Panth\SearchAutocomplete\Helper\Config;
use Panth\SearchAutocomplete\Model\Cache\Type as AutocompleteCache;
use Panth\SearchAutocomplete\Model\Security\RateLimiter;
use Panth\SearchAutocomplete\Model\Security\RequestValidator;
use Panth\SearchAutocomplete\Model\Suggestion\CategoryProvider;
use Panth\SearchAutocomplete\Model\Suggestion\CmsPageProvider;
use Panth\SearchAutocomplete\Model\Suggestion\PopularProvider;
use Panth\SearchAutocomplete\Model\Suggestion\ProductProvider;
use Panth\SearchAutocomplete\Test\Unit\Fixture\QueryDouble;
use PHPUnit\Framework\TestCase;

class SuggestTest extends TestCase
{
    private array $data = [];

    private ?int $code = null;

    private array $headers = [];

    private array $cacheStore = [];

    private array $cacheSaves = [];

    private array $cacheLoads = [];

    private array $providerCalls = [];

    private bool $rateLimiterConsulted = false;

    private array $urlCalls = [];

    private QueryDouble $query;

    private array $settings = [];

    private string $correction = '';

    protected function setUp(): void
    {
        $this->query = new QueryDouble();
    }

    private function controller($validated = 'Shirt', bool $allow = true): Suggest
    {
        $settings = $this->settings + [
            'isEnabled' => true,
            'isCacheEnabled' => true,
            'recordKeystrokePopularity' => false,
            'getCacheTtl' => 300,
        ];

        $result = $this->createStub(Json::class);
        $result->method('setHeader')->willReturnCallback(function ($name, $value) use ($result) {
            $this->headers[$name] = $value;
            return $result;
        });
        $result->method('setHttpResponseCode')->willReturnCallback(function ($code) use ($result) {
            $this->code = $code;
            return $result;
        });
        $result->method('setData')->willReturnCallback(function ($data) use ($result) {
            $this->data = $data;
            return $result;
        });
        $jsonFactory = $this->createStub(JsonFactory::class);
        $jsonFactory->method('create')->willReturn($result);

        $validator = $this->createStub(RequestValidator::class);
        $validator->method('validate')->willReturn($validated);

        $rateLimiter = $this->createStub(RateLimiter::class);
        $rateLimiter->method('allow')->willReturnCallback(function () use ($allow) {
            $this->rateLimiterConsulted = true;
            return $allow;
        });

        $cache = $this->createStub(AutocompleteCache::class);
        $cache->method('load')->willReturnCallback(function ($key) {
            $this->cacheLoads[] = $key;
            return $this->cacheStore[$key] ?? false;
        });
        $cache->method('save')->willReturnCallback(function ($data, $key, $tags, $ttl) {
            $this->cacheSaves[] = ['data' => $data, 'key' => $key, 'tags' => $tags, 'ttl' => $ttl];
            return true;
        });

        $config = $this->createStub(Config::class);
        foreach ($settings as $method => $value) {
            $config->method($method)->willReturn($value);
        }

        $store = $this->createStub(Store::class);
        $store->method('getId')->willReturn(1);
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $session = $this->createStub(CustomerSession::class);
        $session->method('getCustomerGroupId')->willReturn(2);

        $products = $this->createStub(ProductProvider::class);
        $products->method('search')->willReturnCallback(function ($q) {
            $this->providerCalls[] = ['products', $q];
            return [['id' => 1], ['id' => 2]];
        });
        $products->method('getCorrection')->willReturnCallback(fn() => $this->correction);
        $categories = $this->createStub(CategoryProvider::class);
        $categories->method('search')->willReturnCallback(function ($q) {
            $this->providerCalls[] = ['categories', $q];
            return [['id' => 10]];
        });
        $pages = $this->createStub(CmsPageProvider::class);
        $pages->method('search')->willReturnCallback(function ($q) {
            $this->providerCalls[] = ['pages', $q];
            return [];
        });
        $popular = $this->createStub(PopularProvider::class);
        $popular->method('search')->willReturnCallback(function ($q = '') {
            $this->providerCalls[] = ['popular', $q];
            return [['text' => 'shirt', 'results' => 5]];
        });

        $queryFactory = $this->createStub(QueryFactory::class);
        $queryFactory->method('get')->willReturn($this->query);

        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(function ($route, $params) {
            $this->urlCalls[] = [$route, $params];
            return 'https://shop.example.com/catalogsearch/result/?q=' . $params['_query']['q'];
        });

        return new Suggest(
            $this->createStub(RequestInterface::class),
            $jsonFactory,
            $validator,
            $rateLimiter,
            $cache,
            $config,
            $storeManager,
            $session,
            $products,
            $categories,
            $pages,
            $popular,
            $queryFactory,
            $url
        );
    }

    private function expectedKey(string $query): string
    {
        return 'panth_sac_' . sha1('1|2|' . mb_strtolower($query));
    }

    public function testSecurityHeadersAreAlwaysSent(): void
    {
        $this->settings = ['isEnabled' => false];
        $this->controller()->execute();

        $this->assertSame('private, no-store, no-cache, must-revalidate', $this->headers['Cache-Control']);
        $this->assertSame('no-cache', $this->headers['Pragma']);
        $this->assertSame('nosniff', $this->headers['X-Content-Type-Options']);
        $this->assertSame('noindex, nofollow, nosnippet', $this->headers['X-Robots-Tag']);
        $this->assertSame('strict-origin-when-cross-origin', $this->headers['Referrer-Policy']);
    }

    public function testDisabledModuleReturnsEmptyDisabledPayload(): void
    {
        $this->settings = ['isEnabled' => false];
        $this->controller()->execute();

        $this->assertSame(
            ['enabled' => false, 'products' => [], 'categories' => [], 'pages' => [], 'popular' => []],
            $this->data
        );
        $this->assertFalse($this->rateLimiterConsulted);
        $this->assertSame([], $this->providerCalls);
    }

    public function testRejectedRequestGetsSoftEmptyResponse(): void
    {
        $this->controller(null)->execute();

        $this->assertSame(200, $this->code);
        $this->assertTrue($this->data['rejected']);
        $this->assertSame([], $this->data['products']);
        $this->assertFalse($this->rateLimiterConsulted);
        $this->assertSame([], $this->providerCalls);
    }

    public function testThrottledRequestGets429(): void
    {
        $this->controller('shirt', false)->execute();

        $this->assertSame(429, $this->code);
        $this->assertTrue($this->data['throttled']);
        $this->assertSame([], $this->providerCalls);
        $this->assertSame([], $this->cacheLoads);
    }

    public function testEmptyQueryReturnsOnlyPopularTerms(): void
    {
        $this->controller('')->execute();

        $this->assertSame([['popular', '']], $this->providerCalls);
        $this->assertSame('', $this->data['query']);
        $this->assertSame([['text' => 'shirt', 'results' => 5]], $this->data['popular']);
        $this->assertSame([], $this->data['products']);
        $this->assertSame([], $this->cacheLoads);
    }

    public function testCacheMissQueriesAllProvidersAndStoresPayload(): void
    {
        $this->controller('Shirt')->execute();

        $this->assertSame(
            [['products', 'Shirt'], ['categories', 'Shirt'], ['pages', 'Shirt'], ['popular', 'Shirt']],
            $this->providerCalls
        );
        $this->assertSame('miss', $this->data['cache']);
        $this->assertSame('Shirt', $this->data['query']);
        $this->assertTrue($this->data['enabled']);
        $this->assertCount(2, $this->data['products']);
        $this->assertIsInt($this->data['took_ms']);
        $this->assertSame([['catalogsearch/result', ['_query' => ['q' => 'Shirt']]]], $this->urlCalls);

        $this->assertCount(1, $this->cacheSaves);
        $save = $this->cacheSaves[0];
        $this->assertSame($this->expectedKey('Shirt'), $save['key']);
        $this->assertSame(300, $save['ttl']);
        $this->assertSame(
            [
                AutocompleteCache::CACHE_TAG,
                \Magento\Catalog\Model\Category::CACHE_TAG,
                \Magento\Catalog\Model\Product::CACHE_TAG,
                \Magento\Cms\Model\Page::CACHE_TAG,
            ],
            $save['tags']
        );
        $stored = json_decode($save['data'], true);
        $this->assertArrayNotHasKey('took_ms', $stored, 'timing is not cached');
        $this->assertSame('miss', $stored['cache']);
    }

    public function testCacheKeyIsCaseInsensitiveAndScopedByStoreAndGroup(): void
    {
        $this->controller('SHIRT')->execute();

        $this->assertSame([$this->expectedKey('shirt')], $this->cacheLoads);
    }

    public function testCacheHitSkipsProviders(): void
    {
        $this->cacheStore[$this->expectedKey('shirt')] = json_encode(['query' => 'shirt', 'products' => [7]]);

        $this->controller('shirt')->execute();

        $this->assertSame([], $this->providerCalls);
        $this->assertSame([], $this->cacheSaves);
        $this->assertSame('hit', $this->data['cache']);
        $this->assertSame([7], $this->data['products']);
        $this->assertArrayHasKey('took_ms', $this->data);
    }

    public function testCacheHitUsesTheCasingOfTheCurrentQuery(): void
    {
        $this->cacheStore[$this->expectedKey('shirt')] = json_encode([
            'query' => 'shirt',
            'view_all' => 'https://shop.example.com/catalogsearch/result/?q=shirt',
            'products' => [7],
        ]);

        $this->controller('SHIRT')->execute();

        $this->assertSame('hit', $this->data['cache']);
        $this->assertSame('SHIRT', $this->data['query']);
        $this->assertSame('https://shop.example.com/catalogsearch/result/?q=SHIRT', $this->data['view_all']);
    }

    public function testUndecodableCacheEntryFallsThroughToProviders(): void
    {
        $this->cacheStore[$this->expectedKey('shirt')] = '{not json';

        $this->controller('shirt')->execute();

        $this->assertCount(4, $this->providerCalls);
        $this->assertSame('miss', $this->data['cache']);
    }

    public function testCacheDisabledNeverReadsOrWrites(): void
    {
        $this->settings = ['isCacheEnabled' => false];
        $this->controller('shirt')->execute();

        $this->assertSame([], $this->cacheLoads);
        $this->assertSame([], $this->cacheSaves);
        $this->assertSame('miss', $this->data['cache']);
    }

    public function testKeystrokePopularityRecordsTheQuery(): void
    {
        $this->settings = ['recordKeystrokePopularity' => true];
        $this->controller('shirt')->execute();

        $this->assertTrue($this->query->saved);
        $this->assertSame(1, $this->query->recorded['store_id']);
        $this->assertSame('shirt', $this->query->recorded['query_text']);
        $this->assertSame(2, $this->query->recorded['num_results']);
    }

    public function testKeystrokePopularityIsOffByDefault(): void
    {
        $this->controller('shirt')->execute();

        $this->assertFalse($this->query->saved);
        $this->assertSame([], $this->query->recorded);
    }

    public function testKeystrokePopularityFailureDoesNotBreakTheResponse(): void
    {
        $this->settings = ['recordKeystrokePopularity' => true];
        $this->query->failOnSave = true;

        $this->controller('shirt')->execute();

        $this->assertSame('miss', $this->data['cache']);
        $this->assertCount(1, $this->cacheSaves);
    }

    public function testCsrfValidationIsBypassedForThisEndpoint(): void
    {
        $controller = $this->controller();
        $request = $this->createStub(RequestInterface::class);

        $this->assertTrue($controller->validateForCsrf($request));
        $this->assertNull($controller->createCsrfValidationException($request));
    }

    public function testCorrectedQueryIsReturnedAndUsedForViewAll(): void
    {
        $this->correction = 'backpack';

        $this->controller('bakpack')->execute();

        $this->assertSame('backpack', $this->data['did_you_mean']);
        $this->assertSame('bakpack', $this->data['query']);
        $this->assertSame([['catalogsearch/result', ['_query' => ['q' => 'backpack']]]], $this->urlCalls);
        $stored = json_decode($this->cacheSaves[0]['data'], true);
        $this->assertSame('backpack', $stored['did_you_mean']);
    }

    public function testNoCorrectionKeepsTheTypedQueryForViewAll(): void
    {
        $this->controller('Shirt')->execute();

        $this->assertSame('', $this->data['did_you_mean']);
        $this->assertSame([['catalogsearch/result', ['_query' => ['q' => 'Shirt']]]], $this->urlCalls);
    }

    public function testCachedCorrectionIsReusedForViewAll(): void
    {
        $this->cacheStore[$this->expectedKey('bakpack')] = json_encode([
            'query' => 'bakpack',
            'did_you_mean' => 'backpack',
            'products' => [7],
        ]);

        $this->controller('Bakpack')->execute();

        $this->assertSame('hit', $this->data['cache']);
        $this->assertSame('Bakpack', $this->data['query']);
        $this->assertSame('backpack', $this->data['did_you_mean']);
        $this->assertSame([['catalogsearch/result', ['_query' => ['q' => 'backpack']]]], $this->urlCalls);
    }

    public function testOldCacheEntryWithoutCorrectionStillWorks(): void
    {
        $this->cacheStore[$this->expectedKey('shirt')] = json_encode(['query' => 'shirt', 'products' => [7]]);

        $this->controller('shirt')->execute();

        $this->assertSame('', $this->data['did_you_mean']);
        $this->assertSame([['catalogsearch/result', ['_query' => ['q' => 'shirt']]]], $this->urlCalls);
    }
}
