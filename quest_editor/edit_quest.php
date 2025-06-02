<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
session_start();

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$config = require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../bag_query/db.php'; 

// --- Get Return Params for Back Button ---
$return_url_params = '';
if (isset($_GET['return_params'])) {
    $decoded_params = urldecode($_GET['return_params']);
    // Basic validation/sanitization could be added here if needed
    $return_url_params = 'index.php?' . $decoded_params;
} else {
    $return_url_params = 'index.php'; // Default back link if no params provided
}
// --- Get Return Params for Back Button ---

// --- AJAX Handler for Saving Quest ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_quest_ajax') {
    header('Content-Type: application/json; charset=utf-8');
    $response = ['success' => false, 'message' => '发生未知错误。'];

    // 1. Check Login
    $adminConf = $config['admin'] ?? null;
    $isLoggedInAjax = (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true);
    if (!$isLoggedInAjax) {
        $response['message'] = '错误：未登录或会话超时。';
        echo json_encode($response);
        exit;
    }

    // 2. Get and Validate Input
    $quest_id = filter_input(INPUT_POST, 'quest_id', FILTER_VALIDATE_INT);
    $changes_json = $_POST['changes'] ?? null;
    $changes = $changes_json ? json_decode($changes_json, true) : null;

    if (!$quest_id || $quest_id <= 0) {
        $response['message'] = '错误：无效的任务 ID。';
        echo json_encode($response);
        exit;
    }
    if (!is_array($changes) || empty($changes)) {
        $response['message'] = '错误：提交的更改数据无效或为空。';
        echo json_encode($response);
        exit;
    }

    // 3. Database Operation
    $pdo_W = null;
    try {
        $connections = connect_databases();
        if (!isset($connections['db_W'])) {
            throw new Exception("World 数据库连接未配置或失败。");
        }
        $pdo_W = $connections['db_W'];

        // 4. Define Valid/Editable Columns (CRUCIAL FOR SECURITY)
        // Fetch column names dynamically or use a predefined list based on $field_groups
        // Example using a predefined list (sync with $field_groups keys)
        $all_db_fields = []; 
        // Temporarily redefine $field_groups here just to get keys, or pass from main scope if possible
         $temp_field_groups = [ /* Paste $field_groups definition here or load from elsewhere */ 
            '核心信息' => ['QuestType', 'QuestLevel', 'MinLevel', 'QuestSortID', 'QuestInfoID', 'SuggestedGroupNum', 'Flags'],
            '奖励 (主要)' => ['RewardNextQuest', 'RewardXPDifficulty', 'RewardMoney', 'RewardMoneyDifficulty', 'RewardDisplaySpell', 'RewardSpell', 'RewardHonor', 'RewardKillHonor', 'RewardTitle', 'RewardTalents', 'RewardArenaPoints'],
            '文本与目标' => ['LogTitle', 'LogDescription', 'QuestDescription', 'AreaDescription', 'QuestCompletionLog', 'ObjectiveText1', 'ObjectiveText2', 'ObjectiveText3', 'ObjectiveText4'],
            '要求 (基本)' => ['RequiredFactionId1', 'RequiredFactionValue1', 'RequiredFactionId2', 'RequiredFactionValue2','RequiredPlayerKills', 'TimeAllowed', 'AllowableRaces', 'StartItem'],
            '奖励 (物品)' => ['RewardItem1', 'RewardAmount1', 'RewardItem2', 'RewardAmount2', 'RewardItem3', 'RewardAmount3', 'RewardItem4', 'RewardAmount4'],
            '奖励 (可选物品)' => ['RewardChoiceItemID1', 'RewardChoiceItemQuantity1', 'RewardChoiceItemID2', 'RewardChoiceItemQuantity2','RewardChoiceItemID3', 'RewardChoiceItemQuantity3', 'RewardChoiceItemID4', 'RewardChoiceItemQuantity4','RewardChoiceItemID5', 'RewardChoiceItemQuantity5', 'RewardChoiceItemID6', 'RewardChoiceItemQuantity6'],
            '要求 (NPC/GO)' => ['RequiredNpcOrGo1', 'RequiredNpcOrGoCount1', 'RequiredNpcOrGo2', 'RequiredNpcOrGoCount2','RequiredNpcOrGo3', 'RequiredNpcOrGoCount3', 'RequiredNpcOrGo4', 'RequiredNpcOrGoCount4'],
            '要求 (物品)' => ['RequiredItemId1', 'RequiredItemCount1', 'RequiredItemId2', 'RequiredItemCount2','RequiredItemId3', 'RequiredItemCount3', 'RequiredItemId4', 'RequiredItemCount4','RequiredItemId5', 'RequiredItemCount5', 'RequiredItemId6', 'RequiredItemCount6'],
            '奖励 (声望)' => ['RewardFactionID1', 'RewardFactionValue1', 'RewardFactionOverride1','RewardFactionID2', 'RewardFactionValue2', 'RewardFactionOverride2','RewardFactionID3', 'RewardFactionValue3', 'RewardFactionOverride3','RewardFactionID4', 'RewardFactionValue4', 'RewardFactionOverride4','RewardFactionID5', 'RewardFactionValue5', 'RewardFactionOverride5'],
            '地图标记 (POI)' => ['POIContinent', 'POIx', 'POIy', 'POIPriority'],
            '物品掉落 (目标)' => ['ItemDrop1', 'ItemDropQuantity1', 'ItemDrop2', 'ItemDropQuantity2','ItemDrop3', 'ItemDropQuantity3', 'ItemDrop4', 'ItemDropQuantity4'],
            '其他' => ['Unknown0', 'VerifiedBuild' ],
            // Added new groups for Start/End NPC/GO
            '任务开始结束NPC' => [ // Fields removed, data shown in table below
                // Fields removed, data shown in table below
            ],
            '任务开始结束物体' => [ // Fields removed, data shown in table below
                // Fields removed, data shown in table below
            ]
         ];
        foreach($temp_field_groups as $group) { $all_db_fields = array_merge($all_db_fields, $group); }
        $valid_columns = array_flip($all_db_fields);

        // 5. Build Prepared Statement Dynamically
        $set_clauses = [];
        $params = [':quest_id' => $quest_id]; // Use different placeholder name for ID in WHERE

        foreach ($changes as $key => $value) {
            if (!isset($valid_columns[$key])) {
                error_log("Invalid column submitted in AJAX save: " . $key); // Log invalid attempts
                continue; // Skip columns not in our allowed list
            }
            // Basic type coercion (can be enhanced based on column schema)
            // If value is empty string, decide if it should be NULL or 0 based on common patterns
            if ($value === '') {
                 // Simple guess: if key contains ID, Count, Value, Level, Amount, etc. or is numeric-like -> 0, else empty string
                 if (preg_match('/(Id|Count|Value|Level|Amount|Money|Honor|Talents|Points|Num|Override|Priority|TimeAllowed|Races|Flags|Type|SortID|InfoID|Unknown0|Build)/i', $key) || is_numeric($value)) {
                     $value = 0; // Default empty numeric-like fields to 0
                 } else {
                     $value = ''; // Default empty text-like fields to empty string
                 }
                 // Or, consistently set to NULL if column allows: $value = null;
            } elseif (is_numeric($value)) {
                // Ensure proper numeric type if needed (e.g., float for RewardKillHonor)
                if ($key === 'RewardKillHonor') {
                    $value = (float)$value;
                } else {
                    $value = is_int($value + 0) ? (int)$value : (float)$value; // Preserve float/int
                }
            }
            // Add more specific type handling if needed

            $set_clauses[] = "`" . $key . "` = :" . $key;
            $params[':' . $key] = $value;
        }

        // 6. Execute Update
        if (empty($set_clauses)) {
            $response['success'] = true; // Or maybe false? Or a different status?
            $response['message'] = '没有检测到有效或需要更新的字段。';
        } else {
            $sql = "UPDATE `quest_template` SET " . implode(', ', $set_clauses) . " WHERE `ID` = :quest_id";
            $stmt = $pdo_W->prepare($sql);
            $execute_success = $stmt->execute($params);

            if ($execute_success) {
                $affected_rows = $stmt->rowCount();
                $response['success'] = true;
                $response['message'] = "任务 (ID: {$quest_id}) 更新成功。影响行数: {$affected_rows}。";
            } else {
                 $errorInfo = $stmt->errorInfo();
                 $response['message'] = "数据库更新失败: " . ($errorInfo[2] ?? 'Unknown error');
                 error_log("AJAX Save Quest SQL Error: " . print_r($errorInfo, true) . " SQL: " . $sql . " PARAMS: " . print_r($params, true));
            }
        }

    // 7. Catch Errors
    } catch (PDOException $e) {
        error_log("AJAX Save Quest PDO Error: " . $e->getMessage());
        $response['message'] = "数据库错误: " . $e->getMessage();
    } catch (Exception $e) {
        error_log("AJAX Save Quest General Error: " . $e->getMessage());
        $response['message'] = "发生一般错误: " . $e->getMessage();
    }

    // 8. Send Response and Exit
    echo json_encode($response);
    exit;
}
// --- END AJAX Handler ---

// --- AJAX Handler for Deleting Association --- 
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_association_ajax') {
    header('Content-Type: application/json; charset=utf-8');
    $response = ['success' => false, 'message' => '删除关联时发生未知错误。'];

    // 1. Check Login (Reuse existing check)
    $adminConf = $config['admin'] ?? null;
    $isLoggedInAjax = (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true);
    if (!$isLoggedInAjax) {
        $response['message'] = '错误：未登录或会话超时。';
        echo json_encode($response);
        exit;
    }

    // 2. Get and Validate Input
    $quest_id = filter_input(INPUT_POST, 'quest_id', FILTER_VALIDATE_INT);
    $assoc_id = filter_input(INPUT_POST, 'assoc_id', FILTER_VALIDATE_INT); // The ID of the NPC or GO
    $assoc_type = $_POST['assoc_type'] ?? null; // e.g., 'creature_starter', 'gameobject_ender'

    if (!$quest_id || $quest_id <= 0) {
        $response['message'] = '错误：无效的任务 ID。';
        echo json_encode($response);
        exit;
    }
    if (!$assoc_id) { // assoc_id can be negative for GOs, so just check if it exists
        $response['message'] = '错误：无效的关联 ID。';
        echo json_encode($response);
        exit;
    }
    
    // Validate assoc_type and determine table name
    $valid_assoc_types = [
        'creature_starter' => 'creature_queststarter',
        'creature_ender' => 'creature_questender',
        'gameobject_starter' => 'gameobject_queststarter',
        'gameobject_ender' => 'gameobject_questender'
    ];

    if (!isset($valid_assoc_types[$assoc_type])) {
        $response['message'] = '错误：无效的关联类型。';
        echo json_encode($response);
        exit;
    }
    $table_name = $valid_assoc_types[$assoc_type];

    // 3. Database Operation
    $pdo_W = null;
    try {
        $connections = connect_databases();
        if (!isset($connections['db_W'])) {
            throw new Exception("World 数据库连接未配置或失败。");
        }
        $pdo_W = $connections['db_W'];

        // Use backticks for table name, but it's safe here as it comes from our validated list
        $sql = "DELETE FROM `{$table_name}` WHERE `quest` = :quest_id AND `id` = :assoc_id";
        $stmt = $pdo_W->prepare($sql);
        $stmt->bindParam(':quest_id', $quest_id, PDO::PARAM_INT);
        $stmt->bindParam(':assoc_id', $assoc_id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            $affected_rows = $stmt->rowCount();
            if ($affected_rows > 0) {
                $response['success'] = true;
                $response['message'] = "成功删除关联 (任务ID: {$quest_id}, 关联ID: {$assoc_id}, 类型: {$assoc_type})。";
            } else {
                 $response['success'] = true; // Consider it success even if row wasn't found (already deleted?)
                 $response['message'] = "未找到要删除的关联记录，或已被删除。";
            }
        } else {
             $errorInfo = $stmt->errorInfo();
             throw new Exception("数据库删除失败: " . ($errorInfo[2] ?? 'Unknown error'));
        }

    } catch (PDOException $e) {
        error_log("AJAX Delete Association PDO Error: " . $e->getMessage());
        $response['message'] = "数据库错误: " . $e->getMessage();
    } catch (Exception $e) {
        error_log("AJAX Delete Association General Error: " . $e->getMessage());
        $response['message'] = "发生一般错误: " . $e->getMessage();
    }

    // 4. Send Response and Exit
    echo json_encode($response);
    exit;
}
// --- END AJAX Handler for Deleting Association ---

