<?php
declare(strict_types=1);

namespace Panth\SearchAutocomplete\Model\Suggestion;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Panth\SearchAutocomplete\Helper\Config;
use Psr\Log\LoggerInterface;

class CategoryProvider
{
    private CollectionFactory $collectionFactory;
    private StoreManagerInterface $storeManager;
    private Config $config;
    private LoggerInterface $logger;

    public function __construct(
        CollectionFactory $collectionFactory,
        StoreManagerInterface $storeManager,
        Config $config,
        LoggerInterface $logger
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->storeManager = $storeManager;
        $this->config = $config;
        $this->logger = $logger;
    }

    public function search(string $query): array
    {
        $limit = $this->config->getCategoriesLimit();
        if ($limit <= 0 || $query === '' || !$this->config->showCategories()) {
            return [];
        }
        try {
            $store = $this->storeManager->getStore();
            $rootCategoryId = (int) $store->getRootCategoryId();
            $like = '%' . $this->escapeLike($query) . '%';

            $collection = $this->collectionFactory->create();
            $collection
                ->addAttributeToSelect(['name', 'description', 'url_key', 'url_path', 'is_active', 'include_in_menu'])
                ->addAttributeToFilter('is_active', ['eq' => 1])
                ->addAttributeToFilter('path', ['like' => '1/' . $rootCategoryId . '/%'])
                ->addAttributeToFilter('entity_id', ['neq' => $rootCategoryId])
                ->addAttributeToFilter('name', ['like' => $like])
                ->setStore($store)
                ->addUrlRewriteToResult()
                ->setPageSize($limit * 2)
                ->setCurPage(1);

            $rows = $this->extractRows($collection, $query, $rootCategoryId, $limit);

            if (count($rows) < $limit) {
                try {
                    $descColl = $this->collectionFactory->create();
                    $descColl
                        ->addAttributeToSelect(['name', 'description', 'url_key', 'url_path', 'is_active'])
                        ->addAttributeToFilter('is_active', ['eq' => 1])
                        ->addAttributeToFilter('path', ['like' => '1/' . $rootCategoryId . '/%'])
                        ->addAttributeToFilter('entity_id', ['neq' => $rootCategoryId])
                        ->addAttributeToFilter('description', ['like' => $like])
                        ->setStore($store)
                        ->addUrlRewriteToResult()
                        ->setPageSize($limit * 2)
                        ->setCurPage(1);
                    $extra = $this->extractRows($descColl, $query, $rootCategoryId, $limit);
                    $seen = [];
                    foreach ($rows as $r) { $seen[$r['id']] = true; }
                    foreach ($extra as $r) {
                        if (count($rows) >= $limit) {
                            break;
                        }
                        if (!isset($seen[$r['id']])) {
                            $rows[] = $r;
                            $seen[$r['id']] = true;
                        }
                    }
                } catch (\Throwable $e) {
                }
            }

            return $this->addContextToDuplicateNames(array_slice($rows, 0, $limit), $store, $rootCategoryId);
        } catch (\Throwable $e) {
            $this->logger->warning('[PanthSearchAutocomplete] category search failed: ' . $e->getMessage());
            return [];
        }
    }

    private function addContextToDuplicateNames(array $rows, $store, int $rootCategoryId): array
    {
        $byName = [];
        foreach ($rows as $row) {
            $key = mb_strtolower($row['name']);
            $byName[$key] = ($byName[$key] ?? 0) + 1;
        }
        $ancestorIds = [];
        foreach ($rows as $row) {
            if ($byName[mb_strtolower($row['name'])] > 1) {
                foreach ($this->ancestorIds($row, $rootCategoryId) as $id) {
                    $ancestorIds[$id] = true;
                }
            }
        }
        $names = [];
        if ($ancestorIds) {
            try {
                $collection = $this->collectionFactory->create();
                $collection
                    ->addAttributeToSelect(['name'])
                    ->addAttributeToFilter('entity_id', ['in' => array_keys($ancestorIds)])
                    ->setStore($store);
                foreach ($collection as $cat) {
                    $names[(int) $cat->getId()] = trim(
                        html_entity_decode((string) $cat->getName(), ENT_QUOTES | ENT_HTML5, 'UTF-8')
                    );
                }
            } catch (\Throwable $e) {
                $names = [];
            }
        }
        $out = [];
        foreach ($rows as $row) {
            $ids = $byName[mb_strtolower($row['name'])] > 1 ? $this->ancestorIds($row, $rootCategoryId) : [];
            unset($row['path']);
            $labels = [];
            foreach ($ids as $id) {
                if (($names[$id] ?? '') !== '') {
                    $labels[] = $names[$id];
                }
            }
            if ($labels) {
                $row['context'] = implode(' / ', $labels);
            }
            $out[] = $row;
        }
        return $out;
    }

    private function ancestorIds(array $row, int $rootCategoryId): array
    {
        $ids = [];
        foreach (explode('/', (string) ($row['path'] ?? '')) as $part) {
            $id = (int) $part;
            if ($id > 1 && $id !== $rootCategoryId && $id !== $row['id']) {
                $ids[] = $id;
            }
        }
        return $ids;
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    private function extractRows($collection, string $query, int $rootCategoryId, int $limit): array
    {
        $rows = [];
        $needle = mb_strtolower($query);
        foreach ($collection as $cat) {
            $id = (int) $cat->getId();
            $name = trim(html_entity_decode((string) $cat->getName(), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($id <= 0 || $id === $rootCategoryId || $name === '') {
                continue;
            }

            $haystack = mb_strtolower($name . ' ' . (string) $cat->getData('description'));
            if (mb_strpos($haystack, $needle) === false) {
                continue;
            }
            $url = (string) $cat->getUrl();
            if ($url === '') {
                continue;
            }
            $rows[] = [
                'id'    => $id,
                'name'  => $name,
                'url'   => $url,
                'count' => (int) $cat->getProductCount(),
                'path'  => (string) $cat->getData('path'),
            ];
            if (count($rows) >= $limit) {
                break;
            }
        }
        return $rows;
    }
}
