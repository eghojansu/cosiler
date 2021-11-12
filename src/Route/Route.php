<?php

declare(strict_types=1);

namespace Ekok\Cosiler\Route;

use Ekok\Cosiler;
use Ekok\Cosiler\Container;
use Ekok\Cosiler\Http;
use Ekok\Cosiler\Http\Request;

const BASE_PATH = 'route_base_path';
const CANCEL = 'route_cancel';
const DID_MATCH = 'route_did_match';
const STOP_PROPAGATION = 'route_stop_propagation';

/**
 * Define a new route using the GET HTTP method.
 *
 * @param string                                                  $path     The HTTP URI to listen on
 * @param callable|string                                         $callback The callable to be executed or a string to be used with Cosiler\require_fn
 * @param null|array{0: string, 1: string}|ServerRequestInterface $request
 *
 * @return null|mixed
 */
function get(string $path, $callback, $request = null)
{
    return add('get', $path, $callback, $request);
}

/**
 * Define a new route using the POST HTTP method.
 *
 * @param string                                                  $path     The HTTP URI to listen on
 * @param callable|string                                         $callback The callable to be executed or a string to be used with Cosiler\require_fn
 * @param null|array{0: string, 1: string}|ServerRequestInterface $request
 *
 * @return null|mixed
 */
function post(string $path, $callback, $request = null)
{
    return add('post', $path, $callback, $request);
}

/**
 * Define a new route using the PUT HTTP method.
 *
 * @param string                                                  $path     The HTTP URI to listen on
 * @param callable|string                                         $callback The callable to be executed or a string to be used with Cosiler\require_fn
 * @param null|array{0: string, 1: string}|ServerRequestInterface $request
 *
 * @return null|mixed
 */
function put(string $path, $callback, $request = null)
{
    return add('put', $path, $callback, $request);
}

/**
 * Define a new route using the DELETE HTTP method.
 *
 * @param string                                                  $path     The HTTP URI to listen on
 * @param callable|string                                         $callback The callable to be executed or a string to be used with Cosiler\require_fn
 * @param null|array{0: string, 1: string}|ServerRequestInterface $request
 *
 * @return null|mixed
 */
function delete(string $path, $callback, $request = null)
{
    return add('delete', $path, $callback, $request);
}

/**
 * Define a new route using the OPTIONS HTTP method.
 *
 * @param string                                                  $path     The HTTP URI to listen on
 * @param callable|string                                         $callback The callable to be executed or a string to be used with Cosiler\require_fn
 * @param null|array{0: string, 1: string}|ServerRequestInterface $request
 *
 * @return null|mixed
 */
function options(string $path, $callback, $request = null)
{
    return add('options', $path, $callback, $request);
}

/**
 * Define a new route using the any HTTP method.
 *
 * @param string                                                  $path     The HTTP URI to listen on
 * @param callable|string                                         $callback The callable to be executed or a string to be used with Cosiler\require_fn
 * @param null|array{0: string, 1: string}|ServerRequestInterface $request
 *
 * @return null|mixed
 */
function any(string $path, $callback, $request = null)
{
    return add('any', $path, $callback, $request);
}

/**
 * Add a new route.
 *
 * @param string|string[]                                         $method   The HTTP request method to listen on
 * @param string                                                  $path     The HTTP URI to listen on
 * @param callable|string                                         $callback The callable to be executed or a string to be used with Cosiler\require_fn
 * @param null|array{0: string, 1: string}|ServerRequestInterface $request
 * @param mixed                                                   $handler
 *
 * @return null|mixed
 */
function add(string $method, string $path, $handler, $request = null)
{
    if (canceled() || (did_match() && is_propagation_stopped())) {
        return null;
    }

    $path = regexify($path);
    $call = $handler;

    if (is_string($call) && !is_callable($call)) {
        $call = Cosiler\require_fn($call);
    }

    $method_path = method_path($request);

    if (
        \count($method_path) >= 2
        && (Request\method_is($method, \strval($method_path[0])) || 'any' == $method)
        && \preg_match($path, \strval($method_path[1]), $params)
    ) {
        Container\co(DID_MATCH, true);

        return $call($params);
    }

    return null;
}

