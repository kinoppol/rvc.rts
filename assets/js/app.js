/* RTS — client-side interactions */

// ── Theme ─────────────────────────────────────────────────────────────
const THEME_ICONS = { light: '☀️', dark: '🌙', system: '💻' };
const themes = ['light', 'dark', 'system'];

function applyTheme(t) {
    const el = document.querySelector('.rts');
    if (!el) return;
    const resolved = t === 'system'
        ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
        : t;
    el.dataset.theme = resolved;
    const btn = document.getElementById('theme-btn');
    if (btn) btn.textContent = THEME_ICONS[t] || '☀️';
}

function cycleTheme() {
    const cur = localStorage.getItem('rts_theme') || 'light';
    const next = themes[(themes.indexOf(cur) + 1) % themes.length];
    localStorage.setItem('rts_theme', next);
    applyTheme(next);
}

// ── Sidebar ───────────────────────────────────────────────────────────
function toggleSidebar() {
    const sb   = document.querySelector('.sb');
    const main = document.querySelector('.main');
    if (!sb) return;
    const isMob = window.innerWidth <= 768;
    if (isMob) {
        sb.classList.toggle('mob');
    } else {
        sb.classList.toggle('cl');
        main && main.classList.toggle('cl');
        localStorage.setItem('rts_sb', sb.classList.contains('cl') ? '1' : '0');
    }
}

// ── Toast ─────────────────────────────────────────────────────────────
function toast(msg, type = '') {
    const container = document.getElementById('toast');
    if (!container) return;
    const div = document.createElement('div');
    div.className = 'toast' + (type ? ' ' + type : '');
    div.textContent = msg;
    container.appendChild(div);
    setTimeout(() => div.remove(), 3500);
}

// ── Modal helper ──────────────────────────────────────────────────────
function openModal(id) { document.getElementById(id)?.classList.remove('hidden'); }
function closeModal(id) { document.getElementById(id)?.classList.add('hidden'); }

// Close modal on backdrop click
document.addEventListener('click', e => {
    if (e.target.classList.contains('ov')) {
        e.target.classList.add('hidden');
    }
});

// ── Chips (department selector) ────────────────────────────────────────
function initChips(selectId, chipsId, inputId) {
    const sel   = document.getElementById(selectId);
    const chips = document.getElementById(chipsId);
    const inp   = document.getElementById(inputId);
    if (!sel || !chips) return;

    sel.addEventListener('change', () => {
        const v = sel.value;
        if (!v) return;
        const existing = [...chips.querySelectorAll('[data-val]')].map(el => el.dataset.val);
        if (existing.includes(v)) { sel.value = ''; return; }
        addChip(chips, v, inputId);
        sel.value = '';
        syncInput(chips, inp);
    });
}

function addChip(chips, val, inputId) {
    const span = document.createElement('span');
    span.className = 'chip';
    span.dataset.val = val;
    span.innerHTML = `${val} <button class="chip-x" type="button" onclick="removeChip(this,'${inputId}')">×</button>`;
    chips.appendChild(span);
}

function removeChip(btn, inputId) {
    const chip = btn.closest('.chip');
    const chips = chip.parentElement;
    chip.remove();
    syncInput(chips, document.getElementById(inputId));
}

function syncInput(chips, inp) {
    if (!inp) return;
    inp.value = [...chips.querySelectorAll('[data-val]')].map(el => el.dataset.val).join(',');
}

// ── File upload zone ──────────────────────────────────────────────────
function initUploadZone(zoneId, fileInputId, displayId) {
    const zone = document.getElementById(zoneId);
    const fi   = document.getElementById(fileInputId);
    if (!zone || !fi) return;

    zone.addEventListener('click', () => fi.click());
    zone.addEventListener('dragover', e => { e.preventDefault(); zone.style.borderColor = 'var(--p)'; });
    zone.addEventListener('dragleave', () => { zone.style.borderColor = ''; });
    zone.addEventListener('drop', e => {
        e.preventDefault();
        zone.style.borderColor = '';
        if (e.dataTransfer.files[0]) handleFile(e.dataTransfer.files[0], displayId, zone);
    });
    fi.addEventListener('change', () => {
        if (fi.files[0]) handleFile(fi.files[0], displayId, zone);
    });
}

function handleFile(file, displayId, zone) {
    const display = document.getElementById(displayId);
    if (display) {
        display.innerHTML = `<div class="alrt ai"><span>📎</span><span>${file.name} (${(file.size/1024/1024).toFixed(1)} MB)</span></div>`;
        display.dataset.file = '1';
    }
    zone.style.display = 'none';
}

// ── AJAX helper ───────────────────────────────────────────────────────
async function api(url, method = 'GET', data = null) {
    const opts = {
        method,
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
    };
    if (data) {
        if (data instanceof FormData) {
            opts.body = data;
        } else {
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(data);
        }
    }
    const res = await fetch(url, opts);
    if (!res.ok) throw new Error(await res.text());
    return res.json();
}

