<?php
namespace App\Http\Controllers\Shop;
use App\Http\Controllers\Controller; use App\Services\Wishlist\WishlistService;
use Illuminate\Http\RedirectResponse; use Illuminate\Http\Request; use Illuminate\View\View;
class WishlistController extends Controller {
    public function __construct(protected WishlistService $wishlist) {}
    public function index(): View { return view('shop.wishlist', ['products'=>$this->wishlist->products()]); }
    public function toggle(Request $request): RedirectResponse {
        $productId = (int)$request->input('product_id'); abort_if($productId < 1, 404);
        $added = $this->wishlist->toggle($productId);
        return back()->with('status', $added ? 'Добавлено в избранное' : 'Удалено из избранного');
    }
}
