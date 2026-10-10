import { useRef, useState } from 'react'
import { parseAmount } from './parseAmount'
import { parseQuantity } from './parseQuantity'
import { valuesFromDefaults, type EntryValues } from './entryDefaults'
import {
  isStaleReference,
  readErrors,
  readStatus,
  resolveStaleReference,
  withoutStaleReferenceFields,
} from './entryErrors'
import type { ApiClient } from '../api/client'
import type { ApiTransaction, Bootstrap, TransactionWrite } from '../api/types'
import { strings } from '../strings'

export type { EntryValues } from './entryDefaults'

export type EntryForm = {
  values: EntryValues
  categories: Bootstrap['categories']
  dimensions: Bootstrap['dimensions']
  currencies: Bootstrap['currencies']
  setMarketPurchase: (value: boolean) => void
  setQuantity: (input: string) => void
  setQuantityUnit: (unit: EntryValues['quantityUnit']) => void
  setAmount: (input: string) => void
  setCategory: (id: number) => void
  setDimension: (dimensionId: number, valueId: number) => void
  setType: (type: 'income' | 'expense') => void
  setCurrency: (currency: string) => void
  setNote: (note: string) => void
  setDate: (date: string) => void
  canSave: boolean
  /** True once the field holds text `parseAmount` refuses — never true while it's empty. */
  amountInvalid: boolean
  missingRequired: string[]
  save: () => Promise<void>
  lastSaved: ApiTransaction | null
  undo: () => Promise<void>
  dismissSaved: () => void
  fieldErrors: Record<string, string[]>
  notice: string | null
}

export function useEntryForm(bootstrap: Bootstrap, client: ApiClient): EntryForm {
  const [reference, setReference] = useState(bootstrap)
  const [values, setValues] = useState<EntryValues>(() => valuesFromDefaults(bootstrap))
  const [lastSaved, setLastSaved] = useState<ApiTransaction | null>(null)
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({})
  const [notice, setNotice] = useState<string | null>(null)
  // Written only inside `save`, never during render: the same key must survive a retry
  // of the same attempt, and a `ref` is the escape hatch for state a render doesn't own.
  const idempotencyKeyRef = useRef<string | null>(null)

  const parsedAmount = parseAmount(values.amountInput)
  const missingRequired = reference.dimensions
    .filter((dimension) => dimension.is_required && values.dimensionValues[dimension.id] === undefined)
    .map((dimension) => dimension.name)
  const parsedQuantity = parseQuantity(values.quantityInput)
  const quantityValid = values.quantityInput.trim() === '' || parsedQuantity !== null
  const canSave = parsedAmount !== null && missingRequired.length === 0 && quantityValid
  const amountInvalid = values.amountInput !== '' && parsedAmount === null

  // Every setter is the same shape — patch one or more fields onto the current values —
  // except `setDimension`, which needs the current `dimensionValues` to merge into.
  function patch(next: Partial<EntryValues>): void {
    setValues((current) => ({ ...current, ...next }))
  }

  const setQuantityUnit = (unit: EntryValues['quantityUnit']): void => patch({ quantityUnit: unit })
  const setMarketPurchase = (value: boolean): void => patch({ isMarketPurchase: value })
  const setQuantity = (input: string): void => patch({ quantityInput: input })
  const setAmount = (input: string): void => patch({ amountInput: input })
  const setCategory = (id: number): void => patch({ categoryId: id })
  const setType = (type: 'income' | 'expense'): void => patch({ type })
  const setCurrency = (currency: string): void => patch({ currency })
  const setNote = (note: string): void => patch({ note })
  const setDate = (date: string): void => patch({ occurredOn: date })

  function setDimension(dimensionId: number, valueId: number): void {
    setValues((current) => ({
      ...current,
      dimensionValues: { ...current.dimensionValues, [dimensionId]: valueId },
    }))
  }

  // Every branch of a failed save reports through `fieldErrors`/`notice` rather than
  // rejecting: the caller (the tab bar's save button, via `EntryScreen`) never has to
  // `catch` a rejection to give the user a signal, and there is exactly one place — here
  // — that decides what a given failure means.
  async function handleSaveError(error: unknown): Promise<void> {
    const status = readStatus(error)
    const errors = readErrors(error)

    if (status !== 422 || !errors) {
      setNotice(strings.entry.saveFailed)
      return
    }

    if (!isStaleReference(errors)) {
      setFieldErrors(errors)
      return
    }

    // The notice below is the whole message for a stale category/dimension: showing the
    // raw backend string as well ("The selected category_id is invalid.") underneath a
    // friendly "pick again" notice would just repeat the same failure in two registers.
    // Any *other* field the same 422 named still renders normally.
    setFieldErrors(withoutStaleReferenceFields(errors))

    const resolution = await resolveStaleReference(errors, client, values)
    setReference(resolution.reference)
    setNotice(strings.entry.referenceChanged)
    patch({ categoryId: resolution.categoryId, dimensionValues: resolution.dimensionValues })
  }

  async function save(): Promise<void> {
    if (!canSave || parsedAmount === null) return

    setFieldErrors({})
    setNotice(null)

    idempotencyKeyRef.current ??= crypto.randomUUID()
    const key = idempotencyKeyRef.current

    const body: TransactionWrite = {
      type: values.type,
      amount: parsedAmount.amount,
      currency: values.currency,
      occurred_on: values.occurredOn,
      category_id: values.categoryId ?? undefined,
      note: values.note === '' ? null : values.note,
      ...(parsedQuantity !== null ? { quantity: parsedQuantity, quantity_unit: values.quantityUnit } : {}),
      ...(values.isMarketPurchase && values.type === 'expense' ? { is_market_purchase: true } : {}),
      dimension_values: values.dimensionValues,
    }

    try {
      const response = await client.createTransaction(body, key)
      idempotencyKeyRef.current = null
      setLastSaved(response.data)
      setValues(valuesFromDefaults(reference))
    } catch (error) {
      await handleSaveError(error)
    }
  }

  async function undo(): Promise<void> {
    if (lastSaved === null) return
    await client.deleteTransaction(lastSaved.id)
    setLastSaved(null)
  }

  function dismissSaved(): void {
    setLastSaved(null)
  }

  return {
    values,
    categories: reference.categories,
    dimensions: reference.dimensions,
    currencies: reference.currencies,
    setMarketPurchase,
    setQuantity,
    setQuantityUnit,
    setAmount,
    setCategory,
    setDimension,
    setType,
    setCurrency,
    setNote,
    setDate,
    canSave,
    amountInvalid,
    missingRequired,
    save,
    lastSaved,
    undo,
    dismissSaved,
    fieldErrors,
    notice,
  }
}
