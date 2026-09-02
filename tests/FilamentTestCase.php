<?php

namespace Arzcode\Finisterre\Tests;

use Arzcode\Finisterre\FinisterreServiceProvider;
use Arzcode\Finisterre\Tests\Support\TestPanelProvider;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Workbench\App\Models\User;

/**
 * Boots a real Filament panel with the plugin registered, for tests that
 * render the package's pages through Livewire. The plain TestCase deliberately
 * leaves Filament out, so keep using it for everything that does not need a
 * panel.
 */
abstract class FilamentTestCase extends TestCase
{
    protected $enablesPackageDiscoveries = true;

    protected function getPackageProviders($app)
    {
        return [
            ...parent::getPackageProviders($app),
            FinisterreServiceProvider::class,
            TestPanelProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        config()->set([
            'finisterre.active'                     => true,
            'finisterre.panel_slug'                 => 'admin',
            'finisterre.slug'                       => 'tasks',
            'finisterre.authenticatable'            => User::class,
            'finisterre.authenticatable_table_name' => 'users',
            'finisterre.attachments_disk'           => 'public',
            'finisterre.comments.table_name'        => 'finisterre_task_comments',
            'finisterre.subtasks.table_name'        => 'finisterre_subtasks',
            'finisterre.task_changes_table_name'    => 'finisterre_task_changes',
            'media-library.media_model'             => Media::class,
        ]);

        $this->createSupportingTables();
    }

    protected function setUp(): void
    {
        parent::setUp();

        // The provider merges the config file after getEnvironmentSetUp() ran,
        // so re-assert the values the pages read at request time.
        config()->set('finisterre.active', true);

        // Tags are translated into the package locales (es, ca); with any
        // other locale their labels come back empty and the select drops them.
        app()->setLocale('es');

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    protected function createTableIfMissing(string $table, \Closure $blueprint): void
    {
        if (! Schema::hasTable($table)) {
            Schema::create($table, $blueprint);
        }
    }

    /**
     * The base migration only creates the tasks and comments tables; the rest
     * ship as separate published migrations.
     */
    protected function createSupportingTables(): void
    {
        if (! Schema::hasColumn('finisterre_tasks', 'archived')) {
            Schema::table('finisterre_tasks', function(Blueprint $table) {
                $table->boolean('archived')->default(false);
            });
        }

        $this->createTableIfMissing('finisterre_subtasks', function(Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('finisterre_tasks')->cascadeOnDelete();
            $table->string('title');
            $table->boolean('completed')->default(false);
            $table->unsignedInteger('order_column')->nullable();
            $table->timestamps();
        });

        $this->createTableIfMissing('finisterre_task_changes', function(Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('finisterre_tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $this->createTableIfMissing('finisterre_task_comments', function(Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('finisterre_tasks')->cascadeOnDelete();
            $table->longText('comment');
            $table->dateTime('scheduled_for')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->json('notify_user_ids')->nullable();
            $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $this->createTableIfMissing('tags', function(Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->json('slug');
            $table->string('type')->nullable();
            $table->integer('order_column')->nullable();
            $table->timestamps();
        });

        $this->createTableIfMissing('taggables', function(Blueprint $table) {
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->morphs('taggable');
        });

        $this->createTableIfMissing('media', function(Blueprint $table) {
            $table->id();
            $table->morphs('model');
            $table->uuid('uuid')->nullable()->unique();
            $table->string('collection_name');
            $table->string('name');
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->string('disk');
            $table->string('conversions_disk')->nullable();
            $table->unsignedBigInteger('size');
            $table->json('manipulations');
            $table->json('custom_properties');
            $table->json('generated_conversions');
            $table->json('responsive_images');
            $table->unsignedInteger('order_column')->nullable()->index();
            $table->timestamps();
        });
    }
}
