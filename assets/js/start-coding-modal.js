/* =====================================================================
   start-coding-modal.js — Modal "Bắt đầu code" (BA + Lead)
   Logic 4 cấp: Module → Tính năng → Logic → Tính năng ẩn
   Per task_type:
     - Nâng cấp hệ thống → 4 dropdown + nút "+ Thêm mới" cạnh mỗi cấp
     - Fix lỗi hệ thống  → 4 dropdown chỉ chọn (no +)
     - Thay đổi/Lấy dữ liệu → ẩn box, chỉ ghi chú
   Auto-map đơn vị từ tasks.implementing_unit (đã set khi Lead/BA tiếp nhận YC)
   Tự pre-select group filter dev theo đơn vị.
   Dependencies: API, esc(), closeModal(), loadTasks(), loadNotifBell()
   ===================================================================== */

let scTaskId = null;
let _SC_TASK = null;
let _SC_NODES = [];      // toàn bộ system_nodes của system_id hiện tại
let _SC_SYSTEMS = [];
let _SC_GROUPS = [];
let _SC_CURRENT_DEVID = null;

// ============== ENTRY ==============
function openStartCoding(taskId) {
    scTaskId = taskId;
    document.getElementById('sc-title').textContent = 'Đang tải...';
    document.getElementById('sc-meta').textContent = '';
    document.getElementById('sc-req-desc').textContent = '';
    document.getElementById('sc-ba-desc').value = '';
    document.getElementById('sc-mf-box').style.display = 'none';
    document.getElementById('sc-note-box').style.display = 'none';
    document.getElementById('startCodingModal').style.display = 'block';
    // Reset toggle states
    [1,2,3,4].forEach(scResetAddState);

    Promise.all([
        fetch(API + '?action=get_task_detail&task_id=' + taskId).then(r => r.json()),
        fetch(API + '?action=get_dev_list').then(r => r.json()),
        fetch(API + '?action=list_user_groups').then(r => r.json()),
        fetch(API + '?action=list_systems').then(r => r.json())
    ]).then(([task, devs, groups, systems]) => {
        _SC_TASK = task;
        _SC_GROUPS = Array.isArray(groups) ? groups : [];
        _SC_SYSTEMS = Array.isArray(systems) ? systems : [];
        _SC_CURRENT_DEVID = task.dev_id;

        // Header
        document.getElementById('sc-title').textContent = (task.ma_yc || '#'+task.id) + ' — ' + (task.system_name || '');
        document.getElementById('sc-meta').textContent =
            'Người YC: ' + (task.requester_name || '') + ' · ' + (task.requester_dept || '') +
            ' · Loại: ' + (task.task_type || '-') +
            ' · Deadline YC: ' + (task.expected_end_date ? new Date(task.expected_end_date).toLocaleDateString('vi-VN') : '-');
        document.getElementById('sc-req-desc').textContent = task.description || '';
        document.getElementById('sc-ba-desc').value = task.ba_description || '';

        // Đơn vị thực hiện (auto-map từ task)
        const unit = task.implementing_unit || '—';
        document.getElementById('sc-unit-badge').textContent = unit;

        // Pre-fill khoảng ngày Dev
        const startInput = document.getElementById('sc-dev-start');
        const endInput   = document.getElementById('sc-dev-end');
        const pad = n => String(n).padStart(2,'0');
        const today = new Date();
        startInput.value = task.dev_planned_start
            ? task.dev_planned_start.substring(0, 10)
            : `${today.getFullYear()}-${pad(today.getMonth()+1)}-${pad(today.getDate())}`;
        const seedEnd = task.dev_planned_end || task.dev_deadline || task.expected_end_date;
        endInput.value = seedEnd ? seedEnd.substring(0, 10) : '';

        // System dropdown — BA có thể chỉnh sửa nếu form yêu cầu chọn sai
        const sysSel = document.getElementById('sc-system');
        sysSel.innerHTML = '<option value="">-- Chưa chọn --</option>' +
            _SC_SYSTEMS.map(s => `<option value="${s.id}" ${s.id == task.system_id ? 'selected' : ''}>${esc(s.name)}</option>`).join('');

        // Group dropdown — auto-select theo unit
        const grpSel = document.getElementById('sc-group');
        grpSel.innerHTML = '<option value="">-- Tất cả --</option>' +
            _SC_GROUPS.map(g => `<option value="${g.id}">${esc(g.name)} (${g.member_count})</option>`).join('');
        const matchGroup = _SC_GROUPS.find(g => g.name === unit);
        if(matchGroup) {
            grpSel.value = matchGroup.id;
            // Filter dev list theo group
            scReloadDevList();
        } else {
            renderScDevOptions(devs, task.dev_id);
        }

        // Render box module/feature theo task_type
        renderScModuleFeatureBox();
    });
}

