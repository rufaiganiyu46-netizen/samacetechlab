<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$principal = require_role('principal');
$teacherId = (int) ($_GET['id'] ?? $_POST['teacher_id'] ?? 0);
$flash = get_flash();
$error = null;

if ($teacherId < 1) {
    set_flash('error', 'Teacher account was not found.');
    redirect('principal-dashboard.php?panel=teachersPanel');
}

$teacher = fetch_user_by_id($teacherId);
if ($teacher === null || (string) ($teacher['role'] ?? '') !== 'teacher') {
    set_flash('error', 'Teacher account was not found.');
    redirect('principal-dashboard.php?panel=teachersPanel');
}

if (is_post()) {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'save_teacher_details') {
        if (update_teacher_account_by_principal($teacherId, [
            'first_name' => (string) ($_POST['first_name'] ?? ''),
            'surname' => (string) ($_POST['last_name'] ?? ''),
            'teaching_class' => (string) ($_POST['teaching_class'] ?? ''),
            'teaching_subject' => (string) ($_POST['teaching_subject'] ?? ''),
        ], $error)) {
            set_flash('success', 'Teacher details updated successfully.');
            redirect('teacher-details.php?id=' . $teacherId);
        }
    }

    if ($action === 'approve_teacher_account') {
        $assignedClass = (string) ($_POST['teaching_class'] ?? '');

        if (update_teacher_account_by_principal($teacherId, [
            'first_name' => (string) ($_POST['first_name'] ?? ''),
            'surname' => (string) ($_POST['last_name'] ?? ''),
            'teaching_class' => $assignedClass,
            'teaching_subject' => (string) ($_POST['teaching_subject'] ?? ''),
        ], $error) && approve_teacher_account($teacherId, $assignedClass, $error)) {
            set_flash('success', 'Teacher approved and updated successfully.');
            redirect('teacher-details.php?id=' . $teacherId);
        }
    }
}

