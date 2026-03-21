<?php
ob_start();
error_reporting(E_ALL);
date_default_timezone_set('Asia/Tashkent');

define("API_KEY", '8663180438:AAFKDMYpbmvVhqHFc2IMZre12rpaU03AbEw'); // Telegram Bot Token
$admin = 6365371142; // Admin Telegram ID

define("DB_SERVER",   "autorack.proxy.rlwy.net");
define("DB_USERNAME", "root");
define("DB_PASSWORD", "SngtdKxJGJMafHfetMzLBszTQwMprNwi");
define("DB_NAME",     "railway");
define("DB_PORT",     57444);

define('CHECKCARD_SHOP_ID',  '647282');  // @CheckCardUz_bot dan olingan
define('CHECKCARD_SHOP_KEY', '884UESPA3H'); // @CheckCardUz_bot dan olingan
define('CHANNEL_TO_JOIN',    '@Nitesms');

$stars_card   = "5614683582279246";
$premium_card = "5614683582279246";

// ─── DB ────────────────────────────────────────────────────────────────────
$connect = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME, DB_PORT);
if (!$connect) { error_log("DB connection failed: " . mysqli_connect_error()); exit("DB ga ulanishda xato!"); }
mysqli_set_charset($connect, "utf8mb4");

// ─── CheckCard ─────────────────────────────────────────────────────────────
class CheckCardPay {
    private $shop_id;
    private $shop_key;

    public function __construct($shop_id, $shop_key) {
        $this->shop_id  = $shop_id;
        $this->shop_key = $shop_key;
    }

    public function create($amount) {
        // 2-hujjatdagi namuna bilan bir xil: GET so'rov
        $url = "https://checkcard.uz/api?method=create&shop_id=" . urlencode($this->shop_id) . "&shop_key=" . urlencode($this->shop_key) . "&amount=" . intval($amount);
        $r = @file_get_contents($url);
        if ($r === false) { error_log("CheckCard create failed, amount=$amount"); return false; }
        return $r;
    }

    public function check($order_code) {
        $url = "https://checkcard.uz/api?" . http_build_query([
            'method' => 'check',
            'order'  => $order_code,
        ]);
        $r = @file_get_contents($url);
        if ($r === false) { error_log("CheckCard check failed, order=$order_code"); return false; }
        return $r;
    }

    public function cancel($order_code) {
        $url = "https://checkcard.uz/api?" . http_build_query([
            'method' => 'cancel',
            'order'  => $order_code,
        ]);
        $r = @file_get_contents($url);
        if ($r === false) { error_log("CheckCard cancel failed, order=$order_code"); return false; }
        return $r;
    }
}
$CC = new CheckCardPay(CHECKCARD_SHOP_ID, CHECKCARD_SHOP_KEY);

// ─── Telegram helpers ───────────────────────────────────────────────────────
function bot($method, $data = []) {
    $ch = curl_init("https://api.telegram.org/bot" . API_KEY . "/$method");
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POSTFIELDS => $data]);
    $res = curl_exec($ch);
    if ($res === false) { error_log("TG curl error: " . curl_error($ch)); curl_close($ch); return false; }
    curl_close($ch);
    return json_decode($res, true);
}

function sendMessage($chat_id, $text, $kb = null) {
    $d = ['chat_id' => $chat_id, 'text' => $text, 'parse_mode' => 'HTML', 'disable_web_page_preview' => true];
    if ($kb) $d['reply_markup'] = $kb;
    return bot('sendMessage', $d);
}

function sendAnimation($chat_id, $animation, $caption = null, $kb = null) {
    $d = ['chat_id' => $chat_id, 'animation' => $animation, 'parse_mode' => 'HTML'];
    if ($caption)  $d['caption']      = $caption;
    if ($kb)       $d['reply_markup'] = $kb;
    return bot('sendAnimation', $d);
}

function editMessage($chat_id, $msg_id, $text, $kb = null) {
    $d = ['chat_id' => $chat_id, 'message_id' => $msg_id, 'text' => $text, 'parse_mode' => 'HTML', 'disable_web_page_preview' => true];
    if ($kb) $d['reply_markup'] = $kb;
    return bot('editMessageText', $d);
}

