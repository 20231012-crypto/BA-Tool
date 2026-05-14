/* =====================================================================
   bot-sync.js — Cau hinh + trigger Google Sheets sync (Lead only)
   Used by: views/admin/lead.php (section "Dong bo Sheet")
   ===================================================================== */

function bsLoadSettings() {
    fetch(API + '?action=get_bot_settings').then(r => r.json()).then(cfg => {
        if(cfg.error) { document.getElementById('bs-status').innerHTML = '<div style="color:var(--danger-color);">Khong co quyen truy cap.</div>'; return; }

        // BA Sheet
        document.getElementById('bs-sheet-url').value = cfg.sheet_url || '';
        document.getElementById('bs-webhook-url').value = cfg.ba_webhook_url || '';
        document.getElementById('bs-bot-email').value = cfg.bot_email || cfg.credentials_email || '';
        document.getElementById('bs-hour').value = cfg.schedule_hour ?? 23;
        document.getElementById('bs-minute').value = cfg.schedule_minute ?? 0;
        document.getElementById('bs-enabled').checked = parseInt(cfg.enabled) === 1;

        // Dev Sheet
        document.getElementById('bs-dev-sheet-url').value = cfg.dev_sheet_url || '';
        document.getElementById('bs-poller-interval').value = cfg.poller_interval ?? 15;
        document.getElementById('bs-poller-enabled').checked = parseInt(cfg.poller_enabled ?? 1) === 1;

        // Update display
        const intervalDisplay = document.getElementById('bs-poller-interval-display');
        if(intervalDisplay) intervalDisplay.textContent = cfg.poller_interval ?? 15;

        // Status box
        const statusBox = document.getElementById('bs-status');
        const fileOk = parseInt(cfg.credentials_exists) === 1;
        const lastSync = cfg.last_sync_at;
        const lastStatus = cfg.last_sync_status;
        const lastError = cfg.last_sync_error;
        const pollerOn = parseInt(cfg.poller_enabled ?? 1) === 1;

        let statusHtml = '<div class="bs-status-grid">';
        statusHtml += `<div class="bs-stat-cell ${fileOk?'ok':'err'}">
            <div class="bs-stat-label">Credentials</div>
            <div class="bs-stat-value">${fileOk ? '&#10003; Da co' : '&#10007; Chua co'}</div>
            ${cfg.credentials_email ? `<small>${esc(cfg.credentials_email)}</small>` : ''}
        </div>`;
        const webhookOk = !!(cfg.ba_webhook_url);
        statusHtml += `<div class="bs-stat-cell ${webhookOk?'ok':'err'}">
            <div class="bs-stat-label">BA Sheet Webhook</div>
            <div class="bs-stat-value">${webhookOk ? '&#10003; Realtime' : '&#10007; Chua cau hinh'}</div>
        </div>`;
        statusHtml += `<div class="bs-stat-cell ${pollerOn?'ok':''}">
            <div class="bs-stat-label">Dev Sheet Poller</div>
            <div class="bs-stat-value">${pollerOn ? '&#10003; Dang bat' : '&#9208; Dang tat'}</div>
            <small>Moi ${cfg.poller_interval ?? 15}s</small>
        </div>`;
        const syncCls = lastStatus === 'success' ? 'ok' : (lastStatus === 'failed' ? 'err' : '');
        statusHtml += `<div class="bs-stat-cell ${syncCls}">
            <div class="bs-stat-label">Lan dong bo gan nhat</div>
            <div class="bs-stat-value">${lastSync ? new Date(lastSync.replace(' ','T')).toLocaleString('vi-VN') : 'Chua tung'}</div>
            ${lastStatus ? `<small>${lastStatus === 'success' ? '&#10003; Thanh cong' : '&#10007; That bai'}</small>` : ''}
            ${lastError ? `<small style="color:var(--danger-color);display:block;margin-top:3px;max-height:60px;overflow-y:auto;">${esc(lastError)}</small>` : ''}
        </div>`;
        statusHtml += '</div>';
        statusBox.innerHTML = statusHtml;
    });
}

function bsSaveSettings() {
    const fd = new FormData();
    fd.append('action', 'save_bot_settings');
    // BA Sheet
    fd.append('sheet_url', document.getElementById('bs-sheet-url').value.trim());
    fd.append('ba_webhook_url', document.getElementById('bs-webhook-url').value.trim());
    fd.append('bot_email', document.getElementById('bs-bot-email').value.trim());
    fd.append('schedule_hour', document.getElementById('bs-hour').value);
    fd.append('schedule_minute', document.getElementById('bs-minute').value);
    if(document.getElementById('bs-enabled').checked) fd.append('enabled', '1');
    // Dev Sheet
    fd.append('dev_sheet_url', document.getElementById('bs-dev-sheet-url').value.trim());
    fd.append('poller_interval', document.getElementById('bs-poller-interval').value);
    if(document.getElementById('bs-poller-enabled').checked) fd.append('poller_enabled', '1');

    fetch(API, { method:'POST', body:fd }).then(r => r.json()).then(res => {
        if(res.success) { alert('Da luu cau hinh.'); bsLoadSettings(); }
        else alert(res.message || 'Loi khi luu');
    });
}

