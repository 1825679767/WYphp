<?php
// mass_mail/handler.php
declare(strict_types=1);
session_start();

// header('Content-Type: application/json'); // Keep this if switching to AJAX later

$config = require_once __DIR__ . '/../config.php'; // Correctly load config array
require_once __DIR__ . '/../bag_query/db.php';
require_once __DIR__ . '/functions.php';

$response = ['success' => false, 'message' => '无效的请求或未登录。'];

// --- Login Check ---
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // If using redirect, maybe set error message in session and redirect to index login
    $_SESSION['mass_mail_result'] = $response;
     header('Location: index.php'); // Redirect to login if needed
     exit;
}

// --- Parameter Retrieval & Basic Validation ---
$action = $_POST['action'] ?? null;
if (!$action) {
     $response['message'] = '未指定操作。';
     $_SESSION['mass_mail_result'] = $response;
     header('Location: index.php');
     exit;
}

// --- Database Connection ---
$pdo_C = null;
try {
    $connections = connect_databases();
    $pdo_C = $connections['db_C'];
} catch (Exception $e) {
    $response['message'] = "数据库连接失败: " . $e->getMessage();
     $_SESSION['mass_mail_result'] = $response;
     header('Location: index.php');
    exit;
}

// --- SOAP Configuration Check ---
$soapConf = $config['soap'] ?? null;
if (!$soapConf) {
    $response['message'] = 'SOAP 配置未找到。';
     $_SESSION['mass_mail_result'] = $response;
     header('Location: index.php');
    exit;
}

// --- Target Character Determination ---
$target_characters = []; // Renamed back from _batch
$target_type = $_POST['target_type'] ?? 'online';

// Moved the initial fetch/setup outside the main try-catch for clarity
try {
    if ($target_type === 'online') {
        $target_characters = get_online_character_names($pdo_C);
    } elseif ($target_type === 'custom') {
        $custom_list = $_POST['custom_char_list'] ?? '';
        $target_characters = parse_character_list($custom_list);
    /* // Removed 'all' case
    } elseif ($target_type === 'all') {
        // Logic for 'all' is removed
    */
    } else {
        throw new Exception("无效的目标类型: " . $target_type);
    }

    // Check if we have targets before proceeding
    if (empty($target_characters)) { // Simplified check
        $response['success'] = false;
        $response['message'] = '未找到符合条件的目标角色。(' . $target_type . ')';
        $_SESSION['mass_mail_result'] = $response;
        header('Location: index.php');
        exit;
    }

} catch (Exception $e) {
    $response['message'] = "获取目标角色列表时出错: " . $e->getMessage();
    $_SESSION['mass_mail_result'] = $response;
    header('Location: index.php');
    exit;
}

// --- Action Specific Logic & SOAP Execution ---
$success_count = 0;
$fail_count = 0;
$errors = [];
$client = null;
$total_processed_count = count($target_characters); // Total count is now known upfront

try {
     // Establish SOAP connection once
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
        'connection_timeout' => 10 // Can revert timeout if needed
    ]);

    // --- Handle send_announce separately as it's not per-character ---
    if ($action === 'send_announce') {
         $announce_type = $_POST['announce_type'] ?? 'announce';
         $message = trim($_POST['message'] ?? '');
         if (empty($message)) throw new Exception("公告/通知消息内容不能为空。");
         $command_base = ($announce_type === 'notify') ? '.nameannounce' : '.announce';
         $command = sprintf('%s %s', $command_base, $message);
         $client->executeCommand(new SoapParam($command, 'command'));
         $success_count = 1;
         $response['success'] = true;
         $response['message'] = "操作 'send_announce' 完成。";
         $_SESSION['mass_mail_result'] = $response;
         header('Location: index.php');
         exit; // Announce action finished
    }

    // --- Prepare for per-character actions (mail, item, gold) --- (Moved outside loop)
    $subject = trim($_POST['subject'] ?? '系统邮件');
    $body = trim($_POST['body'] ?? '');
    if (empty($subject) && ($action === 'send_mail' || $action === 'send_item' || $action === 'send_gold')) {
         throw new Exception("邮件标题不能为空。");
    }
    $itemId = null; $quantity = null; $amount = null;
    if ($action === 'send_item') {
        $itemId = filter_input(INPUT_POST, 'itemId', FILTER_VALIDATE_INT);
        $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
        if (!$itemId || $itemId <= 0) throw new Exception("无效的物品 ID。");
        if (!$quantity || $quantity <= 0) throw new Exception("无效的物品数量。");
    } elseif ($action === 'send_gold') {
        $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_INT);
         if (!$amount || $amount <= 0) throw new Exception("无效的金币数量。");
    }

    // --- Processing Loop --- (Simplified back)
    // Removed the do...while loop and batch logic
    foreach ($target_characters as $charName) {
        // $total_processed_count++; // No longer needed here
        try {
            $command = '';
             if ($action === 'send_mail') {
                 $command = sprintf('.send mail %s "%s" "%s"', $charName, $subject, $body);
             } elseif ($action === 'send_item') {
                 $command = sprintf('.send items %s "%s" "%s" %d:%d', $charName, $subject, $body, $itemId, $quantity);
             } elseif ($action === 'send_gold') {
                 $command = sprintf('.send money %s "%s" "%s" %d', $charName, $subject, $body, $amount);
             }

            if (!empty($command)) {
                // usleep(100000); // Delay can be removed or kept based on preference for online/custom lists
                $client->executeCommand(new SoapParam($command, 'command'));
                $success_count++;
            }
        } catch (SoapFault $e_loop) {
            error_log("SOAP Error sending to {$charName}: " . $e_loop->getMessage());
            $errors[] = "{$charName}: " . $e_loop->getMessage();
            $fail_count++;
        } catch (Exception $e_loop) {
             error_log("General Error sending to {$charName}: " . $e_loop->getMessage());
             $errors[] = "{$charName}: Error";
             $fail_count++;
        }
    } // End foreach

    // --- Prepare final response --- (Using total_processed_count calculated earlier)
    $response['success'] = ($fail_count === 0);
    $response['message'] = sprintf(
        "操作 '%s' 完成。目标总数: %d, 成功: %d, 失败: %d。",
        $action,
        $total_processed_count, 
        $success_count,
        $fail_count
    );
     if ($fail_count > 0) {
         $response['message'] .= " 部分错误: " . implode("; ", array_slice($errors, 0, 3));
     }

} catch (SoapFault $e) {
    error_log("SOAP Fault in mass_mail handler: " . $e->getMessage());
    $response['message'] = 'SOAP 操作失败: ' . htmlspecialchars($e->getMessage());
} catch (Exception $e) {
    error_log("General Error in mass_mail handler: " . $e->getMessage());
    $response['message'] = '执行操作时发生错误: ' . htmlspecialchars($e->getMessage());
}

// --- Redirect back with result ---
$_SESSION['mass_mail_result'] = $response;
header('Location: index.php');
exit;

?> 