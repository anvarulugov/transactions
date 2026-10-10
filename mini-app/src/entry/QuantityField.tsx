import { useId } from 'react'
import { strings } from '../strings'
import { parseQuantity } from './parseQuantity'

type Unit = 'kg' | 'litr' | 'dona'
type Props = { value: string; unit: Unit; onChange: (value: string) => void; onUnitChange: (unit: Unit) => void }

export function QuantityField({ value, unit, onChange, onUnitChange }: Props) {
  const quantityId = useId()
  const invalid = value.trim() !== '' && parseQuantity(value) === null
  return (
    <div style={{ background: 'var(--field)', borderRadius: 18, padding: '12px 16px' }}>
      <div className="flex items-center gap-3">
        <label htmlFor={quantityId} style={{ font: '600 13px/1.4 "Plus Jakarta Sans"', color: 'var(--teal-900)' }}>{strings.entry.quantity}</label>
        <input id={quantityId} type="text" inputMode="decimal" value={value} onChange={(event) => onChange(event.target.value)}
          placeholder={strings.entry.quantityPlaceholder} aria-invalid={invalid}
          style={{ minWidth: 0, flex: 1, width: 80, textAlign: 'right', border: 0, background: 'transparent', font: '600 16px/1.4 "Plus Jakarta Sans"', color: 'var(--ink)', outline: 'none' }} />
        <select aria-label={strings.entry.quantityUnit} value={unit} onChange={(event) => onUnitChange(event.target.value as Unit)}
          style={{ minWidth: 70, border: 0, borderRadius: 8, padding: '8px', background: 'var(--surface)', color: 'var(--ink)', font: '600 15px/1.4 "Plus Jakarta Sans"' }}>
          <option value="kg">kg</option>
          <option value="litr">litr</option>
          <option value="dona">dona</option>
        </select>
      </div>
      {invalid ? <p role="alert" className="mt-2 text-sm" style={{ color: 'var(--expense)' }}>{strings.entry.invalidQuantity}</p> : null}
    </div>
  )
}
