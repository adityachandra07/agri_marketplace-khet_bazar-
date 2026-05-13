==========================================================
  KHETBAZAAR - Agriculture Marketplace
  Complete Setup Guide
==========================================================

REQUIREMENTS:
  - XAMPP (Apache + MySQL + PHP 7.4+)
  - Browser (Chrome / Firefox)

SETUP STEPS:
-----------
1. COPY PROJECT FOLDER
   Copy the "agri_marketplace" folder to:
   C:\xampp\htdocs\agri_marketplace

2. START XAMPP
   Open XAMPP Control Panel
   Start Apache and MySQL

3. SETUP DATABASE
   - Open browser → http://localhost/phpmyadmin
   - Click "New" → Create database: khetbazaar
   - Select khetbazaar → click "Import"
   - Choose file: database.sql → Click "Go"

4. RUN THE APP
   Open browser → http://localhost/agri_marketplace

5. LOGIN (Demo accounts - password: password)
   Buyer:  buyer@khetbazaar.com
   Seller: seller@khetbazaar.com
   Admin:  admin@khetbazaar.com

6. AI CROP DETECTION (Optional)
   - Open php/ai_scan.php
   - Replace YOUR_ANTHROPIC_API_KEY_HERE with real key
   - Get key from: https://console.anthropic.com
   - Without key: demo mode runs automatically

FOLDER STRUCTURE:
-----------------
agri_marketplace/
├── index.html         ← Homepage
├── login.html         ← Login (Buyer/Seller/Admin)
├── register.html      ← Registration
├── marketplace.html   ← Product listing
├── product.html       ← Product detail
├── cart.html          ← Cart + Fake Payment
├── seller.html        ← Seller Dashboard
├── admin.html         ← Admin Control Panel
├── ai_scan.html       ← AI Crop Detection
├── database.sql       ← Database schema + seed data
├── css/style.css      ← Shared styles
├── js/app.js          ← Shared JS utilities
├── php/
│   ├── config.php     ← DB configuration
│   ├── auth.php       ← Login/Register/Logout
│   ├── products.php   ← Product CRUD + search
│   ├── orders.php     ← Cart + Fake payment
│   ├── ai_scan.php    ← Claude Vision AI crop scan
│   └── admin.php      ← Admin stats + user mgmt
└── uploads/crops/     ← Uploaded product images

FEATURES:
---------
✅ Creative responsive UI (Playfair + DM Sans fonts)
✅ Role-based login: Buyer / Seller / Admin
✅ Product listing with search, filter, organic toggle
✅ AI crop disease detection (Claude Vision API)
✅ Fake payment: UPI / Card / COD / Wallet simulation
✅ Shopping cart with quantity management
✅ Seller dashboard: add products, AI scan, orders
✅ Admin panel: approve products, manage users, analytics
✅ XAMPP MySQL database with real CRUD
✅ Demo mode works without PHP backend

FAKE PAYMENT INFO:
------------------
- UPI: enter any UPI ID (e.g. demo@okaxis)
- Card: use 4242 4242 4242 4242, any expiry, any CVV
- COD: no info needed
- Wallet: enter any mobile number
- 95% success rate (5% random failure to simulate real gateway)
- Transaction ID generated on success

==========================================================
