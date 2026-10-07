<?php
declare(strict_types=1);

namespace Panth\SearchAutocomplete\Controller\Ajax;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\UrlInterface;
use Magento\Search\Model\QueryFactory;
use Magento\Store\Model\StoreManagerInterface;
use Panth\SearchAutocomplete\Helper\Config;
use Panth\SearchAutocomplete\Model\Cache\Type as AutocompleteCache;
use Panth\SearchAutocomplete\Model\Security\RateLimiter;
use Panth\SearchAutocomplete\Model\Security\RequestValidator;
use Panth\SearchAutocomplete\Model\Suggestion\CategoryProvider;
use Panth\SearchAutocomplete\Model\Suggestion\CmsPageProvider;
use Panth\SearchAutocomplete\Model\Suggestion\PopularProvider;
use Panth\SearchAutocomplete\Model\Suggestion\ProductProvider;

class Suggest implements HttpGetActionInterface, HttpPostActionInterface, CsrfAwareActionInterface
{
    private RequestInterface $request;
    private JsonFactory $jsonFactory;
    private RequestValidator $validator;
    private RateLimiter $rateLimiter;
    private AutocompleteCache $cache;
    private Config $config;
    private StoreManagerInterface $storeManager;
    private CustomerSession $customerSession;
    private ProductProvider $productProvider;
    private CategoryProvider $categoryProvider;
    private CmsPageProvider $cmsPageProvider;
    private PopularProvider $popularProvider;
    private QueryFactory $queryFactory;
    private UrlInterface $url;

    public function __construct(
        RequestInterface $request,
        JsonFactory $jsonFactory,
        RequestValidator $validator,
        RateLimiter $rateLimiter,
        AutocompleteCache $cache,
        Config $config,
        StoreManagerInterface $storeManager,
        CustomerSession $customerSession,
        ProductProvider $productProvider,
        CategoryProvider $categoryProvider,
        CmsPageProvider $cmsPageProvider,
        PopularProvider $popularProvider,
        QueryFactory $queryFactory,
        UrlInterface $url
    ) {
        $this->request = $request;
        $this->jsonFactory = $jsonFactory;
        $this->validator = $validator;
        $this->rateLimiter = $rateLimiter;
        $this->cache = $cache;
        $this->config = $config;
        $this->storeManager = $storeManager;
        $this->customerSession = $customerSession;
        $this->productProvider = $productProvider;
        $this->categoryProvider = $categoryProvider;
        $this->cmsPageProvider = $cmsPageProvider;
        $this->popularProvider = $popularProvider;
        $this->queryFactory = $queryFactory;
        $this->url = $url;
    }

    public function execute(): ResultInterface
    {
        $startedAt = microtime(true);
        $result = $this->jsonFactory->create();

        $result->setHeader('Cache-Control', 'private, no-store, no-cache, must-revalidate', true);
        $result->setHeader('Pragma', 'no-cache', true);
        $result->setHeader('X-Content-Type-Options', 'nosniff', true);
        $result->setHeader('X-Robots-Tag', 'noindex, nofollow, nosnippet', true);
        $result->setHeader('Referrer-Policy', 'strict-origin-when-cross-origin', true);

        if (!$this->config->isEnabled()) {
            return $result->setData(['enabled' => false, 'products' => [], 'categories' => [], 'pages' => [], 'popular' => []]);
        }

        $query = $this->validator->validate($this->request);
        if ($query === null) {
            return $result->setHttpResponseCode(200)->setData([
                'rejected' => true,
                'products' => [], 'categories' => [], 'pages' => [], 'popular' => [],
            ]);
        }

        $store = $this->storeManager->getStore();
        $storeId = (int) $store->getId();
        $groupId = (int) $this->customerSession->getCustomerGroupId();

        if (!$this->rateLimiter->allow($this->request, $storeId)) {
            return $result->setHttpResponseCode(429)->setData([
                'throttled' => true,
                'products' => [], 'categories' => [], 'pages' => [], 'popular' => [],
            ]);
        }

        if ($query === '') {
            return $result->setData([
                'enabled'    => true,
                'query'      => '',
                'products'   => [],
                'categories' => [],
                'pages'      => [],
                'popular'    => $this->popularProvider->search(''),
            ]);
        }

        $cacheKey = $this->makeCacheKey($query, $storeId, $groupId);

        $cached = $this->config->isCacheEnabled() ? $this->cache->load($cacheKey) : false;
        if ($cached !== false && $cached !== null && $cached !== '') {
            $payload = json_decode((string) $cached, true);
            if (is_array($payload)) {
                $payload['query'] = $query;
                $payload['did_you_mean'] = (string) ($payload['did_you_mean'] ?? '');
                $viewAllQuery = $payload['did_you_mean'] !== '' ? $payload['did_you_mean'] : $query;
                $payload['view_all'] = $this->url->getUrl(
                    'catalogsearch/result',
                    ['_query' => ['q' => $viewAllQuery]]
                );
                $payload['cache'] = 'hit';
                $payload['took_ms'] = (int) ((microtime(true) - $startedAt) * 1000);
                return $result->setData($payload);
            }
        }

        $products   = $this->productProvider->search($query);
        $didYouMean = $products ? $this->productProvider->getCorrection() : '';
        $categories = $this->categoryProvider->search($query);
        $pages      = $this->cmsPageProvider->search($query);
        $popular    = $this->popularProvider->search($query);

        if ($this->config->recordKeystrokePopularity()) {
            try {
                $magentoQuery = $this->queryFactory->get();
                $magentoQuery->setStoreId($storeId);
                $magentoQuery->setQueryText($query);
                $magentoQuery->setData('num_results', count($products));
                $magentoQuery->saveIncrementalPopularity();
            } catch (\Throwable $e) {
            }
        }

        $payload = [
            'enabled'    => true,
            'query'      => $query,
            'products'   => $products,
            'categories' => $categories,
            'pages'      => $pages,
            'popular'    => $popular,
            'did_you_mean' => $didYouMean,
            'view_all'   => $this->url->getUrl(
                'catalogsearch/result',
                ['_query' => ['q' => $didYouMean !== '' ? $didYouMean : $query]]
            ),
            'cache'      => 'miss',
        ];

        if ($this->config->isCacheEnabled()) {
            $this->cache->save(
                json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                $cacheKey,
                [
                    AutocompleteCache::CACHE_TAG,
                    \Magento\Catalog\Model\Category::CACHE_TAG,
                    \Magento\Catalog\Model\Product::CACHE_TAG,
                    \Magento\Cms\Model\Page::CACHE_TAG,
                ],
                $this->config->getCacheTtl()
            );
        }

        $payload['took_ms'] = (int) ((microtime(true) - $startedAt) * 1000);
        return $result->setData($payload);
    }

    private function makeCacheKey(string $query, int $storeId, int $groupId): string
    {
        $normalised = mb_strtolower($query);
        return 'panth_sac_' . sha1($storeId . '|' . $groupId . '|' . $normalised);
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
