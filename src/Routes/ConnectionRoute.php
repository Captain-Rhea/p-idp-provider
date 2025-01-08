<?php

namespace App\Routes;

use App\Controllers\ConnectionController;

class ConnectionRoute extends BaseRoute
{
    public function register(): void
    {
        $this->app->group('/v1', function ($group) {
            $group->get('/connection', [ConnectionController::class, 'getConnectionList']);
            $group->post('/connection', [ConnectionController::class, 'createConnection']);
            $group->delete('/connection/{id}', [ConnectionController::class, 'deleteConnection']);
        });
    }
}
