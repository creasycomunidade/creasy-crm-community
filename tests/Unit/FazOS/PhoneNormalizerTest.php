<?php

namespace Tests\Unit\FazOS;

use App\Modules\FazOS\Services\PhoneNormalizer;
use PHPUnit\Framework\TestCase;

class PhoneNormalizerTest extends TestCase
{
    public function test_it_normalizes_brazilian_and_international_phones(): void
    {
        $normalizer = new PhoneNormalizer;

        $this->assertSame('+5511999999999', $normalizer->normalize('(11) 99999-9999'));
        $this->assertSame('+5511999999999', $normalizer->normalize('+55 11 99999-9999'));
        $this->assertSame('+14155552671', $normalizer->normalize('+1 (415) 555-2671'));
        $this->assertNull($normalizer->normalize(''));
    }
}
