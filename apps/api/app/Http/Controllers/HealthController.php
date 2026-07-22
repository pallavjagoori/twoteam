<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    public function live(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'service' => 'twoteam-api',
        ]);
    }

    public function ready(): JsonResponse
    {
        $database = $this->databaseReady();
        $cache = $this->cacheReady();
        $ready = $database && $cache;

        return response()->json([
            'status' => $ready ? 'ready' : 'unavailable',
            'service' => 'twoteam-api',
            'dependencies' => [
                'database' => $database ? 'ok' : 'unavailable',
                'cache' => $cache ? 'ok' : 'unavailable',
            ],
        ], $ready ? 200 : 503);
    }

    private function databaseReady(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function cacheReady(): bool
    {
        try {
            $key = 'health:'.getmypid();
            Cache::put($key, 'ok', 10);
            $ready = Cache::get($key) === 'ok';
            Cache::forget($key);

            return $ready;
        } catch (Throwable) {
            return false;
        }
    }
}
