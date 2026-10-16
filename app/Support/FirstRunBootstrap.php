<?php

namespace App\Support;

/** Fresh public downloads contain no .env and no reusable installation keys. */
final class FirstRunBootstrap
{
    public static function prepare(string $root): void
    {
        if (is_file($root.'/.env') || is_file($root.'/storage/installed.lock')) {
            return;
        }
        foreach (['storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'storage/app/private', 'storage/app/public', 'bootstrap/cache', 'public/storage'] as $dir) {
            if (!is_dir($root.'/'.$dir) && !mkdir($root.'/'.$dir, 0775, true) && !is_dir($root.'/'.$dir)) {
                throw new \RuntimeException('Permita escrita nas pastas storage, bootstrap/cache e public/storage.');
            }
        }
        $lock = fopen($root.'/storage/framework/bootstrap.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX)) {
            throw new \RuntimeException('Não foi possível preparar a instalação.');
        }
        try {
            if (is_file($root.'/.env')) return;
            $contents = file_get_contents($root.'/.env.example');
            if ($contents === false) throw new \RuntimeException('Arquivo .env.example ausente.');
            $token = bin2hex(random_bytes(32));
            $values = ['APP_KEY' => 'base64:'.base64_encode(random_bytes(32)), 'INSTALL_TOKEN' => $token,
                'APP_INSTALLED' => 'false', 'APP_DEBUG' => 'false', 'PUBLIC_STORAGE_DIRECT' => 'true'];
            foreach ($values as $key => $value) {
                $line = $key.'='.$value;
                $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
                $contents = preg_match($pattern, $contents) ? preg_replace_callback($pattern, fn () => $line, $contents) : $contents."\n".$line."\n";
            }
            $keyFile = $root.'/storage/app/private/installation-key.txt';
            if (file_put_contents($keyFile, "Chave privada de instalação CREASY CRM:\n".$token."\nLeia pelo gerenciador de arquivos da hospedagem. Não publique este arquivo.\n", LOCK_EX) === false) {
                throw new \RuntimeException('Não foi possível gravar a chave privada de instalação.');
            }
            @chmod($keyFile, 0600);
            $temporary = $root.'/.env.preparing';
            if (file_put_contents($temporary, $contents, LOCK_EX) === false) throw new \RuntimeException('Permita escrita na pasta do projeto para criar .env.');
            @chmod($temporary, 0600);
            if (!rename($temporary, $root.'/.env')) throw new \RuntimeException('Não foi possível criar .env.');
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
