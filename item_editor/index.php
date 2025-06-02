<?php
// item_editor/index.php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8'); // Ensure correct encoding early
session_start();

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// --- AJAX Request Handler ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) { // Check if action is set

    // --- Handle Save Item AJAX Request ---
    if ($_POST['action'] === 'save_item_ajax') {
        header('Content-Type: application/json; charset=utf-8');
        $config = require_once __DIR__ . '/../config.php';
        require_once __DIR__ . '/../bag_query/db.php';
        require_once __DIR__ . '/functions.php';

        $response = ['success' => false, 'message' => '发生未知错误。'];

        // --- 1. Check Login ---
        $adminConf = $config['admin'] ?? null;
        $isLoggedInAjax = (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true);
        if (!$isLoggedInAjax) {
            $response['message'] = '错误：未登录或会话超时。';
            echo json_encode($response);
            exit;
        }

        // --- 2. Get and Validate Input ---
        $entry_id = filter_input(INPUT_POST, 'entry', FILTER_VALIDATE_INT);
        $changes_json = $_POST['changes'] ?? null;
        $changes = $changes_json ? json_decode($changes_json, true) : null;

        if (!$entry_id || $entry_id <= 0) {
            $response['message'] = '错误：无效的物品 Entry ID。';
            echo json_encode($response);
            exit;
        }
        if (!is_array($changes)) {
            $response['message'] = '错误：提交的更改数据格式无效。';
            echo json_encode($response);
            exit;
        }

        // --- 3. Database Operation ---
        $pdo_W = null;
        try {
            $connections = connect_databases();
            if (!isset($connections['db_W'])) {
                throw new Exception("World 数据库连接未配置或失败。");
            }
            $pdo_W = $connections['db_W'];

            // --- 4. Define Valid Columns (Crucial for Security) ---
            // (Existing code for valid columns...)
            $field_groups_keys = [ /* ... existing keys ... */ ];
             $field_groups_keys = [
                 'name', 'description', 'displayid', 'Quality', 'class', 'subclass', 'SoundOverrideSubclass', 'InventoryType', 'ItemLevel', 'BuyCount', 'BuyPrice', 'SellPrice', 'maxcount', 'stackable', 'ContainerSlots', 'bonding', 'startquest', 'Material', 'sheath', 'RandomProperty', 'RandomSuffix', 'itemset', 'BagFamily', 'TotemCategory', 'duration', 'ItemLimitCategory', 'DisenchantID', 'FoodType', 'minMoneyLoot', 'maxMoneyLoot',
                 'Flags', 'FlagsExtra', 'flagsCustom',
                 'PageText', 'PageMaterial', 'LanguageID',
                 'AllowableClass', 'AllowableRace', 'RequiredLevel', 'RequiredSkill', 'RequiredSkillRank', 'requiredspell', 'requiredhonorrank', 'RequiredCityRank', 'RequiredReputationFaction', 'RequiredReputationRank', 'RequiredDisenchantSkill',
                 'Map', 'area', 'HolidayId', 'lockid',
                 'holy_res', 'fire_res', 'nature_res', 'frost_res', 'shadow_res', 'arcane_res',
                 'StatsCount', 'ScalingStatDistribution', 'ScalingStatValue', 'stat_type1', 'stat_value1', 'stat_type2', 'stat_value2', 'stat_type3', 'stat_value3', 'stat_type4', 'stat_value4', 'stat_type5', 'stat_value5', 'stat_type6', 'stat_value6', 'stat_type7', 'stat_value7', 'stat_type8', 'stat_value8', 'stat_type9', 'stat_value9', 'stat_type10', 'stat_value10',
                 'socketBonus', 'GemProperties', 'socketColor_1', 'socketContent_1', 'socketColor_2', 'socketContent_2', 'socketColor_3', 'socketContent_3',
                 'armor', 'ArmorDamageModifier', 'delay', 'ammo_type', 'RangedModRange', 'block', 'MaxDurability',
                 'dmg_min1', 'dmg_max1', 'dmg_type1', 'dmg_min2', 'dmg_max2', 'dmg_type2',
                 'spellid_1', 'spelltrigger_1', 'spellcharges_1', 'spellppmRate_1', 'spellcooldown_1', 'spellcategory_1', 'spellcategorycooldown_1',
                 'spellid_2', 'spelltrigger_2', 'spellcharges_2', 'spellppmRate_2', 'spellcooldown_2', 'spellcategory_2', 'spellcategorycooldown_2',
                 'spellid_3', 'spelltrigger_3', 'spellcharges_3', 'spellppmRate_3', 'spellcooldown_3', 'spellcategory_3', 'spellcategorycooldown_3',
                 'spellid_4', 'spelltrigger_4', 'spellcharges_4', 'spellppmRate_4', 'spellcooldown_4', 'spellcategory_4', 'spellcategorycooldown_4',
                 'spellid_5', 'spelltrigger_5', 'spellcharges_5', 'spellppmRate_5', 'spellcooldown_5', 'spellcategory_5', 'spellcategorycooldown_5',
                 'ScriptName', 'VerifiedBuild'
             ];
            $valid_columns = array_flip($field_groups_keys);

            // --- 5. Build Prepared Statement ---
            $set_clauses = [];
            $params = [':entry_id' => $entry_id];

            foreach ($changes as $key => $value) {
                if (!isset($valid_columns[$key])) {
                    continue;
                }
                // (Existing type coercion logic...)
                 $is_likely_numeric = preg_match('/^(entry|displayid|Quality|class|subclass|InventoryType|ItemLevel|BuyCount|BuyPrice|SellPrice|maxcount|stackable|ContainerSlots|bonding|startquest|Material|sheath|RandomProperty|RandomSuffix|itemset|BagFamily|TotemCategory|duration|ItemLimitCategory|DisenchantID|FoodType|minMoneyLoot|maxMoneyLoot|SoundOverrideSubclass|Flags|FlagsExtra|flagsCustom|PageText|PageMaterial|LanguageID|AllowableClass|AllowableRace|RequiredLevel|RequiredSkill|RequiredSkillRank|requiredspell|requiredhonorrank|RequiredCityRank|RequiredReputationFaction|RequiredReputationRank|RequiredDisenchantSkill|Map|area|HolidayId|lockid|holy_res|fire_res|nature_res|frost_res|shadow_res|arcane_res|StatsCount|ScalingStatDistribution|ScalingStatValue|stat_type\d+|stat_value\d+|socketBonus|GemProperties|socketColor_\d+|socketContent_\d+|armor|ArmorDamageModifier|delay|ammo_type|RangedModRange|block|MaxDurability|dmg_min\d+|dmg_max\d+|dmg_type\d+|spellid_\d+|spelltrigger_\d+|spellcharges_\d+|spellppmRate_\d+|spellcooldown_\d+|spellcategory_\d+|spellcategorycooldown_\d+|VerifiedBuild)$/i', $key);

                 if ($is_likely_numeric && $value === '') {
                     $value = 0;
                 } elseif ($value === null) {
                      $value = 0;
                 }


                $set_clauses[] = "`" . $key . "` = :" . $key;
                $params[':' . $key] = $value;
            }

            // --- 6. Execute Update ---
            if (empty($set_clauses)) {
                $response['success'] = true;
                $response['message'] = '没有检测到需要更新的字段。';
            } else {
                $sql = "UPDATE `item_template` SET " . implode(', ', $set_clauses) . " WHERE `entry` = :entry_id";
                $stmt = $pdo_W->prepare($sql);
                $execute_success = $stmt->execute($params);

                if ($execute_success) {
                    $affected_rows = $stmt->rowCount();
                    $response['success'] = true;
                    $response['message'] = "物品 (ID: {$entry_id}) 更新成功。影响行数: {$affected_rows}。";
                } else {
                     $errorInfo = $stmt->errorInfo();
                     $response['message'] = "数据库更新失败: " . ($errorInfo[2] ?? 'Unknown error');
                     error_log("AJAX Save SQL Error: " . print_r($errorInfo, true) . " SQL: " . $sql . " PARAMS: " . print_r($params, true));
                }
            }

        // --- 7. Catch Errors ---
        } catch (PDOException $e) {
            error_log("AJAX Save PDO Error: " . $e->getMessage());
            $response['message'] = "数据库错误: " . $e->getMessage();
        } catch (Exception $e) {
            error_log("AJAX Save General Error: " . $e->getMessage());
            $response['message'] = "发生一般错误: " . $e->getMessage();
        }

        // --- 8. Send Response and Exit ---
        echo json_encode($response);
        exit;
    }
    // --- NEW: Handle Create Item AJAX Request ---
    elseif ($_POST['action'] === 'create_item_ajax') {
        header('Content-Type: application/json; charset=utf-8');
        $config = require_once __DIR__ . '/../config.php';
        require_once __DIR__ . '/../bag_query/db.php';
        require_once __DIR__ . '/functions.php';

        $response = ['success' => false, 'message' => '创建物品时发生未知错误。'];

        // --- 1. Check Login ---
        $adminConf = $config['admin'] ?? null;
        $isLoggedInAjax = (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true);
        if (!$isLoggedInAjax) {
            $response['message'] = '错误：未登录或会话超时。';
            echo json_encode($response);
            exit;
        }

        // --- 2. Get and Validate Input ---
        $new_item_id = filter_input(INPUT_POST, 'new_item_id', FILTER_VALIDATE_INT);
        $copy_item_id = filter_input(INPUT_POST, 'copy_item_id', FILTER_VALIDATE_INT); // Optional

        if (!$new_item_id || $new_item_id <= 0) {
            $response['message'] = '错误：无效的新建物品 ID。';
            echo json_encode($response);
            exit;
        }
        // copy_item_id is optional, only validate if provided and not 0
        if ($copy_item_id !== false && $copy_item_id !== null && $copy_item_id <= 0) {
             $response['message'] = '错误：提供的复制物品 ID 无效。';
             echo json_encode($response);
             exit;
        }

        // --- 3. Database Operation ---
        $pdo_W = null;
        try {
            $connections = connect_databases();
            if (!isset($connections['db_W'])) {
                throw new Exception("World 数据库连接未配置或失败。");
            }
            $pdo_W = $connections['db_W'];
            $pdo_W->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Throw exceptions on error

            // --- 4. Check if New ID Already Exists ---
            $check_stmt = $pdo_W->prepare("SELECT COUNT(*) FROM item_template WHERE entry = :entry_id");
            $check_stmt->execute([':entry_id' => $new_item_id]);
            if ($check_stmt->fetchColumn() > 0) {
                $response['message'] = "错误：物品 ID {$new_item_id} 已存在。";
                echo json_encode($response);
                exit;
            }

            // --- 5. Prepare Data for Insertion ---
            $insert_sql = "";
            $insert_params = [];

            if ($copy_item_id) {
                // --- 5a. Copy from existing item ---
                $item_to_copy = get_item_template($pdo_W, $copy_item_id);
                if (!$item_to_copy) {
                     $response['message'] = "错误：找不到要复制的物品 ID {$copy_item_id}。";
                     echo json_encode($response);
                     exit;
                }

                // Prepare data for insertion, changing the entry ID
                $insert_data = $item_to_copy;
                $insert_data['entry'] = $new_item_id;

                // Build INSERT statement dynamically
                $columns = array_keys($insert_data);
                $placeholders = array_map(function($col) { return ':' . $col; }, $columns);
                $insert_sql = "INSERT INTO `item_template` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $placeholders) . ")";
                $insert_params = $insert_data; // Use the whole array as params

            } else {
                // --- 5b. Create from default template ---
                // Rely on database defaults for all columns except entry
                $insert_sql = "INSERT INTO `item_template` (`entry`) VALUES (?)";
                $insert_params = [$new_item_id]; // Only bind the entry ID
            }

            // --- 6. Execute Insert ---
            $insert_stmt = $pdo_W->prepare($insert_sql);
            $execute_success = $insert_stmt->execute($insert_params);

            if ($execute_success) {
                $response['success'] = true;
                $response['message'] = "物品 (ID: {$new_item_id}) 创建成功。";
                $response['new_id'] = $new_item_id; // Send back the new ID for redirection
            } else {
                 // This part might not be reached if PDO::ATTR_ERRMODE is set to Exception
                 $errorInfo = $insert_stmt->errorInfo();
                 $response['message'] = "数据库插入失败: " . ($errorInfo[2] ?? 'Unknown error');
                 error_log("AJAX Create SQL Error: " . print_r($errorInfo, true) . " SQL: " . $insert_sql . " PARAMS: " . print_r($insert_params, true));
            }

        // --- 7. Catch Errors ---
        } catch (PDOException $e) {
            error_log("AJAX Create PDO Error: " . $e->getMessage());
            // Provide more specific error for duplicate entry if possible
            if ($e->getCode() == 23000) { // Integrity constraint violation (includes duplicate entry)
                 $response['message'] = "数据库错误：无法创建物品，可能是因为 ID {$new_item_id} 已存在或违反了其他约束。";
            } else {
                $response['message'] = "数据库错误: " . $e->getMessage();
            }
        } catch (Exception $e) {
            error_log("AJAX Create General Error: " . $e->getMessage());
            $response['message'] = "发生一般错误: " . $e->getMessage();
        }

        // --- 8. Send Response and Exit ---
        echo json_encode($response);
        exit;
    }

    // --- NEW: Handle Delete Item AJAX Request ---
    elseif ($_POST['action'] === 'delete_item_ajax') {
        header('Content-Type: application/json; charset=utf-8');
        $config = require_once __DIR__ . '/../config.php';
        require_once __DIR__ . '/../bag_query/db.php';
        // functions.php is likely not needed for simple delete, but include for consistency
        require_once __DIR__ . '/functions.php'; 

        $response = ['success' => false, 'message' => '删除物品时发生未知错误。'];

        // --- 1. Check Login ---
        $adminConf = $config['admin'] ?? null;
        $isLoggedInAjax = (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true);
        if (!$isLoggedInAjax) {
            $response['message'] = '错误：未登录或会话超时。';
            echo json_encode($response);
            exit;
        }

        // --- 2. Get and Validate Input ---
        $entry_id = filter_input(INPUT_POST, 'entry', FILTER_VALIDATE_INT);

        if (!$entry_id || $entry_id <= 0) {
            $response['message'] = '错误：无效的物品 Entry ID。';
            echo json_encode($response);
            exit;
        }

        // --- 3. Database Operation ---
        $pdo_W = null;
        try {
            $connections = connect_databases();
            if (!isset($connections['db_W'])) {
                throw new Exception("World 数据库连接未配置或失败。");
            }
            $pdo_W = $connections['db_W'];
            $pdo_W->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // --- 4. Execute Delete ---
            $delete_sql = "DELETE FROM `item_template` WHERE `entry` = :entry_id";
            $delete_stmt = $pdo_W->prepare($delete_sql);
            $execute_success = $delete_stmt->execute([':entry_id' => $entry_id]);

            if ($execute_success) {
                $affected_rows = $delete_stmt->rowCount();
                if ($affected_rows > 0) {
                     $response['success'] = true;
                     $response['message'] = "物品 (ID: {$entry_id}) 删除成功。";
                } else {
                    // Success technically, but no rows affected (maybe already deleted?)
                    $response['success'] = true; // Still report success to remove row from UI
                    $response['message'] = "物品 (ID: {$entry_id}) 未找到或已被删除。";
                }
            } 
            // else part is unlikely with ERRMODE_EXCEPTION

        // --- 5. Catch Errors ---
        } catch (PDOException $e) {
            error_log("AJAX Delete PDO Error: " . $e->getMessage());
            // Check for foreign key constraints if needed (e.g., Error code 1451 - Cannot delete or update a parent row)
             if ($e->getCode() == '23000') { // Integrity constraint violation
                 // Check for specific foreign key error message if possible
                 if (strpos($e->getMessage(), 'FOREIGN KEY') !== false) {
                      $response['message'] = "数据库错误：无法删除物品 (ID: {$entry_id})，因为它可能被其他数据（如战利品表、商人列表等）引用。请先移除相关引用。";
                 } else {
                      $response['message'] = "数据库约束错误: " . $e->getMessage();
                 }
             } else {
                 $response['message'] = "数据库错误: " . $e->getMessage();
             }
        } catch (Exception $e) {
            error_log("AJAX Delete General Error: " . $e->getMessage());
            $response['message'] = "发生一般错误: " . $e->getMessage();
        }

        // --- 6. Send Response and Exit ---
        echo json_encode($response);
        exit;
    } // End of delete_item_ajax handler

}
// --- END AJAX Request Handler ---


