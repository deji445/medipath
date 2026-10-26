<?php

namespace Tests\Feature;

use App\Models\ConsultantRequest;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\EmergencyPhraseCorpus;
use Tests\TestCase;

class ConsultantAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('medipath.phone_verification.demo_code', '123456');
        config()->set('medipath.phone_verification.driver', 'local');

        Schema::dropIfExists('ai_request_events');
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

        Schema::dropIfExists('consultant_requests');
        Schema::create('consultant_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('normalized_phone');
            $table->text('concern')->nullable();
            $table->string('status')->default('NEW');
            $table->timestamp('phone_verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_the_homepage_exposes_the_consultant_workspace(): void
    {
        $this->get('/')->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->component('Welcome')
                ->where('chatMessages', [])
                ->where('emergencyContact.phone', '112')
                ->where('consultantRequestReference', null)
                ->where('callbackVerification', null),
        );
    }

    public function test_reloading_the_homepage_clears_the_ai_chat(): void
    {
        $this->withSession([
            'consultant_chat' => [
                ['role' => 'user', 'content' => 'A previous question'],
                ['role' => 'assistant', 'content' => 'A previous answer'],
            ],
        ])->get('/')
            ->assertOk()
            ->assertSessionMissing('consultant_chat')
            ->assertInertia(fn (Assert $page) => $page->where('chatMessages', []));
    }

    public function test_a_non_emergency_chat_can_be_carried_into_patient_intake(): void
    {
        $response = $this->withSession([
            'consultant_chat' => [
                ['role' => 'user', 'content' => 'My stomach has been aching since this morning.'],
                ['role' => 'assistant', 'content' => 'Where is the pain located?', 'emergency' => false],
                ['role' => 'user', 'content' => 'It is a dull pain near the middle.'],
            ],
        ])->post('/consultant/chat/intake');

        $response->assertRedirect('/intake')
            ->assertSessionHas(
                'intake.chat_handoff.main_complaint',
                'My stomach has been aching since this morning. It is a dull pain near the middle.',
            )
            ->assertSessionHas('intake.chat_handoff.message_count', 2)
            ->assertSessionMissing('intake.phone');
    }

    public function test_an_emergency_chat_cannot_be_carried_into_ordinary_intake(): void
    {
        $response = $this->withSession([
            'consultant_chat' => [
                ['role' => 'user', 'content' => 'I was shot in the arm.'],
                ['role' => 'assistant', 'content' => 'Call 112 now.', 'emergency' => true],
            ],
        ])->post('/consultant/chat/intake');

        $response->assertRedirect('/emergency')
            ->assertSessionMissing('intake.chat_handoff');
    }

    public function test_a_real_consultant_callback_request_is_recorded_only_after_phone_verification(): void
    {
        $response = $this->post('/consultant/request', [
            'name' => 'Ama Mensah',
            'phone_number' => '024 123 4567',
            'concern' => 'A recurring mild headache',
        ]);

        $response->assertRedirect('/?consult=human')
            ->assertSessionHas('consultant_callback.pending_request.normalized_phone', '+233241234567')
            ->assertSessionHas('consultant_callback.pending_phone_verification.phone', '+233241234567')
            ->assertSessionHas('consultant_callback.demo_verification_code', '123456');

        $this->assertDatabaseCount('consultant_requests', 0);

        $this->get('/?consult=human')->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->component('Welcome')
                ->where('callbackVerification.phoneNumber', '•••••••••4567')
                ->where('callbackVerification.demoCode', '123456'),
        );

        $this->post('/consultant/request/verify', ['code' => '123456'])
            ->assertRedirect('/?consult=human')
            ->assertSessionHas('consultant_request_reference')
            ->assertSessionMissing('consultant_callback.pending_request')
            ->assertSessionMissing('consultant_callback.pending_phone_verification');

        $this->assertDatabaseHas('consultant_requests', [
            'name' => 'Ama Mensah',
            'normalized_phone' => '+233241234567',
            'status' => 'NEW',
        ]);
        $this->assertNotNull(ConsultantRequest::query()->firstOrFail()->phone_verified_at);
    }

    public function test_an_incorrect_callback_verification_code_does_not_create_a_request(): void
    {
        $this->post('/consultant/request', [
            'name' => 'Ama Mensah',
            'phone_number' => '024 123 4567',
            'concern' => 'A recurring mild headache',
        ]);

        $this->post('/consultant/request/verify', ['code' => '000000'])
            ->assertSessionHasErrors('code')
            ->assertSessionHas('consultant_callback.pending_phone_verification.attempts', 1);

        $this->assertDatabaseCount('consultant_requests', 0);
    }

    public function test_an_emergency_callback_concern_is_redirected_before_verification(): void
    {
        $this->post('/consultant/request', [
            'name' => 'Ama Mensah',
            'phone_number' => '024 123 4567',
            'concern' => 'I am pooping blood',
        ])->assertRedirect('/emergency')
            ->assertSessionMissing('consultant_callback.pending_request');

        $this->assertDatabaseCount('consultant_requests', 0);
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

        Http::assertSent(fn ($request): bool => $request['generation_config']['thinking_level'] === 'minimal'
            && $request['generation_config']['max_output_tokens'] === 320
            && str_contains(
                $request['system_instruction'],
                'Never diagnose, suggest likely causes, list possible conditions',
            )
        );
        $this->assertDatabaseHas('ai_request_events', [
            'feature' => 'consultant_chat',
            'provider' => 'gemini',
            'outcome' => 'SUCCEEDED',
            'attempt_count' => 1,
            'retry_count' => 0,
            'error_code' => null,
        ]);
    }

    public function test_the_ai_consultant_uses_twi_when_the_site_locale_is_twi(): void
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
                        'text' => 'Ɛhe na ɛyɛ wo yaw?',
                    ]],
                ]],
            ]),
        ]);

        $this->withSession(['locale' => 'tw'])
            ->postJson('/consultant/chat', ['message' => 'Me yafunu yɛ me yaw.'])
            ->assertOk()
            ->assertJsonPath('messages.1.content', 'Ɛhe na ɛyɛ wo yaw?');

        Http::assertSent(fn ($request): bool => str_contains(
            $request['system_instruction'],
            'Write every user-visible sentence only in clear, natural Ghanaian Twi',
        ) && str_contains(
            $request['system_instruction'],
            'Do not drift into English because earlier conversation messages are English',
        ));
    }

    public function test_twi_remains_the_response_language_when_previous_messages_are_english(): void
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
                        'text' => 'Ɛberɛ bɛn na yaw no fii ase?',
                    ]],
                ]],
            ]),
        ]);

        $this->withSession([
            'locale' => 'tw',
            'consultant_chat' => [
                ['role' => 'user', 'content' => 'My stomach hurts.'],
                ['role' => 'assistant', 'content' => 'When did the pain begin?'],
            ],
        ])->postJson('/consultant/chat', ['message' => 'Ɛyɛ me yaw paa.'])
            ->assertOk()
            ->assertJsonPath('messages.3.content', 'Ɛberɛ bɛn na yaw no fii ase?');

        Http::assertSent(fn ($request): bool => str_contains(
            $request['system_instruction'],
            'Do not drift into English because earlier conversation messages are English',
        ));
    }

    public function test_deterministic_emergency_guidance_is_localized_in_twi(): void
    {
        Http::fake();

        $this->withSession(['locale' => 'tw'])
            ->postJson('/consultant/chat', ['message' => 'I cannot breathe.'])
            ->assertOk()
            ->assertJsonPath(
                'messages.1.content',
                'Ebia eyi yɛ tebea a ɛyɛ ntɛm. Frɛ 112 seesei. Ntwɛn nkɔmmɔbɔ mmuae foforo.',
            )
            ->assertJsonPath('messages.1.emergency', true);

        Http::assertNothingSent();
    }

    public function test_the_ai_consultant_normalizes_encoded_spacing_in_plain_text(): void
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
                        'text' => "Tell me where it hurts.&#x20;\n\nHow long has it lasted?",
                    ]],
                ]],
            ]),
        ]);

        $this->postJson('/consultant/chat', ['message' => 'My tummy hurts.'])
            ->assertOk()
            ->assertJsonPath(
                'messages.1.content',
                "Tell me where it hurts.\n\nHow long has it lasted?",
            );
    }

    public function test_the_ai_consultant_can_reply_without_a_page_navigation(): void
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
                        'text' => 'When did the pain begin?',
                    ]],
                ]],
            ]),
        ]);

        $this->postJson('/consultant/chat', ['message' => 'My shoulder hurts.'])
            ->assertOk()
            ->assertJsonPath('messages.0.content', 'My shoulder hurts.')
            ->assertJsonPath('messages.1.content', 'When did the pain begin?');
    }

    #[DataProvider('emergencyChatMessages')]
    public function test_chat_uses_the_shared_emergency_screen_before_calling_gemini(string $message): void
    {
        config()->set('medipath.assessment.gemini.api_key', null);
        config()->set('medipath.emergency.phone', '112');
        Http::fake();

        $this->postJson('/consultant/chat', ['message' => $message])
            ->assertOk()
            ->assertJsonPath('messages.0.content', $message)
            ->assertJsonPath(
                'messages.1.content',
                'This may be an emergency. Call 112 now. Do not wait for another chat reply.',
            )
            ->assertJsonPath('messages.1.emergency', true)
            ->assertJsonPath('emergencyContact.phone', '112');

        Http::assertNothingSent();
    }

    /** @return iterable<string, array{string}> */
    public static function emergencyChatMessages(): iterable
    {
        foreach (EmergencyPhraseCorpus::cases() as $name => [$message]) {
            yield $name => [$message];
        }
    }

    public function test_an_earlier_red_flag_escalates_the_whole_conversation(): void
    {
        config()->set('medipath.assessment.gemini.api_key', 'test-key');
        Http::fake();

        $this->withSession([
            'consultant_chat' => [
                ['role' => 'user', 'content' => 'I broke my leg'],
                [
                    'role' => 'assistant',
                    'content' => 'Is someone with you?',
                    'emergency' => false,
                ],
            ],
        ])->postJson('/consultant/chat', [
            'message' => 'Yes, someone is with me.',
        ])->assertOk()
            ->assertJsonCount(4, 'messages')
            ->assertJsonPath('messages.3.emergency', true)
            ->assertJsonPath(
                'messages.3.content',
                'This may be an emergency. Call 112 now. Do not wait for another chat reply.',
            );

        Http::assertNothingSent();
    }

    public function test_external_limb_bleeding_includes_basic_first_aid_after_escalation(): void
    {
        Http::fake();

        $this->postJson('/consultant/chat', ['message' => 'My arm is bleeding.'])
            ->assertOk()
            ->assertJsonPath('messages.1.emergency', true)
            ->assertJsonPath(
                'messages.1.first_aid_steps.0',
                'Use a clean cloth or dressing to apply firm, continuous pressure to the wound.',
            )
            ->assertJsonPath(
                'messages.1.first_aid_steps.1',
                'If blood soaks through, add another cloth or dressing on top. Keep the first one in place.',
            );

        Http::assertNothingSent();
    }

    public function test_a_suspected_limb_fracture_includes_basic_first_aid_after_escalation(): void
    {
        Http::fake();

        $this->postJson('/consultant/chat', ['message' => 'I broke my leg.'])
            ->assertOk()
            ->assertJsonPath('messages.1.emergency', true)
            ->assertJsonPath(
                'messages.1.first_aid_steps.0',
                'Keep the injured limb still. Do not straighten it or put weight on it.',
            )
            ->assertJsonPath(
                'messages.1.first_aid_steps.1',
                'Do not move the person unless the area is unsafe.',
            );

        Http::assertNothingSent();
    }

    public function test_a_penetrating_limb_injury_does_not_receive_oversimplified_first_aid(): void
    {
        Http::fake();

        $this->postJson('/consultant/chat', ['message' => 'I was shot in the arm.'])
            ->assertOk()
            ->assertJsonPath('messages.1.emergency', true)
            ->assertJsonPath('messages.1.first_aid_steps', []);

        Http::assertNothingSent();
    }

    public function test_an_emergency_conversation_cannot_resume_normal_chat(): void
    {
        Http::fake();

        $this->withSession([
            'consultant_chat' => [
                ['role' => 'user', 'content' => 'I have trouble breathing'],
                [
                    'role' => 'assistant',
                    'content' => 'This may be an emergency. Call 112 now.',
                    'emergency' => true,
                    'red_flags' => ['severe_breathing_difficulty'],
                ],
            ],
        ])->postJson('/consultant/chat', [
            'message' => 'Can we keep chatting?',
        ])->assertOk()
            ->assertJsonCount(2, 'messages')
            ->assertJsonPath('messages.1.emergency', true);

        Http::assertNothingSent();
    }

    public function test_an_explicitly_negated_emergency_phrase_can_continue_to_gemini(): void
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
                        'text' => 'What discomfort are you having instead?',
                    ]],
                ]],
            ]),
        ]);

        $this->postJson('/consultant/chat', [
            'message' => 'I do not have chest pain, but I have mild indigestion.',
        ])->assertOk()
            ->assertJsonPath('messages.1.content', 'What discomfort are you having instead?');

        Http::assertSentCount(1);
    }

    public function test_a_consultant_timeout_returns_a_clear_retry_message(): void
    {
        config()->set('medipath.assessment.gemini.api_key', 'test-key');
        config()->set('medipath.assessment.gemini.endpoint', 'https://example.test/interactions');
        config()->set('medipath.assessment.gemini.retry_delay_ms', 0);

        $attempts = 0;
        Http::fake(function () use (&$attempts): never {
            $attempts++;

            throw new ConnectionException('Request timed out.');
        });

        $this->post('/consultant/chat', ['message' => 'I have a mild headache.'])
            ->assertSessionHasErrors([
                'message' => 'Gemini took too long to respond. Your message is still here, so you can try again.',
            ]);

        $this->assertSame(2, $attempts);
        $this->assertDatabaseHas('ai_request_events', [
            'feature' => 'consultant_chat',
            'outcome' => 'FAILED',
            'attempt_count' => 2,
            'retry_count' => 1,
            'error_code' => 'GEMINI_CONNECTION_FAILED',
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

    public function test_a_rate_limit_returns_a_specific_wait_message_after_retrying(): void
    {
        config()->set('medipath.assessment.gemini.api_key', 'test-key');
        config()->set('medipath.assessment.gemini.endpoint', 'https://example.test/interactions');
        config()->set('medipath.assessment.gemini.retry_delay_ms', 0);

        Http::fake(['https://example.test/interactions' => Http::response([], 429)]);

        $this->post('/consultant/chat', ['message' => 'I feel mildly unwell.'])
            ->assertSessionHasErrors([
                'message' => 'Gemini received too many requests in a short period. Please wait a minute and try again.',
            ]);

        Http::assertSentCount(2);
    }

    public function test_repeated_provider_failures_open_the_circuit_and_skip_another_slow_request(): void
    {
        config()->set([
            'medipath.assessment.gemini.api_key' => 'test-key',
            'medipath.assessment.gemini.endpoint' => 'https://example.test/interactions',
            'medipath.assessment.gemini.retry_delay_ms' => 0,
            'medipath.assessment.gemini.circuit_failure_threshold' => 2,
            'medipath.assessment.gemini.circuit_cooldown_seconds' => 60,
        ]);

        $attempts = 0;
        Http::fake(function () use (&$attempts): never {
            $attempts++;

            throw new ConnectionException('Request timed out.');
        });

        $this->postJson('/consultant/chat', ['message' => 'I have a mild headache.'])
            ->assertStatus(503);
        $this->postJson('/consultant/chat', ['message' => 'I still have a mild headache.'])
            ->assertStatus(503);

        $this->postJson('/consultant/chat', ['message' => 'Can I try once more?'])
            ->assertStatus(503)
            ->assertJsonPath(
                'message',
                'Gemini is temporarily unavailable after repeated failures. Please wait a minute or request a real consultant.',
            );

        $this->assertSame(4, $attempts);
        $this->assertDatabaseHas('ai_request_events', [
            'feature' => 'consultant_chat',
            'outcome' => 'FAILED',
            'attempt_count' => 0,
            'retry_count' => 0,
            'error_code' => 'GEMINI_CIRCUIT_OPEN',
        ]);
    }

    public function test_the_consultant_returns_a_fast_reply_over_the_event_stream(): void
    {
        config()->set([
            'medipath.assessment.gemini.api_key' => 'test-key',
            'medipath.assessment.gemini.consultant_endpoint' => 'https://example.test/generate-content',
        ]);
        Http::fake([
            'https://example.test/generate-content' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [
                        ['text' => 'How long has it hurt?'],
                    ]],
                ]],
            ]),
        ]);

        $response = $this->post('/consultant/chat/stream', [
            'message' => 'My shoulder hurts mildly.',
        ], ['Accept' => 'text/event-stream']);
        $content = $response->streamedContent();

        $response->assertOk();
        $this->assertStringContainsString('event: delta', $content);
        $this->assertStringContainsString('How long has it hurt?', $content);
        $this->assertStringContainsString('event: complete', $content);
        $response->assertSessionHas(
            'consultant_chat.1.content',
            'How long has it hurt?',
        );
        $this->assertDatabaseHas('ai_request_events', [
            'feature' => 'consultant_chat',
            'outcome' => 'SUCCEEDED',
            'attempt_count' => 1,
        ]);
        Http::assertSent(fn ($request): bool => is_array($request['contents']));
    }

    public function test_the_event_stream_uses_visible_chat_history_when_the_session_is_missing(): void
    {
        config()->set([
            'medipath.assessment.gemini.api_key' => 'test-key',
            'medipath.assessment.gemini.consultant_endpoint' => 'https://example.test/generate-content',
        ]);
        Http::fake([
            'https://example.test/generate-content' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [
                        ['text' => 'Has the foot pain become worse during that week?'],
                    ]],
                ]],
            ]),
        ]);

        $response = $this->post('/consultant/chat/stream', [
            'message' => 'It has been a week.',
            'history' => [
                ['role' => 'user', 'content' => 'My feet hurt.'],
                ['role' => 'assistant', 'content' => 'How long have your feet hurt?'],
            ],
        ], ['Accept' => 'text/event-stream']);

        $response->assertOk();
        $this->assertStringContainsString(
            'Has the foot pain become worse during that week?',
            $response->streamedContent(),
        );
        Http::assertSent(fn ($request): bool => data_get(
            $request->data(),
            'contents.0.parts.0.text',
        ) === 'My feet hurt.' && data_get(
            $request->data(),
            'contents.2.parts.0.text',
        ) === 'It has been a week.');
    }

    public function test_the_event_stream_retries_a_temporary_service_failure(): void
    {
        config()->set([
            'medipath.assessment.gemini.api_key' => 'test-key',
            'medipath.assessment.gemini.consultant_model' => 'primary-flash',
            'medipath.assessment.gemini.consultant_fallback_model' => 'fallback-flash',
            'medipath.assessment.gemini.consultant_endpoint' => 'https://example.test/models/{model}:generate-content',
            'medipath.assessment.gemini.retry_delay_ms' => 0,
        ]);
        Http::fakeSequence()
            ->push([], 503)
            ->push([
                'candidates' => [[
                    'content' => ['parts' => [
                        ['text' => 'How long has it hurt?'],
                    ]],
                ]],
            ]);

        $response = $this->post('/consultant/chat/stream', [
            'message' => 'My shoulder hurts mildly.',
        ], ['Accept' => 'text/event-stream']);

        $response->assertOk();
        $this->assertStringContainsString('How long has it hurt?', $response->streamedContent());
        Http::assertSentCount(2);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://example.test/models/primary-flash:generate-content');
        Http::assertSent(fn ($request): bool => $request->url() === 'https://example.test/models/fallback-flash:generate-content');
        $this->assertDatabaseHas('ai_request_events', [
            'feature' => 'consultant_chat',
            'outcome' => 'SUCCEEDED',
            'attempt_count' => 2,
            'retry_count' => 1,
        ]);
    }

    public function test_the_event_stream_uses_the_fallback_model_when_the_primary_model_is_missing(): void
    {
        config()->set([
            'medipath.assessment.gemini.api_key' => 'test-key',
            'medipath.assessment.gemini.consultant_model' => 'missing-primary',
            'medipath.assessment.gemini.consultant_fallback_model' => 'stable-fallback',
            'medipath.assessment.gemini.consultant_endpoint' => 'https://example.test/models/{model}:generate-content',
            'medipath.assessment.gemini.retry_delay_ms' => 0,
        ]);
        Http::fake([
            'https://example.test/models/missing-primary:generate-content' => Http::response([], 404),
            'https://example.test/models/stable-fallback:generate-content' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [
                        ['text' => 'Has anything made the discomfort better?'],
                    ]],
                ]],
            ]),
        ]);

        $response = $this->post('/consultant/chat/stream', [
            'message' => 'I have mild discomfort.',
        ], ['Accept' => 'text/event-stream']);

        $response->assertOk();
        $this->assertStringContainsString(
            'Has anything made the discomfort better?',
            $response->streamedContent(),
        );
        Http::assertSentCount(2);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://example.test/models/missing-primary:generate-content');
        Http::assertSent(fn ($request): bool => $request->url() === 'https://example.test/models/stable-fallback:generate-content');
        $this->assertDatabaseHas('ai_request_events', [
            'feature' => 'consultant_chat',
            'model' => 'stable-fallback',
            'outcome' => 'SUCCEEDED',
            'attempt_count' => 2,
        ]);
    }

    public function test_missing_models_return_a_specific_error_without_opening_the_provider_circuit(): void
    {
        config()->set([
            'medipath.assessment.gemini.api_key' => 'test-key',
            'medipath.assessment.gemini.consultant_model' => 'missing-primary',
            'medipath.assessment.gemini.consultant_fallback_model' => 'missing-fallback',
            'medipath.assessment.gemini.consultant_endpoint' => 'https://example.test/models/{model}:generate-content',
            'medipath.assessment.gemini.retry_delay_ms' => 0,
            'medipath.assessment.gemini.circuit_failure_threshold' => 1,
        ]);
        Http::fake(fn () => Http::response([], 404));

        $firstResponse = $this->post('/consultant/chat/stream', [
            'message' => 'I have mild discomfort.',
        ], ['Accept' => 'text/event-stream']);
        $secondResponse = $this->post('/consultant/chat/stream', [
            'message' => 'I still have mild discomfort.',
        ], ['Accept' => 'text/event-stream']);

        $this->assertStringContainsString(
            'The AI models are temporarily unavailable.',
            $firstResponse->streamedContent(),
        );
        $this->assertStringContainsString(
            'The AI models are temporarily unavailable.',
            $secondResponse->streamedContent(),
        );
        Http::assertSentCount(4);
    }
}
