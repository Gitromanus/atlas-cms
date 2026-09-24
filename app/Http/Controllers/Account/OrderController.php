<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(): View
    {
        $customer = auth('customers')->user();

        $orders = Order::query()
            ->where('customer_id', $customer->id)
            ->with('status')
            ->latest()
            ->get();

        return view('shop.account.orders', compact('orders'));
    }

    public function show(Order $order): View
    {
        abort_unless($order->customer_id === auth('customers')->id(), 404);

        $order->load(['items', 'status']);

        return view('shop.account.order', compact('order'));
    }
}