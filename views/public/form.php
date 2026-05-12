<?php
require_once 'config/db.php';
require_once 'models/FormConfig.php';
$_db = (new Database())->getConnection();
$_fc = new FormConfig($_db);
$_settings = $_fc->getSettings();
$_fields = $_fc->getAllFields(true);
?><!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($_settings['title'] ?? 'Gửi Yêu Cầu'); ?> - Kin Kin BA Tool</title>
    <link rel="stylesheet" href="/BA.Tool/assets/css/style.css?v=13">
</head>
<body class="standalone-shell">
    <div class="public-form-wrapper">
        <div class="public-form-header">
            <img src="https://kinkinlogistics.com/uploads/up/root/sitecontact/2023/06/13/20/58/kinkin-logistic-logo-01.png"
                 alt="KinKin Logistics" class="logo logo-form">
            <h1><?php echo htmlspecialchars($_settings['title'] ?? 'Gửi Yêu Cầu'); ?></h1>
            <?php if(!empty($_settings['description'])): ?>
                <p><?php echo htmlspecialchars($_settings['description']); ?></p>
            <?php endif; ?>
        </div>
        <div class="public-form-body">
            <form action="?page=submit_form" method="POST" enctype="multipart/form-data">
                <?php foreach($_fields as $f):
                    $key = $f['field_key'];
                    $label = htmlspecialchars($f['label']);
                    $req = (int)$f['required'] === 1 ? 'required' : '';
                    $reqMark = (int)$f['required'] === 1 ? ' <span style="color:var(--danger-color);">*</span>' : '';
                    $ph = htmlspecialchars($f['placeholder'] ?? '');
                    $type = $f['field_type'];
                ?>
                <?php if($type === 'section'): ?>
                    <div class="form-section-divider" style="margin:18px 0 10px;padding-top:14px;border-top:1px solid var(--border-color);">
                        <h3 style="font-size:1rem;color:var(--text-primary);"><?php echo $label; ?></h3>
                        <?php if(!empty($f['placeholder'])): ?><p style="color:var(--text-muted);font-size:0.85rem;margin-top:4px;"><?php echo $ph; ?></p><?php endif; ?>
                    </div>
                <?php else: ?>
                <div class="form-group">
                    <label><?php echo $label . $reqMark; ?></label>

                    <?php if($type === 'text'): ?>
                        <input type="text" name="<?php echo htmlspecialchars($key); ?>" class="form-control" <?php echo $req; ?> placeholder="<?php echo $ph; ?>">

                    <?php elseif($type === 'textarea'): ?>
                        <textarea name="<?php echo htmlspecialchars($key); ?>" class="form-control" rows="5" <?php echo $req; ?> placeholder="<?php echo $ph; ?>"></textarea>

                    <?php elseif($type === 'date'): ?>
                        <input type="date" name="<?php echo htmlspecialchars($key); ?>" class="form-control" <?php echo $req; ?>
                            <?php if($key === 'start_date'): ?>value="<?php echo date('Y-m-d'); ?>"<?php endif; ?>>

                    <?php elseif($type === 'file'): ?>
                        <input type="file" name="<?php echo htmlspecialchars($key); ?>" class="form-control"
                            accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.xls,.xlsx,.zip">
                        <?php if(!empty($ph)): ?><small style="color:var(--text-muted);">(<?php echo $ph; ?>)</small><?php endif; ?>

                    <?php elseif($type === 'dropdown'):
                        $opts = json_decode($f['options_json'] ?? '[]', true) ?: [];
                    ?>
                        <select name="<?php echo htmlspecialchars($key); ?>" class="form-control" <?php echo $req; ?>>
                            <option value="">-- Chọn <?php echo strtolower($label); ?> --</option>
                            <?php foreach($opts as $opt): ?>
                                <option value="<?php echo htmlspecialchars($opt); ?>"><?php echo htmlspecialchars($opt); ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </div>
                <?php endif; endforeach; ?>

                <div style="margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-primary" style="width:100%; padding:12px; font-size:0.98rem;">GỬI YÊU CẦU</button>
                    <div style="text-align:center; margin-top: 16px;">
                        <a href="?page=login" style="color:var(--text-muted); font-size:0.85rem;">(Đăng nhập nội bộ dành cho BA)</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
