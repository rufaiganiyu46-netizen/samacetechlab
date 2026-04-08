<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';

$principal = require_role('principal');
$flash = get_flash();
$announcementError = null;
$titleValue = '';
$messageValue = '';
$audienceValue = 'both';
$editingAnnouncementId = 0;
$currentAttachmentName = '';
$teacherCount = 0;
$parentCount = 0;
$pendingTeacherCount = 0;
$pendingParentCount = 0;
$principalId = (int) ($principal['id'] ?? 0);

if (isset($_GET['edit'])) {
    $editingAnnouncementId = (int) ($_GET['edit'] ?? 0);
    $announcementToEdit = fetch_announcement_by_id_for_principal($editingAnnouncementId, $principalId);
    if ($announcementToEdit) {
        $titleValue = trim((string) ($announcementToEdit['title'] ?? ''));
        $messageValue = trim((string) ($announcementToEdit['message'] ?? ''));
        $audienceValue = (string) ($announcementToEdit['audience'] ?? 'both');
        $currentAttachmentName = trim((string) ($announcementToEdit['attachment_name'] ?? ''));
    } else {
        $editingAnnouncementId = 0;
    }
}

$announcementHistory = fetch_announcements_for_principal($principalId);

if (db_ready() && $db instanceof mysqli) {
    $teacherCountResult = $db->query("SELECT COUNT(*) AS total FROM users WHERE role = 'teacher'");
    if ($teacherCountResult) {
        $teacherCount = (int) ($teacherCountResult->fetch_assoc()['total'] ?? 0);
        $teacherCountResult->free();
    }

    $pendingTeacherCountResult = $db->query("SELECT COUNT(*) AS total FROM users WHERE role = 'teacher' AND approval_status = 'pending'");
    if ($pendingTeacherCountResult) {
        $pendingTeacherCount = (int) ($pendingTeacherCountResult->fetch_assoc()['total'] ?? 0);
        $pendingTeacherCountResult->free();
    }

    $parentCountResult = $db->query("SELECT COUNT(*) AS total FROM users WHERE role = 'parent'");
    if ($parentCountResult) {
        $parentCount = (int) ($parentCountResult->fetch_assoc()['total'] ?? 0);
        $parentCountResult->free();
    }

    $pendingParentCountResult = $db->query("SELECT COUNT(*) AS total FROM users WHERE role = 'parent' AND approval_status = 'pending'");
    if ($pendingParentCountResult) {
        $pendingParentCount = (int) ($pendingParentCountResult->fetch_assoc()['total'] ?? 0);
        $pendingParentCountResult->free();
    }
}

if (is_post() && (string) ($_POST['action'] ?? '') === 'publish_announcement') {
    $titleValue = trim((string) ($_POST['title'] ?? ''));
    $messageValue = trim((string) ($_POST['message'] ?? ''));
    $audienceValue = (string) ($_POST['audience'] ?? 'both');

    if (create_announcement($principalId, $titleValue, $messageValue, $audienceValue, $announcementError, $_FILES['attachment'] ?? null)) {
        set_flash('success', 'Announcement published successfully. Selected users will now see it on their announcement board.');
        redirect('principalent.php');
    }
}

if (is_post() && (string) ($_POST['action'] ?? '') === 'update_announcement') {
    $editingAnnouncementId = (int) ($_POST['announcement_id'] ?? 0);
    $titleValue = trim((string) ($_POST['title'] ?? ''));
    $messageValue = trim((string) ($_POST['message'] ?? ''));
    $audienceValue = (string) ($_POST['audience'] ?? 'both');

    if (update_announcement($editingAnnouncementId, $principalId, $titleValue, $messageValue, $audienceValue, $announcementError, $_FILES['attachment'] ?? null)) {
        set_flash('success', 'Announcement updated successfully. Teachers and parents will now see the edited version.');
        redirect('principalent.php');
    }

    $announcementToEdit = fetch_announcement_by_id_for_principal($editingAnnouncementId, $principalId);
    $currentAttachmentName = trim((string) ($announcementToEdit['attachment_name'] ?? ''));
}

