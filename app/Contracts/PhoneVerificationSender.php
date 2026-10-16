<?php

namespace App\Contracts;

interface PhoneVerificationSender
{
    public function send(string $phoneNumber, string $code): void;
}
