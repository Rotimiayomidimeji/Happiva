/**
 * Happiva - Event Booking Platform JavaScript
 */

// API Configuration
const API_CONFIG = {
    assistantEndpoint: 'ai_api.php'
};

// State
let cart = JSON.parse(sessionStorage.getItem('cart')) || [];
let currentEvent = null;
let chatMessages = [];

// Initialize
document.addEventListener('DOMContentLoaded', function () {
    initNavbar();
    initModals();
    initAuth();
    initCart();
    initMessageNotifications();
    initChat();
    initEventCountdowns();
    initEventStartNotifications();
    initFavoriteToggles();
    initQuantity();
    initSearch();
    initScanner();
    initScrollReveal();
    init3DTilt();
    initHeroParticles();
});

// Navbar
function initNavbar() {
    const navbar = document.querySelector('.navbar');
    if (!navbar) return;

    window.addEventListener('scroll', () => {
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });

    // Mobile menu
    const mobileBtn = document.querySelector('.mobile-menu-btn');
    const navLinks = document.querySelector('.nav-links');

    if (mobileBtn && navLinks) {
        mobileBtn.setAttribute('aria-expanded', 'false');

        const closeNavigation = () => {
            navLinks.classList.remove('active');
            mobileBtn.setAttribute('aria-expanded', 'false');
            mobileBtn.setAttribute('aria-label', 'Open navigation');
            mobileBtn.textContent = '☰';
        };

        mobileBtn.addEventListener('click', () => {
            const isExpanded = navLinks.classList.toggle('active');
            mobileBtn.setAttribute('aria-expanded', String(isExpanded));
            mobileBtn.setAttribute('aria-label', isExpanded ? 'Close navigation' : 'Open navigation');
            mobileBtn.textContent = isExpanded ? '×' : '☰';
        });

        navLinks.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', closeNavigation);
        });

        document.addEventListener('click', event => {
            if (!navbar.contains(event.target)) closeNavigation();
        });

        document.addEventListener('keydown', event => {
            if (event.key === 'Escape') closeNavigation();
        });
    }
}

function initMessageNotifications() {
    if (!document.body.classList.contains('logged-in')) {
        return;
    }

    const messagesLink = document.querySelector('.nav-links a[href="messages.php"]');
    if (!messagesLink) {
        return;
    }

    let badge = messagesLink.querySelector('.message-notification-badge');
    if (!badge) {
        badge = document.createElement('span');
        badge.className = 'message-notification-badge';
        badge.setAttribute('aria-label', 'Unread messages');
        messagesLink.appendChild(badge);
    }

    const updateBadge = () => {
        fetch('message_api.php?action=unread_count', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(response => response.ok ? response.json() : Promise.resolve({ success: false, count: 0 }))
            .then(result => {
                const count = result && result.success ? Number(result.count || 0) : 0;
                if (count > 0) {
                    badge.textContent = count > 99 ? '99+' : count;
                    badge.classList.add('visible');
                } else {
                    badge.textContent = '';
                    badge.classList.remove('visible');
                }
            })
            .catch(() => {
                badge.textContent = '';
                badge.classList.remove('visible');
            });
    };

    updateBadge();
    window.setInterval(updateBadge, 15000);
}

// Modals
function initModals() {
    document.querySelectorAll('[data-modal]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const modalId = btn.dataset.modal;
            openModal(modalId);
        });
    });

    // Modal close button and overlay click handling
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', (e) => {
            // Close modal when clicking on the overlay background (not the modal itself)
            if (e.target === overlay) {
                closeAllModals();
            }
        });
    });

    document.querySelectorAll('.modal-close').forEach(closeBtn => {
        closeBtn.addEventListener('click', (e) => {
            e.preventDefault();
            closeAllModals();
        });
    });
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        modal.style.display = 'none';
    }

    if (!document.querySelector('.modal-overlay.active')) {
        document.body.style.overflow = '';
    }
}

function closeAllModals() {
    document.querySelectorAll('.modal-overlay').forEach(modal => {
        modal.classList.remove('active');
        modal.style.display = 'none';
    });
    document.body.style.overflow = '';
}

// Authentication
function initAuth() {
    const authForms = document.querySelectorAll('.auth-form');
    const authTabs = document.querySelectorAll('.auth-tab');

    authTabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const formId = tab.dataset.form;

            authTabs.forEach(t => t.classList.remove('active'));
            authForms.forEach(f => f.classList.remove('active'));

            tab.classList.add('active');
            document.getElementById(formId).classList.add('active');
        });
    });

    // Login form
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', handleLogin);
    }

    // Register form
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', handleRegister);
    }
}

