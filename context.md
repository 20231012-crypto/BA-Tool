# BA.Tool — Hệ thống quản lý yêu cầu BA · Phân tích toàn diện

> **Cập nhật**: 2026-05-08 (state sau migration v14, KPI dashboard, user groups, dev sync format mới)
> **Repo**: `c:\Vscode\VScode\BA.Tool`
> **Stack**: PHP vanilla + MySQL + vanilla JS (no Composer / no framework). Chạy local trên XAMPP, charset utf8mb4.

---

## 1. Vai trò người dùng (3 role)

| Role | Mô tả | Truy cập |
|---|---|---|
| `lead` | Lead BA — toàn quyền | Mọi section + cấu hình (workflow, form, bot sync), KPI dashboard, quản lý nhân sự + nhóm, xoá YC |
| `ba`   | Nhân viên BA | Việc của tôi, danh sách hệ thống được gán, quản lý Dev (tạo/sửa user role=dev) |
| `dev`  | Developer | Việc Dev của mình, danh sách hệ thống được gán (đọc + sửa cây node) |

Auth: session-based qua `$_SESSION['user_id']` + `$_SESSION['role']`. Mật khẩu hash bằng `password_hash()` (BCRYPT).

---

## 2. Workflow trạng thái task

### 2.1 BA workflow chính (`tasks.status`)
```
Chờ tiếp nhận
  → Todo - chờ xác nhận với Sếp
    → Dion - đang xử lý
      → Dion - Chờ nghiệm thu
        → Kinkin nghiệm thu (terminal)
        
Bất kỳ bước nào → Huỷ → reopen → Todo - chờ xác nhận với Sếp
```

- BA và Lead khi bấm "→ Tiếp nhận" trên task `Chờ tiếp nhận` chưa có người: **auto-claim** (gán `assignee_id = current user`) + advance về `Todo - chờ xác nhận với Sếp` (xem [api/data.php](api/data.php) endpoint `next_step`).
- Khi back về `Chờ tiếp nhận`, hệ thống xoá `assignee_id` để Lead phân lại từ đầu.
- Khi vào `Dion - đang xử lý`: ghi `actual_start_datetime = NOW()`. Vào `Dion - Chờ nghiệm thu`/`Kinkin nghiệm thu`: ghi `actual_end_date`. Vào `Kinkin nghiệm thu`: ghi thêm `acceptance_date`.
- Status hợp lệ trong DB còn có `FFM - đang xử lý` (legacy từ import sheet — giữ nguyên text, không xuất hiện trong workflow chính).

### 2.2 Dev sub-workflow (`tasks.dev_status`)
```
Chờ dev nhận → Dev đang làm → Dev đã xong
            ↑              ↓
         (loop)        Cần sửa
```

- Khi Dev bấm "Bắt đầu": ghi `dev_start_at = NOW()`. Bấm "Hoàn thành": ghi `dev_end_at`.
- Mapping cũ trong sheet (`hoàn thành`/`đang làm`/`todo`/`hủy`) được normalize bởi `TaskImportService` (xem mục 7).

---

## 3. Database schema (cumulative qua v14)

### 3.1 `users`
```sql
id, username UNIQUE, password (bcrypt), full_name, role ENUM('lead','ba','dev'), created_at
```

### 3.2 `tasks` — bảng chính
**Cột public/form (NOT NULL):** `requester_name`, `requester_dept`, `system_name`, `description`, `task_type`, `priority_requester`, `start_date` DATETIME, `expected_end_date` DATETIME, `attachment_url`, `office_link`, `status`, `actual_end_date` DATETIME

**Cột BA nội bộ (v2):** `ma_yc` UNIQUE (YC001…), `module_id` (FK→modules), `feature`, `ba_description`, `priority_ba`, `classification`, `ba_submission_date`, `actual_start_datetime`, `acceptance_date`, `implementing_unit` (DION/Kinkin/FFM), `dev_actual_day` (Vietnamese day-of-week)

