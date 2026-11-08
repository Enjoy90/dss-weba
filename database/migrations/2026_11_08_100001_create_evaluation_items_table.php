<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained();
            $table->foreignId('production_order_id')->constrained();
            $table->json('order_snapshot');
            $table->json('raw_values');
            $table->json('scale_values');
            $table->json('normalized_values');
            $table->json('weighted_values');
            $table->decimal('score', 10, 6);
            $table->unsignedInteger('system_rank');
            $table->unsignedInteger('final_rank');
            $table->unsignedInteger('previous_rank')->nullable();
            $table->unsignedInteger('waiting_days')->default(0);
            $table->string('rank_group', 20)->default('regular');
            $table->string('adjustment_reason', 255)->nullable();
            $table->json('warnings')->nullable();
            $table->timestamps();

            $table->unique(['evaluation_id', 'production_order_id']);
            $table->index(['evaluation_id', 'final_rank']);
            $table->index('production_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_items');
    }
};
