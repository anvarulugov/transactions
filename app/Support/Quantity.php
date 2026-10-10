<?php

namespace App\Support;

use InvalidArgumentException;

class Quantity
{
    public const UNITS = ['kg', 'litr', 'dona'];

    public static function unit(?string $quantity, ?string $unit): ?string
    {
        if ($quantity === null) {
            if ($unit !== null) {
                throw new InvalidArgumentException('Quantity is required with a unit.');
            }

            return null;
        }

        if (! in_array($unit, self::UNITS, true)) {
            throw new InvalidArgumentException('Invalid quantity unit.');
        }

        return $unit;
    }

    public const PATTERN = '/^(?:0|[1-9]\d{0,8})(?:\.\d{1,3})?$/D';

    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (preg_match(self::PATTERN, $value) !== 1 || preg_match('/^0(?:\.0+)?$/D', $value) === 1) {
            throw new InvalidArgumentException('Quantity must be positive kilograms with up to three decimals.');
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return $whole.'.'.str_pad($fraction, 3, '0');
    }
}
