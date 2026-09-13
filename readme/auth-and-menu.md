# Авторизация и меню админки

Закрытый кабинет и левое меню `/manager/`. Контракт меню тот же, что на сайтах с новым ядром (реферальный / test). Режим «публички нет» — **только этот проект**.

## Авторизация

Ядро хук не навязывает. Гейт живёт в `project/Hooks/AuthHook.php`, режим — `project/config/app.php` → `auth.gate`.

| `auth.gate` | Поведение |
|-------------|-----------|
| `all` | Все страницы кроме белого списка. **OMGO.** |
| `manager` | Только `/manager/*`. Так сделан test (витрина открыта). |
| `off` | Хук ничего не делает. |

Белый список (`auth.public_paths`): `/login` и JsAPI, без которых не войдёшь (`csrf`, `form.auth`, `auth.logout`). Хуки с приоритетом &lt; 150 бегут **до** API — если не исключить эти пути, логин сломается.

Роли кабинета: `auth.manager_roles` (сейчас `1,2,3,4`). Несовпадение роли → выход и редирект на логин.

Вход: сессия `omgo_auth_uid`, таблица `tblusers`. Пароль — bcrypt или легаси `sha1(sha1(salt)+sha1(pass))` / `generateDbPassword`. Куку магазина `AUTH_KEY` не ставим.

Схема `tblusers` на OMGO уже ядерная (миграции core: nullable login/email/phone, индексы, FK на `t_users`). У upsolut сверх этого есть `pin_hash` — это **проектная** миграция test (`2026_03_17_000000_AddPinHashToTblusers`), PIN кабинета магазина. На OMGO не переносим.

Страница `/login` — виртуальный маршрут `Strategy\LoginStrategy` + `project/phpfiles/login.php` + шаблон `light.php`.

Корень `/` — `Strategy\HomepageStrategy` (как на test по месту в `routes.php`, не витрина). Hierarchical пустой путь не берёт. OMGO отдаёт активную страницу `mapurl = manager` (тот же узел, что `/manager/`). Если узла нет — редирект на `/manager/`.

Другой легаси на этом ядре с публичной частью: в его `app.php` поставить `auth.gate` = `manager` или `off` и свой `AuthHook`. В ядро «все роуты закрыты» не кладём.

## Меню

`light.php` рисует `MenuView::by('admin_left')->render('admin_sidebar_preview')`.

Цепочка:

1. Таблица `t_menu` (схема как в дампе `t_menu.sql` в корне omgo — **только структура**, INSERT магазина не накатывать).
2. Справочник: строка `tblview` с `tablename = t_menu`, поля `varc_name`, `varc_code`, `catlink_pages`.
3. Запись `varc_code = admin_left`, JSON в `catlink_pages`.
4. `MenuBuilder` читает `catlink_*`, резолвит `page_id` в `catalog` (только `status = 1`).
5. Шаблон `project/UC/menu/admin_sidebar_preview.php`.

Формат узла (как на test):

```json
{"id":"g_13","type":"group","title":"Администрирование","parent":null,"order":0}
{"id":"p_4","type":"page","page_id":4,"parent":"g_13","order":0}
```

Группа — заголовок в сайдбаре. Страница — узел каталога. Вложенность через `parent` (страница может быть родителем страницы).

Откуда JSON: не из магазинного дампа, а обход `catalog` с префиксом `/manager` (`Services\AdminMenuCatalogBuilder`).

- Корень `/manager/` — пункт верхнего уровня.
- Прямые дети с потомками — группа по имени раздела + посадочная страница + дерево.
- Листья — пункты без группы.
- Пропуск служебных `mapurl`: `add`, `login`, `auth` (`menu.skip_mapurls`).

Миграция `project/Database/Migrations/2026_09_13_100000_EnsureTMenu.php`: таблица, справочник, запись `admin_left`, первичная сборка если JSON пустой.

Пересобрать после правок каталога:

```
php project/bin/rebuild-admin-menu.php
php project/bin/rebuild-admin-menu.php --dry-run
```

Без `--keep` перезаписывает JSON. Ручную правку в справочнике после этого не сохранит — либо не гонять скрипт, либо править снова.

`MenuBuilder` уже без привязки к магазину: любая `catlink_*` колонка. test со своим заполненным `t_menu` не затрагивается — код и данные в `project/` OMGO.

В группе «Администрирование» две ядерные страницы (не легаси-конструктор):

| Пункт | URL | PHP | Таблица |
|-------|-----|-----|---------|
| Справочники | `/manager/admin/views/` | `manager/views.php` | `tblview` |
| Страницы | `/manager/admin/pages/` | `manager/pages.php` | `catalog` |

Грид — `meta.ui = admin-table` (`project/UC/dynblock/{catalog,tblview}/base`). Старый узел `/manager/constructor/pages/` не трогаем.

Миграция `2026_09_13_120000_EnsureAdminKernelPages` создаёт узлы и вшивает их в `admin_left` без полной пересборки меню.

## История логики

### 2026-09-13 — корень сайта = кабинет
- **Тип:** новая логика
- **Было:** `/` после входа давал 404 (нет стратегии на пустой путь)
- **Стало:** `HomepageStrategy` открывает страницу `manager` в шаблоне `light.php`

### 2026-09-13 — справочники и страницы в админке
- **Тип:** новая логика
- **Было:** в «Администрировании» не было ядерных интерфейсов `tblview` / `catalog`
- **Стало:** пункты Справочники и Страницы, грид ядра `admin-table`
