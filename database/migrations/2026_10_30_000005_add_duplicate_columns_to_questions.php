<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Possible-duplicate match as real columns (M2-09), so it can be filtered without JSON in SQL.
 * Recomputed whenever a question's stem or subtest changes.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->foreignId('duplicate_of_question_id')->nullable()->index()->constrained('questions')->nullOnDelete();
            $table->decimal('duplicate_similarity', 5, 4)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('duplicate_of_question_id');
            $table->dropColumn('duplicate_similarity');
        });
    }
};
