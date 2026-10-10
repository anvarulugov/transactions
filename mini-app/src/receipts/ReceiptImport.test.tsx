import { act, fireEvent, render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { bootstrapFixture, clientStub } from '../test/fixtures'
import { strings } from '../strings'
import { ReceiptImport } from './ReceiptImport'
import { receiptMinor, receiptTotal } from './money'
vi.mock('./compressPhoto', () => ({ compressPhoto: async (file: File) => file }))
const draft = { id: 1, text: 'Sut 3 litr 30000', items: [{ name: 'Sut', category_id: 7, quantity: '3', quantity_unit: 'litr' as const, amount: '30000' }], total: '30000', currency: 'UZS', confirmed: false }
beforeEach(() => { URL.createObjectURL = vi.fn(() => 'blob:receipt'); URL.revokeObjectURL = vi.fn() })
it('requires review before saving and preserves the selected unit', async () => {
  const client = clientStub({ uploadReceipt: vi.fn().mockResolvedValue({ data: draft }), confirmReceipt: vi.fn().mockResolvedValue({ data: [], receipt_id: 1 }) })
  render(<ReceiptImport client={client} bootstrap={bootstrapFixture} occurredOn="2026-10-09" dimensionValues={{ 3: 9 }} />)
  fireEvent.change(screen.getByLabelText(strings.receipt.upload), { target: { files: [new File(['image'], 'chek.jpg', { type: 'image/jpeg' })] } })
  await screen.findByRole('dialog')
  expect(client.confirmReceipt).not.toHaveBeenCalled()
  await userEvent.clear(screen.getByLabelText(strings.receipt.total))
  await userEvent.type(screen.getByLabelText(strings.receipt.total), '40000')
  expect(screen.getByRole('button', { name: strings.receipt.confirm })).toBeDisabled()
  await userEvent.clear(screen.getByLabelText(strings.receipt.total))
  await userEvent.type(screen.getByLabelText(strings.receipt.total), '30000')
  await userEvent.click(screen.getByRole('button', { name: strings.receipt.confirm }))
  await waitFor(() => expect(client.confirmReceipt).toHaveBeenCalledTimes(1))
  expect(client.confirmReceipt).toHaveBeenCalledWith(1, expect.objectContaining({ confirmed: true, total: '30000', items: [expect.objectContaining({ quantity: '3', quantity_unit: 'litr' })] }))
})
it('does not confirm a receipt without an identified unit', async () => {
  const client = clientStub({ uploadReceipt: vi.fn().mockResolvedValue({ data: { ...draft, items: [{ ...draft.items[0], quantity_unit: null }] } }) })
  render(<ReceiptImport client={client} bootstrap={bootstrapFixture} occurredOn="2026-10-09" dimensionValues={{ 3: 9 }} />)
  await act(async () => fireEvent.change(screen.getByLabelText(strings.receipt.upload), { target: { files: [new File(['x'], 'chek.jpg')] } }))
  expect(screen.getByRole('button', { name: strings.receipt.confirm })).toBeDisabled()
})
it('compares receipt money in integer minor units', () => {
  expect(receiptMinor('30000', 0)).toBe(30000n)
  expect(receiptMinor('2,50', 2)).toBe(250n)
  expect(receiptMinor('2.50', 0)).toBeNull()
  expect(receiptTotal([{ amount: '0.10' }, { amount: '0.20' }], 2)).toBe('0.30')
})
