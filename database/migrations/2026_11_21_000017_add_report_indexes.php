<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['status', 'completed_at'], 'orders_status_completed_at_index');
            $table->index(['cashier_id', 'status', 'completed_at'], 'orders_cashier_status_completed_index');
            $table->index(['cancelled_by', 'cancelled_at'], 'orders_cancelled_by_cancelled_at_index');
            $table->index(['cashier_id', 'approved_at'], 'orders_cashier_approved_at_index');
        });

        Schema::table('order_status_histories', function (Blueprint $table) {
            $table->index(['user_id', 'created_at'], 'osh_user_created_at_index');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->index('created_at', 'stock_movements_created_at_index');
        });

        Schema::table('order_returns', function (Blueprint $table) {
            $table->index('created_at', 'order_returns_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_status_completed_at_index');
            $table->dropIndex('orders_cashier_status_completed_index');
            $table->dropIndex('orders_cancelled_by_cancelled_at_index');
            $table->dropIndex('orders_cashier_approved_at_index');
        });

        Schema::table('order_status_histories', function (Blueprint $table) {
            $table->dropIndex('osh_user_created_at_index');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex('stock_movements_created_at_index');
        });

        Schema::table('order_returns', function (Blueprint $table) {
            $table->dropIndex('order_returns_created_at_index');
        });
    }
};