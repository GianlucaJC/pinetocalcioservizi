<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'uploaded_by',
        'original_filename',
        'storage_path',
        'document_type',
        'period_year',
        'period_month',
        'status',
        'documents_count',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }
}