// ============== SYSTEM CHANGE ==============
function scOnSystemChange() {
    const sysId = document.getElementById('sc-system').value;
    _SC_TASK.system_id = sysId ? parseInt(sysId, 10) : null;
    // Reload nodes của system mới
    [1,2,3,4].forEach(lvl => {
        scResetAddState(lvl);
        const sel = scLevelSelect(lvl);
        if(sel) sel.value = '';
    });
    renderScModuleFeatureBox();
}

// ============== TASK TYPE / MODE ==============
function renderScModuleFeatureBox() {
    const box = document.getElementById('sc-mf-box');
    const note = document.getElementById('sc-note-box');
    const title = document.getElementById('sc-mf-title');
    const warn = document.getElementById('sc-mf-warn');

    const ttype = (_SC_TASK.task_type || '').toLowerCase();
    const sysId = _SC_TASK.system_id;
    const isUpgrade = ttype.includes('nâng cấp');
    const isFix = ttype.includes('fix lỗi');
    const isDataChg = ttype.includes('thay đổi dữ liệu');
    const isDataGet = ttype.includes('lấy dữ liệu');

    if(isDataChg || isDataGet) {
        box.style.display = 'none';
        note.style.display = 'block';
        return;
    }

    box.style.display = 'block';
    note.style.display = 'none';

    if(!sysId) {
        title.textContent = '⚠ Chưa chọn hệ thống';
        warn.textContent = 'Vui lòng chọn hệ thống ở trên trước khi chọn module/tính năng.';
        warn.style.display = 'block';
        // Disable tất cả dropdowns
        [1,2,3,4].forEach(lvl => {
            const sel = scLevelSelect(lvl); if(sel) sel.disabled = true;
            scShowAddBtn(lvl, false);
        });
        return;
    }

    title.textContent = isUpgrade
        ? '🔧 Nâng cấp — chọn từ cây hoặc + để thêm mới ở mỗi cấp'
        : '🐛 Fix lỗi — chọn module/tính năng cần sửa từ danh sách hệ thống';

    // Bật tất cả dropdown
    [1,2,3,4].forEach(lvl => {
        const sel = scLevelSelect(lvl); if(sel) sel.disabled = false;
        // + button: chỉ Nâng cấp mới hiện
        scShowAddBtn(lvl, isUpgrade);
        document.getElementById('sc-l' + lvl + '-add-hint').style.display = isUpgrade ? 'inline' : 'none';
    });

    // Load nodes của system
    fetch(API + '?action=get_system_nodes_for_task&system_id=' + sysId)
        .then(r => r.json()).then(nodes => {
            _SC_NODES = Array.isArray(nodes) ? nodes : [];
            scPopulateLevel(1, null, _SC_TASK.module_node_id);
            scPopulateLevel(2, _SC_TASK.module_node_id, _SC_TASK.feature_node_id);
            scPopulateLevel(3, _SC_TASK.feature_node_id, _SC_TASK.logic_node_id);
            scPopulateLevel(4, _SC_TASK.logic_node_id, _SC_TASK.hidden_node_id);

            // Fix mode + cây trống → warn
            if(isFix) {
                const hasModules = _SC_NODES.some(n => n.node_type === 'module');
                if(!hasModules) {
                    warn.innerHTML = '⚠ Hệ thống này <strong>chưa có Module/Tính năng</strong> trong "Danh sách hệ thống". Vui lòng vào sidebar → Danh sách hệ thống → mở hệ thống → thêm cây node trước khi tiếp tục.';
                    warn.style.display = 'block';
                } else {
                    warn.style.display = 'none';
                }
            } else {
                warn.style.display = 'none';
            }
        });
}

// ============== LEVEL HELPERS ==============
function scLevelSelect(lvl) {
    return document.getElementById(['','sc-mod-sel','sc-feat-sel','sc-logic-sel','sc-hidden-sel'][lvl]);
}
function scLevelNewInput(lvl) {
    return document.getElementById(['','sc-new-module','sc-new-feature','sc-new-logic','sc-new-hidden'][lvl]);
}
function scLevelNewWrapper(lvl) {
    return document.getElementById(['','sc-mod-new','sc-feat-new','sc-logic-new','sc-hidden-new'][lvl]);
}
function scLevelAddBtn(lvl) {
    const row = document.getElementById('sc-level-' + lvl);
    return row ? row.querySelector('.sc-add-btn') : null;
}
function scLevelNodeType(lvl) {
    return ['','module','feature','logic','hidden'][lvl];
}

