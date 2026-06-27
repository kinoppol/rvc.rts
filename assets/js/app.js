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
    // Sync to body so modals appended to body also get dark-mode variables
    document.body.dataset.theme = resolved;
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
function openModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    // Move to body to escape any stacking-context trap
    if (el.parentElement !== document.body) document.body.appendChild(el);
    el.classList.remove('hidden');
}
function closeModal(id) { document.getElementById(id)?.classList.add('hidden'); }

// Modals close via button only — clicking backdrop does nothing

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
// ── PDF page counter (uses PDF.js CDN) ───────────────────────────────
async function getPdfPageCount(file) {
    try {
        if (!window.pdfjsLib) return null;
        const buf = await file.arrayBuffer();
        const pdf = await pdfjsLib.getDocument({ data: buf }).promise;
        return pdf.numPages;
    } catch(e) { return null; }
}

// ── Upload zone (multi-file) ──────────────────────────────────────────
function initUploadZone(zoneId, fileInputId, displayId, opts = {}) {
    const zone = document.getElementById(zoneId);
    const fi   = document.getElementById(fileInputId);
    if (!zone || !fi) return;

    fi.multiple = true;
    zone._uploadOpts  = opts;
    zone._fileInputId = fileInputId;

    const addFiles = files => {
        [...files].forEach(f => handleFile(f, displayId, zone, opts));
    };

    zone.addEventListener('click', () => fi.click());
    zone.addEventListener('dragover', e => { e.preventDefault(); zone.style.borderColor = 'var(--p)'; });
    zone.addEventListener('dragleave', () => { zone.style.borderColor = ''; });
    zone.addEventListener('drop', e => {
        e.preventDefault();
        zone.style.borderColor = '';
        if (e.dataTransfer.files.length) addFiles(e.dataTransfer.files);
    });
    fi.addEventListener('change', () => {
        if (fi.files.length) { addFiles(fi.files); fi.value = ''; }
    });
}

// Shared store for uploaded files
window._uploadedFiles = window._uploadedFiles || [];

function _fileNameWithoutExt(name) {
    return name.replace(/\.[^.]+$/, '');
}

async function handleFile(file, displayId, zone, opts = {}) {
    window._uploadedFiles = window._uploadedFiles || [];
    if (window._uploadedFiles.find(f => f.name === file.name && f.size === file.size)) return;
    window._uploadedFiles.push(file);

    const display = document.getElementById(displayId);
    if (!display) return;

    // Hide drop zone, show display area
    zone.style.display = 'none';

    // Ensure "เพิ่มไฟล์" button exists right after display
    let addBtn = document.getElementById('_upload-add-btn-' + displayId);
    if (!addBtn) {
        addBtn = document.createElement('button');
        addBtn.type = 'button';
        addBtn.id = '_upload-add-btn-' + displayId;
        addBtn.className = 'btn bg bsm';
        addBtn.style = 'margin-top:6px';
        addBtn.textContent = '➕ เพิ่มไฟล์';
        addBtn.onclick = () => { document.querySelector(`input[type=file]#${zone._fileInputId || ''}`) && document.querySelector(`input[type=file]#${zone._fileInputId}`).click(); };
        display.after(addBtn);
    }

    // File row
    const safeName = file.name.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
    const row = document.createElement('div');
    row.className = 'alrt ai';
    row.style = 'display:flex;align-items:center;gap:8px;margin-bottom:6px';
    row.dataset.filename = file.name;
    row.innerHTML = `<span>📎</span><span style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${file.name}">${file.name} <em style="color:var(--tx3)">(${(file.size/1024/1024).toFixed(1)} MB)</em></span><span class="page-count" style="font-size:12px;color:var(--tx2);white-space:nowrap;margin-left:4px">กำลังนับ...</span><button type="button" style="background:none;border:none;cursor:pointer;color:var(--er);font-size:18px;line-height:1;padding:0 2px" onclick="removeUploadedFile('${safeName}','${displayId}','${zone.id}')">×</button>`;
    display.appendChild(row);

    // Count pages
    const pages = await getPdfPageCount(file);
    const pc = row.querySelector('.page-count');
    if (pc) pc.textContent = pages !== null ? `${pages} หน้า` : '';

    // Update total pages field
    if (opts.pagesFieldId) recalcPages(opts.pagesFieldId);

    // Auto-suggest subject from first file only; adding more files never clears it
    if (opts.subjectFieldId) {
        const subj = document.getElementById(opts.subjectFieldId);
        if (subj && !subj.value.trim()) {
            subj.value = _fileNameWithoutExt(file.name);
            subj.dataset.autoFilled = '1';
        }
    }
}

