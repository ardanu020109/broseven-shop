<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_settings', function (Blueprint $table) {
            $table->id();
            $table->char('primary_color', 7)->default('#06D6A0');
            $table->char('secondary_color', 7)->default('#FFD166');
            $table->char('danger_color', 7)->default('#EF233C');
            $table->char('background_color', 7)->default('#F7FFF7');
            $table->char('dark_color', 7)->default('#26547C');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_settings');
    }
};
