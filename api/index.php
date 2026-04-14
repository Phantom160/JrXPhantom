<?php              
/**
 * Phantom Bot - Universal Bypass Engine v3.1.2
 * Features: cURL Engine, Stats Fix, Folder Toggle, Strict Access, Support & Info
 */

// --- CONFIGURATION ---              
$botToken = "8792901084:AAF2lfTyJ27EZQQJfkoJDHv4aI66cxsebCM";              
$adminId  = 1361987726;               
$channelId = "@phantomxhub";               
$jsonFile = "freescripts.json";               
$statsFile = "freestats.json";              
$settingsFile = "freesettings.json";

// --- INITIALIZE DATA ---              
if(!file_exists($jsonFile)) file_put_contents($jsonFile, json_encode([]));              
if(!file_exists($statsFile)) file_put_contents($statsFile, json_encode(['users' => [], 'total_bypass' => 0, 'banned' => []]));              
if(!file_exists($settingsFile)) file_put_contents($settingsFile, json_encode(['maintenance' => 'off']));

$update = json_decode(file_get_contents("php://input"), true);              
if (!$update) exit;              
              
$message = $update['message'] ?? null;              
$callback = $update['callback_query'] ?? null;              
$chatId = $message['chat']['id'] ?? $callback['from']['id'] ?? null;

if (!$chatId) exit;

// --- CORE FUNCTIONS ---              
function bot($method, $params = []) {              
    global $botToken;              
    $url = "https://api.telegram.org/bot$botToken/$method";              
    $ch = curl_init($url);              
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);              
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);              
    $res = curl_exec($ch);              
    curl_close($ch);
    return json_decode($res, true);              
}

function fetchApi($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) PhantomBot/3.1');
    $res = curl_exec($ch);
    curl_close($ch);
    return $res ?: "⚠️ Error: API failed to respond.";
}

function isMember($userId) {              
    global $channelId;              
    $res = bot("getChatMember", ['chat_id' => $channelId, 'user_id' => $userId]);              
    $status = $res['result']['status'] ?? '';              
    return in_array($status, ['creator', 'administrator', 'member']);              
}

function updateStats($userId, $isBypass = false) {              
    global $statsFile;              
    $stats = json_decode(file_get_contents($statsFile), true);              
    if(!isset($stats['users'])) $stats['users'] = [];
    if(!in_array($userId, $stats['users'])) $stats['users'][] = $userId;              
    if($isBypass === true) $stats['total_bypass'] = (int)($stats['total_bypass'] ?? 0) + 1;              
    file_put_contents($statsFile, json_encode($stats, JSON_PRETTY_PRINT));              
}              
              
function buildGrid($items, $prefix = "", $backBtn = "🔙 Back") {              
    $btns = []; $row = [];              
    foreach ($items as $item) {              
        $row[] = ['text' => $prefix . $item];              
        if (count($row) == 3) { $btns[] = $row; $row = []; }              
    }              
    if (!empty($row)) $btns[] = $row;              
    $btns[] = [['text' => $backBtn]];              
    return $btns;              
}              
              
function sendHome($chatId) {              
    $kb = [              
        'keyboard' => [[['text' => "🚀 Scripts"]],[['text' => "👤 Profile"], ['text' => "📊 Stats"]],[['text' => "ℹ️ Bot Info"], ['text' => "💬 Support"]]],               
        'resize_keyboard' => true              
    ];              
    bot("sendMessage", ['chat_id' => $chatId, 'text' => "👋 <b>Welcome to Phantom Bot</b>", 'parse_mode' => 'HTML', 'reply_markup' => json_encode($kb)]);              
}

function isFolderActive($appName) {
    $scripts = json_decode(file_get_contents($GLOBALS['jsonFile']), true) ?: [];
    foreach($scripts as $s) {
        if($s['app_name'] == $appName && ($s['heading'] ?? '') == 'INIT_FOLDER') {
            return ($s['status'] ?? 'active') == 'active';
        }
    }
    return true;
}

