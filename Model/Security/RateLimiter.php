<?php
declare(strict_types=1);

namespace Panth\SearchAutocomplete\Model\Security;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Panth\SearchAutocomplete\Helper\Config;

class RateLimiter
{
    private const KEY_PREFIX = 'panth_sac_rl_';
    private const WINDOW = 60;

    private CacheInterface $cache;
    private Config $config;
    private RemoteAddress $remoteAddress;

    public function __construct(CacheInterface $cache, Config $config, RemoteAddress $remoteAddress)
    {
        $this->cache = $cache;
        $this->config = $config;
        $this->remoteAddress = $remoteAddress;
    }

    public function allow(RequestInterface $request, int $storeId): bool
    {
        $limit = $this->config->getRateLimitPerMinute();
        if ($limit <= 0) {
            return true;
        }
        $key = $this->key($request, $storeId);
        $now = time();
        $raw = (string) $this->cache->load($key);
        $timestamps = $raw === '' ? [] : array_map('intval', explode(',', $raw));

        $cutoff = $now - self::WINDOW;
        $timestamps = array_values(array_filter($timestamps, static fn($t) => $t > $cutoff));
        if (count($timestamps) >= $limit) {
            $this->cache->save(implode(',', $timestamps), $key, [], self::WINDOW);
            return false;
        }
        $timestamps[] = $now;
        $this->cache->save(implode(',', $timestamps), $key, [], self::WINDOW);
        return true;
    }

    private function key(RequestInterface $request, int $storeId): string
    {
        $ip = (string) $this->remoteAddress->getRemoteAddress();
        if ($ip === '') {
            $ip = (string) $request->getServer('REMOTE_ADDR');
        }
        return self::KEY_PREFIX . sha1($ip . '|' . $storeId);
    }
}
