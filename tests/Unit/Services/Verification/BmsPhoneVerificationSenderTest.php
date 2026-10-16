<?php

namespace Tests\Unit\Services\Verification;

use App\Services\Verification\BmsPhoneVerificationSender;
use App\Services\Verification\PhoneVerificationDeliveryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BmsPhoneVerificationSenderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'medipath.phone_verification.expiry_minutes' => 10,
            'medipath.phone_verification.bms.endpoint' => 'https://bms.test/api/sms/quick',
            'medipath.phone_verification.bms.api_key' => 'private-test-key',
            'medipath.phone_verification.bms.sender_id' => 'MediPath',
            'medipath.phone_verification.bms.connect_timeout' => 1,
            'medipath.phone_verification.bms.timeout' => 2,
        ]);
    }

    public function test_it_sends_an_otp_campaign_using_the_bms_contract(): void
    {
        Http::fake([
            'https://bms.test/api/sms/quick*' => Http::response([
                'status' => 'success',
                'code' => '2000',
                'message' => 'messages sent successfully',
            ]),
        ]);

        (new BmsPhoneVerificationSender)->send('+233244000000', '123456');

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://bms.test/api/sms/quick?key=private-test-key'
                && $request['recipient'] === ['0244000000']
                && $request['sender'] === 'MediPath'
                && $request['sms_type'] === 'otp'
                && $request['is_schedule'] === false
                && $request['message'] === 'Your MediPath verification code is 123456. It expires in 10 minutes. Do not share this code.';
        });
    }

    public function test_http_200_is_rejected_when_bms_does_not_return_its_success_code(): void
    {
        Http::fake([
            'https://bms.test/api/sms/quick*' => Http::response([
                'status' => 'error',
                'code' => '4001',
            ]),
        ]);

        $this->expectException(PhoneVerificationDeliveryException::class);

        (new BmsPhoneVerificationSender)->send('+233244000000', '123456');
    }

    public function test_it_retries_a_temporary_bms_failure_once(): void
    {
        Http::fakeSequence()
            ->push([], 503)
            ->push(['status' => 'success', 'code' => '2000'], 200);

        (new BmsPhoneVerificationSender)->send('+233244000000', '123456');

        Http::assertSentCount(2);
    }

    public function test_connection_errors_do_not_retain_a_url_containing_the_api_key(): void
    {
        Http::fake(fn () => throw new ConnectionException(
            'Could not reach https://bms.test/api/sms/quick?key=private-test-key',
        ));

        try {
            (new BmsPhoneVerificationSender)->send('+233244000000', '123456');
            $this->fail('A delivery exception was expected.');
        } catch (PhoneVerificationDeliveryException $exception) {
            $this->assertSame('BMS_CONNECTION_FAILED', $exception->errorCode);
            $this->assertNull($exception->getPrevious());
            $this->assertStringNotContainsString('private-test-key', $exception->getMessage());
        }
    }
}
