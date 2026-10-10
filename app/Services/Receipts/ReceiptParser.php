<?php

namespace App\Services\Receipts;

class ReceiptParser
{
    /** @return array{items: array<int, array{name: string, quantity: ?string, quantity_unit: ?string, amount: string}>, total: ?string, currency: string} */
    public function parse(string $text): array
    {
        $items = [];
        $total = null;
        $pendingName = '';
        $money = '(?:\d{1,3}(?:[ \x{00a0},]\d{3})+|\d+)(?:[.,]\d{2})?';
        foreach (preg_split('/\R/u', $text) ?: [] as $raw) {
            $line = trim($raw);
            if ($line === '') {
                continue;
            }
            if (preg_match('/(?:jami|итого|итог|total|жами|к оплате|umumiy)(?=\s|[:,=]|$).*?('.$money.')\s*(?:UZS|сум|so.m)?\s*$/iu', $line, $matches)) {
                $total = $this->amount($matches[1]);
                $pendingName = '';

                continue;
            }
            if (preg_match('/(?:ИНН|STIR|ТЕЛ|TEL\b|ФИСК|QR\b|НДС|QQS|КАССИР|ОПЛАТА|НАХТ|NALICH|СКИДКА|СДАЧА|PAYMENT|DISCOUNT|CASH|CARD|БАНК|ТЕРМИНАЛ|ЧЕК\s*№|CHEK\s*№)/iu', $line)) {
                $pendingName = '';

                continue;
            }
            if (! preg_match('/('.$money.')\s*(?:UZS|сум|so.m)?\s*$/iu', $line, $amountMatch, PREG_OFFSET_CAPTURE)) {
                if (preg_match('/\p{L}/u', $line)) {
                    $pendingName = mb_substr($line, 0, 150);
                }

                continue;
            }
            $amount = $this->amount($amountMatch[1][0]);
            $prefix = trim(substr($line, 0, $amountMatch[0][1]));
            $quantity = null;
            $unit = null;
            $quantityPattern = '/(?:^|\s)(\d+(?:[.,]\d{1,3})?)\s*(kg|кг\.?|litr|литр\.?|л\.?|dona|дона|шт\.?|pcs)(?=\s|[xх×*=]|$)/iu';
            if (preg_match($quantityPattern, $prefix, $quantityMatch, PREG_OFFSET_CAPTURE)) {
                $quantity = str_replace(',', '.', $quantityMatch[1][0]);
                $unit = $this->unit($quantityMatch[2][0]);
                $prefix = trim(substr($prefix, 0, $quantityMatch[0][1]));
            } elseif (preg_match('/(?:^|\s)(\d+(?:[.,]\d{1,3})?)\s*[xх×*]\s*/iu', $prefix, $quantityMatch, PREG_OFFSET_CAPTURE)) {
                $quantity = str_replace(',', '.', $quantityMatch[1][0]);
                $prefix = trim(substr($prefix, 0, $quantityMatch[0][1]));
            }
            $name = trim(preg_replace('/^\d+[.)]\s*/u', '', $prefix) ?? $prefix);
            if (! preg_match('/\p{L}/u', $name)) {
                $name = $pendingName;
            }
            if ($name === '' || $amount === '0') {
                continue;
            }
            $items[] = ['name' => mb_substr($name, 0, 150), 'quantity' => $quantity, 'quantity_unit' => $unit, 'amount' => $amount];
            $pendingName = '';
            if (count($items) >= 100) {
                break;
            }
        }

        return ['items' => $items, 'total' => $total, 'currency' => 'UZS'];
    }

    private function amount(string $value): string
    {
        $value = preg_replace('/[ \x{00a0}]/u', '', $value) ?? $value;
        if (preg_match('/[.,](\d{2})$/', $value, $matches)) {
            $whole = preg_replace('/\D/', '', substr($value, 0, -3)) ?? '0';

            return ltrim($whole, '0') === '' ? '0' : ltrim($whole, '0').($matches[1] === '00' ? '' : '.'.$matches[1]);
        }

        return ltrim(preg_replace('/\D/', '', $value) ?? '0', '0') ?: '0';
    }

    private function unit(string $unit): string
    {
        $unit = mb_strtolower(rtrim($unit, '.'));
        if (in_array($unit, ['kg', 'кг'], true)) {
            return 'kg';
        }
        if (in_array($unit, ['litr', 'литр', 'л'], true)) {
            return 'litr';
        }

        return 'dona';
    }
}
