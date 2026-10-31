<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Import batches and their rows (plan §3.2, §8). `meta` holds what a JSON file declares once for
 * the whole batch (default source, passages by ref, warnings) until rows are approved.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('original_filename');
            $table->string('format');
            $table->foreignId('default_source_id')->nullable()->constrained('sources')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->json('meta')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('approved_rows')->default(0);
            $table->unsignedInteger('rejected_rows')->default(0);
            $table->timestamps();
        });

        Schema::create('import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('payload');
            $table->json('errors')->nullable();
            $table->json('flags')->nullable();
            $table->foreignId('duplicate_of_question_id')->nullable()->constrained('questions')->nullOnDelete();
            $table->string('decision')->default('pending');
            $table->foreignId('question_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['import_batch_id', 'decision']);
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->foreignId('import_batch_id')->nullable()->index()->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('import_batch_id');
        });

        Schema::dropIfExists('import_rows');
        Schema::dropIfExists('import_batches');
    }
};
