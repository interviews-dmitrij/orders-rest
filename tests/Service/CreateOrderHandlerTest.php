<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Dto\Request\CreateOrderProductRequest;
use App\Dto\Request\CreateOrderRequest;
use App\Exception\DuplicateOrderException;
use App\Service\CreateOrderHandler;
use App\Tests\Repository\InMemoryOrderRepository;
use Brick\Math\BigDecimal;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class CreateOrderHandlerTest extends TestCase
{
    private InMemoryOrderRepository $repository;
    private MockClock $clock;
    private CreateOrderHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryOrderRepository();
        $this->clock = new MockClock('2026-05-26T10:00:00+00:00');
        $this->handler = new CreateOrderHandler($this->repository, $this->clock);
    }

    public function testCreatedOrderEchoesRequestAndIsPersistedUnderCompositeKey(): void
    {
        $request = self::request('ORD-001', totalValue: '499.00', products: [
            ['SKU-001', 'Bluetooth Headphones', '129.99', 2],
        ]);

        $order = $this->handler->create('PARTNER_A', $request);

        self::assertSame('PARTNER_A', $order->partnerId);
        self::assertSame('ORD-001', $order->orderId);
        self::assertSame('2026-06-15', $order->expectedDeliveryDate->format('Y-m-d'));
        self::assertSame('499.00', (string) $order->totalValue);
        self::assertEquals($this->clock->now(), $order->createdAt, 'createdAt must come from the injected clock');
        self::assertSame($order->createdAt, $order->updatedAt, 'fresh order must have equal create/update timestamps');
        self::assertSame($order, $this->repository->findByCompositeKey('PARTNER_A', 'ORD-001'));
    }

    public function testEachProductIsLinkedToParentOrderInDeclarationOrder(): void
    {
        $request = self::request('ORD-002', products: [
            ['SKU-001', 'Bluetooth Headphones', '129.99', 2],
            ['SKU-002', 'USB-C Cable, 2 m', '9.99', 4],
            ['SKU-003', 'Phone Stand', '49.99', 1],
        ]);

        $order = $this->handler->create('PARTNER_B', $request);

        $actual = array_map(
            static fn ($product): array => [
                'productId' => $product->productId,
                'name' => $product->name,
                'price' => (string) $product->price,
                'quantity' => $product->quantity,
                'orderRef' => $product->order,
            ],
            iterator_to_array($order->products),
        );
        self::assertSame(
            [
                ['productId' => 'SKU-001', 'name' => 'Bluetooth Headphones', 'price' => '129.99', 'quantity' => 2, 'orderRef' => $order],
                ['productId' => 'SKU-002', 'name' => 'USB-C Cable, 2 m', 'price' => '9.99', 'quantity' => 4, 'orderRef' => $order],
                ['productId' => 'SKU-003', 'name' => 'Phone Stand', 'price' => '49.99', 'quantity' => 1, 'orderRef' => $order],
            ],
            $actual,
        );
    }

    public function testRejectsDuplicateCompositeKey(): void
    {
        $request = self::request('ORD-DUP');
        $this->handler->create('PARTNER_A', $request);

        $this->expectException(DuplicateOrderException::class);
        $this->handler->create('PARTNER_A', $request);
    }

    /**
     * Optimisation guard: the duplicate check must run before entity construction,
     * otherwise every duplicate POST wastes allocations on an `Order` and N `OrderProduct`
     * objects that we then immediately throw away.
     */
    public function testQueriesRepositoryBeforeConstructingEntities(): void
    {
        $request = self::request('ORD-EARLY');
        $this->handler->create('PARTNER_A', $request);

        try {
            $this->handler->create('PARTNER_A', $request);
            self::fail('expected DuplicateOrderException');
        } catch (DuplicateOrderException) {
            self::assertSame(
                [
                    ['partnerId' => 'PARTNER_A', 'orderId' => 'ORD-EARLY'],
                    ['partnerId' => 'PARTNER_A', 'orderId' => 'ORD-EARLY'],
                ],
                $this->repository->lookupCalls,
            );
        }
    }

    /**
     * @param list<array{0: string, 1: string, 2: string, 3: int}> $products
     */
    private static function request(string $orderId, string $totalValue = '100.00', array $products = [['SKU-1', 'Item', '100.00', 1]]): CreateOrderRequest
    {
        return new CreateOrderRequest(
            orderId: $orderId,
            expectedDeliveryDate: new DateTimeImmutable('2026-06-15'),
            totalValue: BigDecimal::of($totalValue),
            products: array_map(
                static fn (array $line): CreateOrderProductRequest => new CreateOrderProductRequest(
                    productId: $line[0],
                    name: $line[1],
                    price: BigDecimal::of($line[2]),
                    quantity: $line[3],
                ),
                $products,
            ),
        );
    }
}
