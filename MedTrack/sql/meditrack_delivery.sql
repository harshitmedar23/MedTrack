USE meditrack_db;

CREATE TABLE IF NOT EXISTS delivery_agents (
    delivery_agent_id INT AUTO_INCREMENT PRIMARY KEY,
    agent_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    vehicle_type VARCHAR(50) NOT NULL DEFAULT 'Bike',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

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

CREATE TABLE IF NOT EXISTS delivery_tracking (
    tracking_id INT AUTO_INCREMENT PRIMARY KEY,
    delivery_id INT NOT NULL,
    old_status VARCHAR(50) NOT NULL DEFAULT '',
    new_status VARCHAR(50) NOT NULL,
    remarks VARCHAR(255) DEFAULT NULL,
    changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tracking_delivery FOREIGN KEY (delivery_id) REFERENCES delivery_orders(delivery_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE OR REPLACE VIEW vw_delivery_summary AS
SELECT
    d.delivery_id,
    d.bill_id,
    d.order_id,
    d.customer_name,
    d.customer_phone,
    d.delivery_status,
    d.estimated_delivery_date,
    d.created_at,
    a.agent_name,
    a.phone AS agent_phone
FROM delivery_orders d
LEFT JOIN delivery_agents a ON a.delivery_agent_id = d.agent_id;

INSERT INTO delivery_agents (agent_name, phone, vehicle_type) VALUES
('Amit Delivery', '9000100001', 'Bike'),
('Priya Courier', '9000100002', 'Scooter'),
('Ravi Rider', '9000100003', 'Bike')
ON DUPLICATE KEY UPDATE agent_name = VALUES(agent_name);