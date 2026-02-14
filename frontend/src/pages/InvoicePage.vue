<script setup>
import '../styles/invoice.css'
import logoUrl from '/assets/voldhaul-logo.svg'
import { ref, computed, onMounted, watch } from 'vue'
import { fetchJson } from '../utils/api'
import { money } from '../utils/format'

const loading = ref(false)
const saving = ref(false)
const err = ref('')
const okMsg = ref('')

const clients = ref([])

const presets = [
  'Today','Yesterday','2 Days Ago','3 Days Ago','This Week','Last Week','Two Weeks Ago',
  'This Month','Last Month','2 Months Ago','3 Months Ago','This Quarter','Last Quarter',
  'Year To Date','Custom',
]

const form = ref({
  client_id: '',
  date_range: 'Last Week',
  start: '',
  end: '',
  invoice_number: '',
})

const preview = ref(null)

// ✅ after save we keep invoice id here -> enables PDF/XLS
const savedInvoiceId = ref(null)

const canPreview = computed(() =>
    String(form.value.client_id || '').trim() !== '' &&
    String(form.value.start || '').trim() !== '' &&
    String(form.value.end || '').trim() !== ''
)

function pad2(n) {
  const x = Number(n || 0)
  return x < 10 ? `0${x}` : `${x}`
}
function toYMD(d) {
  const dt = new Date(d)
  return `${dt.getFullYear()}-${pad2(dt.getMonth() + 1)}-${pad2(dt.getDate())}`
}
function startOfDay(d) {
  const dt = new Date(d)
  dt.setHours(0, 0, 0, 0)
  return dt
}
function addDays(d, days) {
  const dt = new Date(d)
  dt.setDate(dt.getDate() + days)
  return dt
}
function startOfWeek(d) {
  const dt = startOfDay(d)
  const day = dt.getDay()
  const diff = (day === 0 ? -6 : 1) - day
  dt.setDate(dt.getDate() + diff)
  return dt
}
function endOfWeek(d) {
  const s = startOfWeek(d)
  return addDays(s, 6)
}
function startOfMonth(d) {
  const dt = startOfDay(d)
  dt.setDate(1)
  return dt
}
function endOfMonth(d) {
  const dt = startOfMonth(d)
  dt.setMonth(dt.getMonth() + 1)
  dt.setDate(0)
  return dt
}
function startOfQuarter(d) {
  const dt = startOfDay(d)
  const m = dt.getMonth()
  const qStart = Math.floor(m / 3) * 3
  dt.setMonth(qStart, 1)
  return dt
}
function endOfQuarter(d) {
  const s = startOfQuarter(d)
  const dt = new Date(s)
  dt.setMonth(dt.getMonth() + 3, 0)
  return dt
}
function computeRange(preset) {
  const now = new Date()
  const today = startOfDay(now)

  switch (preset) {
    case 'Today': return [toYMD(today), toYMD(today)]
    case 'Yesterday': { const d = addDays(today, -1); return [toYMD(d), toYMD(d)] }
    case '2 Days Ago': { const d = addDays(today, -2); return [toYMD(d), toYMD(d)] }
    case '3 Days Ago': { const d = addDays(today, -3); return [toYMD(d), toYMD(d)] }
    case 'This Week': { const s = startOfWeek(today); const e = endOfWeek(today); return [toYMD(s), toYMD(e)] }
    case 'Last Week': {
      const s = addDays(startOfWeek(today), -7)
      const e = addDays(endOfWeek(today), -7)
      return [toYMD(s), toYMD(e)]
    }
    case 'Two Weeks Ago': {
      const s = addDays(startOfWeek(today), -14)
      const e = addDays(endOfWeek(today), -14)
      return [toYMD(s), toYMD(e)]
    }
    case 'This Month': { const s = startOfMonth(today); const e = endOfMonth(today); return [toYMD(s), toYMD(e)] }
    case 'Last Month': {
      const d = new Date(today); d.setMonth(d.getMonth() - 1)
      return [toYMD(startOfMonth(d)), toYMD(endOfMonth(d))]
    }
    case '2 Months Ago': {
      const d = new Date(today); d.setMonth(d.getMonth() - 2)
      return [toYMD(startOfMonth(d)), toYMD(endOfMonth(d))]
    }
    case '3 Months Ago': {
      const d = new Date(today); d.setMonth(d.getMonth() - 3)
      return [toYMD(startOfMonth(d)), toYMD(endOfMonth(d))]
    }
    case 'This Quarter': { const s = startOfQuarter(today); const e = endOfQuarter(today); return [toYMD(s), toYMD(e)] }
    case 'Last Quarter': {
      const d = new Date(today); d.setMonth(d.getMonth() - 3)
      return [toYMD(startOfQuarter(d)), toYMD(endOfQuarter(d))]
    }
    case 'Year To Date': { const s = new Date(today.getFullYear(), 0, 1); return [toYMD(s), toYMD(today)] }
    case 'Custom':
    default: return [form.value.start || '', form.value.end || '']
  }
}

