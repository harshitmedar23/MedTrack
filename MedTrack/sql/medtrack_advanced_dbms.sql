USE meditrack_db;

CREATE OR REPLACE VIEW vw_sales_summary AS
SELECT
    b.Bill_ID,
    c.Customer_Name,
    b.Bill_Date,
    SUM(s.Quantity_Sold) AS total_items,
    SUM(s.Sub_Total) AS total_revenue
FROM bill b
INNER JOIN customer c ON c.Customer_ID = b.Customer_ID
INNER JOIN sales s ON s.Bill_ID = b.Bill_ID
GROUP BY b.Bill_ID, c.Customer_Name, b.Bill_Date;

CREATE OR REPLACE VIEW vw_medicine_stock_alerts AS
SELECT
    Medicine_ID,
    Medicine_Name,
    Quantity,
    Expiry_Date,
    Is_Active
FROM medicine
WHERE Is_Active = 1 AND (Quantity <= 10 OR Expiry_Date < CURDATE());

DROP TRIGGER IF EXISTS trg_sales_after_insert;
DELIMITER $$
CREATE TRIGGER trg_sales_after_insert
AFTER INSERT ON sales
FOR EACH ROW
BEGIN
    UPDATE medicine
    SET Quantity = Quantity - NEW.Quantity_Sold
    WHERE Medicine_ID = NEW.Medicine_ID;
END$$
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_update_delivery_status;
DELIMITER $$
CREATE PROCEDURE sp_update_delivery_status(
    IN p_delivery_id INT,
    IN p_status VARCHAR(50),
    IN p_agent_id INT,
    IN p_remarks VARCHAR(255)
)
BEGIN
    UPDATE delivery_orders
    SET delivery_status = p_status,
        agent_id = NULLIF(p_agent_id, 0)
    WHERE delivery_id = p_delivery_id;

    INSERT INTO delivery_tracking (delivery_id, old_status, new_status, remarks)
    SELECT p_delivery_id, delivery_status, p_status, p_remarks
    FROM delivery_orders
    WHERE delivery_id = p_delivery_id;
END$$
DELIMITER ;

-- Example query ideas for viva:
-- SELECT * FROM vw_sales_summary ORDER BY total_revenue DESC;
-- SELECT * FROM vw_medicine_stock_alerts ORDER BY Quantity ASC;