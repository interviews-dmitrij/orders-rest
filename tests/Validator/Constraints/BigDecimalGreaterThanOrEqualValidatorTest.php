<?php

declare(strict_types=1);

namespace App\Tests\Validator\Constraints;

use App\Validator\Constraints\BigDecimalGreaterThanOrEqual;
use App\Validator\Constraints\BigDecimalGreaterThanOrEqualValidator;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<BigDecimalGreaterThanOrEqualValidator>
 */
final class BigDecimalGreaterThanOrEqualValidatorTest extends ConstraintValidatorTestCase
{
    public function testNullValueProducesNoViolation(): void
    {
        $this->validator->validate(null, new BigDecimalGreaterThanOrEqual('0'));

        $this->assertNoViolation();
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function valuesAtOrAboveThreshold(): iterable
    {
        yield 'equal to threshold' => ['0', '0'];
        yield 'one above threshold' => ['1', '0'];
        yield 'large value above zero threshold' => ['9999999999.99', '0'];
        yield 'fractional above threshold' => ['0.01', '0'];
        yield 'value matches positive threshold' => ['100', '100'];
        yield 'value above positive threshold' => ['100.01', '100'];
    }

    #[DataProvider('valuesAtOrAboveThreshold')]
    public function testValueAtOrAboveThresholdProducesNoViolation(string $value, string $threshold): void
    {
        $this->validator->validate(BigDecimal::of($value), new BigDecimalGreaterThanOrEqual($threshold));

        $this->assertNoViolation();
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function valuesBelowThreshold(): iterable
    {
        yield 'negative integer below zero' => ['-1', '0'];
        yield 'negative decimal below zero' => ['-0.01', '0'];
        yield 'large negative below zero' => ['-9999999999.99', '0'];
        yield 'value below positive threshold' => ['99.99', '100'];
    }

    #[DataProvider('valuesBelowThreshold')]
    public function testValueBelowThresholdRaisesViolation(string $value, string $threshold): void
    {
        $this->validator->validate(BigDecimal::of($value), new BigDecimalGreaterThanOrEqual($threshold));

        $this->buildViolation('This value should be greater than or equal to {{ compared_value }}.')
            ->setParameter('{{ compared_value }}', $threshold)
            ->setCode(BigDecimalGreaterThanOrEqual::LESS_THAN_THRESHOLD_ERROR)
            ->assertRaised();
    }

    public function testCustomMessageOverridesDefault(): void
    {
        $constraint = new BigDecimalGreaterThanOrEqual(value: '0', message: 'Negative is forbidden.');

        $this->validator->validate(BigDecimal::of('-5'), $constraint);

        $this->buildViolation('Negative is forbidden.')
            ->setParameter('{{ compared_value }}', '0')
            ->setCode(BigDecimalGreaterThanOrEqual::LESS_THAN_THRESHOLD_ERROR)
            ->assertRaised();
    }

    public function testWrongValueTypeThrowsUnexpectedValueException(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $this->validator->validate('not-a-big-decimal', new BigDecimalGreaterThanOrEqual('0'));
    }

    public function testWrongConstraintTypeThrowsUnexpectedTypeException(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate(BigDecimal::of('1'), new NotNull());
    }

    protected function createValidator(): BigDecimalGreaterThanOrEqualValidator
    {
        return new BigDecimalGreaterThanOrEqualValidator();
    }
}
