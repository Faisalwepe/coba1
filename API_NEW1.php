<?php
// ==========================================
// BACKEND PHP API - MYSQL INTEGRATION
// Handles all CRUD operations for Booking Villa
// ==========================================

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Konfigurasi Database sesuai permintaan user
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

// Tangkap request URL params dan body
$action = isset($_GET['action']) ? $_GET['action'] : '';
$inputData = json_decode(file_get_contents("php://input"), true);

switch ($action) {

    // 1. INTI DATA (INIT) - Memuat seluruh data untuk Dashboard
    case 'init':
        try {
            // Ambil data villas
            $vstmt = $conn->query("SELECT * FROM villas");
            $villas = $vstmt->fetchAll();

            // Ambil data bookings
            $bstmt = $conn->query("SELECT * FROM bookings ORDER BY created_at DESC");
            $bookingsRaw = $bstmt->fetchAll();
            
            $bookings = [];
            foreach ($bookingsRaw as $b) {
                // Ambil items untuk booking ini
                $istmt = $conn->prepare("SELECT villa_id as villaId, qty, price_paid as pricePaid FROM booking_items WHERE booking_id = ?");
                $istmt->execute([$b['id']]);
                $items = $istmt->fetchAll();

                // Ambil addons untuk booking ini
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
                    "totalPrice" => (float)$b['total_price'],
                    "createdAt" => $b['created_at'],
                    "items" => $items,
                    "addons" => $addons
                ];
            }

            // Ambil data akun
            $ustmt = $conn->query("SELECT id, username, password, role FROM accounts");
            $accounts = $ustmt->fetchAll();

            // Ambil data settings
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

    // 2. PROSES LOGIN ADMIN / KASIR
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
                echo json_encode([
                    "status" => "success",
                    "user" => [
                        "id" => $user['id'],
                        "username" => $user['username'],
                        "role" => $user['role']
                    ]
                ]);
            } else {
                echo json_encode(["status" => "error", "message" => "Username atau Password salah!"]);
            }
        } catch (Exception $e) {
            echo json_encode(["status" => "error", "message" => "Gagal memproses login: " . $e->getMessage()]);
        }
        break;

    // 3. SIMPAN RESERVASI BARU ATAU EDIT (SAVE BOOKING)
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
            $totalPrice = (float)$inputData['totalPrice'];
            $createdAt = isset($inputData['createdAt']) ? $inputData['createdAt'] : date('Y-m-d H:i:s');
            
            // Konversi ISO timestamp ke format MySQL datetime jika diperlukan
            if (strpos($createdAt, 'T') !== false) {
                $createdAt = str_replace(['T', 'Z'], [' ', ''], $createdAt);
                $createdAt = substr($createdAt, 0, 19);
            }

            // Cek apakah booking sudah ada (Edit Mode)
            $checkStmt = $conn->prepare("SELECT id FROM bookings WHERE id = ?");
            $checkStmt->execute([$id]);
            $exists = $checkStmt->fetch();

            if ($exists) {
                // Mode Edit: Update Booking Utama
                $upStmt = $conn->prepare("UPDATE bookings SET customer_name = ?, check_in = ?, check_out = ?, discount = ?, dp = ?, total_price = ? WHERE id = ?");
                $upStmt->execute([$customerName, $checkIn, $checkOut, $discount, $dp, $totalPrice, $id]);

                // Hapus item dan addon lama, nanti diinsert ulang
                $delItems = $conn->prepare("DELETE FROM booking_items WHERE booking_id = ?");
                $delItems->execute([$id]);
                $delAddons = $conn->prepare("DELETE FROM booking_addons WHERE booking_id = ?");
                $delAddons->execute([$id]);
            } else {
                // Mode Tambah Baru: Insert Booking Utama
                $insStmt = $conn->prepare("INSERT INTO bookings (id, customer_name, check_in, check_out, discount, dp, total_price, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $insStmt->execute([$id, $customerName, $checkIn, $checkOut, $discount, $dp, $totalPrice, $createdAt]);
            }

            // Insert Items
            if (isset($inputData['items']) && is_array($inputData['items'])) {
                foreach ($inputData['items'] as $item) {
                    $itemStmt = $conn->prepare("INSERT INTO booking_items (booking_id, villa_id, qty, price_paid) VALUES (?, ?, ?, ?)");
                    $itemStmt->execute([$id, $item['villaId'], $item['qty'], $item['pricePaid']]);
                }
            }

            // Insert Addons
            if (isset($inputData['addons']) && is_array($inputData['addons'])) {
                foreach ($inputData['addons'] as $addon) {
                    $addonStmt = $conn->prepare("INSERT INTO booking_addons (booking_id, name, price) VALUES (?, ?, ?)");
                    $addonStmt->execute([$id, $addon['name'], $addon['price']]);
                }
            }

            $conn->commit();
            echo json_encode(["status" => "success", "message" => "Reservasi berhasil disimpan!"]);
        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            echo json_encode(["status" => "error", "message" => "Gagal menyimpan reservasi: " . $e->getMessage()]);
        }
        break;

    // 4. HAPUS RESERVASI (DELETE BOOKING)
    case 'delete_booking':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$inputData) {
            echo json_encode(["status" => "error", "message" => "Data kosong"]);
            break;
        }
        $id = $inputData['id'];

        try {
            $conn->beginTransaction();
            
            // Cascading delete sudah dihandle di FK database MySQL, namun kita pastikan manual
            $delAddons = $conn->prepare("DELETE FROM booking_addons WHERE booking_id = ?");
            $delAddons->execute([$id]);
            
            $delItems = $conn->prepare("DELETE FROM booking_items WHERE booking_id = ?");
            $delItems->execute([$id]);

            $stmt = $conn->prepare("DELETE FROM bookings WHERE id = ?");
            $stmt->execute([$id]);

            $conn->commit();
            echo json_encode(["status" => "success", "message" => "Booking terhapus dari MySQL"]);
        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            echo json_encode(["status" => "error", "message" => "Gagal menghapus booking: " . $e->getMessage()]);
        }
        break;

    // 5. SIMPAN ATAU EDIT JENIS VILLA (SAVE VILLA)
    case 'save_villa':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$inputData) {
            echo json_encode(["status" => "error", "message" => "Data kosong"]);
            break;
        }
        $id = $inputData['id'];
        $name = $inputData['name'];
        $price = (float)$inputData['price'];
        $stock = (int)$inputData['stock'];

        try {
            $checkStmt = $conn->prepare("SELECT id FROM villas WHERE id = ?");
            $checkStmt->execute([$id]);
            $exists = $checkStmt->fetch();

            if ($exists) {
                $stmt = $conn->prepare("UPDATE villas SET name = ?, price = ?, stock = ? WHERE id = ?");
                $stmt->execute([$name, $price, $stock, $id]);
            } else {
                $stmt = $conn->prepare("INSERT INTO villas (id, name, price, stock) VALUES (?, ?, ?, ?)");
                $stmt->execute([$id, $name, $price, $stock]);
            }
            echo json_encode(["status" => "success", "message" => "Data villa diperbarui di MySQL"]);
        } catch (Exception $e) {
            echo json_encode(["status" => "error", "message" => "Gagal mengelola villa: " . $e->getMessage()]);
        }
        break;

    // 6. HAPUS JENIS VILLA (DELETE VILLA)
    case 'delete_villa':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$inputData) {
            echo json_encode(["status" => "error", "message" => "Data kosong"]);
            break;
        }
        $id = $inputData['id'];

        try {
            // Cek apakah villa ini sedang digunakan di riwayat transaksi
            $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM booking_items WHERE villa_id = ?");
            $checkStmt->execute([$id]);
            $usage = $checkStmt->fetch();

            if ($usage['count'] > 0) {
                echo json_encode(["status" => "error", "message" => "Kamar tidak bisa dihapus karena telah terhubung ke transaksi aktif!"]);
                break;
            }

            $stmt = $conn->prepare("DELETE FROM villas WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(["status" => "success", "message" => "Villa dihapus dari MySQL"]);
        } catch (Exception $e) {
            echo json_encode(["status" => "error", "message" => "Gagal menghapus jenis villa: " . $e->getMessage()]);
        }
        break;

    // 7. KELOLA DATA AKUN LOGIN (SAVE ACCOUNT)
    case 'save_account':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$inputData) {
            echo json_encode(["status" => "error", "message" => "Data kosong"]);
            break;
        }
        $id = $inputData['id'];
        $username = trim($inputData['username']);
        $password = $inputData['password'];
        $role = $inputData['role'];

        try {
            $checkStmt = $conn->prepare("SELECT id FROM accounts WHERE id = ?");
            $checkStmt->execute([$id]);
            $exists = $checkStmt->fetch();

            if ($exists) {
                $stmt = $conn->prepare("UPDATE accounts SET username = ?, password = ?, role = ? WHERE id = ?");
                $stmt->execute([$username, $password, $role, $id]);
            } else {
                $stmt = $conn->prepare("INSERT INTO accounts (id, username, password, role) VALUES (?, ?, ?, ?)");
                $stmt->execute([$id, $username, $password, $role]);
            }
            echo json_encode(["status" => "success", "message" => "Akun login berhasil dikelola"]);
        } catch (Exception $e) {
            echo json_encode(["status" => "error", "message" => "Gagal mengelola akun: " . $e->getMessage()]);
        }
        break;

    // 8. HAPUS AKUN LOGIN (DELETE ACCOUNT)
    case 'delete_account':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$inputData) {
            echo json_encode(["status" => "error", "message" => "Data kosong"]);
            break;
        }
        $id = $inputData['id'];

        try {
            $stmt = $conn->prepare("DELETE FROM accounts WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(["status" => "success", "message" => "Akun dihapus dari MySQL"]);
        } catch (Exception $e) {
            echo json_encode(["status" => "error", "message" => "Gagal menghapus akun: " . $e->getMessage()]);
        }
        break;

    // 9. UPDATE IDENTITAS BRANDING VILLA (SAVE BRANDING)
    case 'save_branding':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$inputData) {
            echo json_encode(["status" => "error", "message" => "Data kosong"]);
            break;
        }

        try {
            $conn->beginTransaction();

            foreach ($inputData as $key => $val) {
                // Update atau Insert jika belum ada
                $checkStmt = $conn->prepare("SELECT setting_key FROM settings WHERE setting_key = ?");
                $checkStmt->execute([$key]);
                $exists = $checkStmt->fetch();

                if ($exists) {
                    $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
                    $stmt->execute([$val, $key]);
                } else {
                    $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
                    $stmt->execute([$key, $val]);
                }
            }

            $conn->commit();
            echo json_encode(["status" => "success", "message" => "Identitas branding berhasil diperbarui di MySQL"]);
        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            echo json_encode(["status" => "error", "message" => "Gagal memperbarui branding: " . $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(["status" => "error", "message" => "Aksi tidak dikenali"]);
        break;
}
?>