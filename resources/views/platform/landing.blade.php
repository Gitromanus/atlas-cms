<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AtlasCMS — интернет-магазин с обменом 1С за минуты</title>
    <meta name="description" content="SaaS для запуска онлайн-витрины с каталогом, заказами и синхронизацией товаров, цен и остатков из 1С. Без программистов и отдельного хостинга.">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: { DEFAULT: '#4f46e5', dark: '#3730a3', light: '#818cf8' },
                    },
                    fontFamily: {
                        sans: ['Inter', 'Segoe UI', 'system-ui', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    <style>
        html { scroll-behavior: smooth; }
        .glass { background: rgba(255,255,255,0.06); backdrop-filter: blur(12px); }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 antialiased">
<header class="sticky top-0 z-50 border-b border-white/5 bg-slate-950/80 backdrop-blur-lg">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4">
        <a href="/" class="flex items-center gap-2 font-black tracking-tight">
            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand text-sm text-white shadow-lg shadow-brand/40">A</span>
            <span class="text-lg">Atlas<span class="text-brand-light">CMS</span></span>
        </a>
        <nav class="hidden items-center gap-6 text-sm font-medium text-slate-300 md:flex">
            <a href="#features" class="hover:text-white">Возможности</a>
            <a href="#how" class="hover:text-white">Как работает</a>
            <a href="#demo" class="hover:text-white">Демо</a>
            <a href="#faq" class="hover:text-white">FAQ</a>
        </nav>
        <div class="flex items-center gap-2">
            <a href="{{ url('/shop/login') }}" class="hidden rounded-xl px-4 py-2 text-sm font-semibold text-slate-300 hover:text-white sm:inline">Войти</a>
            <a href="{{ url('/create-shop') }}" class="rounded-xl bg-brand px-4 py-2 text-sm font-bold text-white shadow-lg shadow-brand/30 hover:bg-brand-dark">Создать магазин</a>
        </div>
    </div>
</header>

<section class="relative overflow-hidden">
    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-brand/30 via-slate-950 to-slate-950"></div>
    <div class="relative mx-auto max-w-6xl px-4 pb-20 pt-16 md:pb-28 md:pt-24">
        <div class="mx-auto max-w-3xl text-center">
            <p class="mb-4 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-brand-light">
                SaaS · 1С · Без программистов
            </p>
            <h1 class="text-4xl font-black leading-[1.1] tracking-tight text-white md:text-6xl">
                Интернет-магазин<br>
                <span class="bg-gradient-to-r from-brand-light to-violet-300 bg-clip-text text-transparent">с живыми ценами из 1С</span>
            </h1>
            <p class="mx-auto mt-6 max-w-2xl text-base leading-relaxed text-slate-400 md:text-lg">
                Создайте витрину за минуты: каталог, корзина, заказы, статьи и отзывы.
                Товары, характеристики, цены и остатки подтягиваются из вашей 1С автоматически.
            </p>
            <div class="mt-10 flex flex-wrap items-center justify-center gap-3">
                <a href="{{ url('/create-shop') }}" class="rounded-2xl bg-brand px-8 py-4 text-base font-bold text-white shadow-xl shadow-brand/40 transition hover:-translate-y-0.5 hover:bg-brand-dark">Создать магазин бесплатно</a>
                <a href="{{ url('/odeza-i-obuv') }}" class="rounded-2xl border border-white/15 bg-white/5 px-8 py-4 text-base font-semibold text-white transition hover:bg-white/10">Смотреть демо-магазин</a>
            </div>
            <p class="mt-4 text-sm text-slate-500">Без карты · Без договора · Можно отключить в любой момент</p>
        </div>
        <div class="mx-auto mt-16 grid max-w-3xl grid-cols-2 gap-4 md:grid-cols-4">
            <div class="rounded-2xl border border-white/10 glass p-4 text-center"><p class="text-xl font-black text-white md:text-2xl">1С</p><p class="mt-1 text-xs text-slate-400">CommerceML обмен</p></div>
            <div class="rounded-2xl border border-white/10 glass p-4 text-center"><p class="text-xl font-black text-white md:text-2xl">5 мин</p><p class="mt-1 text-xs text-slate-400">до запуска витрины</p></div>
            <div class="rounded-2xl border border-white/10 glass p-4 text-center"><p class="text-xl font-black text-white md:text-2xl">0 ₽</p><p class="mt-1 text-xs text-slate-400">старт без оплаты</p></div>
            <div class="rounded-2xl border border-white/10 glass p-4 text-center"><p class="text-xl font-black text-white md:text-2xl">SaaS</p><p class="mt-1 text-xs text-slate-400">хостинг уже внутри</p></div>
        </div>
    </div>
</section>

<section id="features" class="border-t border-white/5 bg-slate-900/40 py-20">
    <div class="mx-auto max-w-6xl px-4">
        <div class="mx-auto max-w-2xl text-center">
            <h2 class="text-3xl font-extrabold tracking-tight md:text-4xl">Всё для продаж онлайн</h2>
            <p class="mt-3 text-slate-400">Готовый стек: от витрины до заказов и контента</p>
        </div>
        <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-2xl border border-white/10 bg-slate-950/60 p-6 hover:border-brand/40"><div class="text-3xl">🔄</div><h3 class="mt-4 text-lg font-bold">Обмен с 1С</h3><p class="mt-2 text-sm text-slate-400">Товары, цены, остатки и характеристики по CommerceML.</p></div>
            <div class="rounded-2xl border border-white/10 bg-slate-950/60 p-6 hover:border-brand/40"><div class="text-3xl">🛒</div><h3 class="mt-4 text-lg font-bold">Каталог и корзина</h3><p class="mt-2 text-sm text-slate-400">Категории, фильтры, карточки, оформление заказа.</p></div>
            <div class="rounded-2xl border border-white/10 bg-slate-950/60 p-6 hover:border-brand/40"><div class="text-3xl">📦</div><h3 class="mt-4 text-lg font-bold">Заказы в админке</h3><p class="mt-2 text-sm text-slate-400">Статусы, CSV, email-уведомления, история у покупателя.</p></div>
            <div class="rounded-2xl border border-white/10 bg-slate-950/60 p-6 hover:border-brand/40"><div class="text-3xl">⭐</div><h3 class="mt-4 text-lg font-bold">Отзывы и рейтинг</h3><p class="mt-2 text-sm text-slate-400">Оценки 1–5 с модерацией перед публикацией.</p></div>
            <div class="rounded-2xl border border-white/10 bg-slate-950/60 p-6 hover:border-brand/40"><div class="text-3xl">📰</div><h3 class="mt-4 text-lg font-bold">Статьи и новости</h3><p class="mt-2 text-sm text-slate-400">Контент на витрине, включение галочками в настройках.</p></div>
            <div class="rounded-2xl border border-white/10 bg-slate-950/60 p-6 hover:border-brand/40"><div class="text-3xl">🎨</div><h3 class="mt-4 text-lg font-bold">Свой стиль</h3><p class="mt-2 text-sm text-slate-400">Тема, цвета, логотип и контакты — без кода.</p></div>
        </div>
    </div>
</section>

<section id="how" class="py-20">
    <div class="mx-auto max-w-6xl px-4">
        <div class="mx-auto max-w-2xl text-center">
            <h2 class="text-3xl font-extrabold tracking-tight md:text-4xl">Три шага до витрины</h2>
            <p class="mt-3 text-slate-400">От регистрации до первых заказов — без IT-отдела</p>
        </div>
        <div class="mt-12 grid gap-6 md:grid-cols-3">
            <div class="rounded-2xl border border-white/10 p-6 md:p-8"><span class="text-4xl font-black text-brand/40">01</span><h3 class="mt-3 text-xl font-bold">Создайте магазин</h3><p class="mt-2 text-sm text-slate-400">Название и slug — сразу панель владельца и витрина.</p></div>
            <div class="rounded-2xl border border-white/10 p-6 md:p-8"><span class="text-4xl font-black text-brand/40">02</span><h3 class="mt-3 text-xl font-bold">Подключите 1С</h3><p class="mt-2 text-sm text-slate-400">URL обмена в 1С — каталог, цены и остатки сами.</p></div>
            <div class="rounded-2xl border border-white/10 p-6 md:p-8"><span class="text-4xl font-black text-brand/40">03</span><h3 class="mt-3 text-xl font-bold">Принимайте заказы</h3><p class="mt-2 text-sm text-slate-400">Статусы в админке, контакты из настроек на витрине.</p></div>
        </div>
    </div>
</section>

<section id="demo" class="border-t border-white/5 bg-slate-900/40 py-20">
    <div class="mx-auto max-w-6xl px-4">
        <div class="grid items-center gap-10 lg:grid-cols-2">
            <div>
                <h2 class="text-3xl font-extrabold tracking-tight md:text-4xl">Живое демо</h2>
                <p class="mt-4 leading-relaxed text-slate-400">Магазин «Одежда и обувь»: каталог, корзина, статьи, отзывы. Откройте как покупатель или зайдите в админку.</p>
                <ul class="mt-6 space-y-2 text-sm text-slate-300">
                    <li class="flex gap-2"><span class="text-brand-light">✓</span> Витрина: /odeza-i-obuv</li>
                    <li class="flex gap-2"><span class="text-brand-light">✓</span> Админка: /shop</li>
                    <li class="flex gap-2"><span class="text-brand-light">✓</span> Заказы и модерация отзывов</li>
                </ul>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ url('/odeza-i-obuv') }}" class="rounded-xl bg-white px-6 py-3 text-sm font-bold text-slate-900 hover:bg-slate-100">Открыть витрину</a>
                    <a href="{{ url('/shop/login') }}" class="rounded-xl border border-white/20 px-6 py-3 text-sm font-semibold hover:bg-white/5">Вход в админку</a>
                </div>
            </div>
            <div class="rounded-3xl border border-white/10 bg-gradient-to-br from-brand/20 to-violet-900/30 p-1 shadow-2xl shadow-brand/20">
                <div class="rounded-[1.25rem] bg-slate-950 p-6 md:p-8">
                    <div class="flex items-center gap-2 text-xs text-slate-500">
                        <span class="h-2.5 w-2.5 rounded-full bg-red-400"></span>
                        <span class="h-2.5 w-2.5 rounded-full bg-amber-400"></span>
                        <span class="h-2.5 w-2.5 rounded-full bg-emerald-400"></span>
                        <span class="ml-2">odeza-i-obuv · витрина</span>
                    </div>
                    <div class="mt-6 space-y-3">
                        <div class="h-3 w-2/3 rounded-full bg-white/10"></div>
                        <div class="h-3 w-1/2 rounded-full bg-white/5"></div>
                        <div class="mt-6 grid grid-cols-3 gap-3">
                            <div class="aspect-square rounded-xl bg-gradient-to-br from-brand/40 to-slate-800"></div>
                            <div class="aspect-square rounded-xl bg-gradient-to-br from-violet-500/30 to-slate-800"></div>
                            <div class="aspect-square rounded-xl bg-gradient-to-br from-emerald-500/20 to-slate-800"></div>
                        </div>
                        <div class="mt-4 flex gap-2">
                            <div class="h-8 flex-1 rounded-lg bg-brand/80"></div>
                            <div class="h-8 w-20 rounded-lg bg-white/10"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section id="faq" class="py-20">
    <div class="mx-auto max-w-3xl px-4">
        <h2 class="text-center text-3xl font-extrabold tracking-tight md:text-4xl">Частые вопросы</h2>
        <div class="mt-10 space-y-3">
            <details class="group rounded-2xl border border-white/10 bg-slate-900/50 open:bg-slate-900">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4 font-semibold">Нужен ли свой сервер?<span class="text-slate-500 transition group-open:rotate-45">+</span></summary>
                <p class="border-t border-white/5 px-5 pb-4 pt-3 text-sm text-slate-400">Нет. AtlasCMS — SaaS: витрина и админка уже на платформе.</p>
            </details>
            <details class="group rounded-2xl border border-white/10 bg-slate-900/50 open:bg-slate-900">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4 font-semibold">Как устроен адрес магазина?<span class="text-slate-500 transition group-open:rotate-45">+</span></summary>
                <p class="border-t border-white/5 px-5 pb-4 pt-3 text-sm text-slate-400">Витрина по path: site.ru/ваш-slug. Позже можно подключить свой домен.</p>
            </details>
            <details class="group rounded-2xl border border-white/10 bg-slate-900/50 open:bg-slate-900">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4 font-semibold">Что синхронизируется с 1С?<span class="text-slate-500 transition group-open:rotate-45">+</span></summary>
                <p class="border-t border-white/5 px-5 pb-4 pt-3 text-sm text-slate-400">Номенклатура, характеристики, цены, остатки, картинки. Заказы — по CommerceML.</p>
            </details>
            <details class="group rounded-2xl border border-white/10 bg-slate-900/50 open:bg-slate-900">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4 font-semibold">Можно отключить статьи или отзывы?<span class="text-slate-500 transition group-open:rotate-45">+</span></summary>
                <p class="border-t border-white/5 px-5 pb-4 pt-3 text-sm text-slate-400">Да. Галочки в настройках магазина убирают разделы с витрины и из меню.</p>
            </details>
        </div>
    </div>
