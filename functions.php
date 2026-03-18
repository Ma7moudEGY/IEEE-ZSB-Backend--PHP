<?php

function dd($value) {
    echo "<pre>";
    var_dump($value);
    echo "</pre>";

    die();
}

function urlIs($value) {
    return $value === $_SERVER['REQUEST_URI'];
}

function authorize($condition, $status= Response::FORBIDDEN) {
    if (! $condition) {
        abort($status);
    }
}