async function handleLogin(e) {
    e.preventDefault();

    const email = document.getElementById('loginEmail').value;
    const password = document.getElementById('loginPassword').value;
    const messageEl = document.getElementById('loginMessage');

    if (!email || !password) {
        showMessage(messageEl, 'Please fill in all fields', 'error');
        return;
    }

    try {
        const response = await fetch('auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=login&email=${encodeURIComponent(email)}&password=${encodeURIComponent(password)}`
        });

        const result = await response.json();

        if (result.success) {
            showMessage(messageEl, result.message, 'success');
            setTimeout(() => {
                closeAllModals();
                if (result.role === 'admin' || result.role === 'organizer') {
                    window.location.href = 'dashboard.php';
                } else {
                    window.location.reload();
                }
            }, 1000);
        } else {
            showMessage(messageEl, result.message, 'error');
        }
    } catch (error) {
        showMessage(messageEl, 'An error occurred. Please try again.', 'error');
    }
}

async function handleRegister(e) {
    e.preventDefault();

    const name = document.getElementById('registerName').value;
    const email = document.getElementById('registerEmail').value;
    const password = document.getElementById('registerPassword').value;
    const confirmPassword = document.getElementById('registerConfirmPassword').value;
    const roleEl = document.getElementById('registerRole');
    const role = roleEl ? roleEl.value : 'user';
    const messageEl = document.getElementById('registerMessage');

    if (!name || !email || !password || !confirmPassword) {
        showMessage(messageEl, 'Please fill in all fields', 'error');
        return;
    }

    if (password !== confirmPassword) {
        showMessage(messageEl, 'Passwords do not match', 'error');
        return;
    }

    if (password.length < 6) {
        showMessage(messageEl, 'Password must be at least 6 characters', 'error');
        return;
    }

    try {
        const response = await fetch('auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=register&name=${encodeURIComponent(name)}&email=${encodeURIComponent(email)}&password=${encodeURIComponent(password)}&role=${encodeURIComponent(role)}`
        });

        const result = await response.json();

        if (result.success) {
            showMessage(messageEl, result.message, 'success');
            setTimeout(() => {
                // Switch to login tab
                document.querySelector('[data-form="loginForm"]').click();
            }, 1500);
        } else {
            showMessage(messageEl, result.message, 'error');
        }
    } catch (error) {
        showMessage(messageEl, 'An error occurred. Please try again.', 'error');
    }
}

function showMessage(el, message, type) {
    if (!el) return;

    el.textContent = message;
    el.className = `form-${type}`;
    el.style.display = 'block';

    setTimeout(() => {
        el.style.display = 'none';
    }, 5000);
}

// Cart
function initCart() {
    updateCartCount();

    const bookBtn = document.querySelector('.book-event-btn');
    if (bookBtn) {
        bookBtn.addEventListener('click', handleBooking);
    }
}

function handleBooking() {
    if (!isLoggedIn()) {
        const eventId = document.getElementById('eventId')?.value;
        if (eventId) {
            window.location.href = `login.php?next=${encodeURIComponent(`event.php?id=${eventId}`)}`;
        }
        return;
    }

    const quantity = parseInt(document.getElementById('quantity')?.value || 1);
    const eventId = document.getElementById('eventId')?.value;
    const price = parseFloat(document.getElementById('eventPrice')?.value || 0);

    if (!eventId) {
        showToast('Event not found', 'error');
        return;
    }

    // Store in session and redirect to checkout
    // Use fetch to store in PHP session
    fetch('checkout_api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `event_id=${eventId}&quantity=${quantity}&price=${price}`
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = 'checkout.php';
            } else {
                showToast(data.message || 'Error processing booking', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Error processing booking', 'error');
        });
}

function updateCartCount() {
    const cartCount = document.querySelector('.cart-count');
    if (cartCount) {
        cartCount.textContent = cart.length;
    }
}

