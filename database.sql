-- ============================================================
-- KHETBAZAAR - Agriculture Marketplace Database
-- Import via phpMyAdmin OR run: mysql -u root < database.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS khetbazaar CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE khetbazaar;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('buyer','seller','admin') NOT NULL DEFAULT 'buyer',
    phone VARCHAR(20),
    address TEXT,
    profile_pic VARCHAR(255) DEFAULT 'default.png',
    is_verified TINYINT(1) DEFAULT 1,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    icon VARCHAR(10) DEFAULT '🌿',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    category_id INT,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    unit ENUM('kg','quintal','ton','piece','dozen','bag','litre') DEFAULT 'kg',
    location VARCHAR(200),
    image VARCHAR(255),
    is_organic TINYINT(1) DEFAULT 0,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    ai_scan_status ENUM('not_scanned','healthy','diseased','fake','suspicious') DEFAULT 'not_scanned',
    ai_scan_notes TEXT,
    ai_confidence DECIMAL(5,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

CREATE TABLE cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    buyer_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT DEFAULT 1,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    buyer_id INT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending','confirmed','shipped','delivered','cancelled') DEFAULT 'pending',
    payment_method ENUM('upi','card','cod','wallet') DEFAULT 'cod',
    payment_status ENUM('pending','paid','failed','refunded') DEFAULT 'pending',
    payment_txn_id VARCHAR(100),
    shipping_address TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

CREATE TABLE ai_scan_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT,
    scanned_by INT,
    image_path VARCHAR(255),
    result ENUM('healthy','diseased','fake','suspicious') NOT NULL,
    confidence DECIMAL(5,2) DEFAULT 0,
    crop_type VARCHAR(100),
    disease_name VARCHAR(200),
    quality_grade VARCHAR(5),
    recommendations TEXT,
    scanned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    FOREIGN KEY (scanned_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    buyer_id INT NOT NULL,
    rating TINYINT(1) NOT NULL,
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- SEED DATA
-- ============================================================

INSERT INTO categories (name, icon) VALUES
('Vegetables','🥦'),('Fruits','🍎'),('Grains & Cereals','🌾'),
('Pulses & Legumes','🫘'),('Spices & Herbs','🌶️'),
('Dairy Products','🥛'),('Flowers','🌸'),('Seeds','🌱');

-- Passwords are all: password  (bcrypt hash)
INSERT INTO users (name,email,password,role,phone,address,is_verified) VALUES
('Admin User','admin@khetbazaar.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uXutHybfs','admin','9800000000','New Delhi, India',1),
('Raman Singh','seller@khetbazaar.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uXutHybfs','seller','9876543210','Dehradun, Uttarakhand',1),
('Priya Sharma','buyer@khetbazaar.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uXutHybfs','buyer','9812345678','Haridwar, Uttarakhand',1);

INSERT INTO products (seller_id,category_id,name,description,price,quantity,unit,location,is_organic,status,ai_scan_status,ai_confidence) VALUES
(2,1,'Fresh Red Tomatoes','Organically grown red tomatoes, farm fresh picked today',35.00,500,'kg','Dehradun',1,'approved','healthy',96.5),
(2,2,'Alphonso Mangoes','Premium Alphonso mangoes from Ratnagiri, naturally ripened',250.00,200,'kg','Ratnagiri',0,'approved','healthy',92.3),
(2,3,'Basmati Rice','Long grain aromatic basmati rice, new harvest 2025',85.00,1000,'kg','Punjab',0,'approved','healthy',88.0),
(2,4,'Masoor Dal','Red lentils premium quality, machine cleaned and sorted',95.00,300,'kg','Lucknow',1,'approved','healthy',90.1),
(2,5,'Organic Turmeric','Pure organic turmeric, high curcumin 5%+ content',180.00,150,'kg','Kerala',1,'approved','healthy',94.7),
(2,1,'Green Capsicum','Fresh green bell peppers, crispy and sweet',55.00,250,'kg','Shimla',0,'approved','healthy',87.5),
(2,2,'Kesar Mango','Authentic Kesar mangoes from Gujarat, heavenly aroma',200.00,180,'kg','Gujarat',1,'approved','healthy',93.2),
(2,3,'Wheat Flour (Atta)','Whole wheat stone-ground flour, freshly milled',45.00,800,'kg','Haryana',0,'approved','healthy',85.0);