**Cột systems registry (v10):** `system_id` (FK→systems), `module_node_id` (FK→system_nodes), `feature_node_id` (FK→system_nodes)

**Cột Dev (v4 + v9 + v12):** `dev_id` (FK→users), `dev_status`, `dev_notes`, `dev_attachment_url`, `dev_start_at` DATETIME, `dev_end_at` DATETIME, `dev_deadline` DATETIME, `dev_planned_start` DATE, `dev_planned_end` DATE

**Cột Test (v14, mới):** `tester_id` (FK→users), `test_date` DATE, `test_status` VARCHAR(50)

**FK:** `assignee_id`, `dev_id`, `tester_id` đều ON DELETE SET NULL.

### 3.3 Bảng phụ trợ
| Bảng | Mục đích | Migration |
|---|---|---|
| `modules` | Catalog Module legacy | v2 |
| `features` | Catalog Feature legacy (uniq trên module_id+name) | v2 |
| `breaktasks` | Chia nhỏ task (parent_task_id, sub_description, status, hours, due_date) | v2 |
| `notifications` | Thông báo realtime (user_id, title, message, task_id, is_read…) | v3 |
| `workflows` + `workflow_steps` | Workflow builder (Lead config quy trình tự động) | v5/v6 |
| `form_config` | Cấu hình form public (drag-drop trường) | v7 |
| `bot_settings` | Singleton config Google Sheets sync (sheet_url, credentials_path, last_sync…) | v8 |
| `systems` | Hệ thống cha (replace dần `modules`) | v10 |
| `system_nodes` | Cây Module → Tính năng → Logic → Tính năng ẩn | v10 |
| `system_assignees` | Nhân sự được gán vào hệ thống (lead/ba/dev đều có thể) | v10 |
| `user_groups` | Nhóm nhân sự (DION/Kinkin/FFM) — color, description | **v13 mới** |
| `user_group_members` | Link nhóm ↔ user (CASCADE on delete) | **v13 mới** |

### 3.4 Migrations đã apply (theo thứ tự)
```
database.sql           # schema gốc (V1)
v2_*                   # +25 cột tasks, modules, features, breaktasks, migrate enum cũ
v3_workflow            # bảng notifications
v4_dev                 # role=dev, dev_id, dev_status…
v5_workflow_builder    # workflows + workflow_steps
v6_seed_workflows      # seed quy trình mặc định
v7_form_config         # cấu hình form public
v8_bot_sync            # bot_settings (Google Sheets)
v9_dev_deadline        # dev_deadline DATETIME
v10_systems_tree       # systems, system_nodes, system_assignees
v11_seed_systems       # seed các hệ thống mẫu
v12_dev_date_range     # dev_planned_start, dev_planned_end
v13_user_groups        # user_groups + user_group_members (seed DION/Kinkin)
v14_test_fields        # tester_id, test_date, test_status
```

---

## 4. Cấu trúc thư mục (state hiện tại)

