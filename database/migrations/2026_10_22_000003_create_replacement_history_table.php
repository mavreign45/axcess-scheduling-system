<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('replacement_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('original_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('replacement_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('replaced_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('replaced_at');
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['schedule_assignment_id', 'replaced_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('replacement_history');
    }
};
