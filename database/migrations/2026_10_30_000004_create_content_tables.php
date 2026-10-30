<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Question bank (plan §3.2). questions.import_batch_id arrives with the import tables in M3-02.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->string('visibility')->default('private')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('passages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subtest_id')->constrained()->restrictOnDelete();
            $table->foreignId('topic_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_id')->constrained()->restrictOnDelete();
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->string('language')->default('en');
            $table->string('status')->default('draft');
            $table->timestamps();
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subtest_id')->constrained()->restrictOnDelete();
            $table->foreignId('topic_id')->constrained()->restrictOnDelete();
            $table->foreignId('passage_id')->nullable()->index()->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('passage_position')->nullable();
            $table->foreignId('source_id')->index()->constrained()->restrictOnDelete();
            $table->text('stem');
            $table->text('stem_normalized')->nullable();
            $table->text('explanation')->nullable();
            $table->string('difficulty')->nullable();
            $table->string('language')->default('en');
            $table->string('status')->default('draft');
            $table->json('needs_review_reasons')->nullable();
            $table->boolean('lock_choice_order')->default(false);
            $table->decimal('extraction_confidence', 3, 2)->nullable();
            $table->string('source_file')->nullable();
            $table->string('source_page')->nullable();
            $table->text('source_notes')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'subtest_id', 'topic_id']);
        });

        Schema::create('choices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('position');
            $table->text('body');
            $table->boolean('is_correct')->default(false);
            $table->timestamps();

            $table->unique(['question_id', 'position']);
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('question_tag', function (Blueprint $table) {
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();

            $table->primary(['question_id', 'tag_id']);
        });

        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->morphs('mediable');
            $table->string('disk');
            $table->string('path');
            $table->string('mime');
            $table->unsignedInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('alt_text', 500)->nullable();
            $table->unsignedSmallInteger('position')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media');
        Schema::dropIfExists('question_tag');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('choices');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('passages');
        Schema::dropIfExists('sources');
    }
};
