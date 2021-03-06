<?php

declare(strict_types=1);

/**
 * Helpers functions for HTTP requests.
 */

namespace Ekok\Cosiler\Http\Request;

use Ekok\Utils\Arr;
use Ekok\Utils\Payload;

use function Ekok\Cosiler\Http\url;
use function Ekok\Cosiler\Http\base_path;
use function Ekok\Cosiler\Encoder\Json\decode;

/**
 * Get the current HTTP path info.
 */
function path(): string
{
    $uri = rawurldecode(strstr(($_SERVER['REQUEST_URI'] ?? '') . '?', '?', true));
    $base = base_path(null, true);

    return '/' . ltrim('' === $base ? $uri : preg_replace("#^{$base}#", '', $uri, 1), '/');
}

/**
 * Get the absolute project's URI.
 */
function uri(): string
{
    $uri = url(path());

    if ($_GET) {
        $uri .= '?' . http_build_query($_GET);
    }

    return $uri;
}

/**
 * Returns the raw HTTP body request.
 *
 * @param string $input The input file to check on
 */
function raw(string $input = 'php://input'): string
{
    return false === ($contents = file_get_contents($input)) ? '' : $contents;
}

/**
 * Returns URL decoded raw request body.
 *
 * @param string $input The input file to check on
 */
function params(string $input = 'php://input'): array
{
    $params = array();
    parse_str(raw($input), $params);

    return $params;
}

/**
 * Returns JSON decoded raw request body.
 *
 * @param string $input The input file to check on
 *
 * @return array|bool|float|int|object|string
 */
function json(string $input = 'php://input')
{
    return decode(raw($input));
}

/**
 * Tries to figure out the body type and parse it.
 *
 * @return mixed
 */
function body_parse(string $input = 'php://input')
{
    return is_json() ? json($input) : post();
}

/**
 * Returns true if the current HTTP request is JSON (based on Content-type header).
 */
function is_json(): ?bool
{
    return content_is('application/json');
}

function wants_json(): bool
{
    return false !== strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
}

/**
 * Returns true if the current request is multipart/form-data, based on Content-type header.
 */
function is_multipart(): ?bool
{
    return content_is('multipart/form-data');
}

/**
 * Returns true if the current request is match with compared mime, based on Content-type header.
 */
function content_is(string $mime): ?bool
{
    return null === ($str = content_type()) ? null : $mime === \substr(\strtolower($str), 0, \strlen($mime));
}

/**
 * Returns the Content-type header.
 */
function content_type(): ?string
{
    return headers('Content-Type');
}

function accept(string $mime): bool
{
    $accept = headers('Accept') ?? '*/*';

    return $mime === $accept || preg_match('/\b' . preg_quote($mime, '/') . '\b/i', $accept);
}

/**
 * Returns all the HTTP headers.
 *
 * @param string $key Get single header
 *
 * @return null|array<string, string>|string
 */
function headers(string $key = null): array|string|null
{
    if ($key) {
        $ukey = strtoupper(str_replace('-', '_', $key));

        return $_SERVER[$ukey] ?? $_SERVER['HTTP_'.$ukey] ?? null;
    }

    return Arr::each($_SERVER, fn (Payload $header) => match (true) {
        'CONTENT_TYPE' === $header->key => $header->key('Content-Type'),
        'CONTENT_LENGTH' === $header->key => $header->key('Content-Length'),
        str_starts_with($header->key, 'HTTP_') => $header->key(ucwords(strtolower(str_replace('_', '-', substr($header->key, 5))), '-')),
        default => null,
    }, true);
}

function header_exists(string $key): bool
{
    $ukey = strtoupper(str_replace('-', '_', $key));

    return isset($_SERVER[$ukey]) || isset($_SERVER['HTTP_' . $ukey]);
}

/**
 * Get a value from the $_GET global.
 *
 * @return mixed
 */
function get(?string $key = null)
{
    return $key ? ($_GET[$key] ?? null) : $_GET;
}

/**
 * Get a value from the $_POST global.
 *
 * @return null|array<string, null|string>|string
 */
function post(?string $key = null)
{
    return $key ? ($_POST[$key] ?? null) : $_POST;
}

/**
 * Get a value from the $_REQUEST global.
 *
 * @return null|array<string, null|string>|string
 */
function input(?string $key = null)
{
    return $key ? ($_REQUEST[$key] ?? null) : $_REQUEST;
}

/**
 * Get a value from the $_FILES global.
 *
 * @param null|array $key
 *
 * @return null|array|array<string, array>
 */
function file(?string $key = null)
{
    return $key ? ($_FILES[$key] ?? null) : $_FILES;
}

/**
 * Get a value from the $_SERVER global.
 *
 * @return null|array<string, array>|string
 */
