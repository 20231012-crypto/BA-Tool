/* =====================================================================
   task-detail.js — Shared task detail modal + workflow timeline
   Depends on: API, esc(), formatDateOnly(), statusBadge(), devStatusBadge()
   Used by: views/admin/ba.php, lead.php, dev.php
   ===================================================================== */

const WF_MAIN_STEPS = [
    { key:'new',  label:'YC mới',     sub:'Chờ tiếp nhận', status:'Chờ tiếp nhận' },
    { key:'todo', label:'Phân công',  sub:'Chờ Sếp duyệt', status:'Todo - chờ xác nhận với Sếp' },
    { key:'code', label:'Đang xử lý', sub:'Code / Dev',    status:'Dion - đang xử lý' },
    { key:'test', label:'BA test',    sub:'Chờ nghiệm thu',status:'Dion - Chờ nghiệm thu' },
    { key:'done', label:'Hoàn thành', sub:'Đã nghiệm thu', status:'Kinkin nghiệm thu' }
];
const WF_MAIN_ORDER = {
    'Chờ tiếp nhận': 0,
    'Todo - chờ xác nhận với Sếp': 1,
    'Dion - đang xử lý': 2,
    'Dion - Chờ nghiệm thu': 3,
    'Kinkin nghiệm thu': 4
};
const WF_DEV_STEPS = [
    { key:'wait',  label:'Chờ dev nhận', status:'Chờ dev nhận' },
    { key:'doing', label:'Dev đang làm', status:'Dev đang làm' },
    { key:'done',  label:'Dev đã xong',  status:'Dev đã xong' }
];
const WF_DEV_ORDER = { 'Chờ dev nhận':0, 'Dev đang làm':1, 'Dev đã xong':2 };

function renderWorkflowTimeline(t) {
    if(t.status === 'Huỷ') {
        return `<div class="wf-cancelled">
            <span class="wf-cancel-icon">✗</span>
            <div>
                <strong>Task đã huỷ</strong>
                ${t.actual_end_date ? `<small style="display:block;color:#842029;opacity:.8;">Huỷ lúc: ${formatDateOnly(t.actual_end_date)}</small>` : ''}
            </div>
        </div>`;
    }

    const cur = WF_MAIN_ORDER[t.status];
    const idx = (cur === undefined) ? -1 : cur;

    let html = '<div class="wf-timeline">';
    WF_MAIN_STEPS.forEach((s, i) => {
        let cls = 'pending';
        if(i < idx) cls = 'done';
        else if(i === idx) cls = 'current';

        // Mốc thời gian / người tương ứng
        let stamp = '';
        if(s.key === 'todo' && i <= idx && t.assignee_name) stamp = esc(t.assignee_name);
        else if(s.key === 'code' && i <= idx && t.actual_start_datetime) stamp = formatDateOnly(t.actual_start_datetime);
        else if(s.key === 'test' && i <= idx && t.actual_end_date) stamp = formatDateOnly(t.actual_end_date);
        else if(s.key === 'done' && i <= idx && t.acceptance_date) stamp = formatDateOnly(t.acceptance_date);

        const inner = (cls === 'done') ? '✓' : (i + 1);
        html += `<div class="wf-step ${cls}">
            <div class="wf-circle">${inner}</div>
            <div class="wf-label">${s.label}</div>
            <div class="wf-sub">${s.sub}</div>
            ${stamp ? `<small>${stamp}</small>` : ''}
        </div>`;
        if(i < WF_MAIN_STEPS.length - 1) {
            html += `<div class="wf-connector ${i < idx ? 'done' : ''}"></div>`;
        }
    });
    html += '</div>';

    // Sub-pipeline cho Dev (chỉ khi đang ở giai đoạn code và đã có dev)
    if(t.status === 'Dion - đang xử lý' && (t.dev_id || t.dev_status)) {
        const isRework = t.dev_status === 'Cần sửa';
        const devIdx = (WF_DEV_ORDER[t.dev_status] === undefined) ? -1 : WF_DEV_ORDER[t.dev_status];

        html += `<div class="wf-sub-title">▸ Tiến độ Dev — <strong>${esc(t.dev_name || 'Chưa gán dev')}</strong>${t.dev_hours != null ? ` <span style="color:var(--text-muted);">· ${t.dev_hours}h</span>` : ''}</div>`;
        html += '<div class="wf-timeline wf-timeline-sub">';
        WF_DEV_STEPS.forEach((s, i) => {
            let cls = 'pending';
            if(isRework && i === 1) cls = 'rework';
            else if(i < devIdx) cls = 'done';
            else if(i === devIdx) cls = 'current';

            let stamp = '';
            if(s.key === 'doing' && i <= devIdx && t.dev_start_at) stamp = formatDateOnly(t.dev_start_at);
            else if(s.key === 'done' && i <= devIdx && t.dev_end_at) stamp = formatDateOnly(t.dev_end_at);

            const inner = (cls === 'done') ? '✓' : (cls === 'rework' ? '↩' : (i + 1));
            html += `<div class="wf-step ${cls}">
                <div class="wf-circle">${inner}</div>
                <div class="wf-label">${s.label}</div>
                ${stamp ? `<small>${stamp}</small>` : ''}
            </div>`;
            if(i < WF_DEV_STEPS.length - 1) {
                html += `<div class="wf-connector ${i < devIdx ? 'done' : ''}"></div>`;
            }
        });
        html += '</div>';
        if(isRework) {
            html += `<div class="wf-rework-banner">↩ Cần sửa — BA đã yêu cầu Dev chỉnh lại</div>`;
        }
    }

    return html;
}

