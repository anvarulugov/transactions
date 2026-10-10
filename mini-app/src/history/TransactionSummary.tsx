import type { ApiDimension, ApiTransaction } from '../api/types'
import { strings } from '../strings'
import { formatQuantity } from '../entry/parseQuantity'

type TransactionSummaryProps = {
  transaction: ApiTransaction
  dimensions: ApiDimension[]
}

function dimensionName(dimensions: ApiDimension[], dimensionId: number): string | null {
  return dimensions.find((dimension) => dimension.id === dimensionId)?.name ?? null
}

// The read-only recap shown instead of `TransactionFields` when `canManageTransaction`
// says the signed-in user may not change this row — a manager looking at a colleague's
// entry, or an owner looking at anyone's. Plain text, not disabled form controls: a
// disabled `<select>` still looks like a control waiting for permission, where this is
// the same "view only" fact restated in the field labels the editable form itself uses.
const rowStyle = { background: 'var(--field)', borderRadius: 18, padding: '14px 16px' }
const labelStyle = { font: '600 12px/1 "Plus Jakarta Sans"', color: 'var(--muted)' }
const valueStyle = { font: '700 14px/1 "Plus Jakarta Sans"', color: 'var(--teal-900)' }

export function TransactionSummary({ transaction, dimensions }: TransactionSummaryProps) {
  return (
    <dl className="flex flex-col gap-[9px]">
      <div className="flex items-center justify-between" style={rowStyle}>
        <dt style={labelStyle}>{strings.entry.type}</dt>
        <dd style={valueStyle}>{strings.entry[transaction.type]}</dd>
      </div>
      <div className="flex items-center justify-between" style={rowStyle}>
        <dt style={labelStyle}>{strings.entry.category}</dt>
        <dd style={valueStyle}>{transaction.category.name}</dd>
      </div>
      <div className="flex items-center justify-between" style={rowStyle}>
        <dt style={labelStyle}>{strings.entry.date}</dt>
        <dd style={valueStyle}>{transaction.occurred_on}</dd>
      </div>
      {transaction.is_market_purchase ? <div className="flex items-center justify-between" style={rowStyle}><dt style={labelStyle}>{strings.entry.marketPurchase}</dt><dd style={valueStyle}>✓</dd></div> : null}
      {(transaction.quantity ?? transaction.quantity_kg) ? (
        <div className="flex items-center justify-between" style={rowStyle}>
          <dt style={labelStyle}>{strings.entry.quantity}</dt>
          <dd style={valueStyle}>{formatQuantity((transaction.quantity ?? transaction.quantity_kg)!)} {transaction.quantity_unit ?? 'kg'}</dd>
        </div>
      ) : null}
      {transaction.note ? (
        <div className="flex items-center justify-between" style={rowStyle}>
          <dt style={labelStyle}>{strings.entry.note}</dt>
          <dd style={valueStyle}>{transaction.note}</dd>
        </div>
      ) : null}
      {transaction.dimension_values.map((value) => (
        <div key={value.dimension_id} className="flex items-center justify-between" style={rowStyle}>
          <dt style={labelStyle}>{dimensionName(dimensions, value.dimension_id) ?? value.dimension_key}</dt>
          <dd style={valueStyle}>{value.value_name}</dd>
        </div>
      ))}
    </dl>
  )
}
