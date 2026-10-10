// The blank-slate values a new entry starts from — split out of `useEntryForm` since
// "what a fresh form looks like" is a distinct concern from the state that changes it,
// and both the initial `useState` and every post-save reset need the same answer.
import type { Bootstrap } from '../api/types'

export type EntryValues = {
  type: 'income' | 'expense'
  currency: string
  categoryId: number | null
  dimensionValues: Record<number, number>
  isMarketPurchase: boolean
  quantityInput: string
  quantityUnit: 'kg' | 'litr' | 'dona'
  amountInput: string
  note: string
  occurredOn: string
}

function pad(value: number): string {
  return String(value).padStart(2, '0')
}

// The device's own calendar date, not UTC's: a transaction entered near local midnight
// (Uzbekistan is UTC+5) must date itself the day the person entering it experienced, not
// whatever day UTC happens to still be on at that instant. `toISOString()` reads the UTC
// calendar and would silently misdate exactly that case.
function todayIso(): string {
  const now = new Date()
  return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`
}

export function valuesFromDefaults(bootstrap: Bootstrap): EntryValues {
  return {
    type: bootstrap.defaults.type,
    currency: bootstrap.defaults.currency,
    categoryId: bootstrap.defaults.category_id,
    dimensionValues: { ...bootstrap.defaults.dimension_values },
    isMarketPurchase: false,
    quantityInput: '',
    quantityUnit: 'kg',
    amountInput: '',
    note: '',
    occurredOn: todayIso(),
  }
}