function server(?string $key = null)
{
    return $key ? ($_SERVER[$key] ?? null) : $_SERVER;
}

/**
 * Returns the current HTTP request method.
 * Override with X-Http-Method-Override header or _method on body.
 */
function method(): string
{
    return headers('X-Http-Method-Override') ?? $_POST['_method'] ?? $_SERVER['REQUEST_METHOD'] ?? 'GET';
}

/**
 * Checks for the current HTTP request method.
 *
 * @param string|string[] $method The given method to check on
 */
function method_is($method, ?string $request_method = null): bool
{
    $check = strtolower($request_method ?? method());

    if (is_array($method)) {
        return in_array($check, array_map('strtolower', $method), true);
    }

    return strtolower($method) === $check;
}

/**
 * Returns the list of accepted languages,
 * sorted by priority, taken from the HTTP_ACCEPT_LANGUAGE super global.
 *
 * @return array languages by [language => priority], or empty if none could be found
 */
function accepted_locales(): array
{
    $languages = array();

    if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        // break up string into pieces (languages and q factors)
        preg_match_all(
            '/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.\d+))?/i',
            (string) $_SERVER['HTTP_ACCEPT_LANGUAGE'],
            $lang_parse
        );

        if (count($lang_parse) > 1 && count($lang_parse[1]) > 0) {
            // create a list like "en" => 0.8
            /** @var array<mixed, array-key> $lang_parse_1 */
            $lang_parse_1 = $lang_parse[1];
            /** @var array<mixed, mixed> $lang_parse_4 */
            $lang_parse_4 = $lang_parse[4];
            $languages = array_combine($lang_parse_1, $lang_parse_4);

            /**
             * Set default to 1 for any without q factor.
             *
             * @var string $lang
             * @var string $val
             */
            foreach ($languages as $lang => $val) {
                if ('' === $val) {
                    $languages[$lang] = 1;
                }
            }

            arsort($languages, SORT_NUMERIC | SORT_DESC);
        }
    } //end if

    return $languages;
}

/**
 * Get locale asked in request, or system default if none found.
 *
 * Priority is as follows:
 *
 * - GET param `lang`: ?lang=en.
 * - Session param `lang`: $_SESSION['lang'].
 * - Most requested locale as given by accepted_locales().
 * - Fallback locale, passed in parameter (optional).
 * - Default system locale.
 *
 * @param string $default fallback locale to use if nothing could be selected, just before default system locale
 *
 * @return string selected locale
 */
function recommended_locale(string $default = ''): string
{
    /** @psalm-var array<string, string> $_GET */
    $locale = $_GET['lang'] ?? $_SESSION['lang'] ?? '';

    if (empty($locale)) {
        $locales = accepted_locales();
        $locale = empty($locales) ? '' : (string) array_keys($locales)[0];
    }

    if (empty($locale)) {
        $locale = $default ?: (function_exists('locale_get_default') ? locale_get_default() : '');
    }

    return $locale;
}

/**
 * Look up for Bearer Authorization token.
 */
function bearer(): ?string
{
    return sscanf(authorization_header() ?? '', 'Bearer %s', $token) > 0 ? $token : null;
}

/**
 * Returns the Authorization HTTP header.
 */
function authorization_header(): ?string
{
    return headers('Authorization');
}

/**
 * Returns the HTTP Request User-Agent.
 */
function user_agent(): ?string
{
    return headers('User-Agent');
}

function ip_address(string|int|null $version = null, string ...$ranges): string
{
    $flags = array_reduce(
        $ranges,
        fn (int $flags, string $range) => $flags | (defined($flag = 'FILTER_FLAG_' . strtoupper($range) . '_RANGE') ? constant($flag) : 0),
        $version && defined($flag = 'FILTER_FLAG_IPV' . $version) ? constant($flag) : FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6,
    ) | FILTER_NULL_ON_FAILURE;

    return Arr::first(
        array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ),
        static fn(Payload $key) => Arr::first(
            explode(',', $_SERVER[$key->value] ?? ''),
            static fn(Payload $ip) => filter_var(trim($ip->value), FILTER_VALIDATE_IP, $flags),
        ),
    ) ?? 'localhost';
}

function get_int(string $key): int|null
{
    return isset($_GET[$key]) && is_numeric($_GET[$key]) && is_int($val = $_GET[$key] * 1) ? $val : null;
}

function get_number(string $key)
{
    return isset($_GET[$key]) && is_numeric($_GET[$key]) ? $_GET[$key] * 1 : null;
}

function post_int(string $key): int|null
{
    return isset($_POST[$key]) && is_numeric($_POST[$key]) && is_int($val = $_POST[$key] * 1) ? $val : null;
}

function post_number(string $key)
{
    return isset($_POST[$key]) && is_numeric($_POST[$key]) ? $_POST[$key] * 1 : null;
}
