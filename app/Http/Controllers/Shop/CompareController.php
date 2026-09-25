<?php
namespace App\Http\Controllers\Shop;
use App\Http\Controllers\Controller; use App\Services\Compare\CompareService;
use Illuminate\Http\RedirectResponse; use Illuminate\Http\Request; use Illuminate\View\View;
class CompareController extends Controller {
    public function __construct(protected CompareService $compare) {}
    public function index(): View {
        $products = $this->compare->products();
        $featureNames = $products->flatMap(fn($p)=>$p->features->pluck('name'))->unique()->values();
        return view('shop.compare', compact('products','featureNames'));
    }
    public function toggle(Request $request): RedirectResponse {
        $productId = (int)$request->input('product_id'); abort_if($productId < 1, 404);
        $added = $this->compare->toggle($productId);
        return back()->with('status', $added ? 'Добавлено к сравнению' : 'Убрано из сравнения');
    }
    public function clear(): RedirectResponse {
        $this->compare->clear();
        return redirect()->route('compare.index')->with('status','Список сравнения очищен');
    }
}
