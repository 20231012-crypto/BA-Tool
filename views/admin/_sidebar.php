<?php
/**
 * Sidebar partial — included trong các trang admin.
 * Yêu cầu: $_SESSION đã có user_id, role, full_name
 * Optional: $activeMenu (string) — menu key đang active
 */
$activeMenu = $activeMenu ?? 'tasks';
$role = $_SESSION['role'] ?? 'ba';
$fullName = $_SESSION['full_name'] ?? '';
?>
<aside class="sidebar">
    <div class="sidebar-brand">
        <img src="https://kinkinlogistics.com/uploads/up/root/sitecontact/2023/06/13/20/58/kinkin-logistic-logo-01.png"
             alt="KinKin Logistics" class="logo">
        <small>BA · Kin Kin Logistics</small>
        <h1>Hệ thống Quản lý Yêu cầu</h1>
    </div>
    <nav class="sidebar-menu">
        <div class="sidebar-menu-label">Công việc</div>
        <button class="sidebar-item <?php echo $activeMenu==='tasks' ? 'active' : ''; ?>"
                onclick="switchSection('tasks')">
            <span class="ic">📋</span>
            <?php echo $role === 'lead' ? 'Danh sách yêu cầu' : 'Việc của tôi'; ?>
        </button>

        <?php if(in_array($role, ['lead','ba'])): ?>
        <button class="sidebar-item <?php echo $activeMenu==='systems' ? 'active' : ''; ?>"
                onclick="switchSection('systems')">
            <span class="ic">🗂️</span>
            Danh sách hệ thống
        </button>
        <?php endif; ?>

        <?php if($role === 'lead'): ?>
        <a class="sidebar-item sidebar-item-link"
           href="<?php echo htmlspecialchars(defined('KPI_EXTERNAL_URL') ? KPI_EXTERNAL_URL : '#'); ?>"
           target="_blank" rel="noopener"
           title="Mở dashboard phân tích KPI ở tab mới">
            <span class="ic">📈</span>
            Phân tích KPI <small style="opacity:.7;">↗</small>
        </a>
        <button class="sidebar-item <?php echo $activeMenu==='users' ? 'active' : ''; ?>"
                onclick="switchSection('users')">
            <span class="ic">👥</span>
            Nhân sự
        </button>

        <div class="sidebar-menu-label">Cấu hình</div>
        <button class="sidebar-item <?php echo $activeMenu==='workflows' ? 'active' : ''; ?>"
                onclick="switchSection('workflows')">
            <span class="ic">⚙️</span>
            Quy trình tự động
        </button>
        <button class="sidebar-item <?php echo in_array($activeMenu,['form','formconfig','formlog']) ? 'active' : ''; ?>"
                onclick="switchSection('form')">
            <span class="ic">📝</span>
            Form công khai
        </button>
        <button class="sidebar-item <?php echo $activeMenu==='botsync' ? 'active' : ''; ?>"
                onclick="switchSection('botsync')">
            <span class="ic">🤖</span>
            Đồng bộ Sheet
        </button>
        <button class="sidebar-item <?php echo $activeMenu==='apikeys' ? 'active' : ''; ?>"
                onclick="switchSection('apikeys')">
            <span class="ic">🔑</span>
            API
        </button>
        <?php endif; ?>

        <?php if($role === 'ba'): ?>
        <div class="sidebar-menu-label">Theo dõi</div>
        <button class="sidebar-item <?php echo in_array($activeMenu,['form','formlog']) ? 'active' : ''; ?>"
                onclick="switchSection('form')">
            <span class="ic">📝</span>
            Form công khai
        </button>
        <?php endif; ?>

        <div class="sidebar-menu-label">1Office</div>
        <button class="sidebar-item <?php echo $activeMenu==='oneoffice' ? 'active' : ''; ?>"
                onclick="switchSection('oneoffice')">
            <span class="ic">📊</span>
            Công việc 1O
        </button>

        <div class="sidebar-menu-label">Hệ thống</div>
        <button class="sidebar-item <?php echo $activeMenu==='notifications' ? 'active' : ''; ?>"
                onclick="switchSection('notifications')">
            <span class="ic">🔔</span>
            Thông báo
        </button>
    </nav>
    <div class="sidebar-footer">
        <div style="color:#fff; font-weight:600; font-size:0.86rem;">
            <?php echo htmlspecialchars($fullName); ?>
        </div>
        <div style="margin-top:2px;">
            <?php echo $role === 'lead' ? 'Lead BA' : 'Nhân viên BA'; ?>
        </div>
    </div>
</aside>
