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
            /** Marcado a mano al cotizar: al aceptarse nace un proyecto en vez de una línea suelta. */
            $table->boolean('is_project')->default(false);
            /** El enlace público (/c/{public_token}) para que el cliente la vea y decida sin cuenta. */
            $table->string('public_token', 40)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            /** El monto vive en los renglones (quote_line_items): una cotización de
             *  agencia rara vez es un solo concepto. */
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

        Schema::create('quote_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            /** La línea cobrable que nació de este renglón al aceptarse la cotización. */
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category')->default('other');
            $table->string('billing_frequency');
            $table->decimal('amount', 10, 2);
            $table->timestamps();

            $table->index('quote_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quote_line_items');
        Schema::dropIfExists('quotes');
    }
};
