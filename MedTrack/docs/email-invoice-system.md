# Email Invoice System

This project sends the bill as an email invoice after bill generation using PHPMailer and Gmail SMTP.

## Folder Structure

- `includes/email_config.php` stores SMTP settings.
- `includes/email.php` contains the mail helper and invoice template builder.
- `lib/PHPMailer/src/` contains the PHPMailer source files.
- `billing.php` triggers the email after the bill is saved.

## Gmail SMTP Setup

1. Enable 2-Step Verification in your Gmail account.
2. Create an App Password from Google Account security settings.
3. Set these values in environment variables or `includes/email_config.php`:
   - `SMTP_HOST=smtp.gmail.com`
   - `SMTP_PORT=587`
   - `SMTP_USERNAME=your-gmail@gmail.com`
   - `SMTP_PASSWORD=your-app-password`
   - `SMTP_FROM_EMAIL=your-gmail@gmail.com`
   - `SMTP_FROM_NAME=Smart Pharmacy`
4. Keep `SMTP_TLS=1` for secure sending.

## How It Works

- User enters customer name, phone, and email during billing.
- System creates the bill and sales rows.
- PHPMailer sends an HTML invoice email.
- Optional HTML invoice attachment is created in the `tmp/` folder and removed after sending.

## Error Handling

- If SMTP is not configured, the bill still succeeds and a warning is shown.
- Invalid email addresses stop the email step.
- Billing never rolls back only because email failed.

## Why It Is Suitable for DBMS Mini Project

- Shows real-world automation.
- Uses relational bill data for email generation.
- Demonstrates backend integration, validation, and transaction safety.