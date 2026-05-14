<?php $activeMenu = 'tasks'; ?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lead Dashboard - Kin Kin BA Tool</title>
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/assets/css/style.css?v=20">
</head>
<body>
<div class="app-shell">

    <?php include __DIR__ . '/_sidebar.php'; ?>

    <main class="main">
        <!-- TOPBAR -->
        <div class="topbar">
            <div class="topbar-title">
                <h2 id="page-title">Danh sách yêu cầu hệ thống</h2>
                <small><span class="live-dot"></span>Auto refresh 15 giây</small>
            </div>
            <div class="topbar-actions">
                <button class="btn btn-outline btn-sm" onclick="triggerDevSheetPoll(false)" title="Đọc Google Sheet ngay & cập nhật trạng thái Dev về DB">
                    🔄 Sync Dev Sheet
                </button>
                <small id="dev-sync-stamp" style="color:var(--text-muted);font-size:0.78rem;display:inline-block;min-width:160px;"></small>
                <span class="user-chip"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>

                <!-- NOTIFICATION BELL -->
                <div class="notif-wrapper">
                    <button class="notif-bell" onclick="toggleNotif()" title="Thông báo">
                        🔔
                        <span class="notif-badge" id="notif-badge" style="display:none;">0</span>
                    </button>
                    <div class="notif-panel" id="notif-panel">
                        <div class="notif-panel-head">
                            <span>Thông báo</span>
                            <a href="javascript:void(0)" onclick="markAllRead()">Đánh dấu đã đọc</a>
                        </div>
                        <div id="notif-list">
                            <div class="notif-empty">Đang tải...</div>
                        </div>
                    </div>
                </div>

                <a href="?page=logout" class="btn btn-dark btn-sm">Đăng xuất</a>
            </div>
        </div>

        <div class="content">

            <!-- ============ TASKS SECTION ============ -->
            <section id="section-tasks" class="page-section">
                <div class="card">
                    <div class="section-title">
                        <span>Yêu cầu hệ thống</span>
                        <span id="task-count" style="color:var(--text-muted); font-size:0.85rem; font-weight:500;"></span>
                    </div>

                    <!-- ============ FILTER BAR (Google Sheets style) ============ -->
                    <div class="task-filter-bar" id="task-filter-bar">
                        <div class="tfb-row">
                            <input type="text" id="tf-q" class="form-control tfb-search"
                                   placeholder="🔍 Tìm Mã YC / Hệ thống / Người YC / Mô tả..."
                                   oninput="tfApplyFilters()">
                            <select id="tf-status"   class="form-control tfb-sel" onchange="tfApplyFilters()"><option value="">Trạng thái: Tất cả</option></select>
                            <select id="tf-unit"     class="form-control tfb-sel" onchange="tfApplyFilters()"><option value="">Đơn vị: Tất cả</option></select>
                            <select id="tf-priority" class="form-control tfb-sel" onchange="tfApplyFilters()"><option value="">Ưu tiên: Tất cả</option></select>
                            <select id="tf-tasktype" class="form-control tfb-sel" onchange="tfApplyFilters()"><option value="">Loại YC: Tất cả</option></select>
                            <select id="tf-dept"     class="form-control tfb-sel" onchange="tfApplyFilters()"><option value="">Phòng ban: Tất cả</option></select>
                            <select id="tf-system"   class="form-control tfb-sel" onchange="tfApplyFilters()"><option value="">Hệ thống: Tất cả</option></select>
                            <select id="tf-assignee" class="form-control tfb-sel" onchange="tfApplyFilters()"><option value="">BA phụ trách: Tất cả</option></select>
                            <select id="tf-devstatus" class="form-control tfb-sel" onchange="tfApplyFilters()"><option value="">Trạng thái Dev: Tất cả</option></select>
                        </div>
                        <div class="tfb-row tfb-row-secondary">
                            <label class="tfb-label">📅 Ngày tạo từ
                                <input type="date" id="tf-from" class="form-control tfb-date" onchange="tfApplyFilters()">
                            </label>
                            <label class="tfb-label">đến
                                <input type="date" id="tf-to" class="form-control tfb-date" onchange="tfApplyFilters()">
                            </label>
                            <button class="btn btn-outline btn-sm" onclick="tfReset()">↺ Reset</button>
                            <span id="tf-summary" class="tfb-summary"></span>
                            <span style="flex:1;"></span>
                            <button class="btn btn-outline btn-sm" onclick="tfExportCsv()" title="Xuất CSV theo bộ lọc đang áp dụng">⬇ Xuất CSV</button>
                        </div>
                    </div>

                    <div class="scroll-shell">
                        <div class="scroll-top" id="lead-scroll-top"><div id="lead-scroll-top-inner"></div></div>
                        <div class="scroll-body" id="lead-scroll-body">
                            <table id="lead-tasks-table">
                                <thead>
                                    <tr>
                                        <th>Mã YC</th>
                                        <th>Phòng ban / Người YC</th>
                                        <th>Hệ thống / Module</th>
                                        <th>Loại YC</th>
                                        <th>Ưu tiên</th>
                                        <th>Deadline</th>
                                        <th>Phụ trách</th>
                                        <th>Đơn vị</th>
                                        <th>Trạng thái</th>
                                        <th>Delay</th>
                                        <th>Dev</th>
                                        <th>Hành động</th>
                                    </tr>
                                </thead>
                                <tbody id="tasks-tbody">
                                    <tr><td colspan="12" style="text-align:center;padding:30px;color:var(--text-muted);">Đang tải dữ liệu...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ============ USERS SECTION ============ -->
            <section id="section-users" class="page-section" style="display:none;">

                <!-- ============ NHÓM (User Groups) ============ -->
                <div class="card" style="margin-bottom:14px;">
                    <div class="section-title">
                        <span>👥 Nhóm nhân sự</span>
                        <button onclick="ugOpenCreate()" class="btn btn-primary btn-sm">+ Tạo nhóm mới</button>
                    </div>
                    <p style="color:var(--text-muted);font-size:0.85rem;margin:4px 0 12px;">
                        Tạo nhóm để gom nhân sự (ví dụ "DION" gồm các Dev DION, "Kinkin" gồm các Dev Kinkin).
                        Khi BA hoặc Lead phân Dev, có thể chọn nhóm trước để filter danh sách Dev hiển thị.
                    </p>
                    <div id="ug-grid" class="ug-grid">
                        <div style="grid-column:1/-1;text-align:center;color:var(--text-muted);padding:18px;">Đang tải...</div>
                    </div>
                </div>

                <div class="card">
                    <div class="section-title">
                        <span>Nhân sự &amp; Đánh giá KPI</span>
                        <button onclick="openCreateUserModal()" class="btn btn-primary btn-sm">+ Thêm nhân viên</button>
                    </div>

                    <!-- Filter bar: chips + search -->
                    <div class="user-filter-bar">
                        <div class="user-chips" id="user-role-chips">
                            <button class="user-chip-btn active" data-role="all">Tất cả <span class="cnt" id="cnt-all">0</span></button>
                            <button class="user-chip-btn" data-role="lead">Lead BA <span class="cnt" id="cnt-lead">0</span></button>
                            <button class="user-chip-btn" data-role="ba">BA <span class="cnt" id="cnt-ba">0</span></button>
                            <button class="user-chip-btn" data-role="dev">Developer <span class="cnt" id="cnt-dev">0</span></button>
                        </div>
                        <div class="user-search">
                            <input type="text" class="form-control" id="user-search-input" placeholder="🔍 Tìm theo tên, username..." oninput="filterUsersUI()">
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Họ tên</th>
                                    <th>Nickname</th>
                                    <th>Username</th>
                                    <th>Vai trò</th>
                                    <th>Tổng việc</th>
                                    <th>Hoàn thành</th>
                                    <th style="background:#155724;">Đúng hạn</th>
                                    <th style="background:#842029;">Trễ hạn</th>
                                    <th>Hành động</th>
                                </tr>
                            </thead>
                            <tbody id="users-tbody">
                                <tr><td colspan="9" style="text-align:center;padding:30px;color:var(--text-muted);">Đang tải...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- ============ FORM (gộp Form Log + Cấu hình Form) ============ -->
            <section id="section-form" class="page-section" style="display:none;">
                <!-- Link bar -->
                <div class="card" style="margin-bottom:14px;">
                    <div class="section-title">
                        <span>Link Form công khai</span>
                        <a href="?page=public_form" target="_blank" class="btn btn-outline btn-sm">↗ Mở form</a>
                    </div>
                    <div class="form-link-bar">
                        <span class="form-link-label">Link gửi yêu cầu cho bộ phận:</span>
                        <code id="form-link-url"><?php echo (isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']==='on'?'https':'http').'://'.$_SERVER['HTTP_HOST'].BASE_PATH.'/?page=public_form'; ?></code>
                        <button class="btn btn-outline btn-sm" onclick="copyFormLink()">📋 Sao chép link</button>
                        <span class="copy-hint" id="copy-hint">✓ Đã sao chép!</span>
                    </div>
                </div>

                <!-- Cấu hình Form -->
                <div class="card">
                    <div class="section-title">
                        <span>Cấu hình Form công khai</span>
                    </div>

                    <!-- Form-level settings -->
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px;padding:14px;background:#fafafa;border:1px solid var(--border-color);">
                        <div class="form-group" style="grid-column: span 2; margin:0;">
                            <label>Tiêu đề form</label>
                            <input type="text" id="fc-title" class="form-control" placeholder="Yêu cầu hỗ trợ hệ thống">
                        </div>
                        <div class="form-group" style="grid-column: span 2; margin:0;">
                            <label>Mô tả form (sub-title)</label>
                            <input type="text" id="fc-desc" class="form-control" placeholder="Form này phục vụ cho việc...">
                        </div>
                        <div class="form-group" style="grid-column: span 2; margin:0;">
                            <label>Tin nhắn sau khi gửi thành công</label>
                            <input type="text" id="fc-success-msg" class="form-control" placeholder="Cảm ơn bạn đã gửi yêu cầu...">
                        </div>
                        <div style="grid-column: span 2; text-align:right;">
                            <button class="btn btn-primary btn-sm" onclick="fcSaveSettings()">💾 Lưu thông tin form</button>
                        </div>
                    </div>

                    <!-- Field list + add -->
                    <div class="section-title" style="border:none;padding:0;margin-top:8px;">
                        <span style="font-size:0.95rem;">Các trường (kéo thả ☰ để đổi thứ tự)</span>
                        <button class="btn btn-primary btn-sm" onclick="fcOpenFieldModal(null)">+ Thêm trường mới</button>
                    </div>

                    <div id="fc-fields-list" style="margin-top:10px;">
                        <div style="text-align:center;color:var(--text-muted);padding:30px;">Đang tải...</div>
                    </div>
                </div>
            </section>
            <!-- alias cũ để không vỡ link/bookmark -->
            <section id="section-formlog"  style="display:none;"></section>
            <section id="section-formconfig" style="display:none;"></section>

            <!-- ============ DEV STATS SECTION ============ -->
            <!-- "Hiệu suất Dev" đã được bỏ — Dev quản lý công việc qua Google Sheet riêng -->

            <!-- ============ WORKFLOWS SECTION (Quy trình tự động) ============ -->
            <section id="section-workflows" class="page-section" style="display:none;">
                <!-- View 1: List -->
                <div id="wf-list-view">
                    <div class="card">
                        <div class="section-title">
                            <span>Quy trình tự động</span>
                            <button class="btn btn-primary btn-sm" onclick="wbNewWorkflow()">+ Tạo quy trình mới</button>
                        </div>
                        <div style="padding:10px 0 16px;color:var(--text-muted);font-size:0.85rem;">
                            Cấu hình các quy trình xử lý yêu cầu — kéo-thả node, kết nối luồng, đặt điều kiện phân nhánh.
                        </div>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Mã</th>
                                        <th>Tên quy trình</th>
                                        <th>Nhóm</th>
                                        <th>Cấu trúc</th>
                                        <th>Trạng thái</th>
                                        <th>Tạo bởi</th>
                                        <th>Hành động</th>
                                    </tr>
                                </thead>
                                <tbody id="wf-list-tbody">
                                    <tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text-muted);">Đang tải...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- View 2: Editor -->
                <div id="wf-editor-view" style="display:none;">
                    <div class="card">
                        <div class="section-title">
                            <span>
                                <button class="btn btn-outline btn-sm" onclick="wbBackToList()" style="margin-right:8px;">← Danh sách</button>
                                Cấu hình quy trình
                            </span>
                            <button class="btn btn-primary btn-sm" onclick="wbSaveWorkflow()">💾 Lưu quy trình</button>
                        </div>

                        <!-- Thông tin chung -->
                        <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:10px;margin-bottom:14px;">
                            <div class="form-group">
                                <label>Mã quy trình *</label>
                                <input type="text" class="form-control" id="wb-code" placeholder="WF_BA_NEW">
                            </div>
                            <div class="form-group" style="grid-column: span 2;">
                                <label>Tên quy trình *</label>
                                <input type="text" class="form-control" id="wb-name" placeholder="Ví dụ: Quy trình BA xử lý YC chuẩn">
                            </div>
                            <div class="form-group">
                                <label>Trạng thái</label>
                                <select class="form-control" id="wb-status">
                                    <option value="draft">Bản nháp</option>
                                    <option value="active">Hoạt động</option>
                                    <option value="inactive">Tạm dừng</option>
                                </select>
                            </div>
                            <div class="form-group" style="grid-column: span 1;">
                                <label>Nhóm</label>
                                <input type="text" class="form-control" id="wb-group" placeholder="BA / Dev / HR...">
                            </div>
                            <div class="form-group" style="grid-column: span 3;">
                                <label>Mô tả</label>
                                <input type="text" class="form-control" id="wb-desc" placeholder="Mô tả ngắn về mục đích quy trình">
                            </div>
                        </div>

                        <!-- Toolbar -->
                        <div class="wb-toolbar">
                            <strong>Canvas:</strong>
                            <span class="hint">💡 Kéo node từ bên trái thả vào canvas · Click chấm tròn ở mép node để nối dây · Click node để cấu hình ở panel phải</span>
                        </div>

                        <!-- Builder shell -->
                        <div class="wb-shell">
                            <!-- Palette (left) -->
                            <div class="wb-palette">
                                <div class="wb-palette-title">Các node có sẵn</div>
                                <div class="wb-palette-item" data-type="start" onclick="wbAddNode('start')">
                                    <div class="icon">▶</div>
                                    <div><strong>Bắt đầu</strong><br><small style="color:var(--text-muted);">Khởi điểm</small></div>
                                </div>
                                <div class="wb-palette-item" data-type="task" onclick="wbAddNode('task')">
                                    <div class="icon">◆</div>
                                    <div><strong>Công việc</strong><br><small style="color:var(--text-muted);">Bước thực thi</small></div>
                                </div>
                                <div class="wb-palette-item" data-type="condition" onclick="wbAddNode('condition')">
                                    <div class="icon">?</div>
                                    <div><strong>Điều kiện</strong><br><small style="color:var(--text-muted);">Phân nhánh true/false</small></div>
                                </div>
                                <div class="wb-palette-item" data-type="approval" onclick="wbAddNode('approval')">
                                    <div class="icon">✓</div>
                                    <div><strong>Duyệt</strong><br><small style="color:var(--text-muted);">Chờ phê duyệt</small></div>
                                </div>
                                <div class="wb-palette-item" data-type="notify" onclick="wbAddNode('notify')">
                                    <div class="icon">🔔</div>
                                    <div><strong>Thông báo</strong><br><small style="color:var(--text-muted);">Gửi notification</small></div>
                                </div>
                                <div class="wb-palette-item" data-type="end" onclick="wbAddNode('end')">
                                    <div class="icon">■</div>
                                    <div><strong>Kết thúc</strong><br><small style="color:var(--text-muted);">Điểm kết thúc</small></div>
                                </div>
                            </div>

                            <!-- Canvas (center) -->
                            <div class="wb-canvas-wrap" id="wb-canvas-wrap">
                                <div class="wb-canvas" id="wb-canvas"></div>
                            </div>

                            <!-- Config panel (right) -->
                            <div class="wb-config" id="wb-config">
                                <div class="wb-empty">Chọn một node trên canvas để cấu hình.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ============ SYSTEMS SECTION (Danh sách hệ thống) ============ -->
            <?php include __DIR__ . '/_systems_section.php'; ?>

            <!-- Modal: Add/Edit field -->
            <div id="fcFieldModal" class="modal">
                <div class="modal-content" style="max-width:560px;">
                    <span class="close" onclick="closeModal('fcFieldModal')">&times;</span>
                    <h3 id="fc-field-modal-title">Thêm trường mới</h3>
                    <div class="form-group">
                        <label>Field Key (định danh, không đổi sau khi tạo) <span style="color:var(--danger-color);">*</span></label>
                        <input type="text" id="fc-fld-key" class="form-control" placeholder="vd: user_phone (chữ thường, gạch dưới)">
                        <small style="color:var(--text-muted);">Chỉ chứa chữ thường, số, gạch dưới. Bắt đầu bằng chữ.</small>
                    </div>
                    <div class="form-group">
                        <label>Label hiển thị <span style="color:var(--danger-color);">*</span></label>
                        <input type="text" id="fc-fld-label" class="form-control" placeholder="vd: Số điện thoại liên hệ">
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div class="form-group">
                            <label>Loại field</label>
                            <select id="fc-fld-type" class="form-control" onchange="fcToggleOptionsBox()">
                                <option value="text">Text (1 dòng)</option>
                                <option value="textarea">Textarea (nhiều dòng)</option>
                                <option value="dropdown">Dropdown (chọn 1)</option>
                                <option value="date">Date (ngày tháng)</option>
                                <option value="file">File upload</option>
                                <option value="section">Section divider (tiêu đề mục)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <label style="display:flex;align-items:center;gap:8px;font-weight:500;">
                                <input type="checkbox" id="fc-fld-required" style="width:18px;height:18px;"> Bắt buộc nhập
                            </label>
                            <label style="display:flex;align-items:center;gap:8px;font-weight:500;margin-top:4px;">
                                <input type="checkbox" id="fc-fld-visible" checked style="width:18px;height:18px;"> Hiển thị trên form
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Placeholder / mô tả phụ</label>
                        <input type="text" id="fc-fld-placeholder" class="form-control" placeholder="Hiển thị mờ trong ô input">
                    </div>
                    <div class="form-group" id="fc-fld-options-box" style="display:none;">
                        <label>Các option của dropdown (mỗi option 1 dòng)</label>
                        <textarea id="fc-fld-options" class="form-control" rows="6" placeholder="Option 1&#10;Option 2&#10;Option 3"></textarea>
                    </div>
                    <button class="btn btn-primary" style="width:100%;" onclick="fcSaveField()">💾 Lưu trường</button>
                </div>
            </div>

            <!-- ============ BOT SYNC SECTION (Đồng bộ Google Sheet) ============ -->
            <section id="section-botsync" class="page-section" style="display:none;">
                <div class="card">
                    <div class="section-title">
                        <span>Bot dong bo Google Sheet</span>
                        <div style="display:flex;gap:8px;">
                            <button class="btn btn-outline btn-sm" onclick="bsTriggerImportNow()" title="Doc tab 'Tong quan' va day vao DB">Import tu Sheet</button>
                            <button class="btn btn-primary btn-sm" onclick="bsTriggerSyncNow()">Chay dong bo ngay</button>
                        </div>
                    </div>
                    <p style="color:var(--text-muted);font-size:0.86rem;margin:6px 0 16px;">
                        <strong>Day len (Sync):</strong> tu dong dua danh sach task cua tung nhan vien sang Google Sheet hang ngay.<br>
                        <strong>Keo ve (Import):</strong> doc tab "Tong quan" tren Sheet va dong bo vao DB. Idempotent theo Ma YC.<br>
                        <strong>Dev Poller:</strong> tu dong doc trang thai tu Dev Sheet moi <span id="bs-poller-interval-display">15</span> giay va cap nhat vao he thong.
                    </p>

                    <!-- Status box -->
                    <div id="bs-status" class="bs-status">
                        <div style="text-align:center;color:var(--text-muted);">Dang tai...</div>
                    </div>

                    <!-- ======== BA SHEET CONFIG ======== -->
                    <h4 style="margin:20px 0 10px;padding-bottom:6px;border-bottom:2px solid var(--primary-color);color:var(--primary-color);">BA Sheet (Tong quan + per-user tabs) — Realtime Webhook</h4>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;padding:16px;background:#fafafa;border:1px solid var(--border-color);">
                        <div class="form-group" style="grid-column: span 2; margin:0;">
                            <label>Link Google Sheet BA</label>
                            <input type="text" id="bs-sheet-url" class="form-control" placeholder="https://docs.google.com/spreadsheets/d/...">
                            <small style="color:var(--text-muted);">Sheet ID se tu trich tu URL. Dung cho chuc nang Import tu Sheet.</small>
                        </div>
                        <div class="form-group" style="grid-column: span 2; margin:0;">
                            <label>Webhook URL (Google Apps Script) <span style="color:var(--danger-color);">*</span></label>
                            <input type="text" id="bs-webhook-url" class="form-control" placeholder="https://script.google.com/macros/s/.../exec">
                            <small style="color:var(--text-muted);">Moi khi task thay doi, he thong tu dong gui webhook den Apps Script de ghi vao BA Sheet realtime.</small>
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label>Gio chay auto-sync (24h)</label>
                            <div style="display:flex;gap:6px;align-items:center;">
                                <input type="number" id="bs-hour" class="form-control" min="0" max="23" value="23" style="width:80px;text-align:center;">
                                <span>gio</span>
                                <input type="number" id="bs-minute" class="form-control" min="0" max="59" value="0" style="width:80px;text-align:center;">
                                <span>phut</span>
                            </div>
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label>&nbsp;</label>
                            <label style="display:flex;align-items:center;gap:8px;font-weight:500;padding:8px 0;">
                                <input type="checkbox" id="bs-enabled" style="width:20px;height:20px;"> Bat bot dong bo BA Sheet
                            </label>
                        </div>
                    </div>

                    <!-- ======== DEV SHEET CONFIG ======== -->
                    <h4 style="margin:20px 0 10px;padding-bottom:6px;border-bottom:2px solid #198754;color:#198754;">Dev Sheet (Theo doi trang thai Dev)</h4>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;padding:16px;background:#f0fff4;border:1px solid #a3cfbb;">
                        <div class="form-group" style="grid-column: span 2; margin:0;">
                            <label>Link Google Sheet Dev <span style="color:var(--danger-color);">*</span></label>
                            <input type="text" id="bs-dev-sheet-url" class="form-control" placeholder="https://docs.google.com/spreadsheets/d/...">
                            <small style="color:var(--text-muted);">Sheet rieng cho Dev workflow. Bot se tu tao tab theo tuan.</small>
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label>Tan suat poll Dev Sheet</label>
                            <div style="display:flex;gap:6px;align-items:center;">
                                <input type="number" id="bs-poller-interval" class="form-control" min="10" max="300" value="15" style="width:100px;text-align:center;">
                                <span>giay</span>
                                <small style="color:var(--text-muted);margin-left:8px;">(10-300 giay)</small>
                            </div>
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label>&nbsp;</label>
                            <label style="display:flex;align-items:center;gap:8px;font-weight:500;padding:8px 0;">
                                <input type="checkbox" id="bs-poller-enabled" style="width:20px;height:20px;"> Bat Dev Sheet Poller
                            </label>
                        </div>
                    </div>

                    <!-- ======== BOT / CREDENTIALS CONFIG ======== -->
                    <h4 style="margin:20px 0 10px;padding-bottom:6px;border-bottom:2px solid #6f42c1;color:#6f42c1;">Google Service Account (Bot)</h4>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;padding:16px;background:#f8f5ff;border:1px solid #d4c5f9;">
                        <div class="form-group" style="grid-column: span 2; margin:0;">
                            <label>Email cua Bot (Service Account)</label>
                            <input type="text" id="bs-bot-email" class="form-control" placeholder="bot@project.iam.gserviceaccount.com" readonly style="background:#f0f0f0;">
                            <small style="color:var(--text-muted);">Tu dong lay tu file credentials. Ban can share ca 2 Google Sheet cho email nay voi quyen Editor.</small>
                        </div>
                        <div class="form-group" style="grid-column: span 2; margin:0;">
                            <label>Thay the bot (Upload file credentials JSON moi)</label>
                            <div style="display:flex;gap:10px;align-items:center;">
                                <input type="file" id="bs-cred-file" class="form-control" accept=".json" style="flex:1;">
                                <button class="btn btn-outline btn-sm" onclick="bsUploadCredentials()">Upload &amp; ap dung</button>
                            </div>
                            <div id="bs-cred-info" style="margin-top:8px;font-size:0.85rem;"></div>
                        </div>
                    </div>

                    <!-- Save all -->
                    <div style="text-align:right; margin-top:16px;">
                        <button class="btn btn-primary" onclick="bsSaveSettings()" style="padding:10px 30px;">Luu tat ca cau hinh</button>
                    </div>
                </div>
            </section>

            <!-- KPI Dashboard đã chuyển sang link external (xem sidebar "Phân tích KPI ↗") -->

            <!-- ============ NOTIFICATIONS SECTION ============ -->
            <section id="section-notifications" class="page-section" style="display:none;">
                <div class="card">
                    <div class="section-title">
                        <span>Thông báo của tôi</span>
                        <a href="javascript:void(0)" onclick="markAllRead()" style="font-size:0.82rem;font-weight:500;">Đánh dấu tất cả đã đọc</a>
                    </div>
                    <div id="notif-fullpage">
                        <div class="notif-empty">Đang tải...</div>
                    </div>
                </div>
            </section>

        </div>
    </main>
