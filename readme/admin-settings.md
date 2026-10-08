# Настройки кабинета: ядро + вход под пользователем

Кнопка «Настройки» в шапке — ядерный механизм: правый сайдбар, карта файлов текущей страницы, резолвер шаблона. Как устроено ядро и как проект **полностью** подменяет `panel.php`: [`core/readme/admin-settings.md`](../../core/readme/admin-settings.md).

## Что делает OMGO

`project/UC/AdminSettings/panel.php` — не копия ядра, а обёртка:

1. Дополняет `$ctx` (идёт ли impersonate, можно ли стартовать, список пользователей).
2. Подключает ядерный `core/UC/AdminSettings/panel.php` (роль, ФИО, файлы).
3. Подключает `project/UC/AdminSettings/impersonate.php` (блок «Авторизация»). От ядерной карты отделён отступом сверху и линией (класс `.admin-settings-impersonate`, стили ядерного `admin-settings/style.css`).

Если убрать проектный `panel.php`, останется только ядерная карта файлов. Список входа без ядра сам не появится: его рисует этот файл.

## Имперсонализация

Легаси: `omgo-lu/project/phpfiles/manager/admin.php` (список `data-user` + «Отмена входа» при `$_SESSION['imituser']`).

Сейчас:

- список: `project/config/admin_impersonate.php` (id = `t_users.cat` = `tblusers.id`);
- старт: `tblusers.role < 3` (как легаси), сессия `omgo_auth_impersonator` + смена `omgo_auth_uid`;
- в режиме входа — кнопка «Отмена входа», список скрыт;
- клик обрабатывает ядерный плагин `admin-settings`, запрос — `POST /jsapi/auth.impersonate` (`action=start|exit`, CSRF), хендлер `Api\JsApi\AuthImpersonateHandler`;
- гейт `AuthHook`: если уже impersonate, не выгоняет пользователя с `tblusers.role` вне `auth.manager_roles` (врач/спонсор иначе сразу вылетели бы на логин).

Куку магазина `AUTH_KEY` не используем.

## История логики

### 2026-10-08 — настройки и вход под пользователем

- **Тип:** новая логика
- **Было:** кнопка Настройки пустая; легаси-список входа был только в `manager/admin.php`
- **Стало:** ядерная панель + проектный список id 222/227/226/831/991/228/224/339/710 и выход из имперсонализации
