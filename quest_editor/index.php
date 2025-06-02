<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
session_start();

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$config = require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../bag_query/db.php'; // Assuming shared db connection logic
// require_once __DIR__ . '/functions.php'; // Placeholder for quest-specific functions

// --- Message Handling (For displaying feedback after actions) --- Start ---
$feedback_message = '';
$feedback_type = 'info'; // can be 'success', 'danger', 'warning', 'info'
if (isset($_SESSION['feedback_message'])) {
    $feedback_message = $_SESSION['feedback_message'];
    $feedback_type = $_SESSION['feedback_type'] ?? 'info';
    unset($_SESSION['feedback_message']);
    unset($_SESSION['feedback_type']);
}
// --- Message Handling --- End ---


// --- Login Logic (Adapted from item_editor) --- Start ---
$adminConf = $config['admin'] ?? null;
$isLoggedIn = false;
$loginError = '';

// Logout Handling (Optional, good practice)
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php'); // Redirect to self after logout
    exit;
}

if (!$adminConf || !isset($adminConf['username']) || !isset($adminConf['password_hash'])) {
    $loginError = '管理后台配置不完整，无法登录。';
} else {
    // Handle login form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_username']) && isset($_POST['login_password'])) {
        $username = $_POST['login_username'];
        $password = $_POST['login_password'];
        // Verify username and password hash
        if ($username === $adminConf['username'] && password_verify($password, $adminConf['password_hash'])) {
            $_SESSION['admin_logged_in'] = true;
            // Redirect to clear POST data and show editor
            header('Location: index.php');
            exit;
        } else {
            $loginError = '无效的用户名或密码。';
        }
    }
    // Check session status AFTER potential login attempt or on normal page load
    if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        $isLoggedIn = true;
    }
}
// --- Login Logic --- End ---

