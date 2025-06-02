<?php
// creature_editor/index.php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
session_start();

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// --- AJAX Request Handler Placeholder ---
// We will add AJAX handlers for save/delete later
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Ensure config, db, and functions are loaded for ALL AJAX actions
        $config = require_once __DIR__ . '/../config.php';
        require_once __DIR__ . '/../bag_query/db.php';
        require_once __DIR__ . '/functions.php';
    header('Content-Type: application/json; charset=utf-8');

    $response = ['success' => false, 'message' => '未知的 AJAX 错误。'];
    $pdo_W = null;

    // Centralized Login Check and DB Connection for AJAX
        $adminConf = $config['admin'] ?? null;
        $isLoggedInAjax = (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true);
        if (!$isLoggedInAjax) {
            $response['message'] = '错误：未登录或会话超时。';
            echo json_encode($response);
            exit;
        }

        try {
            $connections = connect_databases();
            if (!isset($connections['db_W'])) {
                throw new Exception("World 数据库连接未配置或失败。");
            }
            $pdo_W = $connections['db_W'];
        $pdo_W->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Route based on action
        switch ($_POST['action']) {
            // --- Handle Add Creature AJAX Request ---
            case 'add_creature_ajax':
                 $new_id = filter_input(INPUT_POST, 'new_creature_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                 $copy_id = filter_input(INPUT_POST, 'copy_creature_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]); // Optional

                 if (!$new_id) {
                     $response['message'] = '错误：新建生物 ID 无效或未提供。';
                     break;
                 }

                 // Check if new ID already exists
                 $stmt_check = $pdo_W->prepare("SELECT 1 FROM `creature_template` WHERE `entry` = :new_id LIMIT 1");
                 $stmt_check->execute([':new_id' => $new_id]);
                 if ($stmt_check->fetch()) {
                     $response['message'] = "错误：生物 ID {$new_id} 已存在。";
                     break;
                 }

                 if ($copy_id) {
                     // --- Copy from existing creature ---
                     $stmt_copy = $pdo_W->prepare("SELECT * FROM `creature_template` WHERE `entry` = :copy_id LIMIT 1");
                     $stmt_copy->execute([':copy_id' => $copy_id]);
                     $data_to_copy = $stmt_copy->fetch(PDO::FETCH_ASSOC);

                     if (!$data_to_copy) {
                         $response['message'] = "错误：无法找到要复制的生物 ID {$copy_id}。";
                         break;
                     }

                     // Replace entry ID and unset primary key if it exists in columns (good practice)
                     $data_to_copy['entry'] = $new_id;
                     // Ensure all columns are present for insertion (though SELECT * should get them)
                     $columns = array_keys($data_to_copy);
                     $placeholders = array_map(fn($col) => ':' . $col, $columns);
                     $sql = "INSERT INTO `creature_template` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $placeholders) . ")";
                     $params = [];
                     foreach($data_to_copy as $key => $value){
                         $params[':' . $key] = $value;
                     }

                 } else {
                     // --- Create from blank template ---
                     // Use a modified version of the user's template with parameter binding
                     $sql = "INSERT INTO `creature_template` (`entry`, `difficulty_entry_1`, `difficulty_entry_2`, `difficulty_entry_3`, `KillCredit1`, `KillCredit2`, `name`, `subname`, `IconName`, `gossip_menu_id`, `minlevel`, `maxlevel`, `exp`, `faction`, `npcflag`, `speed_walk`, `speed_run`, `speed_swim`, `detection_range`, `scale`, `rank`, `dmgschool`, `DamageModifier`, `BaseAttackTime`, `RangeAttackTime`, `BaseVariance`, `RangeVariance`, `unit_class`, `unit_flags`, `unit_flags2`, `dynamicflags`, `family`, `trainer_type`, `trainer_spell`, `trainer_class`, `trainer_race`, `type`, `type_flags`, `lootid`, `pickpocketloot`, `skinloot`, `PetSpellDataId`, `VehicleId`, `mingold`, `maxgold`, `AIName`, `MovementType`, `HoverHeight`, `HealthModifier`, `ManaModifier`, `ArmorModifier`, `ExperienceModifier`, `RacialLeader`, `movementId`, `RegenHealth`, `mechanic_immune_mask`, `spell_school_immune_mask`, `flags_extra`, `ScriptName`, `VerifiedBuild`) VALUES (:entry, 0, 0, 0, 0, 0, :name, '', '', 0, 1, 1, 0, 35, 0, 1, 1.14286, 1, 20, 1, 0, 0, 1, 2000, 2000, 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 7, 0, 0, 0, 0, 0, 0, 0, 0, 'SmartAI', 0, 1, 1, 1, 1, 1, 0, 0, 1, 0, 0, 0, '', 12340)";
                     // Basic default parameters
                     $params = [
                         ':entry' => $new_id,
                         ':name' => 'New Creature ' . $new_id // Default name
                         // Other fields use default values from the SQL string directly
                     ];
                 }

                 // Execute Insert
                 $stmt_insert = $pdo_W->prepare($sql);
                 $execute_success = $stmt_insert->execute($params);

                 if ($execute_success) {
                     $response['success'] = true;
                     $response['message'] = "生物 (ID: {$new_id}) 创建成功" . ($copy_id ? " (复制自 ID: {$copy_id})" : " (使用空白模板)") . "。";
                     $response['new_id'] = $new_id; // Send back new ID for redirection
                 } else {
                     $errorInfo = $stmt_insert->errorInfo();
                     $response['message'] = "数据库插入失败: " . ($errorInfo[2] ?? 'Unknown error');
                     error_log("AJAX Add Creature SQL Error: " . print_r($errorInfo, true) . " SQL: " . $sql . " PARAMS: " . print_r($params, true));
                 }
                break; // End add_creature_ajax case

            // --- Handle Save Creature AJAX Request ---
            case 'save_creature_ajax':
                $entry_id = filter_input(INPUT_POST, 'entry', FILTER_VALIDATE_INT);
                $changes_json = $_POST['changes'] ?? null;
                $changes = $changes_json ? json_decode($changes_json, true) : null;

                if (!$entry_id || $entry_id <= 0 || !is_array($changes) || empty($changes)) {
                    $response['message'] = '错误：提交的生物数据无效或为空。';
                    break; // Go to final echo
                }

                // (Existing save_creature_ajax logic using $changes...)
            // 4. Define Valid Columns
            $valid_columns = get_creature_template_columns();
            if (empty($valid_columns)) {
                 throw new Exception("无法获取有效的 creature_template 列列表。");
            }
            $valid_columns_map = array_flip($valid_columns);

            // 5. Build Prepared Statement
            $set_clauses = [];
            $params = [':entry_id' => $entry_id]; // Add entry_id for WHERE clause

            foreach ($changes as $key => $value) {
                if (!isset($valid_columns_map[$key])) {
                    error_log("AJAX Save Creature: Invalid column skipped: " . $key); // Log skipped columns
                    continue;
                }
                $is_likely_numeric_col = preg_match('/^(entry|difficulty_entry_|killcredit\d|modelid\d?|gossip_menu_id|minlevel|maxlevel|exp|exp_req|faction|npcflag|npcflag2|speed_walk|speed_run|speed_fly|scale|rank|dmgschool|baseattacktime|rangeattacktime|unit_class|unit_flags|unit_flags2|dynamicflags|family|trainer_type|trainer_spell|trainer_class|trainer_race|type|type_flags|type_flags2|lootid|pickpocketloot|skinloot|resistance\d|spell\d|petspelldataid|vehicleid|mingold|maxgold|movementtype|inhabittype|hoverheight|healthmodifier|manamodifier|armormodifier|experiencemodifier|movementid|regenhealth|mechanic_immune_mask|flags_extra|verifiedbuild)$/i', $key);
                if ($is_likely_numeric_col) {
                    if ($value === '' || $value === null || !is_numeric($value)) {
                        $processed_value = 0;
                    } else {
                        $processed_value = strpos((string)$value, '.') !== false ? (float)$value : (int)$value;
                    }
                } else {
                    $processed_value = ($value === null) ? null : (string)$value;
                }
                $set_clauses[] = "`" . $key . "` = :" . $key;
                $params[':' . $key] = $processed_value;
            }

            // 6. Execute Update
            if (empty($set_clauses)) {
                    $response['success'] = true;
                $response['message'] = '没有检测到需要更新的有效字段。';
            } else {
                $sql = "UPDATE `creature_template` SET " . implode(', ', $set_clauses) . " WHERE `entry` = :entry_id";
                $stmt = $pdo_W->prepare($sql);
                $execute_success = $stmt->execute($params);

                if ($execute_success) {
                    $affected_rows = $stmt->rowCount();
                    $response['success'] = true;
                    $response['message'] = "生物 (ID: {$entry_id}) 更新成功。影响行数: {$affected_rows}。";
                } else {
                    $errorInfo = $stmt->errorInfo();
                    $response['message'] = "数据库更新失败: " . ($errorInfo[2] ?? 'Unknown error');
                    error_log("AJAX Save Creature SQL Error: " . print_r($errorInfo, true) . " SQL: " . $sql . " PARAMS: " . print_r($params, true));
                }
            }
                break;

            // --- Handle Add Creature Model AJAX Request ---
            case 'add_creature_model':
                $creature_id = filter_input(INPUT_POST, 'creature_id', FILTER_VALIDATE_INT);
                $display_id = filter_input(INPUT_POST, 'display_id', FILTER_VALIDATE_INT);
                $scale = filter_input(INPUT_POST, 'scale', FILTER_VALIDATE_FLOAT);
                $probability = filter_input(INPUT_POST, 'probability', FILTER_VALIDATE_FLOAT);
                $verified_build = filter_input(INPUT_POST, 'verifiedbuild', FILTER_VALIDATE_INT);
                // Treat 0 or false from filter as null, but allow positive integers
                if ($verified_build === false || $verified_build <= 0) {
                    $verified_build = null;
                }

                if (!$creature_id || !$display_id || $scale === false || $probability === false) {
                    $response['message'] = '错误：提交的新模型数据无效。';
                } else {
                    $response = add_creature_model_entry($pdo_W, $creature_id, $display_id, $scale, $probability, $verified_build);
                }
                break;

            // --- Handle Edit Creature Model AJAX Request ---
            case 'edit_creature_model':
                $creature_id = filter_input(INPUT_POST, 'creature_id', FILTER_VALIDATE_INT);
                $idx = filter_input(INPUT_POST, 'idx', FILTER_VALIDATE_INT);
                $display_id = filter_input(INPUT_POST, 'display_id', FILTER_VALIDATE_INT);
                $scale = filter_input(INPUT_POST, 'scale', FILTER_VALIDATE_FLOAT);
                $probability = filter_input(INPUT_POST, 'probability', FILTER_VALIDATE_FLOAT);
                $verified_build = filter_input(INPUT_POST, 'verifiedbuild', FILTER_VALIDATE_INT);
                // Treat 0 or false from filter as null, but allow positive integers
                if ($verified_build === false || $verified_build <= 0) {
                    $verified_build = null;
                }

                if (!$creature_id || $idx === false || $idx < 0 || !$display_id || $scale === false || $probability === false) {
                    $response['message'] = '错误：提交的编辑模型数据无效。';
                } else {
                    $response = edit_creature_model_entry($pdo_W, $creature_id, $idx, $display_id, $scale, $probability, $verified_build);
                }
                break;

            // --- Handle Delete Creature Model AJAX Request ---
            case 'delete_creature_model':
                $creature_id = filter_input(INPUT_POST, 'creature_id', FILTER_VALIDATE_INT);
                $idx = filter_input(INPUT_POST, 'idx', FILTER_VALIDATE_INT);

                if (!$creature_id || $idx === false || $idx < 0) {
                    $response['message'] = '错误：提交的删除模型数据无效。';
                } else {
                    $response = delete_creature_model_entry($pdo_W, $creature_id, $idx);
                }
                break;

            // --- Handle Delete Creature AJAX Request ---
            case 'delete_creature_ajax':
                $creature_id = filter_input(INPUT_POST, 'creature_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

                if (!$creature_id) {
                    $response['message'] = '错误：无效的生物 ID。校验失败。'; // More specific error
                    break;
                }

                try {
                    $sql = "DELETE FROM `creature_template` WHERE `entry` = :creature_id";
                    $stmt = $pdo_W->prepare($sql);
                    // $stmt->bindParam(':creature_id', $creature_id, PDO::PARAM_INT); // bindParam binds variable, execute binds value
                    $execute_success = $stmt->execute([':creature_id' => $creature_id]);

                    if ($execute_success) {
                        $affected_rows = $stmt->rowCount();
                        if ($affected_rows > 0) {
                            $response['success'] = true;
                            $response['message'] = "生物 (ID: {$creature_id}) 删除成功。";
                        } else {
                            // Technically success, but nothing was deleted (maybe already gone?)
                            $response['success'] = true; // Treat as success even if 0 rows affected
                            $response['message'] = "未找到要删除的生物 (ID: {$creature_id}) 或删除未影响任何行。";
                        }
                    } else {
                        $errorInfo = $stmt->errorInfo();
                        $response['message'] = "数据库删除失败: " . ($errorInfo[2] ?? 'Unknown error');
                        error_log("AJAX Delete Creature SQL Error: " . print_r($errorInfo, true) . " SQL: " . $sql . " ID: " . $creature_id);
                    }
        } catch (PDOException $e) {
                    // Catch potential foreign key constraint errors etc.
                    $response['message'] = "删除生物时发生数据库错误。"; // Generic message first
                     error_log("AJAX Delete Creature PDO Exception: " . $e->getMessage() . " ID: " . $creature_id);
                    // Provide more specific user message based on error code if needed
                    if (strpos($e->getMessage(), '1451') !== false || str_contains(strtolower($e->getMessage()), 'foreign key constraint')) { // Foreign Key constraint
                       $response['message'] = "删除失败：此生物可能被其他数据引用 (例如，在生物生成点、任务或脚本中)，无法直接删除。请先移除相关引用。";
                    }
                }
                break;

            default:
                $response['message'] = '未知的 AJAX 动作。';
                break;
        }

        } catch (PDOException $e) {
        error_log("AJAX Action PDO Error: " . $e->getMessage());
            $response['message'] = "数据库错误: " . $e->getMessage();
        } catch (Exception $e) {
        error_log("AJAX Action General Error: " . $e->getMessage());
            $response['message'] = "发生一般错误: " . $e->getMessage();
        }

    // --- Send Final Response ---
        echo json_encode($response);
        exit;
}
// --- END AJAX ---

