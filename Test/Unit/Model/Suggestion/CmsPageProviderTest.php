<?php
declare(strict_types=1);

namespace Panth\SearchAutocomplete\Test\Unit\Model\Suggestion;

use Magento\Cms\Model\ResourceModel\Page\CollectionFactory;
use Magento\Framework\DataObject;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Panth\SearchAutocomplete\Helper\Config;
use Panth\SearchAutocomplete\Model\Suggestion\CmsPageProvider;
use Panth\SearchAutocomplete\Test\Unit\Fixture\FakeCollection;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CmsPageProviderTest extends TestCase
{
    private array $warnings = [];

    private bool $factoryCalled = false;

    private function provider($collection, int $limit = 3, bool $show = true): CmsPageProvider
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
        $store->method('getId')->willReturn(4);
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(
            static fn($route, $params) => 'https://shop.example.com/' . $params['_direct']
        );

        $config = $this->createStub(Config::class);
        $config->method('getPagesLimit')->willReturn($limit);
        $config->method('showPages')->willReturn($show);

        $logger = $this->createStub(LoggerInterface::class);
        $logger->method('warning')->willReturnCallback(function ($message) {
            $this->warnings[] = $message;
        });

        return new CmsPageProvider($factory, $storeManager, $url, $config, $logger);
    }

    public function testShortCircuitsWithoutQueryLimitOrWhenHidden(): void
    {
        $this->assertSame([], $this->provider(new FakeCollection())->search(''));
        $this->assertSame([], $this->provider(new FakeCollection(), 0)->search('about'));
        $this->assertSame([], $this->provider(new FakeCollection(), 3, false)->search('about'));
        $this->assertFalse($this->factoryCalled);
    }

    public function testMatchingPagesBecomeRowsWithDirectUrls(): void
    {
        $collection = new FakeCollection([
            new DataObject(['id' => 7, 'identifier' => 'about-us', 'title' => 'About &quot;Us&quot;']),
            new DataObject(['id' => 8, 'identifier' => '', 'title' => 'Hidden']),
        ]);

        $result = $this->provider($collection)->search('about');

        $this->assertSame([
            ['id' => 7, 'title' => 'About "Us"', 'url' => 'https://shop.example.com/about-us'],
        ], $result);
    }

    public function testCollectionIsFilteredToActiveStorePagesAndExcludesSystemPages(): void
    {
        $collection = new FakeCollection();
        $this->provider($collection, 2)->search('re_turn');

        $this->assertSame([[4]], $collection->argsFor('addStoreFilter'));
        $filters = $collection->argsFor('addFieldToFilter');
        $this->assertContains(['is_active', ['eq' => 1]], $filters);
        $this->assertContains(['identifier', ['neq' => '']], $filters);
        $excluded = ['no-route', 'enable-cookies', 'home', 'privacy-policy-cookie-restriction-mode'];
        $this->assertContains(['identifier', ['nin' => $excluded]], $filters);

        $search = end($filters);
        $this->assertSame(
            ['title', 'meta_keywords', 'meta_description', 'content_heading', 'content', 'identifier'],
            $search[0]
        );
        $this->assertCount(6, $search[1]);
        $this->assertSame(['like' => '%re\\_turn%'], $search[1][0]);
        $this->assertSame([[2]], $collection->argsFor('setPageSize'));
        $this->assertSame([['title', 'ASC']], $collection->argsFor('setOrder'));
    }

    public function testFailureIsLoggedAndReturnsEmpty(): void
    {
        $this->assertSame([], $this->provider(new \RuntimeException('cms down'))->search('about'));
        $this->assertCount(1, $this->warnings);
        $this->assertStringContainsString('cms down', $this->warnings[0]);
    }
}