// --- Create Quest Logic --- Start ---
if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_quest') {
    $new_quest_id = filter_input(INPUT_POST, 'newQuestId', FILTER_VALIDATE_INT);
    $copy_quest_id_input = trim($_POST['copyQuestId'] ?? '');
    $copy_quest_id = ($copy_quest_id_input !== '') ? filter_var($copy_quest_id_input, FILTER_VALIDATE_INT) : null;
    
    $create_error = null; // Store potential errors
    $pdo_W_create = null;

    // Basic Validation
    if (!$new_quest_id || $new_quest_id <= 0) {
        $create_error = '错误：必须提供一个有效的新任务 ID (正整数)。';
    } elseif ($copy_quest_id !== null && $copy_quest_id <= 0) {
        $create_error = '错误：如果要复制任务，复制源任务 ID 必须是有效的正整数。';
    } elseif ($copy_quest_id !== null && $copy_quest_id === $new_quest_id) {
         $create_error = '错误：新任务 ID 不能与复制源任务 ID 相同。';
    }
    
    if ($create_error === null) {
        try {
            $connections = connect_databases();
            if (!isset($connections['db_W'])) {
                throw new Exception("World 数据库连接未配置或失败。");
            }
            $pdo_W_create = $connections['db_W'];
            $pdo_W_create->beginTransaction(); // Start transaction

            // Check if newQuestId already exists
            $check_stmt = $pdo_W_create->prepare("SELECT 1 FROM quest_template WHERE ID = :id");
            $check_stmt->bindParam(':id', $new_quest_id, PDO::PARAM_INT);
            $check_stmt->execute();
            if ($check_stmt->fetchColumn()) {
                throw new Exception("错误：新任务 ID {$new_quest_id} 已存在。");
            }

            if ($copy_quest_id !== null) {
                // --- Copy existing quest ---
                // Check if copyQuestId exists
                $copy_stmt = $pdo_W_create->prepare("SELECT * FROM quest_template WHERE ID = :copy_id");
                $copy_stmt->bindParam(':copy_id', $copy_quest_id, PDO::PARAM_INT);
                $copy_stmt->execute();
                $source_data = $copy_stmt->fetch(PDO::FETCH_ASSOC);

                if (!$source_data) {
                    throw new Exception("错误：找不到要复制的源任务 ID {$copy_quest_id}。");
                }

                // Prepare data for insertion
                $source_data['ID'] = $new_quest_id; // Set the new ID
                $columns = array_keys($source_data);
                $placeholders = array_map(function($col) { return ':' . $col; }, $columns);
                $insert_sql = "INSERT INTO `quest_template` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $placeholders) . ")";
                
                $insert_stmt = $pdo_W_create->prepare($insert_sql);
                
                // Bind parameters (ensure correct types if necessary)
                foreach ($source_data as $key => $value) {
                    $param_type = PDO::PARAM_STR; // Default to string
                    if (is_int($value)) {
                        $param_type = PDO::PARAM_INT;
                    } elseif (is_null($value)) {
                        $param_type = PDO::PARAM_NULL;
                    }
                    $insert_stmt->bindValue(':' . $key, $value, $param_type);
                }

                if (!$insert_stmt->execute()) {
                     $errorInfo = $insert_stmt->errorInfo();
                     throw new Exception("复制任务时数据库错误: " . ($errorInfo[2] ?? 'Unknown error'));
                }
                 $_SESSION['feedback_message'] = "成功从 ID {$copy_quest_id} 复制并创建新任务 ID {$new_quest_id}。";
                 $_SESSION['feedback_type'] = 'success';

            } else {
                // --- Create blank quest ---
                // Use the provided template, but with the new ID
                $blank_sql = "INSERT INTO `quest_template` (`ID`, `QuestType`, `QuestLevel`, `MinLevel`, `QuestSortID`, `QuestInfoID`, `SuggestedGroupNum`, `RequiredFactionId1`, `RequiredFactionId2`, `RequiredFactionValue1`, `RequiredFactionValue2`, `RewardNextQuest`, `RewardXPDifficulty`, `RewardMoney`, `RewardMoneyDifficulty`, `RewardDisplaySpell`, `RewardSpell`, `RewardHonor`, `RewardKillHonor`, `StartItem`, `Flags`, `RequiredPlayerKills`, `RewardItem1`, `RewardAmount1`, `RewardItem2`, `RewardAmount2`, `RewardItem3`, `RewardAmount3`, `RewardItem4`, `RewardAmount4`, `ItemDrop1`, `ItemDropQuantity1`, `ItemDrop2`, `ItemDropQuantity2`, `ItemDrop3`, `ItemDropQuantity3`, `ItemDrop4`, `ItemDropQuantity4`, `RewardChoiceItemID1`, `RewardChoiceItemQuantity1`, `RewardChoiceItemID2`, `RewardChoiceItemQuantity2`, `RewardChoiceItemID3`, `RewardChoiceItemQuantity3`, `RewardChoiceItemID4`, `RewardChoiceItemQuantity4`, `RewardChoiceItemID5`, `RewardChoiceItemQuantity5`, `RewardChoiceItemID6`, `RewardChoiceItemQuantity6`, `POIContinent`, `POIx`, `POIy`, `POIPriority`, `RewardTitle`, `RewardTalents`, `RewardArenaPoints`, `RewardFactionID1`, `RewardFactionValue1`, `RewardFactionOverride1`, `RewardFactionID2`, `RewardFactionValue2`, `RewardFactionOverride2`, `RewardFactionID3`, `RewardFactionValue3`, `RewardFactionOverride3`, `RewardFactionID4`, `RewardFactionValue4`, `RewardFactionOverride4`, `RewardFactionID5`, `RewardFactionValue5`, `RewardFactionOverride5`, `TimeAllowed`, `AllowableRaces`, `LogTitle`, `LogDescription`, `QuestDescription`, `AreaDescription`, `QuestCompletionLog`, `RequiredNpcOrGo1`, `RequiredNpcOrGo2`, `RequiredNpcOrGo3`, `RequiredNpcOrGo4`, `RequiredNpcOrGoCount1`, `RequiredNpcOrGoCount2`, `RequiredNpcOrGoCount3`, `RequiredNpcOrGoCount4`, `RequiredItemId1`, `RequiredItemId2`, `RequiredItemId3`, `RequiredItemId4`, `RequiredItemId5`, `RequiredItemId6`, `RequiredItemCount1`, `RequiredItemCount2`, `RequiredItemCount3`, `RequiredItemCount4`, `RequiredItemCount5`, `RequiredItemCount6`, `Unknown0`, `ObjectiveText1`, `ObjectiveText2`, `ObjectiveText3`, `ObjectiveText4`, `VerifiedBuild`) VALUES
                (:new_id, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '', '', '', '', '', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '', '', '', '', 0)";
                
                $blank_stmt = $pdo_W_create->prepare($blank_sql);
                $blank_stmt->bindParam(':new_id', $new_quest_id, PDO::PARAM_INT);

                if (!$blank_stmt->execute()) {
                    $errorInfo = $blank_stmt->errorInfo();
                    throw new Exception("创建空白任务时数据库错误: " . ($errorInfo[2] ?? 'Unknown error'));
                }
                 $_SESSION['feedback_message'] = "成功创建空白任务 ID {$new_quest_id}。";
                 $_SESSION['feedback_type'] = 'success';
            }
            
            $pdo_W_create->commit(); // Commit transaction if everything was successful
            
            // Redirect to the new quest editor page
            header('Location: edit_quest.php?id=' . $new_quest_id);
            exit;

        } catch (Exception $e) {
             if ($pdo_W_create && $pdo_W_create->inTransaction()) {
                 $pdo_W_create->rollBack(); // Rollback on error
             }
            $create_error = $e->getMessage();
            $_SESSION['feedback_message'] = $create_error; // Store error message for display
            $_SESSION['feedback_type'] = 'danger';
            // Redirect back to index to show the error and keep modal potentially open (needs JS)
            header('Location: index.php'); 
            exit;
        }
    } else {
        // Validation error occurred before DB attempt
        $_SESSION['feedback_message'] = $create_error;
        $_SESSION['feedback_type'] = 'danger';
         header('Location: index.php'); // Redirect back to index to show the error
         exit;
    }
}
// --- Create Quest Logic --- End ---

