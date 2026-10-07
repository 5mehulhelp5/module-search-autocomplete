<?php
declare(strict_types=1);

namespace Panth\SearchAutocomplete\Controller\Adminhtml\Docs;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    public const ADMIN_RESOURCE = 'Panth_SearchAutocomplete::docs';

    private PageFactory $resultPageFactory;

    public function __construct(Context $context, PageFactory $resultPageFactory)
    {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    public function execute(): Page
    {
        $page = $this->resultPageFactory->create();
        $page->setActiveMenu('Panth_SearchAutocomplete::docs');
        $page->getConfig()->getTitle()->prepend(__('Search Autocomplete - Documentation'));
        return $page;
    }
}
