<?php

namespace App\Services\Assessment;

use App\Enums\RedFlagCode;

final class RedFlagDetector
{
    /**
     * @param  array<string, bool>  $answers
     * @return array<int, RedFlagCode>
     */
    public function detect(array $answers): array
    {
        return array_values(array_filter(
            RedFlagCode::cases(),
            fn (RedFlagCode $code): bool => ($answers[$code->answerKey()] ?? false) === true,
        ));
    }

    /** @param array<string, bool> $answers */
    public function hasEmergency(array $answers): bool
    {
        return $this->detect($answers) !== [];
    }
}
