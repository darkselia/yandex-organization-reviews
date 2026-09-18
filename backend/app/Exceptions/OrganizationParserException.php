<?php

namespace App\Exceptions;

use App\Enums\ParserErrorCode;
use RuntimeException;
use Throwable;

class OrganizationParserException extends RuntimeException
{
    public function __construct(
        public readonly ParserErrorCode $errorCode,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
