# Smart Pharmacy Inventory Management System

A beginner-friendly DBMS mini project built with HTML, CSS, JavaScript, PHP, and MySQL for XAMPP.

## Features

- Admin login authentication
- Dashboard summary cards
- Medicine management with search, edit, and delete
- Supplier management with search, edit, and delete
- Customer management with search, edit, and delete
- Dynamic billing with stock reduction after sale
- Inventory alerts for low stock and expired medicines
- Sales listing and reports
- Responsive sidebar dashboard UI
- PHP and JavaScript form validation
- Email invoice support with PHPMailer and Gmail SMTP
- Delivery management module with agents, tracking, and reports

## Folder Structure

Smart Pharmacy Inventory Management System/
├── assets/
- [delivery_dashboard.php](delivery_dashboard.php)
- [deliveries.php](deliveries.php)
- [delivery_agents.php](delivery_agents.php)
- [delivery_status.php](delivery_status.php)
- [delivery_reports.php](delivery_reports.php)
│   ├── css/
│   │   └── style.css
│   └── js/
│       └── app.js
├── docs/
│   └── er-diagram.md
├── includes/
│   ├── footer.php
│   ├── functions.php
│   ├── header.php
│   └── sidebar.php
├── sql/
│   └── meditrack_db.sql
├── add_medicine.php
├── billing.php
├── customers.php
├── dashboard.php
├── db_connect.php
├── index.php
├── inventory.php
├── login.php
├── logout.php
├── medicines.php
├── reports.php
├── sales.php
└── suppliers.php
```

## Required Pages

- `index.php`
- `login.php`
- `dashboard.php`
- `medicines.php`
- `add_medicine.php`
- `suppliers.php`
- `customers.php`
- `billing.php`
- `inventory.php`
- `sales.php`
- `reports.php`
- `logout.php`
- `db_connect.php`

## Database Setup

### Database Name
`meditrack_db`

### Import Steps
1. Start your existing MySQL Server service (port 3306).
2. Start Apache from XAMPP.
3. Open `http://localhost/phpmyadmin`.
4. Login using your MySQL root credentials.
5. Import [sql/meditrack_db.sql](sql/meditrack_db.sql) into `meditrack_db`.
6. Copy the project folder into `xampp/htdocs/`.
7. Open the project from `http://localhost/medtrack/`.

### Notes for This Build

- `db_connect.php` is preconfigured for `localhost`, `root`, `harshit`, and `meditrack_db`.
- If `meditrack_db` tables are missing, the app auto-imports schema from `sql/meditrack_db.sql` on first run.
- Keep XAMPP MySQL disabled when using an existing MySQL service to avoid port conflicts.
- Configure Gmail SMTP credentials through environment variables or `includes/email_config.php` before enabling invoice email sending.
- Delivery tables are auto-created from `sql/meditrack_delivery.sql`.

## Tables Used

- `admin_users` for login
- `supplier`
- `medicine`
- `customer`
- `employee`
- `bill`
- `sales`

## Default Login

- Username: `admin`
- Password: `admin123`

## ER Diagram Summary

See [docs/er-diagram.md](docs/er-diagram.md) for the relationship explanation.

## Notes

- The project uses plain PHP and MySQL only.
- The admin password is stored as plain text for simple classroom demonstration.
- After adding a sale, the medicine stock is automatically reduced.