// --- Delete Quest Logic --- Start ---
if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_quest') {
    $quest_id_to_delete = filter_input(INPUT_POST, 'quest_id_to_delete', FILTER_VALIDATE_INT);
    $delete_error = null;
    $pdo_W_delete = null;

    if (!$quest_id_to_delete || $quest_id_to_delete <= 0) {
        $delete_error = '错误：无效的任务 ID。';
    } else {
        try {
            $connections = connect_databases();
            if (!isset($connections['db_W'])) {
                throw new Exception("World 数据库连接未配置或失败。");
            }
            $pdo_W_delete = $connections['db_W'];

            $delete_sql = "DELETE FROM `quest_template` WHERE `ID` = :quest_id";
            $delete_stmt = $pdo_W_delete->prepare($delete_sql);
            $delete_stmt->bindParam(':quest_id', $quest_id_to_delete, PDO::PARAM_INT);

            if ($delete_stmt->execute()) {
                $affected_rows = $delete_stmt->rowCount();
                if ($affected_rows > 0) {
                    $_SESSION['feedback_message'] = "成功删除任务 ID {$quest_id_to_delete}。";
                    $_SESSION['feedback_type'] = 'success';
                } else {
                     $_SESSION['feedback_message'] = "警告：未找到要删除的任务 ID {$quest_id_to_delete}，或任务已被删除。";
                     $_SESSION['feedback_type'] = 'warning';
                }
            } else {
                $errorInfo = $delete_stmt->errorInfo();
                throw new Exception("删除任务时数据库错误: " . ($errorInfo[2] ?? 'Unknown error'));
            }
        } catch (Exception $e) {
             $delete_error = $e->getMessage();
             $_SESSION['feedback_message'] = "删除任务时出错: " . $delete_error;
             $_SESSION['feedback_type'] = 'danger';
        }
    }

     if ($delete_error && empty($_SESSION['feedback_message'])) { // Set feedback only if not set by DB logic
        $_SESSION['feedback_message'] = $delete_error;
        $_SESSION['feedback_type'] = 'danger';
     }

     // Redirect back to index page to show feedback and refresh list
     header('Location: index.php');
     exit;
}
// --- Delete Quest Logic --- End ---

