<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('offer_id')->nullable();
            $table->string('iteration')->nullable();
            $table->string('title')->nullable();
            $table->string('price')->nullable();
            $table->string('seller_outer_id')->nullable();
            $table->string('seller_outer_name')->nullable();
            $table->string('game_outer_id')->nullable();
            $table->string('game_outer_name')->nullable();
            $table->string('category_outer_id')->nullable();
            $table->string('category_outer_name')->nullable();
            $table->string('site_name')->nullable();
            $table->string('score')->nullable();
            $table->timestamp('updated')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();
            $table->index(['offer_id', 'site_name']);
            $table->index(['site_name', 'game_outer_name']);
            $table->index(['site_name', 'game_outer_name', 'category_outer_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
