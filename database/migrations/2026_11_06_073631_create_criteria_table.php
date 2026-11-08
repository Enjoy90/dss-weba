<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('criteria', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name', 120);
            $table->string('unit', 30)->nullable();
            $table->string('direction', 20);
            $table->string('value_source', 30)->default('manual');
            $table->unsignedTinyInteger('importance')->default(3);
            $table->decimal('weight', 6, 4)->default(0);
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('criteria');
    }
};
