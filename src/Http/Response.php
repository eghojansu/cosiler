<?php

declare(strict_types=1);

// Helper functions to handle HTTP responses.

namespace Ekok\Cosiler\Http\Response;

use function Ekok\Cosiler\Encoder\Json\encode;
use function Ekok\Cosiler\Http\Request\method_is;
use function Ekok\Cosiler\Http\Request\uri;
use function Ekok\Cosiler\Http\session;
use function Ekok\Cosiler\Http\status;
use function Ekok\Cosiler\Http\url;

function start(int $code = 200, string $mimeType = 'text/html', string $charset = 'utf-8'): void
{
    header(sprintf('%s %s %s', $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1', $code, status($code)), null, true, $code);
    header(sprintf('%s: %s;charset=%s', 'Content-Type', $mimeType, $charset), null, true, $code);
}

/**
 * Outputs the given parameters based on a HTTP response.
 *
 * @param string $content  The HTTP response body
 * @param int    $code     The HTTP response code code
 * @param string $mimeType A value for HTTP Header Content-Type
 * @param string $charset  The HTTP response charset
 *
 * @return int Returns 1, always
 */
function output(string $content = '', int $code = 204, string $mimeType = 'text/plain', string $charset = 'utf-8'): int
{
    start($code, $mimeType, $charset);

    return print $content;
}

/**
 * Outputs a HTTP response as simple text.
 *
 * @param mixed  $content The HTTP response body
 * @param int    $code    The HTTP response status code
 * @param string $charset The HTTP response charset
 *
 * @return int Returns 1, always
 */
function text($content, int $code = 200, string $charset = 'utf-8'): int
{
    return output(strval($content), $code, 'text/plain', $charset);
}

/**
 * Outputs a HTML HTTP response.
 *
 * @param string $content The HTTP response body
 * @param int    $code    The HTTP response status code
 * @param string $charset The HTTP response charset
 *
 * @return int Returns 1, always
 */
function html(string $content, int $code = 200, string $charset = 'utf-8'): int
{
    return output($content, $code, 'text/html', $charset);
}

/**
 * Outputs the given content as JSON mime type.
 *
 * @param string $content The HTTP response body
 * @param int    $code    The HTTP response status code
 * @param string $charset The HTTP response charset
 *
 * @return int Returns 1, always
 */
function json_str(string $content, int $code = 200, string $charset = 'utf-8'): int
{
    return output(strval($content), $code, 'application/json', $charset);
}

/**
 * Outputs the given content encoded as JSON string.
 *
 * @param mixed  $content The HTTP response body
 * @param int    $code    The HTTP response status code
 * @param string $charset The HTTP response charset
 *
 * @return int Returns 1, always
 */
function json($content, int $code = 200, string $charset = 'utf-8'): int
{
    return json_str(encode($content), $code, $charset);
}

/**
 * Helper method to setup a header item as key-value parts.
 *
 * @param string $key     The response header name
 * @param string $val     The response header value
 * @param bool   $replace should replace a previous similar header, or add a second header of the same type
 */
function header(string $key, string $val = null, ...$args): void
{
    headers_sent() || \header(null === $val || '' === $val ? $key : $key.': '.$val, ...$args);
}

function header_if(string $key, string $val = null, string $check = null, ...$args): void
{
    preg_grep('/^' . preg_quote($check ?? strstr($key . ':', ':', true), '/') . '/i', headers_list()) || header($key, $val, ...$args);
}

/**
 * Composes a default HTTP redirect response with the current base url.
 */
function redirect(string $path, bool $continue = false): void
{
    $location = false === strpos($path, '://') ? url($path) : $path;

    header_if('Location', $location);
    $continue || die;
}

function back(string $key = null, $value = null, bool $continue = false): void
{
    null === $key || null === $value || session($key, $value);

    redirect(uri(), $continue);
}

/**
 * Facade for No Content HTTP Responses.
 */
function no_content(): void
{
    output();
}

/**
 * Enable CORS on SAPI.
 */
function cors(string $origin = '*', string $headers = 'Content-Type', string $methods = 'GET, POST, PUT, DELETE', string $credentials = 'true'): void
{
    header_if('Access-Control-Allow-Origin', $origin);
    header_if('Access-Control-Allow-Headers', $headers);
    header_if('Access-Control-Allow-Methods', $methods);
    header_if('Access-Control-Allow-Credentials', $credentials);

    if (method_is('options')) {
        no_content();
    }
}
