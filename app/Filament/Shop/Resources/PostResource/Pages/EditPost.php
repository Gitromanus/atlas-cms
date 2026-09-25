<?php

namespace App\Filament\Shop\Resources\PostResource\Pages;

use App\Filament\Shop\Resources\PostResource;
use App\Support\Slugger;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
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

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
