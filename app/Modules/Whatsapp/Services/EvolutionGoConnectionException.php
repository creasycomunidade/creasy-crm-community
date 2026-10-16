<?php

namespace App\Modules\Whatsapp\Services;

use RuntimeException;
use Throwable;

class EvolutionGoConnectionException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function timeout(?Throwable $previous = null): self
    {
        return new self(
            'timeout',
            'O WhatsApp desta conexão não respondeu e pode estar desconectado. Acesse Configuração de canais, gere um novo QR Code e conecte novamente.',
            $previous,
        );
    }

    public static function disconnected(?Throwable $previous = null): self
    {
        return new self(
            'disconnected',
            'Esta conexão do WhatsApp foi desconectada. Acesse Configuração de canais, gere um novo QR Code e conecte novamente.',
            $previous,
        );
    }
}
