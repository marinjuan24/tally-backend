<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['abono', 'retiro']);
            $table->enum('motive', [
                'compra_en_linea',
                'transferencia_tercero',
                'recarga',
                'recompensa',
                'reembolso',
                'registro'
            ]);
            $table->string('sender')->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('reference', 30);
            $table->timestamps();

            $table->index(['account_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