$config = require_once __DIR__ . '/../config.php'; // Correctly load config array
require_once __DIR__ . '/../bag_query/db.php'; // Reuse db connection
require_once __DIR__ . '/functions.php'; // <<< 确保这行在这里

// --- Login Logic (Define $isLoggedIn for page load) --- Start ---
$adminConf = $config['admin'] ?? null;
$isLoggedIn = false;
$loginError = '';
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}
if (!$adminConf || !isset($adminConf['username']) || !isset($adminConf['password_hash'])) { // Check for password_hash
    $loginError = '管理后台配置不完整，无法登录。';
} else {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_username'])) { // Handle login form submission
        $username = $_POST['login_username'];
        $password = $_POST['login_password'];
        // Verify username and password hash
        if ($username === $adminConf['username'] && isset($adminConf['password_hash']) && password_verify($password, $adminConf['password_hash'])) {
            $_SESSION['admin_logged_in'] = true;
            header('Location: index.php');
            exit;
        } else {
            $loginError = '无效的用户名或密码。';
        }
    }
    // Check session status
    if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        $isLoggedIn = true;
    }
}
// --- Login Logic --- End ---

// Initialize variables used outside login check, even if logged out
$filter_class = -1; // Default value needed for get_all_item_subclasses below

 $pdo_W = null; // Need World DB connection
 $error_message = '';
 $success_message = ''; // Added for success feedback
 $search_results = [];
 $item_to_edit = null; // Holds data for the item being edited
 $cancel_link_query_string = ''; // Initialize variable for cancel link query string

// --- Get Search/Filter Parameters (Using GET for search/filter) ---
$search_type = $_GET['search_type'] ?? 'name';
$search_value = trim($_GET['search_value'] ?? '');
// --- Pagination & Limit ---
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? max(10, (int)$_GET['limit']) : 50; // Removed upper limit
 
// New ItemLevel filter parameters
$filter_itemlevel_op = $_GET['filter_itemlevel_op'] ?? 'any'; // 'any', 'ge', 'le', 'eq'
$filter_itemlevel_val = isset($_GET['filter_itemlevel_val']) && $_GET['filter_itemlevel_val'] !== ''
                        ? (int)$_GET['filter_itemlevel_val']
                        : null; // Store as null if empty or not set

// Validate item level operator
$valid_ops = ['any', 'ge', 'le', 'eq'];
if (!in_array($filter_itemlevel_op, $valid_ops)) {
    $filter_itemlevel_op = 'any'; // Default to 'any' if invalid operator provided
}

if ($isLoggedIn) {
    try {
        $connections = connect_databases();
        if (!isset($connections['db_W'])) {
             throw new Exception("World 数据库连接未配置或失败。");
        }
        $pdo_W = $connections['db_W'];

        // --- Get Search/Filter Parameters (Moved Inside Login Check) ---
        $search_type = $_GET['search_type'] ?? 'name';
        $search_value = trim($_GET['search_value'] ?? '');
        // <<< 新增: 获取物品ID筛选参数 (重复获取以确保在登录检查后可用) >>>
        $filter_entry_op = $_GET['filter_entry_op'] ?? 'all'; 
        $filter_entry_val = trim($_GET['filter_entry_val'] ?? '');
        if (!in_array($filter_entry_op, ['all', 'ge', 'le', 'eq', 'between'])) {
            $filter_entry_op = 'all';
        }
        // <<< 结束新增 >>>
        $filter_quality = isset($_GET['filter_quality']) ? (int)$_GET['filter_quality'] : -1;
        $filter_class = isset($_GET['filter_class']) ? (int)$_GET['filter_class'] : -1;
        $filter_subclass = isset($_GET['filter_subclass']) ? (int)$_GET['filter_subclass'] : -1;
        $totalItems = 0; // Initialize total items count
        $totalPages = 0; // Initialize total pages count
        $pagination_query_string = ''; // Initialize pagination query string

        // --- NEW: Get Sort Parameters ---
        $sort_by = $_GET['sort_by'] ?? 'entry'; // Default sort column to entry
        $sort_dir = strtoupper($_GET['sort_dir'] ?? 'ASC'); // Default sort direction to ASC
        // Validate sort direction
        if ($sort_dir !== 'ASC' && $sort_dir !== 'DESC') {
            $sort_dir = 'DESC'; // Default to DESC if invalid
        }
        // --- END NEW ---

        // --- Handle Search/Filter Request (via GET) ---
        // Always perform search based on current parameters
        $search_data = search_item_templates( // Function will now return array ['results' => ..., 'total' => ...]
            $pdo_W,
            $search_type, // <<< Directly use the selected search type
            $search_value,
            $filter_entry_op, // <<< 新增
            $filter_entry_val, // <<< 新增
            $filter_quality,
            $filter_class,
            $filter_subclass,
            $filter_itemlevel_op,
            $filter_itemlevel_val,
            $limit,
            $page, // Pass current page
            $sort_by, // <<< NEW: Pass sort column
            $sort_dir  // <<< NEW: Pass sort direction
        );

        $search_results = $search_data['results'];
        $totalItems = $search_data['total'];
        $totalPages = ($limit > 0) ? ceil($totalItems / $limit) : 0;

        // Prepare base query parameters for pagination links
        $pagination_params = $_GET; // Copy current filters/search
        unset($pagination_params['page']); // Remove page itself
        $pagination_query_string = http_build_query($pagination_params);

        // Simplified feedback logic for no results
        // This check should happen *after* the search is performed
        if (empty($search_results) && ($_SERVER['REQUEST_METHOD'] === 'GET' && (isset($_GET['limit']) || isset($_GET['search_value'])))) { // Check if a search/filter was explicitly triggered via GET params
             $error_message = "未找到符合条件的物品。";
        }

        // Handle Edit Request (Load item data into edit form)
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['edit_id'])) {
             $edit_id = filter_input(INPUT_GET, 'edit_id', FILTER_VALIDATE_INT);
             if ($edit_id && $edit_id > 0) {
                 $item_to_edit = get_item_template($pdo_W, $edit_id);
                 if (!$item_to_edit) {
                     $error_message = "无法加载要编辑的物品 ID: " . htmlspecialchars((string)$edit_id);
                     $item_to_edit = null; // Clear it if not found
                 } else {
                     // Successfully loaded item, capture other GET params for cancel link
                     $cancel_link_params = $_GET;
                     unset($cancel_link_params['edit_id']); // Exclude edit_id itself
                     if (!empty($cancel_link_params)) {
                         $cancel_link_query_string = '&' . http_build_query($cancel_link_params); // Prepend with & if params exist
                     }
                 }
             }
        }

         // Handle Save Request (POST)
         if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_item'])) {
             $entry_id = filter_input(INPUT_POST, 'entry', FILTER_VALIDATE_INT);
             if ($entry_id) {
                 // TODO: Implement robust validation and saving
                 // For now, just show a message and reload
                 $save_data = $_POST; // Get all submitted data
                 
                 // Example: Basic update (highly insecure without validation/type casting!)
                 /*
                 unset($save_data['save_item']); // Don't save this helper field
                 unset($save_data['entry']); // Don't try to update the primary key
                 
                 $set_clauses = [];
                 $params = [':entry_id' => $entry_id];
                 foreach ($save_data as $key => $value) {
                     // WARNING: Needs proper validation and type checking!
                     // WARNING: Needs check if column exists in item_template!
                     $set_clauses[] = "`" . $key . "` = :" . $key;
                     $params[':' . $key] = $value; 
                 }
                 
                 if (!empty($set_clauses)) {
                     $sql = "UPDATE item_template SET " . implode(', ', $set_clauses) . " WHERE entry = :entry_id";
                     $stmt = $pdo_W->prepare($sql);
                     $stmt->execute($params);
                     $success_message = "物品 (ID: {$entry_id}) 已尝试更新 (未验证)。";
                 } else {
                      $error_message = "没有可更新的数据提交。";
                 }
                 */
                  $error_message = "保存功能正在开发中... (尝试保存 ID: {$entry_id}) - 实际保存逻辑未启用";
                 
                 // Reload the item data after attempting save
                 $item_to_edit = get_item_template($pdo_W, $entry_id);
                 // Capture GET params for cancel link even after a save attempt
                 $cancel_link_params = $_GET;
                 unset($cancel_link_params['edit_id']); // edit_id might be in GET if save reloads
                 if (!empty($cancel_link_params)) {
                    $cancel_link_query_string = '&' . http_build_query($cancel_link_params);
                 }
             } else {
                 $error_message = "保存物品时缺少有效的物品 ID。";
             }
         }


    } catch (Exception $e) {
        $error_message = "发生错误: " . $e->getMessage();
        error_log("Item Editor Error: " . $e->getMessage()); // Log the error
    }
}

// Fetch dropdown options outside the main logic block to ensure they are always available
$quality_options = get_all_qualities();
$class_options = [-1 => '所有类别'] + get_all_item_classes(); // Add 'Any' option
// Subclass options should ideally depend on the selected class filter, 
// but for simplicity without JS, we show all possible or based on current filter.
$subclass_options = [-1 => '所有子类别'] + get_all_item_subclasses($filter_class); 
$itemlevel_op_options = ['any' => '任意等级', 'ge' => '>=', 'le' => '<=', 'eq' => '='];

