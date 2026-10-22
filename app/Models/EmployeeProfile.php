<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'tax_code',
        'phone',
        'pec_email',
        'birth_date',
        'birth_place',
        'address',
        'city',
        'province',
        'postal_code',
        'position',
        'emergency_contact_name',
        'emergency_contact_phone',
        'started_on',
        'notes',
        'work_contract_path',
    ];

    protected $casts = [
        'birth_date' => 'date:Y-m-d',
        'started_on' => 'date:Y-m-d',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }
}
