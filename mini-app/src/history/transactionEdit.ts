// The values one edit sheet holds, and the diff that turns them into a `PATCH` body
// carrying only what actually changed. Split out of `TransactionSheet` the same way
// `entryDefaults.ts`/`entryErrors.ts` are split out of `useEntryForm` — a distinct
// concern (what changed) from the component that renders the fields.
//
// Amount is the one field this form does not pre-fill from the transaction: the
// transaction's own `amount` is already formatted for display (`"10.00"` for a
// two-decimal currency), and running a formatted amount back through `parseAmount`'s
// grammar — which reads `.` as a thousands separator, never a decimal point — would
// silently produce a different minor-unit amount than the one being displayed. Instead
// `amountInput` starts empty, the same "nothing typed yet" state `useEntryForm` starts
// in, and stays a sentinel for "leave the amount alone": `diffValues` below only sends
// `amount`/`currency` once the user has typed something `parseAmount` accepts. That
// mirrors the create form's own field exactly rather than reparsing a value formatted
// for reading.
//
// Currency changes ride along with amount for the same rule the server enforces on
// create: a new `amount` must accompany any `currency` change
// (`tests/Feature/Api/UpdateDeleteTransactionTest.php:105`). `TransactionSheet` gates
// `save` on this — see its own comment — so a currency change reaches here only once a
// valid amount sits alongside it.
import type { ApiTransaction, TransactionWrite } from '../api/types'
import { parseQuantity, formatQuantity } from '../entry/parseQuantity'

export type TransactionEditValues = {
  type: 'income' | 'expense'
  categoryId: number
  note: string
  occurredOn: string
  dimensionValues: Record<number, number>
  currency: string
  /** Raw `parseAmount` input. Empty string is the sentinel for "not touched". */
  isMarketPurchase: boolean
  quantityInput: string
  quantityUnit: 'kg' | 'litr' | 'dona'
  amountInput: string
}

export function valuesFromTransaction(transaction: ApiTransaction): TransactionEditValues {
  return {
    type: transaction.type,
    categoryId: transaction.category.id,
    note: transaction.note ?? '',
    occurredOn: transaction.occurred_on,
    dimensionValues: Object.fromEntries(
      transaction.dimension_values.map((value) => [value.dimension_id, value.value_id]),
    ),
    currency: transaction.currency,
    isMarketPurchase: transaction.is_market_purchase ?? false,
    quantityInput: (transaction.quantity ?? transaction.quantity_kg) ? formatQuantity((transaction.quantity ?? transaction.quantity_kg)!) : '',
    quantityUnit: transaction.quantity_unit ?? 'kg',
    amountInput: '',
  }
}

function dimensionsEqual(a: Record<number, number>, b: Record<number, number>): boolean {
  const keys = new Set([...Object.keys(a), ...Object.keys(b)])
  return [...keys].every((key) => a[Number(key)] === b[Number(key)])
}

// Only the fields that actually changed reach the server: an edit sheet opened and
// closed without touching anything sends an empty `PATCH` body's worth of nothing, and
// a note-only edit never re-sends the category, type or dimension picks alongside it.
//
// `parsedAmount` is `current.amountInput` already run through `parseAmount` by the
// caller (`TransactionSheet`, which also owns the "invalid" and "currency changed with
// no amount typed" gates that keep an unparseable or incomplete edit from reaching
// here at all). Amount and currency travel together whenever `parsedAmount` is not
// null, satisfying the server's "amount must accompany any currency change" rule
// unconditionally — including the case where only the amount changed and the currency
// did not, which costs nothing since the value sent is the current one either way.
export function diffValues(
  original: TransactionEditValues,
  current: TransactionEditValues,
  parsedAmount: string | null,
): TransactionWrite {
  const changes: TransactionWrite = {}

  if (current.quantityInput !== original.quantityInput || (current.quantityUnit !== original.quantityUnit && current.quantityInput.trim() !== '')) {
    changes.quantity = parseQuantity(current.quantityInput)
    changes.quantity_unit = changes.quantity === null ? null : current.quantityUnit
  }
  if (current.isMarketPurchase !== original.isMarketPurchase) changes.is_market_purchase = current.isMarketPurchase
  if (current.type !== original.type) changes.type = current.type
  if (current.categoryId !== original.categoryId) changes.category_id = current.categoryId
  if (current.note !== original.note) changes.note = current.note === '' ? null : current.note
  if (current.occurredOn !== original.occurredOn) changes.occurred_on = current.occurredOn
  if (!dimensionsEqual(current.dimensionValues, original.dimensionValues)) {
    changes.dimension_values = current.dimensionValues
  }
  if (parsedAmount !== null) {
    changes.amount = parsedAmount
    changes.currency = current.currency
  }

  return changes
}

// Reads the server's own refusal message off a rejected save or delete — duck-typed the
// way `entryErrors.ts`'s `readStatus`/`readErrors` read a rejection, so the real
// `ApiError` and a plain `{ status, message }` test double both work. A 403 or 404's
// `message` (from Laravel's own `{"message": "..."}` body, via `extractMessage`) reaches
// the user verbatim: there is no client-side guess here about *why* the server refused.
export function readMessage(error: unknown, fallback: string): string {
  if (typeof error === 'object' && error !== null && 'message' in error) {
    const message = (error as { message?: unknown }).message
    if (typeof message === 'string') return message
  }
  return fallback
}
