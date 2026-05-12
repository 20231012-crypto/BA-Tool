/* =====================================================================
   workflow-builder.js — Drag-drop workflow editor (1Office-style)
   Depends on: API, esc()
   Used by: views/admin/lead.php
   ===================================================================== */

const NODE_TYPES = {
    start: {
        label: 'Bắt đầu',
        icon: '▶',
        desc: 'Điểm khởi đầu của quy trình + điều kiện kích hoạt',
        defaults: () => ({ label: 'Bắt đầu', config: { trigger: 'manual', filter_field: '', filter_op: 'equals', filter_value: '' } })
    },
    task: {
        label: 'Công việc',
        icon: '◆',
        desc: 'Bước thực thi: gán trạng thái + người làm',
        defaults: () => ({ label: 'Công việc mới', config: { status: '', assignee_role: 'ba' } })
    },
    condition: {
        label: 'Điều kiện',
        icon: '?',
        desc: 'Phân nhánh: lọc theo field (đúng/sai)',
        defaults: () => ({ label: 'Điều kiện', config: { field: '', op: 'equals', value: '' } })
    },
    approval: {
        label: 'Duyệt',
        icon: '✓',
        desc: 'Chờ phê duyệt từ role/user',
        defaults: () => ({ label: 'Chờ duyệt', config: { approver_role: 'lead' } })
    },
    notify: {
        label: 'Thông báo',
        icon: '🔔',
        desc: 'Gửi notification cho role/user',
        defaults: () => ({ label: 'Gửi thông báo', config: { target_role: 'ba', message: '' } })
    },
    end: {
        label: 'Kết thúc',
        icon: '■',
        desc: 'Điểm kết thúc quy trình',
        defaults: () => ({ label: 'Kết thúc', config: { status: 'Kinkin nghiệm thu' } })
    }
};

// Field options cho condition
const CONDITION_FIELDS = [
    { value: 'task_type',     label: 'Loại YC' },
    { value: 'priority_ba',   label: 'Ưu tiên BA' },
    { value: 'priority_requester', label: 'Ưu tiên YC' },
    { value: 'system_name',   label: 'Hệ thống' },
    { value: 'requester_dept', label: 'Phòng ban' },
    { value: 'classification', label: 'Phân loại' },
    { value: 'implementing_unit', label: 'Đơn vị thực hiện' }
];
const CONDITION_OPS = [
    { value: 'equals',    label: 'Bằng' },
    { value: 'not_equals', label: 'Không bằng' },
    { value: 'contains',  label: 'Chứa' },
    { value: 'not_empty', label: 'Có giá trị' },
    { value: 'is_empty',  label: 'Rỗng' }
];
const ROLES = [
    { value: 'lead', label: 'Lead BA' },
    { value: 'ba',   label: 'BA' },
    { value: 'dev',  label: 'Developer' }
];
const TASK_STATUSES = [
    'Chờ tiếp nhận',
    'Todo - chờ xác nhận với Sếp',
    'Dion - đang xử lý',
    'Dion - Chờ nghiệm thu',
    'Kinkin nghiệm thu',
    'Huỷ'
];

// Sự kiện kích hoạt quy trình (Start node trigger)
const TRIGGER_EVENTS = [
    { value: 'manual',          label: 'Thủ công (Lead bấm chạy)' },
    { value: 'task_created',    label: 'Khi YC mới được tạo' },
    { value: 'ba_assigned',     label: 'Khi BA được phân công' },
    { value: 'dev_assigned',    label: 'Khi Dev được giao việc' },
    { value: 'status_changed',  label: 'Khi trạng thái chuyển sang...' },
    { value: 'dev_done',        label: 'Khi Dev hoàn thành' },
    { value: 'task_overdue',    label: 'Khi YC quá hạn' }
];

// ─── State ─────────────────────────────────────────────────────────
let WB = {
    workflowId: null,
    code: '', name: '', group_name: '', description: '', status: 'draft',
    nodes: [],
    edges: [],
    selectedNodeId: null,
    connecting: null, // { fromNodeId } | null
    nextNodeId: 1,
    nextEdgeId: 1,
    dragging: null,
};

