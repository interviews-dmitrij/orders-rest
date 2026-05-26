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
     * Regression guard for the wire-format contract: the normalizer must not pad scale-zero
     * values to scale 2, strip trailing zeros, or coerce negatives — the original `MONEY_SCALE`
     * implementation did all of these and the bug was invisible because every test happened
     * to use scale-2 inputs already.
     *
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function scalePreservingPayloads(): iterable
    {
        yield 'scale zero stays scale zero' => ['499', '499'];
        yield 'scale two preserved as is' => ['1299.99', '1299.99'];
        yield 'scale five preserved' => ['10.51119', '10.51119'];
        yield 'negative scale two preserved' => ['-5.50', '-5.50'];
    }

    #[DataProvider('scalePreservingPayloads')]
    public function testNormalizeEmitsBigDecimalAtItsNativeScale(string $input, string $expected): void
    {
        $result = $this->normalizer->normalize(BigDecimal::of($input));

        self::assertSame($expected, $result);
    }

    #[DataProvider('scalePreservingPayloads')]
    public function testDenormalizeReturnsBigDecimalAtItsNativeScale(string $input, string $expected): void
    {
        $result = $this->normalizer->denormalize($input, BigDecimal::class);

        self::assertSame($expected, (string) $result);
    }

    /**
     * PostgreSQL drivers and Symfony's JSON decoder return whole-number wire payloads as PHP
     * integers, not strings, so the denormalizer must accept both. Anything else (float, bool,
     * array, null) is out of the wire contract and must surface as a 422-mappable failure.
     */
    public function testDenormalizeAcceptsPhpInteger(): void
    {
        $result = $this->normalizer->denormalize(499, BigDecimal::class);

        self::assertSame('499', (string) $result);
    }

    /**
     * @return iterable<string, array{0: mixed}>
     */
    public static function nonNumericInputs(): iterable
    {
        yield 'float — out of wire contract (totalValue declared as string)' => [1.5];
        yield 'boolean' => [true];
        yield 'array' => [['nested' => 'object']];
        yield 'null' => [null];
    }

    #[DataProvider('nonNumericInputs')]
    public function testDenormalizeRejectsNonStringIntegerInputs(mixed $input): void
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage('numeric string or integer');

        $this->normalizer->denormalize($input, BigDecimal::class);
    }

    public function testDenormalizeRejectsMalformedNumericString(): void
    {
        $this->expectException(NotNormalizableValueException::class);

        $this->normalizer->denormalize('not-a-decimal', BigDecimal::class);
    }

    public function testAdvertisesBigDecimalToSerializerChain(): void
    {
        self::assertTrue($this->normalizer->supportsNormalization(BigDecimal::of('1')));
        self::assertFalse($this->normalizer->supportsNormalization('1'));
        self::assertFalse($this->normalizer->supportsNormalization(new stdClass()));
        self::assertTrue($this->normalizer->supportsDenormalization('1.99', BigDecimal::class));
        self::assertFalse($this->normalizer->supportsDenormalization('1.99', stdClass::class));
        self::assertSame([BigDecimal::class => true], $this->normalizer->getSupportedTypes(null));
    }
}
