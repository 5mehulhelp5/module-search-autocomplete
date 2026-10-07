<?php
declare(strict_types=1);

namespace Panth\SearchAutocomplete\Model\Security;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Store\Model\StoreManagerInterface;
use Panth\SearchAutocomplete\Helper\Config;

class RequestValidator
{
    private const BOT_UA_NEEDLES = [
        'curl/',
        'wget/',
        'python-requests',
        'python-urllib',
        'go-http-client',
        'okhttp',
        'java/',
        'apache-httpclient',
        'libwww-perl',
        'scrapy',
        'phantomjs',
        'headlesschrome',
        'puppeteer',
        'playwright',
        'masscan',
        'nikto',
        'sqlmap',
        'nmap',
        'zgrab',
        'feroxbuster',
        'dirbuster',
        'gobuster',
    ];

    public const HONEYPOT_FIELD = 'website';

    private Config $config;
    private FormKeyValidator $formKeyValidator;
    private StoreManagerInterface $storeManager;

    public function __construct(
        Config $config,
        FormKeyValidator $formKeyValidator,
        StoreManagerInterface $storeManager
    ) {
        $this->config = $config;
        $this->formKeyValidator = $formKeyValidator;
        $this->storeManager = $storeManager;
    }

    public function validate(RequestInterface $request): ?string
    {
        $method = strtoupper((string) $request->getMethod());
        if ($method !== 'GET' && $method !== 'POST') {
            return null;
        }

        if ($method === 'POST') {
            try {
                $body = (string) $request->getContent();
                if (strlen($body) > $this->config->getMaxBodyBytes()) {
                    return null;
                }
            } catch (\Throwable $e) {
                return null;
            }
        }

        $ua = trim((string) $request->getHeader('User-Agent'));
        if ($this->config->blockEmptyUserAgent() && $ua === '') {
            return null;
        }
        if ($this->config->blockBotUserAgent() && $ua !== '' && $this->isBotUserAgent($ua)) {
            return null;
        }

        if ($this->config->requireAjaxHeader()) {
            $xrw = strtolower((string) $request->getHeader('X-Requested-With'));
            if ($xrw !== 'xmlhttprequest') {
                return null;
            }
        }

        if ($this->config->requireSameOrigin() && !$this->isSameOrigin($request)) {
            return null;
        }

        if ($this->config->isHoneypotEnabled()) {
            $honey = (string) $request->getParam(self::HONEYPOT_FIELD, '');
            if ($honey !== '') {
                return null;
            }
        }

        if ($this->config->requireFormKey() && !$this->formKeyValidator->validate($request)) {
            return null;
        }

        $raw = (string) $request->getParam('q', '');
        if (mb_strlen($raw) > $this->config->getMaxQueryLength() * 4) {
            return null;
        }
        $query = $this->sanitiseQuery($raw);
        if ($query === '') {
            return '';
        }
        $len = mb_strlen($query);
        if ($len < $this->config->getMinQueryLength() || $len > $this->config->getMaxQueryLength()) {
            return null;
        }

        return $query;
    }

    private function isSameOrigin(RequestInterface $request): bool
    {
        try {
            $base = parse_url((string) $this->storeManager->getStore()->getBaseUrl());
            $expectedHost = $base['host'] ?? '';
        } catch (\Throwable $e) {
            return true;
        }
        if ($expectedHost === '') {
            return true;
        }
        $origin = (string) $request->getHeader('Origin');
        $referer = (string) $request->getHeader('Referer');
        if ($origin === '' && $referer === '') {
            return true;
        }
        foreach ([$origin, $referer] as $headerVal) {
            if ($headerVal === '') {
                continue;
            }
            $parsed = parse_url($headerVal);
            $host = $parsed['host'] ?? '';
            if ($host !== '' && strcasecmp($host, $expectedHost) === 0) {
                return true;
            }
        }
        return false;
    }

    public function sanitiseQuery(string $raw): string
    {
        $clean = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $raw) ?? '';

        $clean = str_replace(['<', '>', '"', "'", '`'], ' ', $clean);

        $clean = preg_replace('/\s+/u', ' ', $clean) ?? '';
        return trim($clean);
    }

    private function isBotUserAgent(string $ua): bool
    {
        $low = strtolower($ua);
        foreach (self::BOT_UA_NEEDLES as $needle) {
            if (strpos($low, $needle) !== false) {
                return true;
            }
        }
        return false;
    }
}