```
BA.Tool/
├── index.php                 # Routing chính (?page=…)
├── database*.sql             # Schema + 13 file migration
├── chức năng phân tích (KPI).md  # Spec gốc cho KPI dashboard
├── config/
│   ├── db.php                # PDO connection (root, no password — XAMPP)
│   └── google-credentials.json  # Service account JSON cho Sheets API
├── controllers/
│   ├── AuthController.php
│   ├── FormController.php    # Form public + submit
│   ├── TaskController.php    # Routing dashboard theo role
│   └── UserController.php
├── models/
│   ├── User.php              # CRUD + getAllBA/Dev/WithPerformance
│   ├── Task.php              # CRUD + nextStep + delete (lead-only)
│   ├── Notification.php
│   ├── Workflow.php
│   ├── FormConfig.php
│   ├── BotSettings.php
│   ├── SystemRegistry.php    # systems + system_nodes + reparent + canEdit
│   └── UserGroup.php         # v13 — nhóm nhân sự
├── services/
│   ├── GoogleSheetsBot.php   # Service-account JWT → Sheets API v4 (no Composer)
│   ├── TaskSyncService.php   # DB → Sheet (HEADER_32 cho BA/Lead, HEADER_DEV cho Dev)
│   └── TaskImportService.php # Sheet → DB (idempotent theo ma_yc)
├── views/
│   ├── admin/
│   │   ├── login.php / register.php
│   │   ├── lead.php          # Dashboard Lead (giant — task list, KPI, users, groups, systems, workflow, form-config, bot-sync, formlog, notifications)
│   │   ├── ba.php            # Dashboard BA (task list + filter + Quản lý Dev)
│   │   ├── dev.php           # Dashboard Dev (compact KPI strip + filter + systems tree)
│   │   ├── _sidebar.php      # Shared sidebar (đổi menu theo role)
│   │   ├── _systems_section.php  # Shared section danh sách hệ thống (lead/ba/dev)
│   │   └── _start_coding_modal.php  # Shared modal "Bắt đầu code" (BA + Lead)
│   └── public/
│       └── form.php          # Form gửi yêu cầu (no auth)
├── api/
│   └── data.php              # Single JSON endpoint, 50+ actions theo ?action=
├── assets/
│   ├── css/style.css         # Tất cả CSS, version-bumped (?v=17)
│   └── js/
│       ├── task-detail.js
│       ├── start-coding-modal.js
│       ├── claim-modal.js    # Modal "Tiếp nhận YC" với Đơn vị bắt buộc
│       ├── workflow-builder.js
│       ├── form-config.js
│       ├── bot-sync.js       # Lead's bot config + Import/Sync buttons
│       ├── system-tree.js    # Mind-map hệ thống + drag reparent
│       └── kpi-dashboard.js  # KPI charts (Plotly) — Lead only
├── uploads/                  # File public form upload
└── memory/                   # Auto-memory (không phải app code)
```

---

## 5. Routing & API

### 5.1 `index.php` — page router
```
?page=public_form (default) | submit_form | login | register | logout
?page=dashboard             # Lead → lead.php; BA → ba.php; Dev → dev.php
?page=update_task | users | user_action
```

### 5.2 `api/data.php` — JSON API (50+ actions)
**Convention:** GET với `action=…` cho query, POST với `action=…` cho mutate.

**Auth tier:**
- Public form submit: không cần
- Mọi `action` còn lại: yêu cầu `$_SESSION['user_id']` + đôi khi check role

**Nhóm action chính:**
| Nhóm | Actions tiêu biểu | Auth |
|---|---|---|
| Tasks | `get_tasks`, `get_task_detail`, `assign_task`, `next_step`, `dev_update`, `dev_back`, `claim_task_meta`, **`delete_task`** | Theo role |
| Users | `get_users`, `get_ba_list`, `get_dev_list` (`?group_id=N` filter), `create_user`, `edit_user`, `delete_user` | Lead chủ yếu |
| User groups | `list_user_groups`, `list_users_for_group`, `create_user_group`, `update_user_group`, `delete_user_group`, `set_user_group_members` | Lead |
| Systems | `list_systems`, `get_system_detail`, `create_system`, `update_system`, `delete_system`, `set_system_assignees`, `create_system_node`, `update_system_node`, `delete_system_node`, `reparent_system_node` | Mix |
| Bot Sync | `get_bot_settings`, `save_bot_settings`, `upload_bot_credentials`, `trigger_bot_sync`, **`import_from_sheet`** | Lead only |
| Notifications | `get_notifications`, `mark_notification_read` | Session |
| Form config | `get_form_config`, `save_field`, `delete_field`, `reorder_fields` | Lead |
| Workflow | `list_workflows`, `save_workflow`, `delete_workflow` | Lead |

---

## 6. KPI Dashboard (Lead-only)

Spec từ `chức năng phân tích (KPI).md` — port logic từ `app.js` gốc (vẽ Plotly client-side, đọc DB qua `get_tasks`).