// Quantity Selector
function initQuantity() {
    const quantityInput = document.getElementById('quantity');
    const priceDisplay = document.getElementById('totalPrice');
    const unitPrice = parseFloat(priceDisplay?.dataset.price || 0);

    if (!quantityInput) return;

    const minusBtn = document.querySelector('.quantity-btn.minus');
    const plusBtn = document.querySelector('.quantity-btn.plus');

    if (minusBtn) {
        minusBtn.addEventListener('click', () => {
            let val = parseInt(quantityInput.value) || 1;
            if (val > 1) {
                quantityInput.value = val - 1;
                updateTotalPrice();
            }
        });
    }

    if (plusBtn) {
        plusBtn.addEventListener('click', () => {
            let val = parseInt(quantityInput.value) || 1;
            if (val < 10) {
                quantityInput.value = val + 1;
                updateTotalPrice();
            }
        });
    }

    quantityInput.addEventListener('change', updateTotalPrice);

    function updateTotalPrice() {
        const qty = parseInt(quantityInput.value) || 1;
        if (priceDisplay) {
            priceDisplay.textContent = '₦' + (unitPrice * qty).toFixed(2);
        }
    }
}

// Search & Filter
function initSearch() {
    const searchInput = document.getElementById('searchInput');
    const categoryBtns = document.querySelectorAll('.category-btn');

    if (searchInput) {
        searchInput.addEventListener('input', debounce(handleSearch, 500));
    }

    categoryBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const category = btn.dataset.category;

            categoryBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            filterEvents(category);
        });
    });
}

function handleSearch(e) {
    const query = e.target.value.toLowerCase();
    filterEvents(null, query);
}

function filterEvents(category, search = '') {
    const cards = document.querySelectorAll('.event-card');

    cards.forEach(card => {
        const title = card.querySelector('.event-title')?.textContent.toLowerCase() || '';
        const venue = card.querySelector('.event-venue')?.textContent.toLowerCase() || '';
        const cardCategory = card.dataset.category || '';

        const matchesCategory = !category || category === 'all' || cardCategory === category;
        const matchesSearch = !search || title.includes(search) || venue.includes(search);

        if (matchesCategory && matchesSearch) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function initEventCountdowns() {
    const countdownEls = document.querySelectorAll('.event-countdown');

    countdownEls.forEach((el) => {
        const startAt = el.getAttribute('data-start-at');
        if (!startAt) return;

        updateEventCountdown(el, startAt);
        setInterval(() => updateEventCountdown(el, startAt), 1000);
    });
}

function initFavoriteToggles() {
    document.querySelectorAll('.favorite-toggle').forEach((toggle) => {
        toggle.addEventListener('click', async (e) => {
            e.preventDefault();
            e.stopPropagation();

            const eventId = toggle.getAttribute('data-event-id');
            if (!eventId) return;

            try {
                const response = await fetch('profile.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `favorite_event_id=${encodeURIComponent(eventId)}`
                });

                const result = await response.json();
                if (result.success) {
                    toggle.textContent = result.is_favorite ? '♥' : '♡';
                    toggle.classList.toggle('active', result.is_favorite);
                    showToast(result.message, 'success');
                }
            } catch (error) {
                showToast('Unable to update favorites right now.', 'error');
            }
        });
    });

    document.querySelectorAll('.favorite-remove-btn').forEach((button) => {
        button.addEventListener('click', async (e) => {
            e.preventDefault();
            e.stopPropagation();

            const eventId = button.getAttribute('data-event-id');
            if (!eventId) return;

            try {
                const response = await fetch('profile.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `favorite_event_id=${encodeURIComponent(eventId)}`
                });

                const result = await response.json();
                if (result.success) {
                    button.closest('.favorite-item').remove();
                    showToast(result.message, 'success');
                }
            } catch (error) {
                showToast('Unable to remove favorite right now.', 'error');
            }
        });
    });
}

function initEventStartNotifications() {
    const countdownEls = document.querySelectorAll('.event-countdown');

    countdownEls.forEach((el) => {
        const startAt = el.getAttribute('data-start-at');
        const eventTitle = el.getAttribute('data-event-title') || 'This event';
        if (!startAt) return;

        const startDate = new Date(startAt.replace(' ', 'T'));
        if (Number.isNaN(startDate.getTime())) return;

        const now = new Date();
        const diff = startDate.getTime() - now.getTime();

        if (diff <= 0) {
            showEventStartToast(eventTitle);
            return;
        }

        const threshold = 15 * 60 * 1000;
        if (diff <= threshold) {
            showEventStartToast(eventTitle);
        }
    });
}

function showEventStartToast(eventTitle) {
    const alreadyShown = sessionStorage.getItem('eventStartToastShown');
    if (alreadyShown) return;

    sessionStorage.setItem('eventStartToastShown', '1');
    showToast(`${eventTitle} is starting soon. Don’t miss it!`, 'success');
}

function updateEventCountdown(el, startAt) {
    const valueEl = el.querySelector('.countdown-value') || el;
    const startDate = new Date(startAt.replace(' ', 'T'));

    if (Number.isNaN(startDate.getTime())) {
        valueEl.textContent = 'Schedule pending';
        el.classList.remove('urgent', 'live');
        return;
    }

    const now = new Date();
    const diff = startDate.getTime() - now.getTime();

    if (diff <= 0) {
        valueEl.textContent = 'Live now';
        el.classList.remove('urgent');
        el.classList.add('live');
        return;
    }

    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
    const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    const seconds = Math.floor((diff % (1000 * 60)) / 1000);

    let countdownText = '';

    if (days > 0) {
        countdownText = `${days}d ${hours}h ${minutes}m`;
    } else if (hours > 0) {
        countdownText = `${hours}h ${minutes}m ${seconds}s`;
    } else if (minutes > 0) {
        countdownText = `${minutes}m ${seconds}s`;
    } else {
        countdownText = `${seconds}s`;
    }

    valueEl.textContent = countdownText;
    el.classList.remove('live');
    if (days === 0 && hours < 24) {
        el.classList.add('urgent');
    } else {
        el.classList.remove('urgent');
    }
}

// AI Chat
function initChat() {
    const chatToggle = document.querySelector('.chat-toggle');
    const chatWindow = document.querySelector('.chat-window');
    const chatInput = document.querySelector('.chat-input-area input');
    const chatSend = document.querySelector('.chat-send');
    const chatSuggestions = document.getElementById('chatSuggestions');

    if (!chatToggle || !chatWindow) return;

    chatToggle.addEventListener('click', () => {
        chatWindow.classList.toggle('active');
    });

    if (chatSend) {
        chatSend.addEventListener('click', sendMessage);
    }

    if (chatInput) {
        chatInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                sendMessage();
            }
        });
    }

    if (chatSuggestions) {
        const suggestions = getSuggestedQuestions();
        chatSuggestions.innerHTML = suggestions.map(question => `<button class="chat-suggestion" type="button">${escapeHtml(question)}</button>`).join('');

        chatSuggestions.querySelectorAll('.chat-suggestion').forEach(button => {
            button.addEventListener('click', () => {
                if (chatInput) {
                    chatInput.value = button.textContent;
                    chatInput.focus();
                    sendMessage();
                }
            });
        });
    }

    // Add welcome message
    setTimeout(() => {
        addBotMessage(getWelcomeMessage());
    }, 1000);
}

