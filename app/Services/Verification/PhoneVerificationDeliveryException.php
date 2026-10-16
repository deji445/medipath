<?php

namespace App\Services\Verification;

use RuntimeException;
use Throwable;

final class PhoneVerificationDeliveryException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        ?Throwable $previous = null,
    ) {
        parent::__construct('The verification message could not be delivered.', 0, $previous);
    }
}
