<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Workbench\App\Models\User;

uses(RefreshDatabase::class);

beforeEach(function() {
    config([
        'finisterre.active'                        => false,
        'finisterre.authenticatable'               => User::class,
        'finisterre.authenticatable_table_name'    => 'users',
        'finisterre.authenticatable_attribute'     => 'name',
        'finisterre.authenticatable_filter_column' => '',
        'finisterre.authenticatable_filter_value'  => '',
    ]);
});

it('returns getUserDisplayName from the configured column', function() {
    $user = User::factory()->create(['name' => 'Ada Lovelace']);

    expect($user->getUserDisplayName())->toBe('Ada Lovelace');
});

it('returns getUserNameColumn from the configured column', function() {
    config(['finisterre.authenticatable_attribute' => 'name']);
    expect((new User)->getUserNameColumn())->toBe('name');
});

it('returns first column when attribute is an array', function() {
    config(['finisterre.authenticatable_attribute' => ['first_name', 'last_name']]);
    expect((new User)->getUserNameColumn())->toBe('first_name');
});

it('concatenates multiple columns when attribute is an array', function() {
    if (! Schema::hasColumn('users', 'first_name')) {
        Schema::table('users', function(Blueprint $table) {
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
        });
    }

    config(['finisterre.authenticatable_attribute' => ['first_name', 'last_name']]);

    $user = User::factory()->create();
    $user->forceFill(['first_name' => 'Grace', 'last_name' => 'Hopper'])->save();

    expect($user->getUserDisplayName())->toBe('Grace Hopper');
});

it('returns canArchiveTasks=false when package is inactive', function() {
    config(['finisterre.active' => false]);
    $user = User::factory()->create();

    expect($user->canArchiveTasks())->toBeFalse();
});

it('returns canArchiveTasks=true when package is active', function() {
    config(['finisterre.active' => true]);
    $user = User::factory()->create();

    expect($user->canArchiveTasks())->toBeTrue();
});

it('does not filter assignable users when no filter is configured', function() {
    User::factory()->count(3)->create();

    expect(User::assignableUsers()->count())->toBeGreaterThanOrEqual(3);
});

it('filters assignable users by configured column', function() {
    if (! Schema::hasColumn('users', 'role')) {
        Schema::table('users', function(Blueprint $table) {
            $table->string('role')->nullable();
        });
    }

    User::factory()->create(['name' => 'admin'])->forceFill(['role' => 'admin'])->save();
    User::factory()->create(['name' => 'user'])->forceFill(['role' => 'user'])->save();

    config([
        'finisterre.authenticatable_filter_column' => 'role',
        'finisterre.authenticatable_filter_value'  => 'admin',
    ]);

    $names = User::assignableUsers()->pluck('name')->all();
    expect($names)->toContain('admin')
        ->and($names)->not->toContain('user');
});

it('filters out inactive users when active column exists', function() {
    if (! Schema::hasColumn('users', 'active')) {
        Schema::table('users', function(Blueprint $table) {
            $table->boolean('active')->default(true);
        });
    }

    User::factory()->create(['name' => 'on'])->forceFill(['active' => true])->save();
    User::factory()->create(['name' => 'off'])->forceFill(['active' => false])->save();

    $names = User::assignableUsers()->pluck('name')->all();
    expect($names)->toContain('on')
        ->and($names)->not->toContain('off');
});
