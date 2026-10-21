<?php

namespace App\Services\Verification;

use App\Contracts\PhoneVerificationSender;

final class DemoPhoneVerificationSender implements PhoneVerificationSender
{
    public function send(string $phoneNumber, string $code): void
    {
        // Demo mode intentionally displays the code in the application instead
        // of sending it through an external SMS provider.
    }
}
