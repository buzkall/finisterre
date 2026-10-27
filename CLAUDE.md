# Finisterre

Filament plugin (`arzcode/finisterre`) that adds task management to a panel. It is a Composer package, not an app:
the local environment is Orchestra Testbench (`workbench/`).

## Commands

```bash
composer test       # Pest
composer lint       # Rector, Pint, PHPStan (writes fixes)
composer ci:check   # what CI and the pre-push hook run: Rector dry run, Pint --test, PHPStan, Pest
composer format     # Pint only
```

Run `composer ci:check` before calling a change done. The pre-push hook blocks the push when it fails.

## PHP version

The package supports PHP 8.3 (`composer.json`, CI matrix). Do not chain on `new` without wrapping parentheses:
`new Foo()->bar()` is a parse error on 8.3. Write `(new Foo)->bar()`.

## Code style

Pint (`pint.json`) enforces rules that differ from Laravel's defaults: no trailing commas in multiline, aligned
`=>`, `concat_space: one`, no space after casts. Pint drops unused imports, so if a formatter runs on save, add a
`use` statement in the same edit as the code that needs it.

## CHANGELOG

Every change gets an entry at the top of `CHANGELOG.md`: a bumped version (patch for fixes, minor for features),
today's date as `YYYY-MM-DD`, and a short description written for the package's users, in the style of the
existing entries. When an upgrade needs `php artisan migrate` (a new migration), say so in bold. Tags are cut from
these versions.

## Migrations and settings

An upgrade has to work after `composer update` and a plain `php artisan migrate`, with no `finisterre:update`.

- New migrations go in `database/auto/` as dated `.php` files (`2026_10_11_000000_…`), which the provider loads. Do not
  add `.php.stub` files to `database/migrations/` or names to `FinisterreServiceProvider::migrationNames()`: those are
  the migrations of versions up to 4.11, already published in host applications.
- Code must tolerate its migration not having run yet (see `TaskFollowers::available()`): a deploy runs the new code
  before `migrate`.
- A new setting needs a property in `FinisterreSettings`, a default in `SettingsConfig::defaults()` and its line in
  `applyStored()`. `SettingsConfig::apply()` seeds the missing row on boot, but only when loading fails: a property
  declared with a default (`= null`) loads without its row, and the settings page seeds it on save (`seedAdded()`).

## Translations

Every user-facing string is translatable. Add keys to `resources/lang/{ca,en,es}/finisterre.php` (and the
matching `*.json` files for JSON strings), in all three locales.

## Tests

- `tests/Feature/` uses the bare `TestCase` with no Filament panel.
- `tests/Filament/` uses `FilamentTestCase`, which boots a real panel (`tests/Support/TestPanelProvider.php`) with
  the locale forced to `es`. Page and Livewire tests go here.
- Use `Livewire::test()`. The Pest Livewire plugin is not installed.
- Keep the locale at `es` or `ca`: tag names are only translated into `config('finisterre.locales')`, so with `en`
  the tag select gets empty labels and validation fails.
- Set `finisterre.active` again in `setUp()`, because the provider's config merge runs after
  `getEnvironmentSetUp()`.
- A test that requests a panel page over HTTP (`$this->get('/admin/…')`) needs `config()->set('app.env', 'local')`.
  Outside the local environment, Filament's `Authenticate` middleware returns 403 for users that don't implement
  `FilamentUser`, and the workbench `User` doesn't.
- When a change adds a conditional branch, make sure some test takes it.
