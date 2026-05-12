/* =====================================================================
   system-tree.js — Danh sách hệ thống + cây cấu trúc Module/Tính năng
   Depends on: API, esc(), closeModal()
   Used by: views/admin/lead.php, views/admin/ba.php
   ===================================================================== */

let _SYS = {
    currentSystemId: null,
    detail: null,           // { system, assignees, nodes, can_edit, is_lead }
    editingSysId: null,     // null = create
    editingNodeId: null,    // null = create
    pendingNodeParent: null,
    collapsed: new Set()
};

const NODE_TYPE_META = {
    module:  { label: 'Module',         icon: '📦' },
    feature: { label: 'Tính năng',      icon: '🧩' },
    logic:   { label: 'Logic',          icon: '⚙' },
    hidden:  { label: 'Tính năng ẩn',   icon: '🔒' }
};

// ========== LIST VIEW ===========
function sysBackToList() {
    document.getElementById('sys-list-view').style.display = 'block';
    document.getElementById('sys-detail-view').style.display = 'none';
    sysLoadList();
}

function sysLoadList() {
    fetch(API + '?action=get_systems').then(r => r.json()).then(rows => {
        const grid = document.getElementById('sys-card-grid');
        if(!Array.isArray(rows) || rows.length === 0) {
            grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;color:var(--text-muted);padding:30px;">'
                + (typeof SYS_IS_LEAD !== 'undefined' && SYS_IS_LEAD
                    ? 'Chưa có hệ thống nào. Bấm "+ Tạo hệ thống mới" để bắt đầu.'
                    : 'Bạn chưa được gán vào hệ thống nào.')
                + '</div>';
            return;
        }
        grid.innerHTML = rows.map(s => `
            <div class="sys-card" style="border-left-color:${esc(s.color || '#0d6efd')};" onclick="sysOpenDetail(${s.id})">
                <div class="sys-card-name">${esc(s.name)}</div>
                ${s.code ? `<div class="sys-card-code">${esc(s.code)}</div>` : ''}
                ${s.description ? `<div class="sys-card-desc">${esc(s.description.length > 100 ? s.description.substring(0,100) + '...' : s.description)}</div>` : ''}
                <div class="sys-card-meta">
                    <span>👥 ${s.ba_count || 0} BA</span>
                    <span>🌳 ${s.node_count || 0} node</span>
                    <span style="margin-left:auto;">${s.creator_name ? '· ' + esc(s.creator_name) : ''}</span>
                </div>
            </div>
        `).join('');
    });
}

// ========== DETAIL VIEW ===========
function sysOpenDetail(systemId) {
    _SYS.currentSystemId = systemId;
    document.getElementById('sys-list-view').style.display = 'none';
    document.getElementById('sys-detail-view').style.display = 'block';
    document.getElementById('sys-tree-root').innerHTML = '<div style="text-align:center;color:var(--text-muted);padding:30px;">Đang tải cây...</div>';

    fetch(API + '?action=get_system_detail&id=' + systemId).then(r => r.json()).then(res => {
        if(res.error) { alert('Không tìm thấy hệ thống'); sysBackToList(); return; }
        _SYS.detail = res;

        const s = res.system;
        document.getElementById('sys-detail-title').innerHTML =
            `<span style="color:${esc(s.color || '#0d6efd')};">●</span> ${esc(s.name)} ${s.code ? `<small style="color:var(--text-muted);">[${esc(s.code)}]</small>` : ''}`;
        document.getElementById('sys-detail-meta').innerHTML = `
            ${s.description ? `<div style="margin-bottom:6px;">${esc(s.description)}</div>` : ''}
            <strong>BA được gán:</strong>
            ${res.assignees.length ? res.assignees.map(u => `<span class="badge badge-pending" style="margin-right:4px;">${esc(u.full_name)}</span>`).join('') : '<em style="color:var(--text-muted);">(Chưa có ai)</em>'}`;

        // Action buttons visibility
        document.getElementById('sys-btn-edit').style.display = res.can_edit ? 'inline-flex' : 'none';
        document.getElementById('sys-btn-assignees').style.display = res.is_lead ? 'inline-flex' : 'none';
        document.getElementById('sys-btn-delete').style.display = res.is_lead ? 'inline-flex' : 'none';

        document.getElementById('sys-permission-hint').textContent = res.can_edit
            ? '✓ Bạn có quyền chỉnh sửa cấu trúc'
            : 'Chỉ xem (không có quyền sửa)';

        sysRenderTree();
    });
}

