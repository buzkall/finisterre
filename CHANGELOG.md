# Changelog

All notable changes to `finisterre` will be documented in this file.

## Unreleased - 2026-06-13

Replace the settings **Active** toggle with an **Active environments** field (a comma-separated list like `local,production`). Finisterre is now active whenever the current app environment matches one of the listed values; an empty list means active everywhere, so an installed plugin is enabled by default. The value is stored in `FinisterreSettings::$environments` and derived into `config('finisterre.active')` at runtime by `SettingsConfig`, so every existing `config('finisterre.active')` gate keeps working unchanged. When the settings row is missing (not migrated/seeded yet, i.e. not really installed) the plugin stays **inactive** unless an explicit `FINISTERRE_ENVIRONMENTS` is set — an empty list only means "active everywhere" once the settings row exists, so a half-installed plugin no longer activates and 500s the panel on its not-yet-registered routes. As an extra guard, `TasksKanbanBoard::canAccess()` now also checks `Route::has()` for its own route, so if the board's slug collides with an existing route in the host panel (its page route then isn't registered) the board is hidden instead of crashing the whole panel — re-run the installer to pick a free slug. The settings page is no longer shown in the navigation menu — it's opened from a **Settings** header action on the Kanban board instead. New `FinisterrePlugin::canConfigureFinisterre(bool|Closure)` config callback gates both that header action and access to the settings page (`canConfigure()`), defaulting to `true`.

