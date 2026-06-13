<?php

use Buzkall\Finisterre\Support\SettingsConfig;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        foreach (SettingsConfig::defaults() as $property => $value) {
            $this->migrator->add($property, $value);
        }
    }
};
