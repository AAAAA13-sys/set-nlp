<?php

declare(strict_types=1);

namespace App\Services;

/**
 * One-time credentials for accounts created by the Registrar (roster import or manual creation)
 * and the password policy enforced when a user replaces them.
 *
 * Temporary passwords are random per account, returned to the administrator exactly once,
 * stored only as bcrypt hashes, and flagged `must_change_password = 1` until replaced.
 */
final class TemporaryCredential
{
    public const MIN_LENGTH = 10;
    public const MAX_LENGTH = 128;

    /** Unambiguous characters only (no 0/O, 1/l/I) so printed slips are easy to type. */
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';

    public static function generate(int $length = 12): string
    {
        $max = strlen(self::ALPHABET) - 1;
        do {
            $password = '';
            for ($i = 0; $i < $length; $i++) {
                $password .= self::ALPHABET[random_int(0, $max)];
            }
        } while (preg_match('/[A-Za-z]/', $password) !== 1 || preg_match('/\d/', $password) !== 1);

        return $password;
    }

    /**
     * Returns a user-facing reason when $candidate is not acceptable, or null when it is.
     */
    public static function policyViolation(string $candidate, string $institutionalId = '', string $email = ''): ?string
    {
        $length = strlen($candidate);
        if ($length < self::MIN_LENGTH) {
            return 'Use at least ' . self::MIN_LENGTH . ' characters.';
        }
        if ($length > self::MAX_LENGTH) {
            return 'Use at most ' . self::MAX_LENGTH . ' characters.';
        }
        if (preg_match('/[A-Za-z]/', $candidate) !== 1 || preg_match('/\d/', $candidate) !== 1) {
            return 'Include at least one letter and one number.';
        }

        $lower = strtolower($candidate);
        $emailLocal = strtolower((string) strstr($email, '@', true));
        foreach ([strtolower($institutionalId), $emailLocal] as $personal) {
            if ($personal !== '' && str_contains($lower, $personal)) {
                return 'Do not include your student number, employee ID, or email name.';
            }
        }

        return null;
    }
}
