<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegistrationRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'full_name',
        'email',
        'organization_name',
        'organization_type',
        'country',
        'email_verified',
        'agreed_terms',
        'agreed_privacy',
        'status',
        'review_notes',
    ];

    protected $casts = [
        'email_verified' => 'boolean',
        'agreed_terms' => 'boolean',
        'agreed_privacy' => 'boolean',
    ];
}
