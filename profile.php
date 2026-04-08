<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$user = current_user();
if (!$user) {
    set_flash('error', 'Please log in first.');
    redirect('index.php');
}

$freshUser = fetch_user_by_id((int) ($user['id'] ?? 0));
if ($freshUser) {
    login_user($freshUser);
    $user = $freshUser;
}

$role = (string) ($user['role'] ?? 'parent');
$roleConfig = [
    'principal' => ['accent' => '#F5A623', 'glow' => 'rgba(245,166,35,0.3)', 'badge' => 'Principal Profile', 'dashboard' => 'principal-dashboard.php'],
    'teacher' => ['accent' => '#4A9EFF', 'glow' => 'rgba(74,158,255,0.3)', 'badge' => 'Teacher Profile', 'dashboard' => 'teacher-dashboard.php'],
    'parent' => ['accent' => '#00D4B4', 'glow' => 'rgba(0,212,180,0.3)', 'badge' => 'Parent Profile', 'dashboard' => 'parent-dashboard.php'],
];
$currentRole = $roleConfig[$role] ?? $roleConfig['parent'];
$flash = get_flash();
$error = null;
$chatUnreadCount = 0;
$pendingTeacherCount = 0;
$pendingParentCount = 0;
$pendingLeaveRequestCount = 0;
$sidebarLinks = [
    'principal' => [
        ['label' => 'Dashboard', 'href' => 'principal-dashboard.php', 'icon' => '🏠'],
        ['label' => 'Chats', 'href' => 'chat.php', 'icon' => '💬'],
        ['label' => 'Announcements', 'href' => 'principalent.php', 'icon' => '📢'],
        ['label' => 'Teachers', 'href' => 'principal-dashboard.php?panel=teachersPanel', 'icon' => '👩‍🏫'],
        ['label' => 'Parents', 'href' => 'principal-dashboard.php?panel=parentsPanel', 'icon' => '👨‍👩‍👧'],
    ],
    'teacher' => [
        ['label' => 'Dashboard', 'href' => 'teacher-dashboard.php', 'icon' => '🏠'],
        ['label' => 'Chat', 'href' => 'teacher-dashboard.php?panel=chat', 'icon' => '💬'],
        ['label' => 'Announcements', 'href' => 'teacher-dashboard.php?panel=announcement', 'icon' => '📢'],
    ],
    'parent' => [
        ['label' => 'Dashboard', 'href' => 'parent-dashboard.php', 'icon' => '🏠'],
        ['label' => 'Chat', 'href' => 'parent-dashboard.php?panel=chat', 'icon' => '💬'],
        ['label' => 'Announcements', 'href' => 'parent-dashboard.php?panel=announcement', 'icon' => '📢'],
    ],
];

if ($role === 'principal') {
    $principal = $user;
    $pendingLeaveRequestCount = count_pending_teacher_leave_requests();

    if (db_ready() && $db instanceof mysqli) {
        $pendingTeacherCountResult = $db->query("SELECT COUNT(*) AS total FROM users WHERE role = 'teacher' AND approval_status = 'pending'");
        if ($pendingTeacherCountResult) {
            $pendingTeacherCount = (int) ($pendingTeacherCountResult->fetch_assoc()['total'] ?? 0);
            $pendingTeacherCountResult->free();
        }

        $pendingParentCountResult = $db->query("SELECT COUNT(*) AS total FROM users WHERE role = 'parent' AND approval_status = 'pending'");
        if ($pendingParentCountResult) {
            $pendingParentCount = (int) ($pendingParentCountResult->fetch_assoc()['total'] ?? 0);
            $pendingParentCountResult->free();
        }
    }

    foreach (fetch_support_threads_for_principal((int) ($principal['id'] ?? 0), 'teacher') as $thread) {
        $chatUnreadCount += (int) ($thread['unread_count'] ?? 0);
    }

    foreach (fetch_support_threads_for_principal((int) ($principal['id'] ?? 0), 'parent') as $thread) {
        $chatUnreadCount += (int) ($thread['unread_count'] ?? 0);
    }
}

