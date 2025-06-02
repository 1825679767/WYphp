<?php
declare(strict_types=1);
session_start(); // Start session for login

require_once __DIR__ . '/../bag_query/db.php'; // Reuse db connection function
require_once __DIR__ . '/functions.php';
// require_once __DIR__ . '/../config.php'; // Need full config for SOAP link later
// var_dump($config); die(); // DEBUG: Check if config is loaded correctly - REMOVED
$config = require_once __DIR__ . '/../config.php'; // Capture the returned config array

// --- Login Logic (Similar to other modules) --- Start ---
$adminConf = $config['admin'] ?? null;
$isLoggedIn = false;
$loginError = '';

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

if (!$adminConf || !isset($adminConf['username']) || !isset($adminConf['password_hash'])) {
    // If admin config is missing or incomplete, treat as error or default to logged in?
    // For security, let's treat incomplete config as *not* logged in and prevent access.
    // $isLoggedIn = true; // Or handle missing admin config - Changed for security
    $loginError = '管理后台配置不完整，无法登录。'; // Inform user
} else {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_username'])) {
        $username = $_POST['login_username'];
        $password = $_POST['login_password'];
        // Verify username and password hash
        if ($username === $adminConf['username'] && isset($adminConf['password_hash']) && password_verify($password, $adminConf['password_hash'])) {
            $_SESSION['admin_logged_in'] = true;
            header('Location: index.php'); // Redirect to clear POST data
            exit;
        } else {
            $loginError = '无效的用户名或密码。';
        }
    }
    // Check session status *after* potential login attempt
    if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        $isLoggedIn = true;
    }
}
// --- Login Logic --- End ---

// --- Initialize variables used in both logged-in and logged-out states ---
$search_type = $_GET['search_type'] ?? 'username';
$search_value = trim($_GET['search_value'] ?? '');
$filter_status = $_GET['filter_status'] ?? 'all';
$error_message = ''; // Specific errors for logged-in state

// --- Initialize variables specific to logged-in state ---
$accounts_result = ['data' => [], 'total' => 0];
$accounts = [];
$total_accounts = 0;
$items_per_page = 10;
$current_page = 1;
$total_pages = 0;
$pdo_A = null;
$pdo_C = null;
$pdo_W = null;
$online_count = null; // Use null to indicate not fetched or error
$soapCommandUrl = '';

// --- Account Management Logic (only if logged in) ---
if ($isLoggedIn) {
    // Prepare SOAP base URL *only if logged in*
    if (isset($config['soap'])) { 
        $soapPagePath = '../soap/soap_command.php'; 
        $soapCommandUrl = $soapPagePath . '?prefill_command=';
    }

    // Get current page *only if logged in*
    $current_page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
    if ($current_page === false || $current_page < 1) $current_page = 1;

    try {
        $connections = connect_databases();
        $pdo_A = $connections['db_A'];
        $pdo_C = $connections['db_C'];
        $pdo_W = $connections['db_W'];

        if ($pdo_C) {
             $online_count = get_online_count($pdo_C);
        }

        $accounts_result = get_accounts($pdo_A, $pdo_C, $search_type, $search_value, $filter_status, $current_page, $items_per_page);
        $accounts = $accounts_result['data'];
        $total_accounts = $accounts_result['total'];
        
        if ($total_accounts > 0) {
            $total_pages = (int)ceil($total_accounts / $items_per_page);
            if ($current_page > $total_pages) {
                header('Location: index.php?page='. $total_pages . '&search_type=' . urlencode($search_type) . '&search_value=' . urlencode($search_value) . '&filter_status=' . urlencode($filter_status));
                exit;
            }
        } else {
             $total_pages = 1; // Ensure total_pages is at least 1 even if no results
        }

    } catch (Exception $e) {
        $error_message = "数据库连接或查询失败: " . $e->getMessage();
        // Reset data on error
        $accounts = [];
        $total_accounts = 0;
        $total_pages = 1;
        $online_count = null;
    } finally {
        // Ensure connections are closed if they were opened
        if (isset($pdo_A)) { $pdo_A = null; }
        if (isset($pdo_C)) { $pdo_C = null; }
        if (isset($pdo_W)) { $pdo_W = null; }
    }
}
// --- End Logged-in Logic ---

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>账号管理 - <?= $isLoggedIn ? '控制台' : '登录' ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../bag_query/style.css"> <!-- Reuse bag query styles -->
    <link rel="stylesheet" href="style.css"> <!-- Add module-specific styles -->
