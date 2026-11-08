<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->nullable();
            $table->string('trigger', 20)->default('manual');
            $table->string('status', 20)->default('draft')->index();

            // dateTime, not timestamp: on MariaDB 10.4 the first timestamp column silently gets
            // ON UPDATE CURRENT_TIMESTAMP, which would rewrite the evaluation time on every status change.
            $table->dateTime('evaluated_at')->index();
            $table->foreignId('evaluated_by')->nullable()->constrained('users');

            $table->foreignId('submitted_by')->nullable()->constrained('users');
            $table->dateTime('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->dateTime('approved_at')->nullable();
            $table->string('rejection_note', 500)->nullable();
            $table->foreignId('applied_by')->nullable()->constrained('users');
            $table->dateTime('applied_at')->nullable();

            $table->unsignedSmallInteger('top_n')->nullable();
            $table->json('criteria_snapshot');
            $table->json('settings_snapshot');
            $table->unsignedInteger('order_count')->default(0);
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluations');
    }
};