function wbReset() {
    WB = {
        workflowId: null, code: '', name: '', group_name: '', description: '', status: 'draft',
        nodes: [], edges: [], selectedNodeId: null, connecting: null,
        nextNodeId: 1, nextEdgeId: 1, dragging: null
    };
}

function wbGenNodeId() {
    while(WB.nodes.find(n => n.id === 'n' + WB.nextNodeId)) WB.nextNodeId++;
    return 'n' + (WB.nextNodeId++);
}
function wbGenEdgeId() {
    while(WB.edges.find(e => e.id === 'e' + WB.nextEdgeId)) WB.nextEdgeId++;
    return 'e' + (WB.nextEdgeId++);
}

// ─── Render canvas ─────────────────────────────────────────────────
function wbRenderCanvas() {
    const canvas = document.getElementById('wb-canvas');
    if(!canvas) return;
    canvas.innerHTML = '<svg class="wb-svg" id="wb-svg"></svg>';
    WB.nodes.forEach(n => canvas.appendChild(wbCreateNodeEl(n)));
    wbRenderEdges();
}

function wbCreateNodeEl(n) {
    const def = NODE_TYPES[n.type] || NODE_TYPES.task;
    const el = document.createElement('div');
    el.className = 'wb-node';
    el.dataset.type = n.type;
    el.dataset.id = n.id;
    el.style.left = (n.x || 0) + 'px';
    el.style.top = (n.y || 0) + 'px';
    if(WB.selectedNodeId === n.id) el.classList.add('selected');

    let metaTxt = '';
    if(n.type === 'start') {
        const trig = TRIGGER_EVENTS.find(t => t.value === (n.config?.trigger || 'manual'));
        metaTxt = '🎯 ' + (trig ? esc(trig.label) : 'Thủ công');
        if(n.config?.filter_field) {
            metaTxt += `<br>${esc(n.config.filter_field)} <strong>${esc(n.config.filter_op||'=')}</strong> "${esc(n.config.filter_value||'')}"`;
        }
    } else if(n.type === 'task' || n.type === 'end') {
        if(n.config?.status) metaTxt += '→ ' + esc(n.config.status);
        if(n.config?.assignee_role) metaTxt += (metaTxt?'<br>':'') + 'Role: ' + esc(n.config.assignee_role);
    } else if(n.type === 'condition') {
        if(n.config?.field) metaTxt = `${esc(n.config.field)} <strong>${esc(n.config.op||'=')}</strong> "${esc(n.config.value||'')}"`;
    } else if(n.type === 'approval') {
        if(n.config?.approver_role) metaTxt = 'Duyệt: ' + esc(n.config.approver_role);
    } else if(n.type === 'notify') {
        if(n.config?.target_role) metaTxt = '→ ' + esc(n.config.target_role);
    }

    el.innerHTML = `
        <div class="wb-node-head"><span class="dot">${def.icon}</span>${def.label}</div>
        <div class="wb-node-label">${esc(n.label || def.label)}</div>
        ${metaTxt ? `<div class="wb-node-meta">${metaTxt}</div>` : ''}
        <div class="wb-anchor right"  data-anchor="right"></div>
        <div class="wb-anchor left"   data-anchor="left"></div>
        <div class="wb-anchor top"    data-anchor="top"></div>
        <div class="wb-anchor bottom" data-anchor="bottom"></div>
    `;
    return el;
}

/**
 * Tính 2 điểm anchor (start/end) bám sát mép thật của node DOM.
 * Chọn cạnh ra (right/left/top/bottom) theo vị trí tương đối giữa 2 node để dây ngắn nhất.
 */
function wbAnchorPoints(elFrom, elTo) {
    const fx = elFrom.offsetLeft, fy = elFrom.offsetTop;
    const fw = elFrom.offsetWidth, fh = elFrom.offsetHeight;
    const tx = elTo.offsetLeft, ty = elTo.offsetTop;
    const tw = elTo.offsetWidth, th = elTo.offsetHeight;

    const fcx = fx + fw/2, fcy = fy + fh/2;
    const tcx = tx + tw/2, tcy = ty + th/2;

    const dx = tcx - fcx, dy = tcy - fcy;
    let p1, p2, dir;
    if(Math.abs(dx) >= Math.abs(dy)) {
        // Đi ngang
        if(dx >= 0) { p1 = { x: fx + fw, y: fcy }; p2 = { x: tx,       y: tcy }; dir = 'h'; }
        else        { p1 = { x: fx,       y: fcy }; p2 = { x: tx + tw, y: tcy }; dir = 'h'; }
    } else {
        // Đi dọc
        if(dy >= 0) { p1 = { x: fcx, y: fy + fh }; p2 = { x: tcx, y: ty       }; dir = 'v'; }
        else        { p1 = { x: fcx, y: fy       }; p2 = { x: tcx, y: ty + th }; dir = 'v'; }
    }
    return { p1, p2, dir };
}

