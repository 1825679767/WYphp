<?php
// mass_mail/index.php
declare(strict_types=1);
session_start();

// require_once __DIR__ . '/../config.php'; // Load config first - Incorrect way if config.php returns an array
$config = require_once __DIR__ . '/../config.php'; // Correctly load config array
require_once __DIR__ . '/../bag_query/db.php'; // Reuse db connection
require_once __DIR__ . '/functions.php'; // Module specific functions

// --- Login Logic (Copied from other modules) --- Start ---
$adminConf = $config['admin'] ?? null;
$isLoggedIn = false;
$loginError = '';

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

if (!$adminConf || !isset($adminConf['username']) || !isset($adminConf['password_hash'])) {
    $loginError = '管理后台配置不完整，无法登录。';
} else {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_username'])) {
        $username = $_POST['login_username'];
        $password = $_POST['login_password'];
        if ($username === $adminConf['username'] && isset($adminConf['password_hash']) && password_verify($password, $adminConf['password_hash'])) {
            $_SESSION['admin_logged_in'] = true;
            header('Location: index.php');
            exit;
        } else {
            $loginError = '无效的用户名或密码。';
        }
    }
    if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        $isLoggedIn = true;
    }
}
// --- Login Logic --- End ---

$pdo_C = null; // Needed for getting character lists
$error_message = '';
$success_message = ''; // For displaying results

