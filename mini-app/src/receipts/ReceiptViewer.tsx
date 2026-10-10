import { useEffect, useState } from 'react'
import { strings } from '../strings'
export function ReceiptViewer({ id, load }: { id: number; load: (id: number) => Promise<Blob> }) {
  const [url, setUrl] = useState<string | null>(null)
  const [error, setError] = useState(false)
  const [loading, setLoading] = useState(false)
  useEffect(() => () => { if (url) URL.revokeObjectURL(url) }, [url])
  async function open() {
    setLoading(true); setError(false)
    try { setUrl(URL.createObjectURL(await load(id))) } catch { setError(true) } finally { setLoading(false) }
  }
  return <div>
    <button type="button" disabled={loading} onClick={() => void open()}>{strings.receipt.view}</button>
    {error ? <p role="alert">{strings.receipt.failedImage}</p> : null}
    {url ? <div role="dialog" aria-label={strings.receipt.photo} className="fixed inset-0 z-50 flex flex-col" style={{ background: 'var(--surface)', padding: 16, overflow: 'auto' }}>
      <button type="button" onClick={() => setUrl(null)}>{strings.common.close}</button>
      <img src={url} alt={strings.receipt.photo} style={{ width: '100%', height: 'auto', marginTop: 12 }} />
    </div> : null}
  </div>
}