// Table dates -> MM/DD/YYYY
function fmtDateOnly(dt) {
  if (!dt) return ''
  const d = new Date(dt)
  const mm = String(d.getMonth() + 1).padStart(2, '0')
  const dd = String(d.getDate()).padStart(2, '0')
  const yyyy = d.getFullYear()
  return `${mm}/${dd}/${yyyy}`
}

// Header Date Range -> MM/DD/YY
function fmtMDYY(ymd) {
  if (!ymd || String(ymd).length < 10) return ''
  const y = Number(String(ymd).slice(0, 4))
  const m = Number(String(ymd).slice(5, 7))
  const d = Number(String(ymd).slice(8, 10))

  const mm = String(m).padStart(2, '0')
  const dd = String(d).padStart(2, '0')
  const yy = String(y).slice(-2)

  return `${mm}/${dd}/${yy}`
}

// ✅ if user changes anything, they are no longer exporting the previous saved invoice
function invalidateSaved() {
  savedInvoiceId.value = null
}

let debounceT = null
watch(form, () => {
  invalidateSaved()
  clearTimeout(debounceT)
  debounceT = setTimeout(() => runPreview(), 250)
}, { deep: true })

watch(() => form.value.date_range, (preset) => {
  invalidateSaved()
  if (preset !== 'Custom') {
    const [s, e] = computeRange(preset)
    form.value.start = s
    form.value.end = e
  }
})

async function loadClients() {
  clients.value = await fetchJson('/api/invoices/clients')
}

async function runPreview() {
  err.value = ''
  okMsg.value = ''
  if (!canPreview.value) {
    preview.value = null
    return
  }

  loading.value = true
  try {
    const qs = new URLSearchParams({
      client_id: String(form.value.client_id),
      start: String(form.value.start),
      end: String(form.value.end),
      invoice_number: String(form.value.invoice_number || ''),
    }).toString()

    preview.value = await fetchJson(`/api/invoices/preview?${qs}`)
  } catch (e) {
    err.value = e?.message || String(e)
    preview.value = null
  } finally {
    loading.value = false
  }
}

async function saveInvoice() {
  err.value = ''
  okMsg.value = ''

  if (!preview.value?.invoice) {
    err.value = 'Preview first.'
    return
  }

  saving.value = true
  try {
    const inv = preview.value.invoice

    const res = await fetchJson('/api/invoices/save', {
      method: 'POST',
      body: {
        id_client: inv.id_client,
        invoice_startdate: inv.invoice_startdate,
        invoice_enddate: inv.invoice_enddate,
        invoice_number: inv.invoice_number,
        invoice_total_amount: inv.invoice_total_amount,
        invoice_loadcount: inv.invoice_loadcount,
        invoice_tontotal: inv.invoice_tontotal,
        invoice_milestotal: inv.invoice_milestotal,
      },
    })

    savedInvoiceId.value = res?.id_bill_invoices ?? null
    okMsg.value = `Saved! Invoice ID: ${savedInvoiceId.value ?? ''}`
  } catch (e) {
    err.value = e?.message || String(e)
    savedInvoiceId.value = null
  } finally {
    saving.value = false
  }
}

function downloadPdf() {
  if (!savedInvoiceId.value) return
  window.open(`/api/invoices/${savedInvoiceId.value}/pdf`, '_blank')
}

function downloadXls() {
  if (!savedInvoiceId.value) return
  window.open(`/api/invoices/${savedInvoiceId.value}/xls`, '_blank')
}

onMounted(async () => {
  await loadClients()
  const [s, e] = computeRange(form.value.date_range)
  form.value.start = s
  form.value.end = e
})
</script>

