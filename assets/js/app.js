/* ── AquaBill app.js v1.4 ──────────────────────────────────────────────── */

// ── Sidebar toggle ────────────────────────────────────────────────────────────
const isMobile = () => window.innerWidth <= 768;

function toggleSidebar() {
  if (isMobile()) {
    const sidebar  = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebar-backdrop');
    const isOpen   = sidebar.classList.contains('mobile-open');
    if (isOpen) { closeMobileSidebar(); } else { openMobileSidebar(); }
  } else {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('collapsed');
    document.body.classList.toggle('sidebar-collapsed');
    localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
  }
}

function openMobileSidebar() {
  document.getElementById('sidebar')?.classList.add('mobile-open');
  const bd = document.getElementById('sidebar-backdrop');
  if (bd) bd.classList.add('visible');
  document.body.style.overflow = 'hidden';
}

function closeMobileSidebar() {
  document.getElementById('sidebar')?.classList.remove('mobile-open');
  const bd = document.getElementById('sidebar-backdrop');
  if (bd) bd.classList.remove('visible');
  document.body.style.overflow = '';
}

// Restore desktop collapsed state on load
(function() {
  if (!isMobile() && localStorage.getItem('sidebarCollapsed') === 'true') {
    document.getElementById('sidebar')?.classList.add('collapsed');
    document.body.classList.add('sidebar-collapsed');
  }
})();

window.addEventListener('resize', () => { if (!isMobile()) closeMobileSidebar(); });

// ── Flash auto-dismiss ────────────────────────────────────────────────────────
setTimeout(() => {
  const f = document.getElementById('flash-msg');
  if (!f) return;
  f.style.transition = 'opacity .5s';
  f.style.opacity = '0';
  setTimeout(() => f?.remove(), 500);
}, 4500);

// ── Confirm delete ────────────────────────────────────────────────────────────
function confirmDelete(msg) {
  return confirm(msg || 'Are you sure you want to delete this record?');
}

// ── Bar chart ─────────────────────────────────────────────────────────────────
function renderBarChart(containerId, data) {
  const el = document.getElementById(containerId);
  if (!el || !data?.length) return;
  const max = Math.max(...data.map(d => d.value), 1);
  el.style.height = '100px';
  el.innerHTML = data.map(d => `
    <div class="bar-col">
      <div class="bar" style="height:${Math.max((d.value / max) * 90, 3)}%" title="${d.label}: ${d.value}"></div>
      <span class="bar-label">${d.label}</span>
    </div>`).join('');
}

