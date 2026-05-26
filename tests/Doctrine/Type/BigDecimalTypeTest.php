<?php

declare(strict_types=1);

namespace App\Tests\Doctrine\Type;

use App\Doctrine\Type\BigDecimalType;
use Brick\Math\BigDecimal;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
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

    public function testConvertsBigDecimalToDatabaseString(): void
    {
        $result = $this->type->convertToDatabaseValue(BigDecimal::of('1299.99'), $this->platform);
        self::assertSame('1299.99', $result);
    }

    public function testConvertsDatabaseStringToBigDecimal(): void
    {
        $result = $this->type->convertToPHPValue('1299.99', $this->platform);
        self::assertInstanceOf(BigDecimal::class, $result);
        self::assertSame('1299.99', (string) $result);
    }

    public function testNullPassesThroughBothDirections(): void
    {
        self::assertNull($this->type->convertToDatabaseValue(null, $this->platform));
        self::assertNull($this->type->convertToPHPValue(null, $this->platform));
    }

    public function testSqlDeclarationDelegatesToPlatformDecimal(): void
    {
        $sql = $this->type->getSQLDeclaration(['precision' => 14, 'scale' => 2], $this->platform);
        self::assertSame('NUMERIC(14, 2)', $sql);
    }

    public function testRejectsWrongPhpTypeOnConvertToDatabase(): void
    {
        $this->expectException(InvalidType::class);
        $this->type->convertToDatabaseValue('not-a-bigdecimal', $this->platform);
    }

    public function testAcceptsIntegerFromDriver(): void
    {
        $result = $this->type->convertToPHPValue(1299, $this->platform);

        self::assertInstanceOf(BigDecimal::class, $result);
        self::assertSame('1299', (string) $result);
    }

    public function testThrowsValueNotConvertibleOnMalformedString(): void
    {
        $this->expectException(ValueNotConvertible::class);
        $this->type->convertToPHPValue('not-a-decimal', $this->platform);
    }

    public function testRoundTripsThroughBothDirections(): void
    {
        $original = BigDecimal::of('99999999999.99'); // top of NUMERIC(14, 2) range

        $db = $this->type->convertToDatabaseValue($original, $this->platform);
        $restored = $this->type->convertToPHPValue($db, $this->platform);

        self::assertInstanceOf(BigDecimal::class, $restored);
        self::assertSame((string) $original, (string) $restored, 'BigDecimal round-trip must preserve string representation');
    }
}
