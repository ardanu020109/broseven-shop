<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class OrderWorkflowException extends RuntimeException
{
    public function __construct(
        string $message,
        private int $status = 409,
        private array $extra = [],
    ) {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(
            array_merge(['message' => $this->getMessage()], $this->extra),
            $this->status
        );
    }
}
