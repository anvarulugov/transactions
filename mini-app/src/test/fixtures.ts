// The shared test kit: every later task imports these instead of inventing its own
// client stub or fixture data, so a shape change (like the Bootstrap wrapping fix in
// this task) only has one place to land.
import type { SessionState } from '../auth/useSession'
import type { ApiClient } from '../api/client'
import type {
  ApiTransaction,
  ApiTransactionRevision,
  ApiUser,
  Bootstrap,
  SummaryReport,
  TransactionResponse,
  TrendReport,
} from '../api/types'

export function clientStub(overrides: Partial<ApiClient> = {}): ApiClient {
  return {
    setToken: vi.fn(),
    clearToken: vi.fn(),
    uploadReceipt: vi.fn(),
    confirmReceipt: vi.fn(),
    receiptImage: vi.fn(),
    authenticate: vi.fn().mockResolvedValue({ token: 'test-token', user: staffUser }),
    bootstrap: vi.fn().mockResolvedValue(bootstrapFixture),
    listTransactions: vi.fn().mockResolvedValue({ data: [], meta: { next_cursor: null } }),
    createTransaction: vi.fn().mockResolvedValue({ data: { id: 1 } } as TransactionResponse),
    updateTransaction: vi.fn().mockResolvedValue({ data: { id: 1 } } as TransactionResponse),
    deleteTransaction: vi.fn().mockResolvedValue(undefined),
    revisions: vi.fn().mockResolvedValue([] as ApiTransactionRevision[]),
    summary: vi.fn().mockResolvedValue({ totals: [], groups: [] }),
    trend: vi.fn().mockResolvedValue({ points: [] }),
    ...overrides,
  }
}

export function clientReturning(report: SummaryReport | TrendReport): ApiClient {
  return clientStub({
    summary: vi.fn().mockResolvedValue(report),
    trend: vi.fn().mockResolvedValue(report),
  })
}

export function clientWithTransactions(items: ApiTransaction[]): ApiClient {
  return clientStub({
    listTransactions: vi.fn().mockResolvedValue({ data: items, meta: { next_cursor: null } }),
    updateTransaction: vi.fn().mockResolvedValue({ data: items[0] ?? { id: 1 } } as TransactionResponse),
    deleteTransaction: vi.fn().mockResolvedValue(undefined),
  })
}

export const staffUser: ApiUser = {
  id: 1,
  telegram_id: 100001,
  name: 'Malika Karimova',
  username: 'malika_staff',
  role: 'staff',
  status: 'active',
  locale: 'uz',
  department: null,
  permissions: { can_see_all: false, can_manage: false },
}

export const managerUser: ApiUser = {
  ...staffUser,
  id: 2,
  telegram_id: 100002,
  name: 'Bekzod Yusupov',
  username: 'bekzod_manager',
  role: 'manager',
  permissions: { can_see_all: false, can_manage: true },
}

export const ownerUser: ApiUser = {
  ...staffUser,
  id: 3,
  telegram_id: 100003,
  name: 'Oybek Rashidov',
  username: 'oybek_owner',
  role: 'owner',
  permissions: { can_see_all: true, can_manage: true },
}

export const adminUser: ApiUser = {
  ...staffUser,
  id: 5,
  telegram_id: 100005,
  name: 'Dilnoza Sodiqova',
  username: 'dilnoza_admin',
  role: 'admin',
  permissions: { can_see_all: true, can_manage: true },
}

export const pendingUser: ApiUser = {
  ...staffUser,
  id: 4,
  telegram_id: 100004,
  name: 'Sardor Nazarov',
  username: 'sardor_pending',
  status: 'pending',
}

export const bootstrapFixture: Bootstrap = {
  user: staffUser,
  categories: [{ id: 7, name: 'Taksi', applies_to: 'expense', children: [] }],
  dimensions: [
    {
      id: 3,
      key: 'branch',
      name: 'Filial',
      is_required: true,
      values: [{ id: 9, name: 'Chilonzor' }],
    },
  ],
  currencies: { UZS: 0, USD: 2 },
  defaults: {
    type: 'expense',
    currency: 'UZS',
    category_id: 7,
    dimension_values: {},
  },
}

export const activeSession: SessionState = {
  kind: 'active',
  user: staffUser,
  bootstrap: bootstrapFixture,
}

// Same shape as `activeSession`, but with a user who may see other people's spend —
// for the `App`-level tests proving the Reports tab actually offers the staff
// comparison to someone permitted to see it (Task 8).
export const managerSession: SessionState = {
  kind: 'active',
  user: managerUser,
  bootstrap: bootstrapFixture,
}
