<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\PriceType;
use App\Models\Product;
use App\Models\ProductFeature;
use App\Models\ProductStock;
use App\Models\Tenant;
use App\Models\Theme;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OneCExchangeTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    /**
     * Учётная запись владельца магазина используется для Basic-авторизации обмена.
     */
    protected string $login = 'owner@test.ru';

    protected string $password = 'secret123';

    protected function setUp(): void
    {
        parent::setUp();

        $theme = Theme::query()->create([
            'slug' => 'default',
            'name' => 'Default',
        ]);

        $this->tenant = Tenant::query()->create([
            'name' => 'Тест-магазин',
            'subdomain' => 'test',
            'slug' => 'testshop',
            'theme_id' => $theme->id,
            'is_active' => true,
        ]);

        // Владелец магазина — учётные данные обмена с 1С
        User::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Владелец',
            'email' => $this->login,
            'password' => Hash::make($this->password),
        ]);

        // Чистый каталог файлов обмена на каждый тест
        File::deleteDirectory(storage_path('app/1c/testshop'));
    }

    protected function basicHeaders(): array
    {
        return [
            'Authorization' => 'Basic '.base64_encode($this->login.':'.$this->password),
        ];
    }

    /**
     * POST с произвольным (raw) телом — так 1С передаёт содержимое файлов.
     */
    protected function postRaw(string $uri, string $content): \Illuminate\Testing\TestResponse
    {
        $server = $this->transformHeadersToServerVars($this->basicHeaders());

        return $this->call('POST', $uri, [], [], [], $server, $content);
    }

    public function test_checkauth_returns_session(): void
    {
        $response = $this->get('http://test.atlascms.ru/1c/exchange?type=catalog&mode=checkauth', $this->basicHeaders());

        $response->assertOk();
        $this->assertStringStartsWith("success\n", $response->getContent());
    }

    public function test_checkauth_rejects_bad_credentials(): void
    {
        $response = $this->get('http://test.atlascms.ru/1c/exchange?type=catalog&mode=checkauth', [
            'Authorization' => 'Basic '.base64_encode('bad:creds'),
        ]);

        $response->assertOk();
        $this->assertSame('failure', $response->getContent());
    }

    public function test_repeated_checkauth_keeps_session_and_file_upload_works(): void
    {
        // Первый checkauth — как делает 1С в начале обмена
        $first = $this->get('http://test.atlascms.ru/1c/exchange?type=catalog&mode=checkauth', $this->basicHeaders());
        $sessionId = explode("\n", $first->getContent())[1];

        // Повторный checkauth (1С вызывает его несколько раз) — сессия не должна перезаписываться
        $second = $this->get('http://test.atlascms.ru/1c/exchange?type=catalog&mode=checkauth', $this->basicHeaders());
        $this->assertSame($sessionId, explode("\n", $second->getContent())[1]);

        // У типа sale — собственная сессия
        $sale = $this->get('http://test.atlascms.ru/1c/exchange?type=sale&mode=checkauth', $this->basicHeaders());
        $this->assertNotSame($sessionId, explode("\n", $sale->getContent())[1]);

        // init и отправка файла с исходной сессией должны пройти
        $this->get("http://test.atlascms.ru/1c/exchange?type=catalog&mode=init&session_id={$sessionId}")
            ->assertOk()
            ->assertSee('zip=no');

        $this->postRaw(
            "http://test.atlascms.ru/1c/exchange?type=catalog&mode=file&filename=import.xml&session_id={$sessionId}",
            '<?xml version="1.0" encoding="utf-8"?><КоммерческаяИнформация ВерсияСхемы="2.09"><Каталог><Товары></Товары></Каталог></КоммерческаяИнформация>'
        )->assertOk()->assertSee('success');
    }

    public function test_file_from_previous_run_is_truncated_not_appended(): void
    {
        // Первый прогон: checkauth + файл import.xml
        $first = $this->get('http://test.atlascms.ru/1c/exchange?type=catalog&mode=checkauth', $this->basicHeaders());
        $sessionId = explode("\n", $first->getContent())[1];

        $this->postRaw(
            "http://test.atlascms.ru/1c/exchange?type=catalog&mode=file&filename=import.xml&session_id={$sessionId}",
            '<doc1/>'
        )->assertOk()->assertSee('success');

        // Второй прогон: тот же файл должен приниматься заново, а не дописываться
        $second = $this->get('http://test.atlascms.ru/1c/exchange?type=catalog&mode=checkauth', $this->basicHeaders());
        $sessionId2 = explode("\n", $second->getContent())[1];

        $this->postRaw(
            "http://test.atlascms.ru/1c/exchange?type=catalog&mode=file&filename=import.xml&session_id={$sessionId2}",
            '<doc2/>'
        )->assertOk()->assertSee('success');

        $content = file_get_contents(storage_path('app/1c/testshop/import.xml'));
        $this->assertSame('<doc2/>', trim((string) $content));
    }

    public function test_full_catalog_import(): void
    {
        // 1. Авторизация
        $checkauth = $this->get('http://test.atlascms.ru/1c/exchange?type=catalog&mode=checkauth', $this->basicHeaders());
        $sessionId = explode("\n", $checkauth->getContent())[1];

        // 2. Init
        $this->get("http://test.atlascms.ru/1c/exchange?type=catalog&mode=init&session_id={$sessionId}")
            ->assertOk()
            ->assertSee('zip=no');

        $importXml = <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<КоммерческаяИнформация ВерсияСхемы="2.09">
  <Классификатор>
    <Свойства>
      <Свойство><Ид>prop-1</Ид><Наименование>Память</Наименование></Свойство>
    </Свойства>
    <Группы>
      <Группа>
        <Ид>cat-1</Ид>
        <Наименование>Электроника</Наименование>
      </Группа>
    </Группы>
  </Классификатор>
  <Каталог>
    <Товары>
      <Товар>
        <Ид>prod-1</Ид>
        <Штрихкод>4600000000000</Штрихкод>
        <Артикул>ART-001</Артикул>
        <Наименование>Телефон Atlas</Наименование>
        <Описание>Очень хороший телефон</Описание>
        <БазоваяЕдиница Код="796" НаименованиеПолное="Штука"/>
        <Группы><Ид>cat-1</Ид></Группы>
        <ЗначенияСвойств>
          <ЗначенияСвойства>
            <Ид>prop-1</Ид>
            <Значение>128 ГБ</Значение>
          </ЗначенияСвойства>
        </ЗначенияСвойств>
        <Картинка>https://example.com/img.jpg</Картинка>
      </Товар>
    </Товары>
  </Каталог>
</КоммерческаяИнформация>
XML;

        // 3. Загрузка файла import.xml
        $this->postRaw(
            "http://test.atlascms.ru/1c/exchange?type=catalog&mode=file&filename=import.xml&session_id={$sessionId}",
            $importXml
        )->assertOk();

        // 4. Импорт (в тестах очередь sync — обработка сразу)
        $this->post(
            "http://test.atlascms.ru/1c/exchange?type=catalog&mode=import&filename=import.xml&session_id={$sessionId}",
            [],
            $this->basicHeaders()
        )->assertOk()->assertSee('success');

        // 5. Проверка результата
        $this->assertDatabaseHas('categories', ['ext_id' => 'cat-1', 'name' => 'Электроника']);

        $product = Product::query()->where('ext_id', 'prod-1')->first();
        $this->assertNotNull($product);
        $this->assertSame('ART-001', $product->sku);
        $this->assertSame('Телефон Atlas', $product->name);
        $this->assertSame('Штука', $product->unit);

        $this->assertDatabaseHas('product_features', [
            'product_id' => $product->id,
            'name' => 'Память',
            'value' => '128 ГБ',
        ]);

        $this->assertDatabaseHas('product_images', [
            'product_id' => $product->id,
            'url' => 'https://example.com/img.jpg',
        ]);
    }

    public function test_offers_import_prices_and_stocks(): void
    {
        // Сначала каталог
        $category = Category::query()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Электроника',
            'ext_id' => 'cat-1',
        ]);

        $product = Product::query()->create([
            'tenant_id' => $this->tenant->id,
            'category_id' => $category->id,
            'name' => 'Телефон Atlas',
            'sku' => 'ART-001',
            'ext_id' => 'prod-1',
        ]);

        $checkauth = $this->get('http://test.atlascms.ru/1c/exchange?type=catalog&mode=checkauth', $this->basicHeaders());
        $sessionId = explode("\n", $checkauth->getContent())[1];

        $offersXml = <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<КоммерческаяИнформация ВерсияСхемы="2.09">
  <ПакетПредложений>
    <ТипыЦен>
      <ТипЦены><Ид>pt-1</Ид><Наименование>Розничная</Наименование><Валюта>RUB</Валюта></ТипЦены>
    </ТипыЦен>
    <Склады>
      <Склад><Ид>wh-1</Ид><Наименование>Основной склад</Наименование></Склад>
    </Склады>
    <Предложения>
      <Предложение>
        <Ид>prod-1</Ид>
        <Наименование>Телефон Atlas</Наименование>
        <Цены>
          <Цена><ИдТипаЦены>pt-1</ИдТипаЦены><ЦенаЗаЕдиницу>19999</ЦенаЗаЕдиницу><Валюта>RUB</Валюта></Цена>
        </Цены>
        <Количество>10</Количество>
        <Склады><Склад><Ид>wh-1</Ид><Количество>7</Количество></Склад></Склады>
      </Предложение>
    </Предложения>
  </ПакетПредложений>
