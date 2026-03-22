<?php
ob_start();
error_reporting(E_ALL);
date_default_timezone_set('Asia/Tashkent');
define("API_KEY", '8663180438:AAFKDMYpbmvVhqHFc2IMZre12rpaU03AbEw');  //token
$admin = 6365371142; //adminid

define("DB_SERVER", "autorack.proxy.rlwy.net");
define("DB_USERNAME", "root");
define("DB_PASSWORD", "SngtdKxJGJMafHfetMzLBszTQwMprNwi");
define("DB_NAME", "railway");
define("DB_PORT", 57444);
define('CHECKCARD_SHOP_ID', '647282');   // @CheckCardUz_bot dan olingan shop id
define('CHECKCARD_SHOP_KEY', '884UESPA3H'); // @CheckCardUz_bot dan olingan shop key
define('CHANNEL_TO_JOIN', '@Nitesms'); // Tolovlar kanali
define('SMM_API_KEY', '6ef936c5e8855be0182e2a0bf10434dd'); // locksmm.com dan olingan API key
define('SMM_API_URL', 'https://locksmm.com/api/v2');

$card_number = "5614683582279246";

$connect = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME, DB_PORT);
if (!$connect) {
    error_log("DB connection failed: " . mysqli_connect_error());
    exit("DB ga ulanishda xato yuz berdi!");
}
mysqli_set_charset($connect, "utf8mb4");

// ─── Faqat shu class o'zgartirildi: ProHamyonPay → CheckCardPay ─────────────
class CheckCardPay {
    private $shop_id;
    private $shop_key;

    public function __construct($shop_id, $shop_key) {
        $this->shop_id  = $shop_id;
        $this->shop_key = $shop_key;
    }

    // GET so'rov — checkcard.uz rasmiy namunasi asosida
    public function create_checkout($amount) {
        $api_url = "https://checkcard.uz/api?method=create&shop_id=" . urlencode($this->shop_id) . "&shop_key=" . urlencode($this->shop_key) . "&amount=" . intval($amount) . "&payurl=true";
        $response = @file_get_contents($api_url);
        if ($response === false) {
            error_log("CheckCard create failed, amount=" . $amount);
            return false;
        }
        return $response;
    }

    public function check_payment($order_code) {
        $api_url = "https://checkcard.uz/api?method=check&order=" . urlencode($order_code);
        $response = @file_get_contents($api_url);
        if ($response === false) {
            error_log("CheckCard check payment failed for order: " . $order_code);
            return false;
        }
        return $response;
    }

    public function cancel_payment($order_code) {
        $api_url = "https://checkcard.uz/api?method=cancel&order=" . urlencode($order_code);
        @file_get_contents($api_url);
    }
}

$CheckCardPay = new CheckCardPay(CHECKCARD_SHOP_ID, CHECKCARD_SHOP_KEY);

function bot($method, $datas = []) {
    $url = "https://api.telegram.org/bot" . API_KEY . "/" . $method;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $datas);
    $res = curl_exec($ch);
    if ($res === false) {
        error_log("Telegram API error: " . curl_error($ch));
        curl_close($ch);
        return false;
    }
    curl_close($ch);
    $decoded = json_decode($res, true);
    return $decoded;
}

function sendMessage($chat_id, $text, $reply_markup = null) {
    $data = ['chat_id' => $chat_id, 'text' => $text, 'parse_mode' => 'HTML', 'disable_web_page_preview' => true];
    if ($reply_markup) $data['reply_markup'] = $reply_markup;
    return bot('sendMessage', $data);
}

function sendAnimation($chat_id, $animation, $caption = null, $reply_markup = null, $parse_mode = "HTML") {
    $data = [
        'chat_id' => $chat_id,
        'animation' => $animation,
        'parse_mode' => $parse_mode
    ];
    if ($caption) $data['caption'] = $caption;
    if ($reply_markup) $data['reply_markup'] = $reply_markup;

    return bot('sendAnimation', $data);
}

