<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;

final class BigDecimalType extends Type
{
    public const string NAME = 'bigdecimal';

    /**
     * @param array<string, mixed> $column
     */
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getDecimalTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }
        if (!$value instanceof BigDecimal) {
            throw InvalidType::new($value, self::NAME, ['null', BigDecimal::class]);
        }

        return (string) $value;
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?BigDecimal
    {
        if (null === $value || $value instanceof BigDecimal) {
            return $value;
        }
        if (!\is_string($value) && !\is_int($value)) {
            throw InvalidType::new($value, self::NAME, ['null', BigDecimal::class, 'string', 'int']);
        }
        try {
            return BigDecimal::of($value);
        } catch (MathException $e) {
            throw ValueNotConvertible::new($value, self::NAME, previous: $e);
        }
    }
}
