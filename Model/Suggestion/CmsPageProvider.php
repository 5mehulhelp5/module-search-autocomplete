<?php
declare(strict_types=1);

namespace Panth\SearchAutocomplete\Model\Suggestion;

use Magento\Cms\Model\ResourceModel\Page\CollectionFactory;
use Magento\Cms\Model\Page as CmsPage;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\SearchAutocomplete\Helper\Config;
use Psr\Log\LoggerInterface;

class CmsPageProvider
{
    private CollectionFactory $collectionFactory;
    private StoreManagerInterface $storeManager;
    private UrlInterface $urlBuilder;
    private Config $config;
    private LoggerInterface $logger;

    public function __construct(
        CollectionFactory $collectionFactory,
        StoreManagerInterface $storeManager,
        UrlInterface $urlBuilder,
        Config $config,
        LoggerInterface $logger
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->storeManager = $storeManager;
        $this->urlBuilder = $urlBuilder;
        $this->config = $config;
        $this->logger = $logger;
    }

    public function search(string $query): array
    {
        $limit = $this->config->getPagesLimit();
        if ($limit <= 0 || $query === '' || !$this->config->showPages()) {
            return [];
        }
        try {
            $storeId = (int) $this->storeManager->getStore()->getId();
            $like = '%' . $this->escapeLike($query) . '%';

            $collection = $this->collectionFactory->create();

            $collection->addStoreFilter($storeId);

            $collection->addFieldToFilter('is_active', ['eq' => CmsPage::STATUS_ENABLED]);

            $collection->addFieldToFilter('identifier', ['neq' => '']);

            $collection->addFieldToFilter('identifier', ['nin' => ['no-route', 'enable-cookies', 'home', 'privacy-policy-cookie-restriction-mode']]);

            $collection->addFieldToFilter(
                ['title', 'meta_keywords', 'meta_description', 'content_heading', 'content', 'identifier'],
                [
                    ['like' => $like],
                    ['like' => $like],
                    ['like' => $like],
                    ['like' => $like],
                    ['like' => $like],
                    ['like' => $like],
                ]
            );
            $collection->setPageSize($limit);
            $collection->setOrder('title', 'ASC');

            $rows = [];
            foreach ($collection as $page) {
                $identifier = (string) $page->getIdentifier();
                if ($identifier === '') {
                    continue;
                }
                $url = $this->urlBuilder->getUrl(null, ['_direct' => $identifier]);
                $rows[] = [
                    'id'    => (int) $page->getId(),
                    'title' => html_entity_decode((string) $page->getTitle(), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    'url'   => $url,
                ];
            }
            return $rows;
        } catch (\Throwable $e) {
            $this->logger->warning('[PanthSearchAutocomplete] cms page search failed: ' . $e->getMessage());
            return [];
        }
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
