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

        $token = str_replace('Bearer ', '', $authHeader);
        $token = explode('.', $token);
        $connectionName = $token[0];
        $secretKey = $token[1];
        $existingConnection = ApiConnectionModel::where('connection_name', $connectionName)->first();
        $token = $existingConnection->connection_key . '.' . $secretKey;

        try {
            $decoded = TokenUtils::decodeToken($token);
            $request = $request->withAttribute('service_detail', (array) $decoded);
            $request->getAttribute('service_detail');
            return $handler->handle($request);
        } catch (\Exception $e) {
            return $this->unauthorizedResponse($e->getMessage());
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
