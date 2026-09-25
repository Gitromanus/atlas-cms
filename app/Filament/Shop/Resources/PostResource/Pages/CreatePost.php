<?php

namespace App\Filament\Shop\Resources\PostResource\Pages;

use App\Filament\Shop\Resources\PostResource;
use App\Services\Tenant\TenantContext;
use App\Support\Slugger;
use Filament\Resources\Pages\CreateRecord;

class CreatePost extends CreateRecord
{
    protected static string $resource = PostResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = $data['tenant_id'] ?? app(TenantContext::class)->id();

        if (blank($data['slug'] ?? null) && filled($data['title'] ?? null)) {
            $data['slug'] = Slugger::slug((string) $data['title']) ?: 'post-'.uniqid();
        }

        $cover = $data['cover_path'] ?? null;
        if (is_array($cover)) {
            $data['cover_path'] = $cover[0] ?? null;
        }

        if (! empty($data['is_published']) && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
