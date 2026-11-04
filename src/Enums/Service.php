<?php

declare(strict_types=1);

namespace Beziworld\Ambro\Enums;

/**
 * Services documented by Ambro Express. Call services() for the list enabled on an account.
 */
enum Service: int
{
    case Standard = 38;
    case Furniture = 200;
}
