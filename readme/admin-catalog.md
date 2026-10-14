# Админка: страницы и форма catalog

Как запускаются грид «Страницы» и форма правки. Справочники (`tblview`) — тот же контур, другая таблица.

## Грид `/manager/admin/pages/`

1. Hierarchical: `manager` (id 2) → `admin` (13) → `pages` (83). PHP: `project/phpfiles/manager/pages.php`.
2. `new DynBlock('catalog', 'catalog/base', …)` читает `project/UC/dynblock/catalog/base/config.php` (`meta.ui = admin-table`).
3. Метаданные полей — `TableMetadataManager`: `catalog` системная → `PageActiveRecord::getTableMetadata()`, не `tblfields` (на OMGO справочника `catalog` нет).
4. SELECT: `catalog.*` плюс JOIN `pattern_name`, `id_up_name`.
5. Layout `core/UC/DynBlock/ui/layouts/admin-table.php`. Нет `cell_formatter` — `AdminTableView::renderDefaultCell()`.

«Перегенерировать интерфейс» пишет formatters в тот же `config.php`; без кнопки грид уже показывает значения.

## Грид `/manager/admin/views/`

1. Hierarchical: `manager` → `admin` → `views`. PHP: `project/phpfiles/manager/views.php`.
2. DynBlock `tblview/base`, `meta.ui = admin-table`.
3. В действиях строки — те же кнопки, что на test: поля, данные, генерация интерфейса, клон. Разметка: `core/UC/DynBlock/ui/components/admin-table/row-actions.php` при `$tableName === 'tblview'`. Клики обрабатывает `admin-pages` (`POST /jsapi/view/fields` и соседние action).

## Форма `/manager/add/?table=catalog&hash=…&id=…`

1. `FormRouteStrategy` (prio 750), класс **`core/Core/Router/FormRouteStrategy.php`**.
2. Виртуальная страница: `php = system/form.php`, шаблон `light.php`.
3. `core/phpfiles/system/form.php` → `UC\Form` → поля из тех же системных метаданных.
4. Сохранение: `POST /jsapi/form` (плагин `form-handler`).

Запасной узел каталога id=5 (`/manager/add/`, `php = system/form.php`) нужен, только если стратегия не зарегистрировалась. Легаси `core-add.php` на этом сайте нет.

INSERT новой страницы передаёт только поля формы. В легаси-дампе `secondaryview`, `navig` и `topid` — `NOT NULL` без DEFAULT, MySQL отвечает 1364. После наката дампа это снимает `php core/legacy/run.php` (скрипт `02`, см. `core/readme/legacy-dump.md`). `url` делает ядерная миграция `UpdateCatalogUrlNullable`.

## История логики

### 2026-10-04 — кнопки настройки полей в Справочниках

- **Тип:** правка неверной логики
- **Было:** на `/manager/admin/views/` только правка/удаление строки
- **Стало:** кнопки как на test (поля, данные, генерация, клон) через ядерный `row-actions`

### 2026-10-04 — пустой грид и пустая форма catalog

- **Тип:** правка неверной логики
- **Было:** ячейки грида всегда «—» (stub config без formatter). Форма: стратегия не грузилась, hierarchical открывал `/manager/add/` с отсутствующим `core-add.php`.
- **Стало:** дефолтные ячейки; стратегия в PSR-4 + `system/form.php` / `light.php`; у узла `add` php = `system/form.php`.
