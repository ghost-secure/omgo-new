# Оформление кабинета (светлая тема)

Цель: приблизить светлую тему к легаси OMGO. Темную не трогаем, пока нет отдельного пункта.

## Что с test, что нет

Ядро (`core/assets/`: `config-default.css`, `config.css`, `components.css`, плагины) уже то же, что на test. Отдельно с test **не копировали** магазин и шрифт Geologica.

Единственный проектный CSS админки на test — `project/overrides/assets/config-light.css`. На OMGO его не было: `core.js` грузит файл и при 404 молча идёт дальше. Из‑за этого в светлой теме не было чёрных тултипов.

Причина в ядре: `--tooltip-bg` задан в `config.css` на **`.light-theme`**, а `<html>` носит класс **`theme-light`**. Токен не применяется. На test это закрыто `:root` / оверрайдом. На OMGO — `html.theme-light` в своём `config-light.css` (не чужой индиго `#3834DA`).

## Текущая светлая тема

Файл: `project/overrides/assets/config-light.css`.  
Публичный URL: `/project/overrides/assets/config-light.css` (symlink `public/project/overrides` → `project/overrides`, см. `bootstrap.php`).

| Токен / элемент | Значение | Легаси |
|-----------------|----------|--------|
| `--color-primary` | `#ff7f22` | `--main-color` в `lu-original.css` |
| кнопки `.btn-primary` | тот же primary | promo |
| активный пункт меню | `#ff7f22` | `--sidebar-menu-color-active` |
| сайдбар и ячейка лого | фон `#000` | `--sidebar-bg: var(--black)` |
| тултип | чёрный полупрозрачный, белый текст | как на test после оверрайда |

## Страница входа

`/login`, `/reg`, `/recover` открываются шаблоном `project/templates/auth.php`, не `light.php`.

Разметка как у легаси `_auth_custom.htm`: на весь экран фон, тёмный слой, пустая левая зона, белая панель справа. На ширине до 990px левая зона скрыта, панель на всю ширину. Стили кабинета (`lu-original.css` и прочие файлы легаси) сюда не подключаются: отступы и кнопки панели заданы в самом шаблоне, цвет кнопки `#ff7f22`.

Фон: `project/overrides/assets/light_bg.jpg`, URL `/project/overrides/assets/light_bg.jpg`. Это тот же кадр, что `https://lk.oncogenetic.org/project/web/img/light_bg.jpg`.

Переключение форм — кнопки «Вход», «Регистрация», «Восстановление» (ссылки на три адреса). Лого без крестика сайдбара (`$omgoLogoWithClose = false`).

## Логотип

`core/templates/light.php` подключает `project/phpfiles/manager/logo.php`, если файл есть; иначе текст `CRUSO ADMIN`.

Эталон: `omgo-lu/project/phpfiles/manager/logo.php`. Там `$this->mobile` — у ядра такого флага нет, ветка **CSS 768px**: десктоп 166×56, мобильный 130×40 + крестик (жмёт `#sidebar-toggle`). У двух SVG разные id clip/mask.

## Легаси CSS (не подключать в кабинет)

Сравнивать палитру и сайдбар: **`omgo-lu/project/web/css/`**

| Файл | Что там |
|------|---------|
| `lu-original.css` | `--main-color`, `--sidebar-bg`, меню |
| `uikit.css` | `.bg-promo`, `.text-color-promo` |
| `gisp.css` | старые сетки |
| `tour.css` | тур |

В `omgo/project` эти файлы не копируем и в `light.php` не линкуем: токены пишем в `config-light.css`.

## История логики

### 2026-10-08 — страница входа без шапки кабинета

- **Тип:** новая логика
- **Было:** форма входа внутри `light.php`
- **Стало:** отдельный `templates/auth.php`, фон `light_bg.jpg`, панель справа

### 2026-10-08 — светлая тема ближе к легаси

- **Тип:** новая логика
- **Было:** дефолт ядра (синий primary), текст CRUSO ADMIN, серый сайдбар, тултипы без `--tooltip-bg`
- **Стало:** `#ff7f22`, SVG-лого, чёрный сайдбар, чёрные тултипы через `config-light.css`
