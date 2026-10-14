# Авторизация и меню админки

Закрытый кабинет и левое меню `/manager/`. Контракт меню тот же, что на сайтах с новым ядром (реферальный / test). Режим «публички нет» — **только этот проект**.

## Авторизация

Ядро хук не навязывает. Гейт живёт в `project/Hooks/AuthHook.php`, режим — `project/config/app.php` → `auth.gate`.

| `auth.gate` | Поведение |
|-------------|-----------|
| `all` | Все страницы кроме белого списка. **OMGO.** |
| `manager` | Только `/manager/*`. Так сделан test (витрина открыта). |
| `off` | Хук ничего не делает. |

Белый список (`auth.public_paths`): `/login`, `/auth`, `/reg`, `/recover` и JsAPI, без которых не войдёшь (`csrf`, `form.auth`, `form.reg`, `form.recover`, `auth.logout`). Хуки с приоритетом &lt; 150 бегут **до** API — если не исключить эти пути, логин сломается.

Роли кабинета: `auth.manager_roles` (сейчас `1,2,3,4`). Несовпадение роли → выход и редирект на логин.

Вход: сессия `omgo_auth_uid`, таблица `tblusers`. После логина `AuthService` отдаёт `UserAccount::toArray()`: `profile` (`t_users`) и `roles_map` (`t_access_matrix`). Нужно для `auth()->isRole(RoleService::ROLE_LAB)` и `profile.view_lab`. Пароль — bcrypt или легаси `sha1(sha1(salt)+sha1(pass))` / `generateDbPassword`. Куку магазина `AUTH_KEY` не ставим.

Кнопка «Настройки» в шапке — ядерный правый сайдбар (роль, ФИО, файлы страницы). OMGO добавляет вход под пользователем: [admin-settings.md](admin-settings.md). Пока идёт имперсонализация, гейт не сверяет `tblusers.role` с `auth.manager_roles` — иначе врач/спонсор из списка сразу вылетели бы на логин.

Схема `tblusers` на OMGO уже ядерная (миграции core: nullable login/email/phone, индексы, FK на `t_users`). У upsolut сверх этого есть `pin_hash` — это **проектная** миграция test (`2026_03_17_000000_AddPinHashToTblusers`), PIN кабинета магазина. На OMGO не переносим.

Страницы входа не используют шаблон кабинета `light.php`.

| URL | Форма | JsAPI |
|-----|--------|--------|
| `/login` (и старый `/auth`) | вход | `POST /jsapi/form/auth` |
| `/reg` | регистрация врача | `POST /jsapi/form/reg` |
| `/recover` | новый пароль | `POST /jsapi/form/recover` |

Общий шаблон: `project/templates/auth.php` (фон, панель справа, без меню и шапки). Контент: `project/phpfiles/auth/forms.php`. Кнопки в шапке панели ведут на эти три адреса. Картинка: `/project/overrides/assets/light_bg.jpg`.

Вход ищет `tblusers` по логину, e-mail и телефону. Телефон из 10 или 11 цифр сверяется и как `7…`, и как `8…`. Успех в `AuthService::login` пишет строку журнала `view_type = 4` (`author_add`, `created_add`) и уходит на `redirect` (только путь с `/`, иначе `/manager/`). Колонки и правила строки — [moderator-log.md](../../core/readme/moderator-log.md). Гейт `AuthHook` эту строку не пишет.

Регистрация повторяет легаси `reg_.php`: обязательны фамилия, имя, отчество, телефон, e-mail, медучреждение, адрес. Скрытое поле `email` — ловушка, при заполнении заявка отклоняется. Телефон хранится 11 цифрами с `7`. Занятые e-mail и телефон не принимаются. Адрес берётся из кэша `t_dadata` или из DaData (`DADATA_TOKEN`); без региона заявка не создаётся. Профиль `t_users` создаётся первым (`view_status = 1`, модерация), затем `tblusers` с тем же id, `status = 0`, `role = 3`, роль врача в `t_access_matrix`. Пароль гостю не выдаётся. Письмо администраторам — тип `t_notify_type` 10. Пока `status = 0`, вход закрыт.

