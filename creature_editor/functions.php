<?php
// creature_editor/functions.php
declare(strict_types=1);

// No need to require db.php here as index.php includes it before calling functions from here.
// However, if you plan to use functions independently, you might need it.
// require_once __DIR__ . '/../bag_query/db.php';

/**
 * Searches creature templates based on criteria.
 *
 * @param PDO $pdo_W World database PDO connection.
 * @param string $search_type 'id' or 'name'.
 * @param string $search_value The value to search for.
 * @param int $limit Number of results per page.
 * @param int $page Current page number.
 * @param ?int $filter_minlevel Minimum level filter.
 * @param ?int $filter_maxlevel Maximum level filter.
 * @param int $filter_rank Rank filter.
 * @param int $filter_type Type filter.
 * @param ?int $filter_faction Faction filter.
 * @param ?int $filter_npcflag NPC flag filter.
 * @param string $sort_by Sort column.
 * @param string $sort_dir Sort direction.
 * @return array An array containing 'results' (array of creatures) and 'total' (int total count).
 */
function search_creatures(
    PDO $pdo_W,
    string $search_type,
    string $search_value,
    int $limit = 50,
    int $page = 1,
    // --- NEW: Filter parameters ---
    ?int $filter_minlevel = null,
    ?int $filter_maxlevel = null,
    int $filter_rank = -1, // -1 means 'any'
    int $filter_type = -1, // -1 means 'any'
    ?int $filter_faction = null,
    ?int $filter_npcflag = null,
    // --- NEW: Sort parameters ---
    string $sort_by = 'entry',
    string $sort_dir = 'ASC'
): array
{
    $results = [];
    $total = 0;
    $offset = max(0, ($page - 1) * $limit);

    // Base SQL queries
    $base_sql = "SELECT entry, name, subname, minlevel, maxlevel, faction, npcflag FROM `creature_template`";
    $count_sql = "SELECT COUNT(*) FROM `creature_template`";

    // WHERE clauses and parameters
    $where_clauses = [];
    $params = [];

    if (!empty($search_value)) {
        if ($search_type === 'id') {
            // Try to convert to integer for exact match
            $entry_id = filter_var($search_value, FILTER_VALIDATE_INT);
            if ($entry_id !== false && $entry_id > 0) {
                $where_clauses[] = "entry = :entry_id";
                $params[':entry_id'] = $entry_id;
            } else {
                // Invalid ID format, return no results
                return ['results' => [], 'total' => 0];
            }
        } elseif ($search_type === 'name') {
            $where_clauses[] = "name LIKE :name";
            $params[':name'] = '%' . $search_value . '%';
        }
        // Add more search types later (e.g., SubName)
    }

    // --- NEW: Add Filters ---
    if ($filter_minlevel !== null && $filter_minlevel >= 0) {
        $where_clauses[] = "minlevel >= :minlevel";
        $params[':minlevel'] = $filter_minlevel;
    }
    if ($filter_maxlevel !== null && $filter_maxlevel >= 0) {
        $where_clauses[] = "maxlevel <= :maxlevel";
        $params[':maxlevel'] = $filter_maxlevel;
    }
    if ($filter_rank !== -1) {
        $where_clauses[] = "rank = :rank";
        $params[':rank'] = $filter_rank;
    }
    if ($filter_type !== -1) {
        $where_clauses[] = "type = :type";
        $params[':type'] = $filter_type;
    }
    if ($filter_faction !== null) {
        $where_clauses[] = "faction = :faction";
        $params[':faction'] = $filter_faction;
    }
    if ($filter_npcflag !== null) {
        // Simple exact match for now. Bitwise check could be added later.
        $where_clauses[] = "npcflag = :npcflag";
        $params[':npcflag'] = $filter_npcflag;
    }

    // Construct final SQL
    $final_sql = $base_sql;
    $final_count_sql = $count_sql;

    if (!empty($where_clauses)) {
        $where_sql = " WHERE " . implode(' AND ', $where_clauses);
        $final_sql .= $where_sql;
        $final_count_sql .= $where_sql;
    }

    // --- Get Total Count ---
    try {
        $count_stmt = $pdo_W->prepare($final_count_sql);
        $count_stmt->execute($params);
        $total = (int)$count_stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Creature Search Count Error: " . $e->getMessage() . " SQL: " . $final_count_sql . " Params: " . print_r($params, true));
        // Optionally throw or return error state
        return ['results' => [], 'total' => 0, 'error' => '数据库计数查询失败'];
    }

    // --- Get Paginated Results ---
    if ($total > 0) {
        // --- NEW: Validate sort column and add ORDER BY ---
        $allowed_sort_columns = ['entry', 'name', 'minlevel', 'maxlevel', 'faction', 'npcflag']; // Columns allowed for sorting (lowercase)
        $safe_sort_by = in_array($sort_by, $allowed_sort_columns) ? $sort_by : 'entry'; // Default to 'entry' if invalid
        $safe_sort_dir = ($sort_dir === 'DESC') ? 'DESC' : 'ASC'; // Ensure direction is ASC or DESC

        $final_sql .= " ORDER BY `" . $safe_sort_by . "` " . $safe_sort_dir;

        // Add LIMIT and OFFSET
        $final_sql .= " LIMIT :limit OFFSET :offset";
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;

        try {
            $stmt = $pdo_W->prepare($final_sql);
            // Bind parameters explicitly by type for LIMIT/OFFSET and others
            foreach ($params as $key => $value) {
                 if ($key === ':limit' || $key === ':offset' || is_int($value)) {
                     $stmt->bindValue($key, $value, PDO::PARAM_INT);
                 } else {
                     $stmt->bindValue($key, $value); // PDO determines type automatically for others
                 }
            }
            
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Creature Search Fetch Error: " . $e->getMessage() . " SQL: " . $final_sql . " Params: " . print_r($params, true));
            // Optionally throw or return error state
             return ['results' => [], 'total' => $total, 'error' => '数据库结果查询失败']; // Return total found, but empty results
        }
    }

    return ['results' => $results, 'total' => $total];
}

