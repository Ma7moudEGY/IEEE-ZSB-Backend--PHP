<?php

use Core\Conatiner;

test('It can resovle something out of the container', function () {
    $container = new Conatiner();

    $container->bind('foo', fn() => 'foo');

    $result = $container->resolve('foo');

    expect($result)->toEqual('bar');
});
