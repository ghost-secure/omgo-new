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
- **2 сложных** — скопированы как есть с комментарием `СЛОЖНЫЙ`: `t_analysis.php`, `t_pacient.php`.

## Сложный

1. Скопировать легаси-php как есть.
2. Сверху комментарий: `СЛОЖНЫЙ`, что кастомное (select / фильтр / blockid).
3. Один такой интерфейс — отдельная итерация чата.

Очередь сложных из `gen/`: `t_analysis.php` (GROUP_CONCAT + фильтр/blockid по роли), `t_pacient.php` (join, select, несколько ролей и blockid).

Снаружи `gen/`: врачи — `web/doctors.php` → `t_users/doctors` (53) или `t_users/doctors_lab` (79, без маршрута), см. [doctors.md](doctors.md). Разные `blockid` легаси → разные папки DynBlock, не одно условие в config. Список маршрутов в модалке: `t_analysis_route/route-list` (легаси alias `route-list`).

## История логики

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
