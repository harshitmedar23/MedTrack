<?php
require_once 'includes/functions.php';
// Allow suggestions to be fetched by AJAX from public pages (no redirect for guests).

header('Content-Type: application/json; charset=UTF-8');

$query = trim($_GET['q'] ?? '');
$category = trim($_GET['category'] ?? '');

$medicines = searchMedicineSuggestions($conn, $query, $category, 8);
$payload = [];

foreach ($medicines as $medicine) {
    $imageUrl = medicineImageUrl($medicine['Medicine_Image']);
    if ($imageUrl === '') {
        $imageUrl = medicinePlaceholderImageUrl();
    }

    $payload[] = [
        'id' => (int) $medicine['Medicine_ID'],
        'name' => $medicine['Medicine_Name'],
        'category' => $medicine['Category'],
        'price' => (float) $medicine['Price'],
        'stock' => (int) $medicine['Quantity'],
        'image' => $imageUrl,
    ];
}

echo json_encode([
    'query' => $query,
    'count' => count($payload),
    'items' => $payload,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);