<div align="center">
  
  # 🖥️ CSMS 
  ### Computer Shop Management System
  
  <p align="center">
    <img src="https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP" />
    <img src="https://img.shields.io/badge/MySQL-00000F?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL" />
    <img src="https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black" alt="JavaScript" />
    <img src="https://img.shields.io/badge/Status-Active-00FF00?style=for-the-badge" alt="Status" />
  </p>
  
  <p align="center">
    A comprehensive, high-performance web application designed to streamline the day-to-day operations of computer retail and repair shops.
  </p>
</div>

---

> **System Status:** `Online` | **Access Level:** `Admin/Staff` 

## 🚀 About the Project

This system provides a centralized dashboard to manage everything from point-of-sale (POS) transactions and inventory to custom PC builds and hardware repairs. It is optimized to help shop owners maintain accurate records of customers, suppliers, warranties, and daily sales.

## ✨ Core Features

- **🛒 Point of Sale (POS):** Process sales efficiently with a dedicated, responsive POS interface (`pos.php`).
- **🧾 Billing & Quotes:** Automatically generate and print professional bills (`print_bill.php`) and custom quotes (`print_quote.php`).
- **⚙️ Custom PC Builder:** A specialized module allowing customers to mix and match compatible parts to build custom rigs (`build_pc.php`).
- **📦 Advanced Inventory:** Add, edit, and track products, including individual serial number tracking for hardware (`product_serials.php`).
- **👥 CRM (Customers & Suppliers):** Keep structured, easily searchable records of both your clientele and your vendors.
- **🔧 Repairs & Warranties:** Track ongoing customer hardware repairs and manage strict product warranty periods.
- **📊 Analytics & Reports:** Manage shop purchases and generate business analytics/reports for data-driven decisions.
- **🔐 System Admin:** Full user access control and customizable system settings (including dynamic logo uploads).

## 🗂️ Project Structure

```text
CMS-main/
├── detabase/         # Contains csms_db.sql for database initialization
├── includes/         # Core reusable components (db.php, header, footer)
├── pages/            # Main application modules (Dashboard, POS, Products, etc.)
├── uploads/logo/     # Dynamically uploaded system assets (e.g., brand logos)
├── index.php         # Secure login & entry point
└── setup_settings.php# Initial shop configuration interface
