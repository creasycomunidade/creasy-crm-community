<?php

namespace Tests\Feature\ProductionHardening;

use App\Modules\Broadcasting\Services\Sms\SmsBdDriver;
use App\Modules\Broadcasting\Services\Sms\TwilioDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tests SMS driver HTTP interactions with Http::fake().
 */
class SmsDriverHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_twilio_driver_sends_to_correct_url_and_returns_sid(): void
    {
        Http::fake([
            'api.twilio.com/*' => Http::response(['sid' => 'SM123', 'status' => 'queued'], 201),
        ]);

        $driver = new TwilioDriver('ACtest', 'token', '+15005550006');
        $result = $driver->send('+16175551234', 'Hello');

        $this->assertTrue($result->success);
        $this->assertSame('SM123', $result->messageId);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.twilio.com') &&
                $request->data()['To'] === '+16175551234' &&
                $request->data()['Body'] === 'Hello';
        });
    }

    public function test_smsbd_driver_returns_real_message_id(): void
    {
        Http::fake([
            'api.smsbd.com/*' => Http::response(['Message_ID' => 'BD_MSG_789'], 200),
        ]);

        $driver = new SmsBdDriver('key123', 'SENDER');
        $result = $driver->send('+8801712345678', 'Test msg');

        $this->assertTrue($result->success);
        $this->assertSame('BD_MSG_789', $result->messageId);
    }
}