if (is_post()) {
    if (update_user_profile((int) ($user['id'] ?? 0), [
        'first_name' => (string) ($_POST['first_name'] ?? ''),
        'surname' => (string) ($_POST['last_name'] ?? ''),
        'email' => (string) ($_POST['email'] ?? ''),
        'child_name' => (string) ($_POST['child_name'] ?? ''),
        'requested_teaching_class' => (string) ($_POST['requested_teaching_class'] ?? ''),
    ], $error, $_FILES['profile_picture'] ?? null)) {
        set_flash('success', 'Profile updated successfully.');
        redirect('profile.php');
    }
}

$displayName = display_name_from_user($user, 'User');
$firstName = (string) ($user['first_name'] ?? '');
$lastName = (string) ($user['surname'] ?? '');
$email = (string) ($user['email'] ?? '');
$childName = (string) ($user['child_name'] ?? '');
$requestedTeachingClass = (string) ($user['requested_teaching_class'] ?? '');
$assignedTeachingClass = (string) ($user['teaching_class'] ?? '');
$approvalStatus = (string) ($user['approval_status'] ?? 'approved');
$profilePictureUrl = user_profile_picture_url($user);
$profileInitials = initials_from_name($displayName, 'User');
$startInEditMode = $error !== null;
$bodyClasses = [];

if ($role === 'teacher') {
    $bodyClasses[] = 'teacher-profile-page';
}

