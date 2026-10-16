<?php

namespace App\Console\Commands;

use Database\Seeders\LocaleSeeder;
use Database\Seeders\TranslationSeeder;
use Illuminate\Console\Command;

class I18nSeedDefaultsCommand extends Command
{
    protected $signature = 'i18n:seed-defaults';

    protected $description = 'Seed 4 default locales (en, bn, ar, hi) and sync translations from codebase';

    public function handle(): int
    {
        $this->info('Seeding locales...');
        $this->call(LocaleSeeder::class);

        $this->info('Seeding/syncing translations...');
        $seeder = new TranslationSeeder;
        $seeder->setCommand($this);
        $seeder->run();

        $this->info('Done. Run i18n:scan --sync anytime to add new keys from code.');

        return self::SUCCESS;
    }
}
