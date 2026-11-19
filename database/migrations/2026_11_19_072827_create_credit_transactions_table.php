<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('generation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40);
            $table->bigInteger('amount_nano');
            $table->string('description');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index(['type', 'created_at']);
            $table->index('generation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
    }
};
