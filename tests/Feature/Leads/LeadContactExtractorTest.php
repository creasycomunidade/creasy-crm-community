<?php

namespace Tests\Feature\Leads;

use App\Modules\Leads\Models\Lead;
use App\Modules\Leads\Services\LeadContactExtractor;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LeadContactExtractorTest extends TestCase
{
    public function test_prioritizes_explicit_whatsapp_preserving_landlines_emails_and_social_links(): void
    {
        $contacts = (new LeadContactExtractor)->extract('Telefone (48) 3333-4444 WhatsApp: (48) 99999-1234
            <a href="https://api.whatsapp.com/send?phone=5548999995678&amp;text=Oi">WhatsApp</a>
            <a href="https://wa.me/5548999991234">Converse</a>
            <a href="mailto:Vendas%40empresa.com.br">Email</a> suporte@empresa.com.br
            <a href="https://instagram.com/empresa">Instagram</a>');
        $this->assertSame(['+5548999995678', '+5548999991234'], $contacts['whatsapp']);
        $this->assertContains('+554833334444', $contacts['phones']);
        $this->assertSame(['vendas@empresa.com.br', 'suporte@empresa.com.br'], $contacts['emails']);
        $this->assertContains('https://instagram.com/empresa', $contacts['links']);
    }

    public function test_does_not_assume_a_mobile_number_is_whatsapp_or_mangle_area_code_55(): void
    {
        $extractor = new LeadContactExtractor;
        $contacts = $extractor->extract('Telefone (55) 99999-1234');
        $this->assertSame([], $contacts['whatsapp']);
        $this->assertSame(['+5555999991234'], $contacts['phones']);
        $this->assertNull($extractor->phone('3333-4444'));
        $this->assertSame('+14155552671', $extractor->phone('14155552671', true));
    }

    public function test_website_follows_contact_page_without_leaking_credentials(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://1.1.1.1' => Http::response('<a href="/contato">Fale conosco</a>'),
            'https://1.1.1.1/contato' => Http::response('<footer><a href="https://wa.me/5548999991234">WhatsApp</a><a href="mailto:contato@empresa.com.br">Email</a></footer>'),
        ]);
        $contacts = (new LeadContactExtractor)->website('https://1.1.1.1', microtime(true) + 10);
        $this->assertSame(['+5548999991234'], $contacts['whatsapp']);
        $this->assertSame(['contato@empresa.com.br'], $contacts['emails']);
        Http::assertSentCount(2);
        Http::assertNotSent(fn ($request) => $request->hasHeader('Authorization'));
    }

    public function test_private_redirects_and_expired_budgets_are_not_fetched(): void
    {
        Http::preventStrayRequests();
        Http::fake(['https://1.1.1.1' => Http::response('', 302, ['Location' => 'http://127.0.0.1/secrets'])]);
        $extractor = new LeadContactExtractor;
        $this->assertSame([], $extractor->website('https://1.1.1.1', microtime(true) + 10));
        $this->assertSame([], $extractor->website('http://169.254.169.254', microtime(true) + 10));
        $this->assertSame([], $extractor->website('https://1.1.1.1', microtime(true) - 1));
        Http::assertSentCount(1);
    }

    public function test_rescrape_preserves_existing_contacts_and_prioritizes_new_whatsapp(): void
    {
        $lead = new Lead(['phone' => '+554833334444', 'email' => 'antigo@empresa.com.br',
            'contact_channels' => ['phones' => ['+554833334444'], 'emails' => ['antigo@empresa.com.br']]]);
        $extractor = new LeadContactExtractor;
        $attributes = $extractor->attributes($lead, $extractor->extract('https://wa.me/5548999991234 vendas@empresa.com.br'));
        $this->assertSame('+5548999991234', $attributes['phone']);
        $this->assertSame('vendas@empresa.com.br', $attributes['email']);
        $this->assertContains('+554833334444', $attributes['contact_channels']['phones']);
        $this->assertContains('antigo@empresa.com.br', $attributes['contact_channels']['emails']);
    }
}
