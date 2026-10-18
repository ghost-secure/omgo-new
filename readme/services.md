# Проектные сервисы (аналог легаси `Object\*`)

Как на test: классы в `project/Services/`, регистрация синглтоном в `project/config/services.php`.

Проверка роли текущего пользователя — ядерный `auth()->isRole(RoleService::ROLE_LAB)` (`AuthManager::isRole` по `roles_map` из `t_access_matrix`). Константы ролей — в `RoleService`, не в ядре.

`AuthService::normalizeAccount` отдаёт `UserAccount::toArray()`: `profile` (`t_users`) и `roles_map`. Без этого `isRole` и `view_lab` на страницах вроде врачей не работают.

| Сервис | Легаси | Сейчас |
|--------|--------|--------|
| `Services\RoleService` | `Object\Role\Role` | константы `t_roles.cat` |
| `Services\AnalysisService` | `Object\Analysis\Analysis` | типы и статусы заявки, грид, карточка черновика (`draftByGuid`, `prepareDraftSave`). См. [patients.md](patients.md) |
| `Services\AnalysisWorkflowService` | `Replace` + `aftersave/t_events.php` | меню `t_wf`, запись события, смена статуса. См. [analysis-workflow.md](analysis-workflow.md) |
| `Services\CodeService` | `Object\Code\Code` | пул `t_code`: префикс типа + два блока по 4 символа; `isActivated`, SQL свободного/активированного, ФИО. См. [codes.md](codes.md) |
| `Services\Pdf\ConclusionDocument` | `Object\PDF\PDF` | HTML заключения из `tbldynelements`, блок по типу анализа. См. [pdf.md](pdf.md) |
| `Services\Pdf\RemoteHtmlToPdf` | curl в `form/pdf.php` | POST на `PRINTPDF_URL`. Реализация `HtmlToPdfConverter`. `PRINTPDF_REMOTE=0` сервис не вызывает, ссылка отдаёт HTML |
| `Services\AuthFormsService` | `web/auth_.php`, `reg_.php`, `recover_.php` | вход, регистрация врача, новый пароль. См. [auth-and-menu.md](auth-and-menu.md) |
| `Services\AddressLookupService` | `Object\Dadata\Dadata::addCache` | кэш `t_dadata` и подсказка адреса, токен `DADATA_TOKEN` |
| `Services\NotifyService` | `Object\Notify\Notify` | письмо и колокольчик по `t_notify_type` (типы 8 и 10) |
| `Services\MailService` | почта легаси | SMTP через PHPMailer или `mail()`; testmode — `runtime/logs/mail.log` |

`tblusers.role` (1/2/3/4 в `auth.manager_roles`) — группа доступа ядра, не эти константы.

| Константа | cat | Имя в `t_roles` |
|-----------|-----|-----------------|
| `ROLE_LAB` | 1 | Лаборатория |
| `ROLE_DOCTOR` | 2 | Врач |
| `ROLE_ADMIN` | 3 | Администратор |
| `ROLE_SPONSOR` | 5 | Спонсор |
| `ROLE_SPONSOR_AUDIT` | 6 | Спонсор А |

`menuPageIds()` — страницы сайдбара. `null`, если текущий пользователь суперадмин (`tblusers.role = 1`). Иначе объединение `catlink_parent` его ролей: список id или JSON узлов с `page_id`. Как это режет дерево `admin_left` — [auth-and-menu.md](auth-and-menu.md).

Метод легаси `Role::isLineMenu()` не переносили.

Типы анализа, которые есть в радио пула кодов (как `Object\Analysis\Analysis`): `TYPE_HRD = 1`, `TYPE_AKT = 3`, `TYPE_BRCA = 6`, `TYPE_PTEN = 7`, `TYPE_HRR = 8`. `getLabs()` — селект лабораторий для флоу маршрута (`t_lab.varc_name`).

`viewLabel()` — подпись справочника `view_*`. Id `0` даёт пустую строку: ячейка рисует прочерк. Так выводятся «Статус мутации» и «Статус HRD».

`analysisGridQuery()` — JOIN и `addSelect` грида заявок (код пациента, ФИО, дубль, регионы, мнемоники `status_color` и `test_color`). Туда же SQL фильтров виртуальных столбцов, фамилий и unix-дат материала. Один и тот же запрос у списка `/manager/mant_pacient/` и у паспорта врача. Фильтр по роли в метод не входит: его задаёт `pacientListScope()`. Раскраска и панель фильтров включены только у `t_analysis/list`. См. [patients.md](patients.md).

`pacientListScope()` — кнопка «Добавить пациента», WHERE списка по роли и флаг `doctor` (последняя роль — врач: на телефоне карточки `t_analysis/doctor-mobile`). `unfinishedCodeDraftGuid()` — заявка, где код уже потрачен, а пациент ещё не записан. `routeLabId()` — лаборатория новой заявки из маршрута врача, для PTEN без маршрута лаборатория 3. `pacientFormUrl()` — `/manager/pacient/` и тот же адрес с GUID. `addPacientButtonHtml()` — разметка «Добавить пациента»: на десктопе её вставляет `toolbar_left` списка, на телефоне страница ставит её над гридом.

