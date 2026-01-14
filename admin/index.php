<?php
require_once '../config/config.php';

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Get statistics
$conn = getDBConnection();
$stats = [];

// Total artists
$result = $conn->query("SELECT COUNT(*) as count FROM artists");
$stats['artists'] = $result->fetch_assoc()['count'];

// Total songs
$result = $conn->query("SELECT COUNT(*) as count FROM songs");
$stats['songs'] = $result->fetch_assoc()['count'];

// Total play count
$result = $conn->query("SELECT SUM(play_count) as total FROM songs");
$stats['plays'] = $result->fetch_assoc()['total'] ?? 0;

// Recent songs
$recent_songs = $conn->query("
    SELECT s.*, a.name as artist_name 
    FROM songs s 
    LEFT JOIN artists a ON s.artist_id = a.id 
    ORDER BY s.created_at DESC 
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Dhama Podcast Admin</title>
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
                <a href="index.php" class="nav-item active">
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
                <a href="categories.php" class="nav-item">
                    <span class="nav-item-icon">📁</span>
                    Categories
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="admin-main">
            <!-- Header -->
            <header class="admin-header">
                <div class="header-title">Dashboard</div>
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
                <!-- Stats -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-label">Total Artists</div>
                        <div class="stat-value primary"><?php echo $stats['artists']; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Total Songs</div>
                        <div class="stat-value success"><?php echo $stats['songs']; ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Total Plays</div>
                        <div class="stat-value warning"><?php echo number_format($stats['plays']); ?></div>
                    </div>
                </div>

                <!-- Recent Songs -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Recent Songs</div>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Artist</th>
                                <th>Plays</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_songs)): ?>
                                <tr>
                                    <td colspan="5" class="text-center">
                                        <div class="empty-state">
                                            <div class="empty-state-icon">🎵</div>
                                            <div class="empty-state-text">No songs yet</div>
                                            <div class="empty-state-subtext">Add your first song to get started</div>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_songs as $song): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($song['title']); ?></td>
                                        <td><?php echo htmlspecialchars($song['artist_name']); ?></td>
                                        <td><?php echo number_format($song['play_count']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($song['created_at'])); ?></td>
                                        <td>
                                            <a href="songs.php?action=edit&id=<?php echo $song['id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
