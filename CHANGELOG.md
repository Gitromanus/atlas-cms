# Changelog

## 1.0.0 — 2026-09-26

### Готово к продакшену

- **Свой домен магазина** — указание hostname в настройках `/shop`, DNS A/CNAME на сервер; витрина без префикса `/{slug}`
- **ЮKassa** — оплата на checkout, webhook `/payment/webhook`, ключи в настройках магазина
- **Каталог + 1С CommerceML 2.09** — import/offers, варианты, цены, остатки, заказы
- **Витрина** — корзина, wishlist, compare, checkout, отзывы, статьи/новости, sitemap
- **Админки** — `/platform` (SaaS-владелец), `/shop` (предприниматель)
- **Самообслуживание** — `/create-shop`
- **Деплой** — GitHub Actions → SpaceWeb (SSH + atlas.zip)

### Безопасность

- Смените пароли сидера (`admin@atlascms.ru`, `owner@demo.ru`)
- Не коммитьте `.env` / секреты ЮKassa
