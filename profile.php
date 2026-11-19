<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

$pdo = getDB();
$currentUser = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['favorite_event_id'])) {
        header('Content-Type: application/json');
        $eventId = (int)($_POST['favorite_event_id'] ?? 0);
        if ($eventId > 0) {
            $check = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND event_id = ?");
            $check->execute([$_SESSION['user_id'], $eventId]);
            $exists = $check->fetch();

            if ($exists) {
                $pdo->prepare("DELETE FROM favorites WHERE user_id = ? AND event_id = ?")->execute([$_SESSION['user_id'], $eventId]);
                echo json_encode(['success' => true, 'is_favorite' => false, 'message' => 'Removed from favorites']);
            } else {
                $pdo->prepare("INSERT INTO favorites (user_id, event_id) VALUES (?, ?)")->execute([$_SESSION['user_id'], $eventId]);
                echo json_encode(['success' => true, 'is_favorite' => true, 'message' => 'Saved to favorites']);
            }
            exit;
        }
        echo json_encode(['success' => false, 'message' => 'Invalid event']);
        exit;
    }

    $bio = trim($_POST['bio'] ?? '');
    $profileImage = '';
    $coverImage = '';

    if (isset($_FILES['profile_image_file']) && $_FILES['profile_image_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/uploads/profile/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($_FILES['profile_image_file']['name']));
        $targetPath = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['profile_image_file']['tmp_name'], $targetPath)) {
            $profileImage = 'uploads/profile/' . $fileName;
        }
    } elseif (!empty($_POST['profile_image'])) {
        $profileImage = trim($_POST['profile_image']);
    }

    if (isset($_FILES['cover_image_file']) && $_FILES['cover_image_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/uploads/profile/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileName = time() . '_cover_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($_FILES['cover_image_file']['name']));
        $targetPath = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['cover_image_file']['tmp_name'], $targetPath)) {
            $coverImage = 'uploads/profile/' . $fileName;
        }
    } elseif (!empty($_POST['cover_image'])) {
        $coverImage = trim($_POST['cover_image']);
    }

    $stmt = $pdo->prepare("UPDATE users SET bio = ?, profile_image = ?, cover_image = ? WHERE id = ?");
    $stmt->execute([$bio, $profileImage, $coverImage, $_SESSION['user_id']]);

    $_SESSION['user_bio'] = $bio;
    $_SESSION['user_profile_image'] = $profileImage;
    $_SESSION['user_cover_image'] = $coverImage;
    $currentUser = getCurrentUser();
}

$stmt = $pdo->prepare("SELECT e.* FROM favorites f JOIN events e ON f.event_id = e.id WHERE f.user_id = ? ORDER BY e.date ASC");
$stmt->execute([$_SESSION['user_id']]);
$favorites = $stmt->fetchAll();

$favoriteCount = count($favorites);
$ticketCount = 0;
$ticketStmt = $pdo->prepare("SELECT COUNT(*) as total FROM bookings WHERE user_id = ?");
$ticketStmt->execute([$_SESSION['user_id']]);
$ticketCount = (int)($ticketStmt->fetch()['total'] ?? 0);

$pageTitle = 'My Profile - EventSmart';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=Outfit:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=9">
    <link rel="icon" href="uploads/eventsmart-logo.svg">
</head>
<body class="logged-in">
    <nav class="navbar scrolled">
        <div class="container">
            <a href="index.php" class="logo">
                <img src="uploads/eventsmart-logo.svg" alt="EventSmart logo" style="height: 34px; width: 34px; object-fit: contain; margin-right: 10px;">
                <span>EventSmart</span>
            </a>
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="events.php">Events</a></li>
                <li><a href="tickets.php">My Tickets</a></li>
                <li><a href="profile.php" class="active">Profile</a></li>
            </ul>
            <div class="nav-actions">
                <span class="user-name"><?php echo sanitize($_SESSION['user_name']); ?></span>
                <a href="#" class="btn btn-secondary btn-sm" onclick="event.preventDefault(); openModal('logoutModal');">Logout</a>
                <button class="mobile-menu-btn" type="button" aria-label="Toggle navigation" aria-expanded="false">☰</button>
            </div>
        </div>
    </nav>

    <!-- Logout Confirmation Modal -->
    <div class="modal-overlay" id="logoutModal">
        <div class="modal" style="max-width: 420px;">
            <div class="modal-header">
                <h3>Log out?</h3>
                <button class="modal-close" onclick="closeModal('logoutModal')">&times;</button>
            </div>
            <p style="color: var(--text-secondary); margin-bottom: 8px;">Are you sure you want to end your session?</p>
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="closeModal('logoutModal')">Cancel</button>
                <a href="auth.php?action=logout" class="btn btn-primary">Yes, log out</a>
            </div>
        </div>
    </div>

    <section class="section">
        <div class="container">
            <div class="profile-hero">
                <div class="profile-cover" style="background-image: url('<?php echo sanitize(resolveImageUrl($currentUser['cover_image'] ?? '')); ?>');"></div>
                <div class="profile-header-card">
                    <div class="profile-avatar-wrap">
                        <?php if (!empty($currentUser['profile_image'])): ?>
                            <img src="<?php echo sanitize(resolveImageUrl($currentUser['profile_image'])); ?>" alt="Profile picture" class="profile-avatar">
                        <?php else: ?>
                            <div class="profile-avatar placeholder"><?php echo strtoupper(substr($currentUser['name'] ?? 'U', 0, 1)); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="profile-summary">
                        <h1><?php echo sanitize($currentUser['name'] ?? $_SESSION['user_name']); ?></h1>
                        <p class="profile-email"><?php echo sanitize($currentUser['email'] ?? $_SESSION['user_email']); ?></p>
                        <div class="profile-stats">
                            <div><strong><?php echo $favoriteCount; ?></strong><span>Favorites</span></div>
                            <div><strong><?php echo $ticketCount; ?></strong><span>Tickets</span></div>
                            <div><strong><?php echo $currentUser['role'] ?? 'user'; ?></strong><span>Role</span></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="profile-shell">
                <div class="profile-card">
                    <h2>About You</h2>
                    <form method="post" class="profile-form" enctype="multipart/form-data">
                        <label for="profile_image_file">Upload profile image</label>
                        <input type="file" id="profile_image_file" name="profile_image_file" accept="image/*">

                        <label for="cover_image_file">Upload cover photo</label>
                        <input type="file" id="cover_image_file" name="cover_image_file" accept="image/*">

                        <label for="bio">Personal bio</label>
                        <textarea id="bio" name="bio" rows="4" placeholder="Tell people a bit about yourself..."><?php echo sanitize($currentUser['bio'] ?? ''); ?></textarea>

                        <button type="submit" class="btn btn-primary">Save Profile</button>
                    </form>
                </div>

                <div class="profile-card">
                    <h2>Saved Favorite Events</h2>
                    <?php if (!empty($favorites)): ?>
                        <div class="favorites-list">
                            <?php foreach ($favorites as $event): ?>
                                <div class="favorite-item">
                                    <a href="event.php?id=<?php echo $event['id']; ?>" class="favorite-link">
                                        <strong><?php echo sanitize($event['title']); ?></strong>
                                        <span><?php echo formatDate($event['date']); ?></span>
                                    </a>
                                    <button class="favorite-remove-btn" data-event-id="<?php echo $event['id']; ?>" type="button">Remove</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>m
                        <p class="empty-profile-state">You have not saved any favorite events yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <?php include 'site_footer.php'; ?>
    <script src="js/main.js?v=7"></script>
</body>
</html>