// --- AJAX Handler for Editing Association ID ---
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_association_ajax') {
    header('Content-Type: application/json; charset=utf-8');
    $response = ['success' => false, 'message' => '编辑关联时发生未知错误。'];

    // 1. Check Login
    $adminConf = $config['admin'] ?? null;
    $isLoggedInAjax = (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true);
    if (!$isLoggedInAjax) {
        $response['message'] = '错误：未登录或会话超时。';
        echo json_encode($response);
        exit;
    }

    // 2. Get and Validate Input
    $quest_id = filter_input(INPUT_POST, 'quest_id', FILTER_VALIDATE_INT);
    $original_assoc_id = filter_input(INPUT_POST, 'original_assoc_id', FILTER_VALIDATE_INT);
    $new_assoc_id = filter_input(INPUT_POST, 'new_assoc_id', FILTER_VALIDATE_INT);
    $assoc_type = $_POST['assoc_type'] ?? null;

    if (!$quest_id || $quest_id <= 0) {
        $response['message'] = '错误：无效的任务 ID。';
        echo json_encode($response);
        exit;
    }
     if (!$original_assoc_id) { 
        $response['message'] = '错误：无效的原始关联 ID。';
        echo json_encode($response);
        exit;
    }
     if (!$new_assoc_id) { 
        $response['message'] = '错误：无效的新关联 ID。';
        echo json_encode($response);
        exit;
    }
    if ($original_assoc_id === $new_assoc_id) {
         $response['message'] = '错误：新旧 ID 不能相同。';
         echo json_encode($response);
         exit;
    }

    // Validate assoc_type and determine table name
    $valid_assoc_types = [
        'creature_starter' => 'creature_queststarter',
        'creature_ender' => 'creature_questender',
        'gameobject_starter' => 'gameobject_queststarter',
        'gameobject_ender' => 'gameobject_questender'
    ];
    if (!isset($valid_assoc_types[$assoc_type])) {
        $response['message'] = '错误：无效的关联类型。';
        echo json_encode($response);
        exit;
    }
    $table_name = $valid_assoc_types[$assoc_type];

    // 3. Database Operation
    $pdo_W = null;
    try {
        $connections = connect_databases();
        if (!isset($connections['db_W'])) {
            throw new Exception("World 数据库连接未配置或失败。");
        }
        $pdo_W = $connections['db_W'];
        $pdo_W->beginTransaction();

        // Optional: Check if the new ID already exists for this quest in the same table?
        // Depends on if duplicates are allowed. Assuming for now we just update.

        $sql = "UPDATE `{$table_name}` SET `id` = :new_assoc_id WHERE `quest` = :quest_id AND `id` = :original_assoc_id";
        $stmt = $pdo_W->prepare($sql);
        $stmt->bindParam(':new_assoc_id', $new_assoc_id, PDO::PARAM_INT);
        $stmt->bindParam(':quest_id', $quest_id, PDO::PARAM_INT);
        $stmt->bindParam(':original_assoc_id', $original_assoc_id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            $affected_rows = $stmt->rowCount();
            if ($affected_rows > 0) {
                $pdo_W->commit();
                $response['success'] = true;
                $response['message'] = "成功更新关联 ID 从 {$original_assoc_id} 到 {$new_assoc_id} (任务ID: {$quest_id}, 类型: {$assoc_type})。";
                $response['new_id'] = $new_assoc_id; // Send back new ID for frontend update
            } else {
                 $pdo_W->rollBack(); // Nothing updated, maybe original ID didn't match?
                 $response['message'] = "未找到匹配的原始关联记录进行更新。";
            }
        } else {
             $errorInfo = $stmt->errorInfo();
             $pdo_W->rollBack();
              // Check for duplicate entry error (MySQL error code 1062)
              if ($errorInfo[1] == 1062) {
                   $response['message'] = "数据库更新失败：新的关联 ID ({$new_assoc_id}) 对于此任务已存在。";
              } else {
                    throw new Exception("数据库更新失败: " . ($errorInfo[2] ?? 'Unknown error'));
              }
        }

    } catch (PDOException $e) {
        if ($pdo_W && $pdo_W->inTransaction()) $pdo_W->rollBack();
        error_log("AJAX Edit Association PDO Error: " . $e->getMessage());
        $response['message'] = "数据库错误: " . $e->getMessage();
    } catch (Exception $e) {
        if ($pdo_W && $pdo_W->inTransaction()) $pdo_W->rollBack();
        error_log("AJAX Edit Association General Error: " . $e->getMessage());
        $response['message'] = "发生一般错误: " . $e->getMessage();
    }

    // 4. Send Response and Exit
    echo json_encode($response);
    exit;
}
// --- END AJAX Handler for Editing Association ID ---

// --- AJAX Handler for Adding Association ---
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_association_ajax') {
    header('Content-Type: application/json; charset=utf-8');
    $response = ['success' => false, 'message' => '添加关联时发生未知错误。'];

    // 1. Check Login
    $adminConf = $config['admin'] ?? null;
    $isLoggedInAjax = (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true);
    if (!$isLoggedInAjax) {
        $response['message'] = '错误：未登录或会话超时。';
        echo json_encode($response);
        exit;
    }

    // 2. Get and Validate Input
    $quest_id = filter_input(INPUT_POST, 'quest_id', FILTER_VALIDATE_INT);
    $assoc_id = filter_input(INPUT_POST, 'assoc_id', FILTER_VALIDATE_INT);
    $assoc_type = $_POST['assoc_type'] ?? null; // e.g., 'creature_starter'

    if (!$quest_id || $quest_id <= 0) {
        $response['message'] = '错误：无效的任务 ID。';
        echo json_encode($response);
        exit;
    }
     if (!$assoc_id) { 
        $response['message'] = '错误：请输入有效的关联 ID。';
        echo json_encode($response);
        exit;
    }

    // Validate assoc_type and determine table name
    $valid_assoc_types = [
        'creature_starter' => 'creature_queststarter',
        'creature_ender' => 'creature_questender',
        'gameobject_starter' => 'gameobject_queststarter',
        'gameobject_ender' => 'gameobject_questender'
    ];
    if (!isset($valid_assoc_types[$assoc_type])) {
        $response['message'] = '错误：无效的关联类型。';
        echo json_encode($response);
        exit;
    }
    $table_name = $valid_assoc_types[$assoc_type];

    // 3. Database Operation
    $pdo_W = null;
    try {
        $connections = connect_databases();
        if (!isset($connections['db_W'])) {
            throw new Exception("World 数据库连接未配置或失败。");
        }
        $pdo_W = $connections['db_W'];

        // Prepare INSERT statement
        // Assuming primary key is (id, quest) or similar, insertion might fail if it exists
        $sql = "INSERT INTO `{$table_name}` (`id`, `quest`) VALUES (:assoc_id, :quest_id)";
        $stmt = $pdo_W->prepare($sql);
        $stmt->bindParam(':assoc_id', $assoc_id, PDO::PARAM_INT);
        $stmt->bindParam(':quest_id', $quest_id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = "成功添加关联 (任务ID: {$quest_id}, 关联ID: {$assoc_id}, 类型: {$assoc_type})。";
            // Send back data needed to add row to table
            $response['added_data'] = [
                'id' => $assoc_id,
                'quest' => $quest_id,
                'type' => $assoc_type
            ];
        } else {
             $errorInfo = $stmt->errorInfo();
             // Check for duplicate entry error (MySQL error code 1062)
             if ($errorInfo[1] == 1062) {
                  $response['message'] = "数据库插入失败：该关联已存在 (ID: {$assoc_id}, 类型: {$assoc_type})。";
             } else {
                   throw new Exception("数据库插入失败: " . ($errorInfo[2] ?? 'Unknown error'));
             }
        }

    } catch (PDOException $e) {
        error_log("AJAX Add Association PDO Error: " . $e->getMessage());
        // Check for duplicate entry on PDOException as well (might differ across drivers)
        if (strpos($e->getMessage(), 'Duplicate entry') !== false || $e->getCode() == 23000) { // 23000 is SQLSTATE for integrity constraint violation
             $response['message'] = "数据库插入失败：该关联已存在 (ID: {$assoc_id}, 类型: {$assoc_type})。";
        } else {
            $response['message'] = "数据库错误: " . $e->getMessage();
        }
    } catch (Exception $e) {
        error_log("AJAX Add Association General Error: " . $e->getMessage());
        $response['message'] = "发生一般错误: " . $e->getMessage();
    }

    // 4. Send Response and Exit
    echo json_encode($response);
    exit;
}
// --- END AJAX Handler for Adding Association ---




// --- Login Check (for Page Load) ---
$adminConf = $config['admin'] ?? null;
$isLoggedIn = (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true);

if (!$isLoggedIn) {
    // Redirect to index which handles login form display
    header('Location: index.php'); 
    exit;
}

// --- Get Quest ID ---
$quest_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$quest_data = null;
$error_message = '';
$pdo_W = null;
// --- Variables to store starter/ender lists ---
$creature_starters = [];
$creature_enders = [];
$gameobject_starters = [];
$gameobject_enders = [];
// --- End Variables ---

if (!$quest_id || $quest_id <= 0) {
    $error_message = "无效的任务 ID。";
} else {
    try {
        $connections = connect_databases();
        if (!isset($connections['db_W'])) {
            throw new Exception("World 数据库连接未配置或失败。");
        }
        $pdo_W = $connections['db_W'];

        // Fetch all columns for the quest
        $stmt = $pdo_W->prepare("SELECT * FROM quest_template WHERE ID = :id");
        $stmt->bindParam(':id', $quest_id, PDO::PARAM_INT);
        $stmt->execute();
        $quest_data = $stmt->fetch(PDO::FETCH_ASSOC);

        // --- Fetch Starter/Ender Data --- 
        if ($quest_data) { // Only fetch if quest exists
            // Creature Starters
            $stmt_cs = $pdo_W->prepare("SELECT id FROM creature_queststarter WHERE quest = :quest_id");
            $stmt_cs->bindParam(':quest_id', $quest_id, PDO::PARAM_INT);
            $stmt_cs->execute();
            $creature_starters = $stmt_cs->fetchAll(PDO::FETCH_COLUMN);

            // Creature Enders
            $stmt_ce = $pdo_W->prepare("SELECT id FROM creature_questender WHERE quest = :quest_id");
            $stmt_ce->bindParam(':quest_id', $quest_id, PDO::PARAM_INT);
            $stmt_ce->execute();
            $creature_enders = $stmt_ce->fetchAll(PDO::FETCH_COLUMN);

            // GameObject Starters
            $stmt_gos = $pdo_W->prepare("SELECT id FROM gameobject_queststarter WHERE quest = :quest_id");
            $stmt_gos->bindParam(':quest_id', $quest_id, PDO::PARAM_INT);
            $stmt_gos->execute();
            $gameobject_starters = $stmt_gos->fetchAll(PDO::FETCH_COLUMN);

            // GameObject Enders
            $stmt_goe = $pdo_W->prepare("SELECT id FROM gameobject_questender WHERE quest = :quest_id");
            $stmt_goe->bindParam(':quest_id', $quest_id, PDO::PARAM_INT);
            $stmt_goe->execute();
            $gameobject_enders = $stmt_goe->fetchAll(PDO::FETCH_COLUMN);
        }
        // --- End Fetch Starter/Ender Data ---

        if (!$quest_data) {
            $error_message = "找不到 ID 为 " . htmlspecialchars((string)$quest_id) . " 的任务。";
            $quest_data = null; // Ensure it's null if not found
        }

    } catch (Exception $e) {
        $error_message = "加载任务时出错: " . $e->getMessage();
        $quest_data = null; // Ensure it's null on error
    }
}

// --- Define Field Groups (Based on Wiki Structure) ---
// We define this even if $quest_data is null to prevent errors later, 
// though the form won't render meaningfully without data.
$field_groups = [
    '核心信息' => [
        'ID', 'QuestType', 'QuestLevel', 'MinLevel', 'QuestSortID', 
        'QuestInfoID', 'SuggestedGroupNum', 'Flags'
    ],
    '奖励 (主要)' => [
        'RewardNextQuest', 'RewardXPDifficulty', 'RewardMoney', 'RewardMoneyDifficulty', 
        'RewardDisplaySpell', 'RewardSpell', 'RewardHonor', 'RewardKillHonor', 
        'RewardTitle', 'RewardTalents', 'RewardArenaPoints'
    ],
    '文本与目标' => [
        'LogTitle', 'LogDescription', 'QuestDescription', 'AreaDescription', 
        'QuestCompletionLog', 'ObjectiveText1', 'ObjectiveText2', 'ObjectiveText3', 'ObjectiveText4'
    ],
     '要求 (基本)' => [
        'RequiredFactionId1', 'RequiredFactionValue1', 'RequiredFactionId2', 'RequiredFactionValue2',
        'RequiredPlayerKills', 'TimeAllowed', 'AllowableRaces', 'StartItem'
    ],
     '奖励 (物品)' => [
         'RewardItem1', 'RewardAmount1', 'RewardItem2', 'RewardAmount2', 
         'RewardItem3', 'RewardAmount3', 'RewardItem4', 'RewardAmount4'
    ],
     '奖励 (可选物品)' => [
         'RewardChoiceItemID1', 'RewardChoiceItemQuantity1', 'RewardChoiceItemID2', 'RewardChoiceItemQuantity2',
         'RewardChoiceItemID3', 'RewardChoiceItemQuantity3', 'RewardChoiceItemID4', 'RewardChoiceItemQuantity4',
         'RewardChoiceItemID5', 'RewardChoiceItemQuantity5', 'RewardChoiceItemID6', 'RewardChoiceItemQuantity6'
    ],
    '要求 (NPC/GO)' => [
        'RequiredNpcOrGo1', 'RequiredNpcOrGoCount1', 'RequiredNpcOrGo2', 'RequiredNpcOrGoCount2',
        'RequiredNpcOrGo3', 'RequiredNpcOrGoCount3', 'RequiredNpcOrGo4', 'RequiredNpcOrGoCount4'
    ],
    '要求 (物品)' => [
        'RequiredItemId1', 'RequiredItemCount1', 'RequiredItemId2', 'RequiredItemCount2',
        'RequiredItemId3', 'RequiredItemCount3', 'RequiredItemId4', 'RequiredItemCount4',
        'RequiredItemId5', 'RequiredItemCount5', 'RequiredItemId6', 'RequiredItemCount6'
    ],
    '奖励 (声望)' => [
        'RewardFactionID1', 'RewardFactionValue1', 'RewardFactionOverride1',
        'RewardFactionID2', 'RewardFactionValue2', 'RewardFactionOverride2',
        'RewardFactionID3', 'RewardFactionValue3', 'RewardFactionOverride3',
        'RewardFactionID4', 'RewardFactionValue4', 'RewardFactionOverride4',
        'RewardFactionID5', 'RewardFactionValue5', 'RewardFactionOverride5'
    ],
     '地图标记 (POI)' => [
         'POIContinent', 'POIx', 'POIy', 'POIPriority'
     ],
     '物品掉落 (目标)' => [ // Might relate to objectives
         'ItemDrop1', 'ItemDropQuantity1', 'ItemDrop2', 'ItemDropQuantity2',
         'ItemDrop3', 'ItemDropQuantity3', 'ItemDrop4', 'ItemDropQuantity4'
     ],
     '其他' => [
         'Unknown0', 'VerifiedBuild' 
     ],
     // Added new groups for Start/End NPC/GO
     '任务开始结束NPC' => [ // Fields removed, data shown in table below
         // Fields removed, data shown in table below
     ],
     '任务开始结束物体' => [ // Fields removed, data shown in table below
         // Fields removed, data shown in table below
     ]
];

