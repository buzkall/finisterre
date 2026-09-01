# Finisterre

A Filament plugin that adds a full task-management system to your panel: a Kanban board, task assignments,
threaded and schedulable comments, email and SMS notifications, issue reporting from any of your own models, and an
in-app settings page. Works with Filament v4/v5 — for Filament 3, use the [v1 branch](#quick-install-recommended).

<p align="center">
  <img src="art/logo.png" alt="Finisterre" width="300">
</p>

## Installation

### Quick install (recommended)

```bash
composer require arzcode/finisterre
php artisan finisterre:install
```

The installation command does everything wiring-related in one shot: publishes the config and migrations, asks to run
them, enables Finisterre (active in every environment by default — manage it from the settings page), runs
`php artisan filament:assets`, injects `FinisterrePlugin::make()` into every
`app/Providers/Filament/*PanelProvider.php`, adds `use FinisterreUserTrait;` to `app/Models/User.php`, and appends the
Tailwind `@source` line to every `resources/css/filament/*/theme.css`. Then run `npm run build`.

For each step the command falls back to a printed instruction if your project doesn't match the expected shape
(different panel directory, custom User location, no Filament theme, etc.).

For Filament 3, use the v1 branch:

```bash
composer require arzcode/finisterre:^1.0
```

### Manual installation

If you'd rather wire things by hand, the package ships the usual "publishables". Start with:

```bash
composer require arzcode/finisterre
php artisan vendor:publish --tag="finisterre-config"
```

Publishing Filament assets is required for the kanban board's JavaScript:

```bash
php artisan filament:assets
```

Without this step, the kanban board will load but drag-and-drop will not work due to missing JavaScript assets.

By default, the package is active in every environment. To restrict it to specific environments, set a comma-separated
list in your `.env` file (or edit it later from the settings page):

```bash
FINISTERRE_ENVIRONMENTS=local,production
```

**Important:** If your Filament panel uses a different ID than `admin`, add this to your `.env`:

```bash
FINISTERRE_PANEL_SLUG=your-panel-id
```

To find your panel ID, check your `PanelProvider.php` for `->id('...')`. For example, if your panel uses
`->id('filament')`, set `FINISTERRE_PANEL_SLUG=filament`.

You can change the name of the table in the config file
You need to publish and run the migrations with:

```bash
php artisan vendor:publish --tag="finisterre-migrations"
php artisan migrate
```

If you don't already have the spatie tags package, publish the migrations

```bash
php artisan vendor:publish --provider="Spatie\Tags\TagsServiceProvider" --tag="tags-migrations"
php artisan migrate
```

Same for spatie media package

```bash
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-migrations"
php artisan migrate
```

The tasks are linked to users, but the project can have a different model for users.
You can change the model in the config file and the name attribute column
Also, there is a trait to be included in the user's model

```
use Arzcode\Finisterre\Traits\FinisterreUserTrait;
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="finisterre-views"
```

### Upgrading

After `composer update arzcode/finisterre`, run:

```bash
php artisan finisterre:update
```

It lists every migration the package ships next to the file you published for it and whether that migration has run, so
you can see at a glance what a new version added. Then it offers, one confirmation at a time, to publish the missing
migrations, run them, seed the settings this version added, re-publish the Filament assets and run `npm run build`. It
also reports config keys that were added or dropped (your published `config/finisterre.php` is deep-merged over the
package defaults, so copying new keys over is optional) and warns when a theme file lost its `@source` lines.

Nothing is written without your confirmation, and publishing is idempotent — an already-published migration keeps the
file name it got the first time instead of being copied again under a new timestamp.

To only report what is outstanding — useful in CI, where it exits with a non-zero status when anything is pending:

```bash
php artisan finisterre:update --check
```

### Uninstalling

```bash
php artisan finisterre:uninstall
```

It reverses the install: removes `FinisterrePlugin::make()` from your panel providers, `use FinisterreUserTrait;` from
`app/Models/User.php`, the `@source` lines from your theme files, the Finisterre entries from `.env` and the settings
rows, and then asks separately before dropping the database tables, deleting the published migrations and deleting
`config/finisterre.php`. It finishes by running `composer remove arzcode/finisterre` and `npm run build`.

## Filament Theme CSS

> **Required.** Following Filament v5 guidance, this package ships **raw CSS only** — it no longer compiles a Tailwind
> stylesheet of its own. The utility classes used in its Blade views are compiled by **your** application's Filament
> theme. Without the `@source` lines below the kanban board and task views will render unstyled.

You need a [custom Filament theme](https://filamentphp.com/docs/4.x/styling/overview) (
`php artisan make:filament-theme`). Add the following lines to your theme's CSS file (e.g.
`resources/css/filament/admin/theme.css`) so Tailwind scans the package views for classes, then rebuild your theme (
`npm run build`):

