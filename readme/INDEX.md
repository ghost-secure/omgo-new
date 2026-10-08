# Документация проекта OMGO

Кабинет лаборатории на новом ядре. Магазин из `test/project` не копируем.

Репозиторий этой папки: [ghost-secure/omgo-new](https://github.com/ghost-secure/omgo-new.git). Ядро (`omgo`) `/project` не коммитит. `.gitignore` как у test: `/web/`, `/scripts/deploy/deploy.config`.

| Слой | Каталог | Карта |
|------|---------|-------|
| **Ядро** | `core/readme/` | `core/readme/INDEX.md` |
| **Проект** | `project/readme/` | этот файл |

## Файлы

| Файл | Тема |
|------|------|
| [auth-and-menu.md](auth-and-menu.md) | Гейт входа, меню, страницы Справочники и Страницы |
| [admin-catalog.md](admin-catalog.md) | Грид/форма Страницы; кнопки полей на Справочниках |
| [dynblock-migrate.md](dynblock-migrate.md) | Сборка интерфейсов: простой base / сложный по одному |
| [services.md](services.md) | Проектные сервисы: роли (`t_roles`) и анализ |
| [doctors.md](doctors.md) | Список врачей `/manager/doctors/` (`doctors` / `doctors_lab`) |
| [legacy-wf.md](legacy-wf.md) | Легаси WF: `wf/*.php`, hash, `action.php`, `Lu::Block` |
| [admin-flow.md](admin-flow.md) | Модалки кабинета: `FlowSignature`, `/jsapi/admin/flow` |
| [ui-theme.md](ui-theme.md) | Светлая тема: `#ff7f22`, лого, чёрный сайдбар, тултипы |
| [admin-settings.md](admin-settings.md) | Настройки в шапке: карта файлов + вход под пользователем |

Карта переноса с легаси: **`core/readme/migrate-legacy.md`**.