function getSuggestedQuestions() {
    const context = getAssistantContext();
    const baseSuggestions = [
        'How do I book a ticket?',
        'What is a QR ticket?',
        'Where is this event?',
        'How much is the ticket?'
    ];

    if (context.event) {
        return [
            `When is ${context.event.title}?`,
            'Where is the venue?',
            'Who is hosting this event?',
            'How do I get directions?'
        ];
    }

    return baseSuggestions;
}

function getWelcomeMessage() {
    const context = getAssistantContext();
    const userName = context.userName ? String(context.userName).trim() : '';
    const displayName = userName ? userName.split(' ')[0] : '';

    if (context.event) {
        if (displayName) {
            return `Hey ${displayName}! I’m EventSmart’s assistant here for this event 😊 I can help with the venue, schedule, tickets, and related events. What would you like to know?`;
        }

        return `Hey! I’m EventSmart’s assistant here for this event 😊 I can help with the venue, schedule, tickets, and related events. What would you like to know?`;
    }

    if (displayName) {
        return `Hey ${displayName}! 👋 I’m EventSmart’s assistant. I can help you discover events, book tickets, and answer quick questions. What would you like to do today?`;
    }

    return "Hey there! 👋 I’m EventSmart’s assistant. I can help you discover events, book tickets, and answer quick questions. What would you like to do today?";
}

async function sendMessage() {
    const chatInput = document.querySelector('.chat-input-area input');
    const messagesContainer = document.querySelector('.chat-messages');

    if (!chatInput || !chatInput.value.trim()) return;

    const userMessage = chatInput.value.trim();
    chatInput.value = '';

    chatMessages.push({ role: 'user', content: userMessage });

    // Add user message
    addUserMessage(userMessage);

    // Show typing indicator
    showTyping();

    try {
        const response = await getAIResponse(userMessage);
        hideTyping();
        addBotMessage(response);
        chatMessages.push({ role: 'assistant', content: response });
    } catch (error) {
        hideTyping();
        addBotMessage("I apologize, but I'm having trouble connecting right now. Please try again later or browse our events directly.");
    }
}

