<?php

declare(strict_types=1);

namespace OpenFeature\Providers\AwsAppConfig\Exception;

use Exception;

/**
 * Exception thrown when AWS AppConfig operations fail
 */
class AwsAppConfigException extends Exception
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
