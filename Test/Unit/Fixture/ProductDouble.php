<?php
declare(strict_types=1);

namespace Panth\SearchAutocomplete\Test\Unit\Fixture;

use Magento\Catalog\Model\Product;

/**
 * Product double that skips the heavy model constructor and returns fixed values.
 */
class ProductDouble extends Product
{
    private array $values;

    private $priceInfo;

    // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock
    public function __construct(array $values = [], $priceInfo = null)
    {
        $this->values = $values;
        $this->priceInfo = $priceInfo;
    }

    public function getId()
    {
        return $this->values['id'] ?? null;
    }

    public function getName()
    {
        return $this->values['name'] ?? null;
    }

    public function getSku()
    {
        return $this->values['sku'] ?? null;
    }

    public function getProductUrl($useSid = null)
    {
        return $this->values['url'] ?? '';
    }

    public function getSmallImage()
    {
        return $this->values['small_image'] ?? null;
    }

    public function getPriceInfo()
    {
        if ($this->priceInfo === null) {
            throw new \RuntimeException('no price info');
        }
        return $this->priceInfo;
    }
}