function addUserMessage(message) {
    const messagesContainer = document.querySelector('.chat-messages');
    if (!messagesContainer) return;

    const messageEl = document.createElement('div');
    messageEl.className = 'chat-message user';
    messageEl.innerHTML = `<div class="message-content">${escapeHtml(message)}</div>`;

    messagesContainer.appendChild(messageEl);
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
}

function addBotMessage(message) {
    const messagesContainer = document.querySelector('.chat-messages');
    if (!messagesContainer) return;

    const messageEl = document.createElement('div');
    messageEl.className = 'chat-message bot';
    messageEl.innerHTML = `<div class="message-content">${message}</div>`;

    messagesContainer.appendChild(messageEl);
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
}

function showTyping() {
    const messagesContainer = document.querySelector('.chat-messages');
    if (!messagesContainer) return;

    const typingEl = document.createElement('div');
    typingEl.className = 'typing-indicator';
    typingEl.id = 'typingIndicator';
    typingEl.innerHTML = '<span></span><span></span><span></span>';

    messagesContainer.appendChild(typingEl);
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
}

function hideTyping() {
    const typingEl = document.getElementById('typingIndicator');
    if (typingEl) {
        typingEl.remove();
    }
}

function getAssistantContext() {
    const context = {
        siteName: 'EventSmart',
        features: [
            'browse and discover events',
            'book tickets securely',
            'receive unique QR-code tickets',
            'use contactless venue check-in',
            'view event details, venue, date, and pricing'
        ]
    };

    if (window.currentUserName) {
        context.userName = window.currentUserName;
    }

    if (window.eventAssistantContext && window.eventAssistantContext.type === 'event') {
        context.event = window.eventAssistantContext;
    }

    return context;
}

function normalizeMessage(text) {
    return (text || '')
        .toLowerCase()
        .trim()
        .replace(/[^\w\s]/g, ' ')
        .replace(/\s+/g, ' ');
}

function getDirectionsUrl(assistantContext) {
    const event = assistantContext && assistantContext.event;
    if (!event) {
        return null;
    }

    const destinationParts = [event.venue, event.address, event.title].filter(Boolean);
    const destination = destinationParts.join(', ').trim();

    if (!destination) {
        return null;
    }

    return `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(destination)}`;
}

function getDirectionsResponse(assistantContext) {
    const directionsUrl = getDirectionsUrl(assistantContext);

    if (!directionsUrl) {
        return 'I can help you find the venue. Please check the event page for the address and directions.';
    }

    const event = assistantContext && assistantContext.event;
    const locationText = [event && event.venue, event && event.address].filter(Boolean).join(', ');
    const label = locationText ? `to ${locationText}` : 'to this venue';

    return `You can open Google Maps ${label}. <a href="${directionsUrl}" target="_blank" rel="noopener" style="display:inline-block;margin-top:8px;padding:8px 12px;background:#4f46e5;color:#fff;border-radius:999px;text-decoration:none;">Open directions</a>`;
}

