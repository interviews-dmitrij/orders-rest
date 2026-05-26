<?php

declare(strict_types=1);

namespace App\Tests\Serializer;

use App\Serializer\BigDecimalNormalizer;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function normalizationRoundtrip(): iterable
    {
        yield 'two fractional digits' => ['1299.99', '1299.99'];
        yield 'no fractional digits preserved as scale zero' => ['499', '499'];
        yield 'single fractional digit preserved' => ['1.5', '1.5'];
        yield 'four fractional digits preserved' => ['0.0001', '0.0001'];
        yield 'negative value preserved' => ['-5.50', '-5.50'];
    }

    #[DataProvider('normalizationRoundtrip')]
    public function testNormalizePreservesNativeScale(string $input, string $expected): void
    {
        $result = $this->normalizer->normalize(BigDecimal::of($input));

        self::assertSame($expected, $result);
    }

    public function testSupportsNormalizationForBigDecimal(): void
    {
        self::assertTrue($this->normalizer->supportsNormalization(BigDecimal::of('1')));
        self::assertFalse($this->normalizer->supportsNormalization('1'));
        self::assertFalse($this->normalizer->supportsNormalization(new stdClass()));
    }

    /**
     * @return iterable<string, array{0: string|int, 1: string}>
     */
    public static function denormalizationRoundtrip(): iterable
    {
        yield 'numeric string two fractional digits' => ['1299.99', '1299.99'];
        yield 'numeric string four fractional digits' => ['0.1234', '0.1234'];
        yield 'numeric string no fractional digits' => ['499', '499'];
        yield 'php integer' => [499, '499'];
        yield 'negative decimal string' => ['-5.50', '-5.50'];
    }

    #[DataProvider('denormalizationRoundtrip')]
    public function testDenormalizePreservesNativeScale(string|int $input, string $expected): void
    {
        $result = $this->normalizer->denormalize($input, BigDecimal::class);

        self::assertSame($expected, (string) $result);
    }

    public function testDenormalizationThrowsOnMalformedString(): void
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->normalizer->denormalize('not-a-decimal', BigDecimal::class);
    }

    /**
     * @return iterable<string, array{0: mixed, 1: string}>
     */
    public static function rejectedInputsWithExpectedMessage(): iterable
    {
        yield 'array input' => [['nested' => 'object'], 'numeric string or integer'];
        yield 'float input' => [1.5, 'numeric string or integer'];
        yield 'boolean input' => [true, 'numeric string or integer'];
        yield 'null input' => [null, 'numeric string or integer'];
    }

    #[DataProvider('rejectedInputsWithExpectedMessage')]
    public function testDenormalizationRejectsInputWithExpectedMessage(mixed $input, string $messageFragment): void
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage($messageFragment);

        $this->normalizer->denormalize($input, BigDecimal::class);
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
