<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('mode', 10)->default('text');
            $table->string('scenario')->nullable();
            $table->timestamps();
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('role', 10);
            $table->text('content');
            $table->json('feedback')->nullable();
            $table->timestamps();
        });

        Schema::create('placement_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('level', 2);
            $table->unsignedTinyInteger('score');
            $table->json('scores');
            $table->timestamps();
        });

        Schema::create('mistakes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category', 40);
            $table->text('original');
            $table->text('corrected');
            $table->text('explanation')->nullable();
            $table->timestamps();
        });

        Schema::create('learned_words', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('word', 80);
            $table->text('meaning')->nullable();
            $table->text('example')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'word']);
        });

        Schema::create('practice_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->unsignedInteger('seconds');
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('lesson_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 80);
            $table->unsignedTinyInteger('score')->default(100);
            $table->timestamps();
            $table->unique(['user_id', 'slug']);
        });
    }

    public function down(): void
    {
        foreach (['lesson_completions', 'practice_sessions', 'learned_words', 'mistakes', 'placement_results', 'messages', 'conversations'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
