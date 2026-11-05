<?php

namespace App\Models;

use App\Support\Notifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Comment extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::created(fn (Comment $comment) => Notifier::comment($comment));
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
