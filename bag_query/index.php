<?php
// Main entry point for Bag Query system
declare(strict_types=1);
session_start(); // Start session for login

require_once 'db.php';
require_once 'functions.php';

// --- Login Logic --- Start ---
$config = require __DIR__ . '/../config.php'; // Load config for admin credentials
$adminConf = $config['admin'] ?? null;

$isLoggedIn = false;
$loginError = '';

// 1. Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php'); // Redirect to login page
    exit;
}

// 2. Handle login form submission
if (!$adminConf) {
    // If admin config is missing, treat as fatal error or skip auth?
    // For now, we'll allow access but maybe show a warning.
    // In a real scenario, this should probably block access.
    //$error_message = "Admin configuration missing!";
    $isLoggedIn = true; // Or handle this differently
} else {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_username'])) {
        $username = $_POST['login_username'];
        $password = $_POST['login_password'];

        // Verify username and password hash
        if ($username === $adminConf['username'] && isset($adminConf['password_hash']) && password_verify($password, $adminConf['password_hash'])) {
            $_SESSION['admin_logged_in'] = true;
            header('Location: index.php'); // Redirect after successful login
            exit;
        } else {
            $loginError = '无效的用户名或密码。';
        }
    }

    // 3. Check login status
    if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        $isLoggedIn = true;
    }
}
// --- Login Logic --- End ---

// Initialize variables for bag query (only relevant if logged in)
$query_type = '';
$query_value = '';
$characters = [];
$selected_character_guid = null;
$items = [];
$error_message = ''; // Reset error message for bag query logic
$pdo_A = null;
$pdo_C = null;
$pdo_W = null;
$dbCName = '';
$dbWName = '';

// Execute bag query logic only if logged in
if ($isLoggedIn) {
    // Prioritize GET parameters if viewing items, otherwise use POST or defaults
    $viewing_items_mode = isset($_GET['view_char']);
    $query_type = $viewing_items_mode ? ($_GET['query_type'] ?? 'account') : ($_POST['query_type'] ?? 'account');
    $query_value = $viewing_items_mode ? trim($_GET['query_value'] ?? '') : trim($_POST['query_value'] ?? '');
    $selected_character_guid = $_GET['view_char'] ?? null;

    // Establish DB connections
    try {
        $connections = connect_databases();
        $pdo_A = $connections['db_A'];
        $pdo_C = $connections['db_C'];
        $pdo_W = $connections['db_W'];

        // Get DB names for joins (config already loaded above)
        // $config = require __DIR__ . '/../config.php';
        $dbCName = $config['databases']['db_C']['database'];
        $dbWName = $config['databases']['db_W']['database'];

    } catch (Exception $e) {
        $error_message = "Database connection or configuration failed: " . $e->getMessage();
        // Invalidate connections on failure
        $pdo_A = $pdo_C = $pdo_W = null;
    }

    // Handle Character Search (POST request)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($query_value) && !isset($_POST['login_username'])) { // Check it's not a login post
        try {
            // Call the updated function from functions.php
            if ($pdo_A && $pdo_C) { // Ensure both connections are valid
                $characters = find_characters($pdo_A, $pdo_C, $query_type, $query_value); 
            } else {
                 $error_message = "数据库连接不完整，无法查询角色。";
                 $characters = [];
            }
        } catch (Exception $e) {
            $error_message = "查询角色时出错: " . $e->getMessage();
            $characters = []; // Ensure characters is empty on error
        }
    }

    // Handle Item View Request (GET request with view_char)
    if ($selected_character_guid && $pdo_C) {
        try {
            // Re-fetch characters for context if needed (e.g., direct link access)
            // But only if characters haven't been fetched via POST already
            if (empty($characters) && !empty($query_value)) { 
                if ($pdo_A && $pdo_C) { // Ensure both connections are valid
                     $characters = find_characters($pdo_A, $pdo_C, $query_type, $query_value); 
                } else {
                    $error_message = "数据库连接不完整，无法重新加载角色列表。";
                    $characters = [];
                }
            }
            if ($pdo_W) {
                 $items = get_character_items($pdo_C, $pdo_W, (int)$selected_character_guid, $dbCName, $dbWName);
            }
        } catch (Exception $e) {
            $error_message = "获取物品或重新加载角色列表时出错: " . $e->getMessage();
            $items = []; // Ensure items is empty on error
        }
    }
}
// --- End Bag Query Logic ---

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>物品查询 - <?= $isLoggedIn ? '控制台' : '登录' ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php if (!$isLoggedIn): ?>
    <!-- LOGIN FORM -->
    <div class="login-container">
        <h2>管理员登录 - 物品查询</h2>
        <?php if ($loginError): ?>
            <div class="alert alert-danger" role="alert">
                <?= htmlspecialchars($loginError) ?>
            </div>
        <?php endif; ?>
        <form method="post">
            <div class="mb-3">
                <label for="login_username" class="form-label">用户名</label>
                <input type="text" class="form-control" id="login_username" name="login_username" required>
            </div>
            <div class="mb-3">
                <label for="login_password" class="form-label">密码</label>
                <input type="password" class="form-control" id="login_password" name="login_password" required>
            </div>
            <button type="submit" class="btn btn-query w-100">登录</button> <!-- Use btn-query style -->
        </form>
    </div>

