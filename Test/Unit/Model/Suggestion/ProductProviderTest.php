<?php
declare(strict_types=1);

namespace Panth\SearchAutocomplete\Test\Unit\Model\Suggestion;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Layer\Resolver as LayerResolver;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\CatalogInventory\Helper\Stock as StockHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Pricing\Amount\AmountInterface;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Magento\Framework\Pricing\Price\PriceInterface;
use Magento\Framework\Pricing\PriceInfoInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Panth\SearchAutocomplete\Helper\Config;
use Panth\SearchAutocomplete\Model\Suggestion\ProductProvider;
use Panth\SearchAutocomplete\Model\Vocabulary\VocabularyProvider;
use Panth\SearchAutocomplete\Test\Unit\Fixture\FakeCollection;
use Panth\SearchAutocomplete\Test\Unit\Fixture\ProductDouble;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ProductProviderTest extends TestCase
{
    /** @var array<int, FakeCollection|\Throwable> engine collections handed out in order */
    private array $engineQueue = [];

    /** @var FakeCollection[] */
    private array $engineCreated = [];

    /** @var array<int, FakeCollection|\Throwable> */
    private array $directQueue = [];

    /** @var FakeCollection[] */
    private array $directCreated = [];

    private array $stockFiltered = [];

    private array $warnings = [];

    private array $similarCalls = [];

    private array $similar = [];

    private array $settings = [];

    private function product(int $id, string $name = 'Item', ?float $final = null, ?float $regular = null)
    {
        $priceInfo = null;
        if ($final !== null) {
            $priceInfo = $this->priceInfo($final, $regular ?? $final);
        }
        return new ProductDouble([
            'id' => $id,
            'name' => $name,
            'sku' => 'SKU-' . $id,
            'url' => 'https://shop.example.com/p' . $id . '.html',
            'small_image' => '/a/b/p' . $id . '.jpg',
        ], $priceInfo);
    }

    private function priceInfo(float $final, float $regular): PriceInfoInterface
    {
        $build = function (float $value): PriceInterface {
            $amount = $this->createStub(AmountInterface::class);
            $amount->method('getValue')->willReturn($value);
            $price = $this->createStub(PriceInterface::class);
            $price->method('getAmount')->willReturn($amount);
            return $price;
        };
        $finalPrice = $build($final);
        $regularPrice = $build($regular);
        $info = $this->createStub(PriceInfoInterface::class);
        $info->method('getPrice')->willReturnCallback(
            static fn(string $code) => $code === 'final_price' ? $finalPrice : $regularPrice
        );
        return $info;
    }

    private function provider(bool $storeThrows = false, bool $imageThrows = false): ProductProvider
    {
        $settings = $this->settings + [
            'getProductsLimit' => 3,
            'showImage' => true,
            'showPrice' => true,
            'showOos' => false,
        ];

        $storeManager = $this->createStub(StoreManagerInterface::class);
        if ($storeThrows) {
            $storeManager->method('getStore')->willThrowException(new \RuntimeException('store gone'));
        } else {
            $store = $this->createStub(Store::class);
            $store->method('getId')->willReturn(1);
            $storeManager->method('getStore')->willReturn($store);
        }

        $visibility = $this->createStub(Visibility::class);
        $visibility->method('getVisibleInSearchIds')->willReturn([3, 4]);

        $image = $this->createStub(ImageHelper::class);
        if ($imageThrows) {
            $image->method('init')->willThrowException(new \RuntimeException('no image'));
        } else {
            $image->method('init')->willReturnSelf();
        }
        $image->method('setImageFile')->willReturnSelf();
        $image->method('resize')->willReturnSelf();
        $image->method('getUrl')->willReturn('https://cdn.example.com/thumb.jpg');

        $priceHelper = $this->createStub(PriceHelper::class);
        $priceHelper->method('currency')->willReturnCallback(
            static fn($value) => '$' . number_format((float) $value, 2)
        );

        $config = $this->createStub(Config::class);
        $config->method('getProductsLimit')->willReturn($settings['getProductsLimit']);
        $config->method('showImage')->willReturn($settings['showImage']);
        $config->method('showPrice')->willReturn($settings['showPrice']);

        $logger = $this->createStub(LoggerInterface::class);
        $logger->method('warning')->willReturnCallback(function ($message) {
            $this->warnings[] = $message;
        });

        $stock = $this->createStub(StockHelper::class);
        $stock->method('addInStockFilterToCollection')->willReturnCallback(function ($collection) {
            $this->stockFiltered[] = $collection;
        });

        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            static fn(string $path) => $path === 'cataloginventory/options/show_out_of_stock'
                ? ($settings['showOos'] ? '1' : '0')
                : null
        );

        $vocabulary = $this->createStub(VocabularyProvider::class);
        $vocabulary->method('findSimilar')->willReturnCallback(function (string $token, int $storeId, int $limit) {
            $this->similarCalls[] = [$token, $storeId, $limit];
            return $this->similar[$token] ?? [];
        });

        $directFactory = $this->createStub(ProductCollectionFactory::class);
        $directFactory->method('create')->willReturnCallback(function () {
            $next = array_shift($this->directQueue) ?? new FakeCollection();
            if ($next instanceof \Throwable) {
                throw $next;
            }
            $this->directCreated[] = $next;
            return $next;
        });

        $searchFactory = $this->createStub(ProductCollectionFactory::class);
        $searchFactory->method('create')->willReturnCallback(function () {
            $next = array_shift($this->engineQueue) ?? new FakeCollection();
            if ($next instanceof \Throwable) {
                throw $next;
            }
            $this->engineCreated[] = $next;
            return $next;
        });

        return new ProductProvider(
            $this->createStub(LayerResolver::class),
            $storeManager,
            $visibility,
            $image,
            $priceHelper,
            $config,
            $logger,
            $stock,
            $scopeConfig,
            $vocabulary,
            $directFactory,
            $searchFactory
        );
    }

    public function testEmptyQueryReturnsNothingWithoutSearching(): void
    {
        $this->assertSame([], $this->provider()->search(''));
        $this->assertSame([], $this->engineCreated);
    }

    public function testEngineResultsAreMappedToRows(): void
    {
        $this->engineQueue = [new FakeCollection([
            $this->product(1, 'Caf&eacute; &amp; Tea Mug', 8.0, 10.0),
            $this->product(2, 'Plain', 5.0, 5.0),
            $this->product(3, 'Third', 1.0),
        ])];

        $rows = $this->provider()->search('mug');

        $this->assertCount(3, $rows);
        $this->assertSame([
            'id' => 1,
            'name' => "Caf\u{00E9} & Tea Mug",
            'sku' => 'SKU-1',
            'url' => 'https://shop.example.com/p1.html',
            'image' => 'https://cdn.example.com/thumb.jpg',
            'price' => ['regular' => '$10.00', 'final' => '$8.00', 'has_special' => true],
        ], $rows[0]);
        $this->assertFalse($rows[1]['price']['has_special']);
        $this->assertSame([], $this->directCreated, 'direct SKU lookup is skipped when the engine fills the limit');
    }

    public function testEngineCollectionIsConfiguredForSearch(): void
    {
        $this->engineQueue = [new FakeCollection([$this->product(1), $this->product(2), $this->product(3)])];
        $this->provider()->search('jacket');

        $collection = $this->engineCreated[0];
        $this->assertSame([['jacket']], $collection->argsFor('addSearchFilter'));
        $this->assertSame([[[3, 4]]], $collection->argsFor('setVisibility'));
        $this->assertSame([['relevance', 'DESC']], $collection->argsFor('setOrder'));
        $this->assertSame([[24]], $collection->argsFor('setPageSize'), 'over-fetches at least 24 rows');
        $this->assertSame([[1]], $collection->argsFor('addStoreFilter'));
        $this->assertContains(['status', ['eq' => 1]], $collection->argsFor('addAttributeToFilter'));
    }

    public function testEngineResultsAreTrimmedToTheLimit(): void
    {
        $this->settings = ['getProductsLimit' => 2];
        $this->engineQueue = [new FakeCollection([$this->product(1), $this->product(2), $this->product(3)])];

        $this->assertSame([1, 2], array_column($this->provider()->search('tee'), 'id'));
    }

    public function testOutOfStockFilterAppliedOnlyWhenStoreHidesOutOfStock(): void
    {
        $engine = new FakeCollection([$this->product(1), $this->product(2), $this->product(3)]);
        $this->engineQueue = [$engine];
        $this->provider()->search('tee');
        $this->assertSame([$engine], $this->stockFiltered);

        $this->stockFiltered = [];
        $this->settings = ['showOos' => true];
        $this->engineQueue = [new FakeCollection([$this->product(1), $this->product(2), $this->product(3)])];
        $this->provider()->search('tee');
        $this->assertSame([], $this->stockFiltered);
    }

    public function testDirectSkuMatchesTopUpWithoutDuplicates(): void
    {
        $this->engineQueue = [new FakeCollection([$this->product(1)])];
        $this->directQueue = [new FakeCollection([$this->product(1), $this->product(5), $this->product(6)])];

        $rows = $this->provider()->search('ab_1%');

        $this->assertSame([1, 5, 6], array_column($rows, 'id'));
        $direct = $this->directCreated[0];
        $this->assertContains(['sku', ['like' => '%ab\\_1\\%%']], $direct->argsFor('addAttributeToFilter'));
        $this->assertSame([[3]], $direct->argsFor('setPageSize'));
        $this->assertSame([], $this->similarCalls, 'vocabulary is not consulted once the limit is reached');
    }

    public function testVocabularyExpansionRunsASecondEngineSearch(): void
    {
        $this->engineQueue = [
            new FakeCollection([]),
            new FakeCollection([$this->product(9)]),
        ];
        $this->similar = ['shirts' => ['tshirts'], 'shirt' => ['tshirt', 'tshirts']];

        $rows = $this->provider()->search('Red-Shirts');

        $this->assertSame([9], array_column($rows, 'id'));
        $this->assertSame(
            [['red', 1, 3], ['shirts', 1, 3], ['shirt', 1, 3]],
            $this->similarCalls
        );
        $this->assertSame([['Red-Shirts tshirts tshirt']], $this->engineCreated[1]->argsFor('addSearchFilter'));
    }

    public function testTokeniserStripsPluralsDropsShortTokensAndCapsAtSix(): void
    {
        $this->engineQueue = [new FakeCollection([])];
        $this->provider()->search('a boxes x/y glass caps hat_band');

        $tokens = array_column($this->similarCalls, 0);
        $this->assertSame(['boxes', 'boxe', 'box', 'glass', 'glas', 'caps'], $tokens);
    }

    public function testNoExpansionSearchWhenVocabularyHasNoSuggestions(): void
    {
        $this->engineQueue = [new FakeCollection([])];

        $this->assertSame([], $this->provider()->search('zzzz'));
        $this->assertCount(1, $this->engineCreated);
    }

    public function testEngineFailureIsLoggedAndDirectSearchStillRuns(): void
    {
        $this->engineQueue = [new \RuntimeException('engine down')];
        $this->directQueue = [new FakeCollection([$this->product(4)])];

        $rows = $this->provider()->search('sku4');

        $this->assertSame([4], array_column($rows, 'id'));
        $this->assertStringContainsString('engine down', $this->warnings[0]);
    }

    public function testDirectSearchFailureIsLogged(): void
    {
        $this->engineQueue = [new FakeCollection([$this->product(1)])];
        $this->directQueue = [new \RuntimeException('sku lookup failed')];

        $this->assertSame([1], array_column($this->provider()->search('x1'), 'id'));
        $this->assertStringContainsString('direct attribute search failed: sku lookup failed', $this->warnings[0]);
    }

    public function testStoreFailureReturnsEmptyAndLogs(): void
    {
        $this->assertSame([], $this->provider(true)->search('bag'));
        $this->assertStringContainsString('store gone', $this->warnings[0]);
    }

    public function testImageAndPriceAreOmittedWhenDisabled(): void
    {
        $this->settings = ['showImage' => false, 'showPrice' => false, 'getProductsLimit' => 1];
        $this->engineQueue = [new FakeCollection([$this->product(1, 'A', 5.0)])];

        $row = $this->provider()->search('a1')[0];

        $this->assertSame('', $row['image']);
        $this->assertNull($row['price']);
    }

    public function testImageFailureAndMissingPriceInfoDegradeGracefully(): void
    {
        $this->settings = ['getProductsLimit' => 1];
        $this->engineQueue = [new FakeCollection([$this->product(1, 'A')])];

        $row = $this->provider(false, true)->search('a1')[0];

        $this->assertSame('', $row['image']);
        $this->assertNull($row['price']);
        $this->assertSame('A', $row['name']);
    }

    public function testTypoWithNoDirectHitsReturnsACorrection(): void
    {
        $this->engineQueue = [
            new FakeCollection([]),
            new FakeCollection([$this->product(9), $this->product(10)]),
        ];
        $this->similar = ['bakpack' => ['backpack', 'backpacks'], 'blue' => []];

        $provider = $this->provider();
        $rows = $provider->search('Blue Bakpack');

        $this->assertSame([9, 10], array_column($rows, 'id'));
        $this->assertSame('blue backpack', $provider->getCorrection());
        $this->assertSame([['Blue Bakpack backpack backpacks']], $this->engineCreated[1]->argsFor('addSearchFilter'));
    }

    public function testNoCorrectionWhenThePrimarySearchAlreadyFoundSomething(): void
    {
        $this->engineQueue = [
            new FakeCollection([$this->product(1)]),
            new FakeCollection([$this->product(9)]),
        ];
        $this->similar = ['jack' => ['jacket']];

        $provider = $this->provider();
        $provider->search('jack');

        $this->assertSame('', $provider->getCorrection());
    }

    public function testNoCorrectionWhenTheExpansionFindsNothing(): void
    {
        $this->engineQueue = [new FakeCollection([]), new FakeCollection([])];
        $this->similar = ['zzzq' => ['zzzz']];

        $provider = $this->provider();
        $this->assertSame([], $provider->search('zzzq'));
        $this->assertSame('', $provider->getCorrection());
    }

    public function testCorrectionIsResetOnEverySearch(): void
    {
        $this->engineQueue = [
            new FakeCollection([]),
            new FakeCollection([$this->product(9)]),
            new FakeCollection([$this->product(1), $this->product(2), $this->product(3)]),
        ];
        $this->similar = ['bakpack' => ['backpack']];

        $provider = $this->provider();
        $provider->search('bakpack');
        $this->assertSame('backpack', $provider->getCorrection());

        $provider->search('bag');
        $this->assertSame('', $provider->getCorrection());
    }
}
