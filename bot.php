<?php
ob_start();
error_reporting(E_ALL);
date_default_timezone_set('Asia/Tashkent');
define("API_KEY", '8233780541:AAHV0ZH67NWoluF49RPpj6BuWgmbSWjEIOk');  //token
$admin = 6365371142; //adminid
// Manba: @thewwiw && @WonderfulCoders Manba bilan tarqat
// Manba: @thewwiw && @WonderfulCoders Manba bilan tarqat
// Manba: @thewwiw && @WonderfulCoders Manba bilan tarqat
// Manba: @thewwiw && @WonderfulCoders Manba bilan tarqat
// Manba: @thewwiw && @WonderfulCoders Manba bilan tarqat

define("DB_SERVER", "localhost");
define("DB_USERNAME", "DATABASEUSERNOMI");
define("DB_PASSWORD", "DATABASEPAROLI");
define("DB_NAME", "DATABASENOMI");
define('PROHAMYON_SHOP_ID', '652059'); // @ProHamyonBot dan olingan shop id
define('PROHAMYON_SHOP_KEY', '2Y1AKK4MOS'); // @ProHamyonBot dan olingan shop key
define('CHANNEL_TO_JOIN', '@kinochitestuz'); // Tolovlar kanali

$card_number = "9860600401573909";

$connect = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if (!$connect) {
    error_log("DB connection failed: " . mysqli_connect_error());
    exit;
}
mysqli_set_charset($connect, "utf8mb4");

class ProHamyonPay {
    private $shop_id;
    private $shop_key;

    public function __construct($shop_id, $shop_key){
        $this->shop_id = $shop_id;
        $this->shop_key = $shop_key;
    }

    public function create_checkout($amount){
        $ch = curl_init("https://tezapi.uz/api?method=create");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'shop_id' => $this->shop_id,
            'shop_key' => $this->shop_key,
            'amount' => $amount
        ]));
        $response = curl_exec($ch);
        if($response === false){
            error_log("ProHamyon create curl error: ".curl_error($ch));
            curl_close($ch);
            return false;
        }
        curl_close($ch);
        return $response;
    }

    public function check_payment($order_code){
        $api_url = "https://tezapi.uz/api?method=check&order=" . urlencode($order_code);
        $response = @file_get_contents($api_url);
        if($response === false){
            error_log("ProHamyon check payment failed for order: " . $order_code);
            return false;
        }
        return $response;
    }
}