function wbRenderEdges() {
    const svg = document.getElementById('wb-svg');
    const canvas = document.getElementById('wb-canvas');
    if(!svg || !canvas) return;
    svg.innerHTML = `<defs>
        <marker id="wb-arrow" markerWidth="10" markerHeight="10" refX="9" refY="5" orient="auto">
            <path d="M0,0 L10,5 L0,10 Z" fill="#6c757d"/>
        </marker>
        <marker id="wb-arrow-true" markerWidth="10" markerHeight="10" refX="9" refY="5" orient="auto">
            <path d="M0,0 L10,5 L0,10 Z" fill="#28a745"/>
        </marker>
        <marker id="wb-arrow-false" markerWidth="10" markerHeight="10" refX="9" refY="5" orient="auto">
            <path d="M0,0 L10,5 L0,10 Z" fill="#dc3545"/>
        </marker>
    </defs>`;

    WB.edges.forEach(e => {
        const elA = canvas.querySelector(`.wb-node[data-id="${e.from}"]`);
        const elB = canvas.querySelector(`.wb-node[data-id="${e.to}"]`);
        if(!elA || !elB) return;

        const { p1, p2, dir } = wbAnchorPoints(elA, elB);
        // Bezier control points: lệch theo trục đi
        let c1, c2;
        const span = (dir === 'h')
            ? Math.max(40, Math.abs(p2.x - p1.x) * 0.5)
            : Math.max(40, Math.abs(p2.y - p1.y) * 0.5);
        if(dir === 'h') {
            c1 = { x: p1.x + (p2.x >= p1.x ? span : -span), y: p1.y };
            c2 = { x: p2.x + (p2.x >= p1.x ? -span : span), y: p2.y };
        } else {
            c1 = { x: p1.x, y: p1.y + (p2.y >= p1.y ? span : -span) };
            c2 = { x: p2.x, y: p2.y + (p2.y >= p1.y ? -span : span) };
        }
        const path = `M ${p1.x} ${p1.y} C ${c1.x} ${c1.y}, ${c2.x} ${c2.y}, ${p2.x} ${p2.y}`;

        const cls = e.branch === 'true' ? 'true' : (e.branch === 'false' ? 'false' : '');
        const marker = e.branch === 'true' ? 'url(#wb-arrow-true)' : (e.branch === 'false' ? 'url(#wb-arrow-false)' : 'url(#wb-arrow)');
        const label = e.label || (e.branch ? (e.branch === 'true' ? '✓ Đúng' : '✗ Sai') : '');
        const tx = (p1.x + p2.x) / 2, ty = (p1.y + p2.y) / 2 - 8;

        const g = document.createElementNS('http://www.w3.org/2000/svg', 'g');
        g.classList.add('edge');
        g.dataset.edgeId = e.id;
        g.innerHTML = `<path d="${path}" class="${cls}" marker-end="${marker}"/>${label ? `<text x="${tx}" y="${ty}" text-anchor="middle">${esc(label)}</text>` : ''}`;
        svg.appendChild(g);
    });
}

// ─── Add / drag / select / delete nodes ────────────────────────────
function wbAddNode(type, x = 60, y = 60) {
    const def = NODE_TYPES[type];
    if(!def) return;
    const id = wbGenNodeId();
    const node = { id, type, x, y, ...def.defaults() };
    WB.nodes.push(node);
    wbSelectNode(id);
    wbRenderCanvas();
}

function wbSelectNode(id) {
    WB.selectedNodeId = id;
    document.querySelectorAll('.wb-node').forEach(el => {
        el.classList.toggle('selected', el.dataset.id === id);
    });
    wbRenderConfigPanel();
}