// --- Field Labels (Chinese Translations) ---
$field_labels = [
    'ID' => 'ID', 'QuestType' => '任务类型', 'QuestLevel' => '任务等级', 'MinLevel' => '最低等级', 'QuestSortID' => '任务排序ID',
    'QuestInfoID' => '任务信息ID', 'SuggestedGroupNum' => '建议队伍人数', 'Flags' => '标记 (Flags)',
    'RewardNextQuest' => '后续任务ID', 'RewardXPDifficulty' => '经验奖励难度', 'RewardMoney' => '金钱奖励(铜)', 'RewardMoneyDifficulty' => '金钱奖励难度',
    'RewardDisplaySpell' => '奖励显示法术ID', 'RewardSpell' => '奖励法术ID', 'RewardHonor' => '荣誉奖励', 'RewardKillHonor' => '击杀荣誉奖励',
    'RewardTitle' => '奖励头衔ID', 'RewardTalents' => '奖励天赋点', 'RewardArenaPoints' => '奖励竞技场点数',
    'LogTitle' => '标题', 'LogDescription' => '描述', 'QuestDescription' => '任务描述', 'AreaDescription' => '区域/目标描述',
    'QuestCompletionLog' => '任务完成日志', 'ObjectiveText1' => '目标文本1', 'ObjectiveText2' => '目标文本2', 'ObjectiveText3' => '目标文本3', 'ObjectiveText4' => '目标文本4',
    'RequiredFactionId1' => '要求阵营1 ID', 'RequiredFactionValue1' => '要求阵营1 声望值', 'RequiredFactionId2' => '要求阵营2 ID', 'RequiredFactionValue2' => '要求阵营2 声望值',
    'RequiredPlayerKills' => '要求击杀玩家数', 'TimeAllowed' => '允许时间(秒)', 'AllowableRaces' => '允许种族(掩码)', 'StartItem' => '起始物品ID',
    'RewardItem1' => '奖励物品1 ID', 'RewardAmount1' => '奖励物品1 数量', 'RewardItem2' => '奖励物品2 ID', 'RewardAmount2' => '奖励物品2 数量',
    'RewardItem3' => '奖励物品3 ID', 'RewardAmount3' => '奖励物品3 数量', 'RewardItem4' => '奖励物品4 ID', 'RewardAmount4' => '奖励物品4 数量',
    'RewardChoiceItemID1' => '可选奖励1 ID', 'RewardChoiceItemQuantity1' => '可选奖励1 数量', 'RewardChoiceItemID2' => '可选奖励2 ID', 'RewardChoiceItemQuantity2' => '可选奖励2 数量',
    'RewardChoiceItemID3' => '可选奖励3 ID', 'RewardChoiceItemQuantity3' => '可选奖励3 数量', 'RewardChoiceItemID4' => '可选奖励4 ID', 'RewardChoiceItemQuantity4' => '可选奖励4 数量',
    'RewardChoiceItemID5' => '可选奖励5 ID', 'RewardChoiceItemQuantity5' => '可选奖励5 数量', 'RewardChoiceItemID6' => '可选奖励6 ID', 'RewardChoiceItemQuantity6' => '可选奖励6 数量',
    'RequiredNpcOrGo1' => '要求NPC/GO 1 ID', 'RequiredNpcOrGoCount1' => '要求NPC/GO 1 数量', 'RequiredNpcOrGo2' => '要求NPC/GO 2 ID', 'RequiredNpcOrGoCount2' => '要求NPC/GO 2 数量',
    'RequiredNpcOrGo3' => '要求NPC/GO 3 ID', 'RequiredNpcOrGoCount3' => '要求NPC/GO 3 数量', 'RequiredNpcOrGo4' => '要求NPC/GO 4 ID', 'RequiredNpcOrGoCount4' => '要求NPC/GO 4 数量',
    'RequiredItemId1' => '要求物品1 ID', 'RequiredItemCount1' => '要求物品1 数量', 'RequiredItemId2' => '要求物品2 ID', 'RequiredItemCount2' => '要求物品2 数量',
    'RequiredItemId3' => '要求物品3 ID', 'RequiredItemCount3' => '要求物品3 数量', 'RequiredItemId4' => '要求物品4 ID', 'RequiredItemCount4' => '要求物品4 数量',
    'RequiredItemId5' => '要求物品5 ID', 'RequiredItemCount5' => '要求物品5 数量', 'RequiredItemId6' => '要求物品6 ID', 'RequiredItemCount6' => '要求物品6 数量',
    'RewardFactionID1' => '奖励阵营1 ID', 'RewardFactionValue1' => '奖励阵营1 声望值', 'RewardFactionOverride1' => '奖励阵营1 覆盖值',
    'RewardFactionID2' => '奖励阵营2 ID', 'RewardFactionValue2' => '奖励阵营2 声望值', 'RewardFactionOverride2' => '奖励阵营2 覆盖值',
    'RewardFactionID3' => '奖励阵营3 ID', 'RewardFactionValue3' => '奖励阵营3 声望值', 'RewardFactionOverride3' => '奖励阵营3 覆盖值',
    'RewardFactionID4' => '奖励阵营4 ID', 'RewardFactionValue4' => '奖励阵营4 声望值', 'RewardFactionOverride4' => '奖励阵营4 覆盖值',
    'RewardFactionID5' => '奖励阵营5 ID', 'RewardFactionValue5' => '奖励阵营5 声望值', 'RewardFactionOverride5' => '奖励阵营5 覆盖值',
    'POIContinent' => 'POI 大陆ID', 'POIx' => 'POI X坐标', 'POIy' => 'POI Y坐标', 'POIPriority' => 'POI 优先级',
    'ItemDrop1' => '掉落物品1 ID', 'ItemDropQuantity1' => '掉落物品1 数量', 'ItemDrop2' => '掉落物品2 ID', 'ItemDropQuantity2' => '掉落物品2 数量',
    'ItemDrop3' => '掉落物品3 ID', 'ItemDropQuantity3' => '掉落物品3 数量', 'ItemDrop4' => '掉落物品4 ID', 'ItemDropQuantity4' => '掉落物品4 数量',
    'Unknown0' => '未知0', 'VerifiedBuild' => '验证版本号',
    // Added labels for new fields
    'CreatureStart' => '起始NPC ID', 'CreatureEnd' => '结束NPC ID',
    'GameObjectStart' => '起始物体 ID', 'GameObjectEnd' => '结束物体 ID'
];

// --- Field Descriptions (for tooltips) ---
$field_descriptions = [
    'QuestType' => '任务类型: 接受的值: 0, 1 或 2。<br>0: 任务启用，接受后自动完成，跳过目标和细节。<br>1: 任务禁用 (核心未实现)。<br>2: 任务启用 (不自动完成)。',
    'QuestLevel' => '任务等级: 玩家等级 <= 任务等级+5 时获得全额经验。如果设为-1，则使用玩家等级计算经验。',
    'MinLevel' => '最低等级: 玩家可以接受此任务的最低等级。',
    'QuestSortID' => '任务排序ID: 定义任务在日志中的分类。<br>> 0: AreaTable.dbc 中的区域 ID。<br>< 0: QuestSort.dbc 中的 ID (通常是专业或职业任务)。',
    'QuestInfoID' => '任务信息ID: QuestInfo.dbc 中的 ID。',
    'SuggestedGroupNum' => '建议队伍人数: 完成此任务建议的玩家数量。',
    'RequiredFactionId1' => '要求阵营1 ID: 需要声望阵营的 ID (来自 Faction.dbc)。',
    'RequiredFactionValue1' => '要求阵营1 声望值: 完成任务时，对应阵营需要达到的声望值。',
    'RequiredFactionId2' => '要求阵营2 ID: 第二个需要声望阵营的 ID (来自 Faction.dbc)。',
    'RequiredFactionValue2' => '要求阵营2 声望值: 完成任务时，第二个对应阵营需要达到的声望值。',
    'RewardFactionID1' => '奖励阵营1 ID: 任务给予声望奖励的阵营 ID (来自 Faction.dbc)。',
    'RewardFactionValue1' => '奖励阵营1 声望值: 控制奖励多少声望。正值使用 QuestFactionReward.dbc 第一行，负值使用第二行，RepX列由该值决定。如果 RewardFactionValueId1 不为0，则优先使用 RewardFactionValueId1 的值。', // Mapped from RewardFactionValueId1
    'RewardFactionID2' => '奖励阵营2 ID: 任务给予声望奖励的第二个阵营 ID。',
    'RewardFactionValue2' => '奖励阵营2 声望值: 控制奖励多少声望。正值使用 QuestFactionReward.dbc 第一行，负值使用第二行，RepX列由该值决定。如果 RewardFactionValueId2 不为0，则优先使用 RewardFactionValueId2 的值。', // Mapped from RewardFactionValueId2
    'RewardNextQuest' => '后续任务ID (旧称: NextQuestIdChain): 定义在完成当前任务后立即由同一NPC/GO提供的下一个任务的ID。例如，当你交还一个任务时，NPC会立即给你一个新的任务。',
    'RewardXPDifficulty' => '经验奖励难度: 根据任务等级，从 QuestXP.dbc 中查找基础经验值。此字段还控制经验计算，公式依赖于任务等级。可重复任务只奖励一次经验。实际获得的经验也受玩家与任务等级差异的影响。',
    'RewardMoney' => '金钱奖励(铜): > 0: 完成任务获得的金钱（铜币）。<br>< 0: 完成任务所需的金钱（铜币）。',
    'RewardMoneyDifficulty' => '金钱奖励难度: 引用 MoneyFactor.dbc 中的一个等级相关的金钱系数ID。',
    'RewardDisplaySpell' => '奖励显示法术ID: 在任务日志中显示的、完成任务时"看起来"会施放的法术ID。注意：如果 RewardSpell 字段非零，则实际施放的是 RewardSpell 中的法术，此字段仅作视觉效果。',
    'RewardSpell' => '奖励法术ID: 完成任务时实际施放的法术ID。如果此字段非零，它将覆盖 RewardDisplaySpell 的视觉效果。',
    'RewardHonor' => '荣誉奖励: 完成任务奖励的荣誉点数。例如：值15，在80级时，一次荣誉击杀为124荣誉，124 * 15 = 1860荣誉。',
    'RewardKillHonor' => '击杀荣誉奖励: 完成任务奖励的击杀荣誉值 (通常与战场或特定PvP任务相关)。',
    'StartItem' => '起始物品ID: 接受任务时给予玩家的物品ID。放弃任务时这些物品会被删除。',
    'Flags' => '标记 (Flags): 定义任务的特定类型（如日常、可共享等）。主要用于分组，大多数标记不影响任务需求（需求由其他字段定义）。有些标记的用途尚不明确。',
    // Newly added descriptions from the user
    'RequiredPlayerKills' => '要求击杀玩家数: 完成任务前需要击杀的敌对阵营玩家数量。',
    'RewardItem1' => '奖励物品1 ID: 任务奖励的物品 ID (固定奖励，非可选)。',
    'RewardAmount1' => '奖励物品1 数量: 上述奖励物品的数量。',
    'RewardItem2' => '奖励物品2 ID: 任务奖励的第二个物品 ID。',
    'RewardAmount2' => '奖励物品2 数量: 上述奖励物品的数量。',
    'RewardItem3' => '奖励物品3 ID: 任务奖励的第三个物品 ID。',
    'RewardAmount3' => '奖励物品3 数量: 上述奖励物品的数量。',
    'RewardItem4' => '奖励物品4 ID: 任务奖励的第四个物品 ID。',
    'RewardAmount4' => '奖励物品4 数量: 上述奖励物品的数量。',
    'ItemDrop1' => '掉落物品1 ID: 任务目标需要收集的物品 ID (通常由特定怪物掉落)。',
    'ItemDropQuantity1' => '掉落物品1 数量: 需要收集的 ItemDrop1 的数量。',
    'ItemDrop2' => '掉落物品2 ID: 任务目标需要收集的第二个物品 ID。',
    'ItemDropQuantity2' => '掉落物品2 数量: 需要收集的 ItemDrop2 的数量。',
    'ItemDrop3' => '掉落物品3 ID: 任务目标需要收集的第三个物品 ID。',
    'ItemDropQuantity3' => '掉落物品3 数量: 需要收集的 ItemDrop3 的数量。',
    'ItemDrop4' => '掉落物品4 ID: 任务目标需要收集的第四个物品 ID。',
    'ItemDropQuantity4' => '掉落物品4 数量: 需要收集的 ItemDrop4 的数量。',
    'RewardChoiceItemID1' => '可选奖励1 ID: 玩家可以从中选择的第一个奖励物品的 ID。',
    'RewardChoiceItemQuantity1' => '可选奖励1 数量: 上述可选奖励物品的数量。',
    'RewardChoiceItemID2' => '可选奖励2 ID: 玩家可以从中选择的第二个奖励物品的 ID。',
    'RewardChoiceItemQuantity2' => '可选奖励2 数量: 上述可选奖励物品的数量。',
    'RewardChoiceItemID3' => '可选奖励3 ID: 玩家可以从中选择的第三个奖励物品的 ID。',
    'RewardChoiceItemQuantity3' => '可选奖励3 数量: 上述可选奖励物品的数量。',
    'RewardChoiceItemID4' => '可选奖励4 ID: 玩家可以从中选择的第四个奖励物品的 ID。',
    'RewardChoiceItemQuantity4' => '可选奖励4 数量: 上述可选奖励物品的数量。',
    'RewardChoiceItemID5' => '可选奖励5 ID: 玩家可以从中选择的第五个奖励物品的 ID。',
    'RewardChoiceItemQuantity5' => '可选奖励5 数量: 上述可选奖励物品的数量。',
    'RewardChoiceItemID6' => '可选奖励6 ID: 玩家可以从中选择的第六个奖励物品的 ID。',
    'RewardChoiceItemQuantity6' => '可选奖励6 数量: 上述可选奖励物品的数量。',
    'POIContinent' => 'POI 大陆ID: 任务兴趣点 (POI) 所在的大地图 ID (Map.dbc)。任务激活时会在地图上显示标记。',
    'POIx' => 'POI X坐标: 任务兴趣点 (POI) 的 X 坐标。',
    'POIy' => 'POI Y坐标: 任务兴趣点 (POI) 的 Y 坐标。',
    'POIPriority' => 'POI 优先级: 任务兴趣点 (POI) 的优先级 (具体用途待定 TODO)。',
    'RewardTitle' => '奖励头衔ID: 完成任务奖励的头衔 ID (CharTitles.dbc)。',
    'RewardTalents' => '奖励天赋点: 完成任务奖励的天赋点数 (已弃用)。',
    'RewardArenaPoints' => '奖励竞技场点数: 完成任务奖励的竞技场点数。',
    // IDs 1&2, Values 1&2 already added
    'RewardFactionOverride1' => '奖励阵营1 覆盖值: 控制如何应用阵营声望值1。通常影响声望计算方式或上限。',
    'RewardFactionOverride2' => '奖励阵营2 覆盖值: 控制如何应用阵营声望值2。',
    'RewardFactionID3' => '奖励阵营3 ID: 任务给予声望奖励的第三个阵营 ID。',
    'RewardFactionValue3' => '奖励阵营3 声望值: 控制奖励多少声望。参照 QuestFactionReward.dbc。',
    'RewardFactionOverride3' => '奖励阵营3 覆盖值: 控制如何应用阵营声望值3。',
    'RewardFactionID4' => '奖励阵营4 ID: 任务给予声望奖励的第四个阵营 ID。',
    'RewardFactionValue4' => '奖励阵营4 声望值: 控制奖励多少声望。参照 QuestFactionReward.dbc。',
    'RewardFactionOverride4' => '奖励阵营4 覆盖值: 控制如何应用阵营声望值4。',
    'RewardFactionID5' => '奖励阵营5 ID: 任务给予声望奖励的第五个阵营 ID。',
    'RewardFactionValue5' => '奖励阵营5 声望值: 控制奖励多少声望。参照 QuestFactionReward.dbc。',
    'RewardFactionOverride5' => '奖励阵营5 覆盖值: 控制如何应用阵营声望值5。',
    'TimeAllowed' => '允许时间(秒): 完成任务的时间限制（秒）。0 表示没有限制。',
    'AllowableRaces' => '允许种族(掩码): 可以接受此任务的种族位掩码 (ChrRaces.dbc)。0 表示所有种族。-1 表示无。',
    'LogTitle' => '标题: 任务在日志中显示的标题。',
    'LogDescription' => '描述: 任务的目标描述。如果为空，任务在接受后可立即完成（自动完成）。',
    // Last batch of descriptions
    'QuestDescription' => '任务描述: 任务的主要文本内容。可用占位符: $B (换行), $N (名字), $R (种族), $C (职业), $G&lt;男&gt;:&lt;女&gt;; (性别称谓)。',
    'AreaDescription' => '区域/目标描述: 与任务目标区域或特定目标相关的描述性文本。',
    'QuestCompletionLog' => '任务完成日志: 当玩家与NPC对话但任务未完成时显示的文本 (类似 Wowhead 上的"进行中"文本)。可用占位符同上。',
    'RequiredNpcOrGo1' => '要求NPC/GO 1 ID: > 0: 需要击杀/施法的 creature_template ID。<br>< 0: 需要施法的 gameobject_template ID。<br>如果 RequiredSpellCast 非0，则目标为施法，否则为击杀。如果 RequiredSpellCast 非0 且法术效果为 Send Event 或 Quest Complete，此字段可为空。',
    'RequiredNpcOrGoCount1' => '要求NPC/GO 1 数量: 需要击杀/施法的目标1的数量。',
    'RequiredNpcOrGo2' => '要求NPC/GO 2 ID: 需要击杀/施法的第二个目标 ID。',
    'RequiredNpcOrGoCount2' => '要求NPC/GO 2 数量: 需要击杀/施法的目标2的数量。',
    'RequiredNpcOrGo3' => '要求NPC/GO 3 ID: 需要击杀/施法的第三个目标 ID。',
    'RequiredNpcOrGoCount3' => '要求NPC/GO 3 数量: 需要击杀/施法的目标3的数量。',
    'RequiredNpcOrGo4' => '要求NPC/GO 4 ID: 需要击杀/施法的第四个目标 ID。',
    'RequiredNpcOrGoCount4' => '要求NPC/GO 4 数量: 需要击杀/施法的目标4的数量。',
    'RequiredItemId1' => '要求物品1 ID: 完成任务所需的物品 ID。',
    'RequiredItemCount1' => '要求物品1 数量: 需要的物品1的数量。',
    'RequiredItemId2' => '要求物品2 ID: 完成任务所需的第二个物品 ID。',
    'RequiredItemCount2' => '要求物品2 数量: 需要的物品2的数量。',
    'RequiredItemId3' => '要求物品3 ID: 完成任务所需的第三个物品 ID。',
    'RequiredItemCount3' => '要求物品3 数量: 需要的物品3的数量。',
    'RequiredItemId4' => '要求物品4 ID: 完成任务所需的第四个物品 ID。',
    'RequiredItemCount4' => '要求物品4 数量: 需要的物品4的数量。',
    'RequiredItemId5' => '要求物品5 ID: 完成任务所需的第五个物品 ID。',
    'RequiredItemCount5' => '要求物品5 数量: 需要的物品5的数量。',
    'RequiredItemId6' => '要求物品6 ID: 完成任务所需的第六个物品 ID。',
    'RequiredItemCount6' => '要求物品6 数量: 需要的物品6的数量。',
    'Unknown0' => '未知0: 用途未知的字段。',
    'ObjectiveText1' => '目标文本1: 用于在任务日志中显示自定义目标文本 (例如，"治疗倒下的战士")。数量由相应的 Count 字段更新。',
    'ObjectiveText2' => '目标文本2: 自定义目标文本2。',
    'ObjectiveText3' => '目标文本3: 自定义目标文本3。',
    'ObjectiveText4' => '目标文本4: 自定义目标文本4。',
    'VerifiedBuild' => '验证版本号: 与此任务相关的客户端版本号。0 表示忽略。',
    // Added descriptions for new fields
    'CreatureStart' => '起始NPC ID: 给予此任务的生物 (Creature) 的 ID。',
    'CreatureEnd' => '结束NPC ID: 接收此任务的生物 (Creature) 的 ID。',
    'GameObjectStart' => '起始物体 ID: 给予此任务的游戏对象 (GameObject) 的 ID。',
    'GameObjectEnd' => '结束物体 ID: 接收此任务的游戏对象 (GameObject) 的 ID。'
];

