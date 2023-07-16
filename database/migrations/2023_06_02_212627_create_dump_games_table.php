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
        Schema::create('dump_games', function (Blueprint $table) {
            $table->id();
            $table->string('outer_id')->unique();
            $table->string('game_name');
            $table->string('name');
            $table->string('region')->nullable();
            $table->string('min_stock')->nullable();
            $table->string('max_stock')->nullable();
            $table->float('min_price')->nullable();
            $table->string('dump')->nullable();
            $table->string('competitor_current_lowest_price')->nullable();
            $table->string('our_price')->nullable();
            $table->text('link')->nullable();
            $table->text('link2')->nullable();
            $table->timestamps();
            $table->timestamp('our_price_updated_at')->nullable();

            $table->index(['game_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dump_games');
    }
};