function deleteMessage($chat_id, $message_id) {
    if (empty($chat_id) || empty($message_id)) return false;
    return bot('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);
}

function answerCallback($callback_query_id, $text = '', $show_alert = false) {
    if (empty($callback_query_id)) return false;
    return bot('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => $text, 'show_alert' => $show_alert]);
}

function editMessage($chat_id, $message_id, $text, $reply_markup = null) {
    $data = ['chat_id' => $chat_id, 'message_id' => $message_id, 'text' => $text, 'parse_mode' => 'HTML', 'disable_web_page_preview' => true];
    if ($reply_markup) $data['reply_markup'] = $reply_markup;
    return bot('editMessageText', $data);
}

mysqli_query($connect, "CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT NOT NULL,
    `step` TEXT,
    `date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

mysqli_query($connect, "CREATE TABLE IF NOT EXISTS `review` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT,
    `order_id` TEXT,
    `price` INT,
    `status` TEXT,
    `quantity` INT,
    `username` TEXT,
    `payment_method` VARCHAR(20) DEFAULT '',
    `date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

mysqli_query($connect, "CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT PRIMARY KEY,
    `logs` TEXT,
    `api_key` TEXT,
    `star_price` INT,
    `premium_1_month` INT,
    `premium_3_month` INT,
    `premium_6_month` INT,
    `premium_12_month` INT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

mysqli_query($connect, "CREATE TABLE IF NOT EXISTS `logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT,
    `price` INT,
    `status` TEXT,
    `quantity` INT,
    `username` TEXT,
    `date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

mysqli_query($connect, "CREATE TABLE IF NOT EXISTS `premium_orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT,
    `order_id` TEXT,
    `price` INT,
    `status` TEXT,
    `quantity` INT,
    `username` TEXT,
    `payment_method` VARCHAR(20) DEFAULT '',
    `date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ─── Balans jadvali ──────────────────────────────────────────────────────────
mysqli_query($connect, "CREATE TABLE IF NOT EXISTS `wallet` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT NOT NULL,
    `balance` BIGINT DEFAULT 0,
    `date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_wallet_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

mysqli_query($connect, "CREATE TABLE IF NOT EXISTS `top_up` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT NOT NULL,
    `order_code` TEXT,
    `amount` INT,
    `status` VARCHAR(20) DEFAULT 'unpaid',
    `date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ─── SMM jadvali ─────────────────────────────────────────────────────────────
mysqli_query($connect, "CREATE TABLE IF NOT EXISTS `smm_orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT NOT NULL,
    `smm_order_id` BIGINT,
    `service_id` INT,
    `service_name` TEXT,
    `link` TEXT,
    `quantity` INT,
    `price` INT,
    `status` VARCHAR(50) DEFAULT 'pending',
    `payment_order` TEXT,
    `payment_status` VARCHAR(20) DEFAULT 'unpaid',
    `date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ─── Referal va Game jadvallar ───────────────────────────────────────────────
mysqli_query($connect, "CREATE TABLE IF NOT EXISTS `referrals` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `inviter_id` BIGINT NOT NULL,
    `invited_id` BIGINT NOT NULL,
    `date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_invited (invited_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

mysqli_query($connect, "CREATE TABLE IF NOT EXISTS `game_bonus` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT NOT NULL,
    `stars` DECIMAL(4,2) DEFAULT 0,
    `last_play` DATE DEFAULT NULL,
    `total_stars` DECIMAL(8,2) DEFAULT 0,
    `withdrawn` TINYINT DEFAULT 0,
    UNIQUE KEY uq_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Majburiy obuna kanallari uchun ustun qo'shish (agar yo'q bo'lsa)
$cols_res = mysqli_query($connect, "SHOW COLUMNS FROM `settings`");
$existing_cols = [];
if ($cols_res) { while ($col = mysqli_fetch_assoc($cols_res)) { $existing_cols[] = $col["Field"]; } }
if (!in_array("channels", $existing_cols)) {
    mysqli_query($connect, "ALTER TABLE `settings` ADD COLUMN `channels` TEXT DEFAULT NULL");
}
if (!in_array("bot_active", $existing_cols)) {
    mysqli_query($connect, "ALTER TABLE `settings` ADD COLUMN `bot_active` TINYINT DEFAULT 1");
}
if (!in_array("smm_markup", $existing_cols)) {
    mysqli_query($connect, "ALTER TABLE `settings` ADD COLUMN `smm_markup` INT DEFAULT 20");
}
if (!in_array("smm_usd_rate", $existing_cols)) {
    mysqli_query($connect, "ALTER TABLE `settings` ADD COLUMN `smm_usd_rate` INT DEFAULT 12700");
}

$checkSettings = mysqli_query($connect, "SELECT COUNT(*) as total FROM settings");
$countRow = mysqli_fetch_assoc($checkSettings);
$count = intval($countRow['total'] ?? 0);
if ($count == 0) {
$stmt = mysqli_prepare($connect, "INSERT INTO settings(id, logs, api_key, star_price, premium_1_month, premium_3_month, premium_6_month, premium_12_month) VALUES (1, ?, ?, ?, ?, ?, ?, ?)");
$logs = CHANNEL_TO_JOIN;
$api_key_default = 'none';
$price_default = 240;
$premium_1 = 45000;
$premium_3 = 165000;
$premium_6 = 215000;
$premium_12 = 360000;
mysqli_stmt_bind_param($stmt, 'ssiiiii', $logs, $api_key_default, $price_default, $premium_1, $premium_3, $premium_6, $premium_12);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
}

function step_file($chat_id) { return __DIR__ . "/step/{$chat_id}.step"; }
function save_step($chat_id, $data) { if (!is_dir(__DIR__ . '/step')) mkdir(__DIR__ . '/step', 0755, true); file_put_contents(step_file($chat_id), json_encode($data, JSON_UNESCAPED_UNICODE)); }
function load_step($chat_id) { $f = step_file($chat_id); if (!file_exists($f)) return []; $c = json_decode(file_get_contents($f), true); return is_array($c) ? $c : []; }
function clear_step($chat_id) { $f = step_file($chat_id); if (file_exists($f)) unlink($f); }

function settings($connect) {
$res = mysqli_query($connect, "SELECT * FROM settings WHERE id = 1 LIMIT 1");
$row = mysqli_fetch_assoc($res);
if (!$row) {
return ['logs' => CHANNEL_TO_JOIN, 'api_key' => 'none', 'star_price' => 240, 'premium_1_month' => 45000, 'premium_3_month' => 165000, 'premium_6_month' => 215000, 'premium_12_month' => 360000, 'channels' => null, 'bot_active' => 1, 'smm_markup' => 20, 'smm_usd_rate' => 12700];
}
return $row;
}

// Majburiy kanallarga tekshirish (bir nechta kanal bo'lishi mumkin)
function check_all_channels($user_id, $connect) {
    $cfg = settings($connect);
    $channels_raw = trim($cfg['channels'] ?? '');
    // Asosiy kanal
    $all_channels = [CHANNEL_TO_JOIN];
    // Qo'shimcha kanallar (vergul bilan ajratilgan)
    if (!empty($channels_raw)) {
        $extra = array_filter(array_map('trim', explode(',', $channels_raw)));
        $all_channels = array_merge($all_channels, $extra);
    }
    $not_joined = [];
    foreach ($all_channels as $ch) {
        if (empty($ch)) continue;
        $resp = bot('getChatMember', ['chat_id' => $ch, 'user_id' => $user_id]);
        $status = $resp['result']['status'] ?? '';
        if (!in_array($status, ['member','administrator','creator'])) {
            $not_joined[] = $ch;
        }
    }
    return $not_joined; // bo'sh bo'lsa — hammaga obuna
}

$raw = file_get_contents('php://input');
$update = json_decode($raw, true) ?: [];
if (empty($update)) exit;
$message = $update['message'] ?? null;
$callback = $update['callback_query'] ?? null;

$text = $message['text'] ?? null;
$chat_id = $message['chat']['id'] ?? ($callback['message']['chat']['id'] ?? null);
$message_id = $message['message_id'] ?? ($callback['message']['message_id'] ?? null);
$from = $message['from'] ?? ($callback['from'] ?? []);
$from_id = $from['id'] ?? null;
$username = $from['username'] ?? null;
$callback_data = $callback['data'] ?? null;
$callback_id = $callback['id'] ?? null;

function user_is_member_of_channel($user_id) {
if (empty($user_id)) return false;
$resp = bot('getChatMember', ['chat_id' => CHANNEL_TO_JOIN, 'user_id' => $user_id]);
if (!$resp || empty($resp['ok'])) return false;
$status = $resp['result']['status'] ?? '';
return in_array($status, ['member','administrator','creator']);
}

function require_subscription_prompt($chat_id) {
$join_link = "https://t.me/" . ltrim(CHANNEL_TO_JOIN, '@');
$keyboard = json_encode(['inline_keyboard' => [
[['text' => "🔔 Obuna bo'lish", 'url' => $join_link]],
[['text' => "✅ Tekshirish", 'callback_data' => 'check_subscribe']]
]], JSON_UNESCAPED_UNICODE);
sendMessage($chat_id, "<b>❗ Majburiy obuna talab etiladi.</b>\nBotdan foydalanish uchun quyidagi kanalga obuna bo'ling:", $keyboard);
}

if (!empty($from_id) && $from_id != $admin) {
    // Bot active tekshirish
    $cfg_active = settings($connect);
    if (intval($cfg_active['bot_active'] ?? 1) == 0) {
        sendMessage($chat_id, "⚠️ <b>Bot vaqtincha to'xtatilgan. Tez orada qayta ishga tushadi!</b>");
        exit;
    }

    if ($callback_data === 'check_subscribe') {
        $not_joined = check_all_channels($from_id, $connect);
        if (empty($not_joined)) {
            answerCallback($callback_id, "✅ Siz barcha kanallarga obuna bo'lgansiz!", true);
        } else {
            answerCallback($callback_id, "❌ Hali ba'zi kanallarga obuna bo'lmadingiz.", true);
        }
        exit;
    }

    $not_joined = check_all_channels($from_id, $connect);
    if (!empty($not_joined)) {
        // Obuna bo'lmagan kanallar uchun tugmalar
        $sub_kb = [];
        foreach ($not_joined as $idx => $ch) {
            $ch_link = "https://t.me/" . ltrim($ch, '@');
            $sub_kb[] = [['text' => "🔔 " . $ch . " ga obuna bo'lish", 'url' => $ch_link]];
        }
        $sub_kb[] = [['text' => "✅ Tekshirish", 'callback_data' => 'check_subscribe']];
        sendMessage($chat_id, "<b>❗ Majburiy obuna talab etiladi.</b>
Botdan foydalanish uchun quyidagi kanallarga obuna bo'ling:", json_encode(['inline_keyboard' => $sub_kb], JSON_UNESCAPED_UNICODE));
        exit;
    }
}

if ($chat_id) {
$stmt = mysqli_prepare($connect, "SELECT id FROM users WHERE user_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 's', $chat_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_store_result($stmt);
$num = mysqli_stmt_num_rows($stmt);
mysqli_stmt_close($stmt);
if ($num == 0) {
$stmt2 = mysqli_prepare($connect, "INSERT INTO users (user_id) VALUES (?)");
mysqli_stmt_bind_param($stmt2, 's', $chat_id);
mysqli_stmt_execute($stmt2);
mysqli_stmt_close($stmt2);
}
}

function get_bot_username() {
    $res = bot('getMe', []);
    return $res['result']['username'] ?? '';
}

$menu = json_encode(['inline_keyboard' => [
[['text' => "⭐️ Stars sotib olish", 'callback_data' => "stars"], ['text' => "👑 Premium", 'callback_data' => "premium"]],
[['text' => "📱 SMM Panel", 'callback_data' => "smm_main"], ['text' => "🎮 Game Bar", 'callback_data' => "gamebar"]],
[['text' => "💳 Balans", 'callback_data' => "wallet_main"], ['text' => "👥 Do'stlarni taklif qilish", 'callback_data' => "referral"]],
]], JSON_UNESCAPED_UNICODE);

if ($text !== null && strpos($text, "/start") === 0) {
    // Referal tekshirish
    $parts_start = explode(" ", $text, 2);
    if (isset($parts_start[1]) && is_numeric($parts_start[1])) {
        $inviter_id = intval($parts_start[1]);
        if ($inviter_id != $chat_id) {
            // Foydalanuvchi avval ro'yxatdan o'tganmi?
            $chk_ref = mysqli_prepare($connect, "SELECT id FROM referrals WHERE invited_id=? LIMIT 1");
            mysqli_stmt_bind_param($chk_ref, 'i', $chat_id);
            mysqli_stmt_execute($chk_ref);
            mysqli_stmt_store_result($chk_ref);
            if (mysqli_stmt_num_rows($chk_ref) == 0) {
                $ins_ref = mysqli_prepare($connect, "INSERT IGNORE INTO referrals (inviter_id, invited_id) VALUES (?,?)");
                mysqli_stmt_bind_param($ins_ref, 'ii', $inviter_id, $chat_id);
                mysqli_stmt_execute($ins_ref);
                mysqli_stmt_close($ins_ref);
                // Taklif qiluvchiga xabar
                $ref_count_r = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) c FROM referrals WHERE inviter_id={$inviter_id}"));
                $ref_count = intval($ref_count_r['c']);
                sendMessage($inviter_id, "🎉 <b>Yangi do'stingiz botga qo'shildi!</b>

👥 Jami taklif qilganlar: <b>{$ref_count}/15</b>

✅ 15 ta do'st taklif qilsangiz, yig'gan starslaringizni yecha olasiz!");
            }
            mysqli_stmt_close($chk_ref);
        }
    }
    sendAnimation($chat_id, "https://t.me/PhotosForBots/146", "🎉 <b>Uz Give</b>ga xush kelibsiz!\n\n🎯 <b>Nima olasiz, tanlang:</b>", $menu, "HTML");
    clear_step($chat_id);
    exit;
}

if ($text === "/admin" && $from_id == $admin) {
$cfg_adm = settings($connect);
$bot_status = intval($cfg_adm['bot_active'] ?? 1) ? "✅ Aktiv" : "❌ To'xtatilgan";
$admin_menu = json_encode(['inline_keyboard' => [
[['text' => "📊 Statistika", 'callback_data' => "admin_stats"], ['text' => "💰 Narxlar", 'callback_data' => "admin_prices"]],
[['text' => "📝 Buyurtmalar", 'callback_data' => "admin_logs"], ['text' => "👥 Foydalanuvchilar", 'callback_data' => "admin_users"]],
[['text' => "📢 Majburiy obuna", 'callback_data' => "admin_channels"], ['text' => "📣 Xabar yuborish", 'callback_data' => "admin_broadcast"]],
[['text' => "🎮 Game Bar so'rovlar", 'callback_data' => "admin_game_requests"], ['text' => "🔗 Referal statistika", 'callback_data' => "admin_refs"]],
[['text' => "⚙️ Bot holati: {$bot_status}", 'callback_data' => "admin_toggle_bot"]],
]], JSON_UNESCAPED_UNICODE);
sendMessage($chat_id, "<b>🔧 Admin Panel</b>\n\nKerakli bo'limni tanlang:", $admin_menu);
exit;
}

// ─── Admin: Majburiy obuna kanallari ─────────────────────────────────────────
if ($callback_data === "admin_channels" && $from_id == $admin) {
    $cfg_ch = settings($connect);
    $channels_now = $cfg_ch['channels'] ?? '';
    $kb = [
        [['text' => "➕ Kanal qo'shish", 'callback_data' => "admin_ch_add"]],
        [['text' => "🗑 Kanallarni tozalash", 'callback_data' => "admin_ch_clear"]],
        [['text' => "🔙 Orqaga", 'callback_data' => "admin_back"]],
    ];
    $ch_list = empty($channels_now) ? "Qo'shimcha kanal yo'q" : $channels_now;
    editMessage($chat_id, $message_id, "<b>📢 Majburiy obuna kanallari</b>

🔒 Asosiy kanal: <code>" . CHANNEL_TO_JOIN . "</code>
➕ Qo'shimcha kanallar: <code>{$ch_list}</code>

💡 Kanal qo'shish uchun @username formatida yuboring", json_encode(['inline_keyboard' => $kb], JSON_UNESCAPED_UNICODE));
    exit;
}

if ($callback_data === "admin_ch_add" && $from_id == $admin) {
    save_step($chat_id, ['step' => 'admin_add_channel']);
    sendMessage($chat_id, "📢 Qo'shmoqchi bo'lgan kanal username ini yuboring:

Masalan: <code>@MyChannel</code>");
    exit;
}

if ($callback_data === "admin_ch_clear" && $from_id == $admin) {
    mysqli_query($connect, "UPDATE settings SET channels=NULL WHERE id=1");
    answerCallback($callback_id, "✅ Qo'shimcha kanallar tozalandi!", true);
    // Refresh
    $kb = [
        [['text' => "➕ Kanal qo'shish", 'callback_data' => "admin_ch_add"]],
        [['text' => "🔙 Orqaga", 'callback_data' => "admin_back"]],
    ];
    editMessage($chat_id, $message_id, "<b>📢 Majburiy obuna kanallari</b>

🔒 Asosiy kanal: <code>" . CHANNEL_TO_JOIN . "</code>
➕ Qo'shimcha kanallar: Yo'q", json_encode(['inline_keyboard' => $kb], JSON_UNESCAPED_UNICODE));
    exit;
}

// ─── Admin: Xabar yuborish (Broadcast) ───────────────────────────────────────
if ($callback_data === "admin_broadcast" && $from_id == $admin) {
    save_step($chat_id, ['step' => 'admin_broadcast']);
    $kb = [[['text' => "❌ Bekor qilish", 'callback_data' => "admin_back"]]];
    editMessage($chat_id, $message_id, "<b>📣 Barcha foydalanuvchilarga xabar yuborish</b>

Xabar matnini yuboring:", json_encode(['inline_keyboard' => $kb], JSON_UNESCAPED_UNICODE));
    exit;
}

// ─── Admin: Bot yoqish/o'chirish ──────────────────────────────────────────────
if ($callback_data === "admin_toggle_bot" && $from_id == $admin) {
    $cfg_tgl = settings($connect);
    $current = intval($cfg_tgl['bot_active'] ?? 1);
    $new_val = $current ? 0 : 1;
    mysqli_query($connect, "UPDATE settings SET bot_active={$new_val} WHERE id=1");
    $status_txt = $new_val ? "✅ Bot yoqildi!" : "❌ Bot to'xtatildi!";
    answerCallback($callback_id, $status_txt, true);
    // Refresh admin panel
    $bot_status_tgl = $new_val ? "✅ Aktiv" : "❌ To'xtatilgan";
    $adm_kb = json_encode(['inline_keyboard' => [
        [['text' => "📊 Statistika", 'callback_data' => "admin_stats"], ['text' => "💰 Narxlar", 'callback_data' => "admin_prices"]],
        [['text' => "📝 Buyurtmalar", 'callback_data' => "admin_logs"], ['text' => "👥 Foydalanuvchilar", 'callback_data' => "admin_users"]],
        [['text' => "📢 Majburiy obuna", 'callback_data' => "admin_channels"], ['text' => "📣 Xabar yuborish", 'callback_data' => "admin_broadcast"]],
        [['text' => "🎮 Game Bar so'rovlar", 'callback_data' => "admin_game_requests"], ['text' => "🔗 Referal statistika", 'callback_data' => "admin_refs"]],
        [['text' => "⚙️ Bot holati: {$bot_status_tgl}", 'callback_data' => "admin_toggle_bot"]],
    ]], JSON_UNESCAPED_UNICODE);
    editMessage($chat_id, $message_id, "<b>🔧 Admin Panel</b>

Kerakli bo'limni tanlang:", $adm_kb);
    exit;
}

// ─── Admin: Game Bar so'rovlari ───────────────────────────────────────────────
if ($callback_data === "admin_game_requests" && $from_id == $admin) {
    $game_pending = mysqli_query($connect, "SELECT g.*, u.user_id FROM game_bonus g LEFT JOIN users u ON g.user_id=u.user_id WHERE g.withdrawn=1 ORDER BY g.id DESC LIMIT 15");
    $gtxt = "<b>🎮 Game Bar — Yechish so'rovlari</b>

";
    $gcount = 0;
    while ($gr = mysqli_fetch_assoc($game_pending)) {
        $gcount++;
        $gtxt .= "• ID: <code>{$gr['user_id']}</code> | ⭐ {$gr['total_stars']} stars
";
    }
    if ($gcount == 0) $gtxt .= "Hozircha so'rov yo'q.";
    $kb = [[['text' => "🔙 Orqaga", 'callback_data' => "admin_back"]]];
    editMessage($chat_id, $message_id, $gtxt, json_encode(['inline_keyboard' => $kb], JSON_UNESCAPED_UNICODE));
    exit;
}

// ─── Admin: Referal statistika ────────────────────────────────────────────────
if ($callback_data === "admin_refs" && $from_id == $admin) {
    $total_refs = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) c FROM referrals"))['c'];
    $top_refs = mysqli_query($connect, "SELECT inviter_id, COUNT(*) cnt FROM referrals GROUP BY inviter_id ORDER BY cnt DESC LIMIT 10");
    $rtxt = "<b>🔗 Referal statistika</b>

📊 Jami referallar: <b>{$total_refs}</b>

<b>🏆 Top taklif qiluvchilar:</b>
";
    while ($rr = mysqli_fetch_assoc($top_refs)) {
        $rtxt .= "• <code>{$rr['inviter_id']}</code> — {$rr['cnt']} ta
";
    }
    $kb = [[['text' => "🔙 Orqaga", 'callback_data' => "admin_back"]]];
    editMessage($chat_id, $message_id, $rtxt, json_encode(['inline_keyboard' => $kb], JSON_UNESCAPED_UNICODE));
    exit;
}

// ─── Admin: Statistika (kengaytirilgan) ──────────────────────────────────────
if ($callback_data === "admin_stats" && $from_id == $admin) {
$stats_query = "SELECT 
(SELECT COUNT(*) FROM users) as total_users,
(SELECT COUNT(*) FROM review WHERE status = 'completed') as completed_orders,
(SELECT COUNT(*) FROM premium_orders WHERE status = 'completed') as completed_premium,
(SELECT SUM(price) FROM review WHERE status = 'completed') as total_revenue_stars,
(SELECT SUM(price) FROM premium_orders WHERE status = 'completed') as total_revenue_premium,
(SELECT COUNT(*) FROM referrals) as total_refs,
(SELECT COUNT(*) FROM game_bonus WHERE withdrawn=1) as game_withdrawals,
(SELECT COUNT(*) FROM review WHERE status='unpaid') as pending_stars,
(SELECT COUNT(*) FROM premium_orders WHERE status='unpaid') as pending_premium";
    
$stats_result = mysqli_query($connect, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);   
$total_revenue = ($stats['total_revenue_stars'] ?? 0) + ($stats['total_revenue_premium'] ?? 0);
$today_orders = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) c FROM review WHERE DATE(date)=CURDATE() AND status='completed'"))['c'];
$today_revenue = mysqli_fetch_assoc(mysqli_query($connect, "SELECT IFNULL(SUM(price),0) s FROM review WHERE DATE(date)=CURDATE() AND status='completed'"))['s'];
$reply = json_encode(['inline_keyboard' => [
[['text' => "🔙 Orqaga", 'callback_data' => "admin_back"]]
]], JSON_UNESCAPED_UNICODE);
$txt = "<b>📊 Statistika</b>

";
$txt .= "👥 <b>Jami foydalanuvchilar:</b> " . ($stats['total_users'] ?? 0) . "
";
$txt .= "🔗 <b>Jami referallar:</b> " . ($stats['total_refs'] ?? 0) . "

";
$txt .= "⭐ <b>Stars buyurtmalar (bajarilgan):</b> " . ($stats['completed_orders'] ?? 0) . "
";
$txt .= "👑 <b>Premium buyurtmalar (bajarilgan):</b> " . ($stats['completed_premium'] ?? 0) . "
";
$txt .= "⏳ <b>Kutilayotgan Stars:</b> " . ($stats['pending_stars'] ?? 0) . "
";
$txt .= "⏳ <b>Kutilayotgan Premium:</b> " . ($stats['pending_premium'] ?? 0) . "

";
$txt .= "💰 <b>Jami daromad:</b> " . number_format($total_revenue) . " so'm
";
$txt .= "📅 <b>Bugungi buyurtmalar:</b> {$today_orders} ta | " . number_format($today_revenue) . " so'm

";
$txt .= "🎮 <b>Game Bar yechish so'rovlari:</b> " . ($stats['game_withdrawals'] ?? 0) . "
";
$txt .= "📆 <b>Sana:</b> " . date('d.m.Y H:i');
editMessage($chat_id, $message_id, $txt, $reply);
exit;
}

if ($callback_data === "admin_prices" && $from_id == $admin) {
$settings = settings($connect);
$smm_cfg_p = cfg($connect);
$smm_markup_now = intval($smm_cfg_p['smm_markup'] ?? 20);
$smm_rate_now   = intval($smm_cfg_p['smm_usd_rate'] ?? 12700);
$reply = json_encode(['inline_keyboard' => [
[['text' => "⭐ Stars narxi: {$settings['star_price']} so'm", 'callback_data' => "edit_star_price"]],
[['text' => "👑 1 oy: {$settings['premium_1_month']} so'm", 'callback_data' => "edit_premium_1"]],
[['text' => "👑 3 oy: {$settings['premium_3_month']} so'm", 'callback_data' => "edit_premium_3"]],
[['text' => "👑 6 oy: {$settings['premium_6_month']} so'm", 'callback_data' => "edit_premium_6"]],
[['text' => "👑 12 oy: {$settings['premium_12_month']} so'm", 'callback_data' => "edit_premium_12"]],
[['text' => "📱 SMM Markup: {$smm_markup_now}%", 'callback_data' => "edit_smm_markup"]],
[['text' => "💱 USD kurs: {$smm_rate_now} so'm", 'callback_data' => "edit_smm_rate"]],
[['text' => "🔙 Orqaga", 'callback_data' => "admin_back"]]
]], JSON_UNESCAPED_UNICODE);
editMessage($chat_id, $message_id, "<b>💰 Narxlarni o'zgartirish</b>\n\nO'zgartirmoqchi bo'lgan narxni tanlang:", $reply);
exit;
}

if ($callback_data === "admin_logs" && $from_id == $admin) {
$recent_orders = mysqli_query($connect, "SELECT * FROM review ORDER BY date DESC LIMIT 10");
$recent_premium = mysqli_query($connect, "SELECT * FROM premium_orders ORDER BY date DESC LIMIT 10");   
$log_text = "<b>📝 So'nggi buyurtmalar</b>\n\n";
$log_text .= "<b>⭐ Stars buyurtmalar:</b>\n";
while ($order = mysqli_fetch_assoc($recent_orders)) {
$log_text .= "• {$order['quantity']} stars - {$order['username']} - {$order['status']} - " . date('d.m.Y H:i', strtotime($order['date'])) . "\n";
}
    
$log_text .= "\n<b>👑 Premium buyurtmalar:</b>\n";
while ($order = mysqli_fetch_assoc($recent_premium)) {
$log_text .= "• {$order['quantity']} oy - {$order['username']} - {$order['status']} - " . date('d.m.Y H:i', strtotime($order['date'])) . "\n";
}
    
$reply = json_encode(['inline_keyboard' => [
[['text' => "🔙 Orqaga", 'callback_data' => "admin_back"]]
]], JSON_UNESCAPED_UNICODE);
editMessage($chat_id, $message_id, $log_text, $reply);
exit;
}

if ($callback_data === "admin_users" && $from_id == $admin) {
$users_query = "SELECT COUNT(*) as total FROM users";
$users_result = mysqli_query($connect, $users_query);
$users_count = mysqli_fetch_assoc($users_result)['total'];  
$recent_users = mysqli_query($connect, "SELECT user_id, date FROM users ORDER BY date DESC LIMIT 10");
$users_text = "<b>👥 Foydalanuvchilar</b>\n\n";
$users_text .= "<b>Jami foydalanuvchilar:</b> {$users_count}\n\n";
$users_text .= "<b>So'nggi ro'yxatdan o'tganlar:</b>\n";  
while ($user = mysqli_fetch_assoc($recent_users)) {
$users_text .= "• ID: {$user['user_id']} - " . date('d.m.Y H:i', strtotime($user['date'])) . "\n";
}
    
$reply = json_encode(['inline_keyboard' => [
[['text' => "🔙 Orqaga", 'callback_data' => "admin_back"]]
]], JSON_UNESCAPED_UNICODE);
editMessage($chat_id, $message_id, $users_text, $reply);
exit;
}

if ($callback_data === "admin_back" && $from_id == $admin) {
$cfg_adm2 = settings($connect);
$bot_status2 = intval($cfg_adm2['bot_active'] ?? 1) ? "✅ Aktiv" : "❌ To'xtatilgan";
$admin_menu = json_encode(['inline_keyboard' => [
[['text' => "📊 Statistika", 'callback_data' => "admin_stats"], ['text' => "💰 Narxlar", 'callback_data' => "admin_prices"]],
[['text' => "📝 Buyurtmalar", 'callback_data' => "admin_logs"], ['text' => "👥 Foydalanuvchilar", 'callback_data' => "admin_users"]],
[['text' => "📢 Majburiy obuna", 'callback_data' => "admin_channels"], ['text' => "📣 Xabar yuborish", 'callback_data' => "admin_broadcast"]],
[['text' => "🎮 Game Bar so'rovlar", 'callback_data' => "admin_game_requests"], ['text' => "🔗 Referal statistika", 'callback_data' => "admin_refs"]],
[['text' => "⚙️ Bot holati: {$bot_status2}", 'callback_data' => "admin_toggle_bot"]],
]], JSON_UNESCAPED_UNICODE);
editMessage($chat_id, $message_id, "<b>🔧 Admin Panel</b>\n\nKerakli bo'limni tanlang:", $admin_menu);
exit;
}

if ($callback_data === "edit_star_price" && $from_id == $admin) {
save_step($chat_id, ['step' => 'edit_star_price']);
sendMessage($chat_id, "⭐ Stars narxini kiriting (so'm):");
exit;
}

if ($callback_data === "edit_premium_1" && $from_id == $admin) {
save_step($chat_id, ['step' => 'edit_premium_1']);
sendMessage($chat_id, "👑 Premium 1 oy narxini kiriting (so'm):");
exit;
}

if ($callback_data === "edit_premium_3" && $from_id == $admin) {
save_step($chat_id, ['step' => 'edit_premium_3']);
sendMessage($chat_id, "👑 Premium 3 oy narxini kiriting (so'm):");
exit;
}

if ($callback_data === "edit_premium_6" && $from_id == $admin) {
save_step($chat_id, ['step' => 'edit_premium_6']);
sendMessage($chat_id, "👑 Premium 6 oy narxini kiriting (so'm):");
exit;
}

if ($callback_data === "edit_premium_12" && $from_id == $admin) {
save_step($chat_id, ['step' => 'edit_premium_12']);
sendMessage($chat_id, "👑 Premium 12 oy narxini kiriting (so'm):");
exit;
}

if ($callback_data === "edit_smm_markup" && $from_id == $admin) {
save_step($chat_id, ['step' => 'edit_smm_markup']);
$cur = intval(cfg($connect)['smm_markup'] ?? 20);
sendMessage($chat_id, "📱 <b>SMM Panel markup foizi</b>

Hozirgi: <b>{$cur}%</b>

Yangi foiz kiriting (masalan: 20 = +20% narx qo'shiladi):");
exit;
}

if ($callback_data === "edit_smm_rate" && $from_id == $admin) {
save_step($chat_id, ['step' => 'edit_smm_rate']);
$cur_rate = intval(cfg($connect)['smm_usd_rate'] ?? 12700);
sendMessage($chat_id, "💱 <b>USD → So'm kursi</b>

Hozirgi: <b>{$cur_rate} so'm</b>

Yangi kursni kiriting (masalan: 12700):");
exit;
}

if ($callback_data === "premium") {
deleteMessage($chat_id, $message_id);
$settings = settings($connect);
$reply = json_encode(['inline_keyboard' => [
[['text' => "1 oy 👑 - {$settings['premium_1_month']} so'm", 'callback_data' => "premium_1"], ['text' => "3 oy 👑 - {$settings['premium_3_month']} so'm", 'callback_data' => "premium_3"]],
[['text' => "6 oy 👑 - {$settings['premium_6_month']} so'm", 'callback_data' => "premium_6"], ['text' => "12 oy 👑 - {$settings['premium_12_month']} so'm", 'callback_data' => "premium_12"]],
[['text' => "🔙 Orqaga", 'callback_data' => "menu"]]
]], JSON_UNESCAPED_UNICODE);
sendMessage($chat_id, "<b>👑 Premium obuna\n\n📅 Obuna muddatini tanlang:</b>", $reply);
save_step($chat_id, ['step' => 'premium_amount']);
exit;
}

if ($callback_data === "menu") {
deleteMessage($chat_id, $message_id);
sendAnimation($chat_id, "https://t.me/PhotosForBots/146", "🎉 <b>Uz Give</b>ga xush kelibsiz!\n\n🎯 <b>Nima olasiz, tanlang:</b>", $menu, "HTML");
clear_step($chat_id);
exit;
}

// ─── Wallet helper funksiyalar ───────────────────────────────────────────────
function get_balance($chat_id, $connect) {
    $r = mysqli_fetch_assoc(mysqli_query($connect, "SELECT balance FROM wallet WHERE user_id=" . intval($chat_id) . " LIMIT 1"));
    return $r ? intval($r['balance']) : 0;
}

function add_balance($chat_id, $amount, $connect) {
    $uid = intval($chat_id);
    $amt = intval($amount);
    mysqli_query($connect, "INSERT INTO wallet (user_id, balance) VALUES ({$uid}, {$amt})
        ON DUPLICATE KEY UPDATE balance = balance + {$amt}");
}

function deduct_balance($chat_id, $amount, $connect) {
    $uid = intval($chat_id);
    $amt = intval($amount);
    mysqli_query($connect, "UPDATE wallet SET balance = balance - {$amt} WHERE user_id={$uid} AND balance >= {$amt}");
    return mysqli_affected_rows($connect) > 0;
}

// ─── SMM API Helper ──────────────────────────────────────────────────────────
function smm_api($params) {
    $post_data = array_merge(['key' => SMM_API_KEY], $params);
    $_post = [];
    foreach ($post_data as $k => $v) {
        $_post[] = $k . '=' . urlencode($v);
    }
    $ch = curl_init(SMM_API_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, implode('&', $_post));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result ? json_decode($result, true) : null;
}

// SMM kategoriyalari (xizmat ID → kategoriya)
function smm_categories() {
    return [
        "⭐ Telegram Stars" => [
            ["id" => 108, "name" => "⭐ Stars (Profil uchun) ⚡ Tezkor", "min" => 50,  "max" => 1000000],
            ["id" => 89,  "name" => "⭐ Stars (Post uchun) ⚡ Tezkor",   "min" => 5,   "max" => 10000],
        ],
        "👑 Telegram Premium Obunachi" => [
            ["id" => 458, "name" => "🌟 Premium Obunachi (5 Kunlik) 🔥",  "min" => 100, "max" => 10000],
            ["id" => 459, "name" => "🌟 Premium Obunachi (15 Kunlik) 🔥", "min" => 500, "max" => 10000],
            ["id" => 460, "name" => "🌟 Premium Obunachi (20 Kunlik) 🔥", "min" => 500, "max" => 10000],
            ["id" => 461, "name" => "🌟 Premium Obunachi (30 Kunlik) 🔥", "min" => 500, "max" => 10000],
            ["id" => 462, "name" => "🌟 Premium Obunachi (40 Kunlik) 🔥", "min" => 500, "max" => 10000],
            ["id" => 463, "name" => "🌟 Premium Obunachi (60 Kunlik) 🔥", "min" => 500, "max" => 10000],
            ["id" => 464, "name" => "🌟 Premium Obunachi (90 Kunlik) 🔥", "min" => 500, "max" => 10000],
        ],
        "👥 Telegram Obunachilar" => [
            ["id" => 22,  "name" => "👫 TG Obunachi (30 Kun kafolat)",    "min" => 10,  "max" => 1000000],
            ["id" => 23,  "name" => "👫 TG Obunachi (60 Kun kafolat)",    "min" => 10,  "max" => 1000000],
            ["id" => 24,  "name" => "👫 TG Obunachi (90 Kun kafolat)",    "min" => 10,  "max" => 1000000],
            ["id" => 26,  "name" => "👫 TG Obunachi (365 Kun kafolat)",   "min" => 10,  "max" => 1000000],
            ["id" => 97,  "name" => "👤 TG Obunachilar (30 Kunlik) 🔥",  "min" => 500, "max" => 200000],
            ["id" => 99,  "name" => "👤 TG Obunachilar (90 Kunlik) 🔥",  "min" => 100, "max" => 50000],
            ["id" => 141, "name" => "🇺🇿 Ozbek Obunachilar (30 Kun)",     "min" => 100, "max" => 15000],
            ["id" => 143, "name" => "🇺🇿 Ozbek Obunachilar (90 Kun)",     "min" => 10,  "max" => 15000],
        ],
        "👁 Telegram Korishlar" => [
            ["id" => 168, "name" => "👁 Post korishlar (Eng arzoni)",     "min" => 50,  "max" => 10000],
            ["id" => 170, "name" => "👁 Post korishlar (Tezkor Sifatli)", "min" => 10,  "max" => 50000000],
            ["id" => 171, "name" => "🇺🇿 Ozbekcha korishlar",             "min" => 100, "max" => 50000],
            ["id" => 177, "name" => "📖 Istoriya korishlar (Tezkor)",     "min" => 10,  "max" => 100000],
        ],
        "🔥 Telegram Reaksiyalar" => [
            ["id" => 197, "name" => "⚡ Tezkor reaksiyalar (👍❤️🔥😁🎉)", "min" => 50,  "max" => 1000000],
            ["id" => 221, "name" => "💰 Arzon reaksiyalar (👍🤩🎉🔥🥰)",  "min" => 10,  "max" => 200000],
            ["id" => 238, "name" => "🌟 Premium reaksiyalar (👍🤩🎉🔥❤️)","min" => 10,  "max" => 1000000],
            ["id" => 254, "name" => "🚀 Post ulashishlar ⚡",              "min" => 10,  "max" => 1000000],
        ],
        "📸 Instagram" => [
            ["id" => 1,   "name" => "👁 Reels korishlar ⚡",              "min" => 100, "max" => 3000000],
            ["id" => 10,  "name" => "👤 Obunachilar (Eng arzon)",         "min" => 100, "max" => 10000],
            ["id" => 19,  "name" => "👤 Obunachi (Bir Umr kafolatli)",    "min" => 50,  "max" => 1000000],
            ["id" => 275, "name" => "❤️ Like (Tezkor, Kafolatsiz)",       "min" => 10,  "max" => 10000000],
            ["id" => 276, "name" => "❤️ Like (30 Kun kafolatli) ⚡",     "min" => 50,  "max" => 1000000],
            ["id" => 321, "name" => "✉️ Random Kamentlar ⚡",             "min" => 10,  "max" => 10000],
        ],
        "▶️ YouTube" => [
            ["id" => 335, "name" => "⭐ Obunachilar (30 Kun kafolat)",    "min" => 100, "max" => 25000],
            ["id" => 340, "name" => "👤 Obunachilar (Kafolatsiz, Tezkor)","min" => 10,  "max" => 30000],
            ["id" => 349, "name" => "👁 Korishlar (Bir Umrlik, Tezkor)",  "min" => 100, "max" => 80000],
            ["id" => 344, "name" => "❤️ Like (7 Kun kafolatli)",          "min" => 10,  "max" => 50000],
        ],
        "🎵 TikTok" => [
            ["id" => 370, "name" => "👤 Obunachilar (30 Kun, Tezkor)",   "min" => 10,  "max" => 1000000],
            ["id" => 378, "name" => "👁 Korishlar (Tezkor)",              "min" => 100, "max" => 300000000],
            ["id" => 388, "name" => "❤️ Like (Kafolatsiz, Tezkor)",      "min" => 10,  "max" => 1000000],
            ["id" => 389, "name" => "❤️ Like (30 Kun kafolatli)",        "min" => 10,  "max" => 5000000],
        ],
    ];
}
// ─── Balans to'ldirish funksiyasi ────────────────────────────────────────────
function process_wallet_topup($chat_id, $connect, $amount) {
    global $CheckCardPay, $stars_card;
    if ($amount < 1000) {
        sendMessage($chat_id, "⚠️ Minimal to'ldirish miqdori <b>1,000 so'm</b>!");
        return;
    }
    $rand_num   = rand(1, 99);
    $pay_amount = $amount + $rand_num;

    $resp_w = $CheckCardPay->create_checkout($pay_amount);
    $data_w = $resp_w ? json_decode($resp_w, true) : null;

    if (!$data_w || ($data_w['status'] ?? '') === 'error') {
        $err = $data_w['message'] ?? 'Xatolik';
        if (stripos($err, 'pending') !== false) {
            sendMessage($chat_id, "⚠️ Bu miqdorda kutilayotgan to'lov mavjud.
💡 " . ($pay_amount + 500) . " so'm miqdorida urinib ko'ring.");
        } else {
            sendMessage($chat_id, "⚠️ To'lov yaratishda xatolik: {$err}");
        }
        return;
    }

    $order_code_w = $data_w['order'] ?? null;
    $insert_id_w  = $data_w['insert_id'] ?? $order_code_w;
    $pay_url_w    = $data_w['pay_url'] ?? null;

    if (!$order_code_w) {
        sendMessage($chat_id, "⚠️ To'lov yaratishda xatolik."); return;
    }

    $uid_w = intval($chat_id);
    $amt_w = intval($amount);
    $ins_w = mysqli_prepare($connect, "INSERT INTO top_up (user_id, order_code, amount, status) VALUES (?,?,?,'unpaid')");
    mysqli_stmt_bind_param($ins_w, 'isi', $uid_w, $order_code_w, $amt_w);
    mysqli_stmt_execute($ins_w);
    mysqli_stmt_close($ins_w);

    $kb_w = [];
    if ($pay_url_w) $kb_w[] = [['text' => "💳 To'lov sahifasini ochish", 'url' => $pay_url_w]];
    $kb_w[] = [['text' => "♻️ To'lovni tekshirish", 'callback_data' => "wallet_check={$order_code_w}"]];
    $kb_w[] = [['text' => "❌ Bekor qilish", 'callback_data' => "wallet_cancel={$order_code_w}"]];

    sendMessage($chat_id, "<b>💳 Balans to'ldirish</b>

📋 Buyurtma #{$insert_id_w}
💰 To'ldirish miqdori: <b>" . number_format($amount) . " so'm</b>

💳 Karta: <code>{$stars_card}</code>
💵 To'lov miqdori: <b><u>{$pay_amount} so'm</u></b>

⚠️ Aynan shu miqdorni o'tkazing!
To'lovdan so'ng '♻️ To'lovni tekshirish' tugmasini bosing.", json_encode(['inline_keyboard' => $kb_w], JSON_UNESCAPED_UNICODE));
}

// ─── SMM: Balansdan yoki karta orqali to'lash ─────────────────────────────────
if ($callback_data === "smm_pay_balance") {
    $st = load_step($chat_id);
    $som_price  = intval($st['smm_final_price'] ?? 0);
    $qty        = intval($st['smm_final_qty'] ?? 0);
    $link       = $st['smm_final_link'] ?? '';
    $svc_id     = intval($st['smm_service_id'] ?? 0);
    $svc_name   = $st['smm_service_name'] ?? '';
    if (!$som_price || !$qty) { answerCallback($callback_id, "❌ Buyurtma topilmadi!", true); exit; }

    $deducted = deduct_balance($chat_id, $som_price, $connect);
    if (!$deducted) {
        $bal = get_balance($chat_id, $connect);
        answerCallback($callback_id, "❌ Balans yetarli emas! (" . number_format($bal) . " so'm)", true); exit;
    }
    $new_bal = get_balance($chat_id, $connect);
    // DB
    $uid_s = intval($chat_id);
    $ins_s = mysqli_prepare($connect, "INSERT INTO smm_orders (user_id, service_id, service_name, link, quantity, price, status, payment_order, payment_status) VALUES (?,?,?,?,?,?,'pending','balance_pay','paid')");
    mysqli_stmt_bind_param($ins_s, 'iissii', $uid_s, $svc_id, $svc_name, $link, $qty, $som_price);
    mysqli_stmt_execute($ins_s); $rid_s = mysqli_insert_id($connect); mysqli_stmt_close($ins_s);
    // SMM API
    $smm_res = smm_api(['action' => 'add', 'service' => $svc_id, 'link' => $link, 'quantity' => $qty]);
    if ($smm_res && isset($smm_res['order'])) {
        $smm_oid = intval($smm_res['order']);
        mysqli_query($connect, "UPDATE smm_orders SET smm_order_id={$smm_oid}, status='Pending' WHERE id={$rid_s}");
        deleteMessage($chat_id, $message_id);
        sendMessage($chat_id, "✅ <b>Balansdan to'landi!</b>
💰 " . number_format($som_price) . " so'm
💳 Qolgan balans: <b>" . number_format($new_bal) . " so'm</b>

📋 SMM Buyurtma ID: <b>#{$smm_oid}</b>
⏳ Xizmat bajarilmoqda...", json_encode(['inline_keyboard' => [[['text' => "📋 Buyurtmalarim", 'callback_data' => "smm_my_orders"]]]], JSON_UNESCAPED_UNICODE));
    } else {
        add_balance($chat_id, $som_price, $connect);
        sendMessage($chat_id, "⚠️ SMM API xatolik. Balans qaytarildi.");
    }
    clear_step($chat_id);
    answerCallback($callback_id, "✅ To'landi!", true);
    exit;
}

if ($callback_data === "smm_pay_card") {
    $st = load_step($chat_id);
    $pay_amount = intval($st['smm_pay_amount'] ?? 0);
    if (!$pay_amount) { answerCallback($callback_id, "❌ Buyurtma topilmadi!", true); exit; }
    deleteMessage($chat_id, $message_id);
    // Karta orqali to'lov yaratish (smm_enter_qty bosqichidan davom etish)
    $st['step'] = 'smm_card_pay';
    save_step($chat_id, $st);
    // Qayta to'lov yaratish
    $pay_resp2 = $CheckCardPay->create_checkout($pay_amount);
    $pay_data2 = $pay_resp2 ? json_decode($pay_resp2, true) : null;
    if (!$pay_data2 || ($pay_data2['status'] ?? '') === 'error') {
        sendMessage($chat_id, "⚠️ To'lov yaratishda xatolik."); clear_step($chat_id); exit;
    }
    $oc2 = $pay_data2['order'] ?? null;
    $ii2 = $pay_data2['insert_id'] ?? $oc2;
    $pu2 = $pay_data2['pay_url'] ?? null;
    if (!$oc2) { sendMessage($chat_id, "⚠️ API xatolik."); clear_step($chat_id); exit; }
    // DB
    $uid_c = intval($chat_id);
    $svc_id_c = intval($st['smm_service_id']); $svc_nm_c = $st['smm_service_name'];
    $lnk_c = $st['smm_final_link']; $qty_c = intval($st['smm_final_qty']);
    $prc_c = intval($st['smm_final_price']);
    $ins_c = mysqli_prepare($connect, "INSERT INTO smm_orders (user_id, service_id, service_name, link, quantity, price, status, payment_order, payment_status) VALUES (?,?,?,?,?,?,'pending',?,'unpaid')");
    mysqli_stmt_bind_param($ins_c, 'iissiis', $uid_c, $svc_id_c, $svc_nm_c, $lnk_c, $qty_c, $prc_c, $oc2);
    mysqli_stmt_execute($ins_c); mysqli_stmt_close($ins_c);
    $kb_c = [];
    if ($pu2) $kb_c[] = [['text' => "💳 To'lov sahifasini ochish", 'url' => $pu2]];
    $kb_c[] = [['text' => "♻️ To'lovni tekshirish", 'callback_data' => "smm_check_pay={$oc2}"]];
    $kb_c[] = [['text' => "❌ Bekor qilish", 'callback_data' => "smm_cancel_pay={$oc2}"]];
    sendMessage($chat_id, "<b>📱 SMM Buyurtma #{$ii2}</b>
💳 Karta: <code>5614683582279246</code>
💵 To'lov miqdori: <b><u>{$pay_amount} so'm</u></b>

⚠️ Aynan shu miqdorni o'tkazing!", json_encode(['inline_keyboard' => $kb_c], JSON_UNESCAPED_UNICODE));
    clear_step($chat_id);
    answerCallback($callback_id, "", false);
    exit;
}

// ─── SMM: Asosiy menyu ────────────────────────────────────────────────────────
if ($callback_data === "smm_main") {
    deleteMessage($chat_id, $message_id);
    $cats = array_keys(smm_categories());
    $kb = [];
    $i = 0;
    $row = [];
    foreach ($cats as $idx => $cat) {
        $row[] = ['text' => $cat, 'callback_data' => "smm_cat_{$idx}"];
        if (count($row) == 2) { $kb[] = $row; $row = []; }
    }
    if ($row) $kb[] = $row;
    $kb[] = [['text' => "📋 Buyurtmalarim", 'callback_data' => "smm_my_orders"], ['text' => "💰 SMM Balans", 'callback_data' => "smm_balance"]];
    $kb[] = [['text' => "🔙 Orqaga", 'callback_data' => "menu"]];
    sendMessage($chat_id, "<b>📱 SMM Panel</b>

🌐 <b>locksmm.com</b> orqali xizmatlar

Kategoriyani tanlang:", json_encode(['inline_keyboard' => $kb], JSON_UNESCAPED_UNICODE));
    exit;
}

// ─── SMM: Balans ──────────────────────────────────────────────────────────────
if ($callback_data === "smm_balance") {
    $bal = smm_api(['action' => 'balance']);
    $balance_txt = $bal ? number_format(floatval($bal['balance'] ?? 0), 4) . " " . ($bal['currency'] ?? 'USD') : "API xatolik";
    answerCallback($callback_id, "💰 SMM Balans: {$balance_txt}", true);
    exit;
}

// ─── SMM: Kategoriya ──────────────────────────────────────────────────────────
if ($callback_data && strpos($callback_data, "smm_cat_") === 0) {
    $cat_idx = intval(str_replace("smm_cat_", "", $callback_data));
    $cats = smm_categories();
    $cat_names = array_keys($cats);
    if (!isset($cat_names[$cat_idx])) { answerCallback($callback_id, "Xatolik", true); exit; }
    $cat_name = $cat_names[$cat_idx];
    $services = $cats[$cat_name];
    deleteMessage($chat_id, $message_id);
    $kb = [];
    foreach ($services as $sidx => $svc) {
        $kb[] = [['text' => $svc['name'], 'callback_data' => "smm_svc_{$cat_idx}_{$sidx}"]];
    }
    $kb[] = [['text' => "🔙 Orqaga", 'callback_data' => "smm_main"]];
    sendMessage($chat_id, "<b>📱 {$cat_name}</b>

Xizmatni tanlang:", json_encode(['inline_keyboard' => $kb], JSON_UNESCAPED_UNICODE));
    exit;
}

// ─── SMM: Xizmat tanlash ──────────────────────────────────────────────────────
if ($callback_data && strpos($callback_data, "smm_svc_") === 0) {
    $parts_smm = explode("_", str_replace("smm_svc_", "", $callback_data));
    $cat_idx = intval($parts_smm[0]);
    $svc_idx = intval($parts_smm[1]);
    $cats = smm_categories();
    $cat_names = array_keys($cats);
    if (!isset($cat_names[$cat_idx])) { answerCallback($callback_id, "Xatolik", true); exit; }
    $cat_name = $cat_names[$cat_idx];
    $services = $cats[$cat_name];
    if (!isset($services[$svc_idx])) { answerCallback($callback_id, "Xatolik", true); exit; }
    $svc = $services[$svc_idx];
    deleteMessage($chat_id, $message_id);
    save_step($chat_id, [
        'step' => "smm_enter_link",
        'smm_service_id' => $svc['id'],
        'smm_service_name' => $svc['name'],
        'smm_min' => $svc['min'],
        'smm_max' => $svc['max'],
        'smm_cat_idx' => $cat_idx,
    ]);
    $kb = [[['text' => "🔙 Orqaga", 'callback_data' => "smm_cat_{$cat_idx}"]]];
    // API dan narxni olish va ko'rsatish
    $smm_cfg_s  = cfg($connect);
    $usd_rate_s = intval($smm_cfg_s['smm_usd_rate'] ?? 12700);
    $markup_s   = intval($smm_cfg_s['smm_markup'] ?? 20);
    $svc_list_s = smm_api(['action' => 'services']);
    $rate_som_1000 = 0;
    if ($svc_list_s) {
        foreach ($svc_list_s as $sv_s) {
            if (intval($sv_s['service'] ?? 0) == intval($svc['id'])) {
                $r_usd = floatval($sv_s['rate'] ?? 0);
                $rate_som_1000 = intval($r_usd * $usd_rate_s * (1 + $markup_s / 100));
                break;
            }
        }
    }
    $rate_line = $rate_som_1000 > 0 ? "\n💲 <b>1000 ta narxi: " . number_format($rate_som_1000) . " so'm</b>" : "";
    sendMessage($chat_id, "<b>📱 " . $svc['name'] . "</b>\n\n📊 Min: <b>" . number_format($svc['min']) . "</b> | Max: <b>" . number_format($svc['max']) . "</b>{$rate_line}\n\n🔗 Link kiriting (to'liq URL):\nMasalan: <code>https://t.me/username</code>", json_encode(['inline_keyboard' => $kb], JSON_UNESCAPED_UNICODE));
    exit;
}

// ─── SMM: Mening buyurtmalarim ────────────────────────────────────────────────
if ($callback_data === "smm_my_orders") {
    deleteMessage($chat_id, $message_id);
    $orders = mysqli_query($connect, "SELECT * FROM smm_orders WHERE user_id={$chat_id} ORDER BY date DESC LIMIT 10");
    $txt = "<b>📋 Mening SMM buyurtmalarim</b>

";
    $cnt = 0;
    while ($o = mysqli_fetch_assoc($orders)) {
        $cnt++;
        $status_map = ['Completed'=>'✅','In progress'=>'⏳','Pending'=>'🕐','Partial'=>'⚠️','Canceled'=>'❌'];
        $status_emoji = $status_map[$o['status']] ?? '🔄';
        $txt .= "{$status_emoji} <b>#{$o['smm_order_id']}</b>
";
        $txt .= "   📌 " . mb_substr($o['service_name'], 0, 40) . "
";
        $txt .= "   🔢 {$o['quantity']} | 💰 " . number_format($o['price']) . " so'm
";
        $txt .= "   📅 " . date('d.m.Y H:i', strtotime($o['date'])) . "

";
    }
    if ($cnt == 0) $txt .= "Hozircha buyurtma yo'q.";
    $kb = [[['text' => "🔙 Orqaga", 'callback_data' => "smm_main"]]];
    sendMessage($chat_id, $txt, json_encode(['inline_keyboard' => $kb], JSON_UNESCAPED_UNICODE));
    exit;
}

// ─── SMM: To'lov tekshirish (SMM) ────────────────────────────────────────────
if ($callback_data && strpos($callback_data, "smm_check_pay=") === 0) {
    $smm_order_code = str_replace("smm_check_pay=", "", $callback_data);
    $smm_row = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM smm_orders WHERE payment_order='" . mysqli_real_escape_string($connect, $smm_order_code) . "' AND user_id={$chat_id} LIMIT 1"));
    if (!$smm_row) { answerCallback($callback_id, "❌ Buyurtma topilmadi!", true); exit; }
    if ($smm_row['payment_status'] === 'paid') { answerCallback($callback_id, "✅ Allaqachon to'langan!", true); exit; }

    $raw_smm = $CheckCardPay->check_payment($smm_order_code);
    $result_smm = $raw_smm ? json_decode($raw_smm, true) : null;
    if (!$result_smm || ($result_smm['status'] ?? '') !== 'success') {
        answerCallback($callback_id, "⚠️ To'lovni tekshirishda xatolik.", true); exit;
    }
    $smm_pay_data = $result_smm['data'] ?? [];
    $smm_status = $smm_pay_data['status'] ?? '';

    if ($smm_status === 'paid') {
        // SMM API ga buyurtma yuborish
        $smm_result = smm_api([
            'action' => 'add',
            'service' => $smm_row['service_id'],
            'link' => $smm_row['link'],
            'quantity' => $smm_row['quantity'],
        ]);
        if ($smm_result && isset($smm_result['order'])) {
            $smm_api_order_id = intval($smm_result['order']);
            mysqli_query($connect, "UPDATE smm_orders SET smm_order_id={$smm_api_order_id}, payment_status='paid', status='Pending' WHERE id={$smm_row['id']}");
            @deleteMessage($chat_id, $message_id);
            sendMessage($chat_id, "✅ <b>To'lov qabul qilindi!</b>

📋 SMM Buyurtma ID: <b>#{$smm_api_order_id}</b>
📌 " . $smm_row['service_name'] . "
🔢 Miqdor: <b>" . number_format($smm_row['quantity']) . "</b>

⏳ Xizmat bajarilmoqda...", json_encode(['inline_keyboard' => [[['text' => "📋 Buyurtmalarim", 'callback_data' => "smm_my_orders"]], [['text' => "🔙 Menyu", 'callback_data' => "menu"]]]], JSON_UNESCAPED_UNICODE));
        } else {
            $err_msg = $smm_result['error'] ?? "Noma lum xatolik";
            mysqli_query($connect, "UPDATE smm_orders SET payment_status='paid', status='api_error' WHERE id={$smm_row['id']}");
            sendMessage($chat_id, "⚠️ <b>To'lov qabul qilindi, lekin xizmat yuborishda xato:</b> {$err_msg}

Admin bilan bog'laning.");
            sendMessage($admin, "⚠️ SMM API xato
User: {$chat_id}
Xizmat: {$smm_row['service_name']}
Xato: {$err_msg}");
        }
        answerCallback($callback_id, "✅ To'lov qabul qilindi!", true);
    } elseif ($smm_status === 'pending') {
        answerCallback($callback_id, "⏳ To'lov hali amalga oshirilmagan.", true);
    } elseif ($smm_status === 'cancel') {
        mysqli_query($connect, "UPDATE smm_orders SET payment_status='cancel' WHERE id={$smm_row['id']}");
        deleteMessage($chat_id, $message_id);
        sendMessage($chat_id, "❌ To'lov bekor qilindi!");
        answerCallback($callback_id, "Bekor qilindi.", true);
    } else {
        answerCallback($callback_id, "⚠️ Holat: {$smm_status}", true);
    }
    exit;
}

// ─── SMM: Buyurtmani bekor qilish ────────────────────────────────────────────
if ($callback_data && strpos($callback_data, "smm_cancel_pay=") === 0) {
    $smm_cancel_code = str_replace("smm_cancel_pay=", "", $callback_data);
    $smm_cancel_row = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM smm_orders WHERE payment_order='" . mysqli_real_escape_string($connect, $smm_cancel_code) . "' AND payment_status='unpaid' LIMIT 1"));
    if (!$smm_cancel_row) { answerCallback($callback_id, "❌ Topilmadi yoki allaqachon o'zgargan!", true); exit; }
    mysqli_query($connect, "UPDATE smm_orders SET payment_status='cancel' WHERE id={$smm_cancel_row['id']}");
    $CheckCardPay->cancel_payment($smm_cancel_code);
    deleteMessage($chat_id, $message_id);
    sendMessage($chat_id, "❌ <b>Buyurtma bekor qilindi.</b>", json_encode(['inline_keyboard' => [[['text' => "🔙 SMM Panel", 'callback_data' => "smm_main"]]]], JSON_UNESCAPED_UNICODE));
    answerCallback($callback_id, "Bekor qilindi.", true);
    exit;
}

// ─── 💳 Balans tizimi ────────────────────────────────────────────────────────
if ($callback_data === "wallet_main") {
    deleteMessage($chat_id, $message_id);
    $bal = get_balance($chat_id, $connect);
    $kb = [
        [['text' => "➕ Balans to'ldirish", 'callback_data' => "wallet_topup"]],
        [['text' => "📋 To'ldirish tarixi", 'callback_data' => "wallet_history"]],
        [['text' => "🔙 Orqaga", 'callback_data' => "menu"]],
    ];
    sendMessage($chat_id, "<b>💳 Balans</b>

💰 Hozirgi balans: <b>" . number_format($bal) . " so'm</b>

📌 Balansdan foydalanish:
• Stars va Premium xarid qilish
• SMM Panel xizmatlari
• 1000 so'mdan kam buyurtmalarda faqat balansdan to'lov

💡 Minimal to'ldirish: <b>1,000 so'm</b>", json_encode(['inline_keyboard' => $kb], JSON_UNESCAPED_UNICODE));
    exit;
}

if ($callback_data === "wallet_topup") {
    deleteMessage($chat_id, $message_id);
    save_step($chat_id, ['step' => 'wallet_enter_amount']);
    $kb = [
        [['text' => "5,000 so'm", 'callback_data' => "wallet_amount_5000"],  ['text' => "10,000 so'm", 'callback_data' => "wallet_amount_10000"]],
        [['text' => "25,000 so'm", 'callback_data' => "wallet_amount_25000"], ['text' => "50,000 so'm", 'callback_data' => "wallet_amount_50000"]],
        [['text' => "🔙 Orqaga", 'callback_data' => "wallet_main"]],
    ];
    sendMessage($chat_id, "<b>➕ Balans to'ldirish</b>

💡 Minimal: <b>1,000 so'm</b>

Miqdorni tanlang yoki kiriting:", json_encode(['inline_keyboard' => $kb], JSON_UNESCAPED_UNICODE));
    exit;
}

// Tezkor tugmalar
if ($callback_data && strpos($callback_data, "wallet_amount_") === 0) {
    $amount_w = intval(str_replace("wallet_amount_", "", $callback_data));
    deleteMessage($chat_id, $message_id);
    process_wallet_topup($chat_id, $connect, $amount_w);
    exit;
}

if ($callback_data === "wallet_history") {
    deleteMessage($chat_id, $message_id);
    $hist = mysqli_query($connect, "SELECT * FROM top_up WHERE user_id={$chat_id} ORDER BY date DESC LIMIT 10");
    $txt = "<b>📋 To'ldirish tarixi</b>

";
    $cnt = 0;
    while ($h = mysqli_fetch_assoc($hist)) {
        $cnt++;
        $st_e = $h['status'] === 'paid' ? '✅' : ($h['status'] === 'cancel' ? '❌' : '⏳');
        $txt .= "{$st_e} " . number_format($h['amount']) . " so'm — " . date('d.m.Y H:i', strtotime($h['date'])) . "
";
    }
    if ($cnt == 0) $txt .= "Hozircha to'ldirish yo'q.";
    $kb = [[['text' => "🔙 Orqaga", 'callback_data' => "wallet_main"]]];
    sendMessage($chat_id, $txt, json_encode(['inline_keyboard' => $kb], JSON_UNESCAPED_UNICODE));
    exit;
}

// Balans to'ldirish to'lovini tekshirish
if ($callback_data && strpos($callback_data, "wallet_check=") === 0) {
    $order_code_w = str_replace("wallet_check=", "", $callback_data);
    $top_row = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM top_up WHERE order_code='" . mysqli_real_escape_string($connect, $order_code_w) . "' AND user_id={$chat_id} LIMIT 1"));
    if (!$top_row || $top_row['status'] === 'paid') {
        answerCallback($callback_id, $top_row && $top_row['status'] === 'paid' ? "✅ Allaqachon qo'shilgan!" : "❌ Topilmadi!", true);
        exit;
    }
    $raw_w = $CheckCardPay->check_payment($order_code_w);
    $res_w = $raw_w ? json_decode($raw_w, true) : null;
    if (!$res_w || ($res_w['status'] ?? '') !== 'success') {
        answerCallback($callback_id, "⚠️ Tekshirishda xatolik.", true); exit;
    }
    $pay_data_w = $res_w['data'] ?? [];
    $summa_w    = intval($pay_data_w['amount'] ?? 0);
    $status_w   = $pay_data_w['status'] ?? '';

    if ($status_w === 'paid') {
        mysqli_query($connect, "UPDATE top_up SET status='paid' WHERE order_code='" . mysqli_real_escape_string($connect, $order_code_w) . "'");
        add_balance($chat_id, $top_row['amount'], $connect);
        $new_bal = get_balance($chat_id, $connect);
        @deleteMessage($chat_id, $message_id);
        sendMessage($chat_id, "✅ <b>Balansga " . number_format($top_row['amount']) . " so'm qo'shildi!</b>

💰 Hozirgi balans: <b>" . number_format($new_bal) . " so'm</b>", json_encode(['inline_keyboard' => [[['text' => "💳 Balans", 'callback_data' => "wallet_main"]]]], JSON_UNESCAPED_UNICODE));
        sendMessage($admin, "💳 Balans to'ldirildi
User: {$chat_id}
Miqdor: " . number_format($top_row['amount']) . " so'm
Yangi balans: " . number_format($new_bal) . " so'm");
        answerCallback($callback_id, "✅ Balans to'ldirildi!", true);
    } elseif ($status_w === 'pending') {
        answerCallback($callback_id, "⏳ To'lov hali amalga oshirilmagan.", true);
    } elseif ($status_w === 'cancel') {
        mysqli_query($connect, "UPDATE top_up SET status='cancel' WHERE order_code='" . mysqli_real_escape_string($connect, $order_code_w) . "'");
        deleteMessage($chat_id, $message_id);
        sendMessage($chat_id, "❌ To'lov bekor qilindi.");
        answerCallback($callback_id, "Bekor qilindi.", true);
    } else {
        answerCallback($callback_id, "⚠️ Holat: {$status_w}", true);
    }
    exit;
}

// Balans to'ldirish bekor qilish
if ($callback_data && strpos($callback_data, "wallet_cancel=") === 0) {
    $order_code_wc = str_replace("wallet_cancel=", "", $callback_data);
    $top_row_c = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM top_up WHERE order_code='" . mysqli_real_escape_string($connect, $order_code_wc) . "' AND status='unpaid' LIMIT 1"));
    if (!$top_row_c) { answerCallback($callback_id, "❌ Topilmadi!", true); exit; }
    mysqli_query($connect, "UPDATE top_up SET status='cancel' WHERE order_code='" . mysqli_real_escape_string($connect, $order_code_wc) . "'");
    $CheckCardPay->cancel_payment($order_code_wc);
    deleteMessage($chat_id, $message_id);
    sendMessage($chat_id, "❌ To'ldirish bekor qilindi.", json_encode(['inline_keyboard' => [[['text' => "💳 Balans", 'callback_data' => "wallet_main"]]]], JSON_UNESCAPED_UNICODE));
    answerCallback($callback_id, "Bekor qilindi.", true);
    exit;
}

// ─── Balansdan to'lash ────────────────────────────────────────────────────────
if ($callback_data && strpos($callback_data, "pay_balance_") === 0) {
    $pay_type = str_replace("pay_balance_", "", $callback_data);
    pay_with_balance($chat_id, $connect, $pay_type);
    exit;
}

// Karta orqali to'lash
if ($callback_data && strpos($callback_data, "pay_card_") === 0) {
    $pay_type = str_replace("pay_card_", "", $callback_data);
    if ($pay_type === 'stars') {
        make_stars_payment($chat_id, $connect, $CC);
    } else {
        make_premium_payment($chat_id, $connect, $CC);
    }
    exit;
}

// ─── 🎮 Game Bar ─────────────────────────────────────────────────────────────
if ($callback_data === "gamebar") {
    deleteMessage($chat_id, $message_id);

    // game_bonus jadvalga foydalanuvchi qo'shish
    $gi = mysqli_prepare($connect, "INSERT IGNORE INTO game_bonus (user_id) VALUES (?)");
    mysqli_stmt_bind_param($gi, 'i', $chat_id);
    mysqli_stmt_execute($gi);
    mysqli_stmt_close($gi);

    $gb = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM game_bonus WHERE user_id={$chat_id} LIMIT 1"));
    $today = date('Y-m-d');
    $last_play = $gb['last_play'] ?? null;
    $total = floatval($gb['total_stars'] ?? 0);
    $withdrawn = intval($gb['withdrawn'] ?? 0);

    // Referal soni
    $ref_row = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) c FROM referrals WHERE inviter_id={$chat_id}"));
    $ref_count = intval($ref_row['c']);
    $can_withdraw = ($ref_count >= 15 && $total > 0 && !$withdrawn);

    if ($last_play === $today) {
        // Bugun allaqachon o'ynagan
        $kb = [
            [['text' => "⏰ Ertaga qaytib keling!", 'callback_data' => "gamebar_info"]],
        ];
        if ($can_withdraw) $kb[] = [['text' => "💸 Yechish ({$total} ⭐)", 'callback_data' => "gamebar_withdraw"]];
        $kb[] = [['text' => "🔙 Orqaga", 'callback_data' => "menu"]];
        sendMessage($chat_id, "🎮 <b>Game Bar</b>

✅ Bugun allaqachon o'yndingiz!
💰 Jami yig'ilgan: <b>{$total} ⭐</b>
👥 Taklif qilganlar: <b>{$ref_count}/15</b>

⏰ Ertaga yana o'ynash mumkin bo'ladi!", json_encode(['inline_keyboard' => $kb], JSON_UNESCAPED_UNICODE));
    } else {
        // O'ynash mumkin
        $kb = [
            [['text' => "🎰 O'ynash!", 'callback_data' => "gamebar_play"]],
        ];
        if ($can_withdraw) $kb[] = [['text' => "💸 Yechish ({$total} ⭐)", 'callback_data' => "gamebar_withdraw"]];
        $kb[] = [['text' => "🔙 Orqaga", 'callback_data' => "menu"]];
        sendMessage($chat_id, "🎮 <b>Game Bar</b>

🎯 Har kuni o'ynab <b>0 - 1 ⭐</b> yutib olishingiz mumkin!
💰 Jami yig'ilgan: <b>{$total} ⭐</b>
👥 Taklif qilganlar: <b>{$ref_count}/15</b>

💡 Yig'ilgan starslarni yechish uchun 15 ta do'stingizni taklif qiling!", json_encode(['inline_keyboard' => $kb], JSON_UNESCAPED_UNICODE));
    }
    exit;
}

if ($callback_data === "gamebar_info") {
    answerCallback($callback_id, "⏰ Ertaga qaytib keling! Har kuni 1 marta o'ynash mumkin.", true);
    exit;
}

if ($callback_data === "gamebar_play") {
    $gi2 = mysqli_prepare($connect, "INSERT IGNORE INTO game_bonus (user_id) VALUES (?)");
    mysqli_stmt_bind_param($gi2, 'i', $chat_id);
    mysqli_stmt_execute($gi2);
    mysqli_stmt_close($gi2);

    $gb2 = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM game_bonus WHERE user_id={$chat_id} LIMIT 1"));
    $today2 = date('Y-m-d');

    if ($gb2['last_play'] === $today2) {
        answerCallback($callback_id, "⏰ Bugun allaqachon o'yndingiz! Ertaga keling.", true);
        exit;
    }

    // Random 0 yoki 1 stars (50/50)
    $won = rand(0, 1);
    $won_float = floatval($won);
    $new_total = floatval($gb2['total_stars']) + $won_float;

    $upd = mysqli_prepare($connect, "UPDATE game_bonus SET last_play=?, total_stars=? WHERE user_id=?");
    mysqli_stmt_bind_param($upd, 'sdi', $today2, $new_total, $chat_id);
    mysqli_stmt_execute($upd);
    mysqli_stmt_close($upd);

    $ref_row2 = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) c FROM referrals WHERE inviter_id={$chat_id}"));
    $ref_count2 = intval($ref_row2['c']);
    $can_withdraw2 = ($ref_count2 >= 15 && $new_total > 0);

    deleteMessage($chat_id, $message_id);

    if ($won == 1) {
        $kb2 = [];
        if ($can_withdraw2) $kb2[] = [['text' => "💸 Yechish ({$new_total} ⭐)", 'callback_data' => "gamebar_withdraw"]];
        $kb2[] = [['text' => "🔙 Orqaga", 'callback_data' => "menu"]];
        sendMessage($chat_id, "🎮 <b>Game Bar</b>

🎉 <b>Tabriklaymiz! 1 ⭐ yutdingiz!</b>

💰 Jami yig'ilgan: <b>{$new_total} ⭐</b>
👥 Taklif qilganlar: <b>{$ref_count2}/15</b>

" . ($can_withdraw2 ? "✅ Starslarni yechishingiz mumkin!" : "💡 Yechish uchun 15 ta do'st taklif qiling!"), json_encode(['inline_keyboard' => $kb2], JSON_UNESCAPED_UNICODE));
    } else {
        $kb2 = [[['text' => "🔙 Orqaga", 'callback_data' => "menu"]]];
        sendMessage($chat_id, "🎮 <b>Game Bar</b>

😔 <b>Omadsiz keldingiz. Ertaga yana urinib ko'ring!</b>

💰 Jami yig'ilgan: <b>{$new_total} ⭐</b>
👥 Taklif qilganlar: <b>{$ref_count2}/15</b>

💡 Yechish uchun 15 ta do'st taklif qiling!", json_encode(['inline_keyboard' => $kb2], JSON_UNESCAPED_UNICODE));
    }
    exit;
}

if ($callback_data === "gamebar_withdraw") {
    $gb3 = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM game_bonus WHERE user_id={$chat_id} LIMIT 1"));
    $total3 = floatval($gb3['total_stars'] ?? 0);
    $withdrawn3 = intval($gb3['withdrawn'] ?? 0);

    $ref_row3 = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) c FROM referrals WHERE inviter_id={$chat_id}"));
    $ref_count3 = intval($ref_row3['c']);

    if ($ref_count3 < 15) {
        answerCallback($callback_id, "❌ Yechish uchun 15 ta do'st taklif qilish kerak! Hozir: {$ref_count3}/15", true);
        exit;
    }
    if ($total3 <= 0) {
        answerCallback($callback_id, "❌ Yechish uchun yig'ilgan stars yo'q!", true);
        exit;
    }
    if ($withdrawn3) {
        answerCallback($callback_id, "❌ Siz allaqachon starslarni yechib oldingiz!", true);
        exit;
    }

    // Admin ga yuborish uchun xabar
    $uname3 = $username ?? "ID:{$chat_id}";
    sendMessage($admin, "💸 <b>Game Bar — Stars yechish so'rovi</b>

👤 Foydalanuvchi: @{$uname3} (ID: {$chat_id})
⭐ Miqdor: <b>{$total3} stars</b>
👥 Referallar: {$ref_count3}/15");

    // Mark as withdrawn
    mysqli_query($connect, "UPDATE game_bonus SET withdrawn=1 WHERE user_id={$chat_id}");

    deleteMessage($chat_id, $message_id);
    sendMessage($chat_id, "✅ <b>So'rovingiz qabul qilindi!</b>

⭐ <b>{$total3} stars</b> tez orada hisobingizga o'tkaziladi.

Admin bilan bog'laning agar 24 soat ichida kelmasa.", json_encode(['inline_keyboard' => [[['text' => "🔙 Orqaga", 'callback_data' => "menu"]]]], JSON_UNESCAPED_UNICODE));
    exit;
}

// ─── 👥 Referal ───────────────────────────────────────────────────────────────
if ($callback_data === "referral") {
    deleteMessage($chat_id, $message_id);

    $bot_info = bot('getMe', []);
    $bot_username = $bot_info['result']['username'] ?? 'bot';
    $ref_link = "https://t.me/{$bot_username}?start={$chat_id}";

    $ref_row4 = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) c FROM referrals WHERE inviter_id={$chat_id}"));
    $ref_count4 = intval($ref_row4['c']);

    $gb4 = mysqli_fetch_assoc(mysqli_query($connect, "SELECT total_stars FROM game_bonus WHERE user_id={$chat_id} LIMIT 1"));
    $total4 = floatval($gb4['total_stars'] ?? 0);

    $kb4 = [
        [['text' => "📤 Do'stga yuborish", 'url' => "https://t.me/share/url?url=" . urlencode($ref_link) . "&text=" . urlencode("🎁 Uz Give botiga qo'shiling va har kuni bepul Stars yuting!")]],
        [['text' => "🔙 Orqaga", 'callback_data' => "menu"]],
    ];

    sendMessage($chat_id, "👥 <b>Do'stlarni taklif qilish</b>

🔗 Sizning havolangiz:
<code>{$ref_link}</code>

👥 Taklif qilganlar: <b>{$ref_count4}/15</b>
⭐ Yig'ilgan stars: <b>{$total4}</b>

💡 <b>Shartlar:</b>
• 15 ta do'st taklif qiling
• Game Bar dan yig'gan starslaringizni yeching

🎮 Har kuni Game Bar da o'ynab <b>1 ⭐</b> gacha yutib oling!", json_encode(['inline_keyboard' => $kb4], JSON_UNESCAPED_UNICODE));
    exit;
}

if ($callback_data === "stars") {
deleteMessage($chat_id, $message_id);
$star_price = settings($connect)['star_price'];
$reply = json_encode(['inline_keyboard' => [
[['text' => "50 ⭐", 'callback_data' => "stars_50"], ['text' => "100 ⭐", 'callback_data' => "stars_100"]],
[['text' => "500 ⭐", 'callback_data' => "stars_500"], ['text' => "1000 ⭐", 'callback_data' => "stars_1000"]],
[['text' => "🔙 Orqaga", 'callback_data' => "menu"]]
]], JSON_UNESCAPED_UNICODE);
sendMessage($chat_id, "<b>❓ Nechta star sotib olmoqchisiz (50-5000) kiriting yoki tanlang:\n\n(1 star = {$star_price} so'm)</b>", $reply);
save_step($chat_id, ['step' => 'stars_amount']);
exit;
}

if ($callback_data && strpos($callback_data, "stars_") === 0) {
deleteMessage($chat_id, $message_id);
$parts = explode("_", $callback_data);
$stars = intval($parts[1]);
$star_price = settings($connect)['star_price'];
$price = $stars * $star_price;
save_step($chat_id, ['step' => 'stars_user', 'stars' => $stars]);
$reply = json_encode(['inline_keyboard' => [
[['text' => "👤 O'zimga", 'callback_data' => "self_user"], ['text' => "🔙 Orqaga", 'callback_data' => "stars"]]
]], JSON_UNESCAPED_UNICODE);
sendMessage($chat_id, "<b>⭐️ Stars sotib olish\n📊 Buyurtma ma'lumotlari:\n└ 🎯 Miqdor: {$stars} ⭐️\n└ 💰 Narxi: {$price} so'm\n\n👤 Kimga yuboramiz?\n📝 @username kiriting:</b>", $reply);
exit;
}

if ($callback_data && strpos($callback_data, "premium_") === 0) {
deleteMessage($chat_id, $message_id);
$parts = explode("_", $callback_data);
$months = intval($parts[1]);
$settings = settings($connect);
$price = $settings["premium_{$months}_month"];
save_step($chat_id, ['step' => 'premium_user', 'months' => $months, 'price' => $price]);
$reply = json_encode(['inline_keyboard' => [
[['text' => "👤 O'zimga", 'callback_data' => "self_premium"], ['text' => "🔙 Orqaga", 'callback_data' => "premium"]]
]], JSON_UNESCAPED_UNICODE);
sendMessage($chat_id, "<b>👑 Premium obuna\n📊 Buyurtma ma'lumotlari:\n└ 🎯 Muddat: {$months} oy\n└ 💰 Narxi: {$price} so'm\n\n👤 Kimga yuboramiz?\n📝 @username kiriting:</b>", $reply);
exit;
}

if ($callback_data === "self_user") {
$caller = $callback['from'] ?? null;
$caller_username = $caller['username'] ?? null;
if (!$caller_username) {
answerCallback($callback_id, "❗ Sizda username yo'q! Iltimos, Telegram sozlamalaridan username qo'shing.", true);
exit;
}
$st = load_step($chat_id);
if (empty($st['stars'])) {
answerCallback($callback_id, "⚠️ Buyurtma topilmadi. Iltimos, avval miqdorni tanlang.", true);
exit;
}
$st['receiver'] = '@' . $caller_username;
save_step($chat_id, $st);
offer_payment_method($chat_id, $connect, 'stars');
exit;
}

if ($callback_data === "self_premium") {
$caller = $callback['from'] ?? null;
$caller_username = $caller['username'] ?? null;
if (!$caller_username) {
answerCallback($callback_id, "❗ Sizda username yo'q! Iltimos, Telegram sozlamalaridan username qo'shing.", true);
exit;
}
$st = load_step($chat_id);
if (empty($st['months'])) {
answerCallback($callback_id, "⚠️ Buyurtma topilmadi. Iltimos, avval muddatni tanlang.", true);
exit;
}
$st['receiver'] = '@' . $caller_username;
save_step($chat_id, $st);
offer_payment_method($chat_id, $connect, 'premium');
exit;
}

if ($text !== null) {
$st = load_step($chat_id);
if (!empty($st['step']) && $st['step'] === 'stars_amount') {
if (is_numeric($text)) {
$requested = intval($text);
if ($requested < 50 || $requested > 5000) {
sendMessage($chat_id, "⚠️ Noto'g'ri miqdor! 50 dan 5000 gacha kiriting.");
exit;
}
$star_price = settings($connect)['star_price'];
$price = $requested * $star_price;
save_step($chat_id, ['step' => 'stars_user', 'stars' => $requested]);
$reply = json_encode(['inline_keyboard' => [
[['text' => "👤 O'zimga", 'callback_data' => "self_user"], ['text' => "🔙 Orqaga", 'callback_data' => "stars"]]
]], JSON_UNESCAPED_UNICODE);
sendMessage($chat_id, "<b>⭐️ Stars sotib olish\n📊 Buyurtma ma'lumotlari:\n└ 🎯 Miqdor: {$requested} ⭐️\n└ 💰 Narxi: <u>{$price}</u> so'm\n\n👤 Kimga yuboramiz?\n📝 @username kiriting:</b>", $reply);
} else {
sendMessage($chat_id, "⚠️ Iltimos faqat son kiriting: 50 dan 5000 gacha.");
}
exit;
}

if (!empty($st['step']) && $st['step'] === 'stars_user') {
$input = trim($text);
$username_final = null;
if (preg_match('/@([A-Za-z0-9_]{3,32})/u', $input, $m)) {
$username_final = '@' . $m[1];
} elseif (preg_match('/^[A-Za-z0-9_]{3,32}$/u', $input)) {
$username_final = '@' . $input;
} elseif (preg_match('~t\.me/([A-Za-z0-9_]{3,32})~u', $input, $m2)) {
$username_final = '@' . $m2[1];
}

if (!$username_final) {
sendMessage($chat_id, "⚠️ Username noto'g'ri formatda. Masalan: @username yoki username (faqat harflar/raqamlar/underscore).");
exit;
}

$st['receiver'] = $username_final;
save_step($chat_id, $st);
offer_payment_method($chat_id, $connect, 'stars');
exit;
}

if (!empty($st['step']) && $st['step'] === 'premium_user') {
$input = trim($text);
$username_final = null;

if (preg_match('/@([A-Za-z0-9_]{3,32})/u', $input, $m)) {
$username_final = '@' . $m[1];
} elseif (preg_match('/^[A-Za-z0-9_]{3,32}$/u', $input)) {
$username_final = '@' . $input;
} elseif (preg_match('~t\.me/([A-Za-z0-9_]{3,32})~u', $input, $m2)) {
$username_final = '@' . $m2[1];
}

if (!$username_final) {
sendMessage($chat_id, "⚠️ Username noto'g'ri formatda. Masalan: @username yoki username (faqat harflar/raqamlar/underscore).");
exit;
}

$st['receiver'] = $username_final;
save_step($chat_id, $st);
process_premium_order($chat_id, $connect, $card_number); 
exit;
}

// ─── SMM: Link va miqdor kiritish ────────────────────────────────────────────
// Balans to'ldirish miqdor kiritish
if (!empty($st['step']) && $st['step'] === 'wallet_enter_amount') {
    if (!is_numeric($text) || intval($text) < 1000) {
        sendMessage($chat_id, "⚠️ Minimal miqdor <b>1,000 so'm</b>! To'g'ri son kiriting.");
        exit;
    }
    $amount_entered = intval($text);
    clear_step($chat_id);
    process_wallet_topup($chat_id, $connect, $amount_entered);
    exit;
}

if (!empty($st['step']) && $st['step'] === "smm_enter_link") {
    $link = trim($text);
    if (!filter_var($link, FILTER_VALIDATE_URL)) {
        sendMessage($chat_id, "⚠️ Noto'g'ri URL! To'liq link kiriting.
Masalan: <code>https://t.me/username</code>");
        exit;
    }
    $st['smm_link'] = $link;
    $st['step'] = "smm_enter_qty";
    save_step($chat_id, $st);
    $kb = [[['text' => "🔙 Orqaga", 'callback_data' => "smm_cat_{$st['smm_cat_idx']}"]]];
    sendMessage($chat_id, "<b>📱 " . $st['smm_service_name'] . "</b>

🔗 Link: <code>{$link}</code>

🔢 Miqdor kiriting (Min: <b>" . number_format($st['smm_min']) . "</b> | Max: <b>" . number_format($st['smm_max']) . "</b>):", json_encode(['inline_keyboard' => $kb], JSON_UNESCAPED_UNICODE));
    exit;
}

if (!empty($st['step']) && $st['step'] === "smm_enter_qty") {
    if (!is_numeric($text) || intval($text) <= 0) {
        sendMessage($chat_id, "⚠️ Faqat musbat son kiriting!");
        exit;
    }
    $qty = intval($text);
    if ($qty < $st['smm_min'] || $qty > $st['smm_max']) {
        sendMessage($chat_id, "⚠️ Miqdor <b>" . number_format($st['smm_min']) . "</b> dan <b>" . number_format($st['smm_max']) . "</b> gacha bo'lishi kerak!");
        exit;
    }
    // Narxni SMM API dan olish
    $smm_services_list = smm_api(['action' => 'services']);
    $svc_rate_usd = 0; // USD per 1000 ta
    if ($smm_services_list) {
        foreach ($smm_services_list as $sv) {
            if (intval($sv['service'] ?? 0) == intval($st['smm_service_id'])) {
                $svc_rate_usd = floatval($sv['rate'] ?? 0); // USD per 1000 ta
                break;
            }
        }
    }
    // Narx hisoblash:
    // API rate = USD per 1000 ta
    // Hujjatda ko'rsatilgan narx = 1000 tasi uchun so'm
    // Formulasi: (rate_usd_per_1000 * qty / 1000) * usd_kurs * (1 + markup%)
    $smm_cfg      = cfg($connect);
    $usd_rate     = intval($smm_cfg['smm_usd_rate'] ?? 12700); // 1 USD = X so'm
    $markup_pct   = intval($smm_cfg['smm_markup'] ?? 20);       // foiz qo'shimcha
    $base_som     = ($svc_rate_usd * $qty / 1000) * $usd_rate;  // asosiy narx so'mda
    $markup_som   = $base_som * $markup_pct / 100;              // qo'shimcha foiz
    $som_price    = intval($base_som + $markup_som);
    $som_price    = max($som_price, 500); // Minimum 500 so'm
    $rand_num     = rand(1, 99);
    $pay_amount   = $som_price + $rand_num;
    
    // Foydalanuvchiga narx tafsilotlari
    $rate_per_1000_som = intval($svc_rate_usd * $usd_rate); // 1000 tasi uchun so'm (bazaviy)
    $rate_per_1_som    = round($svc_rate_usd * $usd_rate / 1000, 2); // 1 tasi uchun so'm

    // Balans tekshirish
    $user_bal = get_balance($chat_id, $connect);
    if ($som_price < 1000) {
        // 1000 dan kam — faqat balansdan
        if ($user_bal >= $som_price) {
            $deducted_smm = deduct_balance($chat_id, $som_price, $connect);
            if ($deducted_smm) {
                $new_bal_smm = get_balance($chat_id, $connect);
                // DB ga saqlash
                $smm_uid2 = intval($chat_id); $smm_svc_id2 = intval($st['smm_service_id']);
                $smm_svc_name2 = $st['smm_service_name']; $smm_link2 = $st['smm_link'];
                $smm_qty2 = intval($qty); $smm_price2 = intval($som_price);
                $ins_smm2 = mysqli_prepare($connect, "INSERT INTO smm_orders (user_id, service_id, service_name, link, quantity, price, status, payment_order, payment_status) VALUES (?,?,?,?,?,?,'pending','balance_pay','paid')");
                mysqli_stmt_bind_param($ins_smm2, 'iissii', $smm_uid2, $smm_svc_id2, $smm_svc_name2, $smm_link2, $smm_qty2, $smm_price2);
                mysqli_stmt_execute($ins_smm2); $rid_smm = mysqli_insert_id($connect); mysqli_stmt_close($ins_smm2);
                // SMM API ga yuborish
                $smm_result2 = smm_api(['action' => 'add', 'service' => $st['smm_service_id'], 'link' => $st['smm_link'], 'quantity' => $qty]);
                if ($smm_result2 && isset($smm_result2['order'])) {
                    $smm_oid2 = intval($smm_result2['order']);
                    mysqli_query($connect, "UPDATE smm_orders SET smm_order_id={$smm_oid2}, status='Pending' WHERE id={$rid_smm}");
                    sendMessage($chat_id, "✅ <b>Balansdan to'landi!</b>
💰 " . number_format($som_price) . " so'm
💳 Qolgan balans: <b>" . number_format($new_bal_smm) . " so'm</b>

📋 SMM Buyurtma ID: <b>#{$smm_oid2}</b>
⏳ Xizmat bajarilmoqda...", json_encode(['inline_keyboard' => [[['text' => "📋 Buyurtmalarim", 'callback_data' => "smm_my_orders"]]]], JSON_UNESCAPED_UNICODE));
                } else {
                    add_balance($chat_id, $som_price, $connect);
                    sendMessage($chat_id, "⚠️ SMM API xatolik. Balans qaytarildi. Admin bilan bog'laning.");
                }
                clear_step($chat_id); exit;
            }
        } else {
            $needed_bal = $som_price - $user_bal;
            sendMessage($chat_id, "⚠️ <b>1,000 so'mdan past buyurtmalar faqat balansdan to'lanadi!</b>

💰 Kerakli: " . number_format($som_price) . " so'm
💳 Balans: " . number_format($user_bal) . " so'm

❗ Balansni <b>" . number_format($needed_bal) . " so'm</b> ga to'ldiring.", json_encode(['inline_keyboard' => [[['text' => "➕ Balansni to'ldirish", 'callback_data' => "wallet_topup"]]]], JSON_UNESCAPED_UNICODE));
            clear_step($chat_id); exit;
        }
    }

    // 1000 so'm va undan yuqori — tanlash
    if ($user_bal >= $som_price) {
        $kb_smm_pay = [
            [['text' => "💳 Balansdan (" . number_format($user_bal) . " so'm)", 'callback_data' => "smm_pay_balance"]],
            [['text' => "🏦 Karta orqali", 'callback_data' => "smm_pay_card"]],
            [['text' => "🔙 Bekor qilish", 'callback_data' => "smm_main"]],
        ];
        sendMessage($chat_id, "<b>💳 To'lov usulini tanlang</b>

📱 " . mb_substr($smm_svc_name, 0, 40) . "
💰 Narxi: <b>" . number_format($som_price) . " so'm</b>
💳 Balansingiz: <b>" . number_format($user_bal) . " so'm</b>", json_encode(['inline_keyboard' => $kb_smm_pay], JSON_UNESCAPED_UNICODE));
        // step ni saqlash
        $st['smm_final_price'] = $som_price;
        $st['smm_final_qty']   = $qty;
        $st['smm_final_link']  = $st['smm_link'];
        $st['smm_pay_amount']  = $pay_amount;
        $st['step'] = 'smm_choose_pay';
        save_step($chat_id, $st);
        exit;
    }

    // CheckCard orqali to'lov yaratish
    $pay_resp = $CheckCardPay->create_checkout($pay_amount);
    $pay_data = $pay_resp ? json_decode($pay_resp, true) : null;

    if (!$pay_data || ($pay_data['status'] ?? '') === 'error') {
        $err = $pay_data['message'] ?? 'API xatolik';
        if (stripos($err, 'pending') !== false) {
            sendMessage($chat_id, "⚠️ Bu miqdorda kutilayotgan to'lov mavjud.

💡 {$pay_amount} so'm o'rniga " . ($pay_amount + 500) . " so'm miqdorida qayta urinib ko'ring.");
        } else {
            sendMessage($chat_id, "⚠️ To'lov yaratishda xatolik: {$err}");
        }
        exit;
    }

    $pay_order_code = $pay_data['order'] ?? null;
    $pay_insert_id = $pay_data['insert_id'] ?? $pay_order_code;
    $pay_url_smm = $pay_data['pay_url'] ?? null;

    if (!$pay_order_code) {
        sendMessage($chat_id, "⚠️ To'lov yaratishda xatolik. Qayta urinib ko'ring.");
        exit;
    }

    // DB ga saqlash
    $smm_uid = intval($chat_id);
    $smm_svc_id = intval($st['smm_service_id']);
    $smm_svc_name = $st['smm_service_name'];
    $smm_link = $st['smm_link'];
    $smm_qty = intval($qty);
    $smm_price = intval($pay_amount);
    $smm_pay_order = $pay_order_code;
    $ins_smm = mysqli_prepare($connect, "INSERT INTO smm_orders (user_id, service_id, service_name, link, quantity, price, status, payment_order, payment_status) VALUES (?,?,?,?,?,?,'pending',?,'unpaid')");
    mysqli_stmt_bind_param($ins_smm, 'iissiis', $smm_uid, $smm_svc_id, $smm_svc_name, $smm_link, $smm_qty, $smm_price, $smm_pay_order);
    mysqli_stmt_execute($ins_smm);
    mysqli_stmt_close($ins_smm);

    clear_step($chat_id);

    $kb_smm = [];
    if ($pay_url_smm) $kb_smm[] = [['text' => "💳 To'lov sahifasini ochish", 'url' => $pay_url_smm]];
    $kb_smm[] = [['text' => "♻️ To'lovni tekshirish", 'callback_data' => "smm_check_pay={$pay_order_code}"]];
    $kb_smm[] = [['text' => "❌ Bekor qilish", 'callback_data' => "smm_cancel_pay={$pay_order_code}"]];

    sendMessage($chat_id, "<b>📱 SMM Buyurtma

📋 Buyurtma #{$pay_insert_id}
└ 📌 Xizmat: " . mb_substr($smm_svc_name, 0, 50) . "
└ 🔗 Link: <code>{$smm_link}</code>
└ 🔢 Miqdor: " . number_format($qty) . "
└ 💲 1000 ta narxi: " . number_format($rate_per_1000_som) . " so'm
└ 💰 Jami narx: " . number_format($som_price) . " so'm

💳 Karta: <code>5614683582279246</code>
💵 To'lov miqdori: <b><u>{$pay_amount} so'm</u></b>

⚠️ Aynan shu miqdorni o'tkazing!
To'lovdan so'ng '♻️ To'lovni tekshirish' tugmasini bosing.</b>", json_encode(['inline_keyboard' => $kb_smm], JSON_UNESCAPED_UNICODE));
    exit;
}

if ($from_id == $admin) {
$st = load_step($chat_id);

// ─── Kanal qo'shish ───────────────────────────────────────────────────────
if (!empty($st['step']) && $st['step'] === 'admin_add_channel') {
    $new_ch = trim($text);
    if (!preg_match('/^@[A-Za-z0-9_]{3,}$/', $new_ch)) {
        sendMessage($chat_id, "⚠️ Noto'g'ri format! Masalan: <code>@MyChannel</code>");
        exit;
    }
    $cfg_ch2 = settings($connect);
    $existing = trim($cfg_ch2['channels'] ?? '');
    $channels_list = array_filter(array_map('trim', explode(',', $existing)));
    if (!in_array($new_ch, $channels_list)) {
        $channels_list[] = $new_ch;
    }
    $new_channels_str = implode(',', $channels_list);
    mysqli_query($connect, "UPDATE settings SET channels='" . mysqli_real_escape_string($connect, $new_channels_str) . "' WHERE id=1");
    clear_step($chat_id);
    sendMessage($chat_id, "✅ <b>{$new_ch}</b> kanali qo'shildi!

Jami kanallar: <code>{$new_channels_str}</code>");
    exit;
}

// ─── Broadcast ─────────────────────────────────────────────────────────────
if (!empty($st['step']) && $st['step'] === 'admin_broadcast') {
    clear_step($chat_id);
    $all_users = mysqli_query($connect, "SELECT user_id FROM users");
    $sent = 0; $failed = 0;
    sendMessage($chat_id, "⏳ <b>Xabar yuborilmoqda...</b>");
    while ($usr = mysqli_fetch_assoc($all_users)) {
        $res = sendMessage($usr['user_id'], $text);
        if ($res && !empty($res['ok'])) { $sent++; } else { $failed++; }
        usleep(50000); // 50ms kutish (flood limit)
    }
    sendMessage($chat_id, "✅ <b>Xabar yuborildi!</b>

📤 Muvaffaqiyatli: <b>{$sent}</b>
❌ Xato: <b>{$failed}</b>");
    exit;
}

if (!empty($st['step']) && $st['step'] === 'edit_star_price') {
if (is_numeric($text) && $text > 0) {
$stmt = mysqli_prepare($connect, "UPDATE settings SET star_price = ? WHERE id = 1");
mysqli_stmt_bind_param($stmt, 'i', $text);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
sendMessage($chat_id, "✅ Stars narxi {$text} so'm ga o'zgartirildi!");
clear_step($chat_id);
} else {
sendMessage($chat_id, "⚠️ Iltimos to'g'ri raqam kiriting!");
}
exit;
}

if (!empty($st['step']) && $st['step'] === 'edit_premium_1') {
if (is_numeric($text) && $text > 0) {
$stmt = mysqli_prepare($connect, "UPDATE settings SET premium_1_month = ? WHERE id = 1");
mysqli_stmt_bind_param($stmt, 'i', $text);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
sendMessage($chat_id, "✅ Premium 1 oy narxi {$text} so'm ga o'zgartirildi!");
clear_step($chat_id);
} else {
sendMessage($chat_id, "⚠️ Iltimos to'g'ri raqam kiriting!");
}
exit;
}

if (!empty($st['step']) && $st['step'] === 'edit_premium_3') {
if (is_numeric($text) && $text > 0) {
$stmt = mysqli_prepare($connect, "UPDATE settings SET premium_3_month = ? WHERE id = 1");
mysqli_stmt_bind_param($stmt, 'i', $text);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
sendMessage($chat_id, "✅ Premium 3 oy narxi {$text} so'm ga o'zgartirildi!");
clear_step($chat_id);
} else {
sendMessage($chat_id, "⚠️ Iltimos to'g'ri raqam kiriting!");
}
exit;
}

if (!empty($st['step']) && $st['step'] === 'edit_premium_6') {
if (is_numeric($text) && $text > 0) {
$stmt = mysqli_prepare($connect, "UPDATE settings SET premium_6_month = ? WHERE id = 1");
mysqli_stmt_bind_param($stmt, 'i', $text);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
sendMessage($chat_id, "✅ Premium 6 oy narxi {$text} so'm ga o'zgartirildi!");
clear_step($chat_id);
} else {
sendMessage($chat_id, "⚠️ Iltimos to'g'ri raqam kiriting!");
}
exit;
}

if (!empty($st['step']) && $st['step'] === 'edit_premium_12') {
if (is_numeric($text) && $text > 0) {
$stmt = mysqli_prepare($connect, "UPDATE settings SET premium_12_month = ? WHERE id = 1");
mysqli_stmt_bind_param($stmt, 'i', $text);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
sendMessage($chat_id, "✅ Premium 12 oy narxi {$text} so'm ga o'zgartirildi!");
clear_step($chat_id);
} else {
sendMessage($chat_id, "⚠️ Iltimos to'g'ri raqam kiriting!");
}
exit;
}

if (!empty($st['step']) && $st['step'] === 'edit_smm_markup') {
if (is_numeric($text) && intval($text) >= 0 && intval($text) <= 500) {
$val = intval($text);
$stmt = mysqli_prepare($connect, "UPDATE settings SET smm_markup = ? WHERE id = 1");
mysqli_stmt_bind_param($stmt, 'i', $val);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
sendMessage($chat_id, "✅ SMM Panel markup <b>{$val}%</b> ga o'zgartirildi!

Endi barcha SMM xizmatlari narxiga +{$val}% qo'shimcha qo'shiladi.");
clear_step($chat_id);
} else {
sendMessage($chat_id, "⚠️ 0 dan 500 gacha son kiriting!");
}
exit;
}

if (!empty($st['step']) && $st['step'] === 'edit_smm_rate') {
if (is_numeric($text) && intval($text) >= 1000) {
$val = intval($text);
$stmt = mysqli_prepare($connect, "UPDATE settings SET smm_usd_rate = ? WHERE id = 1");
mysqli_stmt_bind_param($stmt, 'i', $val);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
sendMessage($chat_id, "✅ USD kursi <b>{$val} so'm</b> ga o'zgartirildi!");
clear_step($chat_id);
} else {
sendMessage($chat_id, "⚠️ Kamida 1000 so'm bo'lishi kerak!");
}
exit;
}
}
}

if ($callback_data && mb_stripos($callback_data, "cancelpay=") !== false) {
$orderId = explode("=", $callback_data, 2)[1];
if (!$orderId) {
answerCallback($callback_id, "❌ Bekor qilinadigan to'lov topilmadi!", true);
exit;
}
$stmt = mysqli_prepare($connect, "SELECT * FROM review WHERE order_id = ? AND status = 'unpaid' LIMIT 1");
mysqli_stmt_bind_param($stmt, 's', $orderId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$row) {
answerCallback($callback_id, "❌ Bekor qilinadigan to'lov topilmadi!", true);
exit;
}

$stmt = mysqli_prepare($connect, "UPDATE review SET status = 'cancel', date = NOW() WHERE order_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 's', $orderId);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

deleteMessage($chat_id, $message_id);

sendMessage($row['user_id'], "❌ <b>{$row['price']} so'mlik to'lov bekor qilindi!</b>\n\n🎯 Stars: <b>{$row['quantity']}</b>\n👤 Qabul qiluvchi: <b>{$row['username']}</b>");
answerCallback($callback_id, "To'lov bekor qilindi.", true);
exit;
}

if ($callback_data && mb_stripos($callback_data, "cancelpremium=") !== false) {
$orderId = explode("=", $callback_data, 2)[1];
if (!$orderId) {
answerCallback($callback_id, "❌ Bekor qilinadigan to'lov topilmadi!", true);
exit;
}
$stmt = mysqli_prepare($connect, "SELECT * FROM premium_orders WHERE order_id = ? AND status = 'unpaid' LIMIT 1");
mysqli_stmt_bind_param($stmt, 's', $orderId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$row) {
answerCallback($callback_id, "❌ Bekor qilinadigan to'lov topilmadi!", true);
exit;
}

$stmt = mysqli_prepare($connect, "UPDATE premium_orders SET status = 'cancel', date = NOW() WHERE order_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 's', $orderId);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

deleteMessage($chat_id, $message_id);

sendMessage($row['user_id'], "❌ <b>{$row['price']} so'mlik to'lov bekor qilindi!</b>\n\n🎯 Premium: <b>{$row['quantity']} oy</b>\n👤 Qabul qiluvchi: <b>{$row['username']}</b>");
answerCallback($callback_id, "To'lov bekor qilindi.", true);
exit;
}

// ─── To'lov usulini tanlash ───────────────────────────────────────────────────
function offer_payment_method($chat_id, $connect, $type) {
    $st = load_step($chat_id);
    
    // Narxni hisoblash
    if ($type === 'stars') {
        $stars    = intval($st['stars'] ?? 0);
        $base_som = $stars * cfg($connect)['star_price'];
    } else {
        $base_som = intval($st['price'] ?? 0);
    }
    
    $bal = get_balance($chat_id, $connect);
    
    // 1000 so'mdan kam bo'lsa faqat balansdan to'lash
    if ($base_som < 1000) {
        if ($bal >= $base_som) {
            // Balansdan avtomatik to'lash
            pay_with_balance($chat_id, $connect, $type);
        } else {
            $needed = $base_som - $bal;
            sendMessage($chat_id, "⚠️ <b>1,000 so'mdan past buyurtmalar faqat balansdan to'lanadi!</b>

💰 Kerakli: " . number_format($base_som) . " so'm
💳 Balansingiz: " . number_format($bal) . " so'm

❗ Balansni kamida <b>" . number_format($needed) . " so'm</b> ga to'ldiring.", json_encode(['inline_keyboard' => [
                [['text' => "➕ Balansni to'ldirish", 'callback_data' => "wallet_topup"]],
                [['text' => "🔙 Orqaga", 'callback_data' => "menu"]],
            ]], JSON_UNESCAPED_UNICODE));
        }
        return;
    }
    
    // 1000 so'm va undan yuqori — tanlash imkoniyati
    $kb_pay = [];
    if ($bal >= $base_som) {
        $kb_pay[] = [['text' => "💳 Balansdan to'lash (" . number_format($bal) . " so'm)", 'callback_data' => "pay_balance_{$type}"]];
    } elseif ($bal > 0) {
        $kb_pay[] = [['text' => "💳 Balansdan to'lash (yetarli emas: " . number_format($bal) . " so'm)", 'callback_data' => "wallet_topup"]];
    }
    $kb_pay[] = [['text' => "🏦 Karta orqali to'lash", 'callback_data' => "pay_card_{$type}"]];
    $kb_pay[] = [['text' => "🔙 Orqaga", 'callback_data' => "menu"]];
    
    $type_txt = $type === 'stars' ? "⭐ Stars" : "👑 Premium";
    sendMessage($chat_id, "<b>💳 To'lov usulini tanlang</b>

{$type_txt}
💰 Narxi: <b>" . number_format($base_som) . " so'm</b>

💳 Balansingiz: <b>" . number_format($bal) . " so'm</b>", json_encode(['inline_keyboard' => $kb_pay], JSON_UNESCAPED_UNICODE));
}

function pay_with_balance($chat_id, $connect, $type) {
    $st = load_step($chat_id);
    if ($type === 'stars') {
        $stars    = intval($st['stars'] ?? 0);
        $receiver = $st['receiver'] ?? '';
        $amount   = $stars * cfg($connect)['star_price'];
    } else {
        $months   = intval($st['months'] ?? 0);
        $receiver = $st['receiver'] ?? '';
        $amount   = intval($st['price'] ?? 0);
    }
    
    // Balansdan ayirish
    $deducted = deduct_balance($chat_id, $amount, $connect);
    if (!$deducted) {
        $bal = get_balance($chat_id, $connect);
        sendMessage($chat_id, "❌ <b>Balans yetarli emas!</b>

💰 Kerakli: " . number_format($amount) . " so'm
💳 Balans: " . number_format($bal) . " so'm", json_encode(['inline_keyboard' => [[['text' => "➕ Balansni to'ldirish", 'callback_data' => "wallet_topup"]]]], JSON_UNESCAPED_UNICODE));
        return;
    }
    
    $new_bal = get_balance($chat_id, $connect);
    
    // DB ga saqlash va xizmat yuborish
    if ($type === 'stars') {
        $uid = intval($chat_id);
        $stmt = mysqli_prepare($connect, "INSERT INTO review (user_id, order_id, price, status, quantity, username, payment_method) VALUES (?,?,?,'paid',?,?,'balance')");
        $oid = 'BAL_' . time();
        mysqli_stmt_bind_param($stmt, 'isiis', $uid, $oid, $amount, $stars, $receiver);
        mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
        $rid = mysqli_insert_id($connect);
        
        sendMessage($chat_id, "✅ <b>Balansdan to'landi!</b>
💰 " . number_format($amount) . " so'm
💳 Qolgan balans: <b>" . number_format($new_bal) . " so'm</b>

📦 Xizmat bajarilmoqda...");
        
        // Stars API
        $api_url = "https://domen.uz/stars.php?username=" . urlencode($receiver) . "&starssoni=" . urlencode($stars);
        $ch_a = curl_init($api_url); curl_setopt($ch_a, CURLOPT_RETURNTRANSFER, true);
        $api_resp = curl_exec($ch_a); $http_code = curl_getinfo($ch_a, CURLINFO_HTTP_CODE); curl_close($ch_a);
        $delivered = ($api_resp !== false && $http_code >= 200 && $http_code < 300);
        
        if ($delivered) {
            $upd = mysqli_prepare($connect, "UPDATE review SET status='completed' WHERE id=? LIMIT 1");
            mysqli_stmt_bind_param($upd, 'i', $rid); mysqli_stmt_execute($upd); mysqli_stmt_close($upd);
            sendMessage($chat_id, "⭐️ <b>{$stars} stars muvaffaqiyatli yuborildi: {$receiver}</b>
🎉 Rahmat!");
        } else {
            add_balance($chat_id, $amount, $connect); // qaytarish
            $upd = mysqli_prepare($connect, "UPDATE review SET status='failed_delivery' WHERE id=? LIMIT 1");
            mysqli_stmt_bind_param($upd, 'i', $rid); mysqli_stmt_execute($upd); mysqli_stmt_close($upd);
            sendMessage($chat_id, "⚠️ Xizmat yuborishda muammo. Balans qaytarildi. Admin bilan bog'laning.");
        }
    } else {
        $uid = intval($chat_id);
        $stmt = mysqli_prepare($connect, "INSERT INTO premium_orders (user_id, order_id, price, status, quantity, username, payment_method) VALUES (?,?,?,'paid',?,?,'balance')");
        $oid = 'BAL_' . time();
        mysqli_stmt_bind_param($stmt, 'isiis', $uid, $oid, $amount, $months, $receiver);
        mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
        $rid = mysqli_insert_id($connect);
        
        sendMessage($chat_id, "✅ <b>Balansdan to'landi!</b>
💰 " . number_format($amount) . " so'm
💳 Qolgan balans: <b>" . number_format($new_bal) . " so'm</b>

📦 Xizmat bajarilmoqda...");
        
        // Premium API
        $api_url = "https://domen.uz/premium.php?username=" . urlencode($receiver) . "&premiumoyi=" . urlencode($months);
        $ch_a = curl_init($api_url); curl_setopt($ch_a, CURLOPT_RETURNTRANSFER, true);
        $api_resp = curl_exec($ch_a); $http_code = curl_getinfo($ch_a, CURLINFO_HTTP_CODE); curl_close($ch_a);
        $delivered = ($api_resp !== false && $http_code >= 200 && $http_code < 300);
        
        if ($delivered) {
            $upd = mysqli_prepare($connect, "UPDATE premium_orders SET status='completed' WHERE id=? LIMIT 1");
            mysqli_stmt_bind_param($upd, 'i', $rid); mysqli_stmt_execute($upd); mysqli_stmt_close($upd);
            sendMessage($chat_id, "👑 <b>{$months} oylik Premium muvaffaqiyatli yuborildi: {$receiver}</b>
🎉 Rahmat!");
        } else {
            add_balance($chat_id, $amount, $connect); // qaytarish
            $upd = mysqli_prepare($connect, "UPDATE premium_orders SET status='failed_delivery' WHERE id=? LIMIT 1");
            mysqli_stmt_bind_param($upd, 'i', $rid); mysqli_stmt_execute($upd); mysqli_stmt_close($upd);
            sendMessage($chat_id, "⚠️ Xizmat yuborishda muammo. Balans qaytarildi. Admin bilan bog'laning.");
        }
    }
    clear_step($chat_id);
}

function process_order($chat_id, $connect, $card_number) {
$step = load_step($chat_id);
if (empty($step['stars']) || empty($step['receiver'])) {
sendMessage($chat_id, "⚠️ Buyurtma to'liq emas. Iltimos miqdor va username kiriting.");
return;
}
sendMessage($chat_id, "<b>💳 To'lov yaratilmoqda — CheckCard orqali. Iltimos kuting...</b>");
process_checkcard_payment($chat_id, $connect);
}

function process_premium_order($chat_id, $connect, $card_number) {
$step = load_step($chat_id);
if (empty($step['months']) || empty($step['receiver'])) {
sendMessage($chat_id, "⚠️ Buyurtma to'liq emas. Iltimos muddat va username kiriting.");
return;
}
sendMessage($chat_id, "<b>💳 To'lov yaratilmoqda — CheckCard orqali. Iltimos kuting...</b>");
process_premium_checkcard_payment($chat_id, $connect);
}

function process_checkcard_payment($chat_id, $connect) {
global $CheckCardPay;
$step = load_step($chat_id);
$stars = intval($step['stars'] ?? 0);
$receiver = $step['receiver'] ?? '';
$base_amount = $stars * settings($connect)['star_price'];
$rand_num = rand(1, 100);
$amount = $base_amount + $rand_num;

$resp = $CheckCardPay->create_checkout($amount);
if ($resp === false) {
sendMessage($chat_id, "⚠️ To'lov yaratishda xatolik. Iltimos keyinroq urinib ko'ring.");
return;
}

$response = json_decode($resp, true);
if (!$response) {
sendMessage($chat_id, "⚠️ CheckCard API dan noaniq javob olindi.");
error_log("CheckCard create invalid json: " . $resp);
return;
}
if (isset($response['status']) && $response['status'] === 'error') {
$msg = $response['message'] ?? 'CheckCard xatolik';
if ($msg === "There is a pending payment for this amount.") {
sendMessage($chat_id, "⚠️ Ushbu miqdorda hali yakunlanmagan to'lov mavjud.\n\n💡 Masalan: " . ($amount + 500) . " so'm miqdorida qayta urinib ko'ring.");
} else {
sendMessage($chat_id, "⚠️ " . $msg);
}
return;
}

$order_code = $response['order'] ?? ($response['order_code'] ?? null);
$insert_id  = $response['insert_id'] ?? null;
$pay_url    = $response['pay_url'] ?? null;

if (!$order_code) {
sendMessage($chat_id, "⚠️ CheckCard javobida order topilmadi.");
error_log("CheckCard response missing order: " . $resp);
return;
}

$stmt = mysqli_prepare($connect, "INSERT INTO review (user_id, order_id, price, status, quantity, username, payment_method, date) VALUES (?, ?, ?, 'unpaid', ?, ?, 'checkcard', NOW())");
$user_id_q = intval($chat_id);
$order_q = $order_code;
$amount_q = intval($amount);
$stars_q = intval($stars);
$receiver_q = $receiver;
mysqli_stmt_bind_param($stmt, 'isiis', $user_id_q, $order_q, $amount_q, $stars_q, $receiver_q);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

$kb_rows = [];
if ($pay_url) {
    $kb_rows[] = [['text' => "💳 To'lov sahifasini ochish", 'url' => $pay_url]];
}
$kb_rows[] = [['text' => "♻️ To'lov tekshirish", 'callback_data' => "CheckCard_check={$order_code}"]];
$kb_rows[] = [['text' => "❌ Bekor qilish", 'callback_data' => "cancelpay={$order_code}"]];
$keyboard = json_encode(['inline_keyboard' => $kb_rows], JSON_UNESCAPED_UNICODE);

sendMessage($chat_id, "<b>💳 To'lov ma'lumotlari

📋 Buyurtma #" . htmlspecialchars($insert_id ?? $order_code) . "
└ 🎯 Stars: {$stars} ⭐️
└ 💰 Narxi: {$base_amount} so'm
└ 👤 Username: {$receiver}

💳 Karta ma'lumotlari:
└ 🏦 Karta raqami: <code>5614683582279246</code>
└ 💵 To'lov miqdori: {$amount} so'm

⚠️ Muhim: Ko'rsatilgan miqdorni to'lang: {$amount} so'm (aniq miqdorda)
📱 To'lov qilganingizdan so'ng, botda '♻️ To'lov tekshirish' tugmasini bosing</b>", $keyboard);

clear_step($chat_id);
}

function process_premium_checkcard_payment($chat_id, $connect) {
global $CheckCardPay;
$step = load_step($chat_id);
$months = intval($step['months'] ?? 0);
$receiver = $step['receiver'] ?? '';
$base_amount = intval($step['price'] ?? 0);
$rand_num = rand(1, 100);
$amount = $base_amount + $rand_num;

$resp = $CheckCardPay->create_checkout($amount);
if ($resp === false) {
sendMessage($chat_id, "⚠️ To'lov yaratishda xatolik. Iltimos keyinroq urinib ko'ring.");
return;
}

$response = json_decode($resp, true);
if (!$response) {
sendMessage($chat_id, "⚠️ CheckCard API dan noaniq javob olindi.");
error_log("CheckCard create invalid json: " . $resp);
return;
}
if (isset($response['status']) && $response['status'] === 'error') {
$msg = $response['message'] ?? 'CheckCard xatolik';
if ($msg === "There is a pending payment for this amount.") {
sendMessage($chat_id, "⚠️ Ushbu miqdorda hali yakunlanmagan to'lov mavjud.\n\n💡 Masalan: " . ($amount + 500) . " so'm miqdorida qayta urinib ko'ring.");
} else {
sendMessage($chat_id, "⚠️ " . $msg);
}
return;
}

$order_code = $response['order'] ?? ($response['order_code'] ?? null);
$insert_id  = $response['insert_id'] ?? null;
$pay_url    = $response['pay_url'] ?? null;

if (!$order_code) {
sendMessage($chat_id, "⚠️ CheckCard javobida order topilmadi.");
error_log("CheckCard response missing order: " . $resp);
return;
}

$stmt = mysqli_prepare($connect, "INSERT INTO premium_orders (user_id, order_id, price, status, quantity, username, payment_method, date) VALUES (?, ?, ?, 'unpaid', ?, ?, 'checkcard', NOW())");
$user_id_q = intval($chat_id);
$order_q = $order_code;
$amount_q = intval($amount);
$months_q = intval($months);
$receiver_q = $receiver;
mysqli_stmt_bind_param($stmt, 'isiis', $user_id_q, $order_q, $amount_q, $months_q, $receiver_q);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

$kb_rows = [];
if ($pay_url) {
    $kb_rows[] = [['text' => "💳 To'lov sahifasini ochish", 'url' => $pay_url]];
}
$kb_rows[] = [['text' => "♻️ To'lov tekshirish", 'callback_data' => "CheckCard_premium_check={$order_code}"]];
$kb_rows[] = [['text' => "❌ Bekor qilish", 'callback_data' => "cancelpremium={$order_code}"]];
$keyboard = json_encode(['inline_keyboard' => $kb_rows], JSON_UNESCAPED_UNICODE);

sendMessage($chat_id, "<b>💳 To'lov ma'lumotlari

📋 Buyurtma #" . htmlspecialchars($insert_id ?? $order_code) . "
└ 🎯 Premium: {$months} oy
└ 💰 Narxi: {$base_amount} so'm
└ 👤 Username: {$receiver}

💳 Karta ma'lumotlari:
└ 🏦 Karta raqami: <code>9860036625185040</code>
└ 💵 To'lov miqdori: {$amount} so'm

⚠️ Muhim: Ko'rsatilgan miqdorni to'lang: {$amount} so'm (aniq miqdorda)
📱 To'lov qilganingizdan so'ng, botda '♻️ To'lov tekshirish' tugmasini bosing</b>", $keyboard);

clear_step($chat_id);
}

if ($callback_data && strpos($callback_data, "CheckCard_check=") === 0) {
$order_code = explode("=", $callback_data)[1];

$stmt = mysqli_prepare($connect, "SELECT * FROM review WHERE order_id = ? AND payment_method = 'checkcard' LIMIT 1");
mysqli_stmt_bind_param($stmt, 's', $order_code);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

$response = $CheckCardPay->check_payment($order_code);
if ($response === false) {
answerCallback($callback_id, "⚠️ To'lovni tekshirishda xatolik.", true);
exit;
}

$result = json_decode($response, true);
if (!$result || ($result['status'] ?? '') !== 'success') {
answerCallback($callback_id, "❌ Buyurtma topilmadi yoki API xatolik berdi!", true);
exit;
}

$order_data = $result['data'] ?? [];
$summa = (int)($order_data['amount'] ?? 0);
$status = $order_data['status'] ?? '';
$sav = date("H:i:s | Y-m-d");

if ($status === "paid") {
if ($row) {
$stmt = mysqli_prepare($connect, "UPDATE review SET status = 'paid', date = NOW() WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $row['id']);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

$user_id = $row['user_id'];
$quantity = intval($row['quantity']);
$username_target = $row['username'];
$price = $row['price'];
$review_id = $row['id'];
}

if ($chat_id && $message_id) {
@deleteMessage($chat_id, $message_id);
}

sendMessage($from_id, "<b>✅ To'lov muvaffaqiyatli qabul qilindi.\n💰 Summa: {$summa} so'm\n📱 Buyurtma hozir bajarilmoqda...</b>");

if (!empty($review_id)) {
$stmt = mysqli_prepare($connect, "UPDATE review SET status = 'completed', date = NOW() WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $review_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
}

$quantity = isset($row['quantity']) ? intval($row['quantity']) : 0;
$username_target = !empty($row['username']) ? $row['username'] : 'N/A';
$price = !empty($row['price']) ? $row['price'] : intval($summa);
$logText = "<b>✅ Buyurtma bajarildi (CheckCard)</b>\n\n🆔 <b>OrderCode:</b> {$order_code}\n👤 <b>Foydalanuvchi (chat_id):</b> {$user_id}\n📛 <b>Username:</b> {$username_target}\n⭐ <b>Stars:</b> {$quantity}\n💰 <b>Narx:</b> {$price} so'm\n📝 <b>OrderID (review.id):</b> " . ($review_id ?? 'N/A');
$logs_chat = settings($connect)['logs'];
sendMessage($logs_chat, $logText);
sendMessage(CHANNEL_TO_JOIN, $logText);

if (!empty($quantity) && !empty($username_target)) {
$username_for_api = $username_target;
$api_url = "https://domen.uz/stars.php?username=" . urlencode($username_for_api) . "&starssoni=" . urlencode($quantity);

$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$api_resp = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$delivered = ($api_resp !== false && $http_code >= 200 && $http_code < 300);

if ($delivered) {
$stmt = mysqli_prepare($connect, "UPDATE review SET status = 'completed', date = NOW() WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $review_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

sendMessage($from_id, "<b>⭐️ {$quantity} stars muvaffaqiyatli yuborildi: {$username_target}\n🎉 Rahmat!</b>");
} else {
$errText = "<b>⚠️ Stars yetkazishda xatolik yuz berdi.\nOrderCode: {$order_code}\nFoydalanuvchi: {$from_id}\nUsername: {$username_target}\nStars: {$quantity}\nAPI_http_code: {$http_code}\nAPI_resp: " . htmlspecialchars(substr($api_resp ?? '', 0, 1000)) . "</b>";
sendMessage($from_id, "<b>⚠️ To'lov qabul qilindi, lekin stars yetkazishda muammo yuz berdi. Admin bilan bog'laning yoki keyin qayta tekshiring.</b>");
sendMessage($logs_chat, $errText);
sendMessage(CHANNEL_TO_JOIN, $errText);
if (!empty($admin)) sendMessage($admin, $errText);
$stmt = mysqli_prepare($connect, "UPDATE review SET status = 'failed_delivery', date = NOW() WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $review_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
}
}

answerCallback($callback_id, "✅ To'lov tekshirildi va ishlov berilmoqda.", true);
} elseif ($status === "cancel") {
if ($row) {
$stmt = mysqli_prepare($connect, "UPDATE review SET status = 'cancel', date = NOW() WHERE order_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 's', $order_code);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
}

deleteMessage($chat_id, $message_id);
sendMessage($from_id, "❌ Sizning {$summa} so'mlik to'lovingiz bekor qilindi!");
answerCallback($callback_id, "To'lov bekor qilindi.", true);
} elseif ($status === "pending") {
answerCallback($callback_id, "❌ To'lov hali amalga oshirilmagan.", true);
} else {
answerCallback($callback_id, "⚠️ To'lov holati: $status", true);
}
exit;
}

if ($callback_data && strpos($callback_data, "CheckCard_premium_check=") === 0) {
$order_code = explode("=", $callback_data)[1];
$stmt = mysqli_prepare($connect, "SELECT * FROM premium_orders WHERE order_id = ? AND payment_method = 'checkcard' LIMIT 1");
mysqli_stmt_bind_param($stmt, 's', $order_code);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

$response = $CheckCardPay->check_payment($order_code);
if ($response === false) {
answerCallback($callback_id, "⚠️ To'lovni tekshirishda xatolik.", true);
exit;
}

$result = json_decode($response, true);
if (!$result || ($result['status'] ?? '') !== 'success') {
answerCallback($callback_id, "❌ Buyurtma topilmadi yoki API xatolik berdi!", true);
exit;
}

$order_data = $result['data'] ?? [];
$summa = (int)($order_data['amount'] ?? 0);
$status = $order_data['status'] ?? '';
$sav = date("H:i:s | Y-m-d");

if ($status === "paid") {
if ($row) {
$stmt = mysqli_prepare($connect, "UPDATE premium_orders SET status = 'paid', date = NOW() WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $row['id']);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

$user_id = $row['user_id'];
$quantity = intval($row['quantity']);
$username_target = $row['username'];
$price = $row['price'];
$review_id = $row['id'];
}

if ($chat_id && $message_id) {
@deleteMessage($chat_id, $message_id);
}

sendMessage($from_id, "<b>✅ To'lov muvaffaqiyatli qabul qilindi.\n💰 Summa: {$summa} so'm\n📱 Buyurtma hozir bajarilmoqda...</b>");

if (!empty($review_id)) {
$stmt = mysqli_prepare($connect, "UPDATE premium_orders SET status = 'completed', date = NOW() WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $review_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
}

$quantity = isset($row['quantity']) ? intval($row['quantity']) : 0;
$username_target = !empty($row['username']) ? $row['username'] : 'N/A';
$price = !empty($row['price']) ? $row['price'] : intval($summa);
$logText = "<b>✅ Premium buyurtma bajarildi (CheckCard)</b>\n\n🆔 <b>OrderCode:</b> {$order_code}\n👤 <b>Foydalanuvchi (chat_id):</b> {$user_id}\n📛 <b>Username:</b> {$username_target}\n👑 <b>Premium:</b> {$quantity} oy\n💰 <b>Narx:</b> {$price} so'm\n📝 <b>OrderID (premium_orders.id):</b> " . ($review_id ?? 'N/A');
$logs_chat = settings($connect)['logs'];
sendMessage($logs_chat, $logText);
sendMessage(CHANNEL_TO_JOIN, $logText);

if (!empty($quantity) && !empty($username_target)) {
$username_for_api = $username_target;
$api_url = "https://domen.uz/premium.php?username=" . urlencode($username_for_api) . "&premiumoyi=" . urlencode($quantity);
$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$api_resp = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$delivered = ($api_resp !== false && $http_code >= 200 && $http_code < 300);
if ($delivered) {
$stmt = mysqli_prepare($connect, "UPDATE premium_orders SET status = 'completed', date = NOW() WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $review_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
sendMessage($from_id, "<b>👑 {$quantity} oylik Premium muvaffaqiyatli yuborildi: {$username_target}\n🎉 Rahmat!</b>");
} else {
$errText = "<b>⚠️ Premium yetkazishda xatolik yuz berdi.\nOrderCode: {$order_code}\nFoydalanuvchi: {$from_id}\nUsername: {$username_target}\nPremium: {$quantity} oy\nAPI_http_code: {$http_code}\nAPI_resp: " . htmlspecialchars(substr($api_resp ?? '', 0, 1000)) . "</b>";
sendMessage($from_id, "<b>⚠️ To'lov qabul qilindi, lekin Premium yetkazishda muammo yuz berdi. Admin bilan bog'laning yoki keyin qayta tekshiring.</b>");
sendMessage($logs_chat, $errText);
sendMessage(CHANNEL_TO_JOIN, $errText);
if (!empty($admin)) sendMessage($admin, $errText);
$stmt = mysqli_prepare($connect, "UPDATE premium_orders SET status = 'failed_delivery', date = NOW() WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $review_id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
}
}

answerCallback($callback_id, "✅ To'lov tekshirildi va ishlov berilmoqda.", true);
} elseif ($status === "cancel") {
if ($row) {
$stmt = mysqli_prepare($connect, "UPDATE premium_orders SET status = 'cancel', date = NOW() WHERE order_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 's', $order_code);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);
}

deleteMessage($chat_id, $message_id);
sendMessage($from_id, "❌ Sizning {$summa} so'mlik to'lovingiz bekor qilindi!");
answerCallback($callback_id, "To'lov bekor qilindi.", true);
} elseif ($status === "pending") {
answerCallback($callback_id, "❌ To'lov hali amalga oshirilmagan.", true);
} else {
answerCallback($callback_id, "⚠️ To'lov holati: $status", true);
}
exit;
}

exit;
?>
