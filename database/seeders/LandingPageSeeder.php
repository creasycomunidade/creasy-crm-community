<?php

namespace Database\Seeders;

use App\Http\Controllers\Admin\LandingPageController;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LandingPageSeeder extends Seeder
{
    /**
     * Seed default marketing-site content into system_settings.
     *
     * Non-destructive: existing keys are never overwritten, so any content
     * an admin has customized via the "Site Content" manager is preserved
     * even when this seeder is re-run. Keys/defaults come from
     * LandingPageController::defaults() so they can never drift.
     */
    public function run(): void
    {
        $now = now();

        foreach (LandingPageController::defaults() as $key => $value) {
            $exists = DB::table('system_settings')->where('key', $key)->exists();
            if ($exists) {
                continue;
            }

            DB::table('system_settings')->insert([
                'key'        => $key,
                'value'      => $value,
                'is_secret'  => false,
                'group'      => 'landing',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
