<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([PermissionSeeder::class, RoleSeeder::class, CurrencySeeder::class, LocaleSeeder::class, TranslationSeeder::class, EmailTemplateSeeder::class, \App\Modules\Integrations\Database\Seeders\IntegrationConfigSeeder::class]);
    }
}
