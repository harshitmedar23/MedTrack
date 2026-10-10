<?php
// Prefer environment variables for credentials; fall back to placeholders for beginner-friendly setup.
return [
	'enabled' => (getenv('SMTP_ENABLED') ?: '1') === '1',
	'host' => getenv('SMTP_HOST') ?: 'smtp.gmail.com',
	'port' => (int) (getenv('SMTP_PORT') ?: 587),
	'username' => getenv('SMTP_USERNAME') ?: 'medicinemanagement70@gmail.com',
	'password' => getenv('SMTP_PASSWORD') ?: 'ctrwrecjhbsrflub',
	'from_email' => getenv('SMTP_FROM_EMAIL') ?: (getenv('SMTP_USERNAME') ?: 'medicinemanagement70@gmail.com'),
	'from_name' => getenv('SMTP_FROM_NAME') ?: 'Smart Pharmacy',
	'use_tls' => (getenv('SMTP_TLS') ?: '1') === '1',
	'attach_html_invoice' => (getenv('SMTP_ATTACH_INVOICE') ?: '1') === '1',
];