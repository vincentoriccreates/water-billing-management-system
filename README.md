# 💧 AquaBill – Water Billing Management System
### PHP + MySQL | Barangay Water Utility

---

## 📋 Requirements

- PHP 7.4 or higher (PHP 8.x recommended)
- MySQL 5.7 or higher / MariaDB 10.3+
- Apache or Nginx with mod_rewrite
- Web browser (Chrome, Firefox, Edge, Safari)

---

## 🚀 Installation Steps

### 1. Copy Files
Place the `aquabill/` folder into your web server's root directory:
- **XAMPP**: `C:/xampp/htdocs/aquabill/`
- **WAMP**: `C:/wamp64/www/aquabill/`
- **Linux**: `/var/www/html/aquabill/`

### 2. Create the Database
1. Open **phpMyAdmin** (or MySQL command line)
2. Create a new database named `aquabill`
3. Import the file: `config/schema.sql`
   - In phpMyAdmin: click the database → **Import** → choose `schema.sql` → Go

### 3. Configure Database Connection
Open `config/db.php` and update your credentials:

```php
define('DB_HOST', 'localhost');   // Your MySQL host
define('DB_USER', 'root');        // Your MySQL username
define('DB_PASS', '');            // Your MySQL password
define('DB_NAME', 'aquabill');    // Database name
```

### 4. Access the System
Open your browser and navigate to:
```
http://localhost/aquabill/
```

---

## 🔑 Demo Login Credentials

| Role  | Email                    | Password   |
|-------|--------------------------|------------|
| Admin | admin@barangay.gov       | admin123   |
| Staff | staff@barangay.gov       | staff123   |

> **Note:** For production use, change passwords immediately via User Accounts page.

---

## 📁 Folder Structure

```
aquabill/
├── config/
│   ├── db.php          ← Database connection & constants
│   └── schema.sql      ← Database schema + seed data
├── includes/
│   ├── functions.php   ← Core helpers, auth, formatting
│   └── header.php      ← Layout header & footer renderer
├── pages/              ← (reserved for future sub-modules)
├── assets/
│   ├── css/
│   │   └── style.css   ← Full stylesheet (light + dark mode)
│   └── js/
│       └── app.js      ← UI interactions & chart rendering
├── index.php           ← Login page
├── logout.php          ← Session destroy
├── dashboard.php       ← Dashboard with stats & charts
├── customers.php       ← Customer management (CRUD)
├── readings.php        ← Meter reading management
├── billing.php         ← Bill generation & statements
├── payments.php        ← Payment recording & receipts
├── reports.php         ← Reports with CSV export
└── users.php           ← User account management (Admin only)
```

---

## ✨ Features

### 🔐 Authentication
- Login/logout with session-based auth
- Admin and Staff roles (Staff cannot delete customers or manage users)
- Password hashing with bcrypt

### 👥 Customer Management
- Add, edit, delete customers
- Fields: Name, Address, Contact, Meter No, Status (Active/Disconnected)
- Auto-generated Account Numbers (C001, C002...)
- Search, filter by status, pagination

### 💧 Meter Readings
- Add monthly readings per customer
- Auto-calculates consumption (current − previous)
- Warns if current < previous
- Filter by customer

### 💵 Billing
- Generate bills with auto-calculation:
  - Base Charge: ₱120.00
  - Water Usage: consumption × ₱35.00/m³
  - Optional penalty fee
- View printable billing statement
- Billing status: Unpaid / Paid / Overdue
- Duplicate bill protection (same customer + month)

### 💳 Payments
- Record full or partial payments
- Methods: Cash, GCash, PayMaya, Bank Transfer, Check
- Auto-updates bill to "Paid" when fully settled
- Printable official receipts with receipt numbers

### 📊 Reports
- Monthly Billing Summary
- Payment Collections
- Unpaid Accounts
- Customer Usage Report
- CSV Export for all report types

### 🌙 Dark Mode
- Toggle between light and dark themes
- Preference saved in session

---

## ⚙️ Configuration

