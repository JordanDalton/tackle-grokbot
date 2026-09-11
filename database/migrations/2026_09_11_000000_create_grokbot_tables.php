<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grokbots', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('instructions')->nullable();
            $table->text('webhook_url')->nullable();
            $table->text('bearer_token')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('grokbot_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grokbot_id')->constrained('grokbots')->restrictOnDelete();
            $table->json('payload');
            $table->string('status')->default('sending');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();
            $table->index(['grokbot_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grokbot_deliveries');
        Schema::dropIfExists('grokbots');
    }
};
