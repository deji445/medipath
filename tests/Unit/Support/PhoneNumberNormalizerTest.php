<?php

namespace Tests\Unit\Support;

use App\Support\PhoneNumberNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneNumberNormalizerTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function phoneNumbers(): array
    {
        return [
            'local Ghana number' => ['024 123 4567', '+233241234567'],
            'Ghana country code' => ['233-24-123-4567', '+233241234567'],
            'international number' => ['+44 7700 900123', '+447700900123'],
        ];
    }

    #[DataProvider('phoneNumbers')]
    public function test_it_normalizes_phone_numbers(string $input, string $expected): void
    {
        $this->assertSame($expected, (new PhoneNumberNormalizer)->normalize($input));
    }
}
