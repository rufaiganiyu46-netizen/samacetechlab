(function () {
    const THEME_KEY = 'samace_theme';
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const pointer = { x: null, y: null, active: false };
    let sharedCanvas = null;
    let sharedContext = null;
    let particlesObserver = null;
    let sceneWidth = 0;
    let sceneHeight = 0;
    let sceneParticles = [];
    let sceneComets = [];
    let resizeScheduled = false;
    let interactiveObserver = null;
    let revealObserver = null;

    function getTheme() {
        return 'dark';
    }

    function toRgba(color, alpha) {
        if (color.startsWith('rgba(')) {
            const parts = color.slice(5, -1).split(',').map((part) => part.trim());
            return `rgba(${parts[0]}, ${parts[1]}, ${parts[2]}, ${alpha})`;
        }

        if (color.startsWith('rgb(')) {
            const parts = color.slice(4, -1).split(',').map((part) => part.trim());
            return `rgba(${parts[0]}, ${parts[1]}, ${parts[2]}, ${alpha})`;
        }

        if (color.startsWith('#')) {
            let hex = color.slice(1);
            if (hex.length === 3) {
                hex = hex.split('').map((char) => char + char).join('');
            }
            const red = parseInt(hex.slice(0, 2), 16);
            const green = parseInt(hex.slice(2, 4), 16);
            const blue = parseInt(hex.slice(4, 6), 16);
            return `rgba(${red}, ${green}, ${blue}, ${alpha})`;
        }

        return color;
    }

    function getDotPalette(theme) {
        return theme === 'light'
            ? ['rgba(0,0,0,0.12)', 'rgba(0,0,0,0.08)', 'rgba(80,80,80,0.12)', 'rgba(120,120,120,0.10)']
            : ['rgba(245,166,35,0.34)', 'rgba(0,212,180,0.28)', 'rgba(74,158,255,0.26)', 'rgba(255,255,255,0.22)'];
    }

    function getCanvasPalette(theme) {
        return theme === 'light'
            ? ['rgba(0,0,0,0.14)', 'rgba(0,0,0,0.10)', 'rgba(90,90,90,0.12)', 'rgba(120,120,120,0.12)']
            : ['#F5A623', '#00D4B4', '#4A9EFF', 'rgba(255,255,255,0.72)'];
    }

    function ensureParticlesContainer() {
        let container = document.getElementById('particles-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'particles-container';
            container.setAttribute('aria-hidden', 'true');
            document.body.insertBefore(container, document.body.firstChild);
        }
        return container;
    }

    function ensureSharedOrbs() {
        if (document.querySelector('.shared-bg-orb') || document.querySelector('.orb')) {
            return;
        }

        const configs = ['gold', 'teal', 'blue'];
        configs.forEach((name) => {
            const orb = document.createElement('div');
            orb.className = `shared-bg-orb shared-bg-orb-${name}`;
            orb.setAttribute('aria-hidden', 'true');
            document.body.insertBefore(orb, document.body.firstChild);
        });
    }

    function buildSharedDots(theme) {
        const container = ensureParticlesContainer();
        const colors = getDotPalette(theme);
        const dotCount = window.innerWidth < 768 ? 36 : 72;
        container.innerHTML = '';

        for (let index = 0; index < dotCount; index += 1) {
            const dot = document.createElement('span');
            const color = colors[Math.floor(Math.random() * colors.length)];
            const size = Math.random() * 4 + 2;
            dot.dataset.sharedDot = 'true';
            dot.style.cssText = `position:absolute; width:${size}px; height:${size}px; border-radius:50%; background:${color}; color:${color}; box-shadow:0 0 ${size * 10}px currentColor; top:${Math.random() * 100}%; left:${Math.random() * 100}%; animation:floatDot ${Math.random() * 14 + 8}s ease-in-out ${Math.random() * 5}s infinite alternate;`;
            container.appendChild(dot);
        }
    }

    function syncSharedDots() {
        const container = document.getElementById('particles-container');
        if (!container) {
            return;
        }

        Array.from(container.children).forEach((child) => {
            if (!(child instanceof HTMLElement)) {
                child.remove();
                return;
            }
            if (child.dataset.sharedDot !== 'true') {
                child.remove();
            }
        });
    }

    function observeParticlesContainer() {
        const container = ensureParticlesContainer();
        if (particlesObserver) {
            particlesObserver.disconnect();
        }

        particlesObserver = new MutationObserver(() => {
            syncSharedDots();
        });

        particlesObserver.observe(container, { childList: true });
    }

    function ensureSharedCanvas() {
        const existingCanvas = document.getElementById('backgroundCanvas');
        if (existingCanvas instanceof HTMLCanvasElement) {
            return existingCanvas;
        }

        let canvas = document.getElementById('shared-background-canvas');
        if (!canvas) {
            canvas = document.createElement('canvas');
            canvas.id = 'shared-background-canvas';
            canvas.className = 'shared-background-canvas';
            canvas.setAttribute('aria-hidden', 'true');
            document.body.insertBefore(canvas, document.body.firstChild);
        }
        return canvas;
    }

    function createParticle() {
        const palette = getCanvasPalette(getTheme());
        const angle = Math.random() * Math.PI * 2;
        const speed = 0.12 + Math.random() * 0.4;
        return {
            x: Math.random() * sceneWidth,
            y: Math.random() * sceneHeight,
            size: 1.2 + Math.random() * 3.8,
            vx: Math.cos(angle) * speed,
            vy: Math.sin(angle) * speed,
            color: palette[Math.floor(Math.random() * palette.length)],
            alpha: 0.35 + Math.random() * 0.5,
            pulse: Math.random() * Math.PI * 2,
            glow: 18 + Math.random() * 28
        };
    }

    function createComet() {
        const fromLeft = Math.random() > 0.5;
        return {
            x: fromLeft ? -120 : sceneWidth + 120,
            y: Math.random() * sceneHeight * 0.55,
            vx: fromLeft ? 5 + Math.random() * 2 : -(5 + Math.random() * 2),
            vy: 1.1 + Math.random() * 1.8,
            length: 120 + Math.random() * 80,
            life: 0,
            maxLife: 90 + Math.random() * 50,
            color: ['rgba(245,166,35,0.9)', 'rgba(0,212,180,0.85)', 'rgba(74,158,255,0.85)'][Math.floor(Math.random() * 3)]
        };
    }

    function resizeScene() {
        if (!sharedCanvas || !sharedContext) {
            return;
        }

        sceneWidth = window.innerWidth;
        sceneHeight = window.innerHeight;
        const ratio = window.devicePixelRatio || 1;
        sharedCanvas.width = sceneWidth * ratio;
        sharedCanvas.height = sceneHeight * ratio;
        sharedCanvas.style.width = sceneWidth + 'px';
        sharedCanvas.style.height = sceneHeight + 'px';
        sharedContext.setTransform(ratio, 0, 0, ratio, 0, 0);
        sceneParticles = Array.from({ length: sceneWidth < 768 ? 60 : 120 }, createParticle);
        sceneComets = Array.from({ length: sceneWidth < 768 ? 1 : 2 }, createComet);
        buildSharedDots(getTheme());
    }

    function updateParticle(particle) {
        particle.x += particle.vx;
        particle.y += particle.vy;
        particle.pulse += 0.02;

        if (particle.x < -10) particle.x = sceneWidth + 10;
        if (particle.x > sceneWidth + 10) particle.x = -10;
        if (particle.y < -10) particle.y = sceneHeight + 10;
        if (particle.y > sceneHeight + 10) particle.y = -10;

        if (pointer.active && pointer.x !== null && pointer.y !== null) {
            const dx = particle.x - pointer.x;
            const dy = particle.y - pointer.y;
            const distance = Math.sqrt(dx * dx + dy * dy);
            if (distance < 150 && distance > 0) {
                const force = (150 - distance) / 150;
                particle.x += (dx / distance) * force * 2.5;
                particle.y += (dy / distance) * force * 2.5;
            }
        }
    }

    function updateComet(comet, index) {
        comet.x += comet.vx;
        comet.y += comet.vy;
        comet.life += 1;
        if (comet.life > comet.maxLife || comet.y > sceneHeight + 80 || comet.x < -220 || comet.x > sceneWidth + 220) {
            sceneComets[index] = createComet();
        }
    }

    function drawBackdrop() {
        const gradient = sharedContext.createRadialGradient(sceneWidth * 0.5, sceneHeight * 0.3, 0, sceneWidth * 0.5, sceneHeight * 0.3, Math.max(sceneWidth, sceneHeight) * 0.75);
        gradient.addColorStop(0, 'rgba(74,158,255,0.10)');
        gradient.addColorStop(0.45, 'rgba(0,212,180,0.08)');
        gradient.addColorStop(0.75, 'rgba(245,166,35,0.05)');
        gradient.addColorStop(1, 'rgba(10,10,15,0)');
        sharedContext.fillStyle = gradient;
        sharedContext.fillRect(0, 0, sceneWidth, sceneHeight);
    }

    function drawScene() {
        if (!sharedContext) {
            return;
        }

        sharedContext.clearRect(0, 0, sceneWidth, sceneHeight);
        drawBackdrop();

        for (let index = 0; index < sceneParticles.length; index += 1) {
            const particle = sceneParticles[index];
            updateParticle(particle);

            const pulseScale = 0.82 + ((Math.sin(particle.pulse) + 1) / 2) * 0.5;
            const glowSize = particle.glow * pulseScale;
            const glow = sharedContext.createRadialGradient(particle.x, particle.y, 0, particle.x, particle.y, glowSize);
            glow.addColorStop(0, toRgba(particle.color, particle.alpha * 0.28));
            glow.addColorStop(1, 'rgba(0,0,0,0)');
            sharedContext.beginPath();
            sharedContext.fillStyle = glow;
            sharedContext.arc(particle.x, particle.y, glowSize, 0, Math.PI * 2);
            sharedContext.fill();

            sharedContext.beginPath();
            sharedContext.fillStyle = toRgba(particle.color, particle.alpha);
            sharedContext.arc(particle.x, particle.y, particle.size * pulseScale, 0, Math.PI * 2);
            sharedContext.fill();

            for (let inner = index + 1; inner < sceneParticles.length; inner += 1) {
                const other = sceneParticles[inner];
                const dx = particle.x - other.x;
                const dy = particle.y - other.y;
                const distance = Math.sqrt(dx * dx + dy * dy);
                if (distance <= 140) {
                    const opacity = 1 - (distance / 140);
                    sharedContext.beginPath();
                    sharedContext.strokeStyle = 'rgba(0, 212, 180, ' + (opacity * 0.18) + ')';
                    sharedContext.lineWidth = 0.6 + opacity * 1.2;
                    sharedContext.moveTo(particle.x, particle.y);
                    sharedContext.lineTo(other.x, other.y);
                    sharedContext.stroke();
                }
            }
        }

        for (let index = 0; index < sceneComets.length; index += 1) {
            const comet = sceneComets[index];
            updateComet(comet, index);
            const tailX = comet.x - (comet.vx / Math.abs(comet.vx || 1)) * comet.length;
            const tailY = comet.y - comet.vy * (comet.length / Math.max(Math.abs(comet.vx), 1));
            const trail = sharedContext.createLinearGradient(comet.x, comet.y, tailX, tailY);
            trail.addColorStop(0, comet.color);
            trail.addColorStop(0.35, 'rgba(255,255,255,0.35)');
            trail.addColorStop(1, 'rgba(255,255,255,0)');
            sharedContext.beginPath();
            sharedContext.strokeStyle = trail;
            sharedContext.lineWidth = 2.2;
            sharedContext.moveTo(comet.x, comet.y);
            sharedContext.lineTo(tailX, tailY);
            sharedContext.stroke();

            sharedContext.beginPath();
            sharedContext.fillStyle = 'rgba(255,255,255,0.95)';
            sharedContext.arc(comet.x, comet.y, 2.4, 0, Math.PI * 2);
            sharedContext.fill();
        }

        window.requestAnimationFrame(drawScene);
    }

    function updateParticleColors(theme) {
        const colors = getDotPalette(theme);
        document.querySelectorAll('#particles-container span[data-shared-dot="true"]').forEach((dot) => {
            const color = colors[Math.floor(Math.random() * colors.length)];
            dot.style.background = color;
            dot.style.color = color;
        });

        sceneParticles.forEach((particle) => {
            const palette = getCanvasPalette(theme);
            particle.color = palette[Math.floor(Math.random() * palette.length)];
        });
    }

    function applySavedTheme() {
        const theme = getTheme();
        document.documentElement.setAttribute('data-theme', theme);
        document.querySelectorAll('.orb, .shared-bg-orb').forEach((orb) => {
            orb.style.opacity = theme === 'light' ? '0.55' : '';
        });
        updateParticleColors(theme);
    }

    function getInteractiveSelector() {
        return [
            'button',
            '.btn-base',
            '.portal-button',
            '.action-btn',
            '.btn-action',
            '.submit-btn',
            '.btn-register',
            '.send-btn',
            '.chat-send-btn',
            '.mobile-toggle',
            '.hamburger',
            '.icon-btn',
            '.input-icon',
            '.section-toggle',
            '.menu-action',
            '.new-chat-btn',
            '.back-chat',
            '.ann-toggle-btn',
            '.comment-toggle-btn',
            '.btn-post-comment',
            '.reaction-btn',
            '.topbar-icon-btn',
            '.eye-toggle',
            '.password-toggle',
            '.nav-item',
            'a.btn-base',
            'a.portal-button',
            'a.action-btn',
            'a.btn-action',
            'a.nav-item'
        ].join(', ');
    }

    function getSurfaceSelector() {
        return [
            '.glass-panel',
            '.section-card',
            '.card',
            '.portal-card',
            '.why-card',
            '.footer-panel',
            '.feature-float',
            '.register-card',
            '.login-card',
            '.hero-box',
            '.tile',
            '.chat-shell',
            '.chat-container',
            '.announcement-card',
            '.preview-item',
            '.person-card',
            '.stat-card',
            '.info-card',
            '.sidebar-user',
            '.today-pill',
            '.date-badge',
            '.contact-card',
            '.sidebar',
            '.site-header',
            '.header',
            '.topbar',
            '.contact-list-pane',
            '.contacts-pane',
            '.chat-pane',
            '.chat-window'
        ].join(', ');
    }

    function getRevealSelector() {
        return [
            getSurfaceSelector(),
            '.section-head',
            '.hero-copy',
            '.hero-content',
            '.page-header',
            '.dashboard-header',
            '.welcome-banner',
            '.overview-card',
            '.insight-card',
            '.hero-stats',
            '.stats-grid'
        ].join(', ');
    }

    function getStageSelector() {
        return [
            '.shell',
            '.main',
            '.main-inner',
            '.main-content',
            '.login-shell',
            '.register-wrapper',
            '.site-shell',
            '.hero-grid',
            '.hero-stack',
            '.page-header',
            '.page-head',
            '.chat-shell',
            '.sidebar',
            '.contacts-pane',
            '.chat-pane'
        ].join(', ');
    }

    function hasClassToken(element, matcher) {
        if (!(element instanceof Element)) {
            return false;
        }

        return Array.from(element.classList).some((token) => matcher(token));
    }

    function isSurfaceCandidate(element) {
        if (!(element instanceof HTMLElement) || element.closest('#particles-container')) {
            return false;
        }

        if (element.matches(getSurfaceSelector())) {
            return true;
        }

        return hasClassToken(element, (token) => {
            const normalized = token.toLowerCase();
            return [
                'card',
                'panel',
                'sidebar',
                'header',
                'pane',
                'flash',
                'status'
            ].includes(normalized) || /(card|panel|sidebar|header|pane)$/.test(normalized);
        });
    }

    function isRevealCandidate(element) {
        if (!(element instanceof HTMLElement) || element.closest('#particles-container')) {
            return false;
        }

        if (element.matches(getRevealSelector()) || isSurfaceCandidate(element)) {
            return true;
        }

        return hasClassToken(element, (token) => {
            const normalized = token.toLowerCase();
            return [
                'page-head',
                'page-header',
                'section-head',
                'main-inner',
                'stats-grid',
                'announcement-list',
                'contact-list',
                'preview-list',
                'hero-copy',
                'hero-content'
            ].includes(normalized) || /(grid|list|wrapper)$/.test(normalized);
        });
    }

    function markInteractiveElements(root) {
        if (!(root instanceof Element || root instanceof Document)) {
            return;
        }

        root.querySelectorAll(getInteractiveSelector()).forEach((element) => {
            if (element.closest('#particles-container')) {
                return;
            }
            element.classList.add('ui-reactive');
        });
    }

    function assignRevealDelay(element, index) {
        if (!(element instanceof HTMLElement) || element.dataset.uiRevealDelayAssigned === 'true') {
            return;
        }

        element.style.setProperty('--ui-reveal-delay', (index % 5) * 70 + 'ms');
        element.dataset.uiRevealDelayAssigned = 'true';
    }

    function markMotionElements(root) {
        if (!(root instanceof Element || root instanceof Document)) {
            return;
        }

        const elements = root instanceof Document ? Array.from(root.body ? root.body.querySelectorAll('*') : []) : [root, ...root.querySelectorAll('*')];

        elements.forEach((element, index) => {
            if (!(element instanceof HTMLElement)) {
                return;
            }

            if (element.matches(getStageSelector())) {
                element.classList.add('ui-motion-stage');
            }

            if (element.matches('.page-header, .page-head, .sidebar, .chat-header, .login-card, .register-card, .glass-panel, .section-card, .stat-card, .announcement-card')) {
                element.classList.add('ui-motion-accent');
            }

            if (isSurfaceCandidate(element)) {
                element.classList.add('ui-glass-surface');
                if (!element.matches('.sidebar, .site-header, .header, .topbar, .contacts-pane, .contact-list-pane, .chat-pane, .chat-window, .page-head, .page-header, .flash, .inline-status')) {
                    element.classList.add('ui-float');
                }
                assignRevealDelay(element, index);
            }

            if (isRevealCandidate(element)) {
                element.classList.add('ui-reveal');
                assignRevealDelay(element, index);
            }
        });
    }

    function initializeMotionElements() {
        markMotionElements(document);

        if (prefersReducedMotion) {
            document.querySelectorAll('.ui-reveal').forEach((element) => element.classList.add('is-visible'));
            return;
        }

        if (revealObserver) {
            revealObserver.disconnect();
        }

        revealObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) {
                    return;
                }
                entry.target.classList.add('is-visible');
                revealObserver.unobserve(entry.target);
            });
        }, { threshold: 0.18, rootMargin: '0px 0px -8% 0px' });

        document.querySelectorAll('.ui-reveal').forEach((element) => revealObserver.observe(element));
    }

    function createRipple(target, clientX, clientY) {
        if (!(target instanceof HTMLElement)) {
            return;
        }

        const rect = target.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height) * 1.15;
        const ripple = document.createElement('span');
        ripple.className = 'ui-ripple';
        ripple.style.width = size + 'px';
        ripple.style.height = size + 'px';
        ripple.style.left = clientX - rect.left - size / 2 + 'px';
        ripple.style.top = clientY - rect.top - size / 2 + 'px';
        target.appendChild(ripple);
        ripple.addEventListener('animationend', () => ripple.remove(), { once: true });
    }

    function initializeInteractiveElements() {
        markInteractiveElements(document);

        if (!prefersReducedMotion) {
            document.addEventListener('pointerdown', (event) => {
                const target = event.target instanceof Element ? event.target.closest(getInteractiveSelector()) : null;
                if (!target || target.closest('#particles-container')) {
                    return;
                }
                createRipple(target, event.clientX, event.clientY);
            }, { passive: true });
        }

        if (interactiveObserver) {
            interactiveObserver.disconnect();
        }

        interactiveObserver = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node instanceof Element) {
                        if (node.matches(getInteractiveSelector())) {
                            node.classList.add('ui-reactive');
                        }
                        markInteractiveElements(node);
                        markMotionElements(node);
                        if (!prefersReducedMotion && revealObserver) {
                            node.querySelectorAll('.ui-reveal').forEach((element) => revealObserver.observe(element));
                            if (node.matches('.ui-reveal')) {
                                revealObserver.observe(node);
                            }
                        }
                    }
                });
            });
        });

        if (document.body) {
            interactiveObserver.observe(document.body, { childList: true, subtree: true });
        }
    }

    function initializeSharedBackground() {
        if (!document.body) {
            return;
        }

        ensureSharedOrbs();
        buildSharedDots(getTheme());
        observeParticlesContainer();
        syncSharedDots();

        if (prefersReducedMotion) {
            return;
        }

        sharedCanvas = ensureSharedCanvas();
        if (!sharedCanvas) {
            return;
        }

        sharedContext = sharedCanvas.getContext('2d');
        if (!sharedContext) {
            return;
        }

        resizeScene();

        window.addEventListener('mousemove', (event) => {
            pointer.x = event.clientX;
            pointer.y = event.clientY;
            pointer.active = true;
        }, { passive: true });

        window.addEventListener('mouseout', () => {
            pointer.x = null;
            pointer.y = null;
            pointer.active = false;
        });

        window.addEventListener('resize', () => {
            if (resizeScheduled) {
                return;
            }
            resizeScheduled = true;
            window.requestAnimationFrame(() => {
                resizeScheduled = false;
                resizeScene();
            });
        });

        window.requestAnimationFrame(drawScene);
    }

    function boot() {
        initializeSharedBackground();
        initializeInteractiveElements();
        initializeMotionElements();
        applySavedTheme();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }

    window.addEventListener('load', applySavedTheme);
    window.setTimeout(applySavedTheme, 120);
})();