</div>

<!-- ============ MODALS ============ -->

<!-- Modal: Bắt đầu code (gộp chọn dev + mô tả + module/feature + date range) -->
<?php include __DIR__ . '/_start_coding_modal.php'; ?>

<!-- Modal: Assign Task -->
<div id="assignModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('assignModal')">&times;</span>
        <h3 id="assign-title">Phân công công việc</h3>
        <div class="form-group">
            <label>Người phụ trách (BA)</label>
            <select id="assign-ba" class="form-control">
                <option value="">-- Chưa gán --</option>
            </select>
        </div>
        <div class="form-group">
            <label>Độ ưu tiên BA</label>
            <select id="assign-priority" class="form-control">
                <option value="4. Gấp - Quan trọng">4. Gấp - Quan trọng</option>
                <option value="3. Không gấp - Quan trọng">3. Không gấp - Quan trọng</option>
                <option value="2. Gấp - Không quan trọng">2. Gấp - Không quan trọng</option>
                <option value="1. Không gấp - Không quan trọng" selected>1. Không gấp - Không quan trọng</option>
            </select>
        </div>
        <div class="form-group">
            <label>Đơn vị thực hiện</label>
            <select id="assign-unit" class="form-control">
                <option value="DION">DION</option>
                <option value="Kinkin">Kinkin</option>
            </select>
        </div>
        <div class="form-group">
            <label>Phân loại yêu cầu</label>
            <select id="assign-classification" class="form-control">
                <option value="">-- Chưa phân loại --</option>
                <option value="Hệ thống - Thực hiện">Hệ thống - Thực hiện</option>
                <option value="Người dùng - Lỗi">Người dùng - Lỗi</option>
            </select>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
            <div class="form-group" style="margin:0;">
                <label>BA ước tính — Từ ngày <span style="color:var(--text-muted);font-weight:400;">(nội bộ)</span></label>
                <input type="date" id="assign-ba-start" class="form-control">
            </div>
            <div class="form-group" style="margin:0;">
                <label>Đến ngày</label>
                <input type="date" id="assign-ba-end" class="form-control">
            </div>
        </div>
        <div class="form-group" style="margin-top:10px;">
            <label>Ghi chú phân công <span style="color:var(--text-muted);font-weight:400;">(hiển thị trong chi tiết task của BA nhân viên)</span></label>
            <textarea id="assign-note" class="form-control" rows="2" placeholder="Lưu ý đặc biệt, ưu tiên xử lý, liên hệ ai..."></textarea>
        </div>
        <div class="form-group">
            <label style="display:flex;align-items:center;gap:8px;font-weight:500;">
                <input type="checkbox" id="assign-auto-progress" checked>
                Tự động chuyển sang "Dion - đang xử lý"
            </label>
        </div>
        <button class="btn btn-primary" style="width:100%;" onclick="submitAssign()">Lưu phân công</button>
    </div>
