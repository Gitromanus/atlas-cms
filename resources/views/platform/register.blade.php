<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Создать магазин — AtlasCMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', 'Segoe UI', system-ui, sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 antialiased">

<div class="flex min-h-screen items-center justify-center px-4 py-12">
    <div class="w-full max-w-md">
        <div class="mb-8 text-center">
            <span class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-500 text-2xl font-black">A</span>
            <h1 class="text-2xl font-bold">Создайте свой интернет-магазин</h1>
            <p class="mt-2 text-sm text-slate-400">За 30 секунд — витрина, каталог и обмен с 1С</p>
        </div>

        <form method="POST" action="{{ route('platform.register') }}" class="space-y-4 rounded-2xl bg-white p-8 text-slate-800 shadow-2xl">
            @csrf

            <div>
                <label class="mb-1 block text-sm font-medium">Название магазина</label>
                <input type="text" name="shop_name" value="{{ old('shop_name') }}" required placeholder="Например: Магазин «Свежий хлеб»"
                       class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:border-indigo-500 focus:outline-none">
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">Ваше имя</label>
                <input type="text" name="name" value="{{ old('name') }}" required
                       class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:border-indigo-500 focus:outline-none">
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required
                       class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:border-indigo-500 focus:outline-none">
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">Пароль</label>
                <input type="password" name="password" required minlength="6"
                       class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:border-indigo-500 focus:outline-none">
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">Повторите пароль</label>
                <input type="password" name="password_confirmation" required minlength="6"
                       class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:border-indigo-500 focus:outline-none">
            </div>

            @if ($errors->any())
                <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    <ul class="list-disc pl-4">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <button type="submit" class="w-full rounded-xl bg-indigo-600 px-6 py-3 font-semibold text-white hover:bg-indigo-700">
                Создать магазин
            </button>
        </form>
    </div>
</div>

</body>
</html>