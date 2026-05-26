<?php

declare(strict_types=1);

namespace App\Tests\Validator\Constraints;

use App\Validator\Constraints\BigDecimalMaxScale;
use App\Validator\Constraints\BigDecimalMaxScaleValidator;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<BigDecimalMaxScaleValidator>
 */
final class BigDecimalMaxScaleValidatorTest extends ConstraintValidatorTestCase
{
    public function testNullValueProducesNoViolation(): void
    {
        $this->validator->validate(null, new BigDecimalMaxScale(5));

        $this->assertNoViolation();
    }

    /**
     * @return iterable<string, array{0: string, 1: int}>
     */
    public static function valuesAtOrBelowMaxScale(): iterable
    {
        yield 'scale zero against max five' => ['1299', 5];
        yield 'scale one against max five' => ['1.5', 5];
        yield 'scale four against max five' => ['10.5111', 5];
        yield 'scale five against max five' => ['10.51119', 5];
        yield 'scale zero against max zero' => ['42', 0];
    }

    #[DataProvider('valuesAtOrBelowMaxScale')]
    public function testValueAtOrBelowMaxScaleProducesNoViolation(string $value, int $max): void
    {
        $this->validator->validate(BigDecimal::of($value), new BigDecimalMaxScale($max));

        $this->assertNoViolation();
    }

    /**
     * @return iterable<string, array{0: string, 1: int}>
     */
    public static function valuesAboveMaxScale(): iterable
    {
        yield 'scale six against max five' => ['1.234567', 5];
        yield 'scale seven against max five' => ['1.2345678', 5];
        yield 'scale one against max zero' => ['1.5', 0];
    }

    #[DataProvider('valuesAboveMaxScale')]
    public function testValueAboveMaxScaleRaisesViolation(string $value, int $max): void
    {
        $this->validator->validate(BigDecimal::of($value), new BigDecimalMaxScale($max));

        $this->buildViolation('This value should have at most {{ max }} fractional digits.')
            ->setParameter('{{ max }}', (string) $max)
            ->setCode('big-decimal-max-scale')
            ->assertRaised();
    }

    public function testCustomMessageOverridesDefault(): void
    {
        $constraint = new BigDecimalMaxScale(max: 5, message: 'Too many digits after the decimal point.');

        $this->validator->validate(BigDecimal::of('1.234567'), $constraint);

        $this->buildViolation('Too many digits after the decimal point.')
            ->setParameter('{{ max }}', '5')
            ->setCode('big-decimal-max-scale')
            ->assertRaised();
    }

    public function testWrongValueTypeThrowsUnexpectedValueException(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $this->validator->validate('1.234567', new BigDecimalMaxScale(5));
    }

    public function testWrongConstraintTypeThrowsUnexpectedTypeException(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate(BigDecimal::of('1'), new NotNull());
    }

    protected function createValidator(): BigDecimalMaxScaleValidator
    {
        return new BigDecimalMaxScaleValidator();
    }
}
