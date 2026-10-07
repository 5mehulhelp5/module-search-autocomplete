<?php
declare(strict_types=1);

namespace Panth\SearchAutocomplete\Test\Unit\Model\Vocabulary;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Module\ModuleListInterface;
use Panth\SearchAutocomplete\Model\Cache\Type as AutocompleteCache;
use Panth\SearchAutocomplete\Model\Vocabulary\VocabularyProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class VocabularyProviderTest extends TestCase
{
    private array $cacheStore = [];

    private array $saved = [];

    private array $warnings = [];

    private array $wheres = [];

    private int $connectionCalls = 0;

    /**
     * @param int|\Throwable $attributeId
     */
    private function provider($attributeId = 73, array $names = []): VocabularyProvider
    {
        $cache = $this->createStub(AutocompleteCache::class);
        $cache->method('load')->willReturnCallback(fn($key) => $this->cacheStore[$key] ?? false);
        $cache->method('save')->willReturnCallback(function ($data, $key, $tags, $ttl) {
            $this->saved[] = ['key' => $key, 'data' => $data, 'tags' => $tags, 'ttl' => $ttl];
            return true;
        });

        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('limit')->willReturnSelf();
        $select->method('distinct')->willReturnSelf();
        $select->method('where')->willReturnCallback(function ($cond, $value = null) use ($select) {
            $this->wheres[] = [$cond, $value];
            return $select;
        });

        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        if ($attributeId instanceof \Throwable) {
            $connection->method('fetchOne')->willThrowException($attributeId);
        } else {
            $connection->method('fetchOne')->willReturn((string) $attributeId);
        }
        $connection->method('fetchCol')->willReturn($names);

        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturnCallback(function () use ($connection) {
            $this->connectionCalls++;
            return $connection;
        });
        $resource->method('getTableName')->willReturnArgument(0);

        $logger = $this->createStub(LoggerInterface::class);
        $logger->method('warning')->willReturnCallback(function ($message) {
            $this->warnings[] = $message;
        });

        return new VocabularyProvider($resource, $cache, $logger, $this->createStub(ModuleListInterface::class));
    }

    private function seed(int $storeId, array $words): void
    {
        $this->cacheStore['panth_sav_' . $storeId] = json_encode(['words' => $words, 'buckets' => []]);
    }

    public function testCachedVocabularyIsReturnedWithoutTouchingTheDatabase(): void
    {
        $this->seed(1, ['shirt' => 3]);

        $vocab = $this->provider()->getVocabulary(1);

        $this->assertSame(['words' => ['shirt' => 3], 'buckets' => []], $vocab);
        $this->assertSame(0, $this->connectionCalls);
        $this->assertSame([], $this->saved);
    }

    public function testMalformedCacheEntryTriggersARebuild(): void
    {
        $this->cacheStore['panth_sav_1'] = json_encode(['words' => ['x' => 1]]);

        $this->provider(73, ['Blue Bag'])->getVocabulary(1);

        $this->assertSame(1, $this->connectionCalls);
        $this->assertCount(1, $this->saved);
    }

    public function testBuildCountsTokensSortsByFrequencyAndBuckets(): void
    {
        $vocab = $this->provider(73, ['Red Shirts', 'Blue Shirt', 'Shirt-Dress', 'A b'])->getVocabulary(5);

        $this->assertSame(3, $vocab['words']['shirt']);
        $this->assertSame(1, $vocab['words']['shirts']);
        $this->assertSame(1, $vocab['words']['red']);
        $this->assertSame(1, $vocab['words']['dres']);
        $this->assertArrayNotHasKey('a', $vocab['words']);
        $this->assertSame('shirt', array_key_first($vocab['words']));
        $this->assertContains('shirt', $vocab['buckets']['sh']);
        $this->assertContains('shirts', $vocab['buckets']['sh']);
        $this->assertSame(['blue'], $vocab['buckets']['bl']);
    }

    public function testBuildQueriesNameAttributeForDefaultAndStoreScope(): void
    {
        $this->provider(73, ['Mug'])->getVocabulary(5);

        $this->assertContains(['attribute_code = ?', 'name'], $this->wheres);
        $this->assertContains(['entity_type_id = ?', 4], $this->wheres);
        $this->assertContains(['v.attribute_id = ?', 73], $this->wheres);
        $this->assertContains(['v.store_id IN (?)', [0, 5]], $this->wheres);
    }

    public function testBuiltVocabularyIsCachedPerStoreWithTagsAndTtl(): void
    {
        $this->provider(73, ['Linen Shirt'])->getVocabulary(2);

        $this->assertCount(1, $this->saved);
        $this->assertSame('panth_sav_2', $this->saved[0]['key']);
        $this->assertSame(
            [AutocompleteCache::CACHE_TAG, \Magento\Catalog\Model\Product::CACHE_TAG],
            $this->saved[0]['tags']
        );
        $this->assertSame(3600, $this->saved[0]['ttl']);
        $decoded = json_decode($this->saved[0]['data'], true);
        $this->assertSame(['linen' => 1, 'shirt' => 1], $decoded['words']);
    }

    public function testMissingNameAttributeYieldsEmptyVocabulary(): void
    {
        $vocab = $this->provider(0, ['Ignored'])->getVocabulary(1);

        $this->assertSame(['words' => [], 'buckets' => []], $vocab);
    }

    public function testDatabaseFailureIsLoggedAndNotCached(): void
    {
        $vocab = $this->provider(new \RuntimeException('db gone'))->getVocabulary(1);

        $this->assertSame(['words' => [], 'buckets' => []], $vocab);
        $this->assertSame([], $this->saved);
        $this->assertStringContainsString('db gone', $this->warnings[0]);
    }

    public function testShortTokensAndEmptyVocabularyGiveNoSuggestions(): void
    {
        $this->seed(1, ['shirt' => 1]);
        $this->assertSame([], $this->provider()->findSimilar('sh', 1));

        $this->seed(2, []);
        $this->assertSame([], $this->provider()->findSimilar('shirt', 2));
    }

    public function testSuggestionsAreRankedBySubstringContainmentDistanceThenSound(): void
    {
        $this->seed(1, [
            'shirt' => 9,
            'tshirt' => 2,
            'shirts' => 1,
            'shir' => 9,
            'short' => 3,
            'jacket' => 50,
        ]);

        $result = $this->provider()->findSimilar('Shirt', 1, 10);

        $this->assertSame(['tshirt', 'shirts', 'shir', 'short'], $result);
    }

    public function testResultsAreCappedAtTheLimit(): void
    {
        $this->seed(1, ['tshirt' => 2, 'shirts' => 1, 'shir' => 9, 'short' => 3]);

        $this->assertSame(['tshirt', 'shirts'], $this->provider()->findSimilar('shirt', 1, 2));
    }

    public function testPhoneticMatchIsUsedWhenEditDistanceIsTooLarge(): void
    {
        $this->seed(1, ['phone' => 4, 'table' => 1]);

        $this->assertSame(['phone'], $this->provider()->findSimilar('fone', 1));
    }

    public function testExactWordIsNotSuggestedBackToItself(): void
    {
        $this->seed(1, ['lamp' => 7]);

        $this->assertSame([], $this->provider()->findSimilar('lamp', 1));
    }

    public function testNumericWordIsNotSuggestedBackToItself(): void
    {
        $this->seed(1, ['2024' => 7, '20245' => 1]);

        $this->assertSame(['20245'], $this->provider()->findSimilar('2024', 1));
    }

    public function testEditDistanceCountsCharactersNotBytes(): void
    {
        $this->seed(1, ["\u{0441}\u{0442}\u{0443}\u{043b}" => 3]);

        $this->assertSame(
            ["\u{0441}\u{0442}\u{0443}\u{043b}"],
            $this->provider()->findSimilar("\u{0441}\u{0442}\u{043e}\u{043b}", 1)
        );
    }
}
