<?php

namespace App\Services\Ai;

use RuntimeException;

final class AiCircuitOpenException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('GEMINI_CIRCUIT_OPEN');
    }
}
