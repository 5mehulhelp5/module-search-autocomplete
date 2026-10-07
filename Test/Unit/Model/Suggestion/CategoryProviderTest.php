<?php
declare(strict_types=1);

namespace Panth\SearchAutocomplete\Test\Unit\Model\Suggestion;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\DataObject;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Panth\SearchAutocomplete\Helper\Config;
use Panth\SearchAutocomplete\Model\Suggestion\CategoryProvider;
use Panth\SearchAutocomplete\Test\Unit\Fixture\FakeCollection;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CategoryProviderTest extends TestCase
{
    private const ROOT_ID = 2;

    /** @var FakeCollection[] */
    private array $created = [];

    private array $warnings = [];

    private function category(
        int $id,
        string $name,
        string $url = '',
        string $description = '',
        int $count = 0,
        string $path = ''
    ) {
        return new DataObject([
            'id' => $id,
            'path' => $path,
            'name' => $name,
            'url' => $url === '' ? 'https://shop.example.com/c' . $id . '.html' : $url,
            'description' => $description,
            'product_count' => $count,
        ]);
    }

    /**
     * @param FakeCollection[] $collections returned in order by successive create() calls
     */
    private function provider(
        array $collections,
        int $limit = 5,
        bool $show = true,
        bool $storeThrows = false
    ): CategoryProvider {
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturnCallback(function () use (&$collections) {
            $next = array_shift($collections);
            if ($next instanceof \Throwable) {
                throw $next;
            }
            $this->created[] = $next;
            return $next;
        });

        $storeManager = $this->createStub(StoreManagerInterface::class);
        if ($storeThrows) {
            $storeManager->method('getStore')->willThrowException(new \RuntimeException('store gone'));
        } else {
            $store = $this->createStub(Store::class);
            $store->method('getRootCategoryId')->willReturn(self::ROOT_ID);
            $storeManager->method('getStore')->willReturn($store);
        }

        $config = $this->createStub(Config::class);
        $config->method('getCategoriesLimit')->willReturn($limit);
        $config->method('showCategories')->willReturn($show);

        $logger = $this->createStub(LoggerInterface::class);
        $logger->method('warning')->willReturnCallback(function ($message) {
            $this->warnings[] = $message;
        });

        return new CategoryProvider($factory, $storeManager, $config, $logger);
    }

    public function testShortCircuitsWithoutQueryLimitOrWhenHidden(): void
    {
        $this->assertSame([], $this->provider([new FakeCollection()])->search(''));
        $this->assertSame([], $this->provider([new FakeCollection()], 0)->search('bag'));
        $this->assertSame([], $this->provider([new FakeCollection()], 5, false)->search('bag'));
        $this->assertSame([], $this->created);
    }

    public function testNameMatchesAreReturnedAsRows(): void
    {
        $rows = [
            $this->category(10, 'Bags &amp; Totes', 'https://shop.example.com/bags.html', '', 12),
        ];
        $result = $this->provider([new FakeCollection($rows), new FakeCollection()])->search('bag');

        $this->assertSame([
            ['id' => 10, 'name' => 'Bags & Totes', 'url' => 'https://shop.example.com/bags.html', 'count' => 12],
        ], $result);
    }

    public function testFiltersAreScopedToTheStoreRootAndEscapeLikeWildcards(): void
    {
        $this->provider([new FakeCollection(), new FakeCollection()])->search('50%_off');

        $filters = $this->created[0]->argsFor('addAttributeToFilter');
        $this->assertContains(['path', ['like' => '1/2/%']], $filters);
        $this->assertContains(['entity_id', ['neq' => self::ROOT_ID]], $filters);
        $this->assertContains(['name', ['like' => '%50\\%\\_off%']], $filters);
        $this->assertContains(['is_active', ['eq' => 1]], $filters);
        $this->assertSame([[10]], $this->created[0]->argsFor('setPageSize'));
    }

    public function testRootInvalidAndUrlLessCategoriesAreSkipped(): void
    {
        $rows = [
            $this->category(self::ROOT_ID, 'Bag root'),
            $this->category(0, 'Bag zero'),
            $this->category(11, '   '),
            new DataObject(['id' => 12, 'name' => 'Bag no url', 'url' => '']),
            $this->category(13, 'Bag ok'),
        ];
        $result = $this->provider([new FakeCollection($rows), new FakeCollection()])->search('bag');

        $this->assertSame([13], array_column($result, 'id'));
    }

    public function testRowsThatDoNotContainTheQueryAreDropped(): void
    {
        $rows = [$this->category(20, 'Shoes'), $this->category(21, 'Handbags')];
        $result = $this->provider([new FakeCollection($rows), new FakeCollection()])->search('BAG');

        $this->assertSame([21], array_column($result, 'id'));
    }

    public function testDescriptionMatchesTopUpWithoutDuplicates(): void
    {
        $byName = new FakeCollection([$this->category(30, 'Travel bags')]);
        $byDescription = new FakeCollection([
            $this->category(30, 'Travel bags'),
            $this->category(31, 'Luggage', '', 'Every bag you need'),
            $this->category(32, 'Accessories', '', 'Small bag charms'),
        ]);

        $result = $this->provider([$byName, $byDescription], 2)->search('bag');

        $this->assertSame([30, 31], array_column($result, 'id'));
        $this->assertContains(['description', ['like' => '%bag%']], $byDescription->argsFor('addAttributeToFilter'));
    }

    public function testDescriptionQueryIsSkippedWhenNameMatchesFillTheLimit(): void
    {
        $byName = new FakeCollection([$this->category(40, 'Bag one'), $this->category(41, 'Bag two')]);
        $result = $this->provider([$byName, new FakeCollection()], 2)->search('bag');

        $this->assertSame([40, 41], array_column($result, 'id'));
        $this->assertCount(1, $this->created);
    }

    public function testNameCollectionStopsAtTheLimit(): void
    {
        $rows = [];
        for ($i = 1; $i <= 6; $i++) {
            $rows[] = $this->category(100 + $i, 'Bag ' . $i);
        }
        $result = $this->provider([new FakeCollection($rows)], 3)->search('bag');

        $this->assertCount(3, $result);
    }

    public function testDescriptionFailureKeepsNameResultsSilently(): void
    {
        $byName = new FakeCollection([$this->category(50, 'Bag')]);
        $broken = new FakeCollection([], new \RuntimeException('description query failed'));

        $result = $this->provider([$byName, $broken])->search('bag');

        $this->assertSame([50], array_column($result, 'id'));
        $this->assertSame([], $this->warnings);
    }

    public function testPrimaryFailureIsLoggedAndReturnsEmpty(): void
    {
        $result = $this->provider([new \RuntimeException('db down')])->search('bag');

        $this->assertSame([], $result);
        $this->assertCount(1, $this->warnings);
        $this->assertStringContainsString('db down', $this->warnings[0]);
    }

    public function testStoreFailureIsLoggedAndReturnsEmpty(): void
    {
        $this->assertSame([], $this->provider([], 5, true, true)->search('bag'));
        $this->assertStringContainsString('store gone', $this->warnings[0]);
    }

    public function testDuplicateNamesGetTheirAncestorPathAsContext(): void
    {
        $byName = new FakeCollection([
            $this->category(70, 'Jackets', '', '', 0, '1/2/11/12/70'),
            $this->category(71, 'Jackets', '', '', 0, '1/2/20/21/71'),
            $this->category(72, 'Jackets for kids', '', '', 0, '1/2/30/72'),
        ]);
        $ancestors = new FakeCollection([
            new DataObject(['id' => 11, 'name' => 'Men']),
            new DataObject(['id' => 12, 'name' => 'Tops']),
            new DataObject(['id' => 20, 'name' => 'Women &amp; Girls']),
            new DataObject(['id' => 21, 'name' => 'Tops']),
        ]);

        $result = $this->provider([$byName, new FakeCollection(), $ancestors])->search('jacket');

        $this->assertSame('Men / Tops', $result[0]['context']);
        $this->assertSame('Women & Girls / Tops', $result[1]['context']);
        $this->assertArrayNotHasKey('context', $result[2]);
        $this->assertArrayNotHasKey('path', $result[0]);
        $this->assertArrayNotHasKey('path', $result[2]);
        $filters = $ancestors->argsFor('addAttributeToFilter');
        $this->assertSame([['entity_id', ['in' => [11, 12, 20, 21]]]], $filters);
    }

    public function testAncestorLookupFailureKeepsRowsWithoutContext(): void
    {
        $byName = new FakeCollection([
            $this->category(80, 'Sale', '', '', 0, '1/2/11/80'),
            $this->category(81, 'Sale', '', '', 0, '1/2/12/81'),
        ]);
        $broken = new FakeCollection([], new \RuntimeException('ancestor query failed'));

        $result = $this->provider([$byName, new FakeCollection(), $broken])->search('sale');

        $this->assertSame([80, 81], array_column($result, 'id'));
        $this->assertArrayNotHasKey('context', $result[0]);
        $this->assertArrayNotHasKey('path', $result[1]);
        $this->assertSame([], $this->warnings);
    }
}