Восстановление (`recover_.php`): e-mail или логин, новый пароль `1111-9999`-вида, хэш как у легаси, письмо тип 8. Вместе с паролем `status` становится `1` — так `AddUser` в легаси включает учётку. Если письмо не ушло, пароль и статус не меняются.

Токен сессии и список доменов легаси заменяет CSRF ядра (`public: false` у этих трёх эндпоинтов). Пути форм и `/jsapi/csrf` есть в `auth.public_paths`.

Почта: `Services\MailService`. Вне `production` без настройки SMTP письма пишутся в `runtime/logs/mail.log` (`integration.mail.testmode`). На бою: `MAIL_TESTMODE=0` и SMTP (`MAIL_IS_SMTP`, `MAIL_SMTP_*`) либо `mail()`. Push легаси не отправляем.

Корень `/` — `Strategy\HomepageStrategy`. Публички нет: и `/`, и `/manager/` отвечают 302 на список пациентов `/manager/mant_pacient/`. Разбор хвостов URL (паспорт врача и будущие GUID) — [legacy-routes.md](legacy-routes.md).

Другой легаси на этом ядре с публичной частью: в его `app.php` поставить `auth.gate` = `manager` или `off` и свой `AuthHook`. В ядро «все роуты закрыты» не кладём.

## Меню

`light.php` рисует `MenuView::by('admin_left')->render('admin_sidebar_preview')`. Лого в ячейке `.brand` — `project/UC/layout/logo.php` (если файла нет — `CRUSO ADMIN`). Светлая тема: чёрный сайдбар, активный пункт `#ff7f22` — [ui-theme.md](ui-theme.md).

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

Группа в JSON — узел без своей страницы: дети рисуются пунктами верхнего уровня. Заголовок `.menu-label` шаблон OMGO не выводит. Редактор на `/manager/admin/menu/` группы по-прежнему умеет. Страница — узел каталога. Вложенность через `parent` (страница может быть родителем страницы).

Откуда JSON: не из магазинного дампа, а обход `catalog` с префиксом `/manager` (`Services\AdminMenuCatalogBuilder`).

- Корень `/manager/` — пункт верхнего уровня.
- Прямые дети с потомками — группа по имени раздела + посадочная страница + дерево.
- Листья — пункты без группы.
- Пропуск служебных `mapurl`: `add`, `login`, `auth`, `reg`, `recover` (`menu.skip_mapurls`).

Миграция `project/Database/Migrations/2026_09_13_100000_EnsureTMenu.php`: таблица, справочник, запись `admin_left`, первичная сборка если JSON пустой.

Пересобрать после правок каталога:

```
php project/bin/rebuild-admin-menu.php
php project/bin/rebuild-admin-menu.php --dry-run
```

Без `--keep` перезаписывает JSON. Ручную правку в справочнике после этого не сохранит — либо не гонять скрипт, либо править снова.

`MenuBuilder` уже без привязки к магазину: любая `catlink_*` колонка. test со своим заполненным `t_menu` не затрагивается — код и данные в `project/` OMGO.

## Иконки пунктов меню

Ключ в JSON часто приходит полным адресом Remix Icon, например `https://cdn.jsdelivr.net/npm/remixicon@4.9.1/icons/User%20%26%20Faces/user-line.svg`. `MenuBuilder` сжимает его до `User%20%26%20Faces/user-line`. Префикс CDN один: `Services\RemixIconCache::CDN_PREFIX` (remixicon 4.9.1).

