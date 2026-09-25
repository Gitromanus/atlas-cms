<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Services\Tenant\TenantContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PostController extends Controller
{
    public function articles(Request $request): View
    {
        $this->assertEnabled('enable_articles');

        $posts = Post::query()
            ->articles()
            ->published()
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(12);

        return view('shop.posts.index', [
            'posts' => $posts,
            'heading' => 'Статьи',
            'type' => Post::TYPE_ARTICLE,
        ]);
    }

    public function news(Request $request): View
    {
        $this->assertEnabled('enable_news');

        $posts = Post::query()
            ->news()
            ->published()
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(12);

        return view('shop.posts.index', [
            'posts' => $posts,
            'heading' => 'Новости',
            'type' => Post::TYPE_NEWS,
        ]);
    }

    public function show(Request $request, string $shop, string $postSlug): View
    {
        $post = Post::query()
            ->where('slug', $postSlug)
            ->published()
            ->firstOrFail();

        $key = $post->type === Post::TYPE_NEWS ? 'enable_news' : 'enable_articles';
        $this->assertEnabled($key);

        $related = Post::query()
            ->where('type', $post->type)
            ->published()
            ->whereKeyNot($post->id)
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        return view('shop.posts.show', compact('post', 'related'));
    }

    protected function assertEnabled(string $settingKey): void
    {
        $tenant = app(TenantContext::class)->current();
        abort_unless((bool) $tenant?->setting($settingKey, true), 404);
    }
}
