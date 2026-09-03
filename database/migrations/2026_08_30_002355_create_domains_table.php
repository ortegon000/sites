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
        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('management');
            $table->string('registrar')->nullable();
            $table->string('site_url')->nullable();
            $table->string('hosting_plan')->nullable();
            $table->date('hosted_since')->nullable();
            $table->date('registered_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->string('email_management');
            $table->text('email_notes')->nullable();
            $table->string('status');
            $table->timestamp('expiry_notified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['client_id', 'name']);
            $table->index(['status', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};