// If logged in, proceed with editor logic
if ($isLoggedIn) {
    $canEdit = true; // User is logged in, allow editing

    // --- Filter Values --- (Moved inside logged-in block)
    $filter_id = filter_input(INPUT_GET, 'filter_id', FILTER_VALIDATE_INT);
    $filter_title = filter_input(INPUT_GET, 'filter_title', FILTER_SANITIZE_STRING);

    // Get filter_type, handle false return from filter_input for empty/invalid values
    $filter_type_input = $_GET['filter_type'] ?? null;
    if ($filter_type_input === '' || $filter_type_input === null) {
        $filter_type = null; // Treat empty string or null as no selection
    } else {
        $filter_type_validated = filter_var($filter_type_input, FILTER_VALIDATE_INT);
        // Assign only if it's a valid integer (including 0), otherwise null
        $filter_type = ($filter_type_validated !== false) ? $filter_type_validated : null;
    }

    // --- New Filter Params for Level and MinLevel ---
    $filter_level_op = $_GET['filter_level_op'] ?? 'any'; 
    $filter_level_val = trim($_GET['filter_level_val'] ?? '');
    $filter_min_level_op = $_GET['filter_min_level_op'] ?? 'any';
    $filter_min_level_val = trim($_GET['filter_min_level_val'] ?? '');

    // Validate operators
    $valid_ops = ['any', 'ge', 'le', 'eq', 'between'];
    if (!in_array($filter_level_op, $valid_ops)) { $filter_level_op = 'any'; }
    if (!in_array($filter_min_level_op, $valid_ops)) { $filter_min_level_op = 'any'; }

    // --- DEBUG: Dump initial filter values --- Start ---
    /*
    echo "<pre style='background: #eee; color: #000; padding: 10px; border: 1px solid red;'>";
    echo "DEBUG - Initial Filter Values:\n";
    var_dump(['id' => $filter_id, 'title' => $filter_title, 'level' => $filter_level, 'min_level' => $filter_min_level, 'type' => $filter_type, 'sort_id' => $filter_sort_id]);
    echo "</pre>";
    */
    // --- DEBUG: Dump initial filter values --- End ---

    // --- Pagination & Limit --- (Added)
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? max(10, min(500, (int)$_GET['limit'])) : 50; // Default 50, min 10, max 500
    $offset = ($page - 1) * $limit;

    // --- Sorting --- (Added)
    $valid_sort_columns = ['ID', 'QuestLevel', 'MinLevel', 'LogTitle']; // Add 'LogTitle' if needed
    $sort_by = $_GET['sort_by'] ?? 'ID'; // Default sort by ID
    $sort_dir = strtoupper($_GET['sort_dir'] ?? 'ASC'); // Default sort direction ASC

    // Validate sort parameters
    if (!in_array($sort_by, $valid_sort_columns)) {
        $sort_by = 'ID';
    }
    if ($sort_dir !== 'ASC' && $sort_dir !== 'DESC') {
        $sort_dir = 'ASC';
    }

    $quests = [];
    $error_message = '';
    $pdo_W = null;

    try {
        $connections = connect_databases();
        if (!isset($connections['db_W'])) {
            throw new Exception("World 数据库连接未配置或失败。");
        }
        $pdo_W = $connections['db_W'];

        // --- Build WHERE clause and parameters (reusable for count and data query) ---
        $where_sql = " WHERE 1=1";
        $params = [];

        if ($filter_id !== null && $filter_id !== false) {
            $where_sql .= " AND ID = :filter_id";
            $params[':filter_id'] = $filter_id;
        }
        if ($filter_title !== null && $filter_title !== '') {
            $where_sql .= " AND LogTitle LIKE :filter_title";
            $params[':filter_title'] = '%' . $filter_title . '%';
        }
        // --- Updated QuestLevel Filter ---
        if ($filter_level_op !== 'any' && $filter_level_val !== '') {
            if ($filter_level_op === 'between') {
                $parts = explode('-', $filter_level_val);
                if (count($parts) === 2 && is_numeric(trim($parts[0])) && is_numeric(trim($parts[1]))) {
                    $where_sql .= " AND QuestLevel BETWEEN :level_min AND :level_max";
                    $params[':level_min'] = (int)trim($parts[0]);
                    $params[':level_max'] = (int)trim($parts[1]);
                }
            } elseif (in_array($filter_level_op, ['ge', 'le', 'eq']) && is_numeric($filter_level_val)) {
                $operator = ['ge' => '>=', 'le' => '<=', 'eq' => '='][$filter_level_op];
                $where_sql .= " AND QuestLevel {$operator} :filter_level";
                $params[':filter_level'] = (int)$filter_level_val;
            }
        }
        // --- Updated MinLevel Filter ---
        if ($filter_min_level_op !== 'any' && $filter_min_level_val !== '') {
            if ($filter_min_level_op === 'between') {
                $parts = explode('-', $filter_min_level_val);
                if (count($parts) === 2 && is_numeric(trim($parts[0])) && is_numeric(trim($parts[1]))) {
                    $where_sql .= " AND MinLevel BETWEEN :min_level_min AND :min_level_max";
                    $params[':min_level_min'] = (int)trim($parts[0]);
                    $params[':min_level_max'] = (int)trim($parts[1]);
                }
            } elseif (in_array($filter_min_level_op, ['ge', 'le', 'eq']) && is_numeric($filter_min_level_val)) {
                $operator = ['ge' => '>=', 'le' => '<=', 'eq' => '='][$filter_min_level_op];
                $where_sql .= " AND MinLevel {$operator} :filter_min_level";
                $params[':filter_min_level'] = (int)$filter_min_level_val;
            }
        }
         if ($filter_type !== null && $filter_type !== false) {
            $where_sql .= " AND QuestType = :filter_type";
            $params[':filter_type'] = $filter_type;
        }
        /* Removed Sort ID filter condition
        if ($filter_sort_id !== null && $filter_sort_id !== false) { 
            $where_sql .= " AND QuestSortID = :filter_sort_id";
            $params[':filter_sort_id'] = $filter_sort_id;
        }
        */
        // TODO: Add filters for Flags and AllowableRaces

        // --- Get Total Count --- (Added)
        $count_sql = "SELECT COUNT(*) FROM quest_template" . $where_sql;
        $count_stmt = $pdo_W->prepare($count_sql);
        $count_stmt->execute($params);
        $totalItems = (int)$count_stmt->fetchColumn();
        $totalPages = ($limit > 0) ? ceil($totalItems / $limit) : 0;

        // --- Get Paginated Data ---
        $sql = "SELECT ID, LogTitle, QuestDescription, QuestLevel, MinLevel, QuestType, QuestSortID FROM quest_template" . $where_sql;
        // Add ORDER BY clause based on validated sorting parameters
        $sql .= " ORDER BY `" . $sort_by . "` " . $sort_dir; // Safely use validated values

        // Add LIMIT and OFFSET for pagination
        $sql .= " LIMIT :limit OFFSET :offset";

        $stmt = $pdo_W->prepare($sql);

        // Combine all parameters into one array for execute()
        $execute_params = $params; // Start with filter params
        $execute_params[':limit'] = $limit; // Add limit
        $execute_params[':offset'] = $offset; // Add offset

        // Execute with the combined parameter array containing filters, limit, and offset
        $stmt->execute($execute_params);
        $quests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        $error_message = "查询任务时出错: " . $e->getMessage();
    }
}
// Note: $isLoggedIn, $loginError are defined before this point

// Define quest types for dropdown - Corrected based on wiki
$questTypes = [
    0 => '启用 (自动完成)',
    1 => '禁用',
    2 => '启用 (正常)',
    // 3 => '团队任务', // Removed, not standard type 0,1,2
];

// Define quest sort/zone areas for dropdown - No longer used
/*
$questSortAreas = [
    1 => '东部王国',
    2 => '卡利姆多',
    3 => '地下城',
    4 => '团队副本',
    // Add more as needed
];
*/

// Helper function to generate sorting links for table headers
function render_sortable_header($column_key, $column_label, $current_sort_by, $current_sort_dir, $base_params) {
    $link_params = $base_params;
    $link_params['sort_by'] = $column_key;
    $next_sort_dir = 'ASC';
    $icon_class = 'fa-sort text-muted'; // Default icon

    if ($current_sort_by === $column_key) {
        if ($current_sort_dir === 'ASC') {
            $next_sort_dir = 'DESC';
            $icon_class = 'fa-sort-up'; // Ascending icon
        } else {
            $next_sort_dir = 'ASC';
            $icon_class = 'fa-sort-down'; // Descending icon
        }
    }
    $link_params['sort_dir'] = $next_sort_dir;
    $link_href = '?' . http_build_query($link_params);
    $style = ($column_key === 'ID') ? 'width: 80px;' : (($column_key === 'QuestLevel') ? 'width: 100px;' : (($column_key === 'MinLevel') ? 'width: 100px;' : ''));
    $alignment = ($column_key === 'ID' || $column_key === 'QuestLevel' || $column_key === 'MinLevel') ? 'text-end' : '';
    return "<th style=\"{$style}\" class=\"{$alignment}\"><a href=\"{$link_href}\" class=\"text-decoration-none text-reset\">{$column_label} <i class=\"fas {$icon_class} ms-1\"></i></a></th>";
}

