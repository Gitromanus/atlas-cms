<?php
namespace App\Http\Controllers\Shop;
use App\Http\Controllers\Controller; use App\Models\Order;
use App\Services\Payments\YooKassaService; use App\Services\Tenant\TenantContext;
use Illuminate\Http\RedirectResponse; use Illuminate\Http\Request; use Illuminate\Http\Response;
class PaymentController extends Controller {
    public function __construct(protected YooKassaService $yookassa) {}
    public function pay(string $shop, int $order): RedirectResponse {
        $tenant = app(TenantContext::class)->current();
        $model = Order::query()->whereKey($order)->firstOrFail();
        if ($model->is_paid) return redirect()->route('checkout.success',['shop'=>$shop,'order'=>$model->id]);
        if (!$this->yookassa->isConfigured($tenant)) {
            return redirect()->route('checkout.success',['shop'=>$shop,'order'=>$model->id])->with('status','Онлайн-оплата не настроена.');
        }
        $returnUrl = route('checkout.success',['shop'=>$shop,'order'=>$model->id]);
        $payment = $this->yookassa->createPayment($model,$tenant,$returnUrl);
        if (!$payment || empty($payment['confirmation']['confirmation_url'])) {
            return redirect()->route('checkout.success',['shop'=>$shop,'order'=>$model->id])->with('status','Не удалось создать платёж.');
        }
        $model->payment_id = $payment['id'] ?? null;
        $model->payment_status = $payment['status'] ?? 'pending';
        $model->save();
        return redirect()->away($payment['confirmation']['confirmation_url']);
    }
    public function webhook(Request $request): Response {
        $this->yookassa->handleWebhook($request->all());
        return response('OK', 200);
    }
}