### Billing Rates (in `config/db.php`)
```php
define('RATE_PER_CUBIC', 35.00);   // ₱ per cubic meter
define('BASE_CHARGE',    120.00);  // Monthly base charge
```

### Changing App Name
```php
define('APP_NAME',    'AquaBill');
define('APP_TAGLINE', 'Barangay Water Billing System');
```

---

## 🔒 Security Notes

1. **Change demo passwords** in production immediately
2. The system uses **PDO prepared statements** for all queries (SQL injection safe)
3. All user input is sanitized with `htmlspecialchars()`
4. Passwords are stored as **bcrypt hashes**
5. Consider adding HTTPS for production deployments
6. Restrict `config/` folder access via `.htaccess` in production

---

## 🆘 Troubleshooting

| Problem | Solution |
|---------|----------|
| White screen / errors | Enable error display: add `ini_set('display_errors',1)` to top of `index.php` |
| "Database Connection Failed" | Check `config/db.php` credentials, ensure MySQL is running |
| Login fails | Re-import `schema.sql` to reset seed users |
| CSS not loading | Check file permissions on `assets/` folder |
| Can't delete customers | Must be logged in as Admin |

---

## 📞 Support

For issues with the system, check the PHP error logs:
- XAMPP: `C:/xampp/apache/logs/error.log`
- Linux: `/var/log/apache2/error.log`

---

*AquaBill v1.0 — Built for Barangay Water Utility Management*

---

## 🆕 v1.2 — New Features

### 📥 CSV Import (`import.php`)
- Import bulk meter readings from CSV
- Import bulk customers from CSV
- Downloadable template files
- Error reporting per row

### 💧 Billing Rate Tiers (`rates.php`)
- Define tiered pricing (Lifeline, Basic, Standard, Commercial, Industrial)
- Enable/disable tiers
- Live bill calculator preview
- Admin-only management

### 🗄️ Backup & Restore (`backup.php`)
- Download full SQL backup with one click
- Upload and restore from .sql file
- Table statistics and size overview
- Maintenance recommendations

### 📊 Enhanced Dashboard (`dashboard.php`)
- SVG line chart for consumption trends (with area fill)
- SVG donut/pie chart for bill status breakdown
- Top 5 water consumers with progress bars
- Revenue comparison vs. last month (% change)
- Upcoming due bills panel (next 10 days)
- Live activity feed (payments, bills, new customers)

### 👤 Profile Page (`profile.php`)
- Update name/email
- Change password with confirmation check
- Dark mode preference toggle
- System info for admin (PHP version, timezone, etc.)

### 🔔 Notifications (`notifications.php`)
- Overdue bill alerts with days overdue
- Bills due within 7 days
- Customers with no reading this month
- High consumption alerts (>40 m³)
- Disconnected customers with unpaid bills
- Live notification badge in sidebar

### ⚙️ Settings (`settings.php`) — Admin only
- Bulk mark bills as Overdue
- Apply penalty to all overdue bills
- Bulk update due dates for unpaid bills
- Archive/purge old paid bills

### 🔍 Customer Detail (`customer_detail.php`)
- Full history view per customer
- Reading, billing, and payment history in one page
- Summary stats (total billed, paid, balance, avg consumption)

---

## 📁 Complete File List (v1.2)

```
aquabill/
├── config/
│   ├── db.php
│   └── schema.sql
├── includes/
│   ├── functions.php
│   └── header.php
├── assets/
│   ├── css/
│   │   ├── style.css
│   │   └── print.css
│   └── js/
│       └── app.js
├── .htaccess              ← Apache security rules
├── index.php              ← Login
├── logout.php
├── dashboard.php          ← Enhanced with SVG charts
├── customers.php
├── customer_detail.php    ← NEW: Full customer history
├── readings.php
├── billing.php
├── payments.php
├── reports.php
├── notifications.php      ← NEW: Alert center
├── profile.php            ← NEW: User profile & password
├── settings.php           ← NEW: Admin bulk actions
├── rates.php              ← NEW: Billing rate tiers
├── backup.php             ← NEW: DB backup & restore
├── import.php             ← NEW: CSV bulk import
└── users.php
```
