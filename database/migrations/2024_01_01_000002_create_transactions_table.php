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

            $table->foreignId('client_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->enum('type', ['deposit', 'withdrawal', 'buy', 'sell']);
            $table->decimal('amount', 15, 2);

            $table->string('instrument', 20)->nullable();
            $table->unsignedInteger('quantity')->nullable();
            $table->decimal('price', 15, 2)->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['client_id', 'type']);
            $table->index(['client_id', 'instrument']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};