# Design Rules — Kin Kin BA Tool

Tài liệu quy tắc thiết kế giao diện. Áp dụng cho tất cả trang web nội bộ Kin Kin Logistics để đảm bảo đồng bộ.

---

## 1. Nguyên tắc cốt lõi

| Quy tắc | Mô tả |
|---------|-------|
| **Sharp corners** | `border-radius: 0` cho MỌI thành phần — không có góc bo tròn |
| **Red accent** | Màu chủ đạo `#dc3545` (Kin Kin Red) |
| **Dark sidebar** | Sidebar nền đen `#2c2c2c`, viền đỏ bên phải |
| **Compact** | Padding nhỏ, font-size nhỏ, tối ưu cho nhiều data trên 1 trang |
| **Font** | Inter (Google Fonts) — fallback: system fonts |

```css
/* HARD RULE: không bao giờ có góc bo */
* { border-radius: 0 !important; }
```

---

## 2. Bảng màu (CSS Variables)

```css
:root {
    /* Background */
    --bg-color: #f8f9fa;
    --card-bg: #ffffff;

    /* Text */
    --text-primary: #2c2c2c;
    --text-secondary: #666666;
    --text-muted: #999999;

    /* Border */
    --border-color: #dee2e6;
    --border-light: #eeeeee;

    /* Brand */
    --primary-color: #dc3545;       /* Kin Kin Red */
    --primary-hover: #b02a37;
    --primary-soft: #f8d7da;

    /* Status */
    --success-color: #28a745;       /* Xanh lá */
    --warning-color: #ffc107;       /* Vàng */
    --danger-color: #dc3545;        /* Đỏ */
    --info-color: #0e7490;          /* Xanh dương đậm */

    /* Dark */
    --dark: #2c2c2c;
    --dark-soft: #444444;

    /* Layout */
    --sidebar-width: 240px;
    --sidebar-bg: #2c2c2c;
    --sidebar-text: #cccccc;
    --sidebar-active-bg: #dc3545;

    --card-shadow: 0 1px 3px rgba(0,0,0,0.06);
}
```

---

## 3. Typography

| Thành phần | Font size | Weight | Màu |
|------------|-----------|--------|-----|
| Tiêu đề trang (h2) | 1.1rem | 700 | `--text-primary` |
| Section title | 1.05rem | 700 | `--primary-color` |
| Body text | 0.88-0.95rem | 400 | `--text-primary` |
| Label / form label | 0.88rem | 600 | `--text-primary` |
| Muted text | 0.8-0.85rem | 400 | `--text-muted` |
| Table header | 0.72rem | 700 | `#fff` trên nền `--dark` |
| Table cell | 0.88rem | 400 | `--text-primary` |
| Badge | 0.75rem | 600 | Tùy loại |

```css
font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
line-height: 1.5;
```

---

## 4. Layout

### 4.1. App Shell (Sidebar + Main)

```
┌─────────────┬──────────────────────────────────┐
│             │  TOPBAR (sticky)                 │
│   SIDEBAR   ├──────────────────────────────────┤
│   240px     │                                  │
│   sticky    │  CONTENT                         │
│   dark bg   │  padding: 24px 32px              │
│   red border│                                  │
│             │                                  │
│             │                                  │
└─────────────┴──────────────────────────────────┘
```

```css
.app-shell { display: flex; min-height: 100vh; }
.sidebar { width: 240px; position: sticky; top: 0; height: 100vh; }
.main { flex: 1; min-width: 0; }
.topbar { position: sticky; top: 0; z-index: 100; }
.content { padding: 24px 32px; }
```

### 4.2. Sidebar

```css
.sidebar {
    background: #2c2c2c;
    border-right: 3px solid #dc3545;   /* Viền đỏ phải */
}

/* Menu item */
.sidebar-item {
    padding: 12px 24px;
    font-size: 0.92rem;
    font-weight: 500;
    border-left: 3px solid transparent;
}

/* Active state */
.sidebar-item.active {
    background: rgba(220,53,69,0.12);
    color: #fff;
    border-left-color: #dc3545;
}

/* Menu group label */
.sidebar-menu-label {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1.2px;
    color: #888;
    padding: 14px 24px 6px;
}
```

### 4.3. Card

```css
.card {
    background: #fff;
    border: 1px solid #dee2e6;
    padding: 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    margin-bottom: 20px;
    /* KHÔNG có border-radius */
}

.section-title {
    color: #dc3545;
    font-size: 1.05rem;
    font-weight: 700;
    padding-bottom: 10px;
    border-bottom: 2px solid #dc3545;
    margin-bottom: 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
```

---

## 5. Components

### 5.1. Buttons

