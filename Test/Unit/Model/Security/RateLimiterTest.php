<?php
declare(strict_types=1);

namespace Panth\SearchAutocomplete\Test\Unit\Model\Security;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Panth\SearchAutocomplete\Helper\Config;
use Panth\SearchAutocomplete\Model\Security\RateLimiter;
use PHPUnit\Framework\TestCase;

class RateLimiterTest extends TestCase
{
    private array $store = [];

    private function limiter(string $ip, int $limit = 10): RateLimiter
    {
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('load')->willReturnCallback(fn ($key) => $this->store[$key] ?? false);
        $cache->method('save')->willReturnCallback(function ($data, $key) {
            $this->store[$key] = $data;
            return true;
        });
        $config = $this->createStub(Config::class);
        $config->method('getRateLimitPerMinute')->willReturn($limit);
        $remote = $this->createStub(RemoteAddress::class);
        $remote->method('getRemoteAddress')->willReturn($ip);
        return new RateLimiter($cache, $config, $remote);
    }

    private function request(string $userAgent): HttpRequest
    {
        $request = $this->createStub(HttpRequest::class);
        $request->method('getHeader')->willReturnMap([['User-Agent', false, $userAgent]]);
        $request->method('getServer')->willReturn('10.0.0.1');
        return $request;
    }

    public function testChangingUserAgentDoesNotResetTheLimit(): void
    {
        $limiter = $this->limiter('203.0.113.7');
        for ($i = 0; $i < 10; $i++) {
            $this->assertTrue($limiter->allow($this->request('agent-' . $i), 1));
        }
        $this->assertFalse($limiter->allow($this->request('agent-new'), 1));
    }

    public function testLimitIsCountedPerClientIpAndStore(): void
    {
        $first = $this->limiter('203.0.113.7');
        for ($i = 0; $i < 10; $i++) {
            $first->allow($this->request('ua'), 1);
        }
        $this->assertFalse($first->allow($this->request('ua'), 1));
        $this->assertTrue($first->allow($this->request('ua'), 2));
        $this->assertTrue($this->limiter('203.0.113.8')->allow($this->request('ua'), 1));
    }

    public function testTimestampsOlderThanTheWindowAreDropped(): void
    {
        $limiter = $this->limiter('198.51.100.4');
        $limiter->allow($this->request('ua'), 1);
        $key = array_key_first($this->store);
        $stale = array_fill(0, 10, (string) (time() - 120));
        $this->store[$key] = implode(',', $stale);

        $this->assertTrue($limiter->allow($this->request('ua'), 1));
        $this->assertCount(1, explode(',', $this->store[$key]));
    }

    public function testRejectedRequestKeepsTheCountWithoutAddingToIt(): void
    {
        $limiter = $this->limiter('198.51.100.5');
        for ($i = 0; $i < 10; $i++) {
            $limiter->allow($this->request('ua'), 1);
        }
        $key = array_key_first($this->store);
        $this->assertFalse($limiter->allow($this->request('ua'), 1));
        $this->assertFalse($limiter->allow($this->request('ua'), 1));
        $this->assertCount(10, explode(',', $this->store[$key]));
    }

    public function testCorruptCacheEntryIsTreatedAsEmptyHistory(): void
    {
        $limiter = $this->limiter('198.51.100.6');
        $limiter->allow($this->request('ua'), 1);
        $key = array_key_first($this->store);
        $this->store[$key] = 'garbage,also-garbage';

        $this->assertTrue($limiter->allow($this->request('ua'), 1));
        $this->assertCount(1, explode(',', $this->store[$key]));
    }

    public function testEntriesAreSavedWithASixtySecondLifetime(): void
    {
        $saved = [];
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('load')->willReturn(false);
        $cache->method('save')->willReturnCallback(
            static function ($data, $key, $tags, $lifetime) use (&$saved) {
                $saved[] = [$key, $tags, $lifetime];
                return true;
            }
        );
        $config = $this->createStub(Config::class);
        $config->method('getRateLimitPerMinute')->willReturn(10);
        $remote = $this->createStub(RemoteAddress::class);
        $remote->method('getRemoteAddress')->willReturn('192.0.2.1');

        (new RateLimiter($cache, $config, $remote))->allow($this->request('ua'), 3);

        $this->assertCount(1, $saved);
        $this->assertSame('panth_sac_rl_' . sha1('192.0.2.1|3'), $saved[0][0]);
        $this->assertSame([], $saved[0][1]);
        $this->assertSame(60, $saved[0][2]);
    }

    public function testFallsBackToServerRemoteAddrWhenNoClientIpIsResolved(): void
    {
        $keys = [];
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('load')->willReturn(false);
        $cache->method('save')->willReturnCallback(
            static function ($data, $key) use (&$keys) {
                $keys[] = $key;
                return true;
            }
        );
        $config = $this->createStub(Config::class);
        $config->method('getRateLimitPerMinute')->willReturn(10);
        $remote = $this->createStub(RemoteAddress::class);
        $remote->method('getRemoteAddress')->willReturn(false);

        (new RateLimiter($cache, $config, $remote))->allow($this->request('ua'), 1);

        $this->assertSame(['panth_sac_rl_' . sha1('10.0.0.1|1')], $keys);
    }

    public function testNonPositiveLimitDisablesThrottling(): void
    {
        $limiter = $this->limiter('198.51.100.9', 0);
        for ($i = 0; $i < 50; $i++) {
            $this->assertTrue($limiter->allow($this->request('ua'), 1));
        }
        $this->assertSame([], $this->store);
    }
}