/**
 * Creates a resource route path mapping.
 *
 * @param string                                                  $base_path      The base for the resource
 * @param string                                                  $resources_path The base path name for the corresponding PHP files
 * @param null|array{0: string, 1: string}|ServerRequestInterface $request
 *
 * @return null|mixed
 */
function resource(string $base_path, string $resources_path, ?string $identity_param = null, $request = null)
{
    $base_path = '/'.\trim($base_path, '/');
    $resources_path = \rtrim($resources_path, '/');
    $id = $identity_param ?? 'id';

    /** @var array<\Closure(): mixed> $routes */
    $routes = array(
        /** @return mixed */
        static function () use ($base_path, $resources_path, $request) {
            return get($base_path, $resources_path.'/index.php', $request);
        },
        /** @return mixed */
        static function () use ($base_path, $resources_path, $request) {
            return get($base_path.'/create', $resources_path.'/create.php', $request);
        },
        /** @return mixed */
        static function () use ($base_path, $resources_path, $request, $id) {
            return get($base_path.'/{'.$id.'}/edit', $resources_path.'/edit.php', $request);
        },
        /** @return mixed */
        static function () use ($base_path, $resources_path, $request, $id) {
            return get($base_path.'/{'.$id.'}', $resources_path.'/show.php', $request);
        },
        /** @return mixed */
        static function () use ($base_path, $resources_path, $request) {
            return post($base_path, $resources_path.'/store.php', $request);
        },
        /** @return mixed */
        static function () use ($base_path, $resources_path, $request, $id) {
            return put($base_path.'/{'.$id.'}', $resources_path.'/update.php', $request);
        },
        /** @return mixed */
        static function () use ($base_path, $resources_path, $request, $id) {
            return delete($base_path.'/{'.$id.'}', $resources_path.'/destroy.php', $request);
        },
    );

    /** @var callable(): mixed $route */
    foreach ($routes as $route) {
        /** @var mixed $result */
        $result = $route();

        if (null !== $result) {
            return $result;
        }
    }

    return null;
}

/**
 * Iterates over the given $basePath listening for matching routified files.
 *
 * @param null|array{0: string, 1: string}|ServerRequestInterface $request
 *
 * @return null|mixed
 */
function files(string $basePath, string $prefix = '', $request = null)
{
    $realpath = \realpath($basePath);

    if (false === $realpath) {
        throw new \InvalidArgumentException("{$basePath} does not exists");
    }

    $directory = new \RecursiveDirectoryIterator($realpath);
    $iterator = new \RecursiveIteratorIterator($directory);
    $regex = new \RegexIterator($iterator, '/^.+\.php$/i', \RecursiveRegexIterator::GET_MATCH);

    $files = \array_keys(\iterator_to_array($regex));

    \sort($files);

    $cut = \strlen($realpath);
    $prefix = \rtrim($prefix, '/');

    foreach ($files as $filename) {
        $cut_filename = \substr((string) $filename, $cut);

        if (false === $cut_filename) {
            continue;
        }

        [$method, $path] = routify($cut_filename);

        if ('/' === $path) {
            if ($prefix) {
                $path = $prefix;
            }
        } else {
            $path = $prefix.$path;
        }

        /** @var mixed $result */
        $result = add($method, $path, (string) $filename, $request);

        if (null !== $result) {
            return $result;
        }
    }

    return null;
}

/**
 * Uses a class name to create routes based on its public methods.
 *
 * @param string                                                  $basePath  The prefix for all routes
 * @param class-string|object                                     $className The qualified class name
 * @param null|array{0: string, 1: string}|ServerRequestInterface $request
 *
 * @throws \ReflectionException
 */
