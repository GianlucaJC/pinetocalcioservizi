<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_profile_id',
        'document_batch_id',
        'document_type',
        'title',
        'tax_code',
        'period_year',
        'period_month',
        'issued_on',
        'storage_path',
        'original_filename',
        'first_page',
        'last_page',
        'metadata',
        'viewed_at',
    ];

    protected $casts = [
        'issued_on' => 'date:Y-m-d',
        'metadata' => 'array',
        'viewed_at' => 'datetime',
    ];

    public function employeeProfile(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(DocumentBatch::class, 'document_batch_id');
    }
}
