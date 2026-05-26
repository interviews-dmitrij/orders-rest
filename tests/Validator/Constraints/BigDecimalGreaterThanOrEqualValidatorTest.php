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
    public function testNullValuePassesPerSymfonyConvention(): void
    {
        $this->validator->validate(null, new BigDecimalGreaterThanOrEqual('0'));

        $this->assertNoViolation();
    }

    public function testRejectsValueBelowThresholdWithViolationCarryingThresholdAndCode(): void
    {
        $this->validator->validate(BigDecimal::of('-0.00001'), new BigDecimalGreaterThanOrEqual('0'));

        $this->buildViolation('This value should be greater than or equal to {{ compared_value }}.')
            ->setParameter('{{ compared_value }}', '0')
            ->setCode(BigDecimalGreaterThanOrEqual::LESS_THAN_THRESHOLD_ERROR)
            ->assertRaised();
    }

    public function testAcceptsValueExactlyAtThreshold(): void
    {
        $this->validator->validate(BigDecimal::of('0.00'), new BigDecimalGreaterThanOrEqual('0'));

        $this->assertNoViolation();
    }

    /**
     * Guards against a naive `$a >= $b` PHP comparison: `BigDecimal::of('10') >= BigDecimal::of('9.99')`
     * via PHP's spaceship-on-objects compares internal `value` strings lexicographically and yields
     * `'10' >= '999'` → false. The validator must use `BigDecimal::isLessThan()` instead, otherwise
     * scale-mismatched but mathematically larger values get incorrectly rejected.
     *
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function scaleMismatchedComparisons(): iterable
    {
        yield 'integer-scale value vs higher-scale threshold' => ['10', '9.99'];
        yield 'short integer value vs longer fractional threshold' => ['1000', '999.99999'];
        yield 'value with trailing zeros vs unscaled threshold' => ['100.00', '100'];
    }

    #[DataProvider('scaleMismatchedComparisons')]
    public function testHandlesScaleMismatchedComparisonsNumerically(string $value, string $threshold): void
    {
        $this->validator->validate(BigDecimal::of($value), new BigDecimalGreaterThanOrEqual($threshold));

        $this->assertNoViolation();
    }

    public function testRejectsValueBelowThresholdAcrossScales(): void
    {
        $this->validator->validate(BigDecimal::of('99.99999'), new BigDecimalGreaterThanOrEqual('100'));

        $this->buildViolation('This value should be greater than or equal to {{ compared_value }}.')
            ->setParameter('{{ compared_value }}', '100')
            ->setCode(BigDecimalGreaterThanOrEqual::LESS_THAN_THRESHOLD_ERROR)
            ->assertRaised();
    }

    public function testThrowsWhenValidatedValueIsNotBigDecimal(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $this->validator->validate('5', new BigDecimalGreaterThanOrEqual('0'));
    }

    public function testThrowsWhenDispatchedWithWrongConstraint(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate(BigDecimal::of('1'), new NotNull());
    }

    protected function createValidator(): BigDecimalGreaterThanOrEqualValidator
    {
        return new BigDecimalGreaterThanOrEqualValidator();
    }
}
