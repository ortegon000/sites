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
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            /** De qué cotización salió, cuando salió de una. */
            $table->foreignId('quote_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number')->unique();
            $table->string('title');
            $table->string('status');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->char('currency', 3)->default('MXN');
            /**
             * El texto completo del contrato, no solo sus datos: se genera con
             * los montos y vigencias del día en que se emite y a partir de ahí
             * es el documento. Si el servicio sube de precio el mes que entra,
             * el contrato firmado tiene que seguir diciendo lo que se firmó.
             */
            $table->longText('body');
            $table->string('signed_by')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'status']);
        });

        Schema::create('contract_service', function (Blueprint $table) {
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();

            $table->primary(['contract_id', 'service_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contract_service');
        Schema::dropIfExists('contracts');
    }
};
