<?php

declare(strict_types=1);

namespace ShadowORM\Core\Infrastructure\Exception;

if (!defined('ABSPATH')) {
    exit;
}

use RuntimeException;

final class UnsupportedDatabaseException extends RuntimeException
{
}
