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
