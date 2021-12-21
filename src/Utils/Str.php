<?php

namespace Ekok\Cosiler\Utils\Str;

function fixslashes(string $str): string
{
    return \strtr($str, array('\\' => '/', '//' => '/'));
}

function split(string $str, string $symbols = ',;|'): array
{
    return \array_filter(\array_map('Ekok\\Cosiler\\cast', \preg_split('/[' . $symbols . ']/i', $str, 0, PREG_SPLIT_NO_EMPTY)));
}

function quote(string $text, string $open = '"', string $close = null, string $delimiter = '.'): string
{
    $a = $open;
    $b = $close ?? $a;

    return $a . str_replace($delimiter, $b . $delimiter . $a, $text) . $b;
}

function case_camel(string $text): string
{
    return str_replace('_', '', lcfirst(ucwords($text, '_')));
}

function case_snake(string $text): string
{
    return strtolower(preg_replace('/\p{Lu}/', '_$0', lcfirst($text)));
}
