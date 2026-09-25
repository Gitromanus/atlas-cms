<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderTrackController extends Controller
{
    public function form(Request $request): View
    {
        return view('shop.track', [
            'number' => (string) $request->query('number', ''),
            'phone' => (string) $request->query('phone', ''),
            'order' => null,
            'searched' => false,
        ]);
    }

    public function lookup(Request $request): View
    {
        $data = $request->validate([
            'number' => ['required', 'string', 'max:64'],
            'phone' => ['required', 'string', 'max:32'],
        ]);

        $number = trim($data['number']);
        $digits = preg_replace('/\D+/', '', $data['phone']) ?? '';

        $order = Order::query()
            ->with(['items', 'status'])
            ->where('number', $number)
            ->get()
            ->first(function (Order $order) use ($digits) {
                $orderDigits = preg_replace('/\D+/', '', (string) $order->customer_phone) ?? '';

                return $digits !== '' && $orderDigits !== '' && (
                    str_ends_with($orderDigits, $digits)
                    || str_ends_with($digits, $orderDigits)
                    || $orderDigits === $digits
                );
            });

        return view('shop.track', [
            'number' => $number,
            'phone' => $data['phone'],
            'order' => $order,
            'searched' => true,
        ]);
    }
}
