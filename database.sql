-- ==========================================
-- SKEMA DATABASE UNTUK DASHBOARD BOOKING VILLA
-- Database Name: bald6243_u12345_baleody
-- ==========================================

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS booking_addons;
DROP TABLE IF EXISTS booking_items;
DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS villas;
DROP TABLE IF EXISTS accounts;
DROP TABLE IF EXISTS settings;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. TABEL AKUN LOGIN (ACCOUNTS)
CREATE TABLE accounts (
id varchar(50) NOT NULL,
username varchar(100) NOT NULL,
password varchar(255) NOT NULL,
role varchar(50) NOT NULL DEFAULT 'Kasir',
PRIMARY KEY (id),
UNIQUE KEY username_unique (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Data Bawaan Akun (Password disimpan polos untuk kemudahan atau bisa disesuaikan md5/bcrypt jika diinginkan)
-- Di PHP, kita akan mencocokkan password ini secara langsung
INSERT INTO accounts (id, username, password, role) VALUES
('u-1', 'admin', 'admin', 'Administrator'),
('u-2', 'editor1', '123', 'Editor'),
('u-3', 'kasir1', '123', 'Kasir');

-- 2. TABEL KATEGORI VILLA (VILLAS)
CREATE TABLE villas (
id varchar(50) NOT NULL,
name varchar(150) NOT NULL,
price decimal(15,2) NOT NULL,
stock int(11) NOT NULL,
PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Data Bawaan Villa
INSERT INTO villas (id, name, price, stock) VALUES
('v-1', 'Villa Emerald A (2 Kamar)', 1500000.00, 5),
('v-2', 'Villa Sapphire B (3 Kamar)', 2200000.00, 3),
('v-3', 'Villa Ruby C (Pool Villa)', 3500000.00, 2),
('v-4', 'Villa Diamond D (Grand Suite)', 5000000.00, 1),
('v-5', 'Villa Standard E (Eco Room)', 850000.00, 8);

-- 3. TABEL RESERVASI UTAMA (BOOKINGS)
CREATE TABLE bookings (
id varchar(50) NOT NULL,
customer_name varchar(150) NOT NULL,
check_in date NOT NULL,
check_out date NOT NULL,
discount decimal(15,2) NOT NULL DEFAULT 0.00,
dp decimal(15,2) NOT NULL DEFAULT 0.00,
total_price decimal(15,2) NOT NULL,
created_at datetime NOT NULL,
PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Data Bawaan Reservasi
INSERT INTO bookings (id, customer_name, check_in, check_out, discount, dp, total_price, created_at) VALUES
('b-101', 'Ahmad Subarjo', '2026-07-02', '2026-07-04', 100000.00, 1500000.00, 7800000.00, '2026-07-01 10:30:00'),
('b-102', 'Rina Wijaya', '2026-07-05', '2026-07-07', 200000.00, 3000000.00, 6800000.00, '2026-07-01 14:20:00'),
('b-103', 'Pak Joko', '2026-07-05', '2026-07-06', 0.00, 500000.00, 1500000.00, '2026-07-02 09:00:00');

-- 4. TABEL DETAIL VILLA DIPESAN (BOOKING_ITEMS)
CREATE TABLE booking_items (
id int(11) NOT NULL AUTO_INCREMENT,
booking_id varchar(50) NOT NULL,
villa_id varchar(50) NOT NULL,
qty int(11) NOT NULL,
price_paid decimal(15,2) NOT NULL,
PRIMARY KEY (id),
KEY fk_items_bookings (booking_id),
CONSTRAINT fk_items_bookings FOREIGN KEY (booking_id) REFERENCES bookings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Data Bawaan Detail Item Booking
INSERT INTO booking_items (booking_id, villa_id, qty, price_paid) VALUES
('b-101', 'v-1', 2, 1500000.00),
('b-101', 'v-5', 1, 850000.00),
('b-102', 'v-3', 1, 3500000.00),
('b-103', 'v-1', 1, 1500000.00);

-- 5. TABEL ADDON DETAIL (BOOKING_ADDONS)
CREATE TABLE booking_addons (
id int(11) NOT NULL AUTO_INCREMENT,
booking_id varchar(50) NOT NULL,
name varchar(150) NOT NULL,
price decimal(15,2) NOT NULL,
PRIMARY KEY (id),
KEY fk_addons_bookings (booking_id),
CONSTRAINT fk_addons_bookings FOREIGN KEY (booking_id) REFERENCES bookings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Data Bawaan Addons
INSERT INTO booking_addons (booking_id, name, price) VALUES
('b-101', 'Sewa BBQ Grill', 150000.00),
('b-101', 'Extra Bed', 100000.00);

-- 6. TABEL PENGATURAN / IDENTITAS BISNIS (SETTINGS)
CREATE TABLE settings (
setting_key varchar(100) NOT NULL,
setting_value longtext NOT NULL,
PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Data Bawaan Branding
INSERT INTO settings (setting_key, setting_value) VALUES
('villaName', 'Villa Grand Oasis'),
('villaLogo', ''),
('villaFavicon', '');