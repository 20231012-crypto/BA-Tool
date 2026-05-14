<?php $activeMenu = 'tasks'; ?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BA Dashboard - Kin Kin BA Tool</title>
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/assets/css/style.css?v=20">
</head>
<body>
<div class="app-shell">

    <?php include __DIR__ . '/_sidebar.php'; ?>

    <main class="main">
        <div class="topbar">
            <div class="topbar-title">
                <h2 id="page-title">Việc của tôi</h2>
                <small><span class="live-dot"></span>Auto refresh 15 giây</small>
            </div>
            <div class="topbar-actions">
                <button class="btn btn-outline btn-sm" onclick="triggerDevSheetPoll(false)" title="Đọc Google Sheet ngay & cập nhật trạng thái Dev về DB">
                    🔄 Sync Dev Sheet
                </button>
                <small id="dev-sync-stamp" style="color:var(--text-muted);font-size:0.78rem;display:inline-block;min-width:160px;"></small>
                <span class="user-chip"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                <div class="notif-wrapper">
                    <button class="notif-bell" onclick="toggleNotif()" title="Thông báo">
                        🔔
                        <span class="notif-badge" id="notif-badge" style="display:none;">0</span>
                    </button>
                    <div class="notif-panel" id="notif-panel">
                        <div class="notif-panel-head">
                            <span>Thông báo</span>
                            <a href="javascript:void(0)" onclick="markAllRead()">Đánh dấu đã đọc</a>
                        </div>
                        <div id="notif-list"><div class="notif-empty">Đang tải...</div></div>
                    </div>
                </div>
                <a href="?page=logout" class="btn btn-dark btn-sm">Đăng xuất</a>
            </div>
        </div>

        <div class="content">
            <section id="section-tasks" class="page-section">
                <div class="card">
                    <div class="section-title">
                        <span>Công việc được phân công</span>
                        <span id="task-count" style="color:var(--text-muted); font-size:0.85rem; font-weight:500;"></span>
                    </div>

                    <!-- Quick filter buttons -->
                    <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px;" id="quick-filters">
                        <button class="btn btn-outline btn-sm qf-btn" data-qf="all" onclick="qfApply('all')" style="font-weight:600;">T&#7845;t c&#7843;</button>
                        <button class="btn btn-outline btn-sm qf-btn" data-qf="overdue" onclick="qfApply('overdue')" style="border-color:#dc3545;color:#dc3545;">Qu&#225; h&#7841;n</button>
                        <button class="btn btn-outline btn-sm qf-btn" data-qf="need_receive" onclick="qfApply('need_receive')" style="border-color:#ffc107;color:#856404;">C&#7847;n ti&#7871;p nh&#7853;n</button>
                        <button class="btn btn-outline btn-sm qf-btn" data-qf="assign_dev" onclick="qfApply('assign_dev')" style="border-color:#0dcaf0;color:#0dcaf0;">Ph&#226;n c&#244;ng Dev</button>
                        <button class="btn btn-outline btn-sm qf-btn" data-qf="coding" onclick="qfApply('coding')" style="border-color:#0d6efd;color:#0d6efd;">&#272;ang code</button>
                        <button class="btn btn-outline btn-sm qf-btn" data-qf="testing" onclick="qfApply('testing')" style="border-color:#6f42c1;color:#6f42c1;">Test</button>
                        <button class="btn btn-outline btn-sm qf-btn" data-qf="done" onclick="qfApply('done')" style="border-color:#198754;color:#198754;">Ho&#224;n th&#224;nh</button>
                    </div>

                    <!-- ============ FILTER BAR ============ -->
                    <div class="task-filter-bar">
                        <div class="tfb-row">
                            <input type="text" id="tf-q" class="form-control tfb-search"
                                   placeholder="🔍 Tìm Mã YC / Hệ thống / Người YC / Mô tả..."
                                   oninput="tfApplyFilters()">
                            <select id="tf-status"   class="form-control tfb-sel" onchange="tfApplyFilters()"><option value="">Trạng thái BA: Tất cả</option></select>
                            <select id="tf-priority" class="form-control tfb-sel" onchange="tfApplyFilters()"><option value="">Ưu tiên: Tất cả</option></select>
                            <select id="tf-tasktype" class="form-control tfb-sel" onchange="tfApplyFilters()"><option value="">Loại YC: Tất cả</option></select>
                            <select id="tf-system"   class="form-control tfb-sel" onchange="tfApplyFilters()"><option value="">Hệ thống: Tất cả</option></select>
                            <select id="tf-dev"      class="form-control tfb-sel" onchange="tfApplyFilters()"><option value="">Dev: Tất cả</option></select>
                            <select id="tf-devstatus" class="form-control tfb-sel" onchange="tfApplyFilters()"><option value="">Dev status: Tất cả</option></select>
                            <button class="btn btn-outline btn-sm" onclick="tfReset()">↺ Reset</button>
                        </div>
                    </div>

                    <div class="scroll-shell">
                        <div class="scroll-body">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Mã YC</th>
                                        <th>Hệ thống / Module</th>
                                        <th>Mô tả</th>
                                        <th>Ưu tiên BA</th>
                                        <th>Deadline</th>
                                        <th>Tài liệu</th>
                                        <th>Trạng thái BA</th>
                                        <th>Dev / Trạng thái Dev</th>
                                        <th>Delay</th>
                                        <th>Hành động</th>
                                    </tr>
                                </thead>
                                <tbody id="tasks-tbody">
                                    <tr><td colspan="10" style="text-align:center;padding:30px;color:var(--text-muted);">Đang tải...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ============ FORM SECTION ============ -->
            <section id="section-form" class="page-section" style="display:none;">
                <div class="card">
                    <div class="section-title">
                        <span>Form công khai</span>
                        <a href="?page=public_form" target="_blank" class="btn btn-outline btn-sm">↗ Mở form</a>
                    </div>
                    <div class="form-link-bar">
                        <span class="form-link-label">Link gửi yêu cầu cho bộ phận:</span>
                        <code id="form-link-url"><?php echo (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']==='on'?'https':'http').'://'.$_SERVER['HTTP_HOST'].BASE_PATH.'/?page=public_form'; ?></code>
                        <button class="btn btn-outline btn-sm" onclick="copyFormLink()">📋 Sao chép link</button>
                        <span class="copy-hint" id="copy-hint">✓ Đã sao chép!</span>
                    </div>
                    <p style="color:var(--text-muted);font-size:0.86rem;padding:0 4px;">
                        Chia sẻ link trên cho các bộ phận để họ gửi yêu cầu hệ thống. Yêu cầu sau khi gửi sẽ hiển thị trong mục <strong>Việc của tôi</strong>.
                    </p>
                </div>
            </section>
            <!-- alias cũ -->
            <section id="section-formlog" style="display:none;"></section>

            <!-- ============ SYSTEMS SECTION (Danh sách hệ thống) ============ -->
            <?php include __DIR__ . '/_systems_section.php'; ?>

            <!-- "Quản lý Dev" đã bỏ — Dev quản lý qua Google Sheet, BA chỉ phân Dev khi giao việc -->

            <section id="section-notifications" class="page-section" style="display:none;">
                <div class="card">
                    <div class="section-title">
                        <span>Thông báo của tôi</span>
                        <a href="javascript:void(0)" onclick="markAllRead()" style="font-size:0.82rem;font-weight:500;">Đánh dấu tất cả đã đọc</a>
                    </div>
                    <div id="notif-fullpage"><div class="notif-empty">Đang tải...</div></div>
                </div>
            </section>
        </div>
    </main>
</div>

<!-- Modal: Bắt đầu code (partial dùng chung) -->
<?php include __DIR__ . '/_start_coding_modal.php'; ?>

<!-- Modal: Update office_link -->
<div id="docModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('docModal')">&times;</span>
        <h3 id="doc-title">Cập nhật tài liệu</h3>
        <div class="form-group">
            <label>Link tài liệu 1Office / Google Docs</label>
            <input type="url" id="office-link" class="form-control" placeholder="https://...">
        </div>
        <button class="btn btn-primary" style="width:100%;" onclick="submitDoc()">Lưu</button>
    </div>
</div>

<!-- Modal: Giao Dev (BA có quyền khi task đang xử lý) -->
<div id="devAssignModal" class="modal">
    <div class="modal-content" style="max-width:540px;">
        <span class="close" onclick="closeModal('devAssignModal')">&times;</span>
        <h3 id="dev-assign-title">Giao cho Dev</h3>
        <div style="background:#cfe2ff;border-left:4px solid #084298;padding:10px 14px;margin-bottom:18px;font-size:0.85rem;color:#084298;">
            Task đang ở giai đoạn <strong>Bắt đầu code</strong>. Chọn dev và mô tả kỹ thuật để dev thực hiện.
        </div>
        <div class="form-group">
            <label>Developer phụ trách</label>
            <select id="dev-assign-dev" class="form-control">
                <option value="">-- Chưa gán dev --</option>
            </select>
        </div>
        <div class="form-group">
            <label>Mô tả kỹ thuật từ BA cho Dev <span style="color:var(--primary-color);">*</span></label>
            <textarea id="dev-assign-desc" class="form-control" rows="5"
                placeholder="Mô tả chi tiết: API cần làm, màn hình thay đổi, logic xử lý, điều kiện đặc biệt, edge cases..."></textarea>
        </div>
        <div class="form-group">
            <label>Hạn hoàn thành cho Dev <span style="color:var(--text-muted);font-weight:400;">(tuỳ chọn)</span></label>
            <input type="datetime-local" id="dev-assign-deadline" class="form-control">
        </div>
        <button class="btn btn-primary" style="width:100%;" onclick="submitDevAssign()">💾 Lưu &amp; Giao Dev</button>
    </div>
</div>

<script>
const API = '<?php echo BASE_PATH; ?>/api/data.php';
let currentTaskId = null;

function esc(s) { if(s===null||s===undefined) return ''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function formatDateOnly(d) { if(!d) return '-'; return new Date(d).toLocaleDateString('vi-VN'); }
function timeAgo(d) {
    if(!d) return '';
    const diff = (Date.now() - new Date(d.replace(' ','T'))) / 1000;
    if(diff < 60) return 'vừa xong';
    if(diff < 3600) return Math.floor(diff/60) + ' phút';
    if(diff < 86400) return Math.floor(diff/3600) + ' giờ';
    return Math.floor(diff/86400) + ' ngày';
}
function closeModal(id) { document.getElementById(id).style.display = 'none'; }
window.onclick = e => { if(e.target.classList.contains('modal')) e.target.style.display='none'; }

function switchSection(name) {
    document.querySelectorAll('.page-section').forEach(s => s.style.display = 'none');
    document.querySelectorAll('.sidebar-item').forEach(b => b.classList.remove('active'));
    const sec = document.getElementById('section-' + name);
    if(sec) sec.style.display = 'block';
    event && event.currentTarget && event.currentTarget.classList.add('active');
    if(name === 'formlog') name = 'form';
    const titles = { tasks: 'Việc của tôi', systems: 'Danh sách hệ thống', form: 'Form công khai', notifications: 'Thông báo của tôi' };
    document.getElementById('page-title').textContent = titles[name] || '';
    if(name === 'tasks') loadTasks();
    if(name === 'systems') sysBackToList();
    if(name === 'notifications') loadNotifPage();
}

function statusBadge(s) {
    const map = {
        'Chờ tiếp nhận':         'badge-new',
        'Todo - chờ xác nhận với Sếp': 'badge-pending',
        'Chờ duyệt':                   'badge-pending',
        'Dion - đang xử lý':           'badge-progress',
        'Dion - Chờ nghiệm thu':       'badge-medium',
        'Chờ nghiệm thu':              'badge-medium',
        'Hoàn thành':                   'badge-done',
        'Kinkin nghiệm thu':           'badge-done',
        'Huỷ':                          'badge-high'
    };
    return `<span class="badge ${map[s]||'badge-pending'}">${esc(s)}</span>`;
}
function priorityBadge(p) {
    const map = {
        '4. Gấp - Quan trọng':'badge-high',
        '3. Không gấp - Quan trọng':'badge-medium',
        '2. Gấp - Không quan trọng':'badge-low',
        '1. Không gấp - Không quan trọng':'badge-pending'
    };
    return p ? `<span class="badge ${map[p]||'badge-pending'}">${esc(p)}</span>` : '-';
}
function devStatusBadge(s) {
    if(!s) return '<span style="color:var(--text-muted);font-size:0.8rem;">Chưa gán dev</span>';
    const map = { 'Chờ dev nhận':'badge-pending','Dev đang làm':'badge-progress','Dev đã xong':'badge-done','Cần sửa':'badge-high' };
    return `<span class="badge ${map[s]||'badge-pending'}">${esc(s)}</span>`;
}
function baDevRework(taskId) {
    if(!confirm('Đánh dấu "Cần sửa" và thông báo Dev?')) return;
    const fd = new FormData();
    fd.append('action','dev_rework'); fd.append('task_id',taskId);
    fetch(API,{method:'POST',body:fd}).then(r=>r.json()).then(res=>{ if(res.success) loadTasks(); });
}
function delayCell(t) {
    if(t.delay_hours === null || t.delay_hours === undefined) return '<span style="color:var(--text-muted);">-</span>';
    const h = parseFloat(t.delay_hours);
    if(h === 0) return '<span style="color:var(--success-color);font-weight:600;" title="Đúng hạn">0 — đúng hạn</span>';
    const totalMin = Math.round(h * 60);
    let label;
    if(totalMin < 1440) {
        const hh = Math.floor(totalMin / 60);
        const mm = totalMin % 60;
        label = '+' + hh + 'g' + (mm > 0 ? mm + 'p' : '');
    } else {
        const days = Math.floor(totalMin / 1440);
        const remH  = Math.floor((totalMin % 1440) / 60);
        label = '+' + days + ' ngày' + (remH > 0 ? ' ' + remH + 'g' : '');
    }
    return `<span style="color:var(--danger-color);font-weight:600;" title="Quá hạn">${label}</span>`;
}

const WORKFLOW = {
    'Chờ tiếp nhận':         { next: 'Todo - chờ xác nhận với Sếp', label: 'Nhận YC' },
    'Todo - chờ xác nhận với Sếp': { next: 'Dion - đang xử lý',     label: 'Bắt đầu code' },
    'Chờ duyệt':                   { next: 'Dion - đang xử lý',     label: 'Bắt đầu xử lý' },
    'Dion - đang xử lý':           { next: 'Dion - Chờ nghiệm thu', label: 'Đã xong' },
    'Dion - Chờ nghiệm thu':       { next: 'Kinkin nghiệm thu',     label: 'Nghiệm thu' },
    'Chờ nghiệm thu':              { next: 'Kinkin nghiệm thu',     label: 'Nghiệm thu' },
    'Hoàn thành':                   { next: null,                     label: 'Đã hoàn tất' },
    'Kinkin nghiệm thu':           { next: null,                     label: 'Đã hoàn tất' },
    'Huỷ':                          { next: null,                     label: 'Đã huỷ' }
};
const WORKFLOW_BACK = {
    'Todo - chờ xác nhận với Sếp': 'Chờ tiếp nhận',
    'Chờ duyệt':                   'Chờ tiếp nhận',
    'Dion - đang xử lý':           'Todo - chờ xác nhận với Sếp',
    'Dion - Chờ nghiệm thu':       'Dion - đang xử lý',
    'Chờ nghiệm thu':              'Dion - đang xử lý',
    'Kinkin nghiệm thu':           'Dion - Chờ nghiệm thu',
};

function nextStepCell(t) {
    // Test workflow tại "Dion - Chờ nghiệm thu"
    if(t.status === 'Dion - Chờ nghiệm thu') {
        const ts = t.test_status || '';
        let testBtns = '';
        if(ts === '' || ts === null) {
            testBtns = `<button class="btn-next" style="background:#0d6efd;border-color:#0d6efd;" onclick="testStart(${t.id})" title="Bắt đầu kiểm thử">▶ Bắt đầu test</button>`;
        } else if(ts === 'Đang test') {
            testBtns = `<button class="btn-next" style="background:#198754;border-color:#198754;" onclick="testDonePending(${t.id})" title="Hoàn tất test">✓ Test xong</button>
                        <button class="btn-cancel-flow" onclick="openBugModal(${t.id}, '${esc(t.ma_yc||'#'+t.id)}')" title="Phát hiện lỗi">✗ Phát hiện lỗi</button>`;
        } else if(ts === 'hoàn thành test chờ nghiệm thu') {
            testBtns = `<button class="btn-next" style="background:#198754;border-color:#198754;" onclick="testAccepted(${t.id})" title="User đã nghiệm thu">✓ Đã nghiệm thu</button>
                        <button class="btn-cancel-flow" onclick="openBugModal(${t.id}, '${esc(t.ma_yc||'#'+t.id)}')" title="Phát hiện lỗi sau test">✗ Phát hiện lỗi</button>`;
        }
        return `<div class="row-actions">
            ${testBtns}
            <button class="btn btn-outline btn-icon" onclick="confirmBack(${t.id}, '${esc(t.status)}')" title="Quay lại: Dion - đang xử lý" style="color:var(--text-secondary);">←</button>
            <button class="btn btn-outline btn-icon" onclick="openDoc(${t.id}, '${esc(t.system_name)}', '${esc(t.office_link||'')}')" title="Tài liệu">📄</button>
        </div>`;
    }

    const wf = WORKFLOW[t.status];
    if(!wf) return '-';
    let html = '<div class="row-actions">';

    // Nút tiến tới
    if(wf.next) {
        if(t.status === 'Chờ tiếp nhận') {
            // "Tiếp nhận" → mở modal chọn Đơn vị thực hiện
            html += `<button class="btn-next" onclick="openClaimModal(${t.id})" title="Nhận YC này">→ ${esc(wf.label)}</button>`;
        } else if(t.status === 'Todo - chờ xác nhận với Sếp') {
            // "Bắt đầu code" → mở modal gộp (chọn dev + mô tả + date range)
            html += `<button class="btn-next" onclick="openStartCoding(${t.id})" title="Bắt đầu code">▶ Bắt đầu code</button>`;
        } else {
            html += `<button class="btn-next" onclick="confirmNext(${t.id}, 'next')" title="Chuyển: ${esc(wf.next)}">→ ${esc(wf.label)}</button>`;
        }
    } else {
        html += `<span style="color:var(--text-muted);font-size:0.82rem;font-style:italic;">${esc(wf.label)}</span>`;
    }

    // Nút quay lại bước trước
    if(WORKFLOW_BACK[t.status]) {
        html += `<button class="btn btn-outline btn-icon" onclick="confirmBack(${t.id}, '${esc(t.status)}')"
                    title="Quay lại: ${esc(WORKFLOW_BACK[t.status])}" style="color:var(--text-secondary);">←</button>`;
    }

    // Nút huỷ
    if(t.status !== 'Huỷ' && t.status !== 'Kinkin nghiệm thu') {
        html += `<button class="btn-cancel-flow" onclick="confirmNext(${t.id}, 'cancel')" title="Huỷ">✗</button>`;
    }

    // Lưu ý: nút "Phân công Dev" đã bỏ — gán dev được gộp vào modal "Bắt đầu code" lúc chuyển bước.
    // Nếu cần đổi dev sau khi đã bắt đầu, dùng nút ← Quay lại để về Todo, rồi bấm Bắt đầu code lần nữa.

    html += `<button class="btn btn-outline btn-icon" onclick="openDoc(${t.id}, '${esc(t.system_name)}', '${esc(t.office_link||'')}')" title="Tài liệu">📄</button>`;
    html += '</div>';
    return html;
}

function confirmNext(taskId, direction) {
    fetch(API + '?action=get_tasks').then(r=>r.json()).then(tasks => {
        const task = tasks.find(t => t.id == taskId);
        if(!task) { alert('Không tìm thấy task'); return; }
        let title, body, btn, btnClass;
        if(direction === 'next') {
            const wf = WORKFLOW[task.status];
            if(!wf || !wf.next) { alert('Đã ở bước cuối'); return; }
            title = 'Chuyển bước';
            body = `Chuyển <strong>${esc(task.ma_yc)}</strong> — <em>${esc(task.system_name)}</em><br><br>
                    ${statusBadge(task.status)} <span class="arrow">→</span> ${statusBadge(wf.next)}<br><br>
                    <small style="color:var(--text-muted);">Lead sẽ nhận được thông báo về việc chuyển bước này.</small>`;
            btn = '→ Xác nhận';
            btnClass = 'btn-success';
        } else {
            title = 'Huỷ công việc';
            body = `Huỷ <strong>${esc(task.ma_yc)}</strong> — <em>${esc(task.system_name)}</em>?`;
            btn = '✗ Huỷ';
            btnClass = 'btn-primary';
        }
        const backdrop = document.createElement('div');
        backdrop.className = 'confirm-backdrop';
        backdrop.innerHTML = `
            <div class="confirm-box">
                <div class="head"><h4>${title}</h4></div>
                <div class="body">${body}</div>
                <div class="actions">
                    <button class="btn btn-outline btn-sm" onclick="this.closest('.confirm-backdrop').remove()">Huỷ bỏ</button>
                    <button class="btn ${btnClass} btn-sm" id="do-confirm">${btn}</button>
                </div>
            </div>`;
        document.body.appendChild(backdrop);
        backdrop.addEventListener('click', e => { if(e.target===backdrop) backdrop.remove(); });
        backdrop.querySelector('#do-confirm').addEventListener('click', () => {
            backdrop.remove();
            doNextStep(taskId, direction);
        });
    });
}

function doNextStep(taskId, direction) {
    const fd = new FormData();
    fd.append('action', 'next_step');
    fd.append('task_id', taskId);
    fd.append('direction', direction);
    fetch(API, { method:'POST', body:fd }).then(r => r.json()).then(res => {
        if(res.success) { loadTasks(); loadNotifBell(); }
        else alert(res.message || 'Có lỗi xảy ra');
    });
}

function openDoc(taskId, sysName, link) {
    currentTaskId = taskId;
    document.getElementById('doc-title').textContent = 'Tài liệu: ' + sysName;
    document.getElementById('office-link').value = link;
    document.getElementById('docModal').style.display = 'block';
}
function submitDoc() {
    const fd = new FormData();
    fd.append('action', 'update_status');
    fd.append('task_id', currentTaskId);
    fd.append('status', document.querySelector('#tasks-tbody tr [data-status]')?.dataset?.status || 'Todo - chờ xác nhận với Sếp');
    fd.append('office_link', document.getElementById('office-link').value);
    // Thực ra chỉ cần update office_link riêng, gửi thêm status là để giữ tương thích
    fetch(API, { method:'POST', body:fd }).then(r => r.json()).then(res => {
        if(res.success) { closeModal('docModal'); loadTasks(); }
    });
}

// ============ TASK CACHE + FILTER ============
let _TASKS_CACHE = [];

function loadTasks() {
    fetch(API + '?action=get_tasks').then(r => r.json()).then(tasks => {
        _TASKS_CACHE = tasks || [];
        tfPopulateDropdowns();
        tfApplyFilters();
    });
}

function tfPopulateDropdowns() {
    const map = {
        'tf-status':    'status',
        'tf-priority':  '__priority',
        'tf-tasktype':  'task_type',
        'tf-system':    'system_name',
        'tf-dev':       'dev_name',
        'tf-devstatus': 'dev_status',
    };
    Object.entries(map).forEach(([selId, field]) => {
        const sel = document.getElementById(selId);
        if(!sel) return;
        const cur = sel.value;
        const labelTxt = sel.querySelector('option[value=""]').textContent;
        const set = new Set();
        _TASKS_CACHE.forEach(t => {
            const v = (field === '__priority') ? (t.priority_ba || t.priority_requester) : t[field];
            if(v) set.add(v);
        });
        const opts = Array.from(set).sort((a,b)=>String(a).localeCompare(String(b),'vi'));
        sel.innerHTML = `<option value="">${labelTxt}</option>` + opts.map(v => `<option value="${esc(v)}">${esc(v)}</option>`).join('');
        if(cur && set.has(cur)) sel.value = cur;
    });
}

// ===== QUICK FILTERS =====
let _activeQF = 'all';
function qfApply(qf) {
    _activeQF = qf;
    document.querySelectorAll('.qf-btn').forEach(b => {
        b.style.fontWeight = b.dataset.qf === qf ? '700' : '400';
        b.style.background = b.dataset.qf === qf ? b.style.borderColor : 'transparent';
        b.style.color = b.dataset.qf === qf ? '#fff' : b.style.borderColor;
    });
    tfReset();
}

function qfMatchTask(t) {
    if (_activeQF === 'all') return true;
    const now = new Date();
    switch (_activeQF) {
        case 'overdue':
            if (!t.expected_end_date) return false;
            return new Date(t.expected_end_date.replace(' ','T')) < now
                && !['Kinkin nghiệm thu','Hoàn thành','Huỷ'].includes(t.status);
        case 'need_receive':
            return t.status === 'Chờ tiếp nhận' || t.status === 'Todo - chờ xác nhận với Sếp';
        case 'assign_dev':
            return t.status === 'Todo - chờ xác nhận với Sếp'
                || (t.status === 'Dion - đang xử lý' && !t.dev_id);
        case 'coding':
            return t.status === 'Dion - đang xử lý'
                || (t.dev_status && ['Dev đang làm','Chờ dev nhận'].includes(t.dev_status));
        case 'testing':
            return t.status === 'Dion - Chờ nghiệm thu'
                || t.status === 'Chờ nghiệm thu'
                || (t.test_status && t.test_status !== '');
        case 'done':
            return ['Kinkin nghiệm thu','Hoàn thành'].includes(t.status);
    }
    return true;
}

function tfReset() {
    document.getElementById('tf-q').value = '';
    ['tf-status','tf-priority','tf-tasktype','tf-system','tf-dev','tf-devstatus'].forEach(id => { const el = document.getElementById(id); if(el) el.value = ''; });
    tfApplyFilters();
}

function tfApplyFilters() {
    const q  = (document.getElementById('tf-q').value || '').trim().toLowerCase();
    const st = document.getElementById('tf-status').value;
    const pr = document.getElementById('tf-priority').value;
    const tt = document.getElementById('tf-tasktype').value;
    const sy = document.getElementById('tf-system').value;
    const dv = document.getElementById('tf-dev').value;
    const ds = document.getElementById('tf-devstatus').value;

    const filtered = _TASKS_CACHE.filter(t => {
        if(!qfMatchTask(t)) return false;
        if(st && t.status !== st) return false;
        if(pr) {
            const p = t.priority_ba || t.priority_requester;
            if(p !== pr) return false;
        }
        if(tt && t.task_type !== tt) return false;
        if(sy && t.system_name !== sy) return false;
        if(dv && t.dev_name !== dv) return false;
        if(ds && t.dev_status !== ds) return false;
        if(q) {
            const hay = [t.ma_yc, t.system_name, t.requester_name, t.description, t.ba_description, t.dev_name, t.module_name, t.task_type]
                .filter(Boolean).join(' ').toLowerCase();
            if(!hay.includes(q)) return false;
        }
        return true;
    });
    renderTasks(filtered, _TASKS_CACHE.length);
}

function renderTasks(tasks, totalCount) {
    document.getElementById('task-count').textContent = `${tasks.length}/${totalCount} công việc`;
    const tbody = document.getElementById('tasks-tbody');
    if(!tasks.length) {
        const msg = totalCount ? 'Không có công việc nào khớp bộ lọc.' : 'Bạn chưa có công việc nào.';
        tbody.innerHTML = `<tr><td colspan="10" style="text-align:center;padding:30px;color:var(--text-muted);">${msg}</td></tr>`;
        return;
    }
    tbody.innerHTML = tasks.map(t => `
        <tr id="task-row-${t.id}">
            <td><strong style="color:var(--primary-color);cursor:pointer;" onclick="openDetail(${t.id})" title="Xem chi tiết">${esc(t.ma_yc || '#'+t.id)}</strong></td>
            <td>
                <strong style="cursor:pointer;" onclick="openDetail(${t.id})">${esc(t.system_name)}</strong>
                ${t.module_name ? `<br><small style="color:var(--text-muted);">📦 ${esc(t.module_name)}</small>` : ''}
                <br><small style="color:var(--text-muted);">YC: ${esc(t.requester_name)}</small>
                ${t.assignee_note ? `<br><small style="color:#856404;background:#fff3cd;padding:1px 5px;border-radius:2px;display:inline-block;margin-top:3px;" title="Ghi chú phân công">📌 ${esc(t.assignee_note)}</small>` : ''}
            </td>
            <td><div style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${esc(t.description)}">${esc(t.description)}</div></td>
            <td>${priorityBadge(t.priority_ba)}</td>
            <td><small>${formatDateOnly(t.expected_end_date)}</small></td>
            <td>${t.office_link ? `<a href="${esc(t.office_link)}" target="_blank">Mở</a>` : '-'}</td>
            <td>${statusBadge(t.status)}</td>
            <td style="font-size:0.82rem;">
                ${t.dev_name ? `<strong>${esc(t.dev_name)}</strong><br>` : ''}
                ${devStatusBadge(t.dev_status)}
                ${t.dev_status==='Dev đã xong' ? `<br><button class="btn btn-outline btn-icon" style="margin-top:4px;font-size:0.75rem;" onclick="baDevRework(${t.id})">↩ Sửa lại</button>` : ''}
            </td>
            <td>${delayCell(t)}</td>
            <td>${nextStepCell(t)}</td>
        </tr>
    `).join('');
}

// ===== NOTIFICATIONS =====
function toggleNotif() {
    const panel = document.getElementById('notif-panel');
    panel.classList.toggle('open');
    if(panel.classList.contains('open')) loadNotifBell(true);
}
document.addEventListener('click', e => {
    const wrapper = document.querySelector('.notif-wrapper');
    if(wrapper && !wrapper.contains(e.target)) {
        document.getElementById('notif-panel').classList.remove('open');
    }
});
function renderNotifList(items, container) {
    if(!items.length) { container.innerHTML = '<div class="notif-empty">Không có thông báo nào</div>'; return; }
    container.innerHTML = items.map(n => `
        <div class="notif-item ${n.is_read == 0 ? 'unread' : ''}" onclick="onNotifClick(${n.id}, ${n.task_id || 'null'})">
            <div class="title">${esc(n.title)}${n.task_code ? ` <small style="color:var(--primary-color);">[${esc(n.task_code)}]</small>` : ''}</div>
            <div class="msg">${esc(n.message)}</div>
            <div class="time">${timeAgo(n.created_at)} trước · ${n.from_name ? esc(n.from_name) : 'Hệ thống'}</div>
        </div>
    `).join('');
}
function loadNotifBell(updatePanel = false) {
    fetch(API + '?action=get_notifications').then(r => r.json()).then(res => {
        const badge = document.getElementById('notif-badge');
        if(res.unread_count > 0) {
            badge.textContent = res.unread_count > 99 ? '99+' : res.unread_count;
            badge.style.display = 'inline-block';
        } else {
            badge.style.display = 'none';
        }
        if(updatePanel) renderNotifList(res.items || [], document.getElementById('notif-list'));
    });
}
function loadNotifPage() {
    fetch(API + '?action=get_notifications').then(r => r.json()).then(res => {
        renderNotifList(res.items || [], document.getElementById('notif-fullpage'));
    });
}
function markOneRead(id) {
    const fd = new FormData();
    fd.append('action', 'mark_notification_read');
    fd.append('notification_id', id);
    fetch(API, { method:'POST', body:fd }).then(() => loadNotifBell(true));
}
function markAllRead() {
    const fd = new FormData();
    fd.append('action', 'mark_notification_read');
    fetch(API, { method:'POST', body:fd }).then(() => { loadNotifBell(true); loadNotifPage(); });
}

// openDetail() được cung cấp bởi /assets/js/task-detail.js

// ======= GO TO TASK IN LIST =======
function goToTaskInList(taskId) {
    const tasksSec = document.getElementById('section-tasks');
    if(tasksSec && tasksSec.style.display === 'none') {
        document.querySelectorAll('.page-section').forEach(s => s.style.display = 'none');
        document.querySelectorAll('.sidebar-item').forEach(b => b.classList.remove('active'));
        tasksSec.style.display = 'block';
        const sb = document.querySelector('.sidebar-item[onclick*="tasks"]');
        if(sb) sb.classList.add('active');
        document.getElementById('page-title').textContent = 'Việc của tôi';
    }
    loadTasks();
    setTimeout(() => scrollToTaskRow(taskId), 250);
}
function scrollToTaskRow(taskId) {
    const row = document.getElementById('task-row-' + taskId);
    if(!row) return;
    row.scrollIntoView({ behavior: 'smooth', block: 'start' });
    row.classList.add('row-highlighted');
    setTimeout(() => row.classList.remove('row-highlighted'), 3500);
}

// ======= START CODING MODAL =======
// Tất cả logic được cung cấp bởi /assets/js/start-coding-modal.js (dùng chung BA + Lead)

// ======= TEST WORKFLOW (giống Lead) =======
function testAction(taskId, action, payload) {
    const fd = new FormData();
    fd.append('action', action);
    fd.append('task_id', taskId);
    if(payload) Object.entries(payload).forEach(([k,v]) => fd.append(k, v));
    return fetch(API, { method:'POST', body:fd }).then(r => r.json());
}
function testStart(taskId) {
    if(!confirm('Bắt đầu test task này? Cột "Người thực hiện test" trên sheet sẽ ghi tên bạn.')) return;
    testAction(taskId, 'test_start').then(res => {
        if(res.success) loadTasks(); else alert(res.message || 'Lỗi');
    });
}
function testDonePending(taskId) {
    if(!confirm('Đánh dấu test xong và chờ user nghiệm thu?')) return;
    testAction(taskId, 'test_done_pending_acceptance').then(res => {
        if(res.success) loadTasks(); else alert(res.message || 'Lỗi');
    });
}
function testAccepted(taskId) {
    if(!confirm('User đã nghiệm thu OK?\nTask sẽ chuyển sang "Kinkin nghiệm thu" (kết thúc).')) return;
    testAction(taskId, 'test_accepted').then(res => {
        if(res.success) loadTasks(); else alert(res.message || 'Lỗi');
    });
}
function openBugModal(taskId, maYc) {
    let modal = document.getElementById('bugLogModal');
    if(!modal) {
        modal = document.createElement('div');
        modal.id = 'bugLogModal';
        modal.className = 'modal';
        modal.innerHTML = `
            <div class="modal-content" style="max-width:520px;border-top-color:var(--danger-color);">
                <span class="close" onclick="closeModal('bugLogModal')">&times;</span>
                <h3 style="color:var(--danger-color);">🐛 Báo lỗi & gửi lại Dev</h3>
                <div id="bug-meta" style="background:#f8d7da;border-left:4px solid var(--danger-color);padding:10px 14px;font-size:0.86rem;margin:14px 0;"></div>
                <div class="form-group">
                    <label>Mô tả lỗi <span style="color:var(--danger-color);">*</span></label>
                    <textarea id="bug-desc" class="form-control" rows="5"
                        placeholder="Mô tả chi tiết lỗi: thao tác → kỳ vọng → thực tế."></textarea>
                    <small style="color:var(--text-muted);">Ghi chú này sẽ được append vào cột "Ghi chú" trên sheet để Dev xem.</small>
                </div>
                <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:14px;">
                    <button class="btn btn-outline" onclick="closeModal('bugLogModal')">Huỷ</button>
                    <button class="btn" id="bug-submit-btn" style="background:var(--danger-color);color:#fff;border:1px solid var(--danger-color);" onclick="submitBugReport()">
                        🐛 Báo lỗi & trả về Dev
                    </button>
                </div>
            </div>`;
        document.body.appendChild(modal);
    }
    document.getElementById('bug-meta').innerHTML = `Task <strong style="color:var(--danger-color);">${esc(maYc)}</strong> sẽ:<br>
        • Quay về <strong>Dion - đang xử lý</strong><br>
        • Trạng thái Dev → <strong>cần sửa lại</strong><br>
        • Trạng thái test reset về trống<br>
        • Mô tả lỗi append vào ghi chú`;
    document.getElementById('bug-desc').value = '';
    document.getElementById('bugLogModal').dataset.taskId = taskId;
    modal.style.display = 'block';
    setTimeout(() => document.getElementById('bug-desc').focus(), 50);
}
function submitBugReport() {
    const taskId = document.getElementById('bugLogModal').dataset.taskId;
    const desc   = document.getElementById('bug-desc').value.trim();
    if(!desc) { alert('Vui lòng mô tả lỗi'); return; }
    const btn = document.getElementById('bug-submit-btn');
    btn.disabled = true; btn.textContent = '⏳ Đang gửi...';
    testAction(taskId, 'test_report_bug', { bug_description: desc }).then(res => {
        btn.disabled = false; btn.textContent = '🐛 Báo lỗi & trả về Dev';
        if(res.success) { closeModal('bugLogModal'); loadTasks(); }
        else alert(res.message || 'Lỗi khi báo bug');
    });
}

function confirmBack(taskId, currentStatus) {
    const prevStatus = WORKFLOW_BACK[currentStatus];
    if(!prevStatus) { alert('Không thể quay lại từ bước này'); return; }
    const backdrop = document.createElement('div');
    backdrop.className = 'confirm-backdrop';
    backdrop.innerHTML = `
        <div class="confirm-box">
            <div class="head"><h4>Quay lại bước trước</h4></div>
            <div class="body">
                Quay lại: ${statusBadge(currentStatus)} <span class="arrow">→</span> ${statusBadge(prevStatus)}<br><br>
                <small style="color:var(--text-muted);">Các mốc thời gian tương ứng sẽ được xoá.</small>
            </div>
            <div class="actions">
                <button class="btn btn-outline btn-sm" onclick="this.closest('.confirm-backdrop').remove()">Huỷ bỏ</button>
                <button class="btn btn-primary btn-sm" id="do-back">← Xác nhận quay lại</button>
            </div>
        </div>`;
    document.body.appendChild(backdrop);
    backdrop.addEventListener('click', e => { if(e.target===backdrop) backdrop.remove(); });
    backdrop.querySelector('#do-back').addEventListener('click', () => {
        backdrop.remove();
        doNextStep(taskId, 'back');
    });
}

// ======= DEV ASSIGN (BA) =======
let currentDevTaskId = null;
function openDevAssign(taskId, sysName, devId, baDesc) {
    currentDevTaskId = taskId;
    document.getElementById('dev-assign-title').textContent = 'Giao Dev: ' + sysName;
    document.getElementById('dev-assign-desc').value = baDesc || '';
    document.getElementById('dev-assign-deadline').value = '';
    Promise.all([
        fetch(API + '?action=get_dev_list').then(r => r.json()),
        fetch(API + '?action=get_task_detail&task_id=' + taskId).then(r => r.json())
    ]).then(([devs, task]) => {
        const sel = document.getElementById('dev-assign-dev');
        sel.innerHTML = '<option value="">-- Chưa gán dev --</option>' +
            devs.map(d => `<option value="${d.id}" ${d.id == devId ? 'selected' : ''}>${esc(d.full_name)}</option>`).join('');
        const seed = task.dev_deadline || task.expected_end_date;
        if(seed) {
            const dt = new Date(seed.replace(' ', 'T'));
            const pad = n => String(n).padStart(2,'0');
            document.getElementById('dev-assign-deadline').value =
                `${dt.getFullYear()}-${pad(dt.getMonth()+1)}-${pad(dt.getDate())}T${pad(dt.getHours())}:${pad(dt.getMinutes())}`;
        }
    });
    document.getElementById('devAssignModal').style.display = 'block';
}
function submitDevAssign() {
    const devId = document.getElementById('dev-assign-dev').value;
    const desc  = document.getElementById('dev-assign-desc').value.trim();
    const deadline = document.getElementById('dev-assign-deadline').value;
    if(devId && !desc) { alert('Vui lòng nhập mô tả kỹ thuật cho Dev!'); return; }
    const fd = new FormData();
    fd.append('action', 'assign_dev');
    fd.append('task_id', currentDevTaskId);
    fd.append('dev_id', devId);
    fd.append('ba_description', desc);
    if(deadline) fd.append('dev_deadline', deadline);
    fetch(API, { method: 'POST', body: fd }).then(r => r.json()).then(res => {
        if(res.success) { closeModal('devAssignModal'); loadTasks(); }
        else alert(res.message || 'Lỗi khi giao dev');
    });
}

// loadFormLog không còn dùng (section gộp vào form)
function copyFormLink() {
    const url = document.getElementById('form-link-url').textContent;
    navigator.clipboard.writeText(url).then(() => {
        const hint = document.getElementById('copy-hint');
        hint.classList.add('show');
        setTimeout(() => hint.classList.remove('show'), 2500);
    });
}

// "Quản lý Dev" cho BA đã được bỏ — Dev quản lý qua Google Sheet, chỉ Lead CRUD nhân sự

// Dev sheet poll trigger (auto + manual)
function triggerDevSheetPoll(silent) {
    return fetch(API + '?action=dev_sheet_poll', { method: 'POST' })
        .then(r => r.json()).then(res => {
            const stamp = document.getElementById('dev-sync-stamp');
            if(stamp) {
                if(res.success && !res.throttled) {
                    const s = res.stats || {};
                    stamp.textContent = `Sheet sync: ${new Date().toLocaleTimeString('vi-VN')} · ${s.scanned||0} scan / ${s.updated||0} updated`;
                    stamp.style.color = (s.errors && s.errors.length) ? 'var(--danger-color)' : 'var(--success-color)';
                    if(s.updated > 0) loadTasks();
                } else if(!res.success && !silent) {
                    stamp.textContent = 'Sync lỗi: ' + (res.message || '');
                    stamp.style.color = 'var(--danger-color)';
                }
            }
            return res;
        });
}

loadTasks();
loadNotifBell();
triggerDevSheetPoll(true); // catch-up khi page load

setInterval(() => {
    const visible = document.querySelector('.page-section[style*="block"], .page-section:not([style*="none"])');
    const id = visible ? visible.id : 'section-tasks';
    if(id === 'section-tasks') loadTasks();
    // section-form không cần auto-refresh
    else if(id === 'section-notifications') loadNotifPage();
    triggerDevSheetPoll(true);
    loadNotifBell();
}, 15000);
</script>
<script src="<?php echo BASE_PATH; ?>/assets/js/task-detail.js?v=1"></script>
<script src="<?php echo BASE_PATH; ?>/assets/js/start-coding-modal.js?v=4"></script>
<script src="<?php echo BASE_PATH; ?>/assets/js/claim-modal.js?v=1"></script>
<script src="<?php echo BASE_PATH; ?>/assets/js/system-tree.js?v=3"></script>
<script>const SYS_IS_LEAD = <?php echo $_SESSION['role']==='lead' ? 'true' : 'false'; ?>;</script>
</body>
</html>