// Prepare base parameters for header links (filters, limit)
$header_base_params = $_GET;
unset($header_base_params['page']); // Remove page from header links
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>任务编辑器 - <?= $isLoggedIn ? '控制台' : '登录' ?></title>
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
        
        /* Login Form Styles */
        .login-container {
            max-width: 420px;
            margin: 80px auto;
            padding: 40px;
            border-radius: 16px;
            background: var(--card-bg);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        
        .login-container h2 {
            color: var(--accent-color);
            font-weight: 600;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        
        .login-container .form-control {
            background-color: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--text-primary);
            padding: 12px 15px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .login-container .form-control:focus {
            background-color: rgba(255, 255, 255, 0.08);
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(106, 76, 255, 0.25);
        }
        
        .login-container .form-label {
            color: var(--text-secondary);
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        
        .login-container .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 10px 15px;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .login-container .btn-primary:hover {
            background-color: var(--primary-hover);
            border-color: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(106, 76, 255, 0.3);
        }
        
        .home-btn-link {
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .home-btn-link:hover {
            color: var(--accent-color);
        }
        
        /* Main Editor Styles */
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
        
        .card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
            margin-bottom: 25px;
            transition: all 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
        }
        
        /* Disable hover effect specifically for the filter card */
        #filter-card:hover {
            transform: none;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2); /* Keep the base shadow */
        }
        
        .card-header {
            background-color: rgba(0, 0, 0, 0.2);
            border-bottom: 1px solid var(--border-color);
            padding: 15px 20px;
            font-weight: 600;
            color: var(--accent-color);
            border-radius: 12px 12px 0 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-body {
            padding: 20px;
        }
        
        /* Form Styles */
        .form-control, .form-select {
            background-color: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--text-primary);
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            background-color: rgba(255, 255, 255, 0.08);
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(106, 76, 255, 0.25);
            color: var(--text-primary);
        }
        
        .form-select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23e6e6ff' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
        }
        
        .form-label {
            color: var(--text-secondary);
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        
        /* Button Styles */
        .btn {
            border-radius: 8px;
            padding: 8px 16px;
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
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(106, 76, 255, 0.3);
        }
        
        .btn-secondary {
            background-color: #2d3748;
            border-color: #2d3748;
        }
        
        .btn-secondary:hover {
            background-color: #3a4a5e;
            border-color: #3a4a5e;
        }
        
        .btn-warning {
            background-color: var(--warning-color);
            border-color: var(--warning-color);
            color: #212529;
        }
        
        .btn-warning:hover {
            background-color: #e68a00;
            border-color: #e68a00;
            color: #212529;
        }
        
        .btn-outline-warning {
            color: var(--warning-color);
            border-color: var(--warning-color);
        }
        
        .btn-outline-warning:hover {
            background-color: var(--warning-color);
            color: #212529;
        }
        
        .btn-outline-secondary {
            color: var(--text-secondary);
            border-color: var(--text-secondary);
        }
        
        .btn-outline-secondary:hover {
            background-color: var(--text-secondary);
            color: var(--dark-bg);
        }
        
        /* Table Styles */
        .table {
            color: var(--text-primary);
            border-color: var(--border-color);
            vertical-align: middle;
        }
        
        .table-dark {
            --bs-table-bg: var(--card-bg);
            --bs-table-striped-bg: rgba(255, 255, 255, 0.03);
            --bs-table-striped-color: var(--text-primary);
            --bs-table-active-bg: rgba(255, 255, 255, 0.05);
            --bs-table-active-color: var(--text-primary);
            --bs-table-hover-bg: rgba(255, 255, 255, 0.04);
            --bs-table-hover-color: var(--text-primary);
        }
        
        .table th {
            font-weight: 600;
            color: var(--accent-color);
            border-bottom-width: 1px;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }
        
        .table td {
            border-color: var(--border-color);
            padding: 12px 16px;
        }
        
        /* Pagination Styles */
        .pagination {
            --bs-pagination-color: var(--text-secondary);
            --bs-pagination-bg: var(--card-bg);
            --bs-pagination-border-color: var(--border-color);
            --bs-pagination-hover-color: var(--text-primary);
            --bs-pagination-hover-bg: var(--card-hover);
            --bs-pagination-hover-border-color: var(--border-color);
            --bs-pagination-focus-color: var(--text-primary);
            --bs-pagination-focus-bg: var(--card-hover);
            --bs-pagination-focus-box-shadow: 0 0 0 0.25rem rgba(106, 76, 255, 0.25);
            --bs-pagination-active-color: #fff;
            --bs-pagination-active-bg: var(--primary-color);
            --bs-pagination-active-border-color: var(--primary-color);
            --bs-pagination-disabled-color: var(--text-muted);
            --bs-pagination-disabled-bg: var(--card-bg);
            --bs-pagination-disabled-border-color: var(--border-color);
        }
        
        /* Alert Styles */
        .alert-danger {
            background-color: rgba(244, 67, 54, 0.1);
            border-color: rgba(244, 67, 54, 0.2);
            color: #f44336;
        }
        
        /* Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-out forwards;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .nav-buttons {
                position: static;
                justify-content: center;
                margin-top: 15px;
            }
            
            .page-header {
                padding-bottom: 15px;
            }
            
            .card-header {
                flex-direction: column;
                text-align: center;
            }
        }
        
        /* Improve dropdown option visibility */
        select.form-select option {
            background-color: #fff; /* Light background for options */
            color: #212529; /* Dark text for options */
        }

        /* Improve placeholder visibility in filter card */
        #filter-card input::placeholder {
            color: var(--text-secondary); /* Use a lighter color from the theme */
            opacity: 0.7; /* Adjust opacity if needed */
        }
    </style>
