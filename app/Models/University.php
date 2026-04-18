<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class University extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'location',
        'description',
        'logo_url',
        'status',
    ];

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function verificationLogs(): HasMany
    {
        return $this->hasMany(VerificationLog::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
