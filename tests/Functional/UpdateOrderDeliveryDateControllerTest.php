<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Order;
use App\Repository\OrderRepositoryInterface;
use Brick\Math\BigDecimal;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use const JSON_THROW_ON_ERROR;

final class UpdateOrderDeliveryDateControllerTest extends WebTestCase
{
    private const string PARTNER_ID = 'PARTNER_A';
    private const string ORDER_ID = 'ORD-2026-00001';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    public function testReplacesDeliveryDateAndPreservesEveryOtherField(): void
    {
        $createdBody = $this->seedOrder();

        $this->client->jsonRequest(
            'PUT',
            self::updatePath(self::ORDER_ID),
            ['expectedDeliveryDate' => '2026-07-20'],
        );

        self::assertResponseStatusCodeSame(200);
        self::assertResponseHeaderSame('Content-Type', 'application/json');

        $body = self::decode($this->client->getResponse()->getContent());
        self::assertSame('2026-07-20', $body['expectedDeliveryDate']);
        self::assertSame($createdBody['partnerId'], $body['partnerId']);
        self::assertSame($createdBody['orderId'], $body['orderId']);
        self::assertSame($createdBody['createdAt'], $body['createdAt'], 'createdAt must be immutable');
        self::assertDecimalEquals($createdBody['totalValue'], $body['totalValue']);
        self::assertProductsSemanticallyEqual($createdBody['products'], $body['products']);

        $persisted = self::loadPersisted();
        self::assertNotNull($persisted);
        self::assertSame('2026-07-20', $persisted->expectedDeliveryDate->format('Y-m-d'));
    }

    public function testReturnsFourOhFourProblemJsonWhenOrderIsMissing(): void
    {
        $this->client->jsonRequest(
            'PUT',
            self::updatePath('NO-SUCH-ORDER'),
            ['expectedDeliveryDate' => '2026-07-20'],
        );

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');

        $problem = self::decode($this->client->getResponse()->getContent());
        self::assertSame('Order Not Found', $problem['title']);
        self::assertSame(404, $problem['status']);
    }

    /**
     * Partner isolation: order belongs to PARTNER_A but PARTNER_B tries to update it.
     * Composite-key lookup must refuse the cross-partner request — protects the partner
     * namespace from horizontal data access if `findByCompositeKey` ever degrades.
     */
    public function testReturnsFourOhFourOnCrossPartnerUpdateAttempt(): void
    {
        $this->seedOrder();

        $this->client->jsonRequest(
            'PUT',
            \sprintf('/api/v1/partners/PARTNER_OTHER/orders/%s/delivery-date', self::ORDER_ID),
            ['expectedDeliveryDate' => '2026-07-20'],
        );

        self::assertResponseStatusCodeSame(404);

        $original = self::loadPersisted();
        self::assertNotNull($original);
        self::assertSame('2026-06-15', $original->expectedDeliveryDate->format('Y-m-d'), 'cross-partner call must not touch the original order');
    }

    public function testRejectsMissingExpectedDeliveryDateWithFieldLevelProblemDetail(): void
    {
        $this->seedOrder();

        $this->client->jsonRequest('PUT', self::updatePath(self::ORDER_ID), []);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');

        $problem = self::decode($this->client->getResponse()->getContent());
        self::assertContains('/expectedDeliveryDate', array_column(self::errors($problem), 'pointer'));
    }

    public function testRejectsMalformedDate(): void
    {
        $this->seedOrder();

        $this->client->jsonRequest(
            'PUT',
            self::updatePath(self::ORDER_ID),
            ['expectedDeliveryDate' => '20-07-2026'],
        );

        self::assertResponseStatusCodeSame(422);
        $problem = self::decode($this->client->getResponse()->getContent());
        self::assertContains('/expectedDeliveryDate', array_column(self::errors($problem), 'pointer'));
    }

    public function testIsIdempotentAcrossRepeatedCallsWithSameBody(): void
    {
        $this->seedOrder();
        $body = ['expectedDeliveryDate' => '2026-07-20'];

        $this->client->jsonRequest('PUT', self::updatePath(self::ORDER_ID), $body);
        self::assertResponseStatusCodeSame(200);
        $first = self::decode($this->client->getResponse()->getContent());

        $this->client->jsonRequest('PUT', self::updatePath(self::ORDER_ID), $body);
        self::assertResponseStatusCodeSame(200);
        $second = self::decode($this->client->getResponse()->getContent());

        self::assertSame($first['expectedDeliveryDate'], $second['expectedDeliveryDate']);
        self::assertSame($first['orderId'], $second['orderId']);

        $persisted = self::loadPersisted();
        self::assertNotNull($persisted);
        self::assertSame('2026-07-20', $persisted->expectedDeliveryDate->format('Y-m-d'), 'idempotent PUTs must converge in DB, not only in the response body');
    }

    /**
     * @return array<string, mixed>
     */
    private function seedOrder(): array
    {
        $this->client->jsonRequest(
            'POST',
            \sprintf('/api/v1/partners/%s/orders', self::PARTNER_ID),
            [
                'orderId' => self::ORDER_ID,
                'expectedDeliveryDate' => '2026-06-15',
                'totalValue' => '499.00',
                'products' => [['productId' => 'SKU-001', 'name' => 'Item', 'price' => '129.99', 'quantity' => 1]],
            ],
        );
        self::assertResponseStatusCodeSame(201);

        return self::decode($this->client->getResponse()->getContent());
    }

    private static function updatePath(string $orderId): string
    {
        return \sprintf('/api/v1/partners/%s/orders/%s/delivery-date', self::PARTNER_ID, $orderId);
    }

    private static function loadPersisted(): ?Order
    {
        return static::getContainer()->get(OrderRepositoryInterface::class)
            ->findByCompositeKey(self::PARTNER_ID, self::ORDER_ID);
    }

    /**
     * @return array<string, mixed>
     */
    private static function decode(string|false $content): array
    {
        self::assertIsString($content);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($content, true, flags: JSON_THROW_ON_ERROR);

        return $decoded;
    }

    /**
     * @param array<string, mixed> $problem
     *
     * @return list<array{pointer: string, message: string}>
     */
    private static function errors(array $problem): array
    {
        $errors = $problem['errors'] ?? null;
        self::assertIsArray($errors);

        /** @var list<array{pointer: string, message: string}> $errors */
        return $errors;
    }

    private static function assertDecimalEquals(mixed $expected, mixed $actual): void
    {
        self::assertIsString($expected);
        self::assertIsString($actual);
        self::assertTrue(
            BigDecimal::of($expected)->isEqualTo(BigDecimal::of($actual)),
            \sprintf('expected %s ≈ %s', $expected, $actual),
        );
    }

    private static function assertProductsSemanticallyEqual(mixed $expected, mixed $actual): void
    {
        self::assertIsArray($expected);
        self::assertIsArray($actual);
        self::assertCount(\count($expected), $actual);

        foreach ($actual as $index => $product) {
            self::assertIsArray($product);
            $original = $expected[$index] ?? null;
            self::assertIsArray($original);
            self::assertSame($original['productId'], $product['productId']);
            self::assertSame($original['name'], $product['name']);
            self::assertSame($original['quantity'], $product['quantity']);
            self::assertDecimalEquals($original['price'], $product['price']);
        }
    }
}
