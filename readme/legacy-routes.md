# Легаси-адреса (`logic/index.php`)

Эталон: `omgo-lu/project/logic/index.php`. Адрес сверяется с шаблоном. Совпало — открывается страница каталога, кусок URL уходит параметром маршрута (`RouteMatch` attributes). Иерархия каталога эти хвосты не резолвит: `1171` и GUID не являются `mapurl`.

Стратегия: `Strategy\LegacyUrlStrategy` (приоритет 250, после `HomepageStrategy` 300). Корень `/` — отдельно в `HomepageStrategy`.

## Вход в кабинет

Публичной витрины нет. После входа:

| Адрес | Куда |
|-------|------|
| `/` | 302 на `/manager/mant_pacient/` |
| `/manager/` | 302 на `/manager/mant_pacient/` |

Список пациентов — страница `Helpers\Page::PACIENT_LIST`, `mapurl = mant_pacient`, php `gen/t_pacient.php`. Гость до стратегии не доходит: `AuthHook` (`auth.gate=all`) уводит на `/login`.

Номера страниц (`catalog.id`) лежат в `Helpers\Page`, не в стратегиях. `Core\Page\Page` — объект маршрута, не этот список.

## Паспорт врача

`/manager/doctor/{cat}/` — числовой `t_users.cat` (в легаси переменная называлась `doctor_guid`).

Страница `Helpers\Page::DOCTOR`, `mapurl = doctor`, php `web/doctor-passport.php`, шаблон `light.php`. `catalog.status = 0`: в меню пункт не попадает, голый `/manager/doctor/` иерархия не открывает. Стратегия отдаёт страницу только если id есть в `t_users` и это `Page::DOCTOR`. Параметр: `doctor_id`.

Страница — два DynBlock:

| Блок | Легаси | Шаблон | Фильтр |
|------|--------|--------|--------|
| Карточка | `tbldynelements` 58 | `t_users/doctor-card` (свой `template.php`, без `meta.ui`) | `t_users.cat = doctor_id` |
| Список пациентов | `tbldynelements` 54 | `t_analysis/doctor-patients` (`admin-table`) | `t_analysis.user_doctor = doctor_id` |

Карточка не на `admin-table`: на странице один грид. Два `admin-table` делят `id="filters-form"`, и фильтр списка читал бы форму карточки. В карточке ФИО, лаборатория, медучреждение, регион.

Список: столбцы шапки блока 54 (код, дубль в другой лаборатории, ФИО со ссылкой на `/manager/passport/{varc_guid}/`, даты, диагноз, регионы, анализ, статусы, просрочка `cnt_late`). Создание, правка и удаление строк выключены. Панель фильтров по всем полям `t_analysis` выключена (`filter => false`, в том числе у скрытых колонок): иначе селекты справочников раздувают страницу. Запрос столбцов общий с основным списком: `AnalysisService::analysisGridQuery()`. На паспорте к нему добавляется `user_doctor`. Кнопки маршрута заявки и раскраска строки из легаси на этой странице нет.

Тот же грид без фильтра по врачу — `/manager/mant_pacient/`, шаблон `t_analysis/list`. Роли туда ещё не внесены.

Ссылка «пациенты» в гриде врачей ведёт сюда. См. [doctors.md](doctors.md).

## Намечено: GUID анализа

`match()` эти адреса не берёт. GUID — `t_analysis.varc_guid`. Когда появятся php-файлы, тот же приём: три сегмента (или два у PDF), страница каталога, параметр в attributes.

| URL | Страница | php | Параметр | Кто заполняет |
|-----|----------|-----|----------|----------------|
| `/manager/pacient/{guid}/` | `Page::PACIENT` | `form/pacient.php` | `analysis_guid` | врач, карточка пациента |
| `/manager/analysis/{guid}/` | `Page::ANALYSIS` | `form/analysis.php` | `analysis_guid` | лаборатория, форма анализа |
| `/manager/passport/{guid}/` | `Page::PASSPORT` | `web/passport.php` | `passport_guid` | паспорт анализа |
| `/pdf/{guid}/` | `Page::PDF` | `form/pdf.php` | `analysis_guid` | PDF |

Эти страницы в каталоге со `status = 0`. Карта в коде: `LegacyUrlStrategy::plannedGuidRoutes()`.

## История логики

### 2026-10-14 — номера страниц в Helpers\Page

- **Тип:** новая логика
- **Было:** в стратегии GUID лежали числа `catalog.id`
- **Стало:** константы `Helpers\Page`; в карте маршрутов ключ `page`

### 2026-10-11 — тот же грид на списке пациентов

- **Тип:** новая логика
- **Было:** `/manager/mant_pacient/` не показывал заявки (`gen/t_pacient.php` выходил сразу)
- **Стало:** `t_analysis/list`, все строки, тот же `analysisGridQuery()`, что у паспорта врача

### 2026-10-10 — вход в кабинет и паспорт врача

- **Тип:** правка неверной логики
- **Было:** `/` открывал страницу `manager` (`manager/dash.php`). `/manager/doctor/{id}/` не собирался
- **Стало:** `/` и `/manager/` — 302 на `/manager/mant_pacient/`. Паспорт врача — два DynBlock, id в `doctor_id`. GUID-страницы только в карте `plannedGuidRoutes()`
