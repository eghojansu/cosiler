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

function random(int $len = 8, string $salt = null): string
{
    $min = max(4, min(128, $len));
    $saltiness = $salt ?? bin2hex(random_bytes($len));

    do {
        $hex = md5($saltiness . uniqid('', true));
        $pack = pack('H*', $hex);
        $tmp = base64_encode($pack);
        $uid = preg_replace("#(*UTF8)[^A-Za-z0-9]#", '', $tmp);
    } while (strlen($uid) < $min);

    return substr($uid, 0, $min);
}

function random_up(int $len = 8, string $salt = null): string
{
    return strtoupper(random($len, $salt));
}