</div>

<!-- Modal: User Form -->
<div id="userModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('userModal')">&times;</span>
        <h3 id="user-modal-title">Nhân viên</h3>
        <div id="username-group" class="form-group">
            <label>Username</label>
            <input type="text" id="um-username" class="form-control">
        </div>
        <div class="form-group">
            <label>Họ và tên</label>
            <input type="text" id="um-fullname" class="form-control" required oninput="umAutoFillNickname()">
        </div>
        <div class="form-group">
            <label>Tên (nickname) <span style="color:var(--danger-color);">*</span>
                <small style="color:var(--text-muted);font-weight:400;">— ghi vào sheet, vd "Minh", "Phương Anh"</small>
            </label>
            <input type="text" id="um-nickname" class="form-control" required>
        </div>
        <div class="form-group">
            <label>Mật khẩu <small id="pass-hint" style="color:var(--text-muted);"></small></label>
            <input type="password" id="um-password" class="form-control">
        </div>
        <div class="form-group">
            <label>Vai trò</label>
            <select id="um-role" class="form-control">
                <option value="ba">Nhân viên BA</option>
                <option value="lead">Lead BA</option>
                <option value="dev">Developer (không login, chỉ làm label trên sheet)</option>
            </select>
        </div>
        <button class="btn btn-primary" style="width:100%;" onclick="submitUserForm()">Lưu</button>
    </div>
</div>

<!-- Modal: Assign Dev -->
<div id="devAssignModal" class="modal">
    <div class="modal-content" style="max-width:540px;">
        <span class="close" onclick="closeModal('devAssignModal')">&times;</span>
        <h3 id="dev-assign-title">Giao cho Dev</h3>
        <div style="background:#fff3cd;border-left:4px solid #ffc107;padding:10px 14px;margin-bottom:18px;font-size:0.85rem;">
            ⚡ Task này đang ở giai đoạn <strong>Bắt đầu code</strong>. Giao cho dev và điền mô tả chi tiết để dev thực hiện.
        </div>
        <div class="form-group">
            <label>Lọc theo nhóm <span style="color:var(--text-muted);font-weight:400;">(tuỳ chọn)</span></label>
            <select id="dev-assign-group" class="form-control" onchange="reloadDevAssignList()">
                <option value="">-- Tất cả nhóm --</option>
            </select>
        </div>
        <div class="form-group">
            <label>Developer phụ trách</label>
            <select id="dev-assign-dev" class="form-control">
                <option value="">-- Chưa gán dev --</option>
            </select>
        </div>
        <div class="form-group">
            <label>Mô tả từ BA cho Dev <span style="color:var(--primary-color);">*</span></label>
            <textarea id="dev-assign-desc" class="form-control" rows="4"
                placeholder="Mô tả chi tiết yêu cầu kỹ thuật: API cần làm, màn hình cần thay đổi, logic xử lý, edge cases..."></textarea>
        </div>
        <div class="form-group">
            <label>Hạn hoàn thành cho Dev <span style="color:var(--text-muted);font-weight:400;">(tuỳ chọn)</span></label>
            <input type="datetime-local" id="dev-assign-deadline" class="form-control">
        </div>
        <button class="btn btn-primary" style="width:100%;" onclick="submitDevAssign()">💾 Lưu & Giao Dev</button>
    </div>
</div>

<script>
const API = '<?php echo BASE_PATH; ?>/api/data.php';
const SESSION_USER_ID = <?php echo intval($_SESSION['user_id']); ?>;
const SESSION_ROLE = '<?php echo $_SESSION['role']; ?>';

let currentTaskId = null;
let currentUserAction = 'create';
let currentUserId = null;

