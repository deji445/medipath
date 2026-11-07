<?php

namespace Tests\Unit\Services\Consultant;

use App\Services\Consultant\ConsultantHandoffBuilder;
use Tests\TestCase;

class ConsultantHandoffBuilderTest extends TestCase
{
    public function test_it_builds_a_structured_handoff_from_the_conversation(): void
    {
        $handoff = app(ConsultantHandoffBuilder::class)->build([
            ['role' => 'user', 'content' => 'My feet hurt.'],
            ['role' => 'assistant', 'content' => 'When did it start?'],
            ['role' => 'user', 'content' => 'It has been a week.'],
            ['role' => 'assistant', 'content' => 'How severe is it?'],
            ['role' => 'user', 'content' => 'Moderate.'],
        ]);

        $this->assertSame('My feet hurt. It has been a week. Moderate.', $handoff['main_complaint']);
        $this->assertSame(3, $handoff['message_count']);
        $this->assertContains('onset_duration', $handoff['answered_topics']);
        $this->assertContains('severity', $handoff['answered_topics']);
        $this->assertSame('It has been a week.', $handoff['facts'][0]['answer']);
        $this->assertSame('Moderate.', $handoff['facts'][1]['answer']);
    }
}
