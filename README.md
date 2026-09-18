# DBM Student Management Dashboard

Complete PHP + MySQL Student Management System for Diploma in Business Management (DBM) Course.

Based on the structure of your Excel file (`DBM Course Paid Students.xlsx`).

## Features

- **Dashboard** – Total students, active batches, revenue, pending payments, fully paid, new registrations, certificates pending
- **Students** – List, search, filter by batch/status, add student, detailed student profile
- **Batches** – Manage all batches (1st batch → DBM 26)
- **Payments** – Record installments, pending payments list, fully paid students, payment history with date filter
- **Reports** – Student summary, Income report (monthly), Batch-wise collection report
- **WhatsApp** direct links from student list
- Modern Bootstrap 5 UI + DataTables

## Requirements

- PHP 8.0+
- MySQL 5.7+ / MariaDB
- Apache / Nginx (or XAMPP / Laragon / WAMP)

## Installation

### 1. Database Setup

```bash
# Create database and import schema
mysql -u root -p < sql/schema.sql
```

Or open phpMyAdmin → Import → select `sql/schema.sql`

### 2. Configure Database

Edit `config/database.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'dbm_management');
define('DB_USER', 'root');
define('DB_PASS', 'your_password');
```

Also update `APP_URL` if needed:

```php
define('APP_URL', 'http://localhost/dbm-dashboard');
```

### 3. Place Files

Copy the entire `dbm-dashboard` folder to your web root:

- XAMPP: `C:\xampp\htdocs\dbm-dashboard`
- Laragon: `C:\laragon\www\dbm-dashboard`

### 4. Open in Browser

```
http://localhost/dbm-dashboard
```

Default admin login (you can add authentication later):
- Email: `admin@dbm.local`
- Password: `password`

## Folder Structure

```
dbm-dashboard/
├── config/database.php
├── includes/header.php, sidebar.php, footer.php
├── assets/css/style.css
├── index.php                  ← Dashboard
├── students.php
├── add-student.php
├── student-profile.php
├── batches.php
├── add-batch.php
├── payments.php
├── pending-payments.php
├── paid-students.php
├── payment-history.php
├── assignments.php
├── exams.php
├── certificates.php
├── reports/
│   ├── student-report.php
│   ├── income-report.php
│   └── batch-report.php
└── sql/schema.sql
```

## How it maps to your Excel

| Excel Column              | Database Field                  |
|---------------------------|---------------------------------|
| Register No               | students.register_no            |
| Name                      | students.full_name              |
| Whatsapp No               | students.whatsapp_no            |
| Create..?                 | students.access_given           |
| Username / Password       | students.username / password_plain |
| sent                      | students.credentials_sent       |
| Course fee                | batches.course_fee              |
| 01st / 02nd INSTALEMENT…  | payments table (installment_no) |
| Bank / Ref No / Date      | payments.bank / ref_no / payment_date |
| Batch sheets              | batches table                   |

## Next Steps (Optional Improvements)

1. Add login authentication (session based)
2. Excel Import tool (upload sheet → map to students + payments)
3. Assignment / Exam mark entry pages
4. Certificate status workflow + courier tracking UI
5. SMS / WhatsApp notification integration
6. Export to Excel / PDF reports

---

Created based on your DBM Course Paid Students Excel structure.
