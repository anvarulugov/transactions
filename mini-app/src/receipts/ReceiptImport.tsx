import { useEffect, useRef, useState } from 'react'
import type { ApiClient } from '../api/client'
import type { Bootstrap, ReceiptDraft, ReceiptItem } from '../api/types'
import { strings } from '../strings'
import { parseQuantity } from '../entry/parseQuantity'
import { readMessage } from '../history/transactionEdit'
import { compressPhoto } from './compressPhoto'
import { receiptAmount, receiptMinor, receiptTotal } from './money'

type Props = { client: ApiClient; bootstrap: Bootstrap; occurredOn: string; dimensionValues: Record<number, number> }
export function ReceiptImport({ client, bootstrap, occurredOn, dimensionValues }: Props) {
  const [draft, setDraft] = useState<ReceiptDraft | null>(null)
  const [photo, setPhoto] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [saved, setSaved] = useState(false)
  const [date, setDate] = useState(occurredOn)
  const [dimensions, setDimensions] = useState(dimensionValues)
  const camera = useRef<HTMLInputElement>(null)
  const gallery = useRef<HTMLInputElement>(null)
  const precision = draft ? bootstrap.currencies[draft.currency] ?? 0 : 0
  const sum = draft ? receiptTotal(draft.items, precision) : null
  const total = draft ? receiptMinor(draft.total ?? '', precision) : null
  const complete = !!draft && draft.items.length > 0 && draft.items.every((item) => item.name.trim() && item.category_id && item.quantity_unit && parseQuantity(item.quantity ?? '') !== null && receiptMinor(item.amount, precision) !== null)
  const matched = sum !== null && total !== null && receiptMinor(sum, precision) === total
  const dimensionsComplete = bootstrap.dimensions.every((dimension) => !dimension.is_required || dimensions[dimension.id] !== undefined)
  useEffect(() => () => { if (photo) URL.revokeObjectURL(photo) }, [photo])
  async function upload(file: File | undefined) {
    if (!file) return
    setBusy(true); setError(null); setSaved(false)
    try {
      const compressed = await compressPhoto(file)
      const response = await client.uploadReceipt(compressed)
      setPhoto(URL.createObjectURL(compressed))
      setDraft(response.data); setDate(occurredOn); setDimensions(dimensionValues)
    } catch (caught) { setError(readMessage(caught, strings.receipt.failed)) } finally { setBusy(false) }
  }
  function patchItem(index: number, patch: Partial<ReceiptItem>) {
    setDraft((value) => value ? { ...value, items: value.items.map((item, i) => i === index ? { ...item, ...patch } : item) } : value)
  }
  async function confirm() {
    if (!draft || busy || !complete || !matched || !dimensionsComplete) return
    setBusy(true); setError(null)
    try {
      await client.confirmReceipt(draft.id, { confirmed: true, currency: draft.currency, occurred_on: date, total: receiptAmount(draft.total ?? ''), dimension_values: dimensions,
        items: draft.items.map((item) => ({ ...item, name: item.name.trim(), quantity: parseQuantity(item.quantity ?? ''), amount: receiptAmount(item.amount) })) })
      window.dispatchEvent(new Event('transactions-updated'))
      setDraft(null); setSaved(true); setPhoto(null)
    } catch (caught) { setError(readMessage(caught, strings.entry.saveFailed)) } finally { setBusy(false) }
  }
  const inputStyle = { width: '100%', minWidth: 0, border: '1px solid var(--line-2)', borderRadius: 10, padding: '10px', background: 'var(--surface)', color: 'var(--ink)' }
  return <div style={{ padding: '12px 22px' }}>
    <div className="flex flex-wrap gap-2">
      <button type="button" disabled={busy} onClick={() => camera.current?.click()} style={{ ...inputStyle, width: 'auto' }}>{strings.receipt.camera}</button>
      <button type="button" disabled={busy} onClick={() => gallery.current?.click()} style={{ ...inputStyle, width: 'auto' }}>{strings.receipt.upload}</button>
      <input ref={camera} aria-label={strings.receipt.camera} type="file" accept="image/*" capture="environment" hidden onChange={(event) => { void upload(event.target.files?.[0]); event.target.value = '' }} />
      <input ref={gallery} aria-label={strings.receipt.upload} type="file" accept="image/*" hidden onChange={(event) => { void upload(event.target.files?.[0]); event.target.value = '' }} />
    </div>
    {busy && !draft ? <p role="status">{strings.receipt.reading}</p> : null}
    {saved ? <p role="status" style={{ color: 'var(--teal-900)' }}>{strings.receipt.saved}</p> : null}
    {error && !draft ? <p role="alert">{error}</p> : null}
    {draft ? <div role="dialog" aria-modal="true" aria-label={strings.receipt.title} className="fixed inset-0 z-50" style={{ background: 'var(--bg)', padding: '20px 16px', overflow: 'auto', paddingBottom: 90 }}>
      <div className="flex justify-between gap-2"><h2>{strings.receipt.title}</h2><button type="button" disabled={busy} onClick={() => { setDraft(null); setPhoto(null); setError(null) }}>{strings.common.close}</button></div>
      <p style={{ margin: '12px 0' }}>{draft.confirmed ? strings.receipt.alreadySaved : strings.receipt.review}</p>
      {photo ? <img src={photo} alt={strings.receipt.photo} style={{ width: '100%', maxHeight: 280, objectFit: 'contain', background: '#fff' }} /> : null}
      <p style={{ margin: '12px 0', fontSize: 13 }}>{strings.receipt.unavailable}</p>
      <details><summary>{strings.receipt.raw}</summary><pre style={{ whiteSpace: 'pre-wrap', fontSize: 13 }}>{draft.text}</pre></details>
      {!draft.confirmed ? <>
      <div className="flex gap-2" style={{ margin: '12px 0' }}><label style={{ flex: 1 }}>{strings.entry.date}<input aria-label={strings.entry.date} type="date" value={date} onChange={(event) => setDate(event.target.value)} style={inputStyle} /></label>
      <label>{strings.entry.currency}<select aria-label={strings.entry.currency} value={draft.currency} onChange={(event) => setDraft({ ...draft, currency: event.target.value })} style={inputStyle}>{Object.keys(bootstrap.currencies).map((code) => <option key={code}>{code}</option>)}</select></label></div>
      {bootstrap.dimensions.map((dimension) => <label key={dimension.id} style={{ display: 'block', marginBottom: 10 }}>{dimension.name}{dimension.is_required ? ' *' : ''}<select value={dimensions[dimension.id] ?? ''} onChange={(event) => setDimensions((value) => { const next = { ...value }; if (event.target.value) next[dimension.id] = Number(event.target.value); else delete next[dimension.id]; return next })} style={inputStyle}><option value="">—</option>{dimension.values.map((value) => <option key={value.id} value={value.id}>{value.name}</option>)}</select></label>)}
      {draft.items.map((item, index) => <fieldset key={index} style={{ border: '1px solid var(--line-2)', borderRadius: 16, padding: 12, margin: '12px 0' }}>
        <legend>{index + 1}. {strings.entry.marketPurchase}</legend>
        <label>{strings.receipt.itemName}<input aria-label={`${strings.receipt.itemName} ${index + 1}`} value={item.name} onChange={(event) => patchItem(index, { name: event.target.value })} style={inputStyle} /></label>
        <select aria-label={`${strings.entry.category} ${index + 1}`} value={item.category_id ?? ''} onChange={(event) => patchItem(index, { category_id: event.target.value ? Number(event.target.value) : null })} style={{ ...inputStyle, marginTop: 8 }}>
          <option value="">{strings.receipt.category}</option>{bootstrap.categories.filter((category) => category.applies_to === 'expense' || category.applies_to === 'both').flatMap((category) => [category, ...category.children]).map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}
        </select>
        <div className="flex gap-2" style={{ marginTop: 8 }}>
          <label style={{ flex: 1 }}>{strings.entry.quantity}<input aria-label={`${strings.entry.quantity} ${index + 1}`} inputMode="decimal" value={item.quantity ?? ''} onChange={(event) => patchItem(index, { quantity: event.target.value })} style={inputStyle} /></label>
          <label style={{ width: 100 }}>{strings.entry.quantityUnit}<select aria-label={`${strings.entry.quantityUnit} ${index + 1}`} value={item.quantity_unit ?? ''} onChange={(event) => patchItem(index, { quantity_unit: event.target.value as ReceiptItem['quantity_unit'] })} style={inputStyle}><option value="">{strings.receipt.unit}</option><option>kg</option><option>litr</option><option>dona</option></select></label>
        </div>
        <label style={{ display: 'block', marginTop: 8 }}>{strings.receipt.amount} ({draft.currency})<input aria-label={`${strings.receipt.amount} ${index + 1}`} inputMode="decimal" value={item.amount} onChange={(event) => patchItem(index, { amount: event.target.value })} style={inputStyle} /></label>
        <button type="button" style={{ marginTop: 10 }} onClick={() => setDraft({ ...draft, items: draft.items.filter((_, i) => i !== index) })}>{strings.receipt.remove}</button>
      </fieldset>)}
      <button type="button" onClick={() => setDraft({ ...draft, items: [...draft.items, { name: '', quantity: '', quantity_unit: null, amount: '', category_id: null }] })}>{strings.receipt.add}</button>
      <label style={{ display: 'block', margin: '16px 0' }}>{strings.receipt.total} ({draft.currency})<input aria-label={strings.receipt.total} inputMode="decimal" value={draft.total ?? ''} onChange={(event) => setDraft({ ...draft, total: event.target.value })} style={inputStyle} /></label>
      <p>{strings.receipt.sum}: {sum ?? '—'} {draft.currency}</p>
      {!complete || !dimensionsComplete ? <p role="status">{strings.receipt.required}</p> : null}
      {!matched ? <p role="status" style={{ color: 'var(--expense)' }}>{strings.receipt.mismatch}</p> : null}
      {error ? <p role="alert">{error}</p> : null}
      <button type="button" disabled={busy || !complete || !matched || !dimensionsComplete || date === ''} onClick={() => void confirm()} style={{ ...inputStyle, background: 'var(--teal-900)', color: '#fff', marginTop: 16, opacity: busy || !complete || !matched || !dimensionsComplete ? 0.5 : 1 }}>{busy ? strings.receipt.saving : strings.receipt.confirm}</button>
      </> : null}
    </div> : null}
  </div>
}