function class_name(string $basePath, $className, $request = null): void
{
    $reflection = new \ReflectionClass($className);
    $object = $reflection->newInstance();

    $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

    foreach ($methods as $method) {
        $specs = \preg_split('/(?=[A-Z])/', $method->name);

        $path_segments = \array_map('strtolower', \array_slice($specs, 1));
        $path_segments = \array_filter($path_segments, fn(string $segment) => 'index' !== $segment);
        $path_params = \array_map(function (\ReflectionParameter $param) {
            $param_name = $param->getName();
            $param_name = "{{$param_name}}";

            if ($param->isOptional()) {
                $param_name = "?{$param_name}?";
            }

            return $param_name;
        }, $method->getParameters());

        $path_segments = \array_merge($path_segments, $path_params);

        \array_unshift($path_segments, $basePath);

        add(
            $specs[0],
            \implode('/', $path_segments),
            function (array $params) use ($method, $object) {
                foreach (\array_keys($params) as $key) {
                    if (!\is_int($key)) {
                        unset($params[$key]);
                    }
                }

                $args = \array_slice($params, 1);
                $method->invokeArgs($object, $args);
            },
            $request
        );
    } //end foreach
}

/**
 * @param null|array{0: string, 1: string}|ServerRequestInterface $request
 *
 * @return array{0: string, 1: string}
 *
 * @internal used to guess the given request method and path
 */
function method_path($request = null): array
{
    if (\is_array($request)) {
        return $request;
    }

    return array(Request\method(), Http\path());
}

/**
 * Returns true if routing is canceled.
 */
function canceled(): bool
{
    return (bool) Container\co(CANCEL);
}

/**
 * Resets default routing behaviour.
 */
function resume(): void
{
    Container\co(STOP_PROPAGATION, false);
    Container\co(CANCEL, false);
}

/**
 * Returns true if a Route has a match.
 */
function did_match(): bool
{
    return (bool) Container\co(DID_MATCH);
}

/**
 * Invalidate a route match.
 */
function purge_match(): void
{
    Container\co(DID_MATCH, false);
}

/**
 * Defines a base path for all routes.
 */
function base(string $path): void
{
    Container\co(BASE_PATH, $path);
}

/**
 * Returns true if a Propagation has stopped.
 */
function is_propagation_stopped(): bool
{
    return (bool) Container\co(STOP_PROPAGATION);
}

/**
 * Avoids routes to be called after the first match.
 */
function stop_propagation(): void
{
    Container\co(STOP_PROPAGATION, true);
}

/**
 * Turns a URL route path into a Regexp.
 *
 * @param string $path The HTTP path
 */
function regexify(string $path): string
{
    $patterns = array(
        '/{([A-z-]+)}/',
        '/{([A-z-]+):(.*)}/',
    );
    $replaces = array(
        '(?<$1>[A-z0-9_-]+)',
        '(?<$1>$2)',
    );
    $path = \preg_replace($patterns, $replaces, $path);
    $base = Container\co(BASE_PATH) ?? '';

    return "#^{$base}{$path}/?$#";
}

/**
 * Maps a filename to a route method-path pair.
 *
 * @return array{0: string, 1: string}
 */
function routify(string $filename): array
{
    $filename = \str_replace('\\', '/', $filename);
    $filename = \trim($filename, '/');
    $filename = \str_replace('/', '.', $filename);

    $tokens = \array_slice(\explode('.', $filename), 0, -1);
    $tokens = \array_map(function ($token) {
        if ('$' == $token[0]) {
            $token = '{'.\substr($token, 1).'}';
        }

        if ('@' == $token[0]) {
            $token = '?{'.\substr($token, 1).'}?';
        }

        return $token;
    }, $tokens);

    $method = \array_pop($tokens);
    $path = \implode('/', $tokens);
    $path = '/'.\trim(\str_replace('index', '', $path), '/');

    return array($method, $path);
}
