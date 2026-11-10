<?php

namespace Tests\Unit\Services\Verification;

use App\Services\Verification\BmsDeliveryStatusSynchronizer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BmsDeliveryStatusSynchronizerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'medipath.phone_verification.bms.api_key' => 'private-test-key',
            'medipath.phone_verification.bms.report_endpoint' => 'https://bms.test/api/campaign',
            'medipath.phone_verification.bms.connect_timeout' => 1,
            'medipath.phone_verification.bms.timeout' => 2,
            'medipath.phone_verification.bms.delivery_batch_size' => 50,
            'medipath.phone_verification.bms.delivery_tracking_hours' => 48,
        ]);

        Schema::dropIfExists('verification_delivery_events');
        Schema::create('verification_delivery_events', function (Blueprint $table): void {
            $table->id();
            $table->string('provider');
            $table->string('purpose');
            $table->string('outcome');
            $table->string('delivery_status')->nullable();
            $table->string('provider_reference')->nullable();
            $table->unsignedSmallInteger('delivery_check_count')->default(0);
            $table->timestamp('delivery_status_checked_at')->nullable();
            $table->timestamp('delivery_resolved_at')->nullable();
            $table->string('delivery_error_code')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function test_it_records_a_terminal_delivery_without_storing_report_content(): void
    {
        $eventId = $this->acceptedEvent('campaign-delivered');
        Http::fake([
            'https://bms.test/api/campaign/campaign-delivered*' => Http::response([
                'status' => 'success',
                'report' => [[
                    'recipient' => '233244000000',
                    'message' => 'Your private OTP is 123456',
                    'status' => 'DELIVERED',
                ]],
            ]),
        ]);

        $summary = app(BmsDeliveryStatusSynchronizer::class)->syncPending();

        $this->assertSame(1, $summary['resolved']);
        $this->assertDatabaseHas('verification_delivery_events', [
            'id' => $eventId,
            'delivery_status' => 'DELIVERED',
            'delivery_check_count' => 1,
            'delivery_error_code' => null,
        ]);
        $event = (array) DB::table('verification_delivery_events')->find($eventId);
        $encoded = json_encode($event, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('233244000000', $encoded);
        $this->assertStringNotContainsString('123456', $encoded);
        $this->assertNotNull($event['delivery_resolved_at']);
    }

    public function test_submitted_campaigns_remain_eligible_for_a_later_check(): void
    {
        $eventId = $this->acceptedEvent('campaign-submitted');
        Http::fake([
            '*' => Http::response([
                'status' => 'success',
                'report' => [['status' => 'SUBMITTED']],
            ]),
        ]);

        $summary = app(BmsDeliveryStatusSynchronizer::class)->syncPending();

        $this->assertSame(1, $summary['pending']);
        $this->assertDatabaseHas('verification_delivery_events', [
            'id' => $eventId,
            'delivery_status' => 'SUBMITTED',
            'delivery_check_count' => 1,
            'delivery_resolved_at' => null,
        ]);
    }

    public function test_provider_failures_are_bounded_and_can_be_retried_later(): void
    {
        $eventId = $this->acceptedEvent('campaign-unavailable');
        Http::fake(['*' => Http::response([], 503)]);

        $summary = app(BmsDeliveryStatusSynchronizer::class)->syncPending();

        $this->assertSame(1, $summary['failed']);
        $this->assertDatabaseHas('verification_delivery_events', [
            'id' => $eventId,
            'delivery_status' => 'PENDING',
            'delivery_check_count' => 1,
            'delivery_error_code' => 'BMS_REPORT_HTTP_503',
            'delivery_resolved_at' => null,
        ]);
    }

    public function test_stale_campaigns_expire_without_calling_the_provider(): void
    {
        $eventId = $this->acceptedEvent('campaign-old', now()->subHours(49));
        Http::fake();

        $summary = app(BmsDeliveryStatusSynchronizer::class)->syncPending();

        $this->assertSame(1, $summary['expired']);
        Http::assertNothingSent();
        $this->assertDatabaseHas('verification_delivery_events', [
            'id' => $eventId,
            'delivery_status' => 'UNKNOWN',
            'delivery_error_code' => 'BMS_REPORT_WINDOW_EXPIRED',
        ]);
    }

    private function acceptedEvent(string $reference, mixed $createdAt = null): int
    {
        return (int) DB::table('verification_delivery_events')->insertGetId([
            'provider' => 'bms',
            'purpose' => 'phone_verification',
            'outcome' => 'ACCEPTED',
            'delivery_status' => 'PENDING',
            'provider_reference' => $reference,
            'delivery_check_count' => 0,
            'created_at' => $createdAt ?? now(),
        ]);
    }
}
