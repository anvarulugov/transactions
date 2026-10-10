import type {
  ReceiptDraft,
  ReceiptConfirmation,
  ApiTransaction,
  ApiTransactionRevision,
  AuthExchange,
  Bootstrap,
  CursorPage,
  ReportParams,
  SummaryReport,
  TransactionListParams,
  TransactionResponse,
  TransactionWrite,
  TrendReport,
} from './types'
import { ApiError } from './errors'
import { toQueryString } from './queryString'
import { readBody, extractMessage, extractErrors } from './responseParsing'

export { ApiError } from './errors'

export type ApiClient = {
  setToken(token: string | null): void
  clearToken(): void
  uploadReceipt(image: File): Promise<{ data: ReceiptDraft }>
  confirmReceipt(id: number, body: ReceiptConfirmation): Promise<{ data: ApiTransaction[]; receipt_id: number }>
  receiptImage(id: number): Promise<Blob>
  authenticate(initData: string): Promise<AuthExchange>
  bootstrap(): Promise<Bootstrap>
  listTransactions(params: TransactionListParams): Promise<CursorPage<ApiTransaction>>
  createTransaction(body: TransactionWrite, idempotencyKey?: string): Promise<TransactionResponse>
  updateTransaction(id: number, body: TransactionWrite): Promise<TransactionResponse>
  deleteTransaction(id: number): Promise<void>
  revisions(id: number): Promise<ApiTransactionRevision[]>
  summary(params: ReportParams): Promise<SummaryReport>
  trend(params: ReportParams): Promise<TrendReport>
}

type FetchImpl = typeof fetch

type RequestOptions = {
  /** False for the auth exchange itself, so a bad login cannot retry-loop into itself. */
  allowRetry?: boolean
  blob?: boolean
}

export function createClient(baseUrl: string, fetchImpl: FetchImpl = fetch): ApiClient {
  let token: string | null = null
  let lastInitData: string | null = null
  let reauthPromise: Promise<void> | null = null

  function setToken(next: string | null): void {
    token = next
  }

  function clearToken(): void {
    token = null
  }

  async function request<T>(path: string, init: RequestInit = {}, options: RequestOptions = {}): Promise<T> {
    const headers: Record<string, string> = {
      Accept: 'application/json',
      ...(init.headers as Record<string, string> | undefined),
    }

    if (token) {
      headers.Authorization = `Bearer ${token}`
    }

    const response = await fetchImpl(`${baseUrl}${path}`, { ...init, headers })

    if (response.status === 401 && options.allowRetry !== false && lastInitData) {
      await reauthenticate(lastInitData)
      return request<T>(path, init, { ...options, allowRetry: false })
    }

    if (response.ok && options.blob) return await response.blob() as T

    const body = await readBody(response)

    if (!response.ok) {
      throw new ApiError(response.status, extractMessage(body, response.statusText), extractErrors(body))
    }

    return body as T
  }

  // Concurrent 401s share one re-authentication instead of each firing its own: the
  // check-then-set below is synchronous, so the first caller to see a 401 claims
  // `reauthPromise` before any other caller's continuation can run.
  function reauthenticate(initData: string): Promise<void> {
    reauthPromise ??= performAuth(initData)
      .then((exchange) => {
        if (exchange.token) setToken(exchange.token)
      })
      .finally(() => {
        reauthPromise = null
      })

    return reauthPromise
  }

  function performAuth(initData: string): Promise<AuthExchange> {
    lastInitData = initData

    return request<AuthExchange>(
      '/api/auth/telegram',
      {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ init_data: initData }),
      },
      { allowRetry: false },
    )
  }

  return {
    setToken,
    clearToken,
    authenticate: performAuth,
    uploadReceipt: (image) => {
      const data = new FormData()
      data.append('image', image)
      return request<{ data: ReceiptDraft }>('/api/receipts', { method: 'POST', body: data })
    },
    confirmReceipt: (id, body) => request<{ data: ApiTransaction[]; receipt_id: number }>(`/api/receipts/${id}/confirm`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) }),
    receiptImage: (id) => request<Blob>(`/api/receipts/${id}/image`, {}, { blob: true }),

    bootstrap: () => request<Bootstrap>('/api/bootstrap'),

    listTransactions: (params) =>
      request<CursorPage<ApiTransaction>>(`/api/transactions${toQueryString(params)}`),

    createTransaction: (body, idempotencyKey) =>
      request<TransactionResponse>('/api/transactions', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          ...(idempotencyKey ? { 'Idempotency-Key': idempotencyKey } : {}),
        },
        body: JSON.stringify(body),
      }),

    updateTransaction: (id, body) =>
      request<TransactionResponse>(`/api/transactions/${id}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
      }),

    deleteTransaction: (id) =>
      request<void>(`/api/transactions/${id}`, { method: 'DELETE' }),

    // `TransactionRevisionResource::collection(...)` is the controller action's own
    // top-level return, so — unlike `bootstrap()` — it does carry Laravel's `data`
    // wrapper (`assertJsonCount(1, 'data')` in `UpdateDeleteTransactionTest.php:217`
    // pins the shape); unwrapped here so a caller reads a plain array, the same as
    // every other list this client hands back.
    revisions: (id) =>
      request<{ data: ApiTransactionRevision[] }>(`/api/transactions/${id}/revisions`).then(
        (body) => body.data,
      ),

    summary: (params) => request<SummaryReport>(`/api/reports/summary${toQueryString(params)}`),

    trend: (params) => request<TrendReport>(`/api/reports/trend${toQueryString(params)}`),
  }
}
