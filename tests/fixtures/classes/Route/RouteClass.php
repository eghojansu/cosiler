<?php

namespace Ekok\Cosiler\Test\Fixture\Route;

use Ekok\Cosiler\Route;

final class RouteClass
{
    public function getIndex()
    {
        echo 'className.index';
    }

    public function postFoo()
    {
        echo 'className.postFoo';
    }

    public function putFooBar()
    {
        echo 'className.putFooBar';
        Route\stop_propagation();
    }

    public function anyIndex(string $baz, string $qux, string $quux = null)
    {
        echo "className.$baz.$qux";
        Route\stop_propagation();
    }

    public static function staticMethod(): string
    {
        return 'static_method';
    }
}
