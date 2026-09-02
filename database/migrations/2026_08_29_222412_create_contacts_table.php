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
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable()->unique();
            $table->string('phone')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        /**
         * Un usuario de portal pertenece a la persona, no a una de sus
         * empresas: así un dueño con tres empresas entra una sola vez y las ve
         * todas. La llave se agrega aquí y no en `create_users_table` porque
         * el framework crea `users` mucho antes de que exista `contacts`.
         */
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('contact_id')->nullable()->after('role')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contact_id');
        });

        Schema::dropIfExists('contacts');
    }
};
