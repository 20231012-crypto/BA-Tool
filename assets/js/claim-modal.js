/* =====================================================================
   claim-modal.js — Modal "Tiếp nhận YC" với select Đơn vị thực hiện
   Dùng chung cho BA + Lead. Gọi: openClaimModal(taskId)
   Dependencies: API, esc(), loadTasks(), loadNotifBell()
   ===================================================================== */

let _CLAIM_TASK_ID = null;

function openClaimModal(taskId) {
    _CLAIM_TASK_ID = taskId;
    let modal = document.getElementById('claimModal');
    if(!modal) {
        modal = document.createElement('div');
        modal.id = 'claimModal';
        modal.className = 'modal';
        modal.innerHTML = `
            <div class="modal-content" style="max-width:460px;">
                <span class="close" onclick="closeModal('claimModal')">&times;</span>
                <h3 style="margin-bottom:6px;">→ Tiếp nhận yêu cầu</h3>
                <div id="claim-meta" style="color:var(--text-muted);font-size:0.84rem;margin-bottom:18px;border-bottom:1px solid var(--border-color);padding-bottom:12px;"></div>

                <div class="form-group">
                    <label>Đơn vị thực hiện <span style="color:var(--danger-color);">*</span></label>
                    <select id="claim-unit" class="form-control">
                        <option value="DION">DION</option>
                        <option value="Kinkin">Kinkin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Mức độ ưu tiên (BA đánh giá)</label>
                    <select id="claim-priority" class="form-control">
                        <option value="4. Gấp - Quan trọng">4. Gấp - Quan trọng</option>
                        <option value="3. Không gấp - Quan trọng">3. Không gấp - Quan trọng</option>
                        <option value="2. Gấp - Không quan trọng">2. Gấp - Không quan trọng</option>
                        <option value="1. Không gấp - Không quan trọng" selected>1. Không gấp - Không quan trọng</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Phân loại yêu cầu</label>
                    <select id="claim-classification" class="form-control">
                        <option value="">-- Chưa phân loại --</option>
                        <option value="Hệ thống - Thực hiện">Hệ thống - Thực hiện</option>
                        <option value="Hệ thống - Tham khảo">Hệ thống - Tham khảo</option>
                        <option value="Hỗ trợ user">Hỗ trợ user</option>
                        <option value="Khác">Khác</option>
                    </select>
                </div>
                <button class="btn btn-primary" style="width:100%;padding:12px;font-size:0.95rem;" onclick="submitClaim()">
                    ✓ Xác nhận tiếp nhận
                </button>
            </div>`;
        document.body.appendChild(modal);
    }
    document.getElementById('claim-meta').textContent = 'Đang tải...';
    document.getElementById('claimModal').style.display = 'block';

    fetch(API + '?action=get_task_detail&task_id=' + taskId).then(r => r.json()).then(t => {
        if(!t || t.error) { closeModal('claimModal'); alert('Không tìm thấy task'); return; }
        document.getElementById('claim-meta').innerHTML =
            `<strong style="color:var(--primary-color);">${esc(t.ma_yc || '#'+t.id)}</strong> — ${esc(t.system_name || '')}<br>
             Người YC: ${esc(t.requester_name || '-')} · ${esc(t.requester_dept || '-')} · ${esc(t.task_type || '-')}`;
        // Pre-fill nếu task đã có sẵn priority/unit/classification
        if(t.priority_ba) document.getElementById('claim-priority').value = t.priority_ba;
        if(t.implementing_unit) document.getElementById('claim-unit').value = t.implementing_unit;
        if(t.classification) document.getElementById('claim-classification').value = t.classification;
    });
}

function submitClaim() {
    const unit = document.getElementById('claim-unit').value;
    const priority = document.getElementById('claim-priority').value;
    const classification = document.getElementById('claim-classification').value;
    if(!unit) { alert('Vui lòng chọn Đơn vị thực hiện'); return; }

    const btn = document.querySelector('#claimModal .btn-primary');
    btn.disabled = true;

    // Bước 1: lưu unit + priority + classification (qua update_task_meta)
    const fdMeta = new FormData();
    fdMeta.append('action', 'claim_task_meta');
    fdMeta.append('task_id', _CLAIM_TASK_ID);
    fdMeta.append('implementing_unit', unit);
    fdMeta.append('priority_ba', priority);
    fdMeta.append('classification', classification);

    fetch(API, { method:'POST', body:fdMeta }).then(r => r.json()).then(res => {
        if(!res.success) { btn.disabled = false; alert(res.message || 'Lỗi cập nhật metadata'); return; }
        // Bước 2: gọi next_step để claim + advance về Todo
        const fdStep = new FormData();
        fdStep.append('action', 'next_step');
        fdStep.append('task_id', _CLAIM_TASK_ID);
        fdStep.append('direction', 'next');
        fetch(API, { method:'POST', body:fdStep }).then(r => r.json()).then(res => {
            btn.disabled = false;
            if(res.success) {
                closeModal('claimModal');
                loadTasks();
                if(typeof loadNotifBell === 'function') loadNotifBell();
            } else alert(res.message || 'Có lỗi khi tiếp nhận');
        });
    });
}
