<?php
declare(strict_types=1);

namespace Panth\SearchAutocomplete\Test\Unit\View;

use PHPUnit\Framework\TestCase;

class AutocompleteScriptTest extends TestCase
{
    private const SCRIPT = 'view/frontend/web/js/autocomplete.js';

    private string $js = '';

    protected function setUp(): void
    {
        $path = dirname(__DIR__, 3) . '/' . self::SCRIPT;
        $this->assertTrue(is_file($path), self::SCRIPT . ' is missing');
        $this->js = (string) file_get_contents($path);
    }

    private function submitHandler(): string
    {
        $start = strpos($this->js, "form.addEventListener('submit'");
        $this->assertNotFalse($start, 'Submit handler not found');
        $end = strpos($this->js, "window.addEventListener('pageshow'", (int) $start);
        $this->assertNotFalse($end, 'Pageshow handler not found after the submit handler');

        return substr($this->js, (int) $start, (int) $end - (int) $start);
    }

    public function testSubmitDisablesEveryFieldExceptTheQuery(): void
    {
        $handler = $this->submitHandler();
        $this->assertStringContainsString("form.querySelectorAll('input')", $handler);
        $this->assertStringContainsString('if (field !== input)', $handler);
        $this->assertStringContainsString('field.disabled = true;', $handler);
    }

    public function testSubmitStillAppliesTheCorrectedTermAndSavesTheRecentSearch(): void
    {
        $handler = $this->submitHandler();
        $this->assertStringContainsString('input.value = state.didYouMean;', $handler);
        $this->assertStringContainsString('saveRecent(input.value);', $handler);
        $this->assertLessThan(
            strpos($handler, 'field.disabled = true;'),
            strpos($handler, 'saveRecent(input.value);')
        );
    }

    public function testFieldsAreEnabledAgainWhenThePageIsShownFromHistory(): void
    {
        $start = strpos($this->js, "window.addEventListener('pageshow'");
        $this->assertNotFalse($start);
        $body = substr($this->js, (int) $start, 260);
        $this->assertStringContainsString('field.disabled = false;', $body);
    }

    public function testSuggestionRequestsStillSendTheFormKeyAndSpamTrap(): void
    {
        $this->assertStringContainsString("url.searchParams.set('form_key'", $this->js);
        $this->assertStringContainsString('url.searchParams.set(cfg.honeypotName', $this->js);
        $this->assertStringContainsString("root.querySelector('input[name=\"form_key\"]')", $this->js);
    }

    public function testScriptStaysAscii(): void
    {
        $this->assertSame(1, preg_match('/^[\x09\x0A\x0D\x20-\x7E]*$/', $this->js));
    }

    public function testLumaHeaderSearchFormDisablesTheInjectedFieldsOnSubmit(): void
    {
        $path = dirname(__DIR__, 3) . '/view/frontend/web/js/attach-to-themecustomizer.js';
        $this->assertTrue(is_file($path));
        $js = (string) file_get_contents($path);
        $start = strpos($js, "if (form) form.addEventListener('submit'");
        $this->assertNotFalse($start);
        $end = strpos($js, "if (form) window.addEventListener('pageshow'", (int) $start);
        $this->assertNotFalse($end);
        $handler = substr($js, (int) $start, (int) $end - (int) $start);
        $this->assertStringContainsString(
            "form.querySelectorAll('input[name=\"form_key\"], input[name=\"' + cfg.honeypotName + '\"]')",
            $handler
        );
        $this->assertStringContainsString('saveRecent(input.value);', $handler);
        $this->assertStringContainsString('field.disabled = true;', $handler);
        $this->assertStringContainsString('field.disabled = false;', substr($js, (int) $end, 400));
        $this->assertStringContainsString("fk.name = 'form_key';", $js);
    }
}
