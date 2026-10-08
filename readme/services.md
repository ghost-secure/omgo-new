# Проектные сервисы (аналог легаси `Object\*`)

Как на test: классы в `project/Services/`, регистрация синглтоном в `project/config/services.php`.

Проверка роли текущего пользователя — ядерный `auth()->isRole(RoleService::ROLE_LAB)` (`AuthManager::isRole` по `roles_map` из `t_access_matrix`). Константы ролей — в `RoleService`, не в ядре.

`AuthService::normalizeAccount` отдаёт `UserAccount::toArray()`: `profile` (`t_users`) и `roles_map`. Без этого `isRole` и `view_lab` на страницах вроде врачей не работают.

| Сервис | Легаси | Сейчас |
|--------|--------|--------|
| `Services\RoleService` | `Object\Role\Role` | константы `t_roles.cat` |
| `Services\AnalysisService` | `Object\Analysis\Analysis` | `TBL_ANALYSIS` / `TBL_ROUTE`, `TYPE_PTEN`, `getLabInfo`, `labSeesAllDoctors`, `getLabs` |
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

`TYPE_PTEN = 7` — как в легаси. `getLabs()` — селект лабораторий для флоу маршрута (`t_lab.varc_name`).

## История логики

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