// --- Options for Edit Form Dropdowns ---
// Ensure these are comprehensive based on WotLK 3.3.5a
$invtype_options = get_inventory_types(); // Fetch from functions.php
$bonding_options = get_bonding_types(); // Fetch from functions.php
$material_options = get_material_types(); // Fetch from functions.php
$sheath_options = get_sheath_types(); // Fetch from functions.php
$bagfamily_options = get_bag_families(); // Fetch from functions.php (Bitmask, maybe better as checkboxes later)
$totemcategory_options = get_totem_categories(); // Fetch from functions.php
$foodtype_options = get_food_types(); // Fetch from functions.php

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>物品编辑器 - <?= $isLoggedIn ? '控制台' : '登录' ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../bag_query/style.css"> <!-- Reuse common styles -->
     <style>
         .edit-form-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 1rem; }
         .form-section { border: 1px solid #444; padding: 1rem; margin-bottom: 1rem; border-radius: 5px; }
         .form-section h5 { color: var(--wow-gold); border-bottom: 1px solid #555; padding-bottom: 0.5rem; margin-bottom: 1rem; }
         /* Style for item quality links */
         .quality-0 { color: #9d9d9d; } /* Poor */
         .quality-1 { color: #ffffff; } /* Common */
         .quality-2 { color: #1eff00; } /* Uncommon */
         .quality-3 { color: #0070ff; } /* Rare */
         .quality-4 { color: #a335ee; } /* Epic */
         .quality-5 { color: #ff8000; } /* Legendary */
         .quality-6 { color: #e6cc80; } /* Artifact */
         .quality-7 { color: #e6cc80; } /* Heirloom */
         .item-link { text-decoration: none; font-weight: bold; }
         .item-link:hover { text-decoration: underline; }
         .filter-form .row { margin-bottom: 1rem; } /* Add space between form rows */
         .table th, .table td { white-space: nowrap; } /* Prevent table cell wrap */
         /* Styles for the sticky SQL preview header */
         #sql-preview-section {
             position: sticky;
             top: 0; /* Stick to the top */
             z-index: 1020; /* Ensure it's above other content but below modals if any */
             background-color: rgba(33, 37, 41, 0.95); /* Slightly transparent dark background */
             border-bottom: 1px solid var(--wow-gold);
         }
         #sql-display {
             background-color: #1a1a1a;
             color: #d4d4d4;
             border: 1px solid #555;
             border-radius: 4px;
             padding: 10px;
             font-family: Consolas, Monaco, 'Andale Mono', 'Ubuntu Mono', monospace;
             font-size: 0.9em;
             white-space: pre-wrap; /* Allow wrapping */
             word-break: break-all; /* Break long words/lines */
             max-height: 150px; /* Limit height and make scrollable */
             overflow-y: auto;
         }
         .sql-controls label { margin-right: 10px; }
         .sql-buttons .btn { margin-left: 5px; }
     </style>
</head>
<body>

<?php if (!$isLoggedIn): ?>
    <!-- LOGIN FORM -->
    <div class="login-container">
        <h2>管理员登录 - 物品编辑器</h2>
        <?php if ($loginError): ?> <div class="alert alert-danger"><?= htmlspecialchars($loginError) ?></div> <?php endif; ?>
        <form method="post" action="index.php">
            <div class="mb-3"><label for="login_username" class="form-label">用户名</label><input type="text" class="form-control" id="login_username" name="login_username" required></div>
            <div class="mb-3"><label for="login_password" class="form-label">密码</label><input type="password" class="form-control" id="login_password" name="login_password" required></div>
            <button type="submit" class="btn btn-query w-100">登录</button>
        </form>
         <div class="mt-3 text-center"><a href="../index.php" class="home-btn-link">&laquo; 返回主页</a></div>
    </div>
<?php else: ?>
    <!-- ITEM EDITOR INTERFACE -->
    <div class="container-fluid mt-4 position-relative p-4">
        <a href="../index.php" class="home-btn">&laquo; 返回主页</a>
        <a href="?logout=1" class="logout-btn">退出登录</a>
        <h2 class="text-center mb-4">物品模板编辑器</h2>

        <?php if ($error_message): ?> <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div> <?php endif; ?>
        <?php if ($success_message): ?> <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div> <?php endif; ?>

        <?php if ($item_to_edit): ?>
            <?php
                // Moved this block UP - Define field groups, helper functions, and generate SQL *before* using the variables

                // 重新定义字段分组和中文标签 (参照截图)
                 $field_groups = [
                     '核心' => [ // 对应截图第一部分的大部分字段
                         //'entry' => 'ID (Entry)', // Entry 通常是只读且在标题显示
                         'name' => '名称', 'description' => '描述', 'displayid' => '模型ID', 'Quality' => '品质',
                         'class' => '类别', 'subclass' => '子类别', 'InventoryType' => '物品栏类型', 'ItemLevel' => '物品等级',
                         'BuyCount' => '购买堆叠数', 'BuyPrice' => '购买价格 (铜)', 'SellPrice' => '出售价格 (铜)',
                         'maxcount' => '最大拥有数量', 'stackable' => '可堆叠数量', 'ContainerSlots' => '容器栏位数',
                         'bonding' => '绑定类型', 'startquest' => '开始任务ID', 'Material' => '材质', 'sheath' => '武器归鞘方式',
                         'RandomProperty' => '指向 item_enchantment_template.entry。决定物品首次出现时附加随机属性的几率。此字段与 RandomSuffix 互斥（只能填一个非零值）。数据主要来源于WDB。',
                         'RandomSuffix' => '指向 item_enchantment_template.entry。决定物品首次出现时附加随机后缀（如"之猎鹰"）的几率。此字段与 RandomProperty 互斥（只能填一个非零值）。数据主要来源于WDB。',
                         'itemset' => '此物品所属套装的ID。注意：不能创建新的套装ID，必须使用 ItemSet.dbc 中已定义的ID。',
                         'BagFamily' => '如果物品是背包，此位掩码控制可放入的物品类型。将不同类型的掩码值相加来组合。',
                         'TotemCategory' => '萨满图腾、专业工具等的类别ID', 'duration' => '持续时间(秒)',
                         'ItemLimitCategory' => '物品限制类别ID（如法力宝石、治疗石，限制同类物品持有数量）', 'DisenchantID' => '分解拾取模板ID', 'FoodType' => '食物类型(宠物)',
                         'RandomProperty' => '随机属性ID', 'RandomSuffix' => '随机后缀ID', 'itemset' => '套装ID',
                         'BagFamily' => '背包可存放类型 (掩码)', 'TotemCategory' => '图腾类别ID', 'duration' => '持续时间(秒)',
                         'ItemLimitCategory' => '物品限制类别ID', 'DisenchantID' => '分解拾取模板ID', 'FoodType' => '食物类型(宠物)',
                         'minMoneyLoot' => '最小开出金钱 (铜)', 'maxMoneyLoot' => '最大开出金钱 (铜)',
                         'SoundOverrideSubclass' => '声音覆盖子类' // 放在这里或内部
                     ],
                     '标志' => [
                         'Flags' => '标记 (Flags)', 'FlagsExtra' => '额外标记 (FlagsExtra)', 'flagsCustom' => '自定义标记'
                     ],
                     '文本' => [
                         'PageText' => '书页文本ID', 'PageMaterial' => '书页材质', 'LanguageID' => '语言ID'
                     ],
                     '要求' => [
                         'AllowableClass' => '允许职业',
                         'AllowableRace' => '允许种族',
                         'RequiredLevel' => '最低玩家等级。',
                         'RequiredSkill' => '专业技能ID',
                         'RequiredSkillRank' => '专业技能等级。',
                         'requiredspell' => '需求法术ID。',
                         'requiredhonorrank' => '需求荣誉等级。',
                         'RequiredCityRank' => '需要达到的城市声望等级',
                         'RequiredReputationFaction' => '需要达到特定声望等级的阵营ID',
                         'RequiredReputationRank' => '需要达到的声望等级（仇恨到崇拜）',
                         'RequiredDisenchantSkill' => '需要分解技能等级'
                     ],
                     '区域与锁' => [ // 合并截图中的小分组
                         'Map' => '地图限制ID', 'area' => '区域限制ID', 'HolidayId' => '节日ID', 'lockid' => '锁ID'
                     ],
                     '抗性' => [
                         'holy_res' => '神圣抗性', 'fire_res' => '火焰抗性', 'nature_res' => '自然抗性',
                         'frost_res' => '冰霜抗性', 'shadow_res' => '暗影抗性', 'arcane_res' => '奥术抗性'
                     ],
                     '属性' => [ // StatsCount 和具体属性对
                         'StatsCount' => '此物品提供的属性加成条目数（1-10）。',
                         'ScalingStatDistribution' => '随玩家等级成长的属性分布ID（主要用于传家宝）。',
                         'ScalingStatValue' => '缩放属性在80级时的最终值。',
                         'stat_type1' => '第1条属性的类型。', 'stat_value1' => '对应属性类型增加的值。',
                         'stat_type2' => '第2条属性的类型。', 'stat_value2' => '对应属性类型增加的值。',
                         'stat_type3' => '第3条属性的类型。', 'stat_value3' => '对应属性类型增加的值。',
                         'stat_type4' => '第4条属性的类型。', 'stat_value4' => '对应属性类型增加的值。',
                         'stat_type5' => '第5条属性的类型。', 'stat_value5' => '对应属性类型增加的值。',
                         'stat_type6' => '第6条属性的类型。', 'stat_value6' => '对应属性类型增加的值。',
                         'stat_type7' => '第7条属性的类型。', 'stat_value7' => '对应属性类型增加的值。',
                         'stat_type8' => '第8条属性的类型。', 'stat_value8' => '对应属性类型增加的值。',
                         'stat_type9' => '第9条属性的类型。', 'stat_value9' => '对应属性类型增加的值。',
                         'stat_type10' => '第10条属性的类型。', 'stat_value10' => '对应属性类型增加的值。',
                     ],
                     '镶孔' => [ // 颜色和内容对
                         'socketBonus' => '镶孔奖励ID', 'GemProperties' => '宝石属性ID',
                         'socketColor_1' => '镶孔1 颜色', 'socketContent_1' => '镶孔1 数量',
                         'socketColor_2' => '镶孔2 颜色', 'socketContent_2' => '镶孔2 数量',
                         'socketColor_3' => '镶孔3 颜色', 'socketContent_3' => '镶孔3 数量',
                     ],
                     '武器与护甲' => [
                         'armor' => '护甲', 'ArmorDamageModifier' => '护甲伤害修正', 'delay' => '武器速度 (毫秒)',
                         'ammo_type' => '弹药类型', 'RangedModRange' => '远程距离加成', 'block' => '格挡值',
                         'MaxDurability' => '最大耐久度'
                     ],
                     '伤害' => [ // 伤害对
                         'dmg_min1' => '该伤害类型的最小伤害值。', 'dmg_max1' => '该伤害类型的最大伤害值。', 'dmg_type1' => '该伤害的类型（物理、火焰等）。',
                         'dmg_min2' => '该伤害类型的最小伤害值。', 'dmg_max2' => '该伤害类型的最大伤害值。', 'dmg_type2' => '该伤害的类型（物理、火焰等）。',
                     ],
                     '法术' => [ // 法术组
                         'spellid_1' => '法术ID 1', 'spelltrigger_1' => '触发 1', 'spellcharges_1' => '次数 1', 'spellppmRate_1' => 'PPM 1', 'spellcooldown_1' => '冷却 1', 'spellcategory_1' => '分类 1', 'spellcategorycooldown_1' => '分类冷却 1',
                         'spellid_2' => '法术ID 2', 'spelltrigger_2' => '触发 2', 'spellcharges_2' => '次数 2', 'spellppmRate_2' => 'PPM 2', 'spellcooldown_2' => '冷却 2', 'spellcategory_2' => '分类 2', 'spellcategorycooldown_2' => '分类冷却 2',
                         'spellid_3' => '法术ID 3', 'spelltrigger_3' => '触发 3', 'spellcharges_3' => '次数 3', 'spellppmRate_3' => 'PPM 3', 'spellcooldown_3' => '冷却 3', 'spellcategory_3' => '分类 3', 'spellcategorycooldown_3' => '分类冷却 3',
                         'spellid_4' => '法术ID 4', 'spelltrigger_4' => '触发 4', 'spellcharges_4' => '次数 4', 'spellppmRate_4' => 'PPM 4', 'spellcooldown_4' => '冷却 4', 'spellcategory_4' => '分类 4', 'spellcategorycooldown_4' => '分类冷却 4',
                         'spellid_5' => '法术ID 5', 'spelltrigger_5' => '触发 5', 'spellcharges_5' => '次数 5', 'spellppmRate_5' => 'PPM 5', 'spellcooldown_5' => '冷却 5', 'spellcategory_5' => '分类 5', 'spellcategorycooldown_5' => '分类冷却 5',
                     ],
                     '脚本与其他' => [ // 合并剩余
                         'ScriptName' => '脚本名称', 'VerifiedBuild' => '验证版本号'
                     ]
                 ];

                 // 定义字段描述
                 $field_descriptions = [
                     // 核心
                     'name' => '物品的游戏内显示名称。',
                     'description' => '显示在物品提示框底部（橙色字体）的描述文本。',
                     'displayid' => '物品的模型ID。每个模型都有自己的图标，此字段同时控制模型外观和图标。',
                     'Quality' => '物品的品质等级（如粗糙、精良、史诗等）。',
                     'class' => '物品的基础分类（如武器、护甲、消耗品）。',
                     'subclass' => '物品在基础分类下的具体子类别。',
                     'InventoryType' => '物品可以装备的具体栏位。',
                     'ItemLevel' => '物品的基础等级，影响属性和装备要求。',
                     'BuyCount' => '商人出售时物品的堆叠数量。如果商人限量出售此物品，每次刷新列表时（见npc_vendor.incrtime），库存会增加此数量。',
                     'BuyPrice' => '从商人处购买该物品所需的价格（以铜为单位）。',
                     'SellPrice' => '将此物品出售给商人时，商人支付的价格（以铜为单位）。如果物品不能出售给商人，则设为0。',
                     'maxcount' => '玩家最多能拥有的该物品数量。0表示无限制。',
                     'stackable' => '同一格子中可以堆叠的此物品数量。',
                     'ContainerSlots' => '如果物品是容器（背包），此字段控制其拥有的格子数量。',
                     'bonding' => '物品的绑定规则。注意：设为"账号绑定"需要 Flags 含 134217728 且 bonding > 0。',
                     'startquest' => '右键点击此物品可开始的任务ID。（参照 quest_template.id）',
                     'Material' => '物品的材质。影响移动物品时的音效。消耗品（食物、材料等）使用-1。',
                     'sheath' => '武器收鞘时在角色身上的位置和方式。',
                     'RandomProperty' => '指向随机附魔模板的ID（与RandomSuffix互斥）。',
                     'RandomSuffix' => '指向随机后缀（如"之猎鹰"）模板的ID（与RandomProperty互斥）。',
                     'itemset' => '该物品所属套装的ID（需在ItemSet.dbc中定义）。',
                     'BagFamily' => '如果物品是特定类型背包，定义其可容纳的物品类别掩码。',
                     'TotemCategory' => '萨满图腾、专业工具等的类别ID。',
                     'duration' => '物品的持续时间（以秒为单位，特殊flag可设为真实时间）。',
                     'ItemLimitCategory' => '物品限制类别ID（如法力宝石、治疗石，限制同类物品持有数量）。',
                     'DisenchantID' => '分解拾取模板ID。（参照 disenchant_loot_template.entry）',
                     'FoodType' => '如果物品是食物，定义其作为猎人宠物食物的类型（决定食物类别）。注意：生肉/鱼与普通肉/鱼不同，后两者可能出现于TBC宠物饮食中。',
                     'minMoneyLoot' => '如果物品是可包含金钱的容器，此字段定义容器中包含的最少钱币数量（铜）。',
                     'maxMoneyLoot' => '如果物品是可包含金钱的容器，此字段定义容器中包含的最大钱币数量（铜）。',
                     'SoundOverrideSubclass' => '覆盖默认的武器击中音效。指定一个子类ID，使其听起来像该子类武器的音效。',
                     // 标志
                     'Flags' => '物品的主要标志位掩码，控制多种属性和行为。',
                     'FlagsExtra' => '物品的额外标志位掩码，控制更多特殊属性。',
                     'flagsCustom' => '自定义标志位掩码，用于核心特定的功能。',
                     // 文本
                     'PageText' => '物品关联的文本ID（用于书籍、信件等）。右键点击物品会显示此文本。（参照 page_text.entry）',
                     'PageMaterial' => '页面文本窗口显示的背景材质ID。（参照 PageTextMaterial.dbc）',
                     'LanguageID' => '文本所使用的语言ID。（参照 Languages.dbc）',
                     // 要求
                     'AllowableClass' => '允许使用该物品的职业掩码（-1为所有职业）。',
                     'AllowableRace' => '允许使用该物品的种族掩码（-1为所有种族）。',
                     'RequiredLevel' => '装备或使用该物品所需的最低玩家等级。',
                     'RequiredSkill' => '使用或装备该物品所需的专业技能ID。',
                     'RequiredSkillRank' => '使用或装备该物品所需的专业技能等级。',
                     'requiredspell' => '学习或使用该物品前必须学会的法术ID。',
                     'requiredhonorrank' => '需要达到的荣誉等级。',
                     'RequiredCityRank' => '需要达到的城市声望等级。',
                     'RequiredReputationFaction' => '需要达到特定声望等级的阵营ID。',
                     'RequiredReputationRank' => '需要达到的声望等级（仇恨到崇拜）。',
                     'RequiredDisenchantSkill' => '分解该物品所需的附魔技能等级（-1为不可分解）。',
                     // 区域与锁
                     'Map' => '限制物品只能在指定地图ID内使用。',
                     'area' => '限制物品只能在指定区域ID内使用。',
                     'HolidayId' => '该物品关联的节日ID (Holidays.dbc)。',
                     'lockid' => '与此物品（作为钥匙）关联的锁ID。用于钥匙-门机制。（参照 Lock.dbc）',
                     // 抗性
                     'holy_res' => '提供的神圣抗性。',
                     'fire_res' => '提供的火焰抗性。',
                     'nature_res' => '提供的自然抗性。',
                     'frost_res' => '提供的冰霜抗性。',
                     'shadow_res' => '提供的暗影抗性。',
                     'arcane_res' => '提供的奥术抗性。',
                     // 属性
                     'StatsCount' => '此物品提供的属性加成条目数（1-10）。',
                     'ScalingStatDistribution' => '用于属性缩放的分布ID (ScalingStatDistribution.dbc)。',
                     'ScalingStatValue' => '用于属性缩放的值 (ScalingStatValues.dbc)。',
                     'stat_type1' => '第1条属性的类型。', 'stat_value1' => '第1条属性的值。',
                     'stat_type2' => '第2条属性的类型。', 'stat_value2' => '第2条属性的值。',
                     'stat_type3' => '第3条属性的类型。', 'stat_value3' => '第3条属性的值。',
                     'stat_type4' => '第4条属性的类型。', 'stat_value4' => '第4条属性的值。',
                     'stat_type5' => '第5条属性的类型。', 'stat_value5' => '第5条属性的值。',
                     'stat_type6' => '第6条属性的类型。', 'stat_value6' => '第6条属性的值。',
                     'stat_type7' => '第7条属性的类型。', 'stat_value7' => '第7条属性的值。',
                     'stat_type8' => '第8条属性的类型。', 'stat_value8' => '第8条属性的值。',
                     'stat_type9' => '第9条属性的类型。', 'stat_value9' => '第9条属性的值。',
                     'stat_type10' => '第10条属性的类型。', 'stat_value10' => '第10条属性的值。',
                     // 镶孔
                     'socketBonus' => '镶满宝石后获得的奖励属性ID (GemProperties.dbc)。',
                     'GemProperties' => '对应 GemProperties.dbc 中的ID。',
                     'socketColor_1' => '第1个插槽的颜色。', 'socketContent_1' => 'SocketColor_1 的宝石数量',
                     'socketColor_2' => '第2个插槽的颜色。', 'socketContent_2' => 'SocketColor_2 的宝石数量',
                     'socketColor_3' => '第3个插槽的颜色。', 'socketContent_3' => 'SocketColor_3 的宝石数量',
                     // 武器与护甲
                     'armor' => '物品提供的护甲值。',
                     'ArmorDamageModifier' => '护甲伤害修正值（用途不明确）。',
                     'delay' => '连续攻击之间的间隔时间（毫秒）。',
                     'ammo_type' => '武器使用的弹药类型 (子弹=2, 箭=3)。',
                     'RangedModRange' => '远程武器（弓/枪/弩）的射程修正值。默认约为0.3-0.4码，暴雪武器通常为100。',
                     'block' => '盾牌的格挡值。',
                     'MaxDurability' => '物品的最大耐久度。',
                     // 伤害
                     'dmg_min1' => '该伤害类型的最小伤害值。', 'dmg_max1' => '该伤害类型的最大伤害值。', 'dmg_type1' => '该伤害的类型（物理、火焰等）。',
                     'dmg_min2' => '该伤害类型的最小伤害值。', 'dmg_max2' => '该伤害类型的最大伤害值。', 'dmg_type2' => '该伤害的类型（物理、火焰等）。',
                     // 法术
                     'spellid_1' => '物品可以施放或触发的法术的ID。', 'spelltrigger_1' => '法术的触发方式。', 'spellcharges_1' => '法术可使用次数。0=无限；负数=用完后物品消失；正数=用完后物品不消失。', 'spellppmRate_1' => '每分钟触发率（PPM），控制法术触发频率（当 spelltrigger 为 2 时）。', 'spellcooldown_1' => '指定法术的冷却时间（毫秒）。-1表示使用法术默认冷却。注意：这不是"击中时触发"类特效的内部冷却时间。', 'spellcategory_1' => '法术所属的类别ID（参照 SpellCategory.dbc，或使用 >1260 自定义）。', 'spellcategorycooldown_1' => '同类别（spellcategory）下所有法术共享的冷却时间（毫秒）。-1表示使用法术默认冷却。',
                     'spellid_2' => '物品可以施放或触发的法术的ID。', 'spelltrigger_2' => '法术的触发方式。', 'spellcharges_2' => '法术可使用次数。0=无限；负数=用完后物品消失；正数=用完后物品不消失。', 'spellppmRate_2' => '每分钟触发率（PPM），控制法术触发频率（当 spelltrigger 为 2 时）。', 'spellcooldown_2' => '指定法术的冷却时间（毫秒）。-1表示使用法术默认冷却。注意：这不是"击中时触发"类特效的内部冷却时间。', 'spellcategory_2' => '法术所属的类别ID（参照 SpellCategory.dbc，或使用 >1260 自定义）。', 'spellcategorycooldown_2' => '同类别（spellcategory）下所有法术共享的冷却时间（毫秒）。-1表示使用法术默认冷却。',
                     'spellid_3' => '物品可以施放或触发的法术的ID。', 'spelltrigger_3' => '法术的触发方式。', 'spellcharges_3' => '法术可使用次数。0=无限；负数=用完后物品消失；正数=用完后物品不消失。', 'spellppmRate_3' => '每分钟触发率（PPM），控制法术触发频率（当 spelltrigger 为 2 时）。', 'spellcooldown_3' => '指定法术的冷却时间（毫秒）。-1表示使用法术默认冷却。注意：这不是"击中时触发"类特效的内部冷却时间。', 'spellcategory_3' => '法术所属的类别ID（参照 SpellCategory.dbc，或使用 >1260 自定义）。', 'spellcategorycooldown_3' => '同类别（spellcategory）下所有法术共享的冷却时间（毫秒）。-1表示使用法术默认冷却。',
                     'spellid_4' => '物品可以施放或触发的法术的ID。', 'spelltrigger_4' => '法术的触发方式。', 'spellcharges_4' => '法术可使用次数。0=无限；负数=用完后物品消失；正数=用完后物品不消失。', 'spellppmRate_4' => '每分钟触发率（PPM），控制法术触发频率（当 spelltrigger 为 2 时）。', 'spellcooldown_4' => '指定法术的冷却时间（毫秒）。-1表示使用法术默认冷却。注意：这不是"击中时触发"类特效的内部冷却时间。', 'spellcategory_4' => '法术所属的类别ID（参照 SpellCategory.dbc，或使用 >1260 自定义）。', 'spellcategorycooldown_4' => '同类别（spellcategory）下所有法术共享的冷却时间（毫秒）。-1表示使用法术默认冷却。',
                     'spellid_5' => '物品可以施放或触发的法术的ID。', 'spelltrigger_5' => '法术的触发方式。', 'spellcharges_5' => '法术可使用次数。0=无限；负数=用完后物品消失；正数=用完后物品不消失。', 'spellppmRate_5' => '每分钟触发率（PPM），控制法术触发频率（当 spelltrigger 为 2 时）。', 'spellcooldown_5' => '指定法术的冷却时间（毫秒）。-1表示使用法术默认冷却。注意：这不是"击中时触发"类特效的内部冷却时间。', 'spellcategory_5' => '法术所属的类别ID（参照 SpellCategory.dbc，或使用 >1260 自定义）。', 'spellcategorycooldown_5' => '同类别（spellcategory）下所有法术共享的冷却时间（毫秒）。-1表示使用法术默认冷却。',
                     // 脚本与其他
                     'ScriptName' => '物品关联的脚本名称（如果存在）。',
                     'VerifiedBuild' => '用于确定模板数据是否已通过WDB文件验证。0=未解析；>0=已通过特定客户端版本的WDB解析；-1=占位符，等待WDB数据。',
                     // -- 以下是先前未完全覆盖的字段 --
                     'RequiredDisenchantSkill' => '分解此物品所需的附魔技能等级。设为-1表示物品无法被分解。',
                     'ArmorDamageModifier' => '护甲伤害修正值 (用途不明)。',
                     'duration' => '物品的持续时间（秒）。设置 flagsCustom 的 ITEM_FLAGS_CU_DURATION_REAL_TIME 位可使其按真实时间计时（即使玩家离线）。',
                     'ItemLimitCategory' => '关联 ItemLimitCategory.dbc。定义物品是否属于某个类别（如法力宝石、治疗石），并限制背包中同类物品的数量。',
                     'HolidayId' => '该物品关联的节日ID。（参照 Holidays.dbc）',
                     'DisenchantID' => '分解拾取模板ID。（参照 disenchant_loot_template.entry）',
                     'FoodType' => '如果物品是食物，定义其作为猎人宠物食物的类型（决定食物类别）。注意：生肉/鱼与普通肉/鱼不同，后两者可能出现于TBC宠物饮食中。',
                     'minMoneyLoot' => '如果物品是可包含金钱的容器，此字段定义容器中包含的最少钱币数量（铜）。',
                     'maxMoneyLoot' => '如果物品是可包含金钱的容器，此字段定义容器中包含的最大钱币数量（铜）。',
                     'SoundOverrideSubclass' => '覆盖默认的武器击中音效。指定一个子类ID，使其听起来像该子类武器的音效。',
                 ];

                 // Helper function to render a form field (avoids repetition)
                 function render_form_field($key, $label, $value) {
                     global $field_descriptions; // Make descriptions available
                     global $quality_options, $class_options, $subclass_options; // Existing globals
                     global $invtype_options, $bonding_options, $material_options, $sheath_options, $bagfamily_options, $totemcategory_options, $foodtype_options; // Dropdown options
                     global $stat_type_options; // Add Stat Types
                     global $reputation_rank_options; // Add Reputation Ranks
                     global $item_flags, $item_flags_extra, $item_flags_custom; 
                     global $damage_type_options; // Add Damage Types
                     global $spell_trigger_type_options; // Add Spell Trigger Types
                     global $socket_color_options; // Add Socket Colors
                     global $socket_bonus_options; // Add Socket Bonus

                     $input_type = 'text';
                     $attributes = '';
                     $is_textarea = false;
                     $label = htmlspecialchars($label);
                     $value_str = htmlspecialchars((string)$value);

                     // --- Type Detection Logic ---
                      if (is_numeric($value) && !is_string($value) && $value !== '') {
                          if (strpos($key, 'Float') !== false || in_array(strtolower($key), ['buyprice', 'sellprice', 'dmg_range'])) {
                              $input_type = 'number'; $attributes = ' step="any"';
                          } else {
                              $input_type = 'number'; $attributes = ' step="1"';
                          }
                      } elseif (is_string($value)) {
                          if (in_array(strtolower($key), ['description', 'pagetext'])) {
                              $input_type = 'textarea'; $is_textarea = true;
                          } else { $input_type = 'text'; }
                      } else {
                          if (preg_match('/^(dmg_|res$|armor|delay|stat_value|spellcharges|spellcooldown|spellcategory|socketContent|stackable|maxcount|BuyPrice|SellPrice|ItemLevel|RequiredLevel|displayid|entry|class|subclass|Quality|StatsCount|bonding|Material|sheath|block|itemset|MaxDurability|area|Map|BagFamily|TotemCategory|socketColor_|socketBonus|GemProperties|RequiredDisenchantSkill|ArmorDamageModifier|duration|ItemLimitCategory|HolidayId|DisenchantID|FoodType|minMoneyLoot|maxMoneyLoot|Flags|FlagsExtra|AllowableClass|AllowableRace|requiredspell|requiredhonorrank|RequiredCityRank|RequiredReputationFaction|RequiredReputationRank|RequiredSkill|RequiredSkillRank|spellid_|spelltrigger_|spellppmRate_|spellcategorycooldown_|startquest|lockid|RandomProperty|RandomSuffix|socketContent_|ScalingStatDistribution|ScalingStatValue|VerifiedBuild|flagsCustom|BuyCount|SoundOverrideSubclass|LanguageID|PageMaterial|StatsCount|ammo_type|RangedModRange|dmg_type)/i', $key)) {
                              $input_type = 'number';
                              $attributes = (strpos($key, 'Float') !== false || in_array(strtolower($key), ['buyprice', 'sellprice', 'dmg_range'])) ? ' step="any"' : ' step="1"';
                          } else { $input_type = 'text'; }
                      }
                      // --- End Type Detection ---

                      // --- Special handling for dropdowns --- 
                     $options_data = null;
                     $select_id = $key; // Use the field key as the ID for consistency
                     $is_dropdown = true; // Flag to indicate dropdown rendering

                     switch ($key) {
                         case 'Quality':       $options_data = $quality_options; break;
                         case 'class':         $options_data = $class_options; $select_id = 'class'; break; // Use plain key
                         case 'subclass':      
                             // Subclass options depend on the currently selected class. 
                             // PHP generates initial options based on loaded item's class.
                             // JavaScript will handle dynamic updates.
                             $item_class = $GLOBALS['item_to_edit']['class'] ?? -1; // Get class from item being edited
                             $options_data = get_all_item_subclasses($item_class); // Fetch relevant subclasses
                             $select_id = 'subclass'; // Use plain key
                             break; 
                         case 'InventoryType': $options_data = $invtype_options; break;
                         case 'bonding':       $options_data = $bonding_options; break;
                         case 'Material':      $options_data = $material_options; break;
                         case 'sheath':        $options_data = $sheath_options; break;
                         // case 'BagFamily':     $options_data = $bagfamily_options; break; // Keep as number for now (bitmask)
                         case 'TotemCategory': $options_data = $totemcategory_options; break;
                         case 'FoodType':      $options_data = $foodtype_options; break;
                         case 'RequiredReputationRank': $options_data = get_reputation_ranks(); break;
                          // Add case for Socket Bonus
                          case 'socketBonus':   $options_data = get_socket_bonus_options(); break;
                          default:
                              $is_dropdown = false; // Not a dropdown field
                              break;
                     }

                     // --- Add separate handling for Stat Type dropdowns --- 
                     if (strpos($key, 'stat_type') === 0) { // Check if key starts with 'stat_type'
                         $is_dropdown = true; // Mark as dropdown
                         $select_id = $key;
                         $options_data = get_stat_types(); // Get the stat type options
                     } // Note: This check happens *after* the main switch, 
                       // ensuring it overrides default behavior but doesn't interfere with other specific dropdowns.

                     // --- Add separate handling for Damage Type dropdowns --- 
                     if (strpos($key, 'dmg_type') === 0) { // Check if key starts with 'dmg_type'
                         $is_dropdown = true;
                         $select_id = $key;
                         $options_data = get_damage_types(); 
                     }

                     // --- Add separate handling for Spell Trigger dropdowns --- 
                     if (strpos($key, 'spelltrigger_') === 0) { // Check if key starts with 'spelltrigger_'
                         $is_dropdown = true;
                         $select_id = $key;
                         $options_data = get_spell_trigger_types(); 
                     }

                     // --- Add separate handling for Socket Color dropdowns --- 
                     if (strpos($key, 'socketColor_') === 0) { // Check if key starts with 'socketColor_'
                         $is_dropdown = true;
                         $select_id = $key;
                         $options_data = get_socket_colors(); 
                     }

                     // --- Determine Title Attribute ---
                     $title_attr = $key; // Default to just the key
                     if (isset($field_descriptions[$key])) {
                         $title_attr = $key . ': ' . $field_descriptions[$key];
                     }
                     $title_attr = htmlspecialchars($title_attr);

                     if ($is_dropdown && $options_data !== null) {
                          $options_html = '';
                          $current_value = $value;
                          if (!isset($options_data[$current_value]) && $current_value !== '' && $current_value !== null) {
                                $options_data[(string)$current_value] = "未知/无效 ({$current_value})";
                           }

                           foreach ($options_data as $id => $name) {
                               if ($id === -1 && $current_value != -1 && $key !== 'class' && $key !== 'subclass') continue;
                               $selected = ($current_value !== '' && $current_value !== null && $current_value == $id) ? 'selected' : '';
                               $options_html .= "<option value='" . htmlspecialchars((string)$id) . "' {$selected}>" . htmlspecialchars((string)$name) . "</option>";
                           }
                          return "<label for='{$select_id}' title='{$title_attr}' class='form-label form-label-sm'>{$label}:</label>" .
                                 "<select id='{$select_id}' name='{$key}' class='form-select form-select-sm'>{$options_html}</select>";
                     }
                     // --- End Dropdown Handling ---

                     // --- Special handling for Bitmask Modals --- 
                     $flag_options = null;
                     $is_bitmask_field = true;
                     switch ($key) {
                         case 'Flags': $flag_options = get_item_flags(); break;
                         case 'FlagsExtra': $flag_options = get_item_flags_extra(); break;
                         case 'flagsCustom': $flag_options = get_item_flags_custom(); break;
                         default: $is_bitmask_field = false; break;
                     }

                     if ($is_bitmask_field) {
                         $current_mask_value = is_numeric($value) ? (int)$value : 0;
                         $modal_id = 'modal-' . $key;
                         $field_html = "<label for='{$key}' title='{$title_attr}' class='form-label form-label-sm'>{$label}:</label>";
                         $field_html .= "<div class='input-group input-group-sm'>";
                         $field_html .= "<input type='text' class='form-control form-control-sm' id='{$key}' name='{$key}' value='{$current_mask_value}' readonly>"; // Readonly input
                         $field_html .= "<button class='btn btn-outline-secondary' type='button' data-bs-toggle='modal' data-bs-target='#{$modal_id}'>选择标志</button>";
                         $field_html .= "</div>";
                         return $field_html;
                     }
                     // --- End Bitmask Handling ---

                     // Default rendering
                     $field_html = "<label for='{$key}' title='{$title_attr}' class='form-label form-label-sm'>{$label}:</label>";
                     if ($is_textarea) {
                         $field_html .= "<textarea class='form-control form-control-sm' id='{$key}' name='{$key}' rows='2'>{$value_str}</textarea>";
                     } else {
                          if ($input_type === 'text' && preg_match('/^Flags/i', $key)) { $attributes .= ' pattern="[0-9\\-]*" inputmode="numeric"'; }
                          elseif ($input_type === 'text' && $key === 'ScriptName') { $attributes .= ' pattern="[a-zA-Z0-9_]*"'; }
                         $field_html .= "<input type='{$input_type}' class='form-control form-control-sm' id='{$key}' name='{$key}' value='{$value_str}'{$attributes}>";
                     }
                     return $field_html;
                 }

                 // Helper function to generate UPDATE SQL
                 function generate_update_sql(int $entry_id, array $original_data, array $new_data, array $field_definitions, $mode = 'diff'): string {
                     $set_clauses = [];
                     $valid_columns = array_keys($field_definitions); // Use keys from our definition as valid columns

                     foreach ($new_data as $key => $value) {
                         // Skip non-item fields, the primary key, and fields not in our definition
                         if ($key === 'save_item' || $key === 'entry' || !in_array($key, $valid_columns)) {
                             continue;
                         }

                         $original_value = $original_data[$key] ?? null;

                         // Handle type comparison carefully (e.g., "0" vs 0 vs null)
                         $is_different = false;
                         if (is_numeric($original_value) || is_numeric($value)) {
                             // Compare numerically if either is numeric
                             if ((string)$original_value !== (string)$value) {
                                 $is_different = true;
                             }
                         } elseif ($original_value !== $value) {
                              $is_different = true;
                         }

                         // In diff mode, only add if different; in full mode, add always (unless maybe identical nulls?)
                         if (($mode === 'diff' && $is_different) || $mode === 'full') {
                             // Basic quoting - might need adjustment based on DB type
                             $quoted_value = 'NULL'; // Default to NULL
                             if ($value !== null) { // Check if not NULL first
                                if (is_numeric($value)) {
                                    $quoted_value = $value; // Use numeric value directly
                                } else {
                                    // Explicitly cast to string before addslashes
                                    $quoted_value = "'" . addslashes((string)$value) . "'"; 
                                }
                             }
                            // --- Old Logic with potential error ---
                            /*
                             $quoted_value = is_numeric($value) ? $value : "'" . addslashes($value) . "'"; // Simple quote for strings
                             if ($value === null || $value === '') {
                                 // Handle potentially setting NULL if DB allows, or empty string/0
                                 // This depends on column definitions, safer to default to empty/0 for now
                                 $quoted_value = is_numeric($field_definitions[$key] ?? 'text') ? 0 : "''"; // Guess based on field group def? Risky.
                                  // A better approach would be to have column type info
                                 if (is_null($value)) { $quoted_value = 'NULL';} // Example if null is intended
                             }
                             */
                            // --- End Old Logic ---

                             $set_clauses[] = "`" . $key . "` = " . $quoted_value;
                         }
                     }

                     if (empty($set_clauses)) {
                         return ($mode === 'diff') ? '-- No changes detected --' : '-- No fields to update --';
                     }

                     return "UPDATE `item_template` SET " . implode(', ', $set_clauses) . " WHERE (`entry` = " . $entry_id . ");";
                 }

                 // Flatten field definitions for the SQL generator
                $all_field_labels = [];
                foreach ($field_groups as $group => $fields) {
                    $all_field_labels += $fields;
                }

                 // Generate SQL previews
                 $diff_sql = '-- Diff query requires changes --';
                 $full_sql = '-- Full query requires data --';
                 $submitted_data = null;

                 // Generate SQL based on current state (POST or GET)
                 if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_item'])) {
                     $submitted_data = $_POST;
                     // Ensure entry ID from POST matches the one loaded, as a basic check
                     $current_entry_id = $item_to_edit['entry'] ?? null;
                     $post_entry_id = filter_input(INPUT_POST, 'entry', FILTER_VALIDATE_INT);
                     if ($current_entry_id && $post_entry_id === $current_entry_id) {
                         $diff_sql = generate_update_sql($current_entry_id, $item_to_edit, $submitted_data, $all_field_labels, 'diff');
                         $full_sql = generate_update_sql($current_entry_id, $item_to_edit, $submitted_data, $all_field_labels, 'full');
                     } else {
                         $error_message .= " Entry ID mismatch during save attempt."; // Append to existing errors
                         $diff_sql = "-- Error: Entry ID mismatch --";
                         $full_sql = "-- Error: Entry ID mismatch --";
                     }
                 } else {
                      // On initial GET load, generate full based on current data
                      $diff_sql = '-- No changes detected (initial load) --';
                      if (!empty($item_to_edit)) { // Ensure item data is loaded
                        $full_sql = generate_update_sql($item_to_edit['entry'], $item_to_edit, $item_to_edit, $all_field_labels, 'full');
                      }
                 }
            ?>
            <!-- SQL PREVIEW SECTION (Sticky) -->
            <section id="sql-preview-section" class="mb-4 p-3 bg-dark rounded shadow-sm sticky-top">
                <div class="row align-items-center">
                    <div class="col-md-7 sql-controls">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="sqlPreviewType" id="diffQueryRadio" value="diff" checked>
                            <label class="form-check-label" for="diffQueryRadio">差异查询</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="sqlPreviewType" id="fullQueryRadio" value="full">
                            <label class="form-check-label" for="fullQueryRadio">完整查询</label>
                        </div>
                        <a href="https://www.azerothcore.org/wiki/item_template" target="_blank" class="ms-3 small">
                           <i class="fas fa-external-link-alt"></i> item_template 文档
                        </a>
                        <!-- Add Font Awesome link in <head> if you want icons: <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"> -->
                    </div>
                     <div class="col-md-5 text-end sql-buttons">
                         <button id="copySqlBtn" class="btn btn-secondary btn-sm"><i class="far fa-copy"></i> 复制</button>
                         <button id="executeSqlBtn" class="btn btn-primary btn-sm"><i class="fas fa-bolt"></i> 执行</button>
                         <a href="index.php?edit_id=<?= htmlspecialchars((string)$item_to_edit['entry']) ?><?= $cancel_link_query_string ?>" id="reloadItemBtn" class="btn btn-info btn-sm"><i class="fas fa-sync-alt"></i> 重新加载</a>
                         <a href="index.php?<?= ltrim($cancel_link_query_string, '&') ?>" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i> 关闭</a>
                     </div>
                </div>
                <div class="mt-2">
                    <pre id="sql-display"><?= htmlspecialchars($diff_sql) // Default to showing diff ?></pre>
                    <textarea id="diff-sql-data" style="display:none;"><?= htmlspecialchars($diff_sql) ?></textarea>
                    <textarea id="full-sql-data" style="display:none;"><?= htmlspecialchars($full_sql) ?></textarea>
                </div>
            </section>
            <!-- End SQL PREVIEW SECTION -->

        <?php else: ?>
            <!-- SEARCH/FILTER FORM SECTION -->
             <section class="mb-4 p-3 bg-dark rounded shadow-sm filter-form">
                 <h4 class="text-warning mb-3">搜索与筛选</h4>
                 <form method="GET" action="index.php" id="filter-form">
                     <!-- Row 1: Search & Entry Filter -->
                     <div class="row g-2 align-items-end">
                         <div class="col-md-2 col-sm-4">
                             <label for="search_type" class="form-label">搜索类型:</label>
                             <select id="search_type" name="search_type" class="form-select form-select-sm">
                                 <option value="name" <?= $search_type === 'name' ? 'selected' : '' ?>>名称</option>
                                 <option value="id" <?= $search_type === 'id' ? 'selected' : '' ?>>ID (Entry)</option>
                             </select>
                         </div>
                          <div class="col-md-4 col-sm-8">
                             <label for="search_value" class="form-label">搜索值:</label>
                             <input type="text" class="form-control form-control-sm" id="search_value" name="search_value" value="<?= htmlspecialchars($search_value) ?>" placeholder="输入ID或名称关键字...">
                         </div>
                         <div class="col-md-2 col-sm-4">
                             <label for="filter_entry_op" class="form-label">物品ID:</label>
                             <select id="filter_entry_op" name="filter_entry_op" class="form-select form-select-sm">
                                 <option value="all" <?= $filter_entry_op === 'all' ? 'selected' : '' ?>>全部</option>
                                 <option value="ge" <?= $filter_entry_op === 'ge' ? 'selected' : '' ?>>&gt;=</option>
                                 <option value="le" <?= $filter_entry_op === 'le' ? 'selected' : '' ?>>&lt;=</option>
                                 <option value="eq" <?= $filter_entry_op === 'eq' ? 'selected' : '' ?>>=</option>
                                 <option value="between" <?= $filter_entry_op === 'between' ? 'selected' : '' ?>>介于</option>
                             </select>
                         </div>
                         <div class="col-md-4 col-sm-8">
                             <label for="filter_entry_val" class="form-label">&nbsp;</label> <!-- Spacing label -->
                             <input type="text" class="form-control form-control-sm" id="filter_entry_val" name="filter_entry_val" value="<?= htmlspecialchars($filter_entry_val) ?>" placeholder="输入ID或范围 (如 1000-2000)">
                         </div>
                     </div>
                     <!-- Row 2: Other Filters, Limit, Submit -->
                     <div class="row g-2 align-items-end">
                         <div class="col-md-2 col-sm-4">
                              <label for="filter_quality" class="form-label">品质:</label>
                              <select id="filter_quality" name="filter_quality" class="form-select form-select-sm">
                                  <?php foreach ($quality_options as $id => $name): ?>
                                      <option value="<?= $id ?>" <?= ($filter_quality !== -1 && $filter_quality == $id) ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                                  <?php endforeach; ?>
                              </select>
                          </div>
                          <div class="col-md-2 col-sm-4">
                              <label for="filter_class" class="form-label">类别:</label>
                               <select id="filter_class" name="filter_class" class="form-select form-select-sm">
                                  <?php foreach ($class_options as $id => $name): ?>
                                      <option value="<?= $id ?>" <?= ($filter_class !== -1 && $filter_class == $id) ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                                  <?php endforeach; ?>
                              </select>
                          </div>
                         <div class="col-md-2 col-sm-4">
                              <label for="filter_subclass" class="form-label">子类别:</label>
                              <select id="filter_subclass" name="filter_subclass" class="form-select form-select-sm">
                                   <?php foreach ($subclass_options as $id => $name): ?>
                                      <option value="<?= $id ?>" <?= ($filter_subclass !== -1 && $filter_subclass == $id) ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                                  <?php endforeach; ?>
                              </select>
                          </div>
                          <div class="col-md-2 col-sm-4">
                             <label for="filter_itemlevel_op" class="form-label">物品等级:</label>
                             <select id="filter_itemlevel_op" name="filter_itemlevel_op" class="form-select form-select-sm">
                                  <?php foreach ($itemlevel_op_options as $op => $label): ?>
                                      <option value="<?= $op ?>" <?= $filter_itemlevel_op === $op ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                  <?php endforeach; ?>
                              </select>
                          </div>
                          <div class="col-md-2 col-sm-4">
                             <label for="filter_itemlevel_val" class="form-label">&nbsp;</label> <!-- Label for spacing -->
                             <input type="number" class="form-control form-control-sm" id="filter_itemlevel_val" name="filter_itemlevel_val" value="<?= htmlspecialchars((string)($filter_itemlevel_val ?? '')) ?>" min="0" placeholder="等级值...">
                          </div>
                          <div class="col-md-1 col-sm-2">
                             <label for="limit" class="form-label">条数:</label>
                             <input type="number" class="form-control form-control-sm" id="limit" name="limit" value="<?= htmlspecialchars((string)$limit) ?>" min="10">
                         </div>
                          <div class="col-md-1 col-sm-2">
                            <label class="form-label d-block d-sm-none">&nbsp;</label> <!-- Mobile spacing -->
                            <button type="submit" class="btn btn-primary btn-sm w-100">筛选</button>
                        </div>
                    </div>
                 </form>
             </section>
            <!-- SEARCH RESULTS SECTION -->
            <section class="mb-4 p-3 bg-dark rounded shadow-sm"> <!-- 将 section 移到 if 之前 -->
                 <div class="d-flex justify-content-between align-items-center mb-3"> <!-- 结果和按钮行 -->
                    <h4 class="text-success mb-0">结果 (<?= $totalItems ?>)</h4>
                    <button type="button" class="btn btn-success btn-sm" id="addNewItemBtn"><i class="fas fa-plus"></i> 新增</button>
                 </div>

                 <?php if (!empty($search_results)): ?> <!-- 条件开始 -->
                     <div class="table-responsive"> <!-- 表格只在有结果时显示 -->
                         <table class="table table-sm table-dark table-striped table-bordered table-hover align-middle text-nowrap">
                             <thead>
                                 <tr>
                                     <?php
                                         // Helper function to generate sort links
                                         function get_sort_link($column_name, $display_text, $current_sort_by, $current_sort_dir) {
                                             $base_params = $_GET; // Get all current GET parameters
                                             unset($base_params['sort_by'], $base_params['sort_dir'], $base_params['page']); // Remove sorting and page params

                                             $new_sort_dir = 'ASC';
                                             $sort_indicator = '';

                                             if ($current_sort_by === $column_name) {
                                                 // If currently sorting by this column, toggle direction
                                                 $new_sort_dir = ($current_sort_dir === 'ASC') ? 'DESC' : 'ASC';
                                                 $sort_indicator = ($current_sort_dir === 'ASC') ? ' ▲' : ' ▼'; // Up/Down arrow
                                             }

                                             $sort_params = ['sort_by' => $column_name, 'sort_dir' => $new_sort_dir];
                                             $query_string = http_build_query(array_merge($base_params, $sort_params));

                                             return '<a href="?' . htmlspecialchars($query_string) . '" class="text-decoration-none text-white">' . htmlspecialchars($display_text) . $sort_indicator . '</a>';
                                         }
                                     ?>
                                     <th><?= get_sort_link('entry', 'ID', $sort_by, $sort_dir) ?></th>
                                     <th>名称</th> <!-- Name sorting can be added later if needed -->
                                     <th><?= get_sort_link('ItemLevel', '等级', $sort_by, $sort_dir) ?></th>
                                     <th><?= get_sort_link('Quality', '品质', $sort_by, $sort_dir) ?></th>
                                     <th>类别</th>
                                     <th>子类别</th>
                                     <th>操作</th>
                                 </tr>
                             </thead>
                             <tbody>
                                 <?php foreach ($search_results as $item): ?>
                                 <tr>
                                     <td><?= htmlspecialchars((string)$item['entry']) ?></td>
                                     <td>
                                          <a href="https://db.nfuwow.com/80/?item=<?= $item['entry'] ?>" target="_blank" class="item-link quality-<?= $item['Quality'] ?? 0 ?>">
                                              <?= htmlspecialchars($item['name'] ?? '未知') ?>
                                          </a>
                                     </td>
                                     <td><?= htmlspecialchars((string)($item['ItemLevel'] ?? '?')) ?></td>
                                     <td class="quality-<?= $item['Quality'] ?? 0 ?>"><?= get_quality_name($item['Quality'] ?? 0) ?></td>
                                     <td><?= get_item_class_name($item['class'] ?? -1) ?></td>
                                     <td><?= get_item_subclass_name($item['class'] ?? -1, $item['subclass'] ?? -1) ?></td>
                                     <td>
                                         <?php
                                             // 构建包含当前过滤参数和 edit_id 的新查询数组
                                             $edit_link_params = $_GET; // 复制当前的 GET 参数
                                             $edit_link_params['edit_id'] = $item['entry']; // 添加或覆盖 edit_id
                                         ?>
                                         <a href="index.php?<?= http_build_query($edit_link_params) ?>" class="btn btn-sm btn-warning">编辑</a>
                                         <button type="button" class="btn btn-sm btn-danger deleteItemBtn" 
                                                 data-item-id="<?= htmlspecialchars((string)$item['entry']) ?>" 
                                                 data-item-name="<?= htmlspecialchars($item['name'] ?? '未知') ?>">
                                             删除
                                         </button>
                                     </td>
                                 </tr>
                                 <?php endforeach; ?>
                             </tbody>
                         </table>
                     </div>
                 <?php elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['limit']) && empty($search_results) && !$error_message): ?>
                     <p class="text-info">没有找到符合条件的物品。</p> <!-- "未找到"消息只在特定条件下显示 -->
                 <?php endif; ?> <!-- 条件结束 -->

            </section> <!-- section 在 endif 之后闭合 -->
            <!-- End SEARCH RESULTS SECTION -->

             <!-- Pagination Links -->
             <?php if ($totalPages > 1): ?>
             <nav aria-label="搜索结果分页" class="mt-4 d-flex justify-content-center">
                 <ul class="pagination pagination-sm">
                     <!-- Previous Page Link -->
                     <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                         <?php 
                             // Ensure sorting parameters are included in pagination links
                             $pagination_params = $_GET; 
                             unset($pagination_params['page']); // Remove old page param
                             unset($pagination_params['edit_id']); // Remove edit_id if present
                             // sort_by and sort_dir should already be in $_GET if set
                             $pagination_query_string = http_build_query($pagination_params); 
                         ?>
                         <a class="page-link" href="?<?= $pagination_query_string ?>&page=<?= $page - 1 ?>" aria-label="上一页">
                             <span aria-hidden="true">&laquo;</span>
                         </a>
                     </li>

                     <?php 
                         // Logic to display page numbers (e.g., show first, last, current, and nearby pages)
                         $max_pages_to_show = 7; // Adjust as needed
                         $start_page = max(1, $page - floor($max_pages_to_show / 2));
                         $end_page = min($totalPages, $start_page + $max_pages_to_show - 1);
                         // Adjust start page if end page calculation hits the limit early
                         $start_page = max(1, $end_page - $max_pages_to_show + 1);

                         if ($start_page > 1) {
                             echo '<li class="page-item"><a class="page-link" href="?' . $pagination_query_string . '&page=1">1</a></li>';
                             if ($start_page > 2) {
                                 echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                             }
                         }

                         for ($i = $start_page; $i <= $end_page; $i++): 
                     ?>
                         <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
                             <a class="page-link" href="?<?= $pagination_query_string ?>&page=<?= $i ?>"><?= $i ?></a>
                         </li>
                     <?php endfor; 

                         if ($end_page < $totalPages) {
                             if ($end_page < $totalPages - 1) {
                                 echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                             }
                             echo '<li class="page-item"><a class="page-link" href="?' . $pagination_query_string . '&page=' . $totalPages . '">' . $totalPages . '</a></li>';
                         }
                     ?>

                     <!-- Next Page Link -->
                     <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                         <a class="page-link" href="?<?= $pagination_query_string ?>&page=<?= $page + 1 ?>" aria-label="下一页">
                             <span aria-hidden="true">&raquo;</span>
                         </a>
                     </li>
                 </ul>
             </nav>
             <?php endif; ?>
             <!-- End Pagination Links -->

         <?php endif; ?>


        <!-- Edit Form (Only shown when $item_to_edit is true) -->
        <?php if ($item_to_edit): ?>
            <h4 class="text-warning mb-3">
                正在编辑: 
                <span class="quality-<?= $item_to_edit['Quality'] ?? 0 ?>">
                    [<?= htmlspecialchars((string)($item_to_edit['entry'] ?? '??')) ?>] <?= htmlspecialchars($item_to_edit['name'] ?? '未知物品') ?>
                </span>
                <a href="https://db.nfuwow.com/80/?item=<?= $item_to_edit['entry'] ?? 0 ?>" target="_blank" class="ms-2 small" title="在335数据库中查看">
                    <i class="fas fa-external-link-alt"></i> 查看原版
                </a>
                <!-- Add Font Awesome script in <head> if you don't have it -->
                <!-- <script src="https://kit.fontawesome.com/your-kit-id.js" crossorigin="anonymous"></script> -->
            </h4>
           <section class="mb-4 p-3 bg-dark rounded shadow-sm edit-form-section"> <!-- Removed section header H4 -->
              <form method="POST" id="item-edit-form" action="index.php?edit_id=<?= htmlspecialchars((string)$item_to_edit['entry']) ?><?= $cancel_link_query_string // Keep params on save action URL ?>">
                 <input type="hidden" name="save_item" value="1">
                 <input type="hidden" name="entry" value="<?= htmlspecialchars((string)$item_to_edit['entry']) ?>">
                  <div class="edit-form-container">
                     <?php
                     foreach ($field_groups as $group_name => $fields_in_group):
                         $field_keys = array_keys($fields_in_group);
                         $field_count = count($field_keys);
                         $col_class = 'col-md-3'; // Default: 4 columns per row
                         if (in_array($group_name, ['标志', '文本', '区域与锁'])) { $col_class = 'col-md-4'; } // 3 columns
                         if (in_array($group_name, ['抗性'])) { $col_class = 'col-md-2'; } // 6 columns
                          if (in_array($group_name, ['伤害'])) { $col_class = 'col-md-4'; } // 3 columns
                          if (in_array($group_name, ['武器与护甲'])) { $col_class = 'col-md-3'; } // 4 columns
                          if (in_array($group_name, ['脚本与其他'])) { $col_class = 'col-md-6'; } // 2 columns

                     ?>
                         <div class="form-section">
                             <h5><?= htmlspecialchars($group_name) ?></h5>
                             <div class="row g-2">
                                 <?php
                                 // --- Special handling for complex groups like Stats and Spells ---
                                 if ($group_name === '属性') {
                                     // Display counters first
                                     echo "<div class='{$col_class} mb-2'><div class='form-group'>" . render_form_field('StatsCount', $fields_in_group['StatsCount'], $item_to_edit['StatsCount'] ?? 0) . "</div></div>";
                                     echo "<div class='{$col_class} mb-2'><div class='form-group'>" . render_form_field('ScalingStatDistribution', $fields_in_group['ScalingStatDistribution'], $item_to_edit['ScalingStatDistribution'] ?? 0) . "</div></div>";
                                     echo "<div class='{$col_class} mb-2'><div class='form-group'>" . render_form_field('ScalingStatValue', $fields_in_group['ScalingStatValue'], $item_to_edit['ScalingStatValue'] ?? 0) . "</div></div>";
                                      echo "<div class='col-12'><hr class='my-1'></div>"; // Separator

                                     // Display stat pairs
                                     for ($i = 1; $i <= 10; $i++) {
                                         $type_key = 'stat_type' . $i;
                                         $value_key = 'stat_value' . $i;
                                         // Render only if the type key exists in the definition (prevents errors for missing keys)
                                         if (isset($fields_in_group[$type_key]) && isset($fields_in_group[$value_key])) {
                                             echo "<div class='col-md-3 mb-2'><div class='form-group'>" . render_form_field($type_key, $fields_in_group[$type_key], $item_to_edit[$type_key] ?? 0) . "</div></div>";
                                             echo "<div class='col-md-3 mb-2'><div class='form-group'>" . render_form_field($value_key, $fields_in_group[$value_key], $item_to_edit[$value_key] ?? 0) . "</div></div>";
                                         }
                                     }
                                 } elseif ($group_name === '法术') {
                                      for ($i = 1; $i <= 5; $i++) {
                                          $id_key = 'spellid_' . $i; $trigger_key = 'spelltrigger_' . $i; $charges_key = 'spellcharges_' . $i;
                                          $ppm_key = 'spellppmRate_' . $i; $cd_key = 'spellcooldown_' . $i; $cat_key = 'spellcategory_' . $i; $catcd_key = 'spellcategorycooldown_' . $i;
                                          // Check if the first key exists to render the group
                                          if (isset($fields_in_group[$id_key])) {
                                              echo "<div class='col-md-2 mb-2'><div class='form-group'>" . render_form_field($id_key,      $fields_in_group[$id_key],      $item_to_edit[$id_key] ?? 0) . "</div></div>";
                                              echo "<div class='col-md-2 mb-2'><div class='form-group'>" . render_form_field($trigger_key, $fields_in_group[$trigger_key], $item_to_edit[$trigger_key] ?? 0) . "</div></div>";
                                              echo "<div class='col-md-1 mb-2'><div class='form-group'>" . render_form_field($charges_key, $fields_in_group[$charges_key], $item_to_edit[$charges_key] ?? 0) . "</div></div>";
                                              echo "<div class='col-md-1 mb-2'><div class='form-group'>" . render_form_field($ppm_key,     $fields_in_group[$ppm_key],     $item_to_edit[$ppm_key] ?? 0) . "</div></div>";
                                              echo "<div class='col-md-2 mb-2'><div class='form-group'>" . render_form_field($cd_key,      $fields_in_group[$cd_key],      $item_to_edit[$cd_key] ?? -1) . "</div></div>";
                                              echo "<div class='col-md-2 mb-2'><div class='form-group'>" . render_form_field($cat_key,     $fields_in_group[$cat_key],     $item_to_edit[$cat_key] ?? 0) . "</div></div>";
                                              echo "<div class='col-md-2 mb-2'><div class='form-group'>" . render_form_field($catcd_key,   $fields_in_group[$catcd_key],   $item_to_edit[$catcd_key] ?? -1) . "</div></div>";
                                               if ($i < 5) echo "<div class='col-12'><hr class='my-1'></div>"; // Separator between spell groups
                                          }
                                      }
                                 } elseif ($group_name === '镶孔') {
                                      echo "<div class='{$col_class} mb-2'><div class='form-group'>" . render_form_field('socketBonus', $fields_in_group['socketBonus'], $item_to_edit['socketBonus'] ?? 0) . "</div></div>";
                                      echo "<div class='{$col_class} mb-2'><div class='form-group'>" . render_form_field('GemProperties', $fields_in_group['GemProperties'], $item_to_edit['GemProperties'] ?? 0) . "</div></div>";
                                      echo "<div class='col-12'><hr class='my-1'></div>"; // Separator
                                       for ($i = 1; $i <= 3; $i++) {
                                           $color_key = 'socketColor_' . $i; $content_key = 'socketContent_' . $i;
                                            if (isset($fields_in_group[$color_key]) && isset($fields_in_group[$content_key])) {
                                               echo "<div class='col-md-3 mb-2'><div class='form-group'>" . render_form_field($color_key, $fields_in_group[$color_key], $item_to_edit[$color_key] ?? 0) . "</div></div>";
                                               echo "<div class='col-md-3 mb-2'><div class='form-group'>" . render_form_field($content_key, $fields_in_group[$content_key], $item_to_edit[$content_key] ?? 0) . "</div></div>";
                                            }
                                       }
                                 } else {
                                     // --- Default rendering for other groups ---
                                     foreach ($fields_in_group as $key => $label) {
                                         $value = $item_to_edit[$key] ?? '';
                                         echo "<div class='{$col_class} mb-2'><div class='form-group'>";
                                         echo render_form_field($key, $label, $value); // Use helper function
                                         echo "</div></div>";
                                     }
                                 }
                                 ?>
                             </div> <!-- End row -->
                         </div> <!-- End form-section -->
                     <?php
                     endforeach; // End field groups
                     ?>
                 </div> <!-- End edit-form-container -->
              </form>
          </section>
        <?php endif; ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php if ($item_to_edit): // Only embed data if in edit mode ?>
    <script>
       // Pass original data and field definitions from PHP to JavaScript
       const originalItemData = <?= json_encode($item_to_edit); ?>;
       const allFieldLabels = <?= json_encode($all_field_labels); ?>; // Contains field_name => label
       const validDbFields = Object.keys(allFieldLabels); // Get just the valid field names
       const itemSubclasses = <?= json_encode(ITEM_SUBCLASSES) ?>; // Pass subclass data for JS
    </script>
    <?php endif; ?>
    <script>
    // --- JavaScript for Dynamic Subclass Filter Dropdown ---
    const filterItemSubclasses = <?= json_encode(ITEM_SUBCLASSES) ?>;
    const filterClassSelect = document.getElementById('filter_class');
    const filterSubclassSelect = document.getElementById('filter_subclass');

    function updateFilterSubclassOptions() {
        if (!filterClassSelect || !filterSubclassSelect) return;

        const selectedClassId = filterClassSelect.value;
        const currentSubclassValue = filterSubclassSelect.value; // Get current value before clearing

        filterSubclassSelect.innerHTML = ''; // Clear existing options

        // Add the default 'Any' option first
        const defaultOption = document.createElement('option');
        defaultOption.value = "-1";
        defaultOption.textContent = "所有子类别";
        filterSubclassSelect.appendChild(defaultOption);

        // Populate with relevant subclasses
        if (selectedClassId !== "-1" && filterItemSubclasses[selectedClassId]) {
            for (const subId in filterItemSubclasses[selectedClassId]) {
                const option = document.createElement('option');
                option.value = subId;
                option.textContent = filterItemSubclasses[selectedClassId][subId];
                filterSubclassSelect.appendChild(option);
            }
        } else if (selectedClassId === "-1") {
             // If 'All Classes' is selected, maybe list ALL subclasses? Or keep it simple?
             // For simplicity, we'll just show 'All Subclasses' which is already added.
             /*
             let allSubclasses = {};
             for(const classId in filterItemSubclasses) {
                 Object.assign(allSubclasses, filterItemSubclasses[classId]);
             }
             const sortedSubclassNames = Object.entries(allSubclasses).sort(([,a],[,b]) => a.localeCompare(b));
             for (const [subId, subName] of sortedSubclassNames) {
                 const option = document.createElement('option');
                 option.value = subId;
                 option.textContent = subName;
                 filterSubclassSelect.appendChild(option);
             }
             */
        }

        // Try to re-select the previously selected value if it still exists, otherwise select 'Any'
        let foundPrevious = false;
        for(let i=0; i< filterSubclassSelect.options.length; i++) {
            if (filterSubclassSelect.options[i].value === currentSubclassValue) {
                filterSubclassSelect.value = currentSubclassValue;
                foundPrevious = true;
                break;
            }
        }
         if (!foundPrevious) {
             filterSubclassSelect.value = "-1"; // Default to 'Any' if previous value not found
         }

    }

    if (filterClassSelect) {
        filterClassSelect.addEventListener('change', updateFilterSubclassOptions);
        // Initial population is handled by PHP, no need to call updateFilterSubclassOptions() on load
    }

    // --- NEW JS for SQL Preview Section ---
    const diffQueryRadio = document.getElementById('diffQueryRadio');
    const fullQueryRadio = document.getElementById('fullQueryRadio');
    const sqlDisplay = document.getElementById('sql-display');
    const diffSqlData = document.getElementById('diff-sql-data');
    const fullSqlData = document.getElementById('full-sql-data');
    const copySqlBtn = document.getElementById('copySqlBtn');
    const executeSqlBtn = document.getElementById('executeSqlBtn'); // Placeholder action
    const itemEditForm = document.getElementById('item-edit-form');

    function updateSqlPreview() {
        if (!sqlDisplay || !diffSqlData || !fullSqlData) return;
        if (diffQueryRadio && diffQueryRadio.checked) {
            sqlDisplay.textContent = diffSqlData.value;
        } else if (fullQueryRadio && fullQueryRadio.checked) {
            sqlDisplay.textContent = fullSqlData.value;
        }
    }

    if (diffQueryRadio) diffQueryRadio.addEventListener('change', updateSqlPreview);
    if (fullQueryRadio) fullQueryRadio.addEventListener('change', updateSqlPreview);

    if (copySqlBtn) {
        copySqlBtn.addEventListener('click', () => {
            const sqlToCopy = sqlDisplay.textContent;
            navigator.clipboard.writeText(sqlToCopy).then(() => {
                // Optional: Show temporary feedback
                const originalText = copySqlBtn.innerHTML;
                copySqlBtn.innerHTML = '<i class="fas fa-check"></i> 已复制!';
                setTimeout(() => { copySqlBtn.innerHTML = originalText; }, 1500);
            }).catch(err => {
                console.error('Failed to copy SQL: ', err);
                alert('复制 SQL 失败！');
            });
        });
    }

    // --- NEW: AJAX Save Logic for Execute Button ---
    if (executeSqlBtn && typeof originalItemData !== 'undefined' && typeof validDbFields !== 'undefined') {
        executeSqlBtn.addEventListener('click', async (event) => {
            event.preventDefault(); // Prevent default if it were a submit button
            executeSqlBtn.disabled = true; // Disable button during request
            executeSqlBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 处理中...';

            const entryId = originalItemData['entry'];
            const changes = {};
            validDbFields.forEach(key => {
                const element = document.getElementById(key);
                if (element) {
                    changes[key] = element.value;
                }
            });

            try {
                const formData = new FormData();
                formData.append('action', 'save_item_ajax');
                formData.append('entry', entryId);
                // Sending complex data like an object might be better as JSON
                // Convert changes object to JSON string to send
                formData.append('changes', JSON.stringify(changes)); 

                const response = await fetch('index.php', { // <-- Corrected URL
                    method: 'POST',
                    body: formData // Use FormData
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const result = await response.json(); // Expecting JSON response

                // Display feedback (you might want a dedicated feedback area)
                const feedbackDiv = document.createElement('div');
                feedbackDiv.className = `alert alert-${result.success ? 'success' : 'danger'} alert-dismissible fade show mt-3`;
                feedbackDiv.setAttribute('role', 'alert');
                // Safely set the message text and add the close button
                const messageNode = document.createTextNode(result.message || '操作完成，但未收到具体消息。'); // Use text node for safety
                const closeButton = document.createElement('button');
                closeButton.type = 'button';
                closeButton.className = 'btn-close';
                closeButton.setAttribute('data-bs-dismiss', 'alert');
                closeButton.setAttribute('aria-label', 'Close');
                feedbackDiv.innerHTML = ''; // Clear previous content
                feedbackDiv.appendChild(messageNode);
                feedbackDiv.appendChild(closeButton);
                // Insert feedback after the SQL preview section
                const sqlSection = document.getElementById('sql-preview-section');
                if (sqlSection) {
                   // Remove existing alerts first to avoid stacking
                   document.querySelectorAll('.alert-dismissible').forEach(alert => alert.remove());
                   sqlSection.parentNode.insertBefore(feedbackDiv, sqlSection.nextSibling);
                }

                 if (result.success) {
                     // Optional: Reload the page or update originalItemData and form
                     // Reloading is simplest for now to reflect saved state
                     // Consider delaying reload slightly to allow user to read message
                     // setTimeout(() => { window.location.reload(); }, 2000);
                     // OR just update the button and let user manually reload/close
                     executeSqlBtn.innerHTML = '<i class="fas fa-check"></i> 已执行';
                     // Update original data to prevent re-saving same data showing changes
                     Object.assign(originalItemData, changes);
                     handleFormInputChange(); // Recalculate SQL diff
                     setTimeout(() => { executeSqlBtn.disabled = false; executeSqlBtn.innerHTML = '<i class="fas fa-bolt"></i> 执行'; }, 3000); // Re-enable after delay
                 } else {
                     executeSqlBtn.disabled = false;
                     executeSqlBtn.innerHTML = '<i class="fas fa-bolt"></i> 执行';
                 }

            } catch (error) {
                console.error('Error executing save:', error);
                alert(`保存时发生错误: ${error.message}`);
                executeSqlBtn.disabled = false;
                executeSqlBtn.innerHTML = '<i class="fas fa-bolt"></i> 执行';
            }
        });
    }

    // Initial call in case the page loaded with a specific radio checked
    updateSqlPreview();

    // --- NEW: Function to generate SQL in JavaScript ---
    function generateSqlJS(originalData, currentFormData, mode, dbFields) {
        let setClauses = [];
        const entryId = originalData['entry']; // Assuming entry is always present

        for (const key of dbFields) {
            if (key === 'entry') continue; // Don't update primary key

            const newValueElement = document.getElementById(key); // Use the plain key for ID now

            // --- Strict Check: Ensure element exists ---
            if (!newValueElement) {
                // console.warn(`generateSqlJS: Element with ID '${key}' not found. Skipping.`);
                continue;
            }

            // --- Get New Value (Always String from Form) ---
            const newValueString = newValueElement.value;

            // --- Get Original Value and Convert to String for Comparison ---
            let originalValue = originalData[key];
            let originalValueString;
            if (originalValue === null || typeof originalValue === 'undefined') {
                originalValueString = ''; // Treat original null/undefined as empty string
            } else {
                originalValueString = String(originalValue);
            }

            // --- Comparison Logic ---
            // 1. Default comparison based on string values
            let isDifferent = originalValueString !== newValueString;

            // 2. Apply exceptions: If strings are different, check if they represent the same logical value
            if (isDifferent) { // Only check exceptions if strings differ
                if (newValueString === '' && (originalValue === 0 || originalValue === null || typeof originalValue === 'undefined')) {
                    isDifferent = false; // Empty string form value should match original 0 or null/undefined
                } else if (newValueString === '0' && originalValue === 0) {
                    isDifferent = false; // String "0" from form should match original numeric 0
                }
                // Add any other specific equivalence checks here if needed
            }

            if ((mode === 'diff' && isDifferent) || mode === 'full') {
                let quotedValue;
                // Determine input type (consider dropdowns as potentially numeric if original was)
                const isPotentiallyNumeric = (newValueElement.type === 'number') || 
                                              (typeof originalValue === 'number') || 
                                              (!isNaN(parseInt(newValueString)) && String(parseInt(newValueString)) === newValueString); // Check if string IS an integer

                if (isPotentiallyNumeric && newValueString !== '' && !isNaN(parseFloat(newValueString)) && isFinite(newValueString)) {
                    // Value looks like a number, use it directly (MySQL handles string-to-number)
                    quotedValue = newValueString; 
                } else {
                    // Value is likely a string, quote it properly
                    // If the original value was NULL and the new value is an empty string, consider setting NULL
                    if (originalValue === null && newValueString === '') {
                        // Option 1: Set to NULL (uncomment if your DB columns allow NULL)
                        // quotedValue = 'NULL'; 
                        // Option 2: Set to empty string (safer default)
                        quotedValue = "''"; 
                    } else {
                        // General string quoting
                        quotedValue = "'" + newValueString.replace(/\\/g, '\\').replace(/'/g, "\'") + "'";
                    }
                }

                setClauses.push("`" + key + "` = " + quotedValue);
            }
        }

        if (setClauses.length === 0) {
            return (mode === 'diff') ? '-- No changes detected --' : `UPDATE \`item_template\` SET \`entry\`=\`entry\` WHERE (\`entry\` = ${entryId}); -- No fields to update --`;
        }

        return `UPDATE \`item_template\` SET ${setClauses.join(', ')} WHERE (\`entry\` = ${entryId});`;
    }


    // --- NEW: Function to update SQL based on form changes ---
    function handleFormInputChange() {
         if (!itemEditForm || typeof originalItemData === 'undefined') return; // Ensure data is available

         // Create an object representing current form data (optional, could read directly in generateSqlJS)
         // let currentFormData = {};
         // for (const key of validDbFields) {
         //     const element = document.getElementById(key);
         //     if (element) currentFormData[key] = element.value;
         // } // This step might be redundant if generateSqlJS reads directly

         const newDiffSql = generateSqlJS(originalItemData, null /* Pass null, function reads form */, 'diff', validDbFields);
         const newFullSql = generateSqlJS(originalItemData, null /* Pass null, function reads form */, 'full', validDbFields);

         if (diffSqlData) diffSqlData.value = newDiffSql;
         if (fullSqlData) fullSqlData.value = newFullSql;

         updateSqlPreview(); // Refresh the visible PRE tag
    }

    // --- Attach listener to the form ---
    if (itemEditForm) {
        // Use 'change' event as it's more reliable for <select> elements
        itemEditForm.addEventListener('change', handleFormInputChange);
    }

    // --- NEW JS for Dynamic Subclass in EDIT FORM ---
    // Correct IDs to match the elements rendered by PHP
    const editClassSelect = document.getElementById('class'); 
    const editSubclassSelect = document.getElementById('subclass'); 

    function updateEditSubclassOptions() {
        if (!editClassSelect || !editSubclassSelect || typeof itemSubclasses === 'undefined') return;

        const selectedClassId = editClassSelect.value;
        // Use the actual current value from the form for re-selection attempts
        const formSubclassValue = editSubclassSelect.value; 

        editSubclassSelect.innerHTML = ''; // Clear existing options

        let subclassesForClass = [];
        if (selectedClassId !== "-1" && itemSubclasses[selectedClassId]) {
            subclassesForClass = Object.entries(itemSubclasses[selectedClassId]);
        } else {
            // Handle case where class is 'All' or invalid - maybe show nothing or a message?
            // For now, just leave it empty or add a default.
             const noneOption = document.createElement('option');
             noneOption.value = "0"; // Often 0 is 'None' or default
             noneOption.textContent = "无 (0)";
             editSubclassSelect.appendChild(noneOption);
        }

        // Sort subclasses alphabetically by name
        subclassesForClass.sort(([,a],[,b]) => a.localeCompare(b));

        // Populate with relevant subclasses
        for (const [subId, subName] of subclassesForClass) {
            const option = document.createElement('option');
            option.value = subId;
            option.textContent = `${subName} (${subId})`;
            editSubclassSelect.appendChild(option);
        }

        // Try to re-select the value that was selected *before* the class change
        let reselected = false;
        for(let i=0; i < editSubclassSelect.options.length; i++) {
            if (editSubclassSelect.options[i].value === formSubclassValue) {
                editSubclassSelect.value = formSubclassValue;
                reselected = true;
                break;
            }
        }

        // If still not selected, default to the first available option (if any)
        if (!reselected && editSubclassSelect.options.length > 0) {
             // Select the first valid option if the previous selection is gone
             // Avoid selecting placeholder like "无 (0)" automatically unless it's the only one
             if (editSubclassSelect.options[0].value !== "0" || editSubclassSelect.options.length === 1) {
                  editSubclassSelect.value = editSubclassSelect.options[0].value;
             }
        }
    }

    if (editClassSelect) {
        editClassSelect.addEventListener('change', updateEditSubclassOptions);
        // Initial population when the form loads (ensure it runs *after* itemSubclasses is defined)
        document.addEventListener('DOMContentLoaded', updateEditSubclassOptions);
    }

    // --- NEW JS for Bitmask Checkboxes --- 
    document.querySelectorAll('.bitmask-group').forEach(group => {
        const targetInputId = group.getAttribute('data-target-input');
        const targetInput = document.getElementById(targetInputId);
        const sumValueSpan = group.querySelector('.sum-value');
        const checkboxes = group.querySelectorAll('.bitmask-checkbox');

        function updateBitmaskValue() {
            if (!targetInput || !sumValueSpan) return;
            let currentSum = 0;
            checkboxes.forEach(checkbox => {
                if (checkbox.checked) {
                    currentSum |= parseInt(checkbox.value, 10); // Use bitwise OR to add flag
                }
            });
            targetInput.value = currentSum;
            sumValueSpan.textContent = currentSum;

            // Trigger change event on the hidden input to update SQL preview
            // Need to ensure handleFormInputChange is run AFTER the value is updated
            setTimeout(() => { // Use setTimeout to allow value update to settle
                 const changeEvent = new Event('change', { bubbles: true });
                 targetInput.dispatchEvent(changeEvent);
             }, 0);
        }

        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateBitmaskValue);
        });

        // Initial calculation display (optional, but good practice)
        // updateBitmaskValue(); // Call once on load? Might conflict if data loads slowly.
    });

    // --- NEW JS for Bitmask Modals --- 
    // Ensure this runs after the DOM is fully loaded
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.modal[id^="modal-"]').forEach(modalElement => {
            const modalSumValueSpan = modalElement.querySelector('.modal-sum-value');
            const checkboxes = modalElement.querySelectorAll('.modal-bitmask-checkbox');
            const confirmButton = modalElement.querySelector('.modal-confirm-button');
            const targetInputId = confirmButton ? confirmButton.getAttribute('data-target-input-id') : null;
            const mainFormInput = targetInputId ? document.getElementById(targetInputId) : null;

            // --- Defensive Check ---
            // Ensure all crucial elements are found within this specific modal
            if (!modalSumValueSpan || !checkboxes || checkboxes.length === 0 || !confirmButton || !mainFormInput) {
                console.error('Modal initialization failed for:', modalElement.id, '. Required elements not found or empty checkbox list.');
                // Optionally disable the trigger button if setup fails
                const triggerButton = document.querySelector(`[data-bs-target='#${modalElement.id}']`);
                if(triggerButton) {
                    triggerButton.disabled = true;
                    triggerButton.title = 'Modal setup failed.';
                }
                return; // Stop processing this modal
            }
            // --- End Defensive Check ---

            let currentModalSum = 0;

            // Function to calculate sum within the modal
            function calculateModalSum() {
                currentModalSum = 0;
                checkboxes.forEach(checkbox => {
                    if (checkbox.checked) {
                        // Ensure value is parsed as integer before bitwise OR
                        currentModalSum |= parseInt(checkbox.value, 10) || 0;
                    }
                });
                // Update the displayed sum inside the modal
                modalSumValueSpan.textContent = currentModalSum;
            }

            // Add listeners to checkboxes within the modal
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', calculateModalSum);
            });

            // Add listener for the confirm button
            confirmButton.addEventListener('click', () => {
                // 1. Update the main form's hidden input value
                mainFormInput.value = currentModalSum;

                // 2. Trigger the 'change' event on the main form input
                //    This is crucial for other scripts (like SQL preview) that listen for changes.
                const changeEvent = new Event('change', { bubbles: true });
                mainFormInput.dispatchEvent(changeEvent);

                // 3. Close the modal using Bootstrap's JavaScript API
                //    Make sure Bootstrap's JS is loaded correctly for this to work.
                const modalInstance = bootstrap.Modal.getInstance(modalElement);
                if (modalInstance) {
                    modalInstance.hide();
                } else {
                    console.warn('Bootstrap Modal instance not found for', modalElement.id);
                }
            });

            // Add listener to recalculate sum when the modal is about to be shown
            // This ensures the sum is correct based on the checkboxes' state when opened.
            modalElement.addEventListener('show.bs.modal', () => {
                calculateModalSum();
            });

        }); // End loop through each modal
    }); // End DOMContentLoaded listener

    </script>
