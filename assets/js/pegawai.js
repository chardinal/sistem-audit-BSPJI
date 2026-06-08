/* ============================================================
   AMS — Pegawai Portal JavaScript
   Kalender agenda & polling notifikasi badge
   ============================================================ */

/* ── Kalender Pegawai ─────────────────────────────────────── */
function initKalenderPegawai(eventData) {
  const calEl = document.getElementById('pgw-kalender');
  if (!calEl) return;

  let now = new Date();
  let yr  = now.getFullYear();
  let mo  = now.getMonth();

  function render() {
    const monthNames = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    const dayNames   = ['Min','Sen','Sel','Rab','Kam','Jum','Sab'];

    const firstDay    = new Date(yr, mo, 1).getDay();
    const daysInMonth = new Date(yr, mo + 1, 0).getDate();
    const today       = new Date();

    let html = `
      <div class="kal-nav">
        <button class="btn btn-sm btn-secondary" id="kal-pgw-prev">◀</button>
        <h3>${monthNames[mo]} ${yr}</h3>
        <button class="btn btn-sm btn-secondary" id="kal-pgw-next">▶</button>
      </div>
      <div class="kal-grid">
        ${dayNames.map(d=>`<div class="kal-day-header">${d}</div>`).join('')}
    `;

    for (let i = 0; i < firstDay; i++) html += '<div class="kal-cell other-month"><span class="kal-day-num"></span></div>';

    for (let d = 1; d <= daysInMonth; d++) {
      const dateStr   = `${yr}-${String(mo+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
      const isToday   = today.getFullYear()===yr && today.getMonth()===mo && today.getDate()===d;
      const hasEvent  = (eventData||[]).some(e => e.tgl_mulai <= dateStr && e.tgl_selesai >= dateStr);

      html += `<div class="kal-cell${isToday?' today':''}${hasEvent?' has-event':''}">
        <span class="kal-day-num">${d}</span>
        ${hasEvent ? '<div class="kal-dot"></div>' : ''}
      </div>`;
    }

    html += '</div>';
    calEl.innerHTML = html;

    document.getElementById('kal-pgw-prev')?.addEventListener('click', () => {
      if (--mo < 0) { mo = 11; yr--; }
      render();
    });
    document.getElementById('kal-pgw-next')?.addEventListener('click', () => {
      if (++mo > 11) { mo = 0; yr++; }
      render();
    });
  }

  render();
}

/* ── Toast Notification ───────────────────────────────────── */
function showToast(type, msg) {
  const wrap = document.getElementById('toast-wrap') || createToastWrap();
  const toast = document.createElement('div');
  toast.style.cssText = `
    background:${type==='success'?'#475569':'#EF4444'};
    color:#fff; padding:12px 18px; border-radius:8px;
    font-size:13.5px; font-weight:600;
    box-shadow:0 4px 12px rgba(0,0,0,.15);
    animation: slideInRight .3s ease;
    max-width:300px; word-break:break-word;
  `;
  toast.textContent = msg;
  wrap.appendChild(toast);
  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transition = 'opacity .4s';
    setTimeout(() => toast.remove(), 400);
  }, 3500);
}

function createToastWrap() {
  const wrap = document.createElement('div');
  wrap.id = 'toast-wrap';
  wrap.style.cssText = 'position:fixed;bottom:80px;right:16px;z-index:9999;display:flex;flex-direction:column;gap:8px;';
  document.body.appendChild(wrap);
  return wrap;
}

/* ── Notif Badge Polling ──────────────────────────────────── */
function initNotifPolling() {
  const badges = document.querySelectorAll('.pgw-nav-badge, #notif-badge');
  if (!badges.length) return;

  function refresh() {
    fetch('/api/notifikasi_count.php?portal=pegawai')
      .then(r => r.json())
      .then(res => {
        badges.forEach(b => {
          b.textContent = res.count > 0 ? res.count : '';
          b.style.display = res.count > 0 ? 'flex' : 'none';
        });
      })
      .catch(() => {});
  }

  refresh();
  setInterval(refresh, 30000);
}

/* ── Filter Riwayat ───────────────────────────────────────── */
function initFilterRiwayat() {
  const filterTahun = document.getElementById('filter-tahun');
  const filterBulan = document.getElementById('filter-bulan');
  const items       = document.querySelectorAll('[data-riwayat-date]');

  function applyFilter() {
    const yr = filterTahun ? filterTahun.value : '';
    const mo = filterBulan ? filterBulan.value : '';

    items.forEach(item => {
      const d = item.dataset.riwayatDate || '';
      const [itemYr, itemMo] = d.split('-');
      const matchYr = !yr || itemYr === yr;
      const matchMo = !mo || itemMo === mo.padStart(2, '0');
      item.style.display = (matchYr && matchMo) ? '' : 'none';
    });
  }

  filterTahun?.addEventListener('change', applyFilter);
  filterBulan?.addEventListener('change', applyFilter);
}

/* ── DOMContentLoaded ─────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  initNotifPolling();
  initFilterRiwayat();

  // Flash auto-hide
  document.querySelectorAll('.alert').forEach(el => {
    setTimeout(() => {
      el.style.transition = 'opacity .5s';
      el.style.opacity = '0';
      setTimeout(() => el.remove(), 500);
    }, 4000);
  });
});

// CSS animation keyframe injected via JS
const style = document.createElement('style');
style.textContent = `@keyframes slideInRight{from{transform:translateX(80px);opacity:0}to{transform:translateX(0);opacity:1}}`;
document.head.appendChild(style);
