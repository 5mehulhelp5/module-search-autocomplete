<?php
declare(strict_types=1);

namespace Panth\SearchAutocomplete\Test\Unit\Model\Suggestion;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObject;
use Magento\Search\Model\ResourceModel\Query\CollectionFactory;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Panth\SearchAutocomplete\Helper\Config;
use Panth\SearchAutocomplete\Model\Suggestion\PopularProvider;
use Panth\SearchAutocomplete\Test\Unit\Fixture\FakeCollection;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PopularProviderTest extends TestCase
{
    private array $warnings = [];

    private bool $factoryCalled = false;

    private function provider($collection, int $limit = 5, bool $show = true): PopularProvider
    {
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturnCallback(function () use ($collection) {
            $this->factoryCalled = true;
            if ($collection instanceof \Throwable) {
                throw $collection;
            }
            return $collection;
        });

        $store = $this->createStub(Store::class);
        $store->method('getId')->willReturn(3);
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $config = $this->createStub(Config::class);
        $config->method('getPopularLimit')->willReturn($limit);
        $config->method('showPopular')->willReturn($show);

        $logger = $this->createStub(LoggerInterface::class);
        $logger->method('warning')->willReturnCallback(function ($message) {
            $this->warnings[] = $message;
        });

        return new PopularProvider(
            $factory,
            $storeManager,
            $config,
            $logger,
            $this->createStub(ResourceConnection::class)
        );
    }

    public function testDisabledOrZeroLimitReturnsNothing(): void
    {
        $this->assertSame([], $this->provider(new FakeCollection(), 0)->search('bag'));
        $this->assertSame([], $this->provider(new FakeCollection(), 5, false)->search('bag'));
        $this->assertFalse($this->factoryCalled);
    }

    public function testEmptyQueryListsTopTermsWithoutLikeFilter(): void
    {
        $collection = new FakeCollection([
            new DataObject(['query_text' => ' jacket ', 'num_results' => '14']),
            new DataObject(['query_text' => '   ', 'num_results' => 3]),
            new DataObject(['query_text' => 'tee', 'num_results' => 2]),
        ]);

        $result = $this->provider($collection)->search();

        $this->assertSame([
            ['text' => 'jacket', 'results' => 14],
            ['text' => 'tee', 'results' => 2],
        ], $result);
        $filters = $collection->argsFor('addFieldToFilter');
        $this->assertSame([
            ['store_id', 3],
            ['num_results', ['gt' => 0]],
            ['query_text', ['neq' => '']],
            ['display_in_terms', 1],
        ], $filters);
        $this->assertSame([['popularity', 'DESC']], $collection->argsFor('setOrder'));
        $this->assertSame([[5]], $collection->argsFor('setPageSize'));
    }

    public function testQueryAddsEscapedLikeFilter(): void
    {
        $collection = new FakeCollection();
        $this->provider($collection)->search('100%');

        $filters = $collection->argsFor('addFieldToFilter');
        $this->assertSame(['query_text', ['like' => '%100\\%%']], end($filters));
    }

    public function testFailureIsLoggedAndReturnsEmpty(): void
    {
        $this->assertSame([], $this->provider(new \RuntimeException('terms down'))->search('x'));
        $this->assertStringContainsString('terms down', $this->warnings[0]);
    }
}
