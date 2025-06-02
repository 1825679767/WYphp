<?php
declare(strict_types=1);
session_start();

// --- Setup Wizard Logic ---
$current_step_param = $_GET['step'] ?? 1; 

// --- Handle Form Submissions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($current_step_param === '2_submit') {
        // --- Process Step 2 Form Data (DB & SOAP) ---
        // Initialize session array if it doesn't exist
        if (!isset($_SESSION['setup_data'])) {
            $_SESSION['setup_data'] = [];
        }
        $_SESSION['setup_data']['db_A']['host'] = trim($_POST['db_A_host'] ?? '127.0.0.1');
        $_SESSION['setup_data']['db_A']['port'] = (int)($_POST['db_A_port'] ?? 3306);
        $_SESSION['setup_data']['db_A']['database'] = trim($_POST['db_A_database'] ?? 'acore_auth');
        $_SESSION['setup_data']['db_A']['username'] = trim($_POST['db_A_username'] ?? 'root');
        $_SESSION['setup_data']['db_A']['password'] = $_POST['db_A_password'] ?? ''; // Don't trim password
        $_SESSION['setup_data']['db_A']['charset'] = 'utf8mb4'; // Default charset

        $_SESSION['setup_data']['db_C']['host'] = trim($_POST['db_C_host'] ?? '127.0.0.1');
        $_SESSION['setup_data']['db_C']['port'] = (int)($_POST['db_C_port'] ?? 3306);
        $_SESSION['setup_data']['db_C']['database'] = trim($_POST['db_C_database'] ?? 'acore_characters');
        $_SESSION['setup_data']['db_C']['username'] = trim($_POST['db_C_username'] ?? 'root');
        $_SESSION['setup_data']['db_C']['password'] = $_POST['db_C_password'] ?? '';
        $_SESSION['setup_data']['db_C']['charset'] = 'utf8mb4';

        $_SESSION['setup_data']['db_W']['host'] = trim($_POST['db_W_host'] ?? '127.0.0.1');
        $_SESSION['setup_data']['db_W']['port'] = (int)($_POST['db_W_port'] ?? 3306);
        $_SESSION['setup_data']['db_W']['database'] = trim($_POST['db_W_database'] ?? 'acore_world');
        $_SESSION['setup_data']['db_W']['username'] = trim($_POST['db_W_username'] ?? 'root');
        $_SESSION['setup_data']['db_W']['password'] = $_POST['db_W_password'] ?? '';
        $_SESSION['setup_data']['db_W']['charset'] = 'utf8mb4';

        $_SESSION['setup_data']['soap']['host'] = trim($_POST['soap_host'] ?? '127.0.0.1');
        $_SESSION['setup_data']['soap']['port'] = (int)($_POST['soap_port'] ?? 7878);
        $_SESSION['setup_data']['soap']['username'] = trim($_POST['soap_username'] ?? 'soap_user');
        $_SESSION['setup_data']['soap']['password'] = $_POST['soap_password'] ?? 'soap_pass'; // Don't trim password
        $_SESSION['setup_data']['soap']['uri'] = trim($_POST['soap_uri'] ?? 'urn:AC');

        // Redirect to Step 3 (Connection Test)
        header('Location: setup.php?step=3');
        exit;
    } elseif ($current_step_param === '4_submit') {
        // --- Process Step 4 Form Data (Admin Account) ---
        $admin_username = trim($_POST['admin_username'] ?? '');
        $admin_password = $_POST['admin_password'] ?? '';
        $admin_password_confirm = $_POST['admin_password_confirm'] ?? '';
        $errors = [];

        if (empty($admin_username)) {
            $errors[] = "管理员用户名不能为空。";
        }
        if (empty($admin_password)) {
            $errors[] = "密码不能为空。";
        } elseif ($admin_password !== $admin_password_confirm) {
            $errors[] = "两次输入的密码不匹配。";
        }
        // Add more validation if needed (e.g., password complexity)

        if (empty($errors)) {
            $_SESSION['setup_data']['admin']['username'] = $admin_username;
            // Store HASHED password only
            $_SESSION['setup_data']['admin']['password_hash'] = password_hash($admin_password, PASSWORD_DEFAULT); 
            // Remove plain text password storage
            // unset($_SESSION['setup_data']['admin']['password']); // Ensure it's removed if previously set

            // Redirect to Step 5 (Finish)
            header('Location: setup.php?step=5');
            exit;
        } else {
            // Store errors in session to display them on Step 4 again
            $_SESSION['setup_errors'] = $errors;
            // Redirect back to Step 4 to show errors
            header('Location: setup.php?step=4');
            exit;
        }
    }
    // --- Add processing for other steps (e.g., step 4) here later ---
}

