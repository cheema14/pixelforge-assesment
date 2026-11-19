<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('pixelforge_id')->nullable()->unique();
            $table->string('status', 20)->default('queued');

            $table->text('prompt');
            $table->string('size', 16);
            $table->string('quality', 16);
            $table->json('reference_images');
            $table->json('rates')->nullable();
            $table->json('usage')->nullable();

            $table->unsignedBigInteger('estimated_provider_cost_nano');
            $table->unsignedBigInteger('max_provider_cost_nano');
            $table->unsignedBigInteger('estimated_charge_nano');
            $table->unsignedBigInteger('max_charge_nano');
            $table->unsignedBigInteger('provider_cost_nano')->nullable();
            $table->unsignedBigInteger('charged_nano')->nullable();

            $table->text('image_url')->nullable();
            $table->timestamp('image_url_expires_at')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->text('error_message')->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'updated_at']);
            $table->index(['status', 'settled_at']);
            $table->index('completed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generations');
    }
};
