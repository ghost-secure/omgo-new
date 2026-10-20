# Интерфейс «Врачи»

URL: [http://omgo/manager/doctors/](http://omgo/manager/doctors/). Заголовок грида — «Врачи» (`meta.title`). Имя справочника `t_users` («1. Пользователи») на эту страницу не попадает: та же таблица у списка пользователей.  
`catalog.php` = `web/doctors.php` (как в легаси). Два шаблона DynBlock, как два `blockid` легаси — не прячем столбец условием в одном config:

| Роль | Легаси `tbldynelements` | Папка |
|------|-------------------------|-------|
| не лаборатория | 53 | `project/UC/dynblock/t_users/doctors` |
| `ROLE_LAB` | 79 | `project/UC/dynblock/t_users/doctors_lab` (нет столбца «Маршрут») |

Легаси: `omgo-lu/project/phpfiles/web/doctors.php`. Строки 79 в дампе нет; набор столбцов 79 = 53 без маршрута (как сказал человек).

## Запрос

Таблица `t_users`. Кто врач — не JOIN `t_access_matrix` (в ядре нет `GROUP BY` как в легаси), а `staticWhere` + `EXISTS` по `view_role = RoleService::ROLE_DOCTOR`.

Агрегаты в `addSelect` (легаси `addqueryselect`):

| Алиас | SQL |
|-------|-----|
| `custom_templateparam_tests` | `COUNT` заявок `t_analysis` по `user_doctor` (оба шаблона) |
| `custom_templateparam_route` | `COUNT` маршрутов `t_analysis_route` — только шаблон 53 (`doctors`) |

Фильтр лаборатории (`auth()->isRole(RoleService::ROLE_LAB)`): только врачи своей лабы (`t_users.view_lab`) **или** те, у кого есть заявка в этой лабе. Исключение — `t_lab.stat_alldoctors = 1` (`AnalysisService::labSeesAllDoctors`). Колонки в дампе может не быть.

Профиль: `auth()->getUser()['profile']` (`t_users`, в т.ч. `view_lab`). `AuthService` отдаёт `UserAccount::toArray()` — иначе `isRole` / профиль пустые.

Тулбар создания нет (`meta.actions.create = false`), как легаси `toolbardisable.add`.

## Столбцы

Оба: E-Mail, телефон (`tblusers` ядро JOIN-ит на `t_users`), ФИО, регион, число пациентов, лаборатория, медучреждение, ссылка «пациенты».  
Только 53 / `doctors`: маршрут.

`[=view_region=]` и т.п. — тип `view_`, ядро подставляет `*_name`.  
`[=user=email=]` / `[=user=phone=]` — поля `email` / `phone` после JOIN `tblusers`.

Число пациентов: `0` не показываем (`{if … ?=0?= …}`). Ссылка «пациенты» только если счётчик > 0 → `/manager/doctor/{cat}/` (легаси `{=iconlink … /[71][N_ID]/}`). Паспорт: [legacy-routes.md](legacy-routes.md).

Столбец «Маршрут» (только `doctors`): кнопка **Маршрут** + бейдж `COUNT` из `custom_templateparam_route` (0 — без бейджа). Клик — admin-flow `route` (контекст `table=t_users`, `dataid=cat` врача). Форма: тип PTEN, лаборатория; сохранение в `t_analysis_route`; ниже список `t_analysis_route/route-list` (легаси `Lu::Block` alias `route-list`). После сохранения модалка остаётся, список и бейдж в гриде обновляются. Лаборатория столбец не видит. См. [admin-flow.md](admin-flow.md), [legacy-wf.md](legacy-wf.md).

`custompatterns.php` / `pacientdata` на этой странице не используются.

## История логики

### 2026-10-20 — заголовок «Врачи»

- **Тип:** правка неверной логики
- **Было:** грид показывал имя справочника `t_users`: «1. Пользователи». `meta.title` ядро не брало
- **Стало:** заголовок «Врачи» из `meta.title` шаблонов `doctors` и `doctors_lab`

### 2026-10-07 — врачи на DynBlock

- **Тип:** новая логика
- **Было:** легаси `Dyndata` blockid 53/79, `Object\Role` / `Object\Analysis`
- **Стало:** `UC\DynBlock` `t_users/doctors`, `RoleService` / `AnalysisService`, фильтр лабы через `auth()->isRole`

### 2026-10-07 — отдельный шаблон лаборатории

- **Тип:** новая логика
- **Было:** один `t_users/doctors` на все роли (blockid 79 в дампе не нашли)
- **Стало:** `ROLE_LAB` → `t_users/doctors_lab` без столбца «Маршрут», как легаси 79; не `visible`/условие в общем config

### 2026-10-10 — ссылка «пациенты» открывает паспорт

- **Тип:** новая логика
- **Было:** `/manager/doctor/{cat}/` в гриде есть, страница не собрана
- **Стало:** паспорт врача, см. [legacy-routes.md](legacy-routes.md)

### 2026-10-08 — кнопка маршрута и флоу

- **Тип:** новая логика
- **Было:** в ячейке 53 только число маршрутов, модалки не было
- **Стало:** `Flow::button` «Маршрут» + бейдж; модалка `flow/route.php`; INSERT через hook + ActiveRecord; список `route-list` под формой