// Determine which step to display (use GET param unless submitting)
$stepToShow = $current_step_param; 
if ($stepToShow === '2_submit') { // If submitted step 2, we redirect, so effectively show step 3 next GET request
   $stepToShow = 3; 
} elseif ($stepToShow === '4_submit') {
    // If submission failed, we redirect back to 4, so show 4.
    // If submission succeeded, we redirect to 5 (handled above).
    $stepToShow = 4; 
}
$stepToShow = (int)$stepToShow;
if ($stepToShow < 1) $stepToShow = 1;

// --- Retrieve and clear any submission errors ---
$setup_errors = $_SESSION['setup_errors'] ?? [];
unset($_SESSION['setup_errors']); // Clear errors after retrieving

// Load existing config for warnings and defaults only if not submitting step 2
$configExistsWarning = "";
if ($current_step_param !== '2_submit') {
    $configFilePath = __DIR__ . '/config.php';
    if (file_exists($configFilePath)) {
        $configExistsWarning = "警告：<code>config.php</code> 文件已存在。继续操作将可能覆盖现有配置。";
    }
}

// --- Step 1: Environment Check (Only run if needed for display) ---
$php_version_ok = false;
$pdo_mysql_ok = false;
$soap_ok = false;
$mbstring_ok = false;
$all_checks_ok = false;
if ($stepToShow === 1) { 
    $php_version_required = '7.4';
    $php_version_ok = version_compare(PHP_VERSION, $php_version_required, '>=');
    $pdo_mysql_ok = extension_loaded('pdo_mysql');
    $soap_ok = extension_loaded('soap');
    $mbstring_ok = extension_loaded('mbstring'); 
    $all_checks_ok = $php_version_ok && $pdo_mysql_ok && $soap_ok && $mbstring_ok;
}

