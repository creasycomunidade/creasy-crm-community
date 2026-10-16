<?php

namespace App\Services\Install;

/**
 * Upserts KEY=value pairs into the project's .env file without clobbering other
 * lines. Existing (uncommented) keys are replaced in place; missing keys are
 * appended. Values that contain whitespace or shell-significant characters are
 * wrapped in double quotes and escaped, so passwords and app names survive a
 * round-trip through Dotenv.
 */
class EnvWriter
{
    private string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? base_path('.env');
    }

    /**
     * @param  array<string, string|int|bool|null>  $pairs
     */
    public function set(array $pairs): void
    {
        $contents = is_file($this->path) ? (string) file_get_contents($this->path) : '';

        foreach ($pairs as $key => $value) {
            $line = $key.'='.$this->format($this->stringify($value));
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

            if (preg_match($pattern, $contents)) {
                // Replace via callback so a value containing $1, \0, etc. is never
                // interpreted as a regex backreference.
                $contents = preg_replace_callback($pattern, fn () => $line, $contents, 1);
            } else {
                $contents = ($contents === '' ? '' : rtrim($contents, "\n")."\n").$line."\n";
            }
        }

        if (file_put_contents($this->path, $contents, LOCK_EX) === false) {
            throw new \RuntimeException('Não foi possível gravar o arquivo .env.');
        }
    }

    private function stringify(string|int|bool|null $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) ($value ?? '');
    }

    /**
     * Quote and escape a value when it contains characters that would otherwise
     * break .env parsing (whitespace, quotes, comment markers, interpolation).
     */
    private function format(string $value): string
    {
        if ($value === '') {
            return '';
        }
        return '"'.str_replace(['\\', '"', '$', "\r", "\n"], ['\\\\', '\\"', '\\$', '\\r', '\\n'], $value).'"';
    }
}
