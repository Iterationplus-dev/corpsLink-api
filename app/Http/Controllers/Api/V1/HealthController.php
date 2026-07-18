<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    public function show(): JsonResponse
    {
        try {
            DB::connection()->getPdo();
            $databaseOk = true;
        } catch (Throwable) {
            $databaseOk = false;
        }

        return $this->success([
            'status' => $databaseOk ? 'ok' : 'degraded',
            'database' => $databaseOk ? 'ok' : 'unavailable',
        ], $databaseOk ? 200 : 503);
    }
}
