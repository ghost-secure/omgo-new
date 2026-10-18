# Admin-flow: модалки сценариев

Контракт как на test (`t_neo` / `project/phpfiles/flow`), без магазина. Живёт в **проекте**, не в ядре.

## Открытие

Кнопка в ячейке грида: `data-admin-flow` + `data-flow-id/action/dataid/table/hash`. Плагин `admin-pages` (`light.php`) ловит клик и делает

`GET /jsapi/admin/flow?id=&action=&dataid=&table=&hash=`

Ядро клеит слэш в точку: `/jsapi/admin/flow` → endpoint `admin.flow`.

`AdminFlowHandler`:

1. Пользователь залогинен.
2. `id` в белом списке `project/config/admin_flow.php`.
3. `FlowSignature::verify` — HMAC-SHA256 от `id\0action\0dataid\0table` + `security.app_secret`.
4. `include project/phpfiles/flow/{id}.php` — HTML в `WindowPlugin`.

`table` — таблица **грида**, не обязательно таблица INSERT. Для маршрута: `t_users` + `dataid` = `cat` врача.

## Сохранение

Не универсальный `jsapi/form` (его hash закрывает только `table`+`id` формы). Отдельный POST с той же подписью флоу + CSRF (`public: false`).

Маршрут: `POST /jsapi/admin/analysis-route` (`AnalysisRouteSaveHandler`):

- ещё раз `FlowSignature`;
- `user_doctor` только из `dataid`, не из POST;
- `ROLE_LAB` — отказ (у лаборатории нет столбца «Маршрут»);
- `TableMetadataManager::createActiveRecord('t_analysis_route')` → `save()`;
- `TAnalysisRouteHook::beforeSave` (тип только PTEN, лаба обязательна) и `afterSave` (пока пустой).

После успеха модалка маршрута **не закрывается**: перечитывается список внутри окна и грид врачей (бейдж).

Пул кодов: `POST /jsapi/admin/code-pool` (`CodePoolHandler`). Контекст страницы, не строки: `flow_id=codes`, `action=WF_CODEPULL`, `dataid=0`, `table=t_code`. После успеха модалка закрывается, грид кодов перечитывается. Форма и кнопка — [codes.md](codes.md).

Воркфлоу заявки: id `analysis`, `table=t_analysis`, `dataid` = `cat` строки, `action` = id операции. Форма `flow/analysis.php`, запись `POST /jsapi/admin/analysis-event`. Мгновенные операции модалку не открывают. Меню и статусы — [analysis-workflow.md](analysis-workflow.md).

`Flow::button` с `variant=toolbar` — иконка и подпись, как у кнопок вида. Сторону задаёт слот config: `toolbar_left` слева (кнопки экрана, рядом с «Добавить запись»), `toolbar_extra` справа (рядом с «Фильтры» и «Выгрузить в Excel»). Пул кодов — слева.

## Файлы

| Что | Путь |
|-----|------|
| Подпись | `project/UC/FlowSignature.php` |
| Кнопка | `project/UC/Flow.php` |
| HTML-хендлер | `project/Api/JsApi/AdminFlowHandler.php` |
| Whitelist | `project/config/admin_flow.php` |
| Реестр JsAPI | `project/config/api.php` |
| Сценарии | `project/phpfiles/flow/{id}.php` |

Новый сценарий: файл в `phpfiles/flow/`, строка в `admin_flow.php`, кнопка `Flow::button`, при записи — свой POST-хендлер (если не хватает нативных полей формы).

Легаси-аналог: [legacy-wf.md](legacy-wf.md).

## История логики

### 2026-10-18 — сторона кнопки флоу

- **Тип:** правка неверной логики
- **Было:** `variant=toolbar` описан как правая группа, слот `toolbar_extra`
- **Стало:** вид кнопки задаёт `variant`, сторону — слот. Пул кодов в `toolbar_left`

### 2026-10-15 — сценарий заявки

- **Тип:** новая логика
- **Было:** в белом списке флоу только `route` и `codes`
- **Стало:** сценарий `analysis` и `POST /jsapi/admin/analysis-event`. Поведение меню — [analysis-workflow.md](analysis-workflow.md)

### 2026-10-11 — пул кодов и кнопка тулбара

- **Тип:** новая логика
- **Было:** один сценарий `route`; `Flow::button` — иконка или подпись `btn-sm`
- **Стало:** сценарий `codes` (`POST /jsapi/admin/code-pool`); `variant=toolbar` для кнопки в тулбаре грида. Подробно [codes.md](codes.md)
