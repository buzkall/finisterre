# Finisterre

My helper package

## Installation

### Quick install (recommended)

```bash
composer require buzkall/finisterre
php artisan finisterre:install
```

The install command does everything wiring-related in one shot: publishes the config and migrations, asks to run them, appends `FINISTERRE_ACTIVE=true` to your `.env`, runs `php artisan filament:assets`, injects `FinisterrePlugin::make()` into every `app/Providers/Filament/*PanelProvider.php`, adds `use FinisterreUserTrait;` to `app/Models/User.php`, and appends the Tailwind `@source` line to every `resources/css/filament/*/theme.css`. Then run `npm run build`.

For each step the command falls back to a printed instruction if your project doesn't match the expected shape (different panel directory, custom User location, no Filament theme, etc.).

For Filament 3, use the v1 branch:

```bash
composer require buzkall/finisterre:^1.0
```

### Manual installation

If you'd rather wire things by hand, the package ships the usual publishables. Start with:

```bash
composer require buzkall/finisterre
php artisan vendor:publish --tag="finisterre-config"
```

Publishing Filament assets is required for the kanban board's JavaScript:

```bash
php artisan filament:assets
```

Without this step, the kanban board will load but drag-and-drop will not work due to missing JavaScript assets.

You can publish the config file with:

```bash
php artisan vendor:publish --tag="finisterre-config"
```

By default, the package will not be active; this can be changed by adding the following to your .env file

```bash
FINISTERRE_ACTIVE=true
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
use Buzkall\Finisterre\Traits\FinisterreUserTrait;
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="finisterre-views"
```

**Important:** You must publish Filament assets (including Flowforge kanban JavaScript) with:

```bash
php artisan filament:assets
```

This command publishes all Filament plugin assets, including the Flowforge kanban board JavaScript required for drag-and-drop functionality. **Run this command after installing or updating the package**, otherwise the kanban board will load but drag-and-drop will not work.

## Filament Theme CSS

If you are using a [custom Filament theme](https://filamentphp.com/docs/4.x/styling/overview), you need to add the following lines to your theme's CSS file (e.g. `resources/css/filament/admin/theme.css`) so Tailwind scans the package views for classes:

```css
@source '../../../../vendor/buzkall/finisterre/resources/views';
@source '../../../../vendor/relaticle/flowforge/resources/views';
```

The package comes with a default policy for the tasks that can be overridden in the config file and set your own policy

```php
'model_policy' => Buzkall\Finisterre\Policies\FinisterreTaskPolicy::class,
``` 

## Usage

Add the plugin to your panel provider. By default every authenticated user can view all tasks — no closure needed:

```php
use Buzkall\Finisterre\FinisterrePlugin;

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
    ->userCanScheduleComments(fn() => auth()->user()?->hasRole(RoleEnum::Admin)),
```

### Displaying a user's full name

`finisterre.authenticatable_attribute` accepts either a single column (default `'name'`) or an array of columns to concatenate for display:

```php
'authenticatable_attribute' => ['name', 'lastname'],
```

With an array, the package shows `"John Doe"` in every user-facing select (task assignee, filter, kanban, comment notify list) and uses `CONCAT_WS(' ', ...)` for SQL-level selects. Columns must exist on the `users` table.

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

Any model in your app can let users open a Finisterre task against a specific record. The task stores a polymorphic `subject` pointing back to that record, and the task form shows a link to it.

First publish and run the migration that adds the `subject` columns to the tasks table:

```bash
php artisan vendor:publish --tag="finisterre-migrations"
php artisan migrate
```

Make the model implement `FinisterreReportable`. The `InteractsWithFinisterreReports` trait provides sensible defaults (a `ClassName (#id)` label and a link inferred from the model's Filament resource edit/view page):

```php
use Buzkall\Finisterre\Contracts\FinisterreReportable;
use Buzkall\Finisterre\Traits\InteractsWithFinisterreReports;

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

Then add `ReportIssueAction` wherever the record is available (a resource page, table row, infolist, etc.). It opens a modal asking for a title, description and attachments (images, PDF and videos up to 3&nbsp;MB), and associates the created task with the record:

```php
use Buzkall\Finisterre\Filament\Actions\ReportIssueAction;

ReportIssueAction::make();
```

## Role restriction for Task Reports

TODO

## Development

To build the CSS assets after making changes to Tailwind classes:

```bash
npm run build:styles
```

## Testing

```bash
composer test
```
