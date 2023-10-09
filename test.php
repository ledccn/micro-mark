<?php

use Ledc\Mark\App;
use Ledc\Mark\Utils;

require __DIR__ . '/vendor/autoload.php';

$api = new App('http://0.0.0.0:3000');

$api->count = 4; // process count

$api->any('/', function ($request) {
    return 'Hello World';
});

$api->get('/hello/{name}', function ($request, $name) {
    return "Hello $name";
});

$api->post('/user/create', function ($request) {
    return Utils::json(['code' => 0, 'msg' => 'ok']);
});

$api->group('/api', function (App $api) {
    $api->any('/test', function ($request) {
        return 'Hello test';
    });
});

$api->start();
