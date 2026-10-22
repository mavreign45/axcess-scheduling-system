<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_type_id')->constrained()->restrictOnDelete();
            $table->date('schedule_date');
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->string('status', 20)->default('published');
            $table->boolean('is_standby')->default(false);
            $table->string('standby_status', 20)->default('not_applicable');
            $table->foreignId('replacement_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('replacement_reason')->nullable();
            $table->timestamp('replaced_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['schedule_date', 'status']);
            $table->index(['department_id', 'schedule_date']);
            $table->index(['user_id', 'schedule_date']);
            $table->index(['is_standby', 'schedule_date']);
        });

        Schema::create('schedule_breaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_assignment_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->unsignedSmallInteger('duration_minutes')->default(0);
            $table->timestamps();

            $table->index('schedule_assignment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_breaks');
        Schema::dropIfExists('schedule_assignments');
    }
};