`CodeService::findIdleCode()` / `activate()` — проверка и трата кода на старте заявки. `date_activate` пишется unix-числом.

## История логики

### 2026-10-17 — просмотр заключения без сервиса

- **Тип:** новая логика
- **Было:** ссылка `/pdf/{guid}/` всегда вызывала `RemoteHtmlToPdf`
- **Стало:** `PRINTPDF_REMOTE=0` отдаёт HTML. Подробности — [pdf.md](pdf.md)

### 2026-10-17 — сборка PDF заключения

- **Тип:** новая логика
- **Было:** сервиса заключения не было, печать жила только в легаси `form/pdf.php`
- **Стало:** `ConclusionDocument` собирает HTML, `RemoteHtmlToPdf` печатает по `PRINTPDF_URL`. См. [pdf.md](pdf.md)


### 2026-10-15 — прочерк для пустого справочника view_*

- **Тип:** правка неверной логики
- **Было:** ячейка `view_result` / `view_hrd` при id 0 показывала цифру
- **Стало:** `viewLabel()` возвращает пустую строку, грид рисует прочерк. См. [patients.md](patients.md)

### 2026-10-15 — воркфлоу заявки

- **Тип:** новая логика
- **Было:** `AnalysisService` не знал статусы кроме черновика и не собирал меню операций
- **Стало:** константы статусов и типов; `AnalysisWorkflowService` читает `t_wf` и пишет событие. Поведение — [analysis-workflow.md](analysis-workflow.md)

### 2026-10-15 — страницы роли для сайдбара

- **Тип:** новая логика
- **Было:** `RoleService` хранил только константы `t_roles.cat`
- **Стало:** `menuPageIds()` читает `catlink_parent` текущих ролей. Суперадмин — `null`, без ограничения. Сборка меню — [auth-and-menu.md](auth-and-menu.md)

### 2026-10-14 — старт заявки по коду

- **Тип:** новая логика
- **Было:** `AnalysisService` не выбирал лабораторию маршрута и не резал список по роли; `CodeService` код не тратил
- **Стало:** `pacientListScope`, `routeLabId`, `findIdleCode`, `activate`. Поведение — [patients.md](patients.md)

### 2026-10-11 — цвета и фильтры грида анализов

- **Тип:** новая логика
- **Было:** `analysisGridQuery()` отдавал код, ФИО, дубль и регионы
- **Стало:** плюс мнемоники цвета статуса и типа и `filterColumnExpressions` для виртуальных столбцов, фамилий и unix-дат. Поведение списка — [patients.md](patients.md)

### 2026-10-11 — SQL свободного и активированного кода

- **Тип:** новая логика
- **Было:** признак активации только в `isActivated` для кнопки удаления
- **Стало:** `idleSql` / `activatedSql` для чекбоксов фильтра. Поведение — [codes.md](codes.md)

### 2026-10-11 — активация кода и ФИО в гриде

- **Тип:** новая логика
- **Было:** `CodeService` только собирал пул
- **Стало:** `isActivated` (дата активации или `user_activate`), `userFioSelect` / вывод ФИО для грида кодов. Поведение — [codes.md](codes.md)

### 2026-10-11 — пул проверочных кодов

- **Тип:** новая логика
- **Было:** константа типа только `TYPE_PTEN`; генератора `t_code` в проекте не было
- **Стало:** константы HRD/AKT/BRCA/HRR рядом с PTEN; `CodeService` собирает код и пул. Поведение — [codes.md](codes.md)

### 2026-10-11 — общий запрос грида анализов

- **Тип:** новая логика
- **Было:** JOIN списка заявок только в `web/doctor-passport.php`
- **Стало:** `AnalysisService::analysisGridQuery()`; тот же запрос у паспорта врача и у `/manager/mant_pacient/`

### 2026-10-08 — формы входа, регистрации и восстановления

- **Тип:** новая логика
- **Было:** в проекте только сессионный `AuthService::login`
- **Стало:** `AuthFormsService`, адрес, письма `t_notify_type`, `MailService`. Поведение — [auth-and-menu.md](auth-and-menu.md)

### 2026-10-07 — AuthService + первые методы анализа

- **Тип:** новая логика
- **Было:** `AuthService` отдавал сырую строку `tblusers` без `profile` / `roles_map`; `AnalysisService` пустой
- **Стало:** `UserAccount::toArray()`; у анализа — таблицы и лаборатория для списка врачей

### 2026-10-08 — PTEN и список лаб

- **Тип:** новая логика
- **Было:** у анализа только `getLabInfo` / `labSeesAllDoctors`
- **Стало:** константа `TYPE_PTEN`, `getLabs()` для формы маршрута
