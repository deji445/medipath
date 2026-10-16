<?php

namespace App\Services\Verification;

use App\Contracts\PhoneVerificationSender;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class NaloPhoneVerificationSender implements PhoneVerificationSender
{
    public function send(string $phoneNumber, string $code): void
    {
        $endpoint = (string) config('medipath.phone_verification.nalo.endpoint');
        $authKey = (string) config('medipath.phone_verification.nalo.auth_key');
        $senderId = (string) config('medipath.phone_verification.nalo.sender_id');

        if ($endpoint === '' || $authKey === '' || $senderId === '') {
            throw new PhoneVerificationDeliveryException('NALO_NOT_CONFIGURED');
        }

        $lastConnectionException = null;

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $response = Http::acceptJson()
                    ->asJson()
                    ->connectTimeout((int) config('medipath.phone_verification.nalo.connect_timeout', 5))
                    ->timeout((int) config('medipath.phone_verification.nalo.timeout', 10))
                    ->post($endpoint, [
                        'key' => $authKey,
                        'msisdn' => ltrim($phoneNumber, '+'),
                        'message' => $this->message($code),
                        'sender_id' => $senderId,
                    ]);
            } catch (ConnectionException $exception) {
                $lastConnectionException = $exception;

                if ($attempt === 1) {
                    continue;
                }

                throw new PhoneVerificationDeliveryException('NALO_CONNECTION_FAILED', $exception);
            }

            if ($response->successful()) {
                return;
            }

            Log::warning('NALO verification delivery failed.', [
                'status' => $response->status(),
                'attempt' => $attempt,
            ]);

            if ($attempt === 1 && $this->isRetryable($response)) {
                continue;
            }

            throw new PhoneVerificationDeliveryException('NALO_HTTP_'.$response->status());
        }

        throw new PhoneVerificationDeliveryException('NALO_CONNECTION_FAILED', $lastConnectionException);
    }

    private function message(string $code): string
    {
        $minutes = max(1, (int) config('medipath.phone_verification.expiry_minutes', 10));

        return "Your MediPath verification code is {$code}. It expires in {$minutes} minutes. Do not share this code.";
    }

    private function isRetryable(Response $response): bool
    {
        return in_array($response->status(), [408, 429], true) || $response->serverError();
    }
}
