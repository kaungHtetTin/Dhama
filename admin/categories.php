<?php
require_once '../config/config.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$conn = getDBConnection();
$message = '';
$message_type = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create' || $action === 'update') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        if (!empty($name)) {
            if ($action === 'create') {
                $stmt = $conn->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
                $stmt->bind_param("ss", $name, $description);
                if ($stmt->execute()) {
                    $message = 'Category created successfully';
                    $message_type = 'success';
                } else {
                    $message = 'Error creating category';
                    $message_type = 'error';
                }
            } else {
                $id = intval($_POST['id']);
                $stmt = $conn->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?");
                $stmt->bind_param("ssi", $name, $description, $id);
                if ($stmt->execute()) {
                    $message = 'Category updated successfully';
                    $message_type = 'success';
                } else {
                    $message = 'Error updating category';
                    $message_type = 'error';
                }
            }
            $stmt->close();
        } else {
            $message = 'Name is required';
            $message_type = 'error';
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id']);
        $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $message = 'Category deleted successfully';
            $message_type = 'success';
        } else {
            $message = 'Error deleting category';
            $message_type = 'error';
        }
        $stmt->close();
    }
}

// Get categories
$categories = $conn->query("SELECT * FROM categories ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);

// Get category for editing
$edit_category = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $result = $conn->query("SELECT * FROM categories WHERE id = $id");
    if ($result->num_rows === 1) {
        $edit_category = $result->fetch_assoc();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories - Dhama Podcast Admin</title>
    <link rel="stylesheet" href="assets/css/admin.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500&family=Roboto:wght@400;500&display=swap">
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <aside class="admin-sidebar">
            <div class="sidebar-header">
                <h1>Dhama Podcast</h1>
            </div>
            <nav class="sidebar-nav">
                <a href="index.php" class="nav-item">
                    <span class="nav-item-icon">📊</span>
                    Dashboard
                </a>
                <a href="artists.php" class="nav-item">
                    <span class="nav-item-icon">👤</span>
                    Artists
                </a>
                <a href="songs.php" class="nav-item">
                    <span class="nav-item-icon">🎵</span>
                    Songs
                </a>
                <a href="bulk_upload.php" class="nav-item">
                    <span class="nav-item-icon">📤</span>
                    Bulk Upload
                </a>
                <a href="categories.php" class="nav-item active">
                    <span class="nav-item-icon">📁</span>
                    Categories
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="admin-main">
            <!-- Header -->
            <header class="admin-header">
                <div class="header-title">Categories</div>
                <div class="header-actions">
                    <div class="user-info">
                        <span>👤</span>
                        <span><?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                    </div>
                    <a href="logout.php" class="btn btn-secondary btn-sm">Logout</a>
                </div>
            </header>

            <!-- Content -->
            <div class="admin-content">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>">
                        <span><?php echo $message_type === 'success' ? '✓' : '✗'; ?></span>
                        <span><?php echo htmlspecialchars($message); ?></span>
                    </div>
                <?php endif; ?>

                <div class="content-header">
                    <div>
                        <h2 class="page-title"><?php echo $edit_category ? 'Edit Category' : 'Categories'; ?></h2>
                        <p class="page-subtitle"><?php echo $edit_category ? 'Update category information' : 'Organize your content with categories'; ?></p>
                    </div>
                    <?php if (!$edit_category): ?>
                        <button onclick="showForm()" class="btn btn-primary">+ Add Category</button>
                    <?php endif; ?>
                </div>

                <?php if ($edit_category): ?>
                    <div class="card">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="id" value="<?php echo $edit_category['id']; ?>">
                            
                            <div class="form-group">
                                <label for="name">Name *</label>
                                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($edit_category['name']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea id="description" name="description" rows="4"><?php echo htmlspecialchars($edit_category['description']); ?></textarea>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">Update Category</button>
                                <a href="categories.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <div id="category-form" class="card" style="display: none;">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="create">
                            
                            <div class="form-group">
                                <label for="name">Name *</label>
                                <input type="text" id="name" name="name" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea id="description" name="description" rows="4"></textarea>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">Create Category</button>
                                <button type="button" onclick="hideForm()" class="btn btn-secondary">Cancel</button>
                            </div>
                        </form>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">All Categories (<?php echo count($categories); ?>)</div>
                        </div>
                        <?php if (empty($categories)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">📁</div>
                                <div class="empty-state-text">No categories found</div>
                                <div class="empty-state-subtext">Add your first category to get started</div>
                            </div>
                        <?php else: ?>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Description</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categories as $category): ?>
                                        <tr>
                                            <td><?php echo $category['id']; ?></td>
                                            <td><strong><?php echo htmlspecialchars($category['name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars(substr($category['description'], 0, 50)) . (strlen($category['description']) > 50 ? '...' : ''); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($category['created_at'])); ?></td>
                                            <td>
                                                <a href="?action=edit&id=<?php echo $category['id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this category?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $category['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        function showForm() {
            document.getElementById('category-form').style.display = 'block';
            document.getElementById('category-form').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
        function hideForm() {
            document.getElementById('category-form').style.display = 'none';
        }
    </script>
</body>
</html>
