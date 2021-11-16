<?php

declare(strict_types=1);

// Helper functions to handle HTTP responses.

namespace Ekok\Cosiler\Http\Response;

use Ekok\Cosiler\Encoder\Json;
use Ekok\Cosiler\Http;
use Ekok\Cosiler\Http\Request;

function start(int $code = 200, string $mimeType = 'text/html', string $charset = 'utf-8'): void
{
    headers_sent() || \header(sprintf('%s %s %s', $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1', $code, Http\status($code)));
    headers_sent() || \header(sprintf('%s: %s;charset=%s', 'Content-Type', $mimeType, $charset));
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
    return output(\strval($content), $code, 'text/plain', $charset);
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
    return output(\strval($content), $code, 'application/json', $charset);
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
    return json_str(Json\encode($content), $code, $charset);
}

/**
 * Helper method to setup a header item as key-value parts.
 *
 * @param string $key     The response header name
 * @param string $val     The response header value
 * @param bool   $replace should replace a previous similar header, or add a second header of the same type
 */
function header(string $key, string $val, bool $replace = true): void
{
    headers_sent() || \header($key.': '.$val, $replace);
}

/**
 * Composes a default HTTP redirect response with the current base url.
 */
function redirect(string $path): void
{
    $location = false === strpos($path, '://') ? Http\url($path) : $path;

    header('Location', $location);
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
    header('Access-Control-Allow-Origin', $origin);
    header('Access-Control-Allow-Headers', $headers);
    header('Access-Control-Allow-Methods', $methods);
    header('Access-Control-Allow-Credentials', $credentials);

    if (Request\method_is('options')) {
        no_content();
    }
}

/**
 * Sugar for 404 Not found.
 */
function not_found(string $content = '', string $charset = 'utf-8'): int
{
    return output($content, 404, $charset);
}