// --- Config & DB ---
$config = require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../bag_query/db.php'; // For database connection
require_once __DIR__ . '/functions.php';       // For creature specific functions (will be created next)

// --- Login Logic --- Start ---
$adminConf = $config['admin'] ?? null;
$isLoggedIn = false;
$loginError = '';

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

if (!$adminConf || !isset($adminConf['username']) || !isset($adminConf['password_hash'])) {
    $loginError = '管理后台配置不完整，无法登录。';
} else {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_username'])) { // Handle login form submission
        $username = $_POST['login_username'];
        $password = $_POST['login_password'];
        if ($username === $adminConf['username'] && password_verify($password, $adminConf['password_hash'])) {
            $_SESSION['admin_logged_in'] = true;
            header('Location: index.php'); // Redirect to clear POST data
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

// --- Variables for Logged-in State ---
$pdo_W = null;
$error_message = '';
$success_message = '';
$search_results = [];
$creature_to_edit = null; // Placeholder for edit mode later
$totalItems = 0;
$totalPages = 0;
$pagination_query_string = '';
// --- NEW: Get dropdown options early ---
$rank_options = function_exists('get_creature_ranks') ? get_creature_ranks(false) : [0 => '等级 N/A']; // Pass false for edit form
$type_options = function_exists('get_creature_types') ? get_creature_types(false) : [0 => '类型 N/A']; // Pass false for edit form
$creature_template_columns = function_exists('get_creature_template_columns') ? get_creature_template_columns() : []; // Get valid columns
$cancel_link_query_string = ''; // Initialize cancel link query string

// --- Get Search/Filter Parameters (Only if logged in) ---
$search_type = 'name'; // Default search type
$search_value = '';
$page = 1;
$limit = 50; // Default limit
// --- NEW: Get filter parameters ---
$filter_minlevel = isset($_GET['filter_minlevel']) && $_GET['filter_minlevel'] !== '' ? max(0, (int)$_GET['filter_minlevel']) : null;
$filter_maxlevel = isset($_GET['filter_maxlevel']) && $_GET['filter_maxlevel'] !== '' ? max(0, (int)$_GET['filter_maxlevel']) : null;
$filter_rank = isset($_GET['filter_rank']) ? (int)$_GET['filter_rank'] : -1;
$filter_type = isset($_GET['filter_type']) ? (int)$_GET['filter_type'] : -1;
$filter_faction = isset($_GET['filter_faction']) && $_GET['filter_faction'] !== '' ? (int)$_GET['filter_faction'] : null;
$filter_npcflag = isset($_GET['filter_npcflag']) && $_GET['filter_npcflag'] !== '' ? (int)$_GET['filter_npcflag'] : null;
// --- NEW: Get Sort Parameters ---
$sort_by = $_GET['sort_by'] ?? 'entry'; // Default sort column
$sort_dir = strtoupper($_GET['sort_dir'] ?? 'ASC'); // Default sort direction
// Validate sort direction
if ($sort_dir !== 'ASC' && $sort_dir !== 'DESC') {
    $sort_dir = 'ASC'; // Default to ASC if invalid
}
// Allowed sort columns (should match columns allowed in search_creatures and be lowercase)
$allowed_sort_columns = ['entry', 'name', 'minlevel', 'maxlevel', 'faction', 'npcflag'];
if (!in_array($sort_by, $allowed_sort_columns)) {
    $sort_by = 'entry'; // Default if invalid column
}

if ($isLoggedIn) {
    // --- Get Parameters from GET request ---
    $search_type = $_GET['search_type'] ?? 'name';
    $search_value = trim($_GET['search_value'] ?? '');
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? max(10, (int)$_GET['limit']) : 50;

    // --- Validate Search Type ---
    $valid_search_types = ['id', 'name']; // Basic search types for now
    if (!in_array($search_type, $valid_search_types)) {
        $search_type = 'name';
    }

    // --- Connect to DB and Perform Search ---
    try {
        $connections = connect_databases();
        if (!isset($connections['db_W'])) {
             throw new Exception("World 数据库连接未配置或失败。");
        }
        $pdo_W = $connections['db_W'];

        // --- Call Search Function (Placeholder call) ---
        // We'll implement search_creatures in functions.php next
        if (function_exists('search_creatures')) {
             $search_data = search_creatures(
                 $pdo_W,
                 $search_type,
                 $search_value,
                 $limit,
                 $page,
                 // --- NEW: Pass filters ---
                 $filter_minlevel,
                 $filter_maxlevel,
                 $filter_rank,
                 $filter_type,
                 $filter_faction,
                 $filter_npcflag,
                 // --- NEW: Pass sort params ---
                 $sort_by,
                 $sort_dir
             );
             $search_results = $search_data['results'] ?? [];
             $totalItems = $search_data['total'] ?? 0;
             $totalPages = ($limit > 0) ? ceil($totalItems / $limit) : 0;

             // Prepare base query parameters for pagination links
             $pagination_params = $_GET;
             unset($pagination_params['page']);
             // Ensure sort params are included in pagination string
             $pagination_query_string = http_build_query($pagination_params);

             if (empty($search_results) && isset($_GET['limit'])) { // Check if a search was actually performed
                 $error_message = "未找到符合条件的生物。"; // Re-enable this message
             }
        } else {
            $error_message = "错误：搜索功能尚未完全实现 (search_creatures 函数缺失)。";
        }

        // --- Handle Edit Request --- NOW FUNCTIONAL
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['edit_id'])) {
             $edit_id = filter_input(INPUT_GET, 'edit_id', FILTER_VALIDATE_INT);
             if ($edit_id && $edit_id > 0) {
                 $creature_to_edit = get_creature_template($pdo_W, $edit_id);
                 if (!$creature_to_edit) {
                     $error_message = "无法加载要编辑的生物 ID: " . htmlspecialchars((string)$edit_id);
                     $creature_to_edit = null; // Clear if not found
                 } else {
                     // Successfully loaded creature, capture other GET params for cancel link
                     $cancel_link_params = $_GET;
                     unset($cancel_link_params['edit_id']); // Exclude edit_id itself
                     if (!empty($cancel_link_params)) {
                         // Prepend with & if other params exist, otherwise just ?
                         $cancel_link_query_string = '&' . http_build_query($cancel_link_params);
                     }
                     // --- NEW: Fetch associated models ---
                     $creature_models = get_creature_models($pdo_W, $edit_id);
                 }
             } else {
                 $error_message = "提供了无效的生物编辑 ID。";
             }
        }

    } catch (Exception $e) {
        $error_message = "发生错误: " . $e->getMessage();
        error_log("Creature Editor Error: " . $e->getMessage());
    }
}

// Helper function to generate UPDATE SQL (Adapted from item_editor)
function generate_creature_update_sql(int $entry_id, array $original_data, array $new_data, array $valid_columns, $mode = 'diff'): string {
    $set_clauses = [];

    foreach ($new_data as $key => $value) {
        // Skip non-creature fields, the primary key, and fields not in our definition
        if ($key === 'save_creature' || $key === 'entry' || !in_array($key, $valid_columns)) {
            continue;
        }

        $original_value = $original_data[$key] ?? null;

        // Careful comparison (treat empty strings and nulls potentially differently based on DB needs)
        $is_different = false;
        if (is_numeric($original_value) || is_numeric($value)) {
            // Allow for type differences (e.g., 0 vs "0") but compare values
            // Treat null and 0/empty string as potentially different unless column type allows NULL
            // Simple comparison for now:
            if ((string)$original_value !== (string)$value) {
                // Consider null vs empty string/0 distinction later if needed
                if (!( (is_null($original_value) || $original_value === '') && ($value === '0' || $value === 0) ) &&
                    !( (is_null($value) || $value === '') && ($original_value === '0' || $original_value === 0) )) {
                     $is_different = true;
                }
                // More robust: Check DB column definition if it allows NULL
            }
        } elseif ($original_value !== $value) {
            $is_different = true;
        }

        if (($mode === 'diff' && $is_different) || $mode === 'full') {
            $quoted_value = 'NULL';
            if ($value !== null) {
                if (is_numeric($value) && !is_string($value)) { // Check if it's really numeric, not a numeric string from form
                    $quoted_value = $value;
                } else {
                    // Check if the column is expected to be numeric based on its name (simple heuristic)
                    $is_likely_numeric_col = preg_match('/^(entry|difficulty_entry_|killcredit\d|modelid\d?|gossip_menu_id|minlevel|maxlevel|exp|exp_req|faction|npcflag|npcflag2|speed_walk|speed_run|speed_fly|scale|rank|dmgschool|baseattacktime|rangeattacktime|unit_class|unit_flags|unit_flags2|dynamicflags|family|trainer_type|trainer_spell|trainer_class|trainer_race|type|type_flags|type_flags2|lootid|pickpocketloot|skinloot|resistance\d|spell\d|petspelldataid|vehicleid|mingold|maxgold|movementtype|inhabittype|hoverheight|healthmodifier|manamodifier|armormodifier|experiencemodifier|movementid|regenhealth|mechanic_immune_mask|flags_extra|verifiedbuild)$/i', $key);

                    if ($is_likely_numeric_col && ($value === '' || !is_numeric($value))) {
                        // If it looks numeric but value is empty or non-numeric, default to 0 (could be NULL if allowed)
                        $quoted_value = 0;
                    } elseif (is_numeric($value)) {
                        $quoted_value = $value; // Keep numeric string as number
                    } else {
                         $quoted_value = "'" . addslashes((string)$value) . "'";
                    }
                }
            }
            $set_clauses[] = "`" . $key . "` = " . $quoted_value;
        }
    }

    if (empty($set_clauses)) {
        return ($mode === 'diff') ? '-- No changes detected --' : '-- No fields to update --';
    }

    return "UPDATE `creature_template` SET " . implode(', ', $set_clauses) . " WHERE (`entry` = " . $entry_id . ");";
}

// --- Generate SQL Previews (if in edit mode) ---
$diff_sql = '-- Diff query requires changes --';
$full_sql = '-- Full query requires data --';
if ($isLoggedIn && $creature_to_edit) {
    // On initial load, generate full SQL based on current data
    // Diff SQL will be generated client-side or on POST in future
    $diff_sql = '-- No changes detected (initial load) --';
    $full_sql = generate_creature_update_sql(
        $creature_to_edit['entry'],
        $creature_to_edit, // Original data
        $creature_to_edit, // New data (same as original on load)
        $creature_template_columns, // Valid columns
        'full' // Mode
    );
}

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>生物编辑器 - <?= $isLoggedIn ? '控制台' : '登录' ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../bag_query/style.css"> <!-- Reuse common styles -->
    <!-- NEW: Add Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" integrity="sha512-1ycn6IcaQQ40/MKBW2W4Rhis/DbILU74C1vSrLJxCq57o941Ym01SwNsOMqvEBFlcgUa6xLiPY/NS5R+E6ztJQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        /* Add specific styles for creature editor if needed later */
        .table th, .table td { white-space: nowrap; }
        .filter-form .row { margin-bottom: 1rem; }
        #sql-preview-section { /* Add styles from item_editor for sticky header */
            position: sticky;
            top: 0;
            z-index: 1020;
            background-color: rgba(var(--bs-dark-rgb), 0.95); /* Use BS variable */
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
        <h2>管理员登录 - 生物编辑器</h2>
        <?php if ($loginError): ?> <div class="alert alert-danger"><?= htmlspecialchars($loginError) ?></div> <?php endif; ?>
        <form method="post" action="index.php">
            <div class="mb-3"><label for="login_username" class="form-label">用户名</label><input type="text" class="form-control" id="login_username" name="login_username" required></div>
            <div class="mb-3"><label for="login_password" class="form-label">密码</label><input type="password" class="form-control" id="login_password" name="login_password" required></div>
            <button type="submit" class="btn btn-query w-100">登录</button>
        </form>
         <div class="mt-3 text-center"><a href="../index.php" class="home-btn-link">&laquo; 返回主页</a></div>
    </div>
