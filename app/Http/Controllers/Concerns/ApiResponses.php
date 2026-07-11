<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Every successful response returns the payload directly — no envelope.
 * The frontend contract (src/types/api.ts) declares an exact response
 * shape per endpoint; controllers pass that shape straight through.
 */
trait ApiResponses
{
    protected function success(JsonResource|ResourceCollection|array|null $data = null, int $status = 200): JsonResponse
    {
        // Symfony's JsonResponse constructor silently swaps a null $data for
        // an empty ArrayObject (renders "{}"), which breaks any endpoint
        // that genuinely needs to return JSON null (e.g. "no active seat
        // hold"). Route null through setData() instead, which doesn't have
        // that substitution, so it actually encodes to "null".
        if ($data === null) {
            return response()->json()->setStatusCode($status)->setData(null);
        }

        return response()->json($data, $status);
    }

    protected function created(JsonResource|ResourceCollection|array|null $data = null): JsonResponse
    {
        return $this->success($data, 201);
    }

    protected function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }
}
