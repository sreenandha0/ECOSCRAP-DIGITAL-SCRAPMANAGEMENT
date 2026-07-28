// Initialize Lenis for Smooth Scrolling
const lenis = new Lenis({
    duration: 1.2,
    easing: (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)), // easeOutExpo
    direction: 'vertical',
    gestureDirection: 'vertical',
    smooth: true,
    mouseMultiplier: 1,
    smoothTouch: false,
    touchMultiplier: 2,
    infinite: false,
});

function raf(time) {
    lenis.raf(time);
    requestAnimationFrame(raf);
}
requestAnimationFrame(raf);

// Integrate Lenis with GSAP ScrollTrigger
gsap.registerPlugin(ScrollTrigger);

// Mouse Glow Effect for Bento Cards & CTA
document.querySelectorAll('.mouse-glow').forEach(el => {
    el.addEventListener('mousemove', e => {
        const rect = el.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        el.style.setProperty('--mouse-x', `${x}px`);
        el.style.setProperty('--mouse-y', `${y}px`);
    });
});

// Magnetic Buttons
const magneticBtns = document.querySelectorAll('.magnetic-btn');
magneticBtns.forEach(btn => {
    btn.addEventListener('mousemove', function(e) {
        const position = btn.getBoundingClientRect();
        const x = e.pageX - position.left - position.width / 2;
        const y = e.pageY - position.top - position.height / 2;
        
        gsap.to(btn, {
            x: x * 0.3,
            y: y * 0.3,
            duration: 0.5,
            ease: "power3.out"
        });
    });
    
    btn.addEventListener('mouseleave', function(e) {
        gsap.to(btn, {
            x: 0,
            y: 0,
            duration: 0.5,
            ease: "elastic.out(1, 0.3)"
        });
    });
});

// Navbar Scroll Effect
const navbar = document.getElementById('navbar');
window.addEventListener('scroll', () => {
    if (window.scrollY > 50) {
        navbar.classList.add('scrolled');
    } else {
        navbar.classList.remove('scrolled');
    }
});

// --- GSAP Animations ---

// 1. Hero Animations (Plays on load)
const heroTl = gsap.timeline();
heroTl.to(".gs-reveal-up", {
    y: 0,
    opacity: 1,
    visibility: "visible",
    duration: 1,
    stagger: 0.15,
    ease: "power4.out",
    delay: 0.2
});

// 2. Parallax for Dashboard Preview
gsap.to(".parallax-hero", {
    y: 100,
    ease: "none",
    scrollTrigger: {
        trigger: ".hero",
        start: "top top",
        end: "bottom top",
        scrub: true
    }
});

// 3. Floating Elements in Hero
gsap.to(".float-1", {
    y: -20,
    duration: 2,
    yoyo: true,
    repeat: -1,
    ease: "sine.inOut"
});
gsap.to(".float-2", {
    y: 20,
    duration: 2.5,
    yoyo: true,
    repeat: -1,
    ease: "sine.inOut",
    delay: 0.5
});

// 4. Section Headers Fade In
gsap.utils.toArray('.gs-fade-in').forEach(header => {
    gsap.to(header, {
        opacity: 1,
        y: 0,
        visibility: "visible",
        duration: 1,
        scrollTrigger: {
            trigger: header,
            start: "top 85%",
        }
    });
});

// 5. Problem Section Stagger
gsap.to(".gs-stagger-up", {
    y: 0,
    opacity: 1,
    visibility: "visible",
    duration: 0.8,
    stagger: 0.2,
    ease: "power3.out",
    scrollTrigger: {
        trigger: ".problem-grid",
        start: "top 80%",
    }
});

// 6. Timeline Stagger
gsap.to(".gs-timeline-stagger", {
    x: 0,
    opacity: 1,
    visibility: "visible",
    duration: 0.8,
    stagger: 0.3,
    ease: "power3.out",
    scrollTrigger: {
        trigger: ".timeline-container",
        start: "top 75%",
    }
});

// 7. Bento Grid Reveal
gsap.to(".gs-bento-reveal", {
    y: 0,
    opacity: 1,
    visibility: "visible",
    duration: 0.8,
    stagger: 0.1,
    ease: "power3.out",
    scrollTrigger: {
        trigger: ".bento-grid",
        start: "top 75%",
    }
});

// 8. Stats Counter Animation
const stats = gsap.utils.toArray('.gs-stat');
if(stats.length > 0) {
    ScrollTrigger.create({
        trigger: ".stats-grid",
        start: "top 85%",
        onEnter: () => {
            gsap.to(".gs-stat", {
                y: 0,
                opacity: 1,
                visibility: "visible",
                duration: 0.6,
                stagger: 0.1
            });
            
            document.querySelectorAll('.counter').forEach(counter => {
                const target = +counter.getAttribute('data-target');
                gsap.to(counter, {
                    innerHTML: target,
                    duration: 2,
                    snap: { innerHTML: 1 },
                    ease: "power1.out"
                });
            });
        },
        once: true
    });
}

// 9. CTA Scale Up
gsap.to(".gs-scale-up", {
    scale: 1,
    opacity: 1,
    visibility: "visible",
    duration: 0.8,
    ease: "back.out(1.7)",
    scrollTrigger: {
        trigger: ".cta-section",
        start: "top 80%",
    }
});
