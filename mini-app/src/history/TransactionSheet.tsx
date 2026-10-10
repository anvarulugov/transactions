import { ReceiptViewer } from '../receipts/ReceiptViewer'
import { useState } from 'react'
import type { ApiClient } from '../api/client'
import type { ApiTransaction, ApiUser, Bootstrap } from '../api/types'
import { parseAmount } from '../entry/parseAmount'
import { parseQuantity } from '../entry/parseQuantity'
import { strings } from '../strings'
import { TransactionFields } from './TransactionFields'
import { TransactionHeader } from './TransactionHeader'
import { TransactionSummary } from './TransactionSummary'
import { canManageTransaction } from './transactionPermissions'
import { diffValues, readMessage, valuesFromTransaction, type TransactionEditValues } from './transactionEdit'
import { useRevisionCount } from './useRevisionCount'

export type TransactionSheetProps = {
  transaction: ApiTransaction
  bootstrap: Bootstrap
  user: ApiUser
  client: Pick<ApiClient, 'updateTransaction' | 'deleteTransaction' | 'revisions'> & Partial<Pick<ApiClient, 'receiptImage'>>
  onClose: () => void
  onSaved: (updated: ApiTransaction) => void
  onDeleted: (id: number) => void
}

// One transaction's full read-only record, plus its edit form and delete flow when the
// signed-in user is allowed to touch it. `HistoryScreen` mounts this with
// `key={transaction.id}`, so switching which row is open always starts a fresh instance
// from that row's own values, rather than an effect resyncing state to a changed prop —
// `original` below is a plain snapshot taken once from the prop, not a ref or an effect.
//
// `canManage` only decides what this component *offers*; the server re-runs
// `TransactionPolicy::update` on every `PATCH`/`DELETE` regardless, so a 403 that
// reaches `save`/`confirmDelete` in spite of the gate below (a role that changed since
// the row was fetched, say) still surfaces through `error` exactly as it always did —
// the client never treats its own mirror of the policy as the final word.
export function TransactionSheet({
  transaction,
  bootstrap,
  user,
  client,
  onClose,
  onSaved,
  onDeleted,
}: TransactionSheetProps) {
  const [original] = useState<TransactionEditValues>(() => valuesFromTransaction(transaction))
  const [values, setValues] = useState<TransactionEditValues>(original)
  const [saving, setSaving] = useState(false)
  const [deleting, setDeleting] = useState(false)
  const [confirmingDelete, setConfirmingDelete] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const canManage = canManageTransaction(user, transaction)
  const revisionState = useRevisionCount(client, transaction.id)

  // `''` means "amount not touched" (see `transactionEdit.ts`'s doc comment); anything
  // else must parse or the field is showing text `parseAmount` refuses.
  const parsedAmount = values.amountInput === '' ? null : (parseAmount(values.amountInput)?.amount ?? null)
  const amountInvalid = values.amountInput !== '' && parsedAmount === null
  // The server requires a fresh `amount` alongside any `currency` change; a changed
  // currency with nothing typed in the amount field yet is not a state `diffValues` can
  // turn into a valid request, so `save` stays disabled until one arrives.
  const currencyNeedsAmount = values.currency !== original.currency && parsedAmount === null
  const quantityInvalid = values.quantityInput.trim() !== '' && parseQuantity(values.quantityInput) === null
  const canSave = !amountInvalid && !currencyNeedsAmount && !quantityInvalid

  function patch(next: Partial<TransactionEditValues>): void {
    setValues((current) => ({ ...current, ...next }))
  }

  async function save(): Promise<void> {
    if (!canSave) return

    setSaving(true)
    setError(null)

    try {
      const response = await client.updateTransaction(transaction.id, diffValues(original, values, parsedAmount))
      onSaved(response.data)
    } catch (caught) {
      setError(readMessage(caught, strings.history.actionFailed))
    } finally {
      setSaving(false)
    }
  }

  async function confirmDelete(): Promise<void> {
    setDeleting(true)
    setError(null)

    try {
      await client.deleteTransaction(transaction.id)
      onDeleted(transaction.id)
    } catch (caught) {
      setError(readMessage(caught, strings.history.actionFailed))
      setConfirmingDelete(false)
    } finally {
      setDeleting(false)
    }
  }

  return (
    <div
      role="dialog"
      aria-modal="true"
      className="fixed inset-0 z-10 flex flex-col justify-end"
      style={{ background: 'rgba(11,45,38,.42)' }}
      onClick={onClose}
    >
      <div
        className="flex flex-col gap-3"
        style={{
          background: 'var(--surface)',
          borderRadius: 'var(--r-sheet) var(--r-sheet) 0 0',
          padding: '12px 22px 26px',
          animation: 'sheetIn .26s cubic-bezier(.32,.72,.3,1)',
        }}
        onClick={(event) => event.stopPropagation()}
      >
        <div
          aria-hidden="true"
          style={{ width: 44, height: 5, borderRadius: 999, background: 'var(--line-2)', margin: '0 auto' }}
        />

        <TransactionHeader transaction={transaction} exponents={bootstrap.currencies} revisionState={revisionState} />

        {transaction.receipt_id && client.receiptImage ? <ReceiptViewer id={transaction.receipt_id} load={client.receiptImage} /> : null}

        {canManage ? (
          <TransactionFields values={values} bootstrap={bootstrap} onChange={patch} amountInvalid={amountInvalid} />
        ) : (
          <TransactionSummary transaction={transaction} dimensions={bootstrap.dimensions} />
        )}

        {canManage && currencyNeedsAmount ? (
          <p role="alert" className="text-sm" style={{ color: 'var(--expense)' }}>
            {strings.history.currencyNeedsAmount}
          </p>
        ) : null}

        {error ? (
          <p role="alert" className="text-sm" style={{ color: 'var(--expense)' }}>
            {error}
          </p>
        ) : null}

        <div className="flex gap-2.5" style={{ marginTop: 8 }}>
          <button
            type="button"
            onClick={onClose}
            className="flex-1 text-center"
            style={{ border: 0, borderRadius: 22, padding: 17, font: '700 14px/1 "Plus Jakarta Sans"', background: 'var(--pill-bg)', color: 'var(--ink-2)' }}
          >
            {strings.common.cancel}
          </button>
          {canManage ? (
            <button
              type="button"
              disabled={saving || !canSave}
              onClick={() => void save()}
              className="flex-1 text-center disabled:opacity-50"
              style={{
                border: 0,
                borderRadius: 22,
                padding: 17,
                font: '700 14px/1 "Plus Jakarta Sans"',
                color: '#fff',
                background: 'var(--grad-action)',
                boxShadow: '0 10px 26px rgba(239,139,60,.3)',
              }}
            >
              {strings.common.save}
            </button>
          ) : null}
        </div>

        {canManage ? (
          confirmingDelete ? (
            <div className="flex items-center gap-2.5" style={{ background: '#fdf1ec', borderRadius: 18, padding: '12px 14px' }}>
              <p className="flex-1" style={{ font: '600 12px/1.4 "Plus Jakarta Sans"', color: '#b4553a' }}>
                {strings.history.deleteConfirm}
              </p>
              <button
                type="button"
                disabled={deleting}
                onClick={() => void confirmDelete()}
                className="rounded-full disabled:opacity-50"
                style={{ border: 0, padding: '10px 15px', font: '700 12px/1 "Plus Jakarta Sans"', background: '#e2603c', color: '#fff' }}
              >
                {strings.common.confirm}
              </button>
            </div>
          ) : (
            <button
              type="button"
              onClick={() => setConfirmingDelete(true)}
              className="w-full"
              style={{ border: 0, background: 'transparent', padding: 12, font: '600 13px/1 "Plus Jakarta Sans"', color: '#c0705a' }}
            >
              {strings.common.delete}
            </button>
          )
        ) : null}
      </div>
    </div>
  )
}