```css
/* Base */
.btn {
    padding: 9px 18px;
    font-size: 0.9rem;
    font-weight: 600;
    border: 1px solid transparent;
    cursor: pointer;
    /* KHÔNG border-radius */
}

/* Variants */
.btn-primary   { background: #dc3545; color: #fff; }
.btn-success   { background: #28a745; color: #fff; }
.btn-dark      { background: #2c2c2c; color: #fff; }
.btn-outline   { background: #fff; color: #2c2c2c; border-color: #dee2e6; }

/* Sizes */
.btn-sm   { padding: 5px 12px; font-size: 0.82rem; }
.btn-icon { padding: 5px 10px; font-size: 0.8rem; }

/* Action button (next step) */
.btn-next { background: #28a745; color: #fff; padding: 6px 12px; font-size: 0.82rem; }

/* Cancel/danger outline */
.btn-cancel-flow { background: #fff; color: #dc3545; border: 1px solid #dc3545; }
```

### 5.2. Forms

```css
.form-group { margin-bottom: 16px; }

.form-group label {
    display: block;
    margin-bottom: 6px;
    font-weight: 600;
    font-size: 0.88rem;
}

.form-control {
    width: 100%;
    padding: 9px 12px;
    border: 1px solid #ced4da;
    font-size: 0.95rem;
    /* KHÔNG border-radius */
}

.form-control:focus {
    border-color: #dc3545;
    box-shadow: 0 0 0 2px rgba(220,53,69,0.15);
    outline: none;
}
```

### 5.3. Tables

```css
table { width: 100%; border-collapse: collapse; }

th {
    background: #2c2c2c;        /* Nền đen */
    color: #fff;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 12px 14px;
    border-bottom: 2px solid #dc3545;  /* Viền đỏ dưới header */
    white-space: nowrap;
}

td {
    padding: 12px 14px;
    font-size: 0.88rem;
    border-bottom: 1px solid #eee;
}

tbody tr:hover { background: #fafafa; }

/* Dòng highlight (task mới) */
tr.row-new-task { background: #fff8f0 !important; }
tr.row-new-task td:first-child { border-left: 3px solid #ff6b00; }
```

### 5.4. Badges (Status/Priority)

```css
.badge {
    padding: 3px 9px;
    font-size: 0.75rem;
    font-weight: 600;
    border: 1px solid;
    display: inline-block;
    /* KHÔNG border-radius */
}

/* Variants */
.badge-new      { background: #ff6b00; color: #fff; }      /* Mới */
.badge-pending  { background: #f1f3f5; color: #495057; }   /* Chờ */
.badge-progress { background: #cfe2ff; color: #084298; }   /* Đang xử lý */
.badge-done     { background: #d1e7dd; color: #0f5132; }   /* Hoàn thành */
.badge-medium   { background: #fff3cd; color: #664d03; }   /* Trung bình */
.badge-high     { background: #f8d7da; color: #842029; }   /* Cao/Nguy hiểm */
.badge-low      { background: #e7f1ff; color: #052c65; }   /* Thấp */
.badge-dark     { background: #2c2c2c; color: #fff; }      /* Dark */
```

### 5.5. Modal

```css
.modal {
    display: none;              /* Mặc định ẩn */
    position: fixed;
    inset: 0;
    z-index: 2000;
    background: rgba(44,44,44,0.55);
}

.modal-content {
    background: #fff;
    margin: 7vh auto;
    padding: 28px 32px;
    border: 1px solid #dee2e6;
    border-top: 4px solid #dc3545;  /* Viền đỏ trên */
    max-width: 520px;
    width: 92%;
    box-shadow: 0 12px 30px rgba(0,0,0,0.2);
}

.modal-content h3 {
    color: #dc3545;
    border-bottom: 2px solid #dc3545;
    padding-bottom: 8px;
    margin-bottom: 18px;
}

/* Nút đóng */
.modal .close {
    position: absolute;
    right: 14px; top: 8px;
    font-size: 26px;
    cursor: pointer;
    color: #999;
}

/* Mở modal bằng JS */
document.getElementById('myModal').style.display = 'block';
/* Đóng */
document.getElementById('myModal').style.display = 'none';
```

### 5.6. Confirm Dialog

```css
.confirm-backdrop {
    position: fixed; inset: 0; z-index: 3000;
    background: rgba(44,44,44,0.55);
    display: flex; align-items: center; justify-content: center;
}

.confirm-box {
    background: #fff;
    border-top: 4px solid #dc3545;
    max-width: 460px;
    width: 92%;
}

.confirm-box .head    { padding: 18px 24px; border-bottom: 1px solid #eee; }
.confirm-box .body    { padding: 20px 24px; }
.confirm-box .actions { padding: 12px 24px; border-top: 1px solid #eee; display: flex; gap: 8px; justify-content: flex-end; background: #f8f9fa; }
```