function wbDeleteNode(id) {
    WB.nodes = WB.nodes.filter(n => n.id !== id);
    WB.edges = WB.edges.filter(e => e.from !== id && e.to !== id);
    if(WB.selectedNodeId === id) WB.selectedNodeId = null;
    wbRenderCanvas();
    wbRenderConfigPanel();
}

function wbDeleteEdge(edgeId) {
    WB.edges = WB.edges.filter(e => e.id !== edgeId);
    wbRenderEdges();
}

// ─── Mouse: drag node + connect from anchor ────────────────────────
function wbAttachCanvasHandlers() {
    const wrap = document.getElementById('wb-canvas-wrap');
    const canvas = document.getElementById('wb-canvas');
    if(!wrap || !canvas) return;
    if(canvas.dataset.handlersAttached === '1') return;
    canvas.dataset.handlersAttached = '1';

    canvas.addEventListener('mousedown', e => {
        // Anchor → start connection
        if(e.target.classList.contains('wb-anchor')) {
            const nodeEl = e.target.closest('.wb-node');
            if(nodeEl) {
                WB.connecting = { fromNodeId: nodeEl.dataset.id };
                canvas.classList.add('connecting');
                e.stopPropagation();
                return;
            }
        }
        const nodeEl = e.target.closest('.wb-node');
        if(nodeEl) {
            // If currently connecting, finish on click
            if(WB.connecting && WB.connecting.fromNodeId !== nodeEl.dataset.id) {
                wbCreateEdge(WB.connecting.fromNodeId, nodeEl.dataset.id);
                WB.connecting = null;
                canvas.classList.remove('connecting');
                return;
            }
            // Else start drag
            wbSelectNode(nodeEl.dataset.id);
            const node = WB.nodes.find(n => n.id === nodeEl.dataset.id);
            const rect = canvas.getBoundingClientRect();
            WB.dragging = {
                id: node.id,
                offsetX: (e.clientX - rect.left) - node.x,
                offsetY: (e.clientY - rect.top) - node.y
            };
            e.preventDefault();
        } else {
            // Click empty canvas → cancel connecting / deselect
            if(WB.connecting) { WB.connecting = null; canvas.classList.remove('connecting'); }
            wbSelectNode(null);
        }
    });

    document.addEventListener('mousemove', e => {
        if(!WB.dragging) return;
        const rect = canvas.getBoundingClientRect();
        const node = WB.nodes.find(n => n.id === WB.dragging.id);
        if(!node) return;
        node.x = Math.max(0, Math.round((e.clientX - rect.left - WB.dragging.offsetX) / 10) * 10);
        node.y = Math.max(0, Math.round((e.clientY - rect.top - WB.dragging.offsetY) / 10) * 10);
        const el = canvas.querySelector(`.wb-node[data-id="${node.id}"]`);
        if(el) { el.style.left = node.x + 'px'; el.style.top = node.y + 'px'; }
        wbRenderEdges();
    });

    document.addEventListener('mouseup', () => { WB.dragging = null; });

    // Click edge → option to delete
    canvas.addEventListener('click', e => {
        const g = e.target.closest('g.edge');
        if(g) {
            if(confirm('Xoá kết nối này?')) wbDeleteEdge(g.dataset.edgeId);
        }
    });

    // Drag from palette → drop on canvas
    document.querySelectorAll('.wb-palette-item').forEach(item => {
        item.addEventListener('dragstart', ev => {
            ev.dataTransfer.setData('node-type', item.dataset.type);
            ev.dataTransfer.effectAllowed = 'copy';
        });
        item.draggable = true;
    });
    canvas.addEventListener('dragover', ev => { ev.preventDefault(); ev.dataTransfer.dropEffect = 'copy'; });
    canvas.addEventListener('drop', ev => {
        ev.preventDefault();
        const type = ev.dataTransfer.getData('node-type');
        if(!type) return;
        const rect = canvas.getBoundingClientRect();
        wbAddNode(type, Math.round((ev.clientX - rect.left - 70) / 10) * 10, Math.round((ev.clientY - rect.top - 30) / 10) * 10);
    });
}

