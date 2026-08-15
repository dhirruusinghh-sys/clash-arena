<?php
// superadmin/users.php
// Premium Super Admin User Management (CRUD) - Clash Arena

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';

// Verify role access
checkRole(['superadmin']);

require_once __DIR__ . '/../config/db.php';

$error_msg = '';
$success_msg = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    // Add User
    if ($action === 'create') {
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';
        $role = isset($_POST['role']) ? $_POST['role'] : 'customer';
        $status = isset($_POST['status']) ? $_POST['status'] : 'active';
        
        if (empty($name) || empty($email) || empty($password)) {
            $error_msg = 'Please fill in all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_msg = 'Invalid email address format.';
        } else {
            try {
                // Check if email already exists
                $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM `users` WHERE `email` = :email");
                $stmtCheck->execute(['email' => $email]);
                if ($stmtCheck->fetchColumn() > 0) {
                    $error_msg = 'Email address already registered.';
                } else {
                    // Insert new user
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmtInsert = $pdo->prepare("INSERT INTO `users` (`name`, `email`, `password`, `role`, `status`) VALUES (:name, :email, :password, :role, :status)");
                    $stmtInsert->execute([
                        'name' => $name,
                        'email' => $email,
                        'password' => $hashedPassword,
                        'role' => $role,
                        'status' => $status
                    ]);
                    
                    $new_uid = $pdo->lastInsertId();
                    // Create wallet for player
                    if ($role === 'customer') {
                        $stmtWallet = $pdo->prepare("INSERT INTO `wallets` (`user_id`, `balance`, `coins`) VALUES (:uid, 100.00, 200)");
                        $stmtWallet->execute(['uid' => $new_uid]);
                    }

                    $success_msg = 'Player account created successfully.';
                }
            } catch (PDOException $e) {
                $error_msg = 'Database insertion failure: ' . $e->getMessage();
            }
        }
    }
    
    // Update User
    elseif ($action === 'update') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $role = isset($_POST['role']) ? $_POST['role'] : 'customer';
        $status = isset($_POST['status']) ? $_POST['status'] : 'active';
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';
        
        if ($id <= 0 || empty($name) || empty($email)) {
            $error_msg = 'Please fill in all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_msg = 'Invalid email address format.';
        } else {
            try {
                // Check if email already exists for another user
                $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM `users` WHERE `email` = :email AND `id` != :id");
                $stmtCheck->execute(['email' => $email, 'id' => $id]);
                if ($stmtCheck->fetchColumn() > 0) {
                    $error_msg = 'Email address is already in use by another account.';
                } else {
                    // Update main details
                    if (!empty($password)) {
                        // Include password update
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $stmtUpdate = $pdo->prepare("UPDATE `users` SET `name` = :name, `email` = :email, `password` = :password, `role` = :role, `status` = :status WHERE `id` = :id");
                        $stmtUpdate->execute([
                            'name' => $name,
                            'email' => $email,
                            'password' => $hashedPassword,
                            'role' => $role,
                            'status' => $status,
                            'id' => $id
                        ]);
                    } else {
                        // Standard update
                        $stmtUpdate = $pdo->prepare("UPDATE `users` SET `name` = :name, `email` = :email, `role` = :role, `status` = :status WHERE `id` = :id");
                        $stmtUpdate->execute([
                            'name' => $name,
                            'email' => $email,
                            'role' => $role,
                            'status' => $status,
                            'id' => $id
                        ]);
                    }
                    
                    // If current user updated their own role/status, update session to prevent lockouts
                    if ($id === $_SESSION['user_id']) {
                        $_SESSION['user_name'] = $name;
                        $_SESSION['user_email'] = $email;
                        $_SESSION['user_role'] = $role;
                        $_SESSION['user_status'] = $status;
                        
                        // If they demoted themselves, redirect them out
                        if ($role !== 'superadmin' || $status !== 'active') {
                            header("Location: ../logout.php");
                            exit;
                        }
                    }
                    
                    $success_msg = 'User profile updated successfully.';
                }
            } catch (PDOException $e) {
                $error_msg = 'Database modification failure: ' . $e->getMessage();
            }
        }
    }
    
    // Delete User
    elseif ($action === 'delete') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if ($id === $_SESSION['user_id']) {
            $error_msg = 'Self-deletion is blocked to preserve session security.';
        } else {
            try {
                $stmtDelete = $pdo->prepare("DELETE FROM `users` WHERE `id` = :id");
                $stmtDelete->execute(['id' => $id]);
                $success_msg = 'User account deleted successfully.';
            } catch (PDOException $e) {
                $error_msg = 'Database deletion failure: ' . $e->getMessage();
            }
        }
    }
}

