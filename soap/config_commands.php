<?php
// 常用 GM 命令配置文件
// 参考: https://www.azerothcore.org/wiki/gm-commands
// 注意: 部分命令可能需要指定目标或特定权限，SOAP接口能否执行取决于其具体实现

return [
    [
        'category' => '服务器',
        'command' => 'server info',
        'description' => '显示服务器信息 (版本, 在线人数等)'
    ],
    [
        'category' => '服务器',
        'command' => 'announce YourMessageHere',
        'description' => '向所有在线玩家发送游戏内公告'
    ],
    [
        'category' => '查询',
        'command' => 'lookup player account PlayerName',
        'description' => '根据玩家名称查找其账户名'
    ],
    [
        'category' => '查询',
        'command' => 'lookup item ItemNameOrID',
        'description' => '根据名称或 ID 查找物品'
    ],
    [
        'category' => '查询',
        'command' => 'lookup spell SpellNameOrID',
        'description' => '根据名称或 ID 查找法术'
    ],
    [
        'category' => '查询',
        'command' => 'lookup creature CreatureNameOrID',
        'description' => '根据名称或 ID 查找生物'
    ],
    [
        'category' => '物品',
        'command' => 'additem PlayerNameOrGUID ItemID/ItemLink [Count]',
        'description' => '给指定玩家添加物品 (Count 可选)'
    ],
    [
        'category' => '账号',
        'command' => 'ban account AccountName Duration Reason',
        'description' => '封禁账户 (Duration: 1m, 1h, 1d, 0=永久)'
    ],
    [
        'category' => '账号',
        'command' => 'unban account AccountName',
        'description' => '解封账户'
    ],
    [
        'category' => '玩家',
        'command' => 'kick PlayerName [Reason]',
        'description' => '将玩家踢下线 (Reason 可选)'
    ],
    [
        'category' => '玩家',
        'command' => 'mute PlayerName Duration [Reason]',
        'description' => '禁言玩家账户 (Duration: 1m, 1h, 1d)'
    ],
    [
        'category' => '玩家',
        'command' => 'unmute PlayerName',
        'description' => '解除玩家禁言'
    ],
]; 