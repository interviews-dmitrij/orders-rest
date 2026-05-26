<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Dto\Request\CreateOrderRequest;
use App\Dto\Response\OrderResponse;
use App\Service\OrderCreator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final class CreateOrderController
{
    public function __construct(
        private readonly OrderCreator $creator,
    ) {
    }

    #[Route(
        path: '/api/v1/partners/{partnerId}/orders',
        name: 'create_order',
        methods: ['POST'],
    )]
    public function __invoke(
        string $partnerId,
        #[MapRequestPayload(
            acceptFormat: 'json',
            validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
        )]
        CreateOrderRequest $request,
    ): JsonResponse {
        $order = $this->creator->create($partnerId, $request);

        $response = new JsonResponse(
            OrderResponse::fromEntity($order),
            Response::HTTP_CREATED,
        );
        $response->headers->set(
            'Location',
            \sprintf('/api/v1/partners/%s/orders/%s', $partnerId, $order->orderId),
        );

        return $response;
    }
}