function dmRow(label, val) {
    return `<div style="padding:8px 0;border-bottom:1px solid var(--border-light);">
        <div style="font-size:0.7rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.4px;">${label}</div>
        <div style="margin-top:3px;font-size:0.88rem;">${val || '<span style="color:var(--text-muted);">-</span>'}</div>
    </div>`;
}

function renderTaskDetail(t) {
    // Header
    let html = `<div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;padding-bottom:12px;border-bottom:1px solid var(--border-color);flex-wrap:wrap;">
        <span style="font-size:1.1rem;font-weight:700;color:var(--primary-color);">${esc(t.ma_yc || '#'+t.id)}</span>
        ${statusBadge(t.status)}
        <span style="color:var(--text-muted);font-size:0.85rem;">${esc(t.system_name || '')}</span>
        <button class="btn btn-outline btn-sm" style="margin-left:auto;" onclick="closeModal('detailModal'); if(typeof goToTaskInList==='function') goToTaskInList(${t.id});" title="Mở trong danh sách công việc và scroll tới đây">
            📋 Xem trong danh sách
        </button>
    </div>`;

    // Workflow timeline
    html += renderWorkflowTimeline(t);

    // Mô tả YC (đầy đủ, không cắt)
    html += `<div class="dm-section-title">Mô tả yêu cầu</div>
        <div class="dm-desc-box">${esc(t.description || '(Không có mô tả)')}</div>`;

    // Mô tả BA
    if(t.ba_description) {
        html += `<div class="dm-section-title">Mô tả kỹ thuật từ BA</div>
            <div class="dm-desc-box ba">${esc(t.ba_description)}</div>`;
    }

    // Dev info (notes + attachment)
    if(t.dev_notes || t.dev_attachment_url) {
        html += `<div class="dm-section-title">Ghi chú từ Dev</div>
            <div class="dm-desc-box dev">${esc(t.dev_notes || '(Chưa có ghi chú)')}
            ${t.dev_attachment_url ? `<div style="margin-top:8px;"><a href="${esc(t.dev_attachment_url)}" target="_blank">📎 Tài liệu Dev ↗</a></div>` : ''}</div>`;
    }

    // Metadata grid
    html += `<div class="dm-section-title">Thông tin chi tiết</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 24px;">
            ${dmRow('Người yêu cầu', esc(t.requester_name))}
            ${dmRow('Phòng ban', esc(t.requester_dept))}
            ${dmRow('Module', esc(t.module_name))}
            ${dmRow('Loại YC', esc(t.task_type))}
            ${dmRow('Tính năng', esc(t.feature))}
            ${dmRow('Phân loại', esc(t.classification))}
            ${dmRow('Ưu tiên YC', esc(t.priority_requester))}
            ${dmRow('Ưu tiên BA', esc(t.priority_ba))}
            ${dmRow('Đơn vị thực hiện', esc(t.implementing_unit))}
            ${dmRow('Phụ trách BA', esc(t.assignee_name))}
            ${dmRow('Developer', t.dev_name ? esc(t.dev_name) + ' ' + devStatusBadge(t.dev_status) : null)}
            ${dmRow('Ngày bắt đầu', formatDateOnly(t.start_date))}
            ${dmRow('Deadline', formatDateOnly(t.expected_end_date))}
            ${dmRow('Bắt đầu thực tế', t.actual_start_datetime ? formatDateOnly(t.actual_start_datetime) : null)}
            ${dmRow('Hoàn thành thực tế', formatDateOnly(t.actual_end_date))}
            ${dmRow('Ngày nghiệm thu', formatDateOnly(t.acceptance_date))}
            ${dmRow('Delay', t.delay_hours != null ? `<span style="color:${t.delay_status==='Quá hạn'?'var(--danger-color)':'var(--success-color)'};font-weight:600;">${t.delay_hours > 0 ? '+' : ''}${t.delay_hours}h (${esc(t.delay_status)})</span>` : null)}
            ${dmRow('Tài liệu', t.office_link ? `<a href="${esc(t.office_link)}" target="_blank">Mở 1Office ↗</a>` : null)}
            ${dmRow('File đính kèm', t.attachment_url ? `<a href="${esc(t.attachment_url)}" target="_blank">Tải file ↗</a>` : null)}
        </div>`;

    return html;
}

