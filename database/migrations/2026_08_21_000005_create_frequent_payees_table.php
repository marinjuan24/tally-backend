<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('frequent_payees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payee_id')->constrained('users');
            $table->string('alias')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'payee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('frequent_payees');
    }
};
