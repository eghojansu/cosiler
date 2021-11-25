<?php

namespace Ekok\Cosiler\Http;

class HttpException extends \Exception
{
    public function __construct(
        public int $statusCode = 500,
        string $message = null,
        public ?array $payload = null,
        public ?array $headers = null,
        int $code = 0,
        \Throwable $previous = null,
    ) {
        parent::__construct($message ?? status($statusCode, false), $code, $previous);
    }
}
