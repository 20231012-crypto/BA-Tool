/* =====================================================================
   api-keys.js — API Key management (Lead only)
   ===================================================================== */

function akLoad() {
    // Set base URL
    const baseEl = document.getElementById('ak-base-url');
    if (baseEl) baseEl.textContent = window.location.origin + (typeof BASE_PATH !== 'undefined' ? BASE_PATH : '');

    fetch(API + '?action=get_api_keys').then(r => r.json()).then(keys => {
        const tbody = document.getElementById('ak-tbody');
        if (!keys.length) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:20px;">Ch\u01b0a c\u00f3 API key n\u00e0o. B\u1ea5m "+ T\u1ea1o API Key" \u0111\u1ec3 b\u1eaft \u0111\u1ea7u.</td></tr>';
            return;
        }
        tbody.innerHTML = keys.map(k => `
            <tr>
                <td><strong style="cursor:pointer;color:var(--primary-color);text-decoration:underline;" onclick="akShowDetail('${esc(k.name)}','${esc(k.token)}','${esc(k.methods)}')">${esc(k.name)}</strong><br><small style="color:var(--text-muted);">${esc(k.creator_name || '')}</small></td>
                <td><code style="font-size:0.72rem;word-break:break-all;display:block;max-width:180px;background:#f5f5f5;padding:4px 6px;border-radius:3px;">${esc(k.token.substring(0,12))}...${esc(k.token.substring(k.token.length-8))}</code></td>
                <td><span class="badge badge-pending">${esc(k.methods)}</span></td>
                <td>${k.is_active == 1
                    ? '<span class="badge badge-done">Active</span>'
                    : '<span class="badge badge-high">Inactive</span>'}</td>
                <td>${k.request_count || 0}</td>
                <td><small>${k.last_used_at ? new Date(k.last_used_at.replace(' ','T')).toLocaleString('vi-VN') : '-'}</small></td>
                <td>
                    <div style="display:flex;gap:4px;flex-wrap:wrap;">
                        <button class="btn btn-outline btn-sm" onclick="akToggle(${k.id}, ${k.is_active == 1 ? 0 : 1})"
                            title="${k.is_active == 1 ? 'Tat' : 'Bat'}">${k.is_active == 1 ? '⏸' : '▶'}</button>
                        <button class="btn btn-outline btn-sm" onclick="akRegenerate(${k.id})" title="Tao token moi">🔄</button>
                        <button class="btn btn-outline btn-sm" onclick="akDelete(${k.id}, '${esc(k.name)}')" title="Xoa"
                            style="color:var(--danger-color);">✗</button>
                    </div>
                </td>
            </tr>
        `).join('');
    });
}

function akShowCreate() {
    document.getElementById('ak-create-form').style.display = 'block';
    document.getElementById('ak-token-display').style.display = 'none';
    document.getElementById('ak-name').focus();
}

function akCreate() {
    const name = document.getElementById('ak-name').value.trim();
    const methods = document.getElementById('ak-methods').value;
    if (!name) { alert('Nhap ten API key'); return; }

    const fd = new FormData();
    fd.append('action', 'create_api_key');
    fd.append('name', name);
    fd.append('methods', methods);

    fetch(API, { method: 'POST', body: fd }).then(r => r.json()).then(res => {
        if (res.success) {
            document.getElementById('ak-create-form').style.display = 'none';
            document.getElementById('ak-token-display').style.display = 'block';
            document.getElementById('ak-new-token').textContent = res.data.token;
            document.getElementById('ak-name').value = '';
            akLoad();
        } else {
            alert(res.message || 'Loi');
        }
    });
}

function akCopyToken() {
    const token = document.getElementById('ak-new-token').textContent;
    navigator.clipboard.writeText(token).then(() => alert('Da copy token!')).catch(() => {
        // Fallback
        const ta = document.createElement('textarea');
        ta.value = token; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta);
        alert('Da copy token!');
    });
}

