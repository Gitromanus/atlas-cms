<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(): View
    {
        $customer = auth('customers')->user();
        abort_unless($customer !== null, 403);

        $orders = Order::query()
            ->where('customer_id', $customer->id)
            ->with('status')
            ->latest('placed_at')
            ->latest('id')
            ->get();

        return view('shop.account.orders', compact('orders'));
    }

    public function show(Request $request, string $shop, int|string $order): View
    {
        $customer = auth('customers')->user();
        abort_unless($customer !== null, 403);

        $model = Order::query()
            ->with(['items', 'status'])
            ->whereKey($order)
            ->firstOrFail();

        abort_unless((int) $model->customer_id === (int) $customer->id, 404);

        return view('shop.account.order', ['order' => $model]);
    }
}
