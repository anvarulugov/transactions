export function parseQuantity(input: string): string | null {
  const value = input.trim().replace(',', '.')
  if (value === '') return null
  if (!/^\d{1,9}(?:\.\d{1,3})?$/.test(value)) return null
  const [whole, fraction] = value.split('.')
  const normalizedWhole = whole.replace(/^0+(?=\d)/, '')
  if (/^0(?:\.0+)?$/.test(`${normalizedWhole}${fraction ? `.${fraction}` : ''}`)) return null
  return `${normalizedWhole}${fraction ? `.${fraction}` : ''}`
}

export function formatQuantity(value: string): string {
  return value.replace(/(\.\d*?)0+$/, '$1').replace(/\.$/, '').replace('.', ',')
}
