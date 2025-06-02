<?php
session_start();
$config = require '../config.php';
$commonCommands = require 'config_commands.php';
$soapConf = $config['soap'];
$adminConf = $config['admin']; // 加载管理员配置

$isLoggedIn = false;
$loginError = '';

// 1. 处理退出登录
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// 2. 处理登录请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_username'])) {
    $username = $_POST['login_username'];
    $password = $_POST['login_password'];

    // Verify username and password hash
    if ($username === $adminConf['username'] && isset($adminConf['password_hash']) && password_verify($password, $adminConf['password_hash'])) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: ' . $_SERVER['PHP_SELF']); // 登录成功后刷新页面
        exit;
    } else {
        $loginError = '无效的用户名或密码。';
    }
}

// 3. 检查登录状态
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    $isLoggedIn = true;
}

$result = '';
$error = '';

// 仅在登录后处理 SOAP 命令发送
if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['command'])) {
    $command = trim($_POST['command'] ?? '');
    if ($command) {
        try {
            $url = sprintf(
                'http://%s:%s@%s:%d/',
                urlencode($soapConf['username']),
                urlencode($soapConf['password']),
                $soapConf['host'],
                $soapConf['port']
            );
            $client = new SoapClient(null, [
                'location' => $url,
                'uri'      => $soapConf['uri'],
                'style'    => SOAP_RPC,
                'login'    => $soapConf['username'],
                'password' => $soapConf['password'],
                'exceptions' => true,
            ]);
            $response = $client->executeCommand(new SoapParam($command, 'command'));
            if (is_string($response) && trim($response) !== '') {
                $result = nl2br(htmlspecialchars($response));
            } else {
                $result = '<span style="color:#ffd700;">命令已发送，服务器无返回内容，可能已成功执行。</span>';
            }
        } catch (Exception $e) {
            $error = '错误: ' . htmlspecialchars($e->getMessage());
        }
    } else {
        $error = '请输入命令';
    }
    // 保存到Session
    $_SESSION['last_command'] = $command;
    $_SESSION['last_result'] = $error ? $error : $result;
    // 刷新防止重复提交
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// 仅在登录后加载上次结果
if ($isLoggedIn) {
$lastCommand = $_SESSION['last_command'] ?? '';
$lastResult = $_SESSION['last_result'] ?? '';
} else {
    $lastCommand = '';
    $lastResult = '';
}

// 按分类整理命令 (仅在登录后需要完整列表)
$commandsByCategory = [];
$categories = [];
if ($isLoggedIn) {
    foreach ($commonCommands as $cmd) {
        $category = $cmd['category'] ?? '未分类';
        $commandsByCategory[$category][] = $cmd;
    }
    $categories = array_keys($commandsByCategory);
}

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>SOAP 命令发送 - <?= $isLoggedIn ? '控制台' : '登录' ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-start: #1e2024;
            --bg-end: #2a2d31;
            --container-bg: #2f3338;
            --text-color: #e0e0e0;
            --accent-color: #f0c850;
            --primary-color: #0d6efd;
            --primary-hover: #0b5ed7;
            --result-bg: #1c1e20;
            --result-text: #60e090;
            --border-color: #444;
            --shadow-color: rgba(0, 0, 0, 0.5);
            --font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
            --category-bg: #25282c;
            --category-active-bg: var(--accent-color);
            --category-active-text: var(--container-bg);
        }

        body {
            background: linear-gradient(to bottom right, var(--bg-start), var(--bg-end));
            color: var(--text-color);
            font-family: var(--font-family);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1400px;
            width: 100%;
            background: var(--container-bg);
            border-radius: 15px;
            box-shadow: 0 8px 30px var(--shadow-color);
            padding: 30px;
            border: 1px solid var(--border-color);
            margin: 30px auto;
        }
        .command-form-section {
            margin-bottom: 2rem;
        }
        h2 {
             color: var(--accent-color);
             text-align: center;
             margin-bottom: 1.5rem;
             font-weight: 700;
             letter-spacing: 1px;
        }
        .form-label {
            color: var(--accent-color);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .form-control {
            background-color: #3a3f45;
            color: var(--text-color);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 0.6rem 1rem;
        }
        .form-control:focus {
            background-color: #40454b;
            color: var(--text-color);
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.25rem rgba(240, 200, 80, 0.25);
        }
        .form-control::placeholder {
            color: #888;
        }
        .btn-primary {
             background: var(--primary-color);
            border: none;
             border-radius: 8px;
             padding: 0.6rem 1rem;
             font-weight: 600;
             transition: background-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
             width: 100%;
        }
        .btn-primary:hover {
            background: var(--primary-hover);
            box-shadow: 0 4px 15px rgba(13, 110, 253, 0.3);
        }

        .result-section {
            margin-bottom: 2rem;
        }
        .result-section h4 {
            color: #bbb;
            margin-bottom: 0.8rem;
            font-weight: 500;
        }
        pre {
            background: var(--result-bg);
            color: var(--result-text);
            border-radius: 10px;
            padding: 20px;
            font-size: 0.95rem;
            border: 1px solid var(--border-color);
            white-space: pre-wrap;
            word-wrap: break-word;
            max-height: 300px;
            overflow-y: auto;
        }
        pre span[style*="color:#ffd700;"] {
             color: var(--accent-color) !important;
             font-style: italic;
        }

        .commands-section {
            border-top: 1px solid var(--border-color);
            padding-top: 1.5rem;
        }

        #category-list .list-group-item {
            background-color: var(--category-bg);
            color: var(--text-color);
            border: 1px solid var(--border-color);
            margin-bottom: 5px;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.2s ease, color 0.2s ease;
        }
        #category-list .list-group-item:hover {
            background-color: #3a3f45;
        }
        #category-list .list-group-item.active {
            background-color: var(--category-active-bg);
            color: var(--category-active-text);
            border-color: var(--category-active-bg);
            font-weight: bold;
        }

        #command-display-area {
            max-height: 500px;
            overflow-y: auto;
            padding-right: 15px;
        }
        #command-display-area .command-item {
            background-color: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        #command-display-area code {
            color: var(--accent-color);
            font-size: 0.9em;
            background-color: rgba(0,0,0,0.2);
            padding: 3px 6px;
            border-radius: 4px;
            word-break: break-all;
        }
        #command-display-area small {
            font-size: 0.85em;
            color: #b0b0b0 !important;
            display: block;
            margin-top: 8px;
        }
        .use-command-btn {
            padding: 0.25rem 0.6rem;
            font-size: 0.8rem;
            color: var(--accent-color);
            border-color: var(--accent-color);
            align-self: flex-end;
            margin-top: 10px;
        }
        .use-command-btn:hover {
            background-color: var(--accent-color);
            color: var(--container-bg);
        }
        /* 新增登录表单样式 */
        .login-container {
            max-width: 400px;
            margin: 100px auto;
            background: var(--container-bg);
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 15px var(--shadow-color);
            border: 1px solid var(--border-color);
        }
        .login-container h2 {
            color: var(--accent-color);
            text-align: center;
            margin-bottom: 25px;
        }
        .logout-btn {
            position: absolute;
            top: 15px;
            right: 20px;
            color: #bbb;
            text-decoration: none;
            font-size: 0.9rem;
        }
        .logout-btn:hover {
            color: var(--accent-color);
        }
        /* Return Home Button */
        .home-btn {
            position: absolute;
            top: 15px;
            left: 20px;
            color: #bbb;
            text-decoration: none;
            font-size: 0.9rem;
        }
        .home-btn:hover {
             color: var(--accent-color);
        }
    </style>