<?php endif; ?>

<!-- MODALS FOR FLAG SELECTION -->
<?php if ($item_to_edit): ?>
    <?php
    $flag_fields_config = [
        'Flags' => ['label' => '标记 (Flags)', 'options' => get_item_flags()],
        'FlagsExtra' => ['label' => '额外标记 (FlagsExtra)', 'options' => get_item_flags_extra()],
        'flagsCustom' => ['label' => '自定义标记 (flagsCustom)', 'options' => get_item_flags_custom()]
    ];

    foreach ($flag_fields_config as $key => $config):
        $modal_id = 'modal-' . $key;
        $current_value = $item_to_edit[$key] ?? 0;
        $current_value = is_numeric($current_value) ? (int)$current_value : 0;
        $flag_options = $config['options'];
        ksort($flag_options, SORT_NUMERIC); // Sort by flag value
    ?>
    <div class="modal fade" id="<?= $modal_id ?>" tabindex="-1" aria-labelledby="<?= $modal_id ?>Label" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content bg-dark text-light">
                <div class="modal-header">
                    <h5 class="modal-title" id="<?= $modal_id ?>Label">选择 <?= htmlspecialchars($config['label']) ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>当前选中值的总和: <strong class="modal-sum-value"><?= $current_value ?></strong></p>
                    <hr>
                    <div class="flag-checkbox-container">
                        <?php foreach ($flag_options as $flag_value => $flag_label): ?>
                            <?php
                            $flag_value = (int)$flag_value;
                            $checked = ($current_value & $flag_value) ? 'checked' : '';
                            $checkbox_id_modal = $modal_id . '_' . $flag_value;
                            ?>
                            <div class="form-check">
                                <input class="form-check-input modal-bitmask-checkbox" type="checkbox" id="<?= $checkbox_id_modal ?>" value="<?= $flag_value ?>" <?= $checked ?>>
                                <label class="form-check-label" for="<?= $checkbox_id_modal ?>" title="Value: <?= $flag_value ?>">
                                    <?= htmlspecialchars($flag_label) ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="button" class="btn btn-primary modal-confirm-button" data-target-input-id="<?= $key ?>">确定</button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>
