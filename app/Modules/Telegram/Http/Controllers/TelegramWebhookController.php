<?php

namespace App\Modules\Telegram\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Telegram\Services\TelegramDriver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    public function receive(Request $request, string $token, TelegramDriver $driver): JsonResponse
    {
        $account = ChannelAccount::where('channel', 'telegram')
            ->where('status', 'active')
            ->whereJsonContains('meta_json->webhook_token', $token)
            ->first();

        if (! $account) {
            return response()->json(['ok' => false], 404);
        }

        $expected = (string) ($account->credentials['webhook_secret'] ?? '');
        $provided = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');
        if ($expected === '' || ! hash_equals($expected, $provided)) {
            return response()->json(['ok' => false], 403);
        }

        $driver->processUpdate($account, $request->all());

        return response()->json(['ok' => true]);
    }
}
