<?php

use Slim\App;
use App\Helpers\ResponseHandle;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use App\Routes\AuthRoute;

return function (App $app) {
    $app->get('/', function (Request $request, Response $response) {
        return ResponseHandle::success($response, [], 'Welcome to the API!');
    });

    (new AuthRoute($app))->register();

    $app->map(['GET', 'POST', 'PUT', 'DELETE'], '/{routes:.+}', function (Request $request, Response $response) {
        return ResponseHandle::error($response, 'Route not found', 404);
    });
};