// --- Define options for specific fields ---
$quest_type_options = [
    0 => '0: 启用 (自动完成)',
    1 => '1: 禁用',
    2 => '2: 启用 (正常)'
];

// Define options for Flags field (Checkbox group) - Updated List
$quest_flags_options = [
    0 => ['name' => 'QUEST_FLAGS_NONE', 'label' => '无标志', 'desc' => '无任何标志，任务未分配至任何分组。注意：选择此项会覆盖其他标志。'], // Adding a note for value 0
    1 => ['name' => 'QUEST_FLAGS_STAY_ALIVE', 'label' => '存活要求', 'desc' => '若玩家死亡，任务将失败。'],
    2 => ['name' => 'QUEST_FLAGS_PARTY_ACCEPT', 'label' => '队伍接受', 'desc' => '护送任务或其他事件驱动的任务。若玩家在队伍中，所有可接受此任务的玩家将收到确认窗口以接受任务。'],
    4 => ['name' => 'QUEST_FLAGS_EXPLORATION', 'label' => '探索任务', 'desc' => '涉及触发区域触发器（areatrigger）。'],
    8 => ['name' => 'QUEST_FLAGS_SHARABLE', 'label' => '可分享', 'desc' => '允许任务与其他玩家分享。'],
    16 => ['name' => 'QUEST_FLAGS_HAS_CONDITION', 'label' => '有条件', 'desc' => '目前未使用。'],
    32 => ['name' => 'QUEST_FLAGS_HIDE_REWARD_POI', 'label' => '隐藏奖励点', 'desc' => '目前未使用：内容不明。'],
    64 => ['name' => 'QUEST_FLAGS_RAID', 'label' => '团队任务', 'desc' => '可在团队中完成。'],
    128 => ['name' => 'QUEST_FLAGS_TBC', 'label' => '燃烧的远征限定', 'desc' => '目前未使用：仅在启用燃烧的远征扩展时可用。'],
    256 => ['name' => 'QUEST_FLAGS_NO_MONEY_FROM_XP', 'label' => '无经验转金', 'desc' => '目前未使用：满级时经验不会转换为金币。'],
    512 => ['name' => 'QUEST_FLAGS_HIDDEN_REWARDS', 'label' => '隐藏奖励', 'desc' => '任务详情页和任务日志中隐藏物品及金钱奖励，直到奖励发放时显示。'],
    1024 => ['name' => 'QUEST_FLAGS_TRACKING', 'label' => '追踪任务', 'desc' => '任务完成后自动奖励，客户端任务日志中不会显示。'],
    2048 => ['name' => 'QUEST_FLAGS_DEPRECATE_REPUTATION', 'label' => '废弃声望', 'desc' => '目前未使用。'],
    4096 => ['name' => 'QUEST_FLAGS_DAILY', 'label' => '日常任务', 'desc' => '可重复的日常任务（核心为此标志应用特定行为）。'],
    8192 => ['name' => 'QUEST_FLAGS_FLAGS_PVP', 'label' => '强制PVP', 'desc' => '任务在日志中时强制开启PVP状态。'],
    16384 => ['name' => 'QUEST_FLAGS_UNAVAILABLE', 'label' => '不可用', 'desc' => '用于通常不可用的任务。'],
    32768 => ['name' => 'QUEST_FLAGS_WEEKLY', 'label' => '每周任务', 'desc' => '可重复的每周任务（核心为此标志应用特定行为）。'],
    65536 => ['name' => 'QUEST_FLAGS_AUTOCOMPLETE', 'label' => '自动完成', 'desc' => '任务自动完成。'],
    131072 => ['name' => 'QUEST_FLAGS_DISPLAY_ITEM_IN_TRACKER', 'label' => '追踪器显示物品', 'desc' => '在任务追踪器中显示可使用物品。'],
    262144 => ['name' => 'QUEST_FLAGS_OBJ_TEXT', 'label' => '目标文本即完成文本', 'desc' => '使用目标文本作为完成文本。'],
    524288 => ['name' => 'QUEST_FLAGS_AUTO_ACCEPT', 'label' => '自动接受', 'desc' => '客户端识别为自动接受任务。但当前版本（3.3.5a）无任务使用此标志，可能暴雪曾使用或未来会使用。'],
    1048576 => ['name' => 'QUEST_FLAGS_PLAYER_CAST_ON_ACCEPT', 'label' => '接受时玩家施法', 'desc' => '带有此标志的任务，玩家可通过界面特殊按钮自动提交。'],
    2097152 => ['name' => 'QUEST_FLAGS_PLAYER_CAST_ON_COMPLETE', 'label' => '完成时自动建议', 'desc' => '自动建议接受任务，非NPC触发。'],
    4194304 => ['name' => 'QUEST_FLAGS_UPDATE_PHASE_SHIFT', 'label' => '更新位面切换', 'desc' => '（未具体说明，可能与位面切换相关）。'],
    8388608 => ['name' => 'QUEST_FLAGS_SOR_WHITELIST', 'label' => 'SOR白名单', 'desc' => '（未具体说明，可能与特定系统相关）。'],
    16777216 => ['name' => 'QUEST_FLAGS_LAUNCH_GOSSIP_COMPLETE', 'label' => '完成时触发对话', 'desc' => '任务完成时触发对话。'],
    // Note: Value 54432 seems unusual as it's not a power of 2. Including as requested.
    54432 => ['name' => 'QUEST_FLAGS_REMOVE_EXTRA_GET_ITEMS', 'label' => '移除额外获取物品', 'desc' => '（未具体说明，可能移除某些任务物品的额外获取）。'], 
    67108864 => ['name' => 'QUEST_FLAGS_HIDE_UNTIL_DISCOVERED', 'label' => '发现前隐藏', 'desc' => '任务在被发现前隐藏。'],
    134217728 => ['name' => 'QUEST_FLAGS_PORTRAIT_IN_QUEST_LOG', 'label' => '日志中显示肖像', 'desc' => '任务日志中显示任务肖像。'],
    268435456 => ['name' => 'QUEST_FLAGS_SHOW_ITEM_WHEN_COMPLETED', 'label' => '完成时显示物品', 'desc' => '任务完成时显示相关物品。'],
    536870912 => ['name' => 'QUEST_FLAGS_LAUNCH_GOSSIP_ACCEPT', 'label' => '接受时触发对话', 'desc' => '任务接受时触发对话。'],
    // Values 1073741824 and 2147483648 might exceed 32-bit signed integer limits depending on PHP/DB setup.
    // Be cautious if your system uses 32-bit integers for this field.
    1073741824 => ['name' => 'QUEST_FLAGS_ITEMS_GLOW_WHEN_DONE', 'label' => '物品完成时发光', 'desc' => '任务完成时相关物品发光。'],
    2147483648 => ['name' => 'QUEST_FLAGS_FAIL_ON_LOGOUT', 'label' => '登出失败', 'desc' => '玩家登出时任务失败。'], // Value exceeds max signed 32-bit integer
];

