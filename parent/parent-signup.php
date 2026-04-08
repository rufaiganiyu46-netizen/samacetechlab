<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';

$currentUser = current_user();
if ($currentUser && (($currentUser['role'] ?? '') === 'parent')) {
    redirect('parent-dashboard.php');
}

$error = null;

if (is_post()) {
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        $created = create_user([
            'first_name' => (string) ($_POST['first_name'] ?? ''),
            'surname' => (string) ($_POST['last_name'] ?? ''),
            'email' => (string) ($_POST['email'] ?? ''),
            'password' => $password,
            'role' => 'parent',
            'child_count' => (int) ($_POST['child_count'] ?? 0),
            'child_details' => (string) ($_POST['child_details'] ?? ''),
        ], $error);

        if ($created) {
            set_flash('success', 'Parent registration submitted successfully. Wait for principal approval before logging in.');
            redirect('parent-login.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="dark">
    <title>Parent Sign Up | SAMACE TECH LAB</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Playfair+Display:wght@700;800&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #0A0A0F;
            --bg-card: rgba(255,255,255,0.04);
            --border-glass: rgba(255,255,255,0.09);
            --text-primary: #FFFFFF;
            --text-secondary: #A0A8B8;
            --text-muted: #5A6070;
            --role-accent: #00D4B4;
            --role-glow: rgba(0,212,180,0.3);
            --role-tint: rgba(0,212,180,0.08);
            --font-display: 'Bebas Neue', cursive;
            --font-heading: 'Playfair Display', serif;
            --font-body: 'DM Sans', sans-serif;
            --transition: all 0.35s cubic-bezier(0.23,1,0.32,1);
        }

        * { margin:0; padding:0; box-sizing:border-box; }

        body {
            background: #0A0A0F;
            min-height: 100vh;
            font-family: var(--font-body);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            overflow-x: hidden;
            position: relative;
        }

        .orb { position: fixed; border-radius: 50%; filter: blur(130px); z-index: 0; pointer-events: none; animation: orbDrift 20s ease-in-out infinite alternate; }
        .orb-gold { width: 600px; height: 600px; background: radial-gradient(circle, rgba(245,166,35,0.13), transparent 70%); top: -200px; left: -150px; animation-duration: 18s; }
        .orb-teal { width: 450px; height: 450px; background: radial-gradient(circle, rgba(0,212,180,0.09), transparent 70%); bottom: -100px; right: -100px; animation-direction: alternate-reverse; animation-duration: 24s; }
        .orb-blue { width: 350px; height: 350px; background: radial-gradient(circle, rgba(74,158,255,0.07), transparent 70%); top: 50%; left: 55%; animation-duration: 30s; }
        #particles-container { position: fixed; inset: 0; z-index: 0; pointer-events: none; overflow: hidden; }

        .register-wrapper { position: relative; z-index: 10; width: 100%; max-width: 560px; padding: 20px; }
        .register-card {
            background: var(--bg-card);
            border: 1px solid var(--border-glass);
            border-radius: 24px;
            padding: 52px 48px;
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            box-shadow: 0 0 0 1px rgba(0,212,180,0.07), 0 32px 80px rgba(0,0,0,0.6), inset 0 1px 0 rgba(255,255,255,0.06);
            position: relative;
            overflow: hidden;
            transform-style: preserve-3d;
            animation: cardReveal 0.7s cubic-bezier(0.23,1,0.32,1) both;
            transition: transform 0.25s ease-out;
        }
        .register-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, transparent, rgba(0,212,180,0.9) 30%, rgba(74,158,255,0.45) 70%, transparent);
            border-radius: 24px 24px 0 0;
        }
        .register-card::after {
            content: '';
            position: absolute;
            top: -60px;
            left: 50%;
            transform: translateX(-50%);
            width: 250px;
            height: 130px;
            background: radial-gradient(circle, rgba(0,212,180,0.07), transparent 70%);
            pointer-events: none;
        }
        .corner-dot { position: absolute; width: 6px; height: 6px; border-radius: 50%; animation: cornerPulse 2s ease-in-out infinite alternate; }
        .corner-dot.tl { top:16px; left:16px; background:#F5A623; box-shadow:0 0 10px rgba(245,166,35,0.8); }
        .corner-dot.tr { top:16px; right:16px; background:#00D4B4; box-shadow:0 0 10px rgba(0,212,180,0.8); animation-delay:0.5s; }
        .corner-dot.bl { bottom:16px; left:16px; background:#4A9EFF; box-shadow:0 0 10px rgba(74,158,255,0.8); animation-delay:1s; }
        .corner-dot.br { bottom:16px; right:16px; background:#00D4B4; box-shadow:0 0 10px rgba(0,212,180,0.8); animation-delay:1.5s; }
        .scan-line { position:absolute; left:0; right:0; height:1px; background:linear-gradient(90deg, transparent, rgba(0,212,180,0.35), transparent); animation: scanLine 4s ease-in-out infinite; pointer-events:none; z-index:1; }

        .school-brand { display:flex; align-items:center; gap:12px; margin-bottom:20px; position:relative; z-index:2; }
        .brand-icon {
            width: 46px; height: 46px; background: linear-gradient(135deg, rgba(0,212,180,0.2), rgba(0,212,180,0.05)); border: 1px solid rgba(0,212,180,0.3);
            border-radius: 12px; display:flex; align-items:center; justify-content:center; font-size:22px; box-shadow:0 0 20px rgba(0,212,180,0.15);
        }
        .brand-name { font-family: var(--font-display); font-size:18px; color:var(--role-accent); letter-spacing:2px; display:block; }
        .brand-sub { font-size:10px; color:#5A6070; letter-spacing:2px; text-transform:uppercase; display:block; }
        .portal-badge {
            display:inline-flex; align-items:center; gap:6px; background:rgba(0,212,180,0.1); border:1px solid rgba(0,212,180,0.25); color:var(--role-accent);
            font-size:11px; font-weight:600; letter-spacing:1.5px; text-transform:uppercase; padding:6px 14px; border-radius:50px; margin-bottom:20px; position:relative; z-index:2;
        }
        .card-title { font-family: var(--font-heading); font-size:32px; font-weight:700; color:#fff; margin-bottom:8px; line-height:1.2; position:relative; z-index:2; }
        .card-subtitle { font-size:14px; color:var(--text-secondary); line-height:1.6; margin-bottom:24px; position:relative; z-index:2; }
        .inline-status { padding:12px 14px; border-radius:14px; margin-bottom:18px; font-weight:700; border:1px solid rgba(255,255,255,0.08); position:relative; z-index:2; }
        .inline-status.error { background:rgba(255,71,87,0.12); color:#ff9aa5; }
        form { position:relative; z-index:2; }

        .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px; }
        .form-group { display:flex; flex-direction:column; gap:8px; }
        .form-group.full { grid-column:1 / -1; }
        .form-help { font-size:12px; color:var(--text-secondary); line-height:1.5; }
        .form-label { font-size:11px; font-weight:600; letter-spacing:2px; text-transform:uppercase; color:var(--role-accent); }
        .form-input {
            width:100%; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); border-radius:14px; color:#fff; padding:14px 18px;
            font-family: var(--font-body); font-size:14px; transition:var(--transition); outline:none; min-height:52px;
        }
        .form-input::placeholder { color:#5A6070; }
        .form-input:focus { border-color:var(--role-accent); background:rgba(0,212,180,0.05); box-shadow:0 0 0 3px rgba(0,212,180,0.12), 0 8px 25px rgba(0,0,0,0.3); transform:translateY(-1px); }
        .form-input:hover:not(:focus) { border-color:rgba(255,255,255,0.2); background:rgba(255,255,255,0.07); }
        textarea.form-input { min-height:120px; resize:vertical; }
        .input-wrapper { position:relative; }
        .eye-toggle { position:absolute; right:14px; top:50%; transform:translateY(-50%); background:none; border:none; color:#5A6070; cursor:pointer; font-size:17px; transition:color .3s; padding:4px; }
        .eye-toggle:hover { color:var(--role-accent); }
        .strength-bar { height:3px; border-radius:3px; background:rgba(255,255,255,0.08); margin-top:8px; overflow:hidden; }
        .strength-fill { height:100%; border-radius:3px; width:0%; transition:width .4s ease, background .4s ease; }
        .strength-text { font-size:11px; color:#5A6070; margin-top:4px; text-align:right; }

        .btn-register {
            width:100%; margin-top:8px; background:linear-gradient(135deg, #00D4B4, #009d87); color:#0A0A0F; border:none; border-radius:14px; padding:16px;
            font-family: var(--font-display); font-size:20px; letter-spacing:2px; cursor:pointer; transition:var(--transition); position:relative; overflow:hidden; margin-bottom:24px;
        }
        .btn-register::before { content:''; position:absolute; inset:0; background:linear-gradient(135deg, rgba(255,255,255,0.15), transparent); opacity:0; transition:opacity .3s; }
        .btn-register:hover { transform:translateY(-2px); box-shadow:0 0 40px rgba(0,212,180,0.4), 0 12px 30px rgba(0,0,0,0.4); }
        .btn-register:hover::before { opacity:1; }
        .btn-register:active { transform:translateY(0) scale(0.99); }
        .btn-register.loading { pointer-events:none; opacity:.7; }

        .card-footer-links { display:flex; justify-content:space-between; align-items:center; gap:12px; position:relative; z-index:2; }
        .footer-link { font-size:13px; color:#A0A8B8; text-decoration:none; transition:color .3s; display:flex; align-items:center; gap:5px; }
        .footer-link:hover { color:var(--role-accent); }
        .footer-link.primary { color:var(--role-accent); font-weight:600; }

        @keyframes orbDrift { from { transform:translate(0,0) scale(1); } to { transform:translate(50px,35px) scale(1.08); } }
        @keyframes floatDot {
            0% { transform: translate(0,0) scale(1); opacity: 0.5; }
            33% { transform: translate(12px,-18px) scale(1.3); opacity: 1; }
            66% { transform: translate(-8px,10px) scale(0.7); opacity: 0.3; }
            100% { transform: translate(18px,-25px) scale(1); opacity: 0.6; }
        }
        @keyframes cardReveal { from { opacity:0; transform:translateY(30px) scale(.97); } to { opacity:1; transform:translateY(0) scale(1); } }
        @keyframes cornerPulse { from { opacity:.4; transform:scale(1); } to { opacity:1; transform:scale(1.6); } }
        @keyframes scanLine { 0% { top:0%; opacity:0; } 10% { opacity:1; } 90% { opacity:1; } 100% { top:100%; opacity:0; } }
        @keyframes shake { 0%,100% { transform:translateX(0); } 20% { transform:translateX(-8px); } 40% { transform:translateX(8px); } 60% { transform:translateX(-5px); } 80% { transform:translateX(5px); } }
        .shake { animation: shake .5s ease; border-color:#FF4757 !important; }

        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #0A0A0F; }
        ::-webkit-scrollbar-thumb { background: rgba(0,212,180,0.4); border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #00D4B4; }
        ::selection { background: rgba(0,212,180,0.25); color: #fff; }

        @media (max-width: 600px) {
            body { align-items:flex-start; padding:30px 16px 60px; overflow-y:auto; }
            .register-wrapper { padding:0; }
            .register-card { padding:36px 24px; border-radius:20px; }
            .card-title { font-size:26px; }
            .form-grid { grid-template-columns:1fr; }
            .card-footer-links { flex-direction:column; gap:12px; text-align:center; }
            .orb-gold { width:280px; height:280px; }
            .orb-teal { width:220px; height:220px; }
            .orb-blue { display:none; }
        }
    </style>
    <?php require_once dirname(__DIR__) . '/theme-shared.php'; ?>
    <link rel="stylesheet" href="assets/auth-shared.css">
</head>
<body class="auth-page" data-role="parent" data-page="signup">
    <div class="orb orb-gold"></div>
    <div class="orb orb-teal"></div>
    <div class="orb orb-blue"></div>
    <div id="particles-container"></div>

    <main class="register-wrapper">
        <section class="register-card">
            <div class="corner-dot tl"></div>
            <div class="corner-dot tr"></div>
            <div class="corner-dot bl"></div>
            <div class="corner-dot br"></div>
            <div class="scan-line"></div>

            <div class="school-brand">
                <div class="brand-icon">🎓</div>
                <div class="brand-text">
                    <span class="brand-name">SAMACE TECH LAB</span>
                    <span class="brand-sub">Nursery &amp; Primary School</span>
                </div>
            </div>

            <div class="portal-badge">👨‍👩‍👧 Parent Portal</div>
            <h1 class="card-title">Create Account</h1>
            <p class="card-subtitle">Register as a parent to stay connected with your child's school journey.</p>

            <?php if ($error): ?>
                <div class="inline-status error"><?php echo e($error); ?></div>
            <?php endif; ?>
            <?php if ($db_error): ?>
                <div class="inline-status error"><?php echo e($db_error); ?></div>
            <?php endif; ?>

            <form method="post" novalidate>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="first_name">First Name</label>
                        <input class="form-input" id="first_name" name="first_name" type="text" placeholder="First name" value="<?php echo e((string) ($_POST['first_name'] ?? '')); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="last_name">Last Name</label>
                        <input class="form-input" id="last_name" name="last_name" type="text" placeholder="Last name" value="<?php echo e((string) ($_POST['last_name'] ?? '')); ?>" required>
                    </div>
                    <div class="form-group full">
                        <label class="form-label" for="email">Email Address</label>
                        <input class="form-input" id="email" name="email" type="email" placeholder="Enter your email" value="<?php echo e((string) ($_POST['email'] ?? '')); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="child_count">Children In Our School</label>
                        <input class="form-input" id="child_count" name="child_count" type="number" min="1" step="1" placeholder="How many children?" value="<?php echo e((string) ($_POST['child_count'] ?? '')); ?>" required>
                    </div>
                    <div class="form-group full">
                        <label class="form-label" for="child_details">Children and Classes</label>
                        <textarea class="form-input" id="child_details" name="child_details" placeholder="Example:
Akin in JSS1
Bola in Primary 4" required><?php echo e((string) ($_POST['child_details'] ?? '')); ?></textarea>
                        <div class="form-help">Write one child per line with the class. Example: Akin in JSS1.</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="password">Password</label>
                        <div class="input-wrapper">
                            <input class="form-input" id="password" name="password" type="password" placeholder="Create password" required>
                            <button class="eye-toggle" type="button" aria-label="Toggle password visibility">👁️</button>
                        </div>
                        <div class="strength-bar"><div class="strength-fill" id="strength-fill"></div></div>
                        <div class="strength-text" id="strength-text">Enter a password</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="confirm_password">Confirm Password</label>
                        <div class="input-wrapper">
                            <input class="form-input" id="confirm_password" name="confirm_password" type="password" placeholder="Confirm password" required>
                            <button class="eye-toggle" type="button" aria-label="Toggle confirm password visibility">👁️</button>
                        </div>
                    </div>
                </div>
                <button class="btn-register" type="submit">Sign Up</button>
            </form>

            <div class="card-footer-links">
                <a class="footer-link primary" href="parent-login.php">Already have an account?</a>
                <a class="footer-link" href="index.php">Back to Home</a>
            </div>
        </section>
    </main>

    <script>
        (function initParticles() {
            const container = document.getElementById('particles-container');
            if (!container) return;
            const count = window.innerWidth < 768 ? 25 : 50;
            const colors = ['rgba(245,166,35,0.5)', 'rgba(0,212,180,0.4)', 'rgba(74,158,255,0.35)', 'rgba(255,255,255,0.3)'];
            for (let index = 0; index < count; index += 1) {
                const dot = document.createElement('span');
                const size = Math.random() * 3.5 + 1.5;
                dot.style.cssText = `
                    position:absolute; width:${size}px; height:${size}px;
                    border-radius:50%; background:${colors[Math.floor(Math.random() * colors.length)]};
                    top:${Math.random() * 100}%; left:${Math.random() * 100}%;
                    animation:floatDot ${Math.random() * 14 + 8}s ease-in-out ${Math.random() * 6}s infinite alternate;
                `;
                container.appendChild(dot);
            }
        })();

        document.querySelector('#password')?.addEventListener('input', function() {
            const val = this.value;
            const fill = document.getElementById('strength-fill');
            const text = document.getElementById('strength-text');
            let score = 0;
            if (val.length >= 8) score += 1;
            if (/[A-Z]/.test(val)) score += 1;
            if (/[0-9]/.test(val)) score += 1;
            if (/[^A-Za-z0-9]/.test(val)) score += 1;
            const levels = [
                { w:'0%', c:'transparent', t:'Enter a password' },
                { w:'25%', c:'#FF4757', t:'Weak' },
                { w:'50%', c:'#F5A623', t:'Fair' },
                { w:'75%', c:'#4A9EFF', t:'Good' },
                { w:'100%', c:'#00D4B4', t:'Strong 💪' }
            ];
            if (!fill || !text) return;
            fill.style.width = levels[score].w;
            fill.style.background = levels[score].c;
            text.textContent = levels[score].t;
            text.style.color = levels[score].c;
        });

        const card = document.querySelector('.register-card');
        document.addEventListener('mousemove', (event) => {
            if (!card || window.innerWidth < 768) return;
            const rect = card.getBoundingClientRect();
            const centerX = rect.left + rect.width / 2;
            const centerY = rect.top + rect.height / 2;
            const rotateX = ((event.clientY - centerY) / window.innerHeight) * -6;
            const rotateY = ((event.clientX - centerX) / window.innerWidth) * 6;
            card.style.transform = `perspective(1200px) rotateX(${rotateX}deg) rotateY(${rotateY}deg)`;
        });
        document.addEventListener('mouseleave', () => {
            if (card) card.style.transform = 'perspective(1200px) rotateX(0) rotateY(0)';
        });

        document.querySelectorAll('.eye-toggle').forEach((button) => {
            button.addEventListener('click', function() {
                const input = this.previousElementSibling;
                if (!input) return;
                const isText = input.type === 'text';
                input.type = isText ? 'password' : 'text';
                this.textContent = isText ? '👁️' : '🙈';
            });
        });

        document.querySelector('form')?.addEventListener('submit', function(event) {
            let valid = true;
            this.querySelectorAll('[required]').forEach((field) => {
                if (!field.value.trim()) {
                    field.classList.add('shake');
                    field.style.borderColor = '#FF4757';
                    window.setTimeout(() => {
                        field.classList.remove('shake');
                        field.style.borderColor = '';
                    }, 600);
                    valid = false;
                }
            });

            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            if (password && confirmPassword && password.value !== confirmPassword.value) {
                confirmPassword.classList.add('shake');
                confirmPassword.style.borderColor = '#FF4757';
                window.setTimeout(() => {
                    confirmPassword.classList.remove('shake');
                    confirmPassword.style.borderColor = '';
                }, 600);
                showToast('Passwords do not match', 'error');
                valid = false;
            }

            if (!valid) {
                event.preventDefault();
                showToast('Please fill in all required fields', 'error');
                return;
            }

            const button = document.querySelector('.btn-register');
            if (button) {
                button.classList.add('loading');
                button.textContent = 'Creating Account...';
            }
        });

        document.querySelectorAll('.form-input').forEach((input) => {
            input.addEventListener('input', function() {
                this.style.borderColor = '';
            });
        });

        function showToast(message, type = 'success') {
            const colors = { success:'#00D4B4', error:'#FF4757', warning:'#F5A623', info:'#4A9EFF' };
            const toast = document.createElement('div');
            toast.style.cssText = `
                position:fixed; top:24px; right:24px; z-index:9999;
                background:rgba(15,15,26,0.95); border:1px solid rgba(255,255,255,0.1);
                border-left:4px solid ${colors[type]}; border-radius:12px; padding:16px 20px;
                color:#fff; font-family:'DM Sans',sans-serif; font-size:14px; max-width:320px;
                backdrop-filter:blur(20px); box-shadow:0 20px 60px rgba(0,0,0,0.5);
                transform:translateX(120%); transition:transform .4s cubic-bezier(0.23,1,0.32,1);
            `;
            toast.textContent = message;
            document.body.appendChild(toast);
            window.requestAnimationFrame(() => { toast.style.transform = 'translateX(0)'; });
            window.setTimeout(() => {
                toast.style.transform = 'translateX(120%)';
                window.setTimeout(() => toast.remove(), 400);
            }, 3500);
        }
    </script>
    <script src="assets/auth-shared.js"></script>
</body>
</html>
