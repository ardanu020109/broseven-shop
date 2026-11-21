<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccessCheckController extends Controller
{
    public function superadmin(Request $request): JsonResponse
    {
        return $this->respond($request, 'Superadmin');
    }

    public function cashier(Request $request): JsonResponse
    {
        return $this->respond($request, 'Kasir');
    }

    public function adminMaster(Request $request): JsonResponse
    {
        return $this->respond($request, 'Admin Master');
    }

    private function respond(Request $request, string $area): JsonResponse
    {
        return response()->json([
            'message' => "Akses area {$area} diizinkan untuk {$request->user()->name}.",
        ]);
    }
}
