<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Degree extends Model
{
    use HasFactory;

    protected $fillable = [
        'university_id',
        'code',
        'name',
        'description',
        'level',
        'status',
    ];

    public function university(): BelongsTo
    {
        return $this->belongsTo(University::class);
    }
}
