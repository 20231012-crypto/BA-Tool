/* =====================================================================
   form-config.js — Editor cho form công khai (Google-Forms-like)
   Depends on: API, esc(), closeModal()
   Used by: views/admin/lead.php (section "Cấu hình Form")
   ===================================================================== */

let _FC = { settings: {}, fields: [], editingId: null };

const FIELD_TYPE_LABEL = {
    text: 'Text',
    textarea: 'Textarea',
    dropdown: 'Dropdown',
    date: 'Ngày',
    file: 'File',
    section: 'Section'
};
const FIELD_TYPE_ICON = {
    text: '📝', textarea: '📄', dropdown: '▼', date: '📅', file: '📎', section: '—'
};

function fcLoadConfig() {
    fetch(API + '?action=get_form_config').then(r => r.json()).then(res => {
        _FC.settings = res.settings || {};
        _FC.fields   = res.fields || [];
        // Fill settings inputs
        document.getElementById('fc-title').value       = _FC.settings.title || '';
        document.getElementById('fc-desc').value        = _FC.settings.description || '';
        document.getElementById('fc-success-msg').value = _FC.settings.success_msg || '';
        fcRenderFields();
    });
}

function fcRenderFields() {
    const list = document.getElementById('fc-fields-list');
    if(!_FC.fields.length) {
        list.innerHTML = '<div style="text-align:center;color:var(--text-muted);padding:30px;">Chưa có trường nào. Bấm "+ Thêm trường mới" để bắt đầu.</div>';
        return;
    }
    list.innerHTML = _FC.fields.map(f => {
        const optsCount = (() => { try { return (JSON.parse(f.options_json||'[]')||[]).length; } catch(e) { return 0; } })();
        const dim = (parseInt(f.is_visible)===1) ? '' : 'opacity:0.45;';
        return `<div class="fc-field-row" draggable="true" data-id="${f.id}" style="${dim}">
            <span class="fc-handle" title="Kéo để đổi thứ tự">☰</span>
            <span class="fc-type-icon">${FIELD_TYPE_ICON[f.field_type]||'•'}</span>
            <div class="fc-field-info">
                <div class="fc-field-label">
                    ${esc(f.label)}
                    ${parseInt(f.required)===1 ? '<span class="fc-flag req" title="Bắt buộc">*</span>' : ''}
                    ${parseInt(f.is_visible)===0 ? '<span class="fc-flag hidden" title="Đang ẩn">👁‍🗨 Ẩn</span>' : ''}
                    ${parseInt(f.is_builtin)===1 ? '<span class="fc-flag builtin" title="Field hệ thống">SYSTEM</span>' : ''}
                </div>
                <div class="fc-field-meta">
                    <code>${esc(f.field_key)}</code> · ${FIELD_TYPE_LABEL[f.field_type]||f.field_type}
                    ${f.field_type === 'dropdown' ? ` · ${optsCount} option` : ''}
                </div>
            </div>
            <div class="fc-field-actions">
                <button class="btn btn-outline btn-icon" onclick="fcOpenFieldModal(${f.id})" title="Sửa">✏</button>
                <button class="btn btn-outline btn-icon" onclick="fcToggleVisible(${f.id}, ${parseInt(f.is_visible)===1 ? 0 : 1})" title="${parseInt(f.is_visible)===1 ? 'Ẩn' : 'Hiện'}">${parseInt(f.is_visible)===1 ? '👁' : '👁‍🗨'}</button>
                ${parseInt(f.is_builtin)===0 ? `<button class="btn-cancel-flow" onclick="fcDeleteField(${f.id}, '${esc(f.label)}')" title="Xoá">🗑</button>` : ''}
            </div>
        </div>`;
    }).join('');
    fcAttachDragHandlers();
}

function fcAttachDragHandlers() {
    const list = document.getElementById('fc-fields-list');
    let dragging = null;
    list.querySelectorAll('.fc-field-row').forEach(row => {
        row.addEventListener('dragstart', e => {
            dragging = row;
            row.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
        });
        row.addEventListener('dragend', () => {
            if(dragging) dragging.classList.remove('dragging');
            dragging = null;
            // Save order
            const ids = Array.from(list.querySelectorAll('.fc-field-row')).map(r => parseInt(r.dataset.id));
            const fd = new FormData();
            fd.append('action','reorder_form_fields');
            fd.append('order_map', JSON.stringify(ids));
            fetch(API,{method:'POST',body:fd}).then(r=>r.json()).then(()=>fcLoadConfig());
        });
        row.addEventListener('dragover', e => {
            e.preventDefault();
            if(!dragging || dragging === row) return;
            const rect = row.getBoundingClientRect();
            const before = (e.clientY - rect.top) < rect.height / 2;
            list.insertBefore(dragging, before ? row : row.nextSibling);
        });
    });
}

