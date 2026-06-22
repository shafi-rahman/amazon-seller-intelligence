<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

trait ApiResponse
{
    protected function success(mixed $data, int $status = 200, array $meta = []): JsonResponse
    {
        $response = ['data' => $data];

        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        return response()->json($response, $status);
    }

    protected function paginated(LengthAwarePaginator $paginator): JsonResponse
    {
        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'page'     => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total'    => $paginator->total(),
                'last_page'=> $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * Paginate, transforming each item through a JsonResource class so list
     * endpoints expose the SAME shape (computed fields, UUID ids, no raw columns)
     * as their detail endpoints. $resourceClass = e.g. ProductResource::class.
     */
    protected function paginatedThrough(LengthAwarePaginator $paginator, string $resourceClass): JsonResponse
    {
        $paginator->setCollection(
            $paginator->getCollection()->map(fn ($m) => (new $resourceClass($m))->resolve(request()))
        );

        return $this->paginated($paginator);
    }

    protected function created(mixed $data): JsonResponse
    {
        return $this->success($data, 201);
    }

    protected function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    protected function error(string $message, int $status = 400, ?array $errors = null): JsonResponse
    {
        $response = ['message' => $message];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $status);
    }
}
