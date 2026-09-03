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
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            /** Opcional, como en los servicios: se cotiza al cliente, no al proyecto. */
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            /** La línea cobrable que nació al aceptarla. */
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            /** Marcado a mano al cotizar: al aceptarse nace un proyecto en vez de una línea suelta. */
            $table->boolean('is_project')->default(false);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category')->default('other');
            $table->string('billing_frequency');
            $table->decimal('amount', 10, 2);
            $table->char('currency', 3)->default('MXN');
            $table->string('status');
            $table->date('valid_until')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'status']);
            $table->index(['status', 'valid_until']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
