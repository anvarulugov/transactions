import { act, renderHook, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { bootstrapFixture, clientStub } from '../test/fixtures'
import { strings } from '../strings'
import { useEntryForm } from './useEntryForm'
import { EntryScreen } from './EntryScreen'
import { parseQuantity, formatQuantity } from './parseQuantity'

it.each([['2,5', '2.5'], ['2.5', '2.5'], ['0,125', '0.125'], ['02', '2']])('parses kilogram input %s', (input, value) => {
  expect(parseQuantity(input)).toBe(value)
})
it.each(['0', '-1', '1,0001', '1000000000', 'abc'])('rejects invalid quantity %s', (input) => {
  expect(parseQuantity(input)).toBeNull()
})
it('keeps a decimal kilogram value separate from the money grammar', async () => {
  const client = clientStub()
  const { result } = renderHook(() => useEntryForm(bootstrapFixture, client))
  act(() => { result.current.setAmount('120000'); result.current.setDimension(3, 9); result.current.setQuantity('2,5') })
  await act(async () => result.current.save())
  expect(client.createTransaction).toHaveBeenCalledWith(expect.objectContaining({ amount: '120000', quantity: '2.5', quantity_unit: 'kg' }), expect.any(String))
  expect(result.current.values.quantityInput).toBe('')
  expect(formatQuantity('2.500')).toBe('2,5')
})
it('disables saving with invalid kilograms but allows blank optional quantity', () => {
  const { result } = renderHook(() => useEntryForm(bootstrapFixture, clientStub()))
  act(() => { result.current.setAmount('1000'); result.current.setDimension(3, 9); result.current.setQuantity('-1') })
  expect(result.current.canSave).toBe(false)
  act(() => result.current.setQuantity(''))
  expect(result.current.canSave).toBe(true)
})
it('shows a directly editable kilograms field in the entry screen', async () => {
  render(<EntryScreen bootstrap={bootstrapFixture} client={clientStub()} />)
  await userEvent.type(screen.getByLabelText(strings.entry.quantity), '2,5')
  expect(screen.getByLabelText(strings.entry.quantity)).toHaveValue('2,5')
})

it.each(['kg', 'litr', 'dona'] as const)('saves the selected %s unit separately from money', async (unit) => {
  const client = clientStub()
  const { result } = renderHook(() => useEntryForm(bootstrapFixture, client))
  act(() => { result.current.setAmount('1000'); result.current.setDimension(3, 9); result.current.setQuantity('3'); result.current.setQuantityUnit(unit) })
  await act(async () => result.current.save())
  expect(client.createTransaction).toHaveBeenCalledWith(expect.objectContaining({ quantity: '3', quantity_unit: unit }), expect.any(String))
})
it('offers kg litr and dona and allows selecting a unit', async () => {
  render(<EntryScreen bootstrap={bootstrapFixture} client={clientStub()} />)
  const selector = screen.getByRole('combobox', { name: strings.entry.quantityUnit })
  expect(screen.getByRole('option', { name: 'kg' })).toBeInTheDocument()
  expect(screen.getByRole('option', { name: 'litr' })).toBeInTheDocument()
  expect(screen.getByRole('option', { name: 'dona' })).toBeInTheDocument()
  await userEvent.selectOptions(selector, 'litr')
  expect(selector).toHaveValue('litr')
})
