<?php
namespace App\Services\Compare;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;
class CompareService {
    public const MAX = 4;
    protected function key(): string { return 'compare_ids'; }
    public function ids(): array { return array_values(array_unique(array_map('intval', Session::get($this->key(), [])))); }
    public function toggle(int $productId): bool {
        $ids = $this->ids();
        if (in_array($productId, $ids, true)) { Session::put($this->key(), array_values(array_filter($ids, fn($id)=>$id!==$productId))); return false; }
        if (count($ids) >= self::MAX) array_shift($ids);
        $ids[] = $productId; Session::put($this->key(), $ids); return true;
    }
    public function clear(): void { Session::forget($this->key()); }
    public function count(): int { return count($this->ids()); }
    public function products(): Collection {
        $ids = $this->ids(); if ($ids === []) return collect();
        return Product::query()->active()->with(['images','prices','stocks','features','category'])->whereIn('id',$ids)->get()
            ->sortBy(fn($p)=>array_search($p->id,$ids,true))->values();
    }
}
