<?php

namespace Tests\Feature\Security;

use App\Models\SocialAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SocialLoginTokenEncryptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_social_login_tokens_are_encrypted_at_rest(): void
    {
        $user = $this->createWorkspaceContext()['user'];
        $account = SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_id' => 'provider-user-1',
            'access_token' => 'access-secret-value',
            'refresh_token' => 'refresh-secret-value',
        ]);

        $raw = DB::table('social_accounts')->where('id', $account->id)->first();
        $this->assertNotSame('access-secret-value', $raw->access_token);
        $this->assertNotSame('refresh-secret-value', $raw->refresh_token);
        $this->assertSame('access-secret-value', $account->fresh()->access_token);
        $this->assertSame('refresh-secret-value', $account->fresh()->refresh_token);
    }
}
