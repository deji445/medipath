<?php

namespace App\Services\Consultant;

use Illuminate\Support\Collection;

final class ConsultantHandoffBuilder
{
    /**
     * @param  array<int, mixed>  $messages
     * @return array{
     *     main_complaint: string,
     *     message_count: int,
     *     started_at: string,
     *     transcript: array<int, array{role: string, content: string}>,
     *     answered_topics: array<int, string>,
     *     facts: array<int, array{topic: string, label: string, answer: string}>
     * }
     */
    public function build(array $messages): array
    {
        $transcript = collect(array_slice($messages, -12))
            ->filter(fn (mixed $message): bool => is_array($message)
                && in_array($message['role'] ?? null, ['user', 'assistant'], true)
                && is_string($message['content'] ?? null)
                && trim($message['content']) !== '')
            ->map(fn (array $message): array => [
                'role' => (string) $message['role'],
                'content' => mb_substr(trim($message['content']), 0, 2000),
            ])
            ->values();

        $patientMessages = $transcript
            ->where('role', 'user')
            ->pluck('content')
            ->map(fn (string $message): string => trim($message))
            ->filter()
            ->values();

        $facts = $this->facts($transcript);
        $answeredTopics = $facts->pluck('topic')
            ->merge($this->topicsFromPatientText($patientMessages->implode(' ')))
            ->unique()
            ->values();

        return [
            'main_complaint' => mb_substr($patientMessages->implode(' '), 0, 500),
            'message_count' => $patientMessages->count(),
            'started_at' => now()->toIso8601String(),
            'transcript' => $transcript->all(),
            'answered_topics' => $answeredTopics->all(),
            'facts' => $facts->all(),
        ];
    }

    /**
     * @param  Collection<int, array{role: string, content: string}>  $transcript
     * @return Collection<int, array{topic: string, label: string, answer: string}>
     */
    private function facts(Collection $transcript): Collection
    {
        $facts = collect();
        $pendingTopic = null;

        foreach ($transcript as $message) {
            if ($message['role'] === 'assistant') {
                $pendingTopic = $this->topicFromQuestion($message['content']);

                continue;
            }

            if ($pendingTopic === null) {
                continue;
            }

            $facts->push([
                'topic' => $pendingTopic,
                'label' => $this->topicLabel($pendingTopic),
                'answer' => mb_substr($message['content'], 0, 240),
            ]);
            $pendingTopic = null;
        }

        return $facts
            ->reverse()
            ->unique('topic')
            ->reverse()
            ->values();
    }

    private function topicFromQuestion(string $question): ?string
    {
        $question = mb_strtolower($question);

        return match (true) {
            preg_match('/\b(when|how long|start(?:ed)?|begin|duration|since)\b/u', $question) === 1 => 'onset_duration',
            preg_match('/\b(where|location|which part|what part)\b/u', $question) === 1 => 'location',
            preg_match('/\b(sharp|dull|cramp|burn|ache|what does|feel like|type of pain)\b/u', $question) === 1 => 'character',
            preg_match('/\b(severe|severity|scale|rate|how bad|intensity)\b/u', $question) === 1 => 'severity',
            preg_match('/\b(other symptoms?|along with|nausea|vomit|fever|dizz|bloat)\b/u', $question) === 1 => 'associated_symptoms',
            preg_match('/\b(injur|fall|hit|activity|trigger|what happened|cause)\b/u', $question) === 1 => 'trigger_injury',
            preg_match('/\b(activity|activities|walk|sleep|work|daily|manage)\b/u', $question) === 1 => 'effect_on_activity',
            preg_match('/\b(breath|breathing)\b/u', $question) === 1 => 'breathing',
            preg_match('/\b(blood|bleed|bleeding)\b/u', $question) === 1 => 'bleeding',
            default => null,
        };
    }

    /** @return Collection<int, string> */
    private function topicsFromPatientText(string $text): Collection
    {
        $text = mb_strtolower($text);
        $topics = collect();

        if (preg_match('/\b(since|today|yesterday|tonight|week|day|hour|minute|month|year)s?\b/u', $text) === 1) {
            $topics->push('onset_duration');
        }

        if (preg_match('/\b(mild|moderate|severe|worse|worst|[0-9]{1,2}\s*(?:\/|out of)\s*10)\b/u', $text) === 1) {
            $topics->push('severity');
        }

        if (preg_match('/\b(sharp|dull|cramp(?:ing)?|burn(?:ing)?|ache|throbbing|stabbing)\b/u', $text) === 1) {
            $topics->push('character');
        }

        if (preg_match('/\b(breath|breathing|shortness of breath)\b/u', $text) === 1) {
            $topics->push('breathing');
        }

        if (preg_match('/\b(blood|bleed|bleeding)\b/u', $text) === 1) {
            $topics->push('bleeding');
        }

        return $topics;
    }

    private function topicLabel(string $topic): string
    {
        return match ($topic) {
            'onset_duration' => 'When it started',
            'location' => 'Where it is',
            'character' => 'What it feels like',
            'severity' => 'How severe it is',
            'associated_symptoms' => 'Other symptoms',
            'trigger_injury' => 'Possible trigger or injury',
            'effect_on_activity' => 'Effect on normal activity',
            'breathing' => 'Breathing',
            'bleeding' => 'Bleeding',
            default => 'Captured detail',
        };
    }
}
