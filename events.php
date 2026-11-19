<?php
/**
 * Happiva / EventSmart - Events Page
 */

require_once 'config.php';

$pageTitle = 'EventSmart - Discover Events';

// Null-safe escape helper (avoids trim(null) deprecation warnings)
if (!function_exists('esc')) {
    function esc($value) {
        return htmlspecialchars(trim((string) ($value ?? '')), ENT_QUOTES, 'UTF-8');
    }
}

// Selected filters
$category = trim($_GET['category'] ?? '');
$location = trim($_GET['location'] ?? '');

// Defaults
$categories = ['All', 'Music', 'Sports', 'Tech', 'Art', 'Food', 'Business'];
$events = [];
$locations = [];

try {
    $pdo = getDB();

    // Available locations (taken from event addresses/venues)
    $locationStmt = $pdo->query("
        SELECT DISTINCT address
        FROM events
        WHERE address IS NOT NULL AND address != ''
          AND date >= CURDATE() AND is_approved = 1
        ORDER BY address ASC
    ");
    $locations = array_values(array_filter(
        array_map(function ($row) {
            return trim((string) $row['address']);
        }, $locationStmt->fetchAll()),
        function ($value) {
            return $value !== '';
        }
    ));

    // Upcoming approved events
    $sql = "SELECT * FROM events WHERE date >= CURDATE() AND is_approved = 1";
    $params = [];

    if ($category !== '' && strcasecmp($category, 'All') !== 0) {
        $sql .= " AND category = :category";
        $params[':category'] = $category;
    }

    if ($location !== '') {
        $sql .= " AND address = :location";
        $params[':location'] = $location;
    }

    $sql .= " ORDER BY date ASC, time ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $events = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('Events page error: ' . $e->getMessage());
    $events = [];
    $locations = [];
}

$activeCategory = ($category === '' ? 'All' : $category);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc($pageTitle); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=Outfit:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css?v=11">
    <link rel="icon" href="uploads/eventsmart-logo.svg">
</head>
<body class="<?php echo isLoggedIn() ? 'logged-in' : ''; ?>">

    <!-- Navigation -->
    <nav class="navbar public-nav">
        <div class="container">
            <a href="index.php" class="logo">
                <img src="uploads/eventsmart-logo.svg" alt="EventSmart logo" style="height: 34px; width: 34px; object-fit: contain; margin-right: 10px;">
                <span>EventSmart</span>
            </a>
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="events.php" class="active">Events</a></li>
                <li><a href="contact.php">Contact</a></li>
                <?php if (!isLoggedIn()): ?>
                    <li class="mobile-auth-link"><a href="login.php">Login</a></li>
                    <li class="mobile-auth-link"><a href="register.php">Get Started</a></li>
                <?php else: ?>
                    <li><a href="tickets.php">My Tickets</a></li>
                    <?php if (isOrganizer()): ?>
                        <li><a href="dashboard.php">Dashboard</a></li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
            <div class="nav-actions">
                <?php if (isLoggedIn()): ?>
                    <a href="profile.php" class="user-chip">
                        <?php if (!empty($_SESSION['user_profile_image'])): ?>
                            <img src="<?php echo esc(resolveImageUrl($_SESSION['user_profile_image'])); ?>" alt="Profile" class="user-avatar">
                        <?php else: ?>
                            <span class="user-avatar placeholder"><?php echo esc(strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1))); ?></span>
                        <?php endif; ?>
                        <span class="user-name"><?php echo esc($_SESSION['user_name'] ?? ''); ?></span>
                    </a>
                    <a href="#" class="btn btn-secondary btn-sm" onclick="event.preventDefault(); openModal('logoutModal');">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-ghost">Login</a>
                    <a href="register.php" class="btn btn-primary">Get Started</a>
                <?php endif; ?>
                <button class="mobile-menu-btn" type="button" aria-label="Toggle navigation" aria-expanded="false">☰</button>
            </div>
        </div>
    </nav>

    <!-- Events Header -->
    <section class="event-hero" style="min-height: auto; padding: 140px 0 48px;">
        <div class="container">
            <div class="text-center">
                <h1 class="events-page-heading">Discover Your Next Ticket</h1>
                <p style="color: var(--text-secondary); max-width: 650px; margin: 0 auto;">Browse curated party-style events with clear details, venue info and ticket-inspired layouts designed for every celebration.</p>
            </div>
        </div>
    </section>

    <!-- Events Section -->
    <section class="section" style="padding-top: 0;">
        <div class="container">

            <!-- Search & Filter -->
            <div class="flex flex-between" style="margin-bottom: 32px; flex-wrap: wrap; gap: 16px;">
                <div class="categories" style="justify-content: flex-start; margin-bottom: 0;">
                    <?php foreach ($categories as $cat): ?>
                        <a href="events.php?category=<?php echo urlencode($cat); ?><?php echo $location !== '' ? '&location=' . urlencode($location) : ''; ?>"
                           class="category-btn <?php echo (strcasecmp($activeCategory, $cat) === 0) ? 'active' : ''; ?>">
                            <?php echo esc($cat); ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <form method="get" action="events.php" class="location-filter" style="display: flex; gap: 10px; align-items: center;">
                    <?php if ($category !== '' && strcasecmp($category, 'All') !== 0): ?>
                        <input type="hidden" name="category" value="<?php echo esc($category); ?>">
                    <?php endif; ?>
                    <label for="locationFilter" style="color: var(--text-secondary); white-space: nowrap;">Location</label>
                    <select id="locationFilter" name="location" onchange="this.form.submit()" style="min-width: 180px; padding: 12px 16px; background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-sm); color: var(--text-primary);">
                        <option value="">All locations</option>
                        <?php foreach ($locations as $locationOption): ?>
                            <option value="<?php echo esc($locationOption); ?>" <?php echo strcasecmp($location, $locationOption) === 0 ? 'selected' : ''; ?>>
                                <?php echo esc($locationOption); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>

                <div style="position: relative; width: 300px; max-width: 100%;">
                    <input type="text" id="searchInput" placeholder="Search events..." style="width: 100%; padding: 12px 16px 12px 44px; background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-sm); color: var(--text-primary);">
                    <svg style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted);" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="m21 21-4.35-4.35"></path>
                    </svg>
                </div>
            </div>

            <!-- Events Grid -->
            <div class="events-grid">
                <?php foreach ($events as $event):
                    $venue       = trim((string) ($event['venue'] ?? ''));
                    $address     = trim((string) ($event['address'] ?? ''));
                    $description = (string) ($event['description'] ?? '');
                    $eventAddress = trim($venue . ($address !== '' ? ', ' . $address : ''));
                    $directionUrl = 'https://www.google.com/maps/dir/?api=1&destination=' . urlencode($eventAddress);
                    $categorySlug = strtolower(preg_replace('/[^a-z0-9_-]/i', '', (string) ($event['category'] ?? '')));
                    $descShort = function_exists('mb_substr') ? mb_substr($description, 0, 110) : substr($description, 0, 110);
                    $descLen   = function_exists('mb_strlen') ? mb_strlen($description) : strlen($description);
                ?>
                <div class="event-card theme-<?php echo esc($categorySlug); ?>"
                     data-category="<?php echo esc($categorySlug); ?>"
                     style="--ticket-primary: <?php echo esc($event['ticket_primary_color'] ?? '#4f46e5'); ?>; --ticket-secondary: <?php echo esc($event['ticket_secondary_color'] ?? '#ec4899'); ?>;">

                    <a href="event.php?id=<?php echo (int) $event['id']; ?>" class="event-card-link">
                        <div class="event-image">
                            <img src="<?php echo esc(resolveImageUrl($event['image'] ?? '')); ?>"
                                 alt="<?php echo esc($event['title'] ?? ''); ?>"
                                 onerror="this.onerror=null;this.src='<?php echo esc(resolveImageUrl('uploads/events/event-placeholder.svg')); ?>';">
                            <span class="event-category"><?php echo esc($event['category'] ?? ''); ?></span>
                            <span class="event-price"><?php echo ((float) ($event['price'] ?? 0)) > 0 ? esc(formatPrice($event['price'])) : 'Free'; ?></span>
                        </div>

                        <div class="event-content">
                            <div class="event-date">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                    <line x1="16" y1="2" x2="16" y2="6"></line>
                                    <line x1="8" y1="2" x2="8" y2="6"></line>
                                    <line x1="3" y1="10" x2="21" y2="10"></line>
                                </svg>
                                <?php echo esc(formatDate($event['date'])); ?> at <?php echo esc(formatTime($event['time'])); ?>
                            </div>

                            <div class="event-countdown"
                                 data-start-at="<?php echo esc(($event['date'] ?? '') . ' ' . ($event['time'] ?? '')); ?>"
                                 data-event-title="<?php echo esc($event['title'] ?? ''); ?>">
                                <span class="countdown-label">Starts in</span>
                                <span class="countdown-value">--</span>
                            </div>

                            <h3 class="event-title"><?php echo esc($event['title'] ?? ''); ?></h3>
                            <p class="event-meta"><?php echo esc($descShort); ?><?php echo $descLen > 110 ? '...' : ''; ?></p>

                            <div class="event-venue">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                    <circle cx="12" cy="10" r="3"></circle>
                                </svg>
                                <?php echo esc($venue); ?><?php echo $address !== '' ? ', ' . esc($address) : ''; ?>
                            </div>

                            <div class="event-footer">
                                <span class="event-spots"><?php echo (int) ($event['capacity'] ?? 0); ?> spots</span>
                                <?php if (isLoggedIn()): ?>
                                    <button class="favorite-toggle" data-event-id="<?php echo (int) $event['id']; ?>" type="button">♡</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>

                    <div class="event-card-actions">
                        <a href="<?php echo esc($directionUrl); ?>" target="_blank" rel="noopener" class="btn btn-secondary btn-sm">View Map &amp; Directions</a>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php if (empty($events)): ?>
                <div class="empty-state" style="grid-column: 1 / -1;">
                    <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="m21 21-4.35-4.35"></path>
                    </svg>
                    <h3>No Events Found</h3>
                    <p>Try adjusting your filters or search terms.</p>
                    <a href="events.php" class="btn btn-primary">View All Events</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php include 'site_footer.php'; ?>

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

    <!-- AI Chat Widget -->
    <div class="chat-widget">
        <button class="chat-toggle">💬</button>
        <div class="chat-window">
            <div class="chat-header">
                <div class="chat-avatar">🤖</div>
                <div class="chat-title">
                    <h4>EventSmart Assistant</h4>
                    <p>AI Powered Help</p>
                </div>
            </div>
            <div class="chat-messages"></div>
            <div class="chat-input-area">
                <input type="text" placeholder="Ask me anything...">
                <button class="chat-send">➤</button>
            </div>
        </div>
    </div>

    <script>
        window.currentUserName = <?php echo json_encode($_SESSION['user_name'] ?? null); ?>;
    </script>
    <script src="js/main.js?v=7"></script>

    <script>
    (function () {
        var input = document.getElementById('searchInput');
        if (!input) return;
        input.addEventListener('input', function () {
            var term = this.value.toLowerCase().trim();
            document.querySelectorAll('.events-grid .event-card').forEach(function (card) {
                card.style.display = card.textContent.toLowerCase().indexOf(term) !== -1 ? '' : 'none';
            });
        });
    })();
    </script>
</body>
</html>