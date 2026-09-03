<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            /** Una subtarea cuelga de su tarea: al borrar la madre se van con ella. */
            $table->foreignId('parent_id')->nullable()->constrained('project_tasks')->cascadeOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'parent_id', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_tasks');
    }
};
