<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'company',
        'status',
        'user_id'
    ];

    protected $attributes = [
        'status' => 'active'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
