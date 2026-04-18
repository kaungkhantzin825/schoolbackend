<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'university_id',
        'graduate_name',
        'father_name',
        'gender',
        'date_of_birth',
        'nrc_number',
        'student_id',
        'degree',
        'specialization',
        'graduation_year',
        'photo_url',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
    ];

    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }

    public function verificationLogs(): HasMany
    {
        return $this->hasMany(VerificationLog::class);
    }
}
