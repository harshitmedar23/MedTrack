<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$role = currentRole();
?>
<aside class="sidebar" id="sidebar">
    <div class="brand-box">
        <div class="brand-icon">P</div>
        <div>
            <h2>Smart Pharmacy</h2>
            <p>Inventory Dashboard</p>
        </div>
    </div>

    <nav class="sidebar-nav">
        <?php if ($role === 'employee'): ?>
            <a class="nav-link <?php echo isActivePage('employee_dashboard.php'); ?>" href="employee_dashboard.php">Dashboard</a>
            <a class="nav-link <?php echo isActivePage('billing.php'); ?>" href="billing.php">Billing</a>
            <a class="nav-link <?php echo isActivePage('inventory.php'); ?>" href="inventory.php">Medicines</a>
            <?php $employeeDeliveryOpen = in_array($currentPage, ['deliveries.php', 'delivery_reports.php'], true); ?>
            <details class="sidebar-group" <?php echo $employeeDeliveryOpen ? 'open' : ''; ?>>
                <summary class="nav-link sidebar-group-toggle <?php echo $employeeDeliveryOpen ? 'active' : ''; ?>">Deliveries</summary>
                <div class="sidebar-subnav">
                    <a class="nav-link sub-link <?php echo isActivePage('deliveries.php'); ?>" href="deliveries.php">Delivery List</a>
                    <a class="nav-link sub-link <?php echo isActivePage('delivery_reports.php'); ?>" href="delivery_reports.php">Reports</a>
                </div>
            </details>
            <a class="nav-link logout-link" href="logout.php">Logout</a>
        <?php elseif ($role === 'customer'): ?>
            <a class="nav-link <?php echo isActivePage('user_dashboard.php'); ?>" href="user_dashboard.php">Dashboard</a>
            <a class="nav-link <?php echo isActivePage('shop.php'); ?>" href="shop.php">Shop</a>
            <a class="nav-link <?php echo isActivePage('cart.php'); ?>" href="cart.php">Cart</a>
            <a class="nav-link <?php echo isActivePage('my_orders.php'); ?>" href="my_orders.php">My Orders</a>
            <a class="nav-link <?php echo isActivePage('track_order.php'); ?>" href="track_order.php">Track Order</a>
            <a class="nav-link logout-link" href="logout.php">Logout</a>
        <?php else: ?>
            <a class="nav-link <?php echo isActivePage('dashboard.php'); ?>" href="dashboard.php">Dashboard</a>
            <a class="nav-link <?php echo isActivePage('medicines.php'); ?>" href="medicines.php">Medicines</a>
            <a class="nav-link <?php echo isActivePage('suppliers.php'); ?>" href="suppliers.php">Suppliers</a>
            <a class="nav-link <?php echo isActivePage('customers.php'); ?>" href="customers.php">Customers</a>
            <a class="nav-link <?php echo isActivePage('employees.php'); ?>" href="employees.php">Employees</a>
            <a class="nav-link <?php echo isActivePage('billing.php'); ?>" href="billing.php">Billing</a>
            <?php $adminDeliveryOpen = in_array($currentPage, ['delivery_dashboard.php', 'deliveries.php', 'delivery_agents.php', 'delivery_reports.php'], true); ?>
            <details class="sidebar-group" <?php echo $adminDeliveryOpen ? 'open' : ''; ?>>
                <summary class="nav-link sidebar-group-toggle <?php echo $adminDeliveryOpen ? 'active' : ''; ?>">Deliveries</summary>
                <div class="sidebar-subnav">
                    <a class="nav-link sub-link <?php echo isActivePage('delivery_dashboard.php'); ?>" href="delivery_dashboard.php">Dashboard</a>
                    <a class="nav-link sub-link <?php echo isActivePage('deliveries.php'); ?>" href="deliveries.php">Deliveries</a>
                    <a class="nav-link sub-link <?php echo isActivePage('delivery_agents.php'); ?>" href="delivery_agents.php">Agents</a>
                    <a class="nav-link sub-link <?php echo isActivePage('delivery_reports.php'); ?>" href="delivery_reports.php">Reports</a>
                </div>
            </details>
            <a class="nav-link <?php echo isActivePage('inventory.php'); ?>" href="inventory.php">Inventory</a>
            <a class="nav-link <?php echo isActivePage('sales.php'); ?>" href="sales.php">Sales</a>
            <a class="nav-link <?php echo isActivePage('reports.php'); ?>" href="reports.php">Reports</a>
            <a class="nav-link logout-link" href="logout.php">Logout</a>
        <?php endif; ?>
    </nav>
</aside>