function sendFolderMenu($chatId, $appName) {
    if(!isFolderActive($appName)) {
        bot("sendMessage", ['chat_id' => $chatId, 'text' => "⚠️ <b>Access Denied</b>\n\nThis folder is currently paused by admin.", 'parse_mode' => 'HTML']);
        return;
    }
    $scripts = json_decode(file_get_contents($GLOBALS['jsonFile']), true) ?: [];              
    $items = [];              
    foreach ($scripts as $s) {              
        if ($s['app_name'] == $appName && ($s['status'] ?? '') == 'active' && ($s['heading'] ?? '') !== 'INIT_FOLDER') {              
            $items[] = $s['heading'];              
        }              
    }              
    $btns = buildGrid($items, "🔗 ", "🚀 Scripts");              
    bot("sendMessage", ['chat_id' => $chatId, 'text' => "📂 <b>Available in $appName:</b>", 'parse_mode' => 'HTML', 'reply_markup' => json_encode(['keyboard' => $btns, 'resize_keyboard' => true])]);
}

function sendAdminManage($chatId, $msgId = null) {
    global $settingsFile;
    $settings = json_decode(file_get_contents($settingsFile), true);
    $maintText = ($settings['maintenance'] == 'on') ? "🔴 Maint: ON" : "🟢 Maint: OFF";
    
    $btns = [
        [['text' => "📁 Manage Folders", 'callback_data' => "adm_fld"]],
        [['text' => "🚫 Ban User", 'callback_data' => "adm_ban"], ['text' => "✅ Unban User", 'callback_data' => "adm_unban"]],
        [['text' => $maintText, 'callback_data' => "toggle_maint"]],
        [['text' => "🔙 Back To Home", 'callback_data' => "adm_home"]]
    ];
    
    $payload = ['chat_id' => $chatId, 'text' => "🛠 <b>Admin Panel v3.1.2</b>", 'parse_mode' => 'HTML', 'reply_markup' => json_encode(['inline_keyboard' => $btns])];
    if ($msgId) { $payload['message_id'] = $msgId; bot("editMessageText", $payload); } 
    else { bot("sendMessage", $payload); }
}

// --- SECURITY & STATS ---
updateStats($chatId);
$stats = json_decode(file_get_contents($statsFile), true);
$settings = json_decode(file_get_contents($settingsFile), true);

if ($chatId != $adminId) {
    if (in_array($chatId, ($stats['banned'] ?? []))) {
        bot("sendMessage", ['chat_id' => $chatId, 'text' => "🚫 <b>You are banned.</b>", 'parse_mode' => 'HTML']); exit;
    }
    if (($settings['maintenance'] ?? 'off') == 'on') {
        bot("sendMessage", ['chat_id' => $chatId, 'text' => "🛠 <b>Bot is under maintenance.</b>", 'parse_mode' => 'HTML']); exit;
    }
    if (!isMember($chatId)) {
        if ($callback && $callback['data'] == "verify_join") {
            if (isMember($chatId)) { sendHome($chatId); } 
            else { bot("answerCallbackQuery", ['callback_query_id' => $callback['id'], 'text' => "❌ Join first!", 'show_alert' => true]); }
        } else {
            $link = "https://t.me/" . str_replace('@', '', $channelId);
            $kb = ['inline_keyboard' => [[['text' => "📢 Join Channel", 'url' => $link]], [['text' => "✅ Verified!", 'callback_data' => "verify_join"]]]];
            bot("sendMessage", ['chat_id' => $chatId, 'text' => "⚠️ <b>Join our channel to use the bot.</b>", 'parse_mode' => 'HTML', 'reply_markup' => json_encode($kb)]);
        }
        exit;
    }
}