// =====================================================================
// MIND-MAP RENDERER (vertical chain layout với connectors cong)
// =====================================================================
function sysRenderTree() {
    const root = document.getElementById('sys-tree-root');
    const nodes = _SYS.detail?.nodes || [];
    const sys = _SYS.detail?.system;
    const canEdit = _SYS.detail?.can_edit;

    // Build adjacency: parent_id null = top-level
    const byParent = {};
    nodes.forEach(n => {
        const pid = n.parent_id || 'root';
        if(!byParent[pid]) byParent[pid] = [];
        byParent[pid].push(n);
    });

    const branches = byParent['root'] || [];

    // Render mind-map structure
    const mmHtml = `
        <div class="mm-canvas" id="mm-canvas">
            <!-- Root node (Hệ thống) -->
            <div class="mm-root-wrap">
                <div class="mm-node mm-node-system" data-node-id="root" style="border-color:${esc(sys?.color || '#dc3545')};">
                    <div class="mm-node-icon">🗂️</div>
                    <div class="mm-node-name">${esc(sys?.name || 'Hệ thống')}</div>
                </div>
                ${canEdit ? `<button class="mm-add-btn" onclick="sysOpenAddNode(null)" title="Thêm node gốc">+</button>` : ''}
            </div>

            <!-- Branches container (horizontal) -->
            <div class="mm-branches">
                ${branches.length ? branches.map(b => renderMindMapBranch(b, byParent, canEdit)).join('') : `
                    <div style="text-align:center;color:var(--text-muted);padding:20px;width:100%;">
                        ${canEdit ? '👆 Bấm <strong>+</strong> phía trên để thêm Module/Tính năng đầu tiên' : 'Chưa có cấu trúc nào'}
                    </div>`}
            </div>

            <!-- SVG layer cho connectors -->
            <svg class="mm-connectors" id="mm-connectors"></svg>
        </div>`;

    root.innerHTML = mmHtml;
    requestAnimationFrame(() => {
        drawMindMapConnectors();
        attachMindMapDrag();
    });
}

function renderMindMapBranch(node, byParent, canEdit) {
    return `<div class="mm-branch" data-branch-root="${node.id}">
        ${renderMindMapNode(node, byParent, canEdit, true)}
    </div>`;
}

function renderMindMapNode(n, byParent, canEdit, isBranchRoot = false) {
    const meta = NODE_TYPE_META[n.node_type] || NODE_TYPE_META.module;
    const childList = byParent[n.id] || [];
    const isCollapsed = _SYS.collapsed.has(n.id);
    const hasChildren = childList.length > 0;

    // Description tooltip
    const descAttr = n.description ? ` title="${esc(n.description)}"` : '';

    let html = `<div class="mm-node-wrap" data-node-id="${n.id}">
        <div class="mm-node mm-node-${esc(n.node_type)}"
             data-node-id="${n.id}"
             ${canEdit ? 'draggable="true"' : ''}
             ${descAttr}>
            <div class="mm-node-icon">${meta.icon}</div>
            <div class="mm-node-name">${esc(n.name)}</div>
            ${hasChildren ? `<button class="mm-collapse-btn" onclick="sysToggle(${n.id})" title="${isCollapsed ? 'Mở rộng' : 'Thu gọn'}">${isCollapsed ? '+' : '−'}</button>` : ''}
            ${canEdit ? `<div class="mm-node-actions">
                <button onclick="sysOpenAddNode(${n.id})" title="Thêm con">+</button>
                <button onclick="sysOpenEditNode(${n.id})" title="Sửa">✏</button>
                <button onclick="sysDeleteNode(${n.id}, '${esc(n.name).replace(/'/g, "\\'")}')" title="Xoá">🗑</button>
            </div>` : ''}
        </div>
        ${hasChildren && !isCollapsed ? `
            <div class="mm-children">
                ${childList.map(c => renderMindMapNode(c, byParent, canEdit, false)).join('')}
            </div>` : ''}
    </div>`;
    return html;
}

/**
 * Vẽ SVG connectors curve giữa parent và children dựa trên DOM positions.
 * Gọi sau khi DOM đã render. Cũng gọi lại khi resize.
 */
