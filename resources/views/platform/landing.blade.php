<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AtlasCMS — интернет-магазин с обменом 1С за 30 секунд</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', 'Segoe UI', system-ui, sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 antialiased">

<!-- Шапка -->
<header class="border-b border-white/10">
    <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
        <div class="flex items-center gap-2">
            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-500 text-lg font-black">A</span>
            <span class="text-lg font-bold">AtlasCMS</span>
        </div>
        <a href="{{ url('/platform/login') }}" class="rounded-xl border border-white/15 px-4 py-2 text-sm hover:bg-white/5">Войти</a>
    </div>
</header>

<!-- Герой -->
<section class="mx-auto max-w-6xl px-4 py-20 text-center">
    <h1 class="mx-auto max-w-3xl text-4xl font-extrabold leading-tight md:text-6xl">
        Интернет-магазин с обменом 1С
        <span class="text-indigo-400">за 30 секунд</span>
    </h1>
    <p class="mx-auto mt-6 max-w-2xl text-lg text-slate-400">
        Витрина, каталог, корзина и заказы. Товары, цены и остатки — автоматически из вашей 1С.
        Никакого программирования и хостинга: всё работает сразу.
    </p>
    <a href="{{ route('platform.register') }}"
       class="mt-10 inline-block rounded-2xl bg-indigo-600 px-10 py-4 text-lg font-bold text-white shadow-lg shadow-indigo-600/30 hover:bg-indigo-500">
        Создать магазин бесплатно
    </a>
    <p class="mt-4 text-sm text-slate-500">Без карты и договора · Отмена в любой момент</p>
</section>

<!-- Возможности -->
<section class="mx-auto max-w-6xl px-4 py-12">
    <div class="grid gap-6 md:grid-cols-3">
        <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
            <div class="mb-3 text-3xl">🔄</div>
            <h3 class="text-lg font-bold">Обмен с 1С</h3>
            <p class="mt-2 text-sm text-slate-400">Товары, характеристики, цены и остатки синхронизируются автоматически по CommerceML.</p>
        </div>
        <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
            <div class="mb-3 text-3xl">🛒</div>
            <h3 class="text-lg font-bold">Готовая витрина</h3>
            <p class="mt-2 text-sm text-slate-400">Каталог с категориями, корзина, оформление заказа и личный кабинет покупателя.</p>
        </div>
        <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
            <div class="mb-3 text-3xl">🎨</div>
            <h3 class="text-lg font-bold">Свой стиль</h3>
            <p class="mt-2 text-sm text-slate-400">Выберите тему и цвета магазина в панели — внешний вид меняется без программирования.</p>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="mx-auto max-w-6xl px-4 py-16 text-center">
    <div class="rounded-3xl bg-gradient-to-r from-indigo-600 to-violet-600 p-10 md:p-14">
        <h2 class="text-3xl font-extrabold">Начните продавать онлайн</h2>
        <p class="mx-auto mt-3 max-w-xl text-white/80">Создайте магазин, подключите 1С — и каталог заполнится сам.</p>
        <a href="{{ route('platform.register') }}"
           class="mt-8 inline-block rounded-2xl bg-white px-10 py-4 text-lg font-bold text-indigo-700 hover:bg-slate-100">
            Создать магазин
        </a>
    </div>
</section>

<footer class="border-t border-white/10">
    <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-2 px-4 py-6 text-sm text-slate-500 md:flex-row">
        <span>© {{ date('Y') }} AtlasCMS</span>
        <span>Работает на Laravel · MIT License</span>
    </div>
</footer>

</body>
</html>