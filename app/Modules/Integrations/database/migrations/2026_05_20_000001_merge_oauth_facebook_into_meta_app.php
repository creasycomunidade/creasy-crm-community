<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Load the raw rows (credentials column is encrypted — decrypt manually)
        $metaRow = DB::table('integration_configs')->where('provider', 'meta_app')->first();
        $fbRow   = DB::table('integration_configs')->where('provider', 'oauth_facebook')->first();

        if ($fbRow) {
            try {
                $fbCreds = json_decode(decrypt($fbRow->credentials), true) ?? [];
            } catch (\Throwable) {
                $fbCreds = [];
            }

            if (! empty($fbCreds['client_id']) || ! empty($fbCreds['client_secret'])) {
                if ($metaRow) {
                    try {
                        $metaCreds = json_decode(decrypt($metaRow->credentials), true) ?? [];
                    } catch (\Throwable) {
                        $metaCreds = [];
                    }

                    // Only copy over fields that are not already set in meta_app
                    if (empty($metaCreds['app_id']) && ! empty($fbCreds['client_id'])) {
                        $metaCreds['app_id'] = $fbCreds['client_id'];
                    }
                    if (empty($metaCreds['app_secret']) && ! empty($fbCreds['client_secret'])) {
                        $metaCreds['app_secret'] = $fbCreds['client_secret'];
                    }

                    DB::table('integration_configs')
                        ->where('provider', 'meta_app')
                        ->update(['credentials' => encrypt(json_encode($metaCreds))]);
                } else {
                    // No meta_app row yet — create one from oauth_facebook data
                    DB::table('integration_configs')->insert([
                        'provider'    => 'meta_app',
                        'label'       => 'Meta App (WhatsApp / Instagram / Messenger / Facebook)',
                        'mode'        => 'live',
                        'enabled'     => $fbRow->enabled,
                        'credentials' => encrypt(json_encode([
                            'app_id'     => $fbCreds['client_id'] ?? '',
                            'app_secret' => $fbCreds['client_secret'] ?? '',
                        ])),
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);
                }
            }

            // Remove the now-redundant oauth_facebook record
            DB::table('integration_configs')->where('provider', 'oauth_facebook')->delete();
        }
    }

    public function down(): void
    {
        // Non-reversible data migration — credentials would need to be re-entered manually
    }
};