function getFAQResponse(message, assistantContext) {
    const normalized = normalizeMessage(message);
    const faqEntries = [
        {
            patterns: ['how do i book', 'how can i book', 'book ticket', 'buy ticket', 'how to buy', 'how to book'],
            response: 'Easy peasy 😊 just open an event, choose how many tickets you want, and complete checkout. Once payment is confirmed, you will get your QR ticket.'
        },
        {
            patterns: ['how do i pay', 'payment', 'pay for ticket', 'checkout'],
            response: 'You can pay securely right on the site during checkout. Once the payment is successful, your booking is confirmed and your ticket is ready.'
        },
        {
            patterns: ['qr code', 'qr ticket', 'check in', 'entry', 'how do i enter'],
            response: 'After booking, you will receive a unique QR code ticket. Just show it at the venue and the organizer can scan it for a smooth check-in.'
        },
        {
            patterns: ['how do i find events', 'browse events', 'what events do you have'],
            response: 'You can browse the events page by category or search for the type of event you want. I can also help you narrow it down quickly.'
        },
        {
            patterns: ['how do i get directions', 'get directions', 'directions', 'show me directions', 'where is it', 'where is the venue'],
            response: null
        },
        {
            patterns: ['create profile', 'make profile', 'how can i create profile', 'update profile', 'profile page'],
            response: 'To create or update your profile, open the Profile page from the menu, add your details or photo, and save the changes.'
        },
        {
            patterns: ['talk to attendee', 'talk to attendees', 'message attendee', 'message attendees', 'chat with attendee', 'chat with attendees', 'tlk to attendee', 'tlk to attendees', 'contact attendee', 'contact attendees'],
            response: 'To talk to attendees, go to Messages, choose the event, and open the attendee you want to chat with. If no one appears yet, there may not be other attendees for that event yet.'
        },
        {
            patterns: ['cancel', 'refund', 'refund policy'],
            response: 'Refund and cancellation rules depend on the event and organizer. If you are unsure, check the event page or reach out to support for the latest policy.'
        },
        {
            patterns: ['support', 'contact', 'help me'],
            response: 'I am happy to help here 😊 If you need extra support, you can also check the event details page or contact the site support team.'
        },
        {
            patterns: ['what is eventsmart', 'about eventsmart', 'what is this site'],
            response: 'EventSmart is a simple place to discover events, book tickets, and enjoy QR-based entry without the usual stress.'
        }
    ];

    return faqEntries.find(entry => entry.patterns.some(pattern => normalized.includes(pattern)))?.response || null;
}

function getEventSpecificResponse(message, assistantContext) {
    const normalized = normalizeMessage(message);
    const event = assistantContext.event;

    if (!event) {
        return null;
    }

    if (normalized.includes('where is') || normalized.includes('venue') || normalized.includes('location') || normalized.includes('address')) {
        return `This event is happening at ${event.venue || 'the listed venue'}${event.address ? `, ${event.address}` : ''}.`;
    }

    if (normalized.includes('what time') || normalized.includes('when') || normalized.includes('date') || normalized.includes('time')) {
        return `It is scheduled for ${event.date || 'the listed date'}${event.time ? ` at ${event.time}` : ''}.`;
    }

    if (normalized.includes('is it free') || normalized.includes('how much') || normalized.includes('price') || normalized.includes('cost') || normalized.includes('ticket price')) {
        if (Number(event.price) > 0) {
            return `This event costs ${event.price ? `₦${Number(event.price).toLocaleString()}` : 'a listed price'} for a ticket.`;
        }
        return 'This event is free to attend 🎉';
    }

    if (normalized.includes('how many') && (normalized.includes('tickets') || normalized.includes('spots') || normalized.includes('seats') || normalized.includes('capacity'))) {
        return `This event has ${event.capacity ? `${event.capacity} spots` : 'a set capacity'} available.`;
    }

    if (normalized.includes('who is hosting') || normalized.includes('organizer') || normalized.includes('host')) {
        return `This event is hosted by ${event.organizer || 'the organizer listed on the event page'}.`;
    }

    if (normalized.includes('what to expect') || normalized.includes('expectations') || normalized.includes('what happens')) {
        return event.expectations ? `Here is what to expect: ${event.expectations}` : 'You can expect a smooth, enjoyable experience with booking confirmation, QR entry, and a great event atmosphere.';
    }

    if (normalized.includes('about this event') || normalized.includes('description') || normalized.includes('what is this event about')) {
        return event.description ? `Here is the vibe: ${event.description}` : 'It is designed to give attendees a memorable experience.';
    }

    if (normalized.includes('similar') || normalized.includes('related') || normalized.includes('other events')) {
        const related = (event.relatedEvents || []).map(item => item.title).join(', ');
        return related ? `You might also enjoy: ${related}.` : 'I can help you browse the event list for similar options.';
    }

    if (normalized.includes('comment') || normalized.includes('people') || normalized.includes('feedback')) {
        const comments = (event.comments || []).slice(0, 2).map(comment => `"${comment.comment}"`).join(' | ');
        return comments ? `People have been saying: ${comments}` : 'There are no comments yet for this event.';
    }

    return null;
}

