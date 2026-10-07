<?php
declare(strict_types=1);

namespace Panth\SearchAutocomplete\Test\Unit\Controller\Adminhtml\Docs;

use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\View\Page\Config as PageConfig;
use Magento\Framework\View\Page\Title;
use Magento\Framework\View\Result\PageFactory;
use Panth\SearchAutocomplete\Controller\Adminhtml\Docs\Index;
use PHPUnit\Framework\TestCase;

class IndexTest extends TestCase
{
    public function testAclResourceIsTheDocsResource(): void
    {
        $this->assertSame('Panth_SearchAutocomplete::docs', Index::ADMIN_RESOURCE);
    }

    public function testRendersTheDocsPageWithMenuAndTitle(): void
    {
        $prepended = [];
        $title = $this->createStub(Title::class);
        $title->method('prepend')->willReturnCallback(function ($value) use (&$prepended) {
            $prepended[] = (string) $value;
        });
        $pageConfig = $this->createStub(PageConfig::class);
        $pageConfig->method('getTitle')->willReturn($title);

        $menus = [];
        $page = $this->createStub(Page::class);
        $page->method('getConfig')->willReturn($pageConfig);
        $page->method('setActiveMenu')->willReturnCallback(function ($id) use (&$menus, $page) {
            $menus[] = $id;
            return $page;
        });

        $factory = $this->createStub(PageFactory::class);
        $factory->method('create')->willReturn($page);

        $controller = new Index($this->createStub(Context::class), $factory);

        $this->assertSame($page, $controller->execute());
        $this->assertSame(['Panth_SearchAutocomplete::docs'], $menus);
        $this->assertSame(['Search Autocomplete - Documentation'], $prepended);
    }
}
