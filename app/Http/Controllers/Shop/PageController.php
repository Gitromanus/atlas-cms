<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\View\View;

class PageController extends Controller
{
    public function show(string $pageSlug): View
    {
        $page = Page::query()
            ->where('slug', $pageSlug)
            ->where('is_active', true)
            ->firstOrFail();

        return view('shop.page', compact('page'));
    }
}
