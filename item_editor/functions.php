<?php
// item_editor/functions.php
declare(strict_types=1);

require_once __DIR__ . '/../bag_query/functions.php'; // Reuse helper functions like get_quality_name

// --- Item Constants ---
const ITEM_CLASSES = [
    0 => '消耗品', 1 => '容器', 2 => '武器', 3 => '珠宝', 4 => '护甲',
    5 => '材料', 6 => '投掷物', 7 => '交易品', 8 => '未知', 9 => '配方',
    10 => '货币(已弃用)', 11 => '箭袋', 12 => '任务物品', 13 => '钥匙',
    14 => '永久(已弃用)', 15 => '杂项', 16 => '雕文'
];

// Note: Subclasses depend on the main class. This is a simplified example.
// A more complete implementation might load this from DB or a more detailed config.
const ITEM_SUBCLASSES = [
    // Class 0: Consumable
    0 => [
        0 => '0 - 消耗品 (Consumable)',
        1 => '1 - 药水 (Potion)',
        2 => '2 - 药剂 (Elixir)',
        3 => '3 - 合剂 (Flask)',
        4 => '4 - 卷轴 (Scroll)',
        5 => '5 - 食物和饮料 (Food & Drink)',
        6 => '6 - 物品强化 (Item Enhancement)',
        7 => '7 - 绷带 (Bandage)',
        8 => '8 - 其他 (Other)',
    ],
    // Class 1: Container
    1 => [
        0 => '0 - 背包 (Bag)',
        1 => '1 - 灵魂袋 (Soul Bag)',
        2 => '2 - 草药袋 (Herb Bag)',
        3 => '3 - 附魔袋 (Enchanting Bag)',
        4 => '4 - 工程学袋 (Engineering Bag)',
        5 => '5 - 宝石袋 (Gem Bag)',
        6 => '6 - 矿石袋 (Mining Bag)',
        7 => '7 - 制皮袋 (Leatherworking Bag)',
        8 => '8 - 铭文袋 (Inscription Bag)',
    ],
    // Class 2: Weapon
    2 => [
        0 => '0 - 单手斧 (Axe 1h)',
        1 => '1 - 双手斧 (Axe 2h)',
        2 => '2 - 弓 (Bow)',
        3 => '3 - 枪械 (Gun)',
        4 => '4 - 单手锤 (Mace 1h)',
        5 => '5 - 双手锤 (Mace 2h)',
        6 => '6 - 长柄武器 (Polearm)',
        7 => '7 - 单手剑 (Sword 1h)',
        8 => '8 - 双手剑 (Sword 2h)',
        9 => '9 - 已废弃 (Obsolete)',
        10 => '10 - 法杖 (Staff)',
        11 => '11 - 异种武器1? (Exotic)',
        12 => '12 - 异种武器2? (Exotic)',
        13 => '13 - 拳套 (Fist Weapon)',
        14 => '14 - 杂项武器 (Miscellaneous)',
        15 => '15 - 匕首 (Dagger)',
        16 => '16 - 投掷武器 (Thrown)',
        17 => '17 - 矛 (Spear)',
        18 => '18 - 弩 (Crossbow)',
        19 => '19 - 魔杖 (Wand)',
        20 => '20 - 鱼竿 (Fishing Pole)',
    ],
    // Class 3: Gem
    3 => [
        0 => '0 - 红色 (Red)',
        1 => '1 - 蓝色 (Blue)',
        2 => '2 - 黄色 (Yellow)',
        3 => '3 - 紫色 (Purple)',
        4 => '4 - 绿色 (Green)',
        5 => '5 - 橙色 (Orange)',
        6 => '6 - 多彩 (Meta)',
        7 => '7 - 简易 (Simple)',
        8 => '8 - 棱彩 (Prismatic)',
    ],
    // Class 4: Armor
    4 => [
        0 => '0 - 杂项 (Miscellaneous)',
        1 => '1 - 布甲 (Cloth)',
        2 => '2 - 皮甲 (Leather)',
        3 => '3 - 锁甲 (Mail)',
        4 => '4 - 板甲 (Plate)',
        5 => '5 - 小圆盾(已废弃) (Buckler OBSOLETE)',
        6 => '6 - 盾牌 (Shield)',
        7 => '7 - 圣契 (Libram)',
        8 => '8 - 神像 (Idol)',
        9 => '9 - 图腾 (Totem)',
        10 => '10 - 印记 (Sigil)',
    ],
    // Class 5: Reagent (Material)
    5 => [
        0 => '0 - 材料 (Reagent)',
    ],
    // Class 6: Projectile (Ammo)
    6 => [
        0 => '0 - 魔杖(已废弃) (Wand OBSOLETE)',
        1 => '1 - 弩箭(已废弃) (Bolt OBSOLETE)',
        2 => '2 - 箭 (Arrow)',
        3 => '3 - 子弹 (Bullet)',
        4 => '4 - 投掷物(已废弃) (Thrown OBSOLETE)',
    ],
    // Class 7: Trade Goods
    7 => [
        0 => '0 - 商品 (Trade Goods)',
        1 => '1 - 零件 (Parts)',
        2 => '2 - 爆炸物 (Explosives)',
        3 => '3 - 装置 (Devices)',
        4 => '4 - 珠宝加工 (Jewelcrafting)',
        5 => '5 - 布料 (Cloth)',
        6 => '6 - 皮革 (Leather)',
        7 => '7 - 金属和矿石 (Metal & Stone)',
        8 => '8 - 肉类 (Meat)',
        9 => '9 - 草药 (Herb)',
        10 => '10 - 元素 (Elemental)',
        11 => '11 - 其他商品 (Other)',
        12 => '12 - 附魔 (Enchanting)',
        13 => '13 - 材料 (Materials)',
        14 => '14 - 护甲附魔 (Armor Enchantment)',
        15 => '15 - 武器附魔 (Weapon Enchantment)',
    ],
    // Class 8: Generic (OBSOLETE)
    8 => [
        0 => '0 - 通用(已废弃) (Generic OBSOLETE)',
    ],
    // Class 9: Recipe
    9 => [
        0 => '0 - 书籍 (Book)',
        1 => '1 - 制皮 (Leatherworking)',
        2 => '2 - 裁缝 (Tailoring)',
        3 => '3 - 工程学 (Engineering)',
        4 => '4 - 锻造 (Blacksmithing)',
        5 => '5 - 烹饪 (Cooking)',
        6 => '6 - 炼金术 (Alchemy)',
        7 => '7 - 急救 (First Aid)',
        8 => '8 - 附魔 (Enchanting)',
        9 => '9 - 钓鱼 (Fishing)',
        10 => '10 - 珠宝加工 (Jewelcrafting)',
        // 11 is missing in the provided list, assuming Inscription
        11 => '11 - 铭文 (Inscription)',
    ],
    // Class 10: Money (OBSOLETE)
    10 => [
        0 => '0 - 货币(已废弃) (Money OBSOLETE)',
    ],
    // Class 11: Quiver
    11 => [
        0 => '0 - 箭袋(已废弃) (Quiver OBSOLETE)',
        1 => '1 - 箭袋(已废弃) (Quiver OBSOLETE)',
        2 => '2 - 箭袋 (Quiver - Arrow)',
        3 => '3 - 弹药袋 (Ammo Pouch - Bullet)',
    ],
    // Class 12: Quest
    12 => [
        0 => '0 - 任务物品 (Quest)',
    ],
    // Class 13: Key
    13 => [
        0 => '0 - 钥匙 (Key)',
        1 => '1 - 开锁器 (Lockpick)',
    ],
    // Class 14: Permanent (OBSOLETE)
    14 => [
        0 => '0 - 永久(已废弃) (Permanent)',
    ],
    // Class 15: Miscellaneous
    15 => [
        0 => '0 - 垃圾 (Junk)',
        1 => '1 - 施法材料 (Reagent)',
        2 => '2 - 宠物 (Pet)',
        3 => '3 - 节日物品 (Holiday)',
        4 => '4 - 其他杂项 (Other)',
        5 => '5 - 坐骑 (Mount)',
    ],
    // Class 16: Glyph
    16 => [
        1 => '1 - 战士 (Warrior)',
        2 => '2 - 圣骑士 (Paladin)',
        3 => '3 - 猎人 (Hunter)',
        4 => '4 - 潜行者 (Rogue)',
        5 => '5 - 牧师 (Priest)',
        6 => '6 - 死亡骑士 (Death Knight)',
        7 => '7 - 萨满祭司 (Shaman)',
        8 => '8 - 法师 (Mage)',
        9 => '9 - 术士 (Warlock)',
        // 10 is unused
        11 => '11 - 德鲁伊 (Druid)',
    ],
];

