<?php

namespace Tests\Unit\Services\Verification;

use App\Services\Verification\BmsPhoneVerificationSender;
use App\Services\Verification\PhoneVerificationDeliveryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
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
            'medipath.phone_verification.bms.sender_id' => 'medipath',
            'medipath.phone_verification.bms.connect_timeout' => 1,
            'medipath.phone_verification.bms.timeout' => 2,
            'medipath.phone_verification.bms.max_attempts' => 2,
            'medipath.phone_verification.bms.retry_delay_ms' => 0,
        ]);

        Schema::dropIfExists('verification_delivery_events');
        Schema::create('verification_delivery_events', function (Blueprint $table): void {
            $table->id();
            $table->string('provider');
            $table->string('purpose');
            $table->string('outcome');
            $table->string('provider_reference')->nullable();
            $table->string('provider_code')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('duration_ms');
            $table->unsignedTinyInteger('attempt_count');
            $table->string('error_code')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function test_it_sends_an_otp_campaign_using_the_bms_contract(): void
    {
        Http::fake([
            'https://bms.test/api/sms/quick*' => Http::response([
                'status' => 'success',
                'code' => '2000',
                'message' => 'messages sent successfully',
                'summary' => ['_id' => 'A59CCB70-662D-45EF-9976-1EFAD249793D'],
            ]),
        ]);

        $this->sender()->send('+233244000000', '123456');

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://bms.test/api/sms/quick?key=private-test-key'
                && $request['recipient'] === ['0244000000']
                && $request['sender'] === 'medipath'
                && $request['sms_type'] === 'otp'
                && $request['is_schedule'] === false
                && $request['message'] === 'Your MediPath verification code is 123456. It expires in 10 minutes. Do not share this code.';
        });

        $this->assertDatabaseHas('verification_delivery_events', [
            'provider' => 'bms',
            'purpose' => 'phone_verification',
            'outcome' => 'ACCEPTED',
            'provider_reference' => 'A59CCB70-662D-45EF-9976-1EFAD249793D',
            'provider_code' => '2000',
            'http_status' => 200,
            'attempt_count' => 1,
            'error_code' => null,
        ]);
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

        $this->sender()->send('+233244000000', '123456');
    }

    public function test_it_retries_a_temporary_bms_failure_once(): void
    {
        Http::fakeSequence()
            ->push([], 503)
            ->push(['status' => 'success', 'code' => '2000'], 200);

        $this->sender()->send('+233244000000', '123456');

        Http::assertSentCount(2);

        $this->assertDatabaseHas('verification_delivery_events', [
            'outcome' => 'ACCEPTED',
            'attempt_count' => 2,
        ]);
    }

    public function test_connection_errors_do_not_retain_a_url_containing_the_api_key(): void
    {
        Http::fake(fn () => throw new ConnectionException(
            'Could not reach https://bms.test/api/sms/quick?key=private-test-key',
        ));

        try {
            $this->sender()->send('+233244000000', '123456');
            $this->fail('A delivery exception was expected.');
        } catch (PhoneVerificationDeliveryException $exception) {
            $this->assertSame('BMS_CONNECTION_FAILED', $exception->errorCode);
            $this->assertNull($exception->getPrevious());
            $this->assertStringNotContainsString('private-test-key', $exception->getMessage());
        }

        $this->assertDatabaseHas('verification_delivery_events', [
            'outcome' => 'FAILED',
            'attempt_count' => 2,
            'error_code' => 'BMS_CONNECTION_FAILED',
        ]);
    }

    public function test_delivery_observability_does_not_store_the_phone_number_or_otp(): void
    {
        Http::fake([
            'https://bms.test/api/sms/quick*' => Http::response([
                'status' => 'success',
                'code' => '2000',
                'summary' => ['_id' => 'campaign-safe-123'],
            ]),
        ]);

        $this->sender()->send('+233244000000', '123456');

        $event = (array) DB::table('verification_delivery_events')->first();

        $this->assertNotContains('phone_number', Schema::getColumnListing('verification_delivery_events'));
        $this->assertNotContains('code', Schema::getColumnListing('verification_delivery_events'));
        $this->assertStringNotContainsString('+233244000000', json_encode($event, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('123456', json_encode($event, JSON_THROW_ON_ERROR));
    }

    private function sender(): BmsPhoneVerificationSender
    {
        return app(BmsPhoneVerificationSender::class);
    }
}
