<?php

namespace App\Services\Verification;

use App\Contracts\PhoneVerificationSender;

class LocalPhoneVerificationSender implements PhoneVerificationSender
{
    public function send(string $phoneNumber, string $code): void
    {
        if (app()->isProduction()) {
            throw new \RuntimeException(
                'The local phone verification driver cannot be used in production. Configure an SMS provider first.',
            );
        }
    }
}
