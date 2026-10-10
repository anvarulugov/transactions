import { useEffect, useState, type DependencyList } from 'react'

// Duck-typed the same way `entryErrors.ts`'s `readStatus` reads a rejection: the real
// `ApiError` carries `status`, and a test double a plain object of the same shape, so
// neither an `instanceof` check nor a specific error class is required here either.
function statusOf(error: unknown): number | undefined {
  if (typeof error === 'object' && error !== null && 'status' in error) {
    const status = (error as { status?: unknown }).status
    return typeof status === 'number' ? status : undefined
  }
  return undefined
}

export type ReportFetch<T> = {
  data: T | null
  failed: boolean
  /** A 429 specifically — the authenticated API and the export both carry rate limits, so this is a real state, not a hypothetical one worth folding into `failed` alone. */
  rateLimited: boolean
  retry: () => void
}

// The one fetch-with-retry effect behind Summary, Trend and Staff's report requests —
// extracted so a failed request's retry action and its 429 handling exist in exactly one
// place, not copied three times. Each view supplies its own request (`fetchReport`) and
// the exact values that should trigger a refetch (`deps`): the same explicit list every
// fetch effect in this app already passes (`client`, `period.from`, `period.to`, plus
// whichever of `groupBy`/`interval` is its own) — mirrors `useTransactions`'s own
// dependency list in spirit. `retry` bumps a counter that lives outside `deps` on
// purpose: pressing it must re-run the very same request, not wait for one of its real
// inputs to change.
export function useReportFetch<T>(fetchReport: () => Promise<T>, deps: DependencyList): ReportFetch<T> {
  const [data, setData] = useState<T | null>(null)
  const [failed, setFailed] = useState(false)
  const [rateLimited, setRateLimited] = useState(false)
  const [attempt, setAttempt] = useState(0)

  useEffect(() => {
    const refresh = () => setAttempt((value) => value + 1)
    window.addEventListener('transactions-updated', refresh)
    return () => window.removeEventListener('transactions-updated', refresh)
  }, [])

  useEffect(() => {
    let ignore = false
    setFailed(false)
    setRateLimited(false)

    fetchReport()
      .then((result) => {
        if (!ignore) setData(result)
      })
      .catch((error: unknown) => {
        if (ignore) return
        setFailed(true)
        setRateLimited(statusOf(error) === 429)
      })

    return () => {
      ignore = true
    }
    // `deps` is the caller's own explicit dependency list; `attempt` is `retry`'s own
    // trigger, appended rather than folded into `deps` since it isn't one of the
    // request's real inputs.
  }, [...deps, attempt])

  function retry(): void {
    setAttempt((current) => current + 1)
  }

  return { data, failed, rateLimited, retry }
}
