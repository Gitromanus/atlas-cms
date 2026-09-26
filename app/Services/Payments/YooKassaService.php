<?php
namespace App\Services\Payments;
use App\Models\Order; use App\Models\Tenant;
use Illuminate\Support\Facades\Http; use Illuminate\Support\Facades\Log; use Illuminate\Support\Str;
class YooKassaService {
    public function isConfigured(?Tenant $tenant): bool {
        return $tenant && filled($tenant->setting('yookassa_shop_id')) && filled($tenant->setting('yookassa_secret_key'));
    }
    public function createPayment(Order $order, Tenant $tenant, string $returnUrl): ?array {
        $shopId = $tenant->setting('yookassa_shop_id'); $secret = $tenant->setting('yookassa_secret_key');
        if (!$shopId || !$secret) return null;
        try {
            $r = Http::withBasicAuth((string)$shopId,(string)$secret)->withHeaders(['Idempotence-Key'=>(string)Str::uuid()])->acceptJson()
                ->post('https://api.yookassa.ru/v3/payments', [
                    'amount'=>['value'=>number_format((float)$order->total,2,'.',''),'currency'=>'RUB'],
                    'confirmation'=>['type'=>'redirect','return_url'=>$returnUrl],'capture'=>true,
                    'description'=>'Заказ №'.$order->number,
                    'metadata'=>['order_id'=>$order->id,'tenant_id'=>$tenant->id,'order_number'=>$order->number],
                ]);
            if (!$r->successful()) { Log::warning('YooKassa fail',['body'=>$r->body()]); return null; }
            return $r->json();
        } catch (\Throwable $e) { Log::warning('YooKassa: '.$e->getMessage()); return null; }
    }
    public function handleWebhook(array $payload): ?Order {
        if (($payload['event'] ?? '') === '' && empty($payload['object'])) {
            return null;
        }
        $object = $payload['object'] ?? []; $status = $object['status'] ?? null;
        $paymentId = $object['id'] ?? null; $orderId = $object['metadata']['order_id'] ?? null;
        if (!$orderId || !$paymentId) return null;
        $order = Order::withoutGlobalScopes()->whereKey($orderId)->first(); if (!$order) return null;
        $order->payment_id = $paymentId;
        if (($payload['event']??'')==='payment.succeeded' || $status==='succeeded') { $order->is_paid=true; $order->payment_status='paid'; }
        elseif ($status==='canceled') $order->payment_status='canceled';
        else $order->payment_status = $status ?: 'pending';
        $order->save(); return $order;
    }
}