function wbCreateEdge(fromId, toId) {
    if(fromId === toId) return;
    // Tránh duplicate trừ trường hợp from là condition (cần 2 nhánh)
    const fromNode = WB.nodes.find(n => n.id === fromId);
    const existing = WB.edges.filter(e => e.from === fromId && e.to === toId);
    if(existing.length > 0 && fromNode?.type !== 'condition') return;

    let branch = null, label = null;
    if(fromNode?.type === 'condition') {
        const branches = WB.edges.filter(e => e.from === fromId).map(e => e.branch);
        if(!branches.includes('true')) { branch = 'true'; label = '✓ Đúng'; }
        else if(!branches.includes('false')) { branch = 'false'; label = '✗ Sai'; }
        else { alert('Node điều kiện đã có cả 2 nhánh true/false'); return; }
    }
    WB.edges.push({ id: wbGenEdgeId(), from: fromId, to: toId, branch, label });
    wbRenderEdges();
}

// ─── Config panel (right side) ─────────────────────────────────────
function wbRenderConfigPanel() {
    const panel = document.getElementById('wb-config');
    if(!panel) return;
    if(!WB.selectedNodeId) {
        panel.innerHTML = `<div class="wb-empty">
            <div style="font-size:1.6rem;margin-bottom:8px;">⚙️</div>
            Chọn một node trên canvas để cấu hình.<br><br>
            <small>Kéo thả node từ panel trái.<br>Click vào chấm tròn ở mép node để kéo dây kết nối.</small>
        </div>`;
        return;
    }
    const n = WB.nodes.find(x => x.id === WB.selectedNodeId);
    if(!n) return;
    const def = NODE_TYPES[n.type];

    let html = `<div class="wb-config-title">${def.icon} ${def.label} <small style="color:var(--text-muted);font-weight:400;">[${n.id}]</small></div>`;
    html += `<div class="form-group">
        <label>Tên node</label>
        <input type="text" class="form-control" id="wb-cfg-label" value="${esc(n.label||'')}">
    </div>`;

    if(n.type === 'start') {
        const triggerVal = n.config?.trigger || 'manual';
        html += `<div class="form-group">
            <label>🎯 Sự kiện kích hoạt quy trình</label>
            <select class="form-control" id="wb-cfg-trigger" onchange="wbToggleTriggerExtras()">
                ${TRIGGER_EVENTS.map(t => `<option value="${t.value}" ${triggerVal===t.value?'selected':''}>${esc(t.label)}</option>`).join('')}
            </select>
        </div>
        <div class="form-group" id="wb-cfg-trigger-status-wrap" style="display:${triggerVal==='status_changed'?'block':'none'};">
            <label>Trạng thái đích (kích hoạt khi chuyển sang)</label>
            <select class="form-control" id="wb-cfg-trigger-status">
                <option value="">-- Chọn --</option>
                ${TASK_STATUSES.map(s => `<option value="${esc(s)}" ${n.config?.trigger_status===s?'selected':''}>${esc(s)}</option>`).join('')}
            </select>
        </div>
        <div style="border-top:1px dashed var(--border-color);margin:14px 0 10px;padding-top:10px;">
            <div style="font-size:0.78rem;font-weight:700;color:var(--text-secondary);margin-bottom:8px;">Lọc thêm điều kiện <small style="font-weight:400;color:var(--text-muted);">(tuỳ chọn)</small></div>
        </div>
        <div class="form-group">
            <label>Field (chỉ kích hoạt khi field thoả)</label>
            <select class="form-control" id="wb-cfg-filter-field">
                <option value="">— Không lọc thêm —</option>
                ${CONDITION_FIELDS.map(f => `<option value="${f.value}" ${n.config?.filter_field===f.value?'selected':''}>${esc(f.label)} (${f.value})</option>`).join('')}
            </select>
        </div>
        <div class="form-group">
            <label>Toán tử</label>
            <select class="form-control" id="wb-cfg-filter-op">
                ${CONDITION_OPS.map(o => `<option value="${o.value}" ${n.config?.filter_op===o.value?'selected':''}>${esc(o.label)}</option>`).join('')}
            </select>
        </div>
        <div class="form-group">
            <label>Giá trị</label>
            <input type="text" class="form-control" id="wb-cfg-filter-value" value="${esc(n.config?.filter_value||'')}" placeholder="Vd: Phát triển, Gấp...">
        </div>
        <div style="background:#cfe2ff;border:1px solid #084298;color:#084298;padding:8px 10px;font-size:0.78rem;margin-top:8px;">
            💡 Ví dụ: <strong>"Khi Dev được giao việc"</strong> + filter <code>task_type = "Phát triển"</code> → quy trình này chỉ tự chạy với YC phát triển khi có dev.
        </div>`;
    }
    if(n.type === 'task' || n.type === 'end') {
        html += `<div class="form-group">
            <label>Trạng thái sẽ chuyển sang</label>
            <select class="form-control" id="wb-cfg-status">
                <option value="">-- Chọn --</option>
                ${TASK_STATUSES.map(s => `<option value="${esc(s)}" ${n.config?.status===s?'selected':''}>${esc(s)}</option>`).join('')}
            </select>
        </div>`;
        if(n.type === 'task') {
            html += `<div class="form-group">
                <label>Vai trò người thực hiện</label>
                <select class="form-control" id="wb-cfg-assignee-role">
                    <option value="">-- Không gán --</option>
                    ${ROLES.map(r => `<option value="${r.value}" ${n.config?.assignee_role===r.value?'selected':''}>${r.label}</option>`).join('')}
                </select>
            </div>`;
        }
    }
    if(n.type === 'condition') {
        html += `<div class="form-group">
            <label>Field cần kiểm tra</label>
            <select class="form-control" id="wb-cfg-field">
                <option value="">-- Chọn --</option>
                ${CONDITION_FIELDS.map(f => `<option value="${f.value}" ${n.config?.field===f.value?'selected':''}>${esc(f.label)} (${f.value})</option>`).join('')}
            </select>
        </div>
        <div class="form-group">
            <label>Toán tử</label>
            <select class="form-control" id="wb-cfg-op">
                ${CONDITION_OPS.map(o => `<option value="${o.value}" ${n.config?.op===o.value?'selected':''}>${esc(o.label)}</option>`).join('')}
            </select>
        </div>
        <div class="form-group">
            <label>Giá trị so sánh</label>
            <input type="text" class="form-control" id="wb-cfg-value" value="${esc(n.config?.value||'')}" placeholder="Ví dụ: Phát triển, Gấp...">
        </div>
        <div style="background:#fff3cd;border:1px solid #ffc107;color:#856404;padding:8px 10px;font-size:0.78rem;margin-top:8px;">
            💡 Node này cần <strong>2 dây ra</strong>: nhánh Đúng (xanh) và nhánh Sai (đỏ). Kéo từ chấm phải lần 1 → nhánh Đúng, lần 2 → nhánh Sai.
        </div>`;
    }
    if(n.type === 'approval') {
        html += `<div class="form-group">
            <label>Vai trò phê duyệt</label>
            <select class="form-control" id="wb-cfg-approver-role">
                ${ROLES.map(r => `<option value="${r.value}" ${n.config?.approver_role===r.value?'selected':''}>${esc(r.label)}</option>`).join('')}
            </select>
        </div>`;
    }
    if(n.type === 'notify') {
        html += `<div class="form-group">
            <label>Gửi đến vai trò</label>
            <select class="form-control" id="wb-cfg-target-role">
                ${ROLES.map(r => `<option value="${r.value}" ${n.config?.target_role===r.value?'selected':''}>${esc(r.label)}</option>`).join('')}
            </select>
        </div>
        <div class="form-group">
            <label>Nội dung thông báo</label>
            <textarea class="form-control" id="wb-cfg-message" rows="3" placeholder="Vd: Có YC mới cần xử lý">${esc(n.config?.message||'')}</textarea>
        </div>`;
    }
    html += `<button class="btn btn-primary btn-sm" style="width:100%;margin-top:6px;" onclick="wbApplyConfig()">💾 Cập nhật node</button>`;
    html += `<button class="btn btn-outline btn-sm" style="width:100%;margin-top:6px;color:var(--danger-color);border-color:var(--danger-color);" onclick="wbDeleteNode('${n.id}')">🗑 Xoá node</button>`;
    panel.innerHTML = html;
}