<?php else: ?>
    <!-- BAG QUERY INTERFACE -->
    <div class="container mt-4 position-relative"> <!-- Ensure position-relative -->
        <a href="../index.php" class="home-btn">&laquo; 返回主页</a> <!-- Add Home Button -->
        <a href="?logout=1" class="logout-btn">退出登录</a>
        <h2 class="text-center mb-4">魔兽角色物品查询系统</h2>

        <?php if ($error_message): ?>
            <div class="alert alert-danger" role="alert">
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <!-- 查询表单 -->
        <form method="POST" action="index.php" class="mb-4 query-form">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="query_type" class="form-label">查询类型:</label>
                    <select id="query_type" name="query_type" class="form-select">
                        <option value="character_name" <?= $query_type === 'character_name' ? 'selected' : '' ?>>角色名称</option>
                        <option value="username" <?= $query_type === 'username' ? 'selected' : '' ?>>账号用户名</option>
                    </select>
                </div>
                <div class="col-md-7">
                    <label for="query_value" class="form-label">查询值:</label>
                    <input type="text" id="query_value" name="query_value" class="form-control" value="<?= htmlspecialchars($query_value) ?>" required>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-query w-100">查询</button>
                </div>
            </div>
        </form>

        <!-- 角色和物品展示区域 -->
        <div class="row mt-4">
            <!-- 角色列表 -->
            <div class="col-lg-5 mb-4 mb-lg-0">
                <div class="table-container">
                    <h4 class="table-title">角色列表</h4>
                    <div class="table-responsive">
                        <table class="table table-dark table-striped table-bordered table-hover align-middle character-table">
                            <thead>
                                <tr>
                                    <th>角色GUID</th>
                                    <th>角色名称</th>
                                    <th>等级</th>
                                    <th>种族</th>
                                    <th>职业</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($characters)): ?>
                                    <?php foreach ($characters as $char): ?>
                                        <tr>
                                            <td><?= htmlspecialchars((string)$char['guid']) ?></td>
                                            <td><?= htmlspecialchars($char['name']) ?></td>
                                            <td><?= htmlspecialchars((string)$char['level']) ?></td>
                                            <td><?= htmlspecialchars(get_race_name($char['race'])) ?></td> 
                                            <td><?= htmlspecialchars(get_class_name($char['class'])) ?></td>
                                            <td>
                                                <a href="index.php?view_char=<?= $char['guid'] ?>&query_type=<?= urlencode($query_type) ?>&query_value=<?= urlencode($query_value) ?>" class="btn btn-sm btn-view">查看物品</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['login_username'])): ?>
                                    <tr>
                                        <td colspan="6" class="text-center">未找到符合条件的角色。</td>
                                    </tr>
                                <?php else: ?>
                                     <tr>
                                        <td colspan="6" class="text-center">请输入查询条件并点击查询。</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- 物品列表 -->
            <div class="col-lg-7">
                <div class="table-container">
                    <!-- Title and Search on the same line -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h4 class="table-title mb-0 pb-0 border-0">物品列表</h4>
                        <!-- 物品搜索 -->
                        <div class="item-search">
                            <label for="item_search" class="form-label me-1 mb-0">搜索物品:</label>
                            <input type="text" id="item_search" class="form-control form-control-sm d-inline-block w-auto" placeholder="输入物品名称...">
                        </div>
                    </div>
                    <?php if ($selected_character_guid && !empty($items)): ?>
                       <!-- Display character GUID below title/search -->
                       <p class="mb-2">显示角色 GUID: <?= htmlspecialchars((string)$selected_character_guid) ?> 的物品</p>
                       <div class="table-responsive item-table-wrapper">
                           <table class="table table-dark table-striped table-bordered table-hover align-middle item-table">
                               <thead>
                                   <tr>
                                       <th>物品ID (Entry)</th>
                                       <th>物品名称</th>
                                       <th>品质</th>
                                       <th>数量</th>
                                       <th>分类/位置</th> <!-- Bag/Slot -->
                                       <th>操作</th>
                                   </tr>
                               </thead>
                               <tbody>
                                    <?php foreach ($items as $item): ?>
                                      <tr data-item-name="<?= strtolower(htmlspecialchars($item['name'] ?? '')) ?>">
                                          <td><?= htmlspecialchars((string)$item['itemEntry']) ?></td>
                                          <td>
                                            <a href="https://wotlk.cavernoftime.com/item=<?= $item['itemEntry'] ?>" target="_blank" class="item-link quality-<?= $item['Quality'] ?? 0 ?>">
                                                <?= htmlspecialchars($item['name'] ?? '未知物品') ?>
                                            </a>
                                           </td>
                                          <td class="quality-<?= $item['Quality'] ?? 0 ?>"><?= get_quality_name($item['Quality'] ?? 0) ?></td>
                                          <td data-current-count="<?= (int)($item['count'] ?? 0) ?>"><?= htmlspecialchars((string)$item['count']) ?></td>
                                          <td><?= get_inventory_type_name($item['bag'] ?? -1, $item['slot'] ?? -1) ?></td>
                                          <td>
                                              <button class="btn btn-sm btn-delete" data-item-instance="<?= $item['item_instance_guid'] ?>" data-character-guid="<?= $selected_character_guid ?>">删除</button>
                                          </td>
                                      </tr>
                                   <?php endforeach; ?>
                               </tbody>
                           </table>
                       </div>
                    <?php elseif ($selected_character_guid): ?>
                        <p class="text-center">未找到该角色的物品信息，或角色背包为空。</p>
                    <?php else: ?>
                         <p class="text-center">请先查询角色，然后点击"查看物品"。</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Basic Item Search (Client-side)
        const itemSearchInput = document.getElementById('item_search');
        if (itemSearchInput) {
            itemSearchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase().trim();
                const itemRows = document.querySelectorAll('.item-table tbody tr');
                itemRows.forEach(row => {
                    const itemName = row.getAttribute('data-item-name');
                    if (itemName && itemName.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        }

        const deleteButtons = document.querySelectorAll('.btn-delete');
        deleteButtons.forEach(button => {
            button.addEventListener('click', function() {
                const itemInstanceGuid = this.dataset.itemInstance;
                const characterGuid = this.dataset.characterGuid;
                const row = this.closest('tr');
                const quantityCell = row.querySelector('td[data-current-count]');
                const currentCount = parseInt(quantityCell.dataset.currentCount || '0', 10);
                const itemName = row.querySelector('.item-link').textContent.trim();

                const quantityToDeleteStr = prompt(`要删除多少个 "${itemName}"？\n当前数量: ${currentCount}`, "1");

                if (quantityToDeleteStr === null) {
                    return; // 用户取消
                }

                const quantityToDelete = parseInt(quantityToDeleteStr, 10);

                // 验证数量
                if (isNaN(quantityToDelete) || quantityToDelete <= 0) {
                    alert('请输入一个有效的正整数数量。');
                    return;
                }
                if (quantityToDelete > currentCount) {
                    alert(`删除数量 (${quantityToDelete}) 不能超过当前数量 (${currentCount})。`);
                    return;
                }

                // 发送 AJAX 请求
                const formData = new FormData();
                formData.append('character_guid', characterGuid);
                formData.append('item_instance_guid', itemInstanceGuid);
                formData.append('quantity', quantityToDelete);

                fetch('delete_item.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message); // 显示成功消息
                        if (data.new_count <= 0) {
                            row.remove(); // 完全删除，移除行
                        } else {
                            // 更新数量显示
                            quantityCell.textContent = data.new_count;
                            quantityCell.dataset.currentCount = data.new_count;
                        }
                    } else {
                        alert('删除失败: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error during fetch:', error);
                    alert('删除过程中发生网络或脚本错误。');
                });
            });
        });
    </script>
<?php endif; ?>
</body>
</html> 