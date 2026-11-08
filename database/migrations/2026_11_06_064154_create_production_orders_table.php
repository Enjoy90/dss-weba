<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->unsignedInteger('quantity');
            $table->date('order_date');
            $table->date('deadline_date');
            $table->decimal('unit_price', 15, 2)->nullable();
            $table->string('status', 20)->default('queued')->index();
            $table->string('priority', 20)->default('normal');
            $table->string('lock_type', 20)->default('none')->index();
            $table->string('lock_reason', 255)->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users');
            $table->timestamp('locked_at')->nullable();
            $table->date('lock_until')->nullable();
            $table->string('notes', 500)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index('deadline_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_orders');
    }
};
