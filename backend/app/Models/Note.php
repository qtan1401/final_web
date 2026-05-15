<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Note extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'content',
        'color',
        'attachment',
        'is_locked',
        'password',
        'is_pinned',
        'pinned_at'
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'is_pinned' => 'boolean',
        'is_locked' => 'boolean',
        'pinned_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function labels()
    {
        return $this->belongsToMany(Label::class, 'note_label');
    }

    public function shares()
    {
        return $this->hasMany(NoteShare::class);
    }

    public function images()
    {
        return $this->hasMany(NoteImage::class);
    }
}
