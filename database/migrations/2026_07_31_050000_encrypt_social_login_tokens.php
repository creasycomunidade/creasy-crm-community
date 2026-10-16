<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('social_accounts')) {
            return;
        }

        Schema::table('social_accounts', function (Blueprint $table): void {
            $table->text('access_token')->nullable()->change();
            $table->text('refresh_token')->nullable()->change();
        });

        DB::table('social_accounts')
            ->select(['id', 'access_token', 'refresh_token'])
            ->orderBy('id')
            ->chunkById(100, function ($accounts): void {
                foreach ($accounts as $account) {
                    $updates = [];
                    foreach (['access_token', 'refresh_token'] as $field) {
                        $value = $account->{$field};
                        if ($value !== null && $value !== '' && ! $this->isEncrypted($value)) {
                            $updates[$field] = Crypt::encryptString($value);
                        }
                    }
                    if ($updates !== []) {
                        DB::table('social_accounts')->where('id', $account->id)->update($updates);
                    }
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('social_accounts')) {
            return;
        }

        DB::table('social_accounts')
            ->select(['id', 'access_token', 'refresh_token'])
            ->orderBy('id')
            ->chunkById(100, function ($accounts): void {
                foreach ($accounts as $account) {
                    $updates = [];
                    foreach (['access_token', 'refresh_token'] as $field) {
                        $value = $account->{$field};
                        if ($value !== null && $value !== '' && $this->isEncrypted($value)) {
                            $updates[$field] = Crypt::decryptString($value);
                        }
                    }
                    if ($updates !== []) {
                        DB::table('social_accounts')->where('id', $account->id)->update($updates);
                    }
                }
            });
    }

    private function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
};
