<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_filename');
            $table->string('storage_path');
            $table->string('document_type');
            $table->unsignedSmallInteger('period_year')->nullable();
            $table->unsignedTinyInteger('period_month')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedInteger('documents_count')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('document_type');
            $table->string('title');
            $table->string('tax_code');
            $table->unsignedSmallInteger('period_year')->nullable();
            $table->unsignedTinyInteger('period_month')->nullable();
            $table->date('issued_on')->nullable();
            $table->string('storage_path');
            $table->string('original_filename');
            $table->unsignedInteger('first_page')->nullable();
            $table->unsignedInteger('last_page')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['employee_profile_id', 'document_type', 'period_year'], 'employee_docs_profile_type_year_idx');
            $table->index(['tax_code', 'period_year'], 'employee_docs_tax_year_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_documents');
        Schema::dropIfExists('document_batches');
    }
};
