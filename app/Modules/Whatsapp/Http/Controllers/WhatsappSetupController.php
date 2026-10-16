<?php

namespace App\Modules\Whatsapp\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Integrations\Services\CredentialResolver;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Whatsapp\Models\WhatsappBusinessAccount;
use App\Modules\Whatsapp\Models\WhatsappPhoneNumber;
use App\Modules\Whatsapp\Services\CloudApiClient;
use Illuminate\Http\Client\ConnectionException as HttpConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class WhatsappSetupController extends Controller
{
    public function syncPhoneNumbers(Request $request, WhatsappBusinessAccount $waba): RedirectResponse
    {
        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;
        $this->authorizeWaba($waba, $workspaceId);

        try {
            $n = $this->importPhoneNumbersFromMeta($waba);
        } catch (HttpConnectionException $e) {
            Log::warning('WhatsApp setup: phone sync failed (TLS/network)', [
                'workspace_id' => $workspaceId,
                'waba_id' => $waba->waba_id,
                'exception' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'sync' => 'Could not connect to Meta (TLS/certificate). Set HTTP_CLIENT_CA_PATH in .env to a valid cacert.pem file, or fix curl.cainfo in php.ini. See .env.example.',
            ]);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp setup: phone sync failed', [
                'workspace_id' => $workspaceId,
                'waba_id' => $waba->waba_id,
                'exception' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'sync' => 'Could not load phone numbers from Meta. Check the WABA ID, that numbers are assigned in Business Manager, and that your system user token includes whatsapp_business_management.',
            ]);
        }

        if ($n === 0) {
            return back()->withErrors([
                'sync' => 'Meta returned no phone numbers for this WABA. Confirm the number is added to this exact account in Meta Business Suite, then try again.',
            ]);
        }

        return back()->with('success', "Synced {$n} phone number(s) from Meta.");
    }

    public function destroy(Request $request, WhatsappBusinessAccount $waba): RedirectResponse
    {
        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;
        $this->authorizeWaba($waba, $workspaceId);

        ChannelAccount::where('workspace_id', $workspaceId)
            ->where('channel', 'whatsapp')
            ->where('business_account_id', $waba->waba_id)
            ->delete();

        WhatsappPhoneNumber::where('waba_id_fk', $waba->id)->delete();

        $waba->delete();

        return back()->with('success', 'Conta do WhatsApp Business desconectada.');
    }

    public function refreshPhoneStatus(Request $request, WhatsappBusinessAccount $waba, string $phoneNumberId): JsonResponse
    {
        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;
        $this->authorizeWaba($waba, $workspaceId);

        $token = $this->metaAccessToken($waba);
        if ($token === '') {
            return response()->json(['error' => 'Nenhum token de acesso disponível.'], 422);
        }

        $details = CloudApiClient::fetchPhoneNumberDetails($phoneNumberId, $token);
        if (! is_array($details)) {
            return response()->json(['error' => 'Não foi possível consultar os dados do telefone na Meta.'], 422);
        }

        $throughput = is_array($details['throughput'] ?? null) ? $details['throughput'] : [];
        $tier = $details['messaging_limit_tier'] ?? ($throughput['level'] ?? null);

        $patch = array_filter([
            'display_phone'            => $details['display_phone_number'] ?? null,
            'verified_name'            => $details['verified_name'] ?? null,
            'quality_rating'           => $details['quality_rating'] ?? null,
            'messaging_limit_tier'     => is_string($tier) ? $tier : null,
            'code_verification_status' => $details['code_verification_status'] ?? null,
            'name_status'              => $details['name_status'] ?? null,
            'requested_verified_name'  => $details['requested_verified_name'] ?? null,
            'account_mode'             => $details['account_mode'] ?? null,
        ], fn ($v) => $v !== null);

        WhatsappPhoneNumber::where('phone_number_id', $phoneNumberId)->update($patch);

        return response()->json([
            'success' => true,
            'data'    => array_merge($details, ['phone_number_id' => $phoneNumberId]),
        ]);
    }

    public function changeDisplayName(Request $request, WhatsappBusinessAccount $waba, string $phoneNumberId): JsonResponse
    {
        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;
        $this->authorizeWaba($waba, $workspaceId);

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:1', 'max:100'],
        ]);

        $token = $this->metaAccessToken($waba);
        if ($token === '') {
            return response()->json(['error' => 'Nenhum token de acesso disponível.'], 422);
        }

        $result = CloudApiClient::requestDisplayNameChangeDirect($phoneNumberId, $validated['name'], $token);

        if (! $result['success']) {
            $metaError = $result['response']['error'] ?? null;
            $errMsg = $metaError['error_user_msg']
                ?? $metaError['message']
                ?? ('Meta rejected the name change. Code: ' . ($metaError['code'] ?? 'unknown'));
            Log::warning('WhatsApp display name change failed', [
                'phone_number_id' => $phoneNumberId,
                'new_name'        => $validated['name'],
                'http_status'     => $result['status'],
                'meta_response'   => $result['response'],
            ]);
            return response()->json(['error' => $errMsg], 422);
        }

        // Update local DB to reflect pending review
        WhatsappPhoneNumber::where('phone_number_id', $phoneNumberId)->update([
            'name_status'             => 'PENDING_REVIEW',
            'requested_verified_name' => $validated['name'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Display name change submitted. Meta will review it — this usually takes a few minutes to 24 hours.',
        ]);
    }

    private function authorizeWaba(WhatsappBusinessAccount $waba, int|string $workspaceId): void
    {
        abort_unless((int) $waba->workspace_id === (int) $workspaceId, 403);
    }

    private function importPhoneNumbersFromMeta(WhatsappBusinessAccount $waba): int
    {
        $token = $this->metaAccessToken($waba);

        if ($token === '') {
            throw new \RuntimeException('No system user access token available for this workspace.');
        }

        $rows = CloudApiClient::fetchWabaPhoneNumbers($waba->waba_id, $token);
        $count = 0;

        foreach ($rows as $row) {
            if (empty($row['id'])) {
                continue;
            }
            // Fetch verification status separately (not returned by phone_numbers edge)
            $details = CloudApiClient::fetchPhoneNumberDetails((string) $row['id'], $token);
            if (is_array($details)) {
                $row = array_merge($row, $details);
            }
            $this->attachPhoneNumberToWaba($waba, (string) $row['id'], $row);
            $count++;
        }

        return $count;
    }

    private function metaAccessToken(WhatsappBusinessAccount $waba): string
    {
        $creds = $waba->credentials ?? [];
        $token = $creds['system_user_token'] ?? '';

        if ($token === '') {
            $meta = CredentialResolver::system()->meta();
            $token = $meta?->systemUserToken() ?? '';
        }

        return $token;
    }

    /**
     * @param  array<string, mixed>  $metaRow  Fields from Meta Graph (phone_numbers edge or phone-number node).
     */
    private function attachPhoneNumberToWaba(WhatsappBusinessAccount $waba, string $phoneNumberId, array $metaRow): void
    {
        $conflict = ChannelAccount::where('channel', 'whatsapp')
            ->where('external_account_id', $phoneNumberId)
            ->where('workspace_id', '!=', $waba->workspace_id)
            ->exists();
        if ($conflict) {
            throw ValidationException::withMessages([
                'whatsapp' => __('This WhatsApp number is already connected to another workspace.'),
            ]);
        }

        $throughput = is_array($metaRow['throughput'] ?? null) ? $metaRow['throughput'] : [];
        $tier = $metaRow['messaging_limit_tier'] ?? ($throughput['level'] ?? null);

        // Filter nulls so a partial Meta response never wipes previously-synced
        // descriptive fields. The waba link is always set.
        $details = array_filter([
            'display_phone'            => $metaRow['display_phone_number'] ?? null,
            'verified_name'            => $metaRow['verified_name'] ?? null,
            'quality_rating'           => $metaRow['quality_rating'] ?? null,
            'messaging_limit_tier'     => is_string($tier) ? $tier : null,
            'code_verification_status' => $metaRow['code_verification_status'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        WhatsappPhoneNumber::updateOrCreate(
            ['phone_number_id' => $phoneNumberId],
            array_merge(['waba_id_fk' => $waba->id], $details),
        );

        $account = ChannelAccount::firstOrNew([
            'workspace_id'    => $waba->workspace_id,
            'phone_number_id' => $phoneNumberId,
        ]);

        $account->channel             = 'whatsapp';
        $account->provider            = 'meta';
        $account->business_account_id = $waba->waba_id;
        $account->external_account_id  = $phoneNumberId;
        $account->status              = 'active';

        $label = $metaRow['verified_name'] ?? $metaRow['display_phone_number'] ?? null;
        if ($label !== null && $label !== '') {
            $account->display_name = mb_substr((string) $label, 0, 128);
        } elseif (! $account->exists) {
            $account->display_name = 'WhatsApp';
        }

        $account->save();
    }
}
