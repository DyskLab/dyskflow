<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One message from a Form widget. "data" is a list of ['label' => ..., 'value' => ...] in form order. */
class FormSubmission extends Model
{
    protected $fillable = ['form', 'email', 'data', 'page_url', 'page_title', 'locale', 'is_read'];

    protected function casts(): array
    {
        return ['data' => 'array', 'is_read' => 'boolean'];
    }

    /** The first answers, joined, for the list. */
    public function summary(int $limit = 120): string
    {
        return str(collect($this->data)->pluck('value')->filter(fn ($v) => is_string($v) && $v !== '')->implode(' · '))->squish()->limit($limit)->toString();
    }
}
