<?php

declare(strict_types=1);

namespace App\Serializer;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[AutoconfigureTag('serializer.normalizer', attributes: ['priority' => 1000])]
final class BigDecimalNormalizer implements NormalizerInterface, DenormalizerInterface
{
    private const int MONEY_SCALE = 2;

    public function normalize(mixed $object, ?string $format = null, array $context = []): string
    {
        if (!$object instanceof BigDecimal) {
            throw new InvalidArgumentException(
                'BigDecimalNormalizer can only normalize BigDecimal instances; got ' . get_debug_type($object),
            );
        }

        return (string) $object->toScale(self::MONEY_SCALE);
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof BigDecimal;
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        $deserializationPath = $this->extractDeserializationPath($context);

        if (!\is_string($data) && !\is_int($data)) {
            throw NotNormalizableValueException::createForUnexpectedDataType(
                'BigDecimal expects a numeric string or integer.',
                $data,
                ['string', 'int'],
                $deserializationPath,
                true,
            );
        }

        try {
            $value = BigDecimal::of((string) $data);
        } catch (MathException $exception) {
            throw NotNormalizableValueException::createForUnexpectedDataType(
                $exception->getMessage(),
                $data,
                [BigDecimal::class],
                $deserializationPath,
                true,
                0,
                $exception,
            );
        }

        try {
            $decimal = $value->toScale(self::MONEY_SCALE, RoundingMode::Unnecessary);
        } catch (MathException $exception) {
            throw NotNormalizableValueException::createForUnexpectedDataType(
                'Value has more than ' . self::MONEY_SCALE . ' fractional digits.',
                $data,
                [BigDecimal::class],
                $deserializationPath,
                true,
                0,
                $exception,
            );
        }

        if ($decimal->isNegative()) {
            throw NotNormalizableValueException::createForUnexpectedDataType(
                'Value must not be negative.',
                $data,
                [BigDecimal::class],
                $deserializationPath,
                true,
            );
        }

        return $decimal;
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return BigDecimal::class === $type;
    }

    /**
     * @return array<class-string, true>
     */
    public function getSupportedTypes(?string $format): array
    {
        return [BigDecimal::class => true];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function extractDeserializationPath(array $context): ?string
    {
        $path = $context['deserialization_path'] ?? null;

        return \is_string($path) ? $path : null;
    }
}