<?php else: ?>
    <!-- CREATURE EDITOR INTERFACE -->
    <div class="container-fluid mt-4 position-relative p-4">
        <a href="../index.php" class="home-btn">&laquo; 返回主页</a>
        <a href="?logout=1" class="logout-btn">退出登录</a>
        <h2 class="text-center mb-4">生物模板编辑器</h2>

        <?php if ($error_message): ?> <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div> <?php endif; ?>
        <?php if ($success_message): ?> <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div> <?php endif; ?>

        <?php if ($creature_to_edit): ?>
            <!-- EDIT MODE -->
            <?php
                $creature_name = htmlspecialchars($creature_to_edit['name'] ?? '未知生物');
                $creature_entry = htmlspecialchars((string)($creature_to_edit['entry'] ?? '??'));
                $rank_class = 'rank-' . ($creature_to_edit['rank'] ?? 0);
                // Define rank classes in CSS if needed

                // --- RE-ADD: Define Field Groups based on 60 fields --- (Using lowercase keys)
                $creature_field_groups = [ // KEYS MUST MATCH NOTEPAD CASING
                    '基本信息' => [ // Renamed from '核心信息'
                        // 'entry' => ['label' => 'ID', 'type' => 'number', 'readonly' => true],
                        'name' => ['label' => '名称', 'type' => 'text'],
                        'subname' => ['label' => '子名称', 'type' => 'text'],
                        'IconName' => ['label' => '图标名称', 'type' => 'select', 'options_var' => 'icon_name_options', 'desc' => '用于向玩家指示该生物或NPC的类型。'],
                        'minlevel' => ['label' => '最低等级', 'type' => 'number', 'min' => 0],
                        'maxlevel' => ['label' => '最高等级', 'type' => 'number', 'min' => 0],
                        'exp' => ['label' => '扩展包等级数据', 'type' => 'select', 'options' => [0 => '0-经典旧世', 1 => '1-燃烧的远征', 2 => '2-巫妖王之怒'], 'desc' => '决定生物基础属性参考的资料片标准 (creature_classlevelstats)'],
                        'rank' => ['label' => '等级(Rank)', 'type' => 'select', 'options_var' => 'rank_options'],
                        'type' => ['label' => '类型(Type)', 'type' => 'select', 'options_var' => 'type_options'],
                        'family' => ['label' => '生物家族', 'type' => 'select', 'options_var' => 'family_options', 'desc' => '用于宠物或特殊技能互动'],
                        'unit_class' => ['label' => '单位职业', 'type' => 'select', 'options_var' => 'unit_class_options', 'desc' => '战士=1, 圣骑=2, 盗贼=4, 法师=8'],
                    ],
                    '旧模型（老版本结构请编辑此处）' => [ // New group for old model IDs
                        'modelid1' => ['label' => '模型ID 1', 'type' => 'number', 'min' => 0, 'desc' => 'CreatureDisplayInfo.dbc ID (主)'],
                        'modelid2' => ['label' => '模型ID 2', 'type' => 'number', 'min' => 0, 'desc' => 'CreatureDisplayInfo.dbc ID (副)'],
                        'modelid3' => ['label' => '模型ID 3', 'type' => 'number', 'min' => 0, 'desc' => 'CreatureDisplayInfo.dbc ID (副)'],
                        'modelid4' => ['label' => '模型ID 4', 'type' => 'number', 'min' => 0, 'desc' => 'CreatureDisplayInfo.dbc ID (副)'],
                    ],
                    '新模型' => [ // New group for scale and future model fields
                        // Removed 'scale' field - will rely on the table below from creature_template_model
                        // 'scale' => ['label' => '缩放比例', 'type' => 'number', 'step' => '0.1', 'min' => 0, 'desc' => '模型的显示大小倍数'],
                    ],
                    '阵营与交互' => [
                        'faction' => ['label' => '阵营模板ID', 'type' => 'number', 'min' => 0, 'desc' => '关联 faction_template.id'], // Lowercase
                        'gossip_menu_id' => ['label' => '闲聊菜单 ID', 'type' => 'number', 'min' => 0], // Lowercase
                        'RacialLeader' => ['label' => '阵营领袖', 'type' => 'select', 'options' => [0=>'0-否', 1=>'1-是']], // Mixed case key
                        'KillCredit1' => ['label' => '击杀功绩1', 'type' => 'number', 'min' => 0, 'desc' => '关联其他生物模板 ID'], // Mixed case key
                        'KillCredit2' => ['label' => '击杀功绩2', 'type' => 'number', 'min' => 0, 'desc' => '关联其他生物模板 ID'], // Mixed case key
                    ],
                    '副本难度' => [
                        'difficulty_entry_1' => ['label' => '难度条目1', 'type' => 'number', 'min' => 0, 'desc' => '关联更高难度版本生物ID'],
                        'difficulty_entry_2' => ['label' => '难度条目2', 'type' => 'number', 'min' => 0],
                        'difficulty_entry_3' => ['label' => '难度条目3', 'type' => 'number', 'min' => 0],
                    ],
                    '移动与感知' => [
                        'MovementType' => ['label' => '移动类型', 'type' => 'select', 'options_var' => 'movement_type_options'], // Mixed case key
                        'speed_walk' => ['label' => '行走速度', 'type' => 'number', 'step' => '0.1', 'desc' => '相对基础速度 (2.5) 的倍数'],
                        'speed_run' => ['label' => '跑步速度', 'type' => 'number', 'step' => '0.1', 'desc' => '相对基础速度 (7.0) 的倍数'],
                        'speed_swim' => ['label' => '游泳速度', 'type' => 'number', 'step' => '0.1', 'desc' => '相对基础速度 (4.7?) 的倍数'],
                        'HoverHeight' => ['label' => '悬停高度', 'type' => 'number', 'step' => '0.1'], // Mixed case key
                        'detection_range' => ['label' => '侦测范围', 'type' => 'number', 'step' => '0.1', 'min' => 0],
                        'movementId' => ['label' => '移动路径ID', 'type' => 'number', 'min' => 0, 'desc' => '关联 creature_movement 或 template'], // Mixed case key
                    ],
                    '战斗属性' => [
                        'BaseAttackTime' => ['label' => '基础攻击间隔(ms)', 'type' => 'number', 'min' => 0], // Mixed case key
                        'RangeAttackTime' => ['label' => '远程攻击间隔(ms)', 'type' => 'number', 'min' => 0], // Mixed case key
                        'BaseVariance' => ['label' => '基础伤害浮动', 'type' => 'number', 'step' => '0.1', 'desc' => '基础物理伤害的百分比浮动'], // Mixed case key
                        'RangeVariance' => ['label' => '远程伤害浮动', 'type' => 'number', 'step' => '0.1'], // Mixed case key
                        'dmgschool' => ['label' => '物理伤害类型', 'type' => 'select', 'options_var' => 'damage_school_options', 'desc' => '近战攻击的伤害类型'], // Lowercase
                        'DamageModifier' => ['label' => '所有伤害修正', 'type' => 'number', 'step' => '0.01', 'desc' => '生物造成的所有伤害的乘数'], // Mixed case key
                    ],
                    '状态修正' => [
                        'HealthModifier' => ['label' => '生命值修正', 'type' => 'number', 'step' => '0.01', 'desc' => '基础生命值的乘数'], // Mixed case key
                        'ManaModifier' => ['label' => '法力值修正', 'type' => 'number', 'step' => '0.01', 'desc' => '基础法力值的乘数'], // Mixed case key
                        'ArmorModifier' => ['label' => '护甲修正', 'type' => 'number', 'step' => '0.01', 'desc' => '基础护甲的乘数'], // Mixed case key
                        'ExperienceModifier' => ['label' => '经验值修正', 'type' => 'number', 'step' => '0.01', 'desc' => '提供经验的乘数'], // Mixed case key
                        'scale' => ['label' => '模型缩放', 'type' => 'number', 'step' => '0.1', 'desc' => '默认值为 1.0'], // Lowercase
                        'RegenHealth' => ['label' => '生命恢复', 'type' => 'select', 'options' => [1=>'1-是', 0=>'0-否'], 'desc' => '生物是否在战斗外恢复生命值'], // Mixed case key
                    ],
                    '标志位' => [
                        'npcflag' => ['label' => 'NPC 标志 (NpcFlags)', 'type' => 'bitmask', 'options_const' => 'CREATURE_NPC_FLAGS'],
                        'unit_flags' => ['label' => '单位标志 (UnitFlags)', 'type' => 'bitmask', 'options_const' => 'CREATURE_UNIT_FLAGS'],
                        'unit_flags2' => ['label' => '单位标志2 (UnitFlags2)', 'type' => 'bitmask', 'options_const' => 'CREATURE_UNIT_FLAGS2'],
                        'dynamicflags' => ['label' => '动态标志 (DynamicFlags)', 'type' => 'bitmask', 'options_const' => 'CREATURE_DYNAMIC_FLAGS'],
                        'type_flags' => ['label' => '类型标志 (TypeFlags)', 'type' => 'bitmask', 'options_const' => 'CREATURE_TYPE_FLAGS'],
                        'flags_extra' => ['label' => '额外标志 (FlagsExtra)', 'type' => 'bitmask', 'options_const' => 'CREATURE_FLAGS_EXTRA'],
                    ],
                    '免疫' => [
                        'mechanic_immune_mask' => ['label' => '免疫机制掩码', 'type' => 'bitmask', 'options_const' => 'MECHANIC_IMMUNE_MASK', 'desc' => '免疫特定控制效果(如昏迷)'], // Lowercase
                        'spell_school_immune_mask' => ['label' => '免疫法术学派掩码', 'type' => 'bitmask', 'options_const' => 'SPELL_SCHOOL_IMMUNE_MASK', 'desc' => '免疫特定学派伤害(如火焰)'], // Lowercase
                    ],
                    '战利品与金币' => [
                        'lootid' => ['label' => '战利品ID', 'type' => 'number', 'min' => 0, 'desc' => '关联 creature_loot_template'],
                        'pickpocketloot' => ['label' => '偷窃战利品ID', 'type' => 'number', 'min' => 0, 'desc' => '关联 pickpocketing_loot_template'],
                        'skinloot' => ['label' => '剥皮战利品ID', 'type' => 'number', 'min' => 0, 'desc' => '关联 skinning_loot_template'],
                        'mingold' => ['label' => '最少金币(铜)', 'type' => 'number', 'min' => 0],
                        'maxgold' => ['label' => '最多金币(铜)', 'type' => 'number', 'min' => 0],
                    ],
                    '训练师' => [
                        'trainer_type' => ['label' => '训练师类型', 'type' => 'select', 'options_var' => 'trainer_type_options'], // Lowercase
                        'trainer_spell' => ['label' => '训练师法术ID', 'type' => 'number', 'min' => 0, 'desc' => '如果是法术训练师'], // Lowercase
                        'trainer_class' => ['label' => '训练师职业', 'type' => 'number', 'min' => 0, 'desc' => '如果是职业训练师'], // Lowercase
                        'trainer_race' => ['label' => '训练师种族', 'type' => 'number', 'min' => 0, 'desc' => '如果是种族坐骑训练师'], // Lowercase
                    ],
                    '脚本与特殊' => [
                        'AIName' => ['label' => 'AI 名称', 'type' => 'select', 'options_var' => 'ai_name_options', 'desc' => '选择生物使用的AI。会被ScriptName覆盖。'], // Changed to select
                        'ScriptName' => ['label' => '脚本名称', 'type' => 'textarea', 'desc' => '关联的 C++/DB 脚本名'], // Mixed case key
                        'PetSpellDataId' => ['label' => '宠物法术数据ID', 'type' => 'number', 'min' => 0], // Mixed case key
                        'VehicleId' => ['label' => '载具 ID', 'type' => 'number', 'min' => 0, 'desc' => '如果生物是载具, 则 > 0'], // Mixed case key
                        'VerifiedBuild' => ['label' => '验证版本', 'type' => 'number', 'desc' => '用于 WDB 验证 (可空)'], // Mixed case key
                    ],
                ];

                // --- RE-ADD: Field Descriptions (Using lowercase keys) ---
                $field_descriptions = [
                    'entry' => 'entry: 生物的唯一ID。主键。',
                    'difficulty_entry_1' => 'difficulty_entry_1: 关联的英雄难度（或其他）的生物模板ID。',
                    'difficulty_entry_2' => 'difficulty_entry_2: 关联的史诗/10人普通难度（或其他）的生物模板ID。',
                    'difficulty_entry_3' => 'difficulty_entry_3: 关联的史诗/25人普通难度（或其他）的生物模板ID。',
                    'KillCredit1' => 'KillCredit1: 击杀此生物时，给予哪个生物ID的击杀计数。',
                    'KillCredit2' => 'KillCredit2: 额外的击杀功绩生物ID。',
                    'modelid1' => 'modelid1: 主要模型显示ID (CreatureDisplayInfo.dbc)。标准AC使用creature_template_model表。',
                    'modelid2' => 'modelid2: 备用模型显示ID。标准AC使用creature_template_model表。',
                    'modelid3' => 'modelid3: 备用模型显示ID。标准AC使用creature_template_model表。',
                    'modelid4' => 'modelid4: 备用模型显示ID。标准AC使用creature_template_model表。',
                    'name' => 'name: 生物的主要显示名称。',
                    'subname' => 'subname: 显示在名称下方的子标题，例如 <头衔>。',
                    'IconName' => 'IconName: 用于向玩家指示该生物或NPC的类型（例如小地图追踪图标）。',
                    'gossip_menu_id' => 'gossip_menu_id: 与此生物交互时打开的闲聊菜单ID (gossip_menu.entry)。',
                    'minlevel' => 'minlevel: 生物的最低等级。',
                    'maxlevel' => 'maxlevel: 生物的最高等级，如果固定等级则与最低等级相同。',
                    'exp' => 'exp: 决定生物基础属性参考的资料片标准 (0=经典旧世, 1=燃烧的远征, 2=巫妖王之怒)。关联 creature_classlevelstats。',
                    'faction' => 'faction: 阵营模板ID (faction_template.id)，决定敌友关系。',
                    'npcflag' => 'npcflag: 控制NPC功能的位掩码（商人、任务、修理等）。',
                    'speed_walk' => 'speed_walk: 行走速度修正（基于2.5）。',
                    'speed_run' => 'speed_run: 跑步速度修正（基于7.0）。',
                    'speed_swim' => 'speed_swim: 游泳速度修正（基于4.7?）。',
                    'detection_range' => 'detection_range: 生物能侦测到敌人的范围。',
                    'scale' => 'scale: 模型缩放比例（1 = 100%）。',
                    'rank' => 'rank: 生物等级。影响头像框视觉效果、默认重生/腐烂时间。不直接影响属性/掉落。配合 type_flags=4 可显示骷髅等级。 (0=普通, 1=精英, 2=稀有精英, 3=首领, 4=稀有)',
                    'dmgschool' => 'dmgschool: 近战物理攻击的伤害类型 (0=物理, 1=神圣 ...)。',
                    'BaseAttackTime' => 'BaseAttackTime: 近战攻击间隔（毫秒）。',
                    'RangeAttackTime' => 'RangeAttackTime: 远程攻击间隔（毫秒）。',
                    'BaseVariance' => 'BaseVariance: 近战伤害的基础浮动百分比（例如 0.1 = ±10%）。',
                    'RangeVariance' => 'RangeVariance: 远程伤害的基础浮动百分比。',
                    'unit_class' => 'unit_class: 生物的职业模板。决定基础生命/法力值和能量条类型。实际值受exp/HealthModifier/ManaModifier影响。未设置会产生日志警告。',
                    'unit_flags' => 'unit_flags: 控制单位多种状态和交互行为的位掩码（如不可攻击、沉默、眩晕、可剥皮等）。',
                    'unit_flags2' => 'unit_flags2: 控制单位额外状态和行为的位掩码（如假死、理解语言、镜像等）。',
                    'dynamicflags' => 'dynamicflags: 控制生物视觉外观和特殊标记的位掩码（如可拾取光效、追踪点、死亡外观、被标记状态等）。',
                    'family' => 'family: 生物家族ID，用于宠物系统等 (CreatureFamily.dbc)。',
                    'trainer_type' => 'trainer_type: 训练师类型。需配合 trainer_class/trainer_race/trainer_spell 字段使用。(0=职业, 1=坐骑, 2=专业, 3=宠物)',
                    'trainer_spell' => 'trainer_spell: 如果训练师教授特定法术，则填写该法术ID。',
                    'trainer_class' => 'trainer_class: 如果训练师是职业训练师，填写职业ID (ChrClasses.dbc)。',
                    'trainer_race' => 'trainer_race: 如果训练师是种族坐骑训练师，填写种族ID (ChrRaces.dbc)。',
                    'type' => 'type: 生物类型ID (CreatureType.dbc - 野兽、亡灵等)。',
                    'type_flags' => 'type_flags: 控制生物多种特性的位掩码（如可驯服、可采集、首领状态、交互方式等）。',
                    'lootid' => 'lootid: 普通战利品表ID (creature_loot_template.entry)。',
                    'pickpocketloot' => 'pickpocketloot: 偷窃战利品表ID (pickpocketing_loot_template.entry)。',
                    'skinloot' => 'skinloot: 剥皮战利品表ID (skinning_loot_template.entry)。',
                    'PetSpellDataId' => 'PetSpellDataId: 宠物法术数据ID (PetSpellData.dbc)。',
                    'VehicleId' => 'VehicleId: 如果生物是载具平台，则为对应的 vehicle.id。',
                    'mingold' => 'mingold: 掉落的最少金币（铜）。',
                    'maxgold' => 'maxgold: 掉落的最多金币（铜）。',
                    'AIName' => 'AIName: 指定生物使用的AI脚本名称。如果ScriptName字段也被设置，则此字段会被覆盖。',
                    'MovementType' => 'MovementType: 移动方式（0=站立, 1=随机, 2=路径）。',
                    'HoverHeight' => 'HoverHeight: 悬停在地面以上的高度。',
                    'HealthModifier' => 'HealthModifier: 生命值修正乘数（基于等级计算的基础值）。',
                    'ManaModifier' => 'ManaModifier: 法力值修正乘数。',
                    'ArmorModifier' => 'ArmorModifier: 护甲修正乘数。',
                    'DamageModifier' => 'DamageModifier: 所有伤害修正乘数。',
                    'ExperienceModifier' => 'ExperienceModifier: 经验值修正乘数。',
                    'RacialLeader' => 'RacialLeader: 是否为阵营领袖 (1=是, 0=否)。',
                    'movementId' => 'movementId: 如果移动类型为路径，则关联 creature_movement(_template) 的 ID。',
                    'RegenHealth' => 'RegenHealth: 是否在战斗外恢复生命 (1=是, 0=否)。',
                    'mechanic_immune_mask' => 'mechanic_immune_mask: 使生物免疫特定法术机制的位掩码。对应SpellMechanic.dbc，例如昏迷、恐惧、沉默等。将数值相加组合免疫效果。',
                    'spell_school_immune_mask' => 'spell_school_immune_mask: 使生物免疫特定法术学派伤害的位掩码（物理、火焰、冰霜等）。将数值相加组合免疫效果。',
                    'flags_extra' => 'flags_extra: 控制生物特殊属性和行为的位掩码（如平民、触发器、守卫、免疫击退等）。将数值相加组合标志。',
                    'ScriptName' => 'ScriptName: 关联的数据库脚本 (db_scripts) 或核心 C++ 脚本名称。',
                    'VerifiedBuild' => 'VerifiedBuild: 用于追踪模板数据来源的内部字段（可空）。',
                ];

                // --- RE-ADD: Get options for dropdowns ---
                $family_options = get_creature_families();
                $unit_class_options = get_unit_classes();
                $trainer_type_options = get_trainer_types();
                $damage_school_options = get_damage_schools();
                $ai_name_options = function_exists('get_ai_names') ? get_ai_names() : []; // Get AI names
                $movement_type_options = get_movement_types();
                // $rank_options and $type_options are already fetched earlier for filters

                // ADDED definition for $icon_name_options before getting other options
                // Define options for IconName
                $icon_name_options = [
                    '' => '无/默认', // Empty string is the default
                    '指引' => '指引',
                    '炮手' => '炮手',
                    '载具光标' => '载具光标',
                    '驾驶' => '驾驶',
                    '攻击' => '攻击',
                    '购买' => '购买',
                    '交谈' => '交谈',
                    '拾取' => '拾取',
                    '交互' => '交互',
                    '训练师' => '训练师',
                    '飞行点' => '飞行点',
                    '修理' => '修理',
                    '全部拾取' => '全部拾取',
                    '任务' => '任务 (未知?)', // Uncomment if needed
                    'PvP' => 'PvP (未知?)', // Uncomment if needed
                ];

                // --- RE-ADD: Helper function to render form fields ---
                function render_creature_field($key, $field_meta, $current_value, $options = []) {
                    global $field_descriptions; // Access descriptions

                    $label = htmlspecialchars($field_meta['label'] ?? ucfirst($key));
                    $type = $field_meta['type'] ?? 'text';
                    $value_str = htmlspecialchars((string)$current_value);
                    $desc_key = $key; // Use the key to look up description
                    $desc = htmlspecialchars($field_descriptions[$desc_key] ?? ($field_meta['desc'] ?? $key)); // Use global description first
                    $input_type_attr = 'text';
                    $attributes = '';

                    // Start building field HTML
                    $field_html = "<label for='{$key}' title='{$desc}' class='form-label form-label-sm'>{$label}:</label>";

                    switch ($type) {
                        case 'number':
                            $input_type_attr = 'number';
                            $step = $field_meta['step'] ?? '1';
                            $min = $field_meta['min'] ?? ''; // Let browser handle default min if not set, unless specified
                            $attributes .= " step=\"".$step."\"";
                            if ($min !== '') $attributes .= " min=\"".$min."\"";
                            $field_html .= "<input type='{$input_type_attr}' class='form-control form-control-sm' id='{$key}' name='{$key}' value='{$value_str}'{$attributes}>";
                            break;

                        case 'select':
                            $select_options_html = '';
                            // Ensure options is an array
                            if (!is_array($options)) $options = [];

                            // Handle potentially invalid current value by adding it to the options if not present
                             if ($current_value !== '' && $current_value !== null && !isset($options[$current_value])) {
                                 $options[$current_value] = "当前值 ({$current_value}) - 无效?";
                             }

                            foreach ($options as $opt_val => $opt_name) {
                                $selected = ($current_value !== '' && $current_value !== null && $current_value == $opt_val) ? 'selected' : '';
                                $select_options_html .= "<option value=\"" . htmlspecialchars((string)$opt_val) . "\" {$selected}>" . htmlspecialchars((string)$opt_name) . "</option>";
                            }
                            $field_html .= "<select class='form-select form-select-sm' id='{$key}' name='{$key}'>{$select_options_html}</select>";
                            break;

                        case 'textarea':
                            $rows = $field_meta['rows'] ?? 2;
                            $field_html .= "<textarea class='form-control form-control-sm' id='{$key}' name='{$key}' rows='{$rows}'>{$value_str}</textarea>";
                            break;

                        case 'bitmask':
                            $modal_id = 'modal-' . $key;
                            $field_html .= "<div class='input-group input-group-sm'>";
                            $field_html .= "<input type='text' class='form-control form-control-sm' id='{$key}' name='{$key}' value='{$value_str}' readonly>"; // Readonly input
                            $field_html .= "<button class='btn btn-outline-secondary' type='button' data-bs-toggle='modal' data-bs-target='#{$modal_id}'>选择</button>";
                            $field_html .= "</div>";
                            // Modal HTML structure will be added separately later
                            break;

                        case 'text':
                        default:
                            $input_type_attr = 'text';
                            $field_html .= "<input type='{$input_type_attr}' class='form-control form-control-sm' id='{$key}' name='{$key}' value='{$value_str}'{$attributes}>";
                            break;
                    }

                    if (isset($field_meta['readonly']) && $field_meta['readonly']) {
                        // This needs adjustment - readonly should be added during input generation above
                        // For now, let's assume it's handled correctly by the switch case if needed.
                    }

                    return $field_html;
                }
                // --- End Helper function ---
 
             ?>
            <h4 class="text-warning mb-3">
                 <?php $creature_name = htmlspecialchars($creature_to_edit['name'] ?? '未知生物'); ?>
                 正在编辑: <span class="<?= $rank_class ?>">[<?= $creature_entry ?>] <?= $creature_name ?></span>
                <a href="https://db.nfuwow.com/80/?npc=<?= $creature_entry ?>" target="_blank" class="ms-2 small" title="在数据库中查看">
                    <i class="fas fa-external-link-alt"></i> 查看原版
                </a>
                <!-- Font Awesome needs to be included in <head> -->
            </h4>

            <!-- SQL PREVIEW SECTION (Kept) -->
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
                         <a href="https://www.azerothcore.org/wiki/creature_template" target="_blank" class="ms-3 small">
                            <i class="fas fa-external-link-alt"></i> creature_template 文档
                         </a>
                     </div>
                      <div class="col-md-5 text-end sql-buttons">
                          <button id="copySqlBtn" class="btn btn-secondary btn-sm"><i class="far fa-copy"></i> 复制</button>
                          <button id="executeSqlBtn" class="btn btn-primary btn-sm"><i class="fas fa-bolt"></i> 执行</button>
                          <a href="index.php?edit_id=<?= $creature_entry ?><?= $cancel_link_query_string ?>" id="reloadCreatureBtn" class="btn btn-info btn-sm"><i class="fas fa-sync-alt"></i> 重新加载</a>
                          <a href="index.php?<?= ltrim($cancel_link_query_string, '&') ?>" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i> 关闭</a>
                      </div>
                 </div>
                 <div class="mt-2">
                     <pre id="sql-display"><?= htmlspecialchars($diff_sql) // Default to showing diff ?></pre>
                     <textarea id="diff-sql-data" style="display:none;"><?= htmlspecialchars($diff_sql) ?></textarea>
                     <textarea id="full-sql-data" style="display:none;"><?= htmlspecialchars($full_sql) ?></textarea>
                 </div>
            </section>

            <!-- Placeholder for the form -->
            <section class="mb-4 p-3 bg-dark rounded shadow-sm edit-form-section">
                 <form method="POST" id="creature-edit-form">
                      <input type="hidden" name="action" value="save_creature_ajax"> <!-- For AJAX handler -->
                      <input type="hidden" name="entry" value="<?= $creature_entry ?>">

                      <!-- Render fields based on groups -->
                      <div class="edit-form-container">
                          <?php foreach ($creature_field_groups as $group_name => $fields_in_group): ?>
                              <div class="form-section border border-secondary rounded p-3 mb-4 section-group-<?= strtolower(str_replace(' ', '-', $group_name)) ?>">
                                  <h5 class="text-info border-bottom border-secondary pb-2 mb-3"><?= htmlspecialchars($group_name) ?></h5>
                                  <div class="row g-3">
                                      <?php
                                          // Determine columns per field (adjust as needed)
                                          $default_col = 'col-md-3'; // 4 columns
                                          $col_class = $default_col;
                                          if (count($fields_in_group) <= 3) $col_class = 'col-md-4'; // 3 columns
                                          if (count($fields_in_group) <= 2) $col_class = 'col-md-6'; // 2 columns
                                          if (count($fields_in_group) === 1) $col_class = 'col-md-12'; // 1 column
                                          // Override for specific groups
                                          if ($group_name === '标志位' || $group_name === '免疫') $col_class = 'col-md-4';
                                          if ($group_name === '脚本与特殊' && isset($fields_in_group['scriptname'])) $col_class = 'col-md-6'; // Wider for scriptname
                                          if ($group_name === '旧模型') $col_class = 'col-md-3'; // Force 4 columns for old models
                                          if ($group_name === '新模型') $col_class = 'col-md-6'; // Make scale wider

                                      ?>
                                      <?php foreach ($fields_in_group as $key => $field_meta): ?>
                                          <?php $current_value = $creature_to_edit[strtolower($key)] ?? ''; // Use LOWERCASE key to fetch DB data ?>
                                          <?php
                                             // Prepare options for select fields
                                             $options_for_field = [];
                                             if ($field_meta['type'] === 'select') {
                                                 if (isset($field_meta['options_var'])) {
                                                     $options_var_name = $field_meta['options_var'];
                                                     // Check if the variable exists globally (e.g., $rank_options)
                                                     if (isset($$options_var_name) && is_array($$options_var_name)) {
                                                         $options_for_field = $$options_var_name;
                                                     } else {
                                                         error_log("Creature Editor Warning: Options variable '{$options_var_name}' not found for field '{$key}'.");
                                                     }
                                                 } elseif (isset($field_meta['options']) && is_array($field_meta['options'])) {
                                                     $options_for_field = $field_meta['options'];
                                                 }
                                             }
                                          ?>
                                          <?php
                                              // Determine column class for this specific field
                                              $field_col_class = $col_class;
                                              if ($field_meta['type'] === 'textarea') $field_col_class = 'col-md-6'; // Textareas wider
                                          ?>
                                          <div class="<?= $field_col_class ?>">
                                              <?= render_creature_field($key, $field_meta, $current_value, $options_for_field) ?>
                                          </div>
                                      <?php endforeach; ?>
                                  </div> <!-- /row -->

                                  <?php // --- NEW: Display creature_template_model table in '新模型' section ---
                                    if ($group_name === '新模型' && isset($creature_models)): ?>
                                      <div class="mt-4">
                                          <h6 class="text-muted">附加模型数据 (来自 creature_template_model):</h6>
                                          <?php if (!empty($creature_models)): ?>
                                              <div class="table-responsive">
                                                  <table class="table table-sm table-bordered table-dark table-striped table-hover small">
                                                      <thead>
                                                          <tr>
                                                              <th>索引 (Idx)</th>
                                                              <th>模型显示 ID (CreatureDisplayID)</th>
                                                              <th>模型缩放 (DisplayScale)</th>
                                                              <th>概率 (Probability)</th>
                                                              <th>验证版本 (VerifiedBuild)</th>
                                                              <th>操作</th> <!-- ADDED: Actions header -->
                                                          </tr>
                                                      </thead>
                                                      <tbody>
                                                          <?php foreach ($creature_models as $model_data): ?>
                                                              <tr 
                                                                  data-idx="<?= htmlspecialchars((string)($model_data['Idx'] ?? '')) ?>" 
                                                                  data-displayid="<?= htmlspecialchars((string)($model_data['CreatureDisplayID'] ?? '')) ?>"
                                                                  data-scale="<?= htmlspecialchars((string)($model_data['DisplayScale'] ?? '')) ?>"
                                                                  data-probability="<?= htmlspecialchars((string)($model_data['Probability'] ?? '')) ?>"
                                                                  data-verifiedbuild="<?= htmlspecialchars((string)($model_data['VerifiedBuild'] ?? 'N/A')) ?>"
                                                              >
                                                                  <td><?= htmlspecialchars((string)($model_data['Idx'] ?? '?')) ?></td>
                                                                  <td>
                                                                      <?= htmlspecialchars((string)($model_data['CreatureDisplayID'] ?? '?')) ?>
                                                                      <!-- Removed AoWoW link -->
                                                                  </td>
                                                                  <td><?= htmlspecialchars((string)($model_data['DisplayScale'] ?? '?')) ?></td>
                                                                  <td><?= htmlspecialchars((string)($model_data['Probability'] ?? '?')) ?></td>
                                                                  <td><?= htmlspecialchars((string)($model_data['VerifiedBuild'] ?? 'N/A')) ?></td> <!-- Use N/A if null -->
                                                                  <td> <!-- ADDED: New cell for buttons -->
                                                                      <!-- MOVED buttons here and REMOVED disabled -->
                                                                      <button type="button" class="btn btn-outline-warning btn-sm py-0 px-1 edit-model-btn" title="编辑此模型"><i class="fas fa-pencil-alt fa-xs"></i></button>
                                                                      <button type="button" class="btn btn-outline-danger btn-sm py-0 px-1 delete-model-btn" title="删除此模型"><i class="fas fa-trash-alt fa-xs"></i></button>
                                                                  </td>
                                                              </tr>
                                                          <?php endforeach; ?>
                                                      </tbody>
                                                  </table>
                                              </div>
                                          <?php else: ?>
                                              <p class="text-secondary small">(此生物在 <code>creature_template_model</code> 表中没有定义附加模型数据)</p>
                                          <?php endif; ?>
                                            <!-- MODIFIED: Removed disabled, title, and added id -->
                                            <button type="button" class="btn btn-success btn-sm mt-2" id="add-model-btn"><i class="fas fa-plus"></i> 新增模型条目</button>
                                      </div>
                                    <?php endif; // End check for '新模型' group ?>
                              </div> <!-- /form-section -->
                          <?php endforeach; ?>
                      </div> <!-- /edit-form-container -->
                  </form>
            </section>

            <!-- MODALS FOR BITMASK SELECTION -->
            <?php
            // Define constants locally ONLY if not defined in functions.php (fallback)
            if (!defined('CREATURE_NPC_FLAGS')) {
                // Log error or handle gracefully if constant isn't defined
                // For now, it will use an empty array later
                error_log("Warning: CREATURE_NPC_FLAGS constant not defined in creature_editor/index.php modal generation.");
            }
             if (!defined('CREATURE_UNIT_FLAGS')) {
                error_log("Warning: CREATURE_UNIT_FLAGS constant not defined in creature_editor/index.php modal generation.");
            }
             if (!defined('CREATURE_UNIT_FLAGS2')) {
                error_log("Warning: CREATURE_UNIT_FLAGS2 constant not defined in creature_editor/index.php modal generation.");
            }
             // ... Define other flag constants fallbacks if needed ...
            if (!defined('CREATURE_DYNAMIC_FLAGS')) { define('CREATURE_DYNAMIC_FLAGS', []); }
            if (!defined('CREATURE_TYPE_FLAGS')) { define('CREATURE_TYPE_FLAGS', []); }
            if (!defined('CREATURE_FLAGS_EXTRA')) { define('CREATURE_FLAGS_EXTRA', []); }
            if (!defined('MECHANIC_IMMUNE_MASK')) { define('MECHANIC_IMMUNE_MASK', []); }
            if (!defined('SPELL_SCHOOL_IMMUNE_MASK')) { define('SPELL_SCHOOL_IMMUNE_MASK', []); }

            // Loop through field groups and generate modals for bitmask types
            foreach ($creature_field_groups as $group_name => $fields_in_group) {
                foreach ($fields_in_group as $key => $field_meta) {
                    if (($field_meta['type'] ?? '') === 'bitmask' && isset($field_meta['options_const'])) {
                        $modal_id = 'modal-' . $key;
                        $options_const_name = $field_meta['options_const'];
                        $modal_title = htmlspecialchars($field_meta['label'] ?? ucfirst($key));
                        $options = defined($options_const_name) ? constant($options_const_name) : []; // Get options from constant

                        echo "<div class=\"modal fade\" id=\"{$modal_id}\" tabindex=\"-1\" aria-labelledby=\"{$modal_id}Label\" aria-hidden=\"true\">";
                        echo "  <div class=\"modal-dialog modal-lg modal-dialog-scrollable\">"; // Larger modal
                        echo "    <div class=\"modal-content bg-dark text-light\">";
                        echo "      <div class=\"modal-header\">";
                        echo "        <h5 class=\"modal-title\" id=\"{$modal_id}Label\">选择 {$modal_title} 标志位</h5>";
                        echo "        <button type=\"button\" class=\"btn-close btn-close-white\" data-bs-dismiss=\"modal\" aria-label=\"Close\"></button>";
                        echo "      </div>";
                        echo "      <div class=\"modal-body\">";
                        echo "        <p class=\"text-muted small\">勾选需要的标志位，总值将自动计算。</p>";
                        echo "        <div class=\"row row-cols-1 row-cols-md-2 row-cols-lg-3 g-2\">"; // Use columns

                        if (!empty($options)) {
                            foreach ($options as $flag_value => $flag_name) {
                                echo "<div class=\"col\"><div class=\"form-check\"><input class=\"form-check-input bitmask-checkbox\" type=\"checkbox\" value=\"{$flag_value}\" data-target-input=\"{$key}\" id=\"{$modal_id}-{$flag_value}\"> <label class=\"form-check-label small\" for=\"{$modal_id}-{$flag_value}\">" . htmlspecialchars($flag_name) . " <span class=\"text-muted\">({$flag_value})</span></label></div></div>";
                            }
                        } else {
                            echo "<div class=\"col\"><p class=\"text-warning\">(没有为 {$options_const_name} 定义选项)</p></div>";
                        }

                        echo "        </div>"; // end row
                        echo "      </div>"; // end modal-body
                        echo "      <div class=\"modal-footer\">";
                        echo "         <span class=\"me-auto text-info small\" id=\"{$modal_id}-total\">当前总值: 0</span>"; // Display total
                        echo "         <button type=\"button\" class=\"btn btn-secondary\" data-bs-dismiss=\"modal\">关闭</button>";
                        // echo "         <button type=\"button\" class=\"btn btn-primary\">保存更改</button>"; // Might not be needed if input updates directly
                        echo "      </div>";
                        echo "    </div>";
                        echo "  </div>";
                        echo "</div>";
                    }
                }
            }
            ?>
            <!-- END MODALS -->

         <?php else: ?>
             <!-- SEARCH/FILTER and RESULTS MODE -->
             <!-- SEARCH/FILTER FORM SECTION -->
             <section class="mb-4 p-3 bg-dark rounded shadow-sm filter-form">
                 <h4 class="text-warning mb-3">搜索与筛选</h4>
                 <form method="GET" action="index.php" id="filter-form">
                     <div class="row g-3 align-items-end">
                         <!-- Row 1 -->
                         <div class="col-md-2">
                             <label for="search_type" class="form-label form-label-sm">搜索类型:</label>
                             <select id="search_type" name="search_type" class="form-select form-select-sm">
                                 <option value="name" <?= $search_type === 'name' ? 'selected' : '' ?>>名称</option>
                                 <option value="id" <?= $search_type === 'id' ? 'selected' : '' ?>>ID (Entry)</option>
                             </select>
                         </div>
                         <div class="col-md-3">
                             <label for="search_value" class="form-label form-label-sm">搜索值:</label>
                             <input type="text" class="form-control form-control-sm" id="search_value" name="search_value" value="<?= htmlspecialchars($search_value) ?>" placeholder="输入ID或名称关键字...">
                         </div>
                         <div class="col-md-2">
                              <label for="filter_rank" class="form-label form-label-sm">等级(Rank):</label>
                              <select id="filter_rank" name="filter_rank" class="form-select form-select-sm">
                                  <?php $filter_rank_options = function_exists('get_creature_ranks') ? get_creature_ranks(true) : [-1 => '等级 N/A']; ?>
                                  <?php foreach ($filter_rank_options as $id => $name): ?>
                                      <option value="<?= $id ?>" <?= ((int)($filter_rank ?? -1) === $id) ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                                  <?php endforeach; ?>
                              </select>
                          </div>
                         <div class="col-md-2">
                              <label for="filter_type" class="form-label form-label-sm">类型(Type):</label>
                              <select id="filter_type" name="filter_type" class="form-select form-select-sm">
                                  <?php $filter_type_options = function_exists('get_creature_types') ? get_creature_types(true) : [-1 => '类型 N/A']; ?>
                                  <?php foreach ($filter_type_options as $id => $name): ?>
                                      <option value="<?= $id ?>" <?= ((int)($filter_type ?? -1) === $id) ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                                  <?php endforeach; ?>
                              </select>
                          </div>
                         <div class="col-md-1">
                             <label for="limit" class="form-label form-label-sm">条数:</label>
                             <input type="number" class="form-control form-control-sm" id="limit" name="limit" value="<?= htmlspecialchars((string)$limit) ?>" min="10">
                          </div>
                         <div class="col-md-auto">
                             <button type="submit" class="btn btn-primary btn-sm w-100">搜索 / 筛选</button>
                         </div>
                         <div class="col-md-auto">
                             <button type="button" class="btn btn-success btn-sm w-100" id="addNewCreatureBtn" data-bs-toggle="modal" data-bs-target="#addNewCreatureModal">
                                 <i class="fas fa-plus"></i> 新增生物
                             </button>
                          </div>
                      </div>
                 </form>
             </section>

             <!-- SEARCH RESULTS SECTION -->
             <section class="mb-4 p-3 bg-dark rounded shadow-sm">
                 <h4 class="text-success mb-3">结果 (<?= $totalItems ?>)</h4>
                  <?php if (!empty($search_results)): ?>
                      <div class="table-responsive">
                          <table class="table table-sm table-dark table-striped table-bordered table-hover align-middle text-nowrap">
                              <thead>
                                  <tr>
                                      <?php
                                          // Helper function to generate sort links (scope issue fixed by defining outside or passing globals)
                                          function get_sort_link_creature($column_name, $display_text, $current_sort_by, $current_sort_dir, $allowed_columns) {
                                              if (!in_array($column_name, $allowed_columns)) {
                                                  return htmlspecialchars($display_text); // Not sortable
                                              }

                                              $base_params = $_GET; // Get all current GET parameters
                                              unset($base_params['sort_by'], $base_params['sort_dir'], $base_params['page'], $base_params['edit_id']); // Remove specific params

                                              $new_sort_dir = 'ASC';
                                              $sort_indicator = '';

                                              if ($current_sort_by === $column_name) {
                                                  $new_sort_dir = ($current_sort_dir === 'ASC') ? 'DESC' : 'ASC';
                                                  $sort_indicator = ($current_sort_dir === 'ASC') ? ' ▲' : ' ▼';
                                              }

                                              $sort_params = ['sort_by' => $column_name, 'sort_dir' => $new_sort_dir];
                                              // Merge base params first, then sort params
                                              $query_string = http_build_query(array_merge($base_params, $sort_params));

                                              return '<a href="?' . htmlspecialchars($query_string) . '" class="text-decoration-none text-white">' . htmlspecialchars($display_text) . $sort_indicator . '</a>';
                                          }
                                      ?>
                                      <th><?= get_sort_link_creature('entry', 'ID (Entry)', $sort_by, $sort_dir, $allowed_sort_columns) ?></th>
                                      <th><?= get_sort_link_creature('name', '名称 (Name)', $sort_by, $sort_dir, $allowed_sort_columns) ?></th>
                                      <th>子名称 (SubName)</th> <!-- Not sorting SubName for now -->
                                      <th><?= get_sort_link_creature('minlevel', '最小等级', $sort_by, $sort_dir, $allowed_sort_columns) ?></th>
                                      <th><?= get_sort_link_creature('maxlevel', '最大等级', $sort_by, $sort_dir, $allowed_sort_columns) ?></th>
                                      <th><?= get_sort_link_creature('faction', '阵营', $sort_by, $sort_dir, $allowed_sort_columns) ?></th>
                                      <th><?= get_sort_link_creature('npcflag', 'NPC 标志', $sort_by, $sort_dir, $allowed_sort_columns) ?></th>
                                      <th>操作</th>
                                  </tr>
                              </thead>
                              <tbody>
                                  <?php foreach ($search_results as $creature): ?>
                                  <tr>
                                      <td><?= htmlspecialchars((string)($creature['entry'] ?? '')) ?></td>
                                      <td>
                                          <a href="index.php?edit_id=<?= $creature['entry'] ?? 0 ?><?= $pagination_query_string ? '&' . $pagination_query_string : '' ?>" class="text-info">
                                               <?= htmlspecialchars($creature['name'] ?? '未知') ?>
                                          </a>
                                      </td>
                                      <td><?= htmlspecialchars($creature['subname'] ?? '') ?></td>
                                      <td><?= htmlspecialchars((string)($creature['minlevel'] ?? '')) ?></td>
                                      <td><?= htmlspecialchars((string)($creature['maxlevel'] ?? '')) ?></td>
                                      <td><?= htmlspecialchars((string)($creature['faction'] ?? '')) ?></td>
                                      <td><?= htmlspecialchars((string)($creature['npcflag'] ?? '')) ?></td>
                                      <td>
                                           <?php
                                               // Build edit link, preserving filters/sort/page
                                               $edit_link_params = $_GET;
                                               $edit_link_params['edit_id'] = $creature['entry'] ?? 0;
                                               $edit_query_string = http_build_query($edit_link_params);
                                           ?>
                                           <a href="index.php?<?= $edit_query_string ?>" class="btn btn-sm btn-warning">编辑</a>
                                           <button type="button" class="btn btn-sm btn-danger deleteCreatureBtn"
                                                   data-creature-id="<?= htmlspecialchars((string)($creature['entry'] ?? '')) ?>"
                                                   data-creature-name="<?= htmlspecialchars($creature['name'] ?? '未知') ?>"> <!-- REMOVED disabled -->
                                               删除
                                          </button>
                                      </td>
                                  </tr>
                                  <?php endforeach; ?>
                              </tbody>
                          </table>
                      </div>
                  <?php elseif (isset($_GET['limit'])): // Only show 'not found' if a search was attempted ?>
                      <p class="text-info">没有找到符合条件的生物。</p>
                  <?php endif; ?>
             </section>

             <!-- Pagination Links -->
              <?php if ($totalPages > 1): ?>
              <nav aria-label="搜索结果分页" class="mt-4 d-flex justify-content-center">
                  <ul class="pagination pagination-sm">
                      <!-- Previous Page Link -->
                      <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                          <a class="page-link" href="?<?= $pagination_query_string ?>&page=<?= $page - 1 ?>">&laquo;</a>
                      </li>
                      <!-- Page Numbers -->
                      <?php
                          // Simple pagination display (can be enhanced like item_editor later)
                          $max_pages_to_show = 7;
                          $start_page = max(1, $page - floor($max_pages_to_show / 2));
                          $end_page = min($totalPages, $start_page + $max_pages_to_show - 1);
                          $start_page = max(1, $end_page - $max_pages_to_show + 1);

                          if ($start_page > 1) echo '<li class="page-item"><a class="page-link" href="?' . $pagination_query_string . '&page=1">1</a></li>';
                          if ($start_page > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';

                          for ($i = $start_page; $i <= $end_page; $i++):
                      ?>
                          <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
                              <a class="page-link" href="?<?= $pagination_query_string ?>&page=<?= $i ?>"><?= $i ?></a>
                          </li>
                      <?php
                          endfor;

                          if ($end_page < $totalPages -1 ) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                          if ($end_page < $totalPages) echo '<li class="page-item"><a class="page-link" href="?' . $pagination_query_string . '&page=' . $totalPages . '">' . $totalPages . '</a></li>';
                      ?>
                      <!-- Next Page Link -->
                      <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                          <a class="page-link" href="?<?= $pagination_query_string ?>&page=<?= $page + 1 ?>">&raquo;</a>
                      </li>
                  </ul>
              </nav>
              <?php endif; ?>
              <!-- End Pagination -->
         <?php endif; ?> <!-- End check for edit mode -->

     </div> <!-- End container-fluid -->

     <!-- Scripts should be outside the main edit/search conditional blocks, but can be conditional themselves -->
     <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

     <?php // Script tag specifically for Edit Mode Data and Logic ?>
     <?php if ($isLoggedIn && $creature_to_edit): ?>
     <script>
        // Pass original data and valid columns from PHP to JavaScript
        const originalCreatureData = <?= json_encode($creature_to_edit); ?>;
        const validDbColumns = <?= json_encode($creature_template_columns); ?>;
         const creatureEntry = <?= json_encode($creature_to_edit['entry']); ?>; 
        const cancelLinkQuery = <?= json_encode(ltrim($cancel_link_query_string, '&')); ?>;

         // Model Modals Instances (Declare vars here, initialize inside DOMContentLoaded)
         let addModelModalEl = null;
         let editModelModalEl = null;
         let addModelModal = null;
         let editModelModal = null;

         document.addEventListener('DOMContentLoaded', () => {
             console.log("Edit Mode JS Initializing...");

             // --- Get Edit Mode Specific Elements ---
             const creatureEditForm = document.getElementById('creature-edit-form');
             const sqlDisplay = document.getElementById('sql-display');
             const diffQueryRadio = document.getElementById('diffQueryRadio');
             const fullQueryRadio = document.getElementById('fullQueryRadio');
             const diffSqlDataElem = document.getElementById('diff-sql-data');
             const fullSqlDataElem = document.getElementById('full-sql-data');
             const copySqlBtn = document.getElementById('copySqlBtn');
             const executeSqlBtn = document.getElementById('executeSqlBtn');
             const addModelBtn = document.getElementById('add-model-btn'); 
             const currentAddModelForm = document.getElementById('addModelForm');
             const currentEditModelForm = document.getElementById('editModelForm');
             const modelTableBody = document.querySelector('.section-group-新模型 tbody');

             // --- Initialize Modal Instances inside DOMContentLoaded ---
             addModelModalEl = document.getElementById('addModelModal');
             editModelModalEl = document.getElementById('editModelModal');
             addModelModal = addModelModalEl ? new bootstrap.Modal(addModelModalEl) : null;
             editModelModal = editModelModalEl ? new bootstrap.Modal(editModelModalEl) : null;

             // Generic AJAX Handlers (Implementations omitted for brevity, keep existing code)
             async function handleModelAjax(action, formData, successMessagePrefix) { /* ... */ 
                 try {
                     const response = await fetch('index.php', {
                         method: 'POST',
                         body: formData
                     });
                     if (!response.ok) {
                          throw new Error(`HTTP error! status: ${response.status}`);
                     }
                     const result = await response.json();
                     alert(result.message);
                     if (result.success) {
                          window.location.reload(); 
                         } else {
                          console.error('Model operation failed:', result.message);
                                 }
                 } catch (error) {
                     console.error(`AJAX Error (${action}):`, error);
                     alert(`执行 ${successMessagePrefix} 操作时出错: ${error.message}`);
                     }
             }
             async function saveCreatureAjax() { /* ... */ 
                 if (!creatureEditForm || typeof originalCreatureData === 'undefined' || typeof validDbColumns === 'undefined') {
                     console.error('Save function called without necessary data.');
                     alert('保存功能初始化失败，请刷新页面。');
                     return;
                 }
                 if (executeSqlBtn) {
                 executeSqlBtn.disabled = true;
                 executeSqlBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 处理中...';
                 }
                 const formData = new FormData(creatureEditForm);
                 const changes = {};
                 let hasChanges = false;
                 validDbColumns.forEach(key => {
                     if (key === 'entry') return;
                     const element = creatureEditForm.elements[key];
                     if (element) {
                         const newValue = element.value;
                         const originalValue = originalCreatureData[key];
                         if (String(originalValue ?? '') !== String(newValue ?? '')) {
                              if (!( (originalValue === null || originalValue === '') && (newValue === '0' || newValue === 0) ) &&
                                  !( (newValue === null || newValue === '') && (originalValue === '0' || originalValue === 0) )) {
                                      changes[key] = newValue;
                                      hasChanges = true;
                              }
                         }
                     }
                 });
                 if (!hasChanges) {
                     alert('没有检测到任何更改。');
                     if(executeSqlBtn) {
                     executeSqlBtn.disabled = false;
                     executeSqlBtn.innerHTML = '<i class="fas fa-bolt"></i> 执行';
                     }
                     return;
                 }
                 const postData = new FormData();
                 postData.append('action', 'save_creature_ajax');
                 postData.append('entry', creatureEntry);
                 postData.append('changes', JSON.stringify(changes)); 
                 console.log('Sending changes:', JSON.stringify(changes));
                 try {
                     const response = await fetch('index.php', {
                         method: 'POST',
                         body: postData
                     });
                     if (!response.ok) {
                         throw new Error(`HTTP error! status: ${response.status}`);
                     }
                     const result = await response.json();
                     alert(result.message);
                     if (result.success) {
                         window.location.reload();
                     } else {
                         if(executeSqlBtn){
                         executeSqlBtn.disabled = false;
                         executeSqlBtn.innerHTML = '<i class="fas fa-bolt"></i> 执行';
                     }
                     }
                 } catch (error) {
                     console.error('AJAX Save Error:', error);
                     alert('保存时发生网络或服务器错误：' + error.message);
                      if(executeSqlBtn){
                     executeSqlBtn.disabled = false;
                     executeSqlBtn.innerHTML = '<i class="fas fa-bolt"></i> 执行';
                 }
             }
             }

             // SQL Preview Logic (Implementations omitted for brevity, keep existing code)
             function generateCreatureUpdateSqlJS(originalData, newData, validColumns, mode = 'diff') { /* ... */ 
                 let setClauses = [];
                 validColumns.forEach(key => {
                     if (key === 'entry' || !(key in newData)) {
                         return;
                     }
                     const originalValue = originalData[key] ?? null;
                     const newValue = newData[key] ?? null;
                     let isDifferent = false;
                     const originalString = String(originalValue ?? '');
                     const newString = String(newValue ?? '');
                     if (originalString !== newString) {
                         const isOriginalEmptyLike = (originalValue === null || originalValue === '' || originalValue === 0 || originalValue === '0');
                         const isNewEmptyLike = (newValue === null || newValue === '' || newValue === 0 || newValue === '0');
                         if (!(isOriginalEmptyLike && isNewEmptyLike)) {
                             if (!((originalValue === null || originalValue === '') && (newValue === null || newValue === ''))) {
                                 isDifferent = true;
                             }
                         }
                     }
                     if ((mode === 'diff' && isDifferent) || mode === 'full') {
                         let quotedValue;
                         const isLikelyNumericCol = /^(entry|difficulty_entry_|killcredit\d|modelid\d?|gossip_menu_id|minlevel|maxlevel|exp|exp_req|faction|npcflag|npcflag2|speed_walk|speed_run|speed_fly|scale|rank|dmgschool|baseattacktime|rangeattacktime|unit_class|unit_flags|unit_flags2|dynamicflags|family|trainer_type|trainer_spell|trainer_class|trainer_race|type|type_flags|type_flags2|lootid|pickpocketloot|skinloot|resistance\d|spell\d|petspelldataid|vehicleid|mingold|maxgold|movementtype|inhabittype|hoverheight|healthmodifier|manamodifier|armormodifier|experiencemodifier|movementid|regenhealth|mechanic_immune_mask|flags_extra|verifiedbuild)$/i.test(key);
                         if (isLikelyNumericCol) {
                             if (newValue === null || newValue === '' || isNaN(newValue)) {
                                 quotedValue = 0;
                             } else {
                                 let parsedValue = String(originalValue).includes('.') ? parseFloat(newValue) : parseInt(newValue, 10);
                                 quotedValue = isNaN(parsedValue) ? Number(newValue) : parsedValue;
                                 if (typeof quotedValue === 'number' && String(quotedValue).includes('e')) {
                                      quotedValue = Number(newValue).toFixed(String(newValue).includes('.') ? String(newValue).split('.')[1].length : 0);
                                 }
                             }
                         } else {
                             if (newValue === null) {
                                 quotedValue = 'NULL';
                             } else {
                                 quotedValue = `'${String(newValue).replace(/\'/g, "\\'")}'`;
                             }
                         }
                         setClauses.push("`" + key + "` = " + quotedValue);
                     }
                 });
                 if (setClauses.length === 0) {
                     return (mode === 'diff') ? '-- No changes detected --' : '-- No fields to update --';
                 }
                 return "UPDATE `creature_template` SET " + setClauses.join(", \n    ") + " WHERE (`entry` = " + originalData.entry + ");";
             }
             function handleFormChange() { /* ... */ 
                if (!creatureEditForm || typeof originalCreatureData === 'undefined' || typeof validDbColumns === 'undefined') return;
                 const formData = new FormData(creatureEditForm);
                 const currentData = {};
                 validDbColumns.forEach(col => {
                     if (formData.has(col)) {
                         currentData[col] = formData.get(col);
                     }
                 });
                 const newDiffSql = generateCreatureUpdateSqlJS(originalCreatureData, currentData, validDbColumns, 'diff');
                 if (diffSqlDataElem) {
                     diffSqlDataElem.value = newDiffSql;
                 }
                 if (diffQueryRadio && diffQueryRadio.checked) {
                     if(sqlDisplay) sqlDisplay.textContent = newDiffSql;
                 }
             }

             // Attach SQL Preview Listeners
             if (sqlDisplay && diffQueryRadio && fullQueryRadio && diffSqlDataElem && fullSqlDataElem) { /* ... */ 
                 const updateSqlPreview = () => {
                     if (diffQueryRadio.checked) {
                         sqlDisplay.textContent = diffSqlDataElem.value;
                     } else if (fullQueryRadio.checked) {
                         sqlDisplay.textContent = fullSqlDataElem.value;
                     }
                 };
                 diffQueryRadio.addEventListener('change', updateSqlPreview);
                 fullQueryRadio.addEventListener('change', updateSqlPreview);
             }
             if (copySqlBtn && sqlDisplay) { /* ... */ 
                 copySqlBtn.addEventListener('click', async () => { 
                     const textToCopy = sqlDisplay.textContent;
                     try {
                         if (navigator.clipboard && window.isSecureContext) {
                             await navigator.clipboard.writeText(textToCopy);
                         } else {
                             const textArea = document.createElement('textarea');
                             textArea.value = textToCopy;
                             textArea.style.position = 'fixed';
                             textArea.style.top = '-9999px';
                             textArea.style.left = '-9999px';
                             document.body.appendChild(textArea);
                             textArea.focus();
                             textArea.select();
                             try {
                                 const successful = document.execCommand('copy');
                                 if (!successful) {
                                     throw new Error('execCommand returned false.');
                                 }
                             } catch (err) {
                                 console.error('Fallback copy failed:', err);
                                 alert('复制 SQL 失败！(Fallback)');
                                 return;
                             } finally {
                                 document.body.removeChild(textArea);
                             }
                         }
                         const originalText = copySqlBtn.innerHTML;
                         copySqlBtn.innerHTML = '<i class="fas fa-check"></i> 已复制!';
                         copySqlBtn.disabled = true;
                         setTimeout(() => { copySqlBtn.innerHTML = originalText; copySqlBtn.disabled = false; }, 1500);
                     } catch (err) {
                         console.error('Failed to copy SQL:', err);
                         alert('复制 SQL 失败！请检查浏览器权限或安全设置。');
                     }
                 });
             }
             if (executeSqlBtn) { /* ... */ 
                 executeSqlBtn.addEventListener('click', (event) => {
                     event.preventDefault();
                     saveCreatureAjax();
                 });
             }
             if (creatureEditForm) { /* ... */ 
                 creatureEditForm.addEventListener('input', handleFormChange);
                 creatureEditForm.addEventListener('change', handleFormChange);
             }

             // Bitmask Modal Logic (Implementations omitted for brevity, keep existing code)
             function updateModalTotal(modalId, total) { /* ... */ 
                 const totalSpan = document.getElementById(`${modalId}-total`);
                 if (totalSpan) {
                     totalSpan.textContent = `当前总值: ${total}`;
                 }
             }
             document.querySelectorAll('.bitmask-checkbox').forEach(checkbox => { /* ... */ 
                 checkbox.addEventListener('change', (event) => {
                     const targetInputId = event.target.dataset.targetInput;
                     const targetInput = document.getElementById(targetInputId);
                     const modalId = checkbox.closest('.modal').id;
                     if (targetInput && modalId) {
                         let currentTotal = 0;
                         document.querySelectorAll(`#${modalId} .bitmask-checkbox:checked`).forEach(checkedBox => {
                             currentTotal += parseInt(checkedBox.value, 10);
                         });
                         targetInput.value = currentTotal;
                         updateModalTotal(modalId, currentTotal);
                         handleFormChange();
                     }
                 });
             });
             document.querySelectorAll('.modal[id^="modal-"]').forEach(modal => { /* ... */ 
                 modal.addEventListener('show.bs.modal', (event) => {
                     const modalId = modal.id;
                     if (modalId.startsWith('modal-npcflag') || modalId.startsWith('modal-unit_flags') || modalId.startsWith('modal-unit_flags2') || modalId.startsWith('modal-dynamicflags') || modalId.startsWith('modal-type_flags') || modalId.startsWith('modal-flags_extra') || modalId.startsWith('modal-mechanic_immune_mask') || modalId.startsWith('modal-spell_school_immune_mask')){
                         const targetInputId = modalId.replace('modal-', ''); 
                     const targetInput = document.getElementById(targetInputId);
                     const checkboxes = modal.querySelectorAll('.bitmask-checkbox');
                     if (targetInput && checkboxes.length > 0) {
                         const currentMaskValue = parseInt(targetInput.value, 10) || 0;
                         let calculatedTotal = 0;
                         checkboxes.forEach(checkbox => {
                             const flagValue = parseInt(checkbox.value, 10);
                             if ((currentMaskValue & flagValue) === flagValue) {
                                 checkbox.checked = true;
                                     calculatedTotal += flagValue;
                             } else {
                                 checkbox.checked = false;
                             }
                         });
                             updateModalTotal(modalId, calculatedTotal);
                         }
                     }
                 });
             });

             // Creature Model Add/Edit/Delete Listeners
             if (addModelBtn && addModelModal) { /* ... */ 
                console.log("Attaching listener to Add Model Button (#add-model-btn)");
                addModelBtn.addEventListener('click', () => {
                    if(currentAddModelForm) currentAddModelForm.reset();
                    addModelModal.show();
                });
             } else {
                console.error("Could not find Add Model Button (#add-model-btn) or Modal to attach listener.");
             }
             if (currentAddModelForm) { /* ... */ 
                console.log("Attaching listener to Add Model Form (#addModelForm)");
                currentAddModelForm.addEventListener('submit', (event) => {
                    event.preventDefault();
                    const formData = new FormData(currentAddModelForm);
                    const displayId = formData.get('display_id');
                    const scale = formData.get('scale');
                    const probability = formData.get('probability');
                    if (!displayId || !scale || !probability || isNaN(displayId) || isNaN(scale) || isNaN(probability)) {
                        alert('请填写所有模型字段并确保它们是有效的数字。');
                        return;
                    }
                    if (parseFloat(probability) < 0 || parseFloat(probability) > 1) {
                        alert('概率必须介于 0 和 1 之间。');
                        return;
                    }
                    if(addModelModal) addModelModal.hide();
                    handleModelAjax('add_creature_model', formData, '新增模型');
             });
             } else {
                console.error("Could not find Add Model Form (#addModelForm) to attach listener.");
             }
             if (modelTableBody) { /* ... */ 
                console.log("Attaching delegated listener to Model Table Body (.section-group-新模型 tbody)");
                modelTableBody.addEventListener('click', (event) => {
                    const editButton = event.target.closest('.edit-model-btn');
                    const deleteButton = event.target.closest('.delete-model-btn');
                    if (editButton && editModelModal) {
                        console.log("Edit model button clicked (.edit-model-btn)");
                        const row = editButton.closest('tr');
                        const idx = row.dataset.idx;
                        const displayId = row.dataset.displayid;
                        const scale = row.dataset.scale;
                        const probability = row.dataset.probability;
                        const verifiedBuild = row.dataset.verifiedbuild; // GET verifiedBuild
                        if(currentEditModelForm) { 
                           const idxInput = currentEditModelForm.querySelector('#edit_idx');
                           const displayIdInput = currentEditModelForm.querySelector('#edit_display_id');
                           const scaleInput = currentEditModelForm.querySelector('#edit_scale');
                           const probabilityInput = currentEditModelForm.querySelector('#edit_probability');
                           const verifiedBuildInput = currentEditModelForm.querySelector('#edit_verifiedbuild'); // GET input element
                           if (idxInput) idxInput.value = idx;
                           if (displayIdInput) displayIdInput.value = displayId;
                           if (scaleInput) scaleInput.value = scale;
                           if (probabilityInput) probabilityInput.value = probability;
                           if (verifiedBuildInput) verifiedBuildInput.value = verifiedBuild; // SET value
                           editModelModal.show();
                        } else {
                           console.error("Cannot populate edit form: Edit Model Form not found.");
                        }
                    } else if (deleteButton) {
                        console.log("Delete model button clicked (.delete-model-btn)");
                        const row = deleteButton.closest('tr');
                        const idx = row.dataset.idx;
                        const displayId = row.dataset.displayid;
                        if (confirm(`确定要删除 Creature ${creatureEntry} 的模型条目 (Idx: ${idx}, DisplayID: ${displayId}) 吗？`)) {
                             const formData = new FormData();
                             formData.append('action', 'delete_creature_model');
                             formData.append('creature_id', creatureEntry);
                             formData.append('idx', idx);
                             handleModelAjax('delete_creature_model', formData, '删除模型');
                        }
                    }
                });
             } else {
                console.error("Could not find Model Table Body (.section-group-新模型 tbody) to attach delegated listener.");
             }
             if (currentEditModelForm) { /* ... */ 
                console.log("Attaching listener to Edit Model Form (#editModelForm)");
                currentEditModelForm.addEventListener('submit', (event) => {
                   event.preventDefault();
                   const formData = new FormData(currentEditModelForm);
                   const displayId = formData.get('display_id');
                   const scale = formData.get('scale');
                   const probability = formData.get('probability');
                   const verifiedBuild = formData.get('verifiedbuild'); // GET verifiedBuild from form
                   // Basic validation (already includes other fields)
                   if (!verifiedBuild || isNaN(verifiedBuild)) {
                        alert('请填写有效的验证版本 (VerifiedBuild)。');
                        return;
                   }
                   if (!displayId || !scale || !probability || isNaN(displayId) || isNaN(scale) || isNaN(probability)) {
                       alert('请填写所有模型字段并确保它们是有效的数字。');
                       return;
                   }
                   if(editModelModal) editModelModal.hide();
                   handleModelAjax('edit_creature_model', formData, '编辑模型');
                });
             } else {
                console.error("Could not find Edit Model Form (#editModelForm) to attach listener.");
             }
         }); // End DOMContentLoaded for edit mode
     </script>
     <?php endif; ?> <?php // End the edit mode specific script block ?>

     <script>
         // General JS Loaded Log (Runs always, should contain only universally needed JS or search mode JS)
         document.addEventListener('DOMContentLoaded', () => {
             console.log('Creature Editor JS Loaded (General)');

             // --- Delete Creature Button Handling (Search Results List) ---
             document.querySelectorAll('.deleteCreatureBtn').forEach(button => {
                 // This check ensures we only attach the listener if NOT in edit mode
                 // Redundant check now? Button only exists in list view. Kept for safety.
                 if (!document.getElementById('creature-edit-form')) { 
                    button.addEventListener('click', async (event) => { // Make async
                         const creatureId = button.dataset.creatureId;
                         const creatureName = button.dataset.creatureName;

                         if (!creatureId) {
                            alert('错误：无法获取生物 ID。');
                            return;
                         }

                         if (confirm(`确定要永久删除生物 ID: ${creatureId} (${creatureName}) 吗？\n此操作无法撤销！`)) {
                             // Disable button and show loading state
                             button.disabled = true;
                             button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 删除中...';

                             // --- AJAX Delete Request ---
                             const formData = new FormData();
                             formData.append('action', 'delete_creature_ajax');
                             formData.append('creature_id', creatureId);

                             try {
                                 const response = await fetch('index.php', {
                                     method: 'POST',
                                     body: formData
                                 });
                                 if (!response.ok) {
                                     throw new Error(`HTTP error! status: ${response.status}`);
                                 }
                                 const result = await response.json();

                                 alert(result.message); // Show result message

                                 if (result.success) {
                                     // Remove the table row from the DOM
                                     const row = button.closest('tr');
                                     if (row) {
                                         row.remove();
                                         // Optionally update the total count display if needed
                                         // const totalCountElement = document.querySelector('h4.text-success'); 
                                         // if(totalCountElement) { 
                                         //    let currentCount = parseInt(totalCountElement.textContent.match(/\((\d+)\)/)?.[1] ?? 'NaN'); 
                                         //    if (!isNaN(currentCount)) { 
                                         //       totalCountElement.textContent = `结果 (${currentCount - 1})` 
                                         //    } 
                                         // }
                                     }
                                 } else {
                                     // Re-enable button on failure
                                     button.disabled = false;
                                     button.innerHTML = '删除';
                                 }
                             } catch (error) {
                                 console.error('Delete Creature AJAX Error:', error);
                                 alert('删除生物时发生错误: ' + error.message);
                                 // Re-enable button on error
                                 button.disabled = false;
                                 button.innerHTML = '删除';
                             }
                         }
                     });
                 }
             }); // End deleteCreatureBtn handling

             // --- Add New Creature Modal Handling ---
             const addNewCreatureBtn = document.getElementById('addNewCreatureBtn');
             const addNewCreatureModalEl = document.getElementById('addNewCreatureModal');
             const addNewCreatureForm = document.getElementById('addNewCreatureForm');
             const addNewCreatureSubmitBtn = addNewCreatureForm ? addNewCreatureForm.querySelector('button[type="submit"]') : null; // Get submit button

             if (addNewCreatureBtn && addNewCreatureModalEl && addNewCreatureForm && addNewCreatureSubmitBtn) { // Check button too
                 const addNewCreatureModal = new bootstrap.Modal(addNewCreatureModalEl); 

                 addNewCreatureForm.addEventListener('submit', async (event) => { // Make async
                     event.preventDefault(); // Prevent default form submission
                     const copyIdInput = document.getElementById('copy_creature_id');
                     const newIdInput = document.getElementById('new_creature_id');

                     const copyId = copyIdInput.value.trim();
                     const newId = newIdInput.value.trim();

                     if (!newId || isNaN(parseInt(newId)) || parseInt(newId) <= 0) { // Improved validation
                         alert('必须填写有效的新建生物 ID (正整数)！');
                         return;
                     }
                     if (copyId && (isNaN(parseInt(copyId)) || parseInt(copyId) <= 0)) { // Validate copyId if provided
                        alert('需要复制的生物 ID 必须是有效的正整数！');
                        return;
                     }

                     // Disable button and show loading state
                     addNewCreatureSubmitBtn.disabled = true;
                     addNewCreatureSubmitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 创建中...';

                     // --- AJAX Request ---
                     const formData = new FormData();
                     formData.append('action', 'add_creature_ajax');
                     formData.append('new_creature_id', newId);
                     if (copyId) {
                         formData.append('copy_creature_id', copyId);
                     }

                     try {
                         const response = await fetch('index.php', {
                             method: 'POST',
                             body: formData
                         });
                         if (!response.ok) {
                             throw new Error(`HTTP error! status: ${response.status}`);
                         }
                         const result = await response.json();

                         alert(result.message); // Show message from backend

                         if (result.success && result.new_id) {
                             // Redirect on success
                             window.location.href = `index.php?edit_id=${result.new_id}`;
                             // No need to hide modal manually on redirect
                         } else {
                             // Re-enable button on failure
                             addNewCreatureSubmitBtn.disabled = false;
                             addNewCreatureSubmitBtn.innerHTML = '创建生物';
                             addNewCreatureModal.hide(); // Hide modal only on failure or non-redirect success
                         }

                     } catch (error) {
                         console.error('Add Creature AJAX Error:', error);
                         alert('创建生物时发生错误: ' + error.message);
                         // Re-enable button on error
                         addNewCreatureSubmitBtn.disabled = false;
                         addNewCreatureSubmitBtn.innerHTML = '创建生物';
                         addNewCreatureModal.hide(); // Hide modal on error
                     } 
                     // Removed finally block as logic is handled in success/error paths
                 });
             } else {
                 if (!addNewCreatureBtn) console.error("Add New Creature Button not found!");
                 if (!addNewCreatureModalEl) console.error("Add New Creature Modal element not found!");
                 if (!addNewCreatureForm) console.error("Add New Creature Form not found!");
                 if (!addNewCreatureSubmitBtn) console.error("Add New Creature Submit Button not found!");
             }

             // --- End Add New Creature Modal Handling ---
         });
     </script>

<!-- MODALS FOR MODEL EDIT/ADD (These MUST be outside the main content conditional block) -->
<?php if ($isLoggedIn && $creature_to_edit): // Conditionally render modals only in edit mode ?>
    <!-- Add Model Modal -->
    <div class="modal fade" id="addModelModal" tabindex="-1" aria-labelledby="addModelModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content bg-dark text-light">
          <form id="addModelForm">
            <div class="modal-header">
              <h5 class="modal-title" id="addModelModalLabel">新增模型条目 (Creature: <?= $creature_entry ?>)</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <input type="hidden" name="action" value="add_creature_model">
              <input type="hidden" name="creature_id" value="<?= $creature_entry ?>">
              <div class="mb-3">
                <label for="add_display_id" class="form-label">模型显示 ID (CreatureDisplayID)</label>
                <input type="number" class="form-control form-control-sm" id="add_display_id" name="display_id" required min="1">
              </div>
              <div class="mb-3">
                <label for="add_scale" class="form-label">模型缩放 (DisplayScale)</label>
                <input type="number" step="0.01" class="form-control form-control-sm" id="add_scale" name="scale" required min="0.01" value="1.0">
              </div>
              <div class="mb-3">
                <label for="add_probability" class="form-label">概率 (Probability)</label>
                <input type="number" step="0.01" class="form-control form-control-sm" id="add_probability" name="probability" required min="0" max="1" value="1.0">
                <div class="form-text">所有模型的概率总和应为 1 (或接近 1)。系统会在添加/编辑/删除后尝试自动调整。</div>
              </div>
              <!-- ADDED VerifiedBuild field -->
              <div class="mb-3">
                <label for="add_verifiedbuild" class="form-label">验证版本 (VerifiedBuild)</label>
                <input type="number" class="form-control form-control-sm" id="add_verifiedbuild" name="verifiedbuild" value="12340">
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
              <button type="submit" class="btn btn-success">确认新增</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Edit Model Modal -->
    <div class="modal fade" id="editModelModal" tabindex="-1" aria-labelledby="editModelModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content bg-dark text-light">
          <form id="editModelForm">
            <div class="modal-header">
              <h5 class="modal-title" id="editModelModalLabel">编辑模型条目 (Creature: <?= $creature_entry ?>)</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <input type="hidden" name="action" value="edit_creature_model">
              <input type="hidden" name="creature_id" value="<?= $creature_entry ?>">
              <input type="hidden" id="edit_idx" name="idx">
              <div class="mb-3">
                 <label for="edit_display_id" class="form-label">模型显示 ID (CreatureDisplayID)</label>
                 <input type="number" class="form-control form-control-sm" id="edit_display_id" name="display_id" required min="1">
              </div>
              <div class="mb-3">
                 <label for="edit_scale" class="form-label">模型缩放 (DisplayScale)</label>
                 <input type="number" step="0.01" class="form-control form-control-sm" id="edit_scale" name="scale" required min="0.01">
              </div>
              <div class="mb-3">
                 <label for="edit_probability" class="form-label">概率 (Probability)</label>
                 <input type="number" step="0.01" class="form-control form-control-sm" id="edit_probability" name="probability" required min="0" max="1">
                 <div class="form-text">所有模型的概率总和应为 1 (或接近 1)。系统会在添加/编辑/删除后尝试自动调整。</div>
              </div>
              <!-- ADDED VerifiedBuild field -->
              <div class="mb-3">
                 <label for="edit_verifiedbuild" class="form-label">验证版本 (VerifiedBuild)</label>
                 <input type="number" class="form-control form-control-sm" id="edit_verifiedbuild" name="verifiedbuild">
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
              <button type="submit" class="btn btn-warning">保存更改</button>
            </div>
          </form>
        </div>
      </div>
    </div>
<?php endif; ?> <?php // End the conditional rendering of modals ?>

<?php endif; ?> <?php // THIS IS THE OVERALL endif for the main `if (!$isLoggedIn):` block ?>

<script>
    // Script for filter form conditional inputs
    document.addEventListener('DOMContentLoaded', () => {
        function toggleSecondInput(typeSelectId, valueInput1Id, valueInput2Id) {
            const typeSelect = document.getElementById(typeSelectId);
            const valueInput1 = document.getElementById(valueInput1Id);
            const valueInput2 = document.getElementById(valueInput2Id);

            if (typeSelect && valueInput1 && valueInput2) {
                if (typeSelect.value === 'between') {
                    valueInput1.placeholder = '起始值'; // Set placeholder for first input
                    valueInput2.placeholder = '结束值'; // Set placeholder for second input
                    valueInput2.style.display = 'block'; // Show second input
                } else {
                    valueInput1.placeholder = '值 1'; // Restore placeholder for first input
                    valueInput2.style.display = 'none'; // Hide second input
                    // Optionally clear placeholder for second input: valueInput2.placeholder = '';
                }
            } else {
               // Log error if any element is missing
               if (!typeSelect) console.error(`Element not found: ${typeSelectId}`);
               if (!valueInput1) console.error(`Element not found: ${valueInput1Id}`);
               if (!valueInput2) console.error(`Element not found: ${valueInput2Id}`);
            }
        }

        const idTypeSelect = document.getElementById('id_filter_type');
        const levelTypeSelect = document.getElementById('level_filter_type');

        if (idTypeSelect) {
            // Pass IDs for type select, first value input, and second value input
            idTypeSelect.addEventListener('change', () => toggleSecondInput('id_filter_type', 'id_filter_value1', 'id_filter_value2'));
            // Initial check
            toggleSecondInput('id_filter_type', 'id_filter_value1', 'id_filter_value2'); 
        } else {
            console.error("Could not find element with ID: id_filter_type");
        }
        
        if (levelTypeSelect) {
            // Pass IDs for type select, first value input, and second value input
            levelTypeSelect.addEventListener('change', () => toggleSecondInput('level_filter_type', 'level_filter_value1', 'level_filter_value2'));
            // Initial check
            toggleSecondInput('level_filter_type', 'level_filter_value1', 'level_filter_value2');
        } else {
            console.error("Could not find element with ID: level_filter_type");
        }
    });
     </script>

     <!-- ADD NEW CREATURE MODAL (Placed outside the main edit/search conditional blocks) -->
     <?php if ($isLoggedIn && !$creature_to_edit): // Only render if logged in AND NOT in edit mode ?>
     <div class="modal fade" id="addNewCreatureModal" tabindex="-1" aria-labelledby="addNewCreatureModalLabel" aria-hidden="true">
       <div class="modal-dialog">
         <div class="modal-content bg-dark text-light">
           <form id="addNewCreatureForm">
             <div class="modal-header">
               <h5 class="modal-title" id="addNewCreatureModalLabel">新增生物模板</h5>
               <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
             </div>
             <div class="modal-body">
               <p class="small text-muted">输入新的生物 ID。您可以选择性地提供一个现有生物 ID，以复制其数据作为新生物的起点。</p>
               <div class="mb-3">
                 <label for="copy_creature_id" class="form-label form-label-sm">复制现有生物 ID (可选)</label>
                 <input type="number" class="form-control form-control-sm" id="copy_creature_id" name="copy_creature_id" placeholder="留空则使用空白模板">
               </div>
               <div class="mb-3">
                 <label for="new_creature_id" class="form-label form-label-sm">新建生物 ID <span class="text-danger">*</span></label>
                 <input type="number" class="form-control form-control-sm" id="new_creature_id" name="new_creature_id" required min="1" placeholder="必须填写唯一的数字 ID">
               </div>
             </div>
             <div class="modal-footer">
               <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
               <button type="submit" class="btn btn-success">创建生物</button>
             </div>
           </form>
         </div>
       </div>
     </div>
     <?php endif; ?>
     <!-- END ADD NEW CREATURE MODAL -->

</body>
</html> 