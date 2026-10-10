<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class ReceiptMoney
{
    public static function minor(string $amount, string $currency): int
    {
        if (! Money::isSupported($currency) || preg_match(Money::AMOUNT_PATTERN, $amount) !== 1) {
            throw ValidationException::withMessages(['total' => __('receipts.invalid_amount')]);
        }
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        $precision = Money::exponent($currency);
        if (trim(substr($fraction, $precision), '0') !== '') {
            throw ValidationException::withMessages(['total' => __('receipts.invalid_amount')]);
        }
        $minor = (int) ($whole.str_pad(substr($fraction, 0, $precision), $precision, '0'));
        if ($minor <= 0 || $minor > Money::MAX_MINOR) {
            throw ValidationException::withMessages(['total' => __('receipts.invalid_amount')]);
        }

        return $minor;
    }
}
