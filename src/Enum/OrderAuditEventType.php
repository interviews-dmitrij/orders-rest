<?php

declare(strict_types=1);

namespace App\Enum;

enum OrderAuditEventType: string
{
    case DeliveryDateChanged = 'delivery_date_changed';
}