// --- Step 3: Connection Test Logic (Run only when displaying Step 3) ---
$test_results = [];
$all_connections_ok = false; 
if ($stepToShow === 3) {
    if (!isset($_SESSION['setup_data'])) {
        // 如果没有 Session 数据，可能需要重定向回步骤 2
        // 为简单起见，这里假设数据存在，但在实际应用中应添加错误处理
        $test_results[] = ['name' => '错误', 'status' => false, 'message' => '无法找到配置数据，请返回上一步重新输入。'];
    } else {
        $db_A_ok = false;
        $db_C_ok = false;
        $db_W_ok = false;
        $soap_ok = false;
        $config = $_SESSION['setup_data'];

        // Test Auth DB
        $dsn_A = "mysql:host={$config['db_A']['host']};port={$config['db_A']['port']};dbname={$config['db_A']['database']};charset={$config['db_A']['charset']}";
        try {
            $pdo_A = new PDO($dsn_A, $config['db_A']['username'], $config['db_A']['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Throw exceptions on error
                PDO::ATTR_TIMEOUT => 5 // Set connection timeout (seconds)
            ]);
            $db_A_ok = true;
            $test_results[] = ['name' => 'Auth 数据库', 'status' => true, 'message' => '连接成功'];
            $pdo_A = null; // Close connection
        } catch (PDOException $e) {
            $test_results[] = ['name' => 'Auth 数据库', 'status' => false, 'message' => '连接失败: ' . $e->getMessage()];
        }

        // Test Characters DB
        $dsn_C = "mysql:host={$config['db_C']['host']};port={$config['db_C']['port']};dbname={$config['db_C']['database']};charset={$config['db_C']['charset']}";
        try {
            $pdo_C = new PDO($dsn_C, $config['db_C']['username'], $config['db_C']['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
            $db_C_ok = true;
            $test_results[] = ['name' => 'Characters 数据库', 'status' => true, 'message' => '连接成功'];
            $pdo_C = null;
        } catch (PDOException $e) {
            $test_results[] = ['name' => 'Characters 数据库', 'status' => false, 'message' => '连接失败: ' . $e->getMessage()];
        }

        // Test World DB
        $dsn_W = "mysql:host={$config['db_W']['host']};port={$config['db_W']['port']};dbname={$config['db_W']['database']};charset={$config['db_W']['charset']}";
        try {
            $pdo_W = new PDO($dsn_W, $config['db_W']['username'], $config['db_W']['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
            $db_W_ok = true;
            $test_results[] = ['name' => 'World 数据库', 'status' => true, 'message' => '连接成功'];
            $pdo_W = null;
        } catch (PDOException $e) {
            $test_results[] = ['name' => 'World 数据库', 'status' => false, 'message' => '连接失败: ' . $e->getMessage()];
        }

        // Test SOAP Connection
        $soap_options = [
            'location' => "http://{$config['soap']['host']}:{$config['soap']['port']}/",
            'uri'      => $config['soap']['uri'],
            'login'    => $config['soap']['username'],
            'password' => $config['soap']['password'],
            'style'    => SOAP_RPC,
            'connection_timeout' => 5, 
            'exceptions' => true
        ];
         // Suppress strict warnings during SOAP connection attempt if needed
        error_reporting(E_ALL & ~E_STRICT); // Temporarily change error reporting
        try {
            $client = new SoapClient(null, $soap_options);
            $command = ".server info";
            $result = $client->executeCommand(new SoapParam($command, 'command')); 
            
            // Simple check: If no exception and result is not explicitly false
            // A more robust check might involve parsing $result if the expected output is known.
            if ($result !== false) { // Basic check, adjust if SOAP returns specific error indicators
                 $soap_ok = true;
                 // Try to capture the result string if it's returned
                 $resultMessage = is_string($result) && trim($result) !== '' ? ': ' . htmlspecialchars(trim($result)) : '';
                 $test_results[] = ['name' => 'SOAP 连接', 'status' => true, 'message' => '连接并执行命令成功' . $resultMessage];
            } else {
                 $test_results[] = ['name' => 'SOAP 连接', 'status' => false, 'message' => '执行命令成功但服务器返回 false']; // More specific message
            }
            
        } catch (SoapFault $e) {
            $test_results[] = ['name' => 'SOAP 连接', 'status' => false, 'message' => '连接或执行命令失败: ' . $e->getMessage()];
        } catch (Exception $e) { // Catch other potential exceptions (e.g., network)
             $test_results[] = ['name' => 'SOAP 连接', 'status' => false, 'message' => '连接失败: ' . $e->getMessage()];
        }
         error_reporting(E_ALL); // Restore default error reporting

        $all_connections_ok = $db_A_ok && $db_C_ok && $db_W_ok && $soap_ok;
    }
}

// --- Step 5: Generate Config File (Run only when displaying Step 5) ---
$config_generation_success = false;
$config_generation_error = '';
if ($stepToShow === 5) {
    if (!isset($_SESSION['setup_data']['db_A'], $_SESSION['setup_data']['db_C'], $_SESSION['setup_data']['db_W'], $_SESSION['setup_data']['soap'], $_SESSION['setup_data']['admin'])) {
        $config_generation_error = "错误：配置数据不完整，无法生成配置文件。请从第一步重新开始。";
        // Consider redirecting back to step 1 or showing a more specific error
    } else {
        $config = $_SESSION['setup_data'];
        $configFilePath = __DIR__ . '/config.php';

        // --- Generate config content in array format ---
        $dateNow = date('Y-m-d H:i:s');
        // Use plain text passwords directly from session for compatibility
        $dbAPass = $config['db_A']['password'];
        $dbCPass = $config['db_C']['password'];
        $dbWPass = $config['db_W']['password'];
        $soapPass = $config['soap']['password'];
        $adminPassHash = $config['admin']['password_hash']; // Use admin password hash

        $configContent = <<<PHP
<?php
declare(strict_types=1);
// Auto-generated configuration file by Setup Wizard
// Date: {$dateNow}

return [
    'databases' => [
        'db_A' => [ // Auth 数据库配置
            'host' => '{$config['db_A']['host']}',
            'port' => {$config['db_A']['port']},
            'database' => '{$config['db_A']['database']}',
            'username' => '{$config['db_A']['username']}',
            'password' => '{$dbAPass}',
            'charset' => '{$config['db_A']['charset']}'
        ],
        'db_C' => [ // Characters 数据库配置
            'host' => '{$config['db_C']['host']}',
            'port' => {$config['db_C']['port']},
            'database' => '{$config['db_C']['database']}',
            'username' => '{$config['db_C']['username']}',
            'password' => '{$dbCPass}',
            'charset' => '{$config['db_C']['charset']}'
        ],
        'db_W' => [ // World 数据库配置
            'host' => '{$config['db_W']['host']}',
            'port' => {$config['db_W']['port']},
            'database' => '{$config['db_W']['database']}',
            'username' => '{$config['db_W']['username']}',
            'password' => '{$dbWPass}',
            'charset' => '{$config['db_W']['charset']}'
        ],
    ],
    'admin' => [
        'username' => '{$config['admin']['username']}',
        'password_hash' => '{$adminPassHash}' // Store the hash
    ],
    'soap' => [
        'host' => '{$config['soap']['host']}',
        'port' => {$config['soap']['port']},
        'username' => '{$config['soap']['username']}',
        'password' => '{$soapPass}',
        'uri' => '{$config['soap']['uri']}'
    ],
    // 其他配置项...
];

?>
PHP;

        // --- Write config file ---
        // Suppress errors temporarily to provide custom feedback
        $old_error_reporting = error_reporting(0); 
        $bytesWritten = file_put_contents($configFilePath, $configContent);
        error_reporting($old_error_reporting); // Restore error reporting

        if ($bytesWritten === false) {
            $lastError = error_get_last();
            $errorMsg = $lastError ? $lastError['message'] : '未知原因';
            $config_generation_error = "错误：无法写入 <code>config.php</code> 文件。请检查 Web 服务器对目录 " . __DIR__ . " 是否有写入权限。 (详细信息: {$errorMsg})";
        } else {
            $config_generation_success = true;
            // Optional: Clear setup data from session after success
            unset($_SESSION['setup_data']); 
            unset($_SESSION['setup_errors']); // Might already be cleared
        }
    }
}

// --- HTML Output ---
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>设置向导 - 艾泽拉斯控制台</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="bag_query/style.css"> <!-- Reuse styles if possible -->
    <style>
        body { padding-top: 40px; padding-bottom: 40px; background-color: #2c3034; color: #fff;}
        .wizard-container { 
            max-width: 1100px; /* 增大宽度 */ 
            margin: auto; background-color: #3a3f44; padding: 30px; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.5); 
        }
        .step-header { margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid #4e5459; }
        .list-group-item { background-color: transparent; border-color: #4e5459; color: #fff;}
        .list-group-item .badge { font-size: 0.9em; }
        .text-success { color: #28a745 !important; }
        .text-danger { color: #dc3545 !important; }
        .col-form-label { /* 确保标签垂直居中 */
            padding-top: calc(0.375rem + 1px);
            padding-bottom: calc(0.375rem + 1px);
            line-height: 1.5;
        }
        .text-sm-end { /* Aligns label text to the right on small screens and up */
           text-align: right !important;
        }
         @media (max-width: 575.98px) { 
            .text-sm-end { text-align: left !important; } /* Align left on extra small screens */
        }
    </style>
</head>
<body>
    <div class="container wizard-container">
        <h2 class="text-center mb-4">艾泽拉斯控制台 - 设置向导</h2>

        <?php if ($configExistsWarning): ?>
            <div class="alert alert-warning"><?= $configExistsWarning ?></div>
        <?php endif; ?>

        <?php // Display any errors from form submissions ?>
        <?php if (!empty($setup_errors)): ?>
            <div class="alert alert-danger">
                <strong>请修正以下错误：</strong><br>
                <?= implode('<br>', array_map('htmlspecialchars', $setup_errors)) ?>
            </div>
        <?php endif; ?>

        <?php if ($stepToShow === 1): ?>
            <div class="step-header"><h4>步骤 1: 环境检查</h4></div>
            <ul class="list-group mb-4">
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    PHP 版本 (>= <?= $php_version_required ?? '?.?' ?>)
                    <span class="badge <?= $php_version_ok ? 'bg-success' : 'bg-danger' ?>"><?= $php_version_ok ? ('通过 (' . PHP_VERSION . ')') : ('失败 (' . PHP_VERSION . ')') ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    PDO MySQL 扩展 (pdo_mysql)
                    <span class="badge <?= $pdo_mysql_ok ? 'bg-success' : 'bg-danger' ?>"><?= $pdo_mysql_ok ? '已启用' : '未启用' ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    SOAP 扩展 (soap)
                    <span class="badge <?= $soap_ok ? 'bg-success' : 'bg-danger' ?>"><?= $soap_ok ? '已启用' : '未启用' ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    Multibyte String 扩展 (mbstring)
                    <span class="badge <?= $mbstring_ok ? 'bg-success' : 'bg-danger' ?>"><?= $mbstring_ok ? '已启用' : '未启用' ?></span>
                </li>
            </ul>

            <?php if (!$all_checks_ok): ?>
                <div class="alert alert-danger">
                    您的 PHP 环境不满足所有要求。请检查失败的项目并确保已安装/启用必要的扩展，且 PHP 版本符合要求。
                    <br>您可能需要编辑 <code>php.ini</code> 文件并重启 Web 服务器。
                </div>
                 <button class="btn btn-secondary w-100" onclick="window.location.reload();">重新检查</button>
            <?php else: ?>
                <div class="alert alert-success">环境检查通过！</div>
                <a href="setup.php?step=2" class="btn btn-primary w-100">下一步：配置数据库与 SOAP</a>
            <?php endif; ?>

        <?php elseif ($stepToShow === 2): ?>
            <div class="step-header"><h4>步骤 2: 配置数据库与 SOAP</h4></div>
            <form method="POST" action="setup.php?step=2_submit">
                <div class="row">
                    <div class="col-lg-6 mb-4">
                        <fieldset class="p-3 border rounded h-100">
                            <legend class="w-auto px-2 h6 text-warning">Auth 数据库</legend>
                            
                            <div class="row mb-3 align-items-center">
                                <label for="db_A_host" class="col-sm-3 col-form-label text-sm-end">主机:</label>
                                <div class="col-sm-9">
                                    <input type="text" class="form-control" id="db_A_host" name="db_A_host" value="<?= htmlspecialchars($_SESSION['setup_data']['db_A']['host'] ?? '127.0.0.1') ?>" required>
                                </div>
                            </div>
                            <div class="row mb-3 align-items-center">
                                <label for="db_A_port" class="col-sm-3 col-form-label text-sm-end">端口:</label>
                                <div class="col-sm-9">
                                    <input type="number" class="form-control" id="db_A_port" name="db_A_port" value="<?= htmlspecialchars((string)($_SESSION['setup_data']['db_A']['port'] ?? 3306)) ?>" required>
                                </div>
                            </div>
                            <div class="row mb-3 align-items-center">
                                <label for="db_A_database" class="col-sm-3 col-form-label text-sm-end">数据库名:</label>
                                <div class="col-sm-9">
                                    <input type="text" class="form-control" id="db_A_database" name="db_A_database" value="<?= htmlspecialchars($_SESSION['setup_data']['db_A']['database'] ?? 'acore_auth') ?>" required>
                                </div>
                            </div>
                            <div class="row mb-3 align-items-center">
                                <label for="db_A_username" class="col-sm-3 col-form-label text-sm-end">用户名:</label>
                                <div class="col-sm-9">
                                    <input type="text" class="form-control" id="db_A_username" name="db_A_username" value="<?= htmlspecialchars($_SESSION['setup_data']['db_A']['username'] ?? 'root') ?>" required>
                                </div>
                            </div>
                            <div class="row mb-3 align-items-center">
                                <label for="db_A_password" class="col-sm-3 col-form-label text-sm-end">密码:</label>
                                <div class="col-sm-9">
                                    <input type="password" class="form-control" id="db_A_password" name="db_A_password" value="<?= htmlspecialchars($_SESSION['setup_data']['db_A']['password'] ?? '') ?>">
                                </div>
                            </div>
                        </fieldset>
                    </div>

                    <div class="col-lg-6 mb-4">
                        <fieldset class="p-3 border rounded h-100">
                            <legend class="w-auto px-2 h6 text-warning">Characters 数据库</legend>
                            
                            <div class="row mb-3 align-items-center">
                                <label for="db_C_host" class="col-sm-3 col-form-label text-sm-end">主机:</label>
                                <div class="col-sm-9">
                                    <input type="text" class="form-control" id="db_C_host" name="db_C_host" value="<?= htmlspecialchars($_SESSION['setup_data']['db_C']['host'] ?? '127.0.0.1') ?>" required>
                                </div>
                            </div>
                            <div class="row mb-3 align-items-center">
                                <label for="db_C_port" class="col-sm-3 col-form-label text-sm-end">端口:</label>
                                <div class="col-sm-9">
                                    <input type="number" class="form-control" id="db_C_port" name="db_C_port" value="<?= htmlspecialchars((string)($_SESSION['setup_data']['db_C']['port'] ?? 3306)) ?>" required>
                                </div>
                            </div>
                            <div class="row mb-3 align-items-center">
                                <label for="db_C_database" class="col-sm-3 col-form-label text-sm-end">数据库名:</label>
                                <div class="col-sm-9">
                                    <input type="text" class="form-control" id="db_C_database" name="db_C_database" value="<?= htmlspecialchars($_SESSION['setup_data']['db_C']['database'] ?? 'acore_characters') ?>" required>
                                </div>
                            </div>
                            <div class="row mb-3 align-items-center">
                                <label for="db_C_username" class="col-sm-3 col-form-label text-sm-end">用户名:</label>
                                <div class="col-sm-9">
                                    <input type="text" class="form-control" id="db_C_username" name="db_C_username" value="<?= htmlspecialchars($_SESSION['setup_data']['db_C']['username'] ?? 'root') ?>" required>
                                </div>
                            </div>
                            <div class="row mb-3 align-items-center">
                                <label for="db_C_password" class="col-sm-3 col-form-label text-sm-end">密码:</label>
                                <div class="col-sm-9">
                                    <input type="password" class="form-control" id="db_C_password" name="db_C_password" value="<?= htmlspecialchars($_SESSION['setup_data']['db_C']['password'] ?? '') ?>">
                                </div>
                            </div>
                        </fieldset>
                    </div>

                    <div class="col-lg-6 mb-4">
                        <fieldset class="p-3 border rounded h-100">
                            <legend class="w-auto px-2 h6 text-warning">World 数据库</legend>
                            
                            <div class="row mb-3 align-items-center">
                                <label for="db_W_host" class="col-sm-3 col-form-label text-sm-end">主机:</label>
                                <div class="col-sm-9">
                                    <input type="text" class="form-control" id="db_W_host" name="db_W_host" value="<?= htmlspecialchars($_SESSION['setup_data']['db_W']['host'] ?? '127.0.0.1') ?>" required>
                                </div>
                            </div>
                            <div class="row mb-3 align-items-center">
                                <label for="db_W_port" class="col-sm-3 col-form-label text-sm-end">端口:</label>
                                <div class="col-sm-9">
                                    <input type="number" class="form-control" id="db_W_port" name="db_W_port" value="<?= htmlspecialchars((string)($_SESSION['setup_data']['db_W']['port'] ?? 3306)) ?>" required>
                                </div>
                            </div>
                            <div class="row mb-3 align-items-center">
                                <label for="db_W_database" class="col-sm-3 col-form-label text-sm-end">数据库名:</label>
                                <div class="col-sm-9">
                                    <input type="text" class="form-control" id="db_W_database" name="db_W_database" value="<?= htmlspecialchars($_SESSION['setup_data']['db_W']['database'] ?? 'acore_world') ?>" required>
                                </div>
                            </div>
                            <div class="row mb-3 align-items-center">
                                <label for="db_W_username" class="col-sm-3 col-form-label text-sm-end">用户名:</label>
                                <div class="col-sm-9">
                                    <input type="text" class="form-control" id="db_W_username" name="db_W_username" value="<?= htmlspecialchars($_SESSION['setup_data']['db_W']['username'] ?? 'root') ?>" required>
                                </div>
                            </div>
                            <div class="row mb-3 align-items-center">
                                <label for="db_W_password" class="col-sm-3 col-form-label text-sm-end">密码:</label>
                                <div class="col-sm-9">
                                    <input type="password" class="form-control" id="db_W_password" name="db_W_password" value="<?= htmlspecialchars($_SESSION['setup_data']['db_W']['password'] ?? '') ?>">
                                </div>
                            </div>
                        </fieldset>
                    </div>

                    <div class="col-lg-6 mb-4">
                        <fieldset class="p-3 border rounded h-100">
                            <legend class="w-auto px-2 h6 text-warning">SOAP 配置</legend>
                            
                            <div class="row mb-3 align-items-center">
                                <label for="soap_host" class="col-sm-3 col-form-label text-sm-end">主机/IP:</label>
                                <div class="col-sm-9">
                                    <input type="text" class="form-control" id="soap_host" name="soap_host" value="<?= htmlspecialchars($_SESSION['setup_data']['soap']['host'] ?? '127.0.0.1') ?>" required>
                                </div>
                            </div>
                            <div class="row mb-3 align-items-center">
                                <label for="soap_port" class="col-sm-3 col-form-label text-sm-end">端口:</label>
                                <div class="col-sm-9">
                                    <input type="number" class="form-control" id="soap_port" name="soap_port" value="<?= htmlspecialchars((string)($_SESSION['setup_data']['soap']['port'] ?? 7878)) ?>" required>
                                </div>
                            </div>
                            <div class="row mb-3 align-items-center">
                                <label for="soap_username" class="col-sm-3 col-form-label text-sm-end">用户名:</label>
                                <div class="col-sm-9">
                                    <input type="text" class="form-control" id="soap_username" name="soap_username" value="<?= htmlspecialchars($_SESSION['setup_data']['soap']['username'] ?? 'soap_user') ?>" required>
                                </div>
                            </div>
                            <div class="row mb-3 align-items-center">
                                <label for="soap_uri" class="col-sm-3 col-form-label text-sm-end">URI:</label>
                                <div class="col-sm-9">
                                    <input type="text" class="form-control" id="soap_uri" name="soap_uri" value="<?= htmlspecialchars($_SESSION['setup_data']['soap']['uri'] ?? 'urn:AC') ?>" required>
                                </div>
                            </div>
                            <div class="row mb-3 align-items-center">
                                <label for="soap_password" class="col-sm-3 col-form-label text-sm-end">密码:</label>
                                <div class="col-sm-9">
                                    <input type="password" class="form-control" id="soap_password" name="soap_password" value="<?= htmlspecialchars($_SESSION['setup_data']['soap']['password'] ?? 'soap_pass') ?>" required>
                                </div>
                            </div>
                        </fieldset>
                    </div>
                </div>

                <hr class="my-4">

                <div class="d-flex justify-content-between">
                    <a href="setup.php?step=1" class="btn btn-secondary">返回上一步</a>
                    <button type="submit" class="btn btn-primary">下一步：测试连接</button>
                </div>
            </form>

        <?php elseif ($stepToShow === 3): ?>
             <div class="step-header"><h4>步骤 3: 测试连接</h4></div>
             
             <ul class="list-group mb-4">
                 <?php foreach ($test_results as $result): ?>
                     <li class="list-group-item d-flex justify-content-between align-items-center">
                         <span>
                             <?= htmlspecialchars($result['name']) ?>: 
                             <small class="<?= $result['status'] ? 'text-success' : 'text-danger' ?>">
                                 <?= htmlspecialchars($result['message']) ?>
                             </small>
                         </span>
                         <span class="badge <?= $result['status'] ? 'bg-success' : 'bg-danger' ?>">
                             <?= $result['status'] ? '成功' : '失败' ?>
                         </span>
                     </li>
                 <?php endforeach; ?>
             </ul>

             <?php if (!$all_connections_ok): ?>
                 <div class="alert alert-warning">
                     存在一个或多个连接失败。请返回上一步检查并修改配置信息。
                 </div>
             <?php else: ?>
                  <div class="alert alert-success">
                     所有连接测试成功！
                 </div>
             <?php endif; ?>

              <div class="d-flex justify-content-between mt-4">
                 <a href="setup.php?step=2" class="btn btn-secondary">返回修改配置</a>
                 <a href="setup.php?step=4" 
                    class="btn btn-primary <?= $all_connections_ok ? '' : 'disabled' ?>" 
                    id="next-step-4-btn" 
                    <?= !$all_connections_ok ? 'aria-disabled="true"' : '' ?>>
                    下一步：配置管理员
                 </a> 
              </div>
        <?php elseif ($stepToShow === 4): ?>
             <div class="step-header"><h4>步骤 4: 配置管理员</h4></div>
             <p>请为艾泽拉斯控制台设置一个管理员账号。此账号将用于访问需要权限的管理功能。</p>
             
             <form method="POST" action="setup.php?step=4_submit" id="admin-form">
                 <div class="mb-3">
                     <label for="admin_username" class="form-label">管理员用户名:</label>
                     <input type="text" class="form-control" id="admin_username" name="admin_username" value="<?= htmlspecialchars($_SESSION['setup_data']['admin']['username'] ?? 'admin') ?>" required>
                 </div>
                 <div class="mb-3">
                     <label for="admin_password" class="form-label">密码:</label>
                     <input type="password" class="form-control" id="admin_password" name="admin_password" required>
                 </div>
                 <div class="mb-3">
                     <label for="admin_password_confirm" class="form-label">确认密码:</label>
                     <input type="password" class="form-control" id="admin_password_confirm" name="admin_password_confirm" required>
                     <div id="password-match-error" class="invalid-feedback" style="display: none;">两次输入的密码不匹配。</div>
                 </div>

                 <hr class="my-4">

                 <div class="d-flex justify-content-between">
                     <a href="setup.php?step=3" class="btn btn-secondary">返回上一步</a>
                     <button type="submit" class="btn btn-primary">下一步：完成设置</button>
                 </div>
             </form>

         <?php elseif ($stepToShow === 5): ?>
             <div class="step-header"><h4>步骤 5: 完成设置</h4></div>

             <?php if ($config_generation_success): ?>
                 <div class="alert alert-success">
                     <strong>设置完成！</strong> 配置文件 <code>config.php</code> 已成功生成。
                     <br>
                     您现在可以进入艾泽拉斯控制台了。
                 </div>
                 <div class="text-center">
                      <a href="index.php" class="btn btn-success btn-lg">进入控制台</a>
                 </div>
             <?php else: ?>
                 <div class="alert alert-danger">
                      <strong>生成配置文件失败。</strong>
                      <br>
                      <?= $config_generation_error ?: '发生未知错误。' // Fallback message ?>
                 </div>
                 <div class="d-flex justify-content-between mt-4">
                     <a href="setup.php?step=4" class="btn btn-secondary">返回上一步</a>
                     <button class="btn btn-warning" onclick="window.location.reload();">重试生成</button> 
                 </div>
             <?php endif; ?>

        <?php else: ?>
            <div class="alert alert-warning">无效的步骤。</div>
            <a href="setup.php?step=1" class="btn btn-primary">返回第一步</a>
        <?php endif; ?>

    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php // Add JS for password match check only on Step 4 ?>
    <?php if ($stepToShow === 4): ?>
    <script>
        const adminForm = document.getElementById('admin-form');
        const passwordInput = document.getElementById('admin_password');
        const confirmPasswordInput = document.getElementById('admin_password_confirm');
        const matchErrorDiv = document.getElementById('password-match-error');

        function validatePasswordMatch() {
            if (passwordInput.value !== confirmPasswordInput.value && confirmPasswordInput.value !== '') {
                confirmPasswordInput.classList.add('is-invalid');
                matchErrorDiv.style.display = 'block';
                return false;
            } else {
                confirmPasswordInput.classList.remove('is-invalid');
                matchErrorDiv.style.display = 'none';
                 return true;
            }
        }

        // Add event listeners for real-time feedback
        passwordInput.addEventListener('input', validatePasswordMatch);
        confirmPasswordInput.addEventListener('input', validatePasswordMatch);

        // Validate on form submit as well
        adminForm.addEventListener('submit', function(event) {
            if (!validatePasswordMatch()) {
                event.preventDefault(); // Prevent form submission if passwords don't match
                confirmPasswordInput.focus();
            }
        });
    </script>
    <?php endif; ?>

</body>
</html> 