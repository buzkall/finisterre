# Changelog

All notable changes to `finisterre` will be documented in this file.

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
