import { render, screen } from '@testing-library/react'
import { DetailsSheet } from './DetailsSheet'
import type { EntryValues } from './useEntryForm'
import { bootstrapFixture } from '../test/fixtures'

function values(overrides: Partial<EntryValues> = {}): EntryValues {
  return {
    type: 'expense',
    currency: 'UZS',
    categoryId: 7,
    dimensionValues: {},
    isMarketPurchase: false,
    quantityInput: '',
    quantityUnit: 'kg',
    amountInput: '',
    note: '',
    occurredOn: '2026-07-31',
    ...overrides,
  }
}

const noop = () => {}

// `DetailsSheet` is now a fully controlled bottom modal — no local open state, no
// force-open on a missing required dimension (that guarantee moved to `EntryScreen`'s
// coral dot + disabled Saqlash; see that component's doc comment).
it('shows its fields, including every dimension, while open', () => {
  render(
    <DetailsSheet
      open={true}
      onClose={noop}
      values={values()}
      dimensions={bootstrapFixture.dimensions}
      currencies={bootstrapFixture.currencies}
      onCurrencyChange={noop}
      onDateChange={noop}
      onNoteChange={noop}
      onDimensionChange={noop}
    />,
  )

  expect(screen.getByLabelText('Filial')).toBeVisible()
})

it('renders nothing while closed', () => {
  render(
    <DetailsSheet
      open={false}
      onClose={noop}
      values={values()}
      dimensions={bootstrapFixture.dimensions}
      currencies={bootstrapFixture.currencies}
      onCurrencyChange={noop}
      onDateChange={noop}
      onNoteChange={noop}
      onDimensionChange={noop}
    />,
  )

  expect(screen.queryByLabelText('Filial')).not.toBeInTheDocument()
})