При сборке меню (`MenuBuilder::getMenuData`) и при выводе шаблона `RemixIconCache` кладёт SVG в `runtime/cache/icons/remixicon/4.9.1/` и вставляет разметку в пункт и в стрелку раскрытия. Повторный заход читает файл и на CDN не ходит. У пункта и у стрелки нет `data-icon`, поэтому эти иконки не попадают в `localStorage`.

Каталог `runtime/` в git не входит. Веб-пользователь должен иметь право писать в `runtime/cache`. Первый заход в кабинет нужен, пока jsDelivr ещё отвечает: иначе файла не будет. Уже скачанные SVG работают без CDN.

Плагин ядра `core/assets/plugins/icon-system/plugin.js` для меню не используется. Шапка, таблицы и формы по-прежнему ставят `data-icon`: плагин качает их с jsDelivr и пишет в `localStorage` с префиксом `icon-cache-v1-`.

### Как перенести на сайт, где меню ещё кэширует иконки в localStorage

Ядро и `icon-system` можно не менять. У того сайта свой `project/`:

1. Скопировать `project/Services/RemixIconCache.php`. Префикс в классе должен совпасть с тем, каким `MenuBuilder` срезает URL иконки (сейчас remixicon 4.9.1).
2. В `MenuBuilder::getMenuData` после сборки дерева вызвать `RemixIconCache::warmTree($nodes)`.
3. В своём `project/UC/menu/admin_sidebar_preview.php` вместо `<span class="nav-icon" data-icon="...">` и `<span class="chevron" data-icon="Arrows/arrow-down-s-line">` вставить `RemixIconCache::html($key)`. Пустой ключ пункта — `System/menu-line`.
4. Проверить запись в `runtime/cache/icons/remixicon/4.9.1/`.
5. Открыть кабинет один раз, пока jsDelivr отвечает. Дальше меню берёт файлы с диска.
6. Плагин `icon-system` и ключи `icon-cache-v1-` оставить: они обслуживают остальные `data-icon` страницы. Меню в них больше не пишет. Снимать плагин стоит только когда все такие иконки кабинета переведены на тот же файловый кэш.

В группе «Администрирование» две ядерные страницы (не легаси-конструктор):

| Пункт | URL | PHP | Таблица |
|-------|-----|-----|---------|
| Справочники | `/manager/admin/views/` | `manager/views.php` | `tblview` |
| Страницы | `/manager/admin/pages/` | `manager/pages.php` | `catalog` |
| Меню | `/manager/admin/menu/` | `manager/menu.php` | `t_menu` |

Грид — `meta.ui = admin-table` (`project/UC/dynblock/{catalog,tblview}/base`). Старый узел `/manager/constructor/pages/` не трогаем. Сценарий запуска грида и формы: [admin-catalog.md](admin-catalog.md).

Миграция `2026_09_13_120000_EnsureAdminKernelPages` создаёт узлы Справочники и Страницы и вшивает их в `admin_left` без полной пересборки меню.

«Меню» — грид `t_menu/base` (`meta.ui = admin-table`, столбцы из `tblfields`). В форме поле `catlink_pages` — редактор дерева страниц. Миграция `2026_10_11_130000_EnsureAdminMenuPage` создаёт узел `/manager/admin/menu/` и пункт под `p_13` (порядок 2), JSON целиком не пересобирает.

В JSON узла страницы имени нет, только `page_id`. Редактор сначала рисует `#id`, затем одним запросом `GET /jsapi/view/catalogsearch?ids=…` догружает `catalog.name`. Для списка id ядро отдаёт все запрошенные строки, потолок 500. Текстовый поиск в том же методе по-прежнему не больше 50. У `admin_left` 67 страниц — названия приходят все.

## История логики

### 2026-10-11 — подписи всех страниц в редакторе меню

- **Тип:** правка неверной логики
- **Было:** `catalogsearch` по `ids` резался до 50 строк; у `admin_left` (67 страниц) хвост по алфавиту оставался голым `#id`
- **Стало:** запрос по списку id возвращает все запрошенные страницы, потолок 500. Поиск по строке по-прежнему до 50

