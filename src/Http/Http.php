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
 * @param mixed $value The value to be stored
 *
 * @return mixed
 */
function session(?string $key = null, ...$value)
{
    if ($value && $key) {
        (PHP_SESSION_ACTIVE === \session_status() || headers_sent()) || \session_start();

        $_SESSION[$key] = $value[0];
    }

    return $key ? ($_SESSION[$key] ?? null) : null;
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
    return \rtrim(\str_replace('\\', '/', \dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/').'/'.\ltrim($path ?? '', '/');
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
