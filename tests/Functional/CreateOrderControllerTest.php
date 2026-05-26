<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use const JSON_THROW_ON_ERROR;

final class CreateOrderControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    public function testCreatesOrderAndReturnsTwoZeroOneWithFullBody(): void
    {
        $this->client->jsonRequest(
            'POST',
            '/api/v1/partners/PARTNER_FN/orders',
            [
                'orderId' => 'ORD-FN-001',
                'expectedDeliveryDate' => '2026-06-15',
                'totalValue' => '499',
                'products' => [
                    ['productId' => 'SKU-FN-1', 'name' => 'Test Item', 'price' => '249.5', 'quantity' => 2],
                ],
            ],
        );

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('Content-Type', 'application/json');

        $body = self::decode($this->client->getResponse()->getContent());
        self::assertSame('PARTNER_FN', $body['partnerId']);
        self::assertSame('ORD-FN-001', $body['orderId']);
        self::assertSame('2026-06-15', $body['expectedDeliveryDate']);
        self::assertSame('499.00', $body['totalValue'], 'totalValue must be normalised to scale 2');
        self::assertIsArray($body['products']);
        $firstProduct = $body['products'][0];
        self::assertIsArray($firstProduct);
        self::assertSame('249.50', $firstProduct['price'], 'price must be normalised to scale 2');
        self::assertSame(2, $firstProduct['quantity']);
        self::assertSame('SKU-FN-1', $firstProduct['productId']);
        self::assertSame('Test Item', $firstProduct['name']);
        self::assertArrayHasKey('createdAt', $body);
        self::assertArrayHasKey('updatedAt', $body);
    }

    public function testReturnsFourTwoTwoProblemJsonWhenTotalValueIsNegative(): void
    {
        $this->client->jsonRequest(
            'POST',
            '/api/v1/partners/PARTNER_FN/orders',
            [
                'orderId' => 'ORD-FN-NEG',
                'expectedDeliveryDate' => '2026-06-15',
                'totalValue' => '-1.00',
                'products' => [
                    ['productId' => 'SKU-FN-NEG', 'name' => 'Test Item', 'price' => '1.00', 'quantity' => 1],
                ],
            ],
        );

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');

        $problem = self::decode($this->client->getResponse()->getContent());
        self::assertSame('Validation Failed', $problem['title']);
        self::assertSame(422, $problem['status']);
        self::assertIsArray($problem['errors']);
    }

    public function testReturnsFourZeroNineProblemJsonOnDuplicateCompositeKey(): void
    {
        $body = [
            'orderId' => 'ORD-FN-DUP',
            'expectedDeliveryDate' => '2026-06-15',
            'totalValue' => '100.00',
            'products' => [['productId' => 'SKU', 'name' => 'X', 'price' => '100.00', 'quantity' => 1]],
        ];

        $this->client->jsonRequest('POST', '/api/v1/partners/PARTNER_FN/orders', $body);
        self::assertResponseStatusCodeSame(201);

        $this->client->jsonRequest('POST', '/api/v1/partners/PARTNER_FN/orders', $body);
        self::assertResponseStatusCodeSame(409);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json');

        $problem = self::decode($this->client->getResponse()->getContent());
        self::assertSame('Duplicate Order', $problem['title']);
        self::assertIsString($problem['type']);
        self::assertStringContainsString('duplicate-order', $problem['type']);
        self::assertSame(409, $problem['status']);
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
}
