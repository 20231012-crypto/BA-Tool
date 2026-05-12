<?php
/**
 * Partial: section "Danh sách hệ thống" — dùng chung lead.php + ba.php
 * Yêu cầu: $_SESSION['role']
 */
$_role = $_SESSION['role'] ?? 'ba';
?>
<section id="section-systems" class="page-section" style="display:none;">
    <!-- View 1: Danh sách hệ thống -->
    <div id="sys-list-view">
        <div class="card">
            <div class="section-title">
                <span>Danh sách hệ thống</span>
                <?php if($_role === 'lead'): ?>
                <button class="btn btn-primary btn-sm" onclick="sysOpenCreate()">+ Tạo hệ thống mới</button>
                <?php endif; ?>
            </div>
            <p style="color:var(--text-muted);font-size:0.85rem;margin:6px 0 14px;">
                <?php if($_role === 'lead'): ?>
                Lead có thể tạo hệ thống mới và gán BA vào để quản lý cấu trúc Module → Tính năng → Logic → Tính năng ẩn.
                <?php else: ?>
                Bạn có thể chỉnh sửa các hệ thống được Lead gán cho mình. Cấu trúc cây: Module → Tính năng → Logic → Tính năng ẩn.
                <?php endif; ?>
            </p>
            <div id="sys-card-grid" class="sys-card-grid">
                <div style="grid-column:1/-1;text-align:center;color:var(--text-muted);padding:30px;">Đang tải...</div>
            </div>
        </div>
    </div>

    <!-- View 2: Chi tiết hệ thống + cây node -->
    <div id="sys-detail-view" style="display:none;">
        <div class="card">
            <div class="section-title">
                <span>
                    <button class="btn btn-outline btn-sm" onclick="sysBackToList()" style="margin-right:8px;">← Danh sách</button>
                    <span id="sys-detail-title">Chi tiết hệ thống</span>
                </span>
                <div style="display:flex;gap:6px;">
                    <button class="btn btn-outline btn-sm" id="sys-btn-edit" onclick="sysOpenEdit()" style="display:none;">✏ Sửa thông tin</button>
                    <button class="btn btn-outline btn-sm" id="sys-btn-assignees" onclick="sysOpenAssignees()" style="display:none;">👥 Gán BA</button>
                    <button class="btn-cancel-flow btn-sm" id="sys-btn-delete" onclick="sysDeleteCurrent()" style="display:none;">🗑 Xoá</button>
                </div>
            </div>
            <div id="sys-detail-meta" style="padding:10px 14px;background:#fafafa;border:1px solid var(--border-light);margin-bottom:14px;font-size:0.86rem;"></div>

            <div class="sys-tree-toolbar">
                <strong>Cấu trúc:</strong>
                <span style="color:var(--text-muted);font-size:0.84rem;">
                    📦 Module — 🧩 Tính năng — ⚙ Logic — 🔒 Tính năng ẩn
                </span>
                <span style="margin-left:auto;font-size:0.82rem;color:var(--text-muted);" id="sys-permission-hint"></span>
            </div>

            <div class="sys-tree" id="sys-tree-root">
                <div style="text-align:center;color:var(--text-muted);padding:30px;">Đang tải cây...</div>
            </div>
        </div>
    </div>
</section>

<!-- Modal: Create / Edit System (Lead) -->
<div id="sysModal" class="modal">
    <div class="modal-content" style="max-width:540px;">
        <span class="close" onclick="closeModal('sysModal')">&times;</span>
        <h3 id="sys-modal-title">Tạo hệ thống</h3>
        <div class="form-group">
            <label>Tên hệ thống <span style="color:var(--danger-color);">*</span></label>
            <input type="text" id="sys-name" class="form-control" placeholder="Ví dụ: Hệ thống vận chuyển KinKin">
        </div>
        <div style="display:grid;grid-template-columns:2fr 1fr;gap:10px;">
            <div class="form-group">
                <label>Code (định danh ngắn)</label>
                <input type="text" id="sys-code" class="form-control" placeholder="vd: VC_KK">
            </div>
            <div class="form-group">
                <label>Màu</label>
                <input type="color" id="sys-color" class="form-control" value="#0d6efd" style="height:38px;padding:2px;">
            </div>
        </div>
        <div class="form-group">
            <label>Mô tả</label>
            <textarea id="sys-desc" class="form-control" rows="3" placeholder="Mô tả ngắn gọn về hệ thống"></textarea>
        </div>
        <button class="btn btn-primary" style="width:100%;" onclick="sysSubmit()">💾 Lưu</button>
    </div>
</div>

<!-- Modal: Gán BA (Lead) -->
<div id="sysAssigneesModal" class="modal">
    <div class="modal-content" style="max-width:520px;">
        <span class="close" onclick="closeModal('sysAssigneesModal')">&times;</span>
        <h3>Gán BA vào hệ thống</h3>
        <p style="color:var(--text-muted);font-size:0.85rem;">Tích vào BA muốn gán quyền chỉnh sửa cấu trúc hệ thống này.</p>
        <div id="sys-assignees-list" style="max-height:360px;overflow-y:auto;border:1px solid var(--border-color);padding:10px;"></div>
        <button class="btn btn-primary" style="width:100%;margin-top:10px;" onclick="sysSubmitAssignees()">💾 Lưu danh sách</button>
    </div>
</div>

<!-- Modal: Add/Edit Node -->
<div id="sysNodeModal" class="modal">
    <div class="modal-content" style="max-width:520px;">
        <span class="close" onclick="closeModal('sysNodeModal')">&times;</span>
        <h3 id="sys-node-modal-title">Thêm node</h3>
        <div class="form-group">
            <label>Loại node</label>
            <select id="sys-node-type" class="form-control">
                <option value="module">📦 Module</option>
                <option value="feature">🧩 Tính năng</option>
                <option value="logic">⚙ Logic</option>
                <option value="hidden">🔒 Tính năng ẩn</option>
            </select>
        </div>
        <div class="form-group">
            <label>Tên <span style="color:var(--danger-color);">*</span></label>
            <input type="text" id="sys-node-name" class="form-control">
        </div>
        <div class="form-group">
            <label>Mô tả / Ghi chú</label>
            <textarea id="sys-node-desc" class="form-control" rows="4" placeholder="Mô tả chi tiết logic, edge cases, lưu ý..."></textarea>
        </div>
        <button class="btn btn-primary" style="width:100%;" onclick="sysSubmitNode()">💾 Lưu node</button>
    </div>
</div>
