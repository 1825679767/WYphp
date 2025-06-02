<?php
declare(strict_types=1);

// --- Helper Functions for Display (Copied from bag_query/functions.php) ---

function get_race_name(int $race_id): string
{
    // Data typically from chr_races table in World DB
    $races = [
        1 => '人类', 2 => '兽人', 3 => '矮人', 4 => '暗夜精灵', 5 => '亡灵',
        6 => '牛头人', 7 => '侏儒', 8 => '巨魔', 10 => '血精灵', 11 => '德莱尼'
        // Add other races as needed
    ];
    return $races[$race_id] ?? '未知 (' . $race_id . ')';
}

function get_class_name(int $class_id): string
{
    // Data typically from chr_classes table in World DB
    $classes = [
        1 => '战士', 2 => '圣骑士', 3 => '猎人', 4 => '潜行者', 5 => '牧师',
        6 => '死亡骑士', 7 => '萨满祭司', 8 => '法师', 9 => '术士', 11 => '德鲁伊'
        // Add other classes as needed
    ];
    return $classes[$class_id] ?? '未知 (' . $class_id . ')';
}

// --- End Helper Functions ---

/**
 * Retrieves accounts based on search criteria with pagination.
 *
 * @param PDO $pdo_A Connection to the Auth database.
 * @param PDO $pdo_C Connection to the Characters database.
 * @param string $search_type The field to search ('username', 'id', or 'character_name').
 * @param string $search_value The value to search for.
 * @param string $filter_status Filter by account status ('all', 'online', 'offline').
 * @param int $page Current page number (1-based).
 * @param int $items_per_page Number of items per page.
 * @return array An array containing 'data' (list of accounts) and 'total' (total matching accounts).
 */
