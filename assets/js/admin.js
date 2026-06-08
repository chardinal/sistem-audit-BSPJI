/* ============================================================
   AMS — Admin Portal JavaScript
   Chart.js helpers, kalender visual, notifikasi badge
   ============================================================ */

/* ── Chart.js Helpers ─────────────────────────────────────── */
function initLineChart(canvasId, labels, data) {
  const ctx = document.getElementById(canvasId);
  if (!ctx) return;
  new Chart(ctx, {
    type: 'line',
    data: {
      labels,
      datasets: [{
        label: 'Kunjungan',
        data,
        borderColor: '#3B82F6',
        backgroundColor: 'rgba(59,130,246,.08)',
        borderWidth: 2,
        pointRadius: 4,
        pointBackgroundColor: '#3B82F6',
        fill: true,
        tension: 0.35,
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        y: { beginAtZero: true, ticks: { stepSize: 1, font: { size: 11 } }, grid: { color: '#F3F4F6' } },
        x: { ticks: { font: { size: 11 } }, grid: { display: false } }
      }
    }
  });
}

function initDonutChart(canvasId, labels, data, colors) {
  const ctx = document.getElementById(canvasId);
  if (!ctx) return;
  new Chart(ctx, {
    type: 'doughnut',
    data: { labels, datasets: [{ data, backgroundColor: colors, borderWidth: 2, borderColor: '#fff' }] },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: { position: 'right', labels: { font: { size: 11 }, boxWidth: 12, padding: 10 } }
      },
      cutout: '65%'
    }
  });
}

function initBarChart(canvasId, labels, data) {
  const ctx = document.getElementById(canvasId);
  if (!ctx) return;
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels,
      datasets: [{
        label: 'Penugasan',
        data,
        backgroundColor: labels.map((_, i) => ['#3B82F6','#10B981','#F59E0B','#EF4444','#8B5CF6'][i % 5]),
        borderRadius: 4,
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        y: { beginAtZero: true, ticks: { stepSize: 1, font: { size: 11 } }, grid: { color: '#F3F4F6' } },
        x: { ticks: { font: { size: 10 } }, grid: { display: false } }
      }
    }
  });
}

/* ── Kalender Visual Admin ────────────────────────────────── */
function initKalenderAdmin(eventData) {
  const calEl = document.getElementById('admin-kalender');
  if (!calEl || typeof eventData === 'undefined') return;

  let yr = new Date().getFullYear();
  let mo = new Date().getMonth();

  function render() {
    const monthNames = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    const dayNames   = ['Min','Sen','Sel','Rab','Kam','Jum','Sab'];

    const firstDay    = new Date(yr, mo, 1).getDay();
    const daysInMonth = new Date(yr, mo + 1, 0).getDate();
    const today       = new Date();

    let html = `<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
      <button class="btn btn-sm btn-secondary" id="kal-prev">◀ Prev</button>
      <strong style="font-size:16px;color:#1A1F2E">${monthNames[mo]} ${yr}</strong>
      <button class="btn btn-sm btn-secondary" id="kal-next">Next ▶</button>
    </div>
    <div class="kalender-grid">
      ${dayNames.map(d => `<div class="kal-header-day">${d}</div>`).join('')}`;

    for (let i = 0; i < firstDay; i++) html += '<div class="kal-cell other-month"></div>';

    for (let d = 1; d <= daysInMonth; d++) {
      const dateStr   = `${yr}-${String(mo+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
      const isToday   = today.getFullYear()===yr && today.getMonth()===mo && today.getDate()===d;
      const dayEvents = (eventData||[]).filter(e => e.tgl_mulai <= dateStr && e.tgl_selesai >= dateStr);

      const statusClass = (e) => {
        if (e.status === 'Butuh Intervensi') return ' intervensi';
        if (e.status === 'Selesai')          return ' selesai';
        return '';
      };

      html += `<div class="kal-cell${isToday?' today':''}">
        <div class="kal-date${isToday?' today':''}">${d}</div>
        ${dayEvents.map(e => `<div class="kal-event${statusClass(e)}" title="${escHtml(e.perusahaan)}">${escHtml(e.perusahaan.substring(0,12))}</div>`).join('')}
      </div>`;
    }

    html += '</div>';
    calEl.innerHTML = html;

    document.getElementById('kal-prev')?.addEventListener('click', () => {
      if (--mo < 0) { mo = 11; yr--; }
      render();
    });
    document.getElementById('kal-next')?.addEventListener('click', () => {
      if (++mo > 11) { mo = 0; yr++; }
      render();
    });
  }

  render();
}

/* ── Notifikasi Badge Polling ─────────────────────────────── */
function initNotifPolling(portalType) {
  const badge = document.getElementById('notif-badge');
  if (!badge) return;

  function refresh() {
    fetch(`/api/notifikasi_count.php?portal=${portalType}`)
      .then(r => r.json())
      .then(res => {
        badge.textContent  = res.count > 0 ? res.count : '';
        badge.style.display = res.count > 0 ? 'flex' : 'none';
      })
      .catch(() => {});
  }

  refresh();
  setInterval(refresh, 30000);
}

/* ── Escape HTML Helper ───────────────────────────────────── */
function escHtml(str) {
  return String(str)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ── Flash Auto-hide ──────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.alert').forEach(el => {
    setTimeout(() => {
      el.style.transition = 'opacity .5s';
      el.style.opacity = '0';
      setTimeout(() => el.remove(), 500);
    }, 4000);
  });

  initNotifPolling('admin');
});
