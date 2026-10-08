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

После успеха модалка **не закрывается**: перечитывается список внутри окна и грид врачей (бейдж).

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
