<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('leave_type', 30);
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('duration_days', 5, 1)->default(1);
            $table->string('status', 20)->default('pending');
            $table->text('reason')->nullable();
            $table->text('supporting_information')->nullable();
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['start_date', 'end_date']);
        });

        Schema::create('absences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('absence_date');
            $table->string('type', 30)->default('sick');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'absence_date']);
        });

        Schema::create('off_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('type', 30)->default('regular');
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('off_days');
        Schema::dropIfExists('absences');
        Schema::dropIfExists('leave_requests');
    }
};
