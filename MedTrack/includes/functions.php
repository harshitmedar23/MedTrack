<?php
require_once __DIR__ . '/../db_connect.php';

// Escape output to keep the pages safe and beginner-friendly.
function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function clearAuthSession()
{
    unset(
        $_SESSION['admin_logged_in'],
        $_SESSION['admin_id'],
        $_SESSION['admin_name'],
        $_SESSION['admin_username'],
        $_SESSION['employee_logged_in'],
        $_SESSION['employee_login_id'],
        $_SESSION['employee_id'],
        $_SESSION['employee_name'],
        $_SESSION['employee_username'],
        $_SESSION['customer_logged_in'],
        $_SESSION['customer_id'],
        $_SESSION['customer_name'],
        $_SESSION['customer_email'],
        $_SESSION['auth_role'],
        $_SESSION['auth_name']
    );
}

function currentRole()
{
    if (!empty($_SESSION['admin_logged_in'])) {
        return 'admin';
    }

    if (!empty($_SESSION['employee_logged_in'])) {
        return 'employee';
    }

    if (!empty($_SESSION['customer_logged_in'])) {
        return 'customer';
    }

    return $_SESSION['auth_role'] ?? null;
}

function isAdminLoggedIn()
{
    return currentRole() === 'admin';
}

function isEmployeeLoggedIn()
{
    return currentRole() === 'employee';
}

function isCustomerLoggedIn()
{
    return currentRole() === 'customer';
}

function isAnyLoggedIn()
{
    return currentRole() !== null;
}

function isLoggedIn()
{
    return isAdminLoggedIn();
}

function requireLogin()
{
    if (!isAdminLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function roleHomeRoute($role)
{
    switch ($role) {
        case 'admin':
            return 'dashboard.php';
        case 'employee':
            return 'employee_dashboard.php';
        case 'customer':
            return 'user_dashboard.php';
        default:
            return 'login.php';
    }
}

function roleLoginRoute($role)
{
    return 'login.php';
}

function redirectAuthenticatedUser()
{
    $role = currentRole();
    if ($role !== null) {
        header('Location: ' . roleHomeRoute($role));
        exit;
    }
}

function verifyStoredPassword($plainPassword, $storedPassword)
{
    $plainPassword = (string) $plainPassword;
    $storedPassword = (string) $storedPassword;

    if ($storedPassword === '') {
        return false;
    }

    return hash_equals($storedPassword, $plainPassword);
}

function startAuthSession($role, array $data = [])
{
    session_regenerate_id(true);
    clearAuthSession();

    $_SESSION['auth_role'] = $role;
    $_SESSION['auth_name'] = $data['name'] ?? '';

    if ($role === 'admin') {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = $data['id'] ?? null;
        $_SESSION['admin_name'] = $data['name'] ?? 'Admin';
        $_SESSION['admin_username'] = $data['username'] ?? '';
        return;
    }

    if ($role === 'employee') {
        $_SESSION['employee_logged_in'] = true;
        $_SESSION['employee_login_id'] = $data['login_id'] ?? null;
        $_SESSION['employee_id'] = $data['employee_id'] ?? null;
        $_SESSION['employee_name'] = $data['name'] ?? 'Employee';
        $_SESSION['employee_username'] = $data['username'] ?? '';
        return;
    }

    if ($role === 'customer') {
        $_SESSION['customer_logged_in'] = true;
        $_SESSION['customer_id'] = $data['id'] ?? null;
        $_SESSION['customer_name'] = $data['name'] ?? 'Customer';
        $_SESSION['customer_email'] = $data['email'] ?? '';
    }
}

function requireRole($allowedRoles)
{
    $allowedRoles = array_map('strval', (array) $allowedRoles);
    $role = currentRole();

    if ($role === 'admin' || in_array('admin', $allowedRoles, true) && $role === 'admin') {
        return;
    }

    if ($role !== null && in_array($role, $allowedRoles, true)) {
        return;
    }

    header('Location: login.php');
    exit;
}

function requireAdmin()
{
    $role = currentRole();
    if ($role === 'admin') {
        return;
    }

    if ($role === 'employee') {
        header('Location: employee_dashboard.php');
        exit;
    }

    if ($role === 'customer') {
        header('Location: user_dashboard.php');
        exit;
    }

    header('Location: login.php');
    exit;
}

function requireEmployee()
{
    $role = currentRole();
    if ($role === 'admin' || $role === 'employee') {
        return;
    }

    if ($role === 'customer') {
        header('Location: user_dashboard.php');
        exit;
    }

    header('Location: login.php');
    exit;
}

function requireCustomer()
{
    $role = currentRole();
    if ($role === 'customer') {
        return;
    }

    if ($role === 'employee') {
        header('Location: employee_dashboard.php');
        exit;
    }

    if ($role === 'admin') {
        header('Location: dashboard.php');
        exit;
    }

    header('Location: login.php');
    exit;
}

function logoutCurrentUser($redirectTo = null)
{
    $role = currentRole();
    $redirectTo = $redirectTo ?: roleLoginRoute($role);

    clearAuthSession();
    setFlash('success', 'You have been logged out successfully.');
    session_regenerate_id(true);

    header('Location: ' . $redirectTo);
    exit;
}

function requirePost()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: dashboard.php');
        exit;
    }
}