<template>
  <div class="invoicePage">
    <div class="invoiceTop">
      <div class="invoiceTitle">Invoice Maker</div>
      <div class="invoiceActions">
        <button class="btn" :disabled="saving || !preview?.invoice" @click="saveInvoice">
          {{ saving ? 'Saving...' : 'Save Invoice' }}
        </button>

        <!-- ✅ enabled only after save -->
        <button class="btn ghost" :disabled="!savedInvoiceId" @click="downloadPdf">PDF</button>
        <button class="btn ghost" :disabled="!savedInvoiceId" @click="downloadXls">XLS</button>
      </div>
    </div>

    <div v-if="err" class="err">{{ err }}</div>
    <div v-if="okMsg" class="ok">{{ okMsg }}</div>

    <div class="invoiceGrid">
      <!-- LEFT -->
      <div class="controlsBox">
        <div class="ctrlRow">
          <div class="ctrlLabel">Client:</div>
          <select class="ctrlInput" v-model="form.client_id">
            <option value="">Select...</option>
            <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </div>

        <div class="ctrlRow">
          <div class="ctrlLabel">Date Range:</div>
          <select class="ctrlInput" v-model="form.date_range">
            <option v-for="p in presets" :key="p" :value="p">{{ p }}</option>
          </select>
        </div>

        <div class="ctrlRow">
          <div class="ctrlLabel">Start Date:</div>
          <input class="ctrlInput" type="date" v-model="form.start" />
        </div>

        <div class="ctrlRow">
          <div class="ctrlLabel">End Date:</div>
          <input class="ctrlInput" type="date" v-model="form.end" />
        </div>

        <div class="ctrlRow">
          <div class="ctrlLabel">Invoice #:</div>
          <input class="ctrlInput" v-model="form.invoice_number" placeholder="auto if empty" />
        </div>

        <button class="btn full" :disabled="loading || !canPreview" @click="runPreview">
          {{ loading ? 'Loading...' : 'Refresh Preview' }}
        </button>
      </div>

      <!-- RIGHT -->
      <div class="previewBox">
        <div v-if="!preview" class="empty">Select Client + Dates to preview.</div>

        <div v-else>
          <div class="serviceHeader">
            <div class="serviceHeaderLeft">
              <img :src="logoUrl" class="logoImg" alt="Voldhaul Logo" />
            </div>

            <div class="serviceHeaderCenter">
              <div class="serviceTitle">
                Service Invoice&nbsp;&nbsp;--&nbsp;&nbsp;<span class="mono">{{ preview.invoice.invoice_number }}</span>
              </div>
              <div class="serviceClient">{{ preview.invoice.client_name || '' }}</div>
              <div class="serviceEmail">Please email invoices@voldhaul.com with any changes or corrections.</div>
            </div>

            <div class="serviceHeaderRight">
              <div class="serviceCompany">Voldhaul LLC</div>
              <div>5786 SEVEN RIVERS HWY</div>
              <div>ARTESIA, NM&nbsp;&nbsp;88210</div>
            </div>
          </div>

          <div class="dateRangeRow">
            <div class="dateRangeLabel">Date Range:</div>
            <div class="dateRangeVal">{{ fmtMDYY(preview.invoice.invoice_startdate) }}</div>
            <div class="dateRangeArrow">→</div>
            <div class="dateRangeVal">{{ fmtMDYY(preview.invoice.invoice_enddate) }}</div>
          </div>

          <div class="whiteSpacer"></div>

          <div class="gridTableWrap">
            <table class="gridTable">
              <thead>
              <tr class="totalsRow">
                <th></th><th></th><th></th>
                <th class="totalsInline mono">
                  Load Count&nbsp;<span class="totalsNum">{{ preview.invoice.invoice_loadcount }}</span>
                </th>
                <th></th>
                <th class="num totalsNum mono">{{ preview.invoice.invoice_tontotal }}</th>
                <th class="num totalsNum mono">{{ preview.invoice.invoice_milestotal }}</th>
                <th></th>
                <th class="num totalsNum mono">{{ money(preview.invoice.invoice_total_amount) }}</th>
              </tr>

              <tr class="colsRow">
                <th>Well/Job</th>
                <th>Date</th>
                <th>Driver</th>
                <th>Load #</th>
                <th>BOL</th>
                <th>Tons</th>
                <th>Miles</th>
                <th>Rate/Ton</th>
                <th>Load Pay</th>
              </tr>
              </thead>

              <tbody>
              <tr v-if="(preview.rows || []).length === 0">
                <td colspan="9" class="muted">No rows for selected filters.</td>
              </tr>

              <tr v-for="r in preview.rows" :key="r.id_load + '-' + r.load_no">
                <td>{{ r.well_job }}</td>
                <td class="mono">{{ fmtDateOnly(r.date) }}</td>
                <td>{{ r.driver }}</td>
                <td class="mono">{{ r.load_no }}</td>
                <td class="mono">{{ r.bol }}</td>
                <td class="mono">{{ r.tons }}</td>
                <td class="mono">{{ r.miles }}</td>
                <td class="mono">{{ money(r.rate_ton) }}</td>
                <td class="mono">{{ money(r.load_pay) }}</td>
              </tr>
              </tbody>
            </table>
          </div>

        </div>
      </div>
    </div>
  </div>
</template>
