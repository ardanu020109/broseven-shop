<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'customer_name',
        'customer_phone',
        'notes',
        'status',
        'total_amount',
        'payment_method',
        'barcode_code',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'total_amount' => 'decimal:2',
            'barcode_used_at' => 'datetime',
            'approved_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'stock_deducted_at' => 'datetime',
            'stock_restored_at' => 'datetime',
        ];
    }

    public function scopeWithStatus(Builder $query, OrderStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', OrderStatus::SELESAI->value);
    }

    public function scopeVisibleToCashier(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $group) use ($user) {
            $group->where('status', OrderStatus::DRAFT->value)
                ->orWhere('cashier_id', $user->getKey())
                ->orWhere('cancelled_by', $user->getKey());
        });
    }

    public function isVisibleToCashier(User $user): bool
    {
        return $this->status === OrderStatus::DRAFT
            || $this->isHandledBy($user)
            || ($this->cancelled_by !== null && (int) $this->cancelled_by === (int) $user->getKey());
    }

    public function isHandledBy(User $user): bool
    {
        return $this->cashier_id !== null && (int) $this->cashier_id === (int) $user->getKey();
    }

    public function isWithinReturnWindow(): bool
    {
        $days = (int) config('broseven.return_window_days', 0);

        if ($days <= 0) {
            return true;
        }

        return $this->completed_at !== null && $this->completed_at->gte(now()->subDays($days));
    }

    public function loadDetail(): static
    {
        return $this->load([
            'items.product.unit',
            'cashier:id,name',
            'canceller:id,name',
            'statusHistories' => fn ($query) => $query->with('user:id,name')->orderBy('id'),
        ]);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id')->withTrashed();
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by')->withTrashed();
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function accessTokens(): HasMany
    {
        return $this->hasMany(CustomerAccessToken::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(OrderReturn::class);
    }

    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }
}
