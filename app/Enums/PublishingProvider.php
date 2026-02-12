<?php

namespace App\Enums;

enum PublishingProvider: string
{
    case Direct = 'direct';
    case Webhook = 'webhook';
    case GetLate = 'getlate';

    public function label(): string
    {
        return match ($this) {
            self::Direct => 'Direct API',
            self::Webhook => 'n8n Webhook',
            self::GetLate => 'GetLate',
        };
    }
}