// --- CALLBACK LOGIC ---              
if ($callback) {              
    $data = $callback['data'];              
    $msgId = $callback['message']['message_id'];              
    if ($chatId == $adminId) {              
        if ($data == "adm_home") { sendHome($chatId); bot("deleteMessage", ['chat_id' => $chatId, 'message_id' => $msgId]); }
        if ($data == "adm_manage") { sendAdminManage($chatId, $msgId); }
        if ($data == "toggle_maint") {
            $settings['maintenance'] = ($settings['maintenance'] == 'on') ? 'off' : 'on';
            file_put_contents($settingsFile, json_encode($settings));
            sendAdminManage($chatId, $msgId);
        }
        if ($data == "adm_fld") {
            $scripts = json_decode(file_get_contents($jsonFile), true) ?: [];
            $categories = array_unique(array_column($scripts, 'app_name'));              
            $btns = []; $row = [];              
            foreach ($categories as $cat) { if(!empty($cat)) { $row[] = ['text' => "📂 " . $cat, 'callback_data' => "adm_cat_" . urlencode($cat)]; if (count($row) == 2) { $btns[] = $row; $row = []; } } }              
            if (!empty($row)) $btns[] = $row;              
            $btns[] = [['text' => "➕ Create New Folder", 'callback_data' => "adm_new_app"]];              
            $btns[] = [['text' => "🔙 Back", 'callback_data' => "adm_manage"]];
            bot("editMessageText", ['chat_id' => $chatId, 'message_id' => $msgId, 'text' => "📂 <b>Folder Management:</b>", 'parse_mode' => 'HTML', 'reply_markup' => json_encode(['inline_keyboard' => $btns])]);
        }
        if (strpos($data, "adm_cat_") === 0) {              
            $catName = urldecode(str_replace("adm_cat_", "", $data));              
            $scripts = json_decode(file_get_contents($jsonFile), true) ?: [];
            $folderStatus = 'active';
            foreach($scripts as $s) if($s['app_name'] == $catName && ($s['heading'] ?? '') == 'INIT_FOLDER') $folderStatus = $s['status'] ?? 'active';
            $statusTxt = ($folderStatus == 'active') ? "🟢 Status: ON" : "🔴 Status: OFF";
            $btns = [[['text' => $statusTxt, 'callback_data' => "tgfld_" . urlencode($catName)]], [['text' => "➕ Add Script", 'callback_data' => "addto_" . urlencode($catName)], ['text' => "🗑 Delete Folder", 'callback_data' => "flddel_" . urlencode($catName)]]];              
            $row = [];              
            foreach ($scripts as $id => $s) if ($s['app_name'] == $catName && ($s['heading'] ?? '') !== 'INIT_FOLDER') { $row[] = ['text' => "🗑 " . $s['heading'], 'callback_data' => "adm_del_$id"]; if (count($row) == 2) { $btns[] = $row; $row = []; } }
            if (!empty($row)) $btns[] = $row;              
            $btns[] = [['text' => "🔙 Back", 'callback_data' => "adm_fld"]];              
            bot("editMessageText", ['chat_id' => $chatId, 'message_id' => $msgId, 'text' => "<b>Folder: $catName</b>", 'parse_mode' => 'HTML', 'reply_markup' => json_encode(['inline_keyboard' => $btns])]);              
        }
        if (strpos($data, "tgfld_") === 0) {
            $catName = urldecode(str_replace("tgfld_", "", $data));
            $scripts = json_decode(file_get_contents($jsonFile), true) ?: [];
            foreach($scripts as $id => $s) if($s['app_name'] == $catName && ($s['heading'] ?? '') == 'INIT_FOLDER') $scripts[$id]['status'] = ($s['status'] == 'active') ? 'disabled' : 'active';
            file_put_contents($jsonFile, json_encode($scripts, JSON_PRETTY_PRINT));
            bot("answerCallbackQuery", ['callback_query_id' => $callback['id'], 'text' => "Toggled!"]);
            $callback['data'] = "adm_cat_" . urlencode($catName); bot("deleteMessage", ['chat_id' => $chatId, 'message_id' => $msgId]);
            $scripts = json_decode(file_get_contents($jsonFile), true) ?: [];
            $folderStatus = 'active'; foreach($scripts as $s) if($s['app_name'] == $catName && ($s['heading'] ?? '') == 'INIT_FOLDER') $folderStatus = $s['status'] ?? 'active';
            $statusTxt = ($folderStatus == 'active') ? "🟢 Status: ON" : "🔴 Status: OFF";
            $btns = [[['text' => $statusTxt, 'callback_data' => "tgfld_" . urlencode($catName)]], [['text' => "➕ Add Script", 'callback_data' => "addto_" . urlencode($catName)], ['text' => "🗑 Delete Folder", 'callback_data' => "flddel_" . urlencode($catName)]]];              
            $row = []; foreach ($scripts as $id => $s) if ($s['app_name'] == $catName && ($s['heading'] ?? '') !== 'INIT_FOLDER') { $row[] = ['text' => "🗑 " . $s['heading'], 'callback_data' => "adm_del_$id"]; if (count($row) == 2) { $btns[] = $row; $row = []; } }
            if (!empty($row)) $btns[] = $row; $btns[] = [['text' => "🔙 Back", 'callback_data' => "adm_fld"]];
            bot("sendMessage", ['chat_id' => $chatId, 'text' => "<b>Folder: $catName</b>", 'parse_mode' => 'HTML', 'reply_markup' => json_encode(['inline_keyboard' => $btns])]);
        }
        if ($data == "adm_new_app") { bot("sendMessage", ['chat_id' => $chatId, 'text' => "Enter the <b>New App Name</b>:", 'parse_mode' => 'HTML', 'reply_markup' => json_encode(['force_reply' => true])]); }              
        if (strpos($data, "addto_") === 0) { bot("sendMessage", ['chat_id' => $chatId, 'text' => "Adding to <b>".urldecode(str_replace("addto_", "", $data))."</b>\nFormat: <code>Title|Placeholder|ParamName|API_URL</code>", 'parse_mode' => 'HTML', 'reply_markup' => json_encode(['force_reply' => true])]); }              
    }              
}              
              
