<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('renewals', function (Blueprint $table) {
            $table->id();
            /** Dominio, licencia o servicio anual: caducan igual y se avisan igual. */
            $table->morphs('renewable');
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            /** La línea cobrable que se generó al renovar, si hubo que generarla. */
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->date('due_date');
            $table->string('status');
            $table->decimal('amount', 10, 2)->nullable();
            $table->char('currency', 3)->default('MXN');
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            /** Un ciclo por vencimiento: la corrida diaria no debe duplicarlos. */
            $table->unique(['renewable_type', 'renewable_id', 'due_date'], 'renewals_cycle_unique');
            $table->index(['status', 'due_date']);
            $table->index(['client_id', 'due_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('renewals');
    }
};
