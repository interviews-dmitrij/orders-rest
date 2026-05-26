<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Dto\Request\CreateOrderProductRequest;
use App\Dto\Request\CreateOrderRequest;
use App\Exception\DuplicateOrderException;
use App\Service\CreateOrderHandler;
use App\Tests\Repository\InMemoryOrderRepository;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CreateOrderHandlerTest extends TestCase
{
    public function testCreatesAndPersistsOrderWithSingleProduct(): void
    {
        $repository = new InMemoryOrderRepository();
        $handler = new CreateOrderHandler($repository);
        $request = new CreateOrderRequest(
            orderId: 'ORD-2026-00001',
            expectedDeliveryDate: new DateTimeImmutable('2026-06-15'),
            totalValue: '499.00',
            products: [
                new CreateOrderProductRequest(
                    productId: 'SKU-005',
                    name: 'Mechanical Keyboard',
                    price: '499.00',
                    quantity: 1,
                ),
            ],
        );

        $order = $handler->create('PARTNER_A', $request);

        self::assertSame('PARTNER_A', $order->partnerId);
        self::assertSame('ORD-2026-00001', $order->orderId);
        self::assertSame('2026-06-15', $order->expectedDeliveryDate->format('Y-m-d'));
        self::assertSame('499.00', (string) $order->totalValue);
        self::assertCount(1, $order->products);
        self::assertSame($order, $repository->findByCompositeKey('PARTNER_A', 'ORD-2026-00001'));
        self::assertSame($order->createdAt, $order->updatedAt);
    }

    public function testCreatesOrderWithMultipleProductsLinkedBackToTheOrder(): void
    {
        $handler = new CreateOrderHandler(new InMemoryOrderRepository());
        $request = new CreateOrderRequest(
            orderId: 'ORD-2026-00002',
            expectedDeliveryDate: new DateTimeImmutable('2026-06-20'),
            totalValue: '1299.99',
            products: [
                new CreateOrderProductRequest('SKU-001', 'Bluetooth Headphones', '129.99', 2),
                new CreateOrderProductRequest('SKU-002', 'USB-C Cable, 2 m', '9.99', 4),
                new CreateOrderProductRequest('SKU-003', 'Phone Stand', '49.99', 1),
            ],
        );

        $order = $handler->create('PARTNER_B', $request);

        self::assertCount(3, $order->products);
        $skus = [];
        $names = [];
        $quantities = [];
        $prices = [];
        foreach ($order->products as $product) {
            self::assertSame($order, $product->order, 'each child product points back to its parent order');
            $skus[] = $product->productId;
            $names[] = $product->name;
            $quantities[] = $product->quantity;
            $prices[] = (string) $product->price;
        }
        self::assertSame(['SKU-001', 'SKU-002', 'SKU-003'], $skus);
        self::assertSame(['Bluetooth Headphones', 'USB-C Cable, 2 m', 'Phone Stand'], $names);
        self::assertSame([2, 4, 1], $quantities);
        self::assertSame(['129.99', '9.99', '49.99'], $prices);
    }

    public function testRejectsDuplicateCompositeKey(): void
    {
        $handler = new CreateOrderHandler(new InMemoryOrderRepository());
        $request = new CreateOrderRequest(
            orderId: 'ORD-DUP',
            expectedDeliveryDate: new DateTimeImmutable('2026-06-15'),
            totalValue: '100.00',
            products: [new CreateOrderProductRequest('SKU-1', 'Item', '100.00', 1)],
        );
        $handler->create('PARTNER_A', $request);

        $this->expectException(DuplicateOrderException::class);
        $handler->create('PARTNER_A', $request);
    }

    public function testNormalizesTotalAndPriceToScaleTwo(): void
    {
        $handler = new CreateOrderHandler(new InMemoryOrderRepository());
        $request = new CreateOrderRequest(
            orderId: 'ORD-SCALE',
            expectedDeliveryDate: new DateTimeImmutable('2026-06-15'),
            totalValue: '499',
            products: [new CreateOrderProductRequest('SKU-1', 'Item', '9.9', 1)],
        );

        $order = $handler->create('PARTNER_A', $request);

        $product = $order->products->first();
        self::assertNotFalse($product);
        self::assertSame('499.00', (string) $order->totalValue);
        self::assertSame('9.90', (string) $product->price);
    }

    public function testChecksRepositoryForExistingOrderBeforeConstructingEntities(): void
    {
        $repository = new InMemoryOrderRepository();
        $handler = new CreateOrderHandler($repository);
        $request = $this->orderRequest('ORD-EARLY');
        $handler->create('PARTNER_A', $request);

        try {
            $handler->create('PARTNER_A', $request);
            self::fail('expected DuplicateOrderException');
        } catch (DuplicateOrderException) {
            self::assertSame(
                [
                    ['partnerId' => 'PARTNER_A', 'orderId' => 'ORD-EARLY'],
                    ['partnerId' => 'PARTNER_A', 'orderId' => 'ORD-EARLY'],
                ],
                $repository->lookupCalls,
                'each create() call must lookup before deciding to persist',
            );
        }
    }

    private function orderRequest(string $orderId): CreateOrderRequest
    {
        return new CreateOrderRequest(
            orderId: $orderId,
            expectedDeliveryDate: new DateTimeImmutable('2026-06-15'),
            totalValue: '100.00',
            products: [new CreateOrderProductRequest('SKU-1', 'Item', '100.00', 1)],
        );
    }
}
