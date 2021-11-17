<?php

declare(strict_types=1);

// Helpers for the HTTP abstraction.

namespace Ekok\Cosiler\Http;

/**
 * Get a value from the $_COOKIE global.
 *
 * @return null|array<string, null|string>|string
 */
function cookie(?string $key = null)
{
    return $key ? ($_COOKIE[$key] ?? null) : $_COOKIE;
}

/**
 * Get or set a value from the $_SESSION global.
 *
 * @param mixed $key Session key
 * @param mixed $value The value to be stored
 *
 * @return mixed
 */
function session(?string $key = null, $value = null)
{
    (PHP_SESSION_ACTIVE === \session_status() || headers_sent()) || \session_start();

    if ($key && null !== $value) {
        $_SESSION[$key] = $value;
    }

    return $key ? ($_SESSION[$key] ?? null) : $_SESSION;
}

function session_end(): void
{
    \session_unset();
    PHP_SESSION_ACTIVE !== \session_status() || \session_destroy();
}

/**
 * Get a value from the $_SESSION global and remove it.
 *
 * @return mixed
 */
function flash(?string $key = null)
{
    $value = session($key);

    if ($key) {
        unset($_SESSION[$key]);
    }

    return $value;
}

/**
 * Returns a path based on the projects base url.
 */
function url(?string $path = null): string
{
    return \rtrim(\str_replace('\\', '/', \dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/').'/'.\ltrim($path ?? path(), '/');
}

/**
 * Get the current HTTP path info.
 */
function path(): string
{
    // NOTE: When using built-in server with a router script, SCRIPT_NAME will be same as the REQUEST_URI
    $scriptName = PHP_SAPI === 'cli-server' ? '' : ($_SERVER['SCRIPT_NAME'] ?? '');
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';

    $queryString = \strpos($requestUri, '?');
    $requestUri = false === $queryString ? $requestUri : \substr($requestUri, 0, $queryString);
    $requestUri = \rawurldecode($requestUri);
    $scriptPath = \str_replace('\\', '/', \dirname($scriptName));

    if ('' === \str_replace('/', '', $scriptPath)) {
        return '/'.\ltrim($requestUri, '/');
    }

    return '/'.\ltrim(\preg_replace("#^{$scriptPath}#", '', $requestUri, 1), '/');
}

/**
 * Get the absolute project's URI.
 */
function uri(?string $protocol = null): string
{
    $useProtocol = $protocol ?? (($_SERVER['HTTPS'] ?? '') ? 'https' : 'http');
    $withHost = $_SERVER['HTTP_HOST'] ?? '';

    return $useProtocol.'://'.$withHost.path();
}

function status(int $code): string
{
    static $codes = array(
        100 => 'Continue',
        101 => 'Switching Protocols',
        103 => 'Early Hints',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Payload Too Large',
        414 => 'URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',
        422 => 'Unprocessable Entity',
        425 => 'Too Early',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        451 => 'Unavailable For Legal Reasons',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        510 => 'Not Extended',
        511 => 'Network Authentication Required',
    );

    if (!isset($codes[$code])) {
        throw new \LogicException(\sprintf('Unsupported HTTP code: %s', $code));
    }

    return $codes[$code];
}
