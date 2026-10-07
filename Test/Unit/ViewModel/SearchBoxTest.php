<?php
declare(strict_types=1);

namespace Panth\SearchAutocomplete\Test\Unit\ViewModel;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Panth\SearchAutocomplete\Helper\Config;
use Panth\SearchAutocomplete\Model\Security\RequestValidator;
use Panth\SearchAutocomplete\ViewModel\SearchBox;
use PHPUnit\Framework\TestCase;

class SearchBoxTest extends TestCase
{
    private function viewModel(array $settings = [], $q = null): SearchBox
    {
        $settings += [
            'isEnabled' => true,
            'getMinQueryLength' => 3,
            'getMaxQueryLength' => 40,
            'getDebounceMs' => 150,
            'showImage' => true,
            'showPrice' => false,
            'showCategories' => true,
            'showPages' => false,
            'showPopular' => true,
        ];
        $config = $this->createStub(Config::class);
        foreach ($settings as $method => $value) {
            $config->method($method)->willReturn($value);
        }

        $formKey = $this->createStub(FormKey::class);
        $formKey->method('getFormKey')->willReturn('fk123');

        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(
            static fn(string $route) => 'https://shop.example.com/' . $route . '/'
        );

        $escaper = $this->createStub(Escaper::class);
        $escaper->method('escapeHtml')->willReturnCallback(
            static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES)
        );

        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(static fn($key) => $key === 'q' ? $q : null);

        return new SearchBox($config, $formKey, $url, $escaper, $request);
    }

    public function testSimpleAccessorsDelegateToConfigAndUrlBuilder(): void
    {
        $vm = $this->viewModel();

        $this->assertTrue($vm->isEnabled());
        $this->assertSame('https://shop.example.com/searchautocomplete/ajax/suggest/', $vm->getEndpointUrl());
        $this->assertSame('https://shop.example.com/catalogsearch/result/', $vm->getViewAllSearchUrl());
        $this->assertSame('fk123', $vm->getFormKey());
        $this->assertSame(3, $vm->getMinQueryLength());
        $this->assertSame(40, $vm->getMaxQueryLength());
        $this->assertSame(150, $vm->getDebounceMs());
        $this->assertSame(RequestValidator::HONEYPOT_FIELD, $vm->getHoneypotName());
        $this->assertTrue($vm->showImage());
        $this->assertFalse($vm->showPrice());
        $this->assertTrue($vm->showCategories());
        $this->assertFalse($vm->showPages());
        $this->assertTrue($vm->showPopular());
    }

    public function testDisabledStateIsExposed(): void
    {
        $this->assertFalse($this->viewModel(['isEnabled' => false])->isEnabled());
    }

    public function testJsConfigCarriesAllSettings(): void
    {
        $config = json_decode($this->viewModel()->jsConfig(), true);

        $this->assertSame('https://shop.example.com/searchautocomplete/ajax/suggest/', $config['endpoint']);
        $this->assertSame('https://shop.example.com/catalogsearch/result/', $config['viewAllUrl']);
        $this->assertSame(3, $config['minLength']);
        $this->assertSame(40, $config['maxLength']);
        $this->assertSame(150, $config['debounceMs']);
        $this->assertSame('website', $config['honeypotName']);
        $this->assertTrue($config['showImage']);
        $this->assertFalse($config['showPrice']);
        $this->assertTrue($config['showCategories']);
        $this->assertFalse($config['showPages']);
        $this->assertTrue($config['showPopular']);
        $this->assertSame('Searching...', $config['i18n']['searching']);
        $this->assertSame('See all results for "%1"', $config['i18n']['viewAll']);
        $this->assertSame('Showing results for "%1"', $config['i18n']['didYouMean']);
        $this->assertCount(15, $config['i18n']);
    }

    public function testJsConfigIsSafeToEmbedInHtml(): void
    {
        $json = $this->viewModel()->jsConfig();

        $this->assertStringNotContainsString('"%1"', $json, 'double quotes inside strings are hex-escaped');
        $this->assertStringContainsString('for ' . chr(92) . 'u0022%1' . chr(92) . 'u0022', $json);
        $this->assertStringNotContainsString('<', $json);
        $this->assertStringNotContainsString("'", $json);
        $this->assertStringContainsString('https://shop.example.com/', $json, 'slashes are left unescaped');
    }

    public function testEscapeUsesTheEscaper(): void
    {
        $this->assertSame('&lt;b&gt;&quot;x&quot;', $this->viewModel()->escape('<b>"x"'));
    }

    public function testCurrentQueryIsEmptyWithoutASearchTerm(): void
    {
        $this->assertSame('', $this->viewModel()->getCurrentQuery());
        $this->assertSame('', $this->viewModel([], ['a', 'b'])->getCurrentQuery());
    }

    public function testCurrentQueryIsTrimmedCollapsedAndCapped(): void
    {
        $this->assertSame('blue backpack', $this->viewModel([], "  blue \t backpack ")->getCurrentQuery());
        $this->assertSame(str_repeat('a', 40), $this->viewModel([], str_repeat('a', 60))->getCurrentQuery());
        $this->assertSame("caf\u{00E9}", $this->viewModel(['getMaxQueryLength' => 4], "caf\u{00E9}s")->getCurrentQuery());
    }
}