function ensureDetailModal() {
    let m = document.getElementById('detailModal');
    if(m) return m;
    m = document.createElement('div');
    m.id = 'detailModal';
    m.className = 'modal';
    m.innerHTML = `<div class="modal-content" style="max-width:760px;">
        <span class="close" onclick="closeModal('detailModal')">&times;</span>
        <div id="dm-body"><div style="padding:30px;text-align:center;color:var(--text-muted);">Đang tải...</div></div>
    </div>`;
    document.body.appendChild(m);
    return m;
}

function openDetail(taskId) {
    if(!taskId) return;
    const m = ensureDetailModal();
    const body = document.getElementById('dm-body');
    body.innerHTML = '<div style="padding:30px;text-align:center;color:var(--text-muted);">Đang tải chi tiết...</div>';
    m.style.display = 'block';

    fetch(API + '?action=get_task_detail&task_id=' + encodeURIComponent(taskId))
        .then(r => r.json())
        .then(t => {
            if(!t || t.error) {
                body.innerHTML = '<div style="padding:30px;text-align:center;color:var(--danger-color);">Không tìm thấy task này.</div>';
                return;
            }
            body.innerHTML = renderTaskDetail(t);
        })
        .catch(() => {
            body.innerHTML = '<div style="padding:30px;text-align:center;color:var(--danger-color);">Lỗi tải dữ liệu.</div>';
        });
}

/**
 * Click vào notification: đánh dấu đã đọc + nhảy tới chi tiết task (nếu có task_id).
 * Gọi bởi renderNotifList trong từng dashboard.
 */
function onNotifClick(notifId, taskId) {
    if(typeof markOneRead === 'function') markOneRead(notifId);
    const panel = document.getElementById('notif-panel');
    if(panel) panel.classList.remove('open');
    if(taskId) openDetail(taskId);
}