// ── Tabs ──────────────────────────────────────────────────────────────
function initTabs(containerSel) {
    document.querySelectorAll(containerSel + ' .tab').forEach(tab => {
        tab.addEventListener('click', () => {
            const parent = tab.closest('.tabs-wrap') || tab.closest('[data-tabs]');
            parent && parent.querySelectorAll('.tab').forEach(t => t.classList.remove('act'));
            tab.classList.add('act');
            const target = tab.dataset.target;
            if (target) {
                parent && parent.querySelectorAll('.tab-pane').forEach(p => p.classList.add('hidden'));
                document.getElementById(target)?.classList.remove('hidden');
            }
        });
    });
}

// ── Doc modal (document detail) ────────────────────────────────────────
async function openDocModal(docId) {
    try {
        const data = await api(`/rvc.rts/api/documents.php?id=${docId}`);
        if (!data.doc) return;
        const d = data.doc;
        const depts = (data.depts || []).map(x => `<span class="chip">${x}</span>`).join('');

        const annDone = d.annotation ? ' dn' : ' ac';
        const depDone = d.deputy_note ? ' dn' : (d.annotation ? ' ac' : '');
        const dirDone = d.director_note ? ' dn' : (d.deputy_note ? ' ac' : '');
        const repDone = d.reply_text ? ' dn' : (d.director_note ? ' ac' : '');

        document.getElementById('dm-title').textContent = d.doc_number;
        document.getElementById('dm-badges').innerHTML =
            `${urgBadge(d.urgency)} ${secBadge(d.secrecy)} ${statusBadge(d.status)}`;
        document.getElementById('dm-date').textContent  = d.received_date;
        document.getElementById('dm-from').textContent  = d.from_short || d.from_org;
        document.getElementById('dm-subj').textContent  = d.subject;
        document.getElementById('dm-tl-ann').className  = 'tld' + annDone;
        document.getElementById('dm-tl-dep').className  = 'tld' + depDone;
        document.getElementById('dm-tl-dir').className  = 'tld' + dirDone;
        document.getElementById('dm-tl-rep').className  = 'tld' + repDone;
        document.getElementById('dm-ann-txt').textContent  = d.annotation  || '';
        document.getElementById('dm-dep-txt').textContent  = d.deputy_note || '';
        document.getElementById('dm-dir-txt').textContent  = d.director_note || '';
        document.getElementById('dm-rep-txt').textContent  = d.reply_text  || '';
        document.getElementById('dm-depts').innerHTML  = depts;
        document.getElementById('dm-ann-row').style.display  = d.annotation   ? '' : 'none';
        document.getElementById('dm-dep-row').style.display  = d.deputy_note  ? '' : 'none';
        document.getElementById('dm-dir-row').style.display  = d.director_note? '' : 'none';
        document.getElementById('dm-rep-row').style.display  = d.reply_text   ? '' : 'none';

        const annotBtn = document.getElementById('dm-annot-btn');
        if (annotBtn) annotBtn.href = `/rvc.rts/?page=annotate&id=${d.id}`;
        annotBtn && (annotBtn.style.display = d.status === 'pending_annotation' ? '' : 'none');

        openModal('doc-modal');
    } catch (err) { toast('ไม่สามารถโหลดข้อมูลได้', 'er'); }
}

function urgBadge(k) {
    const m = {normal:['bg-g','ปกติ'],urgent:['bg-y','เร่งด่วน'],critical:['bg-r','ด่วนที่สุด']};
    const [c,l] = m[k] || m.normal;
    return `<span class="bdg ${c}">${l}</span>`;
}
function secBadge(k) {
    const m = {none:['bg-s','ไม่ลับ'],secret:['bg-o','ลับ'],top_secret:['bg-r','ลับที่สุด']};
    const [c,l] = m[k] || m.none;
    return `<span class="bdg ${c}">${l}</span>`;
}
function statusBadge(k) {
    const m = {pending_annotation:['bg-y','รอเกษียน'],pending_deputy:['bg-o','รอรองฯ'],pending_director:['bg-v','รอผอ.พิจารณา'],assigned:['bg-b','มอบหมายแล้ว'],in_progress:['bg-p','กำลังดำเนินการ'],done:['bg-g','เสร็จสิ้น'],blocked:['bg-r','ติดขัด']};
    const [c,l] = m[k] || ['bg-s',k];
    return `<span class="bdg ${c}">${l}</span>`;
}

// ── Init ──────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    // Theme
    const savedTheme = localStorage.getItem('rts_theme') || 'light';
    applyTheme(savedTheme);

    // Sidebar collapsed state
    const sbCollapsed = localStorage.getItem('rts_sb') === '1';
    if (sbCollapsed && window.innerWidth > 768) {
        document.querySelector('.sb')?.classList.add('cl');
        document.querySelector('.main')?.classList.add('cl');
    }

    // Global toggle
    document.getElementById('theme-btn')?.addEventListener('click', cycleTheme);
    document.getElementById('sb-toggle')?.addEventListener('click', toggleSidebar);
});
