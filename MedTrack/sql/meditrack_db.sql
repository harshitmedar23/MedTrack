CREATE DATABASE IF NOT EXISTS meditrack_db;
USE meditrack_db;

-- Admin login table for the system.
CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
);

CREATE TABLE IF NOT EXISTS supplier (
    Supplier_ID INT AUTO_INCREMENT PRIMARY KEY,
    Supplier_Name VARCHAR(100) NOT NULL,
    Phone VARCHAR(20) NOT NULL,
    Address TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS customer (
    Customer_ID INT AUTO_INCREMENT PRIMARY KEY,
    Customer_Name VARCHAR(100) NOT NULL,
    Phone VARCHAR(20) NOT NULL,
    Gender ENUM('Male', 'Female', 'Other') NOT NULL,
    Age INT NOT NULL
);

CREATE TABLE IF NOT EXISTS employee (
    Employee_ID INT AUTO_INCREMENT PRIMARY KEY,
    Employee_Name VARCHAR(100) NOT NULL,
    Role VARCHAR(50) NOT NULL,
    Phone VARCHAR(20) NOT NULL,
    Salary DECIMAL(10,2) NOT NULL
);

CREATE TABLE IF NOT EXISTS medicine (
    Medicine_ID INT AUTO_INCREMENT PRIMARY KEY,
    Medicine_Name VARCHAR(120) NOT NULL,
    Category VARCHAR(80) NOT NULL,
    Price DECIMAL(10,2) NOT NULL,
    Quantity INT NOT NULL DEFAULT 0,
    Expiry_Date DATE NOT NULL,
    Medicine_Image VARCHAR(255) DEFAULT NULL,
    Supplier_ID INT NOT NULL,
    CONSTRAINT fk_medicine_supplier
        FOREIGN KEY (Supplier_ID) REFERENCES supplier(Supplier_ID)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS bill (
    Bill_ID INT AUTO_INCREMENT PRIMARY KEY,
    Customer_ID INT NOT NULL,
    Employee_ID INT NOT NULL,
    Total_Amount DECIMAL(10,2) NOT NULL,
    Bill_Date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_bill_customer
        FOREIGN KEY (Customer_ID) REFERENCES customer(Customer_ID)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_bill_employee
        FOREIGN KEY (Employee_ID) REFERENCES employee(Employee_ID)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS sales (
    Sale_ID INT AUTO_INCREMENT PRIMARY KEY,
    Bill_ID INT NOT NULL,
    Medicine_ID INT NOT NULL,
    Quantity_Sold INT NOT NULL,
    Sub_Total DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_sales_bill
        FOREIGN KEY (Bill_ID) REFERENCES bill(Bill_ID)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_sales_medicine
        FOREIGN KEY (Medicine_ID) REFERENCES medicine(Medicine_ID)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS users (
    User_ID INT AUTO_INCREMENT PRIMARY KEY,
    Full_Name VARCHAR(100) NOT NULL,
    Email VARCHAR(150) NOT NULL,
    Phone VARCHAR(20) NOT NULL,
    Password VARCHAR(255) NOT NULL,
    Address TEXT DEFAULT NULL,
    Is_Active TINYINT(1) NOT NULL DEFAULT 1,
    Last_Login_At DATETIME DEFAULT NULL,
    Created_At TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    Updated_At TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_users_email (Email),
    UNIQUE KEY uq_users_phone (Phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employee_login (
    Employee_Login_ID INT AUTO_INCREMENT PRIMARY KEY,
    Employee_ID INT NOT NULL,
    Username VARCHAR(80) NOT NULL,
    Password VARCHAR(255) NOT NULL,
    Status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
    Is_Active TINYINT(1) NOT NULL DEFAULT 1,
    Last_Login_At DATETIME DEFAULT NULL,
    Created_At TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    Updated_At TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_employee_login_employee (Employee_ID),
    UNIQUE KEY uq_employee_login_username (Username),
    CONSTRAINT fk_employee_login_employee
        FOREIGN KEY (Employee_ID) REFERENCES employee(Employee_ID)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS carts (
    Cart_ID INT AUTO_INCREMENT PRIMARY KEY,
    User_ID INT NOT NULL,
    Cart_Status ENUM('active', 'converted', 'abandoned') NOT NULL DEFAULT 'active',
    Created_At TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    Updated_At TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_carts_user
        FOREIGN KEY (User_ID) REFERENCES users(User_ID)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cart_items (
    Cart_Item_ID INT AUTO_INCREMENT PRIMARY KEY,
    Cart_ID INT NOT NULL,
    Medicine_ID INT NOT NULL,
    Quantity INT NOT NULL DEFAULT 1,
    Unit_Price DECIMAL(10,2) NOT NULL,
    Created_At TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    Updated_At TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cart_medicine (Cart_ID, Medicine_ID),
    CONSTRAINT fk_cart_items_cart
        FOREIGN KEY (Cart_ID) REFERENCES carts(Cart_ID)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_cart_items_medicine
        FOREIGN KEY (Medicine_ID) REFERENCES medicine(Medicine_ID)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_orders (
    Order_ID INT AUTO_INCREMENT PRIMARY KEY,
    User_ID INT DEFAULT NULL,
    Cart_ID INT DEFAULT NULL,
    Order_Number VARCHAR(40) NOT NULL,
    Order_Status ENUM('Pending', 'Confirmed', 'Processing', 'Delivered', 'Cancelled') NOT NULL DEFAULT 'Pending',
    Payment_Status ENUM('Unpaid', 'Paid', 'Refunded') NOT NULL DEFAULT 'Unpaid',
    Total_Amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    Shipping_Name VARCHAR(100) DEFAULT NULL,
    Shipping_Phone VARCHAR(20) DEFAULT NULL,
    Shipping_Address TEXT DEFAULT NULL,
    Placed_At TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    Updated_At TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_orders_number (Order_Number),
    KEY idx_user_orders_user (User_ID),
    KEY idx_user_orders_cart (Cart_ID),
    CONSTRAINT fk_user_orders_user
        FOREIGN KEY (User_ID) REFERENCES users(User_ID)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT fk_user_orders_cart
        FOREIGN KEY (Cart_ID) REFERENCES carts(Cart_ID)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS delivery_orders (
    delivery_id INT AUTO_INCREMENT PRIMARY KEY,
    bill_id INT DEFAULT NULL,
    order_id INT DEFAULT NULL,
    user_id INT DEFAULT NULL,
    reference_type VARCHAR(20) NOT NULL DEFAULT 'bill',
    reference_number VARCHAR(40) DEFAULT NULL,
    customer_name VARCHAR(100) NOT NULL,
    customer_phone VARCHAR(20) NOT NULL,
    customer_email VARCHAR(120) DEFAULT NULL,
    delivery_address TEXT NOT NULL,
    estimated_delivery_date DATE NOT NULL,
    delivery_status ENUM('Order Placed', 'Processing', 'Packed', 'Out for Delivery', 'Delivered') NOT NULL DEFAULT 'Order Placed',
    agent_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_delivery_bill FOREIGN KEY (bill_id) REFERENCES bill(Bill_ID) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_delivery_order FOREIGN KEY (order_id) REFERENCES user_orders(Order_ID) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_delivery_user FOREIGN KEY (user_id) REFERENCES users(User_ID) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_delivery_agent FOREIGN KEY (agent_id) REFERENCES delivery_agents(delivery_agent_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_order_items (
    Order_Item_ID INT AUTO_INCREMENT PRIMARY KEY,
    Order_ID INT NOT NULL,
    Medicine_ID INT NOT NULL,
    Quantity INT NOT NULL DEFAULT 1,
    Unit_Price DECIMAL(10,2) NOT NULL,
    Line_Total DECIMAL(10,2) NOT NULL,
    Created_At TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_order_items_order
        FOREIGN KEY (Order_ID) REFERENCES user_orders(Order_ID)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_user_order_items_medicine
        FOREIGN KEY (Medicine_ID) REFERENCES medicine(Medicine_ID)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO admin_users (full_name, username, password) VALUES
('System Admin', 'admin', 'admin123')
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name);

INSERT INTO supplier (Supplier_Name, Phone, Address) VALUES
('MediCore Distributors', '9876543210', '12 City Center, Ahmedabad'),
('LifeLine Pharma', '9988776655', '45 Market Road, Surat'),
('HealthPlus Supplies', '9123456780', '18 Ring Road, Rajkot');

INSERT INTO customer (Customer_Name, Phone, Gender, Age) VALUES
('Aarav Patel', '9000011111', 'Male', 24),
('Siya Shah', '9000022222', 'Female', 21),
('Dev Mehta', '9000033333', 'Male', 28);

INSERT INTO employee (Employee_Name, Role, Phone, Salary) VALUES
('Nisha Parmar', 'Cashier', '9555011111', 18000.00),
('Rohan Joshi', 'Store Manager', '9555022222', 25000.00);

INSERT INTO medicine (Medicine_Name, Category, Price, Quantity, Expiry_Date, Supplier_ID) VALUES
('Paracetamol 500mg', 'Tablet', 25.00, 120, '2027-12-31', 1),
('Amoxicillin 250mg', 'Capsule', 80.00, 40, '2026-08-15', 2),
('Vitamin C Syrup', 'Syrup', 95.00, 8, '2026-03-01', 3),
('Cough Relief Drops', 'Drops', 60.00, 15, '2026-01-15', 1);
