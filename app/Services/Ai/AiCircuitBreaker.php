<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Cache;

final class AiCircuitBreaker
{
    public function beforeRequest(string $provider = 'gemini'): void
    {
        $state = Cache::get($this->stateKey($provider));

        if (! is_array($state) || ! is_int($state['opened_until'] ?? null)) {
            return;
        }

        if ($state['opened_until'] > time()) {
            throw new AiCircuitOpenException;
        }

        // Once the cooldown expires, only one request is allowed to test recovery.
        if (! Cache::add($this->probeKey($provider), true, $this->cooldownSeconds())) {
            throw new AiCircuitOpenException;
        }
    }

    public function recordSuccess(string $provider = 'gemini'): void
    {
        Cache::forget($this->stateKey($provider));
        Cache::forget($this->probeKey($provider));
    }

    public function recordFailure(string $errorCode, string $provider = 'gemini'): void
    {
        if (! $this->countsAsProviderFailure($errorCode)) {
            return;
        }

        $now = time();

        if (Cache::has($this->probeKey($provider))) {
            $this->open($provider, $now);
            Cache::forget($this->probeKey($provider));

            return;
        }

        $state = Cache::get($this->stateKey($provider));
        $windowStartedAt = $now;
        $failureCount = 0;

        if (is_array($state)) {
            if (is_int($state['window_started_at'] ?? null)) {
                $windowStartedAt = $state['window_started_at'];
            }

            if (is_int($state['failure_count'] ?? null)) {
                $failureCount = $state['failure_count'];
            }
        }

        if (($now - $windowStartedAt) >= $this->failureWindowSeconds()) {
            $windowStartedAt = $now;
            $failureCount = 0;
        }

        $failureCount++;

        if ($failureCount >= $this->failureThreshold()) {
            $this->open($provider, $now, $failureCount, $windowStartedAt);

            return;
        }

        Cache::put($this->stateKey($provider), [
            'failure_count' => $failureCount,
            'window_started_at' => $windowStartedAt,
            'opened_until' => null,
        ], $this->stateTtlSeconds());
    }

    private function open(
        string $provider,
        int $now,
        ?int $failureCount = null,
        ?int $windowStartedAt = null,
    ): void {
        Cache::put($this->stateKey($provider), [
            'failure_count' => $failureCount ?? $this->failureThreshold(),
            'window_started_at' => $windowStartedAt ?? $now,
            'opened_until' => $now + $this->cooldownSeconds(),
        ], $this->stateTtlSeconds());
    }

    private function countsAsProviderFailure(string $errorCode): bool
    {
        return ! in_array($errorCode, [
            'ASSESSMENT_INPUT_INVALID',
            'GEMINI_API_KEY_MISSING',
            'GEMINI_CIRCUIT_OPEN',
            'GEMINI_HTTP_400',
            'GEMINI_HTTP_401',
            'GEMINI_HTTP_403',
        ], true);
    }

    private function failureThreshold(): int
    {
        return max(1, (int) config('medipath.assessment.gemini.circuit_failure_threshold', 3));
    }

    private function failureWindowSeconds(): int
    {
        return max(1, (int) config('medipath.assessment.gemini.circuit_failure_window_seconds', 120));
    }

    private function cooldownSeconds(): int
    {
        return max(1, (int) config('medipath.assessment.gemini.circuit_cooldown_seconds', 60));
    }

    private function stateTtlSeconds(): int
    {
        return max($this->failureWindowSeconds(), $this->cooldownSeconds()) * 2;
    }

    private function stateKey(string $provider): string
    {
        return 'medipath:ai-circuit:'.strtolower($provider).':state';
    }

    private function probeKey(string $provider): string
    {
        return 'medipath:ai-circuit:'.strtolower($provider).':probe';
    }
}
