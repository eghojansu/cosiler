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
    return handle('get', $path, $callback, $request);
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
    return handle('post', $path, $callback, $request);
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
    return handle('put', $path, $callback, $request);
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
    return handle('delete', $path, $callback, $request);
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
    return handle('options', $path, $callback, $request);
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
    return handle('any', $path, $callback, $request);
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
function handle($method, string $path, $handler, $request = null)
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
    $resources = array(
        array('get', null, 'index'),
        array('get', '/create', 'create'),
        array('get', '/{'.$id.'}/edit', 'edit'),
        array('get', '/{'.$id.'}', 'show'),
        array('post', null, 'store'),
        array('put', '/{'.$id.'}', 'update'),
        array('delete', '/{'.$id.'}', 'destroy'),
    );

    return Cosiler\first($resources, fn($args) => handle($args[0], $base_path.$args[1], $resources_path.'/'.$args[2].'.php', $request));
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

    $flags = \FilesystemIterator::KEY_AS_PATHNAME | \FilesystemIterator::CURRENT_AS_FILEINFO | \FilesystemIterator::SKIP_DOTS;
    $directory = new \RecursiveDirectoryIterator($realpath, $flags);
    $iterator = new \RecursiveIteratorIterator($directory);
    $regex = new \RegexIterator($iterator, '/^.+\.php$/i', \RecursiveRegexIterator::GET_MATCH);

    $files = \array_keys(\iterator_to_array($regex));
    $cut = \strlen($realpath);
    $withPrefix = \rtrim($prefix, '/');

    \sort($files);

    foreach ($files as $filename) {
        $cut_filename = \substr((string) $filename, $cut);

        list($method, $path) = routify($cut_filename);

        $path = '/' === $path ? ($withPrefix ?: $path) : $withPrefix . $path;
        $result = handle($method, $path, (string) $filename, $request);

        if (did_match()) {
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

        $path_params = \array_map(fn(\ReflectionParameter $param)  => $param->isOptional() ? "?{{$param->name}}?" : "{{$param->name}}", $method->getParameters());
        $path_segments = \array_map('strtolower', \array_slice($specs, 1));
        $path_segments = \array_filter($path_segments, fn(string $segment) => 'index' !== $segment);
        $path_segments = \array_merge($path_segments, $path_params);

        \array_unshift($path_segments, $basePath);

        handle(
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
 * Avoids routes to be called even on a match.
 *
 * @return void
 */
function cancel(): void
{
    Container\co(CANCEL, true);
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
