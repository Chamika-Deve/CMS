Computer Shop Management System (CSMS)
Welcome to the Computer Shop Management System (CSMS)! This is a comprehensive, web-based PHP application designed to streamline the day-to-day operations of a computer retail and repair shop.

🚀 About the Project
This system provides an all-in-one dashboard to manage everything from point-of-sale (POS) transactions and inventory to custom PC builds and hardware repairs. It is designed to help shop owners keep track of their customers, suppliers, warranties, and sales through a centralized platform.

✨ Key Features
Based on the project modules, this system includes the following core functionalities:

Point of Sale (POS): Process sales efficiently with a dedicated POS interface (pos.php).

Billing & Quotes: Automatically generate and print professional bills (print_bill.php) and quotes (print_quote.php).

Custom PC Builder: A specialized module to help customers mix and match compatible parts to build custom computers (build_pc.php).

Robust Inventory Management: Add, edit, and track products (products.php, product_add.php, product_edit.php), including individual serial number tracking for electronics (product_serials.php).

Customer & Supplier CRM: Keep organized records of both your clientele and your vendors (customers.php, suppliers.php).

Repairs & Warranties: Track ongoing customer hardware repairs (repairs.php) and manage product warranty periods (warranty.php).

Purchasing & Reports: Manage shop purchases (purchases.php) and generate business analytics/reports (reports.php).

System Administration: Full user access control (users.php) and customizable system settings, including logo uploads (settings.php, setup_settings.php).

🗂️ Project Structure
The repository is organized into the following main directories:

/detabase/: Contains the database initialization script (csms_db.sql).

/includes/: Contains core reusable components like database connection (db.php), header.php, and footer.php.

/pages/: The core application modules (Dashboard, POS, Products, Repairs, etc.).

/uploads/logo/: Stores dynamically uploaded system assets, such as company logos.

🛠️ Installation & Setup
Clone the Repository:

Bash
git clone https://github.com/yourusername/CMS-main.git
Environment Setup:

Ensure you have a local server environment running PHP and MySQL (e.g., XAMPP, WAMP, or LAMP).

Place the project folder in your server's root directory (e.g., htdocs or www).

Database Configuration:

Create a new MySQL database (e.g., csms_db).

Import the provided SQL dump located at /detabase/csms_db.sql into your new database.

Update the database credentials in /includes/db.php to match your local database settings.

System Setup:

Navigate to http://localhost/CMS-main/setup_settings.php to configure initial shop settings.

Log in via index.php.

💻 Technologies Used
Backend: PHP

Database: MySQL

Frontend: HTML/CSS/JavaScript (Standard web technologies)
