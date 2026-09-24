<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Tenant;
use App\Models\Theme;
use App\Services\Tenant\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_home_page_renders_with_active_tenant(): void
    {
        $theme = Theme::query()->firstOrCreate(['slug' => 'default'], ['name' => 'Default']);

        $tenant = Tenant::query()->create([
            'name' => 'Демо-магазин',
            'subdomain' => 'demo',
            'slug' => 'demo',
            'theme_id' => $theme->id,
            'is_active' => true,
        ]);

        app(TenantContext::class)->set($tenant);

        $response = $this->get('http://demo.atlascms.ru/');

        $response->assertOk();
        $response->assertSee('Демо-магазин');
    }
}
