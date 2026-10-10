<?php
require_once __DIR__ . '/functions.php';
$mailConfig = require __DIR__ . '/email_config.php';

require_once __DIR__ . '/../lib/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../lib/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../lib/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

function buildInvoiceEmailHtml(array $invoice): string
{
    $rowsHtml = '';
    foreach (($invoice['items'] ?? []) as $item) {
        $rowsHtml .= '<tr>'
            . '<td style="padding:10px;border:1px solid #dbe5f2;">' . h($item['Medicine_Name']) . '</td>'
            . '<td style="padding:10px;border:1px solid #dbe5f2;text-align:center;">' . (int) $item['Quantity_Sold'] . '</td>'
            . '<td style="padding:10px;border:1px solid #dbe5f2;text-align:right;">' . money($item['Price']) . '</td>'
            . '<td style="padding:10px;border:1px solid #dbe5f2;text-align:right;">' . money($item['Sub_Total']) . '</td>'
            . '</tr>';
    }

    $deliveryHtml = '';
    if (!empty($invoice['delivery_details']) && is_array($invoice['delivery_details'])) {
        $delivery = $invoice['delivery_details'];
        $etaValue = $delivery['estimated_date'] ?? '-';
        if ($etaValue !== '-' && $etaValue !== '' && strtotime($etaValue) !== false) {
            $etaValue = date('d M Y', strtotime($etaValue));
        }
        $deliveryHtml = '<div style="margin:18px 0 0;padding:16px;border:1px solid #dbe5f2;border-radius:14px;background:#f8fbff;">'
            . '<div style="font-size:16px;font-weight:800;color:#10345f;margin-bottom:10px;">Delivery Details</div>'
            . '<table style="width:100%;border-collapse:collapse;">'
            . '<tr><td style="padding:5px 0;"><strong>Status:</strong></td><td style="padding:5px 0;">' . h($delivery['status'] ?? 'Order Placed') . '</td></tr>'
            . '<tr><td style="padding:5px 0;"><strong>Reference:</strong></td><td style="padding:5px 0;">' . h($delivery['reference_number'] ?? '') . '</td></tr>'
            . '<tr><td style="padding:5px 0;"><strong>Address:</strong></td><td style="padding:5px 0;">' . h($delivery['address'] ?? '') . '</td></tr>'
            . '<tr><td style="padding:5px 0;"><strong>ETA:</strong></td><td style="padding:5px 0;">' . h($etaValue) . '</td></tr>'
            . '<tr><td style="padding:5px 0;"><strong>Agent:</strong></td><td style="padding:5px 0;">' . h($delivery['agent_name'] ?? 'Unassigned') . '</td></tr>'
            . '</table>'
            . '</div>';
    }

    return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Pharmacy Invoice #' . h($invoice['bill_id']) . '</title>
    <style>
        body{margin:0;background:#f4f7fb;font-family:Segoe UI,Tahoma,Arial,sans-serif;color:#1f2a37}
        .container{max-width:680px;margin:0 auto;padding:16px}
        .card{background:#fff;border:1px solid #dbe5f2;border-radius:12px;overflow:hidden}
        .card-header{padding:14px;background:linear-gradient(135deg,#eff6ff,#ffffff);display:flex;gap:12px;align-items:center}
        .brand{width:48px;height:48px;border-radius:12px;background:#1d6fe4;color:#fff;display:grid;place-items:center;font-weight:800}
        .card-body{padding:14px}
        table{width:100%;border-collapse:collapse;margin-bottom:12px}
        th,td{padding:8px;border:1px solid #e6eef9;font-size:14px}
        th{background:#f1f7ff;text-align:left}
        .totals{display:flex;justify-content:space-between;font-weight:800;font-size:16px;padding-top:10px;border-top:1px solid #e6eef9}
        @media (max-width:520px){.card-body{padding:12px}th,td{display:block;width:100%}thead{display:none}tr{display:block;margin-bottom:8px}td{border:0;padding:6px 0} .totals{flex-direction:column;gap:6px;text-align:right}}
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <div class="brand">SP</div>
                <div>
                    <div style="font-size:18px;font-weight:800;color:#10345f">Smart Pharmacy</div>
                    <div style="color:#6b7280;font-size:13px">Invoice</div>
                </div>
            </div>
            <div class="card-body">
                <table>
                    <tr><td><strong>Bill ID:</strong> #' . h($invoice['bill_id']) . '</td><td><strong>Date:</strong> ' . h($invoice['date_time']) . '</td></tr>
                    <tr><td><strong>Customer:</strong> ' . h($invoice['customer_name']) . '</td><td><strong>Phone:</strong> ' . h($invoice['customer_phone']) . '</td></tr>
                    <tr><td><strong>Employee:</strong> ' . h($invoice['employee_name']) . '</td><td><strong>Total:</strong> ' . money($invoice['total_amount']) . '</td></tr>
                </table>
                <table>
                    <thead>
                        <tr>
                            <th>Medicine</th>
                            <th style="width:80px">Qty</th>
                            <th style="text-align:right;width:120px">Unit Price</th>
                            <th style="text-align:right;width:120px">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>' . $rowsHtml . '</tbody>
                </table>
                ' . $deliveryHtml . '
                <div class="totals">
                    <span>Grand Total</span>
                    <span>' . money($invoice['total_amount']) . '</span>
                </div>
                <p style="margin:14px 0 0;text-align:center;color:#10345f;font-weight:700">Thank you for choosing Smart Pharmacy</p>
            </div>
        </div>
    </div>
</body>
</html>';
}

function sendInvoiceEmail(array $invoice, string $recipientEmail, string $recipientName = '', ?string $attachmentPath = null): array
{
    global $mailConfig;

    if (empty($mailConfig['enabled'])) {
        return [false, 'Email sending is disabled in mail configuration.'];
    }

    if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
        return [false, 'Invalid recipient email address.'];
    }

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $mailConfig['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $mailConfig['username'];
        $mail->Password = $mailConfig['password'];
        $mail->Port = (int) $mailConfig['port'];
        $mail->CharSet = 'UTF-8';

        if (!empty($mailConfig['use_tls'])) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        $mail->setFrom($mailConfig['from_email'], $mailConfig['from_name']);
        $mail->addAddress($recipientEmail, $recipientName !== '' ? $recipientName : $invoice['customer_name']);

        $mail->isHTML(true);
        $mail->Subject = 'Smart Pharmacy Invoice #' . $invoice['bill_id'];
        $mail->Body = buildInvoiceEmailHtml($invoice);
        $mail->AltBody = 'Invoice #' . $invoice['bill_id'] . ' from Smart Pharmacy. Total: ' . money($invoice['total_amount']);

        if ($attachmentPath !== null && is_file($attachmentPath) && !empty($mailConfig['attach_html_invoice'])) {
            $mail->addAttachment($attachmentPath, 'Invoice-' . $invoice['bill_id'] . '.html');
        }

        $mail->send();
        return [true, 'Invoice email sent successfully.'];
    } catch (Exception $exception) {
        return [false, 'Mailer error: ' . $exception->getMessage()];
    } catch (Throwable $exception) {
        return [false, 'Unexpected mail error: ' . $exception->getMessage()];
    }
}