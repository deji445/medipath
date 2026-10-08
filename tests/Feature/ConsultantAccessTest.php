<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ConsultantAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('consultant_requests');
        Schema::create('consultant_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('normalized_phone');
            $table->text('concern')->nullable();
            $table->string('status')->default('NEW');
            $table->timestamps();
        });
    }

    public function test_the_homepage_exposes_the_consultant_workspace(): void
    {
        $this->get('/')->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->component('Welcome')
                ->where('chatMessages', [])
                ->where('consultantRequestReference', null),
        );
    }

    public function test_a_real_consultant_callback_request_is_recorded(): void
    {
        $response = $this->post('/consultant/request', [
            'name' => 'Ama Mensah',
            'phone_number' => '024 123 4567',
            'concern' => 'A recurring mild headache',
        ]);

        $response->assertRedirect('/?consult=human')
            ->assertSessionHas('consultant_request_reference');

        $this->assertDatabaseHas('consultant_requests', [
            'name' => 'Ama Mensah',
            'normalized_phone' => '+233241234567',
            'status' => 'NEW',
        ]);
    }

    public function test_the_ai_consultant_keeps_a_short_session_conversation(): void
    {
        config()->set('medipath.assessment.gemini.api_key', 'test-key');
        config()->set('medipath.assessment.gemini.endpoint', 'https://example.test/interactions');

        Http::fake([
            'https://example.test/interactions' => Http::response([
                'status' => 'completed',
                'steps' => [[
                    'type' => 'model_output',
                    'content' => [[
                        'type' => 'text',
                        'text' => 'How long have you had the headache?',
                    ]],
                ]],
            ]),
        ]);

        $this->post('/consultant/chat', ['message' => 'I have a mild headache.'])
            ->assertRedirect('/?consult=ai')
            ->assertSessionHas('consultant_chat.0.role', 'user')
            ->assertSessionHas('consultant_chat.1.role', 'assistant')
            ->assertSessionHas('consultant_chat.1.content', 'How long have you had the headache?');
    }

    public function test_a_consultant_timeout_returns_a_clear_retry_message(): void
    {
        config()->set('medipath.assessment.gemini.api_key', 'test-key');
        config()->set('medipath.assessment.gemini.endpoint', 'https://example.test/interactions');
        config()->set('medipath.assessment.gemini.retry_delay_ms', 0);

        Http::fake(fn () => throw new ConnectionException('Request timed out.'));

        $this->post('/consultant/chat', ['message' => 'I have a mild headache.'])
            ->assertSessionHasErrors([
                'message' => 'The AI consultant did not respond in time. Check your connection and try again.',
            ]);
    }

    public function test_the_consultant_retries_a_temporary_service_failure(): void
    {
        config()->set('medipath.assessment.gemini.api_key', 'test-key');
        config()->set('medipath.assessment.gemini.endpoint', 'https://example.test/interactions');
        config()->set('medipath.assessment.gemini.retry_delay_ms', 0);

        Http::fakeSequence()
            ->push([], 503)
            ->push([
                'status' => 'completed',
                'steps' => [[
                    'type' => 'model_output',
                    'content' => [[
                        'type' => 'text',
                        'text' => 'How long have you felt this way?',
                    ]],
                ]],
            ]);

        $this->post('/consultant/chat', ['message' => 'I feel mildly unwell.'])
            ->assertRedirect('/?consult=ai')
            ->assertSessionHas('consultant_chat.1.content', 'How long have you felt this way?');

        Http::assertSentCount(2);
    }

    public function test_a_rate_limit_returns_a_specific_wait_message_without_retrying(): void
    {
        config()->set('medipath.assessment.gemini.api_key', 'test-key');
        config()->set('medipath.assessment.gemini.endpoint', 'https://example.test/interactions');
        config()->set('medipath.assessment.gemini.retry_delay_ms', 0);

        Http::fake(['https://example.test/interactions' => Http::response([], 429)]);

        $this->post('/consultant/chat', ['message' => 'I feel mildly unwell.'])
            ->assertSessionHasErrors([
                'message' => 'Gemini received too many requests in a short period. Please wait a minute and try again.',
            ]);

        Http::assertSentCount(1);
    }
}
