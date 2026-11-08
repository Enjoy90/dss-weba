<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained();
            $table->foreignId('criterion_id')->constrained('criteria');
            $table->decimal('raw_value', 15, 2)->nullable();
            $table->boolean('is_overridden')->default(false);
            $table->string('override_reason', 255)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamp('value_updated_at')->nullable();
            $table->timestamps();

            $table->unique(['production_order_id', 'criterion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
