<?php
declare(strict_types=1);

namespace Panth\SearchAutocomplete\Model\Cache;

use Magento\Framework\App\Cache\Type\FrontendPool;
use Magento\Framework\Cache\Frontend\Decorator\TagScope;

class Type extends TagScope
{
    public const TYPE_IDENTIFIER = 'panth_search_autocomplete';
    public const CACHE_TAG = 'PANTH_SEARCH_AUTOCOMPLETE';

    public function __construct(FrontendPool $cacheFrontendPool)
    {
        parent::__construct($cacheFrontendPool->get(self::TYPE_IDENTIFIER), self::CACHE_TAG);
    }
}