</КоммерческаяИнформация>
XML;

        $this->postRaw(
            "http://test.atlascms.ru/1c/exchange?type=catalog&mode=file&filename=offers.xml&session_id={$sessionId}",
            $offersXml
        )->assertOk();

        $this->post(
            "http://test.atlascms.ru/1c/exchange?type=catalog&mode=import&filename=offers.xml&session_id={$sessionId}",
            [],
            $this->basicHeaders()
        )->assertOk();

        $priceType = PriceType::query()->where('ext_id', 'pt-1')->first();
        $this->assertNotNull($priceType);

        $this->assertDatabaseHas('product_prices', [
            'product_id' => $product->id,
            'price_type_id' => $priceType->id,
            'price' => '19999.00',
        ]);

        $warehouse = Warehouse::query()->where('ext_id', 'wh-1')->first();
        $this->assertNotNull($warehouse);

        $this->assertDatabaseHas('product_stocks', [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => '7.000',
        ]);

        $this->assertSame(7.0, (float) $product->stocks()->sum('quantity'));
    }

    public function test_catalog_import_marks_variant_properties(): void
    {
        $checkauth = $this->get('http://test.atlascms.ru/1c/exchange?type=catalog&mode=checkauth', $this->basicHeaders());
        $sessionId = explode("\n", $checkauth->getContent())[1];

        $importXml = <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<КоммерческаяИнформация ВерсияСхемы="2.09">
  <Классификатор>
    <Свойства>
      <Свойство>
        <Ид>prop-color</Ид>
        <Наименование>Цвет</Наименование>
        <ВариантыЗначений>
          <ВариантЗначения><Ид>color-1</Ид><Значение>Красный</Значение></ВариантЗначения>
          <ВариантЗначения><Ид>color-2</Ид><Значение>Синий</Значение></ВариантЗначения>
        </ВариантыЗначений>
      </Свойство>
      <Свойство>
        <Ид>prop-size</Ид>
        <Наименование>Размер</Наименование>
        <ВариантыЗначений>
          <ВариантЗначения><Ид>size-1</Ид><Значение>M</Значение></ВариантЗначения>
        </ВариантыЗначений>
      </Свойство>
    </Свойства>
    <Группы>
      <Группа><Ид>cat-1</Ид><Наименование>Одежда</Наименование></Группа>
    </Группы>
  </Классификатор>
  <Каталог>
    <Товары>
      <Товар>
        <Ид>prod-2</Ид>
        <Артикул>TS-100</Артикул>
        <Наименование>Футболка Atlas</Наименование>
        <Группы><Ид>cat-1</Ид></Группы>
        <ЗначенияСвойств>
          <ЗначенияСвойства><Ид>prop-color</Ид><Значение>Красный</Значение></ЗначенияСвойства>
          <ЗначенияСвойства><Ид>prop-size</Ид><Значение>M</Значение></ЗначенияСвойства>
        </ЗначенияСвойств>
      </Товар>
    </Товары>
  </Каталог>
</КоммерческаяИнформация>
XML;

        $this->postRaw(
            "http://test.atlascms.ru/1c/exchange?type=catalog&mode=file&filename=import.xml&session_id={$sessionId}",
            $importXml
        )->assertOk();

        $this->post(
            "http://test.atlascms.ru/1c/exchange?type=catalog&mode=import&filename=import.xml&session_id={$sessionId}",
            [],
            $this->basicHeaders()
        )->assertOk();

        $product = Product::query()->where('ext_id', 'prod-2')->first();
        $this->assertNotNull($product);

        $color = $product->features()->where('name', 'Цвет')->first();
        $this->assertNotNull($color);
        $this->assertTrue($color->is_variant);
        $this->assertSame(['Красный', 'Синий'], $color->options);

        $size = $product->features()->where('name', 'Размер')->first();
        $this->assertNotNull($size);
        $this->assertTrue($size->is_variant);
        $this->assertSame(['M'], $size->options);
    }
}