function drawMindMapConnectors() {
    const svg = document.getElementById('mm-connectors');
    const canvas = document.getElementById('mm-canvas');
    if(!svg || !canvas) return;

    const cRect = canvas.getBoundingClientRect();
    svg.setAttribute('width', cRect.width);
    svg.setAttribute('height', cRect.height);

    const segments = [];

    // 1) Từ root → mỗi branch (top-level)
    const rootNode = canvas.querySelector('.mm-node[data-node-id="root"]');
    const branches = canvas.querySelectorAll('.mm-branches > .mm-branch > .mm-node-wrap > .mm-node');
    if(rootNode) {
        const rootRect = rootNode.getBoundingClientRect();
        const x1 = rootRect.left + rootRect.width / 2 - cRect.left;
        const y1 = rootRect.bottom - cRect.top;
        branches.forEach(b => {
            const r = b.getBoundingClientRect();
            const x2 = r.left + r.width / 2 - cRect.left;
            const y2 = r.top - cRect.top;
            segments.push(buildBracketPath(x1, y1, x2, y2, 'down'));
        });
    }

    // 2) Từ mỗi parent → children (vertical chain)
    canvas.querySelectorAll('.mm-children').forEach(childrenContainer => {
        const parentWrap = childrenContainer.parentElement;
        const parentNode = parentWrap.querySelector(':scope > .mm-node');
        if(!parentNode) return;
        const pRect = parentNode.getBoundingClientRect();
        const px = pRect.left + 28 - cRect.left;
        const py = pRect.bottom - cRect.top;

        const children = childrenContainer.querySelectorAll(':scope > .mm-node-wrap > .mm-node');
        children.forEach(c => {
            const r = c.getBoundingClientRect();
            const cx = r.left - cRect.left;
            const cy = r.top + r.height / 2 - cRect.top;
            segments.push(`M ${px} ${py} C ${px} ${cy}, ${px} ${cy}, ${cx} ${cy}`);
        });
    });

    svg.innerHTML = segments.map(d => `<path d="${d}" fill="none" stroke="#dc3545" stroke-width="2" />`).join('');
}

function buildBracketPath(x1, y1, x2, y2, dir) {
    // Bracket: từ (x1,y1) đi xuống thẳng → bend → đi xuống tới (x2,y2)
    const midY = (y1 + y2) / 2;
    return `M ${x1} ${y1} C ${x1} ${midY}, ${x2} ${midY}, ${x2} ${y2}`;
}

// =====================================================================
// DRAG & DROP — kéo node để đổi parent
// =====================================================================
let _MM_DRAG_NODE_ID = null;

function attachMindMapDrag() {
    if(!_SYS.detail?.can_edit) return;
    const canvas = document.getElementById('mm-canvas');
    if(!canvas) return;

    canvas.querySelectorAll('.mm-node[draggable="true"]').forEach(el => {
        el.addEventListener('dragstart', (e) => {
            _MM_DRAG_NODE_ID = parseInt(el.dataset.nodeId);
            el.classList.add('mm-dragging');
            e.dataTransfer.effectAllowed = 'move';
            try { e.dataTransfer.setData('text/plain', String(_MM_DRAG_NODE_ID)); } catch(err) {}
        });
        el.addEventListener('dragend', () => {
            el.classList.remove('mm-dragging');
            canvas.querySelectorAll('.mm-drop-target').forEach(d => d.classList.remove('mm-drop-target'));
            _MM_DRAG_NODE_ID = null;
        });
    });

    // Mọi node (kể cả root) có thể là drop target
    canvas.querySelectorAll('.mm-node').forEach(target => {
        target.addEventListener('dragover', (e) => {
            if(_MM_DRAG_NODE_ID === null) return;
            const targetId = target.dataset.nodeId;
            const draggingId = String(_MM_DRAG_NODE_ID);
            if(targetId === draggingId) return; // không thả vào chính nó
            if(isDescendant(_MM_DRAG_NODE_ID, parseInt(targetId))) return; // không thả vào con của chính nó
            e.preventDefault();
            target.classList.add('mm-drop-target');
        });
        target.addEventListener('dragleave', () => {
            target.classList.remove('mm-drop-target');
        });
        target.addEventListener('drop', (e) => {
            e.preventDefault();
            target.classList.remove('mm-drop-target');
            if(_MM_DRAG_NODE_ID === null) return;
            const newParentId = target.dataset.nodeId === 'root' ? null : parseInt(target.dataset.nodeId);
            sysReparentNode(_MM_DRAG_NODE_ID, newParentId);
        });
    });
}

