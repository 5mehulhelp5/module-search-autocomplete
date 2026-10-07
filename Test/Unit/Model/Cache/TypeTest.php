<?php
declare(strict_types=1);

namespace Panth\SearchAutocomplete\Test\Unit\Model\Cache;

use Magento\Framework\App\Cache\Type\FrontendPool;
use Magento\Framework\Cache\FrontendInterface;
use Panth\SearchAutocomplete\Model\Cache\Type;
use PHPUnit\Framework\TestCase;

class TypeTest extends TestCase
{
    private array $requested = [];

    private array $saved = [];

    private function type(): Type
    {
        $frontend = $this->createStub(FrontendInterface::class);
        $frontend->method('save')->willReturnCallback(function ($data, $id, $tags, $ttl) {
            $this->saved[] = [$data, $id, $tags, $ttl];
            return true;
        });
        $pool = $this->createStub(FrontendPool::class);
        $pool->method('get')->willReturnCallback(function ($id) use ($frontend) {
            $this->requested[] = $id;
            return $frontend;
        });
        return new Type($pool);
    }

    public function testUsesItsOwnFrontendAndTag(): void
    {
        $type = $this->type();

        $this->assertSame(['panth_search_autocomplete'], $this->requested);
        $this->assertSame(Type::CACHE_TAG, $type->getTag());
        $this->assertSame('PANTH_SEARCH_AUTOCOMPLETE', Type::CACHE_TAG);
    }

    public function testSavedEntriesAlwaysCarryTheModuleTag(): void
    {
        $this->type()->save('payload', 'key1', ['CAT_P'], 60);

        $this->assertSame('payload', $this->saved[0][0]);
        $this->assertSame('key1', $this->saved[0][1]);
        $this->assertContains('CAT_P', $this->saved[0][2]);
        $this->assertContains(Type::CACHE_TAG, $this->saved[0][2]);
        $this->assertSame(60, $this->saved[0][3]);
    }
}
