<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\Slugger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'title', 'slug', 'body', 'meta_title', 'meta_description',
        'is_published', 'show_in_menu', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'show_in_menu' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $model) {
            if (blank($model->slug) && $model->title) {
                $model->slug = Slugger::slug($model->title) ?: 'page-'.uniqid();
            }
        });
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('is_published', true);
    }

    public function scopeInMenu(Builder $q): Builder
    {
        return $q->where('show_in_menu', true)->orderBy('sort_order')->orderBy('title');
    }
}
