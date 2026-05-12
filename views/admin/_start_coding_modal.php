<?php
/**
 * Partial: Modal "Bắt đầu code" — chọn Dev + mô tả BA + 4-cấp module/tính năng/logic/ẩn
 * Dùng chung BA + Lead.
 *
 * Modes (theo task_type):
 *  - Nâng cấp hệ thống     → 4 dropdown + nút "+ Thêm mới" cạnh mỗi cấp (tạo node mới)
 *  - Fix lỗi hệ thống     → 4 dropdown chỉ chọn từ cây có sẵn (warn nếu cây trống)
 *  - Thay đổi dữ liệu      → chỉ ghi chú
 *  - Lấy dữ liệu           → chỉ ghi chú
 *
 * JS: assets/js/start-coding-modal.js
 */
?>
<div id="startCodingModal" class="modal">
    <div class="modal-content" style="max-width:640px;max-height:90vh;overflow-y:auto;">
        <span class="close" onclick="closeModal('startCodingModal')">&times;</span>
        <h3 id="sc-title" style="margin-bottom:4px;"></h3>
        <div id="sc-meta" style="color:var(--text-muted);font-size:0.82rem;margin-bottom:18px;border-bottom:1px solid var(--border-color);padding-bottom:12px;"></div>

        <!-- Đơn vị thực hiện (auto-map từ Lead's assignment, read-only) -->
        <div style="display:flex;gap:10px;align-items:center;margin-bottom:14px;font-size:0.88rem;">
            <span style="color:var(--text-muted);">Đơn vị thực hiện:</span>
            <span id="sc-unit-badge" style="font-weight:700;padding:4px 12px;background:#f0f4ff;border:1px solid #cfe2ff;color:#084298;">—</span>
            <small style="color:var(--text-muted);">(do Lead/BA đặt khi tiếp nhận YC, nhóm Dev sẽ filter theo đơn vị này)</small>
        </div>

        <!-- Yêu cầu gốc -->
        <div style="background:#f8f9fa;border-left:4px solid var(--border-color);padding:12px 16px;margin-bottom:18px;">
            <div style="font-size:0.72rem;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">Yêu cầu gốc</div>
            <div id="sc-req-desc" style="font-size:0.88rem;color:var(--text-secondary);line-height:1.6;max-height:100px;overflow-y:auto;white-space:pre-wrap;"></div>
        </div>

        <!-- Hệ thống (BA có thể đổi nếu form ghi sai) -->
        <div class="form-group">
            <label>🗂️ Hệ thống <span style="color:var(--text-muted);font-weight:400;">(BA có thể chỉnh sửa nếu form yêu cầu chọn sai)</span></label>
            <select id="sc-system" class="form-control" onchange="scOnSystemChange()">
                <option value="">-- Chưa chọn --</option>
            </select>
        </div>

        <!-- Module / Feature / Logic / Hidden box (4 cấp) — theo task_type -->
        <div id="sc-mf-box" style="display:none;background:#f0f4ff;border:1px solid #cfe2ff;padding:14px;margin-bottom:14px;">
            <div id="sc-mf-title" style="font-size:0.78rem;font-weight:700;color:#084298;margin-bottom:10px;text-transform:uppercase;letter-spacing:.4px;"></div>
            <div id="sc-mf-warn" style="display:none;background:#fff3cd;border:1px solid #ffc107;color:#856404;padding:8px 12px;font-size:0.84rem;margin-bottom:10px;"></div>

            <div id="sc-level-1" class="sc-level-row">
                <label class="sc-level-lbl">① Module <small id="sc-l1-add-hint" style="color:var(--text-muted);font-weight:400;display:none;">(có thể + thêm mới)</small></label>
                <div class="sc-level-input">
                    <select id="sc-mod-sel" class="form-control" onchange="scOnLevelChange(1)"><option value="">-- Chọn Module --</option></select>
                    <button type="button" class="sc-add-btn" onclick="scToggleAdd(1)" title="Thêm Module mới">+</button>
                </div>
                <div id="sc-mod-new" class="sc-new-input" style="display:none;">
                    <input type="text" id="sc-new-module" class="form-control" placeholder="Tên Module mới (vd: Quản lý đơn hàng)">
                </div>
            </div>

            <div id="sc-level-2" class="sc-level-row">
                <label class="sc-level-lbl">② Tính năng <small id="sc-l2-add-hint" style="color:var(--text-muted);font-weight:400;display:none;">(có thể + thêm mới)</small></label>
                <div class="sc-level-input">
                    <select id="sc-feat-sel" class="form-control" onchange="scOnLevelChange(2)"><option value="">-- Chọn Tính năng --</option></select>
                    <button type="button" class="sc-add-btn" onclick="scToggleAdd(2)" title="Thêm Tính năng mới">+</button>
                </div>
                <div id="sc-feat-new" class="sc-new-input" style="display:none;">
                    <input type="text" id="sc-new-feature" class="form-control" placeholder="Tên Tính năng mới (vd: Lọc theo trạng thái)">
                </div>
            </div>

            <div id="sc-level-3" class="sc-level-row">
                <label class="sc-level-lbl">③ Logic <small id="sc-l3-add-hint" style="color:var(--text-muted);font-weight:400;display:none;">(có thể + thêm mới)</small></label>
                <div class="sc-level-input">
                    <select id="sc-logic-sel" class="form-control" onchange="scOnLevelChange(3)"><option value="">-- Không chọn --</option></select>
                    <button type="button" class="sc-add-btn" onclick="scToggleAdd(3)" title="Thêm Logic mới">+</button>
                </div>
                <div id="sc-logic-new" class="sc-new-input" style="display:none;">
                    <input type="text" id="sc-new-logic" class="form-control" placeholder="Tên Logic mới (vd: Tính phí ship theo zone)">
                </div>
            </div>

            <div id="sc-level-4" class="sc-level-row">
                <label class="sc-level-lbl">④ Tính năng ẩn <small id="sc-l4-add-hint" style="color:var(--text-muted);font-weight:400;display:none;">(có thể + thêm mới)</small></label>
                <div class="sc-level-input">
                    <select id="sc-hidden-sel" class="form-control"><option value="">-- Không chọn --</option></select>
                    <button type="button" class="sc-add-btn" onclick="scToggleAdd(4)" title="Thêm Tính năng ẩn mới">+</button>
                </div>
                <div id="sc-hidden-new" class="sc-new-input" style="display:none;">
                    <input type="text" id="sc-new-hidden" class="form-control" placeholder="Tên Tính năng ẩn (config nội bộ, schedule, v.v.)">
                </div>
            </div>
        </div>

        <!-- Note box cho Thay đổi DL / Lấy DL -->
        <div id="sc-note-box" style="display:none;background:#fff8e1;border:1px solid #ffd54f;padding:12px;margin-bottom:14px;">
            <div style="font-size:0.78rem;font-weight:700;color:#856404;margin-bottom:6px;text-transform:uppercase;letter-spacing:.4px;">📝 Loại YC này không liên kết module/tính năng</div>
            <small style="color:#856404;">Vẫn có thể ghi chú thêm chi tiết kỹ thuật ở ô "Mô tả kỹ thuật cho Dev" bên dưới.</small>
        </div>

        <!-- Chọn Nhóm + Dev -->
        <div class="form-group" style="display:grid;grid-template-columns:1fr 2fr;gap:10px;">
            <div>
                <label>Lọc nhóm</label>
                <select id="sc-group" class="form-control" onchange="scReloadDevList()">
                    <option value="">-- Tất cả --</option>
                </select>
            </div>
            <div>
                <label>Giao cho Developer <span style="color:var(--text-muted);font-weight:400;">(tùy chọn)</span></label>
                <select id="sc-dev" class="form-control">
                    <option value="">-- Chưa gán dev --</option>
                </select>
            </div>
        </div>

        <!-- Mô tả kỹ thuật -->
        <div class="form-group">
            <label>Mô tả kỹ thuật cho Dev <span style="color:var(--text-muted);font-weight:400;">(nếu có gán dev)</span></label>
            <textarea id="sc-ba-desc" class="form-control" rows="4"
                placeholder="Mô tả yêu cầu kỹ thuật: API cần làm, màn hình thay đổi, logic xử lý, edge cases..."></textarea>
        </div>

        <!-- Khoảng ngày Dev làm việc (kế hoạch) -->
        <div class="form-group">
            <label>📅 Khoảng ngày Dev làm việc (kế hoạch) <span style="color:var(--text-muted);font-weight:400;">(tuỳ chọn)</span></label>
            <div style="display:flex;gap:10px;align-items:center;">
                <input type="date" id="sc-dev-start" class="form-control" style="flex:1;">
                <span style="color:var(--text-muted);">→</span>
                <input type="date" id="sc-dev-end" class="form-control" style="flex:1;">
            </div>
            <small style="color:var(--text-muted);">Dev sẽ thấy khoảng ngày này trên sheet. Khi Dev đổi trạng thái sang "đang làm" / "hoàn thành", hệ thống tự ghi giờ thực tế.</small>
        </div>

        <button class="btn btn-primary" style="width:100%;padding:12px;font-size:0.95rem;" onclick="submitStartCoding()">
            ▶ Xác nhận Bắt đầu code &amp; Phân công Dev
        </button>
    </div>
</div>