const ITEM_QUALITIES = [
    -1 => '所有品质', // Special value for filter 'Any'
    0 => '粗糙 (灰)', 1 => '普通 (白)', 2 => '优秀 (绿)', 3 => '精良 (蓝)',
    4 => '史诗 (紫)', 5 => '传说 (橙)', 6 => '神器 (淡金)', 7 => '传家宝 (亮金)'
];


/**
 * Gets the Chinese name for an item class ID.
 */
function get_item_class_name(int $class_id): string {
    return ITEM_CLASSES[$class_id] ?? '未知类别 (' . $class_id . ')';
}

/**
 * Gets the Chinese name for an item subclass ID.
 */
function get_item_subclass_name(int $class_id, int $subclass_id): string {
    return ITEM_SUBCLASSES[$class_id][$subclass_id] ?? '未知子类别 (' . $subclass_id . ')';
}

/**
 * Gets the Chinese name for an item quality ID.
 * (Reuses the one from bag_query if available, otherwise defines local one)
 */
if (!function_exists('get_quality_name')) {
    function get_quality_name(int $quality): string {
        return ITEM_QUALITIES[$quality] ?? '未知品质 (' . $quality . ')';
    }
}

/**
 * Returns the array of item classes for dropdowns.
 */
function get_all_item_classes(): array {
    // Based on Class field and user provided list
    return [
        0 => '0 - 消耗品 (Consumable)',
        1 => '1 - 容器 (Container)',
        2 => '2 - 武器 (Weapon)',
        3 => '3 - 宝石 (Gem)',
        4 => '4 - 护甲 (Armor)',
        5 => '5 - 材料 (Reagent)',
        6 => '6 - 弹药 (Projectile)',
        7 => '7 - 商品 (Trade Goods)',
        8 => '8 - 通用 (Generic - 已废弃)',
        9 => '9 - 配方 (Recipe)',
        10 => '10 - 货币 (Money - 已废弃)',
        11 => '11 - 箭袋 (Quiver)',
        12 => '12 - 任务 (Quest)',
        13 => '13 - 钥匙 (Key)',
        14 => '14 - 永久 (Permanent - 已废弃)',
        15 => '15 - 杂项 (Miscellaneous)',
        16 => '16 - 雕文 (Glyph)',
    ];
}

/**
 * Returns the array of item subclasses for a given class, or all if no class specified.
 * Used for dropdowns.
 */
