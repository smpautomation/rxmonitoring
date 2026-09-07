<?php

namespace App\Services;

use App\Models\Personnel;
use InvalidArgumentException;

/**
 * Badge QR codes look like:
 *   01;10293:Juan Dela Cruz     -> PIC
 *   00;88214:Maria Santos       -> operator
 *
 * Any control that needs a "who did this" name in the RX Monitoring flow
 * scans through here rather than accepting a typed name, so every action is
 * attributable to a real badge instead of whatever an operator types.
 */
class PersonnelScanner
{
    public const ROLE_MAP = [
        '00' => 'operator',
        '01' => 'pic',
    ];

    /**
     * @throws InvalidArgumentException if the code doesn't match the expected
     *                                   format or prefix.
     */
    public static function resolve(string $rawCode): Personnel
    {
        $code = trim($rawCode);

        if (! preg_match('/^(\d{2});([A-Za-z0-9\-]+):(.+)$/', $code, $m)) {
            throw new InvalidArgumentException(
                'Unrecognized badge format. Please scan the QR code on the ID again.'
            );
        }

        [, $prefix, $employeeId, $name] = $m;

        if (! isset(self::ROLE_MAP[$prefix])) {
            throw new InvalidArgumentException(
                "Badge prefix \"{$prefix}\" is not a recognized PIC or operator badge."
            );
        }

        return Personnel::updateOrCreate(
            ['employee_id' => $employeeId],
            [
                'name' => trim($name),
                'role' => self::ROLE_MAP[$prefix],
                'is_active' => true,
                'last_scanned_at' => now(),
            ]
        );
    }

    public static function requirePic(string $rawCode): Personnel
    {
        $person = self::resolve($rawCode);

        if (! $person->isPic()) {
            throw new InvalidArgumentException(
                "{$person->name}'s badge is an operator badge. A PIC badge is required for this step."
            );
        }

        return $person;
    }
}
