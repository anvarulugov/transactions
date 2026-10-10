import { useCallback, useEffect, useRef, useState } from 'react'
import { ReceiptImport } from '../receipts/ReceiptImport'
import { Menu } from 'lucide-react'
import type { ApiClient } from '../api/client'
import type { Bootstrap } from '../api/types'
import { strings } from '../strings'
import { formatMoneyString } from '../ui/Money'
import { Toast } from '../ui/Toast'
import { AmountKeypad } from './AmountKeypad'
import { QuantityField } from './QuantityField'
import { amountInputValue, groupDigitsForDisplay } from './amountInputFormat'
import { CategoryChips } from './CategoryChips'
import { DetailsSheet } from './DetailsSheet'
import { parseAmount } from './parseAmount'
import { useEntryForm } from './useEntryForm'

export type EntryScreenProps = {
  bootstrap: Bootstrap
  client: ApiClient
  /**
   * Reports this form's current `canSave` and a stable trigger for `save()` up to
   * `App.tsx`, which wires them into the bottom tab bar's center button — that button
   * becomes the save action (in place of switching to this already-active tab) once the
   * form is valid. `save` itself is a fresh closure on every render of `useEntryForm`
   * (it reads `values` from that render's closure), so this reports a *stable* trigger
   * function instead — see this component's body for how — and only re-notifies the
   * parent when `canSave` actually flips, not on every keystroke.
   */
  onSaveStateChange?: (canSave: boolean, save: () => void) => void
}

