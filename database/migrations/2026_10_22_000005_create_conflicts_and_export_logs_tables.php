<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_conflicts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_assignment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('conflict_date');
            $table->string('type', 40);
            $table->string('severity', 20)->default('warning');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('open');
            $table->string('source', 20)->default('auto');
            $table->string('fingerprint', 64)->unique();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['conflict_date', 'severity']);
            $table->index(['department_id', 'status']);
            $table->index(['schedule_assignment_id']);
        });

        Schema::create('export_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40);
            $table->string('format', 10)->default('xlsx');
            $table->string('file_name');
            $table->json('filters')->nullable();
            $table->unsignedInteger('row_count')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_logs');
        Schema::dropIfExists('schedule_conflicts');
    }
};
