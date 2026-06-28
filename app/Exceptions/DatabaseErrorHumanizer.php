<?php

namespace App\Exceptions;

use Illuminate\Database\QueryException;

class DatabaseErrorHumanizer
{
    private const ERROR_MESSAGES = [
        1062 => 'A record with this value already exists.',
        1216 => 'The related record was not found.',
        1217 => 'This record cannot be deleted because it is linked to other records.',
        1451 => 'This record cannot be deleted because it is linked to other records.',
        1452 => 'The related record was not found.',
        1048 => 'Please fill in all required fields.',
        1364 => 'Please fill in all required fields.',
        1406 => 'The value entered is too long for one of the fields.',
        1265 => 'The value entered is not in the correct format.',
    ];

    private const UNIQUE_KEY_MESSAGES = [
        'customers_passport_no_unique' => 'A customer with this passport number already exists.',
        'customers_iqama_no_unique' => 'A customer with this Iqama number already exists.',
        'passengers_passport_no_unique' => 'A passenger with this passport number already exists.',
        'users_email_unique' => 'A user with this email address already exists.',
        'users_username_unique' => 'A user with this username already exists.',
        'banks_name_unique' => 'A bank with this name already exists.',
        'branches_name_unique' => 'A branch with this name already exists.',
        'districts_name_unique' => 'A district with this name already exists.',
    ];

    public static function humanize(QueryException $e): string
    {
        $message = $e->getMessage();

        $errorCode = self::extractMySqlErrorCode($message);

        if ($errorCode === null) {
            return 'An unexpected database error occurred. Please try again.';
        }

        if ($errorCode === 1062) {
            $specific = self::getDuplicateEntryMessage($message);
            if ($specific !== null) {
                return $specific;
            }
        }

        return self::ERROR_MESSAGES[$errorCode] ?? 'An unexpected database error occurred. Please try again.';
    }

    private static function extractMySqlErrorCode(string $message): ?int
    {
        if (preg_match('/\b(\d{4})\s/', $message, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    private static function getDuplicateEntryMessage(string $message): ?string
    {
        if (preg_match("/for key '([^']+)'/", $message, $matches)) {
            $key = $matches[1];

            if (isset(self::UNIQUE_KEY_MESSAGES[$key])) {
                return self::UNIQUE_KEY_MESSAGES[$key];
            }

            $readable = self::keyToReadableName($key);
            if ($readable !== null) {
                return "A record with this {$readable} already exists.";
            }
        }

        return null;
    }

    private static function keyToReadableName(string $key): ?string
    {
        $key = preg_replace('/_(unique|index|foreign)$/', '', $key);

        $parts = explode('_', $key);

        if (count($parts) > 1) {
            array_shift($parts);
        }

        if (empty($parts)) {
            return null;
        }

        return implode(' ', $parts);
    }
}
