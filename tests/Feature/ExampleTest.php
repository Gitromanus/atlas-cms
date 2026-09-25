<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\Theme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_home_page_renders_with_active_tenant(): void
    {
        $theme = Theme::query()->firstOrCreate(['slug' => 'default'], ['name' => 'Default']);

        Tenant::query()->create([
            'name' => 'Демо-магазин',
            'subdomain' => 'demo',
            'slug' => 'demo',
            'theme_id' => $theme->id,
            'is_active' => true,
        ]);

        // Path-based витрина: /{slug}/
        $response = $this->get('/demo');

        $response->assertOk();
        $response->assertSee('Демо-магазин');
    }

    public function test_platform_landing_on_root(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('AtlasCMS');
    }
}
