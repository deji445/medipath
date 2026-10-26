<?php

namespace Tests\Unit;

use App\Services\Ai\AiCircuitBreaker;
use App\Services\Ai\AiCircuitOpenException;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AiCircuitBreakerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config()->set([
            'medipath.assessment.gemini.circuit_failure_threshold' => 2,
            'medipath.assessment.gemini.circuit_failure_window_seconds' => 120,
            'medipath.assessment.gemini.circuit_cooldown_seconds' => 60,
        ]);
    }

    public function test_it_opens_after_the_configured_number_of_provider_failures(): void
    {
        $breaker = new AiCircuitBreaker;

        $breaker->recordFailure('GEMINI_CONNECTION_FAILED');
        $breaker->beforeRequest();
        $breaker->recordFailure('GEMINI_HTTP_503');

        $this->expectException(AiCircuitOpenException::class);
        $this->expectExceptionMessage('GEMINI_CIRCUIT_OPEN');

        $breaker->beforeRequest();
    }

    public function test_configuration_and_input_failures_do_not_open_the_circuit(): void
    {
        $breaker = new AiCircuitBreaker;

        $breaker->recordFailure('GEMINI_API_KEY_MISSING');
        $breaker->recordFailure('ASSESSMENT_INPUT_INVALID');

        $breaker->beforeRequest();

        $this->assertTrue(true);
    }

    public function test_model_specific_failures_do_not_open_the_provider_circuit(): void
    {
        $breaker = new AiCircuitBreaker;

        $breaker->recordFailure('GEMINI_HTTP_404');
        $breaker->recordFailure('GEMINI_MODELS_UNAVAILABLE');
        $breaker->recordFailure('GEMINI_HTTP_404');

        $breaker->beforeRequest();

        $this->assertTrue(true);
    }

    public function test_a_success_clears_accumulated_failures(): void
    {
        $breaker = new AiCircuitBreaker;

        $breaker->recordFailure('GEMINI_CONNECTION_FAILED');
        $breaker->recordSuccess();
        $breaker->recordFailure('GEMINI_CONNECTION_FAILED');

        $breaker->beforeRequest();

        $this->assertTrue(true);
    }
}
