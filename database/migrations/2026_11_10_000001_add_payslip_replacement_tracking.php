<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_batches', function (Blueprint $table) {
            $table->unsignedInteger('revision_number')->default(0)->after('documents_count');
        });

        Schema::table('employee_documents', function (Blueprint $table) {
            $table->boolean('is_visible_to_employee')->default(true)->after('viewed_at');
            $table->foreignId('replaced_by_document_id')
                ->nullable()
                ->after('is_visible_to_employee')
                ->constrained('employee_documents')
                ->nullOnDelete();

            $table->index(
                ['employee_profile_id', 'document_type', 'period_year', 'period_month', 'is_visible_to_employee'],
                'employee_docs_visible_period_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('employee_documents', function (Blueprint $table) {
            $table->dropIndex('employee_docs_visible_period_idx');
            $table->dropConstrainedForeignId('replaced_by_document_id');
            $table->dropColumn('is_visible_to_employee');
        });

        Schema::table('document_batches', function (Blueprint $table) {
            $table->dropColumn('revision_number');
        });
    }
};
