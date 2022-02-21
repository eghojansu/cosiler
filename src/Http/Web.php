<?php

declare(strict_types=1);

namespace Ekok\Cosiler\Http;

use Ekok\Utils\Arr;
use Ekok\Utils\Str;
use Ekok\Utils\Payload;

class Web
{
    const GLOBALS = 'GET|POST|COOKIE|FILES|ENV|SERVER';
    const STATUS = array(
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

    /** @var bool */
    protected $builtinServer;

    /** @var array */
    protected $cache;

    /** @var array */
    protected $globals;

    public function __construct(bool|null $builtinServer = null, array|null $globals = null)
    {
        $this->builtinServer = $builtinServer ?? 'cli-server' === PHP_SAPI;
        $this->globals = $globals ?? array_reduce(
            explode('|', self::GLOBALS),
            static fn(array $globals, string $global) => $globals + array($global => $GLOBALS[$global] ?? array()),
            array(),
        );

        $this->processGlobals();
    }

    public function baseUrl(string $path = null, array $query = null): string
    {
        return $this->getBaseUrl() . $this->path($path, $query, false);
    }

    public function url(string $path = null, array $query = null): string
    {
        return $this->getBaseUrl() . $this->path($path, $query);
    }

    public function path(string $path = null, array $query = null, bool $entry = true): string
    {
        $str = '/' . ltrim($path ?? $this->getCurrentPath(), '/');

        if ($entry && ($add = $this->getEntryFile())) {
            $str = $add . $str;
        }

        if ($query || (null === $path && $this->globals['GET'])) {
            $str .= '?' . http_build_query($query ?? $this->globals['GET']);
        }

        return $str;
    }

    public function getGlobals(): array
    {
        return $this->globals;
    }

    public function setGlobals(array $globals): static
    {
        $this->globals = $globals + $this->globals;
        $this->baseUrl = null;
        $this->basePath = null;
        $this->entryFile = null;
        $this->scheme = null;
        $this->host = null;
        $this->port = null;

        return $this;
    }

    public function isBuiltinServer(): bool
    {
        return $this->builtinServer;
    }

    public function server(string $key)
    {
        return $this->globals['SERVER'][$key] ?? $this->globals['SERVER'][strtoupper($key)] ?? null;
    }

    public function header(string $key)
    {
        return $this->globals['SERVER']['HTTP_' . $key]
            ?? $this->globals['SERVER']['HTTP_' . strtoupper($key)]
            ?? $this->server($key)
            ?? $this->server(strtoupper(str_replace('-', '_', $key)))
            ?? null;
    }

    public function headers(): array
    {
        return Arr::each($this->globals['SERVER'], fn (Payload $header) => match (true) {
            'CONTENT_TYPE' === $header->key => $header->key('Content-Type'),
            'CONTENT_LENGTH' === $header->key => $header->key('Content-Length'),
            str_starts_with($header->key, 'HTTP_') => $header->key(ucwords(strtolower(str_replace('_', '-', substr($header->key, 5))), '-')),
            default => null,
        }, true);
    }

    public function cookie(string $key = null)
    {
        return $key ? ($this->globals['COOKIE'][$key] ?? null) : $this->globals['COOKIE'];
    }

    public function session(string $key = null, $value = null)
    {
        (PHP_SESSION_ACTIVE === session_status() || headers_sent()) || session_start();

        if ($key && null !== $value) {
            $_SESSION[$key] = $value;
        }

        return $key ? ($_SESSION[$key] ?? null) : $_SESSION;
    }

    public function flash(string $key = null)
    {
        $value = $this->session($key);

        if ($key) {
            unset($_SESSION[$key]);
        }

        return $value;
    }

    public function endSession(): static
    {
        (PHP_SESSION_ACTIVE === session_status() || headers_sent()) || session_start();

        session_unset();
        session_destroy();

        return $this;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl ?? ($this->baseUrl = (static function ($scheme, $host, $port, $base) {
            $baseUrl = $scheme . '://' . $host;

            if (in_array(intval($port), array(0, 80, 443))) {
                $baseUrl .= ':' . $port;
            }

            if ($base && '/' !== $base) {
                $baseUrl .= '/' . trim($base, '/');
            }

            return $baseUrl;
        })($this->getScheme(), $this->getHost(), $this->getPort(), $this->getBasePath()));
    }

    public function getBasePath(): string
    {
        return $this->basePath ?? $this->setBasePath(
            $this->builtinServer ? '' : Str::fixslashes(
                dirname($this->server('SCRIPT_NAME') ?? ''),
            ),
        )->basePath;
    }

    public function setBasePath(string|null $basePath): static
    {
        $this->basePath = $basePath;
        $this->baseUrl = null;

        return $this;
    }

    public function getEntryFile(): string
    {
        return $this->entryFile ?? $this->setEntryFile(
            $this->builtinServer ? '' : basename($this->server('SCRIPT_NAME') ?? ''),
        )->entryFile;
    }

    public function setEntryFile(string|null $entryFile): static
    {
        $this->entryFile = $entryFile;
        $this->baseUrl = null;

        return $this;
    }

    public function getScheme(): string
    {
        return $this->scheme ?? $this->setScheme(($this->server('HTTPS') ?? '') ? 'https' : 'http')->scheme;
    }

    public function setScheme(string|null $scheme): static
    {
        $this->scheme = $scheme;
        $this->baseUrl = null;

        return $this;
    }

    public function getHost(): string
    {
        return strstr(($this->host ?? $this->setHost($this->header('HOST'))->host ?? 'localhost') . ':', ':', true);
    }

    public function setHost(string|null $host): static
    {
        $this->host = $host;
        $this->baseUrl = null;

        return $this;
    }

    public function getPort(): string|int
    {
        return $this->port ?? $this->setPort($this->server('SERVER_PORT') ?? '')->port;
    }

    public function setPort(string|int|null $port): static
    {
        $this->port = $port;
        $this->baseUrl = null;

        return $this;
    }

    public function status(int $code, bool $throw = true): string
    {
        $text = self::STATUS[$code] ?? sprintf('Unsupported HTTP code: %s', $code);

        if (empty(self::STATUS[$code]) && $throw) {
            throw new \LogicException($text);
        }

        return $text;
    }

    public function error(int $code = 500, string $message = null, array $payload = null, array $headers = null)
    {
        throw new HttpException($code, $message, $payload, $headers);
    }

    protected function processGlobals(): static
    {
        $in = $this->builtinServer;
        $srv = $this->globals['SERVER'];

        $uri = rawurldecode(strstr(($srv['REQUEST_URI'] ?? '') . '?', '?', true));
        $script = $srv['SCRIPT_NAME'] ?? '';

        $basePath = $in ? '' : Str::fixslashes(dirname($script));
        $entry = $in ? '' : basename($script);
        $base = $basePath . ($entry ? '/' . $entry : '');
        $currentPath = '/' . ltrim('' === $base ? $uri : preg_replace("#^{$base}#", '', $uri, 1), '/');

        return $this;
    }
}
