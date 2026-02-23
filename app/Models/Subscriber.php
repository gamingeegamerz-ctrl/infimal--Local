<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscriber extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'list_id',
        'email',
        'first_name',
        'last_name',
        'status',
        'source',
        'tags',
        'subscribed_at',
        'unsubscribed_at'
    ];

    protected $casts = [
        'tags' => 'array',
        'subscribed_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relationship: Subscriber belongs to User
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relationship: Subscriber belongs to MailingList
    public function mailingList()
    {
        return $this->belongsTo(MailingList::class, 'list_id');
    }

    // Alternative name for consistency
    public function list()
    {
        return $this->belongsTo(MailingList::class, 'list_id');
    }

    // Helper: Get full name
    public function getFullNameAttribute()
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    // Helper: Check if active
    public function isActive()
    {
        return $this->status === 'active';
    }

    // Helper: Check if unsubscribed
    public function isUnsubscribed()
    {
        return $this->status === 'unsubscribed';
    }

    // Scope: Only active subscribers
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // Scope: By list
    public function scopeByList($query, $listId)
    {
        return $query->where('list_id', $listId);
    }

    // =============================================
    // DUPLICATE PREVENTION
    // =============================================

    /**
     * Check if email already exists in the list
     */
    public static function existsInList($email, $listId, $userId = null)
    {
        $query = self::where('email', $email)
            ->where('list_id', $listId);
        
        if ($userId) {
            $query->where('user_id', $userId);
        }
        
        return $query->exists();
    }

    /**
     * Mark subscriber as bounced
     */
    public function markAsBounced()
    {
        $this->update([
            'status' => 'bounced'
        ]);
    }

    /**
     * Mark subscriber as unsubscribed
     */
    public function markAsUnsubscribed()
    {
        $this->update([
            'status' => 'unsubscribed',
            'unsubscribed_at' => now()
        ]);
    }

    /**
     * Add tag to subscriber
     */
    public function addTag($tag)
    {
        $tags = $this->tags ?? [];
        if (!in_array($tag, $tags)) {
            $tags[] = $tag;
            $this->update(['tags' => $tags]);
        }
        return $this;
    }

    /**
     * Remove tag from subscriber
     */
    public function removeTag($tag)
    {
        $tags = $this->tags ?? [];
        $tags = array_values(array_filter($tags, fn($t) => $t !== $tag));
        $this->update(['tags' => $tags]);
        return $this;
    }
}