function get_all_item_subclasses(int $class_id = -1): array {
     if ($class_id !== -1 && isset(ITEM_SUBCLASSES[$class_id])) {
        return ITEM_SUBCLASSES[$class_id];
     }
     // Return all possible subclasses if no specific class or class not found
     $all_subclasses = [];
     foreach (ITEM_SUBCLASSES as $subclasses) {
         $all_subclasses += $subclasses; // '+' preserves keys if they overlap, good enough here
     }
     return $all_subclasses;
}

/**
 * Returns the array of item qualities for dropdowns.
 */
function get_all_qualities(): array {
    return ITEM_QUALITIES;
}


/**
 * Fetch a single item template by entry ID.
 * @param PDO $pdo_W World database connection.
 * @param int $item_id Entry ID of the item.
 * @return array|false Item data as an associative array, or false if not found.
 */
function get_item_template(PDO $pdo_W, int $item_id)
{
    try {
        $stmt = $pdo_W->prepare("SELECT * FROM item_template WHERE entry = :entry_id");
        $stmt->bindParam(':entry_id', $item_id, PDO::PARAM_INT);
        $stmt->execute();
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        return $item;
    } catch (PDOException $e) {
        error_log("Error fetching item template {$item_id}: " . $e->getMessage());
        return false;
    }
}

/**
 * Searches item templates based on various criteria.
 *
 * @param PDO $pdo_W World database connection.
 * @param string $search_type 'name' or 'id'.
 * @param string $search_value The search term.
 * @param string $filter_entry_op Operator for entry filter ('all', 'ge', 'le', 'eq', 'between').
 * @param string $filter_entry_val Value for entry filter (single ID or range "min-max").
 * @param int $filter_quality Quality ID (-1 for any).
 * @param int $filter_class Class ID (-1 for any).
 * @param int $filter_subclass Subclass ID (-1 for any).
 * @param string $filter_itemlevel_op Operator for item level filter ('any', 'ge', 'le', 'eq').
 * @param int|null $filter_itemlevel_val Value for item level filter.
 * @param int $limit Number of results per page.
 * @param int $page Current page number.
 * @param string $sort_by Default sort column.
 * @param string $sort_dir Default sort direction.
 * @return array ['results' => [], 'total' => 0]
 */