function removeUploadedFile(name, displayId, zoneId) {
    window._uploadedFiles = (window._uploadedFiles || []).filter(f => f.name !== name);
    const display = document.getElementById(displayId);
    const zone    = document.getElementById(zoneId);
    if (display) {
        const row = [...display.querySelectorAll('[data-filename]')].find(el => el.dataset.filename === name);
        if (row) row.remove();
        const remaining = display.querySelectorAll('[data-filename]').length;
        if (!remaining) {
            if (zone) zone.style.display = '';
            const addBtn = document.getElementById('_upload-add-btn-' + displayId);
            if (addBtn) addBtn.remove();
        }
    }
    // Recalc pages
    const opts = zone?._uploadOpts || {};
    if (opts.pagesFieldId) recalcPages(opts.pagesFieldId);
    // Clear subject only when all files removed and it was auto-filled
    if (opts.subjectFieldId && window._uploadedFiles.length === 0) {
        const subj = document.getElementById(opts.subjectFieldId);
        if (subj && subj.dataset.autoFilled === '1') {
            subj.value = '';
            delete subj.dataset.autoFilled;
        }
    }
}

async function recalcPages(fieldId) {
    const field = document.getElementById(fieldId);
    if (!field) return;
    let total = 0;
    for (const f of (window._uploadedFiles || [])) {
        const n = await getPdfPageCount(f);
        if (n) total += n;
    }
    if (total > 0) field.value = total;
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
        document.getElementById('dm-ann-txt').textContent = d.annotation || '';
        // Show edit button for annotation if user is creator or admin
        const annEditBtn = document.getElementById('dm-ann-edit-btn');
        if (annEditBtn) {
            const canEdit = window.__ME && (window.__ME.role === 'admin' || window.__ME.id === d.created_by);
            annEditBtn.style.display = (canEdit && d.annotation) ? '' : 'none';
            annEditBtn.dataset.docId = d.id;
        }
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

        // File attachments
        const filesRow = document.getElementById('dm-files-row');
        const filesEl  = document.getElementById('dm-files');
        if (filesEl && d.file_path) {
            const names = d.file_path.split(',').map(s => s.trim()).filter(Boolean);
            filesEl.innerHTML = names.map((fn, i) =>
                `<a class="btn bg bsm" href="/rvc.rts/uploads/documents/${encodeURIComponent(fn)}" target="_blank" rel="noopener">📄 ไฟล์ ${names.length > 1 ? i + 1 : ''}</a>`
            ).join('');
            filesRow.style.display = '';
        } else if (filesRow) {
            filesRow.style.display = 'none';
        }

        const editBtn = document.getElementById('dm-edit-btn');
        if (editBtn) {
            const pendingStatuses = ['pending_annotation','pending_deputy','pending_director'];
        editBtn.style.display = pendingStatuses.includes(d.status) ? '' : 'none';
            editBtn.dataset.docId = d.id;
        }

        openModal('doc-modal');
    } catch (err) { toast('ไม่สามารถโหลดข้อมูลได้', 'er'); }
}

let _editDocData = null;
let _annotEditDocId = null;