Make the installer self-healing for the settings rows. The settings migration only seeds the `finisterre.*` rows the first time it runs; if they're later removed (e.g. by the uninstaller, which deletes the rows but leaves the migration record in place) a plain `migrate` won't recreate them, so the install step that resolved `FinisterreSettings` silently failed — the board-URL prompt never appeared and the database was left unseeded. The install now calls `SettingsConfig::seedMissing()`, which idempotently recreates any missing rows directly via the settings migrator (from a shared `SettingsConfig::defaults()` now also used by the migration, so the two can't drift). With the rows present, the board-slug prompt fires again and picks a free path when the default (`tasks`) collides with an existing host route.

Fix images disappearing when pasting several at once into a comment (or task description). Filament v5's RichEditor uploads pasted files concurrently, and the racing Livewire temporary uploads evict each other so all but the last image silently fail to insert (Filament bails on `if (! url) return`, no console error). Ship a small JS asset (`resources/dist/finisterre-rich-editor-paste-fix.js`, registered via `FilamentAsset`) that intercepts a multi-file paste and replays it as sequential single-file pastes, waiting for each upload to finish before starting the next — reusing Filament's working single-file path. Run `php artisan filament:assets` after updating to publish it.

The `finisterre:uninstall` command now runs the final cleanup itself instead of printing instructions: it executes `composer remove buzkall/finisterre` and then `npm run build` (stopping with a hint to run the command manually if either fails). It also now deletes the published assets that `php artisan filament:assets` copied into the host's `public/` folder (`public/css/buzkall/finisterre`, `public/js/relaticle/flowforge`, `public/css/relaticle/flowforge`), and matches all eight published migration files by their known base names instead of a `*finisterre*` glob — the previous glob missed `add_task_changes_table`, which has no "finisterre" in its file name.

Add a Cmd+S / Ctrl+S keyboard shortcut to the Finisterre settings page that saves the form.

Remove the unused `move-resources.sh` dev helper (and its `make-filament-resource` / `move-resources` Composer scripts). Add `export-ignore` `.gitattributes` entries for dev-only root files (`package.json`, `package-lock.json`, `postcss.config.js`, `tailwind.config.js`, `pint.json`, `CHANGELOG.md`) so they're excluded from the distributed Composer package.

Add a Filament **settings page** (`ManageFinisterreSettings`) to configure the package from the panel instead of editing `config/finisterre.php` and `.env`. Behavioral options — `active`, board `slug`, hidden statuses, fallback assignee, assignable-users filter, comment avatars/icons, and the full SMS notification block — are now stored in the database via `spatie/laravel-settings` (new `FinisterreSettings` class + a settings migration seeded from the current config defaults). A `SettingsConfig` bridge overrides `config('finisterre.*')` at runtime so every existing read keeps working unchanged, and falls back to the config-file defaults when the settings table hasn't been migrated yet. The page is gated by `FinisterrePlugin::canViewAllTasks()` and is always registered (even when inactive) so the package can be re-enabled from the UI. The plugin now **always registers** its routes (settings page, task resource and Kanban board) and lets `active` gate access/navigation via each `canAccess()` (read at request time once config is hydrated) instead of gating route registration — registering on a DB read was fragile and could make `filament.admin.pages.tasks` undefined, 500-ing any panel that references that route. The installer now publishes/runs the settings migration (skipping it when the host already has a `settings` table), prompts for the task-board URL path (probing the registered routes for a free path so it suggests the canonical `admin/tasks`, falling back to `admin/finisterre` and then `admin/finisterre-2`, `admin/finisterre-3`, … when the host already serves the earlier ones — rather than the stored slug, which `SettingsConfig` would resurface from a prior install; the probe ignores Finisterre's own routes so a re-install doesn't treat the board it registered last time as a collision; it parses the board segment from the answer and re-prompts, with a "use it anyway?" escape hatch, when the chosen path is still taken by another route in the panel) and stores it in settings, and activates the package via settings rather than writing `FINISTERRE_ACTIVE` to `.env`; the uninstaller removes the stored `finisterre` settings. The panel-provider patcher detects an existing `->plugins(` array regardless of formatting (e.g. the array opening on the next line) and injects into it; when the provider has no plugins array at all it appends a `->plugins([FinisterrePlugin::make()])` call at the bottom of the `$panel` chain. Structural values (table names, model class, policies, guard, panel slug, locales) remain config-only.

The installer now also adds a Tailwind `@source` line for the flowforge Kanban views (`vendor/relaticle/flowforge/resources/views`) to each host `theme.css`, alongside the existing finisterre one, so the task board renders styled (and the uninstaller removes both). `FinisterreTask` gains a lazy-load-safe `attachments` accessor: under `Model::shouldBeStrict()`, Filament's `SpatieMediaLibraryFileUpload` internally reads `data_get($record, 'attachments')`, which previously threw a `MissingAttributeException`; the accessor returns the already-loaded media for the field's collection (or an empty collection) without triggering a query.

On the settings page, the three comment-icon fields are now searchable Heroicon pickers instead of free-text inputs. A new `HasIconOptions` trait builds a static, `allowHtml` option list of the outline Heroicons (keyed by their `heroicon-o-…` identifier, labelled with an inline SVG read straight from the blade-heroicons package and a readable name), so the options preload on open and a `getOptionLabelUsing` fallback still renders any stored value — including legacy `heroicon-s-…` ones. The default comment icons changed from solid to outline to match. The `fallback_notifiable_id` select is `preload()`ed.

Add a `finisterre:uninstall` command that reverses the installer: removes `FinisterrePlugin::make()` and its import from every `*PanelProvider.php`, strips `FinisterreUserTrait` from `app/Models/User.php`, removes the Tailwind `@source` line from each `resources/css/filament/*/theme.css`, deletes `FINISTERRE_ACTIVE` from `.env`, and (each behind a confirmation) drops the Finisterre tables, deletes the published migration files, and deletes the published config. Every step is idempotent and skips with a message when the host project doesn't match the expected shape.

Restrict GitHub Actions workflow permissions: add a top-level `permissions: contents: read` block to `phpstan.yml` and `run-tests.yml` so they no longer inherit the default write-all token (flagged by Laravel Moat). The `dependabot-auto-merge.yml` and `fix-php-code-style-issues.yml` workflows keep their minimal write scopes since they merge PRs / commit style fixes.

SHA-pin all GitHub Actions: replace every `uses:` tag/branch ref with the full-length commit SHA (tag kept as a trailing comment) across all four workflows, preventing mutable-tag supply-chain attacks (also flagged by Laravel Moat).

Add a `SECURITY.md` disclosure policy directing vulnerability reports to GitHub Private Vulnerability Reporting or email, so researchers have a private channel instead of public issues (flagged by Laravel Moat).

Add Laravel 13 support: widen `orchestra/testbench` to `^10.9|^11.1` and update the test matrix to run against Laravel 13 (Testbench 11) and 12 (Testbench 10), dropping the Laravel 10 and 11 rows. The full test suite passes on Laravel 13.

Fix all 31 PHPStan errors (CI had been red since May). Add missing `@property` annotations and `comments()` relation generics to `FinisterreTask`, document the Filament `$form` magic property on the three form components, use `getKey()` instead of the magic `$id` and `$this->record` instead of `getRecord()` in the observer/edit page, and scope `ignoreErrors` for three genuine false-positives (package `env()` in config, public-API traits, and the unresolvable `finisterre::` view namespace).

## 3.0.0 - 2026-05-26

Add a `finisterre:install` command (powered by spatie/laravel-package-tools) that drives the full setup in one shot: publishes the config and migrations, prompts to run migrations, appends `FINISTERRE_ACTIVE=true` to `.env`, runs `php artisan filament:assets`, injects `FinisterrePlugin::make()` into every `app/Providers/Filament/*PanelProvider.php`, adds `use FinisterreUserTrait;` to `app/Models/User.php`, and appends the Tailwind `@source` line to every `resources/css/filament/*/theme.css`. Each auto-patch step is idempotent and falls back to a printed instruction when the host project doesn't match the expected shape.

**Breaking:** `FinisterrePlugin::$canViewAllTasks` now defaults to `true`. Existing apps that were relying on the previous "no one can see tasks unless a closure is wired" default must call `->userCanViewAllTasks(fn() => false)` explicitly to keep the resource hidden. Without this change `FinisterrePlugin::make()` alone left the resource hidden in every panel, which is rarely what integrators want.

**Breaking:** Views, assets, translations, migrations and commands are now registered regardless of `FINISTERRE_ACTIVE`. Only the scheduled-comments scheduler still gates on the flag. This unblocks `php artisan vendor:publish` (and `finisterre:install`) before the flag has been set, and makes the package's blade views renderable in tooling and tests without toggling the config.

## 2.3.1 - 2026-05-26

Turn the notify field's "select all" suffix action into a toggle: once every recipient is selected it switches to a "deselect all" action that clears the field on a second click. Swap the heroicon to `users` / `user-minus` so the affordance fits the field context.

## 2.3.0 - 2026-05-26

Add a "select all" suffix action to the notify field on the comment form. When the available recipients list has more than one entry, the action populates the field with every user in a single click.

Fix the kanban board styles by adding the flowforge package's blade views to the Tailwind content paths. Previously the arbitrary utilities used by flowforge (column widths, empty-column min-height, etc.) weren't compiled into `finisterre.css`, leaving the board's columns visually broken.

## 2.2.2 - 2026-05-21

Fix the authenticatable filter when `authenticatable_filter_value` is a backed enum (or an array of them). The previous `(array)` cast left enum instances in the `whereIn` and `in_array` checks, so assignable-user filtering and file-access authorization silently failed to match. Values are now normalized through the new `AuthenticatableFilter` helper, which unwraps backed enums to their scalar value.

## 2.2.0 - 2026-05-21

Add issue reporting from your own models. Models implementing the new `FinisterreReportable` contract (with the optional `InteractsWithFinisterreReports` trait for defaults) can be reported against via the new `ReportIssueAction`, which opens a modal for a title, description and attachments (images, PDF and videos up to 3 MB) and links the created task back to the originating record through a polymorphic `subject`. The task form shows the related record, prefixed with its translated resource label, linking back to it. Run `php artisan vendor:publish --tag="finisterre-migrations"` followed by `php artisan migrate` to pick up the new `add_subject_to_finisterre_tasks` migration.

## 2.1.4 - 2026-05-20

Fix comment email notifications showing the wrong comment. The notification now carries the specific comment that triggered it instead of guessing the task's latest comment, which sent stale (often the previous) content once comment delivery was queued and scheduled comments were involved.

Convert the kanban `order_column` to an integer. Existing installs whose column is still a decimal should run `php artisan vendor:publish --tag="finisterre-migrations"` followed by `php artisan migrate` to pick up the new `convert_order_column_to_integer_in_finisterre_tasks` migration, which renumbers each status column to clean `10, 20, 30, …` values before changing the column type.

## 2.1.3 - 2026-05-19

Set the comment scheduling date picker to non-native so the calendar renders consistently across browsers.

## 2.1.2 - 2026-05-18

Show the `scheduled_for` date in comment emails.

## 2.1.1 - 2026-05-14

Fix the comments count to exclude pending scheduled comments.

## 2.1.0 - 2026-05-14

Ship the scheduled comments functionality and related styling fixes.

## 2.0.20 - 2026-05-14

Add scheduled comments: comments can be queued for a future delivery time and are dispatched by the new `finisterre:dispatch-scheduled-comments` scheduled command. Run `php artisan vendor:publish --tag="finisterre-migrations"` followed by `php artisan migrate` to pick up the new `add_scheduling_to_finisterre_task_comments` migration.

Add `finisterre:reset-sequences` command. Resets every PostgreSQL sequence in the `public` schema to `MAX(id)`, fixing the `duplicate key value violates unique constraint "migrations_pkey"` (and similar) errors that appear after importing a database dump that doesn't include sequence values. No-op on non-PostgreSQL connections.

## 2.0.19 - 2026-05-13

Stop overriding `tags.tag_model` config globally; the package now pins its tag class internally via `FinisterreTask::getTagClassName()`. This lets host apps keep using `spatie/laravel-tags` for their own models without interference.

Show all tags on the kanban board cards instead of only the first one.

Fix PostgreSQL error when editing a task with tags. The Filament tags `Select` no longer relies on `->relationship()` (which triggers `select distinct tags.*` over json columns on PG); options load and tag sync are handled explicitly.

## 2.0.18 - 2026-05-12

Refactor resource structure and use a Select for Spatie Tags

## 2.0.17 - 2026-04-27

Use authenticatable_attribute for the comment author display name

## 2.0.16 - 2026-04-22

Preload assignee users

## 2.0.15 - 2026-04-22

Fix query for postgresql

## 2.0.14 - 2026-04-22

Add user name attribute config

## 2.0.13 - 2026-04-20

Filter assignee by several roles

## 2.0.12 - 2026-04-20

Filter assignee by role

## 2.0.11 - 2026-04-20

Force filters to show in one row

## 2.0.10 - 2026-04-20

Force filters to show in one row

## 2.0.9 - 2026-04-20

Force filters to show in one row

## 2.0.8 - 2026-04-20

Stop hardcoding the name column in user query

## 2.0.7 - 2026-03-16

Stop notifying tasks moved to Done

## 2.0.6 - 2026-03-02

fix rich editor in comments

## 2.0.5 - 2026-02-26

Improvements for different roles

## 2.0.4 - 2026-02-26

Fix error ordering tabs in kanban

## 2.0.3 - 2026-02-18

Fix problem with images in RichEditor

## 2.0.2 - 2026-02-17

Fix filament actions import

## 2.0.1 - 2026-02-10

Fix catalan translation. Add has_changes indicator

## 2.0.0 - 2026-02-09

Upgrade to filament 5 and new kanban board

## 1.21.0 - 2026-02-09

Rename Task list

## 1.20.5 - 2026-01-26

Third time is a charm

## 1.20.4 - 2026-01-26

Fix tags relationship

## 1.20.3 - 2026-01-26

Fix tags relationship

## 1.20.2 - 2026-01-26

Fix tags table name

## 1.20.1 - 2026-01-26

Make tags translatable

## 1.20.0 - 2026-01-26

Add catalan translation

## 1.19.1 - 2025-12-18

Rollback route for notifications

## 1.19.0 - 2025-12-10

Improve new projects installation

## 1.18.5 - 2025-10-08

Improve comment styles

## 1.18.4 - 2025-10-08

Add taskChange on create and fix comment width

## 1.12.0 - 2025-06-25

Add action to archive tasks

## 1.11.1 - 2025-06-24

Convert edit modal to page

## 1.10.1 - 2025-05-13

Fix translations in enum trait

## 1.10.0 - 2025-05-13

Add subtasks

## 1.9.18 - 2025-05-07

Filter notifiable users by active flag (if exists)

## 1.9.17 - 2025-05-06

Parse urls in comments

## 1.9.16 - 2025-05-05

Add phpDocs

## 1.9.15 - 2025-05-05

Add phpDocs

## 1.9.14 - 2025-05-05

Add phpDocs

## 1.9.13 - 2025-05-05

Add phpDocs

## 1.9.12 - 2025-05-05

Extra checks for comments

## 1.9.11 - 2025-05-05

Check comment is defined

## 1.9.10 - 2025-05-05

Add type to record model

## 1.9.9 - 2025-05-05

Add type to record model

## 1.9.8 - 2025-05-05

Yet another phpstan fix

## 1.9.7 - 2025-05-05

Fix controller extension

## 1.9.6 - 2025-05-05

Ignore line for phpstan

## 1.9.5 - 2025-05-05

Ignore line for phpstan

## 1.9.4 - 2025-05-05

Fix check access policy

## 1.9.3 - 2025-04-03

Fix comments notifications

## 1.9.2 - 2025-03-25

Add filter by assignee

## 1.9.1 - 2025-03-24

Fix notification sent

## 1.9.0 - 2025-03-24

Send Filament notifications
Improve task filters

## 1.8.4 - 2025-03-14

Change the way wasRecentlyCreated is checked

## 1.8.3 - 2025-03-13

Change the way wasRecentlyCreated is checked

## 1.8.2 - 2025-03-13

Add implements ShouldQueue to the notifications

## 1.8.1 - 2025-03-13

Set timeout for sms and retries

## 1.8.0 - 2025-03-12

Laravel 12 support and align with kanban new version

## 1.7.3 - 2025-02-17

Fix guard call

## 1.7.2 - 2025-02-17

Fix previous

## 1.7.1 - 2025-02-17

Add guard for route

## 1.7.0 - 2025-02-17

Add SMS Notification
Change Attachment handling

## 1.6.8 - 2025-01-28

Fix problem with dark mode

## 1.6.7 - 2025-01-09

Add instructions to override the policy

## 1.6.6 - 2025-01-08

Missing translations

## 1.6.5 - 2025-01-08

Missing translations

## 1.6.4 - 2025-01-08

Add new status

## 1.6.3 - 2024-12-09

Fix error losing focus on comments

## 1.6.2 - 2024-12-04

Fix css height for images in the comments mailDiFix css height for images in the comments mail

## 1.6.1 - 2024-12-04

Allow edit description

## 1.6.0 - 2024-12-04

Improve notifications and edit taks

## 1.5.1 - 2024-11-25

Minor fixes

## 1.5.0 - 2024-11-21

Improve task notifications

## 1.4.0 - 2024-11-21

Add icons to tasks
Allow to notify comments

## 1.3.0 - 2024-11-19

Private attachments
Improve mail notifications

## 1.2.1 - 2024-11-18

Fix mail sending

## 1.2.0 - 2024-11-18

First approach to notifications

## 1.1.0 - 2024-11-18

**Full Changelog**: https://github.com/buzkall/finisterre/compare/1.0.0...1.1.0

## 1.0.0 - 2024-11-18

Out of beta
Tags, search and more config

## 0.5.3 - 2024-10-11

npm build

## 0.5.2 - 2024-10-11

Fix permission

## 0.5.1 - 2024-10-11

Missing label

## 0.5.0 - 2024-10-11

Add task comments inside the modal

## 0.4.1 - 2024-09-17

Fix error loading package

## 0.3.0 - 2024-09-12

Add Kanban page

## 0.1.0 - 2024-06-28

### What's Changed

First version of the package

## 0.2.0 - 2024-08-26

### What's Changed

Filament resource and translations