// Fetch all users for display
$users = [];
try {
    $stmtUsers = $pdo->query("SELECT *, DATE_FORMAT(`created_at`, '%Y-%m-%d') as date_joined FROM `users` ORDER BY `id` ASC");
    $users = $stmtUsers->fetchAll();
} catch (PDOException $e) {
    $error_msg = 'Failed to fetch user list: ' . $e->getMessage();
}

include_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-content">
    <!-- Breadcrumbs -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item text-muted"><a href="dashboard.php">Console</a></li>
            <li class="breadcrumb-item active" aria-current="page">Manage Users</li>
        </ol>
    </nav>

    <!-- Header Section -->
    <div class="d-flex align-items-center justify-content-between mb-4 pb-2">
        <div>
            <h1 class="fw-bold fs-3 text-adaptive mb-1 font-display">Manage System Users</h1>
            <p class="text-secondary mb-0">Add, edit, modify roles, or deactivate player and staff records.</p>
        </div>
        <button class="btn btn-gaming-purple btn-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="fa-solid fa-plus me-2"></i>New Account
        </button>
    </div>

    <!-- Alert Dialogues -->
    <?php if ($error_msg): ?>
        <div class="alert alert-gaming alert-danger alert-dismissible fade show p-3 mb-4" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i> <?php echo htmlspecialchars($error_msg); ?>
            <button type="button" class="btn-close btn-close-white shadow-none" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($success_msg): ?>
        <div class="alert alert-gaming alert-success alert-dismissible fade show p-3 mb-4" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i> <?php echo htmlspecialchars($success_msg); ?>
            <button type="button" class="btn-close btn-close-white shadow-none" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- User Database Grid -->
    <div class="glass-card p-4">
        <div class="table-responsive">
            <table class="table align-middle mb-0 table-gaming">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User Profile</th>
                        <th>Email Address</th>
                        <th>Role Assigned</th>
                        <th>Account Status</th>
                        <th>Date Registered</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($users)): ?>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td class="font-display text-muted fw-bold">#<?php echo $u['id']; ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-dark font-display" style="width: 32px; height: 32px; font-size: 0.8rem; background: linear-gradient(135deg, var(--accent-purple) 0%, var(--accent-cyan) 100%);">
                                            <?php echo strtoupper(substr($u['name'], 0, 2)); ?>
                                        </div>
                                        <div class="fw-semibold text-adaptive"><?php echo htmlspecialchars($u['name']); ?></div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($u['email']); ?></td>
                                <td>
                                    <?php if ($u['role'] === 'superadmin'): ?>
                                        <span class="badge badge-superadmin">Superadmin</span>
                                    <?php elseif ($u['role'] === 'admin'): ?>
                                        <span class="badge badge-admin">Admin</span>
                                    <?php else: ?>
                                        <span class="badge badge-player">Player</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($u['status'] === 'active'): ?>
                                        <span class="text-success"><i class="fa-solid fa-circle-check me-1"></i> Active</span>
                                    <?php else: ?>
                                        <span class="text-secondary"><i class="fa-solid fa-circle-minus me-1"></i> Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="font-display text-secondary"><?php echo htmlspecialchars($u['date_joined']); ?></td>
                                <td class="text-end">
                                    <div class="d-flex align-items-center justify-content-end gap-2">
                                        <!-- Edit Action Button -->
                                        <button class="btn btn-sm btn-gaming-outline py-1 px-2 font-game" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editUserModal" 
                                                data-id="<?php echo $u['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($u['name']); ?>"
                                                data-email="<?php echo htmlspecialchars($u['email']); ?>"
                                                data-role="<?php echo $u['role']; ?>"
                                                data-status="<?php echo $u['status']; ?>">
                                            <i class="fa-solid fa-pen-to-square"></i> Edit
                                        </button>
                                        
                                        <!-- Delete Action Button -->
                                        <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                                            <form method="POST" action="users.php" onsubmit="return confirm('Are you sure you want to permanently delete this user?');" class="d-inline">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2"><i class="fa-solid fa-trash-can"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No records found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Add User -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-secondary border-0" style="border: 1px solid var(--card-border) !important;">
            <div class="modal-header border-bottom border-secondary border-opacity-25">
                <h5 class="modal-title text-adaptive font-game" id="addUserModalLabel">Create User Account</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="users.php">
                <input type="hidden" name="action" value="create">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label-custom">Full Name</label>
                        <input type="text" name="name" class="form-control form-control-custom" required placeholder="Gamer Tag or Name">
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Email Address</label>
                        <input type="email" name="email" class="form-control form-control-custom" required placeholder="player@clasharena.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Account Password</label>
                        <input type="password" name="password" class="form-control form-control-custom" required placeholder="••••••••">
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label-custom">System Role</label>
                            <select name="role" class="form-control form-control-custom">
                                <option value="customer" selected>Player</option>
                                <option value="admin">Admin</option>
                                <option value="superadmin">Superadmin</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">Status</label>
                            <select name="status" class="form-control form-control-custom">
                                <option value="active" selected>Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary border-opacity-25 p-3">
                    <button type="button" class="btn btn-gaming-outline btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-gaming-purple btn-sm">Create Profile</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit User -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-secondary border-0" style="border: 1px solid var(--card-border) !important;">
            <div class="modal-header border-bottom border-secondary border-opacity-25">
                <h5 class="modal-title text-adaptive font-game" id="editUserModalLabel">Modify User Account</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="users.php">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit-id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label-custom">Full Name</label>
                        <input type="text" name="name" id="edit-name" class="form-control form-control-custom" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Email Address</label>
                        <input type="email" name="email" id="edit-email" class="form-control form-control-custom" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Account Password (Leave blank to keep current)</label>
                        <input type="password" name="password" class="form-control form-control-custom" placeholder="••••••••">
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label-custom">System Role</label>
                            <select name="role" id="edit-role" class="form-control form-control-custom">
                                <option value="customer">Player</option>
                                <option value="admin">Admin</option>
                                <option value="superadmin">Superadmin</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">Status</label>
                            <select name="status" id="edit-status" class="form-control form-control-custom">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary border-opacity-25 p-3">
                    <button type="button" class="btn btn-gaming-outline btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-gaming-purple btn-sm">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Populate values inside Edit Modal dynamically -->
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const editUserModal = document.getElementById('editUserModal');
        if (editUserModal) {
            editUserModal.addEventListener('show.bs.modal', (event) => {
                const button = event.relatedTarget;
                
                // Extract attributes from target button
                const id = button.getAttribute('data-id');
                const name = button.getAttribute('data-name');
                const email = button.getAttribute('data-email');
                const role = button.getAttribute('data-role');
                const status = button.getAttribute('data-status');
                
                // Write into inputs
                document.getElementById('edit-id').value = id;
                document.getElementById('edit-name').value = name;
                document.getElementById('edit-email').value = email;
                document.getElementById('edit-role').value = role;
                document.getElementById('edit-status').value = status;
            });
        }
    });
</script>

<?php
include_once __DIR__ . '/../includes/footer.php';
?>
