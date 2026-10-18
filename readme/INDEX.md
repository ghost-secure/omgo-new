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
| [auth-and-menu.md](auth-and-menu.md) | Гейт входа, меню, страницы Справочники, Страницы и Меню |
| [admin-catalog.md](admin-catalog.md) | Грид/форма Страницы; кнопки полей на Справочниках |
| [dynblock-migrate.md](dynblock-migrate.md) | Сборка интерфейсов: простой base / сложный по одному |
| [services.md](services.md) | Проектные сервисы: роли (`t_roles`) и анализ |
| [doctors.md](doctors.md) | Список врачей `/manager/doctors/` (`doctors` / `doctors_lab`) |
| [legacy-routes.md](legacy-routes.md) | Адреса легаси: вход в кабинет, паспорт врача, карта GUID |
| [legacy-wf.md](legacy-wf.md) | Легаси WF: `wf/*.php`, hash, `action.php`, `Lu::Block` |
| [admin-flow.md](admin-flow.md) | Модалки кабинета: `FlowSignature`, `/jsapi/admin/flow` |
| [codes.md](codes.md) | Проверочные коды: пул на `/manager/mant_code/` |
| [pdf.md](pdf.md) | Заключение PDF: `/pdf/{guid}/`, шаблон по типу анализа, внешняя печать |
| [patients.md](patients.md) | Заявка = пациент и анализ: список, код, карточка черновика |
| [analysis-workflow.md](analysis-workflow.md) | Колонка «Действия»: ссылки и меню воркфлоу заявки |
| [block-expander.md](block-expander.md) | Пилюля на нижней границе блока: раскрывает подблок `data-block-expander-more` |
| [assistant-index.json](assistant-index.json) | Карта документов для помощника: путь, темы, краткое описание |
| [assistant-context-pack.json](assistant-context-pack.json) | Короткие ловушки, которые помощник путает |
| [ui-theme.md](ui-theme.md) | Светлая тема: палитра в `config.css`, лого, чёрный сайдбар, тултипы |
| [admin-settings.md](admin-settings.md) | Настройки в шапке: карта файлов + вход под пользователем |

Карта переноса с легаси: **`core/readme/migrate-legacy.md`**.

Помощник кабинета читает те же README. После проектного чата описание документа обновляется в `assistant-index.json` (правило `.cursor/rules/assistant-docs.mdc`). Подключение к Redis и ключ модели — отдельные переменные `ADMIN_HELP_CHAT_*`, не этот JSON. Классы индекса `Services\Indexing` на OMGO ещё не перенесены: они живут в `test/project`.
