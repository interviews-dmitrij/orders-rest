<?php

declare(strict_types=1);

namespace App\Tests\Doctrine\Type;

use App\Doctrine\Type\BigDecimalType;
use Brick\Math\BigDecimal;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BigDecimalTypeTest extends TestCase
{
    private BigDecimalType $type;
    private PostgreSQLPlatform $platform;

    protected function setUp(): void
    {
        if (!Type::hasType(BigDecimalType::NAME)) {
            Type::addType(BigDecimalType::NAME, BigDecimalType::class);
        }
        $type = Type::getType(BigDecimalType::NAME);
        self::assertInstanceOf(BigDecimalType::class, $type);
        $this->type = $type;
        $this->platform = new PostgreSQLPlatform();
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function representativeValues(): iterable
    {
        yield 'typical money value' => ['1299.99'];
        yield 'scale-zero integer-style value' => ['499'];
        yield 'top of the configured column range' => ['999999999999999999999999999999999.99999'];
        yield 'fractional with leading zero' => ['0.00001'];
    }

    #[DataProvider('representativeValues')]
    public function testRoundTripsBigDecimalLosslessly(string $value): void
    {
        $original = BigDecimal::of($value);

        $db = $this->type->convertToDatabaseValue($original, $this->platform);
        $restored = $this->type->convertToPHPValue($db, $this->platform);

        self::assertSame((string) $original, (string) $restored);
    }

    public function testNullRoundTripsAsNull(): void
    {
        self::assertNull($this->type->convertToDatabaseValue(null, $this->platform));
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testAcceptsIntegerFromDriverForWholeNumberColumns(): void
    {
        $result = $this->type->convertToPHPValue(1299, $this->platform);

        self::assertInstanceOf(BigDecimal::class, $result);
        self::assertSame('1299', (string) $result);
    }

    public function testSqlDeclarationDelegatesToPlatformDecimal(): void
    {
        $sql = $this->type->getSQLDeclaration(['precision' => 38, 'scale' => 5], $this->platform);

        self::assertSame('NUMERIC(38, 5)', $sql);
    }

    public function testConvertToDatabaseValueRejectsNonBigDecimal(): void
    {
        $this->expectException(InvalidType::class);

        $this->type->convertToDatabaseValue('not-a-bigdecimal', $this->platform);
    }

    public function testConvertToPhpValueWrapsBrickFailureInValueNotConvertible(): void
    {
        $this->expectException(ValueNotConvertible::class);

        $this->type->convertToPHPValue('not-a-decimal', $this->platform);
    }
}