// ======= UTILS =======
function esc(s) { if(s===null||s===undefined) return ''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function formatDate(d) { if(!d) return '-'; const dt = new Date(d.replace(' ','T')); return dt.toLocaleDateString('vi-VN') + ' ' + dt.toLocaleTimeString('vi-VN',{hour:'2-digit',minute:'2-digit'}); }
function formatDateOnly(d) { if(!d) return '-'; return new Date(d).toLocaleDateString('vi-VN'); }
function timeAgo(d) {
    if(!d) return '';
    const diff = (Date.now() - new Date(d.replace(' ','T'))) / 1000;
    if(diff < 60) return 'vừa xong';
    if(diff < 3600) return Math.floor(diff/60) + ' phút';
    if(diff < 86400) return Math.floor(diff/3600) + ' giờ';
    return Math.floor(diff/86400) + ' ngày';
}
function closeModal(id) { document.getElementById(id).style.display = 'none'; }
window.onclick = e => { if(e.target.classList.contains('modal')) e.target.style.display='none'; }

// ======= SECTION SWITCHING =======
function switchSection(name) {
    document.querySelectorAll('.page-section').forEach(s => s.style.display = 'none');
    document.querySelectorAll('.sidebar-item').forEach(b => b.classList.remove('active'));
    document.getElementById('section-' + name).style.display = 'block';
    event && event.currentTarget && event.currentTarget.classList.add('active');

    const titles = {
        tasks:        'Danh sách yêu cầu hệ thống',
        users:        'Nhân sự',
        systems:      'Danh sách hệ thống',
        workflows:    'Quy trình tự động',
        form:         'Form công khai & Cấu hình',
        botsync:      'Bot đồng bộ Google Sheet',
        notifications:'Thông báo của tôi'
    };
    // redirect aliases cũ → form
    if(name === 'formconfig' || name === 'formlog') name = 'form';
    document.getElementById('page-title').textContent = titles[name] || '';

    if(name === 'tasks')        loadTasks();
    if(name === 'users')        loadUsers();
    if(name === 'systems')      sysBackToList();
    if(name === 'workflows')    { wbBackToList(); }
    if(name === 'form')         fcLoadConfig();
    if(name === 'botsync')      bsLoadSettings();
    if(name === 'notifications')loadNotifPage();
}

// ======= BADGES =======
function statusBadge(s) {
    const map = {
        'Chờ tiếp nhận':         'badge-new',
        'Todo - chờ xác nhận với Sếp': 'badge-pending',
        'Dion - đang xử lý':           'badge-progress',
        'Dion - Chờ nghiệm thu':       'badge-medium',
        'Kinkin nghiệm thu':           'badge-done',
        'Huỷ':                          'badge-high'
    };
    return `<span class="badge ${map[s]||'badge-pending'}">${esc(s)}</span>`;
}
function priorityBadge(p) {
    const map = {
        '4. Gấp - Quan trọng': 'badge-high',
        '3. Không gấp - Quan trọng': 'badge-medium',
        '2. Gấp - Không quan trọng': 'badge-low',
        '1. Không gấp - Không quan trọng': 'badge-pending'
    };
    return p ? `<span class="badge ${map[p]||'badge-pending'}">${esc(p)}</span>` : '-';
}
function unitBadge(u) {
    if(!u) return '-';
    return `<span class="badge ${u==='DION'?'badge-progress':'badge-medium'}">${esc(u)}</span>`;
}
function devStatusBadge(s) {
    if(!s) return '<span style="color:var(--text-muted);font-size:0.8rem;">—</span>';
    const map = { 'Chờ dev nhận':'badge-pending','Dev đang làm':'badge-progress','Dev đã xong':'badge-done','Cần sửa':'badge-high' };
    return `<span class="badge ${map[s]||'badge-pending'}">${esc(s)}</span>`;
}
function delayCell(t) {
    if(t.delay_hours === null || t.delay_hours === undefined) return '<span style="color:var(--text-muted);">-</span>';
    const h = parseFloat(t.delay_hours);
    if(h === 0) return '<span style="color:var(--success-color);font-weight:600;" title="Đúng hạn">0 — đúng hạn</span>';
    // h > 0: tính ngày / giờ:phút
    const totalMin = Math.round(h * 60);
    let label;
    if(totalMin < 1440) { // < 1 ngày
        const hh = Math.floor(totalMin / 60);
        const mm = totalMin % 60;
        label = '+' + hh + 'g' + (mm > 0 ? mm + 'p' : '');
    } else {
        const days = Math.floor(totalMin / 1440);
        const remH  = Math.floor((totalMin % 1440) / 60);
        label = '+' + days + ' ngày' + (remH > 0 ? ' ' + remH + 'g' : '');
    }
    return `<span style="color:var(--danger-color);font-weight:600;" title="Quá hạn">${label}</span>`;
}

// ======= NEXT-STEP WORKFLOW =======
const WORKFLOW = {
    'Chờ tiếp nhận':         { next: 'Todo - chờ xác nhận với Sếp', label: 'Tiếp nhận' },
    'Todo - chờ xác nhận với Sếp': { next: 'Dion - đang xử lý',     label: 'Bắt đầu code' },
    'Dion - đang xử lý':           { next: 'Dion - Chờ nghiệm thu', label: 'Đã code xong' },
    'Dion - Chờ nghiệm thu':       { next: 'Kinkin nghiệm thu',     label: 'Nghiệm thu' },
    'Kinkin nghiệm thu':           { next: null,                     label: 'Đã hoàn tất' },
    'Huỷ':                          { next: null,                     label: 'Đã huỷ' }
};
const WORKFLOW_BACK = {
    'Todo - chờ xác nhận với Sếp': 'Chờ tiếp nhận',
    'Dion - đang xử lý':           'Todo - chờ xác nhận với Sếp',
    'Dion - Chờ nghiệm thu':       'Dion - đang xử lý',
    'Kinkin nghiệm thu':           'Dion - Chờ nghiệm thu',
};

function nextStepCell(t) {
    const deleteBtn = `<button class="btn-icon btn-delete-task" onclick="openDeleteTaskModal(${t.id}, '${esc(t.ma_yc || '#'+t.id)}', '${esc(t.system_name||'')}')" title="Xoá YC vĩnh viễn">🗑</button>`;

    // Trạng thái mới — chưa phân công: Lead có 2 lựa chọn: tiếp nhận trực tiếp HOẶC phân công cho BA khác
    if(t.status === 'Chờ tiếp nhận') {
        return `<div class="row-actions">
            <button class="btn-next" onclick="openClaimModal(${t.id})" title="Tiếp nhận YC này">→ Tiếp nhận</button>
            <button class="btn btn-outline btn-icon" style="color:#ff6b00;border-color:#ff6b00;"
                onclick="openAssign(${t.id}, '${esc(t.system_name)}', null, '1. Không gấp - Không quan trọng', 'DION', '')"
                title="Phân công cho BA khác">⚙</button>
            <button class="btn-cancel-flow" onclick="confirmNext(${t.id}, 'cancel')" title="Huỷ">✗</button>
            ${deleteBtn}
        </div>`;
    }
    // Test workflow: khi task ở "Dion - Chờ nghiệm thu" → thay nút next bình thường bằng buttons test
    if(t.status === 'Dion - Chờ nghiệm thu') {
        const ts = t.test_status || '';
        let testBtns = '';
        if(ts === '' || ts === null) {
            testBtns = `<button class="btn-next" style="background:#0d6efd;border-color:#0d6efd;" onclick="testStart(${t.id})" title="Bắt đầu kiểm thử">▶ Bắt đầu test</button>`;
        } else if(ts === 'Đang test') {
            testBtns = `<button class="btn-next" style="background:#198754;border-color:#198754;" onclick="testDonePending(${t.id})" title="Hoàn tất test, chờ user nghiệm thu">✓ Test xong</button>
                        <button class="btn-cancel-flow" onclick="openBugModal(${t.id}, '${esc(t.ma_yc||'#'+t.id)}')" title="Phát hiện lỗi">✗ Phát hiện lỗi</button>`;
        } else if(ts === 'hoàn thành test chờ nghiệm thu') {
            testBtns = `<button class="btn-next" style="background:#198754;border-color:#198754;" onclick="testAccepted(${t.id})" title="User đã nghiệm thu OK">✓ Đã nghiệm thu</button>
                        <button class="btn-cancel-flow" onclick="openBugModal(${t.id}, '${esc(t.ma_yc||'#'+t.id)}')" title="Phát hiện lỗi sau test">✗ Phát hiện lỗi</button>`;
        }
        return `<div class="row-actions">
            ${testBtns}
            <button class="btn btn-outline btn-icon" onclick="confirmBack(${t.id}, '${esc(t.status)}')" title="Quay lại: Dion - đang xử lý" style="color:var(--text-secondary);">←</button>
            ${deleteBtn}
        </div>`;
    }

    const wf = WORKFLOW[t.status];
    if(!wf) return `<div class="row-actions">-${deleteBtn}</div>`;
    let html = '<div class="row-actions">';
    if(wf.next) {
        // "Bắt đầu code" → mở modal gộp (chọn dev + mô tả + module/feature + date range)
        if(t.status === 'Todo - chờ xác nhận với Sếp') {
            html += `<button class="btn-next" onclick="openStartCoding(${t.id})" title="Bắt đầu code">▶ ${esc(wf.label)}</button>`;
        } else {
            html += `<button class="btn-next" onclick="confirmNext(${t.id}, 'next')" title="Chuyển: ${esc(wf.next)}">→ ${esc(wf.label)}</button>`;
        }
    } else {
        html += `<span style="color:var(--text-muted); font-size:0.82rem; font-style:italic;">${esc(wf.label)}</span>`;
    }

    // Nút quay lại bước trước
    if(WORKFLOW_BACK[t.status]) {
        html += `<button class="btn btn-outline btn-icon" onclick="confirmBack(${t.id}, '${esc(t.status)}')"
                    title="Quay lại: ${esc(WORKFLOW_BACK[t.status])}" style="color:var(--text-secondary);">←</button>`;
    }

    if(t.status !== 'Huỷ' && t.status !== 'Kinkin nghiệm thu') {
        html += `<button class="btn-cancel-flow" onclick="confirmNext(${t.id}, 'cancel')" title="Huỷ task">✗</button>`;
    } else if(t.status === 'Huỷ') {
        html += `<button class="btn-outline btn-icon" onclick="confirmNext(${t.id}, 'reopen')" title="Mở lại">↺</button>`;
    }
    html += `<button class="btn-outline btn-icon" onclick="openAssign(${t.id}, '${esc(t.system_name)}', ${t.assignee_id||'null'}, '${esc(t.priority_ba||'1. Không gấp - Không quan trọng')}', '${esc(t.implementing_unit||'DION')}', '${esc(t.classification||'')}')" title="Phân công BA">⚙</button>`;
    html += deleteBtn;
    html += '</div>';
    return html;
}

// ======= DELETE TASK (Lead only) =======
let _DELETE_TASK_ID = null;
function openDeleteTaskModal(taskId, maYc, sysName) {
    _DELETE_TASK_ID = taskId;
    let modal = document.getElementById('deleteTaskModal');
    if(!modal) {
        modal = document.createElement('div');
        modal.id = 'deleteTaskModal';
        modal.className = 'modal';
        modal.innerHTML = `
            <div class="modal-content" style="max-width:460px;border-top-color:var(--danger-color);">
                <span class="close" onclick="closeModal('deleteTaskModal')">&times;</span>
                <h3 style="color:var(--danger-color);">⚠ Xác nhận xoá YC</h3>
                <div style="background:#f8d7da;border-left:4px solid var(--danger-color);padding:14px 16px;margin:16px 0;font-size:0.92rem;line-height:1.6;">
                    Bạn sắp xoá <strong id="dt-mayc" style="color:var(--danger-color);"></strong>
                    — <span id="dt-sys"></span>.<br>
                    <strong>Hành động này KHÔNG THỂ HOÀN TÁC.</strong> Toàn bộ dữ liệu YC, lịch sử dev, breaktask, file đính kèm liên quan sẽ bị xoá vĩnh viễn.
                </div>
                <div class="form-group">
                    <label style="font-size:0.86rem;">Gõ <code id="dt-confirm-code" style="background:#fff3cd;padding:2px 6px;font-weight:700;"></code> để xác nhận:</label>
                    <input type="text" id="dt-confirm-input" class="form-control" placeholder="Gõ Mã YC ở trên..." oninput="dtCheckConfirm()">
                </div>
                <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:18px;">
                    <button class="btn btn-outline" onclick="closeModal('deleteTaskModal')">Huỷ</button>
                    <button id="dt-confirm-btn" class="btn" disabled
                        style="background:var(--danger-color);color:#fff;border:1px solid var(--danger-color);opacity:.6;cursor:not-allowed;"
                        onclick="submitDeleteTask()">🗑 Xoá vĩnh viễn</button>
                </div>
            </div>`;
        document.body.appendChild(modal);
    }
    document.getElementById('dt-mayc').textContent = maYc;
    document.getElementById('dt-sys').textContent  = sysName || '(không rõ hệ thống)';
    document.getElementById('dt-confirm-code').textContent = maYc;
    document.getElementById('dt-confirm-input').value = '';
    dtCheckConfirm();
    modal.style.display = 'block';
}
function dtCheckConfirm() {
    const expected = document.getElementById('dt-confirm-code').textContent;
    const got = document.getElementById('dt-confirm-input').value.trim();
    const btn = document.getElementById('dt-confirm-btn');
    const ok = got === expected;
    btn.disabled = !ok;
    btn.style.opacity = ok ? '1' : '.6';
    btn.style.cursor  = ok ? 'pointer' : 'not-allowed';
}
function submitDeleteTask() {
    const btn = document.getElementById('dt-confirm-btn');
    btn.disabled = true; btn.textContent = '⏳ Đang xoá...';
    const fd = new FormData();
    fd.append('action', 'delete_task');
    fd.append('task_id', _DELETE_TASK_ID);
    fetch(API, { method:'POST', body:fd }).then(r => r.json()).then(res => {
        if(res.success) {
            closeModal('deleteTaskModal');
            // Xoá khỏi cache + render lại
            _TASKS_CACHE = _TASKS_CACHE.filter(t => t.id != _DELETE_TASK_ID);
            tfApplyFilters();
            loadNotifBell();
        } else {
            btn.disabled = false; btn.textContent = '🗑 Xoá vĩnh viễn';
            alert(res.message || 'Lỗi khi xoá');
        }
    });
}

// ======= TEST WORKFLOW =======
function testAction(taskId, action, payload) {
    const fd = new FormData();
    fd.append('action', action);
    fd.append('task_id', taskId);
    if(payload) Object.entries(payload).forEach(([k,v]) => fd.append(k, v));
    return fetch(API, { method:'POST', body:fd }).then(r => r.json());
}
function testStart(taskId) {
    if(!confirm('Bắt đầu test task này? Cột "Người thực hiện test" trên sheet sẽ ghi tên bạn.')) return;
    testAction(taskId, 'test_start').then(res => {
        if(res.success) loadTasks();
        else alert(res.message || 'Lỗi');
    });
}
function testDonePending(taskId) {
    if(!confirm('Đánh dấu test xong và chờ user nghiệm thu?')) return;
    testAction(taskId, 'test_done_pending_acceptance').then(res => {
        if(res.success) loadTasks();
        else alert(res.message || 'Lỗi');
    });
}
function testAccepted(taskId) {
    if(!confirm('User đã nghiệm thu OK?\nTask sẽ chuyển sang "Kinkin nghiệm thu" (kết thúc).')) return;
    testAction(taskId, 'test_accepted').then(res => {
        if(res.success) loadTasks();
        else alert(res.message || 'Lỗi');
    });
}

// Modal log lỗi
function openBugModal(taskId, maYc) {
    let modal = document.getElementById('bugLogModal');
    if(!modal) {
        modal = document.createElement('div');
        modal.id = 'bugLogModal';
        modal.className = 'modal';
        modal.innerHTML = `
            <div class="modal-content" style="max-width:520px;border-top-color:var(--danger-color);">
                <span class="close" onclick="closeModal('bugLogModal')">&times;</span>
                <h3 style="color:var(--danger-color);">🐛 Báo lỗi & gửi lại Dev</h3>
                <div id="bug-meta" style="background:#f8d7da;border-left:4px solid var(--danger-color);padding:10px 14px;font-size:0.86rem;margin:14px 0;"></div>
                <div class="form-group">
                    <label>Mô tả lỗi <span style="color:var(--danger-color);">*</span></label>
                    <textarea id="bug-desc" class="form-control" rows="5"
                        placeholder="Mô tả chi tiết lỗi: thao tác → kỳ vọng → thực tế. Có thể paste link screenshot."></textarea>
                    <small style="color:var(--text-muted);">Ghi chú này sẽ được append vào cột "Ghi chú" trên sheet để Dev xem.</small>
                </div>
                <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:14px;">
                    <button class="btn btn-outline" onclick="closeModal('bugLogModal')">Huỷ</button>
                    <button class="btn" id="bug-submit-btn" style="background:var(--danger-color);color:#fff;border:1px solid var(--danger-color);" onclick="submitBugReport()">
                        🐛 Báo lỗi & trả về Dev
                    </button>
                </div>
            </div>`;
        document.body.appendChild(modal);
    }
    document.getElementById('bug-meta').innerHTML = `Task <strong style="color:var(--danger-color);">${esc(maYc)}</strong> sẽ:<br>
        • Quay về <strong>Dion - đang xử lý</strong><br>
        • Trạng thái Dev → <strong>cần sửa lại</strong><br>
        • Trạng thái test reset về trống<br>
        • Mô tả lỗi append vào ghi chú`;
    document.getElementById('bug-desc').value = '';
    document.getElementById('bugLogModal').dataset.taskId = taskId;
    modal.style.display = 'block';
    setTimeout(() => document.getElementById('bug-desc').focus(), 50);
}
function submitBugReport() {
    const taskId = document.getElementById('bugLogModal').dataset.taskId;
    const desc   = document.getElementById('bug-desc').value.trim();
    if(!desc) { alert('Vui lòng mô tả lỗi'); return; }
    const btn = document.getElementById('bug-submit-btn');
    btn.disabled = true; btn.textContent = '⏳ Đang gửi...';
    testAction(taskId, 'test_report_bug', { bug_description: desc }).then(res => {
        btn.disabled = false; btn.textContent = '🐛 Báo lỗi & trả về Dev';
        if(res.success) {
            closeModal('bugLogModal');
            loadTasks();
        } else {
            alert(res.message || 'Lỗi khi báo bug');
        }
    });
}

let pendingAction = null;
function confirmNext(taskId, direction) {
    fetch(API + '?action=get_tasks').then(r=>r.json()).then(tasks => {
        const task = tasks.find(t => t.id == taskId);
        if(!task) { alert('Không tìm thấy task'); return; }

        let title, body, btn, btnClass;
        if(direction === 'next') {
            const wf = WORKFLOW[task.status];
            if(!wf || !wf.next) { alert('Task đã ở bước cuối'); return; }
            title = 'Chuyển bước công việc';
            body = `Chuyển <strong>${esc(task.ma_yc)}</strong> — <em>${esc(task.system_name)}</em><br><br>
                    ${statusBadge(task.status)} <span class="arrow">→</span> ${statusBadge(wf.next)}`;
            btn = '→ Xác nhận chuyển';
            btnClass = 'btn-success';
        } else if(direction === 'cancel') {
            title = 'Huỷ công việc';
            body = `Huỷ <strong>${esc(task.ma_yc)}</strong> — <em>${esc(task.system_name)}</em>?<br><br>Trạng thái sẽ chuyển sang <strong style="color:var(--danger-color);">Huỷ</strong>.`;
            btn = '✗ Xác nhận huỷ';
            btnClass = 'btn-primary';
        } else { // reopen
            title = 'Mở lại công việc';
            body = `Mở lại <strong>${esc(task.ma_yc)}</strong>? Trạng thái sẽ về <strong>Todo - chờ xác nhận với Sếp</strong>.`;
            btn = '↺ Mở lại';
            btnClass = 'btn-success';
        }

        const backdrop = document.createElement('div');
        backdrop.className = 'confirm-backdrop';
        backdrop.innerHTML = `
            <div class="confirm-box">
                <div class="head"><h4>${title}</h4></div>
                <div class="body">${body}</div>
                <div class="actions">
                    <button class="btn btn-outline btn-sm" onclick="this.closest('.confirm-backdrop').remove()">Huỷ bỏ</button>
                    <button class="btn ${btnClass} btn-sm" id="do-confirm">${btn}</button>
                </div>
            </div>`;
        document.body.appendChild(backdrop);
        backdrop.addEventListener('click', e => { if(e.target===backdrop) backdrop.remove(); });
        backdrop.querySelector('#do-confirm').addEventListener('click', () => {
            backdrop.remove();
            doNextStep(taskId, direction);
        });
    });
}

function doNextStep(taskId, direction) {
    const fd = new FormData();
    fd.append('action', 'next_step');
    fd.append('task_id', taskId);
    fd.append('direction', direction);
    fetch(API, { method:'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if(res.success) {
                loadTasks();
                loadNotifBell();
            } else {
                alert(res.message || 'Có lỗi xảy ra');
            }
        });
}