```css
@source '../../../../vendor/arzcode/finisterre/resources/views';
@source '../../../../vendor/relaticle/flowforge/resources/views';
```

The installer adds these lines automatically to each detected theme file.

The package comes with a default policy for the tasks that can be overridden in the config file and set your own policy

```php
'model_policy' => Arzcode\Finisterre\Policies\FinisterreTaskPolicy::class,
``` 

## Usage

Add the plugin to your panel provider. By default every authenticated user can view all tasks — no closure needed:

```php
use Arzcode\Finisterre\FinisterrePlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->plugins([
            FinisterrePlugin::make(),
        ]);
}
```

To restrict access by role, pass closures to the corresponding `user…` setters:

```php
FinisterrePlugin::make()
    ->userCanViewAllTasks(fn() => auth()->user()?->hasRole(RoleEnum::Admin))
    ->userCanViewOnlyTheirTasks(fn() => auth()->user()?->hasAnyRole([RoleEnum::Editor, RoleEnum::Manager]))
    ->userCanScheduleComments(fn() => auth()->user()?->hasRole(RoleEnum::Admin))
    ->userCanConfigureFinisterre(fn() => auth()->user()?->hasRole(RoleEnum::Admin)),
```

## Settings page

Most configuration can be managed at runtime from an in-app **settings page** instead of editing the config file. It is
opened from a header action (⚙️) on the Kanban board — it is intentionally hidden from the navigation menu — and is
gated by `userCanConfigureFinisterre()` (allowed for everyone by default; see the closure above).

Values are stored in the database and take precedence over `config/finisterre.php` at runtime, so admins can change them
without a deploy. The page covers:

