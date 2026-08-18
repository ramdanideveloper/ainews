<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

abstract class ApiController extends Controller
{
    protected function ok(mixed $data = null, string $message = 'OK')
    {
        return response()->json(['success' => true, 'code' => 'ok', 'message' => $message, 'data' => $data]);
    }

    protected function fail(string $code, string $message, int $status = 400, mixed $data = null)
    {
        return response()->json(['success' => false, 'code' => $code, 'message' => $message, 'data' => $data], $status);
    }
}