// --- Add more helper functions later ---
// Example: function get_creature_ranks() { ... }
// Example: function get_npc_flags() { ... }

// --- NEW Helper Functions ---

/**
 * Returns an array of creature ranks for dropdowns.
 * Based on creature_template.rank column values.
 */
function get_creature_ranks(bool $for_filter = true): array {
    $ranks = [
        0 => '0 - 普通',
        1 => '1 - 精英',
        2 => '2 - 稀有精英',
        3 => '3 - 首领',
        4 => '4 - 稀有',
    ];

    if ($for_filter) {
        // Prepend the 'All Ranks' option for filtering
        return [-1 => '所有等级'] + $ranks;
    } else {
        return $ranks; // Return only actual ranks for editing
    }
}

/**
 * Returns an array of creature types for dropdowns.
 * Based on creature_template.type column values (WotLK 3.3.5a).
 */
function get_creature_types(bool $for_filter = true): array {
    $types = [
        0 => '0 - 无',
        1 => '1 - 野兽',
        2 => '2 - 龙类',
        3 => '3 - 恶魔',
        4 => '4 - 元素',
        5 => '5 - 巨人',
        6 => '6 - 亡灵',
        7 => '7 - 人形',
        8 => '8 - 小动物',
        9 => '9 - 机械',
        10 => '10 - 未指定',
        11 => '11 - 图腾',
        12 => '12 - 非战斗宠物',
        13 => '13 - 气体云', // E.g., Poison Cloud
    ];

    if ($for_filter) {
        // Prepend the 'All Types' option for filtering
        return [-1 => '所有类型'] + $types;
    } else {
        return $types; // Return only actual types for editing
    }
}

// --- NEW CONSTANT for NPC Flags ---
/**
 * Defines the bitmask values for creature_template.npcflag.
 * Based on AzerothCore Wiki (3.3.5a).
 */
const CREATURE_NPC_FLAGS = [
    1 => '闲聊 (Gossip)',
    2 => '任务给予者 (Questgiver)',
    16 => '训练师 (Trainer)',
    32 => '职业训练师 (Class Trainer)',
    64 => '专业训练师 (Profession Trainer)',
    128 => '商人 (Vendor)',
    256 => '弹药商人 (Ammo Vendor)',
    512 => '食物商人 (Food Vendor)',
    1024 => '毒药商人 (Poison Vendor)',
    2048 => '材料商人 (Reagent Vendor)',
    4096 => '修理者 (Repairer)',
    8192 => '飞行管理员 (Flight Master)',
    16384 => '灵魂医者 (Spirit Healer)',
    32768 => '灵魂向导 (Spirit Guide)',
    65536 => '旅店老板 (Innkeeper)',
    131072 => '银行职员 (Banker)',
    262144 => '申请管理者 (Tabard Designer / Guild Petitioner)',
    524288 => '公会徽章设计师 (Battlemaster)', // 修正: 524288 应该是战袍设计者
    1048576 => '战场管理员 (Auctioneer)', // 修正: 1048576 应该是拍卖师
    2097152 => '拍卖师 (Stable Master)', // 修正: 2097152 应该是兽栏管理员
    4194304 => '兽栏管理员 (Guild Banker)', // 修正: 4194304 应该是公会银行
    8388608 => '公会银行职员 (Spellclick)', // 修正: 8388608 应该是Spellclick
    16777216 => '法术点击 (Player Vehicle?)', // 修正: 16777216 应该是邮箱 (Mailbox), 需要 npc_spellclick_spells
    // 33554432 => '邮箱 (Mailbox)', // AC uses 67108864 for Mailbox
    67108864 => '邮箱 (Mailbox)',
];

// --- NEW Function to get a single creature template ---
/**
 * Retrieves a single creature template record by its entry ID.
 *
 * @param PDO $pdo_W World database PDO connection.
 * @param int $creature_id The entry ID of the creature.
 * @return array|false An associative array of the creature data or false if not found.
 */
function get_creature_template(PDO $pdo_W, int $creature_id)
{
    if ($creature_id <= 0) {
        return false;
    }
    try {
        $sql = "SELECT * FROM `creature_template` WHERE entry = :entry_id";
        $stmt = $pdo_W->prepare($sql);
        $stmt->bindParam(':entry_id', $creature_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            // Ensure all keys are lowercase for consistent access in index.php
            return array_change_key_case($result, CASE_LOWER);
        }
        return false; // Return false if no row found
    } catch (PDOException $e) {
        error_log("Get Creature Template Error: " . $e->getMessage() . " ID: " . $creature_id);
        return false;
    }
}

// --- NEW Function to get creature_template column names ---
/**
 * Returns a list of valid column names for the creature_template table.
 * Used for validation and SQL generation.
 *
 * @return array List of column names.
 */