function search_item_templates(
    PDO $pdo_W,
    string $search_type,
    string $search_value,
    string $filter_entry_op = 'all', // <<< Default
    string $filter_entry_val = '',    // <<< Default
    int $filter_quality = -1,
    int $filter_class = -1,
    int $filter_subclass = -1,
    string $filter_itemlevel_op = 'any',
    ?int $filter_itemlevel_val = null,
    int $limit = 50,
    int $page = 1,
    string $sort_by = 'ItemLevel', // <<< NEW: Default sort column
    string $sort_dir = 'DESC'      // <<< NEW: Default sort direction
): array
{
    $base_sql = "SELECT entry, name, ItemLevel, Quality, class, subclass FROM item_template";
    $count_sql = "SELECT COUNT(*) FROM item_template";
    $where_clauses = [];
    $params = [];

    // Search Type and Value
    if (!empty($search_value)) {
        if ($search_type === 'id') {
            $where_clauses[] = "entry = :search_id";
            $params[':search_id'] = (int)$search_value;
        } elseif ($search_type === 'name') {
            $where_clauses[] = "name LIKE :search_name";
            $params[':search_name'] = '%' . $search_value . '%';
        }
    }

    // --- NEW: Entry ID Filter --- 
    if ($filter_entry_op !== 'all' && $filter_entry_val !== '') {
        if ($filter_entry_op === 'ge' && is_numeric($filter_entry_val)) {
            $where_clauses[] = "entry >= :entry_val";
            $params[':entry_val'] = (int)$filter_entry_val;
        } elseif ($filter_entry_op === 'le' && is_numeric($filter_entry_val)) {
            $where_clauses[] = "entry <= :entry_val";
            $params[':entry_val'] = (int)$filter_entry_val;
        } elseif ($filter_entry_op === 'eq' && is_numeric($filter_entry_val)) {
            $where_clauses[] = "entry = :entry_val";
            $params[':entry_val'] = (int)$filter_entry_val;
        } elseif ($filter_entry_op === 'between') {
            // Parse the range string "min-max"
            if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $filter_entry_val, $matches)) {
                $min_entry = (int)$matches[1];
                $max_entry = (int)$matches[2];
                // Ensure min <= max
                if ($min_entry <= $max_entry) {
                     $where_clauses[] = "entry BETWEEN :entry_start AND :entry_end";
                     $params[':entry_start'] = $min_entry;
                     $params[':entry_end'] = $max_entry;
                } else {
                     // Maybe log a warning or ignore invalid range
                     error_log("Invalid entry range provided in filter: {$filter_entry_val}");
                }
            } else {
                // Log warning or ignore if format is wrong
                error_log("Invalid format for 'between' entry filter: {$filter_entry_val}");
            }
        }
    }
    // --- END: Entry ID Filter ---

    // Quality Filter
    if ($filter_quality !== -1) {
        $where_clauses[] = "Quality = :quality";
        $params[':quality'] = $filter_quality;
    }

    // Class Filter
    if ($filter_class !== -1) {
        $where_clauses[] = "class = :class";
        $params[':class'] = $filter_class;
    }

    // Subclass Filter
    if ($filter_subclass !== -1) {
        $where_clauses[] = "subclass = :subclass";
        $params[':subclass'] = $filter_subclass;
    }

    // ItemLevel Filter
    if ($filter_itemlevel_op !== 'any' && $filter_itemlevel_val !== null && $filter_itemlevel_val >= 0) {
         // Replace PHP 8 match expression with switch for compatibility
         $operator = null; // Initialize operator
         switch ($filter_itemlevel_op) {
             case 'ge':
                 $operator = '>=';
                 break;
             case 'le':
                 $operator = '<=';
                 break;
             case 'eq':
                 $operator = '=';
                 break;
             // No default case needed as we validate $filter_itemlevel_op earlier
         }
         if ($operator) {
             $where_clauses[] = "ItemLevel {$operator} :itemlevel";
             $params[':itemlevel'] = $filter_itemlevel_val;
         }
    }

    // --- Get Total Count --- 
    $final_count_sql = $count_sql; // Start with base count SQL
    if (!empty($where_clauses)) {
        $final_count_sql .= " WHERE " . implode(" AND ", $where_clauses);
    }
    $totalItems = 0;
    try {
        $count_stmt = $pdo_W->prepare($final_count_sql);
        // Bind parameters for count query (excluding limit/offset)
        $count_params = $params; 
        // Remove limit/offset if they somehow got into $params for count
        unset($count_params[':limit'], $count_params[':offset']); 
        
        $count_stmt->execute($count_params);
        $totalItems = (int)$count_stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error counting item templates: " . $e->getMessage() . " SQL: " . $final_count_sql . " PARAMS: " . print_r($count_params ?? [], true));
        // Don't throw here, allow the main query to potentially proceed or fail
    }

    // --- Get Paginated Results ---
    $offset = max(0, ($page - 1) * $limit); // Ensure offset is not negative
    $final_main_sql = $base_sql; // Start with base select SQL
    if (!empty($where_clauses)) {
        $final_main_sql .= " WHERE " . implode(" AND ", $where_clauses);
    }

    // --- NEW: Sorting Logic ---
    // Validate sort column to prevent SQL injection
    $allowed_sort_columns = ['entry', 'name', 'ItemLevel', 'Quality'];
    if (!in_array($sort_by, $allowed_sort_columns)) {
        $sort_by = 'ItemLevel'; // Default to ItemLevel if invalid column provided
    }
    // Validate sort direction
    $sort_dir = strtoupper($sort_dir);
    if ($sort_dir !== 'ASC' && $sort_dir !== 'DESC') {
        $sort_dir = 'DESC'; // Default to DESC if invalid direction
    }

    // Always add a secondary sort by name for consistency when primary sort values are equal
    $order_by_clause = " ORDER BY `{$sort_by}` {$sort_dir}";
    if ($sort_by !== 'name') {
        // Add name as secondary sort if not already the primary
        $order_by_clause .= ", `name` ASC";
    }

    // Append ORDER BY and LIMIT/OFFSET
    $final_main_sql .= $order_by_clause . " LIMIT :limit OFFSET :offset";
    // --- END NEW: Sorting Logic ---

    // Add limit and offset to params *after* WHERE clause logic
    $params[':limit'] = $limit;
    $params[':offset'] = $offset;

    try {
        $stmt = $pdo_W->prepare($final_main_sql);
        // Bind values with appropriate types
        foreach ($params as $key => &$value) {
             // Identify integer parameters
             $intParams = [':limit', ':offset', ':quality', ':class', ':subclass', ':itemlevel', ':entry_val', ':entry_start', ':entry_end']; // Add entry params here too
             // Only :search_id should be treated as potentially integer-like if search_type is id
             if ($key === ':search_id') { 
                $intParams[] = $key; 
             }
             
             if (in_array($key, $intParams)) {
                 // Use bindValue for integer params as well, it's generally safer
                 $stmt->bindValue($key, (int)$value, PDO::PARAM_INT);
             } else {
                 $stmt->bindValue($key, $value, PDO::PARAM_STR);
             }
        }
        unset($value); // break the reference

        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return ['results' => $results, 'total' => $totalItems]; // Return results and total count
    } catch (PDOException $e) {
        error_log("Error searching item templates: " . $e->getMessage() . " SQL: " . $final_main_sql . " PARAMS: " . print_r($params, true));
        throw $e; // Re-throw
    }
}


function get_inventory_types(): array {
    // Based on InventoryType field in item_template
    // Source: Corrected list provided by user
    return [
        0 => '不可装备 (0)', // 物品无法被装备（如任务物品、材料等）
        1 => '头部 (Head) (1)', // 头盔、帽子类装备
        2 => '颈部 (Neck) (2)', // 项链类饰品
        3 => '肩部 (Shoulder) (3)', // 护肩、披风扣类装备
        4 => '衬衫 (Shirt) (4)', // 装饰性衣物（无属性）
        5 => '胸部 (Chest) (5)', // 胸甲、外套（与长袍 20 互斥）
        6 => '腰部 (Waist) (6)', // 腰带类装备
        7 => '腿部 (Legs) (7)', // 护腿、裤子类装备
        8 => '脚部 (Feet) (8)', // 靴子、鞋类装备
        9 => '手腕 (Wrists) (9)', // 护腕、手镯类装备
        10 => '手部 (Hands) (10)', // 手套类装备
        11 => '手指 (Finger) (11)', // 戒指类饰品（左右手指通用）
        12 => '饰品 (Trinket) (12)', // 特殊效果饰品（左右饰品槽通用）
        13 => '单手武器 (One-Hand) (13)', // 可单手持用的武器（剑、匕首等，与副手武器 22 区分）
        14 => '盾牌 (Shield) (14)', // 副手盾牌（归类为护甲，非武器）
        15 => '远程武器 (Ranged) (15)', // 弓类武器（与 26 远程右栏位区分）
        16 => '背部 (Back) (16)', // 披风类装备
        17 => '双手武器 (Two-Hand) (17)', // 需双手持用的武器（法杖、双手剑等）
        18 => '容器 (Bag) (18)', // 背包、容器类物品
        19 => '战袍 (Tabard) (19)', // 公会战袍等装饰性衣物
        20 => '长袍 (Robe) (20)', // 覆盖胸部和腿部的长袍（与胸甲 5 互斥）
        21 => '主手武器 (Main Hand) (21)', // 强制装备在主手栏的武器（如主手限定剑）
        22 => '副手武器 (Off-Hand) (22)', // 副手栏武器（匕首、副手剑等，与 13 单手武器区分）
        23 => '副手持握 (Held in Off-Hand) (23)', // 副手非武器类物品（典籍、火把、宝珠等，归类为护甲）
        24 => '弹药 (Ammo) (24)', // 箭矢、子弹等消耗品
        25 => '投掷武器 (Thrown) (25)', // 飞刀、标枪等可投掷武器
        26 => '远程右栏位 (Ranged right) (26)', // 魔杖、枪械类武器（与弓 15 区分）
        27 => '箭袋 (Quiver) (27)', // 弓箭专用容器（增加攻击速度） - Note: Might be Relic slot in some versions?
        // 28 => '圣物 (Relic) (28)', // 圣物类装备 (ID corrected based on list) - Previous list had Ammo Pouch here
        28 => '圣物 (Relic) (28)', // 圣物类装备（如圣骑士圣契、萨满图腾，归类为护甲）
    ];
}

