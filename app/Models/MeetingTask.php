<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Tugas atau sub-tugas di Progres Rapat.
 *
 * Aturannya sama dengan TaskFlow: progres tugas utama dihitung dari seluruh
 * sub-tugas di bawahnya, di level mana pun; tenggat yang lewat dan belum
 * selesai berarti terlambat; tenggat hari ini atau besok berarti mendekati.
 */
class MeetingTask extends Model
{
    public const CATEGORIES = ['kerja', 'pribadi', 'belajar', 'bug'];

    public const PRIORITIES = ['high', 'medium', 'low'];

    /** Tugas selesai diarsipkan otomatis setelah selang ini. */
    public const ARCHIVE_AFTER_HOURS = 24;

    protected $fillable = [
        'project_id',
        'parent_id',
        'text',
        'category',
        'priority',
        'deadline',
        'link',
        'completed',
        'completed_at',
        'archived',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'deadline' => 'date',
            'completed' => 'boolean',
            'completed_at' => 'datetime',
            'archived' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $task) {
            $task->user_id ??= Auth::id();
        });

        // Kapan selesainya dicatat, supaya arsip otomatis tahu kapan
        // sehari itu lewat. Dibuka lagi berarti belum selesai.
        static::saving(function (self $task) {
            if ($task->isDirty('completed')) {
                $task->completed_at = $task->completed ? Carbon::now() : null;
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(MeetingProject::class, 'project_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('id');
    }

    /** @param  Builder<self>  $query */
    public function scopeTopLevel(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Tugas utama yang selesai lebih dari sehari lalu dipindah ke arsip —
     * sama seperti TaskFlow, supaya daftar yang sedang dikerjakan tidak
     * dipenuhi yang sudah beres.
     */
    public static function archiveFinished(): int
    {
        return static::query()
            ->topLevel()
            ->where('completed', true)
            ->where('archived', false)
            ->where('completed_at', '<=', Carbon::now()->subHours(static::ARCHIVE_AFTER_HOURS))
            ->update(['archived' => true]);
    }

    /** overdue | soon | normal | null — sama dengan deadlineStatus() TaskFlow. */
    public function deadlineStatus(): ?string
    {
        if (! $this->deadline) {
            return null;
        }

        $today = Carbon::today();

        return match (true) {
            $this->deadline->lt($today) => 'overdue',
            $this->deadline->lte($today->copy()->addDay()) => 'soon',
            default => 'normal',
        };
    }

    public function isOverdue(): bool
    {
        return ! $this->completed && $this->deadlineStatus() === 'overdue';
    }
}