</head>
<body>

<?php if (!$isLoggedIn): ?>
    <!-- LOGIN FORM -->
    <div class="login-container">
        <h2>管理员登录 - 账号管理</h2>
        <?php if ($loginError): // Display login-specific errors here ?>
            <div class="alert alert-danger" role="alert"><?= htmlspecialchars($loginError) ?></div>
        <?php endif; ?>
        <form method="post" action="index.php"> <!-- Action points to self -->
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
         <div class="mt-3 text-center">
            <a href="../index.php" class="home-btn-link">&laquo; 返回主页</a>
        </div>
    </div>

<?php else: ?>
    <!-- ACCOUNT MANAGEMENT INTERFACE (Logged In) -->
    <div class="container mt-4 position-relative">
        <a href="../index.php" class="home-btn">&laquo; 返回主页</a>
        <a href="?logout=1" class="logout-btn">退出登录</a>
        <h2 class="text-center mb-4">账号管理</h2>

        <?php if ($error_message): // Display data fetching errors here ?>
            <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <!-- 搜索表单 -->
        <form method="GET" action="index.php" class="mb-4 query-form">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="search_type" class="form-label">搜索类型:</label>
                    <select id="search_type" name="search_type" class="form-select">
                        <option value="username" <?= $search_type === 'username' ? 'selected' : '' ?>>用户名</option>
                        <option value="id" <?= $search_type === 'id' ? 'selected' : '' ?>>账号 ID</option>
                        <option value="character_name" <?= $search_type === 'character_name' ? 'selected' : '' ?>>角色名</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="search_value" class="form-label">搜索值:</label>
                    <input type="text" id="search_value" name="search_value" class="form-control" value="<?= htmlspecialchars($search_value) ?>">
                </div>
                <div class="col-md-3">
                    <label for="filter_status" class="form-label">状态:</label>
                    <select id="filter_status" name="filter_status" class="form-select">
                        <option value="all" <?= $filter_status === 'all' ? 'selected' : '' ?>>全部</option>
                        <option value="online" <?= $filter_status === 'online' ? 'selected' : '' ?>>在线</option>
                        <option value="offline" <?= $filter_status === 'offline' ? 'selected' : '' ?>>离线</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-query w-100">搜索</button>
                </div>
            </div>
        </form>

        <!-- 账号列表 -->
        <div class="table-container mt-4">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="table-title mb-0">账号列表 (共 <?= $total_accounts ?> 条)</h4>
                <?php if ($online_count !== null): // Check for null instead of >= 0 ?>
                    <span class="online-count">在线人数: <?= $online_count ?></span>
                <?php else: ?>
                     <span class="online-count text-muted">在线人数: N/A</span> <!-- Show N/A -->
                <?php endif; ?>
            </div>
             <div class="table-responsive mt-2"> <!-- Add margin top to table -->
                <table class="table table-dark table-striped table-bordered table-hover align-middle account-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>用户名</th>
                            <th>GM 等级</th>
                            <th>状态</th>
                            <th>封禁状态</th>
                            <th>最后登录 IP</th>
                            <th>最后登录时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($accounts)): ?>
                            <?php foreach ($accounts as $acc): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string)$acc['id']) ?></td>
                                    <td><?= htmlspecialchars($acc['username']) ?></td>
                                    <td><?= isset($acc['gmlevel']) ? htmlspecialchars((string)$acc['gmlevel']) : 'N/A' ?></td>
                                    <td>
                                        <?php 
                                        if (isset($acc['online'])) { 
                                            echo $acc['online'] ? '<span class="badge bg-success">在线</span> ' : '<span class="badge bg-secondary">离线</span> '; 
                                        } else { echo 'N/A '; } 
                                        if (isset($acc['locked']) && $acc['locked']) echo '<span class="badge bg-warning text-dark">锁定</span> '; 
                                        if (isset($acc['mutetime']) && $acc['mutetime'] > time()) echo '<span class="badge bg-secondary">禁言</span>';
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        if (isset($acc['banned_active']) && $acc['banned_active']) {
                                            if (!isset($acc['unbandate']) || $acc['unbandate'] == 0) {
                                                echo '<span class="badge bg-danger">永久封禁</span>';
                                            } elseif ($acc['unbandate'] > time()) {
                                                echo '<span class="badge bg-warning text-dark">封禁至: ' . date('Y-m-d H:i', (int)$acc['unbandate']) . '</span>';
                                            } else {
                                                echo '<span class="badge bg-secondary">已过期</span>';
                                            }
                                        } else {
                                            echo '<span class="badge bg-success">正常</span>';
                                        }
                                        ?>
                                    </td>
                                    <td><?= isset($acc['last_ip']) ? htmlspecialchars($acc['last_ip']) : 'N/A' ?></td>
                                    <td><?= isset($acc['last_login']) && $acc['last_login'] ? date('Y-m-d H:i:s', strtotime($acc['last_login'])) : (isset($acc['last_login']) ? '从未' : 'N/A') ?></td>
                                    <td class="account-actions text-nowrap"> <!-- Add text-nowrap -->
                                        <button class="btn btn-sm btn-info action-btn" data-action="set_gmlevel" data-username="<?= htmlspecialchars($acc['username']) ?>" <?= !isset($acc['gmlevel']) ? 'disabled title="无法获取GM等级信息"' : '' ?>>设置GM</button>
                                        
                                        <?php if (isset($acc['banned_active']) && $acc['banned_active']): ?>
                                            <button class="btn btn-sm btn-success action-btn" data-action="unban_account" data-username="<?= htmlspecialchars($acc['username']) ?>">解封</button>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-warning action-btn" data-action="ban_account" data-username="<?= htmlspecialchars($acc['username']) ?>">封禁</button>
                                        <?php endif; ?>
                                        
                                        <button class="btn btn-sm btn-danger action-btn" data-action="set_password" data-username="<?= htmlspecialchars($acc['username']) ?>">重置密码</button>
                                        <button class="btn btn-sm btn-secondary view-chars-btn" data-account-id="<?= htmlspecialchars((string)$acc['id']) ?>" data-username="<?= htmlspecialchars($acc['username']) ?>">查看角色</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                         <?php elseif (!empty($search_value)): ?>
                            <tr>
                                <td colspan="8" class="text-center">未找到符合条件的账号。</td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center">请输入搜索条件以查找账号。</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
             <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav aria-label="Account pagination" class="mt-3 d-flex justify-content-center">
                <ul class="pagination">
                    <!-- Previous Button -->
                    <li class="page-item <?= ($current_page <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $current_page - 1 ?>&search_type=<?= urlencode($search_type) ?>&search_value=<?= urlencode($search_value) ?>&filter_status=<?= urlencode($filter_status) ?>">上一页</a>
                    </li>

                    <?php 
                    // Determine pagination range (e.g., show 5 links around current page)
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $current_page + 2);
                    // Adjust if near the beginning or end
                    if ($current_page <= 3) {
                        $end_page = min($total_pages, 5);
                    }
                    if ($current_page >= $total_pages - 2) {
                        $start_page = max(1, $total_pages - 4);
                    }

                    // Show first page link if needed
                    if ($start_page > 1) {
                        echo '<li class="page-item"><a class="page-link" href="?page=1&search_type='.urlencode($search_type).'&search_value='.urlencode($search_value).'&filter_status='.urlencode($filter_status).'">1</a></li>';
                        if ($start_page > 2) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                    }

                    // Page number links
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <li class="page-item <?= ($i === $current_page) ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&search_type=<?= urlencode($search_type) ?>&search_value=<?= urlencode($search_value) ?>&filter_status=<?= urlencode($filter_status) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>

                    <!-- Show last page link if needed -->
                    <?php if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) {
                             echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                        echo '<li class="page-item"><a class="page-link" href="?page='.$total_pages.'&search_type='.urlencode($search_type).'&search_value='.urlencode($search_value).'&filter_status='.urlencode($filter_status).'">'.$total_pages.'</a></li>';
                    } ?>

                    <!-- Next Button -->
                    <li class="page-item <?= ($current_page >= $total_pages) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $current_page + 1 ?>&search_type=<?= urlencode($search_type) ?>&search_value=<?= urlencode($search_value) ?>&filter_status=<?= urlencode($filter_status) ?>">下一页</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>

    <!-- Character Modal -->
    <div class="modal fade" id="characterModal" tabindex="-1" aria-labelledby="characterModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content bg-dark text-light">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title" id="characterModalLabel">账号角色</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="characterModalBody">
                    <!-- Character list will be loaded here -->
                    <p>正在加载角色...</p>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">关闭</button>
                </div>
            </div>
        </div>
    </div>
    <!-- End Character Modal -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script> <!-- Ensure Bootstrap JS is included -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const actionButtons = document.querySelectorAll('.action-btn');
            const viewCharsButtons = document.querySelectorAll('.view-chars-btn'); // Get view chars buttons
            const characterModal = new bootstrap.Modal(document.getElementById('characterModal')); // Initialize modal
            const characterModalBody = document.getElementById('characterModalBody');
            const characterModalLabel = document.getElementById('characterModalLabel');
            let characterModalTrigger = null; // <<< 新增：存储触发模态框的按钮

            // Helper function for HTML escaping (simple version) - Moved BEFORE usage
            function htmlspecialchars(str) {
                if (typeof str !== 'string') return str;
                return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
            }
            
            // Function to format copper into G S C with colors - Moved BEFORE usage
            function formatGold(copper) {
                if (copper === null || copper === undefined || isNaN(copper)) {
                    return 'N/A';
                }
                copper = parseInt(copper, 10);
                const gold = Math.floor(copper / 10000);
                const silver = Math.floor((copper % 10000) / 100);
                const copperRemainder = copper % 100;
                
                let result = '';
                if (gold > 0) {
                    result += `<span class="money-gold">${gold}金</span> `;
                }
                if (silver > 0 || gold > 0) { // Show silver if silver > 0 or if gold > 0 (to show 0s)
                    result += `<span class="money-silver">${silver}银</span> `;
                }
                result += `<span class="money-copper">${copperRemainder}铜</span>`;
                
                // If result is empty (e.g., 0 copper), show 0 copper
                if (result.trim() === '' && copper === 0) {
                     return '<span class="money-copper">0铜</span>';
                }

                return result.trim();
            }

            // --- Existing Action Button Listeners --- 
            actionButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const action = this.dataset.action;
                    const username = this.dataset.username;
                    handleAccountAction(action, username);
                });
            });

            // --- New View Characters Button Listeners --- 
            viewCharsButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const accountId = this.dataset.accountId;
                    const accountUsername = this.dataset.username; // Get username for title
                    characterModalTrigger = this; // <<< 新增：在显示前存储触发按钮

                    characterModalLabel.textContent = `账号 "${accountUsername}" 的角色`;
                    characterModalBody.innerHTML = '<p class="text-center">正在加载...</p>'; // Show loading state
                    characterModal.show();

                    const formData = new FormData();
                    formData.append('action', 'get_characters');
                    formData.append('accountId', accountId);

                    fetch('action_handler.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.characters) {
                            // Add Gold and Actions column headers
                            let tableHtml = '<table class="table table-sm table-dark table-striped table-bordered"><thead><tr><th>GUID</th><th>名称</th><th>等级</th><th>种族</th><th>职业</th><th>状态</th><th>金币</th><th>操作</th></tr></thead><tbody>';
                            if (data.characters.length > 0) {
                                data.characters.forEach(char => {
                                    // Add gold cell and action buttons cell with data-char-name
                                    tableHtml += `<tr>
                                                    <td>${char.guid}</td>
                                                    <td>${htmlspecialchars(char.name)}</td>
                                                    <td>${char.level}</td>
                                                    <td>${htmlspecialchars(char.race)}</td>
                                                    <td>${htmlspecialchars(char.class)}</td>
                                                    <td><span class="badge bg-${char.online === '在线' ? 'success' : 'secondary'}">${htmlspecialchars(char.online)}</span></td>
                                                    <td>${formatGold(char.money)}</td>
                                                    <td class="character-actions text-nowrap">
                                                        <button class="btn btn-sm btn-warning char-action-btn" data-action="send_gold" data-char-name="${htmlspecialchars(char.name)}">发金币</button>
                                                        <button class="btn btn-sm btn-primary char-action-btn" data-action="send_item" data-char-name="${htmlspecialchars(char.name)}">发物品</button>
                                                        <button class="btn btn-sm btn-info char-action-btn" data-action="send_whisper" data-char-name="${htmlspecialchars(char.name)}">私聊</button>
                                                        <button class="btn btn-sm btn-danger char-action-btn" data-action="kick_player" data-char-name="${htmlspecialchars(char.name)}">踢下线</button>
                                                        <button class="btn btn-sm btn-success char-action-btn" data-action="unstuck_player" data-char-name="${htmlspecialchars(char.name)}">解卡</button>
                                                    </td>
                                                  </tr>`;
                                });
                            } else {
                                tableHtml += '<tr><td colspan="8" class="text-center">该账号下无角色。</td></tr>'; // Increased colspan to 8
                            }
                            tableHtml += '</tbody></table>';
                            characterModalBody.innerHTML = tableHtml;

                            // Add event listeners to the new character action buttons *after* table is added
                            addCharacterActionListeners(); 

                        } else {
                            characterModalBody.innerHTML = `<p class="text-danger text-center">加载角色失败: ${data.message || '未知错误'}</p>`;
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching characters:', error);
                        characterModalBody.innerHTML = '<p class="text-danger text-center">加载角色时发生网络或脚本错误。</p>';
                    });
                });
            });
            
            // Function to add listeners to character action buttons
            function addCharacterActionListeners() {
                const charActionButtons = document.querySelectorAll('.char-action-btn');
                charActionButtons.forEach(button => {
                    // Remove existing listener to prevent duplicates if modal is reopened
                    button.removeEventListener('click', handleCharacterActionClick);
                    // Add the listener
                    button.addEventListener('click', handleCharacterActionClick);
                });
            }
            
            // Separate click handler function
            function handleCharacterActionClick() {
                const action = this.dataset.action;
                const charName = this.dataset.charName;
                handleCharacterAction(action, charName);
            }

            function handleAccountAction(action, username) {
                let params = {};
                let promptMessage = '';
                let promptDefault = '';
                let userInput = null;
                let userInput2 = null;

                switch (action) {
                    case 'set_gmlevel':
                        promptMessage = `为账号 ${username} 设置 GM 等级 (0-4):`;
                        promptDefault = '0';
                        userInput = prompt(promptMessage, promptDefault);
                        if (userInput === null) return; // Cancelled
                        const level = parseInt(userInput, 10);
                        if (isNaN(level) || level < 0 || level > 4) { // Assuming max GM level 4
                            alert('无效的 GM 等级。'); return;
                        }
                        params.level = level;
                        // Optionally ask for RealmID too if needed
                        break;

                    case 'ban_account':
                        promptMessage = `封禁账号 ${username} 的时长 (例如: 1d, 2h, 3m, 0=永久):`;
                        promptDefault = '1d';
                        userInput = prompt(promptMessage, promptDefault);
                        if (userInput === null || userInput.trim() === '') return; // Cancelled or empty
                        params.duration = userInput.trim();
                        
                        promptMessage = `封禁原因 (可选):`;
                        userInput2 = prompt(promptMessage, '-');
                         if (userInput2 === null) return; // Cancelled
                        params.reason = userInput2.trim() || '-';
                        break;

                    case 'unban_account':
                        if (!confirm(`确定要解封账号 ${username} 吗？`)) {
                             return; // Cancelled
                        }
                        // No extra params needed for unban
                        break;

                    case 'set_password':
                        promptMessage = `为账号 ${username} 设置新密码:`;
                        userInput = prompt(promptMessage);
                        if (userInput === null || userInput.trim() === '') { // Cancelled or empty
                            alert('密码不能为空。'); return;
                        } 
                        // Consider adding password confirmation prompt
                        params.password = userInput; // No trim() for passwords
                        break;
                    
                    default:
                        alert('未知的操作。'); return;
                }

                // Perform AJAX request
                sendAccountActionRequest(action, username, params);
            }

            // New function to handle character actions
            function handleCharacterAction(action, charName) {
                let params = {};
                let promptMessage = '';
                let promptDefault = '';
                let userInput = null;
                let userInput2 = null;

                switch (action) {
                    case 'send_gold':
                        promptMessage = `向角色 ${charName} 发送多少金币？(输入纯数字)`;
                        promptDefault = '10000'; // 1 Gold
                        userInput = prompt(promptMessage, promptDefault);
                        if (userInput === null) return; // Cancelled
                        const amount = parseInt(userInput.replace(/\D/g, ''), 10); // Remove non-digits and parse
                        if (isNaN(amount) || amount <= 0) {
                            alert('无效的金币数量。'); return;
                        }
                        params.amount = amount;
                        break;
                    
                    case 'send_item':
                        promptMessage = `向角色 ${charName} 发送物品 ID:`;
                        userInput = prompt(promptMessage);
                        if (userInput === null || userInput.trim() === '') return; // Cancelled or empty
                        const itemId = parseInt(userInput.trim(), 10);
                        if (isNaN(itemId) || itemId <= 0) {
                            alert('无效的物品 ID。'); return;
                        }
                        params.itemId = itemId;

                        promptMessage = `发送数量:`;
                        promptDefault = '1';
                        userInput2 = prompt(promptMessage, promptDefault);
                        if (userInput2 === null) return; // Cancelled
                        const quantity = parseInt(userInput2.trim(), 10);
                         if (isNaN(quantity) || quantity <= 0) {
                            alert('无效的数量。'); return;
                        }
                        params.quantity = quantity;
                        break;

                    case 'send_whisper':
                        promptMessage = `向角色 ${charName} 发送私聊消息:`;
                        userInput = prompt(promptMessage);
                        if (userInput === null) return; // Cancelled
                        const message = userInput.trim();
                        if (message === '') {
                            alert('消息内容不能为空。'); return;
                        }
                        params.message = message;
                        break;

                    case 'kick_player':
                        if (!confirm(`确定要将角色 ${charName} 踢下线吗？`)) {
                            return; // Cancelled
                        }
                        // No extra params needed for kick
                        break;

                    case 'unstuck_player':
                         if (!confirm(`确定要将角色 ${charName} 传送到最近的旅店吗？`)) {
                            return; // Cancelled
                        }
                        // No extra params needed for unstuck
                        break;

                    default:
                        alert('未知的角色操作。'); return;
                }

                // Send request using a specific function or the modified one
                sendCharacterActionRequest(action, charName, params);
            }

            // Renamed and potentially modified function for sending account actions
            function sendAccountActionRequest(action, username, params) {
                const formData = new FormData();
                formData.append('action', action);
                formData.append('accountName', username);
                for (const key in params) {
                     formData.append(`params[${key}]`, params[key]);
                }
                sendRequest('action_handler.php', formData); // Use generic send function
            }
            
            // New function specifically for character actions
            function sendCharacterActionRequest(action, charName, params) {
                const formData = new FormData();
                formData.append('action', action);
                formData.append('charName', charName); // Send charName instead of accountName
                 for (const key in params) {
                     formData.append(`params[${key}]`, params[key]);
                }
                 sendRequest('action_handler.php', formData); // Use generic send function
            }

            // Generic function to send fetch request and handle response
            function sendRequest(url, formData) {
                showLoadingIndicator(true); 
                fetch(url, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    showLoadingIndicator(false);
                    alert(data.message); // Show response message
                    // Optionally refresh part of the page or the whole page on success
                    // No reload needed for sending items/gold usually, unless we want to update something visually.
                    // if (data.success) { 
                    //    // Maybe update something specific or just rely on the alert
                    // }
                })
                .catch(error => {
                    showLoadingIndicator(false);
                    console.error('Error during action request:', error);
                    alert('执行操作时发生网络或脚本错误。');
                });
            }

            // Simple loading indicator (optional)
            function showLoadingIndicator(show) {
                let indicator = document.getElementById('loading-indicator');
                if (!indicator) {
                    indicator = document.createElement('div');
                    indicator.id = 'loading-indicator';
                    indicator.textContent = '处理中...';
                    indicator.style.position = 'fixed';
                    indicator.style.top = '10px';
                    indicator.style.right = '10px';
                    indicator.style.padding = '5px 10px';
                    indicator.style.backgroundColor = 'rgba(0,0,0,0.7)';
                    indicator.style.color = 'white';
                    indicator.style.borderRadius = '4px';
                    indicator.style.zIndex = '1000';
                    indicator.style.display = 'none';
                    document.body.appendChild(indicator);
                }
                indicator.style.display = show ? 'block' : 'none';
            }

            // <<< 新增：监听模态框隐藏事件以返回焦点
            const characterModalElement = document.getElementById('characterModal');
            if (characterModalElement) {
                characterModalElement.addEventListener('hidden.bs.modal', function () {
                    if (characterModalTrigger) {
                        characterModalTrigger.focus(); // 将焦点设置回触发按钮
                        characterModalTrigger = null; // 清除引用
                    }
                });
            }
        });
    </script>
<?php endif; ?>
</body>
</html> 