function scPopulateLevel(lvl, parentId, preselectId) {
    const sel = scLevelSelect(lvl);
    if(!sel) return;
    const nodeType = scLevelNodeType(lvl);
    // Cấp 1 (module): parent_id IS NULL. Cấp 2-4: lọc theo parent_id chọn ở cấp trên.
    const items = _SC_NODES.filter(n => {
        if(n.node_type !== nodeType) return false;
        if(lvl === 1) return n.parent_id == null;
        return parentId && n.parent_id == parentId;
    });
    const placeholder = lvl <= 2
        ? `<option value="">-- Chọn ${['','Module','Tính năng'][lvl]} --</option>`
        : `<option value="">-- Không chọn --</option>`;
    sel.innerHTML = placeholder + items.map(n =>
        `<option value="${n.id}" ${n.id == preselectId ? 'selected' : ''}>${esc(n.name)}</option>`
    ).join('');
}

function scOnLevelChange(lvl) {
    // Khi đổi cấp N → reset cấp N+1 trở xuống
    const parentId = scLevelSelect(lvl).value || null;
    for(let next = lvl + 1; next <= 4; next++) {
        const childSel = scLevelSelect(next);
        if(childSel) childSel.value = '';
        scResetAddState(next);
    }
    if(lvl < 4 && parentId) {
        scPopulateLevel(lvl + 1, parentId, null);
    } else if(lvl < 4) {
        // Parent rỗng → list cấp dưới rỗng
        scPopulateLevel(lvl + 1, null, null);
    }
}

function scShowAddBtn(lvl, show) {
    const btn = scLevelAddBtn(lvl);
    if(btn) btn.style.display = show ? 'block' : 'none';
}
function scToggleAdd(lvl) {
    // Cấp N chỉ + được nếu cấp trên đã có giá trị (chọn hoặc tạo mới)
    if(lvl > 1) {
        const parentSel = scLevelSelect(lvl - 1);
        const parentNew = scLevelNewInput(lvl - 1);
        const parentHasValue = (parentSel && parentSel.value) || (parentNew && parentNew.value && parentNew.value.trim() !== '');
        if(!parentHasValue) {
            alert(`Vui lòng chọn hoặc thêm mới cấp ${lvl - 1} (${['','Module','Tính năng','Logic'][lvl-1]}) trước.`);
            return;
        }
    }
    const wrap = scLevelNewWrapper(lvl);
    const sel = scLevelSelect(lvl);
    const btn = scLevelAddBtn(lvl);
    const isActive = btn.classList.contains('active');
    if(isActive) {
        wrap.style.display = 'none';
        scLevelNewInput(lvl).value = '';
        btn.classList.remove('active');
        btn.textContent = '+';
        sel.disabled = false;
    } else {
        wrap.style.display = 'block';
        btn.classList.add('active');
        btn.textContent = '−';
        sel.value = '';   // bỏ chọn dropdown khi + thêm mới
        sel.disabled = true;
        scLevelNewInput(lvl).focus();
    }
}
function scResetAddState(lvl) {
    const wrap = scLevelNewWrapper(lvl);
    const btn = scLevelAddBtn(lvl);
    const sel = scLevelSelect(lvl);
    const inp = scLevelNewInput(lvl);
    if(wrap) wrap.style.display = 'none';
    if(inp) inp.value = '';
    if(btn) { btn.classList.remove('active'); btn.textContent = '+'; }
    if(sel) sel.disabled = false;
}

// ============== DEV LIST ==============
function renderScDevOptions(devs, selectedId) {
    const sel = document.getElementById('sc-dev');
    sel.innerHTML = '<option value="">-- Chưa gán dev --</option>' +
        devs.map(d => `<option value="${d.id}" ${d.id == selectedId ? 'selected' : ''}>${esc(d.full_name)}${d.nickname ? ' ('+esc(d.nickname)+')' : ''}</option>`).join('');
}
function scReloadDevList() {
    const gid = document.getElementById('sc-group').value;
    const url = API + '?action=get_dev_list' + (gid ? '&group_id=' + gid : '');
    fetch(url).then(r => r.json()).then(devs => renderScDevOptions(devs, _SC_CURRENT_DEVID));
}

