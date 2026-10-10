<?php
require_once 'includes/functions.php';

if (isAdminLoggedIn()) {
    header('Location: dashboard.php');
} elseif (isEmployeeLoggedIn()) {
    header('Location: employee_dashboard.php');
} elseif (isCustomerLoggedIn()) {
    header('Location: user_dashboard.php');
} else {
    header('Location: login.php');
}
exit;
?>