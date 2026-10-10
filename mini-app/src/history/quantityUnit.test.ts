import { diffValues, valuesFromTransaction } from './transactionEdit'
import type { ApiTransaction } from '../api/types'
const transactionFixture: ApiTransaction = {
  id: 1, type: 'expense', amount_minor: 1000, amount: '1000', currency: 'UZS', occurred_on: '2026-10-08',
  note: null, category: { id: 1, name: 'Grechka' }, user: { id: 1, name: 'User' }, department: null,
  dimension_values: [], created_at: null, updated_at: null,
}

it('keeps litres when opening an existing transaction and sends units when changed', () => {
  const original = valuesFromTransaction({ ...transactionFixture, quantity: '3.000', quantity_unit: 'litr', quantity_kg: null })
  expect(original.quantityInput).toBe('3')
  expect(original.quantityUnit).toBe('litr')
  expect(diffValues(original, { ...original, note: 'new' }, null)).toEqual({ note: 'new' })
  expect(diffValues(original, { ...original, quantityUnit: 'dona' }, null)).toEqual({ quantity: '3', quantity_unit: 'dona' })
  expect(diffValues(original, { ...original, quantityInput: '' }, null)).toEqual({ quantity: null, quantity_unit: null })
})
