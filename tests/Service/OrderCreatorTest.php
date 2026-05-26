<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Dto\Request\CreateOrderProductRequest;
use App\Dto\Request\CreateOrderRequest;
use App\Exception\DuplicateOrderException;
use App\Service\OrderCreator;
use App\Tests\Repository\InMemoryOrderRepository;
use PHPUnit\Framework\TestCase;

final class OrderCreatorTest extends TestCase
{
    public function testCreatesAndPersistsOrderWithSingleProduct(): void
    {
        // Arrange
        $repository = new InMemoryOrderRepository();
        $creator = new OrderCreator($repository);
        $request = new CreateOrderRequest(
            orderId: 'ORD-2026-00001',
            expectedDeliveryDate: '2026-06-15',
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

        // Act
        $order = $creator->create('PARTNER_A', $request);

        // Assert
        self::assertSame('PARTNER_A', $order->partnerId);
        self::assertSame('ORD-2026-00001', $order->orderId);
        self::assertSame('2026-06-15', $order->expectedDeliveryDate->format('Y-m-d'));
        self::assertSame('499.00', $order->totalValue);
        self::assertCount(1, $order->products);
        self::assertSame($order, $repository->findByCompositeKey('PARTNER_A', 'ORD-2026-00001'));
    }

    public function testCreatesOrderWithMultipleProductsLinkedBackToTheOrder(): void
    {
        // Arrange
        $creator = new OrderCreator(new InMemoryOrderRepository());
        $request = new CreateOrderRequest(
            orderId: 'ORD-2026-00002',
            expectedDeliveryDate: '2026-06-20',
            totalValue: '1299.99',
            products: [
                new CreateOrderProductRequest('SKU-001', 'Bluetooth Headphones', '129.99', 2),
                new CreateOrderProductRequest('SKU-002', 'USB-C Cable, 2 m', '9.99', 4),
                new CreateOrderProductRequest('SKU-003', 'Phone Stand', '49.99', 1),
            ],
        );

        // Act
        $order = $creator->create('PARTNER_B', $request);

        // Assert
        self::assertCount(3, $order->products);
        $skus = [];
        foreach ($order->products as $product) {
            self::assertSame($order, $product->order, 'each child product points back to its parent order');
            $skus[] = $product->productId;
        }
        self::assertSame(['SKU-001', 'SKU-002', 'SKU-003'], $skus);
    }

    public function testRejectsDuplicateCompositeKey(): void
    {
        // Arrange
        $creator = new OrderCreator(new InMemoryOrderRepository());
        $request = new CreateOrderRequest(
            orderId: 'ORD-DUP',
            expectedDeliveryDate: '2026-06-15',
            totalValue: '100.00',
            products: [new CreateOrderProductRequest('SKU-1', 'Item', '100.00', 1)],
        );
        $creator->create('PARTNER_A', $request);

        // Assert
        $this->expectException(DuplicateOrderException::class);

        // Act
        $creator->create('PARTNER_A', $request);
    }
}
