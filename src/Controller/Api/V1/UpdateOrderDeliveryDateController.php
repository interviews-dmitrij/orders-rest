<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Dto\Request\UpdateOrderDeliveryDateRequest;
use App\Dto\Response\OrderResponse;
use App\Service\UpdateOrderDeliveryDateHandler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final class UpdateOrderDeliveryDateController
{
    public function __construct(
        private readonly UpdateOrderDeliveryDateHandler $handler,
    ) {
    }

    #[Route(
        path: '/api/v1/partners/{partnerId}/orders/{orderId}/delivery-date',
        name: 'update_order_delivery_date',
        requirements: ['partnerId' => '[^/]{1,64}', 'orderId' => '[^/]{1,64}'],
        methods: ['PUT'],
    )]
    public function __invoke(
        string $partnerId,
        string $orderId,
        #[MapRequestPayload(
            acceptFormat: 'json',
            validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY,
        )]
        UpdateOrderDeliveryDateRequest $request,
    ): JsonResponse {
        $order = $this->handler->update($partnerId, $orderId, $request);

        return new JsonResponse(OrderResponse::fromEntity($order));
    }
}
