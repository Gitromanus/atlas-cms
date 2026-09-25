<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Services\Tenant\TenantContext;
use App\Support\Slugger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use BelongsToTenant;

    public const TYPE_ARTICLE = 'article';

    public const TYPE_NEWS = 'news';

    protected $fillable = [
        'tenant_id',
        'type',
        'title',
        'slug',
        'excerpt',
        'body',
        'cover_path',
        'is_published',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $model) {
            if (blank($model->slug) && $model->title) {
                $model->slug = $model->uniqueSlug();
            }

            if ($model->is_published && $model->published_at === null) {
                $model->published_at = now();
            }
        });
    }

    public function uniqueSlug(): string
    {
        $base = Slugger::slug($this->title) ?: 'post';
        $tenantId = $this->tenant_id ?? app(TenantContext::class)->id();

        $slug = $base;
        $i = 2;

        while (static::query()
            ->where('tenant_id', $tenantId)
            ->where('slug', $slug)
            ->whereKeyNot($this->getKey())
            ->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('is_published', true)
            ->where(function (Builder $q) {
                $q->whereNull('published_at')->orWhere('published_at', '<=', now());
            });
    }

    public function scopeArticles(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_ARTICLE);
    }

    public function scopeNews(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_NEWS);
    }

    public function getCoverUrlAttribute(): ?string
    {
        if (! $this->cover_path) {
            return null;
        }

        return asset('storage/'.ltrim($this->cover_path, '/'));
    }

    public function typeLabel(): string
    {
        return $this->type === self::TYPE_NEWS ? 'Новость' : 'Статья';
    }
}