function get_bonding_types(): array {
    // Based on bonding field
    // Source: Corrected list provided by user
    return [
        0 => '0 - 不绑定 (No bounds)',
        1 => '1 - 拾取绑定 (Binds when picked up)',
        2 => '2 - 装备绑定 (Binds when equipped)',
        3 => '3 - 使用绑定 (Binds when used)',
        4 => '4 - 任务物品 (Quest item)',
        5 => '5 - 任务物品1 (Quest Item1 - 特殊处理?)', // Similar to 4, might have special handling
    ];
}

function get_material_types(): array {
    // Based on Material field
    // Source: Corrected list provided by user
    return [
        -1 => '-1 - 消耗品 (Consumables)', // 食物、药剂、材料等无实体材质的物品（移动时无特殊音效）
        0 => '0 - 未定义 (Not Defined)', // 默认值，通常用于任务物品或特殊道具
        1 => '1 - 金属 (Metal)', // 武器、金属盔甲（移动时发出金属碰撞声）
        2 => '2 - 木材 (Wood)', // 木制武器（法杖、弓等）或盾牌（低沉木质音效）
        3 => '3 - 液体 (Liquid)', // 瓶装液体（药水、油等，摇晃时有水声）
        4 => '4 - 珠宝 (Jewelry)', // 戒指、项链等饰品（轻微清脆音效）
        5 => '5 - 锁甲 (Chain)', // 锁甲类护甲（较金属更柔和的链甲声）
        6 => '6 - 板甲 (Plate)', // 板甲类护甲（厚重金属声，与金属材质略有不同）
        7 => '7 - 布料 (Cloth)', // 布甲、绷带等（几乎无声）
        8 => '8 - 皮革 (Leather)', // 皮甲、皮革制品（柔软摩擦声）
    ];
}

function get_sheath_types(): array {
    // Based on sheath field
    // Source: Corrected list provided by user
    return [
        // 0 => '无 (0)', // User list doesn't include 0, assuming 1-6 are the relevant ones
        1 => '1 - 双手武器 (Two Handed - 剑尖朝下)',
        2 => '2 - 法杖 (Staff - 顶端朝上)',
        3 => '3 - 单手武器 (One Handed - 左侧腰部)',
        4 => '4 - 盾牌 (Shield - 背部中央)',
        5 => '5 - 附魔师法杖 (Enchanter Rod)',
        6 => '6 - 副手武器 (Off hand - 右侧腰部)',
    ];
}

function get_bag_families(): array {
    // Based on BagFamily field (Bitmask)
    // Source: Corrected list provided by user
    // Returns flags for informational purposes; field input remains a number.
    return [
        0 => '0 - 无限制 (None)',                     // 普通背包，可存放任何非专属物品
        1 => '1 - 箭矢 (Arrows)',                   // 仅能存放箭矢类弹药
        2 => '2 - 子弹 (Bullets)',                 // 仅能存放子弹类弹药
        4 => '4 - 灵魂碎片 (Soul Shards)',             // 仅术士的灵魂碎片
        8 => '8 - 制皮材料 (Leatherworking Supplies)',           // 皮革、鳞片等制皮专用材料
        16 => '16 - 铭文材料 (Inscription Supplies)',          // 墨水、羊皮纸等铭文专用材料
        32 => '32 - 草药 (Herbs)',                // 采集的草药类物品
        64 => '64 - 附魔材料 (Enchanting Supplies)',          // 尘、碎片等附魔材料
        128 => '128 - 工程学材料 (Engineering Supplies)',       // 齿轮、火药等工程学材料
        256 => '256 - 钥匙 (Keys)',               // 各类副本/任务钥匙
        512 => '512 - 宝石 (Gems)',               // 切割或未切割的宝石
        1024 => '1024 - 矿石 (Mining Supplies)',             // 矿石、锭等采矿产品
        2048 => '2048 - 灵魂绑定装备 (Soulbound Equipment)',    // 只能存放已绑定的装备
        4096 => '4096 - 小宠物 (Vanity Pets)',        // 非战斗宠物
        8192 => '8192 - 货币/代币 (Currency Tokens)',        // 副本徽章、荣誉奖章等
        16384 => '16384 - 任务物品 (Quest Items)',       // 专用于存放任务相关物品
    ];
}


