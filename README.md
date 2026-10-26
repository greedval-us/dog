# DogLive

[![Tests](https://github.com/greedval-us/dog/actions/workflows/tests.yml/badge.svg?branch=main&event=push)](https://github.com/greedval-us/dog/actions/workflows/tests.yml)
[![Repository hygiene](https://github.com/greedval-us/dog/actions/workflows/repository.yml/badge.svg?branch=main&event=push)](https://github.com/greedval-us/dog/actions/workflows/repository.yml)

DogLive — браузерная игра о собаках на Laravel 13, Inertia 3 и Vue 3.

## Проверки GitHub

При отправке изменений в `main` и в pull request автоматически запускаются:

- форматирование и линтер клиента, TypeScript, Vitest и сборка;
- проверка `composer.json` и `composer.lock`, Pint и PHPStan;
- серверные тесты с PostgreSQL 18;
- браузерные тесты с Chromium;
- проверка `.gitignore`: локальные настройки, секреты и сгенерированные файлы
  не должны отслеживаться, а исходники и lock-файлы должны оставаться доступными
  для коммита.

Бейджи показывают результат последнего запуска для `main`. Результаты отдельных
проверок доступны на вкладке Actions и в pull request.

## Разработка

Нужны PHP 8.3, Composer, Node.js 22 и PostgreSQL 18. Настройки окружения задаются
в локальном `.env` по образцу `.env.example`; тесты используют отдельную БД
`dog_test`.

После установки зависимостей `npm run dev` и `npm run build` автоматически
генерируют TypeScript-файлы Wayfinder. Перед отдельным запуском проверки типов
их можно создать командой `php artisan wayfinder:generate --with-form`.

Контракты модулей описаны в [архитектуре проекта](docs/architecture.md).
