<?php

namespace App\Services;

class WhatsAppPhoneNormalizer
{
    public function normalize(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (strlen($digits) === 10) {
            $digits = config('whatsapp.default_country_code', '52').$digits;
        }

        return strlen($digits) >= 11 && strlen($digits) <= 15 ? $digits : null;
    }
}