</head>
<body>

<?php if (!$isLoggedIn): ?>
    <!-- 显示登录表单 -->
    <div class="login-container">
        <h2>管理员登录</h2>
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
            <button type="submit" class="btn btn-primary w-100">登录</button>
        </form>
    </div>

<?php else: ?>
    <!-- 显示 SOAP 命令界面 -->
    <div class="container position-relative">
        <a href="../index.php" class="home-btn">&laquo; 返回主页</a>
        <a href="?logout=1" class="logout-btn">退出登录</a>
        <!-- 顶部: 标题, 输入框, 按钮 -->
        <div class="command-form-section">
        <h2>魔兽世界 SOAP 命令发送</h2>
        <form method="post" autocomplete="off">
            <div class="mb-3">
                <label for="command" class="form-label">命令</label>
                <input type="text" class="form-control" id="command" name="command" placeholder="如：server status" value="<?= htmlspecialchars($lastCommand) ?>">
            </div>
                <button type="submit" class="btn btn-primary">发送</button>
        </form>
        </div>

        <!-- 中间: 结果 -->
        <?php
        if ($lastResult || $error) {
            echo '<div class="result-section">';
            echo '    <h4>结果：</h4>';
            echo '    <pre>';
            if ($error) {
                echo '<span style="color:red;">' . htmlspecialchars($error) . '</span>';
            } else {
                echo $lastResult;
            }
            echo '    </pre>';
            echo '</div>';
        }
        ?>

        <!-- 底部: 命令分类 和 详细命令 -->
        <div class="commands-section">
            <div class="row">
                <!-- 左侧: 命令分类选项 -->
                <div class="col-md-3">
                    <h5 class="mb-3">命令分类</h5>
                    <div class="list-group" id="category-list">
                        <button type="button" class="list-group-item list-group-item-action active" data-category="all">所有命令</button>
                        <?php foreach ($categories as $category): ?>
                            <button type="button" class="list-group-item list-group-item-action" data-category="<?= htmlspecialchars($category) ?>"><?= htmlspecialchars($category) ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- 右侧: 详细命令展示区 -->
                <div class="col-md-9">
                    <h5 class="mb-3">详细命令</h5>
                    <div id="command-display-area">
                        <?php foreach ($commandsByCategory as $category => $commands): ?>
                            <?php foreach ($commands as $cmd): ?>
                                <div class="command-item" data-category="<?= htmlspecialchars($category) ?>">
                                    <div>
                                        <code><?= htmlspecialchars($cmd['command']) ?></code>
                                        <small><?= htmlspecialchars($cmd['description']) ?></small>
                                    </div>
                                    <button class="btn btn-sm btn-outline-secondary use-command-btn" data-command="<?= htmlspecialchars($cmd['command']) ?>">使用</button>
                                </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- JS 处理命令点击 和 分类切换 (保持不变) -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const commandInput = document.getElementById('command');
            const useButtons = document.querySelectorAll('.use-command-btn');
            const categoryButtons = document.querySelectorAll('#category-list .list-group-item');
            const commandItems = document.querySelectorAll('#command-display-area .command-item');

            // 处理 "使用" 按钮点击
            useButtons.forEach(button => {
                button.addEventListener('click', function() {
                    commandInput.value = this.getAttribute('data-command');
                    commandInput.focus();
                    window.scrollTo(0, commandInput.offsetTop - 20); // 滚动到输入框附近
                });
            });

            // 处理分类按钮点击
            categoryButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // 移除所有按钮的 active 状态
                    categoryButtons.forEach(btn => btn.classList.remove('active'));
                    // 给当前点击的按钮添加 active 状态
                    this.classList.add('active');

                    const selectedCategory = this.getAttribute('data-category');

                    // 显示/隐藏命令项
                    commandItems.forEach(item => {
                        if (selectedCategory === 'all' || item.getAttribute('data-category') === selectedCategory) {
                            item.style.display = 'flex'; // 使用 flex 因为 command-item 设置了 display: flex
                        } else {
                            item.style.display = 'none';
                        }
                    });
                });
            });

            // 初始加载时触发一次点击 "所有命令"，确保显示正确
            // 只有在分类按钮存在时才执行 click
            const allCategoryButton = document.querySelector('#category-list .list-group-item[data-category="all"]');
            if (allCategoryButton) {
                allCategoryButton.click();
            }
        });
    </script>
<?php endif; ?>
</body>
</html>