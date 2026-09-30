<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $urls = [];

        $urls[] = ['loc' => route('home'), 'changefreq' => 'daily', 'priority' => '1.0'];
        $urls[] = ['loc' => route('catalog.index'), 'changefreq' => 'daily', 'priority' => '0.9'];

        foreach (Category::query()->where('is_active', true)->get() as $c) {
            $urls[] = ['loc' => route('catalog.category', $c->slug), 'changefreq' => 'weekly', 'priority' => '0.8'];
        }

        foreach (Product::query()->where('is_active', true)->limit(5000)->get() as $p) {
            $urls[] = ['loc' => route('product.show', $p->slug), 'changefreq' => 'weekly', 'priority' => '0.7'];
        }

        foreach (Page::query()->where('is_active', true)->get() as $page) {
            $urls[] = ['loc' => route('page.show', $page->slug), 'changefreq' => 'monthly', 'priority' => '0.5'];
        }

        foreach (Post::query()->where('is_published', true)->get() as $post) {
            $urls[] = ['loc' => route('posts.show', $post->slug), 'changefreq' => 'weekly', 'priority' => '0.6'];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>\n';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n';
        foreach ($urls as $u) {
            $xml .= '  <url>\n';
            $xml .= '    <loc>'.e($u['loc']).'</loc>\n';
            $xml .= '    <changefreq>'.$u['changefreq'].'</changefreq>\n';
            $xml .= '    <priority>'.$u['priority'].'</priority>\n';
            $xml .= '  </url>\n';
        }
        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
