<?php
declare(strict_types=1);

namespace Panth\SearchAutocomplete\Test\Unit\Fixture;

use Magento\Search\Model\Query;

/**
 * Search query double that records what the controller writes to it.
 */
class QueryDouble extends Query
{
    public array $recorded = [];

    public bool $saved = false;

    public bool $failOnSave = false;

    // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock
    public function __construct()
    {
    }

    public function setStoreId($storeId)
    {
        $this->recorded['store_id'] = $storeId;
        return $this;
    }

    public function setQueryText($text)
    {
        $this->recorded['query_text'] = $text;
        return $this;
    }

    public function setData($key, $value = null)
    {
        $this->recorded[$key] = $value;
        return $this;
    }

    public function saveIncrementalPopularity()
    {
        if ($this->failOnSave) {
            throw new \RuntimeException('db down');
        }
        $this->saved = true;
        return $this;
    }
}
