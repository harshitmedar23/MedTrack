# Advanced DBMS Features

## Soft Delete

- Medicines are deactivated using `Is_Active = 0` instead of permanent deletion.
- This preserves sales history and improves database safety.

## Expiry Alerts

- Dashboard and inventory pages show expired medicines.
- Medicines expiring within 30 days are highlighted in the dashboard.

## Low Stock Alerts

- Medicines with quantity less than or equal to 10 are highlighted.
- Dashboard and inventory pages show low stock warning cards.

## Sales Analytics

- Total revenue and total orders are shown on the dashboard.
- Top selling medicines and best customers are calculated using `GROUP BY` and aggregate queries.

## Reports

- Daily reports.
- Monthly reports.
- Printable reports.
- CSV export for sales and delivery reports.

## SQL Features Used

- `JOIN` queries for bills, sales, customers, medicines, and delivery data.
- `GROUP BY` queries for analytics.
- Aggregate functions like `SUM`, `COUNT`, and `COALESCE`.
- View: `vw_delivery_summary`.
- Additional manual-import examples are in [sql/medtrack_advanced_dbms.sql](../sql/medtrack_advanced_dbms.sql).

## Security Improvements

- Prepared statements are used throughout the PHP code.
- Session-based login protects admin pages.
- File uploads are restricted to image MIME types.
- SQL injection risk is reduced by parameterized queries.

## Why This Helps in DBMS Evaluation

- Shows practical use of relationships and transactions.
- Demonstrates real database reporting and lifecycle management.
- Keeps the project realistic, understandable, and easy to present.