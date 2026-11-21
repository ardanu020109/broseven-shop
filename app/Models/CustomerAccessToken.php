<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CustomerAccessToken extends Model
{
    protected $fillable = [
        'order_id',
        'token_hash',
        'expires_at',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public static function issueFor(Order $order, ?CarbonInterface $expiresAt = null): array
    {
        $plain = Str::random(48);

        $token = static::create([
            'order_id' => $order->id,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => $expiresAt ?? now()->addDays(30),
        ]);

        return [$token, $plain];
    }

    public static function findValid(string $plain): ?self
    {
        return static::query()
            ->where('token_hash', hash('sha256', $plain))
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
