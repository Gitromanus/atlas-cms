<?php
namespace App\Http\Controllers\Shop;
use App\Http\Controllers\Controller; use App\Models\{Category,Page,Post,Product};
use App\Services\Tenant\TenantContext; use Illuminate\Http\Response;
class SitemapController extends Controller {
    public function index(string $shop): Response {
        $base = rtrim((string)config('app.url'),'/').'/'.$shop;
        $urls = [['loc'=>$base,'priority'=>'1.0'],['loc'=>$base.'/catalog','priority'=>'0.9']];
        foreach (Category::query()->get() as $cat) $urls[] = ['loc'=>$base.'/catalog/'.$cat->slug,'priority'=>'0.7'];
        foreach (Product::query()->active()->get(['slug','updated_at']) as $p)
            $urls[] = ['loc'=>$base.'/product/'.$p->slug,'priority'=>'0.8','lastmod'=>optional($p->updated_at)?->toAtomString()];
        foreach (Page::query()->published()->get(['slug','updated_at']) as $page)
            $urls[] = ['loc'=>$base.'/page/'.$page->slug,'priority'=>'0.5','lastmod'=>optional($page->updated_at)?->toAtomString()];
        $xml = '<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n';
        foreach ($urls as $u) {
            $xml .= '  <url><loc>'.e($u['loc']).'</loc>';
            if (!empty($u['lastmod'])) $xml .= '<lastmod>'.$u['lastmod'].'</lastmod>';
            if (!empty($u['priority'])) $xml .= '<priority>'.$u['priority'].'</priority>';
            $xml .= "</url>\n";
        }
        $xml .= '</urlset>';
        return response($xml, 200)->header('Content-Type', 'application/xml');
    }
}