$ProHamyonPay = new ProHamyonPay(PROHAMYON_SHOP_ID, PRoHAMYON_SHOP_KEY);

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
    `payment_method` VARCHAR(20) DEFAULT 'prohamyon',
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
    `payment_method` VARCHAR(20) DEFAULT 'prohamyon',
    `date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

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
return ['logs' => CHANNEL_TO_JOIN, 'api_key' => 'none', 'star_price' => 240, 'premium_1_month' => 45000, 'premium_3_month' => 165000, 'premium_6_month' => 215000, 'premium_12_month' => 360000];
}
return $row;
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
if ($callback_data === 'check_subscribe') {
if (user_is_member_of_channel($from_id)) {
answerCallback($callback_id, "✅ Siz kanalga obuna bo'lgansiz. Endi davom etishingiz mumkin.", true);
} else {
answerCallback($callback_id, "❌ Hali obuna bo'lmadingiz.", true);
}
exit;
}

if (!user_is_member_of_channel($from_id)) {
require_subscription_prompt($chat_id);
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

$menu = json_encode(['inline_keyboard' => [
[['text' => "⭐️ Stars", 'callback_data' => "stars"], ['text' => "👑 Premium", 'callback_data' => "premium"]],
[['text' => "📤 Do'stlarga ulashish", 'url' => "https://t.me/share/url?url=https://t.me/"]]
]], JSON_UNESCAPED_UNICODE);

if ($text === "/start") {
    sendAnimation($chat_id, "https://t.me/PhotosForBots/146", "🎉 <b>Uz Give</b>ga xush kelibsiz!\n\n🎯 <b>Nima olasiz, tanlang:</b>", $menu, "HTML");
    clear_step($chat_id);
    exit;
}

if ($text === "/admin" && $from_id == $admin) {
$admin_menu = json_encode(['inline_keyboard' => [
[['text' => "📊 Statistika", 'callback_data' => "admin_stats"], ['text' => "💰 Narxlarni o'zgartirish", 'callback_data' => "admin_prices"]],
[['text' => "📝 Loglar", 'callback_data' => "admin_logs"], ['text' => "⚙️ Sozlamalar", 'callback_data' => "admin_settings"]],
[['text' => "👥 Foydalanuvchilar", 'callback_data' => "admin_users"]]
]], JSON_UNESCAPED_UNICODE);
sendMessage($chat_id, "<b>🔧 Admin Panel</b>\n\nKerakli bo'limni tanlang:", $admin_menu);
exit;
}

if ($callback_data === "admin_stats" && $from_id == $admin) {
$stats_query = "SELECT 
(SELECT COUNT(*) FROM users) as total_users,
(SELECT COUNT(*) FROM review WHERE status = 'completed') as completed_orders,
(SELECT COUNT(*) FROM premium_orders WHERE status = 'completed') as completed_premium,
(SELECT SUM(price) FROM review WHERE status = 'completed') as total_revenue_stars,
(SELECT SUM(price) FROM premium_orders WHERE status = 'completed') as total_revenue_premium";
    
$stats_result = mysqli_query($connect, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);   
$total_revenue = ($stats['total_revenue_stars'] ?? 0) + ($stats['total_revenue_premium'] ?? 0);
$reply = json_encode(['inline_keyboard' => [
[['text' => "🔙 Orqaga", 'callback_data' => "admin_back"]]
]], JSON_UNESCAPED_UNICODE);   
editMessage($chat_id, $message_id, "<b>📊 Statistika</b>\n\n👥 <b>Jami foydalanuvchilar:</b> " . ($stats['total_users'] ?? 0) . "\n⭐ <b>Bajarilgan Stars buyurtmalar:</b> " . ($stats['completed_orders'] ?? 0) . "\n👑 <b>Bajarilgan Premium buyurtmalar:</b> " . ($stats['completed_premium'] ?? 0) . "\n💰 <b>Jami daromad:</b> " . number_format($total_revenue) . " so'm", $reply);
exit;
}

if ($callback_data === "admin_prices" && $from_id == $admin) {
$settings = settings($connect);
$reply = json_encode(['inline_keyboard' => [
[['text' => "⭐ Stars narxi: {$settings['star_price']} so'm", 'callback_data' => "edit_star_price"]],
[['text' => "👑 1 oy: {$settings['premium_1_month']} so'm", 'callback_data' => "edit_premium_1"]],
[['text' => "👑 3 oy: {$settings['premium_3_month']} so'm", 'callback_data' => "edit_premium_3"]],
[['text' => "👑 6 oy: {$settings['premium_6_month']} so'm", 'callback_data' => "edit_premium_6"]],
[['text' => "👑 12 oy: {$settings['premium_12_month']} so'm", 'callback_data' => "edit_premium_12"]],
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
$admin_menu = json_encode(['inline_keyboard' => [
[['text' => "📊 Statistika", 'callback_data' => "admin_stats"], ['text' => "💰 Narxlarni o'zgartirish", 'callback_data' => "admin_prices"]],
[['text' => "📝 Loglar", 'callback_data' => "admin_logs"], ['text' => "⚙️ Sozlamalar", 'callback_data' => "admin_settings"]],
[['text' => "👥 Foydalanuvchilar", 'callback_data' => "admin_users"]]
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
sendAnimation($chat_id, "https://t.me/PhotosForBots/146", "🎉 <b>Fast Give</b>ga xush kelibsiz!\n\n🎯 <b>Nima olasiz, tanlang:</b>", $menu, "HTML");
clear_step($chat_id);
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
process_order($chat_id, $connect, $card_number); 
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
process_premium_order($chat_id, $connect, $card_number); 
exit;
}

if ($text !== null) {
$st = load_step($chat_id);
if (!empty($st['step']) && $st['step'] === 'stars_amount') {
if (is_numeric($text)) {
$requested = intval($text);
if ($requested < 50 || $requested > 5000) {
sendMessage($chat_id, "⚠️ Noto‘g‘ri miqdor! 50 dan 5000 gacha kiriting.");
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
process_order($chat_id, $connect, $card_number); 
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

if ($from_id == $admin) {
$st = load_step($chat_id);
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

function process_order($chat_id, $connect, $card_number) {
$step = load_step($chat_id);
if (empty($step['stars']) || empty($step['receiver'])) {
sendMessage($chat_id, "⚠️ Buyurtma to'liq emas. Iltimos miqdor va username kiriting.");
return;
}
sendMessage($chat_id, "<b>💳 To'lov yaratilmoqda — ProHamyon orqali. Iltimos kuting...</b>");

process_prohamyon_payment($chat_id, $connect);
}

function process_premium_order($chat_id, $connect, $card_number) {
$step = load_step($chat_id);
if (empty($step['months']) || empty($step['receiver'])) {
sendMessage($chat_id, "⚠️ Buyurtma to'liq emas. Iltimos muddat va username kiriting.");
return;
}
sendMessage($chat_id, "<b>💳 To'lov yaratilmoqda — ProHamyon orqali. Iltimos kuting...</b>");

process_premium_payment($chat_id, $connect);
}

function process_prohamyon_payment($chat_id, $connect) {
global $ProHamyonPay;
$step = load_step($chat_id);
$stars = intval($step['stars'] ?? 0);
$receiver = $step['receiver'] ?? '';
$base_amount = $stars * settings($connect)['star_price'];
$rand_num = rand(1, 100);
$amount = $base_amount + $rand_num;

$resp = $ProHamyonPay->create_checkout($amount);
if ($resp === false) {
sendMessage($chat_id, "⚠️ To'lov yaratishda xatolik. Iltimos keyinroq urinib ko'ring.");
return;
}

$response = json_decode($resp, true);
if (!$response) {
sendMessage($chat_id, "⚠️ ProHamyonPay API dan noaniq javob olindi.");
error_log("ProHamyonPay create invalid json: " . $resp);
return;
}
if (isset($response['status']) && $response['status'] === 'error') {
sendMessage($chat_id, "⚠️ " . ($response['message'] ?? 'ProHamyonPay xatolik'));
return;
}

$order_code = $response['order'] ?? ($response['order_code'] ?? null);
$insert_id = $response['insert_id'] ?? null;

if (!$order_code) {
sendMessage($chat_id, "⚠️ ProHamyonPay javobida order topilmadi.");
error_log("ProHamyonPay response missing order: " . $resp);
return;
}

$stmt = mysqli_prepare($connect, "INSERT INTO review (user_id, order_id, price, status, quantity, username, payment_method, date) VALUES (?, ?, ?, 'unpaid', ?, ?, 'prohamyon', NOW())");
$user_id_q = intval($chat_id);
$order_q = $order_code;
$amount_q = intval($amount);
$stars_q = intval($stars);
$receiver_q = $receiver;
mysqli_stmt_bind_param($stmt, 'isiis', $user_id_q, $order_q, $amount_q, $stars_q, $receiver_q);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

$keyboard = json_encode(['inline_keyboard' => [
[['text' => "♻️ To'lov tekshirish", 'callback_data' => "ProHamyonPay_check={$order_code}"]],
[['text' => "❌ Bekor qilish", 'callback_data' => "cancelpay={$order_code}"]],
]], JSON_UNESCAPED_UNICODE);

sendMessage($chat_id, "<b>💳 To'lov ma'lumotlari

📋 Buyurtma #" . htmlspecialchars($insert_id ?? $order_code) . "
└ 🎯 Stars: {$stars} ⭐️
└ 💰 Narxi: {$base_amount} so'm
└ 👤 Username: {$receiver}

💳 Karta ma'lumotlari:
└ 🏦 Karta raqami: <code>9860036625185040</code>
└ 💵 To'lov miqdori: $amount so'm

⚠️ Muhim: Ko'rsatilgan miqdorni to'lang: $amount so'm (aniq miqdorda)
📱 To'lov qilganingizdan so'ng, botda '♻️ To'lov tekshirish' tugmasini bosing</b>", $keyboard);

clear_step($chat_id);
}

function process_premium_payment($chat_id, $connect) {
global $ProHamyonPay;
$step = load_step($chat_id);
$months = intval($step['months'] ?? 0);
$receiver = $step['receiver'] ?? '';
$base_amount = intval($step['price'] ?? 0);
$rand_num = rand(1, 100);
$amount = $base_amount + $rand_num;

$resp = $ProHamyonPay->create_checkout($amount);
if ($resp === false) {
sendMessage($chat_id, "⚠️ To'lov yaratishda xatolik. Iltimos keyinroq urinib ko'ring.");
return;
}

$response = json_decode($resp, true);
if (!$response) {
sendMessage($chat_id, "⚠️ ProHamyonPay API dan noaniq javob olindi.");
error_log("ProHamyonPay create invalid json: " . $resp);
return;
}
if (isset($response['status']) && $response['status'] === 'error') {
sendMessage($chat_id, "⚠️ " . ($response['message'] ?? 'ProHamyonPay xatolik'));
return;
}

$order_code = $response['order'] ?? ($response['order_code'] ?? null);
$insert_id = $response['insert_id'] ?? null;

if (!$order_code) {
sendMessage($chat_id, "⚠️ ProHamyonPay javobida order topilmadi.");
error_log("ProHamyonPay response missing order: " . $resp);
return;
}

$stmt = mysqli_prepare($connect, "INSERT INTO premium_orders (user_id, order_id, price, status, quantity, username, payment_method, date) VALUES (?, ?, ?, 'unpaid', ?, ?, 'prohamyon', NOW())");
$user_id_q = intval($chat_id);
$order_q = $order_code;
$amount_q = intval($amount);
$months_q = intval($months);
$receiver_q = $receiver;
mysqli_stmt_bind_param($stmt, 'isiis', $user_id_q, $order_q, $amount_q, $months_q, $receiver_q);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

$keyboard = json_encode(['inline_keyboard' => [
[['text' => "♻️ To'lov tekshirish", 'callback_data' => "ProHamyonPay_premium_check={$order_code}"]],
[['text' => "❌ Bekor qilish", 'callback_data' => "cancelpremium={$order_code}"]],
]], JSON_UNESCAPED_UNICODE);

sendMessage($chat_id, "<b>💳 To'lov ma'lumotlari

📋 Buyurtma #" . htmlspecialchars($insert_id ?? $order_code) . "
└ 🎯 Premium: {$months} oy
└ 💰 Narxi: {$base_amount} so'm
└ 👤 Username: {$receiver}

💳 Karta ma'lumotlari:
└ 🏦 Karta raqami: <code>9860036625185040</code>
└ 💵 To'lov miqdori: $amount so'm

⚠️ Muhim: Ko'rsatilgan miqdorni to'lang: $amount so'm (aniq miqdorda)
📱 To'lov qilganingizdan so'ng, botda '♻️ To'lov tekshirish' tugmasini bosing</b>", $keyboard);

clear_step($chat_id);
}

if ($callback_data && strpos($callback_data, "ProHamyonPay_check=") === 0) {
$order_code = explode("=", $callback_data)[1];

$stmt = mysqli_prepare($connect, "SELECT * FROM review WHERE order_id = ? AND payment_method = 'prohamyon' LIMIT 1");
mysqli_stmt_bind_param($stmt, 's', $order_code);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

$response = $ProHamyonPay->check_payment($order_code);
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
$logText = "<b>✅ Buyurtma bajarildi (ProHamyon)</b>\n\n🆔 <b>OrderCode:</b> {$order_code}\n👤 <b>Foydalanuvchi (chat_id):</b> {$user_id}\n📛 <b>Username:</b> {$username_target}\n⭐ <b>Stars:</b> {$quantity}\n💰 <b>Narx:</b> {$price} so'm\n📝 <b>OrderID (review.id):</b> " . ($review_id ?? 'N/A');
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

if ($callback_data && strpos($callback_data, "ProHamyonPay_premium_check=") === 0) {
$order_code = explode("=", $callback_data)[1];
$stmt = mysqli_prepare($connect, "SELECT * FROM premium_orders WHERE order_id = ? AND payment_method = 'prohamyon' LIMIT 1");
mysqli_stmt_bind_param($stmt, 's', $order_code);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);
$response = $ProHamyonPay->check_payment($order_code);
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
$logText = "<b>✅ Premium buyurtma bajarildi (ProHamyon)</b>\n\n🆔 <b>OrderCode:</b> {$order_code}\n👤 <b>Foydalanuvchi (chat_id):</b> {$user_id}\n📛 <b>Username:</b> {$username_target}\n👑 <b>Premium:</b> {$quantity} oy\n💰 <b>Narx:</b> {$price} so'm\n📝 <b>OrderID (premium_orders.id):</b> " . ($review_id ?? 'N/A');
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

// Manba: @thewwiw && @WonderfulCoders Manba bilan tarqat
// Manba: @thewwiw && @WonderfulCoders Manba bilan tarqat
// Manba: @thewwiw && @WonderfulCoders Manba bilan tarqat
// Manba: @thewwiw && @WonderfulCoders Manba bilan tarqat
// Manba: @thewwiw && @WonderfulCoders Manba bilan tarqat


exit;
?>