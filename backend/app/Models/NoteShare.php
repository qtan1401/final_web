<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NoteShare extends Model
{
    protected $fillable = ['note_id', 'shared_by', 'shared_with', 'permission'];

    public function note()
    {
        return $this->belongsTo(Note::class);
    }

    public function sharedBy()
    {
        return $this->belongsTo(User::class, 'shared_by');
    }

    public function sharedWith()
    {
        return $this->belongsTo(User::class, 'shared_with');
    }
}
