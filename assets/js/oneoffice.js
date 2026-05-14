/* =====================================================================
   oneoffice.js — Module Công việc 1Office
   ===================================================================== */

let _ooPage = 1;
let _ooLimit = 50;
let _ooActiveQF = '';
const OO_TASK_URL = 'https://kinkin.1office.vn/work/normal/detail/';

function ooLoad(page) {
    if (page) _ooPage = page;
    const search = (document.getElementById('oo-search').value || '').trim();
    const assign = (document.getElementById('oo-assign').value || '').trim();
    const owner  = (document.getElementById('oo-owner').value || '').trim();

    let params = `action=get_1o_tasks&page=${_ooPage}&limit=${_ooLimit}`;
    if (search) params += '&s=' + encodeURIComponent(search);
    if (assign) params += '&assign_ids=' + encodeURIComponent(assign);
    if (owner)  params += '&owner_ids=' + encodeURIComponent(owner);

    // Quick filter → status or overdue
    if (_ooActiveQF === 'overdue') {
        params += '&is_overdue_task=1';
    } else if (_ooActiveQF === 'near_deadline') {
        // Near deadline: kết thúc dự kiến trong 3 ngày tới
        const now = new Date();
        const from = ooFmtDate(now);
        const to = new Date(now.getTime() + 3 * 86400000);
        params += '&end_plan_from=' + ooFmtDate(now) + '&end_plan_to=' + ooFmtDate(to);
        params += '&status=DOING';
    } else if (_ooActiveQF) {
        params += '&status=' + _ooActiveQF;
    }

    document.getElementById('oo-tbody').innerHTML = '<tr><td colspan="9" style="text-align:center;color:var(--text-muted);padding:20px;">\u0110ang t\u1ea3i...</td></tr>';

    fetch(API + '?' + params).then(r => r.json()).then(res => {
        if (res.error) {
            document.getElementById('oo-tbody').innerHTML = '<tr><td colspan="9" style="text-align:center;color:var(--danger-color);">' + esc(res.message || 'L\u1ed7i') + '</td></tr>';
            return;
        }
        const tasks = res.data || [];
        const total = res.total_item || 0;
        document.getElementById('oo-count').textContent = total + ' c\u00f4ng vi\u1ec7c';
        ooRenderTable(tasks);
        ooRenderPagination(total);
    }).catch(err => {
        document.getElementById('oo-tbody').innerHTML = '<tr><td colspan="9" style="text-align:center;color:var(--danger-color);">L\u1ed7i k\u1ebft n\u1ed1i: ' + esc(err.message) + '</td></tr>';
    });
}

function ooRenderTable(tasks) {
    const tbody = document.getElementById('oo-tbody');
    if (!tasks.length) {
        tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;color:var(--text-muted);padding:20px;">Kh\u00f4ng c\u00f3 c\u00f4ng vi\u1ec7c n\u00e0o</td></tr>';
        return;
    }
    tbody.innerHTML = tasks.map(t => {
        const statusCls = ooStatusClass(t.status);
        const isOverdue = ooIsOverdue(t);
        const nearDl = ooIsNearDeadline(t);
        const rowStyle = isOverdue ? 'background:#fff5f5;' : (nearDl ? 'background:#fffbf0;' : '');

        return `<tr style="${rowStyle}">
            <td><a href="${OO_TASK_URL}${t.ID}" target="_blank" style="color:var(--primary-color);font-weight:600;" title="M\u1edf tr\u00ean 1Office">${esc(t.code || '#' + t.ID)}</a></td>
            <td style="max-width:280px;">
                <a href="${OO_TASK_URL}${t.ID}" target="_blank" style="color:inherit;text-decoration:none;">
                    <strong>${esc(t.title || '')}</strong>
                </a>
                ${isOverdue ? '<br><small style="color:#dc3545;font-weight:600;">Qu\u00e1 h\u1ea1n</small>' : ''}
                ${nearDl ? '<br><small style="color:#fd7e14;font-weight:600;">G\u1ea7n h\u1ebft h\u1ea1n</small>' : ''}
            </td>
            <td><span class="badge ${statusCls}">${esc(t.status || '')}</span></td>
            <td>${esc(t.priority || '')}</td>
            <td>${esc(t.assign_ids || '')}</td>
            <td>${esc(t.owner_ids || '')}</td>
            <td><small>${esc(t.start_plan || '')}</small></td>
            <td><small>${esc(t.end_plan || '')}</small></td>
            <td>${ooProgressBar(t.percent)}</td>
        </tr>`;
    }).join('');
}

