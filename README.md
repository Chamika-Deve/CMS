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

### Additional modules

The current version also includes customer and supplier CRUD, repair tickets with a public tracking link, warranty/RMA claims, staff and role management, accounting/cash-drawer tools, audit logs, and live dashboard KPIs. These modules require the current schema; existing installations should run `php setup_full_system.php` once after updating.

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

The app reads database credentials from environment variables and otherwise uses standard local XAMPP/Laragon defaults (`root` with an empty password):

| Variable | Default |
| --- | --- |
| `DB_HOST` | `127.0.0.1` |
| `DB_PORT` | `3306` |
| `DB_NAME` | `csms_db` |
| `DB_USER` | `root` |
| `DB_PASS` | empty |
| `APP_DEBUG` | `false` |
| `CSMS_DEMO_QUICK_LOGIN` | `false` — when `true`, shows the 1-click demo role switcher on the login screen (development only) |

For Apache, set these with `SetEnv`; for PHP's development server or the CLI, export them in the shell. Do not commit real credentials to `includes/db.php`.

### 4. Setup and upgrades

The imported `detabase/csms_db.sql` already contains the complete current schema. To create a fresh schema without seed catalog data, or to upgrade an older installation, run:

```bash
DB_USER=root DB_PASS=your_password php setup_full_system.php
```

`setup_full_system.php` is idempotent. It creates missing tables/columns, repairs legacy repair and warranty schemas, inserts missing settings, and seeds missing demo staff without overwriting existing accounts. Set `CSMS_SEED_PASSWORD` to choose the initial seeded password. Maintenance scripts require CLI access or an authenticated SuperAdmin session.

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
| Inventory | `inventory@example.com` | `password` |
| Accountant | `accountant@example.com` | `password` |
| SuperAdmin | `superadmin@example.com` | `password` |

**Change these before any production use.** The setup script honors `CSMS_SEED_PASSWORD` when it creates missing accounts.

## 👥 Roles

Access is enforced server-side by `includes/auth.php`; hiding a navigation item is not the security boundary.

| Capability | Admin | Manager | Cashier | Technician | Inventory | Accountant |
| --- | --- | --- | --- | --- | --- | --- |
| Dashboard | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Products (view) | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Catalog/stock changes | ✓ | ✓ | | | ✓ | |
| Sales / POS and PC quotes | ✓ | ✓ | ✓ | | | |
| Purchases / GRN | ✓ | ✓ | | | ✓ | |
| Customers | ✓ | ✓ | ✓ | | | ✓ |
| Suppliers | ✓ | ✓ | | | ✓ | ✓ |
| Repair & service | ✓ | ✓ | ✓ | ✓ | | |
| Warranty & returns | ✓ | ✓ | ✓ | ✓ | ✓ | |
| Accounting | ✓ | ✓ | ✓ | | | ✓ |
| Reports | ✓ | ✓ | | ✓ | ✓ | ✓ |
| Users & audit | ✓ | ✓ | | | | |
| Shop settings | ✓ | | | | | |

SuperAdmin is restricted to infrastructure settings, backups, audit/user administration, and shop settings. Server-side checks enforce the same access rules as the navigation.

## 🗂️ Project structure

```text
CMS/
├── detabase/
│   └── csms_db.sql          # Full schema + seed data
├── includes/
│   ├── auth.php             # Session, role, and CSRF enforcement
│   ├── db.php               # Environment-driven PDO connection
│   ├── schema.php           # Idempotent schema creation/upgrades
│   ├── header.php           # Sidebar and layout start
│   └── footer.php           # Layout end
├── pages/
│   ├── dashboard.php        # Live sales, stock, and trend KPIs
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
│   ├── customers.php        # Customer/CRM management
│   ├── suppliers.php        # Supplier/AP management
│   ├── repairs.php          # Repair workflow and tracking links
│   ├── warranty.php         # Warranty and RMA claims
│   └── users.php            # Staff and role management
├── uploads/
│   └── logo/                # Uploaded shop logos
├── index.php                # Login
├── logout.php               # Destroy session
├── setup_settings.php       # Initialize/repair settings
├── alter_db.php             # Compatibility alias for full migration
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
| `expenses` / `cash_registers` | Expense ledger and cash-drawer reconciliation |
| `activity_logs` | Audit trail for purchasing, repairs, settings, and security actions |

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

- Passwords use `password_hash` / `password_verify`; the session ID and CSRF token rotate after login.
- `includes/auth.php` enforces authentication, server-side role checks, disabled-account checks, global session invalidation, and CSRF validation.
- POS checkout revalidates and locks serial stock, product ownership, and min/max pricing on the server before committing a sale.
- Queries use PDO prepared statements, and database credentials can be supplied through environment variables.
- Logo uploads are MIME/size validated and saved under random names; shop-setting keys are allowlisted.
- Public repair lookup accepts only ticket numbers or unguessable tracking tokens; quote approval requires the tracking token.
- Demo credentials are public — rotate them and set a MySQL password before deploying.
- Database/debug maintenance endpoints require CLI access or SuperAdmin authentication.

## 🗺️ Roadmap

- [ ] Add automated integration tests with disposable MySQL/MariaDB
- [ ] Add customer creation directly inside POS
- [ ] Add purchase-return links to original PO line items
- [ ] Add repair parts consumption from serialized inventory
- [ ] Expand activity logging across all CRUD actions
- [ ] Replace CDN frontend assets with a production build pipeline

## 📄 License

This project is provided as-is for shop operations and learning. Add a license file if you intend to distribute it.

---

**TechShop / CSMS** — Computer Shop Management System.
