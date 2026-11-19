<?php
/**
 * Happiva - Event Booking Platform
 * Home Page
 */

require_once 'config.php';

$pageTitle = 'EventSmart - Discover & Book Amazing Events';

// Get featured events
try {
    $pdo = getDB();
    $stmt = $pdo->query("SELECT * FROM events WHERE date >= CURDATE() AND is_approved = 1 ORDER BY date ASC LIMIT 8");
    $events = $stmt->fetchAll();
    
    $featuredStmt = $pdo->query("SELECT * FROM events WHERE featured = 1 AND is_approved = 1 AND date >= CURDATE() ORDER BY date ASC LIMIT 4");
    $featuredEvents = $featuredStmt->fetchAll();
} catch (PDOException $e) {
    $events = [];
    $featuredEvents = [];
}

// Get categories
$categories = ['All', 'Music', 'Sports', 'Tech', 'Art', 'Food', 'Business'];

$currentUser = null;
if (isLoggedIn()) {
    $currentUser = getCurrentUser();
}
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
<body class="<?php echo isLoggedIn() ? 'logged-in' : ''; ?>">
    <!-- Navigation -->
    <nav class="navbar public-nav">
        <div class="container">
            <a href="index.php" class="logo">
                <img src="uploads/eventsmart-logo.svg" alt="EventSmart logo" style="height: 34px; width: 34px; object-fit: contain; margin-right: 10px;">
                <span>EventSmart</span>
            </a>
            <ul class="nav-links">
                <li><a href="index.php" class="active">Home</a></li>
                <li><a href="events.php">Events</a></li>
                <li><a href="contact.php">Contact</a></li>
                <?php if (!isLoggedIn()): ?>
                <li class="mobile-auth-link"><a href="login.php">Login</a></li>
                <li class="mobile-auth-link"><a href="register.php">Get Started</a></li>
                <?php endif; ?>
                <?php if (isLoggedIn()): ?>
                <li><a href="messages.php">Messages</a></li>
                <li><a href="tickets.php">My Tickets</a></li>
                <li><a href="profile.php">Profile</a></li>
                <?php endif; ?>
                <?php if (isOrganizer()): ?>
                <li><a href="dashboard.php">Dashboard</a></li>
                <?php endif; ?>
            </ul>
            <div class="nav-actions">
                <?php if (isLoggedIn()): ?>
                    <a href="profile.php" class="user-chip">
                        <?php if (!empty($_SESSION['user_profile_image'])): ?>
                            <img src="<?php echo sanitize(resolveImageUrl($_SESSION['user_profile_image'])); ?>" alt="Profile" class="user-avatar">
                        <?php else: ?>
                            <span class="user-avatar placeholder"><?php echo strtoupper(substr($_SESSION['user_name'], 0, 1)); ?></span>
                        <?php endif; ?>
                        <span class="user-name"><?php echo sanitize($_SESSION['user_name']); ?></span>
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

    <!-- Hero Section -->
    <section class="hero" id="hero-section">
        <div class="hero-bg-elements" id="particles"></div>
        <div class="container">
            <div class="hero-content reveal-up">
                <div class="hero-badge">
                    <span>Discover Amazing Events</span>
                </div>
                <h1>Find & Book Your <span>Perfect Event</span></h1>
                <p>Browse thousands of events, book instantly, and get unique QR code tickets for seamless entry. Experience the future of event ticketing.</p>
                <div class="hero-buttons">
                    <a href="events.php" class="btn btn-primary btn-lg">Explore Events</a>
                    <a href="#how-it-works" class="btn btn-secondary btn-lg">How It Works</a>
                </div>
                <?php if (isLoggedIn() && !empty($currentUser) && ($currentUser['role'] ?? 'user') === 'user'): ?>
                <div class="hero-upgrade-card" style="margin-top: 24px; padding: 18px 20px; border: 1px solid rgba(255,255,255,0.14); border-radius: var(--radius-lg); background: rgba(255,255,255,0.08); max-width: 640px;">
                    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px;">
                        <div>
                            <h3 style="margin: 0 0 6px; font-size: 18px;">Become an Event Organizer</h3>
                            <p style="margin: 0; color: var(--text-secondary);">Host your own events, manage bookings, and verify attendees with QR tickets.</p>
                        </div>
                        <?php if (!empty($currentUser['organizer_request_pending'])): ?>
                            <div style="display:flex; gap: 8px; align-items:center; flex-wrap:wrap;">
                                <span class="status-badge inactive">Request Pending</span>
                                <a href="verify_api.php?type=cancel_upgrade" class="btn btn-secondary btn-sm" onclick="return confirm('Are you sure you want to cancel your organizer request?');">Cancel</a>
                            </div>
                        <?php else: ?>
                            <a href="verify_api.php?type=upgrade" class="btn btn-primary">Request Organizer Access</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                <div class="hero-stats">
                    <div class="hero-stat">
                        <h3><?php echo count($events); ?>+</h3>
                        <p>Active Events</p>
                    </div>
                    <div class="hero-stat">
                        <h3>10K+</h3>
                        <p>Happy Users</p>
                    </div>
                    <div class="hero-stat">
                        <h3>50+</h3>
                        <p>Event Categories</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Events -->
    <?php if (!empty($featuredEvents)): ?>
    <section class="section">
        <div class="container">
            <div class="section-header reveal-up">
                <h2>Featured Events</h2>
                <p>Hand-picked events that you shouldn't miss</p>
            </div>
            <div class="featured-grid">
                <?php foreach ($featuredEvents as $event): ?>
                <a href="event.php?id=<?php echo $event['id']; ?>" class="featured-card reveal-up tilt-card">
                    <img src="<?php echo htmlspecialchars(resolveImageUrl($event['image'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo sanitize($event['title']); ?>" onerror="this.onerror=null;this.src='<?php echo htmlspecialchars(resolveImageUrl('uploads/events/event-placeholder.svg'), ENT_QUOTES, 'UTF-8'); ?>';">
                    <div class="featured-overlay">
                        <span class="featured-category"><?php echo sanitize($event['category']); ?></span>
                        <h3><?php echo sanitize($event['title']); ?></h3>
                        <p><?php echo formatDate($event['date']); ?> • <?php echo sanitize($event['venue']); ?></p>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- All Events -->
    <section class="section" style="background: var(--bg-surface);">
        <div class="container">
            <div class="section-header reveal-up">
                <h2>Upcoming Events</h2>
                <p>Discover events that match your interests</p>
            </div>
            
            <div class="categories">
                <?php foreach ($categories as $index => $cat): ?>
                <button class="category-btn <?php echo $index === 0 ? 'active' : ''; ?>" data-category="<?php echo strtolower($cat); ?>">
                    <?php echo $cat; ?>
                </button>
                <?php endforeach; ?>
            </div>

            <div class="events-grid">
                <?php foreach ($events as $event): ?>
                <a href="event.php?id=<?php echo $event['id']; ?>" class="event-card reveal-up tilt-card" data-category="<?php echo strtolower($event['category']); ?>">
                    <div class="event-image">
                        <img src="<?php echo htmlspecialchars(resolveImageUrl($event['image'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo sanitize($event['title']); ?>" onerror="this.onerror=null;this.src='<?php echo htmlspecialchars(resolveImageUrl('uploads/events/event-placeholder.svg'), ENT_QUOTES, 'UTF-8'); ?>';">
                        <span class="event-category"><?php echo sanitize($event['category']); ?></span>
                        <span class="event-price"><?php echo $event['price'] > 0 ? formatPrice($event['price']) : 'Free'; ?></span>
                    </div>
                    <div class="event-content">
                        <div class="event-date">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                            <?php echo formatDate($event['date']); ?>
                        </div>
                        <div class="event-countdown" data-start-at="<?php echo htmlspecialchars(($event['date'] ?? '') . ' ' . ($event['time'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-event-title="<?php echo htmlspecialchars($event['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                            <span class="countdown-label">Starts in</span>
                            <span class="countdown-value">--</span>
                        </div>
                        <h3 class="event-title"><?php echo sanitize($event['title']); ?></h3>
                        <div class="event-venue">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                <circle cx="12" cy="10" r="3"></circle>
                            </svg>
                            <?php echo sanitize($event['venue']); ?>
                        </div>
                        <div class="event-footer">
                            <span class="event-spots"><?php echo $event['capacity']; ?> spots available</span>
                            <?php if (isLoggedIn()): ?>
                                <button class="favorite-toggle" data-event-id="<?php echo $event['id']; ?>" type="button">♡</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
                
                <?php if (empty($events)): ?>
                <div class="empty-state" style="grid-column: 1 / -1;">
                    <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                    <h3>No Events Available</h3>
                    <p>Check back soon for upcoming events!</p>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="text-center mt-4">
                <a href="events.php" class="btn btn-secondary">View All Events</a>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section class="section how-it-works" id="how-it-works">
        <div class="container">
            <div class="section-header reveal-up">
                <h2>How It Works</h2>
                <p>Book your next event in three simple steps</p>
            </div>
            <div class="steps-grid">
                <div class="step-card reveal-up" style="--delay: 0.1s">
                    <div class="step-icon">🔍</div>
                    <h3>Discover Events</h3>
                    <p>Browse through our curated collection of events across various categories and find what interests you.</p>
                </div>
                <div class="step-card reveal-up" style="--delay: 0.2s">
                    <div class="step-icon">📅</div>
                    <h3>Book Instantly</h3>
                    <p>Select your preferred date and number of tickets, then complete your booking with our secure checkout.</p>
                </div>
                <div class="step-card reveal-up" style="--delay: 0.3s">
                    <div class="step-icon">📱</div>
                    <h3>Get QR Ticket</h3>
                    <p>Receive a unique QR code instantly via the app. This serves as your digital entry ticket to the event.</p>
                </div>
                <div class="step-card reveal-up" style="--delay: 0.4s">
                    <div class="step-icon">✓</div>
                    <h3>Quick Check-in</h3>
                    <p>At the venue, simply show your QR code for scanning. Fast, contactless, and hassle-free entry.</p>
                </div>
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
            <div class="chat-messages">
                <!-- Messages will be added here -->
            </div>
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
</body>
</html>