function bsUploadCredentials() {
    const file = document.getElementById('bs-cred-file').files[0];
    if(!file) { alert('Vui long chon file JSON service account.'); return; }
    if(!file.name.endsWith('.json')) { alert('File phai co duoi .json'); return; }

    const fd = new FormData();
    fd.append('action', 'upload_bot_credentials');
    fd.append('credentials_file', file);

    document.getElementById('bs-cred-info').innerHTML = '<span style="color:var(--text-muted);">Dang upload...</span>';
    fetch(API, { method:'POST', body:fd }).then(r => r.json()).then(res => {
        if(res.success) {
            document.getElementById('bs-cred-info').innerHTML =
                `<span style="color:var(--success-color);">&#10003; Upload thanh cong! Bot da duoc thay the.</span><br>
                 <strong>Bot email:</strong> <code>${esc(res.bot_email)}</code><br>
                 <strong>Project:</strong> ${esc(res.project || '')}<br>
                 <span style="color:#856404;">Nho share ca 2 Google Sheet (BA + Dev) cho email tren voi quyen Editor!</span>`;
            bsLoadSettings();
        } else {
            document.getElementById('bs-cred-info').innerHTML = `<span style="color:var(--danger-color);">&#10007; ${esc(res.message || 'Loi upload')}</span>`;
        }
    });
}

function bsTriggerSyncNow() {
    if(!confirm('Chay dong bo ngay? Qua trinh co the mat 10-30 giay.')) return;
    const btn = event.currentTarget;
    btn.disabled = true; btn.textContent = 'Dang dong bo...';

    const fd = new FormData();
    fd.append('action', 'trigger_bot_sync');

    fetch(API, { method:'POST', body:fd }).then(r => r.json()).then(res => {
        btn.disabled = false; btn.textContent = 'Chay dong bo ngay';
        if(res.success) {
            alert('Dong bo thanh cong!\n' + res.message);
        } else {
            alert('Dong bo that bai:\n' + (res.message || 'Loi khong xac dinh'));
        }
        bsLoadSettings();
    }).catch(err => {
        btn.disabled = false; btn.textContent = 'Chay dong bo ngay';
        alert('Loi ket noi: ' + err.message);
    });
}

function bsTriggerImportNow() {
    if(!confirm('Doc tab "Tong quan" tren Google Sheet va dong bo vao DB?\n\n- Task co Ma YC moi -> INSERT\n- Task da ton tai -> UPDATE\n- BA chua co trong DB -> tu tao (pass: kinkin123)')) return;
    const btn = event.currentTarget;
    const originalLabel = btn.textContent;
    btn.disabled = true; btn.textContent = 'Dang import...';

    const fd = new FormData();
    fd.append('action', 'import_from_sheet');
    fd.append('tab', 'Tổng quan');

    fetch(API, { method:'POST', body:fd }).then(r => r.json()).then(res => {
        btn.disabled = false; btn.textContent = originalLabel;
        if(res.success) {
            const s = res.stats || {};
            let msg = 'Import xong:\n'
                    + '  Tong: ' + (s.total_rows || 0) + ' dong\n'
                    + '  Insert: ' + (s.inserted || 0) + '\n'
                    + '  Update: ' + (s.updated || 0) + '\n'
                    + '  Bo qua: ' + (s.skipped || 0) + '\n'
                    + '  BA moi tao: ' + (s.created_ba || []).length;
            if((s.created_ba || []).length) {
                msg += '\n\nBA moi (default password: kinkin123):';
                s.created_ba.forEach(b => { msg += '\n  + ' + b.username + ' -> ' + b.full_name; });
            }
            if((s.errors || []).length) {
                msg += '\n\n' + s.errors.length + ' dong loi:';
                s.errors.slice(0, 5).forEach(e => { msg += '\n  ' + e; });
                if(s.errors.length > 5) msg += '\n  ... (+' + (s.errors.length - 5) + ' dong nua)';
            }
            alert(msg);
        } else {
            alert('Import that bai:\n' + (res.message || 'Loi khong xac dinh'));
        }
    }).catch(err => {
        btn.disabled = false; btn.textContent = originalLabel;
        alert('Loi ket noi: ' + err.message);
    });
}

// Helper esc neu chua co
if(typeof esc === 'undefined') {
    function esc(s) { if(s===null||s===undefined) return ''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
}