function getLocalAssistantResponse(message, assistantContext) {
    const lowerMessage = normalizeMessage(message);
    const event = assistantContext.event;

    if (!lowerMessage) {
        return 'Hey! I’m here and ready to help 😊 What would you like to know?';
    }

    const faqResponse = getFAQResponse(message, assistantContext);
    if (faqResponse) {
        return faqResponse;
    }

    if (lowerMessage.includes('direction') || lowerMessage.includes('map') || lowerMessage.includes('venue')) {
        return getDirectionsResponse(assistantContext);
    }

    if (lowerMessage.includes('direction') || lowerMessage.includes('map') || lowerMessage.includes('venue')) {
        return getDirectionsResponse(assistantContext);
    }

    const eventResponse = getEventSpecificResponse(message, assistantContext);
    if (eventResponse) {
        return eventResponse;
    }

    if (lowerMessage === 'hi' || lowerMessage === 'hello' || lowerMessage === 'hey' || lowerMessage === 'hey there' || lowerMessage === 'hi there' || lowerMessage === 'hello there' || lowerMessage === 'yo' || lowerMessage === 'hii' || lowerMessage === 'hiii' || lowerMessage.includes('good morning') || lowerMessage.includes('good afternoon') || lowerMessage.includes('good evening') || lowerMessage.includes('whats up') || lowerMessage.includes('sup')) {
        return "Hey there! 👋 I’m here and ready to help. Want to browse events, book a ticket, or get details about something specific?";
    }

    if (lowerMessage.includes('how are you') || lowerMessage.includes('how are you doing')) {
        return "I’m doing great, thanks for asking 😊 I’m here to help with events, tickets, and anything else on EventSmart.";
    }

    if (lowerMessage.includes('thank') || lowerMessage.includes('thanks')) {
        return 'You’re very welcome! I’m happy to help 😊';
    }

    if (lowerMessage.includes('bye') || lowerMessage.includes('goodbye') || lowerMessage.includes('see you')) {
        return 'No problem at all — see you around! Come back anytime if you need help with EventSmart.';
    }

    if (event) {
        if (lowerMessage.includes('book') || lowerMessage.includes('buy') || lowerMessage.includes('ticket')) {
            return 'To book tickets, just open the event, choose how many you want, and complete checkout. Once it is confirmed, you will get a QR-code ticket for entry.';
        }
    }

    if (lowerMessage.includes('book') || lowerMessage.includes('buy') || lowerMessage.includes('ticket')) {
        return 'To book tickets, just browse the events, open the one you like, choose how many you need, and complete the payment. Once it’s confirmed, you’ll get a QR-code ticket for entry.';
    }

    if (lowerMessage.includes('qr') || lowerMessage.includes('check')) {
        return 'After booking, you’ll receive a unique QR code for your ticket. Just show it at the venue and the organizer can scan it for a smooth check-in.';
    }

    if (lowerMessage.includes('event') || lowerMessage.includes('what')) {
        return 'EventSmart makes it easy to discover events, book tickets, and enjoy quick QR-based entry. You can browse by category and find details like date, venue, price, and capacity.';
    }

    if (lowerMessage.includes('help') || lowerMessage.includes('how can you help') || lowerMessage.includes('what can you do')) {
        return 'Absolutely! I can help you find great events, book tickets, explain QR entry, and answer details about a specific event 🎉';
    }

    return 'Absolutely — I’m here to help with EventSmart events, bookings, QR tickets, and event details. Feel free to ask me anything about the site or a specific event 😊';
}

async function getAIResponse(userMessage) {
    const assistantContext = getAssistantContext();
    const localResponse = getLocalAssistantResponse(userMessage, assistantContext);

    if (!navigator.onLine) {
        return localResponse;
    }

    try {
        const response = await fetch(API_CONFIG.assistantEndpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                message: userMessage,
                history: chatMessages.slice(-9, -1),
                context: assistantContext
            })
        });

        if (!response.ok) {
            throw new Error('API request failed');
        }

        const data = await response.json();
        return data.success && data.reply ? data.reply : localResponse;
    } catch (error) {
        return localResponse;
    }
}

function getFallbackResponse(message) {
    const context = getAssistantContext();
    return getLocalAssistantResponse(message, context);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// QR Scanner
function initScanner() {
    const startScanBtn = document.getElementById('startScan');
    const video = document.getElementById('scannerVideo');
    const canvas = document.getElementById('scannerCanvas');

    if (!startScanBtn || !video) return;

    startScanBtn.addEventListener('click', async () => {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'environment' }
            });

            video.srcObject = stream;
            video.style.display = 'block';
            startScanBtn.textContent = 'Scanning...';

            // Simulate scanning
            setTimeout(() => {
                simulateScan();
            }, 3000);
        } catch (error) {
            showToast('Camera access denied. Please enable camera permissions.', 'error');
        }
    });
}