if ($role === 'principal') {
    $bodyClasses[] = 'principal-page';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="dark">
    <title>Profile | SAMACE TECH LAB</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@400;500;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <style>
        :root { --accent: <?php echo e($currentRole['accent']); ?>; --glow: <?php echo e($currentRole['glow']); ?>; --bg:#0A0A0F; --card:rgba(255,255,255,0.04); --border:rgba(255,255,255,0.09); --text:#fff; --muted:#A0A8B8; --soft:#5A6070; --sidebar-width:250px; }
        * { box-sizing:border-box; margin:0; padding:0; }
        body { min-height:100vh; font-family:'DM Sans',sans-serif; color:var(--text); background:var(--bg); overflow-x:hidden; position:relative; }
        html, body.teacher-profile-page { height:100%; overflow:hidden; }
        body.teacher-profile-page { display:flex; overflow:hidden; }
        .orb { position:fixed; border-radius:50%; filter:blur(140px); z-index:0; pointer-events:none; animation:orbDrift 20s ease-in-out infinite alternate; }
        body.teacher-profile-page .orb { z-index:3; }
        .orb.one { width:700px; height:700px; background:radial-gradient(circle, var(--glow), transparent 70%); top:-140px; left:-100px; }
        .orb.two { width:420px; height:420px; background:radial-gradient(circle, rgba(255,255,255,0.08), transparent 70%); bottom:-120px; right:-80px; }
        .orb.three { width:340px; height:340px; background:radial-gradient(circle, rgba(74,158,255,0.08), transparent 70%); top:45%; right:12%; }
        body.teacher-profile-page .orb.one { width:700px; height:700px; background:radial-gradient(circle, rgba(245,166,35,0.1), transparent 70%); top:-200px; left:50px; }
        body.teacher-profile-page .orb.two { width:500px; height:500px; background:radial-gradient(circle, rgba(0,212,180,0.07), transparent 70%); bottom:-100px; right:100px; }
        body.teacher-profile-page .orb.three { width:420px; height:420px; background:radial-gradient(circle, rgba(74,158,255,0.08), transparent 70%); top:38%; right:-50px; }
        #particles-container { position:fixed; inset:0; z-index:0; pointer-events:none; overflow:hidden; }
        body.teacher-profile-page #particles-container { z-index:2; }
        @keyframes orbDrift { from { transform:translate(0,0) scale(1); } to { transform:translate(40px,30px) scale(1.06); } }
        @keyframes floatDot { 0% { transform:translate(0,0) scale(1); opacity:.5; } 33% { transform:translate(10px,-15px) scale(1.2); opacity:1; } 66% { transform:translate(-8px,8px) scale(.8); opacity:.3; } 100% { transform:translate(15px,-20px) scale(1); opacity:.6; } }
        .mobile-toggle { display:none; position:fixed; top:16px; left:16px; z-index:1200; width:48px; height:48px; border:0; border-radius:14px; background:rgba(13,13,24,0.95); color:var(--accent); box-shadow:0 18px 36px rgba(0,0,0,0.35); }
        .sidebar-overlay { position:fixed; inset:0; background:rgba(0,0,0,0.6); opacity:0; pointer-events:none; transition:opacity .25s ease; z-index:1050; }
        .layout { position:relative; z-index:1; min-height:100vh; display:flex; }
        body.teacher-profile-page .layout { z-index:4; width:100%; min-height:100vh; }
        .sidebar { width:var(--sidebar-width); position:fixed; inset:0 auto 0 0; z-index:1100; display:flex; flex-direction:column; justify-content:space-between; padding:24px 18px; background:rgba(13,13,24,0.95); border-right:1px solid rgba(255,255,255,0.06); backdrop-filter:blur(24px); -webkit-backdrop-filter:blur(24px); overflow-y:auto; transition:transform .25s ease; }
        body.teacher-profile-page .sidebar { padding:0; }
        .brand { padding:6px 8px 22px; }
        .brand small { display:block; color:#5A6070; text-transform:uppercase; letter-spacing:.16em; font-size:.76rem; font-weight:800; margin-bottom:6px; }
        .brand strong { display:block; color:var(--accent); font-family:'Bebas Neue',cursive; font-size:1.32rem; letter-spacing:.08em; }
        .sidebar-logo { padding:28px 24px 20px; border-bottom:1px solid rgba(255,255,255,0.06); position:relative; }
        .sidebar-logo::after { content:''; position:absolute; bottom:0; left:24px; right:24px; height:1px; background:linear-gradient(90deg, transparent, rgba(74,158,255,0.32), transparent); }
        .sidebar-school-name { font-family:'Bebas Neue',cursive; font-size:17px; letter-spacing:2.5px; color:#4A9EFF; display:block; line-height:1; }
        .sidebar-school-sub { font-size:9px; color:#5A6070; letter-spacing:2px; text-transform:uppercase; display:block; margin-top:4px; }
        .sidebar-role-badge { display:inline-flex; align-items:center; gap:5px; background:rgba(74,158,255,0.1); border:1px solid rgba(74,158,255,0.2); color:#4A9EFF; font-size:10px; font-weight:600; letter-spacing:1px; text-transform:uppercase; padding:4px 10px; border-radius:50px; margin-top:10px; }
        .nav-section-label { font-size:9px; font-weight:700; letter-spacing:2.5px; text-transform:uppercase; color:#5A6070; padding:20px 24px 8px; }
        .nav-item { display:flex; align-items:center; gap:12px; width:calc(100% - 12px); margin-right:12px; padding:13px 24px; border:0; background:transparent; color:#A0A8B8; text-align:left; font-size:14px; font-weight:500; border-left:3px solid transparent; border-radius:0 12px 12px 0; cursor:pointer; transition:all .3s cubic-bezier(0.23,1,0.32,1); position:relative; text-decoration:none; }
        .nav-item:hover, .nav-item.active { color:#fff; background:rgba(255,255,255,0.05); border-left-color:rgba(74,158,255,0.4); transform:translateX(3px); }
        .nav-item.active { color:#4A9EFF; background:rgba(74,158,255,0.08); border-left-color:#4A9EFF; font-weight:600; }
        .nav-item.active::before { content:''; position:absolute; right:16px; top:50%; transform:translateY(-50%); width:6px; height:6px; border-radius:50%; background:#4A9EFF; box-shadow:0 0 8px rgba(74,158,255,0.8); }
        .nav-item.logout:hover { background:rgba(255,71,87,0.08); border-left-color:#FF4757; color:#FF4757; }
        .role-badge { display:inline-flex; align-items:center; gap:6px; margin:0 8px 18px; padding:6px 12px; border-radius:999px; background:color-mix(in srgb, var(--accent) 12%, transparent); border:1px solid color-mix(in srgb, var(--accent) 22%, transparent); color:var(--accent); font-size:10px; font-weight:700; letter-spacing:1px; text-transform:uppercase; }
        .nav-list { display:grid; gap:8px; }
        body.teacher-profile-page .nav-list { gap:0; padding:8px 0 18px; }
        .nav-link { position:relative; display:flex; align-items:center; gap:12px; padding:14px 16px; border-radius:16px; color:#A0A8B8; text-decoration:none; transition:background .22s ease,color .22s ease; }
        body.teacher-profile-page .nav-link { width:calc(100% - 12px); margin-right:12px; padding:13px 24px; border-radius:0 12px 12px 0; }
        .nav-link::before { content:''; position:absolute; left:-18px; top:10px; bottom:10px; width:4px; border-radius:999px; background:transparent; transition:background .22s ease; }
        body.teacher-profile-page .nav-link::before { left:0; top:0; bottom:0; width:3px; border-radius:0 0 0 0; }
        .nav-link:hover, .nav-link.active { background:rgba(255,255,255,0.05); color:#fff; }
        body.teacher-profile-page .nav-link:hover, body.teacher-profile-page .nav-link.active { transform:translateX(3px); }
        .nav-link.active::before { background:var(--accent); }
        body.teacher-profile-page .nav-link.active::after {
            content:'';
            position:absolute;
            right:16px;
            top:50%;
            transform:translateY(-50%);
            width:6px;
            height:6px;
            border-radius:50%;
            background:#4A9EFF;
            box-shadow:0 0 8px rgba(74,158,255,0.8);
        }
        .nav-link.logout:hover { background:rgba(255,71,87,0.12); color:#ffb0b8; }
        body.teacher-profile-page .nav-link.logout:hover { border-left-color:#FF4757; color:#FF4757; background:rgba(255,71,87,0.08); }
        .sidebar-footer { display:flex; align-items:center; gap:12px; padding:18px 8px 8px; border-top:1px solid rgba(255,255,255,0.06); }
        body.teacher-profile-page .sidebar-footer { padding:16px 24px 24px; }
        .avatar { width:42px; height:42px; border-radius:50%; overflow:hidden; flex-shrink:0; display:grid; place-items:center; background:linear-gradient(135deg, #4A9EFF, rgba(74,158,255,0.4)); color:#0A0A0F; font-family:'Bebas Neue',cursive; font-size:18px; box-shadow:0 0 18px rgba(74,158,255,0.3); }
        .sidebar-avatar { width:44px; height:44px; border-radius:50%; display:grid; place-items:center; background:linear-gradient(135deg, var(--accent), color-mix(in srgb, var(--accent) 35%, transparent)); color:#0A0A0F; font-weight:800; }
        body.teacher-profile-page .sidebar-avatar { width:42px; height:42px; background:linear-gradient(135deg, #4A9EFF, rgba(74,158,255,0.4)); box-shadow:0 0 18px rgba(74,158,255,0.3); }
        .sidebar-footer strong { display:block; color:#fff; }
        .sidebar-footer span { display:block; color:#A0A8B8; font-size:.84rem; }
        .main { position:fixed; top:0; left:var(--sidebar-width); right:0; bottom:0; min-width:0; overflow-y:auto; overflow-x:hidden; padding:28px 20px; }
        body.teacher-profile-page .main { padding:0; z-index:4; }
        .shell { width:min(100%, 820px); position:relative; margin:0; }
        body.teacher-profile-page .shell { min-height:100%; width:100%; max-width:none; display:flex; position:relative; z-index:4; }
        .main-inner { display:none; }
        body.teacher-profile-page .main-inner { display:block; padding:32px 20px 40px; position:relative; z-index:4; min-height:100%; }
        .card { background:var(--card); border:1px solid var(--border); border-radius:24px; padding:34px 28px; backdrop-filter:blur(24px); box-shadow:0 30px 80px rgba(0,0,0,0.55); }
        body.teacher-profile-page .card { background:rgba(30,30,40,0.82); border:1px solid rgba(255,255,255,0.08); border-radius:22px; padding:26px; backdrop-filter:blur(10px); box-shadow:0 24px 60px rgba(0,0,0,0.34); max-width:980px; }
        .section-card { display:none; }
        body.teacher-profile-page .section-card { display:block; background:rgba(30,30,40,0.82); border:1px solid rgba(255,255,255,0.08); border-radius:22px; padding:26px; backdrop-filter:blur(10px); box-shadow:0 24px 60px rgba(0,0,0,0.34); max-width:980px; }
        .topbar { display:flex; justify-content:space-between; gap:16px; align-items:flex-start; margin-bottom:24px; }
        body.teacher-profile-page .topbar { margin-bottom:32px; padding-bottom:24px; border-bottom:1px solid rgba(255,255,255,0.06); position:relative; }
        body.teacher-profile-page .topbar::after { content:''; position:absolute; left:0; bottom:-1px; width:80px; height:2px; background:linear-gradient(90deg, #4A9EFF, transparent); border-radius:2px; }
        .page-head { display:none; }
        body.teacher-profile-page .page-head { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap; margin-bottom:32px; padding-bottom:24px; border-bottom:1px solid rgba(255,255,255,0.06); position:relative; }
        body.teacher-profile-page .page-head::after { content:''; position:absolute; left:0; bottom:-1px; width:80px; height:2px; background:linear-gradient(90deg, #4A9EFF, transparent); border-radius:2px; }
        .badge { display:inline-flex; align-items:center; gap:8px; padding:7px 14px; border-radius:999px; border:1px solid rgba(255,255,255,0.08); background:rgba(255,255,255,0.05); color:var(--accent); font-size:12px; font-weight:700; letter-spacing:1.2px; text-transform:uppercase; }
        body.teacher-profile-page .badge { padding:6px 10px; font-size:.78rem; letter-spacing:0; font-weight:800; background:rgba(74,158,255,0.12); border:1px solid rgba(74,158,255,0.18); color:#4A9EFF; }
        h1 { font-family:'Playfair Display',serif; font-size:34px; margin-top:12px; }
        body.teacher-profile-page h1 { font-size:clamp(2rem,3.6vw,2.5rem); margin-top:0; margin-bottom:8px; }
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
        .input:focus { border-color:var(--accent); box-shadow:0 0 0 3px rgba(255,255,255,0.04); }
        .readonly { color:var(--muted); display:flex; align-items:center; }
        .actions { display:flex; justify-content:flex-end; gap:12px; margin-top:22px; }
        .btn { min-height:48px; padding:0 18px; border-radius:14px; border:none; cursor:pointer; font-weight:700; }
        .btn.primary { background:linear-gradient(135deg, var(--accent), color-mix(in srgb, var(--accent) 72%, #000 28%)); color:#0A0A0F; }
        .btn.secondary { background:rgba(255,255,255,0.08); color:#fff; }
        .avatar img, .sidebar-avatar img { width:100%; height:100%; object-fit:cover; object-position:center; border-radius:inherit; display:block; }
        .profile-photo-panel { display:flex; align-items:center; gap:18px; padding:18px; margin-bottom:18px; border-radius:18px; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); }
        .profile-photo-preview { width:88px; height:88px; border-radius:50%; overflow:hidden; flex-shrink:0; display:grid; place-items:center; background:linear-gradient(135deg, var(--accent), color-mix(in srgb, var(--accent) 35%, transparent)); color:#0A0A0F; font-size:1.6rem; font-weight:800; }
        .profile-photo-preview img { width:100%; height:100%; object-fit:cover; display:block; }
        .profile-photo-copy strong { display:block; margin-bottom:6px; color:#fff; }
        .profile-photo-copy p { margin-bottom:12px; }
        .file-input { display:block; width:100%; max-width:320px; color:#A0A8B8; }
        .profile-mode-actions { display:flex; justify-content:flex-end; gap:12px; margin-bottom:18px; }
        .profile-view, .profile-edit { display:grid; gap:18px; }
        .profile-edit[hidden], .profile-view[hidden] { display:none !important; }
        .detail-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
        .detail-card { padding:18px; border-radius:18px; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); }
        .detail-card.full { grid-column:1 / -1; }
        .detail-card-label { display:block; font-size:12px; letter-spacing:1.6px; text-transform:uppercase; color:var(--accent); font-weight:700; margin-bottom:10px; }
        .detail-card-value { color:#fff; font-size:1rem; font-weight:600; word-break:break-word; }
        .detail-card-subtext { color:var(--muted); margin-top:8px; }
        @media (max-width: 768px) {
            .mobile-toggle { display:inline-flex; }
            .sidebar { transform:translateX(-100%); }
            body.sidebar-open .sidebar { transform:translateX(0); }
            body.sidebar-open .sidebar-overlay { opacity:1; pointer-events:auto; }
            .main { left:0; padding:82px 16px 20px; }
            body.teacher-profile-page .main { left:0; padding:0; }
            body.teacher-profile-page .main-inner { padding:78px 6vw 16px 6vw; }
        }
        @media (max-width: 640px) { .card { padding:28px 20px; } .grid, .detail-grid { grid-template-columns:1fr; } .topbar { flex-direction:column; } .actions, .profile-mode-actions { flex-direction:column; } }
    </style>
    <?php if ($role === 'principal'): ?>
        <?php require_once __DIR__ . '/principal-shared.php'; ?>
    <?php endif; ?>
</head>
<body class="<?php echo e(implode(' ', $bodyClasses)); ?>">
    <div class="orb one"></div>
    <div class="orb two"></div>
    <div class="orb three"></div>
    <div id="particles-container"></div>
    <button class="mobile-toggle" id="sidebarToggle" type="button" aria-label="Toggle sidebar">☰</button>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="layout">
        <?php if ($role === 'principal'): ?>
            <?php
                $principalName = $displayName;
                $principalSidebarActive = 'profile';
                require __DIR__ . '/principal-sidebar.php';
            ?>
        <?php else: ?>
            <aside class="sidebar" id="sidebar">
                <div>
                    <?php if ($role === 'teacher'): ?>
                        <div class="sidebar-logo">
                            <span class="sidebar-school-name">SAMACE TECH LAB</span>
                            <span class="sidebar-school-sub">Nursery &amp; Primary School</span>
                            <span class="sidebar-role-badge">👩‍🏫 Teacher Portal</span>
                        </div>
                        <div class="nav-section-label">Navigation</div>
                        <nav class="nav-list">
                            <a class="nav-item" href="teacher-dashboard.php"><span>🏠</span><span>Dashboard</span></a>
                            <a class="nav-item" href="teacher-dashboard.php?panel=chat"><span>💬</span><span>Chat</span></a>
                            <a class="nav-item" href="teacher-dashboard.php?panel=announcement"><span>📢</span><span>Announcement</span></a>
                            <a class="nav-item active" href="profile.php"><span>👤</span><span>Profile</span></a>
                            <a class="nav-item logout" href="logout.php"><span>🚪</span><span>Logout</span></a>
                        </nav>
                    <?php else: ?>
                        <div class="brand"><small>SAMACE TECH LAB</small><strong>Parent Portal</strong></div>
                        <span class="role-badge">👤 <?php echo e($currentRole['badge']); ?></span>
                        <nav class="nav-list">
                            <?php foreach (($sidebarLinks[$role] ?? []) as $link): ?>
                                <a class="nav-link" href="<?php echo e((string) $link['href']); ?>"><span><?php echo e((string) $link['icon']); ?></span><span><?php echo e((string) $link['label']); ?></span></a>
                            <?php endforeach; ?>
                            <a class="nav-link active" href="profile.php"><span>👤</span><span>Profile</span></a>
                            <a class="nav-link logout" href="logout.php"><span>🚪</span><span>Logout</span></a>
                        </nav>
                    <?php endif; ?>
                </div>
                <div class="sidebar-footer">
                    <?php if ($role === 'teacher'): ?>
                        <?php echo render_avatar_html($user, 'avatar', 'User'); ?>
                        <div><strong><?php echo e($displayName); ?></strong><div style="color:rgba(255,255,255,0.68);font-size:0.88rem;">Teacher Account</div></div>
                    <?php else: ?>
                        <?php echo render_avatar_html($user, 'sidebar-avatar', 'User'); ?>
                        <div><strong><?php echo e($displayName); ?></strong><span><?php echo e(ucfirst($role)); ?></span></div>
                    <?php endif; ?>
                </div>
            </aside>
        <?php endif; ?>
        <main class="main">
            <div class="shell">
                <?php if ($role === 'teacher'): ?>
                    <div class="main-inner">
                        <section class="section-card">
                            <div class="page-head">
                                <div>
                                    <span class="badge">👤 Teacher Profile</span>
                                    <h1><?php echo e($displayName); ?></h1>
                                    <p>Update your account information and keep your portal details current.</p>
                                </div>
                                <a class="back-link" href="<?php echo e($currentRole['dashboard']); ?>">Back to dashboard</a>
                            </div>
                <?php else: ?>
                    <section class="card">
                        <div class="topbar">
                            <div>
                                <span class="badge">👤 <?php echo e($currentRole['badge']); ?></span>
                                <h1><?php echo e($displayName); ?></h1>
                                <p>Update your account information and keep your portal details current.</p>
                            </div>
                            <a class="back-link" href="<?php echo e($currentRole['dashboard']); ?>">Back to dashboard</a>
                        </div>
                <?php endif; ?>

            <?php if ($flash): ?>
                <div class="status <?php echo e((string) ($flash['type'] ?? 'success')); ?>"><?php echo e((string) $flash['message']); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="status error"><?php echo e($error); ?></div>
            <?php endif; ?>
            <?php if ($db_error): ?>
                <div class="status error"><?php echo e($db_error); ?></div>
            <?php endif; ?>

            <div class="profile-mode-actions">
                <button class="btn primary" id="editProfileBtn" type="button"<?php echo $startInEditMode ? ' hidden' : ''; ?>>Edit Profile</button>
                <button class="btn secondary" id="cancelEditBtn" type="button"<?php echo $startInEditMode ? '' : ' hidden'; ?>>Cancel Edit</button>
            </div>

            <section class="profile-view" id="profileView"<?php echo $startInEditMode ? ' hidden' : ''; ?>>
                <div class="profile-photo-panel">
                    <div class="profile-photo-preview">
                        <?php if ($profilePictureUrl !== null): ?>
                            <img src="<?php echo e($profilePictureUrl); ?>" alt="<?php echo e($displayName); ?> profile picture">
                        <?php else: ?>
                            <?php echo e($profileInitials); ?>
                        <?php endif; ?>
                    </div>
                    <div class="profile-photo-copy">
                        <strong><?php echo e($displayName); ?></strong>
                        <p>Your profile opens in view mode. Click Edit Profile to update your details or change your picture.</p>
                    </div>
                </div>

                <div class="detail-grid">
                    <article class="detail-card">
                        <span class="detail-card-label">First Name</span>
                        <div class="detail-card-value"><?php echo e($firstName !== '' ? $firstName : 'Not added'); ?></div>
                    </article>
                    <article class="detail-card">
                        <span class="detail-card-label">Last Name</span>
                        <div class="detail-card-value"><?php echo e($lastName !== '' ? $lastName : 'Not added'); ?></div>
                    </article>
                    <article class="detail-card full">
                        <span class="detail-card-label">Email Address</span>
                        <div class="detail-card-value"><?php echo e($email !== '' ? $email : 'Not added'); ?></div>
                    </article>

                    <?php if ($role === 'parent'): ?>
                        <article class="detail-card full">
                            <span class="detail-card-label">Child Name</span>
                            <div class="detail-card-value"><?php echo e($childName !== '' ? $childName : 'Not added yet'); ?></div>
                        </article>
                    <?php endif; ?>

                    <?php if ($role === 'teacher'): ?>
                        <article class="detail-card full">
                            <span class="detail-card-label">Preferred Class</span>
                            <div class="detail-card-value"><?php echo e($requestedTeachingClass !== '' ? $requestedTeachingClass : 'Not added yet'); ?></div>
                        </article>
                        <article class="detail-card">
                            <span class="detail-card-label">Assigned Class</span>
                            <div class="detail-card-value"><?php echo e($assignedTeachingClass !== '' ? $assignedTeachingClass : 'Not assigned yet'); ?></div>
                        </article>
                        <article class="detail-card">
                            <span class="detail-card-label">Approval Status</span>
                            <div class="detail-card-value"><?php echo e(ucfirst($approvalStatus)); ?></div>
                        </article>
                    <?php endif; ?>
                </div>
            </section>

            <form method="post" enctype="multipart/form-data" class="profile-edit" id="profileEditForm"<?php echo $startInEditMode ? '' : ' hidden'; ?>>
                <div class="profile-photo-panel">
                    <div class="profile-photo-preview">
                        <?php if ($profilePictureUrl !== null): ?>
                            <img src="<?php echo e($profilePictureUrl); ?>" alt="<?php echo e($displayName); ?> profile picture">
                        <?php else: ?>
                            <?php echo e($profileInitials); ?>
                        <?php endif; ?>
                    </div>
                    <div class="profile-photo-copy">
                        <strong>Edit Profile Photo</strong>
                        <p>Upload a profile picture for your account. It will appear in chats and on the portal dashboards.</p>
                        <input class="file-input" type="file" name="profile_picture" accept="image/jpeg,image/png,image/webp,image/gif">
                    </div>
                </div>
                <div class="grid">
                    <label class="field">
                        <span class="label">First Name</span>
                        <input class="input" type="text" name="first_name" value="<?php echo e($firstName); ?>" required>
                    </label>
                    <label class="field">
                        <span class="label">Last Name</span>
                        <input class="input" type="text" name="last_name" value="<?php echo e($lastName); ?>" required>
                    </label>
                    <label class="field full">
                        <span class="label">Email Address</span>
                        <input class="input" type="email" name="email" value="<?php echo e($email); ?>" required>
                    </label>

                    <?php if ($role === 'parent'): ?>
                        <label class="field full">
                            <span class="label">Child Name</span>
                            <input class="input" type="text" name="child_name" value="<?php echo e($childName); ?>">
                        </label>
                    <?php endif; ?>

                    <?php if ($role === 'teacher'): ?>
                        <label class="field full">
                            <span class="label">Preferred Class</span>
                            <input class="input" type="text" name="requested_teaching_class" value="<?php echo e($requestedTeachingClass); ?>">
                        </label>
                        <div class="field full">
                            <span class="label">Assigned Class</span>
                            <div class="readonly"><?php echo e($assignedTeachingClass !== '' ? $assignedTeachingClass : 'Not assigned yet'); ?></div>
                        </div>
                        <div class="field full">
                            <span class="label">Approval Status</span>
                            <div class="readonly"><?php echo e(ucfirst($approvalStatus)); ?></div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="actions">
                    <a class="btn secondary" href="<?php echo e($currentRole['dashboard']); ?>" style="display:inline-flex;align-items:center;justify-content:center;text-decoration:none;">Back to Dashboard</a>
                    <button class="btn primary" type="submit">Save Profile</button>
                </div>
            </form>
                <?php if ($role === 'teacher'): ?>
                        </section>
                    </div>
                <?php else: ?>
                    </section>
                <?php endif; ?>
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
        const editProfileBtn = document.getElementById('editProfileBtn');
        const cancelEditBtn = document.getElementById('cancelEditBtn');
        const profileView = document.getElementById('profileView');
        const profileEditForm = document.getElementById('profileEditForm');
        if (sidebarToggle && sidebarOverlay) {
            sidebarToggle.addEventListener('click', () => document.body.classList.toggle('sidebar-open'));
            sidebarOverlay.addEventListener('click', () => document.body.classList.remove('sidebar-open'));
        }

        function setProfileMode(isEditMode) {
            if (profileView) {
                profileView.hidden = isEditMode;
            }
            if (profileEditForm) {
                profileEditForm.hidden = !isEditMode;
            }
            if (editProfileBtn) {
                editProfileBtn.hidden = isEditMode;
            }
            if (cancelEditBtn) {
                cancelEditBtn.hidden = !isEditMode;
            }
        }

        if (editProfileBtn) {
            editProfileBtn.addEventListener('click', () => setProfileMode(true));
        }

        if (cancelEditBtn) {
            cancelEditBtn.addEventListener('click', () => setProfileMode(false));
        }
    </script>
</body>
</html>
