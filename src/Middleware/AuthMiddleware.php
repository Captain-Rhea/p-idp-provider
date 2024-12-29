<?php

namespace App\Middleware;

use App\Helpers\VerifyUserStatus;
use App\Models\User;
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

        $isGuardEnabled = filter_var($_ENV['API_GUARD'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (!$isGuardEnabled) {
            return $handler->handle($request);
        }

        if (empty($authHeader)) {
            return $this->unauthorizedResponse('Authorization header missing');
        }

        $token = str_replace('Bearer ', '', $authHeader);

        try {
            $decoded = TokenUtils::decodeToken($token);
            $request = $request->withAttribute('user', (array) $decoded);

            $user = $request->getAttribute('user');
            $userStatus = User::where('user_id', $user['user_id'])->first();

            $statusCheckResponse = VerifyUserStatus::check($userStatus['status'], new \Slim\Psr7\Response());
            if ($statusCheckResponse) {
                return $statusCheckResponse;
            }

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