function simulateScan() {
    const resultEl = document.getElementById('scanResult');
    if (!resultEl) return;

    // Simulate successful scan
    resultEl.className = 'scanner-result success';
    resultEl.innerHTML = `
        <h4 style="color: var(--success);">✓ Check-in Successful</h4>
        <p><strong>Ticket ID:</strong> TKT-ABC123XYZ</p>
        <p><strong>Attendee:</strong> John Doe</p>
        <p><strong>Event:</strong> Tech Innovation Summit 2026</p>
    `;
    resultEl.style.display = 'block';
}

// Toast Notifications
function showToast(message, type = 'info') {
    let container = document.querySelector('.toast-container');

    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `
        <span>${type === 'success' ? '✓' : type === 'error' ? '✕' : 'ℹ'}</span>
        <span>${message}</span>
    `;

    container.appendChild(toast);

    setTimeout(() => {
        toast.remove();
    }, 5000);
}

// Utility Functions
function isLoggedIn() {
    return document.body.classList.contains('logged-in');
}

// Format currency
function formatCurrency(amount) {
    return '₦' + parseFloat(amount).toFixed(2);
}

// Format date
function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', {
        month: 'long',
        day: 'numeric',
        year: 'numeric'
    });
}

// Format time
function formatTime(timeStr) {
    const [hours, minutes] = timeStr.split(':');
    const hour = parseInt(hours);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour % 12 || 12;
    return `${displayHour}:${minutes} ${ampm}`;
}

// Export functions for PHP usage
window.EventSmart = {
    showToast,
    formatCurrency,
    formatDate,
    formatTime,
    openModal,
    closeAllModals
};
window.Happiva = window.EventSmart;

// ----------------------------------------------------
// Modern Animation Enhancements
// ----------------------------------------------------

// 1. Scroll Reveal Animation
function initScrollReveal() {
    const revealElements = document.querySelectorAll('.reveal-up');

    if ('IntersectionObserver' in window) {
        const observerOptions = {
            root: null,
            rootMargin: '0px',
            threshold: 0.1
        };

        const observer = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('active');
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);

        revealElements.forEach(el => observer.observe(el));
    } else {
        // Fallback for older browsers
        revealElements.forEach(el => el.classList.add('active'));
    }
}

// 2. 3D Tilt Effect on Cards
function init3DTilt() {
    const tiltCards = document.querySelectorAll('.tilt-card');

    tiltCards.forEach(card => {
        card.addEventListener('mousemove', e => {
            const rect = card.getBoundingClientRect();
            const x = e.clientX - rect.left; // x position within the element
            const y = e.clientY - rect.top;  // y position within the element

            const centerX = rect.width / 2;
            const centerY = rect.height / 2;

            const rotateX = ((y - centerY) / centerY) * -10; // Max rotation 10deg
            const rotateY = ((x - centerX) / centerX) * 10;

            card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale3d(1.02, 1.02, 1.02)`;
        });

        card.addEventListener('mouseleave', () => {
            card.style.transform = 'perspective(1000px) rotateX(0deg) rotateY(0deg) scale3d(1, 1, 1)';
        });
    });
}

// 3. Hero Particle Elements
function initHeroParticles() {
    const particlesContainer = document.getElementById('particles');
    if (!particlesContainer) return;

    const particleCount = 10;

    for (let i = 0; i < particleCount; i++) {
        const particle = document.createElement('div');
        particle.className = 'glowing-shape';

        // Randomize size, position and animation delay
        const size = Math.random() * 150 + 50;
        const x = Math.random() * 100; // %
        const y = Math.random() * 100; // %
        const delay = Math.random() * 5; // s
        const duration = Math.random() * 20 + 10; // s

        particle.style.width = `${size}px`;
        particle.style.height = `${size}px`;
        particle.style.left = `${x}%`;
        particle.style.top = `${y}%`;
        particle.style.animationDelay = `${delay}s`;
        particle.style.animationDuration = `${duration}s`;

        particlesContainer.appendChild(particle);
    }

    // Slight parallax effect on mouse movement
    const heroSection = document.getElementById('hero-section');
    if (heroSection) {
        heroSection.addEventListener('mousemove', (e) => {
            const shapes = document.querySelectorAll('.glowing-shape');
            const x = e.clientX / window.innerWidth - 0.5;
            const y = e.clientY / window.innerHeight - 0.5;

            shapes.forEach((shape, index) => {
                const speed = (index + 1) * 20;
                shape.style.transform = `translate(${x * speed}px, ${y * speed}px)`;
            });
        });
    }
}
