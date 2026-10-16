<?php

namespace App\Services\Consultant;

final readonly class ConsultantReply
{
    /**
     * @param  array<int, string>  $redFlags
     * @param  array<int, string>  $firstAidSteps
     */
    public function __construct(
        public string $content,
        public bool $emergency = false,
        public array $redFlags = [],
        public array $firstAidSteps = [],
    ) {}
}