</section>

<section class="pb-20">
    <div class="mx-auto max-w-6xl px-4">
        <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-brand to-violet-600 px-8 py-14 text-center shadow-2xl shadow-brand/30 md:px-16">
            <h2 class="text-3xl font-extrabold text-white md:text-4xl">Готовы запустить витрину?</h2>
            <p class="mx-auto mt-3 max-w-xl text-white/85">Создайте магазин, подключите 1С — каталог заполнится сам.</p>
            <a href="{{ url('/create-shop') }}" class="mt-8 inline-block rounded-2xl bg-white px-10 py-4 text-lg font-bold text-brand hover:bg-slate-100">Создать магазин бесплатно</a>
        </div>
    </div>
</section>

<footer class="border-t border-white/5">
    <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-4 py-8 text-sm text-slate-500 md:flex-row">
        <div class="flex items-center gap-2 font-bold text-slate-300">
            <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-brand text-xs text-white">A</span>
            AtlasCMS
        </div>
        <div class="flex flex-wrap justify-center gap-4">
            <a href="{{ url('/create-shop') }}" class="hover:text-white">Регистрация</a>
            <a href="{{ url('/shop/login') }}" class="hover:text-white">Вход</a>
            <a href="{{ url('/odeza-i-obuv') }}" class="hover:text-white">Демо</a>
        </div>
        <span>© {{ date('Y') }} · Laravel SaaS</span>
    </div>
</footer>
</body>
</html>
