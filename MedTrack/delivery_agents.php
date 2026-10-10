<?php
require_once 'includes/functions.php';
requireLogin();

$pageTitle = 'Delivery Agents';
$editId = (int) ($_GET['edit'] ?? 0);
$agent = [
    'agent_name' => '',
    'phone' => '',
    'vehicle_type' => 'Bike',
    'is_active' => 1,
];

if (isset($_GET['delete'])) {
    $deleteId = (int) $_GET['delete'];
    $stmt = $conn->prepare('UPDATE delivery_agents SET is_active = 0 WHERE delivery_agent_id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $deleteId);
        $stmt->execute();
        $stmt->close();
        setFlash('success', 'Delivery agent deactivated successfully.');
    }
    header('Location: delivery_agents.php');
    exit;
}

if ($editId > 0) {
    $stmt = $conn->prepare('SELECT * FROM delivery_agents WHERE delivery_agent_id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $editId);
        $stmt->execute();
        $result = $stmt->get_result();
        $agent = $result ? $result->fetch_assoc() ?: $agent : $agent;
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $agentId = (int) ($_POST['delivery_agent_id'] ?? 0);
    $agentName = trim($_POST['agent_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $vehicleType = trim($_POST['vehicle_type'] ?? 'Bike');

    if ($agentName === '' || $phone === '') {
        setFlash('error', 'Please fill in agent name and phone.');
    } else {
        if ($agentId > 0) {
            $stmt = $conn->prepare('UPDATE delivery_agents SET agent_name = ?, phone = ?, vehicle_type = ?, is_active = 1 WHERE delivery_agent_id = ?');
            $stmt->bind_param('sssi', $agentName, $phone, $vehicleType, $agentId);
            $message = 'Delivery agent updated successfully.';
        } else {
            $stmt = $conn->prepare('INSERT INTO delivery_agents (agent_name, phone, vehicle_type) VALUES (?, ?, ?)');
            $stmt->bind_param('sss', $agentName, $phone, $vehicleType);
            $message = 'Delivery agent added successfully.';
        }

        if ($stmt && $stmt->execute()) {
            $stmt->close();
            setFlash('success', $message);
            header('Location: delivery_agents.php');
            exit;
        }

        if ($stmt) {
            $stmt->close();
        }
        setFlash('error', 'Could not save delivery agent.');
    }
}

$agents = $conn->query('SELECT * FROM delivery_agents ORDER BY delivery_agent_id DESC');

include 'includes/header.php';
?>
<div class="panel-grid">
    <div class="form-card">
        <h3 class="section-title"><?php echo $editId > 0 ? 'Edit Delivery Agent' : 'Add Delivery Agent'; ?></h3>
        <form method="post" class="needs-validation">
            <input type="hidden" name="delivery_agent_id" value="<?php echo (int) $editId; ?>">
            <div class="form-grid">
                <div class="form-group full">
                    <label for="agent_name">Agent Name</label>
                    <input type="text" id="agent_name" name="agent_name" required value="<?php echo h($agent['agent_name']); ?>">
                </div>
                <div class="form-group">
                    <label for="phone">Phone</label>
                    <input type="text" id="phone" name="phone" required value="<?php echo h($agent['phone']); ?>">
                </div>
                <div class="form-group">
                    <label for="vehicle_type">Vehicle Type</label>
                    <input type="text" id="vehicle_type" name="vehicle_type" value="<?php echo h($agent['vehicle_type']); ?>">
                </div>
            </div>
            <div class="form-actions" style="margin-top: 16px;">
                <button type="submit" class="btn btn-primary"><?php echo $editId > 0 ? 'Update Agent' : 'Save Agent'; ?></button>
                <?php if ($editId > 0): ?>
                    <a class="btn btn-secondary" href="delivery_agents.php">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="table-card">
        <h3 class="section-title">Agent List</h3>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Vehicle</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($agents && $agents->num_rows > 0): ?>
                        <?php while ($row = $agents->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo (int) $row['delivery_agent_id']; ?></td>
                                <td><?php echo h($row['agent_name']); ?></td>
                                <td><?php echo h($row['phone']); ?></td>
                                <td><?php echo h($row['vehicle_type']); ?></td>
                                <td>
                                    <?php if ((int) $row['is_active'] === 1): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-light">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <a class="btn btn-secondary" href="delivery_agents.php?edit=<?php echo (int) $row['delivery_agent_id']; ?>">Edit</a>
                                        <a class="btn btn-danger" href="delivery_agents.php?delete=<?php echo (int) $row['delivery_agent_id']; ?>" onclick="return confirm('Deactivate this agent?');">Deactivate</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6">No delivery agents found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>