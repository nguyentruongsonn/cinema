<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    /**
     * Success response
     */
    protected function successResponse($data = null, $message = 'Success', $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'request_id' => $this->requestId(),
        ], $code);
    }

    /**
     * Error response
     */
    protected function errorResponse($message = 'Error', $code = 400, $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'request_id' => $this->requestId(),
        ], $code);
    }

    /**
     * Success shorthand
     */
    protected function ok($data = null, $message = 'Success'): JsonResponse
    {
        return $this->successResponse($data, $message, 200);
    }

    /**
     * Error shorthand
     */
    protected function error($message = 'Error', $code = 400, $errors = null): JsonResponse
    {
        return $this->errorResponse($message, $code, $errors);
    }

    /**
     * Unauthorized shorthand
     */
    protected function unauthorized($message = 'Unauthorized'): JsonResponse
    {
        return $this->errorResponse($message, 401);
    }

    /**
     * Not found shorthand
     */
    protected function notFound($message = 'Not found'): JsonResponse
    {
        return $this->errorResponse($message, 404);
    }

    /**
     * Paginated response
     */
    protected function paginatedResponse($data, $message = 'Success', $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data->items(),
            'pagination' => [
                'total' => $data->total(),
                'per_page' => $data->perPage(),
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
            ],
            'request_id' => $this->requestId(),
        ], $code);
    }

    private function requestId(): ?string
    {
        return request()->attributes->get('request_id');
    }
}
