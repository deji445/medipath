<?php

namespace Tests\Unit\Services\Verification;

use App\Services\Verification\NaloPhoneVerificationSender;
use App\Services\Verification\PhoneVerificationDeliveryException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NaloPhoneVerificationSenderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'medipath.phone_verification.expiry_minutes' => 10,
            'medipath.phone_verification.nalo.endpoint' => 'https://nalo.test/send-message/',
            'medipath.phone_verification.nalo.auth_key' => 'private-test-key',
            'medipath.phone_verification.nalo.sender_id' => 'MediPath',
            'medipath.phone_verification.nalo.connect_timeout' => 1,
            'medipath.phone_verification.nalo.timeout' => 2,
        ]);
    }

    public function test_it_sends_a_minimal_verification_message_to_nalo(): void
    {
        Http::fake(['https://nalo.test/send-message/' => Http::response(['status' => 'success'])]);

        (new NaloPhoneVerificationSender)->send('+233244000000', '123456');

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://nalo.test/send-message/'
                && $request['key'] === 'private-test-key'
                && $request['msisdn'] === '233244000000'
                && $request['sender_id'] === 'MediPath'
                && $request['message'] === 'Your MediPath verification code is 123456. It expires in 10 minutes. Do not share this code.';
        });
    }

    public function test_it_retries_a_temporary_nalo_failure_once(): void
    {
        Http::fakeSequence()
            ->push([], 503)
            ->push(['status' => 'success'], 200);

        (new NaloPhoneVerificationSender)->send('+233244000000', '123456');

        Http::assertSentCount(2);
    }

    public function test_it_fails_safely_when_nalo_is_not_configured(): void
    {
        config()->set('medipath.phone_verification.nalo.auth_key', null);

        $this->expectException(PhoneVerificationDeliveryException::class);
        $this->expectExceptionMessage('The verification message could not be delivered.');

        (new NaloPhoneVerificationSender)->send('+233244000000', '123456');
    }
}