function setFlash($type, $message)
{
    $_SESSION['flash_message'] = ['type' => $type, 'message' => $message];
}

function renderFlash()
{
    if (empty($_SESSION['flash_message'])) {
        return '';
    }

    $flash = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);

    return '<div class="alert alert-' . h($flash['type']) . '">' . h($flash['message']) . '</div>';
}

function getCount(mysqli $conn, $table, $where = '')
{
    $sql = "SELECT COUNT(*) AS total FROM {$table}";
    if ($where !== '') {
        $sql .= ' ' . $where;
    }
    $result = $conn->query($sql);
    $row = $result ? $result->fetch_assoc() : ['total' => 0];
    return (int) ($row['total'] ?? 0);
}

function fetchRow(mysqli $conn, $sql)
{
    $result = $conn->query($sql);
    return $result ? $result->fetch_assoc() : null;
}

function money($value)
{
    return 'Rs. ' . number_format((float) $value, 2);
}

function medicineImageUrl($path)
{
    $path = trim((string) $path);
    if ($path === '') {
        return '';
    }

    $cleanPath = str_replace('\\', '/', ltrim($path, '/\\'));
    if (strpos($cleanPath, '..') !== false) {
        return '';
    }

    $absolutePath = __DIR__ . '/../' . $cleanPath;
    if (!is_file($absolutePath)) {
        return '';
    }

    return $cleanPath;
}

function medicinePlaceholderImageUrl()
{
    return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 450" role="img" aria-label="Medicine placeholder"><defs><linearGradient id="g" x1="0" x2="1" y1="0" y2="1"><stop stop-color="#eaf2ff"/><stop offset="1" stop-color="#ffffff"/></linearGradient></defs><rect width="800" height="450" rx="28" fill="url(#g)"/><rect x="255" y="110" width="290" height="230" rx="42" fill="#1d6fe4" opacity="0.12"/><rect x="300" y="145" width="200" height="160" rx="28" fill="#1d6fe4" opacity="0.18"/><path d="M400 160a56 56 0 1 0 0 112 56 56 0 0 0 0-112Zm0 22a34 34 0 1 1 0 68 34 34 0 0 1 0-68Z" fill="#1559b7" opacity="0.8"/><path d="M365 220h70v16h-70zM392 193h16v70h-16z" fill="#ffffff"/><text x="50%" y="372" text-anchor="middle" font-family="Segoe UI, Arial, sans-serif" font-size="28" fill="#1559b7" font-weight="700">Smart Pharmacy</text></svg>');
}

function currentAdminName()
{
    return $_SESSION['admin_name'] ?? 'Admin';
}

function currentUserName()
{
    return $_SESSION['auth_name'] ?? $_SESSION['admin_name'] ?? $_SESSION['employee_name'] ?? $_SESSION['customer_name'] ?? 'User';
}

function currentUserLabel()
{
    $role = currentRole();
    if ($role === null) {
        return 'Guest';
    }

    return ucfirst($role);
}

function getCustomerIdFromSession()
{
    return (int) ($_SESSION['customer_id'] ?? 0);
}