function get_accounts(PDO $pdo_A, PDO $pdo_C, string $search_type, string $search_value, string $filter_status = 'all', int $page = 1, int $items_per_page = 10): array
{
    // Base SQL parts
    $accountFields = "a.id, a.username, a.last_ip, a.last_login, a.locked, a.expansion, a.mutetime, a.online";
    $accessJoin = "LEFT JOIN account_access ac ON a.id = ac.id AND ac.RealmID = -1";
    $accessField = "COALESCE(ac.gmlevel, 0) as gmlevel";
    $banJoin = "LEFT JOIN account_banned b ON a.id = b.id AND b.active = 1";
    $banFields = "b.bandate, b.unbandate, b.banreason, b.active as banned_active";

    // Base FROM and JOIN structure
    $baseFrom = "FROM account a {$accessJoin} {$banJoin}";
    $baseSelect = "SELECT {$accountFields}, {$accessField}, {$banFields} ";
    $baseCountSelect = "SELECT COUNT(a.id) ";

    $params = [];
    $whereConditions = []; // Use an array to build conditions
    $isCharacterSearch = false; // Flag for character name search

    // Apply WHERE clause based on search criteria
    if (!empty($search_value)) {
        if ($search_type === 'id' && is_numeric($search_value)) {
            $whereConditions[] = "a.id = :search_value";
            $params[':search_value'] = (int)$search_value;
        } elseif ($search_type === 'username') {
            $whereConditions[] = "a.username LIKE :search_value";
            $params[':search_value'] = '%' . $search_value . '%';
        } elseif ($search_type === 'character_name') {
            $isCharacterSearch = true;
            // --- Search by Character Name --- 
            try {
                $stmt_char = $pdo_C->prepare("SELECT DISTINCT account FROM characters WHERE name LIKE :char_name");
                $stmt_char->bindValue(':char_name', '%' . $search_value . '%', PDO::PARAM_STR);
                $stmt_char->execute();
                $account_ids = $stmt_char->fetchAll(PDO::FETCH_COLUMN);
                
                if (empty($account_ids)) {
                    return ['data' => [], 'total' => 0]; // No accounts found
                }
                
                // Use positional placeholders for IN clause
                $placeholders = implode(',', array_fill(0, count($account_ids), '?'));
                $whereConditions[] = "a.id IN ({$placeholders})";
                $params = $account_ids; // Params are now just the IDs for positional binding later
                
            } catch (PDOException $e) {
                 error_log("Error searching account ID by character name: " . $e->getMessage());
                 throw $e; // Re-throw
            }
            // --- End Search by Character Name ---
        } else {
            // Invalid search type if value is present but type is wrong (should be handled in index.php already)
             return ['data' => [], 'total' => 0]; 
        }
    }

    // --- NEW: Apply Status Filter ---
    if ($filter_status === 'online') {
        $whereConditions[] = "a.online = 1";
    } elseif ($filter_status === 'offline') {
        $whereConditions[] = "a.online = 0";
    }
    // If 'all', no additional condition needed

    // --- Combine WHERE conditions ---
    $whereClause = "";
    if (!empty($whereConditions)) {
        $whereClause = " WHERE " . implode(" AND ", $whereConditions);
    }

    // --- Execute Query --- 
    try {
        // 1. Get total count
        // Count query uses the same where clause and params (named or positional)
        $countSql = $baseCountSelect . $baseFrom . $whereClause;
        $stmt_count = $pdo_A->prepare($countSql);
        // Execute with appropriate params (named map or positional array)
        $stmt_count->execute($params); 
        $total = (int)$stmt_count->fetchColumn();

        if ($total === 0) {
            return ['data' => [], 'total' => 0];
        }

        // 2. Calculate pagination
        $offset = ($page - 1) * $items_per_page;
        if ($offset < 0) $offset = 0;

        // 3. Prepare data query SQL - Use positional placeholders for LIMIT/OFFSET if needed
        $dataSql = $baseSelect . $baseFrom . $whereClause . " ORDER BY a.id ASC";
        
        $dataParams = $params; // Start with WHERE params
        
        if ($isCharacterSearch) {
            // Append positional placeholders for limit and offset
            $dataSql .= " LIMIT ? OFFSET ?";
            // Add limit and offset values to the *end* of the params array
            $dataParams[] = $items_per_page;
            $dataParams[] = $offset;
        } else {
            // Use named placeholders for limit and offset
            $dataSql .= " LIMIT :limit OFFSET :offset";
            // Add limit and offset to the named params map
            $dataParams[':limit'] = $items_per_page;
            $dataParams[':offset'] = $offset;
        }

        $stmt_data = $pdo_A->prepare($dataSql);
        
        // 4. Bind parameters and execute data query
        if ($isCharacterSearch) {
            // Bind all parameters positionally
            $paramIndex = 1;
            foreach ($dataParams as $value) {
                 // Determine type (account IDs are INT, limit/offset are INT)
                 $type = PDO::PARAM_INT; 
                 $stmt_data->bindValue($paramIndex++, $value, $type);
            }
        } else {
             // Bind named parameters
            foreach ($dataParams as $key => $val) {
                $type = is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR;
                 // Special handling for limit/offset keys which should be INT
                if ($key === ':limit' || $key === ':offset') {
                    $type = PDO::PARAM_INT;
                }
                $stmt_data->bindValue($key, $val, $type);
            }
        }
        
        $stmt_data->execute();
        $data = $stmt_data->fetchAll(PDO::FETCH_ASSOC); // Use FETCH_ASSOC for consistency

        return ['data' => $data, 'total' => $total];

    } catch (PDOException $e) {
        // --- Fallback Logic (Simplified for Clarity) ---
        // Fallback logic might need similar adjustments if it also uses character search results.
        // For now, assume the fallback doesn't mix parameter types or handle character search yet.
        // A more robust solution would involve refactoring the fallback too.
        if ($e->getCode() === '42S22' || strpos($e->getMessage(), 'Unknown column') !== false) {
            error_log("Detailed account query failed, falling back: " . $e->getMessage());
            // Construct basic fallback SQL (ensure it doesn't mix params)
            $fallbackSelect = "SELECT a.id, a.username, a.last_ip, a.last_login ";
            $fallbackCountSql = "SELECT COUNT(a.id) ";
            $fallbackFrom = "FROM account a ";
            
            try {
                 // Re-execute count with fallback SQL and original WHERE/params
                 $stmt_count_fb = $pdo_A->prepare($fallbackCountSql . $fallbackFrom . $whereClause);
                 $stmt_count_fb->execute($params); 
                 $total_fb = (int)$stmt_count_fb->fetchColumn();

                 if ($total_fb === 0) return ['data' => [], 'total' => 0];

                 $offset_fb = ($page - 1) * $items_per_page;
                 if ($offset_fb < 0) $offset_fb = 0;

                 // Prepare fallback data SQL (handle param types consistently)
                 $fallbackDataSql = $fallbackSelect . $fallbackFrom . $whereClause . " ORDER BY a.id ASC";
                 $fallbackDataParams = $params;

                 if ($isCharacterSearch) {
                     $fallbackDataSql .= " LIMIT ? OFFSET ?";
                     $fallbackDataParams[] = $items_per_page;
                     $fallbackDataParams[] = $offset_fb;
                 } else {
                     $fallbackDataSql .= " LIMIT :limit OFFSET :offset";
                     $fallbackDataParams[':limit'] = $items_per_page;
                     $fallbackDataParams[':offset'] = $offset_fb;
                 }
                 
                 $stmt_data_fb = $pdo_A->prepare($fallbackDataSql);

                 // Bind parameters for fallback
                 if ($isCharacterSearch) {
                     $paramIndex = 1;
                     foreach ($fallbackDataParams as $value) {
                         $stmt_data_fb->bindValue($paramIndex++, $value, PDO::PARAM_INT);
                     }
                 } else {
                     foreach ($fallbackDataParams as $key => $val) {
                         $type = ($key === ':limit' || $key === ':offset') ? PDO::PARAM_INT : (is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
                         $stmt_data_fb->bindValue($key, $val, $type);
                     }
                 }

                 $stmt_data_fb->execute();
                 $data_fb = $stmt_data_fb->fetchAll(PDO::FETCH_ASSOC);
                 return ['data' => $data_fb, 'total' => $total_fb];

            } catch (PDOException $e_fb) {
                 error_log("Fallback account query also failed: " . $e_fb->getMessage());
                 throw $e_fb; // Re-throw fallback error
            }

        } else {
            error_log("Error in get_accounts query: " . $e->getMessage());
            throw $e; // Re-throw original error if not a column issue
        }
    }
}

/**
 * Gets the current number of online players.
 *
 * @param PDO $pdo_C Connection to the Characters database.
 * @return int The number of online players.
 */
function get_online_count(PDO $pdo_C): ?int
{
    try {
        $stmt = $pdo_C->query("SELECT COUNT(*) FROM characters WHERE online = 1");
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error fetching online count: " . $e->getMessage());
        return null; // Return null on error
    }
}

/**
 * Retrieves characters associated with a specific account ID.
 * Uses helper functions for race/class names.
 *
 * @param PDO $pdo_C Connection to the Characters database.
 * @return array List of character details including race/class names and online status string.
 */
function get_characters_for_account(PDO $pdo_C, int $account_id): array
{
    try {
        $stmt = $pdo_C->prepare(
            "SELECT guid, name, level, race, class, online, money 
             FROM characters 
             WHERE account = :account_id 
             ORDER BY name ASC"
        );
        $stmt->execute(['account_id' => $account_id]);
        $characters = $stmt->fetchAll(PDO::FETCH_ASSOC); // Use FETCH_ASSOC

        // Enhance character data
        foreach ($characters as &$char) { // Use reference to modify array directly
            $char['race'] = get_race_name((int)$char['race']);
            $char['class'] = get_class_name((int)$char['class']);
            $char['online'] = $char['online'] ? '在线' : '离线'; // Convert online status
        }
        unset($char); // Unset reference after loop

        return $characters;
    } catch (PDOException $e) {
        error_log("Error fetching characters for account ID {$account_id}: " . $e->getMessage());
        throw $e; // Re-throw for handling in action_handler
    }
}

// Potential future function for password hashing (if direct DB modification is used)
/*
function calculate_password_hash(string $username, string $password): string
{
    // Common AzerothCore/TrinityCore hash method
    return sha1(strtoupper($username) . ':' . strtoupper($password));
}
*/ 