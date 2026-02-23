<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class EmailList extends Model
{
    use HasFactory;

    protected $table = 'lists';

    protected $fillable = [
        'workspace_id',
        'name',
        'slug',
        'description',
        'subscriber_count',
        'is_public',
        'double_optin',
        'from_email',
        'from_name',
        'welcome_email',
        'unsubscribe_page',
        'confirmation_email',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'double_optin' => 'boolean',
    ];

    // Relationships
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function subscribers(): BelongsToMany
    {
        return $this->belongsToMany(Subscriber::class, 'list_subscriber', 'list_id', 'subscriber_id')
            ->withTimestamps();
    }
}