// --- MESSAGE LOGIC ---              
if ($message) {              
    $text = $message['text'] ?? '';              
    if ($text == "/admin" && $chatId == $adminId) { sendAdminManage($chatId); exit; }              
    if (isset($message['reply_to_message'])) {              
        $replyTxt = $message['reply_to_message']['text'];              
        if (strpos($replyTxt, "New App Name") !== false) {              
            $scripts = json_decode(file_get_contents($jsonFile), true) ?: [];              
            $scripts["fld_".time()] = ['app_name' => $text, 'heading' => 'INIT_FOLDER', 'status' => 'active'];              
            file_put_contents($jsonFile, json_encode($scripts, JSON_PRETTY_PRINT));              
            bot("sendMessage", ['chat_id' => $chatId, 'text' => "✅ Created!"]); sendAdminManage($chatId); exit;              
        }              
        if (strpos($replyTxt, "Adding to") !== false) {              
            preg_match('/Adding to <b>(.*?)<\/b>/', $replyTxt, $m); $appName = $m[1];
            $p = explode('|', $text);              
            if (count($p) >= 4) {              
                $scripts = json_decode(file_get_contents($jsonFile), true) ?: [];              
                $raw_api = trim($p[3]); if (strpos($raw_api, '{param}') === false) $raw_api .= (strpos($raw_api, '?') !== false ? "&" : "?") . "url={param}";
                $scripts[bin2hex(random_bytes(4))] = ['app_name' => $appName, 'heading' => trim($p[0]), 'placeholder' => trim($p[1]), 'param' => trim($p[2]), 'pb_api' => $raw_api, 'status' => 'active'];              
                file_put_contents($jsonFile, json_encode($scripts, JSON_PRETTY_PRINT));              
                bot("sendMessage", ['chat_id' => $chatId, 'text' => "✅ Added!"]); sendAdminManage($chatId);
            } exit;              
        }              
        $scripts = json_decode(file_get_contents($jsonFile), true) ?: [];              
        foreach ($scripts as $s) {              
            if (($s['status'] ?? '') == 'active' && strpos($replyTxt, ($s['heading'] ?? '')) !== false) {              
                if(!isFolderActive($s['app_name'])) { bot("sendMessage", ['chat_id' => $chatId, 'text' => "⚠️ Folder is paused.", 'parse_mode' => 'HTML']); exit; }
                $val = (filter_var($text, FILTER_VALIDATE_URL)) ? (parse_url($text, PHP_URL_QUERY) ? (function($u, $p){ parse_str(parse_url($u, PHP_URL_QUERY), $q); return $q[$p] ?? $u; })($text, $s['param']) : $text) : $text;
                $apiUrl = str_replace("{param}", urlencode($val), $s['pb_api']);              
                bot("sendChatAction", ['chat_id' => $chatId, 'action' => 'typing']);              
                $response = fetchApi($apiUrl); updateStats($chatId, true);
                bot("sendMessage", ['chat_id' => $chatId, 'text' => "✅ <b>Result:</b>\n\n<code>" . htmlspecialchars(substr($response, 0, 3800)) . "</code>", 'parse_mode' => 'HTML']);              
                sendFolderMenu($chatId, $s['app_name']); exit;              
            }              
        }              
    }              
              
    if ($text == "/start" || $text == "🔙 Back") { sendHome($chatId); }              
    elseif ($text == "🚀 Scripts") {              
        $scripts = json_decode(file_get_contents($jsonFile), true) ?: []; $activeFolders = [];
        foreach($scripts as $s) if(($s['heading'] ?? '') == 'INIT_FOLDER' && ($s['status'] ?? 'active') == 'active') $activeFolders[] = $s['app_name'];
        bot("sendMessage", ['chat_id' => $chatId, 'text' => "<b>Select App Folder:</b>", 'parse_mode' => 'HTML', 'reply_markup' => json_encode(['keyboard' => buildGrid($activeFolders, "📂 "), 'resize_keyboard' => true])]);              
    }              
    elseif (strpos($text, "📂 ") === 0) { sendFolderMenu($chatId, str_replace("📂 ", "", $text)); }              
    elseif (strpos($text, "🔗 ") === 0) {              
        $name = str_replace("🔗 ", "", $text);              
        foreach (json_decode(file_get_contents($jsonFile), true) ?: [] as $s) if (($s['heading'] ?? '') == $name) {
            if(!isFolderActive($s['app_name'])) { bot("sendMessage", ['chat_id' => $chatId, 'text' => "⚠️ Folder paused.", 'parse_mode' => 'HTML']); exit; }
            bot("sendMessage", ['chat_id' => $chatId, 'text' => "🚀 <b>{$s['heading']}</b>\nDemo: <code>{$s['placeholder']}</code>", 'parse_mode' => 'HTML', 'reply_markup' => json_encode(['force_reply' => true])]);              
        }
    }              
    elseif ($text == "📊 Stats") {              
        $scripts = json_decode(file_get_contents($jsonFile), true) ?: []; $st = json_decode(file_get_contents($statsFile), true);
        $liveCount = 0; foreach($scripts as $s) if(($s['status']??'')=='active' && ($s['heading']??'') !== 'INIT_FOLDER') $liveCount++;              
        bot("sendMessage", ['chat_id' => $chatId, 'text' => "📊 <b>Live Statistics</b>\n\n👥 Total Users: " . count($st['users'] ?? []) . "\n📂 Live Scripts: $liveCount\n⚡ Total Bypasses: " . ($st['total_bypass'] ?? 0), 'parse_mode' => 'HTML']);              
    }              
    elseif ($text == "👤 Profile") { bot("sendMessage", ['chat_id' => $chatId, 'text' => "👤 <b>Profile</b>\n\n🆔 ID: <code>$chatId</code>\n🛡 Plan: <b>Free Access</b>", 'parse_mode' => 'HTML']); }              
    elseif ($text == "ℹ️ Bot Info") { bot("sendMessage", ['chat_id' => $chatId, 'text' => "ℹ️ <b>Phantom Bot Info</b>\n\n📦 <b>Version:</b> 3.1.2\n⚡ <b>Engine:</b> cURL Universal", 'parse_mode' => 'HTML']); }              
    elseif ($text == "💬 Support") { 
        bot("sendMessage", ['chat_id' => $chatId, 'text' => "<b>Customer Support</b>\nContact the developer for help.", 'parse_mode' => 'HTML', 'reply_markup' => json_encode(['inline_keyboard' => [[['text' => "👨‍💻 Contact Admin", 'url' => "https://t.me/Error4040bot"]]]])]); 
    }              
}              
?>
