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
        Schema::create('ad_campaigns', function (Blueprint $table) {
            $table->id();
            /** La campaña es un activo del cliente: vive mientras corre, no
             *  mientras dura el proyecto que la montó. */
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('platform');
            $table->string('ad_account_id')->nullable();
            $table->string('objective')->nullable();
            $table->decimal('monthly_budget', 10, 2);
            $table->char('currency', 3)->default('MXN');
            $table->string('budget_billing');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('status');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['client_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ad_campaigns');
    }
};
