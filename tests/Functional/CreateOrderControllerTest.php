<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Order;
use App\Repository\OrderRepositoryInterface;
use Brick\Math\BigDecimal;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use const JSON_THROW_ON_ERROR;

final class CreateOrderControllerTest extends WebTestCase
{
    private const string DEFAULT_PARTNER_ID = 'PARTNER_A';
    private const string DEFAULT_ORDER_ID = 'ORD-2026-00001';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    public function testPersistsOrderAndReturnsResourceEchoingClientValues(): void
    {
        $payload = self::payloadWith(['products' => [
            ['productId' => 'SKU-001', 'name' => 'Bluetooth Headphones', 'price' => '129.99', 'quantity' => 2],
            ['productId' => 'SKU-002', 'name' => 'USB-C Cable, 2 m', 'price' => '9.99', 'quantity' => 4],
        ]]);

        $body = $this->postOrder($payload);

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame('PARTNER_A', $body['partnerId']);
        self::assertSame(self::DEFAULT_ORDER_ID, $body['orderId']);
        self::assertSame('2026-06-15', $body['expectedDeliveryDate']);
        self::assertSame('499.00', $body['totalValue']);
        self::assertSame($payload['products'], $body['products']);

        $persisted = self::loadPersisted();
        self::assertInstanceOf(Order::class, $persisted);
        self::assertSame('499.00', (string) $persisted->totalValue);
        self::assertCount(2, $persisted->products);
    }

    /**
     * Regression guard: a previous implementation forced scale 2 inside the normalizer,
     * silently rounding partner inputs like `10.51119` to `10.51`. The contract now is to
     * round-trip whatever the partner sent up to 5 fractional digits, both in the HTTP
     * response and on disk.
     */
    public function testPreservesRawFractionalScaleUpToFiveDigits(): void
    {
        $body = $this->postOrder(self::payloadWith(['products' => [
            ['productId' => 'SKU-X', 'name' => 'Crypto Tx Fee', 'price' => '10.51119', 'quantity' => 1],
        ]]));

        self::assertResponseStatusCodeSame(201);
        self::assertSame('10.51119', self::firstProduct($body)['price']);

        $persisted = self::loadPersisted();
        self::assertNotNull($persisted);
        $persistedProduct = $persisted->products->first();
        self::assertNotFalse($persistedProduct);
        self::assertSame('10.51119', (string) $persistedProduct->price);
    }

    public function testRejectsNegativeTotalValueWithFieldLevelProblemDetailAndDoesNotPersist(): void
    {
        $problem = $this->postOrder(self::payloadWith(['totalValue' => '-1.00']));

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::assertSame('Validation Failed', $problem['title']);
        self::assertContainsErrorAt('/totalValue', 'This value should be greater than or equal to 0.', $problem);
        self::assertNull(self::loadPersisted(), 'rejected requests must not leave partial state behind');
    }

    public function testRejectsScaleOverflowOnNestedProductPriceWithJsonPointerPath(): void
    {
        $problem = $this->postOrder(self::payloadWith(['products' => [
            ['productId' => 'SKU-X', 'name' => 'Too precise', 'price' => '10.123456', 'quantity' => 1],
        ]]));

        self::assertResponseStatusCodeSame(422);
        self::assertContainsErrorAt(
            '/products/0/price',
            'This value should have at most 5 fractional digits.',
            $problem,
        );
    }

    public function testRejectsEmptyProductsCollection(): void
    {
        $problem = $this->postOrder(self::payloadWith(['products' => []]));

        self::assertResponseStatusCodeSame(422);
        self::assertContains('/products', array_column(self::errors($problem), 'pointer'));
    }

    public function testReturnsConflictAndPreservesFirstOrderOnDuplicateCompositeKey(): void
    {
        $payload = self::payloadWith(['orderId' => 'ORD-DUP', 'totalValue' => '100.00']);

        $first = $this->postOrder($payload);
        self::assertResponseStatusCodeSame(201);

        $conflict = $this->postOrder([...$payload, 'totalValue' => '999.00']);
        self::assertResponseStatusCodeSame(409);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');
        self::assertSame('Duplicate Order', $conflict['title']);

        $persisted = self::loadPersisted('ORD-DUP');
        self::assertNotNull($persisted);
        self::assertTrue(
            $persisted->totalValue->isEqualTo(BigDecimal::of('100.00')),
            'second call must not overwrite the first',
        );
        self::assertSame($first['orderId'], $persisted->orderId);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function postOrder(array $payload, string $partnerId = self::DEFAULT_PARTNER_ID): array
    {
        $this->client->jsonRequest('POST', \sprintf('/api/v1/partners/%s/orders', $partnerId), $payload);
        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($content, true, flags: JSON_THROW_ON_ERROR);

        return $decoded;
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private static function payloadWith(array $overrides): array
    {
        return [
            'orderId' => self::DEFAULT_ORDER_ID,
            'expectedDeliveryDate' => '2026-06-15',
            'totalValue' => '499.00',
            'products' => [['productId' => 'SKU-001', 'name' => 'Item', 'price' => '129.99', 'quantity' => 1]],
            ...$overrides,
        ];
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    private static function firstProduct(array $body): array
    {
        $products = $body['products'];
        self::assertIsArray($products);
        $first = $products[0] ?? null;
        self::assertIsArray($first);

        /** @var array<string, mixed> $first */
        return $first;
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

    /**
     * @param array<string, mixed> $problem
     */
    private static function assertContainsErrorAt(string $pointer, string $message, array $problem): void
    {
        self::assertContains(['pointer' => $pointer, 'message' => $message], self::errors($problem));
    }

    private static function loadPersisted(string $orderId = self::DEFAULT_ORDER_ID): ?Order
    {
        return static::getContainer()->get(OrderRepositoryInterface::class)
            ->findByCompositeKey(self::DEFAULT_PARTNER_ID, $orderId);
    }
}