function akToggle(id, active) {
    const fd = new FormData();
    fd.append('action', 'toggle_api_key');
    fd.append('id', id);
    fd.append('active', active);
    fetch(API, { method: 'POST', body: fd }).then(r => r.json()).then(() => akLoad());
}

function akRegenerate(id) {
    if (!confirm('Tao token moi? Token cu se khong dung duoc nua.')) return;
    const fd = new FormData();
    fd.append('action', 'regenerate_api_key');
    fd.append('id', id);
    fetch(API, { method: 'POST', body: fd }).then(r => r.json()).then(res => {
        if (res.success) {
            document.getElementById('ak-token-display').style.display = 'block';
            document.getElementById('ak-new-token').textContent = res.token;
            akLoad();
        }
    });
}

function akDelete(id, name) {
    if (!confirm('Xoa API key "' + name + '"? Tat ca request dung key nay se bi tu choi.')) return;
    const fd = new FormData();
    fd.append('action', 'delete_api_key');
    fd.append('id', id);
    fetch(API, { method: 'POST', body: fd }).then(r => r.json()).then(() => akLoad());
}

function akShowDetail(name, token, methods) {
    const baseUrl = window.location.origin + (typeof BASE_PATH !== 'undefined' ? BASE_PATH : '');
    const apiUrl = baseUrl + '/api/v1/tasks.php';

    // Create modal if not exists
    let modal = document.getElementById('akDetailModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'akDetailModal';
        modal.className = 'modal';
        modal.onclick = function(e) { if (e.target === modal) modal.style.display = 'none'; };
        document.body.appendChild(modal);
    }

    modal.innerHTML = `
        <div class="modal-content" style="max-width:650px;">
            <span class="close" onclick="document.getElementById('akDetailModal').style.display='none'">&times;</span>
            <h3>${esc(name)}</h3>
            <div style="font-size:0.88rem;">
                <div class="form-group">
                    <label>Token</label>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <input type="text" class="form-control" value="${esc(token)}" readonly id="ak-detail-token" style="font-family:monospace;font-size:0.82rem;">
                        <button class="btn btn-outline btn-sm" onclick="akDetailCopy('ak-detail-token', this)">Copy</button>
                    </div>
                </div>
                <div class="form-group">
                    <label>API URL</label>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <input type="text" class="form-control" value="${esc(apiUrl)}" readonly id="ak-detail-url" style="font-size:0.82rem;">
                        <button class="btn btn-outline btn-sm" onclick="akDetailCopy('ak-detail-url', this)">Copy</button>
                    </div>
                </div>
                <div class="form-group">
                    <label>Ph\u01b0\u01a1ng th\u1ee9c</label>
                    <span class="badge badge-pending">${esc(methods)}</span>
                </div>
                <hr style="margin:14px 0;">
                <label>VD: G\u1ecdi nhanh b\u1eb1ng curl</label>
                <div style="position:relative;">
                    <pre id="ak-detail-curl" style="background:#1e1e1e;color:#d4d4d4;padding:12px;overflow-x:auto;font-size:0.78rem;border-radius:4px;margin:6px 0;">curl -H "Authorization: Bearer ${esc(token)}" "${esc(apiUrl)}?limit=5"</pre>
                    <button class="btn btn-outline btn-sm" style="position:absolute;top:6px;right:6px;font-size:0.7rem;" onclick="akDetailCopy('ak-detail-curl', this)">Copy</button>
                </div>
            </div>
        </div>
    `;
    modal.style.display = 'block';
}

function akDetailCopy(elId, btn) {
    const el = document.getElementById(elId);
    const text = el.value || el.textContent;
    navigator.clipboard.writeText(text).then(() => {
        if (btn) { const orig = btn.textContent; btn.textContent = '\u0110\u00e3 copy!'; setTimeout(() => btn.textContent = orig, 1500); }
    });
}

if (typeof esc === 'undefined') {
    function esc(s) { if (s === null || s === undefined) return ''; return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
}
