<?php

namespace App\Enums;

enum AdPlatform: string
{
    case Meta = 'meta';
    case Google = 'google';
    case TikTok = 'tiktok';
    case LinkedIn = 'linkedin';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Meta => 'Meta (Facebook/Instagram)',
            self::Google => 'Google Ads',
            self::TikTok => 'TikTok Ads',
            self::LinkedIn => 'LinkedIn Ads',
            self::Other => 'Otra',
        };
    }
}
