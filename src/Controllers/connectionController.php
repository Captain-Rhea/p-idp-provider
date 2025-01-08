<?php

namespace App\Controllers;

use Exception;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Helpers\ResponseHandle;
use App\Models\ApiConnectionModel;
use App\Utils\TokenUtils;

class ConnectionController
{
    // GET /v1/connection
    public function getConnectionList(Request $request, Response $response): Response
    {
        try {
            $connections = ApiConnectionModel::all();
            return ResponseHandle::success($response, $connections, 'Connection list retrieved successfully');
        } catch (Exception $e) {
            return ResponseHandle::error($response, $e->getMessage(), 500);
        }
    }

    // POST /v1/connection
    public function createConnection(Request $request, Response $response): Response
    {
        try {
            $body = json_decode((string)$request->getBody());
            $connectionName = $body->connection_name ?? null;

            if (empty($connectionName)) {
                return ResponseHandle::error($response, "Connection name is required", 400);
            }

            $existingConnection = ApiConnectionModel::where('connection_name', $connectionName)->first();

            if ($existingConnection) {
                $token = TokenUtils::generateToken([
                    'connection_name' => $connectionName,
                ]);

                $token = explode('.', $token);
                $connectionKey = $token[0] . '.' . $token[1];
                $secretKey = $token[2];

                $existingConnection->update([
                    'connection_key' => $connectionKey,
                ]);

                return ResponseHandle::success($response, [
                    'secret_key' => $connectionName . '.' . $secretKey,
                ], 'Connection updated successfully');
            } else {
                $token = TokenUtils::generateToken([
                    'connection_name' => $connectionName,
                ]);

                $token = explode('.', $token);
                $connectionKey = $token[0] . '.' . $token[1];
                $secretKey = $token[2];

                ApiConnectionModel::create([
                    'connection_name' => $connectionName,
                    'connection_key' => $connectionKey,
                ]);

                return ResponseHandle::success($response, [
                    'secret_key' => $connectionName . '.' . $secretKey,
                ], 'Connection created successfully');
            }
        } catch (Exception $e) {
            return ResponseHandle::error($response, $e->getMessage(), 500);
        }
    }

    // DELETE /v1/connection/{id}
    public function deleteConnection(Request $request, Response $response, $args): Response
    {
        try {
            $id = $args['id'] ?? null;

            if (empty($id)) {
                return ResponseHandle::error($response, "Connection ID is required", 400);
            }

            $connection = ApiConnectionModel::find($id);

            if (!$connection) {
                return ResponseHandle::error($response, "Connection with ID $id not found", 404);
            }

            $connection->delete();

            return ResponseHandle::success($response, [], 'Connection deleted successfully');
        } catch (Exception $e) {
            return ResponseHandle::error($response, $e->getMessage(), 500);
        }
    }
}