function wbToggleTriggerExtras() {
    const trig = document.getElementById('wb-cfg-trigger')?.value;
    const wrap = document.getElementById('wb-cfg-trigger-status-wrap');
    if(wrap) wrap.style.display = (trig === 'status_changed') ? 'block' : 'none';
}

function wbApplyConfig() {
    const n = WB.nodes.find(x => x.id === WB.selectedNodeId);
    if(!n) return;
    n.label = document.getElementById('wb-cfg-label')?.value || n.label;
    n.config = n.config || {};
    if(n.type === 'start') {
        n.config.trigger = document.getElementById('wb-cfg-trigger')?.value || 'manual';
        if(n.config.trigger === 'status_changed') {
            n.config.trigger_status = document.getElementById('wb-cfg-trigger-status')?.value || '';
        } else {
            delete n.config.trigger_status;
        }
        n.config.filter_field = document.getElementById('wb-cfg-filter-field')?.value || '';
        n.config.filter_op    = document.getElementById('wb-cfg-filter-op')?.value || 'equals';
        n.config.filter_value = document.getElementById('wb-cfg-filter-value')?.value || '';
    }
    if(n.type === 'task' || n.type === 'end') {
        n.config.status = document.getElementById('wb-cfg-status')?.value || '';
        if(n.type === 'task') n.config.assignee_role = document.getElementById('wb-cfg-assignee-role')?.value || '';
    }
    if(n.type === 'condition') {
        n.config.field = document.getElementById('wb-cfg-field')?.value || '';
        n.config.op    = document.getElementById('wb-cfg-op')?.value || 'equals';
        n.config.value = document.getElementById('wb-cfg-value')?.value || '';
    }
    if(n.type === 'approval') n.config.approver_role = document.getElementById('wb-cfg-approver-role')?.value || 'lead';
    if(n.type === 'notify') {
        n.config.target_role = document.getElementById('wb-cfg-target-role')?.value || 'ba';
        n.config.message = document.getElementById('wb-cfg-message')?.value || '';
    }
    wbRenderCanvas();
}

