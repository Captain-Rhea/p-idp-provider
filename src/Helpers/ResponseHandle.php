<?php

namespace App\Helpers;

use Psr\Http\Message\ResponseInterface as Response;

class ResponseHandle
{
    /**
     * ส่ง Response แบบสำเร็จ
     */
    public static function success(Response $response, $data = [], $message = 'Success', $code = 200): Response
    {
        $payload = [
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ];

        $response->getBody()->write(json_encode($payload));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($code);
    }

    /**
     * ส่ง Response แบบข้อผิดพลาด
     */
    public static function error(Response $response, $message = 'An error occurred', $code = 500): Response
    {
        $payload = [
            'status' => 'error',
            'message' => $message
        ];

        $response->getBody()->write(json_encode($payload));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($code);
    }
}
