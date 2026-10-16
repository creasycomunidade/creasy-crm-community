<?php

namespace Tests\Feature\Campaign;

use App\Models\Workspace;
use App\Modules\Broadcasting\Jobs\SendCampaignMessageJob;
use App\Modules\Broadcasting\Models\Campaign;
use App\Modules\Broadcasting\Models\CampaignRecipient;
use App\Modules\Broadcasting\Services\CampaignPersonalizer;
use App\Modules\Integrations\Models\IntegrationConfig;
use App\Modules\Shared\Contracts\ChannelDriverInterface;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Conversation;
use App\Modules\Shared\Models\Message;
use App\Modules\Shared\Services\ChannelManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OmnichannelCampaignSendTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_sends_a_personalized_telegram_campaign_through_the_selected_conversation(): void
    {
        FakeCampaignChannelDriver::$lastMessage = null;
        app(ChannelManager::class)->register('telegram', FakeCampaignChannelDriver::class);

        $workspace = Workspace::factory()->create();
        $account = ChannelAccount::create([
            'workspace_id' => $workspace->id,
            'channel' => 'telegram',
            'provider' => 'telegram',
            'display_name' => 'Bot da empresa',
            'status' => 'active',
        ]);
        $contact = Contact::factory()->create([
            'workspace_id' => $workspace->id,
            'first_name' => 'Ana',
        ]);
        Conversation::create([
            'workspace_id' => $workspace->id,
            'channel_account_id' => $account->id,
            'contact_id' => $contact->id,
            'external_thread_id' => '123456',
        ]);
        $campaign = Campaign::factory()->create([
            'workspace_id' => $workspace->id,
            'channel' => 'telegram',
            'channel_account_id' => $account->id,
            'status' => 'sending',
            'payload_json' => ['body' => 'Olá {{contact.first_name}}!'],
        ]);
        $recipient = CampaignRecipient::create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'status' => 'queued',
        ]);

        (new SendCampaignMessageJob($campaign->id, $contact->id))
            ->handle(app(CampaignPersonalizer::class));

        $this->assertSame('sent', $recipient->refresh()->status);
        $this->assertSame('provider-123', $recipient->provider_message_id);
        $this->assertSame('Olá Ana!', FakeCampaignChannelDriver::$lastMessage?->body);
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $contact->conversations()->firstOrFail()->id,
            'channel' => 'telegram',
            'direction' => 'out',
            'sent_by' => 'broadcast',
            'body' => 'Olá Ana!',
        ]);
    }

    #[Test]
    public function it_sends_a_whatsapp_campaign_through_the_selected_qr_account(): void
    {
        $workspace = Workspace::factory()->create();
        IntegrationConfig::create([
            'provider' => 'evolution_go',
            'label' => 'Evolution Go',
            'mode' => 'live',
            'enabled' => true,
            'credentials' => ['base_url' => 'https://1.1.1.1', 'global_api_key' => 'global-key'],
        ]);
        $selected = ChannelAccount::create([
            'workspace_id' => $workspace->id,
            'channel' => 'whatsapp',
            'provider' => 'evolution_go',
            'display_name' => 'Caixa escolhida',
            'credentials' => ['instance_token' => 'selected-token'],
            'status' => 'active',
        ]);
        ChannelAccount::create([
            'workspace_id' => $workspace->id,
            'channel' => 'whatsapp',
            'provider' => 'evolution_go',
            'display_name' => 'Outra caixa',
            'credentials' => ['instance_token' => 'other-token'],
            'status' => 'active',
        ]);
        $contact = Contact::factory()->create([
            'workspace_id' => $workspace->id,
            'first_name' => 'Ana',
            'phone_e164' => '+5547999999999',
            'opt_in_whatsapp' => true,
        ]);
        $campaign = Campaign::factory()->create([
            'workspace_id' => $workspace->id,
            'channel' => 'whatsapp',
            'channel_account_id' => $selected->id,
            'status' => 'sending',
            'payload_json' => ['body' => 'Olá {{contact.first_name}}!'],
        ]);
        $recipient = CampaignRecipient::create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact->id,
            'status' => 'queued',
        ]);

        Http::fake([
            'https://1.1.1.1/send/text' => Http::response(['data' => ['messageId' => 'QR-CAMPAIGN-1']]),
        ]);

        (new SendCampaignMessageJob($campaign->id, $contact->id))
            ->handle(app(CampaignPersonalizer::class));

        $this->assertSame('sent', $recipient->refresh()->status);
        $this->assertSame('evogo:'.$selected->id.':QR-CAMPAIGN-1', $recipient->provider_message_id);
        Http::assertSent(fn ($request) => $request->url() === 'https://1.1.1.1/send/text'
            && $request->hasHeader('apikey', 'selected-token')
            && $request['number'] === '5547999999999'
            && $request['text'] === 'Olá Ana!');
        $this->assertDatabaseHas('conversations', [
            'workspace_id' => $workspace->id,
            'contact_id' => $contact->id,
            'channel_account_id' => $selected->id,
        ]);
    }
}

class FakeCampaignChannelDriver implements ChannelDriverInterface
{
    public static ?Message $lastMessage = null;

    public function send(Message $message): string
    {
        self::$lastMessage = $message;

        return 'provider-123';
    }

    public function receiveWebhook(Request $request): array
    {
        return [];
    }

    public function verifyCreds(): bool
    {
        return true;
    }
}