function get_totem_categories(): array {
    // Based on TotemCategory field
    // Source: Corrected list provided by user
    // Note: Some IDs might be deprecated or have overlapping descriptions
    return [
        // 0 is typically not used for specific categories
        1 => '1 - 剥皮小刀 (OLD) (已废弃)',
        2 => '2 - 大地图腾 (Earth Totem)',
        3 => '3 - 空气图腾 (Air Totem)',
        4 => '4 - 火焰图腾 (Fire Totem)',
        5 => '5 - 水之图腾 (Water Totem)',
        6 => '6 - 铜质符文棒 (Runed Copper Rod)',
        7 => '7 - 银质符文棒 (Runed Silver Rod)',
        8 => '8 - 金质符文棒 (Runed Golden Rod)',
        9 => '9 - 真银符文棒 (Runed Truesilver Rod)',
        10 => '10 - 奥金符文棒 (Runed Arcanite Rod)',
        11 => '11 - 矿工锄 (OLD) (已废弃)',
        12 => '12 - 点金石 (Philosopher Stone)',
        13 => '13 - 铁匠锤 (OLD) (已废弃)',
        14 => '14 - 扳手 (Arclight Spanner)',
        15 => '15 - 微调保险丝 (Gyromatic Micro-Adjustor)',
        21 => '21 - 大师图腾 (Master Totem)',
        41 => '41 - 魔铁符文棒 (Runed Fel Iron Rod)',
        62 => '62 - 精金符文棒 (Runed Adamantite Rod)',
        63 => '63 - 恒金符文棒 (Runed Eternium Rod)',
        81 => '81 - 空心羽毛笔 (Hollow Quill)',
        101 => '101 - 蓝玉符文棒 (Runed Azurite Rod)',
        121 => '121 - 大师墨水组 (Virtuoso Inking Set)',
        141 => '141 - 战鼓 (Drums)',
        161 => '161 - 侏儒军刀 (Gnomish Army Knife)',
        162 => '162 - 铁匠锤 (Blacksmith Hammer)',
        165 => '165 - 矿工锄 (Mining Pick)',
        166 => '166 - 剥皮小刀 (Skinning Knife)',
        167 => '167 - 锤头锄 (Hammer Pick)',
        168 => '168 - 刃式矿锄 (Bladed Pickaxe)',
        169 => '169 - 燧石和火绒 (Flint and Tinder)',
        189 => '189 - 钴蓝符文棒 (Runed Cobalt Rod)',
        190 => '190 - 泰坦符文棒 (Runed Titanium Rod)',
    ];
}

function get_food_types(): array {
    // Based on FoodType field (used for pet feeding)
    // Source: Corrected list provided by user
    return [
        // 0 is typically not used
        1 => '1 - 肉类 (Meat)',         // 熟肉，可喂养食肉宠物
        2 => '2 - 鱼类 (Fish)',         // 熟鱼，可喂养食鱼宠物
        3 => '3 - 奶酪 (Cheese)',       // 奶酪类，部分特殊宠物
        4 => '4 - 面包 (Bread)',        // 面包类，通常宠物不可食用
        5 => '5 - 菌类 (Fungus)',       // 蘑菇等真菌类，可喂养孢子蝠等
        6 => '6 - 水果 (Fruit)',        // 水果类，可喂养猩猩等杂食宠物
        7 => '7 - 生肉 (Raw Meat)',     // 未烹饪肉类，TBC+宠物
        8 => '8 - 生鱼 (Raw Fish)',     // 未烹饪鱼类，TBC+宠物
    ];
}

// Add other helper functions related to item editor if needed...

// Example: Get quality name (might already exist in db.php)
if (!function_exists('get_quality_name')) {
    function get_quality_name(int $quality_id): string {
        return ITEM_QUALITIES[$quality_id] ?? '未知';
    }
}

// Example: Get class name (might already exist in db.php)
if (!function_exists('get_item_class_name')) {
    function get_item_class_name(int $class_id): string {
        return ITEM_CLASSES[$class_id] ?? '未知';
    }
}

// Example: Get subclass name (might already exist in db.php)
if (!function_exists('get_item_subclass_name')) {
    function get_item_subclass_name(int $class_id, int $subclass_id): string {
        return ITEM_SUBCLASSES[$class_id][$subclass_id] ?? '未知';
    }
}

// --- Functions for Flag/Bitmask Fields ---