// ─── Save / Load ───────────────────────────────────────────────────
function wbReadGeneralForm() {
    WB.code        = document.getElementById('wb-code')?.value.trim() || '';
    WB.name        = document.getElementById('wb-name')?.value.trim() || '';
    WB.group_name  = document.getElementById('wb-group')?.value.trim() || '';
    WB.description = document.getElementById('wb-desc')?.value.trim() || '';
    WB.status      = document.getElementById('wb-status')?.value || 'draft';
}
function wbWriteGeneralForm() {
    document.getElementById('wb-code').value  = WB.code;
    document.getElementById('wb-name').value  = WB.name;
    document.getElementById('wb-group').value = WB.group_name || '';
    document.getElementById('wb-desc').value  = WB.description || '';
    document.getElementById('wb-status').value = WB.status || 'draft';
}

function wbSaveWorkflow() {
    wbReadGeneralForm();
    if(!WB.code || !WB.name) { alert('Vui lòng nhập Mã và Tên quy trình'); return; }
    if(WB.nodes.length === 0) { alert('Quy trình cần ít nhất 1 node'); return; }

    const fd = new FormData();
    if(WB.workflowId) fd.append('id', WB.workflowId);
    fd.append('action', 'save_workflow');
    fd.append('code', WB.code);
    fd.append('name', WB.name);
    fd.append('group_name', WB.group_name);
    fd.append('description', WB.description);
    fd.append('status', WB.status);
    fd.append('definition', JSON.stringify({ nodes: WB.nodes, edges: WB.edges }));

    fetch(API, { method:'POST', body: fd }).then(r => r.json()).then(res => {
        if(res.success) {
            alert('Đã lưu quy trình "' + WB.name + '"');
            if(res.id) WB.workflowId = res.id;
            wbBackToList();
        } else {
            alert(res.message || 'Lỗi khi lưu');
        }
    });
}

