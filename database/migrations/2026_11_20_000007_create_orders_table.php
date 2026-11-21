<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 30)->unique();
            $table->string('customer_name', 100);
            $table->string('customer_phone', 20);
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('DRAFT');
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('payment_method', 20)->default('CASH');
            $table->string('barcode_code', 64)->unique();
            $table->timestamp('barcode_used_at')->nullable();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->foreignId('cashier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamp('stock_deducted_at')->nullable();
            $table->timestamp('stock_restored_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['cashier_id', 'status']);
            $table->index('customer_phone');
            $table->index('completed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