function isDescendant(ancestorId, candidateId) {
    // candidateId có phải là descendant (con cháu) của ancestorId không?
    const nodes = _SYS.detail?.nodes || [];
    const byId = {};
    nodes.forEach(n => byId[n.id] = n);
    let cur = byId[candidateId];
    while(cur) {
        if(cur.parent_id == ancestorId) return true;
        if(!cur.parent_id) return false;
        cur = byId[cur.parent_id];
    }
    return false;
}

function sysReparentNode(nodeId, newParentId) {
    const fd = new FormData();
    fd.append('action', 'reparent_system_node');
    fd.append('id', nodeId);
    if(newParentId !== null) fd.append('parent_id', newParentId);
    fetch(API, { method:'POST', body:fd }).then(r => r.json()).then(res => {
        if(res.success) sysOpenDetail(_SYS.currentSystemId);
        else alert(res.message || 'Lỗi khi đổi cha');
    });
}

window.addEventListener('resize', () => {
    if(document.getElementById('mm-canvas')) drawMindMapConnectors();
});

function sysToggle(nodeId) {
    if(_SYS.collapsed.has(nodeId)) _SYS.collapsed.delete(nodeId);
    else _SYS.collapsed.add(nodeId);
    sysRenderTree();
}

// ========== CREATE / EDIT SYSTEM (Lead) ===========
function sysOpenCreate() {
    _SYS.editingSysId = null;
    document.getElementById('sys-modal-title').textContent = 'Tạo hệ thống mới';
    document.getElementById('sys-name').value = '';
    document.getElementById('sys-code').value = '';
    document.getElementById('sys-color').value = '#0d6efd';
    document.getElementById('sys-desc').value = '';
    document.getElementById('sysModal').style.display = 'block';
}
function sysOpenEdit() {
    const s = _SYS.detail?.system; if(!s) return;
    _SYS.editingSysId = s.id;
    document.getElementById('sys-modal-title').textContent = 'Sửa: ' + s.name;
    document.getElementById('sys-name').value = s.name;
    document.getElementById('sys-code').value = s.code || '';
    document.getElementById('sys-color').value = s.color || '#0d6efd';
    document.getElementById('sys-desc').value = s.description || '';
    document.getElementById('sysModal').style.display = 'block';
}
function sysSubmit() {
    const name = document.getElementById('sys-name').value.trim();
    if(!name) { alert('Cần nhập tên hệ thống'); return; }
    const fd = new FormData();
    fd.append('action', _SYS.editingSysId ? 'update_system' : 'create_system');
    if(_SYS.editingSysId) fd.append('id', _SYS.editingSysId);
    fd.append('name', name);
    fd.append('code', document.getElementById('sys-code').value.trim());
    fd.append('color', document.getElementById('sys-color').value);
    fd.append('description', document.getElementById('sys-desc').value.trim());
    fetch(API, { method:'POST', body:fd }).then(r => r.json()).then(res => {
        if(res.success) {
            closeModal('sysModal');
            if(_SYS.editingSysId) sysOpenDetail(_SYS.editingSysId);
            else sysBackToList();
        } else alert(res.message || 'Lỗi khi lưu');
    });
}
function sysDeleteCurrent() {
    const s = _SYS.detail?.system; if(!s) return;
    if(!confirm(`Xoá hệ thống "${s.name}" và toàn bộ cây cấu trúc bên trong?`)) return;
    const fd = new FormData(); fd.append('action','delete_system'); fd.append('id', s.id);
    fetch(API,{method:'POST',body:fd}).then(r=>r.json()).then(res => {
        if(res.success) sysBackToList();
        else alert(res.message || 'Lỗi khi xoá');
    });
}