<!-- END MODALS -->

<!-- Add New Item Modal -->
<div class="modal fade" id="newItemModal" tabindex="-1" aria-labelledby="newItemModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header">
                <h5 class="modal-title" id="newItemModalLabel">新增物品</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="newItemErrorAlert" class="alert alert-danger d-none" role="alert"></div>
                <div class="mb-3">
                    <label for="copyItemIdInput" class="form-label">复制物品 ID (可选):</label>
                    <input type="number" class="form-control" id="copyItemIdInput" placeholder="输入要复制数据的物品 Entry ID">
                    <div class="form-text">如果留空，将使用默认模板创建。</div>
                </div>
                <div class="mb-3">
                    <label for="newItemIdInput" class="form-label">新建物品 ID (必填):</label>
                    <input type="number" class="form-control" id="newItemIdInput" required min="1" placeholder="输入新物品的 Entry ID">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                <button type="button" class="btn btn-primary" id="confirmCreateBtn">确认创建</button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Item Confirmation Modal -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteConfirmModalLabel">确认删除</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="deleteErrorAlert" class="alert alert-danger d-none" role="alert"></div>
                <p>您确定要删除以下物品吗？</p>
                <p><strong id="deleteItemInfo"></strong></p>
                <p class="text-danger">此操作不可撤销！</p>
            </div>
            <div class="modal-footer">
                <input type="hidden" id="deleteItemIdHidden"> 
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">确认删除</button>
            </div>
        </div>
    </div>
