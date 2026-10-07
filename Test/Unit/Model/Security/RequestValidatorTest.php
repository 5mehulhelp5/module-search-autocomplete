<?php
declare(strict_types=1);

namespace Panth\SearchAutocomplete\Test\Unit\Model\Security;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Panth\SearchAutocomplete\Helper\Config;
use Panth\SearchAutocomplete\Model\Security\RequestValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RequestValidatorTest extends TestCase
{
    private const BROWSER_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120 Safari/537.36';

    private function validator(
        array $configOverrides = [],
        bool $formKeyValid = true,
        string $baseUrl = 'https://shop.example.com/',
        bool $storeThrows = false
    ): RequestValidator {
        $settings = $configOverrides + [
            'getMaxBodyBytes' => 4096,
            'blockEmptyUserAgent' => true,
            'blockBotUserAgent' => true,
            'requireAjaxHeader' => true,
            'requireSameOrigin' => true,
            'isHoneypotEnabled' => true,
            'requireFormKey' => true,
            'getMaxQueryLength' => 64,
            'getMinQueryLength' => 2,
        ];
        $config = $this->createStub(Config::class);
        foreach ($settings as $method => $value) {
            $config->method($method)->willReturn($value);
        }

        $formKey = $this->createStub(FormKeyValidator::class);
        $formKey->method('validate')->willReturn($formKeyValid);

        $storeManager = $this->createStub(StoreManagerInterface::class);
        if ($storeThrows) {
            $storeManager->method('getStore')->willThrowException(new \RuntimeException('no store'));
        } else {
            $store = $this->createStub(Store::class);
            $store->method('getBaseUrl')->willReturn($baseUrl);
            $storeManager->method('getStore')->willReturn($store);
        }

        return new RequestValidator($config, $formKey, $storeManager);
    }

    private function request(array $overrides = []): HttpRequest
    {
        $spec = $overrides + [
            'method' => 'GET',
            'content' => '',
            'headers' => [],
            'params' => [],
        ];
        $headers = $spec['headers'] + [
            'User-Agent' => self::BROWSER_UA,
            'X-Requested-With' => 'XMLHttpRequest',
            'Origin' => 'https://shop.example.com',
            'Referer' => '',
        ];
        $params = $spec['params'] + ['q' => 'shirt'];

        $request = $this->createStub(HttpRequest::class);
        $request->method('getMethod')->willReturn($spec['method']);
        if ($spec['content'] instanceof \Throwable) {
            $request->method('getContent')->willThrowException($spec['content']);
        } else {
            $request->method('getContent')->willReturn($spec['content']);
        }
        $request->method('getHeader')->willReturnCallback(
            static fn($name, $default = false) => $headers[$name] ?? $default
        );
        $request->method('getParam')->willReturnCallback(
            static fn($name, $default = null) => $params[$name] ?? $default
        );
        return $request;
    }

    public function testValidBrowserRequestReturnsTheQuery(): void
    {
        $this->assertSame('shirt', $this->validator()->validate($this->request()));
    }

    public function testLowercasePostIsAcceptedWhenBodyIsSmall(): void
    {
        $request = $this->request(['method' => 'post', 'content' => 'q=shirt']);
        $this->assertSame('shirt', $this->validator()->validate($request));
    }

    public static function rejectedMethodProvider(): array
    {
        return [['PUT'], ['DELETE'], ['HEAD'], ['OPTIONS'], ['']];
    }

    #[DataProvider('rejectedMethodProvider')]
    public function testOtherHttpMethodsAreRejected(string $method): void
    {
        $this->assertNull($this->validator()->validate($this->request(['method' => $method])));
    }

    public function testOversizedPostBodyIsRejected(): void
    {
        $request = $this->request(['method' => 'POST', 'content' => str_repeat('a', 1025)]);
        $this->assertNull($this->validator(['getMaxBodyBytes' => 1024])->validate($request));
    }

    public function testPostBodyExactlyAtLimitIsAccepted(): void
    {
        $request = $this->request(['method' => 'POST', 'content' => str_repeat('a', 1024)]);
        $this->assertSame('shirt', $this->validator(['getMaxBodyBytes' => 1024])->validate($request));
    }

    public function testGetIgnoresTheBodySizeLimit(): void
    {
        $request = $this->request(['method' => 'GET', 'content' => str_repeat('a', 5000)]);
        $this->assertSame('shirt', $this->validator(['getMaxBodyBytes' => 1024])->validate($request));
    }

    public function testUnreadablePostBodyIsRejected(): void
    {
        $request = $this->request(['method' => 'POST', 'content' => new \RuntimeException('stream')]);
        $this->assertNull($this->validator()->validate($request));
    }

    public function testBlankUserAgentIsRejectedWhenBlockingIsOn(): void
    {
        $request = $this->request(['headers' => ['User-Agent' => '   ']]);
        $this->assertNull($this->validator()->validate($request));
    }

    public function testEmptyUserAgentIsAllowedWhenBlockingIsOff(): void
    {
        $request = $this->request(['headers' => ['User-Agent' => '']]);
        $this->assertSame('shirt', $this->validator(['blockEmptyUserAgent' => false])->validate($request));
    }

    public static function botUserAgentProvider(): array
    {
        return [
            'curl'     => ['curl/8.1.2'],
            'wget'     => ['Wget/1.21'],
            'python'   => ['python-requests/2.31'],
            'go'       => ['Go-http-client/2.0'],
            'headless' => ['Mozilla/5.0 HeadlessChrome/120.0'],
            'scanner'  => ['sqlmap/1.7'],
            'java'     => ['Java/17.0.1'],
        ];
    }

    #[DataProvider('botUserAgentProvider')]
    public function testKnownBotUserAgentsAreRejected(string $ua): void
    {
        $request = $this->request(['headers' => ['User-Agent' => $ua]]);
        $this->assertNull($this->validator()->validate($request));
    }

    public function testBotUserAgentIsAllowedWhenBotBlockingIsOff(): void
    {
        $request = $this->request(['headers' => ['User-Agent' => 'curl/8.1.2']]);
        $this->assertSame('shirt', $this->validator(['blockBotUserAgent' => false])->validate($request));
    }

    public function testMissingAjaxHeaderIsRejected(): void
    {
        $request = $this->request(['headers' => ['X-Requested-With' => '']]);
        $this->assertNull($this->validator()->validate($request));
    }

    public function testAjaxHeaderIsCaseInsensitive(): void
    {
        $request = $this->request(['headers' => ['X-Requested-With' => 'xmlHTTPrequest']]);
        $this->assertSame('shirt', $this->validator()->validate($request));
    }

    public function testAjaxHeaderNotRequiredWhenDisabled(): void
    {
        $request = $this->request(['headers' => ['X-Requested-With' => '']]);
        $this->assertSame('shirt', $this->validator(['requireAjaxHeader' => false])->validate($request));
    }

    public function testForeignOriginIsRejected(): void
    {
        $request = $this->request(['headers' => ['Origin' => 'https://evil.example.net']]);
        $this->assertNull($this->validator()->validate($request));
    }

    public function testOriginHostComparisonIgnoresCase(): void
    {
        $request = $this->request(['headers' => ['Origin' => 'https://SHOP.Example.COM']]);
        $this->assertSame('shirt', $this->validator()->validate($request));
    }

    public function testMatchingRefererIsEnoughWhenOriginIsForeign(): void
    {
        $request = $this->request([
            'headers' => ['Origin' => 'https://evil.example.net', 'Referer' => 'https://shop.example.com/p.html'],
        ]);
        $this->assertSame('shirt', $this->validator()->validate($request));
    }

    public function testSameHostRefererAloneIsAccepted(): void
    {
        $request = $this->request([
            'headers' => ['Origin' => '', 'Referer' => 'https://shop.example.com/women.html'],
        ]);
        $this->assertSame('shirt', $this->validator()->validate($request));
    }

    public function testForeignRefererAloneIsRejected(): void
    {
        $request = $this->request([
            'headers' => ['Origin' => '', 'Referer' => 'https://other.example.org/'],
        ]);
        $this->assertNull($this->validator()->validate($request));
    }

    public function testMissingOriginAndRefererIsAllowed(): void
    {
        $request = $this->request(['headers' => ['Origin' => '', 'Referer' => '']]);
        $this->assertSame('shirt', $this->validator()->validate($request));
    }

    public function testOriginWithoutHostIsRejected(): void
    {
        $request = $this->request(['headers' => ['Origin' => 'not a url']]);
        $this->assertNull($this->validator()->validate($request));
    }

    public function testSameOriginCheckPassesWhenStoreCannotBeResolved(): void
    {
        $request = $this->request(['headers' => ['Origin' => 'https://evil.example.net']]);
        $this->assertSame('shirt', $this->validator([], true, '', true)->validate($request));
    }

    public function testSameOriginCheckPassesWhenBaseUrlHasNoHost(): void
    {
        $request = $this->request(['headers' => ['Origin' => 'https://evil.example.net']]);
        $this->assertSame('shirt', $this->validator([], true, '/relative/')->validate($request));
    }

    public function testForeignOriginAllowedWhenSameOriginIsDisabled(): void
    {
        $request = $this->request(['headers' => ['Origin' => 'https://evil.example.net']]);
        $this->assertSame('shirt', $this->validator(['requireSameOrigin' => false])->validate($request));
    }

    public function testFilledHoneypotIsRejected(): void
    {
        $request = $this->request(['params' => [RequestValidator::HONEYPOT_FIELD => 'http://spam']]);
        $this->assertNull($this->validator()->validate($request));
    }

    public function testFilledHoneypotIgnoredWhenDisabled(): void
    {
        $request = $this->request(['params' => [RequestValidator::HONEYPOT_FIELD => 'x']]);
        $this->assertSame('shirt', $this->validator(['isHoneypotEnabled' => false])->validate($request));
    }

    public function testInvalidFormKeyIsRejected(): void
    {
        $this->assertNull($this->validator([], false)->validate($this->request()));
    }

    public function testInvalidFormKeyIgnoredWhenNotRequired(): void
    {
        $this->assertSame('shirt', $this->validator(['requireFormKey' => false], false)->validate($this->request()));
    }

    public function testEmptyQueryReturnsEmptyString(): void
    {
        $request = $this->request(['params' => ['q' => '']]);
        $this->assertSame('', $this->validator()->validate($request));
    }

    public function testQueryThatSanitisesToNothingReturnsEmptyString(): void
    {
        $request = $this->request(['params' => ['q' => " <> \"' \t"]]);
        $this->assertSame('', $this->validator()->validate($request));
    }

    public function testTooShortQueryIsRejected(): void
    {
        $request = $this->request(['params' => ['q' => 'a']]);
        $this->assertNull($this->validator()->validate($request));
    }

    public function testTooLongQueryIsRejected(): void
    {
        $request = $this->request(['params' => ['q' => str_repeat('b', 9)]]);
        $this->assertNull($this->validator(['getMaxQueryLength' => 8])->validate($request));
    }

    public function testQueryAtMaxLengthIsAccepted(): void
    {
        $request = $this->request(['params' => ['q' => str_repeat('b', 8)]]);
        $this->assertSame('bbbbbbbb', $this->validator(['getMaxQueryLength' => 8])->validate($request));
    }

    public function testRawQueryFarBeyondLimitIsRejectedBeforeSanitising(): void
    {
        $request = $this->request(['params' => ['q' => str_repeat(' ', 33) . 'ab']]);
        $this->assertNull($this->validator(['getMaxQueryLength' => 8])->validate($request));
    }

    public function testLengthIsMeasuredInCharactersNotBytes(): void
    {
        $twoChars = "\u{00E9}\u{00E8}";
        $request = $this->request(['params' => ['q' => $twoChars]]);
        $this->assertSame($twoChars, $this->validator(['getMaxQueryLength' => 8])->validate($request));
    }

    public function testSanitisedQueryIsReturned(): void
    {
        $request = $this->request(['params' => ['q' => "  red\t<b>shirt</b>  "]]);
        $this->assertSame('red b shirt /b', $this->validator()->validate($request));
    }

    public static function sanitiseProvider(): array
    {
        return [
            'plain'           => ['blue jeans', 'blue jeans'],
            'trim'            => ["  bag \n", 'bag'],
            'control chars'   => ["a\x00b\x1Fc\x7Fd", 'a b c d'],
            'html chars'      => ['<i>x</i>', 'i x /i'],
            'quotes and tick' => ["it's \"big\" `x`", 'it s big x'],
            'collapse spaces' => ["a    b\t\tc", 'a b c'],
            'unicode kept'    => ["caf\u{00E9} cr\u{00E8}me", "caf\u{00E9} cr\u{00E8}me"],
            'only junk'       => ['<>"\'`', ''],
        ];
    }

    #[DataProvider('sanitiseProvider')]
    public function testSanitiseQuery(string $raw, string $expected): void
    {
        $this->assertSame($expected, $this->validator()->sanitiseQuery($raw));
    }
}