### 2026-10-11 — иконки меню в runtime/cache

- **Тип:** новая логика
- **Было:** шаблон ставил `data-icon`, плагин `icon-system` качал SVG с jsDelivr и хранил в `localStorage`
- **Стало:** `RemixIconCache` пишет файл в `runtime/cache/icons/remixicon/4.9.1/` и вставляет SVG в HTML пункта и стрелки. Плагин ядра для остальных `data-icon` кабинета остаётся

### 2026-10-11 — группы без заголовка в сайдбаре

- **Тип:** новая логика
- **Было:** узел `type: group` рисовал `<div class="menu-label">` перед своими страницами
- **Стало:** `project/UC/menu/admin_sidebar_preview.php` заголовок не печатает, детей выводит как пункты верхнего уровня. Чужой проектный шаблон меню это не затрагивает

### 2026-10-11 — страница управления меню

- **Тип:** новая логика
- **Было:** `t_menu` собирался скриптом `rebuild-admin-menu.php`, отдельной страницы в кабинете не было
- **Стало:** `/manager/admin/menu/` — DynBlock `t_menu/base`, пункт «Меню» в группе «Администрирование»

### 2026-10-10 — вход в журнал новыми колонками

- **Тип:** правка неверной логики
- **Было:** `AuthFormsService` вставлял `author_0`, `created_1`, `view_8`, `cnt_4 = 0`
- **Стало:** одна запись из `AuthService::login` через `ModeratorLog::onAuth`: `author_add`, `created_add`, `view_type = 4`

### 2026-10-08 — отдельный шаблон входа

- **Тип:** новая логика
- **Было:** `/login` рисовал форму внутри `light.php` (меню и шапка); регистрации и восстановления не было
- **Стало:** `templates/auth.php` для `/login`, `/reg`, `/recover`; обработчики `form.auth`, `form.reg`, `form.recover`

### 2026-10-08 — настройки и имперсонализация

- **Тип:** новая логика
- **Было:** кнопка Настройки пустая; гейт всегда сверял `tblusers.role` с `manager_roles`
- **Стало:** панель Настроек (ядро + список входа); при impersonate гейт не выгоняет чужую группу доступа

### 2026-10-08 — логотип в слоте UC

- **Тип:** правка неверной логики
- **Было:** файл `phpfiles/manager/logo.php`
- **Стало:** `project/UC/layout/logo.php`

### 2026-10-08 — логотип и цвет меню

- **Тип:** новая логика
- **Было:** в `.brand` текст `CRUSO ADMIN`; сайдбар серый, primary синий ядра
- **Стало:** `phpfiles/manager/logo.php`; светлая тема — чёрный сайдбар и `#ff7f22`, см. [ui-theme.md](ui-theme.md)

### 2026-10-07 — profile и roles_map в сессии

- **Тип:** новая логика
- **Было:** `getUser()` — сырая строка `tblusers`, `isRole` всегда false
- **Стало:** как на test, через `UserAccount::toArray()`

### 2026-10-10 — корень и /manager/ на список пациентов
- **Тип:** правка неверной логики
- **Было:** `/` открывал страницу `manager` (`manager/dash.php`)
- **Стало:** `/` и `/manager/` — 302 на `/manager/mant_pacient/`, как легаси `PAGE_PACIENTLIST`

### 2026-09-13 — корень сайта = кабинет
- **Тип:** новая логика
- **Было:** `/` после входа давал 404 (нет стратегии на пустой путь)
- **Стало:** `HomepageStrategy` открывает страницу `manager` в шаблоне `light.php`

### 2026-09-13 — справочники и страницы в админке
- **Тип:** новая логика
- **Было:** в «Администрировании» не было ядерных интерфейсов `tblview` / `catalog`
- **Стало:** пункты Справочники и Страницы, грид ядра `admin-table`
