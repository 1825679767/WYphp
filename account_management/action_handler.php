<?php
declare(strict_types=1);
session_start();

header('Content-Type: application/json');

// Try loading config using an absolute path based on DOCUMENT_ROOT
$configFile = $_SERVER['DOCUMENT_ROOT'] . '/config.php';

if (!file_exists($configFile)) {
    $response = ['success' => false, 'message' => '配置文件未找到: ' . $configFile];
    error_log("Config file not found at expected absolute path in action_handler.php: " . $configFile);
    echo json_encode($response);
    exit;
}

$config = require_once $configFile;

// Add check to ensure config is loaded correctly
if (!isset($config) || !is_array($config)) {
    $response = ['success' => false, 'message' => '无法加载配置文件或配置文件格式错误。'];
    error_log("Failed to load or parse config.php in action_handler.php");
    echo json_encode($response);
    exit;
}

$response = ['success' => false, 'message' => '无效的请求。'];

// 1. 验证管理员登录状态
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    $response['message'] = '管理员未登录。';
    echo json_encode($response);
    exit;
}

// 2. 验证请求方法和参数
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = '仅支持 POST 请求。';
    echo json_encode($response);
    exit;
}

// Need DB functions for get_characters action
require_once __DIR__ . '/../bag_query/db.php'; 
require_once __DIR__ . '/functions.php';

$action = $_POST['action'] ?? null;
$accountName = $_POST['accountName'] ?? null;
$accountId = filter_input(INPUT_POST, 'accountId', FILTER_VALIDATE_INT);
$params = $_POST['params'] ?? [];
$charName = $_POST['charName'] ?? null; // Get charName for new actions

if (!$action || (!$accountName && !$accountId && !$charName)) { // Include charName in check
    $response['message'] = '缺少必要参数 (action, accountName/accountId/charName)。';
    echo json_encode($response);
    exit;
}

