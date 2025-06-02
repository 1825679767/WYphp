<?php
declare(strict_types=1);

// Define inventory slot constants once at the top level
// Based on common AzerothCore slot conventions
// https://www.azerothcore.org/wiki/character_inventory
if (!defined('SLOT_START_EQUIPMENT')) define('SLOT_START_EQUIPMENT', 0);
if (!defined('SLOT_END_EQUIPMENT')) define('SLOT_END_EQUIPMENT', 18);
if (!defined('SLOT_START_BACKPACK')) define('SLOT_START_BACKPACK', 19);
if (!defined('SLOT_END_BACKPACK')) define('SLOT_END_BACKPACK', 34); // Base backpack
if (!defined('SLOT_START_BAGS')) define('SLOT_START_BAGS', 35);
if (!defined('SLOT_END_BAGS')) define('SLOT_END_BAGS', 38); // Bag slots themselves
if (!defined('SLOT_START_BANK')) define('SLOT_START_BANK', 39);
if (!defined('SLOT_END_BANK')) define('SLOT_END_BANK', 66); // Bank slots
if (!defined('SLOT_START_BANK_BAGS')) define('SLOT_START_BANK_BAGS', 67);
if (!defined('SLOT_END_BANK_BAGS')) define('SLOT_END_BANK_BAGS', 73); // Bank bag slots
if (!defined('SLOT_START_KEYRING')) define('SLOT_START_KEYRING', 89);
if (!defined('SLOT_END_KEYRING')) define('SLOT_END_KEYRING', 120); // Approximated

/**
 * Finds characters based on different search criteria.
 *
 * @param PDO $pdo_A Connection to the Auth database.
 * @param PDO $pdo_C Connection to the Characters database.
 * @param string $search_type The type of search: 'username', 'character_name', 'account_id'.
 * @param string $search_value The value to search for.
 * @return array List of character details (guid, name, level, race, class).
 */
function find_characters(PDO $pdo_A, PDO $pdo_C, string $search_type, string $search_value): array
{
    if (empty($search_value)) {
        return [];
    }

    $baseSelect = "SELECT guid, name, level, race, class FROM characters";
    $params = [];

    switch ($search_type) {
        case 'username':
            // 1. Find account ID from auth.account
            try {
                $stmt_A = $pdo_A->prepare("SELECT id FROM account WHERE username = :username");
                $stmt_A->execute([':username' => $search_value]);
                $account = $stmt_A->fetch();

                if (!$account) {
                    return []; // Account not found
                }
                $account_id = $account['id'];
                
                // Prepare query for characters based on account ID
                $sql = $baseSelect . " WHERE account = :account_id ORDER BY name ASC";
                $params = [':account_id' => $account_id];

            } catch (PDOException $e) {
                error_log("Error finding account ID by username: " . $e->getMessage());
                throw $e; // Re-throw
            }
            break;

        case 'character_name':
            $sql = $baseSelect . " WHERE name LIKE :name ORDER BY name ASC";
            $params = [':name' => '%' . $search_value . '%'];
            break;

        default:
            // Invalid search type or type not handled (e.g., if account_id was removed)
             // You might want to log this or return an error, or just empty results
             error_log("Invalid or unhandled search type in find_characters: " . $search_type);
            return [];
    }

    // Execute the query on the characters database
    try {
        $stmt_C = $pdo_C->prepare($sql);
        $stmt_C->execute($params);
        return $stmt_C->fetchAll(PDO::FETCH_ASSOC); // Fetch as associative array
    } catch (PDOException $e) {
        error_log("Error fetching characters: " . $e->getMessage());
        throw $e; // Re-throw
    }
}

/**
 * Retrieves items for a specific character using a single JOIN query.
 *
 * @param PDO $pdo_C Connection to the Characters database (used for execution).
 * @param PDO $pdo_W Connection to the World database (needed conceptually, but not used for execution).
 * @param int $character_guid The character's GUID.
 * @param string $dbCName The name of the Characters database.
 * @param string $dbWName The name of the World database.
 * @return array List of item details.
 */
function get_character_items(PDO $pdo_C, PDO $pdo_W, int $character_guid, string $dbCName, string $dbWName): array
{
    // Use backticks for database and table names for safety
    $sql = "SELECT
                ci.item AS item_instance_guid,
                ci.bag,
                ci.slot,
                ii.itemEntry,
                ii.count,
                it.name,
                it.Quality
            FROM `{$dbCName}`.`character_inventory` ci
            JOIN `{$dbCName}`.`item_instance` ii ON ci.item = ii.guid
            JOIN `{$dbWName}`.`item_template` it ON ii.itemEntry = it.entry
            WHERE ci.guid = :guid
            ORDER BY FIELD(ci.slot, ".implode(',', range(0, 18))."), ci.bag, ci.slot"; // Improved ordering: Equipment first
            // FIELD function orders equipment slots 0-18 first, then by bag/slot

    try {
        $stmt = $pdo_C->prepare($sql);
        $stmt->execute([':guid' => $character_guid]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC); // Ensure associative array fetch
    } catch (PDOException $e) {
        // Log the error or handle it more gracefully
        error_log("Error in get_character_items JOIN query: " . $e->getMessage());
        // You might want to throw the exception or return an empty array
        // depending on how you want to handle DB errors upstream.
        throw $e; // Re-throw for now
    }
}

