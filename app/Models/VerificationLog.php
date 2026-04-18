<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerificationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'university_id',
        'student_id',
        'verifier_name',
        'verifier_email',
        'organization_type',
        'organization_name',
        'searched_name',
        'searched_father_name',
        'searched_degree',
        'searched_year',
        'result',
        'status',
        'notes',
    ];

    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