**Files:**
- View: section `#section-kpi` trong [lead.php](views/admin/lead.php) — class `kpi-dashboard-card` (overflow-x: auto, min-width 1100px)
- JS: [assets/js/kpi-dashboard.js](assets/js/kpi-dashboard.js) — `kpiInit()`, `kpiApply()`, các `kpiRender*()`
- CSS: `.kpi-dashboard-card`, `.kpi-scorecards`, `.kpi-card-mini`, `.kpi-grid`, `.kpi-tables-grid` (style.css)
- Plotly CDN: `https://cdn.plot.ly/plotly-2.35.2.min.js`

**Bộ lọc:** date range from/to + quick range (Tuần/Tháng này/trước/Toàn bộ) + period (week/month) + đơn vị + BA.

**5 scorecards:** Tổng YC (split unit), Tỷ lệ HT, Tỷ lệ đúng hạn, Đang xử lý, Tổng giờ code.

**5 charts:** Trend (bar+line), Pie phòng ban, Pie loại YC, Stacked bar Top 10 hệ thống, Grouped bar hiệu suất từng BA.

**2 bảng chi tiết:** Theo Hệ thống + Theo BA.

---

## 7. Google Sheets Integration

### 7.1 Cấu hình
- Sheet ID đang dùng: `1N5YNAS-aykn9E_LNwqHCXEDq-hdFGAfJHg0lc2-saJc` (lưu ở `bot_settings`)
- Service account JWT → access_token (no Composer): [services/GoogleSheetsBot.php](services/GoogleSheetsBot.php). Methods: `listTabs()`, `ensureTab()`, `getValues($range)`, `updateValues()`, `clearRange()`, `overwriteTab()` (đã fix clear range A:AZ để không sót cột cũ).
- Credentials: `config/google-credentials.json` — bot email phải được Sheet share quyền **Editor**.

### 7.2 Sync OUT (DB → Sheet) — [services/TaskSyncService.php](services/TaskSyncService.php)
**Tab "Tổng quan"**: tất cả task, **HEADER_32** (32 cột chuẩn template gốc).

**Mỗi user 1 tab riêng:**
- Tab BA / Lead: dùng `HEADER_32` (32 cột) + `build32($t)`.
- Tab Dev: dùng `HEADER_DEV` (19 cột) + `build19Dev($t)` — **mới v14** (xem mục 7.4 dưới).

**Cách trigger:**
- Manual: Lead vào "Đồng bộ Sheet" → "⚡ Chạy đồng bộ ngay"
- Auto: Windows Task Scheduler chạy `cron/sync_to_sheet.php` (chưa có cron file — cần tạo nếu muốn auto)

### 7.3 Import IN (Sheet → DB) — [services/TaskImportService.php](services/TaskImportService.php)
- Đọc tab "Tổng quan" 32 cột (chỉ tab này, **không đọc tab Dev**)
- Idempotent theo `ma_yc`: tồn tại → UPDATE; chưa có → INSERT
- Auto-create user role=ba khi tên BA chưa có trong DB (slugify tiếng Việt + bỏ dấu, default password `kinkin123`)
- Normalize values:
  - Status: `Dion- Chờ nghiệm thu` → `Dion - Chờ nghiệm thu`, `Pending` → `Chờ tiếp nhận`. Status `FFM - đang xử lý` giữ nguyên text.
  - Implementing unit: giữ nguyên (DION / Kinkin / FFM)
  - Priority requester: thêm prefix `1./2./3./4.` nếu sheet thiếu
  - Priority BA: collapse double-space
  - Classification: fix space `Hệ thống -Thực hiện` → `Hệ thống - Thực hiện`
  - Dev status: lowercase Vietnamese → `Chờ dev nhận / Dev đang làm / Dev đã xong / Cần sửa`
  - Date: parse `d/m/Y H:i` hoặc `d/m/Y` → `Y-m-d H:i:s`
- Cách trigger: Lead vào "Đồng bộ Sheet" → "⬇ Import từ Sheet"

