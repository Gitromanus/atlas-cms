<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductReview;
use App\Services\Tenant\TenantContext;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $tenant = app(TenantContext::class)->current();
        $enableArticles = (bool) $tenant?->setting('enable_articles', true);
        $enableNews = (bool) $tenant?->setting('enable_news', true);
        $enableReviews = (bool) $tenant?->setting('enable_reviews', true);

        $with = ['images', 'category', 'features', 'variants', 'prices', 'stocks'];
        $base = Product::query()->active()->with($with)->inStock();

        $products = (clone $base)->latest()->limit(8)->get();

        $hasFlags = Schema::hasColumn('products', 'is_hit');
        $hits = $hasFlags
            ? (clone $base)->hits()->limit(8)->get()
            : collect();
        $newArrivals = $hasFlags
            ? (clone $base)->newArrivals()->limit(8)->get()
            : collect();
        $saleProducts = $hasFlags
            ? (clone $base)->onSale()->limit(8)->get()
            : collect();

        $categories = collect(Category::menuTree());

        $articles = $enableArticles
            ? Post::query()->articles()->published()->orderByDesc('published_at')->limit(3)->get()
            : collect();

        $news = $enableNews
            ? Post::query()->news()->published()->orderByDesc('published_at')->limit(4)->get()
            : collect();

        $latestReviews = $enableReviews
            ? ProductReview::query()
                ->where('is_approved', true)
                ->with(['product.images'])
                ->latest()
                ->limit(6)
                ->get()
            : collect();

        return view('shop.home', [
            'products' => $products,
            'hits' => $hits,
            'newArrivals' => $newArrivals,
            'saleProducts' => $saleProducts,
            'categories' => $categories,
            'articles' => $articles,
            'news' => $news,
            'latestReviews' => $latestReviews,
            'enableArticles' => $enableArticles,
            'enableNews' => $enableNews,
            'enableReviews' => $enableReviews,
        ]);
    }
}
