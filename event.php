<?php
/**
 * Happiva - Event Booking Platform
 * Event Details Page
 */

require_once 'config.php';

// Get event ID
$eventId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$eventId) {
    redirect('events.php');
}

// Get event details
try {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT e.*, u.name as organizer_name FROM events e LEFT JOIN users u ON e.organizer_id = u.id WHERE e.id = ?");
    $stmt->execute([$eventId]);
    $event = $stmt->fetch();
    
    if (!$event) {
        redirect('events.php');
    }
    
    // Get comments
    $commentStmt = $pdo->prepare("SELECT c.*, u.name FROM event_comments c JOIN users u ON c.user_id = u.id WHERE c.event_id = ? ORDER BY c.created_at DESC");
    $commentStmt->execute([$eventId]);
    $comments = $commentStmt->fetchAll();

    // Get similar events for assistant recommendations
    $relatedStmt = $pdo->prepare("SELECT id, title, date, venue, price, category FROM events WHERE category = ? AND id != ? AND is_approved = 1 ORDER BY date ASC LIMIT 3");
    $relatedStmt->execute([$event['category'], $eventId]);
    $relatedEvents = $relatedStmt->fetchAll();
    
    $pageTitle = $event['title'] . ' - EventSmart';
} catch (PDOException $e) {
    redirect('events.php');
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
    <link rel="stylesheet" href="css/style.css?v=10">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90' font-family='sans-serif' font-weight='bold' fill='%236366F1'>H</text></svg>">
</head>
<body class="<?php echo isLoggedIn() ? 'logged-in' : ''; ?>">
    <!-- Navigation -->
    <nav class="navbar scrolled public-nav">
        <div class="container">
            <a href="index.php" class="logo">
                <img src="uploads/eventsmart-logo.svg" alt="EventSmart logo" style="height: 34px; width: 34px; object-fit: contain; margin-right: 10px;">
                <span>EventSmart</span>
            </a>
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="events.php">Events</a></li>
                <li><a href="contact.php">Contact</a></li>
                <?php if (!isLoggedIn()): ?>
                <li class="mobile-auth-link"><a href="login.php">Login</a></li>
                <li class="mobile-auth-link"><a href="register.php">Get Started</a></li>
                <?php endif; ?>
                <?php if (isLoggedIn()): ?>
                <li><a href="messages.php">Messages</a></li>
                <li><a href="tickets.php">My Tickets</a></li>
                <?php endif; ?>
                <?php if (isOrganizer()): ?>
                <li><a href="dashboard.php">Dashboard</a></li>
                <?php endif; ?>
            </ul>
            <div class="nav-actions">
                <?php if (isLoggedIn()): ?>
                    <span class="user-name"><?php echo sanitize($_SESSION['user_name']); ?></span>
                    <a href="#" class="btn btn-secondary btn-sm" onclick="event.preventDefault(); openModal('logoutModal');">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-ghost">Login</a>
                    <a href="register.php" class="btn btn-primary">Get Started</a>
                <?php endif; ?>
                <button class="mobile-menu-btn" type="button" aria-label="Toggle navigation" aria-expanded="false">☰</button>
            </div>
        </div>
    </nav>

    <!-- Event Hero -->
    <section class="event-hero">
        <img class="event-hero-bg" src="<?php echo htmlspecialchars(resolveImageUrl($event['image'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" alt="" aria-hidden="true" onerror="this.onerror=null;this.classList.add('fallback');">
        <div class="container event-hero-content">
            <span class="event-category"><?php echo sanitize($event['category']); ?></span>
            <h1><?php echo sanitize($event['title']); ?></h1>
            <div class="event-countdown event-countdown-banner" data-start-at="<?php echo htmlspecialchars(($event['date'] ?? '') . ' ' . ($event['time'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-event-title="<?php echo htmlspecialchars($event['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <span class="countdown-label">Starts in</span>
                <span class="countdown-value">--</span>
            </div>
            <div class="event-meta">
                <div class="event-meta-item">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                    <span><?php echo formatDate($event['date']); ?> at <?php echo formatTime($event['time']); ?></span>
                </div>
                <div class="event-meta-item">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                        <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                    <span><?php echo sanitize($event['venue']); ?><?php echo $event['address'] ? ', ' . sanitize($event['address']) : ''; ?></span>
                </div>
                <div class="event-meta-item">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                    <span><?php echo $event['capacity']; ?> capacity</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Event Details -->
    <section class="section">
        <div class="container">
            <div class="event-details-grid">
                <!-- Main Content -->
                <div class="event-description">
                    <h2>About This Event</h2>
                    <p><?php echo nl2br(sanitize($event['description'])); ?></p>
                    
                    <h2 style="margin-top: 48px;">What to Expect</h2>
                    <ul style="list-style: none; padding: 0;">
                        <?php
                        $expectations = !empty($event['expectations']) ? explode("\n", $event['expectations']) : [
                            'Instant booking confirmation',
                            'Unique QR code ticket for entry',
                            'Contactless check-in at venue',
                            'Digital ticket stored in My Tickets'
                        ];
                        foreach ($expectations as $expectation):
                            $expectation = trim($expectation);
                            if (!empty($expectation)):
                        ?>
                            <li style="padding: 12px 0; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 12px;">
                                <span style="color: var(--secondary); font-size: 18px;">✓</span>
                                <span><?php echo sanitize($expectation); ?></span>
                            </li>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    </ul>
                    
                    <!-- Event Location Map -->
                    <h2 style="margin-top: 48px;">Event Location</h2>
                    <div id="event-map" style="width: 100%; height: 400px; border-radius: var(--radius-lg); overflow: hidden; margin-bottom: 24px; position: relative; background: var(--bg-surface); border: 1px solid var(--border);">
                        <iframe id="map-frame" title="Event location map" width="100%" height="100%" style="border:0; border-radius: var(--radius-lg); display:block;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="<?php echo htmlspecialchars('https://www.google.com/maps?q=' . urlencode(trim(($event['venue'] ?? '') . ($event['address'] ? ', ' . $event['address'] : ''))) . '&output=embed&z=15'); ?>"></iframe>
                        <div id="map-fallback" style="display: none; position: absolute; inset: 0; align-items: center; justify-content: center; background: linear-gradient(135deg, rgba(79,70,229,0.08), rgba(236,72,153,0.08)); color: var(--text-secondary); padding: 24px; text-align: center;">
                            <div style="max-width: 360px;">
                                <p style="margin: 0 0 12px; font-weight: 600; color: var(--text-primary);">Open this location on Google Maps</p>
                                <p style="margin: 0 0 16px;">You can view the venue and get directions directly from Google Maps.</p>
                                <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                                    <a id="map-fallback-link" href="#" target="_blank" rel="noopener" class="btn btn-secondary">View on Google Maps</a>
                                    <a id="directions-fallback-link" href="#" target="_blank" rel="noopener" class="btn btn-primary">Get Directions</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <a id="directions-link" href="#" target="_blank" class="btn btn-secondary">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 11l19-9-9 19-2-8-8-2z"></path>
                        </svg>
                        Get Directions
                    </a>
                    
                    <!-- Comments Section -->
                    <div class="event-comments" style="margin-top: 48px; border-top: 1px solid var(--border); padding-top: 32px;">
                        <h2>Event Discussions</h2>
                        
                        <?php if (isLoggedIn()): ?>
                            <form id="commentForm" style="margin-top: 24px; margin-bottom: 32px;">
                                <input type="hidden" id="commentEventId" value="<?php echo $eventId; ?>">
                                <textarea id="commentText" class="form-control" rows="3" placeholder="Leave a comment about this event..." required style="width: 100%; padding: 12px; border-radius: var(--radius-sm); border: 1px solid var(--border); background: var(--bg-surface); color: var(--text-primary); margin-bottom: 12px; resize: vertical;"></textarea>
                                <button type="submit" class="btn btn-primary" id="submitCommentBtn">Post Comment</button>
                            </form>
                        <?php else: ?>
                            <div style="margin: 24px 0; padding: 16px; background: var(--bg-surface); border-radius: var(--radius-sm); border: 1px solid var(--border); text-align: center;">
                                <p>Please <a href="login.php" style="color: var(--primary);">log in</a> to join the conversation.</p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="comments-list" id="commentsList">
                            <?php if (empty($comments)): ?>
                                <p id="noCommentsMsg" style="color: var(--text-muted); font-style: italic;">No comments yet. Be the first to comment!</p>
                            <?php else: ?>
                                <?php foreach ($comments as $c): ?>
                                    <div class="comment-item" style="padding: 16px 0; border-bottom: 1px solid var(--border);">
                                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                            <strong style="color: var(--primary-light);"><?php echo sanitize($c['name']); ?></strong>
                                            <span style="font-size: 12px; color: var(--text-muted);"><?php echo formatDate($c['created_at']); ?></span>
                                        </div>
                                        <p style="margin: 0; line-height: 1.5;"><?php echo nl2br(sanitize($c['comment'])); ?></p>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Sidebar -->
                <div class="event-sidebar">
                    <?php if (isLoggedIn()): ?>
                    <div class="booking-card" style="margin-bottom: 16px;">
                        <h3>Message Attendees</h3>
                        <p style="color: var(--text-secondary); margin-bottom: 12px;">Chat with other people attending this event to plan meetups or ask questions.</p>
                        <a href="messages.php?event_id=<?php echo $event['id']; ?>" class="btn btn-secondary" style="width: 100%;">Open Messages Hub</a>
                    </div>
                    <?php endif; ?>
                    <div class="booking-card">
                        <h3>Book Tickets</h3>
                        
                        <div class="price-display">
                            <span>Price per ticket</span>
                            <span class="price-amount"><?php echo $event['price'] > 0 ? formatPrice($event['price']) : 'Free'; ?></span>
                        </div>
                        <?php if (isLoggedIn()): ?>
                        <button class="favorite-toggle favorite-toggle-large" data-event-id="<?php echo $event['id']; ?>" type="button">♡ Save to Favorites</button>
                        <?php endif; ?>
                        
                        <div class="quantity-selector">
                            <span>Number of tickets</span>
                            <div class="quantity-controls">
                                <button type="button" class="quantity-btn minus">−</button>
                                <input type="number" id="quantity" value="1" min="1" max="10" readonly>
                                <button type="button" class="quantity-btn plus">+</button>
                            </div>
                        </div>
                        
                        <div class="total-price">
                            <span>Total</span>
                            <span id="totalPrice" data-price="<?php echo $event['price']; ?>"><?php echo $event['price'] > 0 ? formatPrice($event['price']) : '$0.00'; ?></span>
                        </div>
                        
                        <input type="hidden" id="eventId" value="<?php echo $event['id']; ?>">
                        <input type="hidden" id="eventPrice" value="<?php echo $event['price']; ?>">
                        
                        <button type="button" class="btn btn-primary btn-lg book-event-btn" style="width: 100%; margin-top: 16px;">
                            Book Now
                        </button>
                        
                        <p style="text-align: center; font-size: 13px; color: var(--text-muted); margin-top: 16px;">
                            You'll receive a QR code ticket after booking
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
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
            <div class="chat-suggestions" id="chatSuggestions"></div>
            <div class="chat-input-area">
                <input type="text" placeholder="Ask me anything about this event...">
                <button class="chat-send">➤</button>
            </div>
        </div>
    </div>

    <script>
        window.currentUserName = <?php echo json_encode($_SESSION['user_name'] ?? null); ?>;
        window.eventAssistantContext = {
            type: 'event',
            title: <?php echo json_encode($event['title'] ?? ''); ?>,
            description: <?php echo json_encode(trim(strip_tags($event['description'] ?? ''))); ?>,
            expectations: <?php echo json_encode(trim(strip_tags($event['expectations'] ?? ''))); ?>,
            venue: <?php echo json_encode($event['venue'] ?? ''); ?>,
            address: <?php echo json_encode($event['address'] ?? ''); ?>,
            date: <?php echo json_encode($event['date'] ?? ''); ?>,
            time: <?php echo json_encode($event['time'] ?? ''); ?>,
            category: <?php echo json_encode($event['category'] ?? ''); ?>,
            price: <?php echo json_encode((float)($event['price'] ?? 0)); ?>,
            capacity: <?php echo json_encode((int)($event['capacity'] ?? 0)); ?>,
            organizer: <?php echo json_encode($event['organizer_name'] ?? 'Organizer'); ?>,
            comments: <?php echo json_encode(array_map(function ($comment) {
                return [
                    'name' => $comment['name'] ?? 'Guest',
                    'comment' => $comment['comment'] ?? ''
                ];
            }, $comments)); ?>,
            relatedEvents: <?php echo json_encode(array_map(function ($relatedEvent) {
                return [
                    'id' => (int)($relatedEvent['id'] ?? 0),
                    'title' => $relatedEvent['title'] ?? '',
                    'date' => $relatedEvent['date'] ?? '',
                    'venue' => $relatedEvent['venue'] ?? '',
                    'price' => (float)($relatedEvent['price'] ?? 0),
                    'category' => $relatedEvent['category'] ?? ''
                ];
            }, $relatedEvents)); ?>
        };
    </script>
    <script src="js/main.js?v=7"></script>
    <script>
        // Load event map on page load
        document.addEventListener('DOMContentLoaded', function() {
            const venue = <?php echo json_encode($event['venue'] ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
            const address = <?php echo json_encode($event['address'] ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
            const fullAddress = [venue, address].filter(Boolean).join(', ');
            
            // Encode address for URL
            const encodedAddress = encodeURIComponent(fullAddress);
            
            // Set map iframe using Google Maps
            const mapFrame = document.getElementById('map-frame');
            const mapFallback = document.getElementById('map-fallback');
            const mapFallbackLink = document.getElementById('map-fallback-link');
            const directionsFallbackLink = document.getElementById('directions-fallback-link');
            const directionsLink = document.getElementById('directions-link');
            const mapQuery = fullAddress || venue || 'Event location';
            const encodedQuery = encodeURIComponent(mapQuery);
            const googleMapUrl = 'https://www.google.com/maps?q=' + encodedQuery + '&output=embed&z=15';
            const googleSearchUrl = 'https://www.google.com/maps/search/?api=1&query=' + encodedQuery;
            const googleDirectionsUrl = 'https://www.google.com/maps/dir/?api=1&destination=' + encodedQuery;
            
            mapFrame.src = googleMapUrl;
            mapFallbackLink.href = googleSearchUrl;
            directionsFallbackLink.href = googleDirectionsUrl;
            directionsLink.href = googleDirectionsUrl;
            
            mapFrame.addEventListener('load', function() {
                mapFallback.style.display = 'none';
            });
            mapFrame.addEventListener('error', function() {
                mapFallback.style.display = 'flex';
            });
            setTimeout(function() {
                if (mapFrame.contentWindow && mapFrame.contentWindow.location && mapFrame.contentWindow.location.href.indexOf('about:blank') === -1) {
                    mapFallback.style.display = 'none';
                } else {
                    mapFallback.style.display = 'flex';
                }
            }, 1500);
            
            // Try to get coordinates and update map
            fetch('map_api.php?api=map&action=geocode&address=' + encodedAddress)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const encodedLocation = encodeURIComponent(data.display_name || (data.lat + ',' + data.lon));
                        mapFrame.src = 'https://www.google.com/maps?q=' + encodedLocation + '&output=embed&z=15';
                        mapFallbackLink.href = 'https://www.google.com/maps/search/?api=1&query=' + encodedLocation;
                        directionsFallbackLink.href = 'https://www.google.com/maps/dir/?api=1&destination=' + data.lat + ',' + data.lon;
                        directionsLink.href = 'https://www.google.com/maps/dir/?api=1&destination=' + data.lat + ',' + data.lon;
                    }
                })
                .catch(err => console.log('Map loading:', err));
                
            // Handle comments submission
            const commentForm = document.getElementById('commentForm');
            if (commentForm) {
                commentForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const btn = document.getElementById('submitCommentBtn');
                    const textEl = document.getElementById('commentText');
                    const eventId = document.getElementById('commentEventId').value;
                    const text = textEl.value.trim();
                    
                    if (!text) return;
                    
                    btn.disabled = true;
                    btn.textContent = 'Posting...';
                    
                    const formData = new URLSearchParams();
                    formData.append('event_id', eventId);
                    formData.append('comment', text);
                    
                    fetch('comment_api.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.location.reload(); // Quick reload to show new comment
                        } else {
                            Happiva.showToast(data.message, 'error');
                            btn.disabled = false;
                            btn.textContent = 'Post Comment';
                        }
                    })
                    .catch(() => {
                        Happiva.showToast('Error posting comment.', 'error');
                        btn.disabled = false;
                        btn.textContent = 'Post Comment';
                    });
                });
            }
        });
    </script>
</body>
</html>
