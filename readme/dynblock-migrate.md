# Сборка интерфейсов: легаси Dyndata → DynBlock

Путь php **не меняем**: как в `catalog.php` (часто `gen/t_*.php`) → тот же файл в `project/phpfiles/`. Так проще сверять URL с легаси.

Эталон легаси: `omgo-lu/project/phpfiles/…`. Референс папки base: `test/project/UC/dynblock/t_action_detail/base` (смысл: авто-поля справочника). Монолит `template.php` с test не копируем — у нас `meta.ui = admin-table`.

## Как классифицировать

Смотрим массив `$params` у `$this->Dyndata` (обычно строки около `join` / `addqueryselect` / `filter` / `blockid`).

| | Простой | Сложный |
|--|---------|---------|
| `join`, `addqueryselect` | `NULL` или пустая строка | SQL, GROUP_CONCAT, лишние JOIN |
| `filter` | `'1'` (все строки) | роль, профиль, чужой алиас (`d.`, `v.`) |
| `blockid` | одно число | ветки по ролям (38 / 45 = `tbldynelements.id`) |
| прочий PHP | Instant/обёртка — не делает сложным | несколько шаблонов, смена таблицы |

`blockid` легаси в новый `base` не переносим.

## Простой

1. Перенести файл по тому же пути, что `catalog.php`.
2. Вместо `Dyndata` — `new \UC\DynBlock('<table>', '<table>/base', 20, 1, ['short' => true])`.
3. Папка `project/UC/dynblock/<table>/base/`: `config.php` с `meta.ui = admin-table` **без** `columns`; stub `template.php` (`return;`).
4. Столбцы ядро берёт из `tblfields` (`AdminTableView`, пустой `columns`).

Пример: `/manager/klassifikatory/mant_chem/` → `gen/t_chem.php` → `t_chem/base`. Instant «быстрая запись» не переносили: создание в тулбаре `admin-table`.

Весь `omgo-lu/project/phpfiles/gen/` (50 файлов) разобран:

- **48 простых** — php = вызов `base`, папка `UC/dynblock/<table>/base`.
- **2 сложных** — `t_analysis.php` ещё легаси-заглушка. `t_pacient.php` — грид всех заявок `t_analysis/list` (роли позже).

## Сложный

1. Скопировать легаси-php как есть.
2. Сверху комментарий: `СЛОЖНЫЙ`, что кастомное (select / фильтр / blockid).
3. Один такой интерфейс — отдельная итерация чата.

Очередь сложных из `gen/`: `t_analysis.php` (GROUP_CONCAT + фильтр/blockid по роли). `t_pacient.php` уже показывает все заявки гридом `t_analysis/list` (копия столбцов паспорта врача). Роли, черновик, отчёты и кнопка «Добавить пациента» ещё не перенесены.

Снаружи `gen/`: врачи — `web/doctors.php` → `t_users/doctors` (53) или `t_users/doctors_lab` (79, без маршрута), см. [doctors.md](doctors.md). Паспорт врача — `web/doctor-passport.php`: карточка `t_users/doctor-card` (58) и список `t_analysis/doctor-patients` (54, фильтр `user_doctor`), см. [legacy-routes.md](legacy-routes.md). Разные `blockid` легаси → разные папки DynBlock, не одно условие в config. Список маршрутов в модалке: `t_analysis_route/route-list` (легаси alias `route-list`).

Пользователи: каталог `/manager/admin/users/` → `project/phpfiles/manager/users.php` → DynBlock `t_users/base`. Конфиг сгенерирован (`POST /jsapi/view/interface`, таблица `t_users`). В гриде видны поля профиля, которые включены в `tblfields`, плюс учётные из `tblusers` (логин, email, телефон, группа, статус аккаунта, даты). Скрыты: `view_region` и `view_status` (`tblfields.disabled = 1`), `passwd` / `salt` / `hash`. Колонка `address_dadata` показывает чистый адрес (`value` из JSON), не сам JSON. Шаблоны врачей `doctors` и `doctors_lab` генератор не трогает.

## История логики

### 2026-10-11 — фильтры активации на списке кодов

- **Тип:** новая логика
- **Было:** у `t_code/base` только фильтры полей справочника
- **Стало:** два чекбокса свободных и активированных кодов. См. [codes.md](codes.md)

### 2026-10-11 — список кодов: правки нет, ФИО в двух колонках

- **Тип:** новая логика
- **Было:** `t_code/base` без `columns`, ячейки автора и активатора — число
- **Стало:** столбцы из `tblfields` собираются в config, у `author_add` и `user_activate` форматтер ФИО; удаление только неактивированных. См. [codes.md](codes.md)

### 2026-10-11 — кнопка пула на списке кодов

- **Тип:** новая логика
- **Было:** `gen/t_code.php` — простой `t_code/base` без кнопок сценария
- **Стало:** тот же `base`, в тулбаре кнопка «Создать пул кодов» (`toolbar_extra`). Сценарий — [codes.md](codes.md)

### 2026-10-11 — список всех анализов

- **Тип:** новая логика
- **Было:** `gen/t_pacient.php` сразу `return`, легаси ниже не выполнялся
- **Стало:** DynBlock `t_analysis/list`, все строки, столбцы как у `t_analysis/doctor-patients`. Фильтра по роли нет

### 2026-10-10 — паспорт врача

- **Тип:** новая логика
- **Было:** `web/doctor-passport.php` в каталоге, файла и DynBlock не было
- **Стало:** `t_users/doctor-card` и `t_analysis/doctor-patients`, см. [legacy-routes.md](legacy-routes.md)

### 2026-10-08 — адрес в списке пользователей

- **Тип:** новая логика
- **Было:** ячейка `address_dadata` печатала JSON DaData
- **Стало:** в гриде чистая строка `value`

### 2026-10-08 — пользователи `/manager/admin/users/`

- **Тип:** новая логика
- **Было:** в каталоге php `manager/users.php`, файла не было
- **Стало:** `t_users/base` (генератор) + страница как у референса; скрыты отключённые в `tblfields` регион и статус профиля

### 2026-10-07 — врачи (первый сложный вне gen/)

- **Тип:** новая логика
- **Было:** `web/doctors.php` не было в omgo
- **Стало:** DynBlock `t_users/doctors`, запрос как в легаси 53 (EXISTS + addSelect)

### 2026-10-07 — два шаблона врачей по роли

- **Тип:** новая логика
- **Было:** один `t_users/doctors`
- **Стало:** `ROLE_LAB` → `t_users/doctors_lab` (легаси 79, без «Маршрут»)

### 2026-10-08 — список маршрутов в модалке

- **Тип:** новая логика
- **Было:** alias `route-list` только в легаси `Lu::Block`
- **Стало:** `UC/dynblock/t_analysis_route/route-list`, фильтр `user_doctor` из флоу

### 2026-10-04 — весь gen/: 48 простых + 2 сложных

- **Тип:** новая логика
- **Было:** только `t_chem` собран; остальные `gen/*` ещё в легаси
- **Стало:** все простые `gen/*.php` зовут свой `base`; `t_analysis` и `t_pacient` отложены с пометкой

### 2026-10-04 — правило простой / сложный + t_chem

- **Тип:** новая логика
- **Было:** страницы `gen/*.php` смотрели на легаси `Dyndata`, файлов в `omgo/project` не было
- **Стало:** классификация по `$params`; простой = `UC/dynblock/<table>/base`; первый — `t_chem`