function get_item_flags(): array {
    // Source: Corrected list provided by user
    return [
        1 => '物品标志：不可拾取 (未实装) (1)', // 0x01 ITEM_FLAG_NO_PICKUP (NOT IMPLEMENTED)
        2 => '魔法制造的物品 (2)', // 0x02 Conjured item
        4 => '可打开 (右键) (4)', // 0x04 Openable (can be opened by right-click)
        8 => '英雄物品提示标志 (未实装) (8)', // 0x08 ITEM_FLAG_HEROIC_TOOLTIP (NOT IMPLEMENTED)
        16 => '已废弃物品标志 (未实装) (16)', // 0x010 ITEM_FLAG_DEPRECATED (NOT IMPLEMENTED)
        32 => '不可摧毁 (仅能通过法术消耗) (32)', // 0x020 Item can not be destroyed, except by using spell
        64 => '玩家施法标志 (未实装) (64)', // 0x040 ITEM_FLAG_PLAYERCAST (NOT IMPLEMENTED)
        128 => '无装备冷却时间 (128)', // 0x080 ITEM_FLAG_NO_EQUIP_COOLDOWN
        256 => '多玩家任务共享拾取标志 (未实装) (256)', // 0x0100 ITEM_FLAG_MULTI_LOOT_QUEST (NOT IMPLEMENTED)
        512 => '包装容器 (可包裹其他物品) (512)', // 0x0200 Wrapper : Item can wrap other items
        1024 => '消耗资源标志 (未实装) (1024)', // 0x0400 ITEM_FLAG_USES_RESOURCES (NOT IMPLEMENTED)
        2048 => '队伍共享战利品 (全队可拾取) (2048)', // 0x0800 Item is party loot and can be looted by all
        4096 => '可退还物品 (4096)', // 0x01000 Item is refundable
        8192 => '契约物品 (竞技场/公会) (8192)', // 0x02000 Charter (Arena or Guild)
        16384 => '含文本标志 (未实装) (16384)', // 0x04000 ITEM_FLAG_HAS_TEXT (NOT IMPLEMENTED)
        32768 => '禁止分解标志 (未实装, 见分解技能) (32768)', // 0x08000 ITEM_FLAG_NO_DISENCHANT (NOT IMPLEMENTED)
        65536 => '真实持续时间标志 (未实装, 见flagsCustom) (65536)', // 0x010000 ITEM_FLAG_REAL_DURATION (NOT IMPLEMENTED)
        131072 => '隐藏制造者标志 (部分实装) (131072)', // 0x020000 ITEM_FLAG_NO_CREATOR (NOT IMPLEMENTED OR PARTIALLY)
        262144 => '可勘探 (矿石) (262144)', // 0x040000 Item can be prospected
        524288 => '唯一装备 (524288)', // 0x080000 Unique equipped
        1048576 => '忽略光环效果标志 (未实装) (1048576)', // 0x0100000 ITEM_FLAG_IGNORE_FOR_AURAS (NOT IMPLEMENTED)
        2097152 => '竞技场可用 (2097152)', // 0x0200000 Item can be used during arena match
        4194304 => '可投掷物品 (提示用) (4194304)', // 0x0400000 Throwable (for tooltip ingame)
        8388608 => '变形形态可用 (8388608)', // 0x0800000 Item can be used in shapeshift forms
        16777216 => '任务物品发光标志 (未实装) (16777216)', // 0x01000000 ITEM_FLAG_HAS_QUEST_GLOW (NOT IMPLEMENTED)
        33554432 => '专业配方 (需满足要求且未学习) (33554432)', // 0x02000000 Profession recipes loot flag
        67108864 => '竞技场禁用 (67108864)', // 0x04000000 Item cannot be used in arena
        134217728 => '账号绑定 (需设置Bonding > 0) (134217728)', // 0x08000000 Bind to Account
        268435456 => '无材料消耗施法 (268435456)', // 0x010000000 Spell is cast ignoring reagents (ITEM_FLAG_NO_REAGENT_COST)
        536870912 => '可研磨 (草药) (536870912)', // 0x020000000 Millable
        1073741824 => '公会聊天报告标志 (未实装) (1073741824)', // 0x040000000 ITEM_FLAG_REPORT_TO_GUILD_CHAT (NOT IMPLEMENTED)
        // Using float for 2^31 due to potential 32-bit integer overflow issues in PHP
        2147483648 => '非渐进式拾取标志 (未实装) (2147483648)', // 0x080000000 ITEM_FLAG_NO_PROGRESSIVE_LOOT (NOT IMPLEMENTED)
    ];
}

function get_item_flags_extra(): array {
    // Source: Corrected list provided by user
    return [
        1 => '部落专属 (1)', // 0x01 Horde Only
        2 => '联盟专属 (2)', // 0x02 Alliance Only
        4 => '额外费用需金币 (4)', // 0x04 When item uses ExtendedCost in npc_vendor, gold is also required
        256 => '禁用需求掷骰 (256)', // 0x0100 Makes need roll for this item disabled
        512 => '禁用需求掷骰 (同256?) (512)', // 0x0200 NEED_ROLL_DISABLED - Seems duplicate/redundant with 256?
        16384 => '物品具有标准定价 (16384)', // 0x04000 HAS_NORMAL_PRICE
        131072 => '战网账号绑定 (335a无用?) (131072)', // 0x020000 BNET_ACCOUNT_BOUND (seems useless on 3.3.5a)
        2097152 => '不可被幻化 (来源限制) (2097152)', // 0x0200000 CANNOT_BE_TRANSMOG
        4194304 => '不可幻化其他物品 (目标限制) (4194304)', // 0x0400000 CANNOT_TRANSMOG
        8388608 => '可幻化 (允许被复制外观) (8388608)', // 0x0800000 CAN_TRANSMOG
        // Note: Transmog flags might not be relevant or fully functional in standard 3.3.5a.
    ];
}

function get_item_flags_custom(): array {
    // Source: Corrected list provided by user (Matches AzerothCore wiki)
    return [
        1 => '真实时间计时 (离线也计时) (1)', // ITEM_FLAGS_CU_DURATION_REAL_TIME
        2 => '忽略任务状态 (掉落) (2)', // ITEM_FLAGS_CU_IGNORE_QUEST_STATUS
        4 => '遵循队伍拾取规则 (4)', // ITEM_FLAGS_CU_FOLLOW_LOOT_RULES
    ];
}

// --- Functions for Requirement Fields ---

function get_reputation_ranks(): array {
    // Based on RequiredReputationRank field and user provided list
    return [
        0 => '0 - 仇恨 (Hated)',
        1 => '1 - 敌对 (Hostile)',
        2 => '2 - 冷淡 (Unfriendly)',
        3 => '3 - 中立 (Neutral)',
        4 => '4 - 友善 (Friendly)',
        5 => '5 - 尊敬 (Honored)',
        6 => '6 - 崇敬 (Revered)',
        7 => '7 - 崇拜 (Exalted)',
    ];
}

// --- Functions for Stat Fields ---

