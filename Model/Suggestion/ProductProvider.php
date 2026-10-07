<?php
declare(strict_types=1);

namespace Panth\SearchAutocomplete\Model\Suggestion;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Layer\Resolver as LayerResolver;
use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductStatus;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\CatalogInventory\Helper\Stock as StockHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Pricing\Helper\Data as PriceHelper;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\SearchAutocomplete\Helper\Config;
use Panth\SearchAutocomplete\Model\Vocabulary\VocabularyProvider;
use Psr\Log\LoggerInterface;

class ProductProvider
{
    private const MAX_EXPANSION_TOKENS = 6;

    private const SEARCH_COLLECTION_FACTORY =
        \Magento\CatalogSearch\Model\ResourceModel\Fulltext\SearchCollectionFactory::class;

    private LayerResolver $layerResolver;
    private StoreManagerInterface $storeManager;
    private Visibility $visibility;
    private ImageHelper $imageHelper;
    private PriceHelper $priceHelper;
    private Config $config;
    private LoggerInterface $logger;
    private StockHelper $stockHelper;
    private ScopeConfigInterface $scopeConfig;
    private VocabularyProvider $vocabulary;
    private ProductCollectionFactory $searchCollectionFactory;
    private string $correction = '';
    private ProductCollectionFactory $productCollectionFactory;

    public function __construct(
        LayerResolver $layerResolver,
        StoreManagerInterface $storeManager,
        Visibility $visibility,
        ImageHelper $imageHelper,
        PriceHelper $priceHelper,
        Config $config,
        LoggerInterface $logger,
        StockHelper $stockHelper,
        ScopeConfigInterface $scopeConfig,
        VocabularyProvider $vocabulary,
        ProductCollectionFactory $productCollectionFactory,
        ?ProductCollectionFactory $searchCollectionFactory = null
    ) {
        $this->layerResolver = $layerResolver;
        $this->storeManager = $storeManager;
        $this->visibility = $visibility;
        $this->imageHelper = $imageHelper;
        $this->priceHelper = $priceHelper;
        $this->config = $config;
        $this->logger = $logger;
        $this->stockHelper = $stockHelper;
        $this->scopeConfig = $scopeConfig;
        $this->vocabulary = $vocabulary;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->searchCollectionFactory = $searchCollectionFactory
            ?? ObjectManager::getInstance()->get(self::SEARCH_COLLECTION_FACTORY);
    }

    public function search(string $query): array
    {
        $this->correction = '';
        $limit = $this->config->getProductsLimit();
        if ($limit <= 0 || $query === '') {
            return [];
        }

        try {
            $store = $this->storeManager->getStore();
            $storeId = (int) $store->getId();

            $items = $this->runEngineSearch($query, $store, $storeId, $limit);

            if (count($items) < $limit) {
                $extra = $this->searchByDirectAttributes($query, $store, $storeId, $limit);
                $items = $this->mergeUnique($items, $extra, $limit);
            }

            if (count($items) < $limit) {
                $primaryCount = count($items);
                $similar = [];
                $expanded = $this->expandQueryViaVocabulary($query, $storeId, $similar);
                if ($expanded !== '' && $expanded !== $query) {
                    $extra = $this->runEngineSearch($expanded, $store, $storeId, $limit);
                    $items = $this->mergeUnique($items, $extra, $limit);
                    if ($primaryCount === 0 && $items) {
                        $this->correction = $this->buildCorrection($query, $similar);
                    }
                }
            }
            return $items;
        } catch (\Throwable $e) {
            $this->logger->warning('[PanthSearchAutocomplete] product search failed: ' . $e->getMessage());
            return [];
        }
    }

