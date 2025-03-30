<?php

namespace App\Middleware;

use App\Models\ApiConnectionModel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use App\Utils\TokenUtils;

class AuthMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandler $handler): Response
    {
        $authHeader = $request->getHeaderLine('Authorization');

        if (empty($authHeader)) {
            return $this->unauthorizedResponse('Authorization header missing');
        }

        $tokenParts = explode('.', str_replace('Bearer ', '', $authHeader));

        if (count($tokenParts) !== 2) {
            return $this->unauthorizedResponse('Invalid token format');
        }

        [$connectionName, $secretKey] = $tokenParts;
        $existingConnection = ApiConnectionModel::where('connection_name', $connectionName)->first();

        if (!$existingConnection) {
            return $this->unauthorizedResponse('Invalid connection name');
        }

        $token = $existingConnection->connection_key . '.' . $secretKey;

        try {
            $decoded = TokenUtils::decodeToken($token);
            $request = $request->withAttribute('service_detail', (array) $decoded);
            return $handler->handle($request);
        } catch (\Exception $e) {
            return $this->unauthorizedResponse('Token validation failed: ' . $e->getMessage());
        }
    }

    private function unauthorizedResponse(string $message): Response
    {
        $response = new \Slim\Psr7\Response();
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => $message,
        ]));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(401);
    }
}