// 3. 构造 SOAP 命令 or Handle DB Query
$command = null;
if ($action === 'get_characters') {
    // Handle DB query directly, no SOAP command needed for this one
    if (!$accountId) {
        $response['message'] = '获取角色需要账号 ID。';
        echo json_encode($response);
        exit;
    }
    try {
        $connections = connect_databases();
        if (!isset($connections['db_C'])) {
             throw new Exception("角色数据库连接未配置或失败。");
        }
        $pdo_C = $connections['db_C'];
        
        // Call the function which already returns enhanced data
        $characters = get_characters_for_account($pdo_C, $accountId);
        
        $response['success'] = true;
        $response['message'] = '成功获取角色列表。';
        // Directly return the processed data from get_characters_for_account
        $response['characters'] = $characters; 

    } catch (Exception $e) {
        error_log("Error handling get_characters action: " . $e->getMessage());
        $response['message'] = '获取角色列表时出错: ' . htmlspecialchars($e->getMessage());
    }
    // Output JSON and exit for this action
    echo json_encode($response);
    exit;

} else {
    // Existing SOAP command logic
    // Actions like send_gold, send_item need charName
    if (!$accountName && !$charName) { // Check for either accountName or charName based on action
        $response['message'] = '此操作需要账号名称或角色名称。';
        echo json_encode($response);
        exit;
    }
    switch ($action) {
        case 'set_gmlevel':
            // Needs accountName
            if (!$accountName) { /* Error */ $response['message'] = '设置GM等级需要账号名称。'; echo json_encode($response); exit; }
            $level = $params['level'] ?? null;
            $realmId = $params['realmId'] ?? '-1'; // Default to all realms
            if ($level === null || !is_numeric($level)) {
                $response['message'] = '缺少或无效的 GM 等级参数。';
                echo json_encode($response);
                exit;
            }
            $command = sprintf('.account set gmlevel %s %d %s', $accountName, (int)$level, $realmId);
            break;

        case 'ban_account':
            $duration = $params['duration'] ?? null;
            $reason = $params['reason'] ?? '-';
            if ($duration === null) {
                $response['message'] = '缺少封禁时长参数。';
                echo json_encode($response);
                exit;
            }
            $command = sprintf('.ban account %s %s %s', $accountName, $duration, $reason);
            break;

        case 'unban_account':
            $command = sprintf('.unban account %s', $accountName);
            break;

        case 'set_password':
            $password = $params['password'] ?? null;
            if (!$password) {
                $response['message'] = '缺少新密码参数。';
                echo json_encode($response);
                exit;
            }
            $command = sprintf('.account set password %s %s %s', $accountName, $password, $password);
            break;

        case 'send_gold':
            // Needs charName
            if (!$charName) { /* Error */ $response['message'] = '发送金币需要角色名称。'; echo json_encode($response); exit; }
            $amount = filter_var($params['amount'] ?? null, FILTER_VALIDATE_INT);
            if ($amount === false || $amount <= 0) {
                $response['message'] = '无效或未提供金币数量。';
                echo json_encode($response);
                exit;
            }
            // Use the requested subject and body
            $subject = "感谢支持，请查收！";
            $body = "感谢支持，请查收！";
            // Corrected command format
            $command = sprintf('.send money %s "%s" "%s" %d', $charName, $subject, $body, $amount);
            break;
        
        case 'send_item':
             // Needs charName
            if (!$charName) { /* Error */ $response['message'] = '发送物品需要角色名称。'; echo json_encode($response); exit; }
            $itemId = filter_var($params['itemId'] ?? null, FILTER_VALIDATE_INT);
            $quantity = filter_var($params['quantity'] ?? null, FILTER_VALIDATE_INT);

            if ($itemId === false || $itemId <= 0) {
                $response['message'] = '无效或未提供物品 ID。';
                echo json_encode($response);
                exit;
            }
            if ($quantity === false || $quantity <= 0) {
                $response['message'] = '无效或未提供物品数量。';
                echo json_encode($response);
                exit;
            }
            // Use the requested subject and body
            $subject = "感谢支持，请查收！";
            $body = "感谢支持，请查收！";
            // Corrected command format
            // Note: Character name usually doesn't need quotes in the command itself unless it contains spaces.
            // The sprintf function handles basic string formatting. If names can have special chars, further escaping might be needed.
            $command = sprintf('.send items %s "%s" "%s" %d:%d', $charName, $subject, $body, $itemId, $quantity);
            break;

        case 'send_whisper':
            // Needs charName
            if (!$charName) { /* Error */ $response['message'] = '发送私聊需要角色名称。'; echo json_encode($response); exit; }
            $message = trim($params['message'] ?? '');
            if (empty($message)) {
                 $response['message'] = '私聊消息不能为空。';
                 echo json_encode($response);
                 exit;
            }
            // Command format: .send message PlayerName "Message Text"
            // Try removing quotes around the character name.
            $command = sprintf('.send message %s "%s"', $charName, $message);
            break;
            
        case 'kick_player':
             // Needs charName
            if (!$charName) { /* Error */ $response['message'] = '踢下线需要角色名称。'; echo json_encode($response); exit; }
            // Command format: .kick PlayerName
            $command = sprintf('.kick %s', $charName);
            break;

        case 'unstuck_player':
            // Needs charName
            if (!$charName) { /* Error */ $response['message'] = '解卡需要角色名称。'; echo json_encode($response); exit; }
            // Command format: .unstuck PlayerName inn
            $command = sprintf('.unstuck %s inn', $charName);
            break;

        default:
            $response['message'] = '无效的操作类型。';
            echo json_encode($response);
            exit;
    }

    // 4. 执行 SOAP 命令
    $soapConf = $config['soap'] ?? null;
    if (!$soapConf) {
        $response['message'] = 'SOAP 配置未找到。';
        echo json_encode($response);
        exit;
    }

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
            'exceptions' => true, // Throw exceptions on SOAP errors
            'connection_timeout' => 10 // Add a timeout
        ]);

        $soapResponse = $client->executeCommand(new SoapParam($command, 'command'));

        // Assuming success if no exception is thrown
        $response['success'] = true;
        $response['message'] = '命令已发送。';

    } catch (SoapFault $e) {
        error_log("SOAP Fault in action_handler: " . $e->getMessage());
        $response['message'] = 'SOAP 操作失败: ' . htmlspecialchars($e->getMessage());
    } catch (Exception $e) {
        error_log("General Error in action_handler: " . $e->getMessage());
        $response['message'] = '执行操作时发生错误: ' . htmlspecialchars($e->getMessage());
    }
}

// 5. 返回 JSON 结果 (only for SOAP actions now)
echo json_encode($response);
exit; 