<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('criterion_scales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('criterion_id')->constrained('criteria')->cascadeOnDelete();
            $table->unsignedTinyInteger('score');
            $table->string('label', 80)->nullable();
            $table->decimal('lower_bound', 15, 2)->nullable();
            $table->decimal('upper_bound', 15, 2)->nullable();
            $table->timestamps();

            $table->unique(['criterion_id', 'score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('criterion_scales');
    }
};