if ($isLoggedIn) {
    try {
        $connections = connect_databases();
        $pdo_C = $connections['db_C'];
    } catch (Exception $e) {
        $error_message = "数据库连接失败: " . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>群发系统 - <?= $isLoggedIn ? '控制台' : '登录' ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../bag_query/style.css"> <!-- Reuse common styles -->
    <link rel="stylesheet" href="style.css">
    <style>
        /* Add some spacing */
        .action-section { margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 1px solid #444; }
        .target-options label { margin-right: 1rem; }
        #custom_char_list { min-height: 100px; }
    </style>
</head>
<body>

<?php if (!$isLoggedIn): ?>
    <!-- LOGIN FORM -->
    <div class="login-container">
        <h2>管理员登录 - 群发系统</h2>
        <?php if ($loginError): ?>
            <div class="alert alert-danger" role="alert"><?= htmlspecialchars($loginError) ?></div>
        <?php endif; ?>
        <form method="post" action="index.php">
            <div class="mb-3">
                <label for="login_username" class="form-label">用户名</label>
                <input type="text" class="form-control" id="login_username" name="login_username" required>
            </div>
            <div class="mb-3">
                <label for="login_password" class="form-label">密码</label>
                <input type="password" class="form-control" id="login_password" name="login_password" required>
            </div>
            <button type="submit" class="btn btn-query w-100">登录</button>
        </form>
         <div class="mt-3 text-center">
            <a href="../index.php" class="home-btn-link">&laquo; 返回主页</a>
        </div>
    </div>

<?php else: ?>
    <!-- MASS MAIL/SEND INTERFACE -->
    <div class="container mt-4 position-relative">
        <a href="../index.php" class="home-btn">&laquo; 返回主页</a>
        <a href="?logout=1" class="logout-btn">退出登录</a>
        <h2 class="text-center mb-4">群发系统</h2>

        <?php if ($error_message): ?>
            <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>
         <?php if (isset($_SESSION['mass_mail_result'])): ?>
            <div class="alert alert-<?= $_SESSION['mass_mail_result']['success'] ? 'success' : 'warning' ?>" role="alert">
                <?= htmlspecialchars($_SESSION['mass_mail_result']['message']) ?>
            </div>
            <?php unset($_SESSION['mass_mail_result']); // Clear message after display ?>
        <?php endif; ?>


        <!-- Section 1: Announcements / System Messages -->
        <section class="action-section" id="section-announce">
            <h4>发布全服公告/通知</h4>
            <form id="form-announce" action="handler.php" method="POST">
                <input type="hidden" name="action" value="send_announce">
                 <div class="mb-3">
                    <label for="announce_type" class="form-label">类型:</label>
                    <select id="announce_type" name="announce_type" class="form-select">
                        <option value="announce">公告 (Announce - 黄字)</option>
                        <option value="notify">通知 (Notify - 红字)</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="announce_message" class="form-label">消息内容:</label>
                    <textarea class="form-control" id="announce_message" name="message" rows="3" required></textarea>
                </div>
                <button type="submit" class="btn btn-primary">发送公告/通知</button>
            </form>
        </section>

        <!-- Section 2: Mail / Item / Gold Sending -->
        <section class="action-section" id="section-send">
             <h4>群发邮件/物品/金币</h4>
            <form id="form-send" action="handler.php" method="POST">
                <!-- Action Type Selection -->
                 <div class="mb-3">
                    <label for="send_action_type" class="form-label">发送类型:</label>
                    <select id="send_action_type" name="action" class="form-select" required>
                        <option value="">--请选择--</option>
                        <option value="send_mail">发送邮件 (纯文本)</option>
                        <option value="send_item">发送物品 (通过邮件)</option>
                        <option value="send_gold">发送金币 (通过邮件)</option>
                    </select>
                </div>

                <!-- Common Fields -->
                <div class="mb-3">
                    <label for="send_subject" class="form-label">邮件标题:</label>
                    <input type="text" class="form-control" id="send_subject" name="subject" value="感谢支持，请查收！" required>
                </div>
                 <div class="mb-3">
                    <label for="send_body" class="form-label">邮件正文:</label>
                    <textarea class="form-control" id="send_body" name="body" rows="3">来自管理员的操作。</textarea>
                </div>

                 <!-- Conditional Fields based on Action Type -->
                 <div id="conditional-fields">
                     <div class="mb-3 conditional" data-action-type="send_item" style="display: none;">
                        <label for="send_item_id" class="form-label">物品 ID:</label>
                        <input type="number" class="form-control" id="send_item_id" name="itemId" min="1">
                    </div>
                    <div class="mb-3 conditional" data-action-type="send_item" style="display: none;">
                        <label for="send_quantity" class="form-label">物品数量:</label>
                        <input type="number" class="form-control" id="send_quantity" name="quantity" value="1" min="1">
                    </div>
                    <div class="mb-3 conditional" data-action-type="send_gold" style="display: none;">
                        <label for="send_amount" class="form-label">金币数量 (铜币):</label>
                        <input type="number" class="form-control" id="send_amount" name="amount" min="1">
                    </div>
                 </div>

                <!-- Target Selection -->
                 <div class="mb-3">
                    <label class="form-label d-block">发送目标:</label>
                    <div class="form-check form-check-inline target-options">
                        <input class="form-check-input" type="radio" name="target_type" id="target_online" value="online" checked>
                        <label class="form-check-label" for="target_online">当前在线角色</label>
                    </div>
                     <div class="form-check form-check-inline target-options">
                        <input class="form-check-input" type="radio" name="target_type" id="target_custom" value="custom">
                        <label class="form-check-label" for="target_custom">自定义列表</label>
                    </div>
                 </div>

                 <!-- Custom List Input (Conditional) -->
                <div class="mb-3" id="custom-list-input" style="display: none;">
                    <label for="custom_char_list" class="form-label">自定义角色列表 (每行一个角色名):</label>
                    <textarea class="form-control" id="custom_char_list" name="custom_char_list" rows="5"></textarea>
                </div>

                 <button type="submit" class="btn btn-warning">发送邮件/物品/金币</button>
            </form>
        </section>

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sendActionTypeSelect = document.getElementById('send_action_type');
            const conditionalFieldsContainer = document.getElementById('conditional-fields');
            const conditionalInputs = conditionalFieldsContainer.querySelectorAll('.conditional');

            const targetTypeRadios = document.querySelectorAll('input[name="target_type"]');
            const customListInputDiv = document.getElementById('custom-list-input');
            const customListTextarea = document.getElementById('custom_char_list');

            // Show/hide conditional fields based on action type
            if (sendActionTypeSelect) {
                sendActionTypeSelect.addEventListener('change', function() {
                    const selectedAction = this.value;
                    conditionalInputs.forEach(inputDiv => {
                        if (inputDiv.dataset.actionType === selectedAction) {
                            inputDiv.style.display = 'block';
                             // Make inputs required only when visible
                            inputDiv.querySelectorAll('input, select, textarea').forEach(el => el.required = true);
                        } else {
                            inputDiv.style.display = 'none';
                            inputDiv.querySelectorAll('input, select, textarea').forEach(el => el.required = false);
                        }
                    });
                });
                 // Trigger change on load in case of pre-filled form (e.g., after error)
                sendActionTypeSelect.dispatchEvent(new Event('change'));
            }

             // Show/hide custom list input based on target type
             if (targetTypeRadios.length > 0) {
                 function toggleCustomList() {
                     const selectedTarget = document.querySelector('input[name="target_type"]:checked').value;
                     if (selectedTarget === 'custom') {
                         customListInputDiv.style.display = 'block';
                         customListTextarea.required = true;
                     } else {
                         customListInputDiv.style.display = 'none';
                         customListTextarea.required = false;
                     }
                 }
                 targetTypeRadios.forEach(radio => {
                     radio.addEventListener('change', toggleCustomList);
                 });
                  // Trigger on load
                 toggleCustomList();
             }

             // Optional: Add form submission confirmation, especially for "All Characters"
             const sendForm = document.getElementById('form-send');
             if (sendForm) {
                 sendForm.addEventListener('submit', function(event) {
                    const selectedTarget = document.querySelector('input[name="target_type"]:checked')?.value;
                    if (selectedTarget === 'custom') {
                        if (customListTextarea.value.trim() === '') {
                             alert('请在自定义列表中输入角色名称。');
                             event.preventDefault();
                        }
                    }
                    // Add validation for conditional required fields
                    const selectedAction = sendActionTypeSelect.value;
                    let conditionalValid = true;
                     conditionalInputs.forEach(inputDiv => {
                        if (inputDiv.dataset.actionType === selectedAction) {
                             inputDiv.querySelectorAll('input[required]').forEach(el => {
                                if (!el.value.trim()) {
                                    conditionalValid = false;
                                    el.classList.add('is-invalid'); // Optional: highlight invalid field
                                } else {
                                     el.classList.remove('is-invalid');
                                }
                             });
                        }
                     });
                     if (!conditionalValid) {
                        alert('请填写所选发送类型的所有必填项 (物品ID/数量 或 金币数量)。');
                        event.preventDefault();
                     }
                 });
             }
        });
    </script>
<?php endif; ?>
</body>
</html> 