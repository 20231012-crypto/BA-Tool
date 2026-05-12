/* =====================================================================
   bot-sync.js — Cấu hình + trigger Google Sheets sync (Lead only)
   Used by: views/admin/lead.php (section "Đồng bộ Sheet")
   ===================================================================== */

function bsLoadSettings() {
    fetch(API + '?action=get_bot_settings').then(r => r.json()).then(cfg => {
        if(cfg.error) { document.getElementById('bs-status').innerHTML = '<div style="color:var(--danger-color);">Không có quyền truy cập.</div>'; return; }

        document.getElementById('bs-sheet-url').value = cfg.sheet_url || '';
        document.getElementById('bs-bot-email').value = cfg.bot_email || cfg.credentials_email || '';
        document.getElementById('bs-hour').value = cfg.schedule_hour ?? 23;
        document.getElementById('bs-minute').value = cfg.schedule_minute ?? 0;
        document.getElementById('bs-enabled').checked = parseInt(cfg.enabled) === 1;

        // Status box
        const statusBox = document.getElementById('bs-status');
        const fileOk = parseInt(cfg.credentials_exists) === 1;
        const lastSync = cfg.last_sync_at;
        const lastStatus = cfg.last_sync_status;
        const lastError = cfg.last_sync_error;

        let statusHtml = '<div class="bs-status-grid">';
        statusHtml += `<div class="bs-stat-cell ${fileOk?'ok':'err'}">
            <div class="bs-stat-label">Credentials</div>
            <div class="bs-stat-value">${fileOk ? '✓ Đã có' : '✗ Chưa có'}</div>
            ${cfg.credentials_email ? `<small>${esc(cfg.credentials_email)}</small>` : ''}
        </div>`;
        statusHtml += `<div class="bs-stat-cell ${parseInt(cfg.enabled)?'ok':''}">
            <div class="bs-stat-label">Trạng thái bot</div>
            <div class="bs-stat-value">${parseInt(cfg.enabled) ? '✓ Đang bật' : '⏸ Đang tắt'}</div>
        </div>`;
        statusHtml += `<div class="bs-stat-cell">
            <div class="bs-stat-label">Giờ chạy auto</div>
            <div class="bs-stat-value">${String(cfg.schedule_hour ?? 23).padStart(2,'0')}:${String(cfg.schedule_minute ?? 0).padStart(2,'0')}</div>
        </div>`;
        const syncCls = lastStatus === 'success' ? 'ok' : (lastStatus === 'failed' ? 'err' : '');
        statusHtml += `<div class="bs-stat-cell ${syncCls}">
            <div class="bs-stat-label">Lần đồng bộ gần nhất</div>
            <div class="bs-stat-value">${lastSync ? new Date(lastSync.replace(' ','T')).toLocaleString('vi-VN') : 'Chưa từng'}</div>
            ${lastStatus ? `<small>${lastStatus === 'success' ? '✓ Thành công' : '✗ Thất bại'}</small>` : ''}
            ${lastError ? `<small style="color:var(--danger-color);display:block;margin-top:3px;max-height:60px;overflow-y:auto;">${esc(lastError)}</small>` : ''}
        </div>`;
        statusHtml += '</div>';
        statusBox.innerHTML = statusHtml;
    });
}

function bsSaveSettings() {
    const fd = new FormData();
    fd.append('action', 'save_bot_settings');
    fd.append('sheet_url', document.getElementById('bs-sheet-url').value.trim());
    fd.append('bot_email', document.getElementById('bs-bot-email').value.trim());
    fd.append('schedule_hour', document.getElementById('bs-hour').value);
    fd.append('schedule_minute', document.getElementById('bs-minute').value);
    if(document.getElementById('bs-enabled').checked) fd.append('enabled', '1');

    fetch(API, { method:'POST', body:fd }).then(r => r.json()).then(res => {
        if(res.success) { alert('Đã lưu cấu hình bot.'); bsLoadSettings(); }
        else alert(res.message || 'Lỗi khi lưu');
    });
}