function wbLoadWorkflow(id) {
    fetch(API + '?action=get_workflow&id=' + id).then(r => r.json()).then(w => {
        if(!w || w.error) { alert('Không tìm thấy quy trình'); return; }
        wbReset();
        WB.workflowId  = w.id;
        WB.code        = w.code;
        WB.name        = w.name;
        WB.group_name  = w.group_name || '';
        WB.description = w.description || '';
        WB.status      = w.status || 'draft';
        try {
            const def = JSON.parse(w.definition || '{}');
            WB.nodes = def.nodes || [];
            WB.edges = def.edges || [];
        } catch(e) { WB.nodes = []; WB.edges = []; }
        wbShowEditor();
    });
}

function wbNewWorkflow() {
    wbReset();
    WB.code = 'WF_' + Date.now().toString(36).toUpperCase();
    WB.name = 'Quy trình mới';
    WB.status = 'draft';
    // Tạo sẵn node Start để dễ bắt đầu
    wbAddNode('start', 40, 60);
    wbShowEditor();
}

function wbShowEditor() {
    document.getElementById('wf-list-view').style.display = 'none';
    document.getElementById('wf-editor-view').style.display = 'block';
    wbWriteGeneralForm();
    wbRenderCanvas();
    wbRenderConfigPanel();
    wbAttachCanvasHandlers();
}

function wbBackToList() {
    document.getElementById('wf-list-view').style.display = 'block';
    document.getElementById('wf-editor-view').style.display = 'none';
    wbLoadList();
}

function wbLoadList() {
    fetch(API + '?action=get_workflows').then(r => r.json()).then(rows => {
        const tbody = document.getElementById('wf-list-tbody');
        if(!rows || rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text-muted);">Chưa có quy trình nào. Bấm "+ Tạo quy trình mới" để bắt đầu.</td></tr>';
            return;
        }
        tbody.innerHTML = rows.map(w => {
            let nodeCount = 0, edgeCount = 0;
            try { const d = JSON.parse(w.definition||'{}'); nodeCount = (d.nodes||[]).length; edgeCount = (d.edges||[]).length; } catch(e) {}
            const statusCls = 'wf-list-status-' + w.status;
            const statusLabel = { active:'Hoạt động', inactive:'Tạm dừng', draft:'Bản nháp' }[w.status] || w.status;
            return `<tr>
                <td><strong style="color:var(--primary-color);">${esc(w.code)}</strong></td>
                <td><strong>${esc(w.name)}</strong>${w.description?`<br><small style="color:var(--text-muted);">${esc(w.description.substring(0,80))}${w.description.length>80?'...':''}</small>`:''}</td>
                <td>${esc(w.group_name||'-')}</td>
                <td>${nodeCount} node · ${edgeCount} kết nối</td>
                <td class="${statusCls}">${statusLabel}</td>
                <td><small>${esc(w.creator_name||'-')}<br><span style="color:var(--text-muted);">${w.updated_at?new Date(w.updated_at).toLocaleDateString('vi-VN'):''}</span></small></td>
                <td>
                    <div class="row-actions">
                        <button class="btn-outline btn-icon" onclick="wbLoadWorkflow(${w.id})" title="Sửa">✏</button>
                        <button class="btn-outline btn-icon" onclick="wbToggleStatus(${w.id}, '${w.status}')" title="${w.status==='active'?'Tạm dừng':'Kích hoạt'}">${w.status==='active'?'⏸':'▶'}</button>
                        <button class="btn-cancel-flow" onclick="wbDeleteWorkflow(${w.id}, '${esc(w.name)}')" title="Xoá">🗑</button>
                    </div>
                </td>
            </tr>`;
        }).join('');
    });
}

function wbDeleteWorkflow(id, name) {
    if(!confirm('Xoá quy trình "' + name + '"?')) return;
    const fd = new FormData(); fd.append('action','delete_workflow'); fd.append('id',id);
    fetch(API,{method:'POST',body:fd}).then(r=>r.json()).then(res => {
        if(res.success) wbLoadList();
        else alert(res.message || 'Lỗi khi xoá');
    });
}

function wbToggleStatus(id, currentStatus) {
    const next = currentStatus === 'active' ? 'inactive' : 'active';
    const fd = new FormData();
    fd.append('action','set_workflow_status');
    fd.append('id',id);
    fd.append('status',next);
    fetch(API,{method:'POST',body:fd}).then(r=>r.json()).then(res => {
        if(res.success) wbLoadList();
        else alert(res.message || 'Lỗi cập nhật trạng thái');
    });
}
