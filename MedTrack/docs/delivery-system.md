# Online Medicine Delivery System

This module adds delivery management to the Smart Pharmacy system.

## Entities

- `delivery_agents` stores delivery staff information.
- `delivery_orders` stores bill-linked delivery records.
- `delivery_tracking` stores status history changes.

## Delivery Statuses

- Order Placed
- Processing
- Packed
- Out for Delivery
- Delivered

## Relationships

- One bill can create one delivery order.
- One delivery agent can handle many delivery orders.
- One delivery order can produce many tracking history rows.

## CRUD Pages

- `delivery_dashboard.php` shows delivery summary cards and recent orders.
- `deliveries.php` lists delivery orders and links to status updates.
- `delivery_agents.php` manages delivery staff.
- `delivery_status.php` updates status and writes tracking history.
- `delivery_reports.php` shows daily/monthly delivery reports and CSV export.

## Bill Integration

- The billing page can create a delivery order during bill generation.
- Customer name, phone, email, address, ETA, and optional agent are stored with the delivery order.
- Status starts as `Order Placed`.

## DBMS Value

- Demonstrates foreign keys, tracking history, and normalized reporting.
- Easy to explain in viva because every order is tied to a bill.