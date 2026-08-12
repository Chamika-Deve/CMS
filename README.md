<div align="center">

# 🖥️ CSMS
### Computer Shop Management System (TechShop)

<p align="center">
  <img src="https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP" />
  <img src="https://img.shields.io/badge/MySQL-4479A1?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL" />
  <img src="https://img.shields.io/badge/PDO-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PDO" />
  <img src="https://img.shields.io/badge/Tailwind_CSS-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white" alt="Tailwind CSS" />
  <img src="https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black" alt="JavaScript" />
</p>

A native PHP + MySQL web app for computer retail and repair shops. It covers barcode-driven inventory, serial-number stock, point of sale, custom PC quotations, purchases, printable receipts, and business reports.

</div>

---

## 📖 About

CSMS (branded in the UI as **TechShop**) is a session-based shop management system. Staff log in with role-based access, scan product barcodes and unique serial numbers, sell stock through a dedicated POS, assemble custom PC quotes, and review sales analytics.

The app is designed to run on **XAMPP**, **Laragon**, or any local Apache + PHP + MySQL stack. There is no Composer/npm build step — pages are plain PHP with Tailwind CSS and Font Awesome loaded from CDN.

## ✨ Features

### Implemented

| Module | Description |
| --- | --- |
| **Authentication** | Email/password login with bcrypt (`password_verify`). Disabled accounts are rejected. |
| **Role-based nav** | Sidebar menus differ for Admin, Manager, Cashier, and Technician. |
| **Products & inventory** | Catalog with categories, brands, EAN/UPC, selling / min / max price, warranty months, and Active / Discontinued status. |
| **Rapid stock entry** | Scan a product barcode, then scan unique serial numbers into `product_serials` (in-stock). Audio beep feedback. |
| **Serial management** | Per-product serial list, add/check/delete, status (`in_stock`, `sold`, `returned`, `repair`, `defective`). |
| **Barcode lookup** | New-product form can look up EAN/UPC via the UPCItemDB trial API. |
| **Point of Sale** | Product grid, serial picker, direct SN scan, customer select, cash / card / bank, tax from settings, min/max price guard. |
| **Printable bills** | Thermal-style receipt (`80mm` / `58mm` / `A4`) with grouped items and serials. |
| **Custom PC builder** | Slot-based builder (CPU, motherboard, RAM, GPU, storage, PSU, case, cooler) and printable quotation. |
| **Purchases / stock-in** | Rapid inbound scan: product barcode then serials, with a session log. |
| **Reports & analytics** | Date ranges, revenue / profit / items / tax KPIs, sales trend, payment mix, category breakdown, top products, low-stock alerts, repair counts, recent invoices. Printable. |
| **Settings (Admin)** | Shop profile, currency, tax, receipt width, return policy, timezone, system name, logo upload, factory reset. |

### Schema ready, UI not built yet

These tables exist in `detabase/csms_db.sql` and appear in the sidebar, but the pages currently show **Module Under Construction**:

- Customers (`pages/customers.php`)
- Suppliers (`pages/suppliers.php`)
- Repair & Service (`pages/repairs.php`)
- Warranty & Returns (`pages/warranty.php`)
- Users & Roles (`pages/users.php`)

The dashboard overview cards are still demo placeholders (not live SQL).

## 🧰 Tech stack

- **Backend:** PHP 8+ (sessions, PDO, prepared statements)
- **Database:** MySQL / MariaDB (`csms_db`, utf8mb4)
- **Frontend:** Tailwind CSS Play CDN, Inter (Google Fonts), Font Awesome 6
- **Server:** Apache (or any PHP-capable web server)

## 📋 Requirements

- PHP 8.0 or newer (PDO MySQL extension enabled)
- MySQL 5.7+ / 8.x or MariaDB
- Apache with `mod_rewrite` optional (pretty URLs are not required)
- Writable `uploads/logo/` for shop logos

## 🚀 Installation

### 1. Clone or copy the project

```bash
git clone https://github.com/Chamika-Deve/CMS.git
```

Place the folder in your web root, for example:

- XAMPP: `C:\xampp\htdocs\CMS`
- Laragon: `C:\laragon\www\CMS`

### 2. Create the database

1. Start MySQL (XAMPP / Laragon).
2. Open phpMyAdmin or the MySQL CLI.
3. Import the dump:

```bash
mysql -u root -p < detabase/csms_db.sql
```

Or in phpMyAdmin: **Import** → select `detabase/csms_db.sql`.

The dump creates `csms_db`, all tables, seed products, serials, users, suppliers, and default settings.

### 3. Configure the connection

Edit `includes/db.php` if your MySQL credentials are not the XAMPP/Laragon defaults:

```php
$host = '127.0.0.1';
$db   = 'csms_db';
$user = 'root';
$pass = ''; // default empty password
```

### 4. Optional setup scripts

Run these once in the browser if you need them:

| Script | Purpose |
| --- | --- |
| `setup_settings.php` | Creates the `settings` table and inserts shop defaults (name, address, currency `Rs.`, timezone `Asia/Colombo`). |
| `alter_db.php` | Adds `min_price` / `max_price` on `products` if you imported an older schema. |

### 5. Open the app

Visit:

```text
http://localhost/CMS/
```

or your vhost equivalent. You land on the login screen (`index.php`).

## 🔐 Demo accounts

All seeded passwords are `password` (bcrypt hash in `users`).