// The staff expense entry screen: a gradient amount header with the income/expense
// toggle, the category chips, a keypad, and the details sheet. There is no save button
// on this screen itself — the bottom tab bar's center button doubles as Save once the
// form is valid (`App.tsx`, via `onSaveStateChange` below).
export function EntryScreen({ bootstrap, client, onSaveStateChange }: EntryScreenProps) {
  const form = useEntryForm(bootstrap, client)
  const [detailsOpen, setDetailsOpen] = useState(false)

  const parsed = parseAmount(form.values.amountInput)

  // `form.save` is a new closure every render (it closes over that render's `values`),
  // so reporting it to the parent directly would make the effect below re-fire — and
  // `onSaveStateChange` re-run — on every keystroke, not just when `canSave` flips. A
  // ref holds the latest closure; `triggerSave`, with an empty dependency array, never
  // changes identity, so the parent's own state only updates when `canSave` really does.
  const saveRef = useRef(form.save)
  saveRef.current = form.save
  const triggerSave = useCallback(() => void saveRef.current(), [])

  useEffect(() => {
    onSaveStateChange?.(form.canSave, triggerSave)
  }, [form.canSave, onSaveStateChange, triggerSave])

  return (
    <div className="flex flex-col" style={{ paddingBottom: 130 }}>
      <div
        style={{
          background: 'var(--grad-header)',
          padding: 'calc(56px + max(env(safe-area-inset-top), var(--tg-content-safe-top))) 22px 26px',
          borderRadius: '0 0 34px 34px',
          position: 'relative',
          overflow: 'hidden',
        }}
      >
        <div
          aria-hidden="true"
          style={{
            position: 'absolute',
            width: 230,
            height: 230,
            borderRadius: '50%',
            background: 'rgba(255,255,255,.09)',
            top: -120,
            right: -70,
          }}
        />
        <div
          aria-hidden="true"
          style={{
            position: 'absolute',
            width: 150,
            height: 150,
            borderRadius: '50%',
            background: 'rgba(255,255,255,.07)',
            bottom: -90,
            left: -40,
          }}
        />

        <div style={{ position: 'relative', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
          <div>
            <div style={{ font: '500 12px/1 "Plus Jakarta Sans"', color: 'rgba(255,255,255,.72)', letterSpacing: '.04em' }}>
              {strings.tabs.add}
            </div>
            <div style={{ font: '700 19px/1.2 "Plus Jakarta Sans"', color: '#fff', marginTop: 5 }}>
              {strings.entry.newEntry}
            </div>
          </div>
          <div role="group" aria-label={strings.entry.type} style={{ display: 'flex', background: 'rgba(255,255,255,.16)', borderRadius: 999, padding: 3 }}>
            {(['expense', 'income'] as const).map((type) => {
              const selected = form.values.type === type
              return (
                <button
                  key={type}
                  type="button"
                  aria-pressed={selected}
                  onClick={() => form.setType(type)}
                  className="rounded-full"
                  style={{
                    border: 0,
                    padding: '9px 13px',
                    font: '600 12px/1 "Plus Jakarta Sans"',
                    background: selected ? '#ffffff' : 'transparent',
                    color: selected ? 'var(--teal-900)' : 'rgba(255,255,255,.85)',
                  }}
                >
                  {strings.entry[type]}
                </button>
              )
            })}
          </div>
        </div>

        <div style={{ position: 'relative', marginTop: 26, display: 'flex', alignItems: 'flex-end', justifyContent: 'flex-end', gap: 9 }}>
          {/* A real text input, not a display span: the approved amount grammar (comma,
              `k`, `ming`, `mln`, `mlrd`, and their Cyrillic spellings — see `parseAmount`'s
              doc comment) is typed here directly. `inputMode="decimal"` only hints a
              numeric-leaning keyboard; the field still accepts the letters that grammar
              needs, exactly the way a plain `type="text"` would. The keypad below stays as
              a convenience for the pure-digit case, writing into the same value. */}
          <input
            type="text"
            inputMode="decimal"
            aria-label={strings.entry.amount}
            value={groupDigitsForDisplay(form.values.amountInput)}
            onChange={(event) => form.setAmount(amountInputValue(event.target.value))}
            placeholder="0"
            style={{
              width: 250,
              background: 'transparent',
              border: 0,
              outline: 'none',
              textAlign: 'right',
              font: '800 46px/1 "Plus Jakarta Sans"',
              color: '#fff',
              fontVariantNumeric: 'tabular-nums',
              letterSpacing: '-.02em',
            }}
          />
          <span style={{ font: '600 15px/1 "Plus Jakarta Sans"', color: 'rgba(255,255,255,.7)', paddingBottom: 6 }}>
            {form.values.currency}
          </span>
        </div>
        <div
          style={{
            position: 'relative',
            height: 16,
            marginTop: 6,
            textAlign: 'right',
            font: '500 11px/1 "Plus Jakarta Sans"',
            color: form.amountInvalid ? '#ffd9c9' : 'rgba(255,255,255,.55)',
          }}
        >
          {form.amountInvalid ? (
            <span role="alert">{strings.entry.invalidAmount}</span>
          ) : parsed ? (
            `${formatMoneyString(parsed.amount)} so'm`
          ) : null}
        </div>
      </div>

      {form.values.type === 'expense' ? <div style={{ padding: '14px 22px 0' }}><label className="flex items-center gap-2"><input type="checkbox" checked={form.values.isMarketPurchase} onChange={(event) => form.setMarketPurchase(event.target.checked)} />{strings.entry.marketPurchase}</label></div> : null}
      {form.values.type === 'expense' ? <ReceiptImport client={client} bootstrap={bootstrap} occurredOn={form.values.occurredOn} dimensionValues={form.values.dimensionValues} /> : null}
      <CategoryChips
        categories={form.categories}
        type={form.values.type}
        selectedId={form.values.categoryId}
        defaultId={bootstrap.defaults.category_id}
        onSelect={form.setCategory}
      />

      <div style={{ padding: '0 22px 12px' }}>
        <QuantityField value={form.values.quantityInput} unit={form.values.quantityUnit} onChange={form.setQuantity} onUnitChange={form.setQuantityUnit} />
      </div>

      <AmountKeypad value={form.values.amountInput} onChange={form.setAmount} />

      <div style={{ display: 'flex', gap: 10, padding: '8px 22px 0' }}>
        <button
          type="button"
          aria-expanded={detailsOpen}
          onClick={() => setDetailsOpen(true)}
          style={{
            flex: '0 0 auto',
            display: 'flex',
            alignItems: 'center',
            gap: 7,
            border: `1.5px solid ${form.missingRequired.length > 0 ? '#f6cfae' : 'var(--line-2)'}`,
            background: 'var(--surface)',
            borderRadius: 20,
            padding: '16px 18px',
            font: '600 13px/1 "Plus Jakarta Sans"',
            color: 'var(--teal-900)',
          }}
        >
          <Menu size={16} aria-hidden="true" />
          {strings.entry.details}
          {form.missingRequired.length > 0 ? (
            <span
              aria-hidden="true"
              style={{ width: 7, height: 7, borderRadius: '50%', background: 'var(--coral-600)', display: 'inline-block' }}
            />
          ) : null}
        </button>
      </div>

      {form.notice ? (
        <p role="alert" className="px-[22px] py-2 text-sm" style={{ color: 'var(--muted)' }}>
          {form.notice}
        </p>
      ) : null}

      {Object.keys(form.fieldErrors).length > 0 ? (
        <ul role="alert" className="space-y-1 px-[22px] py-2 text-sm" style={{ color: 'var(--muted)' }}>
          {Object.entries(form.fieldErrors).map(([field, messages]) => (
            <li key={field}>{messages[0]}</li>
          ))}
        </ul>
      ) : null}

      <DetailsSheet
        open={detailsOpen}
        onClose={() => setDetailsOpen(false)}
        values={form.values}
        dimensions={form.dimensions}
        currencies={form.currencies}
        onCurrencyChange={form.setCurrency}
        onDateChange={form.setDate}
        onNoteChange={form.setNote}
        onDimensionChange={form.setDimension}
      />

      {form.lastSaved ? (
        <Toast
          message={strings.entry.saved}
          action={strings.entry.undo}
          onAction={() => void form.undo()}
          onDismiss={form.dismissSaved}
        />
      ) : null}
    </div>
  )
}