function confirmBack(taskId, currentStatus) {
    const prevStatus = WORKFLOW_BACK[currentStatus];
    if(!prevStatus) { alert('Không thể quay lại từ bước này'); return; }
    const backdrop = document.createElement('div');
    backdrop.className = 'confirm-backdrop';
    backdrop.innerHTML = `
        <div class="confirm-box">
            <div class="head"><h4>Quay lại bước trước</h4></div>
            <div class="body">
                Quay lại: ${statusBadge(currentStatus)} <span class="arrow">→</span> ${statusBadge(prevStatus)}<br><br>
                <small style="color:var(--text-muted);">Các mốc thời gian tương ứng sẽ được xoá.</small>
            </div>
            <div class="actions">
                <button class="btn btn-outline btn-sm" onclick="this.closest('.confirm-backdrop').remove()">Huỷ bỏ</button>
                <button class="btn btn-primary btn-sm" id="do-back">← Xác nhận quay lại</button>
            </div>
        </div>`;
    document.body.appendChild(backdrop);
    backdrop.addEventListener('click', e => { if(e.target===backdrop) backdrop.remove(); });
    backdrop.querySelector('#do-back').addEventListener('click', () => {
        backdrop.remove();
        doNextStep(taskId, 'back');
    });
}

// openDetail() được cung cấp bởi /assets/js/task-detail.js

// ======= GO TO TASK IN LIST =======
function goToTaskInList(taskId) {
    const tasksSec = document.getElementById('section-tasks');
    if(tasksSec && tasksSec.style.display === 'none') {
        document.querySelectorAll('.page-section').forEach(s => s.style.display = 'none');
        document.querySelectorAll('.sidebar-item').forEach(b => b.classList.remove('active'));
        tasksSec.style.display = 'block';
        const sb = document.querySelector('.sidebar-item[onclick*="tasks"]');
        if(sb) sb.classList.add('active');
        document.getElementById('page-title').textContent = 'Danh sách yêu cầu hệ thống';
    }
    loadTasks();
    setTimeout(() => scrollToTaskRow(taskId), 250);
}
function scrollToTaskRow(taskId) {
    const row = document.getElementById('task-row-' + taskId);
    if(!row) return;
    row.scrollIntoView({ behavior: 'smooth', block: 'start' });
    row.classList.add('row-highlighted');
    setTimeout(() => row.classList.remove('row-highlighted'), 3500);
}

// ======= DUAL SCROLLBAR SYNC =======
function setupDualScroll() {
    const body = document.getElementById('lead-scroll-body');
    const top = document.getElementById('lead-scroll-top');
    const inner = document.getElementById('lead-scroll-top-inner');
    const table = document.getElementById('lead-tasks-table');
    if(!body || !top || !inner || !table) return;
    inner.style.width = table.offsetWidth + 'px';
    let isLocked = false;
    body.onscroll = () => { if(isLocked) return; isLocked = true; top.scrollLeft = body.scrollLeft; isLocked = false; };
    top.onscroll  = () => { if(isLocked) return; isLocked = true; body.scrollLeft = top.scrollLeft; isLocked = false; };
}
window.addEventListener('resize', () => setupDualScroll());

// ======= TASKS =======
// ============== TASK CACHE + FILTER ==============
let _TASKS_CACHE = [];

function loadTasks() {
    fetch(API + '?action=get_tasks')
        .then(r => r.json())
        .then(tasks => {
            _TASKS_CACHE = tasks || [];
            tfPopulateDropdowns();
            tfApplyFilters();
        });
}

function tfPopulateDropdowns() {
    const fields = {
        'tf-status':    'status',
        'tf-unit':      'implementing_unit',
        'tf-priority':  '__priority',
        'tf-tasktype':  'task_type',
        'tf-dept':      'requester_dept',
        'tf-assignee':  'assignee_name',
        'tf-devstatus': 'dev_status',
    };
    Object.entries(fields).forEach(([selId, field]) => {
        const sel = document.getElementById(selId);
        if(!sel) return;
        const current = sel.value;
        const labelOption = sel.querySelector('option[value=""]');
        const labelText = labelOption ? labelOption.textContent : '';
        const set = new Set();
        _TASKS_CACHE.forEach(t => {
            const v = (field === '__priority') ? (t.priority_ba || t.priority_requester) : t[field];
            if(v !== null && v !== undefined && v !== '') set.add(v);
        });
        const opts = Array.from(set).sort((a, b) => String(a).localeCompare(String(b), 'vi'));
        sel.innerHTML = `<option value="">${labelText}</option>` +
            opts.map(v => `<option value="${esc(v)}">${esc(v)}</option>`).join('');
        if(current && set.has(current)) sel.value = current;
    });
    // tf-system: load từ API để bao gồm hệ thống chưa có task nào
    const sysSel = document.getElementById('tf-system');
    if(sysSel) {
        const prevVal = sysSel.value;
        fetch(API + '?action=get_systems').then(r => r.json()).then(systems => {
            const names = [...new Set([
                ...systems.map(s => s.name).filter(Boolean),
                ..._TASKS_CACHE.map(t => t.system_name).filter(Boolean)
            ])].sort((a,b) => a.localeCompare(b,'vi'));
            sysSel.innerHTML = '<option value="">Hệ thống: Tất cả</option>' +
                names.map(n => `<option value="${esc(n)}">${esc(n)}</option>`).join('');
            if(prevVal && names.includes(prevVal)) sysSel.value = prevVal;
        });
    }
}

function tfGetState() {
    return {
        q:         (document.getElementById('tf-q').value || '').trim().toLowerCase(),
        status:    document.getElementById('tf-status').value,
        unit:      document.getElementById('tf-unit').value,
        priority:  document.getElementById('tf-priority').value,
        tasktype:  document.getElementById('tf-tasktype').value,
        dept:      document.getElementById('tf-dept').value,
        system:    document.getElementById('tf-system').value,
        assignee:  document.getElementById('tf-assignee').value,
        devstatus: document.getElementById('tf-devstatus').value,
        from:      document.getElementById('tf-from').value,
        to:        document.getElementById('tf-to').value,
    };
}

function tfMatch(t, f) {
    if(f.status     && t.status            !== f.status)     return false;
    if(f.unit       && t.implementing_unit !== f.unit)       return false;
    if(f.priority) {
        const p = t.priority_ba || t.priority_requester;
        if(p !== f.priority) return false;
    }
    if(f.tasktype   && t.task_type         !== f.tasktype)   return false;
    if(f.dept       && t.requester_dept    !== f.dept)       return false;
    if(f.system     && t.system_name       !== f.system)     return false;
    if(f.assignee   && t.assignee_name     !== f.assignee)   return false;
    if(f.devstatus  && t.dev_status        !== f.devstatus)  return false;

    if(f.from || f.to) {
        const c = t.created_at ? t.created_at.substring(0, 10) : null;
        if(!c) return false;
        if(f.from && c < f.from) return false;
        if(f.to   && c > f.to)   return false;
    }
    if(f.q) {
        const hay = [
            t.ma_yc, t.system_name, t.requester_name, t.requester_dept, t.task_type,
            t.assignee_name, t.dev_name, t.implementing_unit, t.status, t.dev_status,
            t.description, t.ba_description, t.feature, t.module_node_name, t.module_name,
            t.priority_ba, t.priority_requester, t.classification
        ].filter(Boolean).join(' ').toLowerCase();
        if(!hay.includes(f.q)) return false;
    }
    return true;
}

