<!--
SPDX-FileCopyrightText: Chen Asraf <contact@casraf.dev>
SPDX-License-Identifier: CC0-1.0
-->

# Nextcloud Pantry

[![GitHub Release](https://img.shields.io/github/v/release/chenasraf/nextcloud-pantry?color=blue)](https://github.com/chenasraf/nextcloud-pantry/releases/latest)
[![Build NPM](https://github.com/chenasraf/nextcloud-pantry/actions/workflows/build-npm.yml/badge.svg)](https://github.com/chenasraf/nextcloud-pantry/actions/workflows/build-npm.yml)
[![Lint PHP](https://github.com/chenasraf/nextcloud-pantry/actions/workflows/lint-php.yml/badge.svg)](https://github.com/chenasraf/nextcloud-pantry/actions/workflows/lint-php.yml)
[![Frontend Tests](https://github.com/chenasraf/nextcloud-pantry/actions/workflows/vitest.yml/badge.svg)](https://github.com/chenasraf/nextcloud-pantry/actions/workflows/vitest.yml)
[![PHPUnit MySQL](https://github.com/chenasraf/nextcloud-pantry/actions/workflows/phpunit-mysql.yml/badge.svg)](https://github.com/chenasraf/nextcloud-pantry/actions/workflows/phpunit-mysql.yml)
[![PHPUnit PostgreSQL](https://github.com/chenasraf/nextcloud-pantry/actions/workflows/phpunit-pgsql.yml/badge.svg)](https://github.com/chenasraf/nextcloud-pantry/actions/workflows/phpunit-pgsql.yml)

A household management app for Nextcloud — shared shopping lists, photo boards, and notes, all in
one place.

## Features

- **Houses**: Group household members and their shared data. A person can belong to multiple houses
  and switch between them freely.
- **Shopping Lists**: Create and manage shared shopping lists with support for recurring items (e.g.
  milk every week) that automatically reappear when due.
- **Photo Boards**: Keep shared reference photos organized in folders — the right brand of dog food,
  a favorite recipe card, a product label, and so on.
- **Notes Wall**: A lightweight shared space for household reminders, pinned messages, and quick
  notes with customizable colors.
- **Notifications**: Get notified when household members upload photos, add notes, or edit shared
  content.
- **Modern UI**: Built with Vue 3 and Nextcloud Vue components.

## Installation

### From the Nextcloud App Store

Install Pantry directly from your Nextcloud instance through the Apps page.

### Manual Installation

1. Download the latest release from the
   [releases page](https://github.com/chenasraf/nextcloud-pantry/releases)
2. Extract to your Nextcloud apps directory:

```bash
cd /path/to/nextcloud/custom_apps
tar xfv pantry-vX.X.X.tar.gz
```

3. Enable the app from Nextcloud's Apps page or via command line:

```bash
php occ app:enable pantry
```

## Contributing

I am developing this app on my free time, so any support, whether code, issues, or just stars is
very helpful to sustaining its life. If you are feeling incredibly generous and would like to donate
just a small amount to help sustain this project, I would be very very thankful!

<a href='https://ko-fi.com/casraf' target='_blank'>
  <img height='36' style='border:0px;height:36px;'
    src='https://cdn.ko-fi.com/cdn/kofi1.png?v=3'
    alt='Buy Me a Coffee at ko-fi.com' />
</a>

I welcome any issues or pull requests on GitHub. If you find a bug, or would like a new feature,
don't hesitate to open an appropriate issue and I will do my best to reply promptly.

## Development

### Prerequisites

- [pnpm](https://pnpm.io/)
- [Composer](https://getcomposer.org/) (auto-downloaded if missing)
- A running Nextcloud instance (Docker recommended)

### Quick Start

```bash
make build       # install PHP+JS deps and build
pnpm dev         # start watching for frontend changes
make test        # run PHP tests
pnpm test        # run frontend tests
make lint        # lint JS + PHP
```

### Project Layout

```
.
├─ appinfo/          # App metadata & registration (info.xml, routes.php)
├─ lib/              # PHP backend (PSR-4: OCA\Pantry\…)
│  ├─ Controller/    # API endpoints
│  ├─ Service/       # Business logic
│  ├─ Db/            # Entities & mappers
│  ├─ Migration/     # Database migrations
│  └─ Notification/  # Notification handlers
├─ src/              # Frontend (Vue 3 + Vite + TypeScript)
│  ├─ components/    # Reusable UI components
│  ├─ views/         # Route views
│  ├─ composables/   # Vue composables
│  └─ api/           # API client layer
├─ tests/            # PHPUnit tests
└─ gen/              # Scaffolding templates (pnpm gen)
```

## License

This app is licensed under the [AGPL-3.0-or-later](LICENSE) license.
