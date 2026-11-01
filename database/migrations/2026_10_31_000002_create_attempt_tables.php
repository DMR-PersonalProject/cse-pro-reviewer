<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attempts and their snapshotted answers and scores (plan §3.2). Answers keep their snapshot
 * when the question is deleted, so `question_id` is nulled, never cascaded.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('mode');
            $table->string('timing');
            $table->foreignId('subtest_id')->nullable()->constrained()->restrictOnDelete();
            $table->json('topic_ids')->nullable();
            $table->unsignedInteger('item_count');
            $table->json('config');
            $table->boolean('is_standard')->default(false);
            $table->boolean('is_partial')->default(false);
            $table->boolean('has_private_items')->default(false);
            $table->string('status')->default('in_progress');
            $table->dateTime('started_at');
            $table->dateTime('deadline_at')->nullable();
            $table->dateTime('finalized_at')->nullable();
            $table->unsignedInteger('time_used_seconds')->nullable();
            $table->unsignedInteger('current_position')->default(1);
            $table->unsignedBigInteger('seed');
            $table->boolean('is_suspicious')->default(false);
            $table->dateTime('hidden_at')->nullable();
            $table->foreignId('hidden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('hidden_reason')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index(['mode', 'status', 'finalized_at']);
        });

        Schema::create('attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->unsignedInteger('position');
            $table->foreignId('subtest_id')->constrained()->restrictOnDelete();
            $table->foreignId('topic_id')->constrained()->restrictOnDelete();
            $table->string('passage_key')->nullable();
            $table->json('snapshot');
            $table->string('correct_key', 1);
            $table->string('selected_key', 1)->nullable();
            $table->boolean('is_correct')->nullable();
            $table->boolean('flagged')->default(false);
            $table->dateTime('answered_at')->nullable();
            $table->dateTime('revealed_at')->nullable();
            $table->dateTime('purged_at')->nullable();
            $table->timestamps();
            $table->unique(['attempt_id', 'position']);
            $table->index(['topic_id', 'is_correct']);
        });

        Schema::create('attempt_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('formula_version');
            $table->decimal('general_rating', 5, 2)->index();
            $table->decimal('passing_grade', 5, 2);
            $table->boolean('passed');
            $table->unsignedInteger('correct_count');
            $table->unsignedInteger('total_count');
            $table->unsignedInteger('time_used_seconds');
            $table->string('end_reason');
            $table->json('topic_breakdown');
            $table->timestamps();
        });

        Schema::create('attempt_subtest_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subtest_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('correct');
            $table->unsignedInteger('total');
            $table->decimal('percent', 5, 2);
            $table->timestamps();
            $table->unique(['attempt_id', 'subtest_id']);
            $table->index(['subtest_id', 'percent']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attempt_subtest_scores');
        Schema::dropIfExists('attempt_results');
        Schema::dropIfExists('attempt_answers');
        Schema::dropIfExists('attempts');
    }
};