function deleteMessage($chat_id, $msg_id) {
    if (!$chat_id || !$msg_id) return false;
    return bot('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $msg_id]);
}

function answerCallback($cb_id, $text = '', $alert = false) {
    if (!$cb_id) return false;
    return bot('answerCallbackQuery', ['callback_query_id' => $cb_id, 'text' => $text, 'show_alert' => $alert]);
}

// ─── Step (session) ─────────────────────────────────────────────────────────
function step_file($chat_id) { return __DIR__ . "/step/{$chat_id}.step"; }
function save_step($chat_id, $data) {
    if (!is_dir(__DIR__ . '/step')) mkdir(__DIR__ . '/step', 0755, true);
    file_put_contents(step_file($chat_id), json_encode($data, JSON_UNESCAPED_UNICODE));
}
function load_step($chat_id) {
    $f = step_file($chat_id);
    if (!file_exists($f)) return [];
    $c = json_decode(file_get_contents($f), true);
    return is_array($c) ? $c : [];
}
function clear_step($chat_id) {
    $f = step_file($chat_id);
    if (file_exists($f)) unlink($f);
}

// ─── DB tables ──────────────────────────────────────────────────────────────
mysqli_query($connect, "CREATE TABLE IF NOT EXISTS `users` (
    `id`      INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT NOT NULL,
    `step`    TEXT,
    `date`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

mysqli_query($connect, "CREATE TABLE IF NOT EXISTS `review` (
    `id`             INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`        BIGINT,
    `order_id`       TEXT,
    `price`          INT,
    `status`         TEXT,
    `quantity`       INT,
    `username`       TEXT,
    `payment_method` VARCHAR(20) DEFAULT 'checkcard',
    `date`           TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

mysqli_query($connect, "CREATE TABLE IF NOT EXISTS `premium_orders` (
    `id`             INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`        BIGINT,
    `order_id`       TEXT,
    `price`          INT,
    `status`         TEXT,
    `quantity`       INT,
    `username`       TEXT,
    `payment_method` VARCHAR(20) DEFAULT 'checkcard',
    `date`           TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

mysqli_query($connect, "CREATE TABLE IF NOT EXISTS `settings` (
    `id`              INT PRIMARY KEY,
    `logs`            TEXT,
    `star_price`      INT,
    `premium_1_month` INT,
    `premium_3_month` INT,
    `premium_6_month` INT,
    `premium_12_month` INT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$chk = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as c FROM settings"));
if (intval($chk['c']) == 0) {
    $stmt = mysqli_prepare($connect, "INSERT INTO settings VALUES (1,?,?,?,?,?,?)");
    $logs = CHANNEL_TO_JOIN; $sp = 240; $p1 = 45000; $p3 = 165000; $p6 = 215000; $p12 = 360000;
    mysqli_stmt_bind_param($stmt, 'siiiii', $logs, $sp, $p1, $p3, $p6, $p12);
    mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
}

function cfg($connect) {
    $r = mysqli_fetch_assoc(mysqli_query($connect, "SELECT * FROM settings WHERE id=1 LIMIT 1"));
    return $r ?: ['logs' => CHANNEL_TO_JOIN, 'star_price' => 240, 'premium_1_month' => 45000, 'premium_3_month' => 165000, 'premium_6_month' => 215000, 'premium_12_month' => 360000];
}

// ─── Parse update ───────────────────────────────────────────────────────────
$update   = json_decode(file_get_contents('php://input'), true) ?: [];
if (empty($update)) exit;

$message       = $update['message'] ?? null;
$callback      = $update['callback_query'] ?? null;
$text          = $message['text'] ?? null;
$chat_id       = $message['chat']['id'] ?? ($callback['message']['chat']['id'] ?? null);
$message_id    = $message['message_id'] ?? ($callback['message']['message_id'] ?? null);
$from          = $message['from'] ?? ($callback['from'] ?? []);
$from_id       = $from['id'] ?? null;
$username      = $from['username'] ?? null;
$callback_data = $callback['data'] ?? null;
$callback_id   = $callback['id'] ?? null;

// ─── Subscription check ─────────────────────────────────────────────────────
function is_member($user_id) {
    $r = bot('getChatMember', ['chat_id' => CHANNEL_TO_JOIN, 'user_id' => $user_id]);
    if (!$r || empty($r['ok'])) return false;
    return in_array($r['result']['status'] ?? '', ['member', 'administrator', 'creator']);
}

function sub_prompt($chat_id) {
    $link = "https://t.me/" . ltrim(CHANNEL_TO_JOIN, '@');
    $kb   = json_encode(['inline_keyboard' => [
        [['text' => "🔔 Obuna bo'lish", 'url' => $link]],
        [['text' => "✅ Tekshirish",     'callback_data' => 'check_subscribe']],
    ]], JSON_UNESCAPED_UNICODE);
    sendMessage($chat_id, "<b>❗ Botdan foydalanish uchun kanalga obuna bo'ling:</b>", $kb);
}

if (!empty($from_id) && $from_id != $admin) {
    if ($callback_data === 'check_subscribe') {
        answerCallback($callback_id, is_member($from_id)
            ? "✅ Obuna bo'lgansiz. Davom etishingiz mumkin."
            : "❌ Hali obuna bo'lmadingiz.", true);
        exit;
    }
    if (!is_member($from_id)) { sub_prompt($chat_id); exit; }
}

// ─── Register user ──────────────────────────────────────────────────────────
if ($chat_id) {
    $st = mysqli_prepare($connect, "SELECT id FROM users WHERE user_id=? LIMIT 1");
    mysqli_stmt_bind_param($st, 's', $chat_id);
    mysqli_stmt_execute($st); mysqli_stmt_store_result($st);
    if (mysqli_stmt_num_rows($st) == 0) {
        $st2 = mysqli_prepare($connect, "INSERT INTO users (user_id) VALUES (?)");
        mysqli_stmt_bind_param($st2, 's', $chat_id);
        mysqli_stmt_execute($st2); mysqli_stmt_close($st2);
    }
    mysqli_stmt_close($st);
}

// ─── Main menu ──────────────────────────────────────────────────────────────
$menu = json_encode(['inline_keyboard' => [
    [['text' => "⭐️ Stars",              'callback_data' => "stars"],
     ['text' => "👑 Premium",            'callback_data' => "premium"]],
    [['text' => "📤 Do'stlarga ulashish", 'url' => "https://t.me/share/url?url=https://t.me/"]],
]], JSON_UNESCAPED_UNICODE);

// ─── /start ──────────────────────────────────────────────────────────────────
if ($text === "/start") {
    sendAnimation($chat_id, "https://t.me/PhotosForBots/146",
        "🎉 <b>Uz Give</b>ga xush kelibsiz!\n\n🎯 <b>Nima olasiz, tanlang:</b>", $menu);
    clear_step($chat_id);
    exit;
}

// ─── /admin ──────────────────────────────────────────────────────────────────
if ($text === "/admin" && $from_id == $admin) {
    $kb = json_encode(['inline_keyboard' => [
        [['text' => "📊 Statistika",           'callback_data' => "admin_stats"],
         ['text' => "💰 Narxlar",              'callback_data' => "admin_prices"]],
        [['text' => "📝 Loglar",               'callback_data' => "admin_logs"],
         ['text' => "👥 Foydalanuvchilar",     'callback_data' => "admin_users"]],
    ]], JSON_UNESCAPED_UNICODE);
    sendMessage($chat_id, "<b>🔧 Admin Panel</b>", $kb);
    exit;
}

// ─── Admin callbacks ─────────────────────────────────────────────────────────
$admin_back_kb = json_encode(['inline_keyboard' => [[['text' => "🔙 Orqaga", 'callback_data' => "admin_back"]]]], JSON_UNESCAPED_UNICODE);

if ($callback_data === "admin_back" && $from_id == $admin) {
    $kb = json_encode(['inline_keyboard' => [
        [['text' => "📊 Statistika",       'callback_data' => "admin_stats"],
         ['text' => "💰 Narxlar",          'callback_data' => "admin_prices"]],
        [['text' => "📝 Loglar",           'callback_data' => "admin_logs"],
         ['text' => "👥 Foydalanuvchilar", 'callback_data' => "admin_users"]],
    ]], JSON_UNESCAPED_UNICODE);
    editMessage($chat_id, $message_id, "<b>🔧 Admin Panel</b>", $kb);
    exit;
}

if ($callback_data === "admin_stats" && $from_id == $admin) {
    $s = mysqli_fetch_assoc(mysqli_query($connect,
        "SELECT (SELECT COUNT(*) FROM users) tu,
                (SELECT COUNT(*) FROM review WHERE status='completed') co,
                (SELECT COUNT(*) FROM premium_orders WHERE status='completed') cp,
                (SELECT IFNULL(SUM(price),0) FROM review WHERE status='completed') rs,
                (SELECT IFNULL(SUM(price),0) FROM premium_orders WHERE status='completed') ps"));
    $rev = $s['rs'] + $s['ps'];
    editMessage($chat_id, $message_id,
        "<b>📊 Statistika</b>\n\n👥 Foydalanuvchilar: <b>{$s['tu']}</b>\n⭐ Stars buyurtmalar: <b>{$s['co']}</b>\n👑 Premium buyurtmalar: <b>{$s['cp']}</b>\n💰 Jami daromad: <b>" . number_format($rev) . " so'm</b>",
        $admin_back_kb);
    exit;
}

if ($callback_data === "admin_prices" && $from_id == $admin) {
    $c = cfg($connect);
    $kb = json_encode(['inline_keyboard' => [
        [['text' => "⭐ Stars: {$c['star_price']} so'm",        'callback_data' => "ep_star"]],
        [['text' => "👑 1 oy: {$c['premium_1_month']} so'm",   'callback_data' => "ep_1"]],
        [['text' => "👑 3 oy: {$c['premium_3_month']} so'm",   'callback_data' => "ep_3"]],
        [['text' => "👑 6 oy: {$c['premium_6_month']} so'm",   'callback_data' => "ep_6"]],
        [['text' => "👑 12 oy: {$c['premium_12_month']} so'm", 'callback_data' => "ep_12"]],
        [['text' => "🔙 Orqaga", 'callback_data' => "admin_back"]],
    ]], JSON_UNESCAPED_UNICODE);
    editMessage($chat_id, $message_id, "<b>💰 Narxlarni o'zgartirish</b>\n\nO'zgartirish uchun tanlang:", $kb);
    exit;
}

if ($callback_data === "admin_logs" && $from_id == $admin) {
    $txt = "<b>📝 So'nggi buyurtmalar</b>\n\n<b>⭐ Stars:</b>\n";
    $rs  = mysqli_query($connect, "SELECT * FROM review ORDER BY date DESC LIMIT 10");
    while ($r = mysqli_fetch_assoc($rs)) $txt .= "• {$r['quantity']}⭐ — {$r['username']} — {$r['status']} — " . date('d.m.Y H:i', strtotime($r['date'])) . "\n";
    $txt .= "\n<b>👑 Premium:</b>\n";
    $rp  = mysqli_query($connect, "SELECT * FROM premium_orders ORDER BY date DESC LIMIT 10");
    while ($r = mysqli_fetch_assoc($rp)) $txt .= "• {$r['quantity']}oy — {$r['username']} — {$r['status']} — " . date('d.m.Y H:i', strtotime($r['date'])) . "\n";
    editMessage($chat_id, $message_id, $txt, $admin_back_kb);
    exit;
}

if ($callback_data === "admin_users" && $from_id == $admin) {
    $cnt = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) c FROM users"))['c'];
    $txt = "<b>👥 Foydalanuvchilar: {$cnt}</b>\n\nSo'nggi:\n";
    $ru  = mysqli_query($connect, "SELECT user_id, date FROM users ORDER BY date DESC LIMIT 10");
    while ($r = mysqli_fetch_assoc($ru)) $txt .= "• {$r['user_id']} — " . date('d.m.Y H:i', strtotime($r['date'])) . "\n";
    editMessage($chat_id, $message_id, $txt, $admin_back_kb);
    exit;
}

// Price edit triggers
$ep_map = ['ep_star' => 'star_price', 'ep_1' => 'premium_1_month', 'ep_3' => 'premium_3_month', 'ep_6' => 'premium_6_month', 'ep_12' => 'premium_12_month'];
$ep_lbl = ['ep_star' => '⭐ Stars narxi', 'ep_1' => '👑 1 oy narxi', 'ep_3' => '👑 3 oy narxi', 'ep_6' => '👑 6 oy narxi', 'ep_12' => '👑 12 oy narxi'];
if ($callback_data && isset($ep_map[$callback_data]) && $from_id == $admin) {
    save_step($chat_id, ['step' => 'edit_price', 'field' => $ep_map[$callback_data], 'label' => $ep_lbl[$callback_data]]);
    sendMessage($chat_id, "{$ep_lbl[$callback_data]} uchun yangi miqdor kiriting (so'm):");
    exit;
}

// ─── Stars menu ──────────────────────────────────────────────────────────────
if ($callback_data === "stars") {
    deleteMessage($chat_id, $message_id);
    $sp = cfg($connect)['star_price'];
    $kb = json_encode(['inline_keyboard' => [
        [['text' => "50 ⭐",   'callback_data' => "buy_stars_50"],
         ['text' => "100 ⭐",  'callback_data' => "buy_stars_100"]],
        [['text' => "500 ⭐",  'callback_data' => "buy_stars_500"],
         ['text' => "1000 ⭐", 'callback_data' => "buy_stars_1000"]],
        [['text' => "🔙 Orqaga", 'callback_data' => "menu"]],
    ]], JSON_UNESCAPED_UNICODE);
    sendMessage($chat_id, "<b>⭐️ Stars sotib olish\n\n50 dan 5000 gacha kiriting yoki tanlang:\n(1 star = {$sp} so'm)</b>", $kb);
    save_step($chat_id, ['step' => 'stars_amount']);
    exit;
}

// ─── Premium menu ────────────────────────────────────────────────────────────
if ($callback_data === "premium") {
    deleteMessage($chat_id, $message_id);
    $c  = cfg($connect);
    $kb = json_encode(['inline_keyboard' => [
        [['text' => "1 oy 👑 — {$c['premium_1_month']} so'm",  'callback_data' => "buy_prem_1"],
         ['text' => "3 oy 👑 — {$c['premium_3_month']} so'm",  'callback_data' => "buy_prem_3"]],
        [['text' => "6 oy 👑 — {$c['premium_6_month']} so'm",  'callback_data' => "buy_prem_6"],
         ['text' => "12 oy 👑 — {$c['premium_12_month']} so'm",'callback_data' => "buy_prem_12"]],
        [['text' => "🔙 Orqaga", 'callback_data' => "menu"]],
    ]], JSON_UNESCAPED_UNICODE);
    sendMessage($chat_id, "<b>👑 Premium obuna\n\nMuddatni tanlang:</b>", $kb);
    exit;
}

// ─── menu back ───────────────────────────────────────────────────────────────
if ($callback_data === "menu") {
    deleteMessage($chat_id, $message_id);
    sendAnimation($chat_id, "https://t.me/PhotosForBots/146",
        "🎉 <b>Uz Give</b>ga xush kelibsiz!\n\n🎯 <b>Nima olasiz, tanlang:</b>", $menu);
    clear_step($chat_id);
    exit;
}

// ─── Stars quantity buttons ──────────────────────────────────────────────────
if ($callback_data && strpos($callback_data, "buy_stars_") === 0) {
    deleteMessage($chat_id, $message_id);
    $stars = intval(str_replace("buy_stars_", "", $callback_data));
    $price = $stars * cfg($connect)['star_price'];
    save_step($chat_id, ['step' => 'stars_user', 'stars' => $stars]);
    $kb = json_encode(['inline_keyboard' => [
        [['text' => "👤 O'zimga", 'callback_data' => "self_stars"],
         ['text' => "🔙 Orqaga", 'callback_data' => "stars"]],
    ]], JSON_UNESCAPED_UNICODE);
    sendMessage($chat_id, "<b>⭐️ Buyurtma\n└ Miqdor: {$stars} ⭐️\n└ Narxi: {$price} so'm\n\n👤 Kimga?\n📝 @username kiriting:</b>", $kb);
    exit;
}

// ─── Premium quantity buttons ─────────────────────────────────────────────────
if ($callback_data && strpos($callback_data, "buy_prem_") === 0) {
    deleteMessage($chat_id, $message_id);
    $months = intval(str_replace("buy_prem_", "", $callback_data));
    $c      = cfg($connect);
    $price  = $c["premium_{$months}_month"];
    save_step($chat_id, ['step' => 'premium_user', 'months' => $months, 'price' => $price]);
    $kb = json_encode(['inline_keyboard' => [
        [['text' => "👤 O'zimga", 'callback_data' => "self_premium"],
         ['text' => "🔙 Orqaga", 'callback_data' => "premium"]],
    ]], JSON_UNESCAPED_UNICODE);
    sendMessage($chat_id, "<b>👑 Buyurtma\n└ Muddat: {$months} oy\n└ Narxi: {$price} so'm\n\n👤 Kimga?\n📝 @username kiriting:</b>", $kb);
    exit;
}

// ─── Self shortcuts ───────────────────────────────────────────────────────────
if ($callback_data === "self_stars") {
    if (!$username) { answerCallback($callback_id, "❗ Sizda username yo'q! Telegram sozlamalaridan qo'shing.", true); exit; }
    $st = load_step($chat_id);
    if (empty($st['stars'])) { answerCallback($callback_id, "⚠️ Buyurtma topilmadi.", true); exit; }
    $st['receiver'] = '@' . $username;
    save_step($chat_id, $st);
    make_stars_payment($chat_id, $connect, $CC);
    exit;
}

if ($callback_data === "self_premium") {
    if (!$username) { answerCallback($callback_id, "❗ Sizda username yo'q! Telegram sozlamalaridan qo'shing.", true); exit; }
    $st = load_step($chat_id);
    if (empty($st['months'])) { answerCallback($callback_id, "⚠️ Buyurtma topilmadi.", true); exit; }
    $st['receiver'] = '@' . $username;
    save_step($chat_id, $st);
    make_premium_payment($chat_id, $connect, $CC);
    exit;
}

// ─── Cancel pay ───────────────────────────────────────────────────────────────
if ($callback_data && strpos($callback_data, "cancel_stars=") === 0) {
    $order_code = substr($callback_data, strlen("cancel_stars="));
    $st = mysqli_prepare($connect, "SELECT * FROM review WHERE order_id=? AND status='unpaid' LIMIT 1");
    mysqli_stmt_bind_param($st, 's', $order_code); mysqli_stmt_execute($st);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($st)); mysqli_stmt_close($st);
    if (!$row) { answerCallback($callback_id, "❌ To'lov topilmadi yoki allaqachon o'zgargan!", true); exit; }
    $upd = mysqli_prepare($connect, "UPDATE review SET status='cancel' WHERE order_id=? LIMIT 1");
    mysqli_stmt_bind_param($upd, 's', $order_code); mysqli_stmt_execute($upd); mysqli_stmt_close($upd);
    $CC->cancel($order_code);
    deleteMessage($chat_id, $message_id);
    sendMessage($row['user_id'], "❌ <b>{$row['price']} so'mlik to'lov bekor qilindi.</b>\n⭐ Stars: {$row['quantity']}\n👤 {$row['username']}");
    answerCallback($callback_id, "Bekor qilindi.", true);
    exit;
}

if ($callback_data && strpos($callback_data, "cancel_prem=") === 0) {
    $order_code = substr($callback_data, strlen("cancel_prem="));
    $st = mysqli_prepare($connect, "SELECT * FROM premium_orders WHERE order_id=? AND status='unpaid' LIMIT 1");
    mysqli_stmt_bind_param($st, 's', $order_code); mysqli_stmt_execute($st);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($st)); mysqli_stmt_close($st);
    if (!$row) { answerCallback($callback_id, "❌ To'lov topilmadi yoki allaqachon o'zgargan!", true); exit; }
    $upd = mysqli_prepare($connect, "UPDATE premium_orders SET status='cancel' WHERE order_id=? LIMIT 1");
    mysqli_stmt_bind_param($upd, 's', $order_code); mysqli_stmt_execute($upd); mysqli_stmt_close($upd);
    $CC->cancel($order_code);
    deleteMessage($chat_id, $message_id);
    sendMessage($row['user_id'], "❌ <b>{$row['price']} so'mlik to'lov bekor qilindi.</b>\n👑 Premium: {$row['quantity']} oy\n👤 {$row['username']}");
    answerCallback($callback_id, "Bekor qilindi.", true);
    exit;
}

// ─── Check payment ────────────────────────────────────────────────────────────
if ($callback_data && strpos($callback_data, "check_stars=") === 0) {
    $order_code = substr($callback_data, strlen("check_stars="));

    $st = mysqli_prepare($connect, "SELECT * FROM review WHERE order_id=? AND payment_method='checkcard' LIMIT 1");
    mysqli_stmt_bind_param($st, 's', $order_code); mysqli_stmt_execute($st);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($st)); mysqli_stmt_close($st);

    $raw    = $CC->check($order_code);
    $result = $raw ? json_decode($raw, true) : null;
    if (!$result || ($result['status'] ?? '') !== 'success') { answerCallback($callback_id, "❌ API xatolik berdi!", true); exit; }

    $order_data = $result['data'] ?? [];
    $summa      = (int)($order_data['amount'] ?? 0);
    $status     = $order_data['status'] ?? '';

    if ($status === "paid") {
        $uid    = $row['user_id'] ?? $from_id;
        $qty    = intval($row['quantity'] ?? 0);
        $rcv    = $row['username'] ?? 'N/A';
        $prc    = $row['price'] ?? $summa;
        $rid    = $row['id'] ?? null;
        $logs   = cfg($connect)['logs'];

        // Mark paid
        $upd = mysqli_prepare($connect, "UPDATE review SET status='paid' WHERE id=? LIMIT 1");
        mysqli_stmt_bind_param($upd, 'i', $rid); mysqli_stmt_execute($upd); mysqli_stmt_close($upd);

        @deleteMessage($chat_id, $message_id);
        sendMessage($from_id, "<b>✅ To'lov qabul qilindi.\n💰 {$summa} so'm\n📦 Buyurtma bajarilmoqda...</b>");

        // Deliver stars via API
        $api_url   = "https://domen.uz/stars.php?username=" . urlencode($rcv) . "&starssoni=" . urlencode($qty);
        $ch_curl   = curl_init($api_url);
        curl_setopt($ch_curl, CURLOPT_RETURNTRANSFER, true);
        $api_resp  = curl_exec($ch_curl);
        $http_code = curl_getinfo($ch_curl, CURLINFO_HTTP_CODE);
        curl_close($ch_curl);
        $delivered = ($api_resp !== false && $http_code >= 200 && $http_code < 300);

        if ($delivered) {
            $upd2 = mysqli_prepare($connect, "UPDATE review SET status='completed' WHERE id=? LIMIT 1");
            mysqli_stmt_bind_param($upd2, 'i', $rid); mysqli_stmt_execute($upd2); mysqli_stmt_close($upd2);
            sendMessage($from_id, "<b>⭐️ {$qty} stars muvaffaqiyatli yuborildi: {$rcv}\n🎉 Rahmat!</b>");
        } else {
            $upd3 = mysqli_prepare($connect, "UPDATE review SET status='failed_delivery' WHERE id=? LIMIT 1");
            mysqli_stmt_bind_param($upd3, 'i', $rid); mysqli_stmt_execute($upd3); mysqli_stmt_close($upd3);
            sendMessage($from_id, "<b>⚠️ To'lov qabul qilindi, ammo stars yetkazishda muammo. Admin bilan bog'laning.</b>");
            $err = "<b>⚠️ Stars delivery FAIL\nOrder: {$order_code}\nUser: {$uid}\n{$rcv} → {$qty}⭐\nHTTP: {$http_code}</b>";
            sendMessage($logs, $err); sendMessage($admin, $err);
        }

        $log = "<b>✅ Stars buyurtma (CheckCard)\nOrder: {$order_code}\nUser: {$uid}\n{$rcv} → {$qty}⭐\nNarx: {$prc} so'm</b>";
        sendMessage($logs, $log); sendMessage(CHANNEL_TO_JOIN, $log);
        answerCallback($callback_id, "✅ To'lov qabul qilindi!", true);

    } elseif ($status === "cancel") {
        if ($row) {
            $upd = mysqli_prepare($connect, "UPDATE review SET status='cancel' WHERE order_id=? LIMIT 1");
            mysqli_stmt_bind_param($upd, 's', $order_code); mysqli_stmt_execute($upd); mysqli_stmt_close($upd);
        }
        deleteMessage($chat_id, $message_id);
        sendMessage($from_id, "❌ <b>{$summa} so'mlik to'lov bekor qilingan.</b>");
        answerCallback($callback_id, "Bekor qilingan.", true);
    } elseif ($status === "pending") {
        answerCallback($callback_id, "⏳ To'lov hali amalga oshirilmagan.", true);
    } else {
        answerCallback($callback_id, "⚠️ Holat: $status", true);
    }
    exit;
}

if ($callback_data && strpos($callback_data, "check_prem=") === 0) {
    $order_code = substr($callback_data, strlen("check_prem="));

    $st = mysqli_prepare($connect, "SELECT * FROM premium_orders WHERE order_id=? AND payment_method='checkcard' LIMIT 1");
    mysqli_stmt_bind_param($st, 's', $order_code); mysqli_stmt_execute($st);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($st)); mysqli_stmt_close($st);

    $raw    = $CC->check($order_code);
    $result = $raw ? json_decode($raw, true) : null;
    if (!$result || ($result['status'] ?? '') !== 'success') { answerCallback($callback_id, "❌ API xatolik berdi!", true); exit; }

    $order_data = $result['data'] ?? [];
    $summa      = (int)($order_data['amount'] ?? 0);
    $status     = $order_data['status'] ?? '';

    if ($status === "paid") {
        $uid  = $row['user_id'] ?? $from_id;
        $qty  = intval($row['quantity'] ?? 0);
        $rcv  = $row['username'] ?? 'N/A';
        $prc  = $row['price'] ?? $summa;
        $rid  = $row['id'] ?? null;
        $logs = cfg($connect)['logs'];

        $upd = mysqli_prepare($connect, "UPDATE premium_orders SET status='paid' WHERE id=? LIMIT 1");
        mysqli_stmt_bind_param($upd, 'i', $rid); mysqli_stmt_execute($upd); mysqli_stmt_close($upd);

        @deleteMessage($chat_id, $message_id);
        sendMessage($from_id, "<b>✅ To'lov qabul qilindi.\n💰 {$summa} so'm\n📦 Buyurtma bajarilmoqda...</b>");

        $api_url   = "https://domen.uz/premium.php?username=" . urlencode($rcv) . "&premiumoyi=" . urlencode($qty);
        $ch_curl   = curl_init($api_url);
        curl_setopt($ch_curl, CURLOPT_RETURNTRANSFER, true);
        $api_resp  = curl_exec($ch_curl);
        $http_code = curl_getinfo($ch_curl, CURLINFO_HTTP_CODE);
        curl_close($ch_curl);
        $delivered = ($api_resp !== false && $http_code >= 200 && $http_code < 300);

        if ($delivered) {
            $upd2 = mysqli_prepare($connect, "UPDATE premium_orders SET status='completed' WHERE id=? LIMIT 1");
            mysqli_stmt_bind_param($upd2, 'i', $rid); mysqli_stmt_execute($upd2); mysqli_stmt_close($upd2);
            sendMessage($from_id, "<b>👑 {$qty} oylik Premium muvaffaqiyatli yuborildi: {$rcv}\n🎉 Rahmat!</b>");
        } else {
            $upd3 = mysqli_prepare($connect, "UPDATE premium_orders SET status='failed_delivery' WHERE id=? LIMIT 1");
            mysqli_stmt_bind_param($upd3, 'i', $rid); mysqli_stmt_execute($upd3); mysqli_stmt_close($upd3);
            sendMessage($from_id, "<b>⚠️ To'lov qabul qilindi, ammo Premium yetkazishda muammo. Admin bilan bog'laning.</b>");
            $err = "<b>⚠️ Premium delivery FAIL\nOrder: {$order_code}\nUser: {$uid}\n{$rcv} → {$qty}oy\nHTTP: {$http_code}</b>";
            sendMessage($logs, $err); sendMessage($admin, $err);
        }

        $log = "<b>✅ Premium buyurtma (CheckCard)\nOrder: {$order_code}\nUser: {$uid}\n{$rcv} → {$qty} oy\nNarx: {$prc} so'm</b>";
        sendMessage($logs, $log); sendMessage(CHANNEL_TO_JOIN, $log);
        answerCallback($callback_id, "✅ To'lov qabul qilindi!", true);

    } elseif ($status === "cancel") {
        if ($row) {
            $upd = mysqli_prepare($connect, "UPDATE premium_orders SET status='cancel' WHERE order_id=? LIMIT 1");
            mysqli_stmt_bind_param($upd, 's', $order_code); mysqli_stmt_execute($upd); mysqli_stmt_close($upd);
        }
        deleteMessage($chat_id, $message_id);
        sendMessage($from_id, "❌ <b>{$summa} so'mlik to'lov bekor qilingan.</b>");
        answerCallback($callback_id, "Bekor qilingan.", true);
    } elseif ($status === "pending") {
        answerCallback($callback_id, "⏳ To'lov hali amalga oshirilmagan.", true);
    } else {
        answerCallback($callback_id, "⚠️ Holat: $status", true);
    }
    exit;
}

// ─── Text input handler ────────────────────────────────────────────────────
if ($text !== null) {
    $st = load_step($chat_id);

    // Admin price edit
    if ($from_id == $admin && !empty($st['step']) && $st['step'] === 'edit_price') {
        if (is_numeric($text) && $text > 0) {
            $field = $st['field'];
            $label = $st['label'];
            $upd   = mysqli_prepare($connect, "UPDATE settings SET `{$field}` = ? WHERE id=1");
            mysqli_stmt_bind_param($upd, 'i', $text);
            mysqli_stmt_execute($upd); mysqli_stmt_close($upd);
            sendMessage($chat_id, "✅ {$label} <b>{$text} so'm</b> ga o'zgartirildi!");
            clear_step($chat_id);
        } else {
            sendMessage($chat_id, "⚠️ Iltimos to'g'ri musbat son kiriting.");
        }
        exit;
    }

    // Stars amount input
    if (!empty($st['step']) && $st['step'] === 'stars_amount') {
        if (!is_numeric($text)) { sendMessage($chat_id, "⚠️ Faqat son kiriting (50-5000)."); exit; }
        $qty = intval($text);
        if ($qty < 50 || $qty > 5000) { sendMessage($chat_id, "⚠️ 50 dan 5000 oralig'ida kiriting."); exit; }
        $price = $qty * cfg($connect)['star_price'];
        save_step($chat_id, ['step' => 'stars_user', 'stars' => $qty]);
        $kb = json_encode(['inline_keyboard' => [
            [['text' => "👤 O'zimga", 'callback_data' => "self_stars"],
             ['text' => "🔙 Orqaga", 'callback_data' => "stars"]],
        ]], JSON_UNESCAPED_UNICODE);
        sendMessage($chat_id, "<b>⭐️ Buyurtma\n└ Miqdor: {$qty} ⭐️\n└ Narxi: {$price} so'm\n\n👤 Kimga?\n📝 @username kiriting:</b>", $kb);
        exit;
    }

    // Stars username input
    if (!empty($st['step']) && $st['step'] === 'stars_user') {
        $uname = parse_username($text);
        if (!$uname) { sendMessage($chat_id, "⚠️ Username noto'g'ri. Masalan: @username"); exit; }
        $st['receiver'] = $uname;
        save_step($chat_id, $st);
        make_stars_payment($chat_id, $connect, $CC);
        exit;
    }

    // Premium username input
    if (!empty($st['step']) && $st['step'] === 'premium_user') {
        $uname = parse_username($text);
        if (!$uname) { sendMessage($chat_id, "⚠️ Username noto'g'ri. Masalan: @username"); exit; }
        $st['receiver'] = $uname;
        save_step($chat_id, $st);
        make_premium_payment($chat_id, $connect, $CC);
        exit;
    }
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
function parse_username($input) {
    $input = trim($input);
    if (preg_match('/@([A-Za-z0-9_]{3,32})/', $input, $m))    return '@' . $m[1];
    if (preg_match('/^[A-Za-z0-9_]{3,32}$/', $input))         return '@' . $input;
    if (preg_match('~t\.me/([A-Za-z0-9_]{3,32})~', $input, $m)) return '@' . $m[1];
    return null;
}

function make_stars_payment($chat_id, $connect, $CC) {
    global $stars_card;
    $st = load_step($chat_id);
    if (empty($st['stars']) || empty($st['receiver'])) {
        sendMessage($chat_id, "⚠️ Buyurtma to'liq emas."); return;
    }
    $stars    = intval($st['stars']);
    $receiver = $st['receiver'];
    $base     = $stars * cfg($connect)['star_price'];
    $amount   = $base + rand(1, 100);

    $raw  = $CC->create($amount);
    $resp = $raw ? json_decode($raw, true) : null;

    if (!$resp) { sendMessage($chat_id, "⚠️ CheckCard API bilan bog'lanishda xatolik."); return; }
    if (($resp['status'] ?? '') === 'error') {
        $msg = $resp['message'] ?? 'Xatolik';
        if ($msg === "There is a pending payment for this amount.") {
            sendMessage($chat_id, "⚠️ Ushbu miqdorda hali yakunlanmagan to'lov mavjud.\n\n💡 Masalan: " . ($amount + 500) . " so'm kiriting.");
        } else {
            sendMessage($chat_id, "⚠️ $msg");
        }
        return;
    }

    $order_code = $resp['order'] ?? null;
    $insert_id  = $resp['insert_id'] ?? $order_code;
    if (!$order_code) { sendMessage($chat_id, "⚠️ API javobida order topilmadi."); return; }

    $ins = mysqli_prepare($connect, "INSERT INTO review (user_id, order_id, price, status, quantity, username, payment_method) VALUES (?,?,?,'unpaid',?,?,'checkcard')");
    $uid = intval($chat_id);
    mysqli_stmt_bind_param($ins, 'isiis', $uid, $order_code, $amount, $stars, $receiver);
    mysqli_stmt_execute($ins); mysqli_stmt_close($ins);

    $kb = json_encode(['inline_keyboard' => [
        [['text' => "♻️ To'lovni tekshirish", 'callback_data' => "check_stars={$order_code}"]],
        [['text' => "❌ Bekor qilish",        'callback_data' => "cancel_stars={$order_code}"]],
    ]], JSON_UNESCAPED_UNICODE);

    sendMessage($chat_id, "<b>💳 To'lov ma'lumotlari

📋 Buyurtma #{$insert_id}
└ ⭐ Stars: {$stars}
└ 💰 Narxi: {$base} so'm
└ 👤 Username: {$receiver}

💳 Karta: <code>{$stars_card}</code>
💵 To'lov miqdori: <b><u>{$amount} so'm</u></b>

⚠️ Aynan shu miqdorni o'tkazing!
Keyin '♻️ To'lovni tekshirish' tugmasini bosing.</b>", $kb);

    clear_step($chat_id);
}

function make_premium_payment($chat_id, $connect, $CC) {
    global $premium_card;
    $st = load_step($chat_id);
    if (empty($st['months']) || empty($st['receiver'])) {
        sendMessage($chat_id, "⚠️ Buyurtma to'liq emas."); return;
    }
    $months   = intval($st['months']);
    $receiver = $st['receiver'];
    $base     = intval($st['price']);
    $amount   = $base + rand(1, 100);

    $raw  = $CC->create($amount);
    $resp = $raw ? json_decode($raw, true) : null;

    if (!$resp) { sendMessage($chat_id, "⚠️ CheckCard API bilan bog'lanishda xatolik."); return; }
    if (($resp['status'] ?? '') === 'error') {
        $msg = $resp['message'] ?? 'Xatolik';
        if ($msg === "There is a pending payment for this amount.") {
            sendMessage($chat_id, "⚠️ Ushbu miqdorda hali yakunlanmagan to'lov mavjud.\n\n💡 Masalan: " . ($amount + 500) . " so'm kiriting.");
        } else {
            sendMessage($chat_id, "⚠️ $msg");
        }
        return;
    }

    $order_code = $resp['order'] ?? null;
    $insert_id  = $resp['insert_id'] ?? $order_code;
    if (!$order_code) { sendMessage($chat_id, "⚠️ API javobida order topilmadi."); return; }

    $ins = mysqli_prepare($connect, "INSERT INTO premium_orders (user_id, order_id, price, status, quantity, username, payment_method) VALUES (?,?,?,'unpaid',?,?,'checkcard')");
    $uid = intval($chat_id);
    mysqli_stmt_bind_param($ins, 'isiis', $uid, $order_code, $amount, $months, $receiver);
    mysqli_stmt_execute($ins); mysqli_stmt_close($ins);

    $kb = json_encode(['inline_keyboard' => [
        [['text' => "♻️ To'lovni tekshirish", 'callback_data' => "check_prem={$order_code}"]],
        [['text' => "❌ Bekor qilish",        'callback_data' => "cancel_prem={$order_code}"]],
    ]], JSON_UNESCAPED_UNICODE);

    sendMessage($chat_id, "<b>💳 To'lov ma'lumotlari

📋 Buyurtma #{$insert_id}
└ 👑 Premium: {$months} oy
└ 💰 Narxi: {$base} so'm
└ 👤 Username: {$receiver}

💳 Karta: <code>{$premium_card}</code>
💵 To'lov miqdori: <b><u>{$amount} so'm</u></b>

⚠️ Aynan shu miqdorni o'tkazing!
Keyin '♻️ To'lovni tekshirish' tugmasini bosing.</b>", $kb);

    clear_step($chat_id);
}

exit;
?>
