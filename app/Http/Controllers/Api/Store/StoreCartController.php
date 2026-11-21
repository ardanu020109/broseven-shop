<?php

namespace App\Http\Controllers\Api\Store;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\CartValidateRequest;
use App\Services\CartPricingService;
use Illuminate\Http\JsonResponse;

class StoreCartController extends Controller
{
    public function validateCart(CartValidateRequest $request, CartPricingService $pricing): JsonResponse
    {
        $result = $pricing->price($request->validated('items'));

        return response()->json([
            'lines' => $result['lines'],
            'removed' => $result['removed'],
            'total' => $result['total'],
        ]);
    }
}
