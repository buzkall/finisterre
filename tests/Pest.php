<?php

use Arzcode\Finisterre\Tests\FilamentTestCase;
use Arzcode\Finisterre\Tests\TestCase;

uses(TestCase::class)->in('Feature', 'ArchTest.php', 'FilamentResourceTest.php');

// Pages rendered through a real Filament panel.
uses(FilamentTestCase::class)->in('Filament');
