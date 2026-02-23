<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmtpSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'name',
        'driver',
        'host',
        'port',
        'username',
        'password',
        'encryption',
        'from_address',
        'from_name',
        'reply_to',
        'daily_limit',
        'sent_today',
        'is_active',
        'verified_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'verified_at' => 'datetime',
        'sent_today' => 'integer',
        'daily_limit' => 'integer',
    ];

    // Hide password
    protected $hidden = ['password'];

    // Relationships
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
