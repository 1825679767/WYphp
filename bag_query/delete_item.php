<?php
declare(strict_types=1);

header('Content-Type: application/json');

require_once 'db.php';
require_once 'functions.php';

$response = ['success' => false, 'message' => '无效的请求。', 'new_count' => -1];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $character_guid = filter_input(INPUT_POST, 'character_guid', FILTER_VALIDATE_INT);
    $item_instance_guid = filter_input(INPUT_POST, 'item_instance_guid', FILTER_VALIDATE_INT);
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);

    if ($character_guid && $item_instance_guid && $quantity) {
        try {
            $connections = connect_databases();
            $pdo_C = $connections['db_C'];
            
            // 调用更新/删除函数
            $result = update_or_delete_item_stack($pdo_C, $character_guid, $item_instance_guid, $quantity);
            $response = $result; // 直接使用函数返回的结果

        } catch (Exception $e) {
            error_log("Delete item endpoint error: " . $e->getMessage());
            $response = ['success' => false, 'message' => '服务器错误: ' . $e->getMessage(), 'new_count' => -1];
        }
    } else {
        $response['message'] = '缺少或无效的参数。';
    }
} 

echo json_encode($response);
exit; 