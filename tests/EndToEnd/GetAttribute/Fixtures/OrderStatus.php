<?php

declare(strict_types=1);

namespace TwigStan\EndToEnd\GetAttribute\Fixtures;

enum OrderStatus: string
{
    case Paid = 'paid';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
}