</head>
<body>

<?php if (!$isLoggedIn): ?>
    <!-- LOGIN FORM -->
    <div class="login-container fade-in">
        <h2><i class="fas fa-scroll me-2"></i>任务编辑器</h2>
        <p class="text-center text-muted mb-4">请登录以访问管理控制台</p>
        
        <?php if (!empty($loginError)): ?>
            <div class="alert alert-danger mb-4">
                <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($loginError) ?>
            </div>
        <?php endif; ?>
        
        <form method="post" action="index.php">
            <div class="mb-4">
                <label for="login_username" class="form-label">
                    <i class="fas fa-user me-2"></i>用户名
                </label>
                <input type="text" class="form-control" id="login_username" name="login_username" required>
            </div>
            <div class="mb-4">
                <label for="login_password" class="form-label">
                    <i class="fas fa-lock me-2"></i>密码
                </label>
                <input type="password" class="form-control" id="login_password" name="login_password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100 mb-3">
                <i class="fas fa-sign-in-alt me-2"></i>登录
            </button>
        </form>
        <div class="mt-4 text-center">
            <a href="../index.php" class="home-btn-link">
                <i class="fas fa-home"></i> 返回主页
            </a>
        </div>
    </div>

<?php else: ?>
    <!-- QUEST EDITOR INTERFACE -->
    <div class="page-header fade-in">
        <div class="container-fluid d-flex align-items-center justify-content-between">
            <div>
                <a href="../index.php" class="btn btn-outline-warning">
                    <i class="fas fa-home"></i> 返回主页
                </a>
            </div>

            <div class="text-center">
                <h1><i class="fas fa-scroll me-2"></i> 任务编辑器</h1>
                <p class="subtitle mb-0">管理游戏世界中的任务数据</p>
            </div>

            <div>
                <a href="?logout=1" class="btn btn-outline-secondary">
                    <i class="fas fa-sign-out-alt"></i> 退出登录
                </a>
            </div>
        </div>
    </div>

    <div class="container-fluid px-4 fade-in" style="animation-delay: 0.1s;">
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger mb-4">
                <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Filter Card -->
        <div class="card" id="filter-card">
            <div class="card-header">
                <i class="fas fa-filter"></i> 筛选任务
            </div>
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-2">
                        <label for="filter_id" class="form-label">任务 ID</label>
                        <input type="number" class="form-control" id="filter_id" name="filter_id" value="<?= htmlspecialchars((string)($filter_id ?? '')); ?>" placeholder="输入ID">
                    </div>
                    <div class="col-md-4">
                        <label for="filter_title" class="form-label">任务标题</label>
                        <input type="text" class="form-control" id="filter_title" name="filter_title" value="<?= htmlspecialchars((string)($filter_title ?? '')); ?>" placeholder="搜索标题">
                    </div>
                    <div class="col-md-3"> <!-- Quest Level Op + Val -->
                        <label for="filter_level_op" class="form-label">任务等级</label>
                        <div class="input-group">
                            <select class="form-select flex-grow-0" id="filter_level_op" name="filter_level_op" style="width: auto;">
                                <option value="any" <?= $filter_level_op === 'any' ? 'selected' : '' ?>>任意</option>
                                <option value="ge" <?= $filter_level_op === 'ge' ? 'selected' : '' ?>>&gt;=</option>
                                <option value="le" <?= $filter_level_op === 'le' ? 'selected' : '' ?>>&lt;=</option>
                                <option value="eq" <?= $filter_level_op === 'eq' ? 'selected' : '' ?>>=</option>
                                <option value="between" <?= $filter_level_op === 'between' ? 'selected' : '' ?>>介于</option>
                            </select>
                            <input type="text" class="form-control" id="filter_level_val" name="filter_level_val" value="<?= htmlspecialchars($filter_level_val) ?>" placeholder="等级或 Min-Max">
                        </div>
                    </div>
                    <div class="col-md-3"> <!-- Min Level Op + Val -->
                        <label for="filter_min_level_op" class="form-label">最低等级</label>
                        <div class="input-group">
                             <select class="form-select flex-grow-0" id="filter_min_level_op" name="filter_min_level_op" style="width: auto;">
                                <option value="any" <?= $filter_min_level_op === 'any' ? 'selected' : '' ?>>任意</option>
                                <option value="ge" <?= $filter_min_level_op === 'ge' ? 'selected' : '' ?>>&gt;=</option>
                                <option value="le" <?= $filter_min_level_op === 'le' ? 'selected' : '' ?>>&lt;=</option>
                                <option value="eq" <?= $filter_min_level_op === 'eq' ? 'selected' : '' ?>>=</option>
                                <option value="between" <?= $filter_min_level_op === 'between' ? 'selected' : '' ?>>介于</option>
                            </select>
                            <input type="text" class="form-control" id="filter_min_level_val" name="filter_min_level_val" value="<?= htmlspecialchars($filter_min_level_val) ?>" placeholder="等级或 Min-Max">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label for="filter_type" class="form-label">任务类型</label>
                        <select class="form-select" id="filter_type" name="filter_type">
                            <option value="">全部类型</option>
                            <?php foreach ($questTypes as $id => $name): ?>
                                <option value="<?= $id ?>" <?= ($filter_type !== null && (int)$filter_type === (int)$id) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($name) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="limit" class="form-label">每页显示</label>
                        <select class="form-select" id="limit" name="limit">
                            <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>10 条</option>
                            <option value="25" <?= $limit == 25 ? 'selected' : '' ?>>25 条</option>
                            <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50 条</option>
                            <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100 条</option>
                        </select>
                    </div>
                    <div class="col-md-8 d-flex align-items-end"> <!-- Buttons -->
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-search me-1"></i> 搜索
                        </button>
                        <a href="index.php" class="btn btn-secondary me-auto">
                            <i class="fas fa-times"></i> 重置
                        </a>
                        <a href="#" class="btn btn-success"><i class="fas fa-plus"></i> 新建任务</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Results Card -->
        <div class="card fade-in" style="animation-delay: 0.2s;">
            <div class="card-header">
                <i class="fas fa-list"></i> 任务列表
                <span class="badge bg-primary ms-2"><?= $totalItems ?> 个任务</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-dark table-striped table-hover align-middle">
                        <thead>
                            <tr>
                                <?= render_sortable_header('ID', 'ID', $sort_by, $sort_dir, $header_base_params) ?>
                                <th>任务标题 (LogTitle)</th>
                                <th>任务描述</th>
                                <?= render_sortable_header('QuestLevel', '等级', $sort_by, $sort_dir, $header_base_params) ?>
                                <?= render_sortable_header('MinLevel', '最低等级', $sort_by, $sort_dir, $header_base_params) ?>
                                <th style="width: 100px;">任务类型</th>
                                <th style="width: 180px; min-width: 180px;" class="text-center">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($quests)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <i class="fas fa-search me-2 text-muted"></i>
                                        没有找到匹配的任务，请尝试其他筛选条件。
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($quests as $quest): ?>
                                    <tr>
                                        <td class="text-end"><?= htmlspecialchars((string)($quest['ID'] ?? '')); ?></td>
                                        <td class="fw-medium"><?= htmlspecialchars($quest['LogTitle'] ?? ''); ?></td>
                                        <td>
                                            <?php
                                                $description = $quest['QuestDescription'] ?? '';
                                                // Truncate long descriptions
                                                $maxLength = 50; // Max characters to show
                                                if (mb_strlen($description) > $maxLength) {
                                                    $description = mb_substr($description, 0, $maxLength) . '...';
                                                }
                                                echo htmlspecialchars($description);
                                            ?>
                                        </td>
                                        <td class="text-end"><?= htmlspecialchars((string)($quest['QuestLevel'] ?? '')); ?></td>
                                        <td class="text-center"><?= htmlspecialchars((string)($quest['MinLevel'] ?? '')); ?></td>
                                        <td>
                                            <?php 
                                                $typeId = $quest['QuestType'] ?? '';
                                                echo htmlspecialchars($questTypes[$typeId] ?? (string)$typeId); // Use lookup array
                                            ?>
                                        </td>
                                        <td class="text-center">
                                            <?php
                                                // Get current query string, remove leading '&' if present, urlencode it
                                                $current_query = http_build_query($_GET); // Rebuild from $_GET to ensure correct format
                                                $return_params_encoded = urlencode($current_query);
                                                $edit_link = "edit_quest.php?id=" . htmlspecialchars((string)($quest['ID'] ?? '')) . "&return_params=" . $return_params_encoded;
                                            ?>
                                            <a href="<?= $edit_link ?>" class="btn btn-warning btn-sm">
                                                <i class="fas fa-edit"></i> 编辑
                                            </a>
                                            <button type="button" class="btn btn-danger btn-sm ms-1 delete-quest-btn" data-quest-id="<?= htmlspecialchars((string)($quest['ID'] ?? '')) ?>" data-quest-title="<?= htmlspecialchars($quest['LogTitle'] ?? '') ?>" data-bs-toggle="tooltip" title="删除">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination Links -->
                <?php if ($totalPages > 1): ?>
                <nav aria-label="任务列表分页" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php
                            // Prepare base query parameters for pagination links (include sorting)
                            $pagination_params = $_GET; // Copy current filters/search/limit
                            unset($pagination_params['page']); // Remove page itself
                            $pagination_query_string = http_build_query($pagination_params);
                        ?>
                        <!-- Previous Page Link -->
                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= $pagination_query_string ?>&page=<?= $page - 1 ?>" aria-label="上一页">
                                <i class="fas fa-chevron-left"></i>
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
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
                <!-- End Pagination Links -->
            </div>
        </div>
    </div>