// ── Print receipt (shows ONLY the receipt) ───────────────────────────────────
function esc(s) {
  return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function printReceipt(html) {
  const slot = document.getElementById('printable-receipt');
  if (slot) { slot.innerHTML = html; slot.style.display = 'block'; }
  window.print();
  setTimeout(() => { if (slot) { slot.innerHTML=''; slot.style.display='none'; } }, 1200);
}

function buildReceiptHtml(d) {
  const amt = parseFloat(d.amount||0).toLocaleString('en-PH',{minimumFractionDigits:2});
  return `
    <div class="rp-header">
      <div class="rp-org">AQUABILL COOP. INC.</div>
      <div class="rp-address">San Juan, Siquijor, Philippines</div>
      <div class="rp-title">&#x2014; OFFICIAL RECEIPT &#x2014;</div>
    </div>
    <div class="rp-row"><span class="rp-label">Receipt No:</span><span>${esc(d.receipt_no)}</span></div>
    <div class="rp-row"><span class="rp-label">Date:</span><span>${esc(d.payment_date)}</span></div>
    <hr class="rp-divider">
    <div class="rp-row"><span class="rp-label">Customer:</span><span>${esc(d.cname)}</span></div>
    <div class="rp-row"><span class="rp-label">Account No:</span><span>${esc(d.cid)}</span></div>
    <div class="rp-row"><span class="rp-label">Bill Period:</span><span>${esc(d.billing_month)}</span></div>
    <div class="rp-row"><span class="rp-label">Method:</span><span>${esc(d.method)}</span></div>
    ${d.notes ? `<div class="rp-row"><span class="rp-label">Notes:</span><span>${esc(d.notes)}</span></div>` : ''}
    <hr class="rp-divider">
    <div class="rp-amount">&#x20B1;${amt}</div>
    <hr class="rp-divider">
    <div class="rp-footer">
      Thank you for your payment!<br>
      This serves as your official receipt.<br>
      AquaBill Coop. Inc. &mdash; San Juan, Siquijor, Philippines
    </div>`;
}

function buildBillHtml(d) {
  const total = parseFloat(d.total||0).toLocaleString('en-PH',{minimumFractionDigits:2});
  const paid  = parseFloat(d.paid_amount||0).toFixed(2);
  const bal   = (parseFloat(d.total||0)-parseFloat(d.paid_amount||0)).toFixed(2);
  const pen   = parseFloat(d.penalty||0);
  return `
    <div class="rp-header">
      <div class="rp-org">AQUABILL COOP. INC.</div>
      <div class="rp-address">San Juan, Siquijor, Philippines</div>
      <div class="rp-title">&#x2014; WATER BILL STATEMENT &#x2014;</div>
    </div>
    <div class="rp-row"><span class="rp-label">Bill No:</span><span>${esc(d.id)}</span></div>
    <div class="rp-row"><span class="rp-label">Period:</span><span>${esc(d.billing_month)}</span></div>
    <div class="rp-row"><span class="rp-label">Due Date:</span><span>${esc(d.due_date)}</span></div>
    <hr class="rp-divider">
    <div class="rp-row"><span class="rp-label">Customer:</span><span>${esc(d.cname)}</span></div>
    <div class="rp-row"><span class="rp-label">Account No:</span><span>${esc(d.cid)}</span></div>
    <div class="rp-row"><span class="rp-label">Meter No:</span><span>${esc(d.meter_no)}</span></div>
    <div class="rp-row"><span class="rp-label">Address:</span><span>${esc(d.address||'')}</span></div>
    <hr class="rp-divider">
    <div class="rp-row"><span>Previous Reading:</span><span>${esc(d.prev_reading)} m&#xB3;</span></div>
    <div class="rp-row"><span>Current Reading:</span><span>${esc(d.curr_reading)} m&#xB3;</span></div>
    <div class="rp-row"><span class="rp-label">Consumption:</span><span class="rp-label">${esc(d.consumption)} m&#xB3;</span></div>
    <hr class="rp-divider">
    <div class="rp-row"><span>Base Charge:</span><span>&#x20B1;${parseFloat(d.base_charge||120).toFixed(2)}</span></div>
    <div class="rp-row"><span>Usage (${esc(d.consumption)}m&#xB3; &times; &#x20B1;${esc(d.rate_per_cubic||35)}):</span><span>&#x20B1;${(d.consumption*(d.rate_per_cubic||35)).toFixed(2)}</span></div>
    ${pen>0?`<div class="rp-row"><span>Penalty:</span><span>&#x20B1;${pen.toFixed(2)}</span></div>`:''}
    <hr class="rp-divider">
    <div class="rp-amount">&#x20B1;${total}</div>
    <div class="rp-row"><span>Amount Paid:</span><span>&#x20B1;${paid}</span></div>
    <div class="rp-row"><span class="rp-label">Balance Due:</span><span class="rp-label">&#x20B1;${bal}</span></div>
    <div class="rp-row"><span>Status:</span><span>${esc(d.status)}</span></div>
    <hr class="rp-divider">
    <div class="rp-footer">
      AquaBill Coop. Inc. &mdash; San Juan, Siquijor, Philippines<br>
      Please pay on or before the due date to avoid penalties.
    </div>`;
}

// ── Billing: load readings into generate modal ────────────────────────────────
function loadReadings() {
  const custId = document.getElementById('gen_customer')?.value;
  const sel    = document.getElementById('gen_reading');
  if (!sel) return;
  sel.innerHTML = '<option value="">Select reading...</option>';
  const preview = document.getElementById('bill_preview');
  if (preview) preview.style.display = 'none';
  const data = window.__readingsData || {};
  if (!custId || !data[custId]) return;
  data[custId].forEach(r => {
    const opt = document.createElement('option');
    opt.value = r.id;
    opt.dataset.consumption = r.consumption;
    opt.textContent = `${r.reading_date} — ${r.consumption} m³ (${r.previous_reading}→${r.current_reading})`;
    sel.appendChild(opt);
  });
}

function updateBillPreview() {
  const sel  = document.getElementById('gen_reading');
  const opt  = sel?.options[sel.selectedIndex];
  const c    = parseFloat(opt?.dataset?.consumption || 0);
  const pen  = parseFloat(document.getElementById('penalty')?.value || 0);
  const el   = document.getElementById('bill_preview');
  if (!el || !opt?.value) { if (el) el.style.display='none'; return; }
  const B = window.__baseCharge || 120, R = window.__rate || 35;
  el.innerHTML = `Base ₱${B.toFixed(2)} + Usage (${c}m³×₱${R}) ₱${(c*R).toFixed(2)} + Penalty ₱${pen.toFixed(2)} = <strong>Total ₱${(B+c*R+pen).toFixed(2)}</strong>`;
  el.style.display = 'block';
}

// ── Billing: bulk select ──────────────────────────────────────────────────────
function toggleAll(cb) {
  document.querySelectorAll('.bill-checkbox').forEach(c => { c.checked = cb.checked; });
  updateBulkBar();
}
function updateBulkBar() {
  const n   = document.querySelectorAll('.bill-checkbox:checked').length;
  const bar = document.getElementById('bulk-bar');
  const cnt = document.getElementById('bulk-count');
  if (cnt) cnt.textContent = n + ' selected';
  if (bar) bar.style.display = n > 0 ? 'flex' : 'none';
  const all = document.querySelectorAll('.bill-checkbox');
  const sa  = document.getElementById('select-all');
  if (sa) sa.indeterminate = n > 0 && n < all.length;
}
function clearSelection() {
  document.querySelectorAll('.bill-checkbox,#select-all').forEach(c => c.checked=false);
  updateBulkBar();
}
function confirmBulk() {
  const n  = document.querySelectorAll('.bill-checkbox:checked').length;
  const st = document.querySelector('[name="bulk_new_status"]')?.value;
  if (!st) { alert('Please select a status to apply.'); return false; }
  return confirm(`Update ${n} bill(s) to "${st}"?`);
}

// ── Readings consumption preview ──────────────────────────────────────────────
function updateConsumption() {
  const prev = parseFloat(document.getElementById('prev_reading')?.value) || 0;
  const curr = parseFloat(document.getElementById('current_reading')?.value) || 0;
  const el   = document.getElementById('consumption_preview');
  if (el) el.textContent = Math.max(0, curr-prev).toFixed(2) + ' m³';
}
