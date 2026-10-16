<?php

namespace Tests\Unit;

use App\Support\SafeHtml;
use PHPUnit\Framework\TestCase;

class SafeHtmlTest extends TestCase
{
    public function test_removes_scripts_event_handlers_and_unsafe_urls(): void
    {
        $html = <<<'HTML'
<p onclick="alert(1)">Hello <strong>world</strong></p>
<script>alert(document.cookie)</script>
<a href="javascript:alert(1)">bad</a>
<img src="https://example.com/image.png" onerror="alert(1)">
HTML;

        $safe = SafeHtml::sanitize($html);

        $this->assertStringContainsString('<strong>world</strong>', $safe);
        $this->assertStringContainsString('src="https://example.com/image.png"', $safe);
        $this->assertStringNotContainsStringIgnoringCase('<script', $safe);
        $this->assertStringNotContainsStringIgnoringCase('onclick', $safe);
        $this->assertStringNotContainsStringIgnoringCase('onerror', $safe);
        $this->assertStringNotContainsStringIgnoringCase('javascript:', $safe);
    }

    public function test_hardens_links_opened_in_a_new_tab(): void
    {
        $safe = SafeHtml::sanitize('<a href="https://example.com" target="_blank">Example</a>');

        $this->assertStringContainsString('rel="noopener noreferrer"', $safe);
    }

    public function test_preserves_utf8_content(): void
    {
        $safe = SafeHtml::sanitize('<p>Olá, informação e ação.</p>');

        $this->assertStringContainsString('Olá, informação e ação.', $safe);
    }
}
