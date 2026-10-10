import type { Bootstrap } from '../api/types'
import { QuantityField } from '../entry/QuantityField'
import { amountInputValue, groupDigitsForDisplay } from '../entry/amountInputFormat'
import { strings } from '../strings'
import { flattenCategoriesForType } from './historyCategories'
import type { TransactionEditValues } from './transactionEdit'

type TransactionFieldsProps = {
  values: TransactionEditValues
  bootstrap: Bootstrap
  onChange: (next: Partial<TransactionEditValues>) => void
  /** True the moment the amount field holds text `parseAmount` refuses. */
  amountInvalid: boolean
}

const TYPES = ['expense', 'income'] as const

// The editable fields of one transaction — type, category, amount, currency, date, note,
// and every active dimension — split out of `TransactionSheet` purely to keep that
// file's line count down, the same way `DetailsSheet` holds the entry form's own fields
// separately from `EntryScreen`. Amount starts empty rather than pre-filled: see
// `transactionEdit.ts`'s doc comment for why, and `TransactionSheet` for the validation
// and "currency changed but no amount yet" gating this field's value feeds.
const rowStyle = { background: 'var(--field)', borderRadius: 18, padding: '14px 16px' }
const labelStyle = { font: '600 12px/1 "Plus Jakarta Sans"', color: 'var(--muted)' }
// 16px, not the design's 14px: iOS Safari zooms the whole page on focus for any input
// under 16px, and every field here is a real `<input>`/`<select>` a thumb can tap.
const valueStyle = { border: 0, background: 'transparent', font: '700 16px/1 "Plus Jakarta Sans"', color: 'var(--teal-900)', outline: 'none', textAlign: 'right' as const }

export function TransactionFields({ values, bootstrap, onChange, amountInvalid }: TransactionFieldsProps) {
  return (
    <div className="flex flex-col gap-[9px]">
      {values.type === 'expense' ? <label className="flex items-center gap-2"><input type="checkbox" checked={values.isMarketPurchase} onChange={(event) => onChange({ isMarketPurchase: event.target.checked })} />{strings.entry.marketPurchase}</label> : null}
      <QuantityField value={values.quantityInput} unit={values.quantityUnit} onChange={(quantityInput) => onChange({ quantityInput })} onUnitChange={(quantityUnit) => onChange({ quantityUnit })} />
      <div role="group" aria-label={strings.entry.type} className="flex gap-2">
        {TYPES.map((type) => (
          <button
            key={type}
            type="button"
            aria-pressed={values.type === type}
            onClick={() => onChange({ type })}
            className="rounded-full"
            style={{
              border: 0,
              padding: '9px 13px',
              font: '600 12px/1 "Plus Jakarta Sans"',
              background: values.type === type ? 'var(--teal-900)' : 'var(--pill-bg)',
              color: values.type === type ? '#fff' : 'var(--ink-2)',
            }}
          >
            {strings.entry[type]}
          </button>
        ))}
      </div>

      <label className="flex items-center justify-between" style={amountInvalid ? { ...rowStyle, background: 'var(--field-warn)' } : rowStyle}>
        <span style={labelStyle}>{strings.entry.amount}</span>
        <input
          type="text"
          inputMode="decimal"
          value={groupDigitsForDisplay(values.amountInput)}
          onChange={(event) => onChange({ amountInput: amountInputValue(event.target.value) })}
          placeholder={strings.history.amountUnchanged}
          style={valueStyle}
        />
      </label>

      {amountInvalid ? (
        <p role="alert" className="text-sm" style={{ color: 'var(--expense)' }}>
          {strings.entry.invalidAmount}
        </p>
      ) : null}

      <label className="flex items-center justify-between" style={rowStyle}>
        <span style={labelStyle}>{strings.entry.currency}</span>
        <select
          value={values.currency}
          onChange={(event) => onChange({ currency: event.target.value })}
          style={valueStyle}
        >
          {Object.keys(bootstrap.currencies).map((code) => (
            <option key={code} value={code}>
              {code}
            </option>
          ))}
        </select>
      </label>

      <label className="flex items-center justify-between" style={rowStyle}>
        <span style={labelStyle}>{strings.entry.category}</span>
        <select
          value={values.categoryId}
          onChange={(event) => onChange({ categoryId: Number(event.target.value) })}
          style={valueStyle}
        >
          {flattenCategoriesForType(bootstrap.categories, values.type).map((category) => (
            <option key={category.id} value={category.id}>
              {category.name}
            </option>
          ))}
        </select>
      </label>

      <label className="flex items-center justify-between" style={rowStyle}>
        <span style={labelStyle}>{strings.entry.date}</span>
        <input
          type="date"
          value={values.occurredOn}
          onChange={(event) => onChange({ occurredOn: event.target.value })}
          style={valueStyle}
        />
      </label>

      <label className="block" style={rowStyle}>
        <span style={labelStyle}>{strings.entry.note}</span>
        <input
          type="text"
          value={values.note}
          onChange={(event) => onChange({ note: event.target.value })}
          className="mt-2 block w-full"
          style={{ border: 0, background: 'transparent', font: '600 16px/1 "Plus Jakarta Sans"', color: 'var(--ink)', outline: 'none' }}
        />
      </label>

      {bootstrap.dimensions.map((dimension) => (
        <label key={dimension.id} className="flex items-center justify-between" style={rowStyle}>
          <span style={labelStyle}>{dimension.name}</span>
          <select
            value={values.dimensionValues[dimension.id] ?? ''}
            onChange={(event) =>
              onChange({ dimensionValues: { ...values.dimensionValues, [dimension.id]: Number(event.target.value) } })
            }
            style={valueStyle}
          >
            <option value="" disabled>
              {strings.entry.choose}
            </option>
            {dimension.values.map((value) => (
              <option key={value.id} value={value.id}>
                {value.name}
              </option>
            ))}
          </select>
        </label>
      ))}
    </div>
  )
}