// Helper function to render form fields
function render_quest_field($key, $value) {
    global $field_labels, $field_descriptions, $quest_type_options, $quest_flags_options; // Access global arrays and options
    $label = htmlspecialchars($field_labels[$key] ?? $key);
    $value_str = htmlspecialchars((string)$value);
    $input_type = 'text';
    $is_textarea = false;

    // Basic type guessing
    if (is_numeric($value)) {
        $input_type = 'number';
    }
    // Guess textareas based on field name
    if (stripos($key, 'description') !== false || stripos($key, 'log') !== false || stripos($key, 'objective') !== false) {
        $is_textarea = true;
    }

    // --- Tooltip Logic ---
    $tooltip_attributes = '';
    if (isset($field_descriptions[$key])) {
        $description = $field_descriptions[$key]; // Keep HTML for tooltip
        // Escape the key for the attribute, but allow HTML in description
        $tooltip_title = htmlspecialchars($key . ': ') . $description; 
        // Use data-bs-html="true" to allow HTML in tooltips
        // Ensure the title attribute itself is properly escaped
        $tooltip_attributes = ' data-bs-toggle="tooltip" data-bs-placement="top" data-bs-html="true" title="' . htmlspecialchars($tooltip_title, ENT_QUOTES) . '"';
    }
    // --- End Tooltip Logic ---

    $field_html = "<div class='mb-3'>"; // Wrap field in a div
    // Add tooltip attributes to the label
    $field_html .= "<label for='field_{$key}' class='form-label form-label-sm'{$tooltip_attributes}>{$label}</label>";

    // --- Specific Field Rendering ---
    if ($key === 'QuestType') {
        $field_html .= "<select class='form-select form-select-sm' id='field_{$key}' name='{$key}'>";
        foreach ($quest_type_options as $option_value => $option_label) {
            $selected = ($value !== null && (int)$value === $option_value) ? 'selected' : '';
            $field_html .= "<option value=\"{$option_value}\" {$selected}>" . htmlspecialchars($option_label) . "</option>";
        }
        $field_html .= "</select>";
    } elseif ($key === 'Flags') {
        $current_flags = ($value !== null) ? (int)$value : 0;
        // Display input (now writable) and button to trigger modal
        $field_html .= '<div class="input-group">';
        // Removed readonly attribute
        $field_html .= "<input type='number' class='form-control form-control-sm' id='field_Flags_display' value='{$current_flags}' placeholder='输入Flag值...'>";
        // Hidden input stores the actual value, synced via JS
        $field_html .= "<input type='hidden' id='field_Flags_hidden' name='Flags' value='{$current_flags}'>";
        // Removed data-bs-* attributes, added id="openFlagsModalBtn"
        $field_html .= '<button class="btn btn-outline-secondary btn-sm" type="button" id="openFlagsModalBtn"><i class="fas fa-list-check"></i> 选择</button>'; 
        $field_html .= '</div>';
    } elseif ($is_textarea) {
        $field_html .= "<textarea class='form-control form-control-sm' id='field_{$key}' name='{$key}' rows='3'>{$value_str}</textarea>";
    } else {
        $readonly = ($key === 'ID') ? 'readonly' : '';
        $field_html .= "<input type='{$input_type}' class='form-control form-control-sm' id='field_{$key}' name='{$key}' value='{$value_str}' {$readonly}>";
    }
    // --- End Specific Field Rendering ---

    $field_html .= "</div>";
    return $field_html;
}

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>编辑任务 - <?= htmlspecialchars((string)($quest_data['LogTitle'] ?? $quest_id ?? '错误')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
         :root {
             --primary-color: #6a4cff;
             --primary-hover: #5a3df7;
             --secondary-color: #ff7e5f;
             --accent-color: #ffb56b;
             --dark-bg: #1a1a2e;
             --card-bg: #16213e;
             --card-hover: #1f2b4d;
             --text-primary: #e6e6ff;
             --text-secondary: #b8b8d4;
             --text-muted: #8888a8;
             --border-color: #2a3a5a;
             --success-color: #4caf50;
             --warning-color: #ff9800;
             --danger-color: #f44336;
             --info-color: #2196f3;
         }
         
          body {
             font-family: 'Poppins', sans-serif;
             background-color: var(--dark-bg);
             color: var(--text-primary);
             line-height: 1.6;
         }
         
         .page-header {
             background: linear-gradient(135deg, var(--card-bg), var(--dark-bg));
             padding: 20px 0;
             margin-bottom: 30px;
             border-bottom: 1px solid var(--border-color);
             position: relative;
         }
         
         .page-header h1 {
             font-weight: 700;
             color: var(--accent-color);
             margin: 0;
             text-align: center; 
         }
                  
         .page-header .subtitle {
             color: var(--text-secondary);
             text-align: center;
             margin-top: 5px;
             font-size: 0.95rem;
         }
         
         .form-section {
             background-color: var(--card-bg);
             border: 1px solid var(--border-color);
             border-radius: 12px;
             box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
             margin-bottom: 25px;
             padding: 20px;
         }

         .form-section h5 {
             color: var(--accent-color);
             border-bottom: 1px solid var(--border-color);
             padding-bottom: 10px;
             margin-bottom: 20px;
             font-weight: 600;
         }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem 1.5rem; /* row-gap column-gap */
        }
        
        .form-control, .form-select {
             background-color: rgba(255, 255, 255, 0.05);
             border: 1px solid rgba(255, 255, 255, 0.1);
             color: var(--text-primary);
             border-radius: 8px;
        }
         
         .form-control:focus, .form-select:focus {
             background-color: rgba(255, 255, 255, 0.08);
             border-color: var(--primary-color);
             box-shadow: 0 0 0 0.25rem rgba(106, 76, 255, 0.25);
             color: var(--text-primary);
         }

         .form-control[readonly] {
              background-color: rgba(255, 255, 255, 0.02);
              cursor: not-allowed;
         }
         
         .form-label {
             color: var(--text-secondary);
             font-weight: 500;
             margin-bottom: 0.5rem;
         }
         
         .form-actions {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end; /* Align buttons to the right */
            gap: 10px;
         }
         
         .btn {
             border-radius: 8px;
             padding: 10px 20px; /* Slightly larger padding */
             font-weight: 500;
             transition: all 0.3s ease;
         }
         
         .btn-primary {
             background-color: var(--primary-color);
             border-color: var(--primary-color);
         }
         
         .btn-primary:hover {
             background-color: var(--primary-hover);
             border-color: var(--primary-hover);
         }
         
         .btn-secondary {
             background-color: #2d3748;
             border-color: #2d3748;
         }
         
         .btn-secondary:hover {
             background-color: #3a4a5e;
             border-color: #3a4a5e;
         }

         /* Styles for the sticky SQL preview header */
         #sql-preview-section {
             position: sticky;
             top: 0; /* Stick to the top */
             z-index: 1020; /* Ensure it's above other content */
             background-color: rgba(26, 26, 46, 0.95); /* Slightly transparent dark background */
             border-bottom: 1px solid var(--primary-color);
             backdrop-filter: blur(5px);
         }
         #sql-display {
             background-color: rgba(0,0,0,0.3);
             color: #e0e0ff;
             border: 1px solid var(--border-color);
             border-radius: 6px;
             padding: 10px 15px;
             font-family: Consolas, Monaco, 'Andale Mono', 'Ubuntu Mono', monospace;
             font-size: 0.9em;
             white-space: pre-wrap; /* Allow wrapping */
             word-break: break-all; /* Break long words/lines */
             max-height: 150px; /* Limit height and make scrollable */
             overflow-y: auto;
         }
         .sql-controls label {
              margin-right: 15px;
              color: var(--text-secondary);
              cursor: pointer;
         }
         .sql-controls .form-check-input {
            background-color: rgba(255,255,255,0.1);
            border-color: rgba(255,255,255,0.2);
         }
        .sql-controls .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
         .sql-buttons .btn {
              margin-left: 5px;
              padding: 6px 12px; /* Smaller padding for header buttons */
         }

         .form-select {
             background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23e6e6ff' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
         }

         /* Improve dropdown option visibility on dark themes */
         select.form-select option {
            background-color: #fff; /* Light background for options */
            color: #212529;       /* Dark text for options */
         }

    </style>