    private function searchByDirectAttributes(string $query, $store, int $storeId, int $limit): array
    {
        try {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query) . '%';
            $collection = $this->productCollectionFactory->create();
            $collection
                ->addAttributeToSelect(['name', 'small_image', 'thumbnail', 'price', 'special_price', 'sku', 'url_key'])
                ->setStore($store)
                ->addStoreFilter($storeId)
                ->addAttributeToFilter('status', ['eq' => ProductStatus::STATUS_ENABLED])
                ->setVisibility($this->visibility->getVisibleInSearchIds())

                ->addAttributeToFilter('sku', ['like' => $like])
                ->setPageSize($limit)
                ->setCurPage(1);

            $showOos = (bool) $this->scopeConfig->getValue(
                'cataloginventory/options/show_out_of_stock',
                ScopeInterface::SCOPE_STORE
            );
            if (!$showOos) {
                $this->stockHelper->addInStockFilterToCollection($collection);
            }

            $rows = [];
            foreach ($collection as $product) {
                $rows[] = $this->buildRow($product);
            }
            return $rows;
        } catch (\Throwable $e) {
            $this->logger->warning('[PanthSearchAutocomplete] direct attribute search failed: ' . $e->getMessage());
            return [];
        }
    }

    private function mergeUnique(array $primary, array $secondary, int $limit): array
    {
        $seen = [];
        foreach ($primary as $row) {
            $seen[$row['id']] = true;
        }
        foreach ($secondary as $row) {
            if (count($primary) >= $limit) {
                break;
            }
            if (!isset($seen[$row['id']])) {
                $primary[] = $row;
                $seen[$row['id']] = true;
            }
        }
        return $primary;
    }

    private function buildRow(\Magento\Catalog\Api\Data\ProductInterface $product): array
    {
        $imageUrl = '';
        if ($this->config->showImage()) {
            try {
                $imageUrl = $this->imageHelper
                    ->init($product, 'product_small_image')
                    ->setImageFile((string) $product->getSmallImage())
                    ->resize(120, 120)
                    ->getUrl();
            } catch (\Throwable $e) {
                $imageUrl = '';
            }
        }
        $priceRow = null;
        if ($this->config->showPrice()) {
            $priceRow = $this->extractPrice($product);
        }
        return [
            'id'    => (int) $product->getId(),
            'name'  => html_entity_decode((string) $product->getName(), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'sku'   => (string) $product->getSku(),
            'url'   => (string) $product->getProductUrl(),
            'image' => $imageUrl,
            'price' => $priceRow,
        ];
    }

    public function getCorrection(): string
    {
        return $this->correction;
    }

    private function buildCorrection(string $query, array $similar): string
    {
        $normalised = $this->normalise($query);
        if ($normalised === '') {
            return '';
        }
        $words = [];
        foreach (explode(' ', $normalised) as $word) {
            $words[] = isset($similar[$word][0]) ? (string) $similar[$word][0] : $word;
        }
        $corrected = implode(' ', $words);
        return $corrected === $normalised ? '' : $corrected;
    }

    private function expandQueryViaVocabulary(string $query, int $storeId, array &$similarByToken = []): string
    {
        $tokens = array_slice($this->tokenise($this->normalise($query)), 0, self::MAX_EXPANSION_TOKENS);
        if (!$tokens) {
            return '';
        }
        $extras = [];
        foreach ($tokens as $tok) {
            $tokStr = (string) $tok;
            if ($tokStr === '') {
                continue;
            }
            $found = $this->vocabulary->findSimilar($tokStr, $storeId, 3);
            $similarByToken[$tokStr] = array_map('strval', $found);
            foreach ($found as $similar) {
                $extras[(string) $similar] = true;
            }
        }
        if (!$extras) {
            return '';
        }
        return $query . ' ' . implode(' ', array_keys($extras));
    }

    private function runEngineSearch(string $query, $store, int $storeId, int $limit): array
    {
        try {
            $collection = $this->searchCollectionFactory->create();
            $collection
                ->addAttributeToSelect(['name', 'small_image', 'thumbnail', 'price', 'special_price', 'sku', 'url_key'])
                ->setStore($store)
                ->addStoreFilter((int) $store->getId())

                ->setVisibility($this->visibility->getVisibleInSearchIds())

                ->addAttributeToFilter('status', ['eq' => ProductStatus::STATUS_ENABLED])
                ->addSearchFilter($query)

                ->setOrder('relevance', 'DESC')

                ->setPageSize(max($limit * 4, 24))
                ->setCurPage(1);

            $showOos = (bool) $this->scopeConfig->getValue(
                'cataloginventory/options/show_out_of_stock',
                ScopeInterface::SCOPE_STORE
            );
            if (!$showOos) {
                $this->stockHelper->addInStockFilterToCollection($collection);
            }

            $ordered = [];
            foreach ($collection as $product) {
                $ordered[] = $product;
            }
            $ordered = array_slice($ordered, 0, $limit);

            $items = [];
            foreach ($ordered as $product) {
                $items[] = $this->buildRow($product);
            }
            return $items;
        } catch (\Throwable $e) {
            $this->logger->warning('[PanthSearchAutocomplete] product search failed: ' . $e->getMessage());
            return [];
        }
    }

    private function normalise(string $value): string
    {
        $value = mb_strtolower($value);

        $value = preg_replace('/[\-_\/]+/u', ' ', $value) ?? $value;

        $value = preg_replace('/[^\p{L}\p{N} ]+/u', '', $value) ?? $value;

        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        return trim($value);
    }

    private function tokenise(string $normalised): array
    {
        if ($normalised === '') {
            return [];
        }
        $raw = explode(' ', $normalised);
        $out = [];
        foreach ($raw as $tok) {
            if (mb_strlen($tok) < 2) {
                continue;
            }
            $out[$tok] = true;

            if (mb_strlen($tok) >= 4 && mb_substr($tok, -1) === 's') {
                $out[mb_substr($tok, 0, -1)] = true;
            }

            if (mb_strlen($tok) >= 5 && mb_substr($tok, -2) === 'es') {
                $out[mb_substr($tok, 0, -2)] = true;
            }
        }
        return array_keys($out);
    }

    private function extractPrice(\Magento\Catalog\Api\Data\ProductInterface $product): ?array
    {
        try {
            $priceInfo = $product->getPriceInfo();
            $finalAmount = $priceInfo->getPrice('final_price')->getAmount()->getValue();
            $regularAmount = $priceInfo->getPrice('regular_price')->getAmount()->getValue();
            return [
                'regular'     => $this->priceHelper->currency((float) $regularAmount, true, false),
                'final'       => $this->priceHelper->currency((float) $finalAmount, true, false),
                'has_special' => (float) $finalAmount < (float) $regularAmount,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }
}