### 7.4 HEADER_DEV (19 cột — dành riêng tab Dev)
| # | Header | Field DB |
|---|---|---|
| 1 | Loại yêu cầu | `task_type` |
| 2 | Mã Yêu Cầu | `ma_yc` |
| 3 | Nội dung yêu cầu | `ba_description ?: description` |
| 4 | Người yêu cầu | `requester_name` |
| 5 | Người thực hiện | `dev_name` |
| 6 | Ngày thực hiện code | `DATE(dev_start_at)` |
| 7 | Ngày hoàn thành code | `DATE(dev_end_at)` |
| 8 | Ngày dự kiến hoàn thành | `dev_planned_end ?: dev_deadline` |
| 9 | Trạng thái task của dev | `dev_status` |
| 10 | Mức độ | `priority_ba ?: priority_requester` |
| 11 | Người thực hiện test | `tester_name` (join `users` qua `tester_id`) |
| 12 | Ngày test | `test_date` |
| 13 | Trạng thái test | `test_status` |
| 14 | Ghi chú | `dev_notes` |
| 15 | Thời gian bắt đầu code | `dev_start_at` (datetime) |
| 16 | Thời gian kết thúc code | `dev_end_at` (datetime) |
| 17 | Thời gian BA yêu cầu | `ba_submission_date` |
| 18 | Thời gian nghiệm thu từ nhân viên | `acceptance_date` |
| 19 | Ngày Dev thực hiện yêu cầu | `dev_actual_day` (Vietnamese day-of-week) |

> **Lưu ý**: Import KHÔNG đọc HEADER_DEV — vẫn chỉ đọc tab "Tổng quan" 32 cột. Đây là yêu cầu rõ ràng từ user: "import từ sheet sẽ không làm thay đổi logic hiện tại mà chỉ thêm công việc cho dev".

---

## 8. Tính năng UI nổi bật (recent)

### 8.1 Bộ lọc Google-Sheets-style
Cả Lead, BA, Dev đều có thanh bộ lọc trên danh sách task:
- Search full-text (Mã YC / hệ thống / mô tả / người YC / BA / Dev…)
- 5–8 dropdown auto-populate từ data: Trạng thái, Đơn vị, Ưu tiên, Loại YC, Phòng ban, Hệ thống, BA/Dev, Trạng thái Dev
- Date range (chỉ Lead)
- Nút Reset + counter `${n}/${total}`
- Lead có thêm nút "⬇ Xuất CSV" — xuất theo bộ lọc hiện tại với 32-col header khớp sheet

### 8.2 Compact Dev UI
Dev dashboard dùng pill row (`.dev-kpi-strip`) thay vì grid card — click pill = quick filter theo `dev_status`. Dev không bị KPI cards to chiếm không gian danh sách task.

### 8.3 Modal "Bắt đầu code" (BA + Lead)
- Chọn Dev với dropdown **Lọc nhóm** (DION/Kinkin/...) trước khi chọn Dev
- Mô tả kỹ thuật cho Dev
- Module/Feature box (theo task_type: nâng cấp = nhập mới, fix lỗi = chọn từ cây)
- Khoảng ngày Dev làm việc (`dev_planned_start` → `dev_planned_end`)
- Modal có vertical scrollbar (max-height 90vh)

### 8.4 Modal "Tiếp nhận YC" (claim)
- Đơn vị thực hiện **bắt buộc** (DION/Kinkin)
- Priority BA + classification
- 2 bước: lưu metadata → next_step (auto-claim assignee + advance Todo)

### 8.5 Lead — xoá YC với type-to-confirm
- Nút 🗑 trong action cell mọi task
- Modal xác nhận: phải gõ đúng `ma_yc` → enable nút "Xoá vĩnh viễn"
- Cascade ON DELETE xoá cả breaktasks/system_assignees liên quan

