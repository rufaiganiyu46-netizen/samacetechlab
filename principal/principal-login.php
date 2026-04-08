<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';

$currentUser = current_user();
if ($currentUser && (($currentUser['role'] ?? '') === 'principal')) {
    redirect('principal-dashboard.php');
}

$error = null;
$flash = get_flash();

if (is_post()) {
    $user = attempt_login((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''), 'principal', $error);

    if ($user) {
        set_flash('success', 'Welcome back, ' . (($user['first_name'] ?? '') ?: ($user['full_name'] ?? 'Principal')) . '.');
        redirect('principal-dashboard.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="dark">
    <title>Principal Login | SAMACE TECH LAB</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Playfair+Display:wght@400;700&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #0A0A0F;
            --bg-secondary: #0F0F1A;
            --bg-card: rgba(255,255,255,0.04);
            --border-glass: rgba(255,255,255,0.08);
            --accent-gold: #F5A623;
            --accent-teal: #00D4B4;
            --accent-blue: #4A9EFF;
            --text-primary: #FFFFFF;
            --text-secondary: #A0A8B8;
            --text-muted: #5A6070;
            --glow-gold: 0 0 40px rgba(245,166,35,0.3);
            --glow-teal: 0 0 40px rgba(0,212,180,0.3);
            --glow-blue: 0 0 40px rgba(74,158,255,0.3);
            --radius-card: 20px;
            --radius-btn: 50px;
            --font-display: 'Bebas Neue', cursive;
            --font-heading: 'Playfair Display', serif;
            --font-body: 'DM Sans', sans-serif;
            --transition: all 0.4s cubic-bezier(0.23, 1, 0.32, 1);
            --sidebar-bg: #0D0D18;
            --sidebar-width: 250px;
            --role-accent: #F5A623;
            --role-glow: rgba(245,166,35,0.3);
            --role-tint: rgba(245,166,35,0.08);
            --role-text: #0A0A0F;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #0A0A0F; }
        ::-webkit-scrollbar-thumb { background: rgba(245,166,35,0.4); border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--accent-gold); }
        ::selection { background: rgba(245,166,35,0.25); color: #fff; }

        body {
            font-family: var(--font-body);
            color: var(--text-primary);
            min-height: 100vh;
            background: var(--bg-primary);
            padding: 24px;
            overflow-x: hidden;
            position: relative;
        }

        a { color: inherit; text-decoration: none; }

        .orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(120px);
            z-index: 0;
            pointer-events: none;
            animation: orbDrift 20s ease-in-out infinite alternate;
        }

        .orb-gold {
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(245,166,35,0.12), transparent 70%);
            top: -100px;
            left: -100px;
        }

        .orb-teal {
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(0,212,180,0.08), transparent 70%);
            bottom: 0;
            right: 0;
            animation-direction: alternate-reverse;
            animation-duration: 25s;
        }

        .orb-blue {
            width: 350px;
            height: 350px;
            background: radial-gradient(circle, rgba(74,158,255,0.07), transparent 70%);
            top: 50%;
            left: 50%;
            animation-duration: 30s;
        }

        .login-shell {
            position: relative;
            z-index: 1;
            min-height: calc(100vh - 48px);
            display: grid;
            place-items: center;
        }

        .login-card {
            position: relative;
            width: min(100%, 460px);
            padding: 52px 44px 34px;
            border-radius: 24px;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.09);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            box-shadow: 0 32px 80px rgba(0,0,0,0.6);
            transform-style: preserve-3d;
            animation: cardReveal 0.7s cubic-bezier(0.23, 1, 0.32, 1) both;
            overflow: hidden;
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, transparent, var(--role-accent), transparent);
        }

        .login-card::after {
            content: '';
            position: absolute;
            inset: -40% 0 auto;
            height: 35%;
            background: linear-gradient(180deg, transparent, rgba(255,255,255,0.08), transparent);
            transform: translateY(-160%);
            animation: scanLine 4.5s linear infinite;
            pointer-events: none;
        }

        .corner-dot {
            position: absolute;
            width: 10px;
            height: 10px;
            border-radius: 999px;
            background: var(--role-accent);
            box-shadow: 0 0 20px var(--role-glow);
        }

        .corner-dot.tl { top: 18px; left: 18px; }
        .corner-dot.tr { top: 18px; right: 18px; }
        .corner-dot.bl { bottom: 18px; left: 18px; }
        .corner-dot.br { bottom: 18px; right: 18px; }

        .brand-lockup {
            text-align: center;
            margin-bottom: 26px;
        }

        .school-chip {
            width: 78px;
            height: 78px;
            margin: 0 auto 18px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: var(--role-tint);
            border: 1px solid rgba(255,255,255,0.08);
            font-size: 2rem;
            box-shadow: 0 0 30px var(--role-glow);
        }

        .school-name {
            font-family: var(--font-display);
            color: var(--accent-gold);
            font-size: 2rem;
            letter-spacing: 0.08em;
            line-height: 1;
        }

        .school-subtitle {
            margin-top: 6px;
            color: var(--text-muted);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.18em;
        }

        .portal-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-top: 16px;
            padding: 8px 14px;
            border-radius: 999px;
            background: var(--role-tint);
            border: 1px solid rgba(255,255,255,0.08);
            color: var(--role-accent);
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .card-title {
            font-family: var(--font-heading);
            color: var(--text-primary);
            font-size: clamp(2rem, 5vw, 2.125rem);
            line-height: 1.1;
            margin-bottom: 10px;
            text-align: center;
        }

        .card-subtitle {
            color: var(--text-secondary);
            margin-bottom: 24px;
            text-align: center;
            line-height: 1.75;
        }

        .inline-status {
            padding: 12px 14px;
            border-radius: 14px;
            margin-bottom: 18px;
            font-weight: 700;
            font-size: 0.92rem;
            border: 1px solid rgba(255,255,255,0.08);
        }

        .inline-status.error {
            background: rgba(255,71,87,0.12);
            color: #ff9aa5;
        }

        .inline-status.success {
            background: rgba(0,212,180,0.12);
            color: #9ff4e7;
        }

        .login-form {
            display: grid;
            gap: 16px;
        }

        .field-group {
            display: grid;
            gap: 8px;
        }

        .field-label {
            display: block;
            font-size: 0.68rem;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: var(--role-accent);
            font-weight: 700;
        }

        .glass-input,
        input,
        textarea,
        select {
            background: rgba(255,255,255,0.06) !important;
            border: 1px solid rgba(255,255,255,0.1) !important;
            border-radius: 14px !important;
            color: #fff !important;
            padding: 14px 18px !important;
            font-family: var(--font-body) !important;
            transition: var(--transition) !important;
            outline: none !important;
            width: 100%;
            min-height: 54px;
        }

        input:hover {
            background: rgba(255,255,255,0.07) !important;
        }

        input:focus,
        textarea:focus,
        select:focus {
            border-color: var(--role-accent) !important;
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--role-accent) 22%, transparent) !important;
        }

        input.error {
            border-color: #FF4757 !important;
            box-shadow: 0 0 0 3px rgba(255,71,87,0.14) !important;
        }

        ::placeholder { color: var(--text-muted) !important; }

        .password-wrap {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            top: 50%;
            right: 14px;
            transform: translateY(-50%);
            width: 34px;
            height: 34px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            color: var(--text-secondary);
            background: rgba(255,255,255,0.03);
        }

        .password-toggle:hover {
            color: var(--role-accent);
            background: var(--role-tint);
        }

        .form-meta {
            display: flex;
            justify-content: flex-end;
            margin-top: -4px;
        }

        .forgot-link {
            font-size: 0.75rem;
            color: var(--role-accent);
            opacity: 0.8;
            transition: var(--transition);
        }

        .forgot-link:hover {
            opacity: 1;
        }

        .submit-btn {
            width: 100%;
            min-height: 56px;
            border-radius: 14px;
            background: var(--role-accent);
            color: var(--role-text);
            font-family: var(--font-display);
            font-size: 1.25rem;
            letter-spacing: 0.12em;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: var(--transition);
            margin-top: 6px;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 30px var(--role-glow);
        }

        .submit-btn.loading {
            pointer-events: none;
            opacity: 0.9;
        }

        .spinner {
            width: 18px;
            height: 18px;
            border: 2px solid rgba(10,10,15,0.2);
            border-top-color: currentColor;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        .card-links {
            display: flex;
            justify-content: space-between;
            gap: 18px;
            margin-top: 18px;
            flex-wrap: wrap;
        }

        .card-links a {
            color: var(--text-muted);
            font-size: 0.82rem;
            transition: var(--transition);
        }

        .card-links a:hover {
            color: var(--role-accent);
        }

        .shake {
            animation: shake 0.36s ease;
        }

        @keyframes orbDrift {
            from { transform: translate(0,0) scale(1); }
            to { transform: translate(60px,40px) scale(1.1); }
        }

        @keyframes floatDot {
            0% { transform: translate(0, 0) scale(1); opacity: 0.6; }
            33% { transform: translate(15px, -20px) scale(1.2); opacity: 1; }
            66% { transform: translate(-10px, 10px) scale(0.8); opacity: 0.4; }
            100% { transform: translate(20px, -30px) scale(1); opacity: 0.7; }
        }

        @keyframes cardReveal {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes scanLine {
            from { transform: translateY(-180%); }
            to { transform: translateY(420%); }
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20% { transform: translateX(-8px); }
            40% { transform: translateX(6px); }
            60% { transform: translateX(-5px); }
            80% { transform: translateX(3px); }
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @media (max-width: 480px) {
            body { padding: 16px; }
            .login-shell { min-height: calc(100vh - 32px); }
            .login-card { padding: 44px 22px 28px; }
            .card-links { flex-direction: column; }
        }
    </style>
    <?php require_once dirname(__DIR__) . '/theme-shared.php'; ?>
    <link rel="stylesheet" href="assets/auth-shared.css">
</head>
<body class="auth-page" data-role="principal" data-page="login">
    <div class="orb orb-gold"></div>
    <div class="orb orb-teal"></div>
    <div class="orb orb-blue"></div>
    <div id="particles-container" style="position:fixed;inset:0;z-index:0;pointer-events:none;overflow:hidden;"></div>

    <main class="login-shell">
        <section class="login-card" id="loginCard">
            <span class="corner-dot tl"></span>
            <span class="corner-dot tr"></span>
            <span class="corner-dot bl"></span>
            <span class="corner-dot br"></span>

            <div class="brand-lockup">
                <div class="school-chip">🎓</div>
                <div class="school-name">SAMACE TECH LAB</div>
                <div class="school-subtitle">Nursery &amp; Primary School</div>
                <div class="portal-badge">Principal Portal</div>
            </div>

            <h1 class="card-title">Welcome Back</h1>
            <p class="card-subtitle">Sign in to access principal tools and features.</p>

            <?php if ($flash): ?>
                <div class="inline-status <?php echo e((string) $flash['type']); ?>"><?php echo e((string) $flash['message']); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="inline-status error"><?php echo e($error); ?></div>
            <?php endif; ?>
            <?php if ($db_error): ?>
                <div class="inline-status error"><?php echo e($db_error); ?></div>
            <?php endif; ?>

            <form method="post" class="login-form" id="loginForm" novalidate>
                <div class="field-group">
                    <label class="field-label" for="email">Email</label>
                    <input id="email" name="email" type="email" placeholder="Enter your email" value="<?php echo e((string) ($_POST['email'] ?? '')); ?>">
                </div>

                <div class="field-group">
                    <label class="field-label" for="password">Password</label>
                    <div class="password-wrap">
                        <input id="password" name="password" type="password" placeholder="Enter your password">
                        <button class="password-toggle" id="passwordToggle" type="button" aria-label="Toggle password visibility">👁️</button>
                    </div>
                </div>

                <div class="form-meta">
                    <a class="forgot-link" href="#">Forgot Password?</a>
                </div>

                <button class="submit-btn" id="submitButton" type="submit">
                    <span class="label">Login</span>
                </button>
            </form>

            <div class="card-links">
                <a href="index.php#portals">Principal account is managed internally</a>
                <a href="index.php">&larr; Back to Home</a>
            </div>
        </section>
    </main>

    <script>
        (function initParticles() {
            const container = document.getElementById('particles-container');
            if (!container || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
            const count = window.innerWidth < 768 ? 30 : 60;
            const colors = [
                'rgba(245,166,35,0.4)',
                'rgba(0,212,180,0.3)',
                'rgba(74,158,255,0.3)',
                'rgba(255,255,255,0.25)'
            ];
            for (let i = 0; i < count; i++) {
                const dot = document.createElement('span');
                const size = Math.random() * 4 + 2;
                dot.style.cssText = `
                    position:absolute;
                    width:${size}px;height:${size}px;
                    border-radius:50%;
                    background:${colors[Math.floor(Math.random() * colors.length)]};
                    top:${Math.random() * 100}%;
                    left:${Math.random() * 100}%;
                    animation:floatDot ${Math.random() * 15 + 8}s ease-in-out ${Math.random() * 5}s infinite alternate;
                `;
                container.appendChild(dot);
            }
        }());

        function showToast(message, type = 'success') {
            const colors = {
                success: 'var(--accent-teal)',
                error: '#FF4757',
                warning: 'var(--accent-gold)',
                info: 'var(--accent-blue)'
            };
            const toast = document.createElement('div');
            toast.style.cssText = `
                position: fixed; top: 24px; right: 24px; z-index: 9999;
                background: rgba(15,15,26,0.95);
                border: 1px solid rgba(255,255,255,0.1);
                border-left: 4px solid ${colors[type]};
                border-radius: 12px;
                padding: 16px 20px;
                color: #fff;
                font-family: var(--font-body);
                font-size: 14px;
                max-width: 320px;
                backdrop-filter: blur(20px);
                box-shadow: 0 20px 60px rgba(0,0,0,0.5);
                transform: translateX(120%);
                transition: transform 0.4s cubic-bezier(0.23,1,0.32,1);
            `;
            toast.textContent = message;
            document.body.appendChild(toast);
            requestAnimationFrame(() => { toast.style.transform = 'translateX(0)'; });
            setTimeout(() => {
                toast.style.transform = 'translateX(120%)';
                setTimeout(() => toast.remove(), 400);
            }, 3500);
        }

        (function initLoginPage() {
            const card = document.getElementById('loginCard');
            const form = document.getElementById('loginForm');
            const email = document.getElementById('email');
            const password = document.getElementById('password');
            const toggle = document.getElementById('passwordToggle');
            const submitButton = document.getElementById('submitButton');

            if (toggle && password) {
                toggle.addEventListener('click', () => {
                    const isPassword = password.getAttribute('type') === 'password';
                    password.setAttribute('type', isPassword ? 'text' : 'password');
                    toggle.textContent = isPassword ? '🙈' : '👁️';
                });
            }

            [email, password].forEach((field) => {
                if (!field) return;
                field.addEventListener('input', () => field.classList.remove('error'));
            });

            if (card && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                card.addEventListener('mousemove', (event) => {
                    const rect = card.getBoundingClientRect();
                    const rotateY = ((event.clientX - rect.left) / rect.width - 0.5) * 16;
                    const rotateX = ((event.clientY - rect.top) / rect.height - 0.5) * -16;
                    card.style.transform = `rotateX(${rotateX}deg) rotateY(${rotateY}deg)`;
                });
                card.addEventListener('mouseleave', () => {
                    card.style.transform = 'rotateX(0deg) rotateY(0deg)';
                });
            }

            if (form) {
                form.addEventListener('submit', (event) => {
                    const invalidFields = [email, password].filter((field) => !field || !field.value.trim());
                    if (invalidFields.length > 0) {
                        event.preventDefault();
                        invalidFields.forEach((field) => field.classList.add('error'));
                        card.classList.remove('shake');
                        void card.offsetWidth;
                        card.classList.add('shake');
                        showToast('Please fill in your email and password.', 'error');
                        return;
                    }

                    submitButton.classList.add('loading');
                    submitButton.innerHTML = '<span class="spinner"></span><span class="label">Signing In...</span>';
                });
            }

            <?php if ($flash): ?>showToast(<?php echo json_encode((string) $flash['message']); ?>, <?php echo json_encode((string) $flash['type']); ?>);<?php endif; ?>
            <?php if ($error): ?>showToast(<?php echo json_encode((string) $error); ?>, 'error');<?php endif; ?>
            <?php if ($db_error): ?>showToast(<?php echo json_encode((string) $db_error); ?>, 'error');<?php endif; ?>
        }());
    </script>
    <script src="assets/auth-shared.js"></script>
</body>
</html>
