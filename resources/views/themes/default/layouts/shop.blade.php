<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', $currentTenant?->name ?? 'AtlasCMS')</title>
    <link rel="stylesheet" href="{{ asset('css/shop.css') }}">
    <link rel="stylesheet" href="{{ route('theme.css') }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: 'var(--color-primary)',
                        accent: 'var(--color-accent)',
                    },
                    fontFamily: {
                        sans: ['var(--font-family)'],
                    },
                    borderRadius: {
                        theme: 'var(--radius)',
                    },
                }
            }
        }
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak]{display:none!important}</style>
    @stack('head')
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">

<header class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3">
        <a href="{{ route('home') }}" class="flex items-center gap-2">
            @if ($currentTenant?->logo_path)
                <img src="{{ asset('storage/logos/'.$currentTenant->logo_path) }}" alt="{{ $currentTenant->name }}" class="h-8 w-8 rounded-theme object-cover">
            @else
                <span class="flex h-8 w-8 items-center justify-center rounded-theme bg-primary font-black text-white">A</span>
            @endif
            <span class="text-lg font-bold">{{ $currentTenant?->name ?? 'Магазин' }}</span>
        </a>

        <nav class="hidden items-center gap-5 md:flex">
            <a href="{{ route('home') }}" class="hover:text-primary">Главная</a>
            <a href="{{ route('catalog.index') }}" class="hover:text-primary">Каталог</a>

            @php($categoryMenu = \App\Models\Category::menuTree())
            @foreach ($categoryMenu as $category)
                @include('shop.partials.category-menu', ['category' => $category, 'menuLevel' => 0])
            @endforeach

            <a href="{{ route('cart.index') }}" class="relative hover:text-primary">
                Корзина
                @if (\App\Services\Cart\CartService::class && app()->bound(\App\Services\Cart\CartService::class))
                    @php($cartCount = app(\App\Services\Cart\CartService::class)->count())
                    @if ($cartCount > 0)
                        <span class="absolute -right-3 -top-2 flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1 text-xs font-bold text-white">{{ $cartCount }}</span>
                    @endif
                @endif
            </a>
        </nav>

        <div class="flex items-center gap-3">
            @auth('customers')
                <a href="{{ route('account.orders') }}" class="hover:text-primary">Мои заказы</a>
                <form method="POST" action="{{ route('customer.logout') }}">
                    @csrf
                    <button type="submit" class="rounded-theme border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-100">Выйти</button>
                </form>
            @else
                <a href="{{ route('customer.login') }}" class="rounded-theme border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-100">Войти</a>
            @endauth
        </div>
    </div>
</header>

<main class="mx-auto max-w-6xl flex-1 px-4 py-8">
    @if (session('status'))
        <div class="mb-6 rounded-theme border border-green-200 bg-green-50 px-4 py-3 text-green-800">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-6 rounded-theme border border-red-200 bg-red-50 px-4 py-3 text-red-800">
            <ul class="list-disc pl-4">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @yield('content')
</main>

<footer class="border-t border-slate-200 bg-white">
    <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-2 px-4 py-6 text-sm text-slate-500 md:flex-row">
        <span>© {{ date('Y') }} {{ $currentTenant?->name ?? 'Магазин' }}</span>
        <span>Работает на AtlasCMS</span>
    </div>
</footer>

@stack('scripts')
</body>
</html>