function tfApplyFilters() {
    const f = tfGetState();
    const filtered = _TASKS_CACHE.filter(t => tfMatch(t, f));
    renderTasks(filtered, _TASKS_CACHE.length);
}

function tfReset() {
    document.getElementById('tf-q').value = '';
    ['tf-status','tf-unit','tf-priority','tf-tasktype','tf-dept','tf-system','tf-assignee','tf-devstatus','tf-from','tf-to']
        .forEach(id => { const el = document.getElementById(id); if(el) el.value = ''; });
    tfApplyFilters();
}

function tfExportCsv() {
    const f = tfGetState();
    const filtered = _TASKS_CACHE.filter(t => tfMatch(t, f));
    if(!filtered.length) { alert('Không có dữ liệu để xuất.'); return; }
    // 30-col format khớp với sheet (đã bỏ Tuần/Tháng)
    const HEADER = ['Thời gian nhân viên đưa yêu cầu','Tên hệ thống','Mô tả yêu cầu nhân viên','Tên người yêu cầu','Mức độ ưu tiên ( Nhân viên tự đánh giá)','Thời gian bắt đầu ( Nhân viên tự ước tính )','Thời gian kết thúc ( Nhân viên tự ước tính )','File upload đính kèm (nếu có)','Link công việc 1Office','Phòng ban','Loại yêu cầu','Mã Yêu Cầu','Tên Module','Tính năng','Mô tả yêu cầu (BA)','Mức độ ưu tiên ( BA đánh giá )','Phân loại yêu cầu','Trạng thái hoàn thành','Thời gian bắt đầu ( BA ước tính )','Thời gian kết thúc ( BA ước tính )','Ngày BA đưa YC','Thời gian bắt đầu (thực tế code)','Thời gian kết thúc (thực tế code)','Ngày nghiệm thu','Kết quả delay (h)','Trạng thái delay','BA thực hiện YC','Đơn vị thực hiện','Ngày trong tuần Dev thực hiện thực tế','Trạng thái Dev hoàn thành'];
    const fmtDt = s => { if(!s) return ''; const d = new Date(s.replace(' ','T')); if(isNaN(d)) return s; const p = n => String(n).padStart(2,'0'); return `${p(d.getDate())}/${p(d.getMonth()+1)}/${d.getFullYear()} ${p(d.getHours())}:${p(d.getMinutes())}`; };
    const fmtD = s => { if(!s) return ''; const d = new Date(s); if(isNaN(d)) return s; const p = n => String(n).padStart(2,'0'); return `${p(d.getDate())}/${p(d.getMonth()+1)}/${d.getFullYear()}`; };
    const rows = [HEADER];
    filtered.forEach(t => rows.push([
        fmtDt(t.created_at), t.system_name||'', t.description||'', t.requester_name||'', t.priority_requester||'',
        fmtDt(t.start_date), fmtDt(t.expected_end_date), t.attachment_url||'', t.office_link||'', t.requester_dept||'',
        t.task_type||'', t.ma_yc||('#'+t.id), t.module_node_name||t.module_name||'',
        t.feature_node_name||t.feature||'', t.ba_description||'', t.priority_ba||'', t.classification||'', t.status||'',
        fmtD(t.ba_start_date), fmtD(t.ba_end_date), fmtD(t.ba_submission_date), fmtDt(t.actual_start_datetime), fmtDt(t.actual_end_date),
        fmtD(t.acceptance_date), (t.delay_hours==null||t.delay_hours==='')?'':t.delay_hours, t.delay_status||'', t.assignee_name||'', t.implementing_unit||'',
        t.dev_actual_day||'', t.dev_status||''
    ]));
    const csv = rows.map(r => r.map(c => {
        const s = String(c==null?'':c);
        return /[",\n]/.test(s) ? '"' + s.replace(/"/g,'""') + '"' : s;
    }).join(',')).join('\r\n');
    const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = `tasks_export_${new Date().toISOString().substring(0,10)}.csv`;
    document.body.appendChild(a); a.click(); a.remove();
    URL.revokeObjectURL(url);
}

function renderTasks(tasks, totalCount) {
    const tbody = document.getElementById('tasks-tbody');
    const sumEl = document.getElementById('tf-summary');
    document.getElementById('task-count').textContent = `${tasks.length}/${totalCount} yêu cầu`;
    if(sumEl) sumEl.textContent = (tasks.length === totalCount) ? `${totalCount} yêu cầu` : `Hiển thị ${tasks.length}/${totalCount}`;
    if(!tasks.length) { tbody.innerHTML = '<tr><td colspan="12" style="text-align:center;padding:30px;color:var(--text-muted);">Không có yêu cầu nào khớp bộ lọc.</td></tr>'; return; }
    tbody.innerHTML = tasks.map(t => `
        <tr id="task-row-${t.id}" class="${t.status === 'Chờ tiếp nhận' ? 'row-new-task' : ''}">
            <td><strong style="color:var(--primary-color);cursor:pointer;" onclick="openDetail(${t.id})" title="Xem chi tiết">${esc(t.ma_yc || '#'+t.id)}</strong></td>
            <td>
                <strong>${esc(t.requester_name)}</strong><br>
                <small style="color:var(--text-muted);">${esc(t.requester_dept)}</small>
            </td>
            <td>
                <span style="cursor:pointer;" onclick="openDetail(${t.id})">${esc(t.system_name)}</span>
                ${t.module_name ? `<br><small style="color:var(--text-muted);">📦 ${esc(t.module_name)}</small>` : ''}
            </td>
            <td><small>${esc(t.task_type)}</small></td>
            <td>${priorityBadge(t.priority_ba || t.priority_requester)}</td>
            <td><small>${formatDateOnly(t.expected_end_date)}</small></td>
            <td>${t.assignee_name ? `<strong>${esc(t.assignee_name)}</strong>` : '<span style="color:var(--text-muted);">Chưa gán</span>'}</td>
            <td>${unitBadge(t.implementing_unit)}</td>
            <td>${statusBadge(t.status)}</td>
            <td>${delayCell(t)}</td>
            <td style="font-size:0.82rem;">
                ${t.dev_name ? `<strong>${esc(t.dev_name)}</strong><br>${devStatusBadge(t.dev_status)}` : devStatusBadge(t.dev_status)}
            </td>
            <td>${nextStepCell(t)}</td>
        </tr>
    `).join('');
    setupDualScroll();
}

// ======= ASSIGN =======
function openAssign(taskId, sysName, assigneeId, priority, unit, classification) {
    currentTaskId = taskId;
    document.getElementById('assign-title').textContent = 'Phân công: ' + sysName;
    document.getElementById('assign-priority').value = priority;
    document.getElementById('assign-unit').value = unit || 'DION';
    document.getElementById('assign-classification').value = classification || '';
    document.getElementById('assign-ba-start').value = '';
    document.getElementById('assign-ba-end').value   = '';
    document.getElementById('assign-note').value     = '';
    fetch(API + '?action=get_ba_list')
        .then(r => r.json())
        .then(bas => {
            const sel = document.getElementById('assign-ba');
            sel.innerHTML = '<option value="">-- Chưa gán --</option>' +
                bas.map(b => `<option value="${b.id}" ${b.id == assigneeId ? 'selected' : ''}>${esc(b.full_name)}</option>`).join('');
        });
    document.getElementById('assignModal').style.display = 'block';
}
function submitAssign() {
    const fd = new FormData();
    fd.append('action', 'assign_task');
    fd.append('task_id', currentTaskId);
    fd.append('assignee_id', document.getElementById('assign-ba').value);
    fd.append('priority_ba', document.getElementById('assign-priority').value);
    fd.append('implementing_unit', document.getElementById('assign-unit').value);
    fd.append('classification', document.getElementById('assign-classification').value);
    fd.append('auto_progress', document.getElementById('assign-auto-progress').checked ? '1' : '0');
    const baStart = document.getElementById('assign-ba-start').value;
    const baEnd   = document.getElementById('assign-ba-end').value;
    if(baStart) fd.append('ba_start_date', baStart);
    if(baEnd)   fd.append('ba_end_date',   baEnd);
    fd.append('assignee_note', document.getElementById('assign-note').value || '');
    fetch(API, { method:'POST', body:fd })
        .then(r => r.json())
        .then(res => { if(res.success) { closeModal('assignModal'); loadTasks(); } });
}

// ======= USERS =======
let _USERS_CACHE = [];
let _USER_FILTER = { role: 'all', q: '' };

function userRoleBadge(role) {
    const map = {
        lead: ['badge-high', 'Lead BA'],
        ba:   ['badge-pending', 'BA'],
        dev:  ['badge-progress', 'Developer']
    };
    const m = map[role] || ['badge-pending', role];
    return `<span class="badge ${m[0]}">${m[1]}</span>`;
}

function loadUsers() {
    fetch(API + '?action=get_users')
        .then(r => r.json())
        .then(users => {
            _USERS_CACHE = users || [];
            // Update counts
            const counts = { all: _USERS_CACHE.length, lead: 0, ba: 0, dev: 0 };
            _USERS_CACHE.forEach(u => { counts[u.role] = (counts[u.role]||0) + 1; });
            ['all','lead','ba','dev'].forEach(k => {
                const el = document.getElementById('cnt-' + k); if(el) el.textContent = counts[k];
            });
            renderUsersTable();
        });
    ugLoad();
}

// ======= USER GROUPS =======
let _UG_CACHE = [];

function ugLoad() {
    fetch(API + '?action=list_user_groups').then(r => r.json()).then(groups => {
        _UG_CACHE = Array.isArray(groups) ? groups : [];
        ugRender();
    });
}

function ugRender() {
    const grid = document.getElementById('ug-grid');
    if(!grid) return;
    if(!_UG_CACHE.length) {
        grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;color:var(--text-muted);padding:18px;">Chưa có nhóm nào. Bấm "+ Tạo nhóm mới" để bắt đầu.</div>';
        return;
    }
    grid.innerHTML = _UG_CACHE.map(g => {
        const memberPreview = (g.members || []).slice(0, 5).map(m =>
            `<span class="ug-member-chip" title="${esc(m.role)}: ${esc(m.username)}">${esc(m.full_name)}</span>`
        ).join('');
        const more = g.member_count > 5 ? `<span class="ug-member-chip" style="background:#e9ecef;">+${g.member_count - 5}</span>` : '';
        return `
        <div class="ug-card" style="border-left-color:${esc(g.color)};">
            <div class="ug-head">
                <div>
                    <div class="ug-name" style="color:${esc(g.color)};">${esc(g.name)}</div>
                    <div class="ug-desc">${esc(g.description || 'Không có mô tả')}</div>
                </div>
                <div class="ug-actions">
                    <button class="btn btn-outline btn-icon" onclick="ugOpenEdit(${g.id})" title="Sửa">✏</button>
                    <button class="btn btn-outline btn-icon" onclick="ugOpenMembers(${g.id})" title="Quản lý thành viên">👥</button>
                    <button class="btn btn-danger-outline btn-icon" onclick="ugConfirmDelete(${g.id}, '${esc(g.name)}')" title="Xoá">🗑</button>
                </div>
            </div>
            <div class="ug-body">
                <strong>${g.member_count || 0} thành viên:</strong><br>
                ${memberPreview || '<em style="color:var(--text-muted);">Chưa có thành viên</em>'}
                ${more}
            </div>
        </div>`;
    }).join('');
}

let _UG_EDITING_ID = null;
function ugOpenCreate() { ugOpenForm(null); }
function ugOpenEdit(id) { ugOpenForm(id); }
function ugOpenForm(id) {
    _UG_EDITING_ID = id;
    let modal = document.getElementById('ugFormModal');
    if(!modal) {
        modal = document.createElement('div');
        modal.id = 'ugFormModal';
        modal.className = 'modal';
        modal.innerHTML = `
            <div class="modal-content" style="max-width:440px;">
                <span class="close" onclick="closeModal('ugFormModal')">&times;</span>
                <h3 id="ug-form-title">Tạo nhóm mới</h3>
                <div class="form-group"><label>Tên nhóm <span style="color:var(--danger-color);">*</span></label>
                    <input type="text" id="ug-name" class="form-control" placeholder="VD: DION, Kinkin, FFM..."></div>
                <div class="form-group"><label>Mô tả</label>
                    <input type="text" id="ug-desc" class="form-control" placeholder="VD: Đội phát triển DION"></div>
                <div class="form-group"><label>Màu nhận diện</label>
                    <input type="color" id="ug-color" class="form-control" style="height:40px;width:120px;"></div>
                <button class="btn btn-primary" style="width:100%;" onclick="ugSubmitForm()">💾 Lưu</button>
            </div>`;
        document.body.appendChild(modal);
    }
    if(id) {
        const g = _UG_CACHE.find(x => x.id == id);
        document.getElementById('ug-form-title').textContent = 'Sửa nhóm: ' + g.name;
        document.getElementById('ug-name').value  = g.name;
        document.getElementById('ug-desc').value  = g.description || '';
        document.getElementById('ug-color').value = g.color || '#0d6efd';
    } else {
        document.getElementById('ug-form-title').textContent = 'Tạo nhóm mới';
        document.getElementById('ug-name').value  = '';
        document.getElementById('ug-desc').value  = '';
        document.getElementById('ug-color').value = '#dc3545';
    }
    modal.style.display = 'block';
}
function ugSubmitForm() {
    const fd = new FormData();
    fd.append('action', _UG_EDITING_ID ? 'update_user_group' : 'create_user_group');
    if(_UG_EDITING_ID) fd.append('id', _UG_EDITING_ID);
    fd.append('name', document.getElementById('ug-name').value.trim());
    fd.append('description', document.getElementById('ug-desc').value.trim());
    fd.append('color', document.getElementById('ug-color').value);
    fetch(API, { method:'POST', body:fd }).then(r => r.json()).then(res => {
        if(res.success) { closeModal('ugFormModal'); ugLoad(); }
        else alert(res.message || 'Lỗi');
    });
}
function ugConfirmDelete(id, name) {
    if(!confirm(`Xoá nhóm "${name}"? Các thành viên trong nhóm sẽ bị bỏ khỏi nhóm (không xoá user).`)) return;
    const fd = new FormData();
    fd.append('action', 'delete_user_group');
    fd.append('id', id);
    fetch(API, { method:'POST', body:fd }).then(r => r.json()).then(res => {
        if(res.success) ugLoad();
        else alert(res.message || 'Lỗi');
    });
}

let _UG_MEMBERS_GID = null;
function ugOpenMembers(gid) {
    _UG_MEMBERS_GID = gid;
    let modal = document.getElementById('ugMembersModal');
    if(!modal) {
        modal = document.createElement('div');
        modal.id = 'ugMembersModal';
        modal.className = 'modal';
        modal.innerHTML = `
            <div class="modal-content" style="max-width:560px;max-height:90vh;overflow-y:auto;">
                <span class="close" onclick="closeModal('ugMembersModal')">&times;</span>
                <h3 id="ug-members-title">Quản lý thành viên</h3>
                <div style="margin:10px 0;">
                    <input type="text" id="ug-members-search" class="form-control" placeholder="🔍 Tìm theo tên / username..." oninput="ugRenderMembersList()">
                </div>
                <div class="ug-members-tabs">
                    <button class="ug-mtab active" data-role="" onclick="ugFilterRole(this)">Tất cả</button>
                    <button class="ug-mtab" data-role="lead" onclick="ugFilterRole(this)">Lead</button>
                    <button class="ug-mtab" data-role="ba" onclick="ugFilterRole(this)">BA</button>
                    <button class="ug-mtab" data-role="dev" onclick="ugFilterRole(this)">Dev</button>
                </div>
                <div id="ug-members-list" style="margin-top:12px;max-height:50vh;overflow-y:auto;border:1px solid var(--border-color);"></div>
                <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:14px;">
                    <button class="btn btn-outline" onclick="closeModal('ugMembersModal')">Đóng</button>
                    <button class="btn btn-primary" onclick="ugSaveMembers()">💾 Lưu thành viên</button>
                </div>
            </div>`;
        document.body.appendChild(modal);
    }
    const g = _UG_CACHE.find(x => x.id == gid);
    document.getElementById('ug-members-title').textContent = 'Thành viên nhóm: ' + (g ? g.name : '');
    document.getElementById('ug-members-search').value = '';
    document.querySelectorAll('.ug-mtab').forEach(b => b.classList.toggle('active', b.dataset.role === ''));
    ugLoadMembers(gid, '');
    modal.style.display = 'block';
}
let _UG_MEMBERS_USERS = [];
let _UG_MEMBERS_ROLE = '';
function ugLoadMembers(gid, role) {
    _UG_MEMBERS_ROLE = role;
    fetch(API + '?action=list_users_for_group&group_id=' + gid + (role ? '&role=' + role : ''))
        .then(r => r.json()).then(users => {
            _UG_MEMBERS_USERS = Array.isArray(users) ? users : [];
            ugRenderMembersList();
        });
}
function ugFilterRole(btn) {
    document.querySelectorAll('.ug-mtab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    ugLoadMembers(_UG_MEMBERS_GID, btn.dataset.role);
}
function ugRenderMembersList() {
    const q = (document.getElementById('ug-members-search').value || '').trim().toLowerCase();
    const list = _UG_MEMBERS_USERS.filter(u =>
        !q || (u.full_name||'').toLowerCase().includes(q) || (u.username||'').toLowerCase().includes(q)
    );
    const cont = document.getElementById('ug-members-list');
    if(!list.length) { cont.innerHTML = '<div style="text-align:center;padding:20px;color:var(--text-muted);">Không có user nào.</div>'; return; }
    cont.innerHTML = list.map(u => `
        <label class="ug-member-row">
            <input type="checkbox" data-uid="${u.id}" ${u.in_group == 1 ? 'checked' : ''}>
            <div class="ug-member-info">
                <strong>${esc(u.full_name)}</strong>
                <small>${esc(u.username)} · ${esc(u.role)}</small>
            </div>
        </label>
    `).join('');
}
function ugSaveMembers() {
    const checked = Array.from(document.querySelectorAll('#ug-members-list input[type=checkbox]:checked'))
        .map(cb => parseInt(cb.dataset.uid, 10));
    const fd = new FormData();
    fd.append('action', 'set_user_group_members');
    fd.append('group_id', _UG_MEMBERS_GID);
    fd.append('user_ids', JSON.stringify(checked));
    fetch(API, { method:'POST', body:fd }).then(r => r.json()).then(res => {
        if(res.success) { closeModal('ugMembersModal'); ugLoad(); }
        else alert(res.message || 'Lỗi');
    });
}

function renderUsersTable() {
    const tbody = document.getElementById('users-tbody');
    const q = (_USER_FILTER.q || '').trim().toLowerCase();
    const filtered = _USERS_CACHE.filter(u => {
        if(_USER_FILTER.role !== 'all' && u.role !== _USER_FILTER.role) return false;
        if(q && !((u.full_name||'').toLowerCase().includes(q) || (u.username||'').toLowerCase().includes(q))) return false;
        return true;
    });
    if(!filtered.length) {
        tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;padding:30px;color:var(--text-muted);">Không có user nào khớp bộ lọc.</td></tr>';
        return;
    }
    tbody.innerHTML = filtered.map(u => {
        const nickWarn = !u.nickname ? ' <span title="Chưa có nickname — sẽ không match được khi đọc sheet" style="color:var(--danger-color);">⚠</span>' : '';
        return `
        <tr id="user-row-${u.id}">
            <td>${u.id}</td>
            <td><strong>${esc(u.full_name)}</strong></td>
            <td><code style="background:#f0f4ff;padding:2px 6px;font-size:0.85rem;">${esc(u.nickname || '—')}</code>${nickWarn}</td>
            <td>${esc(u.username)}</td>
            <td>${userRoleBadge(u.role)}</td>
            <td style="text-align:center;">${u.total_tasks||0}</td>
            <td style="text-align:center;">${u.completed_tasks||0}</td>
            <td style="text-align:center;font-weight:bold;color:var(--success-color);">${u.on_time||0}</td>
            <td style="text-align:center;font-weight:bold;color:var(--danger-color);">${u.late||0}</td>
            <td>
                <button class="btn btn-outline btn-icon" onclick="openEditUserModal(${u.id},'${esc(u.full_name)}','${u.role}','${esc(u.nickname || '')}')">Sửa</button>
                ${u.id != SESSION_USER_ID ? `<button class="btn btn-danger-outline btn-icon" onclick="confirmDeleteUser(${u.id},'${esc(u.full_name)}')">Xoá</button>` : ''}
            </td>
        </tr>`;
    }).join('');
}

function filterUsersUI() {
    _USER_FILTER.q = document.getElementById('user-search-input').value;
    renderUsersTable();
}

document.addEventListener('click', e => {
    const chip = e.target.closest('.user-chip-btn');
    if(chip) {
        document.querySelectorAll('.user-chip-btn').forEach(b => b.classList.remove('active'));
        chip.classList.add('active');
        _USER_FILTER.role = chip.dataset.role;
        renderUsersTable();
    }
});
// Auto-fill nickname từ token cuối của full_name (chỉ khi user chưa nhập tay)
function umAutoFillNickname() {
    const nickEl = document.getElementById('um-nickname');
    if(nickEl.dataset.userEdited === '1') return; // user đã sửa tay → không ghi đè
    const full = document.getElementById('um-fullname').value.trim();
    if(!full) { nickEl.value = ''; return; }
    const parts = full.split(/\s+/);
    nickEl.value = parts[parts.length - 1] || '';
}
document.addEventListener('input', e => {
    if(e.target && e.target.id === 'um-nickname') e.target.dataset.userEdited = '1';
});

function openCreateUserModal() {
    currentUserAction = 'create'; currentUserId = null;
    document.getElementById('user-modal-title').textContent = 'Thêm nhân viên';
    document.getElementById('username-group').style.display = 'block';
    ['um-username','um-fullname','um-password','um-nickname'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('um-nickname').dataset.userEdited = '';
    document.getElementById('um-password').required = true;
    document.getElementById('pass-hint').textContent = '';
    document.getElementById('um-role').value = 'ba';
    document.getElementById('userModal').style.display = 'block';
}
function openEditUserModal(id, fullName, role, nickname) {
    currentUserAction = 'edit'; currentUserId = id;
    document.getElementById('user-modal-title').textContent = 'Cập nhật nhân viên';
    document.getElementById('username-group').style.display = 'none';
    document.getElementById('um-fullname').value = fullName;
    document.getElementById('um-nickname').value = nickname || '';
    document.getElementById('um-nickname').dataset.userEdited = '1'; // nickname đã có sẵn
    document.getElementById('um-password').value = '';
    document.getElementById('um-password').required = false;
    document.getElementById('pass-hint').textContent = '(Bỏ trống nếu không đổi)';
    document.getElementById('um-role').value = role;
    document.getElementById('userModal').style.display = 'block';
}
function submitUserForm() {
    const nickname = document.getElementById('um-nickname').value.trim();
    if(!nickname) { alert('Vui lòng nhập Tên (nickname) — sẽ được ghi vào Google Sheet.'); return; }
    const fd = new FormData();
    if(currentUserAction === 'create') {
        fd.append('action', 'create_user');
        fd.append('username', document.getElementById('um-username').value);
        fd.append('password', document.getElementById('um-password').value);
        fd.append('full_name', document.getElementById('um-fullname').value);
        fd.append('nickname', nickname);
        fd.append('role', document.getElementById('um-role').value);
    } else {
        fd.append('action', 'edit_user');
        fd.append('user_id', currentUserId);
        fd.append('full_name', document.getElementById('um-fullname').value);
        fd.append('password', document.getElementById('um-password').value);
        fd.append('nickname', nickname);
        fd.append('role', document.getElementById('um-role').value);
    }
    fetch(API, { method:'POST', body:fd }).then(r => r.json()).then(res => {
        if(res.success) { closeModal('userModal'); loadUsers(); }
        else alert(res.message || 'Có lỗi xảy ra!');
    });
}
function confirmDeleteUser(id, name) {
    const backdrop = document.createElement('div');
    backdrop.className = 'confirm-backdrop';
    backdrop.innerHTML = `
        <div class="confirm-box">
            <div class="head"><h4>Xoá tài khoản nhân viên?</h4></div>
            <div class="body">Bạn sắp xoá <strong>${esc(name)}</strong>.<br>Công việc đã giao của họ sẽ giữ lại trong hệ thống.</div>
            <div class="actions">
                <button class="btn btn-outline btn-sm" onclick="this.closest('.confirm-backdrop').remove()">Huỷ bỏ</button>
                <button class="btn btn-primary btn-sm" id="do-del">Xác nhận xoá</button>
            </div>
        </div>`;
    document.body.appendChild(backdrop);
    backdrop.addEventListener('click', e => { if(e.target===backdrop) backdrop.remove(); });
    backdrop.querySelector('#do-del').addEventListener('click', () => {
        backdrop.remove();
        const fd = new FormData();
        fd.append('action', 'delete_user');
        fd.append('user_id', id);
        fetch(API, { method:'POST', body:fd }).then(r => r.json()).then(res => {
            if(res.success) loadUsers();
            else alert(res.message || 'Không thể xoá!');
        });
    });
}

// ======= NOTIFICATIONS =======
function toggleNotif() {
    const panel = document.getElementById('notif-panel');
    panel.classList.toggle('open');
    if(panel.classList.contains('open')) loadNotifBell(true);
}
document.addEventListener('click', e => {
    const wrapper = document.querySelector('.notif-wrapper');
    if(wrapper && !wrapper.contains(e.target)) {
        document.getElementById('notif-panel').classList.remove('open');
    }
});

function renderNotifList(items, container) {
    if(!items.length) {
        container.innerHTML = '<div class="notif-empty">Không có thông báo nào</div>';
        return;
    }
    container.innerHTML = items.map(n => `
        <div class="notif-item ${n.is_read == 0 ? 'unread' : ''}" onclick="onNotifClick(${n.id}, ${n.task_id || 'null'})">
            <div class="title">${esc(n.title)}${n.task_code ? ` <small style="color:var(--primary-color);">[${esc(n.task_code)}]</small>` : ''}</div>
            <div class="msg">${esc(n.message)}</div>
            <div class="time">${timeAgo(n.created_at)} trước · ${n.from_name ? esc(n.from_name) : 'Hệ thống'}</div>
        </div>
    `).join('');
}

function loadNotifBell(updatePanel = false) {
    fetch(API + '?action=get_notifications')
        .then(r => r.json())
        .then(res => {
            const badge = document.getElementById('notif-badge');
            if(res.unread_count > 0) {
                badge.textContent = res.unread_count > 99 ? '99+' : res.unread_count;
                badge.style.display = 'inline-block';
            } else {
                badge.style.display = 'none';
            }
            if(updatePanel) renderNotifList(res.items || [], document.getElementById('notif-list'));
        });
}
function loadNotifPage() {
    fetch(API + '?action=get_notifications')
        .then(r => r.json())
        .then(res => renderNotifList(res.items || [], document.getElementById('notif-fullpage')));
}
function markOneRead(id) {
    const fd = new FormData();
    fd.append('action', 'mark_notification_read');
    fd.append('notification_id', id);
    fetch(API, { method:'POST', body:fd }).then(() => loadNotifBell(true));
}
function markAllRead() {
    const fd = new FormData();
    fd.append('action', 'mark_notification_read');
    fetch(API, { method:'POST', body:fd }).then(() => { loadNotifBell(true); loadNotifPage(); });
}

// ======= DEV ASSIGN =======
let currentDevTaskId = null;
let _DEV_ASSIGN_CURRENT_DEVID = null;

function openDevAssign(taskId, sysName, devId, baDesc) {
    currentDevTaskId = taskId;
    _DEV_ASSIGN_CURRENT_DEVID = devId;
    document.getElementById('dev-assign-title').textContent = 'Giao Dev: ' + sysName;
    document.getElementById('dev-assign-desc').value = baDesc || '';
    document.getElementById('dev-assign-deadline').value = '';

    // Populate group dropdown từ cache
    const grpSel = document.getElementById('dev-assign-group');
    if(grpSel) {
        grpSel.value = '';
        grpSel.innerHTML = '<option value="">-- Tất cả nhóm --</option>' +
            (_UG_CACHE || []).map(g => `<option value="${g.id}">${esc(g.name)} (${g.member_count})</option>`).join('');
    }

    Promise.all([
        fetch(API + '?action=get_dev_list').then(r => r.json()),
        fetch(API + '?action=get_task_detail&task_id=' + taskId).then(r => r.json()),
        // Đảm bảo có UG cache
        (_UG_CACHE && _UG_CACHE.length) ? Promise.resolve() : fetch(API + '?action=list_user_groups').then(r => r.json()).then(g => { _UG_CACHE = g||[]; })
    ]).then(([devs, task]) => {
        renderDevAssignOptions(devs, devId);
        // Re-render group dropdown sau khi load xong
        if(grpSel && (!grpSel.options.length || grpSel.options.length === 1)) {
            grpSel.innerHTML = '<option value="">-- Tất cả nhóm --</option>' +
                (_UG_CACHE || []).map(g => `<option value="${g.id}">${esc(g.name)} (${g.member_count})</option>`).join('');
        }
        const seed = task.dev_deadline || task.expected_end_date;
        if(seed) {
            const dt = new Date(seed.replace(' ', 'T'));
            const pad = n => String(n).padStart(2,'0');
            document.getElementById('dev-assign-deadline').value =
                `${dt.getFullYear()}-${pad(dt.getMonth()+1)}-${pad(dt.getDate())}T${pad(dt.getHours())}:${pad(dt.getMinutes())}`;
        }
    });
    document.getElementById('devAssignModal').style.display = 'block';
}

function renderDevAssignOptions(devs, selectedId) {
    const sel = document.getElementById('dev-assign-dev');
    sel.innerHTML = '<option value="">-- Chưa gán dev --</option>' +
        devs.map(d => `<option value="${d.id}" ${d.id == selectedId ? 'selected' : ''}>${esc(d.full_name)}</option>`).join('');
}

function reloadDevAssignList() {
    const gid = document.getElementById('dev-assign-group').value;
    const url = API + '?action=get_dev_list' + (gid ? '&group_id=' + gid : '');
    fetch(url).then(r => r.json()).then(devs => renderDevAssignOptions(devs, _DEV_ASSIGN_CURRENT_DEVID));
}
function submitDevAssign() {
    const deadline = document.getElementById('dev-assign-deadline').value;
    const fd = new FormData();
    fd.append('action', 'assign_dev');
    fd.append('task_id', currentDevTaskId);
    fd.append('dev_id', document.getElementById('dev-assign-dev').value);
    fd.append('ba_description', document.getElementById('dev-assign-desc').value);
    if(deadline) fd.append('dev_deadline', deadline);
    fetch(API, { method:'POST', body:fd }).then(r => r.json()).then(res => {
        if(res.success) { closeModal('devAssignModal'); loadTasks(); }
        else alert(res.message || 'Lỗi khi giao dev');
    });
}

function devRework(taskId) {
    if(!confirm('Đánh dấu task này "Cần sửa" và thông báo cho Dev?')) return;
    const fd = new FormData();
    fd.append('action', 'dev_rework');
    fd.append('task_id', taskId);
    fetch(API, { method:'POST', body:fd }).then(r => r.json()).then(res => {
        if(res.success) loadTasks();
    });
}

// ======= FORM LOG =======
function loadFormLog() {
    fetch(API + '?action=get_form_log').then(r => r.json()).then(rows => {
        document.getElementById('formlog-count').textContent = `${rows.length} yêu cầu`;
        const tbody = document.getElementById('formlog-tbody');
        if(!rows.length) {
            tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;padding:30px;color:var(--text-muted);">Chưa có yêu cầu nào.</td></tr>';
            return;
        }
        tbody.innerHTML = rows.map(t => `
            <tr>
                <td><strong style="color:var(--primary-color);">${esc(t.ma_yc || '#'+t.id)}</strong></td>
                <td><small>${formatDate(t.created_at)}</small></td>
                <td><strong>${esc(t.requester_name)}</strong></td>
                <td><small style="color:var(--text-muted);">${esc(t.requester_dept)}</small></td>
                <td>${esc(t.system_name)}</td>
                <td><small>${esc(t.task_type)}</small></td>
                <td>${priorityBadge(t.priority_requester)}</td>
                <td><small>${formatDateOnly(t.expected_end_date)}</small></td>
                <td>${statusBadge(t.status)}</td>
                <td>${t.assignee_name ? `<strong>${esc(t.assignee_name)}</strong>` : '<span style="color:var(--text-muted);">Chưa gán</span>'}</td>
            </tr>
        `).join('');
    });
}
function copyFormLink() {
    const url = document.getElementById('form-link-url').textContent;
    navigator.clipboard.writeText(url).then(() => {
        const hint = document.getElementById('copy-hint');
        hint.classList.add('show');
        setTimeout(() => hint.classList.remove('show'), 2500);
    });
}

// ======= POLLING =======
// Trigger dev sheet poll: server đọc sheet, sync về DB. Server-side throttle 8s.
let _DEV_SYNC_LAST_STAMP = null;
function triggerDevSheetPoll(silent) {
    return fetch(API + '?action=dev_sheet_poll', { method: 'POST' })
        .then(r => r.json()).then(res => {
            const stamp = document.getElementById('dev-sync-stamp');
            if(stamp) {
                if(res.success) {
                    if(res.throttled) { /* khỏi update, lần trước cách <8s */ }
                    else {
                        const s = res.stats || {};
                        _DEV_SYNC_LAST_STAMP = new Date().toLocaleTimeString('vi-VN');
                        stamp.textContent = `Sheet sync: ${_DEV_SYNC_LAST_STAMP} · ${s.scanned||0} scan / ${s.updated||0} updated`;
                        stamp.style.color = (s.errors && s.errors.length) ? 'var(--danger-color)' : 'var(--success-color)';
                        if(s.updated > 0) loadTasks(); // có thay đổi → re-render task list
                    }
                } else if(!silent) {
                    stamp.textContent = 'Sheet sync lỗi: ' + (res.message || '');
                    stamp.style.color = 'var(--danger-color)';
                }
            }
            return res;
        });
}

loadTasks();
loadNotifBell();
triggerDevSheetPoll(true); // catch-up khi page load (đặc biệt sau khi PC khởi động)

setInterval(() => {
    const visible = document.querySelector('.page-section[style*="block"], .page-section:not([style*="none"])');
    const id = visible ? visible.id : 'section-tasks';
    if(id === 'section-tasks')        loadTasks();
    else if(id === 'section-users')   loadUsers();
    else if(id === 'section-formlog') loadFormLog();
    else if(id === 'section-notifications') loadNotifPage();
    loadNotifBell();
    triggerDevSheetPoll(true); // poll dev sheet song song với refresh task list
}, 15000);
</script>
<script src="<?php echo BASE_PATH; ?>/assets/js/task-detail.js?v=1"></script>
<script src="<?php echo BASE_PATH; ?>/assets/js/start-coding-modal.js?v=4"></script>
<script src="<?php echo BASE_PATH; ?>/assets/js/claim-modal.js?v=1"></script>
<script src="<?php echo BASE_PATH; ?>/assets/js/workflow-builder.js?v=1"></script>
<script src="<?php echo BASE_PATH; ?>/assets/js/form-config.js?v=1"></script>
<script src="<?php echo BASE_PATH; ?>/assets/js/bot-sync.js?v=1"></script>
<script src="<?php echo BASE_PATH; ?>/assets/js/system-tree.js?v=3"></script>
<script>const SYS_IS_LEAD = <?php echo $_SESSION['role']==='lead' ? 'true' : 'false'; ?>;</script>
</body>
</html>
