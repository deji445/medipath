<?php

namespace App\Services\Verification;

use App\Contracts\PhoneVerificationSender;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class BmsPhoneVerificationSender implements PhoneVerificationSender
{
    public function __construct(
        private readonly VerificationDeliveryRecorder $deliveryRecorder,
    ) {}

    public function send(string $phoneNumber, string $code): void
    {
        $startedAt = hrtime(true);
        $endpoint = (string) config('medipath.phone_verification.bms.endpoint');
        $apiKey = (string) config('medipath.phone_verification.bms.api_key');
        $senderId = (string) config('medipath.phone_verification.bms.sender_id');

        if ($endpoint === '' || $apiKey === '' || $senderId === '') {
            $this->deliveryRecorder->recordDeferred(
                'FAILED',
                $startedAt,
                0,
                errorCode: 'BMS_NOT_CONFIGURED',
            );

            throw new PhoneVerificationDeliveryException('BMS_NOT_CONFIGURED');
        }

        $maxAttempts = max(1, min((int) config('medipath.phone_verification.bms.max_attempts', 2), 3));

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $response = Http::acceptJson()
                    ->asJson()
                    ->connectTimeout((int) config('medipath.phone_verification.bms.connect_timeout', 5))
                    ->timeout((int) config('medipath.phone_verification.bms.timeout', 10))
                    ->withQueryParameters(['key' => $apiKey])
                    ->post($endpoint, [
                        'recipient' => [$this->ghanaLocalNumber($phoneNumber)],
                        'sender' => $senderId,
                        'message' => $this->message($code),
                        'is_schedule' => false,
                        'schedule_date' => '',
                        'sms_type' => 'otp',
                    ]);
            } catch (ConnectionException $exception) {
                Log::warning('BMS verification connection failed.', [
                    'exception' => $exception::class,
                    'attempt' => $attempt,
                ]);

                if ($attempt < $maxAttempts) {
                    $this->pauseBeforeRetry();

                    continue;
                }

                $this->deliveryRecorder->recordDeferred(
                    'FAILED',
                    $startedAt,
                    $attempt,
                    errorCode: 'BMS_CONNECTION_FAILED',
                );

                // BMS authenticates through the query string. Do not retain the
                // provider exception because its URL may contain the API key.
                throw new PhoneVerificationDeliveryException('BMS_CONNECTION_FAILED');
            }

            if ($this->wasAccepted($response)) {
                $this->deliveryRecorder->recordDeferred(
                    'ACCEPTED',
                    $startedAt,
                    $attempt,
                    $this->safeProviderReference($response),
                    $this->safeProviderCode($response),
                    $response->status(),
                );

                return;
            }

            Log::warning('BMS verification delivery failed.', [
                'status' => $response->status(),
                'provider_code' => $this->safeProviderCode($response),
                'attempt' => $attempt,
            ]);

            if ($attempt < $maxAttempts && $this->isRetryable($response)) {
                $this->pauseBeforeRetry();

                continue;
            }

            $errorCode = $response->successful()
                ? 'BMS_REQUEST_REJECTED'
                : 'BMS_HTTP_'.$response->status();

            $this->deliveryRecorder->recordDeferred(
                'FAILED',
                $startedAt,
                $attempt,
                $this->safeProviderReference($response),
                $this->safeProviderCode($response),
                $response->status(),
                $errorCode,
            );

            throw new PhoneVerificationDeliveryException($errorCode);
        }

        $this->deliveryRecorder->recordDeferred(
            'FAILED',
            $startedAt,
            $maxAttempts,
            errorCode: 'BMS_CONNECTION_FAILED',
        );

        throw new PhoneVerificationDeliveryException('BMS_CONNECTION_FAILED');
    }

    private function ghanaLocalNumber(string $phoneNumber): string
    {
        $digits = ltrim($phoneNumber, '+');

        return str_starts_with($digits, '233')
            ? '0'.substr($digits, 3)
            : $digits;
    }

    private function message(string $code): string
    {
        $minutes = max(1, (int) config('medipath.phone_verification.expiry_minutes', 10));

        return "Your MediPath verification code is {$code}. It expires in {$minutes} minutes. Do not share this code.";
    }

    private function wasAccepted(Response $response): bool
    {
        return $response->successful()
            && $response->json('status') === 'success'
            && (string) $response->json('code') === '2000';
    }

    private function safeProviderCode(Response $response): ?string
    {
        $code = $response->json('code');

        return is_string($code) && preg_match('/^[A-Za-z0-9_-]{1,40}$/', $code) === 1
            ? $code
            : null;
    }

    private function safeProviderReference(Response $response): ?string
    {
        $reference = $response->json('summary._id');

        return is_string($reference) && preg_match('/^[A-Za-z0-9_-]{1,100}$/', $reference) === 1
            ? $reference
            : null;
    }

    private function isRetryable(Response $response): bool
    {
        return in_array($response->status(), [408, 429], true) || $response->serverError();
    }

    private function pauseBeforeRetry(): void
    {
        $milliseconds = max(0, min(
            (int) config('medipath.phone_verification.bms.retry_delay_ms', 500),
            5000,
        ));

        if ($milliseconds > 0) {
            usleep($milliseconds * 1000);
        }
    }
}
