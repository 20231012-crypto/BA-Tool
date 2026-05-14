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
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:20px;">Chua co API key nao. Bam "+ Tao API Key" de bat dau.</td></tr>';
            return;
        }
        tbody.innerHTML = keys.map(k => `
            <tr>
                <td><strong>${esc(k.name)}</strong><br><small style="color:var(--text-muted);">${esc(k.creator_name || '')}</small></td>
                <td>
                    <code style="font-size:0.72rem;word-break:break-all;display:block;max-width:220px;background:#f5f5f5;padding:4px 6px;border-radius:3px;">${esc(k.token)}</code>
                    <button class="btn btn-outline btn-sm" style="margin-top:4px;font-size:0.7rem;" onclick="navigator.clipboard.writeText('${esc(k.token)}');this.textContent='Da copy!';setTimeout(()=>this.textContent='Copy',1500)">Copy</button>
                </td>
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

if (typeof esc === 'undefined') {
    function esc(s) { if (s === null || s === undefined) return ''; return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
}