- **General** — active environments and the Filament panel slug.
- **Tasks** — statuses to hide from the board, and the fallback user notified when a task has no assignee.
- **Assignable users filter** — column/value used to limit who can be assigned tasks (e.g. `role` = `admin`).
- **Subtasks** — whether to notify the assignee of checklist changes, and how long to group them for.
- **Comments** — whether to show avatars, and the heroicons used for the comment actions.
- **SMS** — enable/disable and credentials for SMS notifications (see [SMS notifications](#sms-notifications)).

## Subtask notifications

When somebody other than the assignee adds, renames, ticks or deletes one of a task's
subtasks, the assignee gets an email and an in-app notification. Edits are **grouped**: the
first change starts a five-minute window, and everything done inside it arrives as a single
digest rather than one message per subtask.

The digest reports the **net change** over the window, not each keystroke, because it works
by diffing a snapshot taken when the window opened against the checklist as it stands when
the digest is built. So a subtask that was added and then deleted, a tick that was undone,
and a rename that was reverted are all simply absent — there is nothing to report.

Both settings live on the settings page, and in `config/finisterre.php`:

```php
'subtasks' => [
    'notify'                     => true,
    'notification_delay_minutes' => 5,
],
```

No extra tables are involved: the snapshot rides along in the queued job's payload.

**Grouping needs a queue worker and a shared, lock-capable cache store.** The window is held
open by a unique-job lock on the default cache store, and the digest itself is a delayed job:

| Host setup | Result |
|---|---|
| `redis`, `memcached`, `database` or `dynamodb` cache, plus `queue:work` | Grouping works as described |
| `file` cache on a single server | Works; on a multi-server deploy each server keeps its own window |
| `CACHE_STORE=array` | The lock is per-PHP-process, so grouping silently does nothing |
| `QUEUE_CONNECTION=sync` | The delay is ignored and the job runs inline — one email per change |

Two things worth knowing. Only a non-assignee edit *opens* a window; once one is open, the
diff covers the whole checklist, so an edit the assignee makes themselves during that window
is reported to them along with the rest. And seeders or bulk imports that create subtasks
would otherwise trigger digests — set `finisterre.subtasks.notify` to `false`, or wrap the
import in `FinisterreSubtask::withoutEvents(fn () => ...)`.

## Displaying a user's full name

`finisterre.authenticatable_attribute` accepts either a single column (default `'name'`) or an array of columns to
concatenate for display:

```php
'authenticatable_attribute' => ['name', 'lastname'],
```

With an array, the package shows `"John Doe"` in every user-facing select (task assignee, filter, kanban, comment notify
list) and uses `CONCAT_WS(' ', ...)` for SQL-level selects. Columns must exist on the `users` table.

## Kanban ordering

The kanban board (powered by [flowforge](https://github.com/relaticle/flowforge)) stores each
card's position in the `order_column`, which is an **integer**. Cards within a column are kept as
`10, 20, 30, …`.

Flowforge's default algorithm computes positions as the decimal midpoint between two cards plus
random jitter, which fills `order_column` with long decimals like `63821.3847291500`. To keep the
value a clean integer, `TasksKanbanBoard` overrides flowforge's `calculateAndUpdatePosition()` and
renumbers the whole target column sequentially on every move. No vendor files are patched, so the
behavior survives `composer update`.

If you are upgrading an existing install whose `order_column` is still a decimal, publish and run
the conversion migration. It renumbers each status column to clean `10, 20, 30, …` integers and
changes the column type to `unsignedInteger`:

```bash
php artisan vendor:publish --tag="finisterre-migrations"
php artisan migrate
```

Notifications follow the move semantics:

- **Reordering a card within the same column** only changes `order_column` and does **not** notify
  the assignee.
- **Moving a card to a different column** changes its status and **does** notify the assignee
  (unless the new status is "Done").

## SMS notifications

Using smsarena.es as provider.
By default, only notifies tasks on creation with priority TaskPriorityEnum::Urgent
That can be changed in the config file

Add to your .env file the following:

```
# Finisterre
FINISTERRE_SMS_ENABLED=false
FINISTERRE_SMS_AUTH_KEY=CHANGE
FINISTERRE_SMS_SENDER=CHANGE
FINISTERRE_SMS_NOTIFY_TO=CHANGE
```

## Reporting issues from your own models

Any model in your app can let users open a Finisterre task against a specific record. The task stores a polymorphic
`subject` pointing back to that record, and the task form shows a link to it.

First publish and run the migration that adds the `subject` columns to the tasks table:

```bash
php artisan vendor:publish --tag="finisterre-migrations"
php artisan migrate
```

Make the model implement `FinisterreReportable`. The `InteractsWithFinisterreReports` trait provides sensible defaults (
a `ClassName (#id)` label and a link inferred from the model's Filament resource edit/view page):

```php
use Arzcode\Finisterre\Contracts\FinisterreReportable;
use Arzcode\Finisterre\Traits\InteractsWithFinisterreReports;

class Order extends Model implements FinisterreReportable
{
    use InteractsWithFinisterreReports;
}
```

Override either method to customise the label or link:

```php
public function getFinisterreReportLabel(): string
{
    return "Order {$this->reference}";
}

public function getFinisterreReportUrl(): ?string
{
    return route('orders.show', $this);
}
```

Then add `ReportIssueAction` wherever the record is available (a resource page, table row, infolist, etc.). It opens a
modal asking for a title, description and attachments (images, PDF, and videos up to 3&nbsp;MB), and associates the
created task with the record:

```php
use Arzcode\Finisterre\Filament\Actions\ReportIssueAction;

ReportIssueAction::make();
```

## Role restriction for Task Reports

TODO

## Development

The package ships raw CSS (`resources/css/app.css`) with no Tailwind build — utility classes in the views are compiled
by the host application's theme via the `@source` lines above. There is no JavaScript build step. Run
`php artisan filament:assets` in the host application to publish the package's assets.

## Testing

```bash
composer test
```