### 8.6 User Groups (v13)
- Lead vào "Nhân sự & KPI" → card "Nhóm nhân sự" trên cùng
- Tạo nhóm với màu + mô tả
- Modal "Quản lý thành viên" có tab role (All/Lead/BA/Dev) + search + checkbox
- Khi BA/Lead phân Dev: dropdown filter nhóm trước khi chọn Dev (`get_dev_list?group_id=N`)
- Default seed: **DION** (đỏ #dc3545) + **Kinkin** (xanh #0d6efd)

### 8.7 Dev xem & sửa cây hệ thống
- Sidebar Dev có menu "🗂️ Danh sách hệ thống" (v3 — sau update)
- `SystemRegistry::listSystems()` filter theo `system_assignees` cho mọi role non-lead
- `SystemRegistry::userCanEdit()` true nếu user trong `system_assignees` (kể cả Dev) → Dev được sửa cây node nếu Lead gán quyền
- Modal "Phân nhân sự" giờ hiện cả 3 role (Lead/BA/Dev) chia nhóm rõ ràng

### 8.8 Mind-map system tree
Cây hệ thống dạng mind-map với:
- Root ở trên, branches horizontal, children vertical
- SVG bezier curves dynamic (recompute khi resize)
- Drag-and-drop reparent với cycle protection (`isDescendant()`)
- Border-radius 18px override cho mm-nodes

---

## 9. Thông tin user mặc định / đã import

### Lead BA Demo
- Username `lead`, password `lead123`
- Role: `lead`

### BA users tạo từ import (2026-05-08)
| ID | Username | Họ tên | Default password |
|---|---|---|---|
| 1 | `lead` | Lead BA Demo | `lead123` |
| 5 | `ba2` | Trần Đăng Quang | (đã có trước import) |
| 9 | `lexuantruong` | Lê Xuân Trường | `kinkin123` |
| 10 | `tranducminh` | Trần Đức Minh | `kinkin123` |
| 11 | `phamphuonganh` | Phạm Phương Anh | `kinkin123` |

163 task lịch sử (YC346…) đã import từ tab "Tổng quan" của sheet vào DB.

---

## 10. Convention quan trọng

### 10.1 Sharp-corners design
CSS global override `border-radius: 0 !important` (theme KinKin red #dc3545). Chỉ mind-map node mới có border-radius riêng.

### 10.2 Inline JS trong views
- `<script>const API = '/BA.Tool/api/data.php';</script>` đầu file
- Tất cả handler đều global function (đặt trong `<script>` block lớn)
- Cuối file mới load các shared JS files (`task-detail.js`, `start-coding-modal.js`, …)
- Không dùng module / import

### 10.3 Cache busting
Mỗi lần đổi CSS hay shared JS, bump `?v=N` ở [lead.php](views/admin/lead.php), [ba.php](views/admin/ba.php), [dev.php](views/admin/dev.php). Hiện tại CSS=17, system-tree.js=3, start-coding-modal.js=2.

### 10.4 Status string là source of truth
DB lưu chính xác chuỗi tiếng Việt (`Chờ tiếp nhận`, `Dion - đang xử lý`, ...). Tất cả comparison ở JS/PHP phải khớp ký tự — đã có incident lớn vì code dùng `'New - chờ tiếp nhận'` mà DB chỉ có `'Chờ tiếp nhận'`. Khi thêm status mới phải update đồng thời:
- `models/Task.php::getNextStatus`
- `services/TaskSyncService.php::STATUS_MAP`
- `assets/js/task-detail.js` (WF_MAIN_STEPS, WF_MAIN_ORDER)
- `assets/js/workflow-builder.js` (TASK_STATUSES)
- `views/admin/lead.php` + `ba.php` (WORKFLOW + statusBadge map)

### 10.5 Auto-claim convention
Khi BA hoặc Lead bấm next-step trên task `Chờ tiếp nhận` chưa có `assignee_id`: API `next_step` tự gán `assignee_id = $_SESSION['user_id']` rồi mới advance. Dev không có quyền claim.

### 10.6 Dev role không thấy form public
Form public là route riêng (`?page=public_form`), không qua sidebar. Dev không có quyền truy cập "Form Log" (chỉ Lead/BA).

---

## 11. Phân quyền chi tiết

| Chức năng | Public | BA | Dev | Lead |
|---|---|---|---|---|
| Gửi yêu cầu (form public) | ✅ | — | — | — |
| Đăng nhập | — | ✅ | ✅ | ✅ |
| Xem task được gán | — | ✅ | ✅ (dev_id) | ✅ (tất cả) |
| Auto-claim YC `Chờ tiếp nhận` | — | ✅ | — | ✅ |
| Phân BA cho YC | — | — | — | ✅ |
| Phân Dev (Bắt đầu code) | — | ✅ | — | ✅ |
| Cập nhật dev_status | — | — | ✅ | ✅ |
| Tạo/Sửa user role=dev | — | ✅ | — | ✅ |
| Tạo/Sửa user role=lead/ba | — | — | — | ✅ |
| Quản lý nhóm nhân sự | — | — | — | ✅ |
| Xem cây hệ thống | — | ✅ (được gán) | ✅ (được gán) | ✅ (tất cả) |
| Sửa cây node | — | ✅ (được gán) | ✅ (được gán) | ✅ |
| Xoá hệ thống | — | — | — | ✅ |
| Xoá YC vĩnh viễn | — | — | — | ✅ |
| KPI Dashboard | — | — | — | ✅ |
| Workflow builder | — | — | — | ✅ |
| Form config | — | — | — | ✅ |
| Bot Sync settings + import/export | — | — | — | ✅ |
| Form Log | — | ✅ | — | ✅ |

---

## 12. Tech debt / điểm cần biết

### 12.1 Code style hint (không lỗi)
- Các file PHP có nhiều hint từ IDE: `Convert to string interpolation`, `Missing visibility modifier` cho constant array, `Remove redundant closing tag ?>`, `parentheses unnecessary`, `curl_close deprecated` — tất cả là code style hiện hữu, không phải lỗi runtime. Không đụng tới khi không cần thiết để tránh diff lớn.

### 12.2 Bảo mật cần lưu ý nếu deploy production
- `config/db.php` hardcode password rỗng (XAMPP root) — **đổi credential khi deploy**
- Form public: thiếu CSRF token, thiếu rate limiting, thiếu mime type check cho file upload
- `Task::update()` xây query động từ key `$data` — đã có `array_intersect_key` với `$allowed_columns` để chống injection cột, nhưng vẫn nên double-check khi thêm field
- `config/google-credentials.json` chứa private key — KHÔNG được commit lên public repo

### 12.3 Test fields chưa có UI
Migration v14 thêm `tester_id`, `test_date`, `test_status` vào DB và `Task::$allowed_columns`, nhưng UI nhập vẫn chưa có. Hiện chỉ có thể update qua API trực tiếp hoặc phpMyAdmin. Sync OUT sẽ output empty cho 3 cột này nếu DB chưa có data.

### 12.4 Chưa có cron auto-sync
`bot_settings` có `schedule_hour`/`schedule_minute` nhưng cron file chưa có. Lead phải bấm thủ công "⚡ Chạy đồng bộ ngay" hoặc cài Windows Task Scheduler trỏ tới `php cron/sync_to_sheet.php` (cần tạo file này).

---

## 13. Deploy options (free)

Đã thảo luận với user — 3 con đường:

| Option | Ưu | Nhược |
|---|---|---|
| **Cloudflare Tunnel** (recommended cho local PC bật 24/7) | Free vĩnh viễn, không port-forward, HTTPS auto, custom domain. Quick Tunnel = 1 lệnh `cloudflared tunnel --url http://localhost:80` | Phụ thuộc PC nhà bật + có internet |
| **Oracle Cloud Always Free VPS** | Free vĩnh viễn (4 vCPU ARM + 24GB RAM), 24/7 thật sự, có thể chạy cron | Phải migrate sang Linux Apache/MariaDB, setup ~2-3h |
| **InfinityFree** (zero-config nhưng giới hạn) | Đăng ký 5 phút có PHP + MySQL | Có thể chặn outgoing cURL → Google Sheets API fail; subdomain xấu (`*.rf.gd`) |

User hiện đang chạy local XAMPP, chưa migrate. Khi cần deploy, nên migrate `config/db.php` credentials + verify `cURL` OK.

---

## 14. Các session làm việc gần đây — log mile-stones

### 2026-05-07
- V1 → V2: chuẩn hoá schema theo Google Sheet 32 cột (`ma_yc`, `module_id`, `ba_description`, `classification`, `implementing_unit`...)
- Migrate enum giá trị status/priority/dept theo sheet

### 2026-05-08 (session đang xử lý)
1. **Mở rộng task workflow**: thêm bước claim modal với Đơn vị bắt buộc, fix bug status string mismatch (`New - chờ tiếp nhận` → `Chờ tiếp nhận`), redesign system tree thành mind-map
2. **Logo + UI fixes**: KinKin logo, fix lỗi BA dashboard (deprecated comment block phá JS)
3. **Sheet integration**:
   - Build `TaskImportService` — auto-create 3 BA users từ sheet (Lê Xuân Trường, Trần Đức Minh, Phạm Phương Anh, password `kinkin123`)
   - Import 163 task lịch sử
   - Filter bar Google-Sheets-style cho Lead + BA + Dev
   - KPI Dashboard cho Lead với Plotly
4. **UI optimization & permissions**:
   - Compact Dev UI (KPI strip), filter cho ba.php + dev.php, scroll dọc + ngang
   - Lead xoá YC với type-to-confirm modal
   - User groups (v13) + filter Dev theo nhóm trong các modal phân Dev
   - Dev có quyền xem + sửa cây hệ thống được gán
5. **Dev sync format mới**:
   - Migration v14: tester_id, test_date, test_status
   - HEADER_DEV (19 cột) cho tab Dev khi sync
   - Fix `overwriteTab` clearRange A:Z → A:AZ

---

## 15. Memory references (auto-memory system)

`C:\Users\DQR7\.claude\projects\c--Vscode-VScode-BA-Tool\memory\`:
- `MEMORY.md` — index
- `project_ba_users.md` — 3 BA tạo từ import (id 9/10/11, password `kinkin123`)
- `project_user_groups.md` — Migration v13, default DION/Kinkin, `?group_id=N` filter
- `project_dev_sync_format.md` — Migration v14 + HEADER_DEV 19 cols + clearRange fix

---

## 16. Quick reference cho development

### 16.1 Cách thêm 1 cột mới vào tasks
1. Tạo `database_migration_vN_*.sql` dùng pattern `add_column_if_not_exists` (xem v2 hoặc v14)
2. Apply: `mysql -u root ba_tool < migration_file.sql`
3. Thêm tên cột vào `Task::$allowed_columns` ([models/Task.php](models/Task.php#L6-L21))
4. Nếu cần JOIN khi sync sheet: cập nhật SQL trong `TaskSyncService::runSync()`
5. Nếu hiển thị trong sheet output: thêm vào `HEADER_32`/`HEADER_DEV` + `build32()`/`build19Dev()`

### 16.2 Cách thêm 1 status mới
1. Update `Task::getNextStatus` map
2. Update `TaskSyncService::STATUS_MAP`
3. Update `task-detail.js` (WF_MAIN_STEPS, WF_MAIN_ORDER)
4. Update `workflow-builder.js` (TASK_STATUSES)
5. Update `lead.php` + `ba.php` (WORKFLOW + statusBadge map)

### 16.3 Cách thêm 1 API endpoint
1. Thêm `if($action === 'xxx') { ... }` trong [api/data.php](api/data.php)
2. Check role: `if($_SESSION['role'] !== 'lead') { ... exit; }`
3. Frontend: `fetch(API + '?action=xxx')` (GET) hoặc `FormData` POST

### 16.4 Cách bump cache
- CSS: tăng số trong `style.css?v=N` ở 3 file (lead/ba/dev)
- JS: tăng `?v=N` cho file shared cụ thể
- Memory: thêm bullet vào `MEMORY.md` nếu là feature mới đáng nhớ
