<?php

use Arzcode\Finisterre\Support\SettingsConfig;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        SettingsConfig::encryptStoredSmsAuthKey();
    }
};
