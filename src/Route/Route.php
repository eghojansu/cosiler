<?php

declare(strict_types=1);

namespace Ekok\Cosiler\Route;

use Ekok\Utils\Arr;
use Ekok\Utils\File;
use Ekok\Utils\Payload;

use function Ekok\Cosiler\storage;
use function Ekok\Cosiler\require_fn;
use function Ekok\Cosiler\Http\Request\path;
use function Ekok\Cosiler\Http\Request\method;
use function Ekok\Cosiler\Http\Request\method_is;

const CANCEL = 'route_cancel';
const DID_MATCH = 'route_did_match';
const STOP_PROPAGATION = 'route_stop_propagation';
const GLOBALS = 'route_globals';

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
        $call = require_fn($call, globals());
    }

    $method_path = method_path($request);

    if (
        count($method_path) >= 2
        && (method_is($method, strval($method_path[0])) || 'any' == $method)
        && preg_match($path, strval($method_path[1]), $params)
    ) {
        storage(DID_MATCH, true);

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
    $base_path = '/'.trim($base_path, '/');
    $resources_path = rtrim($resources_path, '/');
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

    return Arr::first($resources, fn(Payload $args) => handle($args->value[0], $base_path.$args->value[1], $resources_path.'/'.$args->value[2].'.php', $request));
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
    $realpath = realpath($basePath);

    if (false === $realpath) {
        throw new \InvalidArgumentException("{$basePath} does not exists");
    }

    $cut = strlen($realpath);
    $withPrefix = rtrim($prefix, '/');
    $files = array_keys(iterator_to_array(File::traverse($realpath, '/^.+\.php$/i', \RecursiveRegexIterator::GET_MATCH)));

    sort($files);

    foreach ($files as $filename) {
        $file = substr($filename, $cut);
        $hide = '_' === ($file[1] ?? null);

        if ($hide) {
            continue;
        }

        list($method, $path) = routify($file);

        $path = '/' === $path ? ($withPrefix ?: $path) : $withPrefix . $path;
        $result = handle($method, $path, $filename, $request);

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
 * @param null|array{0: string, 1: string} $request
 *
 * @throws \ReflectionException
 */
function class_name(string $basePath, $className, $request = null)
{
    $reflection = new \ReflectionClass($className);
    $object = $reflection->newInstance();

    $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

    foreach ($methods as $method) {
        $specs = preg_split('/(?=[A-Z])/', $method->name);

        $path_params = array_map(fn(\ReflectionParameter $param)  => $param->isOptional() ? "?{{$param->name}}?" : "{{$param->name}}", $method->getParameters());
        $path_segments = array_map('strtolower', array_slice($specs, 1));
        $path_segments = array_filter($path_segments, fn(string $segment) => 'index' !== $segment);
        $path_segments = array_merge($path_segments, $path_params);

        array_unshift($path_segments, $basePath);

        $result = handle(
            $specs[0],
            implode('/', $path_segments),
            static function (array $params) use ($method, $object) {
                $args = array_slice(array_filter($params, 'is_numeric', ARRAY_FILTER_USE_KEY), 1);
                $method->invokeArgs($object, $args);
            },
            $request
        );

        if (did_match()) {
            return $result;
        }
    } //end foreach

    return null;
}

function globals(): array
{
    return storage(GLOBALS) ?? array();
}

function globals_add($key, $value = null, bool $replace = false): void
{
    $add = is_array($key) ? $key : array($key => $value);

    storage(GLOBALS, $replace ? $add : $add + globals());
}

/**
 * @param null|array{0: string, 1: string} $request
 *
 * @return array{0: string, 1: string}
 *
 * @internal used to guess the given request method and path
 */
function method_path($request = null): array
{
    if (is_array($request)) {
        return $request;
    }

    return array(method(), path());
}

/**
 * Avoids routes to be called even on a match.
 *
 * @return void
 */
function cancel(): void
{
    storage(CANCEL, true);
}

/**
 * Returns true if routing is canceled.
 */
function canceled(): bool
{
    return (bool) storage(CANCEL);
}

/**
 * Resets default routing behaviour.
 */
function resume(): void
{
    storage(STOP_PROPAGATION, false);
    storage(CANCEL, false);
}

/**
 * Returns true if a Route has a match.
 */
function did_match(): bool
{
    return (bool) storage(DID_MATCH);
}

/**
 * Invalidate a route match.
 */
function purge_match(): void
{
    storage(DID_MATCH, false);
}

/**
 * Returns true if a Propagation has stopped.
 */
function is_propagation_stopped(): bool
{
    return (bool) storage(STOP_PROPAGATION);
}

/**
 * Avoids routes to be called after the first match.
 */
function stop_propagation(): void
{
    storage(STOP_PROPAGATION, true);
}

/**
 * Turns a URL route path into a Regexp.
 *
 * @param string $path The HTTP path
 */
function regexify(string $path): string
{
    return (
        '#^' .
        preg_replace_callback(
            '/{(\w+)(?::([^}]+)|(@))?}/',
            static fn (array $m) => '(?<' . $m[1] . '>' . (($m[3] ?? '') ? '.*' : ($m[2] ?? '[\w-]+')) . ')',
            $path,
        ) .
        '/?$#'
    );
}

/**
 * Maps a filename to a route method-path pair.
 *
 * @return array{0: string, 1: string}
 */
function routify(string $filename): array
{
    $file = trim(str_replace(array('\\', '/'), '.', $filename), '.');
    $tokens = array_map(function ($token) {
        if ('@' === $token[0]) {
            $add = null;
            $pos = 1;

            if ('@' === $token[1]) {
                $add = '?';
                $pos = 2;
            }

            $token = $add . '{' . substr($token, $pos) . '}' . $add;
        }

        return $token;
    }, array_slice(explode('.', $file), 0, -1));

    $method = array_pop($tokens);
    $path = implode('/', $tokens);
    $path = '/' . trim(str_replace('index', '', $path), '/');

    return array($method, $path);
}