function ooRenderPagination(total) {
    const container = document.getElementById('oo-pagination');
    const totalPages = Math.ceil(total / _ooLimit);
    if (totalPages <= 1) { container.innerHTML = ''; return; }

    let html = '';
    // Prev
    if (_ooPage > 1) html += `<button class="btn btn-outline btn-sm" onclick="ooLoad(${_ooPage - 1})">&lt;</button>`;

    // Page numbers
    const start = Math.max(1, _ooPage - 3);
    const end = Math.min(totalPages, _ooPage + 3);
    if (start > 1) html += `<button class="btn btn-outline btn-sm" onclick="ooLoad(1)">1</button><span style="padding:0 4px;">...</span>`;
    for (let i = start; i <= end; i++) {
        html += `<button class="btn ${i === _ooPage ? 'btn-primary' : 'btn-outline'} btn-sm" onclick="ooLoad(${i})">${i}</button>`;
    }
    if (end < totalPages) html += `<span style="padding:0 4px;">...</span><button class="btn btn-outline btn-sm" onclick="ooLoad(${totalPages})">${totalPages}</button>`;

    // Next
    if (_ooPage < totalPages) html += `<button class="btn btn-outline btn-sm" onclick="ooLoad(${_ooPage + 1})">&gt;</button>`;

    container.innerHTML = html;
}

function ooQuickFilter(qf) {
    _ooActiveQF = qf;
    _ooPage = 1;
    document.querySelectorAll('.oo-qf').forEach(b => {
        const isActive = b.dataset.qf === qf;
        b.style.fontWeight = isActive ? '700' : '400';
        b.style.background = isActive ? b.style.borderColor : 'transparent';
        b.style.color = isActive ? '#fff' : b.style.borderColor;
    });
    ooLoad();
}

function ooReset() {
    document.getElementById('oo-search').value = '';
    document.getElementById('oo-assign').value = '';
    document.getElementById('oo-owner').value = '';
    _ooActiveQF = '';
    _ooPage = 1;
    document.querySelectorAll('.oo-qf').forEach(b => {
        b.style.fontWeight = '400';
        b.style.background = 'transparent';
    });
    ooLoad();
}

// ── Helpers ──
function ooStatusClass(s) {
    const map = {
        '\u0110ang th\u1ef1c hi\u1ec7n': 'badge-progress',
        'Ho\u00e0n th\u00e0nh': 'badge-done',
        '\u0110ang ch\u1edd': 'badge-new',
        '\u0110ang \u0111\u00e1nh gi\u00e1': 'badge-medium',
        'Ch\u01b0a ho\u00e0n th\u00e0nh': 'badge-high',
        'Kh\u00f4ng ho\u00e0n th\u00e0nh': 'badge-high',
        'T\u1ea1m d\u1eebng': 'badge-pending',
        'H\u1ee7y': 'badge-high',
        'D\u1ef1 ki\u1ebfn': 'badge-pending',
        '\u0110\u00e3 \u0111\u00f3ng': 'badge-done',
    };
    return map[s] || 'badge-pending';
}

function ooParseDate(s) {
    if (!s) return null;
    // Format: dd/mm/yyyy
    const p = s.split('/');
    if (p.length !== 3) return null;
    return new Date(parseInt(p[2]), parseInt(p[1]) - 1, parseInt(p[0]));
}

function ooIsOverdue(t) {
    if (!t.end_plan || t.status === 'Ho\u00e0n th\u00e0nh' || t.status === 'H\u1ee7y' || t.status === '\u0110\u00e3 \u0111\u00f3ng') return false;
    const end = ooParseDate(t.end_plan);
    return end && end < new Date();
}

function ooIsNearDeadline(t) {
    if (!t.end_plan || t.status === 'Ho\u00e0n th\u00e0nh' || t.status === 'H\u1ee7y' || t.status === '\u0110\u00e3 \u0111\u00f3ng') return false;
    const end = ooParseDate(t.end_plan);
    if (!end) return false;
    const now = new Date();
    const diff = (end - now) / 86400000;
    return diff >= 0 && diff <= 3;
}

function ooProgressBar(pct) {
    const p = parseInt(pct) || 0;
    const color = p >= 100 ? '#198754' : (p >= 50 ? '#0d6efd' : '#fd7e14');
    return `<div style="display:flex;align-items:center;gap:6px;">
        <div style="flex:1;height:6px;background:#e9ecef;border-radius:3px;min-width:50px;">
            <div style="width:${p}%;height:100%;background:${color};border-radius:3px;"></div>
        </div>
        <small>${p}%</small>
    </div>`;
}

function ooFmtDate(d) {
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    return dd + '/' + mm + '/' + d.getFullYear();
}

if (typeof esc === 'undefined') {
    function esc(s) { if (s === null || s === undefined) return ''; return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
}
