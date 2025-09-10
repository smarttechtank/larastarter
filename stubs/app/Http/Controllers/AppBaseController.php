<?php

namespace App\Http\Controllers;

/**
 * Base controller class that provides common response formatting methods
 * for API controllers throughout the application.
 */
class AppBaseController extends Controller
{
    /**
     * Create a standardized success response array.
     *
     * @param string $message Success message to include in response
     * @param mixed $data Data to include in response
     * @return array Formatted response array
     */
    public static function makeResponse(string $message, mixed $data): array
    {
        return [
            'success' => true,
            'data'    => $data,
            'message' => $message,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Create a standardized error response array.
     *
     * @param string $message Error message to include in response
     * @param array $data Optional error data to include in response
     * @return array Formatted error response array
     */
    public static function makeError(string $message, array $data = []): array
    {
        $res = [
            'success' => false,
            'message' => $message,
            'timestamp' => now()->toISOString(),
        ];

        if (!empty($data)) {
            $res['data'] = $data;
        }

        return $res;
    }

    /**
     * Send a JSON success response with data.
     *
     * @param mixed $result Data to include in response
     * @param string $message Success message
     * @param int $code HTTP status code (default: 200)
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendResponse($result, $message, $code = 200)
    {
        return response()->json(self::makeResponse($message, $result), $code);
    }

    /**
     * Send a JSON error response.
     *
     * @param string $error Error message
     * @param int $code HTTP status code (default: 404)
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendError($error, $code = 404)
    {
        return response()->json(self::makeError($error), $code);
    }

    /**
     * Send a JSON success response without data.
     *
     * @param string $message Success message
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendSuccess($message)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'timestamp' => now()->toISOString(),
        ], 200);
    }
}
