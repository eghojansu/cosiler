<?php

declare(strict_types=1);

// Helpers for the HTTP abstraction.

namespace Ekok\Cosiler\Http;

use function Ekok\Cosiler\Container\co;
use function Ekok\Cosiler\Utils\Str\fixslashes;

const BASE_PATH = 'http_base_path';
const ENTRY_FILE = 'http_entry_file';
const HTTP_SCHEME = 'http_scheme';
const HTTP_HOST = 'http_host';
const HTTP_PORT = 'http_port';
const ASSET_PREFIX = 'http_asset_prefix';

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

function is_builtin(): bool
{
    return 'cli-server' === PHP_SAPI;
}

function set_base_path(string $base): void
{
    co(BASE_PATH, $base);
}

function base_path(string $path = null, bool $entry = false): string
{
    $str = co(BASE_PATH) ?? co(BASE_PATH, is_builtin() ? '' : fixslashes(\dirname($_SERVER['SCRIPT_NAME'])));

    if ($entry) {
        $str .= entry(true);
    }

    if ($path) {
        $str .= '/' . \ltrim($path, '/');
    }

    return $str;
}

function set_entry(string $entry): void
{
    co(ENTRY_FILE, $entry);
}

function entry(bool $prefix = false): string
{
    $entry = co(ENTRY_FILE) ?? co(ENTRY_FILE, is_builtin() ? '' : \basename($_SERVER['SCRIPT_NAME']));

    return $prefix ? '/' . $entry : $entry;
}

function set_scheme(string $scheme): void
{
    co(HTTP_SCHEME, $scheme);
}

function scheme(bool $suffix = false): string
{
    $scheme = co(HTTP_SCHEME) ?? co(HTTP_SCHEME, ($_SERVER['HTTPS'] ?? '') ? 'https' : 'http');

    return $suffix ? $scheme . '://' : $scheme;
}

function set_host(string $host): void
{
    co(HTTP_HOST, $host);
}

function host(): string
{
    return strstr((co(HTTP_HOST) ?? co(HTTP_HOST, $_SERVER['HTTP_HOST'] ?? 'localhost')) . ':', ':', true);
}

function set_port(string|int $port): void
{
    co(HTTP_PORT, $port);
}

function port(bool $prefix = false): string|int
{
    $port = co(HTTP_PORT) ?? co(HTTP_PORT, $_SERVER['SERVER_PORT'] ?? '');

    return $prefix ? (\in_array(\intval($port), array(0, 80, 443)) ? '' : ':' . $port) : $port;
}

function set_asset(string $path): void
{
    co(ASSET_PREFIX, '/' . trim($path, '/'));
}

function asset(string $path): string
{
    return base_url(base_path(co(ASSET_PREFIX) . $path), true);
}

function path(string $path = null): string
{
    return base_path($path, true);
}

function base_url(string $path = null, bool $prefixed = false): string
{
    return scheme(true) . host() . port(true) . ($prefixed ? $path : base_path($path));
}

/**
 * Returns a path based on the projects base url.
 */
function url(string $path = null): string
{
    return base_url(path($path), true);
}

function status(int $code, bool $throw = true): string
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
    $unsupported = isset($codes[$code]) ? null : \sprintf('Unsupported HTTP code: %s', $code);

    if ($unsupported && $throw) {
        throw new \LogicException($unsupported);
    }

    return $codes[$code] ?? $unsupported;
}

function error(int $code = 500, string $message = null, array $payload = null, array $headers = null): void
{
    throw new HttpException($code, $message, $headers);
}

function unprocessable(string $message = null, array $payload = null, array $headers = null): void
{
    throw new HttpException(422, $message, $payload, $headers);
}

function not_allowed(string $message = null, array $payload = null, array $headers = null): void
{
    throw new HttpException(405, $message, $payload, $headers);
}

function not_found(string $message = null, array $payload = null, array $headers = null): void
{
    throw new HttpException(404, $message, $payload, $headers);
}

function forbidden(string $message = null, array $payload = null, array $headers = null): void
{
    throw new HttpException(403, $message, $payload, $headers);
}

function unauthorized(string $message = null, array $payload = null, array $headers = null): void
{
    throw new HttpException(401, $message, $payload, $headers);
}

function bad_request(string $message = null, array $payload = null, array $headers = null): void
{
    throw new HttpException(400, $message, $payload, $headers);
}