<?php endif; // End of $isLoggedIn check ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            });
        });
    </script>

    <!-- Add New Quest Modal -->
    <div class="modal fade" id="newQuestModal" tabindex="-1" aria-labelledby="newQuestModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content bg-dark text-light"> <!-- Match theme -->
                <div class="modal-header border-secondary">
                    <h5 class="modal-title" id="newQuestModalLabel"><i class="fas fa-plus-circle me-2"></i>新增任务</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="index.php"> 
                        <div class="modal-body">
                            <div id="newQuestErrorAlert" class="alert alert-danger d-none" role="alert"></div>
                            <div class="mb-3">
                                <label for="copyQuestIdInput" class="form-label">复制源任务 ID (可选):</label>
                                <input type="number" class="form-control" id="copyQuestIdInput" name="copyQuestId" placeholder="留空则创建空白任务">
                                <div class="form-text">如果填写，将复制该 ID 任务的所有数据作为新任务的基础。</div>
                            </div>
                            <div class="mb-3">
                                <label for="newQuestIdInput" class="form-label">新建任务 ID (必填):</label>
                                <input type="number" class="form-control" id="newQuestIdInput" name="newQuestId" placeholder="例如: 90001" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <input type="hidden" name="action" value="create_quest">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                            <button type="submit" class="btn btn-primary" id="confirmCreateQuestBtn">确认创建</button> 
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Add New Quest Modal Logic
        const addNewQuestBtn = document.querySelector('.btn-success'); // Assuming the Add button has .btn-success
        const newQuestModalElement = document.getElementById('newQuestModal');
        const newQuestModal = newQuestModalElement ? new bootstrap.Modal(newQuestModalElement) : null;
        const modalForm = newQuestModalElement ? newQuestModalElement.querySelector('form') : null; 
        const confirmCreateQuestBtn = modalForm ? modalForm.querySelector('#confirmCreateQuestBtn') : null; 
        const copyQuestIdInput = modalForm ? modalForm.querySelector('#copyQuestIdInput') : null;
        const newQuestIdInput = modalForm ? modalForm.querySelector('#newQuestIdInput') : null;
        const newQuestErrorAlert = modalForm ? modalForm.querySelector('#newQuestErrorAlert') : null;

        if (addNewQuestBtn && newQuestModal) {
            addNewQuestBtn.addEventListener('click', (event) => {
                event.preventDefault(); // Prevent default link behavior if it's an anchor
                // Reset fields and error message when opening modal
                if(copyQuestIdInput) copyQuestIdInput.value = '';
                if(newQuestIdInput) newQuestIdInput.value = '';
                if(newQuestErrorAlert) {
                    newQuestErrorAlert.classList.add('d-none');
                    newQuestErrorAlert.textContent = '';
                }
                newQuestModal.show();
            });
        }

        // Handle form submission via JS to keep validation
        if (modalForm && confirmCreateQuestBtn) {
            modalForm.addEventListener('submit', (event) => {
                // Prevent default form submission to run validation first
                event.preventDefault(); 

                // Example basic validation:
                if (!newQuestIdInput || !newQuestIdInput.value || parseInt(newQuestIdInput.value) <= 0) {
                    if (newQuestErrorAlert) {
                        newQuestErrorAlert.textContent = '请输入有效的新建任务 ID。';
                        newQuestErrorAlert.classList.remove('d-none');
                    }
                    return;
                } else {
                    if (newQuestErrorAlert) {
                        newQuestErrorAlert.classList.add('d-none');
                    }
                }

                // If validation passes, manually submit the form
                modalForm.submit();
            });
        }
    </script>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteQuestModal" tabindex="-1" aria-labelledby="deleteQuestModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content bg-danger text-light"> <!-- Danger theme for delete -->
                <form method="POST" action="index.php"> 
                    <div class="modal-header border-danger">
                        <h5 class="modal-title" id="deleteQuestModalLabel"><i class="fas fa-exclamation-triangle me-2"></i> 确认删除任务</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>您确定要永久删除以下任务吗？此操作无法撤销。</p>
                        <p>
                            <strong>ID:</strong> <span id="deleteQuestIdSpan"></span><br>
                            <strong>标题:</strong> <span id="deleteQuestTitleSpan"></span>
                        </p>
                        <input type="hidden" name="action" value="delete_quest">
                        <input type="hidden" name="quest_id_to_delete" id="questIdToDeleteInput">
                    </div>
                    <div class="modal-footer border-top-0">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">取消</button>
                        <button type="submit" class="btn btn-outline-light">确认删除</button> 
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Delete Quest Modal Logic
        const deleteQuestModalElement = document.getElementById('deleteQuestModal');
        const deleteQuestModal = deleteQuestModalElement ? new bootstrap.Modal(deleteQuestModalElement) : null;
        const deleteQuestIdSpan = document.getElementById('deleteQuestIdSpan');
        const deleteQuestTitleSpan = document.getElementById('deleteQuestTitleSpan');
        const questIdToDeleteInput = document.getElementById('questIdToDeleteInput');

        if (deleteQuestModal) {
            // Get all delete buttons in the table
            const deleteButtons = document.querySelectorAll('.delete-quest-btn');

            deleteButtons.forEach(button => {
                button.addEventListener('click', (event) => {
                    const questId = button.dataset.questId;
                    const questTitle = button.dataset.questTitle;

                    if (deleteQuestIdSpan) deleteQuestIdSpan.textContent = questId;
                    if (deleteQuestTitleSpan) deleteQuestTitleSpan.textContent = questTitle;
                    if (questIdToDeleteInput) questIdToDeleteInput.value = questId;

                    deleteQuestModal.show();
                });
            });
        }
    </script>

</body>
</html>