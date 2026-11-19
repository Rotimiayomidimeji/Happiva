# Happiva - Event Booking Platform Specification

## 1. Project Overview

**Project Name:** Happiva
**Type:** Web-based Event Booking Platform
**Core Functionality:** A comprehensive platform for browsing, booking, and paying for events online with QR code ticketing and AI assistance.
**Target Users:** Event attendees, event organizers, venue staff

---

## 2. UI/UX Specification

### Layout Structure

**Pages:**
1. Home Page - Hero section, featured events, categories, how it works
2. Events Listing - Filterable grid of events
3. Event Details - Full event info, booking form, AI chat
4. My Tickets - User's booked events with QR codes
5. Organizer Dashboard - Create/manage events, scan QR codes
6. Checkout - Payment processing
7. Success Page - Confirmation with ticket

**Responsive Breakpoints:**
- Mobile: < 768px
- Tablet: 768px - 1024px
- Desktop: > 1024px

### Visual Design

**Color Palette:**
- Primary: #6366F1 (Indigo)
- Primary Dark: #4F46E5
- Secondary: #10B981 (Emerald)
- Accent: #F59E0B (Amber)
- Background: #0F172A (Dark slate)
- Surface: #1E293B (Slate)
- Surface Light: #334155
- Text Primary: #F8FAFC
- Text Secondary: #94A3B8
- Error: #EF4444
- Success: #22C55E

**Typography:**
- Headings: 'Outfit', sans-serif (weights: 600, 700)
- Body: 'DM Sans', sans-serif (weights: 400, 500)
- Sizes: H1: 48px, H2: 36px, H3: 24px, Body: 16px, Small: 14px

**Spacing System:**
- Base unit: 4px
- Sections: 80px vertical padding
- Cards: 24px padding
- Elements: 16px gap

**Visual Effects:**
- Card shadows: 0 4px 24px rgba(0,0,0,0.3)
- Hover transitions: 0.3s ease
- Gradient overlays on hero images
- Glassmorphism on modals: backdrop-blur(12px)
- Subtle glow effects on primary buttons

### Components

**Navigation:**
- Fixed top navbar with logo, nav links, user menu
- Mobile hamburger menu
- Transparent on hero, solid on scroll

**Event Cards:**
- Image with gradient overlay
- Event title, date, location
- Price badge, category tag
- Hover: scale(1.02), enhanced shadow

**Booking Form:**
- Ticket quantity selector
- Date/time display
- Total price calculation
- Proceed to checkout button

**QR Code Ticket:**
- Unique QR code image
- Event details summary
- Ticket ID
- Download/print option

**AI Chat Assistant:**
- Floating chat button (bottom-right)
- Expandable chat window
- Message bubbles with typing indicator
- Quick question suggestions

**QR Scanner:**
- Camera viewfinder
- Scan result display
- Success/error feedback
- Attendee details on scan

---

## 3. Functionality Specification

### Core Features

**Event Browsing:**
- Featured events carousel
- Category filtering (Music, Sports, Tech, Art, Food, Business)
- Search by keyword
- Sort by date, price, popularity
- Pagination

**Event Booking:**
- View event details
- Select ticket quantity (1-10)
- View pricing breakdown
- Add to cart / direct book

**Payment Processing:**
- Simulated payment form (card details)
- Order confirmation
- Email notification (simulated)

**QR Code Ticketing:**
- Generate unique QR code per booking
- QR contains: ticket ID, event ID, user ID
- Download as image
- Print-friendly ticket view

**Check-in System:**
- Organizer login
- QR code scanner (camera access)
- Verify ticket validity
- Mark as checked-in
- Prevent duplicate check-in

**AI Chat Assistant:**
- Powered by Groq API (free tier)
- Context-aware responses about events
- Event recommendations
- Booking assistance
- FAQ responses

### User Interactions

**Guest User:**
- Browse events
- View event details
- Chat with AI
- Sign up/login to book

**Registered User:**
- All guest features
- Book events
- View/manage tickets
- Check-in status

**Organizer:**
- Create events
- Manage events
- Scan QR codes for check-in
- View attendee list

### Data Handling

**Database (MySQL):**
- users: id, name, email, password, role, created_at
- events: id, title, description, date, time, venue, price, capacity, category, image, organizer_id
- bookings: id, user_id, event_id, quantity, total_price, status, qr_code, checked_in, created_at
- checkin_logs: id, booking_id, scanned_at, scanned_by

**Session Management:**
- PHP sessions for user auth
- Cart stored in session

### Edge Cases

- Event fully booked → show "Sold Out"
- Past events → hide or show "Event Ended"
- Invalid QR code → show error
- Already checked-in → show warning
- Network error → retry with feedback

---

## 4. Acceptance Criteria

### Visual Checkpoints
- [ ] Dark theme with indigo accents applied consistently
- [ ] All pages responsive on mobile/tablet/desktop
- [ ] Smooth hover animations on interactive elements
- [ ] Professional typography hierarchy
- [ ] Glassmorphism effects on modals

### Functional Checkpoints
- [ ] Events display with images and details
- [ ] Category filter works correctly
- [ ] Search returns relevant results
- [ ] Booking flow completes successfully
- [ ] QR code generates and displays
- [ ] QR scanner validates tickets
- [ ] AI chat responds to queries
- [ ] User authentication works

### Performance
- [ ] Page load under 3 seconds
- [ ] Smooth animations (60fps)
- [ ] No console errors

---

## 5. Technical Stack

- **Backend:** PHP 8.x, MySQL
- **Frontend:** HTML5, CSS3, JavaScript (Vanilla)
- **QR Generation:** phpqrcode library
- **AI Chat:** Groq API integration
- **Server:** XAMPP (Apache + MySQL)