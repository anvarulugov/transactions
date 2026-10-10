export function receiptMinor(value: string, precision: number): bigint | null {
  const canonical = value.trim().replace(/\s/g, '').replace(',', '.')
  if (!/^\d{1,15}(?:\.\d{1,4})?$/.test(canonical)) return null
  const [whole, fraction = ''] = canonical.split('.')
  if (fraction.slice(precision).replace(/0/g, '') !== '') return null
  const minor = BigInt(whole + fraction.slice(0, precision).padEnd(precision, '0'))
  return minor > 0n && minor <= 1_000_000_000_000_000n ? minor : null
}
export function receiptAmount(value: string): string {
  return value.trim().replace(/\s/g, '').replace(',', '.')
}
export function receiptTotal(items: { amount: string }[], precision: number): string | null {
  let sum = 0n
  for (const item of items) {
    const minor = receiptMinor(item.amount, precision)
    if (minor === null) return null
    sum += minor
  }
  const digits = sum.toString().padStart(precision + 1, '0')
  return precision === 0 ? digits : digits.slice(0, -precision) + '.' + digits.slice(-precision)
}
