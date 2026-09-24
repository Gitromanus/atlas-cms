# Деплой AtlasCMS на SpaceWeb

Пошаговая инструкция развёртывания на бесплатном тарифе SpaceWeb
(PHP + MySQL, доступ по FTP/панели).

## 1. Сборка проекта локально

```bash
composer install --no-dev --optimize-autoloader
```

После сборки на сервер загружается весь проект (папка `vendor` уже внутри).

> Проект не требует Node.js на хостинге: фронтенд собран на Blade + CDN-стилях.

## 2. Загрузка на хостинг

Структура каталогов сайта SpaceWeb:

```
/каталог_сайта/
├── public_html/   ← сюда загружается содержимое public/ проекта
└── (выше корня)   ← остальная часть проекта (app, bootstrap, vendor...)
```

Рекомендуемая структура:

```
/home/user/atlas-cms/      ← весь проект (кроме public/)
└── public_html/           ← содержимое public/
```

Настройте документ-корень сайта на `public_html` (или на `/home/user/atlas-cms/public`
при возможности указать его в панели).

## 3. Настройка .env

Скопируйте `.env.example` в `.env` и заполните:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ваш-домен.ru
ATLAS_ROOT_DOMAIN=ваш-домен.ru   # поддомены магазинов: shop1.ваш-домен.ru

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=имя_базы
DB_USERNAME=пользователь
DB_PASSWORD=пароль

QUEUE_CONNECTION=database
SESSION_DRIVER=database
CACHE_STORE=database
```

## 4. База данных

1. Создайте базу MySQL в панели хостинга.
2. Выполните миграции и сидер:

```bash
php artisan migrate --force
php artisan db:seed --force
```

3. Владельцу нужно сменить доступы: супер-админ `admin@atlascms.ru`,
   пароль по умолчанию `admin12345`.

## 5. Файловые ссылки

Выполните `php artisan storage:link`, либо вручную создайте симлинк
`public/storage` → `storage/app/public`. Если символические ссылки
недоступны, скопируйте содержимое папки `storage/app/public` в `public/storage`.

## 6. Права на запись

Убедитесь, что веб-сервер может писать в:

- `storage/framework/{cache,sessions,views}`
- `storage/logs`
- `storage/app/1c` (файлы обмена с 1С)

## 7. Cron (очереди)

В панели SpaceWeb добавьте задание cron (каждую минуту):

```cron
* * * * * php /home/user/atlas-cms/artisan schedule:run >> /dev/null 2>&1
```

Оно обрабатывает очередь импорта из 1С. Если cron недоступен на бесплатном
тарифе — включите обмен в синхронном режиме: в `.env` установите
`QUEUE_CONNECTION=sync` (файлы будут обрабатываться сразу при обмене).

## 8. SSL и поддомены

1. Включите SSL для основного домена.
2. Настройте **wildcard-поддомен** `*.ваш-домен.ru` → тот же сайт
   (нужен для витрин магазинов) либо создавайте поддомены вручную.
3. У каждого магазина в админке можно указать собственный домен.

## 9. Проверка

- `https://ваш-домен.ru` — витрина;
- `https://ваш-домен.ru/admin` — админ-панель;
- `https://shop1.ваш-домен.ru` — витрина магазина shop1;
- `https://ваш-домен.ru/up` — health-check Laravel.