<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_documents', function (Blueprint $table) {
            $table->index(
                ['employee_profile_id', 'period_year', 'period_month', 'issued_on'],
                'employee_docs_profile_period_browse_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('employee_documents', function (Blueprint $table) {
            $table->dropIndex('employee_docs_profile_period_browse_idx');
        });
    }
};