function get_creature_template_columns(): array {
    // Ideally, fetch this dynamically or keep updated with DB schema
    // Based on standard AzerothCore 3.3.5a - using lowercase names from wiki
    return [
        'entry', 'difficulty_entry_1', 'difficulty_entry_2', 'difficulty_entry_3',
        'killcredit1', 'killcredit2', 'modelid1', 'modelid2', 'modelid3', 'modelid4',
        'name', 'subname', 'iconname', 'gossip_menu_id', 'minlevel', 'maxlevel',
        'exp', 'exp_req', 'faction', 'npcflag', 'npcflag2', 'speed_walk', 'speed_run',
        'speed_fly', 'scale', 'rank', 'dmgschool', 'damagemodifier', 'baseattacktime',
        'rangeattacktime', 'basevariance', 'rangevariance', 'unit_class', 'unit_flags',
        'unit_flags2', 'dynamicflags', 'family', 'trainer_type', 'trainer_spell',
        'trainer_class', 'trainer_race', 'type', 'type_flags', 'type_flags2', 'lootid', 'pickpocketloot',
        'skinloot', 'resistance1', 'resistance2', 'resistance3', 'resistance4',
        'resistance5', 'resistance6', 'spell1', 'spell2', 'spell3', 'spell4',
        'spell5', 'spell6', 'spell7', 'spell8', 'petspelldataid', 'vehicleid',
        'mingold', 'maxgold', 'ainame', 'movementtype', 'inhabittype', 'hoverheight',
        'healthmodifier', 'manamodifier', 'armormodifier', 'experiencemodifier',
        'racialleader', 'movementid', 'regenhealth', 'mechanic_immune_mask',
        'flags_extra', 'scriptname', 'verifiedbuild'
        // Add any custom columns if they exist
    ];
}

// --- Constants for Bitmask Fields ---
const CREATURE_UNIT_FLAGS = [
    1 => '服务器控制 (Server controlled movement)',
    2 => '不可攻击 (Non-attackable)',
    4 => '禁止移动 (Disable move)',
    8 => '玩家控制 (PvP attackable?)', // Wiki says 'PVP attackable', description says 'Player Controlled'
    16 => '可重命名 (Rename)',
    32 => '准备状态 (Preparation)',
    64 => '未知标志_6 (SmartScript target?)',
    128 => '不可攻击_1 (FFA PvP?)',
    256 => '对玩家免疫 (Immune to PC)',
    512 => '对NPC免疫 (Immune to NPC)',
    1024 => '拾取中 (Looting)',
    2048 => '宠物战斗中 (Pet in combat)',
    4096 => 'PvP',
    8192 => '沉默 (Silenced)',
    16384 => '无法游泳 (Cannot swim)',
    32768 => '游泳 (Swim animation?)', // Wiki says UNK_15 prevents swim?
    65536 => '不可攻击_2 (OOC?)',
    131072 => '平静 (Pacified)',
    262144 => '眩晕 (Stunned)',
    524288 => '战斗中 (In combat)',
    1048576 => '飞行禁止施法 (Taxi flight)',
    2097152 => '解除武装 (Disarmed)',
    4194304 => '混乱 (Confused)',
    8388608 => '恐惧 (Feared)',
    16777216 => '被控制 (Possessed/Player Controlled)',
    33554432 => '不可选择 (Not selectable)',
    67108864 => '可剥皮 (Skinnable)',
    134217728 => '坐骑 (Mount)',
    268435456 => '未知标志_28 (Prevent Kneel?)',
    536870912 => '禁止聊天表情 (Prevent emotes)',
    1073741824 => '武器入鞘 (Sheathe)',
    2147483648 => '免疫伤害 (Immune?)'
];

const CREATURE_UNIT_FLAGS2 = [
    1 => '假死 (Feign Death)',
    2 => '隐藏身体 (Hide Body - model only?)',
    4 => '忽略声望 (Ignore Reputation)',
    8 => '理解语言 (Comprehend Language)',
    16 => '镜像 (Mirror Image)',
    32 => '无渐显 (Instant Summon Display?)', // Wiki says FORCE_MOVE
    64 => '强制移动 (Force Move?)', // Wiki says DISARM_OFFHAND
    128 => '副手解除武装 (Disarm Offhand?)', // Wiki says DISABLE_PRED_STATS
    256 => '禁用预测属性 (Disable Pred Stats - for raid frames?)',
    // 512 is missing in provided list
    1024 => '远程武器解除武装 (Disarm Ranged?)',
    2048 => '能量再生 (Regenerate Power)',
    4096 => '限制队伍交互 (Restrict Party Interaction)',
    8192 => '禁止法术点击 (Prevent Spell Click)',
    16384 => '允许敌对交互 (Allow Enemy Interact)',
    32768 => '无法转向 (Disable Turn)',
    65536 => '未知标志_2 (Unknown)',
    131072 => '播放死亡动画 (Play Special Death Anim)',
    262144 => '允许作弊魔法 (Allow Cheat Spells)'
];

const CREATURE_DYNAMIC_FLAGS = [
    0 => '无 (None)', // Value 0 isn't typically included in bitmasks
    1 => '可拾取 (Lootable)',
    2 => '追踪单位 (Track Unit)',
    4 => '被标记 (Tapped by other?)',
    8 => '被玩家标记 (Tapped by player)',
    16 => '特殊信息 (Special Info)',
    32 => '死亡 (Dead Appearance)',
    64 => '推荐好友 (Refer-a-Friend)',
    128 => '被所有威胁列表标记 (Tapped by all threat list)'
];

