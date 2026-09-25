<?php
namespace App\Http\Middleware;
use Closure; use Illuminate\Http\Request; use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;
class CaptureUtm {
    public function handle(Request $request, Closure $next): Response {
        $keys = ['utm_source','utm_medium','utm_campaign','utm_content','utm_term'];
        $data = Session::get('utm', []); $found=false;
        foreach ($keys as $key) { if ($request->filled($key)) { $data[$key]=substr((string)$request->query($key),0,255); $found=true; } }
        if ($found) Session::put('utm', $data);
        return $next($request);
    }
}
