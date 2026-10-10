# ER Diagram Explanation

The Smart Pharmacy Inventory Management System is built around the core billing and delivery entities below:

- **Supplier** stores supplier details such as name, phone, and address.
- **Medicine** stores medicine data and links each medicine to one supplier.
- **Customer** stores customer information used during billing.
- **Employee** stores the cashier or staff who generates the bill.
- **Bill** stores the bill header with customer, employee, total amount, and date.
- **Sales** stores bill-wise line items for each medicine sold.
- **Delivery Agent** stores delivery staff details.
- **Delivery Order** stores delivery status, ETA, address, and bill linkage.
- **Delivery Tracking** stores status history for each delivery.

## Relationships

- One **Supplier** can supply many **Medicines**.
- One **Customer** can have many **Bills**.
- One **Employee** can generate many **Bills**.
- One **Bill** can contain many **Sales** records.
- One **Medicine** can appear in many **Sales** records.
- One **Bill** can have one **Delivery Order**.
- One **Delivery Agent** can manage many **Delivery Orders**.
- One **Delivery Order** can have many **Delivery Tracking** entries.

## Foreign Key Flow

- `Medicine.Supplier_ID` references `Supplier.Supplier_ID`
- `Bill.Customer_ID` references `Customer.Customer_ID`
- `Bill.Employee_ID` references `Employee.Employee_ID`
- `Sales.Bill_ID` references `Bill.Bill_ID`
- `Sales.Medicine_ID` references `Medicine.Medicine_ID`
- `DeliveryOrders.bill_id` references `Bill.Bill_ID`
- `DeliveryOrders.agent_id` references `DeliveryAgents.delivery_agent_id`
- `DeliveryTracking.delivery_id` references `DeliveryOrders.delivery_id`

This structure keeps the project normalized and easy to explain during viva.

## Email Invoice Flow

- When a bill is generated, the system builds an invoice payload from the bill, customer, employee, and sales rows.
- PHPMailer sends the invoice to the customer email using Gmail SMTP.
- An optional HTML attachment can be included for demonstration.
- The bill is still saved even if email sending fails, which keeps the billing module stable.