---

## 6. Quick Filter Buttons

```css
/* Dãy nút lọc nhanh — mỗi nút có border màu riêng */
.qf-btn {
    padding: 5px 12px;
    font-size: 0.82rem;
    background: transparent;
    cursor: pointer;
}

/* Active state: nền = border color, chữ trắng */
.qf-btn.active {
    background: [border-color];
    color: #fff;
    font-weight: 700;
}
```

**Bảng màu filter:**

| Filter | Border/Active color |
|--------|-------------------|
| Quá hạn | `#dc3545` |
| Cần phân công | `#fd7e14` |
| Cần tiếp nhận | `#ffc107` (text: `#856404`) |
| Phân công Dev | `#0dcaf0` |
| Đang code | `#0d6efd` |
| Test | `#6f42c1` |
| Hoàn thành | `#198754` |

---

## 7. Status Grid (Bot Sync)

```css
.bs-status-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
}

.bs-stat-cell {
    padding: 14px;
    border: 1px solid #dee2e6;
    border-left: 4px solid #dee2e6;
}

.bs-stat-cell.ok  { border-left-color: #22c55e; background: #f0fff4; }
.bs-stat-cell.err { border-left-color: #ef4444; background: #fff5f5; }

.bs-stat-label { font-size: 0.7rem; text-transform: uppercase; color: #999; }
.bs-stat-value { font-size: 1rem; font-weight: 700; }
```

---

## 8. Notification Bell

```css
.notif-bell {
    position: relative;
    cursor: pointer;
    font-size: 1.2rem;
}

/* Badge đỏ số thông báo */
.notif-count {
    position: absolute;
    top: -6px; right: -8px;
    background: #dc3545;
    color: #fff;
    font-size: 0.65rem;
    min-width: 16px;
    height: 16px;
    text-align: center;
    line-height: 16px;
}
```

---

## 9. Login Page

```css
.standalone-shell {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8f9fa;
}

.login-box {
    background: #fff;
    padding: 40px;
    border: 1px solid #dee2e6;
    border-top: 4px solid #dc3545;
    width: 420px;
    max-width: 95%;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}

.login-box h2 {
    color: #dc3545;
    text-align: center;
    margin-bottom: 6px;
}
```

---

## 10. Code Patterns (JS)

### API Call

```javascript
const API = BASE_PATH + '/api/data.php';

// GET
fetch(API + '?action=get_tasks').then(r => r.json()).then(data => { ... });

// POST
const fd = new FormData();
fd.append('action', 'update_task');
fd.append('task_id', 123);
fetch(API, { method: 'POST', body: fd }).then(r => r.json()).then(res => {
    if (res.success) { /* OK */ }
    else alert(res.message || 'Lỗi');
});
```

### Escape HTML

```javascript
function esc(s) {
    if (s === null || s === undefined) return '';
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}
```

### Section Switch (SPA-like)

```javascript
function switchSection(name) {
    document.querySelectorAll('.page-section').forEach(s => s.style.display = 'none');
    document.querySelectorAll('.sidebar-item').forEach(b => b.classList.remove('active'));
    document.getElementById('section-' + name).style.display = 'block';
    event.currentTarget.classList.add('active');
}
```

### Modal Open/Close

```javascript
// Mở
document.getElementById('myModal').style.display = 'block';

// Đóng
function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}

// Click ngoài modal đóng
modal.onclick = function(e) {
    if (e.target === modal) modal.style.display = 'none';
};
```

---

## 11. Responsive

```css
@media (max-width: 900px) {
    .sidebar { display: none; }
    .content { padding: 16px; }
    .topbar { padding: 10px 16px; }
    .bs-status-grid { grid-template-columns: repeat(2, 1fr); }
}
```

---

## 12. Checklist áp dụng cho web mới

- [ ] Import font Inter từ Google Fonts
- [ ] Copy CSS variables vào `:root`
- [ ] Set `* { border-radius: 0 !important; }`
- [ ] Sidebar: nền `#2c2c2c`, viền phải `3px solid #dc3545`
- [ ] Table header: nền `#2c2c2c`, chữ trắng, viền dưới đỏ
- [ ] Modal: `border-top: 4px solid #dc3545`
- [ ] Buttons: dùng đúng class `.btn-primary`, `.btn-outline`
- [ ] Badge: dùng đúng variant theo status
- [ ] Focus state: `box-shadow: 0 0 0 2px rgba(220,53,69,0.15)`
- [ ] Không dùng `border-radius` ở bất kỳ đâu
