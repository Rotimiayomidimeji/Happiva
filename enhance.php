<?php
$css = <<<CSS

/* PREMIUM AESTHETICS ENHANCEMENTS */
.navbar {
    background: rgba(15, 23, 42, 0.75) !important;
    backdrop-filter: blur(16px) !important;
    -webkit-backdrop-filter: blur(16px) !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
}

.hero-section {
    background: radial-gradient(circle at 50% -20%, rgba(99, 102, 241, 0.25) 0%, rgba(15, 23, 42, 1) 80%) !important;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), #818CF8) !important;
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4) !important;
    transition: all 0.3s ease !important;
}
.btn-primary:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 8px 25px rgba(99, 102, 241, 0.6) !important;
}

.event-card {
    background: rgba(30, 41, 59, 0.6) !important;
    backdrop-filter: blur(10px) !important;
    border: 1px solid rgba(255, 255, 255, 0.05) !important;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) !important;
}
.event-card:hover {
    transform: translateY(-10px) scale(1.02) !important;
    border-color: rgba(99, 102, 241, 0.5) !important;
    box-shadow: 0 15px 30px rgba(0,0,0,0.5), 0 0 15px rgba(99, 102, 241, 0.25) !important;
    z-index: 2;
}

.auth-modal .modal-content, .checkout-container, .event-details-content {
    background: rgba(30, 41, 59, 0.85) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5) !important;
}

.brand-h {
    background: linear-gradient(to right, #818CF8, #C084FC) !important;
    -webkit-background-clip: text !important;
    -webkit-text-fill-color: transparent !important;
    animation: gradientShift 3s ease infinite alternate;
}

.glass-box {
    background: rgba(255, 255, 255, 0.03) !important;
    border: 1px solid rgba(255, 255, 255, 0.05) !important;
    backdrop-filter: blur(10px) !important;
    border-radius: var(--radius-md) !important;
}

@keyframes gradientShift {
    0% { filter: hue-rotate(0deg); }
    100% { filter: hue-rotate(60deg); }
}

CSS;

$file = 'css/style.css';
if (file_exists($file)) {
    file_put_contents($file, $css, FILE_APPEND);
    echo "CSS Aesthetics Injected!\n";
} else {
    echo "CSS file not found!\n";
}
?>
