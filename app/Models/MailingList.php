<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MailingList extends Model
{
    use HasFactory;

    protected $table = 'mailing_lists';

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'description',
        'is_public',
        'is_default' // Add this line
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'is_default' => 'boolean', // Add this line
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($list) {
            if (empty($list->slug)) {
                $list->slug = Str::slug($list->name . '-' . time());
            }
        });
    }

    // Relationship: List belongs to User
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relationship: List has many Subscribers
    public function subscribers()
    {
        return $this->hasMany(Subscriber::class, 'list_id');
    }

    // Relationship: List has many Campaigns
    public function campaigns()
    {
        return $this->hasMany(Campaign::class, 'list_id');
    }

    // Helper: Get active subscribers count
    public function getActiveSubscribersCountAttribute()
    {
        return $this->subscribers()->where('status', 'active')->count();
    }

    // Helper: Get total subscribers count
    public function getTotalSubscribersCountAttribute()
    {
        return $this->subscribers()->count();
    }
}