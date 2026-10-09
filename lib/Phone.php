<?php
declare(strict_types=1);

/**
 * Phone numbers. Everything is stored in international form (E.164): "+" then the country code then the number,
 * 8 to 15 digits in all, for example +237612345678.
 *
 * What people type is more varied than that: "6 12 34 56 78", "0612345678", "00237 612 345 678", "+237-6-12-34-56-78".
 * normalize() turns those into one form. A number with no country code gets the default one (SMS_DEFAULT_COUNTRY,
 * 237 for Cameroon).
 */
final class Phone
{
    public static function defaultCountry(): string
    {
        $cc = defined('SMS_DEFAULT_COUNTRY') ? (string)SMS_DEFAULT_COUNTRY : '237';
        return preg_match('/^[1-9]\d{0,2}$/', $cc) === 1 ? $cc : '237';
    }

    /** @return string|null the E.164 number, or null when the input cannot be a phone number */
    public static function normalize(string $raw, ?string $defaultCc = null): ?string
    {
        $defaultCc ??= self::defaultCountry();
        $s = trim($raw);
        if ($s === '' || mb_strlen($s) > 30) {
            return null;
        }
        // Only digits, spaces and the usual separators are accepted, with an optional leading + or 00
        if (preg_match('/^\+?[\d\s().\-]+$/', $s) !== 1) {
            return null;
        }
        $hasPlus = str_starts_with($s, '+');
        $digits = preg_replace('/\D+/', '', $s) ?? '';
        if ($digits === '') {
            return null;
        }
        if (!$hasPlus && str_starts_with($digits, '00')) {
            $hasPlus = true;
            $digits = substr($digits, 2);
        }
        if (!$hasPlus) {
            if (str_starts_with($digits, $defaultCc) && strlen($digits) >= strlen($defaultCc) + 8) {
                // "237612345678" typed without the plus
            } else {
                $digits = $defaultCc . ltrim($digits, '0');
            }
        }
        $e164 = '+' . $digits;
        if (preg_match('/^\+[1-9]\d{7,14}$/', $e164) !== 1) {
            return null;
        }
        // Cameroon numbers are 9 digits after the country code
        if (str_starts_with($e164, '+237') && strlen($e164) !== 13) {
            return null;
        }
        return $e164;
    }

    /** "+237 6•• ••• 678": enough for the owner to recognise their number, not enough to read it out loud. */
    public static function mask(string $e164): string
    {
        if (strlen($e164) < 8) {
            return $e164;
        }
        return substr($e164, 0, 4) . ' ' . substr($e164, 4, 1) . '•• ••• ' . substr($e164, -3);
    }
}