</head>
<body>

    <div class="page-header fade-in">
         <div class="container-fluid d-flex align-items-center justify-content-between">
             <!-- Left Button -->
             <div>
                 <a href="<?= htmlspecialchars($return_url_params) ?>" class="btn btn-outline-secondary"> <!-- Changed style for back button -->
                     <i class="fas fa-arrow-left me-1"></i> 返回列表
                 </a>
             </div>

             <!-- Center Title/Subtitle Group -->
             <div class="text-center">
                  <h1><i class="fas fa-edit me-2"></i> 编辑任务</h1>
                  <p class="subtitle mb-0">
                    <?php if ($quest_data): ?>
                        ID: <?= htmlspecialchars((string)$quest_data['ID']) ?> - <?= htmlspecialchars($quest_data['LogTitle'] ?? '未知标题') ?>
                        <a href="https://db.nfuwow.com/80/?quest=<?= $quest_id ?>" target="_blank" class="ms-2 small text-info" title="在数据库中查看原版">
                             <i class="fas fa-external-link-alt"></i> 原版任务
                        </a>
                    <?php else: ?>
                        加载任务...
                    <?php endif; ?>
                  </p>
             </div>

             <!-- Right Placeholder (for potential future buttons) -->
             <div style="min-width: 120px;"> <!-- Adjust min-width as needed -->
                &nbsp; 
             </div>
         </div>
     </div>

    <div class="container-fluid px-4 fade-in" style="animation-delay: 0.1s;">

        <?php 
            // --- Generate Initial SQL (Full query based on loaded data) ---
            $initial_full_sql = '-- No data loaded --';
            $initial_diff_sql = '-- No changes detected (initial load) --';
            
            // Helper function (simplified version for now, needs refinement for proper quoting/types)
            function generate_quest_update_sql(int $quest_id, array $data, array $field_groups, bool $only_diff = false, ?array $original_data = null): string {
                 $set_clauses = [];
                 $all_fields = [];
                 foreach($field_groups as $group) {
                    $all_fields = array_merge($all_fields, $group);
                 }
                 $valid_columns = array_flip($all_fields); // Use defined fields as valid columns

                 foreach ($data as $key => $value) {
                     if ($key === 'quest_id' || $key === 'save_quest' || !isset($valid_columns[$key]) || $key === 'ID') {
                         continue; // Skip non-db fields, ID
                     }

                     $original_value = $original_data ? ($original_data[$key] ?? null) : null;

                     // Basic difference check (needs improvement for type juggling)
                     $is_different = $original_data === null || ($original_value !== $value);

                     if (!$only_diff || $is_different) {
                         $quoted_value = 'NULL'; // Default to NULL
                         if ($value !== null) {
                            if (is_numeric($value)) {
                                $quoted_value = $value;
                            } else {
                                // Basic quoting, needs PDO prepare for safety
                                $quoted_value = "'" . addslashes((string)$value) . "'"; 
                            }
                         }
                         $set_clauses[] = "`" . $key . "` = " . $quoted_value;
                     }
                 }

                 if (empty($set_clauses)) {
                     return ($only_diff && $original_data !== null) ? '-- No changes detected --' : '-- No fields to update --';
                 }

                 return "UPDATE `quest_template` SET " . implode(', ', $set_clauses) . " WHERE (`ID` = " . $quest_id . ");";
            }

            if ($quest_data) {
                $initial_full_sql = generate_quest_update_sql($quest_id, $quest_data, $field_groups, false);
                // Diff SQL needs comparison, so it starts empty on initial load
            }
        ?>

        <!-- SQL PREVIEW SECTION (Sticky) -->
            <section id="sql-preview-section" class="mb-4 p-3 rounded shadow-sm sticky-top">
                <div class="row align-items-center g-2">
                    <div class="col-md-6 sql-controls">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="sqlPreviewType" id="diffQueryRadio" value="diff" checked>
                            <label class="form-check-label" for="diffQueryRadio">差异查询</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="sqlPreviewType" id="fullQueryRadio" value="full">
                            <label class="form-check-label" for="fullQueryRadio">完整查询</label>
                        </div>
                        <a href="https://www.azerothcore.org/wiki/quest_template" target="_blank" class="ms-3 small text-info">
                           <i class="fas fa-external-link-alt"></i> quest_template 文档
                        </a>
                    </div>
                     <div class="col-md-6 text-end sql-buttons">
                         <button id="copySqlBtn" class="btn btn-secondary btn-sm"><i class="far fa-copy"></i> 复制</button>
                         <button id="executeSqlBtn" class="btn btn-primary btn-sm"><i class="fas fa-bolt"></i> 执行</button>
                         <a href="edit_quest.php?id=<?= $quest_id ?>&return_params=<?= urlencode($_GET['return_params'] ?? '') ?>" id="reloadQuestBtn" class="btn btn-info btn-sm"><i class="fas fa-sync-alt"></i> 重新加载</a>
                         <a href="<?= htmlspecialchars($return_url_params) ?>" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i> 关闭</a>
                     </div>
                </div>
                <div class="mt-2">
                    <pre id="sql-display" class="text-light"><?= htmlspecialchars($initial_diff_sql) // Default to showing diff ?></pre>
                    <textarea id="diff-sql-data" style="display:none;"><?= htmlspecialchars($initial_diff_sql) ?></textarea>
                    <textarea id="full-sql-data" style="display:none;"><?= htmlspecialchars($initial_full_sql) ?></textarea>
                </div>
            </section>
            <!-- End SQL PREVIEW SECTION -->

        <?php if ($error_message): ?>
            <div class="alert alert-danger mb-4">
                 <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error_message); ?>
            </div>
        <?php elseif ($quest_data): ?>
            <form method="POST" action=""> <!-- POST to self for now -->
                <input type="hidden" name="quest_id" value="<?= htmlspecialchars((string)$quest_data['ID']) ?>">

                <?php foreach ($field_groups as $group_name => $fields_in_group): ?>
                    <section class="form-section">
                        <h5><?= htmlspecialchars($group_name) ?></h5>
                        <div class="form-grid">
                            <?php foreach ($fields_in_group as $field_key): ?>
                                <?php 
                                    $field_value = array_key_exists($field_key, $quest_data) ? $quest_data[$field_key] : null; 
                                ?>
                                <?php if ($field_key !== 'ID'): // Hide ID field ?>
                                    <?= render_quest_field($field_key, $field_value) ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php // --- Display Starter/Ender Lists in Tables --- ?>
                        <?php if ($group_name === '任务开始结束NPC'): ?>
                            <?php /* <hr class="my-3 border-secondary"> REMOVED */ ?>
                            <?php /* <h6><i class="fas fa-users me-1"></i> 实际关联NPC列表 <small class="text-muted">(来自关联表)</small></h6> REMOVED */ ?>
                            <div id="npc-assoc-section"> <?php // Wrapper div ?>
                                <?php if (empty($creature_starters) && empty($creature_enders)): ?>
                                    <p class="text-white-50 mt-2" id="no-npc-records">无关联NPC记录。</p> <?php // Changed class, added ID ?>
                                    <?php // Always output table structure for JS to target ?>
                                    <table id="quest-assoc-npc-table" class="table table-sm table-dark table-bordered table-hover mt-2 d-none" style="font-size: 0.9em;"> <?php // Added ID, hidden initially if empty ?>
                                        <thead>
                                            <tr>
                                                <th style="width: 30%;">类型</th>
                                                <th>NPC ID</th>
                                                <th style="width: 150px;" class="text-center">操作</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php // Rows will be added by PHP if data exists, or by JS if added later ?>
                                        </tbody>
                                    </table>
                                <?php else: ?>
                                    <?php // Data exists, show table normally ?>
                                    <table id="quest-assoc-npc-table" class="table table-sm table-dark table-bordered table-hover mt-2" style="font-size: 0.9em;"> <?php // Added ID ?>
                                    <thead>
                                        <tr>
                                            <th style="width: 30%;">类型</th>
                                            <th>NPC ID</th>
                                            <th style="width: 150px;" class="text-center">操作</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($creature_starters as $npc_id): ?>
                                            <tr>
                                                <td><span class="badge bg-info">起始NPC</span></td>
                                                <td><?= htmlspecialchars((string)$npc_id) ?></td>
                                                <td class="text-center">
                                                     <button type="button" class="btn btn-outline-warning btn-sm me-1 edit-assoc-btn" data-id="<?= $npc_id ?>" data-quest-id="<?= $quest_id ?>" data-type="creature_starter" title="编辑"><i class="fas fa-pencil-alt"></i></button>
                                                     <button type="button" class="btn btn-outline-danger btn-sm delete-assoc-btn" data-id="<?= $npc_id ?>" data-quest-id="<?= $quest_id ?>" data-type="creature_starter" title="删除"><i class="fas fa-trash-alt"></i></button>
                                                 </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php foreach($creature_enders as $npc_id): ?>
                                            <tr>
                                                <td><span class="badge bg-success">结束NPC</span></td>
                                                <td><?= htmlspecialchars((string)$npc_id) ?></td>
                                                 <td class="text-center">
                                                      <button type="button" class="btn btn-outline-warning btn-sm me-1 edit-assoc-btn" data-id="<?= $npc_id ?>" data-quest-id="<?= $quest_id ?>" data-type="creature_ender" title="编辑"><i class="fas fa-pencil-alt"></i></button>
                                                      <button type="button" class="btn btn-outline-danger btn-sm delete-assoc-btn" data-id="<?= $npc_id ?>" data-quest-id="<?= $quest_id ?>" data-type="creature_ender" title="删除"><i class="fas fa-trash-alt"></i></button>
                                                 </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                            <?php // Moved Add button outside the if/else block ?>
                            <div class="text-end mt-2">
                                <button type="button" class="btn btn-success btn-sm add-assoc-btn" data-quest-id="<?= $quest_id ?>" data-assoc-type="creature"><i class="fas fa-plus me-1"></i> 新增NPC关联</button>
                            </div>
                        <?php elseif ($group_name === '任务开始结束物体'): ?>
                             <?php /* <hr class="my-3 border-secondary"> REMOVED */ ?>
                             <?php /* <h6><i class="fas fa-cube me-1"></i> 实际关联物体列表 <small class="text-muted">(来自关联表)</small></h6> REMOVED */ ?>
                             <div id="go-assoc-section"> <?php // Wrapper div ?>
                                 <?php if (empty($gameobject_starters) && empty($gameobject_enders)): ?>
                                     <p class="text-white-50 mt-2" id="no-go-records">无关联物体记录。</p> <?php // Changed class, added ID ?>
                                     <?php // Always output table structure for JS to target ?>
                                     <table id="quest-assoc-go-table" class="table table-sm table-dark table-bordered table-hover mt-2 d-none" style="font-size: 0.9em;"> <?php // Added ID and mt-2, hidden initially if empty ?>
                                         <thead>
                                             <tr>
                                                 <th style="width: 30%;">类型</th>
                                                 <th>物体 ID</th>
                                                 <th style="width: 150px;" class="text-center">操作</th>
                                             </tr>
                                         </thead>
                                         <tbody>
                                             <?php // Rows will be added by PHP if data exists, or by JS if added later ?>
                                         </tbody>
                                     </table>
                                 <?php else: ?>
                                     <?php // Data exists, show table normally ?>
                                     <table id="quest-assoc-go-table" class="table table-sm table-dark table-bordered table-hover mt-2" style="font-size: 0.9em;"> <?php // Added ID and mt-2 ?>
                                     <thead>
                                         <tr>
                                             <th style="width: 30%;">类型</th>
                                             <th>物体 ID</th>
                                             <th style="width: 150px;" class="text-center">操作</th>
                                         </tr>
                                     </thead>
                                     <tbody>
                                         <?php foreach($gameobject_starters as $go_id): ?>
                                             <tr>
                                                 <td><span class="badge bg-info">起始物体</span></td>
                                                 <td><?= htmlspecialchars((string)$go_id) ?></td>
                                                 <td class="text-center">
                                                     <button type="button" class="btn btn-outline-warning btn-sm me-1 edit-assoc-btn" data-id="<?= $go_id ?>" data-quest-id="<?= $quest_id ?>" data-type="gameobject_starter" title="编辑"><i class="fas fa-pencil-alt"></i></button>
                                                     <button type="button" class="btn btn-outline-danger btn-sm delete-assoc-btn" data-id="<?= $go_id ?>" data-quest-id="<?= $quest_id ?>" data-type="gameobject_starter" title="删除"><i class="fas fa-trash-alt"></i></button>
                                                 </td>
                                             </tr>
                                         <?php endforeach; ?>
                                         <?php foreach($gameobject_enders as $go_id): ?>
                                             <tr>
                                                 <td><span class="badge bg-success">结束物体</span></td>
                                                 <td><?= htmlspecialchars((string)$go_id) ?></td>
                                                 <td class="text-center">
                                                     <button type="button" class="btn btn-outline-warning btn-sm me-1 edit-assoc-btn" data-id="<?= $go_id ?>" data-quest-id="<?= $quest_id ?>" data-type="gameobject_ender" title="编辑"><i class="fas fa-pencil-alt"></i></button>
                                                     <button type="button" class="btn btn-outline-danger btn-sm delete-assoc-btn" data-id="<?= $go_id ?>" data-quest-id="<?= $quest_id ?>" data-type="gameobject_ender" title="删除"><i class="fas fa-trash-alt"></i></button>
                                                 </td>
                                             </tr>
                                         <?php endforeach; ?>
                                     </tbody>
                                 </table>
                             <?php endif; ?>
                             <?php // Moved Add button outside the if/else block ?>
                             <div class="text-end mt-2">
                                 <button type="button" class="btn btn-success btn-sm add-assoc-btn" data-quest-id="<?= $quest_id ?>" data-assoc-type="gameobject"><i class="fas fa-plus me-1"></i> 新增物体关联</button>
                             </div>
                        <?php endif; ?>
                        <?php // --- End Display Starter/Ender Lists --- ?>

                    </section>
                <?php endforeach; ?>

            </form>
        <?php endif; ?>

    </div>

    <!-- End Flags Selection Modal -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <?php if ($quest_data): // Only add JS if data was loaded ?>
    <script>
        // --- Main Quest Data and Form Elements ---
        const originalQuestData = <?= json_encode($quest_data); ?>;
        const questIdForSql = <?= json_encode($quest_id); ?>;
        const questEditForm = document.querySelector('form[method="POST"]'); // Get the main form
        const sqlDisplay = document.getElementById('sql-display');
        const diffSqlData = document.getElementById('diff-sql-data');
        const fullSqlData = document.getElementById('full-sql-data');
        const diffQueryRadio = document.getElementById('diffQueryRadio');
        const fullQueryRadio = document.getElementById('fullQueryRadio');
        const copySqlBtn = document.getElementById('copySqlBtn');
        const executeSqlBtn = document.getElementById('executeSqlBtn'); 
        const reloadQuestBtn = document.getElementById('reloadQuestBtn'); // Assuming ID for reload button

        // --- SQL Preview Update Function ---
        function updateSqlPreview() {
            if (!sqlDisplay || !diffSqlData || !fullSqlData) return;
            if (diffQueryRadio && diffQueryRadio.checked) {
                sqlDisplay.textContent = diffSqlData.value;
            } else if (fullQueryRadio && fullQueryRadio.checked) {
                sqlDisplay.textContent = fullSqlData.value;
            }
        }

        // --- Copy SQL Functionality ---
        if (copySqlBtn) {
            copySqlBtn.addEventListener('click', () => {
                const sqlToCopy = sqlDisplay.textContent;
                if (!sqlToCopy || sqlToCopy.trim() === '' || sqlToCopy.startsWith('--')) {
                    alert("没有可复制的有效 SQL 查询。");
                    return;
                }
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(sqlToCopy).then(() => {
                        const originalText = copySqlBtn.innerHTML;
                        copySqlBtn.innerHTML = '<i class="fas fa-check"></i> 已复制!';
                        copySqlBtn.disabled = true;
                        setTimeout(() => { 
                            copySqlBtn.innerHTML = originalText; 
                            copySqlBtn.disabled = false;
                        }, 1500);
                    }).catch(err => {
                        console.error('Clipboard API copy failed: ', err); 
                        alert('复制 SQL 失败！(API)');
                    });
                } else {
                    const textArea = document.createElement('textarea');
                    textArea.value = sqlToCopy;
                    textArea.style.position = 'fixed'; textArea.style.top = '-9999px'; textArea.style.left = '-9999px';
                    document.body.appendChild(textArea);
                    textArea.focus();
                    textArea.select();
                    try {
                        const successful = document.execCommand('copy');
                        if (successful) {
                            const originalText = copySqlBtn.innerHTML;
                            copySqlBtn.innerHTML = '<i class="fas fa-check"></i> 已复制!';
                            copySqlBtn.disabled = true;
                            setTimeout(() => { 
                                copySqlBtn.innerHTML = originalText; 
                                copySqlBtn.disabled = false;
                            }, 1500);
                        } else {
                             throw new Error('document.execCommand returned false.');
                        }
                    } catch (err) {
                        console.error('Fallback copy (execCommand) failed: ', err);
                        alert('复制 SQL 失败！(Fallback)');
                    } finally {
                        document.body.removeChild(textArea);
                    }
                }
            });
        }

        // --- Execute SQL (Save Changes) Functionality ---
        if (executeSqlBtn) {
            executeSqlBtn.addEventListener('click', async () => {
                const changedData = generateDiffDataJS(originalQuestData, questEditForm);
                if (Object.keys(changedData).length === 0) {
                    alert('没有检测到任何更改。');
                    return;
                }
                executeSqlBtn.disabled = true;
                executeSqlBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 处理中...';
                try {
                    const formData = new FormData();
                    formData.append('action', 'save_quest_ajax');
                    formData.append('quest_id', questIdForSql);
                    formData.append('changes', JSON.stringify(changedData)); 
                    const response = await fetch('edit_quest.php', { method: 'POST', body: formData });
                    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                    const result = await response.json();
                    alert(result.message);
                    if (result.success) {
                        // Update original data so diff resets
                        Object.assign(originalQuestData, changedData);
                        // Regenerate diff SQL
                        handleFormInputChange(); 
                    }
                } catch (error) {
                    console.error('Error executing save:', error);
                    alert(`保存时发生客户端错误: ${error.message}`);
                } finally {
                     executeSqlBtn.disabled = false;
                     executeSqlBtn.innerHTML = '<i class="fas fa-bolt"></i> 执行';
                }
            });
        }
        
        // --- Helper: Generate Diff Data Object ---
        function generateDiffDataJS(originalData, formElement) {
            const changedFields = {};
            const formData = new FormData(formElement);
            for (const key in originalData) {
                if (key === 'ID') continue;
                const formValue = formData.get(key);
                let originalValue = originalData[key];
                let formValueStr = formValue === null ? '' : String(formValue);
                let originalValueStr = originalValue === null ? '' : String(originalValue);
                let isDifferent = false;
                if (originalValue === null && formValueStr !== '') {
                    isDifferent = true;
                } else if (originalValue !== null && originalValueStr !== formValueStr) {
                     // Avoid marking 0/0.0/etc and empty string as different if original was numeric-like 0
                     if (!(formValueStr === '' && (originalValue == 0 || originalValue === '0') && is_numeric_php_like(originalValueStr))) {
                         isDifferent = true;
                     }
                }
                if (isDifferent) {
                    changedFields[key] = formValue; 
                }
            }
            return changedFields;
        }

        // --- Helper: Generate Diff SQL (for display only) ---
        function generateDiffSqlJS(originalData, formElement) {
             const changedData = generateDiffDataJS(originalData, formElement);
             let setClauses = [];
             const questId = originalData['ID'];
             for (const key in changedData) {
                 let value = changedData[key];
                 let originalValueForTypeCheck = originalData[key];
                 let originalValueStrForTypeCheck = originalValueForTypeCheck === null ? '' : String(originalValueForTypeCheck);
                 let formValueStr = value === null ? '' : String(value);
                 let quotedValue = 'NULL';
                 if (value !== null && formValueStr !== '') {
                     // Check if it looks like a number (integer or float)
                     if (!isNaN(value) && value.toString().trim() !== '') {
                         quotedValue = value; // Keep as number
                     } else {
                         // Escape single quotes and backslashes for SQL string
                         quotedValue = "'" + String(value).replace(/\\/g, '\\\\').replace(/'/g, "\\'") + "'";
                     }
                 } else if (originalValueForTypeCheck !== null) { 
                      // If original was numeric-like and new value is empty, set to 0
                      if (is_numeric_php_like(originalValueStrForTypeCheck)) { 
                          quotedValue = 0;
                      } else {
                          quotedValue = "''"; // Otherwise set to empty string
                      } 
                 }
                 setClauses.push("`" + key + "` = " + quotedValue);
             }
             if (setClauses.length === 0) return '-- No changes detected --';
             // Ensure backticks around table and column names
             return `UPDATE \`quest_template\` SET ${setClauses.join(', ')} WHERE (\`ID\` = ${questId});`;
        }
        
        // Helper: Mimic PHP's is_numeric
        function is_numeric_php_like(val) {
             if (typeof val === 'number') return true;
             if (typeof val !== 'string') return false;
             // Trim whitespace and check if it's a valid number representation
             const trimmedVal = val.trim();
             if (trimmedVal === '') return false; // Empty string is not numeric
             return !isNaN(trimmedVal) && isFinite(trimmedVal); // Check for finite numbers (excludes Infinity)
        }

        // --- Function to handle form changes and update diff SQL --- 
        function handleFormInputChange() {
             if (!questEditForm || typeof originalQuestData === 'undefined' || !diffSqlData) return;
             const newDiffSql = generateDiffSqlJS(originalQuestData, questEditForm);
             diffSqlData.value = newDiffSql;
             updateSqlPreview(); // Refresh the visible PRE tag
        }

        // Wrap DOM-dependent initialization in DOMContentLoaded
        document.addEventListener('DOMContentLoaded', () => {

            // --- Flags Modal Helper Functions (Defined inside DOMContentLoaded) ---
            function calculateModalFlagsTotal_internal() {
                let total = 0;
                // Access variables defined within this scope
                if (modalCheckboxesContainerInstance) {
                    const checkboxes = modalCheckboxesContainerInstance.querySelectorAll('.modal-flag-checkbox');
                    checkboxes.forEach(checkbox => {
                        if (checkbox.checked) {
                            // Use bitwise OR to combine flags
                            total |= parseInt(checkbox.dataset.flagValue || '0', 10);
                        }
                    });
                }
                // Correct for potential 32-bit signed integer overflow during bitwise operations
                if (total < 0) {
                    total += 4294967296; // Add 2^32 to get the correct unsigned value
                }
                return total;
            }

            function syncModalCheckboxes_internal(value) {
                const flagsValue = parseInt(value || '0', 10);
                 // Access variables defined within this scope
                if (modalCheckboxesContainerInstance) {
                    const checkboxes = modalCheckboxesContainerInstance.querySelectorAll('.modal-flag-checkbox');
                    checkboxes.forEach(checkbox => {
                        const flagValue = parseInt(checkbox.dataset.flagValue || '0', 10);
                        if (flagValue === 0) {
                            checkbox.checked = (flagsValue === 0);
                        } else {
                            checkbox.checked = (flagsValue !== 0 && (flagsValue & flagValue) === flagValue);
                        }
                    });
                }
            }
            // --- End Helper Functions ---

            // Get elements after DOM is ready
            const questEditFormInstance = document.querySelector('form[method="POST"]');
            const diffQueryRadioInstance = document.getElementById('diffQueryRadio');
            const fullQueryRadioInstance = document.getElementById('fullQueryRadio');

            // --- Attach Listeners for Form Changes and SQL Preview ---
            if (questEditFormInstance) {
                questEditFormInstance.addEventListener('input', handleFormInputChange); // Assuming handleFormInputChange is defined globally or earlier
                questEditFormInstance.addEventListener('change', handleFormInputChange); // Assuming handleFormInputChange is defined globally or earlier
            }
            if (diffQueryRadioInstance) diffQueryRadioInstance.addEventListener('change', updateSqlPreview); // Assuming updateSqlPreview is defined globally or earlier
            if (fullQueryRadioInstance) fullQueryRadioInstance.addEventListener('change', updateSqlPreview); // Assuming updateSqlPreview is defined globally or earlier

            // --- Initialize Tooltips for Static Elements ---
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]:not(.modal [data-bs-toggle="tooltip"])'))
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl, { html: true, sanitize: false });
            });

            // --- Flags Modal Initialization and Listeners ---
            const flagsModalElementInstance = document.getElementById('flagsModal');
            const openFlagsModalButton = document.getElementById('openFlagsModalBtn');
            const hiddenFlagsInputInstance = document.getElementById('field_Flags_hidden');
            const displayFlagsInputInstance = document.getElementById('field_Flags_display');
            const modalCheckboxesContainerInstance = document.getElementById('modal-flags-checkbox-container'); // Define here
            const confirmFlagsBtnInstance = document.getElementById('confirmFlagsSelection');
            const currentTotalSpanInstance = document.getElementById('modal-flags-current-total');

            if (flagsModalElementInstance && openFlagsModalButton && hiddenFlagsInputInstance &&
                modalCheckboxesContainerInstance && currentTotalSpanInstance && displayFlagsInputInstance && confirmFlagsBtnInstance) {

                const flagsModal = new bootstrap.Modal(flagsModalElementInstance);

                openFlagsModalButton.addEventListener('click', () => {
                    flagsModal.show();
                });

                flagsModalElementInstance.addEventListener('show.bs.modal', () => {
                    const initialValue = parseInt(displayFlagsInputInstance.value || '0', 10);
                    currentModalFlagsValue = initialValue;
                    currentTotalSpanInstance.textContent = initialValue;
                    syncModalCheckboxes_internal(initialValue); // Use internal function
                    /* Tooltip re-init commented out */
                });

                modalCheckboxesContainerInstance.addEventListener('change', (event) => {
                    if (event.target.classList.contains('modal-flag-checkbox')) {
                         const changedFlagValue = parseInt(event.target.dataset.flagValue || '0', 10);
                         if (changedFlagValue === 0 && event.target.checked) {
                             currentModalFlagsValue = 0;
                             const checkboxes = modalCheckboxesContainerInstance.querySelectorAll('.modal-flag-checkbox');
                             checkboxes.forEach(cb => { if(parseInt(cb.dataset.flagValue) !== 0) cb.checked = false; });
                         } else {
                             if (changedFlagValue !== 0 && event.target.checked) {
                                 const noneCheckbox = modalCheckboxesContainerInstance.querySelector('.modal-flag-checkbox[data-flag-value="0"]');
                                 if (noneCheckbox) noneCheckbox.checked = false;
                             }
                             currentModalFlagsValue = calculateModalFlagsTotal_internal(); // Use internal function
                         }
                         currentTotalSpanInstance.textContent = currentModalFlagsValue;
                         displayFlagsInputInstance.value = currentModalFlagsValue;
                         hiddenFlagsInputInstance.value = currentModalFlagsValue;
                         hiddenFlagsInputInstance.dispatchEvent(new Event('input', { bubbles: true }));
                     }
                });

                displayFlagsInputInstance.addEventListener('input', () => {
                    const newValue = parseInt(displayFlagsInputInstance.value || '0', 10);
                    const validatedValue = Math.max(0, isNaN(newValue) ? 0 : newValue);
                    if (validatedValue !== newValue) {
                        displayFlagsInputInstance.value = validatedValue;
                    }
                    hiddenFlagsInputInstance.value = validatedValue;
                    currentModalFlagsValue = validatedValue;
                    currentTotalSpanInstance.textContent = validatedValue;
                    syncModalCheckboxes_internal(validatedValue); // Use internal function
                     hiddenFlagsInputInstance.dispatchEvent(new Event('input', { bubbles: true }));
                });

                 if (confirmFlagsBtnInstance) {
                    confirmFlagsBtnInstance.addEventListener('click', () => {
                        console.log('Confirm Flags button clicked.');
                        const finalValue = calculateModalFlagsTotal_internal(); // Use internal function
                        console.log('Calculated final value on confirm:', finalValue);
                        hiddenFlagsInputInstance.value = finalValue;
                        displayFlagsInputInstance.value = finalValue;
                        try {
                            console.log('Dispatching final input event on hidden field...');
                            hiddenFlagsInputInstance.dispatchEvent(new Event('input', { bubbles: true }));
                            console.log('Final input event dispatched.');
                        } catch (e) {
                            console.error('Error dispatching final input event:', e);
                        }
                        const modalInstance = bootstrap.Modal.getInstance(flagsModalElementInstance);
                        console.log('Modal instance on confirm:', modalInstance);
                        if(modalInstance) {
                            try {
                                console.log('Attempting to hide modal on confirm...');
                                modalInstance.hide();
                                console.log('Modal hide called on confirm.');
                            } catch (e) {
                                console.error('Error hiding modal on confirm:', e);
                            }
                        } else {
                            console.error('Could not get modal instance to hide on confirm.');
                        }
                    });
                }

            } else {
                console.error("Flags Modal Error: One or more required elements not found.", {
                    modal: !!flagsModalElementInstance,
                    button: !!openFlagsModalButton,
                    hiddenInput: !!hiddenFlagsInputInstance,
                    displayInput: !!displayFlagsInputInstance,
                    container: !!modalCheckboxesContainerInstance,
                    confirmBtn: !!confirmFlagsBtnInstance,
                    totalSpan: !!currentTotalSpanInstance
                });
            } // End if flagsModalElementInstance etc.

            // --- Delete Association Button Logic ---
            document.body.addEventListener('click', async (event) => {
                const targetButton = event.target.closest('.delete-assoc-btn');
                if (!targetButton) return; // Exit if the clicked element is not a delete button or its child

                const assocId = targetButton.dataset.id;
                const questId = targetButton.dataset.questId;
                const assocType = targetButton.dataset.type;
                const typeText = assocType.includes('creature') ? 'NPC' : '物体';
                const startEndText = assocType.includes('starter') ? '起始' : '结束';

                if (!assocId || !questId || !assocType) {
                    console.error('Delete button missing required data attributes.');
                    alert('删除错误：按钮缺少必要数据。');
                    return;
                }

                if (confirm(`确定要删除这个 ${startEndText}${typeText} 关联吗？\n任务 ID: ${questId}\n关联 ID: ${assocId}`)) {
                    // Optional: Show loading state on button
                    targetButton.disabled = true;
                    targetButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

                    try {
                        const formData = new FormData();
                        formData.append('action', 'delete_association_ajax');
                        formData.append('quest_id', questId);
                        formData.append('assoc_id', assocId);
                        formData.append('assoc_type', assocType);

                        const response = await fetch('edit_quest.php', {
                            method: 'POST',
                            body: formData
                        });

                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }

                        const result = await response.json();

                        alert(result.message); // Show feedback

                        if (result.success) {
                            // Remove the table row from the DOM
                            const rowToRemove = targetButton.closest('tr');
                            if (rowToRemove) {
                                rowToRemove.remove();
                                // Optional: Check if table body is now empty and show placeholder
                            } else {
                                console.warn('Could not find table row to remove.');
                            }
                        } else {
                             // Re-enable button on failure
                             targetButton.disabled = false;
                             targetButton.innerHTML = '<i class="fas fa-trash-alt"></i>';
                        }
                    } catch (error) {
                        console.error('Error deleting association:', error);
                        alert(`删除关联时发生错误: ${error.message}`);
                        // Re-enable button on error
                        targetButton.disabled = false;
                        targetButton.innerHTML = '<i class="fas fa-trash-alt"></i>';
                    }
                } // end confirm
            });
            // --- End Delete Association Button Logic ---

        }); // End DOMContentLoaded listener

    </script>
    <?php endif; ?>
    <!-- Flags Selection Modal (Moved Outside PHP Condition) -->
    <div class="modal fade" id="flagsModal" tabindex="-1" aria-labelledby="flagsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content bg-dark text-light"> <!-- Theme matching -->
                <div class="modal-header border-secondary">
                    <h5 class="modal-title" id="flagsModalLabel"><i class="fas fa-flag me-2"></i>选择任务标志 (Flags)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">勾选需要的标志。总值将自动计算。</p>
                    <div id="modal-flags-checkbox-container">
                        <?php
                        // Generate checkboxes inside the modal body
                        // Check if $quest_flags_options is defined (it should be globally defined earlier)
                        if (isset($quest_flags_options) && is_array($quest_flags_options)) {
                            foreach ($quest_flags_options as $flag_value => $flag_info) {
                                if (!is_int($flag_value) || !is_array($flag_info) || empty($flag_info['label']) || empty($flag_info['name']) || empty($flag_info['desc'])) continue;

                                $flag_id_modal = 'modal_flag_' . $flag_value;
                                $label_text_modal = htmlspecialchars($flag_value . ': ' . $flag_info['label']);
                                $tooltip_title_modal = htmlspecialchars($flag_value . ' ('. $flag_info['name'] .'): ' . $flag_info['desc'], ENT_QUOTES);

                                echo '<div class="form-check mb-2">'; // Use mb-2 for better spacing
                                echo "<input class='form-check-input modal-flag-checkbox' type='checkbox' id='{$flag_id_modal}' data-flag-value='{$flag_value}'>";
                                echo "<label class='form-check-label' for='{$flag_id_modal}' data-bs-toggle=\"tooltip\" data-bs-placement=\"top\" data-bs-html=\"true\" title=\"{$tooltip_title_modal}\">{$label_text_modal}</label>";
                                echo '</div>';
                            }
                        } else {
                            // This part should ideally not be reached if the array is defined globally
                            echo '<p class="text-danger">错误：Flags 选项数组未定义。</p>';
                        }
                        ?>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <span class="me-auto text-muted">当前总值: <strong id="modal-flags-current-total">0</strong></span>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="button" class="btn btn-primary" id="confirmFlagsSelection">确认</button>
                </div>
            </div>
        </div>
    </div>
    <!-- End Flags Selection Modal -->

    <!-- Edit Association Modal -->
    <div class="modal fade" id="editAssocModal" tabindex="-1" aria-labelledby="editAssocModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-sm"> <!-- Smaller modal for single input -->
            <div class="modal-content bg-dark text-light"> 
                <div class="modal-header border-secondary">
                    <h5 class="modal-title" id="editAssocModalLabel"><i class="fas fa-pencil-alt me-2"></i>编辑关联 ID</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                     <div id="editAssocErrorAlert" class="alert alert-danger d-none" role="alert"></div>
                    <input type="hidden" id="editAssocOriginalId">
                    <input type="hidden" id="editAssocQuestId">
                    <input type="hidden" id="editAssocType">
                    <div class="mb-3">
                        <label for="editAssocNewIdInput" class="form-label">新的关联 ID:</label>
                        <input type="number" class="form-control form-control-sm" id="editAssocNewIdInput" required>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="button" class="btn btn-primary" id="saveAssocEditBtn">保存</button>
                </div>
            </div>
        </div>
    </div>
    <!-- End Edit Association Modal -->

    <!-- Add Association Modal -->
    <div class="modal fade" id="addAssocModal" tabindex="-1" aria-labelledby="addAssocModalLabel" aria-hidden="true">
        <div class="modal-dialog"> 
            <div class="modal-content bg-dark text-light"> 
                <div class="modal-header border-secondary">
                    <h5 class="modal-title" id="addAssocModalLabel"><i class="fas fa-plus me-2"></i>新增关联</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                     <div id="addAssocErrorAlert" class="alert alert-danger d-none" role="alert"></div>
                    <input type="hidden" id="addAssocQuestId">
                    <div class="mb-3">
                        <label for="addAssocTypeSelect" class="form-label">关联类型:</label>
                        <select class="form-select form-select-sm" id="addAssocTypeSelect" required>
                            <option value="" selected disabled>-- 请选择 --</option>
                            <optgroup label="NPC">
                                <option value="creature_starter">起始NPC</option>
                                <option value="creature_ender">结束NPC</option>
                            </optgroup>
                             <optgroup label="物体">
                                <option value="gameobject_starter">起始物体</option>
                                <option value="gameobject_ender">结束物体</option>
                            </optgroup>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="addAssocIdInput" class="form-label">NPC/物体 ID:</label>
                        <input type="number" class="form-control form-control-sm" id="addAssocIdInput" required placeholder="输入有效的ID">
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="button" class="btn btn-primary" id="saveNewAssocBtn">确认新增</button>
                </div>
            </div>
        </div>
    </div>
    <!-- End Add Association Modal -->
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <?php if ($quest_data): ?>
    <script>
        // --- Define Helper Functions First ---
        // ... (calculateModalFlagsTotal_internal, syncModalCheckboxes_internal etc.) ...

        // Wrap DOM-dependent initialization in DOMContentLoaded
        document.addEventListener('DOMContentLoaded', () => {
            // ... (Existing DOMContentLoaded code: tooltips, Flags modal setup) ...

            // --- Edit Association Modal Logic ---
            const editAssocModalElement = document.getElementById('editAssocModal');
            const editAssocModal = editAssocModalElement ? new bootstrap.Modal(editAssocModalElement) : null;
            const editAssocOriginalIdInput = document.getElementById('editAssocOriginalId');
            const editAssocQuestIdInput = document.getElementById('editAssocQuestId');
            const editAssocTypeInput = document.getElementById('editAssocType');
            const editAssocNewIdInput = document.getElementById('editAssocNewIdInput');
            const saveAssocEditBtn = document.getElementById('saveAssocEditBtn');
            const editAssocErrorAlert = document.getElementById('editAssocErrorAlert');
            let currentlyEditingRow = null; // To store the <tr> element being edited

            // Delegate click listener for edit buttons
            document.body.addEventListener('click', (event) => {
                const targetButton = event.target.closest('.edit-assoc-btn');
                if (!targetButton || !editAssocModal) return;

                // Store the row for potential update later
                currentlyEditingRow = targetButton.closest('tr');
                if (!currentlyEditingRow) {
                    console.error('Could not find parent row for edit button.');
                    return;
                }

                const assocId = targetButton.dataset.id;
                const questId = targetButton.dataset.questId;
                const assocType = targetButton.dataset.type;

                // Populate modal fields
                if(editAssocOriginalIdInput) editAssocOriginalIdInput.value = assocId;
                if(editAssocQuestIdInput) editAssocQuestIdInput.value = questId;
                if(editAssocTypeInput) editAssocTypeInput.value = assocType;
                if(editAssocNewIdInput) editAssocNewIdInput.value = assocId; // Pre-fill with current ID
                 if(editAssocErrorAlert) editAssocErrorAlert.classList.add('d-none'); // Hide error

                editAssocModal.show();
            });

            // Listener for the modal save button
            if (saveAssocEditBtn && editAssocModal) {
                saveAssocEditBtn.addEventListener('click', async () => {
                    const originalId = editAssocOriginalIdInput?.value;
                    const questId = editAssocQuestIdInput?.value;
                    const assocType = editAssocTypeInput?.value;
                    const newId = editAssocNewIdInput?.value;

                    // Basic validation
                    if (!originalId || !questId || !assocType || !newId || isNaN(parseInt(newId))) {
                         if(editAssocErrorAlert) {
                             editAssocErrorAlert.textContent = '错误：缺少必要信息或新ID无效。';
                             editAssocErrorAlert.classList.remove('d-none');
                         }
                         return;
                     }
                     if (originalId === newId) {
                          if(editAssocErrorAlert) {
                             editAssocErrorAlert.textContent = '错误：新ID不能与原始ID相同。';
                             editAssocErrorAlert.classList.remove('d-none');
                         }
                         return;
                     }
                      if(editAssocErrorAlert) editAssocErrorAlert.classList.add('d-none');

                    // Show loading state
                    saveAssocEditBtn.disabled = true;
                    saveAssocEditBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 保存中...';

                    try {
                        const formData = new FormData();
                        formData.append('action', 'edit_association_ajax');
                        formData.append('quest_id', questId);
                        formData.append('original_assoc_id', originalId);
                        formData.append('new_assoc_id', newId);
                        formData.append('assoc_type', assocType);

                        const response = await fetch('edit_quest.php', { method: 'POST', body: formData });
                        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                        const result = await response.json();

                        alert(result.message);

                        if (result.success) {
                            // Update the table row visually
                            if (currentlyEditingRow) {
                                const idCell = currentlyEditingRow.querySelector('td:nth-child(2)'); // Assuming ID is the second cell
                                if (idCell) idCell.textContent = result.new_id;
                                // Update data attributes on buttons in the row
                                const editBtn = currentlyEditingRow.querySelector('.edit-assoc-btn');
                                const deleteBtn = currentlyEditingRow.querySelector('.delete-assoc-btn');
                                if(editBtn) editBtn.dataset.id = result.new_id;
                                if(deleteBtn) deleteBtn.dataset.id = result.new_id;
                            }
                            editAssocModal.hide();
                        } else {
                            // Display specific error from backend if available
                            if(editAssocErrorAlert) {
                                editAssocErrorAlert.textContent = result.message || '保存失败，请重试。';
                                editAssocErrorAlert.classList.remove('d-none');
                            }
                        }

                    } catch (error) {
                        console.error('Error editing association:', error);
                         if(editAssocErrorAlert) {
                            editAssocErrorAlert.textContent = `客户端错误: ${error.message}`;
                            editAssocErrorAlert.classList.remove('d-none');
                        }
                    } finally {
                        saveAssocEditBtn.disabled = false;
                        saveAssocEditBtn.innerHTML = '保存';
                    }
                });
            }
            // --- End Edit Association Modal Logic ---

            // --- Add Association Modal Logic ---
            const addAssocModalElement = document.getElementById('addAssocModal');
            const addAssocModal = addAssocModalElement ? new bootstrap.Modal(addAssocModalElement) : null;
            const addAssocQuestIdInput = document.getElementById('addAssocQuestId');
            const addAssocTypeSelect = document.getElementById('addAssocTypeSelect');
            const addAssocIdInput = document.getElementById('addAssocIdInput');
            const saveNewAssocBtn = document.getElementById('saveNewAssocBtn');
            const addAssocErrorAlert = document.getElementById('addAssocErrorAlert');

            // Delegate listener for "Add" buttons below tables
            document.body.addEventListener('click', (event) => {
                const targetButton = event.target.closest('.add-assoc-btn');
                if (!targetButton || !addAssocModal) return;

                const questId = targetButton.dataset.questId;
                const baseType = targetButton.dataset.assocType; // 'creature' or 'gameobject'

                // Populate quest ID and reset form
                if (addAssocQuestIdInput) addAssocQuestIdInput.value = questId;
                if (addAssocIdInput) addAssocIdInput.value = '';
                if (addAssocErrorAlert) addAssocErrorAlert.classList.add('d-none');
                
                // Filter/disable options in the type select based on button clicked
                 if (addAssocTypeSelect) {
                    addAssocTypeSelect.value = ''; // Reset selection
                     for (let option of addAssocTypeSelect.options) {
                         if (option.value) { // Skip placeholder
                             option.disabled = !option.value.startsWith(baseType);
                         }
                     }
                 }

                addAssocModal.show();
            });
            
             // Listener for the modal save button
             if (saveNewAssocBtn && addAssocModal) {
                saveNewAssocBtn.addEventListener('click', async () => {
                    const questId = addAssocQuestIdInput?.value;
                    const assocType = addAssocTypeSelect?.value;
                    const assocId = addAssocIdInput?.value;
                    
                    // Validation
                    if (!questId || !assocType || !assocId || isNaN(parseInt(assocId))) {
                         if(addAssocErrorAlert) {
                             addAssocErrorAlert.textContent = '错误：请选择类型并输入有效的关联 ID。';
                             addAssocErrorAlert.classList.remove('d-none');
                         }
                         return;
                    }
                     if(addAssocErrorAlert) addAssocErrorAlert.classList.add('d-none');

                     // Show loading state
                     saveNewAssocBtn.disabled = true;
                     saveNewAssocBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 新增中...';
                     
                     try {
                        const formData = new FormData();
                        formData.append('action', 'add_association_ajax');
                        formData.append('quest_id', questId);
                        formData.append('assoc_id', assocId);
                        formData.append('assoc_type', assocType);

                        const response = await fetch('edit_quest.php', { method: 'POST', body: formData });
                        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                        const result = await response.json();

                        alert(result.message);
                        
                        if (result.success) {
                            // Add the new row to the correct table
                            const newRowData = result.added_data;
                            const isCreature = newRowData.type.startsWith('creature');
                            const tableId = isCreature ? 'quest-assoc-npc-table' : 'quest-assoc-go-table';
                            const noRecordsPId = isCreature ? 'no-npc-records' : 'no-go-records';
                            
                            let targetTable = document.getElementById(tableId);
                            const noRecordsP = document.getElementById(noRecordsPId);

                            // Helper function to get display text for type
                            function getAssocTypeText(type) {
                                const isCreature = type.startsWith('creature');
                                const isStarter = type.endsWith('starter');
                                const typePrefix = isCreature ? 'NPC' : '物体';
                                const startEndText = isStarter ? '起始' : '结束';
                                return `${startEndText}${typePrefix}`;
                            }
                            // Helper function to get badge class
                            function getAssocBadgeClass(type) {
                                return type.endsWith('starter') ? 'bg-info' : 'bg-success';
                            }

                            if (targetTable) {
                                // Find or ensure tbody exists
                                let targetTableBody = targetTable.querySelector('tbody');
                                if (!targetTableBody) {
                                    console.warn(`Tbody not found for ${tableId}, creating one.`);
                                    targetTableBody = document.createElement('tbody');
                                    targetTable.appendChild(targetTableBody);
                                }

                                // Create and append the new row FIRST
                                if (targetTableBody && newRowData) {
                                    const newRow = document.createElement('tr');
                                    // Populate newRow.innerHTML (using helpers defined above)
                                    newRow.innerHTML = `
                                        <td><span class="badge ${getAssocBadgeClass(newRowData.type)}">${getAssocTypeText(newRowData.type)}</span></td>
                                        <td>${newRowData.id}</td> 
                                        <td class="text-center">
                                             <button type="button" class="btn btn-outline-warning btn-sm me-1 edit-assoc-btn" data-id="${newRowData.id}" data-quest-id="${newRowData.quest}" data-type="${newRowData.type}" title="编辑"><i class="fas fa-pencil-alt"></i></button>
                                             <button type="button" class="btn btn-outline-danger btn-sm delete-assoc-btn" data-id="${newRowData.id}" data-quest-id="${newRowData.quest}" data-type="${newRowData.type}" title="删除"><i class="fas fa-trash-alt"></i></button>
                                         </td>
                                    `;
                                    targetTableBody.appendChild(newRow);

                                    // THEN, if the 'no records' message exists, remove it and show the table
                                    if (noRecordsP) {
                                        noRecordsP.remove();
                                        // $(targetTable).removeClass('d-none'); // Show the table - REPLACED with Vanilla JS
                                        targetTable.classList.remove('d-none'); // Use Vanilla JS
                                        console.log(`Removed ${noRecordsPId} and showed ${tableId}`);
                                    }
                                } else {
                                     console.error('Could not append row. Tbody found:', !!targetTableBody, 'New data:', newRowData);
                                      alert('添加关联数据时出错，无法更新表格。');
                                }
                            } else {
                                console.error('Target table not found:', tableId);
                                alert('添加成功，但刷新页面以查看更改时出错。'); 
                            }
                            
                            addAssocModal.hide();
                        } else {
                             if(addAssocErrorAlert) {
                                addAssocErrorAlert.textContent = result.message || '新增失败，请重试。';
                                addAssocErrorAlert.classList.remove('d-none');
                            }
                        }

                    } catch (error) {
                        console.error('Error adding association:', error);
                         if(addAssocErrorAlert) {
                            addAssocErrorAlert.textContent = `客户端错误: ${error.message}`;
                            addAssocErrorAlert.classList.remove('d-none');
                        }
                    } finally {
                        saveNewAssocBtn.disabled = false;
                        saveNewAssocBtn.innerHTML = '确认新增';
                    }
                 });
             }
            // --- End Add Association Modal Logic ---

        }); // End DOMContentLoaded listener
    </script>
    <?php endif; ?>

</body>
</html> 