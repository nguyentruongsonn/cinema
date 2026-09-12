<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected function successResponse($data = null, $message = 'Success', $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'request_id' => $this->requestId(),
        ], $code);
    }

    protected function errorResponse($message = 'Error', $code = 400, $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'request_id' => $this->requestId(),
        ], $code);
    }

    protected function ok($data = null, $message = 'Success'): JsonResponse
    {
        return $this->successResponse($data, $message, 200);
    }

    protected function error($message = 'Error', $code = 400, $errors = null): JsonResponse
    {
        return $this->errorResponse($message, $code, $errors);
    }

    protected function unauthorized($message = 'Unauthorized'): JsonResponse
    {
        return $this->errorResponse($message, 401);
    }

    protected function notFound($message = 'Not found'): JsonResponse
    {
        return $this->errorResponse($message, 404);
    }

    protected function paginatedResponse($data, $message = 'Success', $code = 200, ?callable $transformer = null): JsonResponse
    {
        $items = $transformer === null
            ? $data->items()
            : collect($data->items())->map($transformer)->values()->all();

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $items,
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
