<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailList extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'description', 'is_active'];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subscribers()
    {
        return $this->hasMany(Subscriber::class, 'list_id');
    }

    public function campaigns()
    {
        return $this->hasMany(Campaign::class, 'list_id');
    }
}
