# Yandex Reviews

Приложение для подключения организации на Яндекс Картах и загрузки её отзывов. Репозиторий состоит из двух 
независимых приложений:

- `backend` — Laravel API;
- `frontend` — Vue 3 SPA.


## Требования

Для запуска без Docker потребуются:

- PHP 8.4 с расширениями `pdo_sqlite` и `sqlite3`;
- Composer 2;
- Node.js 24 и npm;
- SQLite.

Альтернативный вариант — Docker с поддержкой Docker Compose.

## Локальный запуск

Подготовьте backend из корня проекта:

```powershell
cd backend
composer setup
```

Команда создаст `.env` из `.env.example`, если файла ещё нет, создаст SQLite-базу, сгенерирует `APP_KEY` и выполнит миграции.

Подготовьте frontend:

```powershell
cd ..\frontend
Copy-Item .env.example .env
npm install
```

Запустите приложения в отдельных терминалах.

Laravel API:

```powershell
cd backend
composer serve
```

Обработчик очереди:

```powershell
cd backend
composer queue
```

Vue:

```powershell
cd frontend
npm run dev
```

После запуска доступны:

- frontend: <http://localhost:5173>;
- API: <http://localhost:8000>;
- healthcheck: <http://localhost:8000/up>.

## Запуск через Docker

Создайте локальные env-файлы:

```powershell
Copy-Item backend\.env.example backend\.env
Copy-Item frontend\.env.example frontend\.env
```

Соберите образы и выполните первоначальную настройку Laravel:

```powershell
docker compose build
docker compose run --rm api composer setup
```

Запустите API, queue worker и frontend:

```powershell
docker compose up
```

Для остановки используйте `Ctrl+C` или выполните:

```powershell
docker compose down
```

SQLite-файл хранится в `backend/database/database.sqlite`. API и queue worker используют один и тот же файл.

## Проверки

Backend:

```powershell
cd backend
composer check
```

Frontend:

```powershell
cd frontend
npm run type-check
npm run build
```

Docker Compose:

```powershell
docker compose config
```

## Переменные окружения

Основные переменные backend:

- `APP_URL` — адрес Laravel API;
- `FRONTEND_URL` — origin Vue-приложения для CORS;
- `SANCTUM_STATEFUL_DOMAINS` — адреса SPA, которым разрешена cookie-аутентификация;
- `DB_CONNECTION=sqlite` — локальная база данных;
- `SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=database` — хранение сессий, кэша и очереди в SQLite.

Frontend использует `VITE_API_URL` как базовый адрес API.

## Тестовый пользователь

После `composer setup` пользователь создаётся автоматически. Для существующей базы его можно создать или обновить отдельно:

```powershell
cd backend
php artisan db:seed
```

Данные по умолчанию:

```text
Email: demo@example.com
Пароль: demo-password
```

Значения задаются через `SEED_USER_NAME`, `SEED_USER_EMAIL` и `SEED_USER_PASSWORD`. Они опубликованы в `.env.example` 

## API авторизации

Sanctum использует cookie-сессию. Frontend должен выполнять запросы с `credentials: include` в следующем порядке:

1. `GET /sanctum/csrf-cookie` — получить CSRF-cookie.
2. `POST /api/login` — передать `email` и `password`.
3. `GET /api/user` — получить текущего пользователя.
4. `POST /api/logout` — завершить сессию.

## Модель данных

- `user` хранит пользователей
- `organizations` хранит ссылку на карточку, внешний ID, название, рейтинг и точные счётчики;
- `reviews` хранит отзывы и защищена от дублей уникальной парой организации и внешнего ID;
- `parse_runs` хранит состояние, прогресс и результат каждой попытки фоновой синхронизации;
- `organization_snapshots` хранит историю изменения рейтинга и счётчиков.
