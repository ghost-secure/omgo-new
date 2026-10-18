# Легаси: воркфлоу (WF) и `action.php`

Эталон: `omgo-lu`. Нужно, чтобы при переносе не путать старый попап с новым admin-flow.

## Как открывалась модалка

В гриде врачей (`tbldynelements` 53) в столбце «Маршрут» стоял токен `{{ route }}`. Ядро легаси (`octopus.php` / `replace.php`) подставляло кнопку: число маршрутов + клик открывал `project/phpfiles/wf/route.php`.

Параметры URL сценария:

| Параметр | Смысл |
|----------|--------|
| `action` | код сценария, для маршрута `WF_ROUTE` |
| `dataid` | контекстный id строки грида (`t_users.cat` врача) |
| `h` | `md5('action='.$action.'&dataid='.$dataid.$securesalt.$uid)` |

Подпись завязана на секрет и uid. Без неё файл логирует `#wronghash` и обрывается.

## Что внутри `wf/route.php`

Форма POST на ядерный **`/core/php/action.php`**:

- `table = t_analysis_route` — action.php сохраняет поля по нативной логике префиксов (`view_`, `user_`, …).
- `user_doctor` / `user_doctor_val` = `dataid` врача.
- Радио `view_type` — только `Analysis::TYPE_PTEN` (7).
- Селект `view_lab` — `Dataprovider::getAllLabs()` (`SELECT * FROM t_lab ORDER BY cat`).
- `Forms::Callback(['id' => 'route'])` — маркер колбэка попапа, не отдельная бизнес-логика.

Ниже заголовок «Существующие маршруты» и **`Lu::Block`**:

```php
Lu::Block([
    'id' => 'route-list',
    'filter' => 'v.user_doctor = '.$dataset_id,
    'sort' => 'cat desc',
    'cache' => 0,
]);
```

`Lu::Block($params['id'])` = тот же легаси Dyndata, alias `route-list`. Это не отдельный движок — тот же список, что и на страницах каталога.

Отдельного `aftersave/t_analysis_route.php` в легаси нет: сохранение = INSERT полей таблицы.

## Как `action.php` сохранял

`core/php/action.php` — общий POST-вход форм кабинета:

1. CSRF/token, антидубль POST за 10 секунд.
2. Если есть `table` — разобрать POST-поля по префиксам, INSERT/UPDATE в эту таблицу.
3. При необходимости `project/logic/preprocess.php` и `aftersave/<table>.php`.

Для маршрута достаточно пункта 2: `user_doctor`, `view_type`, `view_lab`.

## Соответствие на новом ядре

| Легаси | Сейчас |
|--------|--------|
| `{{ route }}` + `h` | `UC\Flow::button` + `FlowSignature` (HMAC, не md5) |
| `wf/route.php` | `project/phpfiles/flow/route.php` |
| `/core/php/action.php` + `table=` | `POST /jsapi/admin/analysis-route` → ActiveRecord + `TAnalysisRouteHook` |
| `Lu::Block('route-list')` | `UC\DynBlock('t_analysis_route', 't_analysis_route/route-list')` |

Инфра модалок — [admin-flow.md](admin-flow.md). Кейс врачей — [doctors.md](doctors.md).

## Пул кодов (`wf/codes.php`)

Кнопка на странице `gen/t_code.php`: `WF::Button` с `id=codes`, `action=WF_CODEPULL`. Форма в `wf/codes.php` уходит в `action.php` с `table=t_events`. Сам пул пишет `aftersave/t_events.php`: N строк `t_code` с `view_analiz`, код — `Code::makeCode`. Отдельный `aftersave/t_code.php` заполняет `varc_code` у одной новой строки из POST `view_analiz`.

На новом ядре это `flow/codes.php` и `CodeService`, без строки в `t_events`. См. [codes.md](codes.md).

## Меню заявки (`{{ workflow }}`, `{{ operation }}`)

В колонке «Действия» списка пациентов легаси стояли два токена. `{{ workflow || dropdown }}` — выпадающий список операций из `t_wf` (`Replace::AnalysisWorkflow`). `{{ workflow || buttons }}` — те же операции кнопками, так рисуется паспорт. `{{ operation }}` — ссылки «пациент», «анализ», «заключение» и чекбоксы заключения и экспресса.

Кнопка с модалкой открывала `wf/analysis.php` и сохраняла строку `t_events` через `action.php`. Дальше статус менял `logic/aftersave/t_events.php`. Шесть операций (материал получен, пригоден, израсходован, вернуть материал, пациент заберёт сам, завершить анализ) уходили формой сразу, без окна комментария.

На новом ядре это колонка грида и флоу `analysis`. См. [analysis-workflow.md](analysis-workflow.md).
