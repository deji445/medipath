<?php

namespace Tests\Unit\Services\Ai;

use App\Services\Ai\AiRequestRecorder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AiRequestRecorderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('ai_request_events', function (Blueprint $table): void {
            $table->id();
            $table->string('feature');
            $table->string('provider');
            $table->string('model');
            $table->string('outcome');
            $table->unsignedInteger('duration_ms');
            $table->unsignedTinyInteger('attempt_count');
            $table->unsignedTinyInteger('retry_count');
            $table->string('error_code')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function test_it_records_only_bounded_operational_metadata(): void
    {
        (new AiRequestRecorder)->record(
            'consultant_chat',
            'gemini-test',
            'FAILED',
            hrtime(true),
            2,
            'Patient Ama reported a private symptom',
        );

        $event = DB::table('ai_request_events')->first();

        $this->assertNotNull($event);
        $this->assertSame('gemini', $event->provider);
        $this->assertSame(2, $event->attempt_count);
        $this->assertSame(1, $event->retry_count);
        $this->assertSame('UNCLASSIFIED_AI_FAILURE', $event->error_code);
        $this->assertSame([
            'id',
            'feature',
            'provider',
            'model',
            'outcome',
            'duration_ms',
            'attempt_count',
            'retry_count',
            'error_code',
            'created_at',
        ], Schema::getColumnListing('ai_request_events'));
    }
}