function toggleAnnotEdit() {
    const area = document.getElementById('dm-ann-edit-area');
    const txt  = document.getElementById('dm-ann-txt');
    const btn  = document.getElementById('dm-ann-edit-btn');
    if (!area) return;
    const opening = area.style.display === 'none';
    area.style.display = opening ? '' : 'none';
    txt.style.display  = opening ? 'none' : '';
    btn.textContent    = opening ? '✕ ยกเลิก' : '✏️ แก้ไข';
    if (opening) {
        _annotEditDocId = document.getElementById('dm-ann-edit-btn').dataset.docId;
        document.getElementById('dm-ann-edit-txt').value = txt.dataset.raw || txt.textContent;
        document.getElementById('dm-ann-edit-txt').focus();
    }
}

async function saveAnnotEdit() {
    const newTxt = document.getElementById('dm-ann-edit-txt').value.trim();
    if (!newTxt) { toast('ความเห็นต้องไม่ว่าง', 'er'); return; }
    try {
        await api(`/rvc.rts/api/documents.php?id=${_annotEditDocId}`, { method:'PUT', body: JSON.stringify({ annotation: newTxt, _edit_annot: true }) });
        document.getElementById('dm-ann-txt').textContent = newTxt;
        document.getElementById('dm-ann-txt').dataset.raw = newTxt;
        toggleAnnotEdit();
        toast('บันทึกเรียบร้อย', 'ok');
    } catch (e) { toast('เกิดข้อผิดพลาด', 'er'); }
}

function openEditDocById(id) {
    // Set the doc id on the edit button so openEditDocModal can read it
    const btn = document.getElementById('dm-edit-btn');
    if (btn) btn.dataset.docId = id;
    openEditDocModal(id);
}

function openEditDocModal(forceId) {
    const id = forceId || document.getElementById('dm-edit-btn')?.dataset.docId;
    if (!id) return;
    api(`/rvc.rts/api/documents.php?id=${id}`).then(data => {
        if (!data.doc) return;
        const d = data.doc;
        _editDocData = d;
        document.getElementById('edm-id').value          = d.id;
        document.getElementById('edm-date').value        = d.received_date;
        document.getElementById('edm-from-org').value    = d.from_org;
        document.getElementById('edm-from-short').value  = d.from_short || '';
        document.getElementById('edm-subject').value     = d.subject;
        document.getElementById('edm-doctype').value     = d.doc_type || 'ราชการ';
        document.getElementById('edm-pages').value       = d.pages || 0;
        document.getElementById('edm-urgency').value     = d.urgency || 'normal';
        document.getElementById('edm-secrecy').value     = d.secrecy || 'none';
        closeModal('doc-modal');
        openModal('edit-doc-modal');
    }).catch(() => toast('ไม่สามารถโหลดข้อมูลได้', 'er'));
}

async function saveEditDoc() {
    const id = document.getElementById('edm-id').value;
    const payload = {
        _edit:       true,
        received_date: document.getElementById('edm-date').value,
        from_org:    document.getElementById('edm-from-org').value.trim(),
        from_short:  document.getElementById('edm-from-short').value.trim(),
        subject:     document.getElementById('edm-subject').value.trim(),
        doc_type:    document.getElementById('edm-doctype').value,
        pages:       parseInt(document.getElementById('edm-pages').value) || 0,
        urgency:     document.getElementById('edm-urgency').value,
        secrecy:     document.getElementById('edm-secrecy').value,
    };
    if (!payload.received_date || !payload.from_org || !payload.subject) {
        toast('กรุณากรอกข้อมูลให้ครบ', 'er'); return;
    }
    try {
        await api(`/rvc.rts/api/documents.php?id=${id}`, { method:'PUT', body: JSON.stringify(payload) });
        toast('บันทึกเรียบร้อย', 'ok');
        closeModal('edit-doc-modal');
        location.reload();
    } catch (err) { toast('เกิดข้อผิดพลาด', 'er'); }
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
