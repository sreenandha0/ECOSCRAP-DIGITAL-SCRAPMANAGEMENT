<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EcoScrap - Smart Scrap Management System</title>
    
    <!-- Premium Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Phosphor Icons -->
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <!-- 1. Navbar -->
    <nav class="navbar" id="navbar">
        <div class="nav-container">
            <a href="#" class="logo">
                <div class="logo-mark"></div>
                EcoScrap
            </a>
            <div class="nav-links">
                <a href="#problem">Problem</a>
                <a href="#how-it-works">How it works</a>
                <a href="#features">Features</a>
                <a href="#impact">Impact</a>
            </div>
            <div class="nav-actions">
                <a href="login.php" class="btn-ghost magnetic-btn">Log in</a>
                <a href="#" class="btn-primary magnetic-btn">Get Started</a>
            </div>
        </div>
    </nav>

    <!-- 2. Hero Section -->
    <section class="hero" id="hero">
        <div class="hero-particles" id="particles"></div>
        <div class="container hero-container">
            <div class="hero-badge gs-reveal-up">
                <span class="pulse-dot"></span>
                Reimagining Waste Management
            </div>
            <h1 class="hero-title gs-reveal-up">
                Transform Scrap Into A <span class="gradient-text">Smarter Future</span>
            </h1>
            <p class="hero-subtitle gs-reveal-up">
                EcoScrap connects users, scrap collectors, and recycling networks through intelligent waste management. The operating system for a sustainable planet.
            </p>
            <div class="hero-cta gs-reveal-up">
                <a href="#" class="btn-primary btn-lg magnetic-btn">Request Pickup</a>
                <a href="#" class="btn-secondary btn-lg magnetic-btn">Become Collector</a>
            </div>
            
            <div class="hero-dashboard-preview gs-reveal-up parallax-hero">
                <!-- Using the generated dashboard mockup -->
                <div class="dashboard-frame glass-panel">
                    <img src="C:/Users/Sreenandha M S/.gemini/antigravity-ide/brain/06377ff9-ee4a-4faf-a741-8b96941ac33f/ecoscrap_dashboard_mockup_1784821266052.png" alt="EcoScrap Dashboard" class="dashboard-img">
                    
                    <!-- Floating UI elements -->
                    <div class="floating-element float-1 glass-card">
                        <i class="ph-fill ph-check-circle" style="color: var(--primary);"></i>
                        <div>
                            <strong>Pickup Confirmed</strong>
                            <span>25kg E-Waste</span>
                        </div>
                    </div>
                    <div class="floating-element float-2 glass-card">
                        <i class="ph-fill ph-leaf" style="color: var(--primary);"></i>
                        <div>
                            <strong>CO₂ Saved</strong>
                            <span>142 kg</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 3. Problem Section -->
    <section class="problem section-padding" id="problem">
        <div class="container">
            <div class="section-header text-center gs-fade-in">
                <h2 class="section-title">The Broken System</h2>
                <p class="section-desc">Traditional scrap management is fragmented, inefficient, and heavily reliant on manual processes.</p>
            </div>
            
            <div class="problem-grid">
                <div class="problem-card glass-card gs-stagger-up">
                    <div class="problem-icon"><i class="ph ph-trash"></i></div>
                    <h3>Unorganized Sector</h3>
                    <p>85% of scrap collection relies on unverified, informal networks with no standardized pricing.</p>
                </div>
                <div class="problem-card glass-card gs-stagger-up">
                    <div class="problem-icon"><i class="ph ph-chart-line-down"></i></div>
                    <h3>Low Recycling Rates</h3>
                    <p>Only 20% of global e-waste is formally recycled due to high friction in user participation.</p>
                </div>
                <div class="problem-card glass-card gs-stagger-up">
                    <div class="problem-icon"><i class="ph ph-shield-warning"></i></div>
                    <h3>Lack of Trust</h3>
                    <p>Users struggle to find reliable collectors, and collectors lack optimized routing and verification.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- 4. How EcoScrap Works -->
    <section class="how-it-works section-padding" id="how-it-works">
        <div class="container">
            <div class="section-header gs-fade-in">
                <h2 class="section-title">How EcoScrap Works</h2>
                <p class="section-desc">A frictionless flow from disposal to recycling.</p>
            </div>
            
            <div class="timeline-container">
                <div class="timeline-line"></div>
                
                <div class="timeline-item gs-timeline-stagger">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content glass-card">
                        <span class="step-num">Step 1</span>
                        <h3>Register & Request</h3>
                        <p>User logs in and creates a pickup request with estimated scrap details and location.</p>
                    </div>
                </div>
                
                <div class="timeline-item gs-timeline-stagger">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content glass-card">
                        <span class="step-num">Step 2</span>
                        <h3>Collector Assignment</h3>
                        <p>The system intelligently routes and assigns the nearest verified collector to the request.</p>
                    </div>
                </div>
                
                <div class="timeline-item gs-timeline-stagger">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content glass-card">
                        <span class="step-num">Step 3</span>
                        <h3>QR Verification</h3>
                        <p>Collector arrives, scans the user's unique QR code to securely verify the transaction.</p>
                    </div>
                </div>
                
                <div class="timeline-item gs-timeline-stagger">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content glass-card">
                        <span class="step-num">Step 4</span>
                        <h3>Recycling & Rewards</h3>
                        <p>Scrap is transported to certified recycling networks. User earns EcoPoints and views impact.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 5. Features Section (Bento Grid) -->
    <section class="features section-padding" id="features">
        <div class="container">
            <div class="section-header text-center gs-fade-in">
                <h2 class="section-title">Engineered for Scale</h2>
                <p class="section-desc">Powerful features wrapped in a minimal, intuitive interface.</p>
            </div>
            
            <div class="bento-grid">
                <div class="bento-card bento-large glass-card gs-bento-reveal mouse-glow">
                    <div class="bento-content">
                        <i class="ph ph-map-pin-line bento-icon"></i>
                        <h3>Smart Pickup Management</h3>
                        <p>Algorithmic routing ensures collectors travel the shortest distance, saving fuel and time.</p>
                    </div>
                    <div class="bento-visual">
                        <!-- Abstract map visual -->
                        <div class="abstract-map">
                            <div class="route-line"></div>
                            <div class="map-node node-1"></div>
                            <div class="map-node node-2"></div>
                        </div>
                    </div>
                </div>
                
                <div class="bento-card glass-card gs-bento-reveal mouse-glow">
                    <i class="ph ph-users bento-icon"></i>
                    <h3>Collector Network</h3>
                    <p>Verified, trusted professionals with background checks.</p>
                </div>
                
                <div class="bento-card glass-card gs-bento-reveal mouse-glow">
                    <i class="ph ph-qr-code bento-icon"></i>
                    <h3>QR Verification</h3>
                    <p>Bank-grade secure handoffs using dynamic QR codes.</p>
                </div>
                
                <div class="bento-card glass-card gs-bento-reveal mouse-glow">
                    <i class="ph ph-clock-counter-clockwise bento-icon"></i>
                    <h3>Real-Time Tracking</h3>
                    <p>Live GPS updates from request to completion.</p>
                </div>
                
                <div class="bento-card glass-card gs-bento-reveal mouse-glow">
                    <i class="ph ph-chart-pie-slice bento-icon"></i>
                    <h3>Recycling Analytics</h3>
                    <p>Granular data on waste categories and volumes.</p>
                </div>
                
                <div class="bento-card bento-wide glass-card gs-bento-reveal mouse-glow">
                    <div class="bento-content">
                        <i class="ph ph-plant bento-icon"></i>
                        <h3>Environmental Impact Tracking</h3>
                        <p>Visualize your carbon footprint reduction in real-time.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- 6. Dashboard Preview (Parallax) -->
    <section class="preview-section section-padding">
        <div class="container">
            <div class="preview-flex">
                <div class="preview-content gs-fade-in">
                    <h2 class="section-title">One Platform.<br>Multiple Experiences.</h2>
                    <p class="section-desc">Dedicated interfaces for users, collectors, and facility admins. Fully responsive, blazingly fast.</p>
                    
                    <ul class="feature-list">
                        <li><i class="ph-fill ph-check-circle"></i> User Dashboard (PWA)</li>
                        <li><i class="ph-fill ph-check-circle"></i> Collector Mobile App</li>
                        <li><i class="ph-fill ph-check-circle"></i> Admin Analytics Portal</li>
                    </ul>
                </div>
                <div class="preview-visuals">
                    <img src="C:/Users/Sreenandha M S/.gemini/antigravity-ide/brain/06377ff9-ee4a-4faf-a741-8b96941ac33f/ecoscrap_collector_app_1784821288651.png" alt="Collector App" class="app-mockup parallax-fast glass-panel">
                </div>
            </div>
        </div>
    </section>

    <!-- 7. Impact Statistics -->
    <section class="impact section-padding" id="impact">
        <div class="container">
            <div class="stats-grid">
                <div class="stat-item gs-stat">
                    <div class="stat-value"><span class="counter" data-target="500">0</span>K+</div>
                    <div class="stat-label">Kg Scrap Collected</div>
                </div>
                <div class="stat-item gs-stat">
                    <div class="stat-value"><span class="counter" data-target="12">0</span>K+</div>
                    <div class="stat-label">Active Users</div>
                </div>
                <div class="stat-item gs-stat">
                    <div class="stat-value"><span class="counter" data-target="850">0</span>+</div>
                    <div class="stat-label">Verified Collectors</div>
                </div>
                <div class="stat-item gs-stat">
                    <div class="stat-value"><span class="counter" data-target="240">0</span>T</div>
                    <div class="stat-label">CO₂ Reduced</div>
                </div>
            </div>
        </div>
    </section>

    <!-- 8. Final CTA -->
    <section class="cta-section section-padding">
        <div class="container">
            <div class="cta-box glass-panel gs-scale-up mouse-glow">
                <h2>Join the Smart Recycling Revolution</h2>
                <p>Start turning your waste into value today.</p>
                <div class="cta-buttons">
                    <a href="#" class="btn-primary btn-lg magnetic-btn">Create Free Account</a>
                    <a href="#" class="btn-secondary btn-lg magnetic-btn">Contact Sales</a>
                </div>
            </div>
        </div>
    </section>

    <!-- 9. Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-brand">
                    <a href="#" class="logo">
                        <div class="logo-mark"></div>
                        EcoScrap
                    </a>
                    <p class="footer-desc">Smart Scrap Management System.<br>MCA Final Year Project.</p>
                </div>
                <div class="footer-links">
                    <h4>Product</h4>
                    <a href="#">Features</a>
                    <a href="#">Security</a>
                    <a href="#">Pricing</a>
                </div>
                <div class="footer-links">
                    <h4>Company</h4>
                    <a href="#">About</a>
                    <a href="#">Blog</a>
                    <a href="#">Careers</a>
                </div>
                <div class="footer-links">
                    <h4>Legal</h4>
                    <a href="#">Privacy</a>
                    <a href="#">Terms</a>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2026 EcoScrap. Designed for a sustainable future.</p>
            </div>
        </div>
    </footer>

    <!-- GSAP & Lenis Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/ScrollTrigger.min.js"></script>
    <script src="https://unpkg.com/@studio-freight/lenis@1.0.33/dist/lenis.min.js"></script>
    
    <!-- Custom Script -->
    <script src="assets/js/script.js"></script>
</body>
</html>
