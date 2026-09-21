<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Proyek di Progres Rapat. Menghapusnya ikut menghapus tugasnya. */
class MeetingProject extends Model
{
    protected $fillable = ['name'];

    public function tasks(): HasMany
    {
        return $this->hasMany(MeetingTask::class, 'project_id');
    }
}
