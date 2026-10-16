<?php

namespace App\Modules\Whatsapp\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappAutoReply extends Model
{
    protected $table = 'whatsapp_auto_replies';

    protected $fillable = [
        'workspace_id', 'channel_account_id', 'trigger_type', 'match_mode',
        'keywords', 'schedule_json', 'response_kind', 'payload_json', 'enabled', 'priority',
    ];

    protected function casts(): array
    {
        return [
            'keywords' => 'array',
            'schedule_json' => 'array',
            'payload_json' => 'array',
            'enabled' => 'boolean',
        ];
    }

    public function matchesMessage(string $body): bool
    {
        if ($this->trigger_type !== 'keyword') {
            return false;
        }
        $keywords = $this->keywords ?? [];
        foreach ($keywords as $kw) {
            $match = match ($this->match_mode) {
                'exact' => strtolower(trim($body)) === strtolower($kw),
                'contains' => str_contains(strtolower($body), strtolower($kw)),
                'regex' => self::safeRegexMatch($kw, $body),
                default => false,
            };
            if ($match) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validates the regex pattern first to prevent ReDoS and invalid-pattern errors.
     * Returns false when the pattern is invalid or on match failure.
     */
    public static function safeRegexMatch(string $pattern, string $subject): bool
    {
        // Reject blank patterns
        if ($pattern === '') {
            return false;
        }

        // Test that the pattern compiles without error before using it on real input
        set_error_handler(static fn () => true);
        $test = preg_match('/'.$pattern.'/i', '');
        restore_error_handler();

        if ($test === false || preg_last_error() !== PREG_NO_ERROR) {
            return false;
        }

        return (bool) @preg_match('/'.$pattern.'/i', $subject);
    }

    /** Returns true when $pattern is a valid PCRE pattern (used for input validation). */
    public static function isValidRegex(string $pattern): bool
    {
        if ($pattern === '') {
            return false;
        }
        set_error_handler(static fn () => true);
        $result = preg_match('/'.$pattern.'/i', '');
        restore_error_handler();

        return $result !== false && preg_last_error() === PREG_NO_ERROR;
    }
}
