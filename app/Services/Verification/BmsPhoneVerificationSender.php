<?php

namespace App\Services\Verification;

use App\Contracts\PhoneVerificationSender;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class BmsPhoneVerificationSender implements PhoneVerificationSender
{
    public function send(string $phoneNumber, string $code): void
    {
        $endpoint = (string) config('medipath.phone_verification.bms.endpoint');
        $apiKey = (string) config('medipath.phone_verification.bms.api_key');
        $senderId = (string) config('medipath.phone_verification.bms.sender_id');

        if ($endpoint === '' || $apiKey === '' || $senderId === '') {
            throw new PhoneVerificationDeliveryException('BMS_NOT_CONFIGURED');
        }

        for ($attempt = 1; $attempt <= 2; $attempt++) {
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

                if ($attempt === 1) {
                    continue;
                }

                // BMS authenticates through the query string. Do not retain the
                // provider exception because its URL may contain the API key.
                throw new PhoneVerificationDeliveryException('BMS_CONNECTION_FAILED');
            }

            if ($this->wasAccepted($response)) {
                return;
            }

            Log::warning('BMS verification delivery failed.', [
                'status' => $response->status(),
                'provider_code' => $this->safeProviderCode($response),
                'attempt' => $attempt,
            ]);

            if ($attempt === 1 && $this->isRetryable($response)) {
                continue;
            }

            $errorCode = $response->successful()
                ? 'BMS_REQUEST_REJECTED'
                : 'BMS_HTTP_'.$response->status();

            throw new PhoneVerificationDeliveryException($errorCode);
        }

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

    private function isRetryable(Response $response): bool
    {
        return in_array($response->status(), [408, 429], true) || $response->serverError();
    }
}
