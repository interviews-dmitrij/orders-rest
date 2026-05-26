<?php

declare(strict_types=1);

namespace App\Tests\Serializer;

use App\Serializer\BigDecimalNormalizer;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;

final class BigDecimalNormalizerTest extends TestCase
{
    private BigDecimalNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new BigDecimalNormalizer();
    }

    public function testNormalizesBigDecimalToScaleTwoString(): void
    {
        $result = $this->normalizer->normalize(BigDecimal::of('1299.99'));
        self::assertSame('1299.99', $result);
    }

    public function testNormalizesScaleZeroValueWithExplicitTwoFractionalDigits(): void
    {
        $result = $this->normalizer->normalize(BigDecimal::of('499'));
        self::assertSame('499.00', $result);
    }

    public function testSupportsNormalizationForBigDecimal(): void
    {
        self::assertTrue($this->normalizer->supportsNormalization(BigDecimal::of('1')));
        self::assertFalse($this->normalizer->supportsNormalization('1'));
        self::assertFalse($this->normalizer->supportsNormalization(new stdClass()));
    }

    public function testDenormalizesNumericStringToBigDecimalAtScaleTwo(): void
    {
        $result = $this->normalizer->denormalize('1299.99', BigDecimal::class);
        self::assertSame('1299.99', (string) $result);
    }

    public function testDenormalizesIntegerStringWithoutFractionalToScaleTwo(): void
    {
        $result = $this->normalizer->denormalize('499', BigDecimal::class);
        self::assertSame('499.00', (string) $result);
    }

    public function testDenormalizesIntegerToBigDecimalAtScaleTwo(): void
    {
        $result = $this->normalizer->denormalize(499, BigDecimal::class);
        self::assertSame('499.00', (string) $result);
    }

    public function testDenormalizationThrowsOnMalformedString(): void
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->normalizer->denormalize('not-a-decimal', BigDecimal::class);
    }

    public function testDenormalizationThrowsWhenInputHasMoreThanTwoFractionalDigits(): void
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->normalizer->denormalize('1.234', BigDecimal::class);
    }

    public function testDenormalizationRejectsNonScalarInput(): void
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->normalizer->denormalize(['nested' => 'object'], BigDecimal::class);
    }

    public function testSupportsDenormalizationForBigDecimal(): void
    {
        self::assertTrue($this->normalizer->supportsDenormalization('1.99', BigDecimal::class));
        self::assertFalse($this->normalizer->supportsDenormalization('1.99', stdClass::class));
    }

    public function testGetSupportedTypesAdvertisesBigDecimal(): void
    {
        self::assertSame(
            [BigDecimal::class => true],
            $this->normalizer->getSupportedTypes(null),
        );
    }
}