/**
 * Updates item stack count or deletes the item if count reaches zero or less.
 *
 * @param PDO $pdo_C Connection to the Characters database.
 * @param int $character_guid The character's GUID.
 * @param int $item_instance_guid The GUID of the item instance to update/delete.
 * @param int $quantity_to_delete The number of items to delete from the stack.
 * @return array An array indicating success status, a message, and the new count.
 */
function update_or_delete_item_stack(PDO $pdo_C, int $character_guid, int $item_instance_guid, int $quantity_to_delete): array
{
    if ($quantity_to_delete <= 0) {
        return ['success' => false, 'message' => '删除数量必须大于0。', 'new_count' => -1];
    }

    $pdo_C->beginTransaction();
    try {
        // 1. 获取当前物品实例信息 (特别是数量) 并锁定行以防并发问题
        $stmt_get = $pdo_C->prepare("SELECT count FROM item_instance WHERE guid = :item_instance_guid FOR UPDATE");
        $stmt_get->execute([':item_instance_guid' => $item_instance_guid]);
        $item_instance = $stmt_get->fetch();

        if (!$item_instance) {
            $pdo_C->rollBack();
            return ['success' => false, 'message' => '找不到物品实例。', 'new_count' => -1];
        }

        $current_count = (int)$item_instance['count'];

        if ($quantity_to_delete > $current_count) {
             $pdo_C->rollBack();
            return ['success' => false, 'message' => "删除数量 ({$quantity_to_delete}) 不能超过当前堆叠数量 ({$current_count})。", 'new_count' => $current_count];
        }

        $new_count = $current_count - $quantity_to_delete;

        if ($new_count <= 0) {
            // 完全删除物品
            // 先从 character_inventory 删除
            $stmt_inv = $pdo_C->prepare("DELETE FROM character_inventory WHERE guid = :character_guid AND item = :item_instance_guid");
            $stmt_inv->execute([
                ':character_guid' => $character_guid,
                ':item_instance_guid' => $item_instance_guid
            ]);
            $deleted_inventory = $stmt_inv->rowCount();

            if ($deleted_inventory === 0) {
                 $pdo_C->rollBack();
                 // 如果实例存在但库存中找不到，可能数据不一致？
                 return ['success' => false, 'message' => '在角色库存中找不到要删除的物品条目。', 'new_count' => -1];
            }

            // 再删除 item_instance 自身
            $stmt_inst = $pdo_C->prepare("DELETE FROM item_instance WHERE guid = :item_instance_guid");
            $stmt_inst->execute([':item_instance_guid' => $item_instance_guid]);

            $pdo_C->commit();
            return ['success' => true, 'message' => '物品堆叠已完全删除。', 'new_count' => 0];

        } else {
            // 更新数量
            $stmt_update = $pdo_C->prepare("UPDATE item_instance SET count = :new_count WHERE guid = :item_instance_guid");
            $stmt_update->execute([
                ':new_count' => $new_count,
                ':item_instance_guid' => $item_instance_guid
            ]);
            $updated = $stmt_update->rowCount();

            $pdo_C->commit();
            if ($updated > 0) {
                return ['success' => true, 'message' => "成功删除 {$quantity_to_delete} 个物品。剩余: {$new_count}。", 'new_count' => $new_count];
            } else {
                 // 理论上不应发生，防御性处理
                 return ['success' => false, 'message' => '更新物品数量失败。', 'new_count' => $current_count];
            }
        }

    } catch (Exception $e) {
        $pdo_C->rollBack();
        error_log("Error updating/deleting item stack: " . $e->getMessage());
        return ['success' => false, 'message' => '删除过程中发生错误: ' . $e->getMessage(), 'new_count' => -1];
    }
}

// --- Helper Functions for Display ---

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

function get_quality_name(int $quality_id): string
{
    $qualities = [
        0 => '粗糙', // Poor
        1 => '普通', // Common
        2 => '优秀', // Uncommon
        3 => '精良', // Rare
        4 => '史诗', // Epic
        5 => '传说', // Legendary
        6 => '神器', // Artifact
        7 => '传家宝' // Heirloom
    ];
    return $qualities[$quality_id] ?? '未知 (' . $quality_id . ')';
}

function get_inventory_type_name(int $bag, int $slot): string
{
    // Constants are now defined globally, no need to define them here.

    if ($slot >= SLOT_START_EQUIPMENT && $slot <= SLOT_END_EQUIPMENT) {
        return "装备"; // Equipment
    } elseif ($slot >= SLOT_START_BACKPACK && $slot <= SLOT_END_BACKPACK) {
        return "背包"; // Backpack
    } elseif ($slot >= SLOT_START_BAGS && $slot <= SLOT_END_BAGS) {
        return "背包栏"; // The bag item itself
    } elseif ($slot >= SLOT_START_BANK && $slot <= SLOT_END_BANK) {
        return "银行"; // Bank
    } elseif ($slot >= SLOT_START_BANK_BAGS && $slot <= SLOT_END_BANK_BAGS) {
        return "银行背包栏"; // Bank bag slots
    } elseif ($slot >= SLOT_START_KEYRING && $slot <= SLOT_END_KEYRING) {
        return "钥匙链"; // Keyring
    } elseif ($bag > 0) {
        // If bag ID is set and slot doesn't match known containers, assume it's in a bag
         return "背包"; // General Bag slot
    }
    
    return "未知 ({$bag}/{$slot})";
} 