// ============== SUBMIT ==============
function submitStartCoding() {
    const devId = document.getElementById('sc-dev').value;
    const desc  = document.getElementById('sc-ba-desc').value.trim();
    const planStart = document.getElementById('sc-dev-start').value;
    const planEnd = document.getElementById('sc-dev-end').value;
    const sysId = document.getElementById('sc-system').value;
    if(devId && !desc) { alert('Vui lòng nhập mô tả kỹ thuật cho Dev!'); return; }
    if(planStart && planEnd && planStart > planEnd) { alert('Ngày bắt đầu phải trước ngày kết thúc!'); return; }

    const btn = document.querySelector('#startCodingModal .btn-primary');
    btn.disabled = true; btn.textContent = '⏳ Đang xử lý...';

    // Gather 4-level data
    const fdAssign = new FormData();
    fdAssign.append('action', 'assign_dev');
    fdAssign.append('task_id', scTaskId);
    if(devId) fdAssign.append('dev_id', devId);
    fdAssign.append('ba_description', desc);
    if(planStart) fdAssign.append('dev_planned_start', planStart);
    if(planEnd)   fdAssign.append('dev_planned_end', planEnd);

    // System (BA có thể đổi)
    if(sysId && sysId !== String(_SC_TASK.system_id || '')) {
        // Lưu system_id mới qua endpoint update_task_meta? Nhưng assign_dev không nhận.
        // Tạm dùng workaround: gọi update_task_meta riêng nếu khác.
        // Sẽ wire endpoint này ở step sau. Để tránh leak, không pass system_id cho assign_dev.
    }

    // 4-level
    [1,2,3,4].forEach(lvl => {
        const sel = scLevelSelect(lvl);
        const inp = scLevelNewInput(lvl);
        const newName = (inp && inp.value || '').trim();
        const fieldId = ['', 'module_node_id','feature_node_id','logic_node_id','hidden_node_id'][lvl];
        const fieldNew = ['', 'new_module_name','new_feature_name','new_logic_name','new_hidden_name'][lvl];
        if(newName) {
            fdAssign.append(fieldNew, newName);
        } else if(sel && sel.value) {
            fdAssign.append(fieldId, sel.value);
        }
    });

    const writeAndAdvance = (showNodeToast = false) => {
        const fdStep = new FormData();
        fdStep.append('action', 'next_step');
        fdStep.append('task_id', scTaskId);
        fdStep.append('direction', 'next');
        fetch(API, { method:'POST', body:fdStep }).then(r => r.json()).then(res => {
            btn.disabled = false; btn.textContent = '▶ Xác nhận Bắt đầu code & Phân công Dev';
            if(res.success) {
                closeModal('startCodingModal');
                if(typeof loadTasks === 'function') loadTasks();
                if(typeof loadNotifBell === 'function') loadNotifBell();
                if(showNodeToast) _scShowNewNodeToast();
                if(res.sheet_synced === false) {
                    alert('⚠ Task đã advance nhưng ghi vào Dev Sheet thất bại:\n' + (res.sheet_error || ''));
                }
            } else {
                alert(res.message || 'Có lỗi xảy ra');
            }
        });
    };

    // Ghi nhận xem có node mới được tạo không (để sau khi xong hiện link hệ thống)
    const hadNewNodes = [1,2,3,4].some(lvl => {
        const inp = scLevelNewInput(lvl);
        return inp && inp.value && inp.value.trim() !== '';
    });

    // Step 1: assign_dev (luôn gọi để lưu mod/feat/logic/hidden + ba_desc)
    fetch(API, { method:'POST', body:fdAssign }).then(r => r.json()).then(res => {
        if(!res.success) { btn.disabled = false; btn.textContent = '▶ Xác nhận Bắt đầu code & Phân công Dev'; alert(res.message || 'Lỗi gán dev/lưu metadata'); return; }
        // Step 2: nếu BA đổi system_id, gọi assign_task để update system (chứ assign_dev ko nhận)
        if(sysId && sysId !== String(_SC_TASK.system_id || '')) {
            const fdSys = new FormData();
            fdSys.append('action', 'update_task_system');
            fdSys.append('task_id', scTaskId);
            fdSys.append('system_id', sysId);
            fetch(API, { method:'POST', body:fdSys }).then(() => writeAndAdvance(hadNewNodes));
        } else {
            writeAndAdvance(hadNewNodes);
        }
    });
}

function _scShowNewNodeToast() {
    let toast = document.getElementById('sc-new-node-toast');
    if(!toast) {
        toast = document.createElement('div');
        toast.id = 'sc-new-node-toast';
        toast.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:9999;background:#084298;color:#fff;padding:12px 18px;border-radius:4px;box-shadow:0 4px 12px rgba(0,0,0,.2);font-size:0.88rem;display:flex;align-items:center;gap:12px;';
        toast.innerHTML = `<span>✅ Node mới đã lưu vào Danh sách hệ thống</span>
            <button style="background:#fff;color:#084298;border:none;padding:4px 10px;cursor:pointer;font-weight:600;border-radius:2px;"
                onclick="if(typeof switchSection==='function')switchSection('systems');document.getElementById('sc-new-node-toast').remove();">
                Xem ngay →
            </button>
            <button style="background:transparent;color:#fff;border:none;cursor:pointer;font-size:1.1rem;padding:0 4px;"
                onclick="this.closest('#sc-new-node-toast').remove();">×</button>`;
        document.body.appendChild(toast);
    }
    clearTimeout(toast._timer);
    toast._timer = setTimeout(() => toast.remove(), 8000);
}
