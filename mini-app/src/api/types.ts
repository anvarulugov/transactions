// Types mirrored from the backend's JSON resources (app/Http/Resources) and reports
// (app/Reports). Verified against a running instance rather than transcribed blind: see
// `.superpowers/sdd/2026-07-31-mini-app/task-2-report.md` for the requests that produced
// these shapes. Where the live response disagreed with the plan, the response won.

export type ApiUser = {
  id: number
  telegram_id: number
  name: string
  username: string | null
  role: 'staff' | 'manager' | 'owner' | 'admin'
  status: 'pending' | 'active' | 'blocked'
  locale: string
  department: { id: number; name: string } | null
  permissions: { can_see_all: boolean; can_manage: boolean }
}

export type ApiCategory = {
  id: number
  name: string
  applies_to: 'income' | 'expense' | 'both'
  children: ApiCategory[]
}

export type ApiDimension = {
  id: number
  key: string
  name: string
  is_required: boolean
  values: { id: number; name: string }[]
}

export type ApiTransaction = {
  id: number
  type: 'income' | 'expense'
  amount_minor: number
  amount: string
  currency: string
  occurred_on: string
  note: string | null
  is_market_purchase?: boolean
  receipt_id?: number | null
  quantity_kg?: string | null
  quantity?: string | null
  quantity_unit?: 'kg' | 'litr' | 'dona' | null
  category: { id: number; name: string }
  user: { id: number; name: string }
  department: { id: number; name: string } | null
  dimension_values: {
    dimension_id: number
    dimension_key: string
    value_id: number
    value_name: string
  }[]
  created_at: string | null
  updated_at: string | null
}

// `BootstrapController` returns `user`, `categories` and `dimensions` as values nested
// inside a plain array. `JsonResource::jsonSerialize()` does not add the `data` wrapper
// in that position (the wrapper is a `toResponse()` behaviour, applied only when a
// resource is the top-level thing a route returns), so none of the three are wrapped.
// Confirmed with a live `/api/bootstrap` call: see the task report.
export type Bootstrap = {
  user: ApiUser
  categories: ApiCategory[]
  dimensions: ApiDimension[]
  currencies: Record<string, number>
  defaults: {
    type: 'income' | 'expense'
    currency: string
    category_id: number | null
    dimension_values: Record<string, number>
  }
}

export type AggregateRow = {
  currency: string
  type: 'income' | 'expense'
  amount_minor: number
  amount: string
  count: number
}

export type SummaryReport = {
  totals: AggregateRow[]
  groups: (AggregateRow & { key: string | null; label: string })[]
}

export type TrendReport = {
  points: (AggregateRow & { period: string })[]
}

// `POST /api/auth/telegram` also returns `user` unwrapped, for the same reason as
// Bootstrap above: it is a value inside a plain array, not the route's top-level return.
export type AuthExchange = {
  token: string | null
  user: ApiUser
}

// The fields a caller may set when creating or editing a transaction. Left permissive
// (every field optional) because an edit sends only the fields that changed and the
// server is the sole authority on which combinations are valid.
export type TransactionWrite = {
  type?: 'income' | 'expense'
  amount?: string
  currency?: string
  occurred_on?: string
  category_id?: number
  note?: string | null
  is_market_purchase?: boolean
  receipt_id?: number | null
  quantity_kg?: string | null
  quantity?: string | null
  quantity_unit?: 'kg' | 'litr' | 'dona' | null
  dimension_values?: Record<string, number>
}

export type TransactionListParams = {
  from?: string
  to?: string
  type?: 'income' | 'expense'
  category_id?: number
  currency?: string
  user_id?: number
  department_id?: number
  dimension?: Record<string, number>
  cursor?: string
}

export type ReportParams = TransactionListParams & {
  group_by?: string
  interval?: string
}

// The `cursorPaginate()` envelope also carries `links` and extra `meta` fields
// (`path`, `per_page`, `prev_cursor`); this type only names what the app reads. See the
// task report for the full envelope observed from a live `/api/transactions` call.
export type CursorPage<T> = {
  data: T[]
  meta: { next_cursor: string | null }
}

export type TransactionResponse = { data: ApiTransaction }

// One row of `GET /api/transactions/{id}/revisions`, mirrored from
// `TransactionRevisionResource`. The mini app only reads the collection's length today
// (the sheet's read-only revision count); `snapshot` is left as `unknown` rather than
// typed field by field since nothing here reads into it yet.
export type ApiTransactionRevision = {
  id: number
  action: 'created' | 'updated' | 'deleted' | 'restored'
  actor: { id: number; name: string }
  snapshot: Record<string, unknown>
  created_at: string
}

export type ReceiptItem = { name: string; quantity: string | null; quantity_unit: 'kg' | 'litr' | 'dona' | null; amount: string; category_id: number | null }
export type ReceiptDraft = { id: number; text: string; items: ReceiptItem[]; total: string | null; currency: string; confirmed: boolean }
export type ReceiptConfirmation = { confirmed: true; currency: string; occurred_on: string; total: string; dimension_values: Record<number, number>; items: ReceiptItem[] }