function getCustomerCartItemCount(mysqli $conn, int $customerId)
{
    if ($customerId <= 0) {
        return 0;
    }

    $stmt = $conn->prepare(
        'SELECT COALESCE(SUM(ci.Quantity), 0) AS total_items
         FROM carts c
         INNER JOIN cart_items ci ON ci.Cart_ID = c.Cart_ID
         WHERE c.User_ID = ? AND c.Cart_Status = "active"'
    );

    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param('i', $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return (int) ($row['total_items'] ?? 0);
}

function customerOrderProgressSteps()
{
    return [
        'Pending',
        'Confirmed',
        'Processing',
        'Packed',
        'Out for Delivery',
        'Delivered',
    ];
}

function customerProgressIndex($status)
{
    $steps = customerOrderProgressSteps();
    $status = normalizeOrderStatus($status);
    $index = array_search($status, $steps, true);

    return $index === false ? 0 : (int) $index;
}

function renderCustomerNavbar(string $activePage = '', int $cartCount = 0, string $searchMarkup = '')
{
    $cartBadge = $cartCount > 0 ? '<span class="nav-badge">' . (int) $cartCount . '</span>' : '';
    if ($searchMarkup !== '') {
        $searchSection = '<div class="customer-navbar-search">' . $searchMarkup . '</div>';
    } else {
        $searchSection = '<div class="customer-navbar-search">'
            . '<form method="get" class="customer-search-form navbar-search" id="shopSearchForm" data-suggestions-url="medicine_suggestions.php">'
            . '<div class="search-field">'
            . '<input type="search" id="medicineSearch" name="search" placeholder="Search medicines, e.g. paracetmol" autocomplete="off">'
            . '<div class="search-suggestions" id="searchSuggestions" hidden></div>'
            . '</div>'
            . '<button class="btn btn-primary" type="submit">Search</button>'
            . '</form></div>';
    }

    return '<nav class="customer-navbar">
        <div class="customer-brand">
            <div class="customer-brand-mark">SP</div>
            <div>
                <strong>Smart Pharmacy</strong>
                <span>Medicine Delivery Portal</span>
            </div>
        </div>
        ' . $searchSection . '
        <div class="customer-nav-links">
            <a class="customer-nav-link ' . ($activePage === 'shop' ? 'active' : '') . '" href="shop.php">Shop</a>
            <a class="customer-nav-link ' . ($activePage === 'cart' ? 'active' : '') . '" href="cart.php">Cart ' . $cartBadge . '</a>
            <a class="customer-nav-link ' . ($activePage === 'orders' ? 'active' : '') . '" href="my_orders.php">My Orders</a>
            <a class="customer-nav-link ' . ($activePage === 'track' ? 'active' : '') . '" href="track_order.php">Track Order</a>
            <a class="customer-nav-link" href="user_dashboard.php">Profile</a>
            <a class="customer-nav-link logout" href="logout.php">Logout</a>
        </div>
    </nav>';
}

function renderCategoryChip($label, $value, $activeCategory, $baseUrl = 'shop.php')
{
    $active = ((string) $activeCategory === (string) $value) ? 'active' : '';
    return '<a class="category-chip ' . $active . '" href="' . h($baseUrl) . '?category=' . rawurlencode($value) . '">' . h($label) . '</a>';
}

function medicineStockBadgeClass($quantity)
{
    $quantity = (int) $quantity;
    if ($quantity <= 0) {
        return 'badge-danger';
    }

    if ($quantity <= 5) {
        return 'badge-warning';
    }

    return 'badge-success';
}

function medicineSearchSimilarityScore($needle, array $medicine)
{
    $needle = trim(strtolower(preg_replace('/[^a-z0-9]+/i', '', (string) $needle)));
    $haystack = trim(strtolower(preg_replace('/[^a-z0-9]+/i', '', (string) ($medicine['Medicine_Name'] ?? ''))));

    if ($needle === '' || $haystack === '') {
        return 0;
    }

    if ($needle === $haystack) {
        return 100;
    }

    $score = 0;
    if (strpos($haystack, $needle) !== false) {
        $score += 60;
    }

    similar_text($needle, $haystack, $percent);
    $score += (int) round($percent);

    $distance = levenshtein($needle, $haystack);
    if ($distance <= 2) {
        $score += 45;
    } elseif ($distance <= 4) {
        $score += 25;
    }

    if (!empty($medicine['Category'])) {
        $category = strtolower((string) $medicine['Category']);
        if (strpos($category, $needle) !== false) {
            $score += 15;
        }
    }

    return $score;
}

function searchMedicineSuggestions(mysqli $conn, string $query, string $category = '', int $limit = 8)
{
    $query = trim($query);
    $category = trim($category);
    $limit = max(1, min(12, $limit));

    $sql = 'SELECT Medicine_ID, Medicine_Name, Category, Price, Quantity, Medicine_Image FROM medicine WHERE Is_Active = 1';
    $params = [];
    $types = '';

    if ($category !== '') {
        $sql .= ' AND Category = ?';
        $params[] = $category;
        $types .= 's';
    }

    $prefilterSql = $sql;
    if ($query !== '') {
        $prefilterSql .= ' AND (Medicine_Name LIKE ? OR Category LIKE ?)';
        $likeQuery = '%' . $query . '%';
        $params[] = $likeQuery;
        $params[] = $likeQuery;
        $types .= 'ss';
    }

    $stmt = $conn->prepare($prefilterSql . ' ORDER BY Medicine_Name ASC');
    if (!$stmt) {
        return [];
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $prefiltered = [];
    while ($result && ($row = $result->fetch_assoc())) {
        $prefiltered[] = $row;
    }
    $stmt->close();

    if ($query === '') {
        return array_slice($prefiltered, 0, $limit);
    }

    $allSql = 'SELECT Medicine_ID, Medicine_Name, Category, Price, Quantity, Medicine_Image FROM medicine WHERE Is_Active = 1';
    $allParams = [];
    $allTypes = '';
    if ($category !== '') {
        $allSql .= ' AND Category = ?';
        $allParams[] = $category;
        $allTypes .= 's';
    }

    $allStmt = $conn->prepare($allSql . ' ORDER BY Medicine_Name ASC');
    if (!$allStmt) {
        return $prefiltered;
    }

    if (!empty($allParams)) {
        $allStmt->bind_param($allTypes, ...$allParams);
    }

    $allStmt->execute();
    $allResult = $allStmt->get_result();
    $medicines = [];
    while ($allResult && ($row = $allResult->fetch_assoc())) {
        $medicines[] = $row;
    }
    $allStmt->close();

    $rankSource = !empty($prefiltered) ? $prefiltered : $medicines;
    $ranked = [];
    foreach ($rankSource as $medicine) {
        $name = strtolower((string) $medicine['Medicine_Name']);
        $categoryText = strtolower((string) $medicine['Category']);
        $needle = strtolower($query);
        $score = 0;

        if (strpos($name, $needle) !== false) {
            $score += 100;
        }
        if (strpos($categoryText, $needle) !== false) {
            $score += 20;
        }

        $score += medicineSearchSimilarityScore($needle, $medicine);

        $ranked[] = [$score, $medicine];
    }

    usort($ranked, function ($left, $right) {
        if ($left[0] === $right[0]) {
            return strcmp($left[1]['Medicine_Name'], $right[1]['Medicine_Name']);
        }

        return $right[0] <=> $left[0];
    });

    $results = [];
    foreach ($ranked as $entry) {
        if ($entry[0] <= 0) {
            continue;
        }

        $results[] = $entry[1];
        if (count($results) >= $limit) {
            break;
        }
    }

    if (empty($results)) {
        return array_slice($medicines, 0, $limit);
    }

    return $results;
}

function getOrCreateActiveCart(mysqli $conn, int $userId)
{
    if ($userId <= 0) {
        return null;
    }

    $stmt = $conn->prepare("SELECT Cart_ID, Cart_Status FROM carts WHERE User_ID = ? AND Cart_Status = 'active' ORDER BY Cart_ID DESC LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $cart = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if ($cart) {
            return $cart;
        }
    }

    $insert = $conn->prepare("INSERT INTO carts (User_ID, Cart_Status) VALUES (?, 'active')");
    if (!$insert) {
        return null;
    }

    $insert->bind_param('i', $userId);
    if (!$insert->execute()) {
        $insert->close();
        return null;
    }

    $cartId = (int) $conn->insert_id;
    $insert->close();

    return [
        'Cart_ID' => $cartId,
        'Cart_Status' => 'active',
    ];
}

function generateOrderNumber()
{
    try {
        $suffix = random_int(1000, 9999);
    } catch (Throwable $exception) {
        $suffix = mt_rand(1000, 9999);
    }

    return 'SP-' . date('YmdHis') . '-' . $suffix;
}

function normalizeOrderStatus($status)
{
    $status = trim((string) $status);
    $map = [
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'processing' => 'Processing',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
    ];

    $lower = strtolower($status);
    return $map[$lower] ?? ($status !== '' ? ucfirst($lower) : 'Pending');
}

function customerOrderStatuses()
{
    return ['Pending', 'Confirmed', 'Processing', 'Delivered', 'Cancelled'];
}

function isActivePage($fileName)
{
    return basename($_SERVER['PHP_SELF']) === $fileName ? 'active' : '';
}
?>