$teacher = fetch_user_by_id($teacherId) ?? $teacher;
$teacherName = display_name_from_user($teacher, 'Teacher');
$principalName = display_name_from_user($principal, 'Principal');
$firstName = (string) ($teacher['first_name'] ?? '');
$lastName = (string) ($teacher['surname'] ?? '');
$email = (string) ($teacher['email'] ?? '');
$teachingClass = (string) ($teacher['teaching_class'] ?? '');
$requestedClass = (string) ($teacher['requested_teaching_class'] ?? '');
$teachingSubject = (string) ($teacher['teaching_subject'] ?? '');
$approvalStatus = (string) ($teacher['approval_status'] ?? 'approved');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="dark">
    <title>Teacher Details | SAMACE TECH LAB</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@400;500;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <style>
        :root { --accent:#F5A623; --accent-soft:rgba(245,166,35,0.16); --bg:#0A0A0F; --panel:rgba(255,255,255,0.04); --border:rgba(255,255,255,0.09); --text:#fff; --muted:#A0A8B8; --soft:#5A6070; --sidebar-width:250px; }
        * { box-sizing:border-box; margin:0; padding:0; }
        body { min-height:100vh; font-family:'DM Sans',sans-serif; color:var(--text); background:var(--bg); overflow-x:hidden; position:relative; }
        .orb { position:fixed; border-radius:50%; filter:blur(140px); z-index:0; pointer-events:none; animation:orbDrift 20s ease-in-out infinite alternate; }
        .orb.one { width:700px; height:700px; background:radial-gradient(circle, rgba(245,166,35,0.12), transparent 70%); top:-150px; left:-80px; }
        .orb.two { width:440px; height:440px; background:radial-gradient(circle, rgba(0,212,180,0.08), transparent 70%); bottom:-130px; right:-70px; }
        .orb.three { width:340px; height:340px; background:radial-gradient(circle, rgba(74,158,255,0.08), transparent 70%); top:40%; right:14%; }
        #particles-container { position:fixed; inset:0; z-index:0; pointer-events:none; overflow:hidden; }
        @keyframes orbDrift { from { transform:translate(0,0) scale(1); } to { transform:translate(40px,30px) scale(1.06); } }
        @keyframes floatDot { 0% { transform:translate(0,0) scale(1); opacity:.5; } 33% { transform:translate(10px,-15px) scale(1.2); opacity:1; } 66% { transform:translate(-8px,8px) scale(.8); opacity:.3; } 100% { transform:translate(15px,-20px) scale(1); opacity:.6; } }
        .mobile-toggle { display:none; position:fixed; top:16px; left:16px; z-index:1200; width:48px; height:48px; border:0; border-radius:14px; background:rgba(13,13,24,0.95); color:var(--accent); box-shadow:0 18px 36px rgba(0,0,0,0.35); }
        .sidebar-overlay { position:fixed; inset:0; background:rgba(0,0,0,0.6); opacity:0; pointer-events:none; transition:opacity .25s ease; z-index:1050; }
        .layout { position:relative; z-index:1; min-height:100vh; display:flex; }
        .sidebar { width:var(--sidebar-width); position:fixed; inset:0 auto 0 0; z-index:1100; display:flex; flex-direction:column; justify-content:space-between; padding:24px 18px; background:rgba(13,13,24,0.95); border-right:1px solid rgba(255,255,255,0.06); backdrop-filter:blur(24px); -webkit-backdrop-filter:blur(24px); overflow-y:auto; transition:transform .25s ease; }
        .brand { padding:6px 8px 22px; }
        .brand small { display:block; color:#5A6070; text-transform:uppercase; letter-spacing:.16em; font-size:.76rem; font-weight:800; margin-bottom:6px; }
        .brand strong { display:block; color:var(--accent); font-family:'Bebas Neue',cursive; font-size:1.32rem; letter-spacing:.08em; }
        .role-badge { display:inline-flex; align-items:center; gap:6px; margin:0 8px 18px; padding:6px 12px; border-radius:999px; background:var(--accent-soft); border:1px solid rgba(245,166,35,0.22); color:var(--accent); font-size:10px; font-weight:700; letter-spacing:1px; text-transform:uppercase; }
        .nav-list { display:grid; gap:8px; }
        .nav-link { position:relative; display:flex; align-items:center; gap:12px; padding:14px 16px; border-radius:16px; color:#A0A8B8; text-decoration:none; transition:background .22s ease,color .22s ease; }
        .nav-link::before { content:''; position:absolute; left:-18px; top:10px; bottom:10px; width:4px; border-radius:999px; background:transparent; transition:background .22s ease; }
        .nav-link:hover, .nav-link.active { background:rgba(255,255,255,0.05); color:#fff; }
        .nav-link.active::before { background:var(--accent); }
        .nav-link.logout:hover { background:rgba(255,71,87,0.12); color:#ffb0b8; }
        .sidebar-footer { display:flex; align-items:center; gap:12px; padding:18px 8px 8px; border-top:1px solid rgba(255,255,255,0.06); }
        .sidebar-avatar { width:44px; height:44px; border-radius:50%; display:grid; place-items:center; background:linear-gradient(135deg, var(--accent), rgba(245,166,35,0.35)); color:#0A0A0F; font-weight:800; }
        .sidebar-footer strong { display:block; color:#fff; }
        .sidebar-footer span { display:block; color:#A0A8B8; font-size:.84rem; }
        .main { position:fixed; top:0; left:var(--sidebar-width); right:0; bottom:0; min-width:0; overflow-y:auto; overflow-x:hidden; padding:30px 20px; }
        .shell { width:min(100%, 920px); position:relative; margin:0; }
        .card { background:var(--panel); border:1px solid var(--border); border-radius:26px; padding:34px 28px; backdrop-filter:blur(24px); box-shadow:0 30px 80px rgba(0,0,0,0.55); }
        .topbar { display:flex; justify-content:space-between; gap:16px; align-items:flex-start; margin-bottom:24px; }
        .badge { display:inline-flex; align-items:center; gap:8px; padding:7px 14px; border-radius:999px; border:1px solid rgba(255,255,255,0.08); background:rgba(255,255,255,0.05); color:var(--accent); font-size:12px; font-weight:700; letter-spacing:1.2px; text-transform:uppercase; }
        h1 { font-family:'Playfair Display',serif; font-size:34px; margin-top:12px; }
        p { color:var(--muted); line-height:1.7; }
        .back-link { color:var(--accent); text-decoration:none; font-weight:700; }
        .status { padding:13px 16px; border-radius:14px; margin-bottom:16px; border:1px solid rgba(255,255,255,0.08); }
        .status.error { background:rgba(255,71,87,0.12); color:#ffb0b8; }
        .status.success { background:rgba(0,212,180,0.12); color:#aaf7ea; }
        .grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
        .field { display:grid; gap:8px; }
        .field.full { grid-column:1 / -1; }
        .label { font-size:12px; letter-spacing:1.6px; text-transform:uppercase; color:var(--accent); font-weight:700; }
        .input, .readonly { width:100%; min-height:52px; padding:14px 16px; border-radius:14px; border:1px solid rgba(255,255,255,0.1); background:rgba(255,255,255,0.05); color:var(--text); outline:none; }
        .input:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(245,166,35,0.08); }
        .readonly { color:var(--muted); display:flex; align-items:center; }
        .actions { display:flex; justify-content:flex-end; gap:12px; margin-top:24px; flex-wrap:wrap; }
        .btn { min-height:48px; padding:0 18px; border-radius:14px; border:none; cursor:pointer; font-weight:700; text-decoration:none; display:inline-flex; align-items:center; justify-content:center; }
        .btn.primary { background:linear-gradient(135deg, var(--accent), #e8950f); color:#0A0A0F; }
        .btn.secondary { background:rgba(255,255,255,0.08); color:#fff; }
        .btn.ghost { border:1px solid rgba(245,166,35,0.24); background:rgba(245,166,35,0.08); color:var(--accent); }
        @media (max-width: 768px) {
            .mobile-toggle { display:inline-flex; }
            .sidebar { transform:translateX(-100%); }
            body.sidebar-open .sidebar { transform:translateX(0); }
            body.sidebar-open .sidebar-overlay { opacity:1; pointer-events:auto; }
            .main { left:0; padding:82px 16px 20px; }
        }
        @media (max-width: 640px) { .card { padding:28px 20px; } .grid { grid-template-columns:1fr; } .topbar { flex-direction:column; } .actions { flex-direction:column; } }
    </style>
    <?php require_once __DIR__ . '/theme-shared.php'; ?>
</head>
<body>
    <div class="orb one"></div>
    <div class="orb two"></div>
    <div class="orb three"></div>
    <div id="particles-container"></div>
    <button class="mobile-toggle" id="sidebarToggle" type="button" aria-label="Toggle sidebar">☰</button>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="layout">
        <aside class="sidebar" id="sidebar">
            <div>
                <div class="brand"><small>SAMACE TECH LAB</small><strong>Principal Suite</strong></div>
                <span class="role-badge">👩‍🏫 Teacher Details</span>
                <nav class="nav-list">
                    <a class="nav-link" href="principal-dashboard.php"><span>🏠</span><span>Dashboard</span></a>
                    <a class="nav-link" href="chat.php"><span>💬</span><span>Chats</span></a>
                    <a class="nav-link" href="principalent.php"><span>📢</span><span>Announcements</span></a>
                    <a class="nav-link active" href="principal-dashboard.php?panel=teachersPanel"><span>👩‍🏫</span><span>Teachers</span></a>
                    <a class="nav-link" href="principal-dashboard.php?panel=parentsPanel"><span>👨‍👩‍👧</span><span>Parents</span></a>
                    <a class="nav-link" href="profile.php"><span>👤</span><span>Profile</span></a>
                    <a class="nav-link logout" href="logout.php"><span>🚪</span><span>Logout</span></a>
                </nav>
            </div>
            <div class="sidebar-footer">
                <div class="sidebar-avatar"><?php echo e(strtoupper(substr($principalName, 0, 1))); ?></div>
                <div><strong><?php echo e($principalName); ?></strong><span>Principal</span></div>
            </div>
        </aside>
        <main class="main">
            <div class="shell">
                <section class="card">
                    <div class="topbar">
                        <div>
                            <span class="badge">👩‍🏫 Teacher Details</span>
                            <h1><?php echo e($teacherName); ?></h1>
                            <p>Edit teacher name, class, and position from the principal portal, then save the changes.</p>
                        </div>
                        <a class="back-link" href="principal-dashboard.php?panel=teachersPanel">Back to teachers</a>
                    </div>

                    <?php if ($flash): ?>
                        <div class="status <?php echo e((string) ($flash['type'] ?? 'success')); ?>"><?php echo e((string) $flash['message']); ?></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="status error"><?php echo e($error); ?></div>
                    <?php endif; ?>
                    <?php if ($db_error): ?>
                        <div class="status error"><?php echo e($db_error); ?></div>
                    <?php endif; ?>

                    <form method="post">
                        <input type="hidden" name="teacher_id" value="<?php echo e((string) $teacherId); ?>">
                        <div class="grid">
                            <label class="field">
                                <span class="label">First Name</span>
                                <input class="input" type="text" name="first_name" value="<?php echo e($firstName); ?>" required>
                            </label>
                            <label class="field">
                                <span class="label">Last Name</span>
                                <input class="input" type="text" name="last_name" value="<?php echo e($lastName); ?>" required>
                            </label>
                            <div class="field full">
                                <span class="label">Email Address</span>
                                <div class="readonly"><?php echo e($email !== '' ? $email : 'No email address'); ?></div>
                            </div>
                            <label class="field">
                                <span class="label">Assigned Class</span>
                                <input class="input" type="text" name="teaching_class" value="<?php echo e($teachingClass !== '' ? $teachingClass : $requestedClass); ?>" required>
                            </label>
                            <label class="field">
                                <span class="label">Position / Subject</span>
                                <input class="input" type="text" name="teaching_subject" value="<?php echo e($teachingSubject); ?>">
                            </label>
                            <div class="field full">
                                <span class="label">Requested Class</span>
                                <div class="readonly"><?php echo e($requestedClass !== '' ? $requestedClass : 'No class preference submitted'); ?></div>
                            </div>
                            <div class="field full">
                                <span class="label">Approval Status</span>
                                <div class="readonly"><?php echo e(ucfirst($approvalStatus)); ?></div>
                            </div>
                        </div>

                        <div class="actions">
                            <a class="btn secondary" href="principal-dashboard.php?panel=teachersPanel">Cancel</a>
                            <?php if ($approvalStatus === 'pending'): ?>
                                <button class="btn ghost" type="submit" name="action" value="approve_teacher_account">Approve &amp; Save</button>
                            <?php endif; ?>
                            <button class="btn primary" type="submit" name="action" value="save_teacher_details">Save Changes</button>
                        </div>
                    </form>
                </section>
            </div>
        </main>
    </div>

    <script>
        (function initParticles() {
            const container = document.getElementById('particles-container');
            if (!container) return;
            const count = window.innerWidth < 768 ? 25 : 55;
            const colors = ['rgba(245,166,35,0.45)', 'rgba(0,212,180,0.35)', 'rgba(74,158,255,0.3)', 'rgba(255,255,255,0.2)'];
            for (let index = 0; index < count; index += 1) {
                const dot = document.createElement('span');
                const size = Math.random() * 3 + 1.5;
                dot.style.cssText = `position:absolute; width:${size}px; height:${size}px; border-radius:50%; background:${colors[Math.floor(Math.random() * colors.length)]}; top:${Math.random() * 100}%; left:${Math.random() * 100}%; animation:floatDot ${Math.random() * 14 + 8}s ease-in-out ${Math.random() * 5}s infinite alternate;`;
                container.appendChild(dot);
            }
        }());

        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        if (sidebarToggle && sidebarOverlay) {
            sidebarToggle.addEventListener('click', () => document.body.classList.toggle('sidebar-open'));
            sidebarOverlay.addEventListener('click', () => document.body.classList.remove('sidebar-open'));
        }
    </script>
</body>
</html>