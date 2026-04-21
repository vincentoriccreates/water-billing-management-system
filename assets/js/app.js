// ── Sidebar toggle ────────────────────────────────────────────────────────────
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('collapsed');
  document.body.classList.toggle('sidebar-collapsed');
  localStorage.setItem('sidebarCollapsed', document.body.classList.contains('sidebar-collapsed'));
}
(function () {
  if (localStorage.getItem('sidebarCollapsed') === 'true') {
    document.getElementById('sidebar')?.classList.add('collapsed');
    document.body.classList.add('sidebar-collapsed');
  }
})();

// ── Auto-dismiss flash ────────────────────────────────────────────────────────
setTimeout(() => {
  const f = document.getElementById('flash-msg');
  if (f) f.style.transition = 'opacity .5s', f.style.opacity = '0', setTimeout(() => f.remove(), 500);
}, 4000);

// ── Confirm delete ────────────────────────────────────────────────────────────
function confirmDelete(msg) {
  return confirm(msg || 'Are you sure you want to delete this record?');
}

// ── Bar chart render ──────────────────────────────────────────────────────────
function renderBarChart(containerId, data) {
  const el = document.getElementById(containerId);
  if (!el || !data.length) return;
  const max = Math.max(...data.map(d => d.value), 1);
  el.innerHTML = data.map(d => `
    <div class="bar-col">
      <div class="bar" style="height:${Math.max((d.value / max) * 70, 3)}px" title="${d.label}: ${d.value}"></div>
      <span class="bar-label">${d.label}</span>
    </div>`).join('');
}

// ── Reading auto-calc ─────────────────────────────────────────────────────────
function updateConsumption() {
  const prev = parseFloat(document.getElementById('prev_reading')?.value) || 0;
  const curr = parseFloat(document.getElementById('current_reading')?.value) || 0;
  const consumption = Math.max(0, curr - prev);
  const el = document.getElementById('consumption_preview');
  if (el) el.textContent = consumption.toFixed(2) + ' m³';
}

// ── Bill calc preview ─────────────────────────────────────────────────────────
function updateBillPreview() {
  const readingSel = document.getElementById('reading_id');
  if (!readingSel) return;
  const opt = readingSel.options[readingSel.selectedIndex];
  const consumption = parseFloat(opt?.dataset?.consumption || 0);
  const penalty = parseFloat(document.getElementById('penalty')?.value || 0);
  const base = 120, rate = 35;
  const total = base + (consumption * rate) + penalty;
  const el = document.getElementById('bill_preview');
  if (el) {
    el.innerHTML = `Base: ₱${base.toFixed(2)} &nbsp;|&nbsp; Usage (${consumption} m³ × ₱${rate}): ₱${(consumption*rate).toFixed(2)} &nbsp;|&nbsp; <strong>Total: ₱${total.toFixed(2)}</strong>`;
    el.style.display = 'block';
  }
}

// ── Payment remaining balance ─────────────────────────────────────────────────
function updateBalance() {
  const billSel = document.getElementById('bill_id');
  if (!billSel) return;
  const opt = billSel.options[billSel.selectedIndex];
  const balance = parseFloat(opt?.dataset?.balance || 0);
  const el = document.getElementById('balance_preview');
  if (el) el.textContent = '₱' + balance.toFixed(2);
  const amtInput = document.getElementById('amount');
  if (amtInput) amtInput.max = balance;
}

// ── Tab switching ─────────────────────────────────────────────────────────────
function switchTab(tabId, groupClass) {
  document.querySelectorAll('.' + groupClass + '-pane').forEach(p => p.style.display = 'none');
  document.querySelectorAll('.' + groupClass + '-btn').forEach(b => b.classList.remove('active'));
  document.getElementById(tabId).style.display = 'block';
  event.currentTarget.classList.add('active');
}

// ── Animate numbers on dashboard load ────────────────────────────────────────
function animateCounter(el) {
  const target = parseFloat(el.dataset.target || el.textContent.replace(/[^0-9.]/g, ''));
  if (isNaN(target)) return;
  const isFloat  = String(target).includes('.');
  const prefix   = el.dataset.prefix || '';
  const suffix   = el.dataset.suffix || '';
  const duration = 900;
  const steps    = 40;
  const increment= target / steps;
  let current    = 0;
  const timer    = setInterval(() => {
    current += increment;
    if (current >= target) { current = target; clearInterval(timer); }
    el.textContent = prefix + (isFloat ? current.toFixed(2) : Math.round(current).toLocaleString()) + suffix;
  }, duration / steps);
}

document.querySelectorAll('.stat-val[data-animate]').forEach(animateCounter);

// ── Confirm form submissions ───────────────────────────────────────────────
document.querySelectorAll('form[data-confirm]').forEach(f => {
  f.addEventListener('submit', e => {
    if (!confirm(f.dataset.confirm)) e.preventDefault();
  });
});

// ── Live search filter on table ───────────────────────────────────────────
function liveSearch(inputId, tableId) {
  const input = document.getElementById(inputId);
  const table = document.getElementById(tableId);
  if (!input || !table) return;
  input.addEventListener('input', () => {
    const q = input.value.toLowerCase();
    table.querySelectorAll('tbody tr').forEach(row => {
      row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  });
}

// ── Format currency inputs ────────────────────────────────────────────────
document.querySelectorAll('input[data-currency]').forEach(input => {
  input.addEventListener('blur', () => {
    const val = parseFloat(input.value);
    if (!isNaN(val)) input.value = val.toFixed(2);
  });
});

// ── Tooltip on hover ──────────────────────────────────────────────────────
document.querySelectorAll('[data-tooltip]').forEach(el => {
  el.style.position = 'relative';
  el.addEventListener('mouseenter', () => {
    const tip = document.createElement('div');
    tip.className = 'tooltip-bubble';
    tip.textContent = el.dataset.tooltip;
    tip.style.cssText = `
      position:absolute;bottom:calc(100% + 6px);left:50%;transform:translateX(-50%);
      background:#111;color:#fff;padding:4px 10px;border-radius:6px;
      font-size:11px;white-space:nowrap;z-index:999;pointer-events:none;
    `;
    el.appendChild(tip);
  });
  el.addEventListener('mouseleave', () => {
    el.querySelector('.tooltip-bubble')?.remove();
  });
});
