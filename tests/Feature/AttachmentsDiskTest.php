<?php

use Arzcode\Finisterre\Support\AttachmentsDisk;
use Illuminate\Support\Facades\File;

beforeEach(function() {
    $this->dir = storage_path('framework/testing/attachments-disk');

    File::ensureDirectoryExists($this->dir);
});

afterEach(function() {
    File::deleteDirectory($this->dir);
});

function configFixture(string $dir, string $name, string $contents): string
{
    $path = $dir . '/' . $name;

    file_put_contents($path, $contents);

    return $path;
}

it('adds the private disk to a stock config/filesystems.php', function() {
    $path = configFixture($this->dir, 'filesystems.php', (string)file_get_contents(base_path('config/filesystems.php')));

    expect(AttachmentsDisk::addDiskTo($path))->toBeTrue();

    $config = require $path;

    expect(array_keys($config['disks']))->toBe(['local', 'public', 's3', 'finisterre'])
        ->and($config['disks']['finisterre']['driver'])->toBe('local')
        ->and($config['disks']['finisterre']['root'])->toBe(storage_path('app/finisterre-files'))
        ->and($config['disks']['finisterre']['url'])->toEndWith('/storage/finisterre-files')
        ->and($config['links'])->toHaveCount(1);
});

it('finds the disks array past comments and strings full of brackets and quotes', function() {
    $path = configFixture($this->dir, 'filesystems.php', <<<'PHP'
        <?php

        return [
            /*
             | La Cupida's portraits: [not] an array.
             */
            'default' => 'local', // it's ['local']

            'disks' => [
                'local' => ['driver' => 'local', 'root' => '/tmp/[x]'],
                // 'finisterre' => ['driver' => 'local'],
                'public' => ['driver' => 'local'] // no trailing comma
            ],

            'links' => [],
        ];
        PHP);

    expect(AttachmentsDisk::addDiskTo($path))->toBeTrue();

    $config = require $path;

    expect(array_keys($config['disks']))->toBe(['local', 'public', 'finisterre'])
        ->and($config['disks']['local']['root'])->toBe('/tmp/[x]')
        ->and($config['default'])->toBe('local')
        ->and($config['links'])->toBe([]);
});

it('leaves a filesystems config that already has the disk untouched', function() {
    $contents = "<?php\n\nreturn [\n    'disks' => [\n        'finisterre' => ['driver' => 'local'],\n    ],\n];\n";
    $path = configFixture($this->dir, 'filesystems.php', $contents);

    expect(AttachmentsDisk::addDiskTo($path))->toBeTrue()
        ->and(file_get_contents($path))->toBe($contents);
});

it('reports a filesystems config it cannot patch instead of guessing', function() {
    $contents = "<?php\n\nreturn array('disks' => array());\n";
    $path = configFixture($this->dir, 'filesystems.php', $contents);

    expect(AttachmentsDisk::addDiskTo($path))->toBeFalse()
        ->and(file_get_contents($path))->toBe($contents)
        ->and(AttachmentsDisk::addDiskTo($this->dir . '/missing.php'))->toBeFalse();
});

it('points attachments_disk at the private disk and leaves commented-out lines alone', function() {
    $path = configFixture($this->dir, 'finisterre.php', <<<'PHP'
        <?php

        return [
            // 1. Change the 'attachments_disk' to 'finisterre'
            // 'attachments_disk' => 'other',
            'attachments_disk' => 'public', // finisterre
        ];
        PHP);

    expect(AttachmentsDisk::useDisk($path, 'finisterre'))->toBeTrue()
        ->and(require $path)->toBe(['attachments_disk' => 'finisterre'])
        ->and(file_get_contents($path))
        ->toContain("// 'attachments_disk' => 'other',")
        ->toContain("'attachments_disk' => 'finisterre', // finisterre");
});

it('reports a finisterre config without the key', function() {
    $path = configFixture($this->dir, 'finisterre.php', "<?php\n\nreturn [];\n");

    expect(AttachmentsDisk::useDisk($path, 'finisterre'))->toBeFalse()
        ->and(AttachmentsDisk::useDisk($this->dir . '/missing.php', 'finisterre'))->toBeFalse();
});
