<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\CreateOrderRequest;
use App\Http\Resources\CustomerOrderResource;
use App\Models\CustomerAccessToken;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreOrderController extends Controller
{
    public function store(CreateOrderRequest $request, OrderService $orders): JsonResponse
    {
        [$order, $token, $created] = $orders->createFromCart($request->validated());

        $order->load('items.product');

        return response()->json([
            'message' => 'Pesanan berhasil dibuat.',
            'token' => $token,
            'data' => CustomerOrderResource::make($order)->resolve(),
        ], $created ? 201 : 200);
    }

    public function show(Request $request, string $orderNumber): JsonResponse
    {
        $notFound = response()->json([
            'message' => 'Pesanan tidak ditemukan atau token tidak valid.',
        ], 404);

        $plain = (string) $request->header('X-Order-Token', '');

        if ($plain === '' || strlen($plain) > 100) {
            return $notFound;
        }

        $token = CustomerAccessToken::findValid($plain);

        if ($token === null) {
            return $notFound;
        }

        $order = $token->order()->with('items.product')->first();

        if ($order === null || ! hash_equals($order->order_number, strtoupper(trim($orderNumber)))) {
            return $notFound;
        }

        if ($token->last_used_at === null || $token->last_used_at->lt(now()->subMinutes(5))) {
            $token->update(['last_used_at' => now()]);
        }

        return response()->json([
            'data' => CustomerOrderResource::make($order)->resolve(),
        ]);
    }
}