| Role | Email | Password |
| --- | --- | --- |
| Admin | `admin@example.com` | `password` |
| Manager | `manager@example.com` | `password` |
| Cashier | `cashier@example.com` | `password` |
| Technician | `tech@example.com` | `password` |

**Change these before any production use.**

## 👥 Roles

Access is enforced in `includes/header.php` (sidebar) and on Admin-only pages such as Settings.

| Capability | Admin | Manager | Cashier | Technician |
| --- | --- | --- | --- | --- |
| Dashboard | ✓ | ✓ | ✓ | ✓ |
| Products & inventory | ✓ | ✓ | ✓ | ✓ |
| Sales / POS | ✓ | ✓ | ✓ | |
| Build PC (quote) | ✓ | ✓ | ✓ | |
| Purchases | ✓ | ✓ | | |
| Customers | ✓ | ✓ | ✓ | |
| Suppliers | ✓ | ✓ | | |
| Repair & service | ✓ | ✓ | | ✓ |
| Warranty & returns | ✓ | ✓ | ✓ | |
| Reports | ✓ | ✓ | | |
| Users & roles | ✓ | | | |
| Settings | ✓ | | | |

## 🗂️ Project structure

```text
CMS/
├── detabase/
│   └── csms_db.sql          # Full schema + seed data
├── includes/
│   ├── db.php               # PDO connection
│   ├── header.php           # Auth guard, sidebar, layout start
│   └── footer.php           # Layout end
├── pages/
│   ├── dashboard.php        # Overview (placeholder KPIs)
│   ├── products.php         # Catalog, categories, rapid stock-in
│   ├── product_add.php      # New product model
│   ├── product_edit.php     # Edit product
│   ├── product_serials.php  # Per-product serial inventory
│   ├── pos.php              # Point of sale
│   ├── print_bill.php       # Sale receipt
│   ├── build_pc.php         # Custom PC quotation builder
│   ├── print_quote.php      # Printable PC quote
│   ├── purchases.php        # Rapid inbound stock entry
│   ├── reports.php          # Analytics
│   ├── settings.php         # Admin shop / billing / logo
│   ├── customers.php        # Placeholder
│   ├── suppliers.php        # Placeholder
│   ├── repairs.php          # Placeholder
│   ├── warranty.php         # Placeholder
│   └── users.php            # Placeholder
├── uploads/
│   └── logo/                # Uploaded shop logos
├── index.php                # Login
├── logout.php               # Destroy session
├── setup_settings.php       # Seed settings table
├── alter_db.php             # Product price-range migration
└── dump_cat.php             # Debug: dump categories as JSON
```

## 🗄️ Database overview

| Table | Purpose |
| --- | --- |
| `users` | Staff accounts and roles |
| `products` | Product models (SKU, prices, warranty, brand, category) |
| `categories` | Hierarchical categories (`parent_id`) |
| `brands` | Brands (Dell, HP, Asus, Intel, Nvidia, …) |
| `product_serials` | Unique unit serials and stock status |
| `customers` | Customers and loyalty points |
| `suppliers` | Vendors |
| `purchases` / `purchase_items` | Supplier invoices |
| `sales` / `sale_items` | Completed sales and line items (linked to serials) |
| `repair_jobs` / `repair_parts_used` | Service tickets |
| `warranty_claims` | Warranty cases |
| `settings` | Key/value shop configuration |
| `activity_logs` | Audit trail (table exists; not yet written from the UI) |

## ⌨️ POS shortcuts

| Key | Action |
| --- | --- |
| **Ctrl** | Focus product / barcode search |
| **Alt** | Focus serial input on the last cart line |
| **Enter** in search | Select the only matching product, or look up a serial directly |

After checkout, the receipt opens in a new tab (`print_bill.php?id=…`) and stock counts refresh.

## ⚙️ Default shop settings

Seeded in `settings` (also reset from **Settings → Factory Reset**):

| Key | Default |
| --- | --- |
| Shop name | Tech Solutions Inc. |
| Address | 123 Main Street, Colombo 01 |
| Phone | +94 77 123 4567 |
| Currency | `Rs.` |
| Tax rate | `0` |
| Receipt width | `80mm` |
| Return policy | 7 days |
| Timezone | Asia/Colombo |
| System name | TechShop |

## 🔒 Security notes

- Passwords are stored with PHP `password_hash` / `password_verify`.
- Queries use PDO prepared statements.
- Pages under `pages/` require a logged-in session (`includes/header.php`).
- Settings and logo upload are Admin-only.
- Demo credentials are public — rotate them and set a MySQL password before deploying.
- `alter_db.php`, `setup_settings.php`, and `dump_cat.php` are maintenance/debug scripts; do not leave them exposed on a public host.

## 🗺️ Roadmap

- [ ] Live dashboard KPIs from `sales` / `product_serials` / `repair_jobs`
- [ ] Customers CRUD and POS “add customer”
- [ ] Suppliers CRUD and full purchase invoices
- [ ] Repair job workflow (status, parts used, technician assign)
- [ ] Warranty claims against sold serials
- [ ] Users & roles admin UI
- [ ] Activity log writes
- [ ] Mobile sidebar (hamburger is present but not wired)

## 📄 License

This project is provided as-is for shop operations and learning. Add a license file if you intend to distribute it.

---

**TechShop / CSMS** — Computer Shop Management System.
