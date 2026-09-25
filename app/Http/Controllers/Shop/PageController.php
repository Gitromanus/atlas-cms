<?php
namespace App\Http\Controllers\Shop;
use App\Http\Controllers\Controller; use App\Models\Page; use Illuminate\View\View;
class PageController extends Controller {
    public function show(string $shop, string $pageSlug): View {
        $page = Page::query()->published()->where('slug', $pageSlug)->firstOrFail();
        return view('shop.page', compact('page'));
    }
}