const CREATURE_TYPE_FLAGS = [
    1 => '可驯服 (Tameable)',
    2 => '对灵魂可见 (Ghost - visible to spirits)',
    4 => '首领 (Boss - immune to certain spells, shows ??)',
    8 => '无受伤格挡动画 (No wound/parry anim)',
    16 => '隐藏阵营提示 (Hide faction tooltip)',
    32 => '更响亮 (Louder sounds?)',
    64 => '可被魔法攻击 (Spell Attackable?)',
    128 => '死亡时可交互 (Dead Interact)',
    256 => '可采药 (Herb Loot)',
    512 => '可采矿 (Mining Loot)',
    1024 => '无死亡信息 (No combat log death?)', // Wiki says CANASSIST
    2048 => '允许骑乘战斗 (Can remain mounted?)', // Wiki says IS_PET_BAR_USED
    4096 => '可协助 (Can Assist?)', // Wiki says MASK_UID
    8192 => '无宠物栏 (No Pet Bar?)', // Wiki says ENGINEERLOOT
    16384 => '掩码UID? (Mask UID?)', // Wiki says EXOTIC
    32768 => '可工程拾取 (Engineer Loot)', // Wiki says USE_DEFAULT_COLLISION_BOX
    65536 => '可驯服异种 (Exotic Pet?)', // Wiki says IS_SIEGE_WEAPON
    131072 => '使用模型碰撞体积 (Use model collision box?)', // Wiki says PROJECTILE_COLLISION
    262144 => '战斗中可交互 (Siege Weapon interaction?)', // Wiki says IS_PET_TALENT_USED
    524288 => '可与投射物碰撞 (Projectile Collision?)', // Wiki says HIDE_NAMEPLATE
    1048576 => '隐藏姓名板 (Hide Nameplate)', // Wiki says DO_NOT_PLAY_MOUNTED_ANIMATIONS
    2097152 => '无骑乘动画 (No Mounted Anim?)', // Wiki says IS_WORLD_BOSS
    4194304 => '全部链接? (World Boss?)', // Wiki says TARGETABLE_BY_AOE_ONLY_IN_COMBAT
    8388608 => '仅限创造者交互? (Creator Interact Only?)', // Wiki says DO_NOT_SHEATHE
    16777216 => '无单位事件音效? (No Event Sounds?)', // Wiki says TAUNT_DIMINISHING
    33554432 => '无阴影效果 (No Shadow?)',
    67108864 => '视为团队单位 (Usable in Raid Groups)',
    134217728 => '强制闲聊 (Force Gossip)',
    268435456 => '不入鞘 (Do Not Sheathe)',
    536870912 => '交互时不选为目标 (No Select on Interact)',
    1073741824 => '不显示对象名称 (Hide Name)',
    2147483648 => '任务首领 (Quest Boss)'
];

const CREATURE_FLAGS_EXTRA = [
    1 => '实例绑定 (INSTANCE_BIND)',
    2 => '平民 (CIVILIAN - 不主动攻击)',
    4 => '无招架 (NO_PARRY)',
    8 => '招架不加速 (NO_PARRY_HASTEN)',
    16 => '无格挡 (NO_BLOCK)',
    32 => '无碾压 (NO_CRUSHING_BLOWS)',
    64 => '无经验 (NO_XP)',
    128 => '触发器 (TRIGGER - 对玩家隐形)',
    256 => '免疫嘲讽 (NO_TAUNT)',
    // 512 => '未使用 (NO_MOVE_FLAGS_UPDATE - Unused)',
    1024 => '灵魂可见 (GHOST_VISIBILITY)',
    2048 => '使用副手攻击 (USE_OFFHAND_ATTACK)',
    4096 => '非卖品商人 (NO_SELL_VENDOR)',
    8192 => '忽略战斗? (IGNORE_COMBAT)',
    16384 => '世界事件 (WORLDEVENT)',
    32768 => '守卫 (GUARD - 忽略潜行/消失)',
    65536 => '忽略假死 (IGNORE_FEIGN_DEATH)',
    131072 => '无爆击 (NO_CRIT)',
    262144 => '无技能提升 (NO_SKILL_GAINS)',
    524288 => '嘲讽递减 (OBEYS_TAUNT_DIMINISHING_RETURNS)',
    1048576 => '所有递减 (ALL_DIMINISH)',
    2097152 => '无玩家伤害要求 (NO_PLAYER_DAMAGE_REQ - 击杀计数)',
    4194304 => '规避AOE (AVOID_AOE)',
    8388608 => '无闪避 (NO_DODGE)',
    16777216 => '模块生物 (MODULE)',
    33554432 => '不呼叫援军 (DONT_CALL_ASSISTANCE)',
    67108864 => '忽略所有援军呼叫 (IGNORE_ALL_ASSISTANCE_CALLS)',
    134217728 => '不覆盖 SAI 条目 (DONT_OVERRIDE_SAI_ENTRY)',
    268435456 => '副本首领 (DUNGEON_BOSS - 由核心设置,勿手动添加)',
    536870912 => '忽略寻路 (IGNORE_PATHFINDING)',
    1073741824 => '免疫击退 (IMMUNITY_KNOCKBACK)',
    2147483648 => '强制重置 (HARD_RESET - 逃脱时消失)'
];