if (is_post() && (string) ($_POST['action'] ?? '') === 'delete_announcement') {
    $announcementId = (int) ($_POST['announcement_id'] ?? 0);
    if (delete_announcement($announcementId, $principalId, $announcementError)) {
        set_flash('success', 'Announcement deleted successfully. It is no longer visible on parent and teacher boards.');
        redirect('principalent.php');
    }
}

function principal_announcement_audience_label(string $audience): string
{
    return match ($audience) {
        'teachers' => 'Teachers Only',
        'parents' => 'Parents Only',
        'both' => 'Teachers and Parents',
        default => ucfirst($audience),
    };
}

function principal_recipient_summary(string $audience, int $teacherCount, int $parentCount): string
{
    return match ($audience) {
        'teachers' => $teacherCount . ' teacher' . ($teacherCount === 1 ? '' : 's') . ' will receive this announcement.',
        'parents' => $parentCount . ' parent' . ($parentCount === 1 ? '' : 's') . ' will receive this announcement.',
        'both' => ($teacherCount + $parentCount) . ' users will receive this announcement.',
        default => 'Select who should receive this announcement.',
    };
}

$principalName = (string) (($principal['first_name'] ?? '') ?: ($principal['full_name'] ?? 'Principal'));
$summaryText = principal_recipient_summary($audienceValue, $teacherCount, $parentCount);
$principalInitial = strtoupper(substr($principalName, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="dark">
    <title>Principal Announcements | SAMACE TECH LAB</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@400;500;700;800&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <style>
        :root {
            --navy:#0F2444;
            --navy-strong:#0A1930;
            --gold:#F5A623;
            --gold-soft:rgba(245,166,35,.14);
            --sky:#F4F7FC;
            --card:#FFFFFF;
            --text:#1D2A3B;
            --soft:#6B7A90;
            --line:#DBE4F0;
            --success:#166534;
            --success-bg:rgba(22,163,74,.08);
            --danger:#991B1B;
            --danger-bg:rgba(220,38,38,.08);
            --shadow:0 18px 40px rgba(15,36,68,.10);
            --radius:22px;
            --transition:220ms ease;
            --sidebar-width:250px;
        }

        * { box-sizing:border-box; margin:0; padding:0; }
        body { font-family:'DM Sans',sans-serif; background:var(--sky); color:var(--text); }
        a { color:inherit; text-decoration:none; }
        button, input, select, textarea { font:inherit; }

        .shell { min-height:100vh; display:flex; }
        .mobile-toggle { position:fixed; top:18px; left:18px; z-index:1200; width:48px; height:48px; border:0; border-radius:14px; background:var(--navy); color:#fff; display:none; align-items:center; justify-content:center; box-shadow:var(--shadow); cursor:pointer; }
        .overlay { position:fixed; inset:0; background:rgba(8,18,35,.46); opacity:0; pointer-events:none; transition:opacity var(--transition); z-index:1050; }
        .sidebar { width:var(--sidebar-width); position:fixed; inset:0 auto 0 0; z-index:1100; display:flex; flex-direction:column; justify-content:space-between; padding:24px 18px; background:linear-gradient(180deg,var(--navy) 0%,var(--navy-strong) 100%); color:rgba(255,255,255,.9); overflow-y:auto; transition:transform var(--transition); }
        .sidebar-brand { display:flex; align-items:center; gap:14px; padding:8px 8px 24px; }
        .sidebar-brand-mark { width:52px; height:52px; border-radius:16px; background:rgba(255,255,255,.08); display:grid; place-items:center; padding:8px; }
        .sidebar-brand-mark img { width:100%; height:100%; object-fit:contain; }
        .sidebar-brand-text small { display:block; color:rgba(245,166,35,.88); text-transform:uppercase; letter-spacing:.16em; font-size:.76rem; font-weight:800; margin-bottom:4px; }
        .sidebar-brand-text strong { display:block; color:var(--gold); font-family:'Fraunces',serif; font-size:1.18rem; line-height:1.15; }
        .nav-list { display:grid; gap:8px; margin-top:28px; }
        .nav-link { position:relative; display:flex; align-items:center; gap:12px; padding:14px 16px; border-radius:16px; transition:background var(--transition), color var(--transition); }
        .nav-link::before { content:''; position:absolute; left:-18px; top:10px; bottom:10px; width:4px; border-radius:999px; background:transparent; transition:background var(--transition); }
        .nav-link:hover, .nav-link.active { background:rgba(255,255,255,.08); }
        .nav-link.active::before { background:var(--gold); }
        .nav-link.logout:hover { background:rgba(220,38,38,.14); }
        .sidebar-footer { display:flex; align-items:center; gap:12px; padding:18px 8px 8px; border-top:1px solid rgba(255,255,255,.1); }
        .avatar { width:44px; height:44px; border-radius:50%; display:grid; place-items:center; background:rgba(245,166,35,.16); color:var(--gold); font-weight:800; }
        .sidebar-footer strong { display:block; color:#fff; }
        .sidebar-footer span { display:block; color:rgba(255,255,255,.64); font-size:.9rem; }

        .main { position:fixed; top:0; left:var(--sidebar-width); right:0; bottom:0; min-width:0; overflow-y:auto; overflow-x:hidden; }
        .main-inner { padding:28px; }
        .page-head { display:flex; justify-content:space-between; align-items:flex-start; gap:18px; flex-wrap:wrap; margin-bottom:24px; }
        .page-head h1 { font-family:'Fraunces',serif; font-size:clamp(2rem,3.8vw,2.9rem); margin-bottom:8px; color:var(--navy); }
        .page-head p { color:var(--soft); max-width:720px; }
        .quick-note { background:#fff; border:1px solid var(--line); border-radius:999px; padding:12px 16px; box-shadow:var(--shadow); font-weight:700; color:var(--navy); }
        .flash { border-radius:16px; padding:14px 16px; margin-bottom:18px; font-weight:700; }
        .flash.success { color:var(--success); background:var(--success-bg); }
        .flash.error { color:var(--danger); background:var(--danger-bg); }

        .stats { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:18px; margin-bottom:24px; }
        .stat-card, .compose-card, .history-card, .info-card { background:var(--card); border:1px solid var(--line); border-radius:var(--radius); box-shadow:var(--shadow); }
        .stat-card { padding:22px; border-left:5px solid var(--navy); }
        .stat-card:nth-child(2) { border-left-color:var(--gold); }
        .stat-card:nth-child(3) { border-left-color:#2563EB; }
        .stat-label { display:block; color:var(--soft); font-weight:700; margin-bottom:10px; }
        .stat-value { font-size:2rem; font-weight:800; color:var(--navy); }

        .grid { display:grid; grid-template-columns:minmax(0,1.05fr) minmax(320px,.95fr); gap:22px; }
        .compose-card, .history-card, .info-card { padding:24px; }
        .compose-card h2, .history-card h2, .info-card h2 { font-family:'Fraunces',serif; color:var(--navy); margin-bottom:12px; }
        .compose-card p, .info-card p { color:var(--soft); }
        .form-grid { display:grid; gap:16px; margin-top:18px; }
        .form-field { display:grid; gap:8px; }
        .form-field label { font-weight:700; color:var(--navy); }
        .form-field input, .form-field select, .form-field textarea { width:100%; border:1px solid var(--line); border-radius:16px; padding:14px 15px; background:#fff; outline:none; transition:border-color var(--transition), box-shadow var(--transition); }
        .form-field textarea { min-height:180px; resize:vertical; }
        .form-field input:focus, .form-field select:focus, .form-field textarea:focus { border-color:rgba(15,36,68,.34); box-shadow:0 0 0 4px rgba(15,36,68,.08); }
        .recipient-note { padding:14px 16px; border-radius:16px; background:var(--gold-soft); color:#7A4E00; font-weight:700; }
        .form-actions { display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; }
        .submit-btn { min-height:52px; padding:0 22px; border:0; border-radius:16px; background:var(--navy); color:#fff; font-weight:800; cursor:pointer; }
        .submit-btn:hover { background:var(--navy-strong); }

        .info-list { display:grid; gap:14px; margin-top:16px; }
        .info-item { padding:16px; border-radius:18px; background:#F8FAFD; border:1px solid var(--line); }
        .info-item strong { display:block; color:var(--navy); margin-bottom:6px; }

        .table-wrap { overflow:auto; margin-top:16px; }
        table { width:100%; border-collapse:collapse; min-width:640px; }
        th, td { padding:14px 12px; border-bottom:1px solid var(--line); text-align:left; vertical-align:top; }
        th { color:var(--soft); font-size:.84rem; text-transform:uppercase; letter-spacing:.06em; }
        td strong { color:var(--navy); }
        .badge { display:inline-flex; align-items:center; justify-content:center; padding:6px 10px; border-radius:999px; background:var(--gold-soft); color:#8A5600; font-size:.78rem; font-weight:800; white-space:nowrap; }
        .muted { color:var(--soft); }

        @media (max-width:1080px) {
            .stats, .grid { grid-template-columns:1fr; }
        }

        @media (max-width:768px) {
            .mobile-toggle { display:inline-flex; }
            .sidebar { transform:translateX(-100%); }
            body.sidebar-open .sidebar { transform:translateX(0); }
            body.sidebar-open .overlay { opacity:1; pointer-events:auto; }
            .main { left:0; }
            .main-inner { padding:82px 18px 20px; }
        }

        @media (max-width:480px) {
            .main-inner { padding:82px 14px 18px; }
            .compose-card, .history-card, .info-card, .stat-card { padding:18px; }
            .stats { grid-template-columns:1fr; }
            .quick-note { width:100%; border-radius:18px; }
        }

        body {
            background: #0A0A0F;
            color: #FFFFFF;
            position: relative;
            overflow-x: hidden;
        }
        .orb { position: fixed; border-radius: 50%; filter: blur(140px); z-index: 0; pointer-events: none; animation: orbDrift 20s ease-in-out infinite alternate; }
        .orb-gold { width:700px; height:700px; background:radial-gradient(circle, rgba(245,166,35,0.11), transparent 70%); top:-200px; left:50px; }
        .orb-teal { width:500px; height:500px; background:radial-gradient(circle, rgba(0,212,180,0.08), transparent 70%); bottom:-100px; right:100px; animation-direction:alternate-reverse; animation-duration:26s; }
        .orb-blue { width:400px; height:400px; background:radial-gradient(circle, rgba(74,158,255,0.07), transparent 70%); top:40%; right:-50px; animation-duration:32s; }
        #particles-container { position: fixed; inset: 0; z-index: 0; pointer-events: none; overflow: hidden; }
        @keyframes orbDrift { from { transform: translate(0,0) scale(1); } to { transform: translate(40px,30px) scale(1.06); } }
        @keyframes floatDot {
            0% { transform: translate(0,0) scale(1); opacity: 0.5; }
            33% { transform: translate(10px,-15px) scale(1.2); opacity: 1; }
            66% { transform: translate(-8px,8px) scale(0.8); opacity: 0.3; }
            100% { transform: translate(15px,-20px) scale(1); opacity: 0.6; }
        }
        .shell { position: relative; z-index: 1; }
        .sidebar { background: rgba(13,13,24,0.95); border-right:1px solid rgba(255,255,255,0.06); backdrop-filter:blur(24px); -webkit-backdrop-filter:blur(24px); }
        .sidebar-brand-text small { color:#5A6070; font-family:'DM Sans',sans-serif; }
        .sidebar-brand-text strong { color:#F5A623; font-family:'Bebas Neue',cursive; font-size:1.3rem; letter-spacing:0.08em; }
        .sidebar-role-badge { display:inline-flex; align-items:center; gap:5px; background:rgba(245,166,35,0.1); border:1px solid rgba(245,166,35,0.2); color:#F5A623; font-size:10px; font-weight:600; letter-spacing:1px; text-transform:uppercase; padding:4px 10px; border-radius:50px; margin:0 8px 20px; }
        .nav-link { color:#A0A8B8; }
        .nav-link:hover, .nav-link.active { background:rgba(255,255,255,0.05); color:#fff; }
        .nav-link.active::before { background:#F5A623; }
        .sidebar-footer { border-top:1px solid rgba(255,255,255,0.06); }
        .avatar { background:linear-gradient(135deg,#F5A623,rgba(245,166,35,0.4)); color:#0A0A0F; box-shadow:0 0 15px rgba(245,166,35,0.3); }
        .main-inner { position: relative; z-index: 1; }
        .quick-note,
        .stat-card,
        .compose-card,
        .history-card,
        .info-card,
        .info-item { background: rgba(255,255,255,0.04); border-color: rgba(255,255,255,0.08); box-shadow: 0 20px 60px rgba(0,0,0,0.32); }
        .page-head h1,
        .compose-card h2,
        .history-card h2,
        .info-card h2,
        .info-item strong,
        .stat-value,
        .sidebar-footer strong,
        .form-field label,
        td strong { color:#FFFFFF; font-family:'Playfair Display',serif; }
        .page-head p,
        .muted,
        .sidebar-footer span,
        th,
        td,
        .info-card p,
        .form-actions .muted { color:#A0A8B8; }
        .quick-note { color:#F5A623; }
        .flash.success { background:rgba(0,212,180,0.12); color:#9ff4e7; }
        .flash.error { background:rgba(255,71,87,0.12); color:#ffb0b8; }
        .form-field input,
        .form-field select,
        .form-field textarea { background: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.1); color:#fff; }
        .form-field input:focus,
        .form-field select:focus,
        .form-field textarea:focus { border-color:#F5A623; box-shadow:0 0 0 4px rgba(245,166,35,0.12); }
        .recipient-note,
        .badge { background: rgba(245,166,35,0.14); color:#F5A623; }
        .submit-btn { background: linear-gradient(135deg,#F5A623,#d99212); color:#0A0A0F; }
        .submit-btn:hover { background: linear-gradient(135deg,#ffbf45,#d99212); }
        table { color:#fff; }
    </style>
    <?php require_once dirname(__DIR__) . '/theme-shared.php'; ?>
    <?php require_once __DIR__ . '/principal-shared.php'; ?>
</head>
<body class="principal-page">
    <div class="orb orb-gold"></div>
    <div class="orb orb-teal"></div>
    <div class="orb orb-blue"></div>
    <div id="particles-container"></div>
    <button class="mobile-toggle" id="sidebarToggle" type="button" aria-label="Toggle sidebar">☰</button>
    <div class="overlay" id="sidebarOverlay"></div>
    <div class="shell">
        <?php
            $principalSidebarActive = 'announcements';
            require __DIR__ . '/principal-sidebar.php';
        ?>

        <main class="main">
            <div class="main-inner">
                <div class="page-head">
                    <div>
                        <h1>Publish Announcements</h1>
                        <p>Create a real announcement and choose exactly who should see it. Teachers will receive it on the teacher dashboard, and parents will receive it on the parent dashboard based on the audience you select.</p>
                    </div>
                    <div class="quick-note">Announcements sent: <?php echo e((string) count($announcementHistory)); ?></div>
                </div>

                <?php if ($flash): ?>
                    <div class="flash <?php echo e((string) ($flash['type'] ?? 'success')); ?>"><?php echo e((string) ($flash['message'] ?? '')); ?></div>
                <?php endif; ?>
                <?php if ($announcementError): ?>
                    <div class="flash error"><?php echo e($announcementError); ?></div>
                <?php endif; ?>
                <?php if (!db_ready() && !empty($db_error)): ?>
                    <div class="flash error"><?php echo e((string) $db_error); ?></div>
                <?php endif; ?>

                <section class="stats">
                    <article class="stat-card">
                        <span class="stat-label">👩‍🏫 Teachers</span>
                        <div class="stat-value"><?php echo e((string) $teacherCount); ?></div>
                    </article>
                    <article class="stat-card">
                        <span class="stat-label">👨‍👩‍👧‍👦 Parents</span>
                        <div class="stat-value"><?php echo e((string) $parentCount); ?></div>
                    </article>
                    <article class="stat-card">
                        <span class="stat-label">📬 Published Announcements</span>
                        <div class="stat-value"><?php echo e((string) count($announcementHistory)); ?></div>
                    </article>
                </section>

                <section class="grid">
                    <article class="compose-card">
                        <h2>Compose New Announcement</h2>
                        <p>Select the recipient group, write the message, and publish it directly to the correct announcement boards.</p>

                        <form method="post" class="form-grid" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="<?php echo e($editingAnnouncementId > 0 ? 'update_announcement' : 'publish_announcement'); ?>">
                            <?php if ($editingAnnouncementId > 0): ?>
                                <input type="hidden" name="announcement_id" value="<?php echo e((string) $editingAnnouncementId); ?>">
                            <?php endif; ?>

                            <div class="form-field">
                                <label for="announcementTitle">Announcement Title</label>
                                <input id="announcementTitle" name="title" type="text" value="<?php echo e($titleValue); ?>" placeholder="Enter announcement title" required>
                            </div>

                            <div class="form-field">
                                <label for="announcementAudience">Send To</label>
                                <select id="announcementAudience" name="audience" required>
                                    <option value="both" <?php echo $audienceValue === 'both' ? 'selected' : ''; ?>>Both Parents and Teachers</option>
                                    <option value="teachers" <?php echo $audienceValue === 'teachers' ? 'selected' : ''; ?>>Teachers Only</option>
                                    <option value="parents" <?php echo $audienceValue === 'parents' ? 'selected' : ''; ?>>Parents Only</option>
                                </select>
                            </div>

                            <div class="recipient-note" id="recipientSummary"><?php echo e($summaryText); ?></div>

                            <div class="form-field">
                                <label for="announcementMessage">Message</label>
                                <textarea id="announcementMessage" name="message" placeholder="Write the announcement message here" required><?php echo e($messageValue); ?></textarea>
                            </div>

                            <div class="form-field">
                                <label for="announcementAttachment">Attachment</label>
                                <input id="announcementAttachment" name="attachment" type="file" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt">
                                <?php if ($currentAttachmentName !== ''): ?>
                                    <span class="muted">Current attachment: <?php echo e($currentAttachmentName); ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="form-actions">
                                <span class="muted"><?php echo e($editingAnnouncementId > 0 ? 'Editing updates the live announcement and recipients will see it marked as edited.' : 'Publishing makes the announcement visible immediately on the selected users\' boards.'); ?></span>
                                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                                    <?php if ($editingAnnouncementId > 0): ?>
                                        <a class="submit-btn" style="display:inline-flex;align-items:center;justify-content:center;background:rgba(255,255,255,0.08);color:#fff;" href="principalent.php">Cancel Edit</a>
                                    <?php endif; ?>
                                    <button class="submit-btn" type="submit"><?php echo e($editingAnnouncementId > 0 ? 'Save Changes' : 'Send Announcement'); ?></button>
                                </div>
                            </div>
                        </form>
                    </article>

                    <div style="display:grid; gap:22px;">
                        <article class="info-card">
                            <h2>Delivery Rules</h2>
                            <div class="info-list">
                                <div class="info-item">
                                    <strong>Teachers Only</strong>
                                    <p>Only teachers will see this on the teacher announcement board.</p>
                                </div>
                                <div class="info-item">
                                    <strong>Parents Only</strong>
                                    <p>Only parents will see this on the parent announcement board.</p>
                                </div>
                                <div class="info-item">
                                    <strong>Both Parents and Teachers</strong>
                                    <p>Both groups will receive the same announcement immediately.</p>
                                </div>
                            </div>
                        </article>

                        <article class="history-card">
                            <h2>Recent History</h2>
                            <div class="table-wrap">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Title</th>
                                            <th>Audience</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!$announcementHistory): ?>
                                            <tr>
                                                <td colspan="6" class="muted">No announcements have been published yet.</td>
                                            </tr>
                                        <?php endif; ?>
                                        <?php foreach ($announcementHistory as $index => $announcement): ?>
                                            <tr>
                                                <td><?php echo e((string) ($index + 1)); ?></td>
                                                <td>
                                                    <strong><?php echo e((string) ($announcement['title'] ?? '')); ?></strong><br>
                                                    <span class="muted"><?php echo e((string) mb_strimwidth((string) ($announcement['message'] ?? ''), 0, 90, '...')); ?></span>
                                                    <?php if (!empty($announcement['attachment_name'])): ?><br><span class="muted">Attachment: <?php echo e((string) $announcement['attachment_name']); ?></span><?php endif; ?>
                                                </td>
                                                <td><span class="badge"><?php echo e(principal_announcement_audience_label((string) ($announcement['audience'] ?? 'both'))); ?></span></td>
                                                <td><span class="badge"><?php echo e((int) ($announcement['is_active'] ?? 1) === 1 ? (!empty($announcement['edited_at']) ? 'Edited' : 'Live') : 'Deleted'); ?></span></td>
                                                <td><?php echo e((string) ($announcement['published_at'] ?? '')); ?></td>
                                                <td>
                                                    <?php if ((int) ($announcement['is_active'] ?? 1) === 1): ?>
                                                        <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                                            <a class="badge" href="principalent.php?edit=<?php echo e((string) ($announcement['id'] ?? 0)); ?>">Edit</a>
                                                            <form method="post" onsubmit="return window.confirm('Delete this announcement?');">
                                                                <input type="hidden" name="action" value="delete_announcement">
                                                                <input type="hidden" name="announcement_id" value="<?php echo e((string) ($announcement['id'] ?? 0)); ?>">
                                                                <button class="badge" style="border:none;cursor:pointer;background:rgba(220,38,38,.14);color:#ffb4b4;" type="submit">Delete</button>
                                                            </form>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="muted">Unavailable</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </article>
                    </div>
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
        const audienceSelect = document.getElementById('announcementAudience');
        const recipientSummary = document.getElementById('recipientSummary');
        const teacherCount = <?php echo json_encode($teacherCount); ?>;
        const parentCount = <?php echo json_encode($parentCount); ?>;

        function updateRecipientSummary() {
            if (!audienceSelect || !recipientSummary) {
                return;
            }

            const audience = audienceSelect.value;
            if (audience === 'teachers') {
                recipientSummary.textContent = `${teacherCount} teacher${teacherCount === 1 ? '' : 's'} will receive this announcement.`;
                return;
            }

            if (audience === 'parents') {
                recipientSummary.textContent = `${parentCount} parent${parentCount === 1 ? '' : 's'} will receive this announcement.`;
                return;
            }

            recipientSummary.textContent = `${teacherCount + parentCount} users will receive this announcement.`;
        }

        if (sidebarToggle && sidebarOverlay) {
            sidebarToggle.addEventListener('click', () => document.body.classList.toggle('sidebar-open'));
            sidebarOverlay.addEventListener('click', () => document.body.classList.remove('sidebar-open'));
        }

        if (audienceSelect) {
            audienceSelect.addEventListener('change', updateRecipientSummary);
            updateRecipientSummary();
        }
    </script>
</body>
</html>