// ========== ASSIGNEES (Lead) ===========
function sysOpenAssignees() {
    fetch(API + '?action=get_users').then(r => r.json()).then(users => {
        const eligible = users.filter(u => ['ba','lead','dev'].includes(u.role));
        const assigned = new Set((_SYS.detail?.assignees || []).map(a => a.id));
        // Group by role để dễ chọn
        const groupOrder = [['lead','Lead BA'], ['ba','BA'], ['dev','Developer']];
        let html = '';
        groupOrder.forEach(([role, label]) => {
            const list = eligible.filter(u => u.role === role);
            if(!list.length) return;
            html += `<div style="font-size:0.74rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.4px;padding:8px 8px 4px;background:#f8f9fa;">${label} (${list.length})</div>`;
            html += list.map(u => `<label style="display:flex;align-items:center;gap:8px;padding:8px;border-bottom:1px solid var(--border-light);cursor:pointer;">
                <input type="checkbox" value="${u.id}" ${assigned.has(u.id) ? 'checked' : ''} style="width:18px;height:18px;">
                <strong>${esc(u.full_name)}</strong>
                <small style="color:var(--text-muted);margin-left:auto;">${esc(u.username)}</small>
            </label>`).join('');
        });
        document.getElementById('sys-assignees-list').innerHTML = html
            || '<div style="text-align:center;color:var(--text-muted);padding:20px;">Chưa có nhân sự nào trong hệ thống.</div>';
        document.getElementById('sysAssigneesModal').style.display = 'block';
    });
}
function sysSubmitAssignees() {
    const ids = Array.from(document.querySelectorAll('#sys-assignees-list input[type=checkbox]:checked')).map(i => parseInt(i.value));
    const fd = new FormData();
    fd.append('action','set_system_assignees');
    fd.append('system_id', _SYS.currentSystemId);
    fd.append('user_ids', JSON.stringify(ids));
    fetch(API,{method:'POST',body:fd}).then(r=>r.json()).then(res => {
        if(res.success) { closeModal('sysAssigneesModal'); sysOpenDetail(_SYS.currentSystemId); }
        else alert(res.message || 'Lỗi khi gán');
    });
}

// ========== ADD/EDIT NODE ===========
function sysOpenAddNode(parentId) {
    if(!_SYS.detail?.can_edit) { alert('Bạn không có quyền chỉnh sửa hệ thống này'); return; }
    _SYS.editingNodeId = null;
    _SYS.pendingNodeParent = parentId;

    let parentLabel = 'gốc (cấp 1)';
    if(parentId) {
        const p = (_SYS.detail.nodes || []).find(n => n.id == parentId);
        if(p) parentLabel = `dưới "${p.name}"`;
    }
    document.getElementById('sys-node-modal-title').textContent = 'Thêm node ' + parentLabel;
    document.getElementById('sys-node-name').value = '';
    document.getElementById('sys-node-desc').value = '';
    // Mặc định: nếu add ở gốc → module; nếu add con → feature
    document.getElementById('sys-node-type').value = parentId ? 'feature' : 'module';
    document.getElementById('sysNodeModal').style.display = 'block';
}
function sysOpenEditNode(nodeId) {
    if(!_SYS.detail?.can_edit) { alert('Bạn không có quyền'); return; }
    const n = (_SYS.detail.nodes || []).find(x => x.id == nodeId);
    if(!n) return;
    _SYS.editingNodeId = nodeId;
    document.getElementById('sys-node-modal-title').textContent = 'Sửa node: ' + n.name;
    document.getElementById('sys-node-name').value = n.name;
    document.getElementById('sys-node-desc').value = n.description || '';
    document.getElementById('sys-node-type').value = n.node_type;
    document.getElementById('sysNodeModal').style.display = 'block';
}
function sysSubmitNode() {
    const name = document.getElementById('sys-node-name').value.trim();
    if(!name) { alert('Cần nhập tên'); return; }
    const fd = new FormData();
    if(_SYS.editingNodeId) {
        fd.append('action', 'update_system_node');
        fd.append('id', _SYS.editingNodeId);
    } else {
        fd.append('action', 'create_system_node');
        fd.append('system_id', _SYS.currentSystemId);
        if(_SYS.pendingNodeParent) fd.append('parent_id', _SYS.pendingNodeParent);
    }
    fd.append('name', name);
    fd.append('node_type', document.getElementById('sys-node-type').value);
    fd.append('description', document.getElementById('sys-node-desc').value.trim());
    fetch(API,{method:'POST',body:fd}).then(r=>r.json()).then(res => {
        if(res.success) { closeModal('sysNodeModal'); sysOpenDetail(_SYS.currentSystemId); }
        else alert(res.message || 'Lỗi khi lưu node');
    });
}
function sysDeleteNode(nodeId, name) {
    if(!_SYS.detail?.can_edit) { alert('Bạn không có quyền'); return; }
    if(!confirm(`Xoá node "${name}" và toàn bộ node con?`)) return;
    const fd = new FormData();
    fd.append('action', 'delete_system_node');
    fd.append('id', nodeId);
    fetch(API,{method:'POST',body:fd}).then(r=>r.json()).then(res => {
        if(res.success) sysOpenDetail(_SYS.currentSystemId);
        else alert(res.message || 'Lỗi khi xoá');
    });
}

// Helper esc nếu chưa có
if(typeof esc === 'undefined') {
    function esc(s) { if(s===null||s===undefined) return ''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
}
