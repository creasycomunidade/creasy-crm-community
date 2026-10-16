<?php

namespace App\Console\Commands;

use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Shared\Models\Contact;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Diagnostic: calls Meta's Messenger User Profile API with the stored page token
 * for a connected Messenger account and prints the raw response. Use this to tell
 * apart a token problem (credentials unreadable / missing) from a permission problem
 * (token works but Meta returns an empty/error profile because the app lacks
 * pages_messaging Advanced Access or isn't Live).
 *
 *   php artisan messenger:test-profile                 # auto-pick account + latest PSID
 *   php artisan messenger:test-profile --account=12    # specific channel account id
 *   php artisan messenger:test-profile --psid=1234567  # specific sender PSID
 */
class MessengerProfileTestCommand extends Command
{
    protected $signature = 'messenger:test-profile
                            {--account= : ChannelAccount id (defaults to the first active messenger account)}
                            {--psid= : Sender PSID to look up (defaults to the latest messenger contact)}';

    protected $description = 'Test the Messenger User Profile API (name/picture) for a connected page';

    public function handle(): int
    {
        $account = $this->option('account')
            ? ChannelAccount::where('channel', 'messenger')->find($this->option('account'))
            : ChannelAccount::where('channel', 'messenger')->orderByDesc('id')->first();

        if (! $account) {
            $this->error('No Messenger channel account found.');

            return self::FAILURE;
        }

        $this->line('  <fg=cyan>ChannelAccount:</> '.$account->id.'  ('.$account->display_name.')');
        $this->line('  <fg=cyan>Workspace:</> '.$account->workspace_id);

        // Read credentials through the model so the encrypted:array cast runs. If the
        // token was ever written via a query-builder update(), this throws/returns
        // empty — which itself is the diagnosis.
        try {
            $token = $account->credentials['page_access_token'] ?? '';
        } catch (\Throwable $e) {
            $this->error('credentials could not be decrypted: '.$e->getMessage());
            $this->warn('→ The page token is corrupted (likely written without the cast). Reconnect Messenger.');

            return self::FAILURE;
        }

        if ($token === '') {
            $this->error('No page_access_token stored on this account. Reconnect Messenger.');

            return self::FAILURE;
        }

        $this->line('  <fg=cyan>Token:</> '.substr($token, 0, 12).'… ('.strlen($token).' chars)');
        $this->info('');

        // --- Probe what KIND of token this is. A page token's /me returns the Page
        // (has a category); a user token returns a person (no category). The User
        // Profile API only works with a PAGE token, so this tells us if the connect
        // flow stored the wrong token type (code bug) vs a permission gate (Meta).
        $me = Http::withToken($token)->timeout(10)
            ->get('https://graph.facebook.com/v20.0/me', ['fields' => 'id,name,category']);
        $this->line('  <fg=cyan>/me:</> '.json_encode($me->json(), JSON_UNESCAPED_SLASHES));
        $isPageToken = ! empty($me->json('category')) || $me->json('id') === ($account->meta_json['page_id'] ?? null);
        if ($isPageToken) {
            $this->info('  → This is a PAGE token (good).');
        } else {
            $this->error('  → This looks like a USER token, NOT a page token.');
            $this->warn('    A user token cannot resolve page-scoped PSIDs → error 100. The connect flow must');
            $this->warn('    store $page[\'access_token\'] from /me/accounts, not the user long-lived token.');
        }
        $this->info('');

        $psid = $this->option('psid');
        if (! $psid) {
            $contact = Contact::where('workspace_id', $account->workspace_id)
                ->whereNotNull('custom_fields->messenger_psid')
                ->orderByDesc('id')
                ->first();
            $psid = $contact?->custom_fields['messenger_psid'] ?? null;
        }

        if (! $psid) {
            $this->error('No PSID given and no messenger contact found. Pass --psid=...');

            return self::FAILURE;
        }

        $this->line('  <fg=cyan>PSID:</> '.$psid);
        $this->info('');

        $resp = Http::withToken($token)
            ->timeout(10)
            ->get("https://graph.facebook.com/v20.0/{$psid}", [
                'fields' => 'first_name,last_name,profile_pic',
            ]);

        $this->line('  <fg=cyan>HTTP status:</> '.$resp->status());
        $this->line('  <fg=cyan>Response:</>');
        $this->line(json_encode($resp->json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->info('');

        if ($resp->successful() && ($resp->json('first_name') || $resp->json('last_name'))) {
            $this->info('✓ Profile API works — name returned. The driver will populate the contact on the next message.');

            return self::SUCCESS;
        }

        $code = $resp->json('error.code');
        $this->warn('✗ No name returned. Meta error code: '.($code ?? 'none').' — '.($resp->json('error.message') ?? 'empty profile'));
        $this->warn('  Common cause: the Meta app lacks "pages_messaging" Advanced Access, or the app is not in Live mode,');
        $this->warn('  so the User Profile API returns nothing for non-tester users. Grant Advanced Access + set the app Live,');
        $this->warn('  then reconnect Messenger. (This is the Messenger equivalent of Instagram\'s instagram_manage_messages.)');

        return self::SUCCESS;
    }
}
