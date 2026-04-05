<!--
SPDX-FileCopyrightText: Your Name <your@email.com>
SPDX-License-Identifier: CC0-1.0
-->

# Nextcloud App Template

This is a starter template for a Nextcloud app, using Vue 3 with Vite as frontend.

It also has a convenient file generator for when you will be developing your app.

## How to use this template

At the top of the GitHub page for this repository, click "Use this template" to create a copy of
this repository.

Once you have it cloned on your machine:

1. Run `./rename-template.sh` to do a mass renaming of all the relevant files to match your app name
   and your user/full name. They will be asked as input when you run the script. This will also move
   the GitHub workflow files to the right place.
1. Run `make` - this will trigger the initial build, download all dependencies and make other
   preparations as necessary.
1. Start developing! Read the rest of the readme below for more information about what you can do.

## Table of Contents

- [Makefile](#makefile)
  - [Quick workflows](#quick-workflows)
- [NPM (package.json)](#npm-packagejson)
- [Common workflows](#common-workflows)
- [Scaffolding](#scaffolding)
  - [Available generators](#available-generators)
  - [How migrations are numbered](#how-migrations-are-numbered)
  - [Examples](#examples)
  - [Tips & gotchas](#tips--gotchas)
- [GitHub Workflows](#github-workflows)
- [Project layout](#project-layout)
- [Testing](#testing)
- [Release Please (automated versioning & releases)](#release-please-automated-versioning--releases)
- [Resources](#resources)

## Makefile

There is a robust Makefile in the project which should give you everything you need in order to
develop &amp; release your app.

Below is a rundown of the different targets you can run:

| Command                 | What it does                                                                                                                       | When to use it                              | Notes                                                                                                            |
| ----------------------- | ---------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------- | ---------------------------------------------------------------------------------------------------------------- |
| `make`                  | Alias for `make build`.                                                                                                            | Anytime; default target.                    | Same as `make build`.                                                                                            |
| `make build`            | Installs PHP deps (if `composer.json` exists) and JS deps (if `package.json` or `js/package.json` exists), then runs the JS build. | First run; after pulling changes; CI.       | Skips steps that don’t apply (no `composer.json` / no `package.json`).                                           |
| `make composer`         | Installs Composer deps. If Composer isn’t installed, fetches a local `composer.phar`.                                              | When PHP deps changed.                      | Skips if `vendor/` already exists.                                                                               |
| `make pnpm`             | `pnpm install --frozen-lockfile` then run build. Uses root `package.json` if present, else `js/`.                                  | When JS deps or build changed.              | Requires `pnpm`.                                                                                                 |
| `make clean`            | Removes `build/` artifacts.                                                                                                        | Before re-packaging; to start fresh.        | Keeps dependencies.                                                                                              |
| `make refresh-autoload` | Regenerate Composer autoload files                                                                                                 | After renaming the app namespace or classes | Most useful after using `./rename-template.sh` but also sometimes useful after moving around a lot of PHP files. |
| `make distclean`        | `clean` + removes `vendor/`, `node_modules/`, `js/vendor/`, `js/node_modules/`.                                                    | Nuke-from-orbit cleanup.                    | You’ll need to re-install deps.                                                                                  |
| `make dist`             | Runs `make source` and `make appstore`.                                                                                            | Release prep; CI packaging.                 | Produces both tarballs.                                                                                          |
| `make source`           | Builds a **source** tarball at `build/artifacts/source/<app>.tar.gz`.                                                              | Sharing source-only bundle.                 | Excludes tests, logs, node_modules, etc.                                                                         |
| `make appstore`         | Builds an **App Store–ready** tarball at `build/artifacts/appstore/<app>.tar.gz`.                                                  | Upload to Nextcloud App Store.              | Aggressively excludes dev/test files & dotfiles.                                                                 |
| `make test`             | Runs PHP unit tests (`tests/phpunit.xml` and optional `tests/phpunit.integration.xml`).                                            | CI or local test run.                       | Ensures Composer deps first.                                                                                     |
| `make lint`             | Lints JS (`pnpm lint`) and PHP (`composer run lint` via local `composer.phar` if needed).                                          | Pre-commit checks.                          | Requires corresponding scripts.                                                                                  |
| `make php-cs-fixer`     | Fixes **staged** PHP files with PHP-CS-Fixer (after `php -l`).                                                                     | Before committing PHP changes.              | Operates on files staged in Git.                                                                                 |
| `make format`           | Formats JS (`pnpm format`) and PHP (`composer run cs:fix`).                                                                        | Enforce code style.                         | Requires those scripts in composer/package.json.                                                                 |
| `make openapi`          | Generates OpenAPI JSON via composer script `openapi`.                                                                              | Refresh API docs.                           | Output: `build/openapi/openapi.json`.                                                                            |
| `make sign`             | Downloads the GitHub release tarball for the version in `version.txt` and prints a base64 SHA-512 signature.                       | Manual signing for App Store.               | Needs private key at `~/.nextcloud/certificates/<app>.key`.                                                      |
| `make release`          | Uploads the signed release to the Nextcloud App Store.                                                                             | Final publish step.                         | Needs `NEXTCLOUD_API_TOKEN` env var; prompts if missing.                                                         |

### Quick workflows

**Fresh setup / development**

```bash
pnpm --version   # ensure pnpm is installed
make build       # install PHP+JS deps and build
make test        # run PHP tests
make lint        # lint JS + PHP
```

**Package for release**

```bash
make dist        # builds both source + appstore tarballs
```

**Sign and publish to App Store**

```bash
# Ensure version.txt is set, and your key exists at ~/.nextcloud/certificates/<app>.key
make sign        # prints signature for the GitHub tarball
export NEXTCLOUD_API_TOKEN=...   # or let the target prompt you
make release
```

> Prerequisites: `make`, `curl`, `tar`, `pnpm`, and (optionally) `composer`. If Composer isn’t
> installed, the Makefile auto-downloads a local `composer.phar`.

## NPM (package.json)

Run with `pnpm <script>` (or `npm run <script>` / `yarn <script>` if you prefer).

| Script         | What it does                                                                         | When to use it                                                    | Notes                                                                                                                                         |
| -------------- | ------------------------------------------------------------------------------------ | ----------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------- |
| `pnpm dev`     | Runs `vite build --watch`.                                                           | Local dev where you want incremental rebuilds written to `dist/`. | This is a **watching build**, not a dev server. It continuously rebuilds on file changes. Pair it with Nextcloud serving the compiled assets. |
| `pnpm build`   | Type-checks with `vue-tsc -b` and then does a full `vite build`.                     | CI, release builds, or when you want a clean production bundle.   | `vue-tsc -b` runs TypeScript project references/build mode, catching TS errors beyond ESLint.                                                 |
| `pnpm lint`    | Lints the `src/` directory using ESLint.                                             | Quick checks before committing or in CI.                          | Respects your `.eslintrc*` config. No auto-fix.                                                                                               |
| `pnpm format`  | Auto-fixes ESLint issues in `src/` and then runs Prettier on `src/` and `README.md`. | Enforce consistent style automatically.                           | Safe to run often; keeps diffs clean.                                                                                                         |
| `pnpm prepare` | Runs `husky` installation hook.                                                      | After `pnpm install` (automatically).                             | Ensures Git hooks (like pre-commit linting) are installed.                                                                                    |
| `pnpm gen`     | Generates scaffolding for various templates (both PHP and TS)                        | Anytime you want to easily create new files from templates.       | See [below](#scaffolding)                                                                                                                     |

## Common workflows

**While developing (continuous build output):**

```bash
pnpm dev
# Edit files → Vite rebuilds to dist/ automatically.
```

**Before pushing a branch (runs automatically using commit hooks):**

```bash
pnpm lint
pnpm build
```

**Fix style issues quickly:**

```bash
pnpm format
```

**Fresh clone:**

```bash
pnpm install
# husky installs via "prepare"
pnpm build
```

### Scaffolding

Generate boilerplate for common app pieces with:

```bash
pnpm gen <type> [name]
```

- **`name` is required** for every type **except** `migration`.
- Files are created from templates in `gen/<type>` and written to the configured output directory.
  Feel free to modify/remove any of these templates or add new ones.
- Generators never create subfolders (they write directly into the output path).

#### Available generators

| Type          | Purpose                                   | Output directory | Name required? | Template folder   | Notes                                             |
| ------------- | ----------------------------------------- | ---------------- | -------------- | ----------------- | ------------------------------------------------- |
| `component`   | Vue single-file component for reusable UI | `src/components` | ✅             | `gen/component`   | For user-facing building blocks.                  |
| `page`        | Vue page / route view                     | `src/pages`      | ✅             | `gen/page`        | Pair with your router.                            |
| `api`         | PHP controller (API endpoint)             | `lib/Controller` | ✅             | `gen/api`         | PSR-4 namespace: `OCA\<App>\Controller`.          |
| `service`     | PHP service class                         | `lib/Service`    | ✅             | `gen/service`     | Business logic; DI-friendly.                      |
| `util`        | PHP utility/helper                        | `lib/Util`       | ✅             | `gen/util`        | Pure helpers / small utilities.                   |
| `model`       | PHP DB model / entity                     | `lib/Db`         | ✅             | `gen/model`       | Pair with migrations.                             |
| `command`     | Nextcloud OCC console command             | `lib/Command`    | ✅             | `gen/command`     | Shows up in `occ`.                                |
| `task-queued` | Queued background job                     | `lib/Cron`       | ✅             | `gen/task-queued` | Extend queued job base.                           |
| `task-timed`  | Timed background job (cron)               | `lib/Cron`       | ✅             | `gen/task-timed`  | Scheduled execution.                              |
| `migration`   | Database migration                        | `lib/Migration`  | ❌             | `gen/migration`   | Auto-numbers version; injects `version` and `dt`. |

##### How migrations are numbered

The scaffolder looks at `lib/Migration`, finds the latest `VersionNNNN...` file, and **increments**
it for you. It also injects:

- `version` — the next numeric version
- `dt` — a timestamp like `YYYYMMDDHHmmss` (via `date-fns`)

You don’t pass a name for migrations.

#### Examples

Create a Vue component:

```bash
pnpm gen component UserListItem
# → src/components/UserListItem.vue
```

Create a Vue page:

```bash
pnpm gen page Settings
# → src/pages/Settings.vue
```

Create an API controller:

```bash
pnpm gen api Users
# → lib/Controller/UsersController.php
```

Create a service:

```bash
pnpm gen service MyService
# → lib/Service/MyService.php
```

Create a queued job:

```bash
pnpm gen task-queued UpdateUsers
# → lib/Cron/UpdateUsers.php
```

Create a migration (no name):

```bash
pnpm gen migration
# → lib/Migration/Version{NEXT}.php   (with injected {version} and {dt})
```

#### Tips & gotchas

- **Router pages:** After `pnpm gen page <Name>`, add the route in your router
  (`src/router/index.ts`) and import the file.
- **Cron vs queued:** Use `task-timed` for scheduled runs, `task-queued` for background work
  enqueued by events or controllers.

## GitHub Workflows

Here’s a drop-in **GitHub Workflows** section for your README. It explains each workflow, what
triggers it, what it does, any required secrets, and how they’re enabled by your
`rename-template.sh` script.

---

## GitHub Workflows

In the template, all workflows live under **`.github/workflows.template/`** so they don’t run on the
template repo itself.

When you create a new project from this template and run `./rename-template.sh`, the script **moves
them to `.github/workflows/`** so Actions start running automatically.

| Workflow file                                                 | What it’s called in Actions                                                                      | Triggers                         | What it does                                                                                                                                         | Secrets / env                                                                                                              | Notes                                                                                                                                                            |
| ------------------------------------------------------------- | ------------------------------------------------------------------------------------------------ | -------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `.github/workflows.template/block-unconventional-commits.yml` | **Block unconventional commits**                                                                 | On PR open/ready/reopen/sync     | Blocks PRs with non-conventional commit messages (Conventional Commits).                                                                             | Uses default `GITHUB_TOKEN`.                                                                                               | Uses `webiny/action-conventional-commits`. Good for keeping a clean history and auto-changelogs.                                                                 |
| `.github/workflows.template/build-npm.yml`                    | **Build NPM**                                                                                    | Push & PR                        | Installs pnpm deps and runs `pnpm build` to ensure the project builds.                                                                               | —                                                                                                                          | Sets `CYPRESS_INSTALL_BINARY=0`, `PUPPETEER_SKIP_DOWNLOAD=true` to speed up CI.                                                                                  |
| `.github/workflows.template/lint-appinfo-xml.yml`             | **Lint appinfo.xml**                                                                             | Push to `master` & PR            | Validates `appinfo/info.xml` against the App Store XSD schema.                                                                                       | —                                                                                                                          | Downloads schema from Nextcloud App Store repo and lints with `xmllint`.                                                                                         |
| `.github/workflows.template/lint-eslint.yml`                  | **Lint eslint** (summary shows as `eslint`)                                                      | Push & PR                        | ESLint on `src/**` and related files. Skips if no relevant changes.                                                                                  | —                                                                                                                          | Uses `dorny/paths-filter` to skip unrelated changes and a summary job so you can mark ESLint as “required” in branch protection.                                 |
| `.github/workflows.template/lint-openapi.yml`                 | **Lint OpenAPI**                                                                                 | Push to `master` & PR            | Regenerates OpenAPI via Composer and fails if `openapi*.json` (and optional TS types) aren’t committed.                                              | —                                                                                                                          | Skips if repo owner is `nextcloud-gmbh`. Expects `composer run openapi`, and will check for `src/types/openapi/openapi*.ts` if present.                          |
| `.github/workflows.template/lint-php-cs.yml`                  | **Lint php-cs**                                                                                  | Push to `master` & PR            | Runs PHP coding-standards check (`composer run cs:check`). Suggests `composer run cs:fix` on failure.                                                | —                                                                                                                          | Sets up PHP with common extensions.                                                                                                                              |
| `.github/workflows.template/lint-php.yml`                     | **Lint php** / **php-lint-summary**                                                              | Push to `master` & PR            | Runs `composer run lint` across a PHP version matrix. Summary job reports overall status.                                                            | —                                                                                                                          | Uses Nextcloud version matrix action to decide supported PHP versions.                                                                                           |
| `.github/workflows.template/psalm-matrix.yml`                 | **Static analysis**                                                                              | **Manual** (`workflow_dispatch`) | Runs Psalm static analysis against a Nextcloud OCP version matrix.                                                                                   | —                                                                                                                          | You can uncomment PR/push triggers if you want it always on. Requires `composer run psalm`.                                                                      |
| `.github/workflows.template/release.yml`                      | **Release** / **App Store Build** / **Upload Release Artifacts** / **Release to Nextcloud Apps** | Push & PR on `master`            | Uses Release Please to create a GitHub release; builds App Store tarball; uploads artifact to the release; optionally pushes to Nextcloud App Store. | `RELEASE_PLEASE_TOKEN` (GitHub token), `NEXTCLOUD_API_TOKEN` (App Store), `NEXTCLOUD_APP_PRIVATE_KEY` (PEM, base64 or raw) | Build step runs `make && make appstore`. The Upload step renames the tarball with the tag. The final step signs and submits to the App Store using your secrets. |

## Project layout

```
.
├─ appinfo/                     # App metadata & registration (info.xml, routes.php, app.php)
├─ lib/                         # PHP backend code (PSR-4: OCA\<App>\…)
│  ├─ Controller/               # OCS/HTTP controllers (API endpoints)
│  ├─ Service/                  # Business logic & integrations
│  ├─ Db/                       # Entities / mappers
│  ├─ Migration/                # Database migrations (Version*.php)
│  ├─ Cron/                     # Timed/queued background jobs
│  ├─ Command/                  # occ console commands
│  └─ Util/                     # Small helpers
├─ src/                         # Frontend (Vue 3 + Vite + TS)
│  ├─ app.ts                    # ⚡ Loader for the **user-facing app** (loaded via templates/app.php)
│  ├─ settings.ts               # ⚙️ Loader for the **settings page** (loaded via templates/settings.php)
│  ├─ main.ts                   # (optional) main entry or shared bootstrap
│  ├─ components/               # Reusable UI components
│  ├─ pages/                    # Route views / pages (user-facing)
│  ├─ views/                    # Additional views (e.g., settings sub-pages)
│  ├─ router/                   # Vue Router setup
│  ├─ styles/                   # Global styles
│  └─ assets/                   # Static assets used by the frontend
├─ templates/                   # Server-rendered entry templates
│  ├─ app.php                   # Mounts the user-facing app bundle (uses dist output of src/app.ts)
│  └─ settings.php              # Mounts the settings bundle (uses dist output of src/settings.ts)
├─ l10n/                        # Translations (JSON/JS) for IL10N
├─ build/                       # Build artifacts & tools (created by Makefile)
│  ├─ artifacts/                # Packaged tarballs (source/appstore)
│  └─ tools/                    # composer.phar, etc.
├─ gen/                         # Scaffolding templates (used by `pnpm gen`)
│  ├─ component/ page/ api/ …   # See “Scaffolding” section
├─ dist/                        # Vite build output (bundled JS/CSS)
├─ tests/                       # PHPUnit configs & tests
├─ package.json                 # Frontend scripts (`pnpm build`, `pnpm dev`, etc.)
├─ composer.json                # PSR-4 autoload for PHP (e.g., "OCA\\<App>\\" : "lib/")
├─ Makefile                     # Build, lint, package, release
├─ version.txt                  # App version (used by sign/release targets)
└─ rename-template.sh           # One-time renamer script for template cloning
```

## Testing

### Frontend Testing

This template includes a complete frontend testing setup using [Vitest](https://vitest.dev/) and
[Vue Test Utils](https://test-utils.vuejs.org/).

#### Running tests

```bash
# Run tests in watch mode (recommended during development)
pnpm test

# Run tests once (useful for CI)
pnpm test:run
```

#### Test file structure

Test files are placed next to the files they test, using the `.test.ts` suffix:

```
src/
├─ utils/
│  ├─ string.ts           # Utility functions
│  └─ string.test.ts      # Tests for string.ts
├─ components/
│  ├─ StatusBadge.vue     # Vue component
│  └─ StatusBadge.test.ts # Tests for StatusBadge.vue
```

#### Writing tests

##### Pure TypeScript/utility functions

For utility functions, use the standard `describe`/`it`/`expect` pattern:

```typescript
import { describe, expect, it } from 'vitest'
import { myFunction } from './myModule'

describe('myFunction', () => {
  it('handles normal input', () => {
    expect(myFunction('hello')).toBe('HELLO')
  })

  it('handles null input', () => {
    expect(myFunction(null)).toBe('')
  })
})
```

##### Vue components

For Vue components, you'll need to mock Nextcloud dependencies. Here's a typical pattern:

```typescript
import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import MyComponent from './MyComponent.vue'

// Mock @nextcloud/l10n
vi.mock('@nextcloud/l10n', () => ({
  t: (app: string, text: string, vars?: Record<string, unknown>) => {
    if (vars) {
      return Object.entries(vars).reduce(
        (acc, [key, value]) => acc.replace(`{${key}}`, String(value)),
        text,
      )
    }
    return text
  },
  n: (app: string, singular: string, plural: string, count: number) => {
    return count === 1 ? singular : plural
  },
}))

// Mock Nextcloud Vue components
vi.mock('@nextcloud/vue/components/NcButton', () => ({
  default: {
    name: 'NcButton',
    template:
      '<button :disabled="disabled" @click="$emit(\'click\')"><slot /><slot name="icon" /></button>',
    props: ['variant', 'disabled', 'ariaLabel', 'title'],
  },
}))

// Mock icon components
vi.mock('@icons/Check.vue', () => ({
  default: { name: 'CheckIcon', template: '<span />', props: ['size'] },
}))

describe('MyComponent', () => {
  it('renders with props', () => {
    const wrapper = mount(MyComponent, {
      props: { title: 'Hello' },
    })
    expect(wrapper.text()).toContain('Hello')
  })

  it('emits events', async () => {
    const wrapper = mount(MyComponent, {
      props: { clickable: true },
    })
    await wrapper.trigger('click')
    expect(wrapper.emitted('click')).toBeTruthy()
  })

  it('computes values correctly', () => {
    const wrapper = mount(MyComponent, {
      props: { count: 5 },
    })
    // Access computed properties via wrapper.vm
    expect((wrapper.vm as InstanceType<typeof MyComponent>).doubleCount).toBe(10)
  })
})
```

#### Tips

- **Test file location**: Place test files next to the files they test (e.g., `Component.test.ts`
  next to `Component.vue`)
- **TypeScript errors**: You may see "Cannot find module './Component.vue'" errors in test files.
  These can be ignored as Vitest handles Vue files correctly at runtime
- **Mocking**: Keep mocks minimal - only mock what's necessary for the test to run
- **happy-dom**: This template uses happy-dom instead of jsdom for faster test execution. Note that
  happy-dom preserves hex colors (e.g., `#ff5500`) rather than converting to RGB
- **Globals**: The vitest config enables globals, so you don't need to import `describe`, `it`,
  `expect` in every file (though explicit imports are recommended for clarity)

#### Resources

- [Vitest documentation](https://vitest.dev/)
- [Vue Test Utils documentation](https://test-utils.vuejs.org/)
- [Testing Vue 3 components](https://test-utils.vuejs.org/guide/)

### Backend Testing (PHP)

This template uses [PHPUnit](https://phpunit.de/) for PHP unit testing, integrated with the
Nextcloud testing framework.

#### Running PHP tests

There are two ways to run PHP tests:

**Option 1: Docker (recommended)**

```bash
make test-docker
```

This automatically finds a running Nextcloud container and runs tests inside it. Works with
[nextcloud-docker-dev](https://github.com/juliushaertl/nextcloud-docker-dev) and similar setups.

**Option 2: Local Nextcloud installation**

```bash
# Set NEXTCLOUD_ROOT to your Nextcloud server path
NEXTCLOUD_ROOT=~/path/to/nextcloud make test

# Or set it in the Makefile (line 47) for convenience
make test
```

#### Test file structure

PHP tests live in the `tests/` directory:

```
tests/
├─ unit/
│  └─ Controller/
│     └─ ApiTest.php      # Unit tests for ApiController
├─ bootstrap.php          # Test bootstrap (loads Nextcloud environment)
├─ phpunit.xml            # PHPUnit config for local testing
└─ phpunit.docker.xml     # PHPUnit config for Docker testing
```

#### Writing PHP tests

Here's an example test showing how to mock Nextcloud dependencies:

```php
<?php

declare(strict_types=1);

namespace Controller;

use OCA\YourApp\AppInfo\Application;
use OCA\YourApp\Controller\ApiController;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ApiTest extends TestCase {
    private ApiController $controller;
    /** @var IRequest&MockObject */
    private IRequest $request;
    /** @var IAppConfig&MockObject */
    private IAppConfig $config;
    /** @var IL10N&MockObject */
    private IL10N $l10n;

    protected function setUp(): void {
        $this->request = $this->createMock(IRequest::class);
        $this->config = $this->createMock(IAppConfig::class);
        $this->l10n = $this->createMock(IL10N::class);

        // Mock translation to return the input string
        $this->l10n->method('t')
            ->willReturnCallback(function ($text, $params = []) {
                if (empty($params)) {
                    return $text;
                }
                return vsprintf($text, $params);
            });

        $this->controller = new ApiController(
            Application::APP_ID,
            $this->request,
            $this->config,
            $this->l10n
        );
    }

    public function testGetHello(): void {
        $this->config->method('getValueString')
            ->willReturn('');

        $resp = $this->controller->getHello()->getData();

        $this->assertIsArray($resp);
        $this->assertArrayHasKey('message', $resp);
    }

    public function testPostHello(): void {
        $this->config->expects($this->once())
            ->method('setValueString');

        $resp = $this->controller->postHello([
            'name' => 'World',
        ])->getData();

        $this->assertStringContainsString('World', $resp['message']);
    }
}
```

#### Tips

- **Mocking**: Use `$this->createMock()` for Nextcloud interfaces like `IRequest`, `IAppConfig`,
  `IL10N`, etc.
- **Test isolation**: Each test should be independent; use `setUp()` to create fresh mocks
- **Naming convention**: Test files should end with `Test.php` (e.g., `ApiTest.php`)
- **Docker vs local**: Docker testing is more reliable as it uses a fully configured Nextcloud
  environment

#### Resources

- [PHPUnit documentation](https://docs.phpunit.de/)
- [Nextcloud app testing guide](https://docs.nextcloud.com/server/latest/developer_manual/digging_deeper/testing.html)

## Release Please (automated versioning & releases)

This template includes **[Release Please](https://github.com/googleapis/release-please)** to
automate changelogs, tags, and GitHub releases based on **Conventional Commits**. It also updates
your app version in **`appinfo/info.xml`** as part of the release process.

### How it works

1. **You merge PRs** that use Conventional Commits (e.g., `feat:`, `fix:`, `docs:`).
2. The **“Release”** workflow (`.github/workflows/release.yml`) runs **Release Please**:

   - Generates or updates a **release PR** with a version bump and changelog.
   - When that release PR is merged, it **creates a Git tag and GitHub Release**.

3. The workflow then:

   - **Builds the App Store tarball** (`make && make appstore`).
   - **Uploads the artifact** to the GitHub Release.
   - Optionally **publishes** to the Nextcloud App Store (via `make release`).

4. As part of the release, the **app version in `appinfo/info.xml` is updated** automatically.

### Conventional Commits (what to write in your commits)

- `feat: add currency search` → minor bump
- `fix: handle empty payload` → patch bump
- `docs: update README`
- `chore: bump deps`
- `refactor: extract service`
- **Breaking change:** include `!` or a `BREAKING CHANGE:` footer e.g. `feat!: drop PHP 8.0`, or add
  a footer:

  ```
  BREAKING CHANGE: dropped PHP 8.0 support
  ```

> This template also includes a **“Block unconventional commits”** workflow to help you keep commit
> messages compliant.

### Required secrets (for full release automation)

- **`RELEASE_PLEASE_TOKEN`** — GitHub token for the Release Please action.
- **`NEXTCLOUD_API_TOKEN`** — App Store API token (for the final publish step).
- **`NEXTCLOUD_APP_PRIVATE_KEY`** — Private key used to sign the app (PEM text or base64).

> If you’re just testing the flow, you can skip the App Store secrets; the GitHub release and
> artifact upload will still work.

### Typical release flow

1. Merge feature/fix PRs with Conventional Commits.
2. Release Please opens/updates a **“release-please” PR** (you’ll see proposed version + changelog).
3. **Merge the release PR.** This:

   - Tags the repo (e.g., `v1.2.0`)
   - Creates the **GitHub Release**
   - Triggers build & artifact upload
   - Calls `make release` to publish to the **Nextcloud App Store** (if secrets are set)

### Troubleshooting

- **No release PR appears** → Ensure the `Release` workflow is enabled (moved from
  `.github/workflows.template/` by `./rename-template.sh`) and `RELEASE_PLEASE_TOKEN` is set.
- **Version not updated in `appinfo/info.xml`** → Check the `Release` workflow logs; make sure the
  release PR included the XML bump (action handles this).
- **App Store publish fails** → Verify `NEXTCLOUD_API_TOKEN` and `NEXTCLOUD_APP_PRIVATE_KEY`, and
  that `version.txt` matches the intended release version.
- **Initial version is 1.0.0** → If you want to start from a custom version, change it in
  `version.txt` and in `.release-please-manifest.json`. The default is `0.0.0` which bumps the first
  release to `1.0.0` automatically. If you start from a version like `0.1.0`, the next release will
  not bump the major until a breaking change is introduced.

### Useful docs

- [Release Please action](https://github.com/googleapis/release-please)
- [Conventional Commits](https://www.conventionalcommits.org/)
- [Nextcloud App publishing](https://docs.nextcloud.com/server/19/developer_manual/app/publishing.html)

## Resources

- **Nextcloud app development**

  - [App dev guide](https://nextcloud.com/developer/)
  - [OCS API](https://docs.nextcloud.com/server/stable/developer_manual/client_apis/OCS/ocs-api-overview.html)
  - [Publishing to the App Store](https://nextcloudappstore.readthedocs.io/en/latest/developer.html#publishing-apps-on-the-app-store)
  - [App signing](https://nextcloudappstore.readthedocs.io/en/latest/developer.html#obtaining-a-certificate)
  - [Server dev environment](https://docs.nextcloud.com/server/latest/developer_manual/getting_started/devenv.html)

- **Nextcloud UI & components**

  - [nextcloud-vue (components)](https://github.com/nextcloud/nextcloud-vue)
  - [Component docs/Style guide](https://next--nextcloud-vue-components.netlify.app)

- **Frontend stack**

  - [Vue 3](https://vuejs.org/)
  - [Vue Router](https://router.vuejs.org/)
  - [Vite](https://vitejs.dev/guide/)
  - [pnpm](https://pnpm.io/)
  - [Axios](https://axios-http.com/)

- **Backend & tooling**

  - [Composer](https://getcomposer.org/doc/)
  - [PHP CS Fixer](https://github.com/PHP-CS-Fixer/PHP-CS-Fixer)
  - [ESLint](https://eslint.org/docs/latest/)
  - [Prettier](https://prettier.io/docs/en/)
  - [OpenAPI spec](https://spec.openapis.org/oas/latest.html)
  - [date-fns](https://date-fns.org/)