function fcSaveSettings() {
    const fd = new FormData();
    fd.append('action','save_form_settings');
    fd.append('title', document.getElementById('fc-title').value);
    fd.append('description', document.getElementById('fc-desc').value);
    fd.append('success_msg', document.getElementById('fc-success-msg').value);
    fetch(API,{method:'POST',body:fd}).then(r=>r.json()).then(res => {
        if(res.success) alert('Đã lưu cấu hình form');
        else alert(res.message || 'Lỗi khi lưu');
    });
}

function fcOpenFieldModal(id) {
    _FC.editingId = id;
    const isEdit = !!id;
    const f = isEdit ? _FC.fields.find(x => x.id == id) : null;

    document.getElementById('fc-field-modal-title').textContent = isEdit
        ? `Sửa trường: ${f.label}`
        : 'Thêm trường mới';
    document.getElementById('fc-fld-key').value = f?.field_key || '';
    document.getElementById('fc-fld-key').disabled = isEdit && parseInt(f?.is_builtin) === 1;
    document.getElementById('fc-fld-label').value = f?.label || '';
    document.getElementById('fc-fld-type').value = f?.field_type || 'text';
    document.getElementById('fc-fld-type').disabled = isEdit && parseInt(f?.is_builtin) === 1;
    document.getElementById('fc-fld-required').checked = parseInt(f?.required) === 1;
    document.getElementById('fc-fld-visible').checked = isEdit ? parseInt(f?.is_visible) === 1 : true;
    document.getElementById('fc-fld-placeholder').value = f?.placeholder || '';

    let optsText = '';
    try {
        const arr = JSON.parse(f?.options_json || '[]') || [];
        optsText = arr.join('\n');
    } catch(e) {}
    document.getElementById('fc-fld-options').value = optsText;

    fcToggleOptionsBox();
    document.getElementById('fcFieldModal').style.display = 'block';
}

function fcToggleOptionsBox() {
    const t = document.getElementById('fc-fld-type').value;
    document.getElementById('fc-fld-options-box').style.display = (t === 'dropdown') ? 'block' : 'none';
}

function fcSaveField() {
    const key = document.getElementById('fc-fld-key').value.trim();
    const label = document.getElementById('fc-fld-label').value.trim();
    const type = document.getElementById('fc-fld-type').value;
    const required = document.getElementById('fc-fld-required').checked ? 1 : 0;
    const visible = document.getElementById('fc-fld-visible').checked ? 1 : 0;
    const placeholder = document.getElementById('fc-fld-placeholder').value.trim();
    const optsRaw = document.getElementById('fc-fld-options').value;

    if(!label) { alert('Vui lòng nhập Label'); return; }
    if(!_FC.editingId && !key) { alert('Vui lòng nhập Field Key'); return; }
    if(type === 'dropdown' && !optsRaw.trim()) { alert('Dropdown cần ít nhất 1 option'); return; }

    let optsJson = null;
    if(type === 'dropdown') {
        const arr = optsRaw.split('\n').map(s => s.trim()).filter(s => s.length > 0);
        optsJson = JSON.stringify(arr);
    }

    const fd = new FormData();
    fd.append('action','save_form_field');
    if(_FC.editingId) fd.append('id', _FC.editingId);
    fd.append('field_key', key);
    fd.append('label', label);
    fd.append('field_type', type);
    fd.append('required', required);
    fd.append('is_visible', visible);
    fd.append('placeholder', placeholder);
    if(optsJson) fd.append('options_json', optsJson);

    fetch(API,{method:'POST',body:fd}).then(r=>r.json()).then(res => {
        if(res.success) {
            closeModal('fcFieldModal');
            fcLoadConfig();
        } else {
            alert(res.message || 'Lỗi khi lưu trường');
        }
    });
}

function fcToggleVisible(id, newVal) {
    const f = _FC.fields.find(x => x.id == id);
    if(!f) return;
    const fd = new FormData();
    fd.append('action','save_form_field');
    fd.append('id', id);
    fd.append('field_key', f.field_key);
    fd.append('label', f.label);
    fd.append('field_type', f.field_type);
    fd.append('required', f.required);
    fd.append('is_visible', newVal);
    fd.append('placeholder', f.placeholder || '');
    if(f.options_json) fd.append('options_json', f.options_json);
    fetch(API,{method:'POST',body:fd}).then(r=>r.json()).then(res => {
        if(res.success) fcLoadConfig();
    });
}

function fcDeleteField(id, label) {
    if(!confirm('Xoá trường "' + label + '"?')) return;
    const fd = new FormData();
    fd.append('action','delete_form_field');
    fd.append('id', id);
    fetch(API,{method:'POST',body:fd}).then(r=>r.json()).then(res => {
        if(res.success) fcLoadConfig();
        else alert(res.message || 'Lỗi khi xoá');
    });
}
