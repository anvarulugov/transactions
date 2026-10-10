import { useEffect, useRef } from 'react'
import { ArrowDownLeft, ArrowUpRight } from 'lucide-react'
import type { ApiTransaction } from '../api/types'
import { strings } from '../strings'
import { formatQuantity } from '../entry/parseQuantity'
import { formatMoney } from '../ui/Money'

export type TransactionListProps = {
  items: ApiTransaction[]
  exponents: Record<string, number>
  hasMore: boolean
  onLoadMore: () => void
  onSelect: (id: number) => void
}

type DayGroup = {
  date: string
  items: ApiTransaction[]
}

// Groups the already-fetched, already-ordered `items` by `occurred_on` — presentational
// only, derived at render time. Does not touch `useTransactions`, the cursor paging, or
// ordering: a group's items keep whatever order `items` arrived in.
function groupByDay(items: ApiTransaction[]): DayGroup[] {
  const groups: DayGroup[] = []

  for (const item of items) {
    const current = groups.at(-1)
    if (current && current.date === item.occurred_on) {
      current.items.push(item)
    } else {
      groups.push({ date: item.occurred_on, items: [item] })
    }
  }

  return groups
}

// `formatMoney` already emits its own `-`, so signing the formatted string here too would
// double-sign it — the net is built as `sign + group(Math.abs(net))` instead, entirely
// independent of any single transaction's own currency exponent (a day's net can span
// several transactions, but never several currencies at once within one exponent lookup —
// see the exponent-less integer grouping below).
function dayNet(items: ApiTransaction[]): string {
  const net = items.reduce((sum, item) => sum + (item.type === 'income' ? item.amount_minor : -item.amount_minor), 0)
  const sign = net >= 0 ? '+' : String.fromCharCode(0x2212) // '−'
  const grouped = Math.abs(net).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ')
  return `${sign}${grouped}`
}

// The ledger rows, grouped by day, plus a sentinel element the intersection observer
// below watches to call `onLoadMore` — the sentinel stays the last child of the last
// group's container, per the design brief, so scrolling near the bottom of the last
// visible day still triggers the next page. No virtualisation, per the brief. Each row's
// `data-testid="transaction-<id>"` is what the edit/delete tests use to open a specific
// row's sheet.
export function TransactionList({ items, exponents, hasMore, onLoadMore, onSelect }: TransactionListProps) {
  const sentinelRef = useRef<HTMLDivElement | null>(null)
  const groups = groupByDay(items)

  // The IntersectionObserver is a genuine external system (the browser's own viewport
  // tracking), so watching the sentinel is a real effect, not something derivable at
  // render time — and it is disconnected in the cleanup so a row list that shrinks back
  // below `hasMore`, or unmounts entirely, never leaves a dangling observer calling
  // `onLoadMore` on a component that's gone.
  useEffect(() => {
    if (!hasMore) return

    const node = sentinelRef.current
    if (!node) return

    const observer = new IntersectionObserver((entries) => {
      if (entries.some((entry) => entry.isIntersecting)) onLoadMore()
    })
    observer.observe(node)

    return () => observer.disconnect()
  }, [hasMore, onLoadMore])

  return (
    <div className="flex flex-col gap-[18px]">
      {groups.map((group, groupIndex) => {
        const isLastGroup = groupIndex === groups.length - 1
        return (
          <div key={group.date}>
            <div className="flex items-center justify-between" style={{ margin: '0 4px 9px' }}>
              <span style={{ font: '700 12px/1 "Plus Jakarta Sans"', color: 'var(--muted)', letterSpacing: '.03em' }}>
                {group.date}
              </span>
              <span style={{ font: '600 12px/1 "Plus Jakarta Sans"', color: 'var(--ink-2)', fontVariantNumeric: 'tabular-nums' }}>
                {dayNet(group.items)}
              </span>
            </div>
            <div style={{ background: 'var(--surface)', borderRadius: 'var(--r-list)', padding: '4px 16px', boxShadow: 'var(--shadow-card)' }}>
              {group.items.map((item, itemIndex) => (
                <button
                  key={item.id}
                  type="button"
                  data-testid={`transaction-${item.id}`}
                  onClick={() => onSelect(item.id)}
                  className="flex w-full items-center text-left"
                  style={{
                    gap: 12,
                    background: 'transparent',
                    border: 0,
                    borderBottom: itemIndex === group.items.length - 1 ? undefined : '1px solid var(--line)',
                    padding: '14px 0',
                  }}
                >
                  <span
                    aria-hidden="true"
                    className="flex flex-none items-center justify-center"
                    style={{
                      width: 40,
                      height: 40,
                      borderRadius: 'var(--r-tile)',
                      background: item.type === 'income' ? 'var(--income-bg)' : 'var(--expense-bg)',
                      color: item.type === 'income' ? 'var(--income)' : 'var(--expense)',
                    }}
                  >
                    {item.type === 'income' ? <ArrowUpRight size={16} /> : <ArrowDownLeft size={16} />}
                  </span>
                  <span className="min-w-0 flex-1">
                    <span className="block truncate" style={{ font: '600 14px/1.2 "Plus Jakarta Sans"', color: 'var(--ink)' }}>
                      {item.receipt_id && item.note ? item.note : item.category.name}
                    </span>
                    <span className="mt-1 block" style={{ font: '500 11px/1 "Plus Jakarta Sans"', color: 'var(--muted-2)' }}>
                      {strings.entry[item.type]}{item.is_market_purchase ? ' · '+strings.entry.marketPurchase : ''} · {item.occurred_on}{(item.quantity ?? item.quantity_kg) ? ` · ${formatQuantity((item.quantity ?? item.quantity_kg)!)} ${item.quantity_unit ?? 'kg'}` : ''}
                    </span>
                  </span>
                  <span
                    style={{
                      font: '700 14px/1 "Plus Jakarta Sans"',
                      fontVariantNumeric: 'tabular-nums',
                      color: item.type === 'income' ? 'var(--income)' : 'var(--expense)',
                    }}
                  >
                    {item.type === 'income' ? '+' : String.fromCharCode(0x2212)}
                    {formatMoney(Math.abs(item.amount_minor), item.currency, exponents)}
                  </span>
                </button>
              ))}
              {isLastGroup && hasMore ? <div ref={sentinelRef} aria-hidden="true" data-testid="history-sentinel" /> : null}
            </div>
          </div>
        )
      })}
    </div>
  )
}

