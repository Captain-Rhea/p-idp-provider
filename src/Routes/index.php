<?php

use Slim\App;
use App\Helpers\ResponseHandle;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use App\Routes\AuthRoute;
use App\Routes\MemberRoute;
use App\Routes\MyMemberRoute;

return function (App $app) {
    (new AuthRoute($app))->register();
    (new MemberRoute($app))->register();
    (new MyMemberRoute($app))->register();

    $app->map(['GET', 'POST', 'PUT', 'DELETE'], '/{routes:.+}', function (Request $request, Response $response) {
        return ResponseHandle::error($response, 'Route not found', 404);
    });
};
