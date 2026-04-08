(() => {
    const body = document.body;
    if (!body || !body.classList.contains('auth-page')) {
        return;
    }

    const ROLE = body.dataset.role || 'principal';
    const pageType = body.dataset.page || 'login';
    const ROLE_CONFIG = {
        principal: {
            accent: '#F5A623',
            accentRgb: '245,166,35',
            glow: 'rgba(245,166,35,0.3)',
            badge: '🎓 PRINCIPAL PORTAL',
            gradient: 'linear-gradient(135deg, #F5A623, #e8950f)',
            particles: ['rgba(245,166,35,', 'rgba(255,200,80,', 'rgba(255,255,255,'],
            lines: 'rgba(245,166,35,'
        },
        parent: {
            accent: '#00D4B4',
            accentRgb: '0,212,180',
            glow: 'rgba(0,212,180,0.3)',
            badge: '👨‍👩‍👧 PARENT PORTAL',
            gradient: 'linear-gradient(135deg, #00D4B4, #009d87)',
            particles: ['rgba(0,212,180,', 'rgba(74,158,255,', 'rgba(0,180,255,'],
            lines: 'rgba(0,212,180,'
        },
        teacher: {
            accent: '#4A9EFF',
            accentRgb: '74,158,255',
            glow: 'rgba(74,158,255,0.3)',
            badge: '👩‍🏫 TEACHER PORTAL',
            gradient: 'linear-gradient(135deg, #4A9EFF, #2d7dd2)',
            particles: ['rgba(74,158,255,', 'rgba(140,100,255,', 'rgba(0,212,180,'],
            lines: 'rgba(74,158,255,'
        }
    };
    const C = ROLE_CONFIG[ROLE] || ROLE_CONFIG.principal;

    document.documentElement.style.setProperty('--accent', C.accent);
    document.documentElement.style.setProperty('--accent-rgb', C.accentRgb);
    document.documentElement.style.setProperty('--gradient', C.gradient);

    const badge = document.querySelector('.portal-badge');
    if (badge) {
        badge.textContent = C.badge;
    }

    function ensureDecor() {
        if (!document.getElementById('bg-canvas')) {
            const canvas = document.createElement('canvas');
            canvas.id = 'bg-canvas';
            document.body.prepend(canvas);
        }

        if (!document.querySelector('.geo-wrap')) {
            const wrap = document.createElement('div');
            wrap.className = 'geo-wrap';
            ['geo-1', 'geo-2', 'geo-3', 'geo-ring-1', 'geo-ring-2'].forEach((cls) => {
                const div = document.createElement('div');
                div.className = `geo ${cls}`;
                wrap.appendChild(div);
            });
            document.body.appendChild(wrap);
        }

        if (!document.querySelector('.glass-pill')) {
            ['gp-1', 'gp-2', 'gp-3', 'gp-4'].forEach((cls) => {
                const div = document.createElement('div');
                div.className = `glass-pill ${cls}`;
                document.body.appendChild(div);
            });
        }

        if (!document.querySelector('.dot-cluster.dc-tl')) {
            ['dc-tl', 'dc-br'].forEach((cls) => {
                const cluster = document.createElement('div');
                cluster.className = `dot-cluster ${cls}`;
                for (let index = 0; index < 12; index += 1) {
                    cluster.appendChild(document.createElement('span'));
                }
                document.body.appendChild(cluster);
            });
        }

        document.querySelectorAll('.login-card, .register-card').forEach((card) => {
            if (!card.querySelector('.card-glow-bar')) {
                const glowBar = document.createElement('div');
                glowBar.className = 'card-glow-bar';
                card.prepend(glowBar);
            }
            if (!card.querySelector('.scan-line')) {
                const scan = document.createElement('div');
                scan.className = 'scan-line';
                card.prepend(scan);
            }
            const dotClasses = [
                ['corner-dot cd-tl', 'cd-tl'],
                ['corner-dot cd-tr', 'cd-tr'],
                ['corner-dot cd-bl', 'cd-bl'],
                ['corner-dot cd-br', 'cd-br']
            ];
            dotClasses.forEach(([fullClass, checkClass]) => {
                if (!card.querySelector('.' + checkClass)) {
                    const dot = document.createElement('div');
                    dot.className = fullClass;
                    card.prepend(dot);
                }
            });
            if (!card.querySelector('.card-content')) {
                const content = document.createElement('div');
                content.className = 'card-content';
                Array.from(card.childNodes).forEach((node) => {
                    if (node instanceof HTMLElement && (node.classList.contains('corner-dot') || node.classList.contains('scan-line') || node.classList.contains('card-glow-bar'))) {
                        return;
                    }
                    content.appendChild(node);
                });
                card.appendChild(content);
            }
        });
    }

    function initCanvas() {
        const canvas = document.getElementById('bg-canvas');
        if (!(canvas instanceof HTMLCanvasElement)) {
            return;
        }
        const ctx = canvas.getContext('2d');
        if (!ctx) {
            return;
        }

        const mouse = { x: -1000, y: -1000 };

        function resizeCanvas() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        }
        resizeCanvas();
        window.addEventListener('resize', resizeCanvas);

        const COUNT = window.innerWidth < 768 ? 40 : 85;
        const particles = Array.from({ length: COUNT }, () => createParticle());

        function createParticle() {
            const cols = C.particles;
            const col = cols[Math.floor(Math.random() * cols.length)];
            return {
                x: Math.random() * window.innerWidth,
                y: Math.random() * window.innerHeight,
                vx: (Math.random() - 0.5) * 0.55,
                vy: (Math.random() - 0.5) * 0.55,
                size: Math.random() * 2.8 + 1,
                opacity: Math.random() * 0.55 + 0.2,
                col,
                pulse: Math.random() * Math.PI * 2,
                pulseSpd: Math.random() * 0.022 + 0.008
            };
        }

        document.addEventListener('mousemove', (event) => {
            mouse.x = event.clientX;
            mouse.y = event.clientY;
        }, { passive: true });

        let frame = 0;
        function animate() {
            requestAnimationFrame(animate);
            const bg = ctx.createLinearGradient(0, 0, canvas.width, canvas.height);
            bg.addColorStop(0, '#080810');
            bg.addColorStop(0.5, '#0A0A18');
            bg.addColorStop(1, '#060610');
            ctx.fillStyle = bg;
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            if (mouse.x > 0) {
                const sp = ctx.createRadialGradient(mouse.x, mouse.y, 0, mouse.x, mouse.y, 260);
                sp.addColorStop(0, `${C.lines}0.07)`);
                sp.addColorStop(1, 'transparent');
                ctx.fillStyle = sp;
                ctx.fillRect(0, 0, canvas.width, canvas.height);
            }

            for (let outer = 0; outer < particles.length; outer += 1) {
                for (let inner = outer + 1; inner < particles.length; inner += 1) {
                    const dx = particles[outer].x - particles[inner].x;
                    const dy = particles[outer].y - particles[inner].y;
                    const dist = Math.sqrt(dx * dx + dy * dy);
                    if (dist < 130) {
                        ctx.beginPath();
                        ctx.moveTo(particles[outer].x, particles[outer].y);
                        ctx.lineTo(particles[inner].x, particles[inner].y);
                        ctx.strokeStyle = `${C.lines}${(1 - dist / 130) * 0.22})`;
                        ctx.lineWidth = 0.7;
                        ctx.stroke();
                    }
                }
            }

            particles.forEach((particle) => {
                const dx = particle.x - mouse.x;
                const dy = particle.y - mouse.y;
                const dist = Math.sqrt(dx * dx + dy * dy);
                if (dist < 130 && dist > 0) {
                    const force = (130 - dist) / 130;
                    particle.vx += (dx / dist) * force * 0.7;
                    particle.vy += (dy / dist) * force * 0.7;
                }
                const speed = Math.sqrt(particle.vx * particle.vx + particle.vy * particle.vy);
                if (speed > 2) {
                    particle.vx *= 0.94;
                    particle.vy *= 0.94;
                }
                particle.x += particle.vx;
                particle.y += particle.vy;
                if (particle.x < 0) particle.x = canvas.width;
                if (particle.x > canvas.width) particle.x = 0;
                if (particle.y < 0) particle.y = canvas.height;
                if (particle.y > canvas.height) particle.y = 0;
                particle.pulse += particle.pulseSpd;
                const radius = particle.size + Math.sin(particle.pulse) * 0.9;
                ctx.beginPath();
                ctx.arc(particle.x, particle.y, radius, 0, Math.PI * 2);
                ctx.fillStyle = `${particle.col}${particle.opacity})`;
                ctx.fill();
            });
            frame += 1;
        }
        animate();
    }

    function initTilt() {
        const card = document.querySelector('.login-card, .register-card');
        if (!card) {
            return;
        }
        document.addEventListener('mousemove', (event) => {
            if (window.innerWidth <= 600 || window.matchMedia('(hover:none)').matches) {
                return;
            }
            const rect = card.getBoundingClientRect();
            const cx = rect.left + rect.width / 2;
            const cy = rect.top + rect.height / 2;
            const dx = event.clientX - cx;
            const dy = event.clientY - cy;
            const rotX = -(dy / (rect.height / 2)) * 11;
            const rotY = (dx / (rect.width / 2)) * 11;
            card.style.transform = `perspective(1000px) rotateX(${rotX}deg) rotateY(${rotY}deg)`;
            const xp = ((event.clientX - rect.left) / rect.width) * 100;
            const yp = ((event.clientY - rect.top) / rect.height) * 100;
            card.style.setProperty('--mx', xp + '%');
            card.style.setProperty('--my', yp + '%');
            const icon = card.querySelector('.brand-icon, .school-chip');
            const name = card.querySelector('.brand-name, .school-name');
            if (icon instanceof HTMLElement) {
                icon.style.transform = `translateY(calc(-7px + ${-dy * 0.014}px)) translateX(${-dx * 0.014}px)`;
            }
            if (name instanceof HTMLElement) {
                name.style.transform = `translate(${dx * 0.007}px,${dy * 0.007}px)`;
            }
        });
        document.addEventListener('mouseleave', () => {
            card.style.transform = 'perspective(1000px) rotateX(0) rotateY(0)';
            const icon = card.querySelector('.brand-icon, .school-chip');
            const name = card.querySelector('.brand-name, .school-name');
            if (icon instanceof HTMLElement) {
                icon.style.transform = '';
            }
            if (name instanceof HTMLElement) {
                name.style.transform = '';
            }
        });
    }

    function initRipple() {
        document.querySelectorAll('.btn-submit, .submit-btn, .btn-register').forEach((button) => {
            button.addEventListener('click', function (event) {
                const ripple = document.createElement('span');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height) * 2;
                ripple.className = 'ripple';
                ripple.style.width = `${size}px`;
                ripple.style.height = `${size}px`;
                ripple.style.left = `${event.clientX - rect.left - size / 2}px`;
                ripple.style.top = `${event.clientY - rect.top - size / 2}px`;
                this.appendChild(ripple);
                setTimeout(() => ripple.remove(), 700);
            });
        });
    }

    function initEyeToggle() {
        document.querySelectorAll('.eye-toggle, .password-toggle').forEach((button) => {
            button.addEventListener('click', function () {
                const wrap = this.closest('.input-wrap, .input-wrapper, .password-wrap');
                const input = wrap ? wrap.querySelector('input') : null;
                if (!(input instanceof HTMLInputElement)) {
                    return;
                }
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                this.innerHTML = show ? '🙈' : '👁️';
                this.style.color = show ? 'var(--accent)' : '';
            });
        });
    }

    function initPasswordStrength() {
        const password = document.querySelector('#password');
        if (!(password instanceof HTMLInputElement)) {
            return;
        }
        password.addEventListener('input', function () {
            const v = this.value;
            let s = 0;
            if (v.length >= 8) s += 1;
            if (/[A-Z]/.test(v)) s += 1;
            if (/[0-9]/.test(v)) s += 1;
            if (/[^A-Za-z0-9]/.test(v)) s += 1;
            const lvl = [
                { w: '0%', c: 'transparent', t: '' },
                { w: '25%', c: '#FF4757', t: 'Weak' },
                { w: '50%', c: '#F5A623', t: 'Fair' },
                { w: '75%', c: '#4A9EFF', t: 'Good' },
                { w: '100%', c: '#00D4B4', t: 'Strong 💪' }
            ][s];
            const fill = document.getElementById('strength-fill');
            const text = document.getElementById('strength-text');
            if (fill) {
                fill.style.width = lvl.w;
                fill.style.background = lvl.c;
            }
            if (text) {
                text.textContent = lvl.t;
                text.style.color = lvl.c;
            }
        });
    }

    function showToast(message, type = 'success') {
        const colors = { success: '#00D4B4', error: '#FF4757', warning: '#F5A623', info: '#4A9EFF' };
        const toast = document.createElement('div');
        toast.style.cssText = `position:fixed;top:24px;right:24px;z-index:99999;background:rgba(15,15,26,0.97);border:1px solid rgba(255,255,255,0.1);border-left:4px solid ${colors[type] || colors.success};border-radius:14px;padding:16px 20px;color:#fff;font-family:'DM Sans',sans-serif;font-size:14px;max-width:320px;backdrop-filter:blur(20px);box-shadow:0 20px 60px rgba(0,0,0,0.5);transform:translateX(120%);transition:transform 0.4s cubic-bezier(0.23,1,0.32,1);`;
        toast.textContent = message;
        document.body.appendChild(toast);
        requestAnimationFrame(() => { toast.style.transform = 'translateX(0)'; });
        setTimeout(() => {
            toast.style.transform = 'translateX(120%)';
            setTimeout(() => toast.remove(), 400);
        }, 3500);
    }

    window.authShowToast = showToast;

    function initValidation() {
        const form = document.querySelector('form');
        if (!form) {
            return;
        }
        form.addEventListener('submit', function (event) {
            let ok = true;
            this.querySelectorAll('input[required],select[required]').forEach((input) => {
                if (!(input instanceof HTMLInputElement || input instanceof HTMLSelectElement) || input.value.trim()) {
                    return;
                }
                input.classList.add('shake');
                input.style.borderColor = 'var(--error)';
                setTimeout(() => {
                    input.classList.remove('shake');
                    input.style.borderColor = '';
                }, 700);
                ok = false;
            });
            const pw = this.querySelector('#password');
            const cpw = this.querySelector('#confirm_password');
            if (pw instanceof HTMLInputElement && cpw instanceof HTMLInputElement && pw.value !== cpw.value) {
                cpw.classList.add('shake');
                cpw.style.borderColor = 'var(--error)';
                setTimeout(() => {
                    cpw.classList.remove('shake');
                    cpw.style.borderColor = '';
                }, 700);
                showToast('⚠️ Passwords do not match', 'error');
                ok = false;
            }
            if (!ok) {
                event.preventDefault();
                showToast(pageType === 'login' ? '⚠️ Fill in your email and password' : '⚠️ Fill in all required fields', 'error');
            } else {
                const btn = this.querySelector('.btn-submit, .submit-btn, .btn-register');
                if (btn instanceof HTMLButtonElement) {
                    btn.classList.add('loading');
                    btn.textContent = 'Please wait...';
                }
            }
        });
        document.querySelectorAll('.form-input, input').forEach((input) => {
            input.addEventListener('input', function () {
                this.style.borderColor = '';
            });
        });
    }

    function initEntrance() {
        document.querySelectorAll('.card-content > *').forEach((el, i) => {
            if (!(el instanceof HTMLElement)) {
                return;
            }
            el.style.opacity = '0';
            el.style.transform = 'translateY(22px)';
            el.style.transition = `opacity 0.5s ease ${i * 0.09}s, transform 0.5s ease ${i * 0.09}s`;
            requestAnimationFrame(() => setTimeout(() => {
                el.style.opacity = '1';
                el.style.transform = 'translateY(0)';
            }, 80 + i * 90));
        });
    }

    function initStatusToasts() {
        if (pageType !== 'signup') {
            return;
        }
        document.querySelectorAll('.inline-status').forEach((node) => {
            const message = node.textContent ? node.textContent.trim() : '';
            if (!message) {
                return;
            }
            const type = node.classList.contains('error') ? 'error' : node.classList.contains('warning') ? 'warning' : node.classList.contains('info') ? 'info' : 'success';
            setTimeout(() => showToast(message, type), 350);
        });
    }

    ensureDecor();
    initCanvas();
    initTilt();
    initRipple();
    initEntrance();
    initStatusToasts();
})();
