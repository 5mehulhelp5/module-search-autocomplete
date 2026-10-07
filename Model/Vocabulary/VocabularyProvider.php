<?php
declare(strict_types=1);

namespace Panth\SearchAutocomplete\Model\Vocabulary;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Module\ModuleListInterface;
use Panth\SearchAutocomplete\Model\Cache\Type as AutocompleteCache;
use Psr\Log\LoggerInterface;

class VocabularyProvider
{
    private const MIN_WORD = 3;

    private const MIN_FREQ = 1;

    private const TTL = 3600;

    private ResourceConnection $resource;
    private AutocompleteCache $cache;
    private LoggerInterface $logger;
    private ModuleListInterface $moduleList;

    public function __construct(
        ResourceConnection $resource,
        AutocompleteCache $cache,
        LoggerInterface $logger,
        ModuleListInterface $moduleList
    ) {
        $this->resource = $resource;
        $this->cache = $cache;
        $this->logger = $logger;
        $this->moduleList = $moduleList;
    }

    public function getVocabulary(int $storeId): array
    {
        $key = 'panth_sav_' . $storeId;
        $cached = $this->cache->load($key);
        if ($cached !== false && $cached !== null && $cached !== '') {
            $decoded = json_decode((string) $cached, true);
            if (is_array($decoded) && isset($decoded['words'], $decoded['buckets'])) {
                return $decoded;
            }
        }
        try {
            $vocab = $this->build($storeId);
            $this->cache->save(
                json_encode($vocab, JSON_UNESCAPED_UNICODE),
                $key,
                [
                    AutocompleteCache::CACHE_TAG,
                    \Magento\Catalog\Model\Product::CACHE_TAG,
                ],
                self::TTL
            );
            return $vocab;
        } catch (\Throwable $e) {
            $this->logger->warning('[PanthSearchAutocomplete] vocabulary build failed: ' . $e->getMessage());
            return ['words' => [], 'buckets' => []];
        }
    }

    private function build(int $storeId): array
    {
        $conn = $this->resource->getConnection();
        $eav = $this->resource->getTableName('eav_attribute');
        $varchar = $this->resource->getTableName('catalog_product_entity_varchar');

        $attrId = (int) $conn->fetchOne(
            $conn->select()
                ->from($eav, 'attribute_id')
                ->where('entity_type_id = ?', 4)
                ->where('attribute_code = ?', 'name')
                ->limit(1)
        );
        if ($attrId <= 0) {
            return ['words' => [], 'buckets' => []];
        }

        $select = $conn->select()
            ->distinct(true)
            ->from(['v' => $varchar], ['value'])
            ->where('v.attribute_id = ?', $attrId)
            ->where('v.store_id IN (?)', [0, $storeId])
            ->where('v.value IS NOT NULL')
            ->where("v.value != ''")
            ->limit(50000);
        $names = $conn->fetchCol($select);

        $words = [];
        foreach ($names as $name) {
            foreach ($this->tokenise((string) $name) as $token) {
                if (!isset($words[$token])) {
                    $words[$token] = 0;
                }
                $words[$token]++;
            }
        }

        if (self::MIN_FREQ > 1) {
            $words = array_filter($words, static fn($freq) => $freq >= self::MIN_FREQ);
        }
        arsort($words);

        $buckets = [];
        foreach (array_keys($words) as $word) {
            $word = (string) $word;
            $bucket = mb_substr($word, 0, 2);
            if (!isset($buckets[$bucket])) {
                $buckets[$bucket] = [];
            }
            $buckets[$bucket][] = $word;
        }

        return ['words' => $words, 'buckets' => $buckets];
    }

    private function tokenise(string $name): array
    {
        $name = mb_strtolower($name);
        $name = preg_replace('/[\-_\/]+/u', ' ', $name) ?? $name;
        $name = preg_replace('/[^\p{L}\p{N} ]+/u', '', $name) ?? $name;
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;
        $words = explode(' ', trim($name));
        $out = [];
        foreach ($words as $w) {
            if (mb_strlen($w) < self::MIN_WORD) {
                continue;
            }
            $out[] = $w;

            if (mb_strlen($w) >= 4 && mb_substr($w, -1) === 's') {
                $out[] = mb_substr($w, 0, -1);
            }
        }
        return $out;
    }

    public function findSimilar(string $token, int $storeId, int $limit = 5): array
    {
        if (mb_strlen($token) < self::MIN_WORD) {
            return [];
        }
        $vocab = $this->getVocabulary($storeId);
        if (!$vocab['words']) {
            return [];
        }
        $token = mb_strtolower($token);
        $tokenLen = mb_strlen($token);
        $tokenMeta = function_exists('metaphone') ? metaphone($token) : '';
        $maxDist = max(1, (int) ceil($tokenLen / 4));

        $matches = [];
        foreach ($vocab['words'] as $word => $freq) {
            $word = (string) $word;
            if ($word === $token) {
                continue;
            }
            $wordLen = mb_strlen((string) $word);

            if (mb_strpos((string) $word, $token) !== false) {
                $matches[$word] = 1000 + (int) $freq;
                continue;
            }

            if ($wordLen >= 4 && mb_strpos($token, (string) $word) !== false) {
                $matches[$word] = 800 + (int) $freq;
                continue;
            }

            if (abs($wordLen - $tokenLen) <= $maxDist) {
                $dist = $this->editDistance($token, $word);
                if ($dist > 0 && $dist <= $maxDist) {
                    $matches[$word] = (200 - $dist * 50) + (int) $freq;
                    continue;
                }
            }

            if ($tokenMeta !== '' && metaphone((string) $word) === $tokenMeta) {
                $matches[$word] = 50 + (int) $freq;
            }
        }
        if (!$matches) {
            return [];
        }
        arsort($matches);
        return array_map('strval', array_slice(array_keys($matches), 0, $limit));
    }

    private function editDistance(string $a, string $b): int
    {
        $left = mb_str_split($a);
        $right = mb_str_split($b);
        $previous = range(0, count($right));
        foreach ($left as $i => $leftChar) {
            $current = [$i + 1];
            foreach ($right as $j => $rightChar) {
                $current[$j + 1] = min(
                    $previous[$j + 1] + 1,
                    $current[$j] + 1,
                    $previous[$j] + ($leftChar === $rightChar ? 0 : 1)
                );
            }
            $previous = $current;
        }
        return $previous[count($right)];
    }
}