const MECHANIC_IMMUNE_MASK = [
    1 => '魅惑 (MECHANIC_CHARM)',
    2 => '迷惑 (MECHANIC_DISORIENTED)',
    4 => '缴械 (MECHANIC_DISARM)',
    8 => '分心 (MECHANIC_DISTRACT)',
    16 => '恐惧 (MECHANIC_FEAR)',
    32 => '死亡之握类 (MECHANIC_GRIP)',
    64 => '缠绕 (MECHANIC_ROOT)',
    128 => '攻击减速 (MECHANIC_SLOW_ATTACK)',
    256 => '沉默 (MECHANIC_SILENCE)',
    512 => '沉睡 (MECHANIC_SLEEP)',
    1024 => '诱捕 (MECHANIC_SNARE)',
    2048 => '昏迷 (MECHANIC_STUN)',
    4096 => '冰冻 (MECHANIC_FREEZE)',
    8192 => '击倒 (MECHANIC_KNOCKOUT)',
    16384 => '流血 (MECHANIC_BLEED)',
    32768 => '治疗/绷带 (MECHANIC_BANDAGE)',
    65536 => '变形 (MECHANIC_POLYMORPH)',
    131072 => '放逐 (MECHANIC_BANISH)',
    262144 => '护盾 (MECHANIC_SHIELD)',
    524288 => '束缚亡灵 (MECHANIC_SHACKLE)',
    1048576 => '召唤坐骑 (MECHANIC_MOUNT)',
    2097152 => '感染 (MECHANIC_INFECTED)',
    4194304 => '驱邪类 (MECHANIC_TURN)',
    8388608 => '恐惧术/惊骇 (MECHANIC_HORROR)',
    16777216 => '无敌 (MECHANIC_INVULNERABILITY)',
    33554432 => '打断 (MECHANIC_INTERRUPT)',
    67108864 => '眩晕 (MECHANIC_DAZE)',
    134217728 => '制造物品 (MECHANIC_DISCOVERY)',
    268435456 => '免疫护盾 (MECHANIC_IMMUNE_SHIELD)',
    536870912 => '闷棍 (MECHANIC_SAPPED)',
    1073741824 => '激怒 (MECHANIC_ENRAGED)'
];

const SPELL_SCHOOL_IMMUNE_MASK = [
    1 => '物理 (SPELL_SCHOOL_NORMAL)',
    2 => '神圣 (SPELL_SCHOOL_HOLY)',
    4 => '火焰 (SPELL_SCHOOL_FIRE)',
    8 => '自然 (SPELL_SCHOOL_NATURE)',
    16 => '冰霜 (SPELL_SCHOOL_FROST)',
    32 => '暗影 (SPELL_SCHOOL_SHADOW)',
    64 => '奥术 (SPELL_SCHOOL_ARCANE)'
];

// --- NEW Helper functions for dropdowns ---

/**
 * Returns creature family options.
 */
function get_creature_families(): array {
    // Based on https://www.azerothcore.org/wiki/creature_family
    return [
        0 => '0 - 无 (None)',
        1 => '1 - 狼 (Wolf)',
        2 => '2 - 猫科 (Cat)',
        3 => '3 - 蜘蛛 (Spider)',
        8 => '8 - 螃蟹 (Crab)',
        9 => '9 - 猩猩 (Gorilla)',
        11 => '11 - 迅猛龙',
        12 => '12 - 高地行者 (Tallstrider)',
        15 => '15 - 地狱猎犬',
        16 => '16 - 虚空行者 (Voidwalker)',
        17 => '17 - 魅魔 (Succubus)',
        19 => '19 - 末日守卫 (Doomguard)',
        20 => '20 - 蝎子 (Scorpid)',
        21 => '21 - 海龟',
        23 => '23 - 小鬼 (Imp)',
        24 => '24 - 蝙蝠 (Bat)',
        25 => '25 - 土狼 (Hyena)',
        26 => '26 - 猫头鹰',
        27 => '27 - 风蛇 (Wind Serpent)',
        28 => '28 - 遥控装置',
        29 => '29 - 恶魔卫士',
        30 => '30 - 龙鹰 (Dragonhawk)',
        31 => '31 - 掠食者 (Ravager)',
        32 => '32 - 迁跃追踪者',
        33 => '33 - 孢子蝠 (Sporebat)',
        34 => '34 - 虚空鳐 (Nether Ray)',
        35 => '35 - 蛇 (Serpent)',
        37 => '37 - 蛾子 (Moth)',
        38 => '38 - 奇美拉 (Chimaera)',
        39 => '39 - 魔暴龙 (Devilsaur)',
        40 => '40 - 食尸鬼 (Ghoul)',
        41 => '41 - 异种虫 (Silithid)',
        42 => '42 - 蠕虫 (Worm)',
        43 => '43 - 犀牛 (Rhino)',
        44 => '44 - 胡蜂',
        45 => '45 - 熔岩犬',
        46 => '46 - 灵魂兽 (Spirit Beast)',
    ];
}

/**
 * Returns unit class options.
 */
function get_unit_classes(): array {
    // Based on https://www.azerothcore.org/wiki/creature_template#unit_class
    return [
        1 => '1 - 战士 (仅生命值)',
        2 => '2 - 圣骑士 (生命值/法力值)',
        4 => '4 - 潜行者 (仅生命值)',
        8 => '8 - 法师 (生命值/法力值)',
    ];
}

/**
 * Returns trainer type options.
 */
function get_trainer_types(): array {
    return [
        0 => '0 - 职业 (Class)',
        1 => '1 - 专业坐骑 (Mounts)',
        2 => '2 - 专业 (Profession)',
        3 => '3 - 宠物 (Pet)',
    ];
}

/**
 * Returns damage school options.
 */
