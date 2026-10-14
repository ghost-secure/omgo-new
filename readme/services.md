# Проектные сервисы (аналог легаси `Object\*`)

Как на test: классы в `project/Services/`, регистрация синглтоном в `project/config/services.php`.

Проверка роли текущего пользователя — ядерный `auth()->isRole(RoleService::ROLE_LAB)` (`AuthManager::isRole` по `roles_map` из `t_access_matrix`). Константы ролей — в `RoleService`, не в ядре.

`AuthService::normalizeAccount` отдаёт `UserAccount::toArray()`: `profile` (`t_users`) и `roles_map`. Без этого `isRole` и `view_lab` на страницах вроде врачей не работают.

| Сервис | Легаси | Сейчас |
|--------|--------|--------|
| `Services\RoleService` | `Object\Role\Role` | константы `t_roles.cat` |
| `Services\AnalysisService` | `Object\Analysis\Analysis` | `TBL_ANALYSIS` / `TBL_ROUTE`, типы HRD/AKT/BRCA/PTEN/HRR, `getLabInfo`, `labSeesAllDoctors`, `getLabs`, `analysisGridQuery` |
| `Services\CodeService` | `Object\Code\Code` | пул `t_code`: префикс типа + два блока по 4 символа; `isActivated`, SQL свободного/активированного, ФИО. См. [codes.md](codes.md) |
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

Метод легаси `Role::isLineMenu()` не переносили.

Типы анализа, которые есть в радио пула кодов (как `Object\Analysis\Analysis`): `TYPE_HRD = 1`, `TYPE_AKT = 3`, `TYPE_BRCA = 6`, `TYPE_PTEN = 7`, `TYPE_HRR = 8`. `getLabs()` — селект лабораторий для флоу маршрута (`t_lab.varc_name`).

`analysisGridQuery()` — JOIN и `addSelect` грида заявок (код пациента, ФИО, дубль, регионы, мнемоники `status_color` и `test_color`). Туда же SQL фильтров виртуальных столбцов, фамилий и unix-дат материала. Один и тот же запрос у списка `/manager/mant_pacient/` и у паспорта врача. Фильтр по врачу в метод не входит. Раскраска и панель фильтров включены только у `t_analysis/list`. См. [patients.md](patients.md).

## История логики

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