function get_stat_types(): array {
    // Based on stat_typeX fields and user provided list
    return [
        0 => '0 - 法力值 (MANA)',
        1 => '1 - 生命值 (HEALTH)',
        // 2 is unused
        3 => '3 - 敏捷 (AGILITY)',
        4 => '4 - 力量 (STRENGTH)',
        5 => '5 - 智力 (INTELLECT)',
        6 => '6 - 精神 (SPIRIT)',
        7 => '7 - 耐力 (STAMINA)',
        // 8-11 are unused
        12 => '12 - 防御技能等级 (DEFENSE_SKILL_RATING)',
        13 => '13 - 躲闪等级 (DODGE_RATING)',
        14 => '14 - 招架等级 (PARRY_RATING)',
        15 => '15 - 格挡等级 (BLOCK_RATING)',
        16 => '16 - 近战命中等级 (HIT_MELEE_RATING)',
        17 => '17 - 远程命中等级 (HIT_RANGED_RATING)',
        18 => '18 - 法术命中等级 (HIT_SPELL_RATING)',
        19 => '19 - 近战暴击等级 (CRIT_MELEE_RATING)',
        20 => '20 - 远程暴击等级 (CRIT_RANGED_RATING)',
        21 => '21 - 法术暴击等级 (CRIT_SPELL_RATING)',
        22 => '22 - 被近战命中躲闪等级 (HIT_TAKEN_MELEE_RATING)',
        23 => '23 - 被远程命中躲闪等级 (HIT_TAKEN_RANGED_RATING)',
        24 => '24 - 被法术命中躲闪等级 (HIT_TAKEN_SPELL_RATING)',
        25 => '25 - 被近战暴击抵抗等级 (CRIT_TAKEN_MELEE_RATING)',
        26 => '26 - 被远程暴击抵抗等级 (CRIT_TAKEN_RANGED_RATING)',
        27 => '27 - 被法术暴击抵抗等级 (CRIT_TAKEN_SPELL_RATING)',
        28 => '28 - 近战急速等级 (HASTE_MELEE_RATING)',
        29 => '29 - 远程急速等级 (HASTE_RANGED_RATING)',
        30 => '30 - 法术急速等级 (HASTE_SPELL_RATING)',
        31 => '31 - 命中等级 (HIT_RATING)',
        32 => '32 - 暴击等级 (CRIT_RATING)',
        33 => '33 - 被命中躲闪等级 (HIT_TAKEN_RATING)',
        34 => '34 - 被暴击抵抗等级 (CRIT_TAKEN_RATING)',
        35 => '35 - 韧性等级 (RESILIENCE_RATING)',
        36 => '36 - 急速等级 (HASTE_RATING)',
        37 => '37 - 精准等级 (EXPERTISE_RATING)',
        38 => '38 - 攻击强度 (ATTACK_POWER)',
        39 => '39 - 远程攻击强度 (RANGED_ATTACK_POWER)',
        40 => '40 - 野性攻击强度 (FERAL_ATTACK_POWER - unused 3.3+)',
        41 => '41 - 法术治疗加成 (SPELL_HEALING_DONE)',
        42 => '42 - 法术伤害加成 (SPELL_DAMAGE_DONE)',
        43 => '43 - 法力回复 (MANA_REGENERATION)',
        44 => '44 - 护甲穿透等级 (ARMOR_PENETRATION_RATING)',
        45 => '45 - 法术强度 (SPELL_POWER)',
        46 => '46 - 生命回复 (HEALTH_REGEN)',
        47 => '47 - 法术穿透 (SPELL_PENETRATION)',
        48 => '48 - 格挡值 (BLOCK_VALUE)',
        // 49+ are likely unused or specific expansion features
    ];
}

// --- Functions for Damage Fields ---

function get_damage_types(): array {
    // Based on dmg_typeX fields and user provided list
    return [
        0 => '0 - 物理 (Physical)',
        1 => '1 - 神圣 (Holy)',
        2 => '2 - 火焰 (Fire)',
        3 => '3 - 自然 (Nature)',
        4 => '4 - 冰霜 (Frost)',
        5 => '5 - 暗影 (Shadow)',
        6 => '6 - 奥术 (Arcane)',
    ];
}

// --- Functions for Spell Fields ---

function get_spell_trigger_types(): array {
    // Based on spelltrigger_X fields and user provided list
    return [
        0 => '0 - 使用触发 (Use)',
        1 => '1 - 装备触发 (On Equip)',
        2 => '2 - 击中时概率触发 (Chance on Hit)',
        // 3 is unused?
        4 => '4 - 灵魂石复活 (Soulstone)',
        5 => '5 - 无延迟使用 (Use with no delay)',
        6 => '6 - 学习法术 (Learn Spell ID)',
    ];
}

// --- Functions for Socket Fields ---

function get_socket_colors(): array {
    // Based on socketColor_X fields and user provided list
    return [
        // 0 is typically not a valid socket color
        1 => '1 - 多彩 (Meta)',
        2 => '2 - 红色 (Red)',
        4 => '4 - 黄色 (Yellow)',
        8 => '8 - 蓝色 (Blue)',
    ];
}

function get_socket_bonus_options(): array {
    // Based on socketBonus field and user provided list
    return [
        // Include 0 or a 'None' option if applicable
        0 => '0 - 无奖励', 
        3    => '3 - +8 智力 (Intellect)',
        3305 => '3305 - +12 耐力 (Stamina)',
        3312 => '3312 - +8 力量 (Strength)',
        3313 => '3313 - +8 敏捷 (Agility)',
        2872 => '2872 - +9 治疗强度 (Healing)',
        3753 => '3753 - +9 法术强度 (Spell Power)',
        3877 => '3877 - +16 攻击强度 (Attack Power)',
        // Add other common bonuses if known, or rely on manual input for others
    ];
}

?> 