function bsUploadCredentials() {
    const file = document.getElementById('bs-cred-file').files[0];
    if(!file) { alert('Vui lòng chọn file JSON service account.'); return; }
    if(!file.name.endsWith('.json')) { alert('File phải có đuôi .json'); return; }

    const fd = new FormData();
    fd.append('action', 'upload_bot_credentials');
    fd.append('credentials_file', file);

    document.getElementById('bs-cred-info').innerHTML = '<span style="color:var(--text-muted);">Đang upload...</span>';
    fetch(API, { method:'POST', body:fd }).then(r => r.json()).then(res => {
        if(res.success) {
            document.getElementById('bs-cred-info').innerHTML =
                `<span style="color:var(--success-color);">✓ Đã upload thành công.</span><br>
                 <strong>Bot email:</strong> <code>${esc(res.bot_email)}</code><br>
                 <strong>Project:</strong> ${esc(res.project || '')}<br>
                 <span style="color:#856404;">⚠ Đừng quên share Google Sheet cho email trên với quyền Editor!</span>`;
            bsLoadSettings();
        } else {
            document.getElementById('bs-cred-info').innerHTML = `<span style="color:var(--danger-color);">✗ ${esc(res.message || 'Lỗi upload')}</span>`;
        }
    });
}

function bsTriggerSyncNow() {
    if(!confirm('Chạy đồng bộ ngay? Quá trình có thể mất 10-30 giây.')) return;
    const btn = event.currentTarget;
    btn.disabled = true; btn.textContent = '⏳ Đang đồng bộ...';

    const fd = new FormData();
    fd.append('action', 'trigger_bot_sync');

    fetch(API, { method:'POST', body:fd }).then(r => r.json()).then(res => {
        btn.disabled = false; btn.textContent = '⚡ Chạy đồng bộ ngay';
        if(res.success) {
            alert(`✓ Đồng bộ thành công!\n${res.message}`);
        } else {
            alert(`✗ Đồng bộ thất bại:\n${res.message || 'Lỗi không xác định'}`);
        }
        bsLoadSettings();
    }).catch(err => {
        btn.disabled = false; btn.textContent = '⚡ Chạy đồng bộ ngay';
        alert('Lỗi kết nối: ' + err.message);
    });
}

function bsTriggerImportNow() {
    if(!confirm('Đọc tab "Tổng quan" trên Google Sheet và đồng bộ vào DB?\n\n• Task có Mã YC mới → INSERT\n• Task đã tồn tại → UPDATE\n• BA chưa có trong DB → tự tạo (pass: kinkin123)')) return;
    const btn = event.currentTarget;
    const originalLabel = btn.textContent;
    btn.disabled = true; btn.textContent = '⏳ Đang import...';

    const fd = new FormData();
    fd.append('action', 'import_from_sheet');
    fd.append('tab', 'Tổng quan');

    fetch(API, { method:'POST', body:fd }).then(r => r.json()).then(res => {
        btn.disabled = false; btn.textContent = originalLabel;
        if(res.success) {
            const s = res.stats || {};
            let msg = `✓ Import xong:\n`
                    + `  • Tổng: ${s.total_rows || 0} dòng\n`
                    + `  • Insert: ${s.inserted || 0}\n`
                    + `  • Update: ${s.updated || 0}\n`
                    + `  • Bỏ qua (thiếu Mã YC): ${s.skipped || 0}\n`
                    + `  • BA mới tạo: ${(s.created_ba || []).length}`;
            if((s.created_ba || []).length) {
                msg += '\n\nBA mới (default password: kinkin123):';
                s.created_ba.forEach(b => { msg += `\n  + ${b.username} → ${b.full_name}`; });
            }
            if((s.errors || []).length) {
                msg += `\n\n⚠ ${s.errors.length} dòng lỗi:`;
                s.errors.slice(0, 5).forEach(e => { msg += '\n  ' + e; });
                if(s.errors.length > 5) msg += `\n  ... (+${s.errors.length - 5} dòng nữa)`;
            }
            alert(msg);
        } else {
            alert(`✗ Import thất bại:\n${res.message || 'Lỗi không xác định'}`);
        }
    }).catch(err => {
        btn.disabled = false; btn.textContent = originalLabel;
        alert('Lỗi kết nối: ' + err.message);
    });
}

// Helper esc nếu chưa có (lead.php đã có nhưng phòng case load độc lập)
if(typeof esc === 'undefined') {
    function esc(s) { if(s===null||s===undefined) return ''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
}
