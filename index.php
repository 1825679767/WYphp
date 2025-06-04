<?php
// index.php (root)

// --- Configuration Check ---
// Define the path to the config file relative to this index.php
$configFilePath = __DIR__ . '/config.php'; 

if (!file_exists($configFilePath)) {
    // Config file doesn't exist, redirect to setup wizard
    header('Location: setup.php');
    exit; // Stop further execution of index.php
}
// --- End Configuration Check ---

// If config.php exists, proceed with the rest of index.php
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>艾泽拉斯控制台</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="魔兽世界私服管理控制台">
    
    <!-- DNS预解析 -->
    <link rel="dns-prefetch" href="//cdn.jsdelivr.net">
    <link rel="dns-prefetch" href="//fonts.googleapis.com">
    <link rel="dns-prefetch" href="//fonts.gstatic.com">
    <link rel="dns-prefetch" href="//cdnjs.cloudflare.com">
    
    <!-- 预连接重要资源 -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <!-- 优先加载关键CSS -->
    <link rel="stylesheet" href="assets/css/styles.css">
    
    <!-- 异步加载外部CSS -->
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"></noscript>
    
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400..900&family=MedievalSharp&display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400..900&family=MedievalSharp&display=swap"></noscript>
    
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>
    
    <!-- CSS加载失败回退脚本 -->
    <script>
        // 检查Bootstrap是否加载成功，失败时使用本地备份
        function checkCSSLoaded() {
            var links = document.querySelectorAll('link[rel="stylesheet"]');
            links.forEach(function(link) {
                if (link.sheet === null) {
                    console.warn('CSS加载失败:', link.href);
                    // 可以在这里添加本地备份的逻辑
                }
            });
        }
        
        // 页面加载完成后检查
        window.addEventListener('load', checkCSSLoaded);
    </script>
</head>
<body>
    <!-- 音频播放器 - 延迟加载 -->
    <audio id="bgMusic" loop preload="none">
        <source src="assets/audio/wow-background.mp3" type="audio/mpeg">
        您的浏览器不支持音频元素。
    </audio>
    
    <!-- 音乐控制按钮 -->
    <div class="music-control">
        <button id="musicToggle" class="music-btn">
            <i class="fas fa-volume-up" id="volumeIcon"></i>
        </button>
    </div>
    
    <!-- 音乐播放提示 -->
    <div id="musicPrompt" class="music-prompt">
        点击右上角按钮开启背景音乐
    </div>

    <div class="main-container">
        <header class="wow-header">
            <h1 class="wow-title">艾泽拉斯控制台</h1>
            <p class="wow-subtitle">勇士，欢迎来到艾泽拉斯控制中枢。在此，你可以向世界服务器发送古老的 SOAP 指令，查询角色的行囊，或管理用户账号等。</p>
        </header>

        <div class="console-container">
            <div class="menu-grid">
                <!-- 第一行 -->
                <div class="menu-item">
                    <a href="soap/soap_command.php" class="menu-btn btn-gm">
                        <div class="menu-icon-wrapper">
                            <i class="fas fa-bolt menu-icon"></i>
                        </div>
                        <span class="menu-text">远程GM指令</span>
                    </a>
                </div>
                <div class="menu-item">
                    <a href="account_management/index.php" class="menu-btn btn-account">
                        <div class="menu-icon-wrapper">
                            <i class="fas fa-user-shield menu-icon"></i>
                        </div>
                        <span class="menu-text">管理用户账号</span>
                    </a>
                </div>
                <div class="menu-item">
                    <a href="bag_query/index.php" class="menu-btn btn-bag">
                        <div class="menu-icon-wrapper">
                            <i class="fas fa-briefcase menu-icon"></i>
                        </div>
                        <span class="menu-text">查询角色物品</span>
                    </a>
                </div>
                
                <!-- 第二行 -->
                <div class="menu-item">
                    <a href="item_editor/index.php" class="menu-btn btn-item">
                        <div class="menu-icon-wrapper">
                            <i class="fas fa-gem menu-icon"></i>
                        </div>
                        <span class="menu-text">物品编辑器</span>
                    </a>
                </div>
                <div class="menu-item">
                    <a href="creature_editor/index.php" class="menu-btn btn-creature">
                        <div class="menu-icon-wrapper">
                            <i class="fas fa-dragon menu-icon"></i>
                        </div>
                        <span class="menu-text">生物编辑器</span>
                    </a>
                </div>
                <div class="menu-item">
                    <a href="quest_editor/index.php" class="menu-btn btn-quest">
                        <div class="menu-icon-wrapper">
                            <i class="fas fa-scroll menu-icon"></i>
                        </div>
                        <span class="menu-text">任务编辑器</span>
                    </a>
                </div>
                
                <!-- 第三行 -->
                <div class="menu-item">
                    <a href="mass_mail/index.php" class="menu-btn btn-mail-system">
                        <div class="menu-icon-wrapper">
                            <i class="fas fa-mail-bulk menu-icon"></i>
                        </div>
                        <span class="menu-text">群发系统</span>
                    </a>
                </div>
                <div class="menu-item">
                    <a href="mail_management/index.php" class="menu-btn btn-mail">
                        <div class="menu-icon-wrapper">
                            <i class="fas fa-envelope menu-icon"></i>
                        </div>
                        <span class="menu-text">邮件管理</span>
                        <span class="status-badge">待实现</span>
                    </a>
                </div>
                <div class="menu-item">
                    <a href="drop_query/index.php" class="menu-btn btn-drop">
                        <div class="menu-icon-wrapper">
                            <i class="fas fa-box-open menu-icon"></i>
                        </div>
                        <span class="menu-text">掉落查询</span>
                        <span class="status-badge">待实现</span>
                    </a>
                </div>
            </div>
        </div>

        <footer class="footer">
            <p>© <?php echo date('Y'); ?> 艾泽拉斯控制台 | 为魔兽世界私服管理而设计</p>
            <div class="attribution">
                <p>本系统由飞翔熊猫开发，严禁用于商业！</p>
                <p>交流QQ群：738942437</p>
            </div>
        </footer>
    </div>

    <!-- JavaScript延迟加载 -->
    <script>
        // 延迟加载非关键JavaScript
        function loadScript(src, callback) {
            var script = document.createElement('script');
            script.src = src;
            script.async = true;
            script.onload = callback || function(){};
            script.onerror = function() {
                console.warn('脚本加载失败:', src);
            };
            document.head.appendChild(script);
        }
        
        // 页面主要内容加载完成后再加载JavaScript
        window.addEventListener('DOMContentLoaded', function() {
            // 优先加载本地JS
            loadScript('assets/js/main.js', function() {
                // 本地JS加载完成后再加载Bootstrap
                loadScript('https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js');
            });
        });
    </script>
</body>
</html>