</div>

<script>
    // --- Add New Item Modal Logic --- (Existing)
    // Add event listener for the Add New button
    const addNewItemBtn = document.getElementById('addNewItemBtn');
    const newItemModalElement = document.getElementById('newItemModal');
    const newItemModal = newItemModalElement ? new bootstrap.Modal(newItemModalElement) : null;
    const confirmCreateBtn = document.getElementById('confirmCreateBtn');
    const copyItemIdInput = document.getElementById('copyItemIdInput');
    const newItemIdInput = document.getElementById('newItemIdInput');
    const newItemErrorAlert = document.getElementById('newItemErrorAlert');

    if (addNewItemBtn && newItemModal) {
        addNewItemBtn.addEventListener('click', () => {
            // Reset fields and error message when opening modal
            if(copyItemIdInput) copyItemIdInput.value = '';
            if(newItemIdInput) newItemIdInput.value = '';
            if(newItemErrorAlert) {
                newItemErrorAlert.classList.add('d-none');
                newItemErrorAlert.textContent = '';
            }
            newItemModal.show();
        });
    }

    if (confirmCreateBtn && copyItemIdInput && newItemIdInput && newItemErrorAlert) {
        confirmCreateBtn.addEventListener('click', async () => {
            const copyId = copyItemIdInput.value.trim();
            const newId = newItemIdInput.value.trim();

            // Basic validation
            if (!newId || parseInt(newId) <= 0) {
                newItemErrorAlert.textContent = '请输入有效的新建物品 ID。';
                newItemErrorAlert.classList.remove('d-none');
                return;
            }
            if (copyId && parseInt(copyId) <= 0) {
                 newItemErrorAlert.textContent = '输入的复制物品 ID 无效。';
                 newItemErrorAlert.classList.remove('d-none');
                 return;
            }

            // Disable button, show loading state
            confirmCreateBtn.disabled = true;
            confirmCreateBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> 处理中...';
            newItemErrorAlert.classList.add('d-none');

            try {
                const formData = new FormData();
                formData.append('action', 'create_item_ajax');
                formData.append('new_item_id', newId);
                if (copyId) {
                    formData.append('copy_item_id', copyId);
                }

                const response = await fetch('index.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success && result.new_id) {
                    newItemModal.hide();
                    window.location.href = `index.php?edit_id=${result.new_id}`;
                    // No need to re-enable button as we are navigating away
                } else {
                    newItemErrorAlert.textContent = result.message || '创建物品时发生未知错误。';
                    newItemErrorAlert.classList.remove('d-none');
                    // Re-enable button on error
                    confirmCreateBtn.disabled = false;
                    confirmCreateBtn.innerHTML = '确认创建';
                }

            } catch (error) {
                console.error('Error creating item:', error);
                newItemErrorAlert.textContent = `客户端错误: ${error.message}`;
                newItemErrorAlert.classList.remove('d-none');
                 // Re-enable button on error
                 confirmCreateBtn.disabled = false;
                 confirmCreateBtn.innerHTML = '确认创建';
            }
        });
    }

    // --- Delete Item Modal Logic --- (New)
    const deleteConfirmModalElement = document.getElementById('deleteConfirmModal');
    const deleteConfirmModal = deleteConfirmModalElement ? new bootstrap.Modal(deleteConfirmModalElement) : null;
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    const deleteItemInfo = document.getElementById('deleteItemInfo');
    const deleteItemIdHidden = document.getElementById('deleteItemIdHidden');
    const deleteErrorAlert = document.getElementById('deleteErrorAlert');
    const resultsTableBody = document.querySelector('.table tbody'); // Assuming only one results table

    // Add event listeners to all delete buttons using event delegation on the table body
    if (resultsTableBody) {
        resultsTableBody.addEventListener('click', (event) => {
            if (event.target && event.target.classList.contains('deleteItemBtn')) {
                const button = event.target;
                const itemId = button.getAttribute('data-item-id');
                const itemName = button.getAttribute('data-item-name');

                if (itemId && deleteConfirmModal && deleteItemInfo && deleteItemIdHidden && deleteErrorAlert) {
                    // Populate modal
                    deleteItemInfo.textContent = `ID: ${itemId} - ${itemName}`;
                    deleteItemIdHidden.value = itemId;
                    deleteErrorAlert.classList.add('d-none'); // Hide error on open
                    deleteErrorAlert.textContent = '';

                    // Show modal
                    deleteConfirmModal.show();
                }
            }
        });
    }

    // Add listener for the final confirm delete button
    if (confirmDeleteBtn && deleteConfirmModal && deleteItemIdHidden && deleteErrorAlert) {
        confirmDeleteBtn.addEventListener('click', async () => {
            const itemIdToDelete = deleteItemIdHidden.value;

            if (!itemIdToDelete) {
                deleteErrorAlert.textContent = '无法获取要删除的物品 ID。';
                deleteErrorAlert.classList.remove('d-none');
                return;
            }

            // Disable button, show loading
            confirmDeleteBtn.disabled = true;
            confirmDeleteBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> 删除中...';
            deleteErrorAlert.classList.add('d-none');

            try {
                const formData = new FormData();
                formData.append('action', 'delete_item_ajax');
                formData.append('entry', itemIdToDelete); // Use 'entry' to match likely backend expectation

                const response = await fetch('index.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    deleteConfirmModal.hide();
                    // Find and remove the table row
                    const rowToDelete = document.querySelector(`.deleteItemBtn[data-item-id="${itemIdToDelete}"]`)?.closest('tr');
                    if (rowToDelete) {
                        rowToDelete.remove();
                    }
                    // Optional: Show a success message on the main page
                    const mainSuccessDiv = document.querySelector('.alert-success'); // Find existing success div
                    if (mainSuccessDiv) {
                        mainSuccessDiv.textContent = result.message || '物品删除成功。';
                        mainSuccessDiv.classList.remove('d-none');
                    } else {
                        // Or create one if it doesn't exist (adapt structure as needed)
                         alert(result.message || '物品删除成功。');
                    }
                    // No need to re-enable button as modal is hidden
                } else {
                    deleteErrorAlert.textContent = result.message || '删除物品时发生未知错误。';
                    deleteErrorAlert.classList.remove('d-none');
                    // Re-enable button on error
                    confirmDeleteBtn.disabled = false;
                    confirmDeleteBtn.innerHTML = '确认删除';
                }

            } catch (error) {
                console.error('Error deleting item:', error);
                deleteErrorAlert.textContent = `客户端错误: ${error.message}`;
                deleteErrorAlert.classList.remove('d-none');
                 // Re-enable button on error
                 confirmDeleteBtn.disabled = false;
                 confirmDeleteBtn.innerHTML = '确认删除';
            }
        });
    }

    // --- End Delete Item Modal Logic ---

    // --- NEW: Update Entry Filter Placeholder --- 
    const filterEntryOpSelect = document.getElementById('filter_entry_op');
    const filterEntryValInput = document.getElementById('filter_entry_val');

    function updateEntryPlaceholder() {
        if (!filterEntryOpSelect || !filterEntryValInput) return;

        const selectedOp = filterEntryOpSelect.value;
        if (selectedOp === 'between') {
            filterEntryValInput.placeholder = '输入范围 (最小值-最大值)';
        } else {
            filterEntryValInput.placeholder = '输入ID或范围';
        }
    }

    if (filterEntryOpSelect) {
        filterEntryOpSelect.addEventListener('change', updateEntryPlaceholder);
        // Call once on load to set initial placeholder
        updateEntryPlaceholder();
    }
    // --- END: Update Entry Filter Placeholder ---

    // --- COPY SQL BUTTON LOGIC ---
    if (copySqlBtn && sqlDisplay) {
        copySqlBtn.addEventListener('click', async () => { // Make async
            const textToCopy = sqlDisplay.textContent;
             const originalText = copySqlBtn.innerHTML; // Store original button text

             try {
                 if (navigator.clipboard && window.isSecureContext) {
                     // Modern way: Clipboard API (preferred)
                     await navigator.clipboard.writeText(textToCopy);
                     copySqlBtn.innerHTML = '<i class="fas fa-check"></i> 已复制!'; // Feedback
                 } else {
                     // Fallback way: execCommand
                     const textArea = document.createElement('textarea');
                     textArea.value = textToCopy;
                     // Make the textarea out of viewport
                     textArea.style.position = 'fixed';
                     textArea.style.top = '-9999px';
                     textArea.style.left = '-9999px';
                     document.body.appendChild(textArea);
                     textArea.focus();
                     textArea.select();
                     try {
                         const successful = document.execCommand('copy');
                         if (successful) {
                            copySqlBtn.innerHTML = '<i class="fas fa-check"></i> 已复制!'; // Feedback
                         } else {
                             throw new Error('execCommand returned false.');
                         }
                     } catch (err) {
                         console.error('Fallback copy failed:', err);
                         alert('复制 SQL 失败！(Fallback)');
                         // Revert button text on fallback failure
                         copySqlBtn.innerHTML = originalText;
                         return; // Exit early
                     } finally {
                         document.body.removeChild(textArea);
                     }
                 }

                 // Revert button text after a short delay
                 copySqlBtn.disabled = true; // Disable briefly
                 setTimeout(() => {
                     copySqlBtn.innerHTML = originalText;
                     copySqlBtn.disabled = false;
                 }, 1500); // 1.5 seconds

             } catch (err) {
                 console.error('Failed to copy SQL:', err);
                 alert('复制 SQL 失败！请检查浏览器权限或安全设置。浏览器控制台可能有更多信息。');
                 // Ensure button text is reverted on modern API failure too
                 copySqlBtn.innerHTML = originalText;
                 copySqlBtn.disabled = false;
             }
        });
    } else {
         if (!copySqlBtn) console.error("Copy SQL button not found.");
         if (!sqlDisplay) console.error("SQL display element not found.");
    }

</script>
</body>
</html> 