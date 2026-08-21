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
- **Comments** — whether to show avatars, and the heroicons used for the comment actions.
- **SMS** — enable/disable and credentials for SMS notifications (see [SMS notifications](#sms-notifications)).

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

## Events

Since 5.0 Finisterre also manages **events**: meetings with their own entity (not a task type),
scheduled by collecting the attendees' availability.

An event has:

- a **public agenda** (shown on its public page) and a **private agenda** (shown only to logged-in users),
- an **estimated duration**, and a set of **suggested day/time windows** proposed by the creator,
- **attendees** that can be panel users or external **guests** (name + email); each attendee receives a
  personal tokenized link by email,
- optional **open registration**, so anyone with the public link can sign up as a guest,
- configurable **reminder emails** (minutes before the start),
- a **video call** link, and lightweight **event tasks** (action items) jotted down while it runs.

### Scheduling flow

1. Create the event (it starts as a draft), add windows and attendees, then use **Open scheduling**:
   every attendee gets an email with their personal link.
2. On that page (mobile-first) each attendee taps the time frames — of the event's duration, generated
   every `slot_step_minutes` inside the windows — that suit them.
3. When everyone has answered, the earliest frame accepted by all wins. With **Confirm final time**
   enabled, the creator confirms it first (the confirm action also resolves ties or lets you pick
   manually when no frame suits everyone). Everyone is notified of the final time.

### Public pages

Events are served outside Filament under `/{events.route_prefix}` (default `events`):

- `/events/{slug}` — public page: agenda, status/date and the registration form when open registration
  is enabled.
- `/events/{slug}/a/{token}` — each attendee's personal page with the availability picker.

### Video calls (Whereby)

With a [Whereby Embedded](https://whereby.com/information/embedded/) API key in
`FINISTERRE_WHEREBY_API_KEY`, a room is created per event when it is scheduled and embedded in the
event page while the call is open. Without an API key, set `FINISTERRE_WHEREBY_ROOM_URL` (or paste a
URL per event) and attendees get a join button instead.

### Reminders

Schedule reminders run through `finisterre:send-event-reminders` (registered automatically in the
scheduler). The offsets come from `events.default_reminder_offsets` in the config and can be
overridden per event.

### Follow-up task

Once the event is over, the **Create follow-up task** action turns the event's action items into a
regular Finisterre task with those items as subtasks.

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
