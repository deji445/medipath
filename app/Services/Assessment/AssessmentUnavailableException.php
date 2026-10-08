<?php

namespace App\Services\Assessment;

use RuntimeException;
use Throwable;

final class AssessmentUnavailableException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        ?Throwable $previous = null,
    ) {
        parent::__construct('The assessment provider could not return a safe, valid result.', 0, $previous);
    }
}