function get_damage_schools(): array {
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

/**
 * Returns movement type options.
 */
function get_movement_types(): array {
    return [
        0 => '0 - 站立 (Idle/Ground)',
        1 => '1 - 随机 (Random)',
        2 => '2 - 路径 (Waypoint)',
    ];
}

// --- NEW: Function to get AI names ---
/**
 * Returns AI Name options for dropdown.
 */
function get_ai_names(): array {
    return [
        '' => '无生物AI (NullCreatureAI - Default)',
        'TriggerAI' => '触发AI (TriggerAI)',
        'AggressorAI' => '侵略者AI (AggressorAI)',
        'ReactorAI' => '反应者AI (ReactorAI)',
        'PassiveAI' => '被动AI (PassiveAI)',
        'CritterAI' => '小动物AI (CritterAI)',
        'GuardAI' => '卫兵AI (GuardAI)',
        'PetAI' => '宠物AI (PetAI)',
        'TotemAI' => '图腾AI (TotemAI)',
        'CombatAI' => '战斗AI (CombatAI)',
        'ArcherAI' => '射手AI (ArcherAI)',
        'TurretAI' => '炮台AI (TurretAI)',
        'VehicleAI' => '载具AI (VehicleAI)',
        'SmartAI' => '智能AI (SmartAI)',
        // Add other known AI names if needed
    ];
}

// --- NEW: Function to get creature models from creature_template_model ---
/**
 * Retrieves associated models for a creature from the creature_template_model table.
 *
 * @param PDO $pdo_W World database PDO connection.
 * @param int $creature_id The creature template entry ID.
 * @return array An array of associative arrays representing the models, ordered by Idx.
 */
function get_creature_models(PDO $pdo_W, int $creature_id): array
{
    if ($creature_id <= 0) {
        return [];
    }
    try {
        $sql = "SELECT `Idx`, `CreatureDisplayID`, `DisplayScale`, `Probability`, `VerifiedBuild`
                FROM `creature_template_model`
                WHERE `CreatureID` = :creature_id
                ORDER BY `Idx` ASC";
        $stmt = $pdo_W->prepare($sql);
        $stmt->bindParam(':creature_id', $creature_id, PDO::PARAM_INT);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $results ?: []; // Return results or an empty array
    } catch (PDOException $e) {
        error_log("Get Creature Models Error: " . $e->getMessage() . " CreatureID: " . $creature_id);
        return []; // Return empty array on error
    }
}

// --- NEW: Function to handle adding a creature model entry ---
/**
 * Adds a new model entry for a creature in creature_template_model.
 *
 * @param PDO $pdo_W World DB connection.
 * @param int $creature_id Creature Entry ID.
 * @param int $display_id CreatureDisplayInfo ID.
 * @param float $scale Model scale.
 * @param float $probability Model probability (0-1).
 * @param ?int $verified_build Verified build version (can be null).
 * @return array Response array ['success' => bool, 'message' => string, 'new_idx' => ?int].
 */
function add_creature_model_entry(PDO $pdo_W, int $creature_id, int $display_id, float $scale, float $probability, ?int $verified_build = null): array
{
    if ($creature_id <= 0 || $display_id <= 0 || $scale <= 0 || $probability < 0 || $probability > 1) {
        return ['success' => false, 'message' => '提供的模型数据无效。'];
    }

    if ($verified_build !== null && $verified_build <= 0) { // Allow null, but disallow <= 0 if provided
        return ['success' => false, 'message' => '提供的验证版本 (VerifiedBuild) 无效。'];
    }

    try {
        $pdo_W->beginTransaction();

        // Determine the next available Idx
        $sql_max_idx = "SELECT MAX(`Idx`) FROM `creature_template_model` WHERE `CreatureID` = :creature_id";
        $stmt_max = $pdo_W->prepare($sql_max_idx);
        $stmt_max->bindParam(':creature_id', $creature_id, PDO::PARAM_INT);
        $stmt_max->execute();
        $max_idx = $stmt_max->fetchColumn();
        $new_idx = ($max_idx === false || $max_idx === null) ? 0 : (int)$max_idx + 1;

        if ($new_idx > 3) {
             $pdo_W->rollBack();
             return ['success' => false, 'message' => '错误：模型索引(Idx)已达到最大值(3)。'];
        }

        $sql_insert = "INSERT INTO `creature_template_model` (`CreatureID`, `Idx`, `CreatureDisplayID`, `DisplayScale`, `Probability`, `VerifiedBuild`) 
                       VALUES (:creature_id, :idx, :display_id, :scale, :probability, :verified_build)";
        $stmt_insert = $pdo_W->prepare($sql_insert);
        $stmt_insert->bindParam(':creature_id', $creature_id, PDO::PARAM_INT);
        $stmt_insert->bindParam(':idx', $new_idx, PDO::PARAM_INT);
        $stmt_insert->bindParam(':display_id', $display_id, PDO::PARAM_INT);
        $stmt_insert->bindParam(':scale', $scale);
        $stmt_insert->bindParam(':probability', $probability);
        $stmt_insert->bindParam(':verified_build', $verified_build, $verified_build === null ? PDO::PARAM_NULL : PDO::PARAM_INT);

        $success = $stmt_insert->execute();

        if ($success) {
            // --- Re-calculate probabilities for this CreatureID --- (Optional but recommended)
             $sql_sum = "SELECT SUM(`Probability`) FROM `creature_template_model` WHERE `CreatureID` = :creature_id";
             $stmt_sum = $pdo_W->prepare($sql_sum);
             $stmt_sum->bindParam(':creature_id', $creature_id, PDO::PARAM_INT);
             $stmt_sum->execute();
             $current_total_prob = (float)$stmt_sum->fetchColumn();
 
             if ($current_total_prob > 0 && abs($current_total_prob - 1.0) > 0.001) { // Check if adjustment needed
                 $sql_get_all = "SELECT `Idx`, `Probability` FROM `creature_template_model` WHERE `CreatureID` = :creature_id";
                 $stmt_get = $pdo_W->prepare($sql_get_all);
                 $stmt_get->bindParam(':creature_id', $creature_id, PDO::PARAM_INT);
                 $stmt_get->execute();
                 $models_to_adjust = $stmt_get->fetchAll(PDO::FETCH_ASSOC);
 
                 $sql_update_prob = "UPDATE `creature_template_model` SET `Probability` = :prob WHERE `CreatureID` = :cid AND `Idx` = :idx";
                 $stmt_update = $pdo_W->prepare($sql_update_prob);
 
                 foreach ($models_to_adjust as $model) {
                     $adjusted_prob = $model['Probability'] / $current_total_prob;
                     $stmt_update->bindParam(':prob', $adjusted_prob);
                     $stmt_update->bindParam(':cid', $creature_id, PDO::PARAM_INT);
                     $stmt_update->bindParam(':idx', $model['Idx'], PDO::PARAM_INT);
                     $stmt_update->execute();
                 }
                 $adjust_msg = " (概率已自动调整为总和 1)";
             } else {
                 $adjust_msg = "";
             }
            // --- End Probability Adjustment ---

            $pdo_W->commit();
            return ['success' => true, 'message' => "模型条目 (Idx: {$new_idx}) 添加成功。" . $adjust_msg, 'new_idx' => $new_idx];
        } else {
            $pdo_W->rollBack();
            $errorInfo = $stmt_insert->errorInfo();
            return ['success' => false, 'message' => '数据库插入失败: ' . ($errorInfo[2] ?? '未知错误')];
        }
    } catch (PDOException $e) {
        if ($pdo_W->inTransaction()) {
            $pdo_W->rollBack();
        }
        error_log("Add Creature Model PDO Error for Creature {$creature_id}: " . $e->getMessage());
        // Check for duplicate entry specifically
        if ($e->getCode() == 23000) { // SQLSTATE for integrity constraint violation
             return ['success' => false, 'message' => '添加模型失败：可能已存在具有相同索引(Idx)的模型。' . $e->getMessage()];
        } else {
             return ['success' => false, 'message' => '添加模型数据库错误: ' . $e->getMessage()];
        }
    }
}

// --- NEW: Function to handle editing a creature model entry ---
/**
 * Updates an existing model entry for a creature in creature_template_model.
 *
 * @param PDO $pdo_W World DB connection.
 * @param int $creature_id Creature Entry ID.
 * @param int $idx Model Index (0-3).
 * @param int $display_id CreatureDisplayInfo ID.
 * @param float $scale Model scale.
 * @param float $probability Model probability (0-1).
 * @param ?int $verified_build Verified build version (can be null).
 * @return array Response array ['success' => bool, 'message' => string].
 */
function edit_creature_model_entry(PDO $pdo_W, int $creature_id, int $idx, int $display_id, float $scale, float $probability, ?int $verified_build = null): array
{
    if ($creature_id <= 0 || $idx < 0 || $idx > 3 || $display_id <= 0 || $scale <= 0 || $probability < 0 || $probability > 1) {
        return ['success' => false, 'message' => '提供的模型数据无效。'];
    }

    if ($verified_build !== null && $verified_build <= 0) { // Allow null, but disallow <= 0 if provided
        return ['success' => false, 'message' => '提供的验证版本 (VerifiedBuild) 无效。'];
    }

    try {
        $pdo_W->beginTransaction();
        $sql = "UPDATE `creature_template_model` 
               SET `CreatureDisplayID` = :display_id, `DisplayScale` = :scale, `Probability` = :probability, `VerifiedBuild` = :verified_build 
               WHERE `CreatureID` = :creature_id AND `Idx` = :idx";
        $stmt = $pdo_W->prepare($sql);
        $stmt->bindParam(':display_id', $display_id, PDO::PARAM_INT);
        $stmt->bindParam(':scale', $scale);
        $stmt->bindParam(':probability', $probability);
        $stmt->bindParam(':verified_build', $verified_build, $verified_build === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindParam(':creature_id', $creature_id, PDO::PARAM_INT);
        $stmt->bindParam(':idx', $idx, PDO::PARAM_INT);

        $success = $stmt->execute();

        if ($success) {
            $affected_rows = $stmt->rowCount();
            if ($affected_rows > 0) {
                // --- Re-calculate probabilities --- (Similar to add)
                $sql_sum = "SELECT SUM(`Probability`) FROM `creature_template_model` WHERE `CreatureID` = :creature_id";
                $stmt_sum = $pdo_W->prepare($sql_sum);
                $stmt_sum->bindParam(':creature_id', $creature_id, PDO::PARAM_INT);
                $stmt_sum->execute();
                $current_total_prob = (float)$stmt_sum->fetchColumn();
    
                if ($current_total_prob > 0 && abs($current_total_prob - 1.0) > 0.001) {
                    $sql_get_all = "SELECT `Idx`, `Probability` FROM `creature_template_model` WHERE `CreatureID` = :creature_id";
                    $stmt_get = $pdo_W->prepare($sql_get_all);
                    $stmt_get->bindParam(':creature_id', $creature_id, PDO::PARAM_INT);
                    $stmt_get->execute();
                    $models_to_adjust = $stmt_get->fetchAll(PDO::FETCH_ASSOC);
    
                    $sql_update_prob = "UPDATE `creature_template_model` SET `Probability` = :prob WHERE `CreatureID` = :cid AND `Idx` = :idx";
                    $stmt_update = $pdo_W->prepare($sql_update_prob);
    
                    foreach ($models_to_adjust as $model) {
                        $adjusted_prob = $model['Probability'] / $current_total_prob;
                        $stmt_update->bindParam(':prob', $adjusted_prob);
                        $stmt_update->bindParam(':cid', $creature_id, PDO::PARAM_INT);
                        $stmt_update->bindParam(':idx', $model['Idx'], PDO::PARAM_INT);
                        $stmt_update->execute();
                    }
                    $adjust_msg = " (概率已自动调整为总和 1)";
                } else {
                    $adjust_msg = "";
                }
                // --- End Probability Adjustment ---
                $pdo_W->commit();
                 return ['success' => true, 'message' => "模型条目 (Idx: {$idx}) 更新成功。" . $adjust_msg];
            } else {
                $pdo_W->rollBack(); // Nothing was updated, maybe ID/Idx combination didn't exist
                return ['success' => false, 'message' => '更新失败：未找到匹配的模型条目或数据未更改。'];
            }
        } else {
            $pdo_W->rollBack();
            $errorInfo = $stmt->errorInfo();
            return ['success' => false, 'message' => '数据库更新失败: ' . ($errorInfo[2] ?? '未知错误')];
        }
    } catch (PDOException $e) {
         if ($pdo_W->inTransaction()) {
            $pdo_W->rollBack();
         }
        error_log("Edit Creature Model PDO Error for Creature {$creature_id}, Idx {$idx}: " . $e->getMessage());
        return ['success' => false, 'message' => '编辑模型数据库错误: ' . $e->getMessage()];
    }
}

// --- NEW: Function to handle deleting a creature model entry ---
/**
 * Deletes a model entry for a creature from creature_template_model.
 *
 * @param PDO $pdo_W World DB connection.
 * @param int $creature_id Creature Entry ID.
 * @param int $idx Model Index (0-3).
 * @return array Response array ['success' => bool, 'message' => string].
 */
function delete_creature_model_entry(PDO $pdo_W, int $creature_id, int $idx): array
{
     if ($creature_id <= 0 || $idx < 0 || $idx > 3) {
        return ['success' => false, 'message' => '提供的模型索引(Idx)无效。'];
    }

    try {
        $pdo_W->beginTransaction();
        $sql = "DELETE FROM `creature_template_model` WHERE `CreatureID` = :creature_id AND `Idx` = :idx";
        $stmt = $pdo_W->prepare($sql);
        $stmt->bindParam(':creature_id', $creature_id, PDO::PARAM_INT);
        $stmt->bindParam(':idx', $idx, PDO::PARAM_INT);

        $success = $stmt->execute();
        $affected_rows = $stmt->rowCount();

        if ($success && $affected_rows > 0) {
            // --- Re-calculate probabilities after delete --- (Optional but recommended)
            $sql_count = "SELECT COUNT(*) FROM `creature_template_model` WHERE `CreatureID` = :creature_id";
            $stmt_count = $pdo_W->prepare($sql_count);
            $stmt_count->bindParam(':creature_id', $creature_id, PDO::PARAM_INT);
            $stmt_count->execute();
            $remaining_count = (int)$stmt_count->fetchColumn();

            $adjust_msg = "";
            if ($remaining_count > 0) {
                $sql_sum = "SELECT SUM(`Probability`) FROM `creature_template_model` WHERE `CreatureID` = :creature_id";
                $stmt_sum = $pdo_W->prepare($sql_sum);
                $stmt_sum->bindParam(':creature_id', $creature_id, PDO::PARAM_INT);
                $stmt_sum->execute();
                $current_total_prob = (float)$stmt_sum->fetchColumn();
    
                if ($current_total_prob > 0 && abs($current_total_prob - 1.0) > 0.001) {
                    $sql_get_all = "SELECT `Idx`, `Probability` FROM `creature_template_model` WHERE `CreatureID` = :creature_id";
                    $stmt_get = $pdo_W->prepare($sql_get_all);
                    $stmt_get->bindParam(':creature_id', $creature_id, PDO::PARAM_INT);
                    $stmt_get->execute();
                    $models_to_adjust = $stmt_get->fetchAll(PDO::FETCH_ASSOC);
    
                    $sql_update_prob = "UPDATE `creature_template_model` SET `Probability` = :prob WHERE `CreatureID` = :cid AND `Idx` = :idx";
                    $stmt_update = $pdo_W->prepare($sql_update_prob);
    
                    foreach ($models_to_adjust as $model) {
                        $adjusted_prob = $model['Probability'] / $current_total_prob;
                        $stmt_update->bindParam(':prob', $adjusted_prob);
                        $stmt_update->bindParam(':cid', $creature_id, PDO::PARAM_INT);
                        $stmt_update->bindParam(':idx', $model['Idx'], PDO::PARAM_INT);
                        $stmt_update->execute();
                    }
                    $adjust_msg = " (剩余模型概率已自动调整为总和 1)";
                } 
            }
            // --- End Probability Adjustment ---
            $pdo_W->commit();
            return ['success' => true, 'message' => "模型条目 (Idx: {$idx}) 删除成功。" . $adjust_msg];
        } elseif ($success && $affected_rows === 0) {
            $pdo_W->rollBack(); // No rows were deleted
            return ['success' => false, 'message' => '删除失败：未找到要删除的模型条目。'];
        } else {
            $pdo_W->rollBack();
            $errorInfo = $stmt->errorInfo();
            return ['success' => false, 'message' => '数据库删除失败: ' . ($errorInfo[2] ?? '未知错误')];
        }
    } catch (PDOException $e) {
        if ($pdo_W->inTransaction()) {
            $pdo_W->rollBack();
        }
        error_log("Delete Creature Model PDO Error for Creature {$creature_id}, Idx {$idx}: " . $e->getMessage());
        return ['success' => false, 'message' => '删除模型数据库错误: ' . $e->getMessage()];
    }
}

?> 