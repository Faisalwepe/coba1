<?php
// ==========================================
// BACKEND PHP API - MYSQL INTEGRATION
// Handles all CRUD operations for Booking Villa
// ==========================================

// Izinkan akses CORS untuk mencegah error saat diakses dari domain/subdomain
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Konfigurasi Database - PASTIKAN INI SESUAI DENGAN HOSTING ANDA
$db_host = "localhost";
$db_name = "bald6243_u12345_baleody";
$db_user = "bald6243_u12345_userody";
$db_pass = "JF2J!9=Lzv]+}@Wl";

try {
    $conn = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo json_encode([
        "status" => "error",
        "message" => "Koneksi database MySQL gagal: " . $e->getMessage()
    ]);
    exit();
}

$action = isset($_GET['action']) ? $_GET['action'] : '';
$inputData = json_decode(file_get_contents("php://input"), true);

switch ($action) {

    // 1. INTI DATA (INIT)
    case 'init':
        try {
            $vstmt = $conn->query("SELECT * FROM villas");
            $villas = $vstmt->fetchAll();

            $bstmt = $conn->query("SELECT * FROM bookings ORDER BY created_at DESC");
            $bookingsRaw = $bstmt->fetchAll();
            
            $bookings = [];
            foreach ($bookingsRaw as $b) {
                $istmt = $conn->prepare("SELECT villa_id as villaId, qty, price_paid as pricePaid FROM booking_items WHERE booking_id = ?");
                $istmt->execute([$b['id']]);
                $items = $istmt->fetchAll();

                $astmt = $conn->prepare("SELECT name, price FROM booking_addons WHERE booking_id = ?");
                $astmt->execute([$b['id']]);
                $addons = $astmt->fetchAll();

                $bookings[] = [
                    "id" => $b['id'],
                    "customerName" => $b['customer_name'],
                    "checkIn" => $b['check_in'],
                    "checkOut" => $b['check_out'],
                    "discount" => (float)$b['discount'],
                    "dp" => (float)$b['dp'],
                    "dpPaymentMethod" => isset($b['dp_payment_method']) ? $b['dp_payment_method'] : 'None',
                    "lunasPaymentMethod" => isset($b['lunas_payment_method']) ? $b['lunas_payment_method'] : 'None',
                    "totalPrice" => (float)$b['total_price'],
                    "createdAt" => $b['created_at'],
                    "items" => $items,
                    "addons" => $addons
                ];
            }

            $ustmt = $conn->query("SELECT id, username, password, role FROM accounts");
            $accounts = $ustmt->fetchAll();

            $sstmt = $conn->query("SELECT * FROM settings");
            $settingsRaw = $sstmt->fetchAll();
            $settings = [
                "villaName" => "Villa Grand Oasis",
                "villaLogo" => "",
                "villaFavicon" => ""
            ];
            foreach ($settingsRaw as $s) {
                $settings[$s['setting_key']] = $s['setting_value'];
            }

            echo json_encode([
                "status" => "success",
                "villas" => $villas,
                "bookings" => $bookings,
                "accounts" => $accounts,
                "settings" => $settings
            ]);
        } catch (Exception $e) {
            echo json_encode(["status" => "error", "message" => "Gagal load data: " . $e->getMessage()]);
        }
        break;

    // 2. PROSES LOGIN
    case 'login':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$inputData) {
            echo json_encode(["status" => "error", "message" => "Metode tidak valid"]);
            break;
        }
        $username = trim($inputData['username']);
        $password = $inputData['password'];

        try {
            $stmt = $conn->prepare("SELECT * FROM accounts WHERE LOWER(username) = LOWER(?) AND password = ?");
            $stmt->execute([$username, $password]);
            $user = $stmt->fetch();

            if ($user) {
                echo json_encode(["status" => "success", "user" => ["id" => $user['id'], "username" => $user['username'], "role" => $user['role']]]);
            } else {
                echo json_encode(["status" => "error", "message" => "Username atau Password salah!"]);
            }
        } catch (Exception $e) {
            echo json_encode(["status" => "error", "message" => "Gagal login: " . $e->getMessage()]);
        }
        break;

    // 3. SIMPAN RESERVASI BARU ATAU EDIT
    case 'save_booking':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$inputData) {
            echo json_encode(["status" => "error", "message" => "Data input kosong"]);
            break;
        }

        try {
            $conn->beginTransaction();

            $id = $inputData['id'];
            $customerName = $inputData['customerName'];
            $checkIn = $inputData['checkIn'];
            $checkOut = $inputData['checkOut'];
            $discount = (float)$inputData['discount'];
            $dp = (float)$inputData['dp'];
            $dpMethod = isset($inputData['dpPaymentMethod']) ? $inputData['dpPaymentMethod'] : 'None';
            $totalPrice = (float)$inputData['totalPrice'];
            $createdAt = isset($inputData['createdAt']) ? $inputData['createdAt'] : date('Y-m-d H:i:s');
            
            if (strpos($createdAt, 'T') !== false) {
                $createdAt = str_replace(['T', 'Z'], [' ', ''], $createdAt);
                $createdAt = substr($createdAt, 0, 19);
            }

            $checkStmt = $conn->prepare("SELECT id FROM bookings WHERE id = ?");
            $checkStmt->execute([$id]);
            $exists = $checkStmt->fetch();

            if ($exists) {
                $upStmt = $conn->prepare("UPDATE bookings SET customer_name = ?, check_in = ?, check_out = ?, discount = ?, dp = ?, dp_payment_method = ?, total_price = ? WHERE id = ?");
                $upStmt->execute([$customerName, $checkIn, $checkOut, $discount, $dp, $dpMethod, $totalPrice, $id]);

                $conn->prepare("DELETE FROM booking_items WHERE booking_id = ?")->execute([$id]);
                $conn->prepare("DELETE FROM booking_addons WHERE booking_id = ?")->execute([$id]);
            } else {
                $insStmt = $conn->prepare("INSERT INTO bookings (id, customer_name, check_in, check_out, discount, dp, dp_payment_method, total_price, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $insStmt->execute([$id, $customerName, $checkIn, $checkOut, $discount, $dp, $dpMethod, $totalPrice, $createdAt]);
            }

            if (isset($inputData['items']) && is_array($inputData['items'])) {
                foreach ($inputData['items'] as $item) {
                    $itemStmt = $conn->prepare("INSERT INTO booking_items (booking_id, villa_id, qty, price_paid) VALUES (?, ?, ?, ?)");
                    $itemStmt->execute([$id, $item['villaId'], $item['qty'], $item['pricePaid']]);
                }
            }

            if (isset($inputData['addons']) && is_array($inputData['addons'])) {
                foreach ($inputData['addons'] as $addon) {
                    $addonStmt = $conn->prepare("INSERT INTO booking_addons (booking_id, name, price) VALUES (?, ?, ?)");
                    $addonStmt->execute([$id, $addon['name'], $addon['price']]);
                }
            }

            $conn->commit();
            echo json_encode(["status" => "success", "message" => "Reservasi disimpan!"]);
        } catch (Exception $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            echo json_encode(["status" => "error", "message" => "Gagal menyimpan reservasi: " . $e->getMessage()]);
        }
        break;

    // 4. HAPUS RESERVASI
    case 'delete_booking':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$inputData) break;
        $id = $inputData['id'];
        try {
            $conn->beginTransaction();
            $conn->prepare("DELETE FROM booking_addons WHERE booking_id = ?")->execute([$id]);
            $conn->prepare("DELETE FROM booking_items WHERE booking_id = ?")->execute([$id]);
            $conn->prepare("DELETE FROM bookings WHERE id = ?")->execute([$id]);
            $conn->commit();
            echo json_encode(["status" => "success"]);
        } catch (Exception $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
        break;

    // 5. SIMPAN JENIS VILLA
    case 'save_villa':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$inputData) break;
        try {
            $id = $inputData['id']; $name = $inputData['name']; $price = (float)$inputData['price']; $stock = (int)$inputData['stock'];
            $exists = $conn->prepare("SELECT id FROM villas WHERE id = ?"); $exists->execute([$id]);
            if ($exists->fetch()) {
                $conn->prepare("UPDATE villas SET name = ?, price = ?, stock = ? WHERE id = ?")->execute([$name, $price, $stock, $id]);
            } else {
                $conn->prepare("INSERT INTO villas (id, name, price, stock) VALUES (?, ?, ?, ?)")->execute([$id, $name, $price, $stock]);
            }
            echo json_encode(["status" => "success"]);
        } catch (Exception $e) { echo json_encode(["status" => "error", "message" => $e->getMessage()]); }
        break;

    // 6. HAPUS JENIS VILLA
    case 'delete_villa':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$inputData) break;
        try {
            $usage = $conn->prepare("SELECT COUNT(*) as count FROM booking_items WHERE villa_id = ?"); $usage->execute([$inputData['id']]);
            if ($usage->fetch()['count'] > 0) { echo json_encode(["status" => "error", "message" => "Kamar sedang digunakan!"]); break; }
            $conn->prepare("DELETE FROM villas WHERE id = ?")->execute([$inputData['id']]); echo json_encode(["status" => "success"]);
        } catch (Exception $e) { echo json_encode(["status" => "error", "message" => $e->getMessage()]); }
        break;

    // 7. SIMPAN AKUN
    case 'save_account':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$inputData) break;
        try {
            $id = $inputData['id']; $usr = trim($inputData['username']); $pwd = $inputData['password']; $role = $inputData['role'];
            $exists = $conn->prepare("SELECT id FROM accounts WHERE id = ?"); $exists->execute([$id]);
            if ($exists->fetch()) {
                $conn->prepare("UPDATE accounts SET username = ?, password = ?, role = ? WHERE id = ?")->execute([$usr, $pwd, $role, $id]);
            } else {
                $conn->prepare("INSERT INTO accounts (id, username, password, role) VALUES (?, ?, ?, ?)")->execute([$id, $usr, $pwd, $role]);
            }
            echo json_encode(["status" => "success"]);
        } catch (Exception $e) { echo json_encode(["status" => "error", "message" => $e->getMessage()]); }
        break;

    // 8. HAPUS AKUN
    case 'delete_account':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$inputData) break;
        try { $conn->prepare("DELETE FROM accounts WHERE id = ?")->execute([$inputData['id']]); echo json_encode(["status" => "success"]); } 
        catch (Exception $e) { echo json_encode(["status" => "error", "message" => $e->getMessage()]); }
        break;

    // 9. SIMPAN BRANDING
    case 'save_branding':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$inputData) break;
        try {
            foreach ($inputData as $key => $val) {
                $exists = $conn->prepare("SELECT setting_key FROM settings WHERE setting_key = ?"); $exists->execute([$key]);
                if ($exists->fetch()) {
                    $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?")->execute([$val, $key]);
                } else {
                    $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)")->execute([$key, $val]);
                }
            }
            echo json_encode(["status" => "success"]);
        } catch (Exception $e) { echo json_encode(["status" => "error", "message" => $e->getMessage()]); }
        break;

    // 10. SET LUNAS (Fitur Baru)
    case 'set_lunas':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$inputData) {
            echo json_encode(["status" => "error", "message" => "Data kosong"]);
            break;
        }
        $id = $inputData['id'];
        $lunasMethod = isset($inputData['method']) ? $inputData['method'] : 'Cash';

        try {
            $stmt = $conn->prepare("UPDATE bookings SET dp = total_price, lunas_payment_method = ? WHERE id = ?");
            $stmt->execute([$lunasMethod, $id]);
            echo json_encode(["status" => "success", "message" => "Tagihan dilunasi"]);
        } catch (Exception $e) {
            echo json_encode(["status" => "error", "message" => "Gagal melunasi tagihan: " . $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(["status" => "error", "message" => "Aksi tidak dikenali"]);
        break;
}
?>
