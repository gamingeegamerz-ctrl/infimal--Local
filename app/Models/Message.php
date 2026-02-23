<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'user_id',
        'name',
        'subject',
        'content',
        'type',
        'category',
        'is_template',
        'used_count',
        'variables',
        'thumbnail',
    ];

    protected $casts = [
        'is_template' => 'boolean',
        'variables' => 'array',
    ];

    // Relationships
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Type scopes
    public function scopeEmailTemplates($query)
    {
        return $query->where('type', 'email')->where('is_template', true);
    }

    public function scopeSmsTemplates($query)
    {
        return $query->where('type', 'sms')->where('is_template', true);
    }
}
