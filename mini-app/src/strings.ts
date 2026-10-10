// The single Uzbek string table for the mini app. Draft only: a native speaker reviews
// these before staff see them, same as the Telegram bot's strings (lang/uz/bot.php).
// Grows namespace by namespace as later tasks add the screens that need them.
export const strings = {
  session: {
    loading: 'Yuklanmoqda...',
    // Matches the bot's own wording for the same state (lang/uz/bot.php: pending).
    pending: "Siz hali ro'yxatdan o'tmagansiz. Administrator tasdiqlashini kuting.",
    refusedFallback: 'Kirish rad etildi.',
    errorFallback: "Nimadir xato ketdi. Qaytadan urinib ko'ring.",
    // Shown when Telegram never handed the app any `initData` — a plain browser tab, not
    // the Telegram client. There is nothing to authenticate and nothing a retry would fix.
    noTelegram: "Bu ilova faqat Telegram orqali ochiladi.",
  },
  tabs: {
    add: 'Kiritish',
    reports: 'Hisobotlar',
    history: 'Tarix',
  },
  receipt: {
    title: 'Xarid chekidan kiritish', camera: 'Chekni suratga olish', upload: 'Chek rasmini yuklash', reading: 'Chek o‘qilmoqda...',
    review: 'Ro‘yxat to‘g‘rimi? Rasm bilan solishtirib, kerak bo‘lsa tuzating.',
    photo: 'Chek rasmi', itemName: 'Tovar nomi', amount: 'Summa', total: 'Chek jami', sum: 'Tovarlar jami',
    add: 'Tovar qo‘shish', remove: 'Qatorni olib tashlash', category: 'Turkumni tanlang', unit: 'Birlikni tanlang',
    confirm: 'Ha, xarajatlarga yozilsin', mismatch: 'Tovarlar jami chek summasiga mos emas.',
    required: 'Har bir tovar nomi, turkumi, miqdori, birligi va summasini tekshiring.',
    saved: 'Bozorlik xarajatlari saqlandi.', alreadySaved: 'Bu chek allaqachon xarajatlarga yozilgan.',
    failed: 'Chekni o‘qib bo‘lmadi. Tiniqroq rasmni yuklang.', saving: 'Saqlanmoqda...',
    raw: 'Rasmdan o‘qilgan matn', view: 'Xarid chekini ko‘rish', failedImage: 'Chek rasmini ochib bo‘lmadi.',
    unavailable: 'Ayrim qatorlar aniqlanmagan bo‘lishi mumkin. Rasmni tekshiring.',
  },
  common: {
    retry: 'Qaytadan urinish',
    save: 'Saqlash',
    delete: "O'chirish",
    confirm: 'Tasdiqlash',
    cancel: 'Bekor qilish',
    // The ✕ button on a bottom sheet (details sheet, transaction sheet).
    close: 'Yopish',
  },
  entry: {
    marketPurchase: 'Bozorlik',
    // The entry screen's gradient-header title, above the amount field.
    newEntry: 'Yangi yozuv',
    amount: 'Summa',
    quantity: 'Miqdor',
    quantityUnit: 'Miqdor birligi',
    quantityPlaceholder: 'Masalan: 2,5',
    invalidQuantity: 'Noldan katta miqdor kiriting. Masalan: 2,5 (3 tagacha kasr raqami).',
    category: 'Turkum',
    details: 'Batafsil',
    type: 'Turi',
    expense: 'Xarajat',
    income: 'Daromad',
    currency: 'Valyuta',
    date: 'Sana',
    note: 'Izoh',
    backspace: "O'chirish",
    save: 'Saqlash',
    saved: 'Saqlandi.',
    undo: 'Bekor qilish',
    choose: 'Tanlang',
    // Shown when a 422 names `category_id` or `dimension_values`: the reference row was
    // deactivated since bootstrap loaded, so retrying the same pick cannot succeed.
    referenceChanged: "Ma'lumotlar yangilandi. Iltimos, qayta tanlang.",
    // Shown for any save failure that isn't a validation 422 (network, 500, malformed
    // body): the one signal the brief doesn't have a dedicated flow for, so it borrows
    // `notice` rather than leaving the tap looking like it did nothing at all.
    saveFailed: "Saqlashda xatolik yuz berdi. Qaytadan urinib ko'ring.",
    // Shown under the amount field the moment it holds text `parseAmount` refuses.
    // Mirrors the Telegram bot's own `unparseable` wording (lang/uz/bot.php) so the same
    // grammar failure reads the same way in both surfaces.
    invalidAmount: "Summani tushunmadim. Masalan: 120000, 12.500, 30 ming.",
    // Closes the details sheet — a full-width confirmation button, not a save action
    // (the form isn't submitted until Saqlash on the entry screen itself).
    done: 'Tayyor',
  },
  reports: {
    period: 'Davr',
    thisMonth: 'Shu oy',
    lastMonth: "O'tgan oy",
    // The third option in the same picker (Task 8's approved design: "this month, last
    // month, custom range"), plus the two date inputs and the message shown while the
    // chosen end date comes before its start.
    customRange: 'Boshqa davr',
    rangeFrom: 'Boshlanishi',
    rangeTo: 'Tugashi',
    rangeInvalid: "Tugash sanasi boshlanish sanasidan oldin bo'lishi mumkin emas.",
    groupBy: 'Guruhlash',
    category: 'Turkum',
    interval: 'Oraliq',
    day: 'Kun',
    week: 'Hafta',
    month: 'Oy',
    // Shown instead of a chart with nothing in it: an empty period is a fact worth
    // stating plainly, not a blank rectangle the user has to interpret themselves.
    empty: "Bu davr uchun ma'lumot yo'q.",
    loadFailed: "Hisobotni yuklab bo'lmadi. Qaytadan urinib ko'ring.",
    // Shown instead of `loadFailed` when the request itself reports a 429: the authenticated
    // API and the export both carry rate limits, so this is a state a person actually hits,
    // not a hypothetical one worth folding into the generic message.
    rateLimited: "So'rovlar juda ko'p. Birozdan keyin qaytadan urinib ko'ring.",
    // The report-view switch inside the Reports tab (Task 8): buttons inside the
    // Reports panel, not a second row of app-level tabs. `byStaff` only renders for a
    // user who may see others' spend — see `ReportsScreen`'s permission gate.
    view: "Ko'rinish",
    bySummary: 'Umumiy',
    byTrend: 'Dinamika',
    byStaff: 'Xodimlar',
    // The pie chart's 9th+ category, folded into one slice so the chart never exceeds
    // the validated 8-slot color order (dataviz skill).
    other: 'Boshqa',
    // The donut's centered total label.
    total: 'Jami',
  },
  history: {
    // The collapsible filter panel's toggle button, next to the screen title.
    filters: 'Filtrlar',
    // Filter labels: `category`/`currency` reuse the same words as the entry form's
    // fields, since they name the same concepts; only the "all X" options below are new.
    category: 'Turkum',
    currency: 'Valyuta',
    allCategories: 'Barcha turkumlar',
    allCurrencies: 'Barcha valyutalar',
    allValues: 'Barchasi',
    empty: "Bu davr uchun yozuvlar yo'q.",
    loadFailed: "Tarixni yuklab bo'lmadi. Qaytadan urinib ko'ring.",
    deleteConfirm: "Haqiqatan ham o'chirmoqchimisiz?",
    // Shown under the edit form's save button when the currency was changed but the
    // amount field is still empty — the server requires a fresh amount alongside any
    // currency change (`transactionEdit.ts`'s doc comment).
    currencyNeedsAmount: "Valyutani o'zgartirish uchun summani kiriting.",
    // The edit form's amount field placeholder: unlike every other field here, amount
    // starts empty (not pre-filled with the current value) and this text says why.
    amountUnchanged: "O'zgarishsiz qoladi",
    // Shown for any save/delete failure that isn't a validation 422 — the server's own
    // `message` (a 403/404 refusal, or anything else) reaches the user verbatim rather
    // than being replaced with a client-side guess about why it failed.
    actionFailed: "Amalni bajarib bo'lmadi.",
    // The read-only recap in the sheet: labels for fields no edit form ever showed
    // (amount, whose record it is, which department) plus the revision count.
    person: 'Xodim',
    department: "Bo'lim",
    noDepartment: "Bo'lim yo'q",
    revisions: "O'zgarishlar soni",
    revisionsLoading: '…',
    revisionsFailed: "Yuklab bo'lmadi.",
  },
} as const
