<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'user_name',
        'user_email',
        'role',
        'action',
        'description',
        'ip_address',
        'country',
        'country_code',
        'city',
        'device',
        'browser',
        'platform',
        'user_agent',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Human label for the action code. */
    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            'login'                    => 'Signed in',
            'login_failed'             => 'Failed sign-in',
            'logout'                   => 'Signed out',
            'password_reset_requested' => 'Requested password reset',
            'password_reset'           => 'Reset password',
            'password_changed'         => 'Changed password',
            'account_created'          => 'Account created',
            'registration_submitted'   => 'Registration submitted',
            default                    => ucfirst(str_replace('_', ' ', (string) $this->action)),
        };
    }
}
