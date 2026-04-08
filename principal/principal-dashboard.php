<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';

$principal = require_role('principal');
$flash = get_flash();
$displayFlash = $flash;
if ($displayFlash && (string) ($displayFlash['type'] ?? '') === 'success' && str_starts_with((string) ($displayFlash['message'] ?? ''), 'Welcome back,')) {
    $displayFlash = null;
}
$principalName = (string) (($principal['first_name'] ?? '') ?: ($principal['full_name'] ?? 'Principal'));
$principalProfilePictureUrl = user_profile_picture_url($principal);
$initialPrincipalPanel = in_array((string) ($_GET['panel'] ?? 'dashboardPanel'), ['dashboardPanel', 'attendancePanel', 'leavePanel', 'teachersPanel', 'parentsPanel'], true)
    ? (string) ($_GET['panel'] ?? 'dashboardPanel')
    : 'dashboardPanel';

$teacherCount = 0;
$pendingTeacherCount = 0;
$parentCount = 0;
$pendingParentCount = 0;
$announcementCountValue = 0;
$studentCount = 0;
$chatUnreadCount = 0;
$announcementHistory = [];
$recentAnnouncements = [];
$teachersDirectory = [];
$parentsDirectory = [];
$attendanceSummaryToday = ['checked_in' => 0, 'checked_out' => 0, 'pending_checkout' => 0];
$recentTeacherAttendance = [];
$pendingLeaveRequestCount = 0;
$pendingLeaveRequests = [];
$recentLeaveRequests = [];

if (is_post() && (string) ($_POST['action'] ?? '') === 'approve_teacher_assignment') {
    $teacherId = (int) ($_POST['teacher_id'] ?? 0);
    $assignedClass = (string) ($_POST['assigned_class'] ?? '');
    $approvalError = null;

    if (approve_teacher_account($teacherId, $assignedClass, $approvalError)) {
        set_flash('success', 'Teacher approved and class assigned successfully.');
    } else {
        set_flash('error', $approvalError ?: 'Unable to approve that teacher right now.');
    }

    redirect('principal-dashboard.php?panel=teachersPanel');
}

if (is_post() && (string) ($_POST['action'] ?? '') === 'approve_parent_account') {
    $parentId = (int) ($_POST['parent_id'] ?? 0);
    $approvalError = null;

    if (approve_parent_account($parentId, $approvalError)) {
        set_flash('success', 'Parent registration approved successfully.');
    } else {
        set_flash('error', $approvalError ?: 'Unable to approve that parent right now.');
    }

    redirect('principal-dashboard.php?panel=parentsPanel');
}

if (is_post() && (string) ($_POST['action'] ?? '') === 'delete_directory_user') {
    $targetUserId = (int) ($_POST['user_id'] ?? 0);
    $targetRole = (string) ($_POST['user_role'] ?? '');
    $deleteError = null;

    if (delete_user_account_by_principal((int) ($principal['id'] ?? 0), $targetUserId, $deleteError)) {
        set_flash('success', ucfirst($targetRole === 'parent' ? 'parent' : 'teacher') . ' account deleted successfully.');
    } else {
        set_flash('error', $deleteError ?: 'Unable to delete that account right now.');
    }

    $redirectPanel = $targetRole === 'parent' ? 'parentsPanel' : 'teachersPanel';
    redirect('principal-dashboard.php?panel=' . $redirectPanel);
}

if (is_post() && (string) ($_POST['action'] ?? '') === 'review_teacher_leave_request') {
    $requestId = (int) ($_POST['request_id'] ?? 0);
    $decision = (string) ($_POST['decision'] ?? '');
    $principalNote = (string) ($_POST['principal_note'] ?? '');
    $reviewError = null;

    if (review_teacher_leave_request((int) ($principal['id'] ?? 0), $requestId, $decision, $principalNote, $reviewError)) {
        set_flash('success', 'Teacher leave request updated successfully.');
    } else {
        set_flash('error', $reviewError ?: 'Unable to review that leave request right now.');
    }

    redirect('principal-dashboard.php?panel=leavePanel');
}

if (db_ready() && $db instanceof mysqli) {
    $teacherCountResult = $db->query("SELECT COUNT(*) AS total FROM users WHERE role = 'teacher' AND approval_status = 'approved'");
    if ($teacherCountResult) {
        $teacherCount = (int) (($teacherCountResult->fetch_assoc()['total'] ?? 0));
        $teacherCountResult->free();
    }

    $pendingTeacherCountResult = $db->query("SELECT COUNT(*) AS total FROM users WHERE role = 'teacher' AND approval_status = 'pending'");
    if ($pendingTeacherCountResult) {
        $pendingTeacherCount = (int) (($pendingTeacherCountResult->fetch_assoc()['total'] ?? 0));
        $pendingTeacherCountResult->free();
    }

    $parentCountResult = $db->query("SELECT COUNT(*) AS total FROM users WHERE role = 'parent' AND approval_status = 'approved'");
    if ($parentCountResult) {
        $parentCount = (int) (($parentCountResult->fetch_assoc()['total'] ?? 0));
        $parentCountResult->free();
    }

    $pendingParentCountResult = $db->query("SELECT COUNT(*) AS total FROM users WHERE role = 'parent' AND approval_status = 'pending'");
    if ($pendingParentCountResult) {
        $pendingParentCount = (int) (($pendingParentCountResult->fetch_assoc()['total'] ?? 0));
        $pendingParentCountResult->free();
    }

    $studentCountResult = $db->query("SELECT COALESCE(SUM(CASE WHEN child_count IS NOT NULL AND child_count > 0 THEN child_count WHEN NULLIF(TRIM(child_name), '') IS NOT NULL THEN 1 ELSE 0 END), 0) AS total FROM users WHERE role = 'parent' AND approval_status = 'approved'");
    if ($studentCountResult) {
        $studentCount = (int) (($studentCountResult->fetch_assoc()['total'] ?? 0));
        $studentCountResult->free();
    }

    $teachersDirectory = fetch_teachers_for_principal();

    $parentsDirectoryResult = $db->query("SELECT id, full_name, first_name, surname, email, child_name, child_count, child_details, approval_status, profile_picture_path, created_at FROM users WHERE role = 'parent' ORDER BY approval_status ASC, created_at DESC, id DESC");
    if ($parentsDirectoryResult) {
        $parentsDirectory = $parentsDirectoryResult->fetch_all(MYSQLI_ASSOC) ?: [];
        $parentsDirectoryResult->free();
    }

    $announcementHistory = fetch_announcements_for_principal((int) $principal['id']);
    $announcementCountValue = count($announcementHistory);
    $recentAnnouncements = array_slice($announcementHistory, 0, 3);
    $attendanceSummaryToday = fetch_teacher_attendance_summary_today();
    $recentTeacherAttendance = fetch_recent_teacher_attendance_for_principal(6);
    $pendingLeaveRequestCount = count_pending_teacher_leave_requests();
    $pendingLeaveRequests = fetch_leave_requests_for_principal('pending', 12);
    $recentLeaveRequests = fetch_leave_requests_for_principal(null, 12);

    foreach (fetch_support_threads_for_principal((int) ($principal['id'] ?? 0)) as $thread) {
        $chatUnreadCount += (int) ($thread['unread_count'] ?? 0);
    }
}

function format_audience_label(string $audience): string
{
    return match ($audience) {
        'teachers' => 'Teachers Only',
        'parents' => 'Parents Only',
        'both' => 'Both Parents and Teachers',
        default => ucfirst($audience),
    };
}

function build_initials(string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return 'PA';
    }

    $parts = preg_split('/\s+/', $name) ?: [];
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $initials .= strtoupper(substr($part, 0, 1));
    }

    return $initials !== '' ? $initials : strtoupper(substr($name, 0, 2));
}

function format_parent_name(array $parent): string
{
    $fullName = trim((string) ($parent['full_name'] ?? ''));
    if ($fullName !== '') {
        return $fullName;
    }

    return trim((string) (($parent['first_name'] ?? '') . ' ' . ($parent['surname'] ?? ''))) ?: 'Parent';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="dark">
    <title>Principal Dashboard | SAMACE TECH LAB</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@400;500;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <style>
        :root {
            --role-accent: #F5A623;
            --text-primary: #fff;
            --text-secondary: #A0A8B8;
            --text-muted: #5A6070;
            --sidebar-width: 250px;
            --font-display: 'Bebas Neue', cursive;
            --font-heading: 'Playfair Display', serif;
            --font-body: 'DM Sans', sans-serif;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        html, body { height:100%; overflow:hidden; }
        body {
            background: #0A0A0F;
            font-family: var(--font-body);
            color: #fff;
            overflow: hidden;
            position: relative;
        }
        a { color: inherit; text-decoration: none; }
        button, input { font: inherit; }
        .orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(140px);
            z-index: 3;
            pointer-events: none;
            animation: orbDrift 20s ease-in-out infinite alternate;
        }
        .orb-gold { width:700px; height:700px; background:radial-gradient(circle, rgba(245,166,35,0.1), transparent 70%); top:-200px; left:50px; }
        .orb-teal { width:500px; height:500px; background:radial-gradient(circle, rgba(0,212,180,0.07), transparent 70%); bottom:-100px; right:100px; animation-direction:alternate-reverse; animation-duration:26s; }
        .orb-blue { width:400px; height:400px; background:radial-gradient(circle, rgba(74,158,255,0.06), transparent 70%); top:40%; right:-50px; animation-duration:32s; }
        @keyframes orbDrift { from { transform: translate(0,0) scale(1); } to { transform: translate(40px,30px) scale(1.06); } }
        #particles-container { position:fixed; inset:0; z-index:2; pointer-events:none; overflow:hidden; }
        @keyframes floatDot {
            0% { transform: translate(0,0) scale(1); opacity:0.5; }
            33% { transform: translate(10px,-15px) scale(1.2); opacity:1; }
            66% { transform: translate(-8px,8px) scale(0.8); opacity:0.3; }
            100% { transform: translate(15px,-20px) scale(1); opacity:0.6; }
        }
        ::-webkit-scrollbar { width:5px; height:5px; }
        ::-webkit-scrollbar-track { background:#0A0A0F; }
        ::-webkit-scrollbar-thumb { background:rgba(245,166,35,0.35); border-radius:3px; }
        ::-webkit-scrollbar-thumb:hover { background:#F5A623; }
        ::selection { background:rgba(245,166,35,0.2); color:#fff; }
        .shell {
            min-height: 100vh;
            display: flex;
            position: relative;
            z-index: 4;
        }
        .hamburger {
            display:none; position:fixed; top:16px; left:16px; z-index:200; background:rgba(15,15,26,0.9);
            border:1px solid rgba(255,255,255,0.1); border-radius:10px; padding:10px; color:#F5A623; cursor:pointer; backdrop-filter:blur(12px);
        }
        .sidebar-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:99; backdrop-filter:blur(4px); opacity:0; pointer-events:none; transition:opacity .25s ease; }
        .sidebar-overlay.show { opacity:1; pointer-events:auto; }
        .sidebar {
            position:fixed; top:0; left:0; width:250px; height:100vh; background:rgba(13,13,24,0.95); border-right:1px solid rgba(255,255,255,0.06);
            display:flex; flex-direction:column; z-index:100; backdrop-filter:blur(24px); -webkit-backdrop-filter:blur(24px); overflow-y:auto; transition:transform .4s cubic-bezier(0.23,1,0.32,1);
        }
        .sidebar-logo { padding:28px 24px 20px; border-bottom:1px solid rgba(255,255,255,0.06); position:relative; }
        .sidebar-logo::after { content:''; position:absolute; bottom:0; left:24px; right:24px; height:1px; background:linear-gradient(90deg, transparent, rgba(245,166,35,0.3), transparent); }
        .sidebar-school-name { font-family:var(--font-display); font-size:17px; letter-spacing:2.5px; color:#F5A623; display:block; line-height:1; }
        .sidebar-school-sub { font-size:9px; color:#5A6070; letter-spacing:2px; text-transform:uppercase; display:block; margin-top:4px; }
        .sidebar-role-badge { display:inline-flex; align-items:center; gap:5px; background:rgba(245,166,35,0.1); border:1px solid rgba(245,166,35,0.2); color:#F5A623; font-size:10px; font-weight:600; letter-spacing:1px; text-transform:uppercase; padding:4px 10px; border-radius:50px; margin-top:10px; }
        .nav-section-label { font-size:9px; font-weight:700; letter-spacing:2.5px; text-transform:uppercase; color:#5A6070; padding:20px 24px 8px; }
        .sidebar nav { padding:8px 0; flex:1; }
        .sidebar nav a, .sidebar nav button {
            display:flex; align-items:center; gap:12px; width:calc(100% - 12px); margin-right:12px; padding:13px 24px; color:#A0A8B8; font-size:14px; font-weight:500;
            border-left:3px solid transparent; transition:color .2s ease, background .2s ease, border-color .2s ease; position:relative; border-radius:0 12px 12px 0; background:transparent; border-top:none; border-right:none; border-bottom:none; cursor:pointer; text-align:left;
        }
        .nav-icon { font-size:18px; width:22px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .nav-label { flex:1; }
        .nav-badge { background:#FF4757; color:#fff; font-size:10px; font-weight:700; min-width:18px; height:18px; border-radius:50px; padding:0 5px; display:flex; align-items:center; justify-content:center; }
        .sidebar nav a:hover, .sidebar nav button:hover { color:#fff; background:rgba(255,255,255,0.05); border-left-color:rgba(245,166,35,0.4); transform:none; }
        .sidebar nav .active { color:#F5A623; background:rgba(245,166,35,0.08); border-left-color:#F5A623; font-weight:600; }
        .sidebar nav .active::before { content:''; position:absolute; right:16px; top:50%; transform:translateY(-50%); width:6px; height:6px; border-radius:50%; background:#F5A623; box-shadow:0 0 8px rgba(245,166,35,0.8); }
        .logout-link { color:#FF4757 !important; margin-top:4px; }
        .logout-link:hover { background:rgba(255,71,87,0.08) !important; border-left-color:#FF4757 !important; color:#FF4757 !important; }
        .sidebar-footer { padding:16px 24px 24px; border-top:1px solid rgba(255,255,255,0.06); position:relative; }
        .sidebar-footer::before { content:''; position:absolute; top:0; left:24px; right:24px; height:1px; background:linear-gradient(90deg, transparent, rgba(255,255,255,0.08), transparent); }
        .sidebar-user { display:flex; align-items:center; gap:12px; padding:10px 12px; border-radius:14px; background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.06); }
        .sidebar-avatar { width:38px; height:38px; border-radius:50%; overflow:hidden; background:linear-gradient(135deg, #F5A623, rgba(245,166,35,0.4)); display:flex; align-items:center; justify-content:center; font-family:var(--font-display); font-size:17px; color:#0A0A0F; box-shadow:0 0 15px rgba(245,166,35,0.3); }
        .sidebar-user-name { font-size:13px; font-weight:600; color:#fff; display:block; }
        .sidebar-user-role { font-size:11px; color:#5A6070; display:block; }
        .main-content {
            position: fixed;
            top: 0;
            left: var(--sidebar-width);
            right: 0;
            bottom: 0;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 32px 20px 40px;
            z-index: 1;
        }
        .panel { display:none; }
        .panel.active { display:block; }
        .page-header { display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; margin-bottom:36px; padding-bottom:28px; border-bottom:1px solid rgba(255,255,255,0.06); position:relative; gap:18px; }
        .page-header::after { content:''; position:absolute; bottom:-1px; left:0; width:80px; height:2px; background:linear-gradient(90deg, #F5A623, transparent); border-radius:2px; }
        .page-header-main { display:flex; align-items:center; gap:18px; min-width:0; flex:1 1 520px; max-width:100%; }
        .dashboard-profile-circle { width:96px; height:96px; min-width:96px; border-radius:50%; overflow:hidden; flex-shrink:0; display:grid; place-items:center; background:linear-gradient(135deg, rgba(245,166,35,0.26), rgba(74,158,255,0.16)); border:1px solid rgba(245,166,35,0.24); box-shadow:0 18px 44px rgba(8,16,30,0.34); color:#fff2d8; font-family:var(--font-display); font-size:34px; }
        .dashboard-profile-circle img { width:100%; height:100%; object-fit:cover; object-position:center; display:block; border-radius:inherit; }
        .dashboard-profile-circle.has-photo img { transform:scale(1.18); transform-origin:center; }
        .page-header-copy { min-width:0; flex:1 1 auto; }
        .welcome-title { font-family:var(--font-heading); font-size:clamp(2rem, 3.1vw, 36px); font-weight:700; color:#fff; line-height:1.12; overflow-wrap:anywhere; word-break:break-word; }
        .welcome-subtitle { font-size:14px; color:#A0A8B8; margin-top:8px; max-width:760px; line-height:1.55; }
        .date-badge { background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); border-radius:12px; padding:10px 18px; font-size:13px; color:#A0A8B8; backdrop-filter:blur(10px); white-space:nowrap; font-weight:500; flex:0 0 auto; align-self:flex-start; margin-left:auto; }
        .section-title { font-family:var(--font-heading); font-size:20px; font-weight:700; color:#fff; margin-bottom:20px; display:flex; align-items:center; gap:10px; }
        .section-title::after { content:''; flex:1; height:1px; background:linear-gradient(90deg, rgba(255,255,255,0.08), transparent); }
        .stats-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:20px; margin-bottom:36px; }
        .stat-card { background:rgba(30,30,40,0.82); border:1px solid rgba(255,255,255,0.08); border-radius:20px; padding:24px; position:relative; overflow:hidden; backdrop-filter:blur(8px); transition:border-color .2s ease, box-shadow .2s ease; animation:none; z-index:4; }
        .stat-card:nth-child(1) { animation-delay:.1s; }
        .stat-card:nth-child(2) { animation-delay:.2s; }
        .stat-card:nth-child(3) { animation-delay:.3s; }
        .stat-card:nth-child(4) { animation-delay:.4s; }
        @keyframes cardReveal { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }
        .stat-card:hover { transform:none; border-color:rgba(255,255,255,0.14); box-shadow:0 20px 60px rgba(0,0,0,0.4); }
        .stat-card::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; border-radius:20px 20px 0 0; }
        .stat-card.gold::before { background:linear-gradient(90deg, #F5A623, rgba(245,166,35,0.3)); }
        .stat-card.teal::before { background:linear-gradient(90deg, #00D4B4, rgba(0,212,180,0.3)); }
        .stat-card.blue::before { background:linear-gradient(90deg, #4A9EFF, rgba(74,158,255,0.3)); }
        .stat-card.purple::before { background:linear-gradient(90deg, #9B6DFF, rgba(155,109,255,0.3)); }
        .stat-icon { font-size:28px; margin-bottom:14px; display:block; }
        .stat-label { font-size:11px; font-weight:600; letter-spacing:1.5px; text-transform:uppercase; color:#5A6070; margin-bottom:8px; display:block; }
        .stat-value { font-family:var(--font-display); font-size:52px; line-height:1; color:#fff; display:block; }
        .glass-panel { background:rgba(30,30,40,0.82); border:1px solid rgba(255,255,255,0.07); border-radius:20px; padding:28px; backdrop-filter:blur(8px); position:relative; overflow:hidden; margin-bottom:24px; z-index:4; }
        .glass-panel::before { content:''; position:absolute; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg, transparent, rgba(255,255,255,0.08), transparent); }
        .dashboard-grid, .directory-grid { display:grid; grid-template-columns:1.4fr 1fr; gap:24px; }
        .dark-table { width:100%; border-collapse:collapse; }
        .dark-table thead tr { border-bottom:1px solid rgba(255,255,255,0.08); }
        .dark-table thead th { font-size:10px; font-weight:700; letter-spacing:2px; text-transform:uppercase; color:#5A6070; padding:10px 16px; text-align:left; }
        .dark-table tbody tr { border-bottom:1px solid rgba(255,255,255,0.04); }
        .dark-table tbody tr:hover { background:rgba(255,255,255,0.04); }
        .dark-table tbody td { padding:14px 16px; font-size:14px; color:#A0A8B8; }
        .dark-table tbody td:first-child { color:#fff; font-weight:500; }
        .badge { display:inline-flex; align-items:center; gap:5px; padding:4px 12px; border-radius:50px; font-size:11px; font-weight:600; letter-spacing:.5px; }
        .badge-gold { background:rgba(245,166,35,0.12); color:#F5A623; border:1px solid rgba(245,166,35,0.2); }
        .badge-teal { background:rgba(0,212,180,0.12); color:#00D4B4; border:1px solid rgba(0,212,180,0.2); }
        .badge-blue { background:rgba(74,158,255,0.12); color:#4A9EFF; border:1px solid rgba(74,158,255,0.2); }
        .link-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:16px; }
        .action-link { display:block; padding:18px 20px; border-radius:16px; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); color:#fff; transition:border-color .2s ease, box-shadow .2s ease; }
        .action-link:hover { transform:none; border-color:rgba(245,166,35,0.25); box-shadow:0 18px 40px rgba(0,0,0,0.3); }
        .action-link strong { display:block; margin-bottom:6px; color:#F5A623; font-size:14px; }
        .action-link span { font-size:13px; color:#A0A8B8; }
        .search-input { width:100%; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.1); border-radius:14px; padding:13px 16px; color:#fff; outline:none; transition:border-color .2s ease, box-shadow .2s ease; margin-bottom:18px; }
        .search-input:focus { border-color:#F5A623; box-shadow:0 0 0 3px rgba(245,166,35,0.1); }
        .search-input::placeholder { color:#5A6070; }
        .card-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:18px; align-items:stretch; }
        .person-card { background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); border-radius:18px; padding:18px; transition:border-color .2s ease, box-shadow .2s ease; display:flex; flex-direction:column; min-width:0; width:100%; overflow:hidden; }
        .person-card:hover { transform:none; border-color:rgba(255,255,255,0.14); box-shadow:0 20px 40px rgba(0,0,0,0.28); }
        .person-card.hidden { display:none; }
        .person-head { display:flex; justify-content:space-between; gap:12px; align-items:flex-start; margin-bottom:14px; flex-wrap:wrap; }
        .person-meta-wrap { display:flex; align-items:center; gap:12px; min-width:0; flex:1; }
        .person-meta-wrap > div { min-width:0; flex:1; }
        .person-avatar { width:52px; min-width:52px; max-width:52px; height:52px; min-height:52px; max-height:52px; flex:0 0 52px; aspect-ratio:1 / 1; border-radius:999px; overflow:hidden; display:flex; align-items:center; justify-content:center; font-family:var(--font-display); font-size:18px; line-height:1; color:#0A0A0F; background:linear-gradient(135deg, #F5A623, rgba(245,166,35,0.5)); box-shadow:0 10px 24px rgba(0,0,0,0.22); }
        .sidebar-avatar img, .person-avatar img { width:100%; height:100%; object-fit:cover; object-position:center; display:block; border-radius:inherit; }
        .person-name { font-weight:700; color:#fff; margin-bottom:4px; overflow-wrap:anywhere; word-break:break-word; }
        .person-name-link { color:#fff; text-decoration:none; transition:none; }
        .person-name-link:hover { color:#F5A623; }
        .person-meta { font-size:13px; color:#A0A8B8; margin-bottom:4px; overflow-wrap:anywhere; word-break:break-word; }
        .person-actions { display:grid; grid-template-columns:repeat(auto-fit, minmax(92px, 1fr)); gap:10px; margin-top:14px; align-items:stretch; width:100%; }
        .person-actions form { display:flex; min-width:0; }
        .person-actions > * { min-width:0; width:100%; }
        .action-btn { display:inline-flex; align-items:center; justify-content:center; width:100%; min-height:40px; padding:0 14px; border-radius:12px; border:1px solid rgba(255,255,255,0.08); background:rgba(255,255,255,0.06); color:#fff; transition:border-color .2s ease, box-shadow .2s ease, background .2s ease, color .2s ease; text-align:center; }
        .action-btn.primary { background:linear-gradient(135deg, #F5A623, #e8950f); color:#0A0A0F; border:none; }
        .action-btn.danger { background:rgba(255,71,87,0.12); border-color:rgba(255,71,87,0.24); color:#ffb0b8; }
        .action-btn:hover { transform:none; }
        .action-link strong, .action-link span { overflow-wrap:anywhere; word-break:break-word; }
        html.shared-index-background body.principal-dashboard-page .ui-glass-surface,
        html.shared-index-background body.principal-dashboard-page .ui-float,
        html.shared-index-background body.principal-dashboard-page .ui-motion-stage,
        html.shared-index-background body.principal-dashboard-page .ui-reactive,
        html.shared-index-background body.principal-dashboard-page .ui-reactive:hover,
        html.shared-index-background body.principal-dashboard-page .ui-reveal,
        html.shared-index-background body.principal-dashboard-page .ui-reveal.is-visible {
            animation: none !important;
            transform: none !important;
            transition: none !important;
        }
        html.shared-index-background body.principal-dashboard-page .ui-glass-surface::after,
        html.shared-index-background body.principal-dashboard-page .ui-glass-surface::before,
        html.shared-index-background body.principal-dashboard-page .ui-reactive::before,
        html.shared-index-background body.principal-dashboard-page .ui-ripple {
            opacity: 0 !important;
            display: none !important;
        }
        html.shared-index-background body.principal-dashboard-page .ui-reactive:hover {
            box-shadow: none !important;
        }
        html.shared-index-background body.principal-dashboard-page .shared-background-canvas,
        html.shared-index-background body.principal-dashboard-page #shared-background-canvas,
        html.shared-index-background body.principal-dashboard-page #backgroundCanvas,
        html.shared-index-background body.principal-dashboard-page #particles-container,
        html.shared-index-background body.principal-dashboard-page .shared-bg-orb,
        html.shared-index-background body.principal-dashboard-page .orb {
            display: none !important;
        }
        .muted-empty { color:#A0A8B8; text-align:center; padding:30px 20px; border:1px dashed rgba(255,255,255,0.12); border-radius:18px; }
        .toast { position:fixed; top:24px; right:24px; z-index:9999; background:rgba(15,15,26,0.95); border:1px solid rgba(255,255,255,0.1); border-left:4px solid #F5A623; border-radius:12px; padding:16px 20px; color:#fff; max-width:320px; backdrop-filter:blur(20px); box-shadow:0 20px 60px rgba(0,0,0,0.5); transform:translateX(120%); transition:transform .4s cubic-bezier(0.23,1,0.32,1); }
        .toast.show { transform:translateX(0); }
        .flash-banner { margin-bottom:20px; padding:14px 18px; border-radius:16px; border:1px solid rgba(255,255,255,0.08); background:rgba(255,255,255,0.04); color:#fff; }
        .attendance-summary-row { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:14px; margin-bottom:20px; }
        .attendance-summary-chip { padding:16px 18px; border-radius:16px; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); }
        .attendance-summary-chip strong { display:block; color:#fff; font-size:1.4rem; margin-top:6px; }
        .attendance-summary-chip span { color:#A0A8B8; font-size:12px; letter-spacing:1.3px; text-transform:uppercase; }
        .review-form { display:grid; gap:10px; margin-top:14px; }
        .review-form textarea { width:100%; min-height:88px; resize:vertical; padding:12px 14px; border-radius:12px; border:1px solid rgba(255,255,255,0.1); background:rgba(255,255,255,0.06); color:#fff; outline:none; }
        .review-actions { display:flex; gap:10px; flex-wrap:wrap; }
        .flash-banner.error { background:rgba(255,71,87,0.12); color:#ffb0b8; }
        .flash-banner.success { background:rgba(0,212,180,0.12); color:#a6fff1; }
        .approve-form { display:grid; gap:10px; margin-top:14px; }
        .approve-form input { width:100%; min-height:40px; padding:0 12px; border-radius:12px; border:1px solid rgba(255,255,255,0.1); background:rgba(255,255,255,0.06); color:#fff; outline:none; }
        .approve-form input:focus { border-color:#F5A623; box-shadow:0 0 0 3px rgba(245,166,35,0.1); }
        @media (max-width:1024px) {
            .stats-grid { grid-template-columns:repeat(2,1fr); }
            .dashboard-grid, .directory-grid { grid-template-columns:1fr; }
            .card-grid, .attendance-summary-row { grid-template-columns:repeat(2,1fr); }
        }
        @media (max-width:768px) {
            .sidebar {
                transform:translateX(-100%) !important;
                opacity:0 !important;
                visibility:hidden !important;
                pointer-events:none !important;
                width:min(84vw, 320px) !important;
                max-width:320px !important;
                box-shadow:0 0 40px rgba(0,0,0,0.45) !important;
            }
            .sidebar.open {
                transform:translateX(0) !important;
                opacity:1 !important;
                visibility:visible !important;
                pointer-events:auto !important;
            }
            .sidebar-overlay { display:block !important; }
            .hamburger { display:flex !important; top:14px; left:14px; }
            .main-content { left:0 !important; width:100% !important; padding:84px 16px 24px !important; }
            .page-header { flex-direction:column !important; gap:12px !important; margin-bottom:24px !important; }
            .page-header-main { flex-direction:column; align-items:flex-start; gap:14px; flex-basis:auto; }
            .dashboard-profile-circle { width:84px; height:84px; min-width:84px; }
            .welcome-title { font-size:26px !important; }
            .date-badge { width:100%; white-space:normal; margin-left:0; }
            .stats-grid, .card-grid, .attendance-summary-row, .person-actions { grid-template-columns:1fr !important; }
            .glass-panel, .stat-card, .person-card { padding:18px !important; border-radius:18px !important; }
            .dark-table thead th, .dark-table tbody td { padding:12px 10px; }
            body .glass-panel, body .stat-card {
                background:rgba(30,30,40,0.82) !important;
                border:1px solid rgba(255,255,255,0.10) !important;
                box-shadow:none !important;
                backdrop-filter:blur(8px) !important;
                -webkit-backdrop-filter:blur(8px) !important;
                filter:none !important;
            }
        }
        @media (max-width:480px) {
            .stats-grid { grid-template-columns:1fr; }
            .stat-value { font-size:40px; }
            .link-grid { grid-template-columns:1fr; }
            .main-content { padding:78px 14px 18px !important; }
            .welcome-title { font-size:22px !important; }
            .person-head, .review-actions { flex-direction:column; align-items:stretch; }
        }
    </style>
    <?php require_once dirname(__DIR__) . '/theme-shared.php'; ?>
    <?php require_once __DIR__ . '/principal-shared.php'; ?>
</head>
<body class="principal-dashboard-page principal-page">
    <div class="orb orb-gold"></div>
    <div class="orb orb-teal"></div>
    <div class="orb orb-blue"></div>
    <div id="particles-container"></div>

    <button class="hamburger" id="hamburger" type="button" aria-label="Toggle sidebar">☰</button>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="shell">
    <?php
        $principalSidebarActive = match ($initialPrincipalPanel) {
            'attendancePanel' => 'attendance',
            'leavePanel' => 'leave',
            'teachersPanel' => 'teachers',
            'parentsPanel' => 'parents',
            default => 'dashboard',
        };
        require __DIR__ . '/principal-sidebar.php';
    ?>

    <main class="main-content">
        <?php if ($displayFlash): ?>
            <div class="flash-banner <?php echo e((string) ($displayFlash['type'] ?? 'success')); ?>"><?php echo e((string) $displayFlash['message']); ?></div>
        <?php endif; ?>
        <section class="panel <?php echo $initialPrincipalPanel === 'dashboardPanel' ? 'active' : ''; ?>" id="dashboardPanel">
            <div class="page-header">
                <div class="page-header-main">
                    <div class="dashboard-profile-circle<?php echo $principalProfilePictureUrl !== null ? ' has-photo' : ''; ?>">
                        <?php if ($principalProfilePictureUrl !== null): ?>
                            <img src="<?php echo e($principalProfilePictureUrl); ?>" alt="<?php echo e($principalName); ?> profile picture">
                        <?php else: ?>
                            <?php echo e(initials_from_name($principalName, 'Principal')); ?>
                        <?php endif; ?>
                    </div>
                    <div class="page-header-copy">
                        <h1 class="welcome-title">Welcome back, <?php echo e($principalName); ?></h1>
                        <p class="welcome-subtitle">Monitor staff, family accounts, and school communication from one executive space.</p>
                    </div>
                </div>
                <div class="date-badge" id="todayDate">Loading date...</div>
            </div>

            <div class="stats-grid">
                <article class="stat-card gold"><span class="stat-icon">👩‍🏫</span><span class="stat-label">Total Teachers</span><span class="stat-value" data-count="<?php echo e((string) $teacherCount); ?>">0</span></article>
                <article class="stat-card teal"><span class="stat-icon">👨‍👩‍👧</span><span class="stat-label">Total Parents</span><span class="stat-value" data-count="<?php echo e((string) $parentCount); ?>">0</span></article>
                <article class="stat-card blue"><span class="stat-icon">📢</span><span class="stat-label">Announcements Sent</span><span class="stat-value" data-count="<?php echo e((string) $announcementCountValue); ?>">0</span></article>
                <article class="stat-card purple"><span class="stat-icon">🎓</span><span class="stat-label">Students Listed</span><span class="stat-value" data-count="<?php echo e((string) $studentCount); ?>">0</span></article>
            </div>
            <div class="dashboard-grid">
                <div class="glass-panel">
                    <h2 class="section-title">Recent Announcements</h2>
                    <div style="overflow-x:auto;">
                        <table class="dark-table">
                            <thead>
                                <tr><th>Title</th><th>Audience</th><th>Date</th></tr>
                            </thead>
                            <tbody>
                                <?php if (!$recentAnnouncements): ?>
                                    <tr><td colspan="3">No announcements have been sent yet.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($recentAnnouncements as $announcement): ?>
                                    <tr>
                                        <td><?php echo e((string) $announcement['title']); ?></td>
                                        <td><span class="badge badge-gold"><?php echo e(format_audience_label((string) $announcement['audience'])); ?></span></td>
                                        <td><?php echo e((string) date('M j, Y', strtotime((string) ($announcement['published_at'] ?? 'now')))); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="glass-panel">
                    <h2 class="section-title">Quick Access</h2>
                    <div class="link-grid">
                        <a class="action-link" href="principalent.php"><strong>Publish Update</strong><span>Open the announcement center and send a new school-wide message.</span></a>
                        <a class="action-link" href="chat.php"><strong>Open Chats</strong><span>Jump directly into staff and parent conversations.</span></a>
                        <a class="action-link" href="#" data-panel-target="teachersPanel"><strong>View Teachers</strong><span>Inspect the teaching directory and open quick actions.</span></a>
                        <a class="action-link" href="#" data-panel-target="parentsPanel"><strong>View Parents</strong><span>Check parent registrations and child listings.</span></a>
                    </div>
                </div>
            </div>

            <div class="glass-panel">
                <h2 class="section-title">Teacher Attendance Today</h2>
                <div class="attendance-summary-row">
                    <div class="attendance-summary-chip"><span>Checked In</span><strong><?php echo e((string) ($attendanceSummaryToday['checked_in'] ?? 0)); ?></strong></div>
                    <div class="attendance-summary-chip"><span>Checked Out</span><strong><?php echo e((string) ($attendanceSummaryToday['checked_out'] ?? 0)); ?></strong></div>
                    <div class="attendance-summary-chip"><span>Awaiting Check-Out</span><strong><?php echo e((string) ($attendanceSummaryToday['pending_checkout'] ?? 0)); ?></strong></div>
                </div>
                <?php if (!$recentTeacherAttendance): ?>
                    <div class="muted-empty">No teacher attendance records have been submitted yet.</div>
                <?php else: ?>
                    <div class="card-grid">
                        <?php foreach ($recentTeacherAttendance as $attendanceRow): ?>
                            <?php
                                $attendanceTeacherName = display_name_from_user($attendanceRow, 'Teacher');
                                $checkedOut = (string) ($attendanceRow['check_out_at'] ?? '') !== '';
                                $checkedIn = (string) ($attendanceRow['check_in_at'] ?? '') !== '';
                            ?>
                            <article class="person-card">
                                <div class="person-head">
                                    <div class="person-meta-wrap">
                                        <?php echo render_avatar_html($attendanceRow, 'person-avatar', 'Teacher'); ?>
                                        <div>
                                            <div class="person-name"><?php echo e($attendanceTeacherName); ?></div>
                                            <div class="person-meta"><?php echo e((string) ($attendanceRow['email'] ?? '')); ?></div>
                                        </div>
                                    </div>
                                    <span class="badge <?php echo $checkedOut ? 'badge-teal' : ($checkedIn ? 'badge-gold' : 'badge-blue'); ?>"><?php echo e($checkedOut ? 'Checked Out' : ($checkedIn ? 'Checked In' : 'Pending')); ?></span>
                                </div>
                                <div class="person-meta">Date: <?php echo e((string) date('D, j M Y', strtotime((string) ($attendanceRow['attendance_date'] ?? 'now')))); ?></div>
                                <div class="person-meta">Check In: <?php echo e($checkedIn ? (string) date('g:i A', strtotime((string) $attendanceRow['check_in_at'])) : 'Not yet'); ?></div>
                                <div class="person-meta">Check Out: <?php echo e($checkedOut ? (string) date('g:i A', strtotime((string) $attendanceRow['check_out_at'])) : 'Not yet'); ?></div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="panel <?php echo $initialPrincipalPanel === 'teachersPanel' ? 'active' : ''; ?>" id="teachersPanel">
            <div class="page-header">
                <div>
                    <h1 class="welcome-title">Teachers Directory</h1>
                    <p class="welcome-subtitle">Review the academic team and jump into teacher-focused actions.</p>
                </div>
            </div>

            <div class="glass-panel">
                <h2 class="section-title">Teaching Staff</h2>
                <?php if ($pendingTeacherCount > 0): ?>
                    <div class="flash-banner" style="margin-top:0; margin-bottom:18px;">There <?php echo $pendingTeacherCount === 1 ? 'is' : 'are'; ?> <?php echo e((string) $pendingTeacherCount); ?> pending teacher registration<?php echo $pendingTeacherCount === 1 ? '' : 's'; ?> waiting for class assignment and approval.</div>
                <?php endif; ?>
                <input class="search-input" id="teacherSearch" type="text" placeholder="Search by teacher name, class, or subject...">
                <?php if (!$teachersDirectory): ?>
                    <div class="muted-empty">No teacher accounts have been registered yet.</div>
                <?php else: ?>
                    <div class="card-grid" id="teacherGrid">
                        <?php foreach ($teachersDirectory as $teacherRecord): ?>
                            <?php
                                $teacherName = trim((string) ($teacherRecord['full_name'] ?? ''));
                                if ($teacherName === '') {
                                    $teacherName = trim((string) (($teacherRecord['first_name'] ?? '') . ' ' . ($teacherRecord['surname'] ?? '')));
                                }
                                if ($teacherName === '') {
                                    $teacherName = 'Teacher';
                                }
                                $subject = trim((string) ($teacherRecord['teaching_subject'] ?? ''));
                                $className = trim((string) ($teacherRecord['teaching_class'] ?? ''));
                                $requestedClassName = trim((string) ($teacherRecord['requested_teaching_class'] ?? ''));
                                $approvalStatus = (string) ($teacherRecord['approval_status'] ?? 'approved');
                                $teacherMeta = $subject !== '' ? $subject : ($className !== '' ? 'Class ' . $className : ($requestedClassName !== '' ? 'Requested ' . $requestedClassName : 'Teacher'));
                                $teacherSearchText = strtolower(trim($teacherName . ' ' . $teacherMeta . ' ' . $className . ' ' . $requestedClassName . ' ' . $approvalStatus));
                                $teacherDetailsHref = 'teacher-details.php?id=' . rawurlencode((string) ($teacherRecord['id'] ?? 0));
                            ?>
                            <article class="person-card" data-search="<?php echo e($teacherSearchText); ?>">
                                <div class="person-head">
                                    <div class="person-meta-wrap">
                                        <?php echo render_avatar_html($teacherRecord, 'person-avatar', 'Teacher'); ?>
                                        <div>
                                            <div class="person-name"><a class="person-name-link" href="<?php echo e($teacherDetailsHref); ?>"><?php echo e($teacherName); ?></a></div>
                                            <div class="person-meta"><?php echo e($teacherMeta); ?></div>
                                        </div>
                                    </div>
                                    <span class="badge <?php echo $approvalStatus === 'pending' ? 'badge-gold' : 'badge-teal'; ?>"><?php echo e($approvalStatus === 'pending' ? 'Pending Approval' : 'Approved'); ?></span>
                                </div>
                                <div class="person-meta">Class: <?php echo e($className !== '' ? $className : 'Not assigned yet'); ?></div>
                                <?php if ($approvalStatus === 'pending'): ?>
                                    <div class="person-meta">Requested class: <?php echo e($requestedClassName !== '' ? $requestedClassName : 'No preference submitted'); ?></div>
                                <?php endif; ?>
                                <div class="person-meta"><?php echo e((string) ($teacherRecord['email'] ?? 'No email address')); ?></div>
                                <?php if ($approvalStatus === 'pending'): ?>
                                    <div class="person-actions">
                                        <a class="action-btn" href="<?php echo e($teacherDetailsHref); ?>">Edit</a>
                                        <a class="action-btn primary" href="<?php echo e($teacherDetailsHref); ?>">Review &amp; Approve</a>
                                        <form method="post" onsubmit="return window.confirm('Delete <?php echo e(addslashes($teacherName)); ?> permanently?');">
                                            <input type="hidden" name="action" value="delete_directory_user">
                                            <input type="hidden" name="user_id" value="<?php echo e((string) ($teacherRecord['id'] ?? 0)); ?>">
                                            <input type="hidden" name="user_role" value="teacher">
                                            <button class="action-btn danger" type="submit">Delete</button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <div class="person-actions">
                                        <a class="action-btn" href="<?php echo e($teacherDetailsHref); ?>">Edit</a>
                                        <a class="action-btn primary" href="chat.php?contact_role=Teacher&amp;contact_name=<?php echo rawurlencode($teacherName); ?>&amp;contact_email=<?php echo rawurlencode((string) ($teacherRecord['email'] ?? '')); ?>&amp;contact_meta=<?php echo rawurlencode($teacherMeta); ?>">Chat</a>
                                        <button class="action-btn" type="button" data-toast="<?php echo e($teacherName . ' | ' . ($subject !== '' ? $subject : 'No subject') . ' | ' . ($className !== '' ? $className : 'No class')); ?>">Profile</button>
                                        <form method="post" onsubmit="return window.confirm('Delete <?php echo e(addslashes($teacherName)); ?> permanently?');">
                                            <input type="hidden" name="action" value="delete_directory_user">
                                            <input type="hidden" name="user_id" value="<?php echo e((string) ($teacherRecord['id'] ?? 0)); ?>">
                                            <input type="hidden" name="user_role" value="teacher">
                                            <button class="action-btn danger" type="submit">Delete</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="panel <?php echo $initialPrincipalPanel === 'attendancePanel' ? 'active' : ''; ?>" id="attendancePanel">
            <div class="page-header">
                <div>
                    <h1 class="welcome-title">Teachers Attendance</h1>
                    <p class="welcome-subtitle">Review teacher check-in and check-out records from one place.</p>
                </div>
            </div>

            <div class="glass-panel">
                <h2 class="section-title">Today Summary</h2>
                <div class="attendance-summary-row">
                    <div class="attendance-summary-chip"><span>Checked In</span><strong><?php echo e((string) ($attendanceSummaryToday['checked_in'] ?? 0)); ?></strong></div>
                    <div class="attendance-summary-chip"><span>Checked Out</span><strong><?php echo e((string) ($attendanceSummaryToday['checked_out'] ?? 0)); ?></strong></div>
                    <div class="attendance-summary-chip"><span>Awaiting Check-Out</span><strong><?php echo e((string) ($attendanceSummaryToday['pending_checkout'] ?? 0)); ?></strong></div>
                </div>
            </div>

            <div class="glass-panel">
                <h2 class="section-title">Recent Teacher Attendance</h2>
                <?php if (!$recentTeacherAttendance): ?>
                    <div class="muted-empty">No teacher attendance records have been submitted yet.</div>
                <?php else: ?>
                    <div class="card-grid">
                        <?php foreach ($recentTeacherAttendance as $attendanceRow): ?>
                            <?php
                                $attendanceTeacherName = display_name_from_user($attendanceRow, 'Teacher');
                                $checkedOut = (string) ($attendanceRow['check_out_at'] ?? '') !== '';
                                $checkedIn = (string) ($attendanceRow['check_in_at'] ?? '') !== '';
                            ?>
                            <article class="person-card">
                                <div class="person-head">
                                    <div class="person-meta-wrap">
                                        <?php echo render_avatar_html($attendanceRow, 'person-avatar', 'Teacher'); ?>
                                        <div>
                                            <div class="person-name"><?php echo e($attendanceTeacherName); ?></div>
                                            <div class="person-meta"><?php echo e((string) ($attendanceRow['email'] ?? '')); ?></div>
                                        </div>
                                    </div>
                                    <span class="badge <?php echo $checkedOut ? 'badge-teal' : ($checkedIn ? 'badge-gold' : 'badge-blue'); ?>"><?php echo e($checkedOut ? 'Checked Out' : ($checkedIn ? 'Checked In' : 'Pending')); ?></span>
                                </div>
                                <div class="person-meta">Date: <?php echo e((string) date('D, j M Y', strtotime((string) ($attendanceRow['attendance_date'] ?? 'now')))); ?></div>
                                <div class="person-meta">Check In: <?php echo e($checkedIn ? (string) date('g:i A', strtotime((string) $attendanceRow['check_in_at'])) : 'Not yet'); ?></div>
                                <div class="person-meta">Check Out: <?php echo e($checkedOut ? (string) date('g:i A', strtotime((string) $attendanceRow['check_out_at'])) : 'Not yet'); ?></div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="panel <?php echo $initialPrincipalPanel === 'leavePanel' ? 'active' : ''; ?>" id="leavePanel">
            <div class="page-header">
                <div>
                    <h1 class="welcome-title">Leave Requests</h1>
                    <p class="welcome-subtitle">Approve or decline teacher leave and vacation requests.</p>
                </div>
            </div>

            <div class="glass-panel">
                <h2 class="section-title">Pending Requests</h2>
                <?php if (!$pendingLeaveRequests): ?>
                    <div class="muted-empty">There are no pending leave requests right now.</div>
                <?php else: ?>
                    <div class="card-grid">
                        <?php foreach ($pendingLeaveRequests as $leaveRequest): ?>
                            <?php $leaveTeacherName = display_name_from_user($leaveRequest, 'Teacher'); ?>
                            <article class="person-card">
                                <div class="person-head">
                                    <div class="person-meta-wrap">
                                        <?php echo render_avatar_html($leaveRequest, 'person-avatar', 'Teacher'); ?>
                                        <div>
                                            <div class="person-name"><?php echo e($leaveTeacherName); ?></div>
                                            <div class="person-meta"><?php echo e(ucfirst((string) ($leaveRequest['leave_type'] ?? 'leave'))); ?> request</div>
                                        </div>
                                    </div>
                                    <span class="badge badge-gold">Pending</span>
                                </div>
                                <div class="person-meta">Dates: <?php echo e((string) date('j M Y', strtotime((string) ($leaveRequest['start_date'] ?? 'now')))); ?> to <?php echo e((string) date('j M Y', strtotime((string) ($leaveRequest['end_date'] ?? 'now')))); ?></div>
                                <div class="person-meta"><?php echo nl2br(e((string) ($leaveRequest['reason'] ?? ''))); ?></div>
                                <form class="review-form" method="post">
                                    <input type="hidden" name="action" value="review_teacher_leave_request">
                                    <input type="hidden" name="request_id" value="<?php echo e((string) ($leaveRequest['id'] ?? 0)); ?>">
                                    <textarea name="principal_note" placeholder="Optional note for the teacher..."></textarea>
                                    <div class="review-actions">
                                        <button class="action-btn primary" type="submit" name="decision" value="approved">Approve</button>
                                        <button class="action-btn danger" type="submit" name="decision" value="declined">Decline</button>
                                    </div>
                                </form>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="glass-panel">
                <h2 class="section-title">Recent Leave Decisions</h2>
                <?php if (!$recentLeaveRequests): ?>
                    <div class="muted-empty">No leave requests have been submitted yet.</div>
                <?php else: ?>
                    <div class="card-grid">
                        <?php foreach ($recentLeaveRequests as $leaveRequest): ?>
                            <?php
                                $leaveTeacherName = display_name_from_user($leaveRequest, 'Teacher');
                                $leaveStatus = (string) ($leaveRequest['status'] ?? 'pending');
                                $reviewerName = trim((string) (($leaveRequest['reviewer_full_name'] ?? '') !== '' ? $leaveRequest['reviewer_full_name'] : (($leaveRequest['reviewer_first_name'] ?? '') . ' ' . ($leaveRequest['reviewer_surname'] ?? ''))));
                            ?>
                            <article class="person-card">
                                <div class="person-head">
                                    <div class="person-meta-wrap">
                                        <?php echo render_avatar_html($leaveRequest, 'person-avatar', 'Teacher'); ?>
                                        <div>
                                            <div class="person-name"><?php echo e($leaveTeacherName); ?></div>
                                            <div class="person-meta"><?php echo e(ucfirst((string) ($leaveRequest['leave_type'] ?? 'leave'))); ?> request</div>
                                        </div>
                                    </div>
                                    <span class="badge <?php echo $leaveStatus === 'approved' ? 'badge-teal' : ($leaveStatus === 'declined' ? 'badge-blue' : 'badge-gold'); ?>"><?php echo e(ucfirst($leaveStatus)); ?></span>
                                </div>
                                <div class="person-meta">Dates: <?php echo e((string) date('j M Y', strtotime((string) ($leaveRequest['start_date'] ?? 'now')))); ?> to <?php echo e((string) date('j M Y', strtotime((string) ($leaveRequest['end_date'] ?? 'now')))); ?></div>
                                <div class="person-meta"><?php echo nl2br(e((string) ($leaveRequest['reason'] ?? ''))); ?></div>
                                <?php if ($reviewerName !== '' || (string) ($leaveRequest['principal_note'] ?? '') !== ''): ?>
                                    <div class="person-meta" style="margin-top:10px;">
                                        <?php if ($reviewerName !== ''): ?>Reviewed by: <?php echo e($reviewerName); ?><?php endif; ?>
                                        <?php if ((string) ($leaveRequest['principal_note'] ?? '') !== ''): ?><div style="margin-top:6px;">Note: <?php echo nl2br(e((string) $leaveRequest['principal_note'])); ?></div><?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="panel <?php echo $initialPrincipalPanel === 'parentsPanel' ? 'active' : ''; ?>" id="parentsPanel">
            <div class="page-header">
                <div>
                    <h1 class="welcome-title">Parents Directory</h1>
                    <p class="welcome-subtitle">View parent registrations, child listings, and direct contact options.</p>
                </div>
            </div>

            <div class="directory-grid">
                <div class="glass-panel">
                    <h2 class="section-title">Registration Snapshot</h2>
                    <div class="stats-grid" style="margin-bottom:0;">
                        <article class="stat-card gold"><span class="stat-icon">👨‍👩‍👧</span><span class="stat-label">Registered Parents</span><span class="stat-value" data-count="<?php echo e((string) $parentCount); ?>">0</span></article>
                        <article class="stat-card blue"><span class="stat-icon">🎓</span><span class="stat-label">Children Listed</span><span class="stat-value" data-count="<?php echo e((string) $studentCount); ?>">0</span></article>
                        <article class="stat-card teal"><span class="stat-icon">⏳</span><span class="stat-label">Pending Parents</span><span class="stat-value" data-count="<?php echo e((string) $pendingParentCount); ?>">0</span></article>
                    </div>
                </div>

                <div class="glass-panel">
                    <h2 class="section-title">Announcement Archive</h2>
                    <div style="overflow-x:auto;">
                        <table class="dark-table">
                            <thead>
                                <tr><th>#</th><th>Title</th><th>Audience</th></tr>
                            </thead>
                            <tbody>
                                <?php if (!$announcementHistory): ?>
                                    <tr><td colspan="3">No announcement history yet.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($announcementHistory as $index => $announcement): ?>
                                    <tr>
                                        <td><?php echo e((string) ($index + 1)); ?></td>
                                        <td><?php echo e((string) $announcement['title']); ?></td>
                                        <td><span class="badge badge-gold"><?php echo e(format_audience_label((string) $announcement['audience'])); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="glass-panel">
                <h2 class="section-title">Parent Accounts</h2>
                <?php if ($pendingParentCount > 0): ?>
                    <div class="flash-banner" style="margin-top:0; margin-bottom:18px;">There <?php echo $pendingParentCount === 1 ? 'is' : 'are'; ?> <?php echo e((string) $pendingParentCount); ?> pending parent registration<?php echo $pendingParentCount === 1 ? '' : 's'; ?> waiting for approval.</div>
                <?php endif; ?>
                <?php if (!$parentsDirectory): ?>
                    <div class="muted-empty">No parent accounts have been registered yet.</div>
                <?php else: ?>
                    <div class="card-grid">
                        <?php foreach ($parentsDirectory as $parentRecord): ?>
                            <?php
                                $parentName = format_parent_name($parentRecord);
                                $childName = trim((string) ($parentRecord['child_name'] ?? ''));
                                $childCountValue = (int) ($parentRecord['child_count'] ?? 0);
                                $childDetails = trim((string) ($parentRecord['child_details'] ?? ''));
                                $childLines = preg_split('/\r\n|\r|\n/', $childDetails) ?: [];
                                $childLines = array_values(array_filter(array_map(static fn(string $line): string => trim($line), $childLines), static fn(string $line): bool => $line !== ''));
                                $parentEmail = (string) ($parentRecord['email'] ?? '');
                                $childSummary = $childDetails !== '' ? $childDetails : ($childName !== '' ? $childName : 'Not added yet');
                                $approvalStatus = (string) ($parentRecord['approval_status'] ?? 'approved');
                            ?>
                            <article class="person-card">
                                <div class="person-head">
                                    <div class="person-meta-wrap">
                                        <?php echo render_avatar_html($parentRecord, 'person-avatar', 'Parent'); ?>
                                        <div>
                                            <div class="person-name"><?php echo e($parentName); ?></div>
                                            <div class="person-meta"><?php echo e($parentEmail !== '' ? $parentEmail : 'No email address'); ?></div>
                                        </div>
                                    </div>
                                    <span class="badge <?php echo $approvalStatus === 'pending' ? 'badge-gold' : 'badge-blue'; ?>"><?php echo e($approvalStatus === 'pending' ? 'Pending Approval' : 'Parent'); ?></span>
                                </div>
                                <div class="person-meta">Children in school: <?php echo e((string) max($childCountValue, $childName !== '' ? 1 : 0)); ?></div>
                                <div class="person-meta">First child: <?php echo e($childName !== '' ? $childName : 'Not added yet'); ?></div>
                                <?php if ($childLines): ?>
                                    <div class="person-meta" style="margin-top:8px; display:grid; gap:4px;">
                                        <?php foreach ($childLines as $childLine): ?>
                                            <div><?php echo e($childLine); ?></div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="person-meta">Joined: <?php echo e((string) date('M j, Y', strtotime((string) ($parentRecord['created_at'] ?? 'now')))); ?></div>
                                <?php if ($approvalStatus === 'pending'): ?>
                                    <div class="person-actions">
                                        <form method="post">
                                            <input type="hidden" name="action" value="approve_parent_account">
                                            <input type="hidden" name="parent_id" value="<?php echo e((string) ($parentRecord['id'] ?? 0)); ?>">
                                            <button class="action-btn primary" type="submit">Approve</button>
                                        </form>
                                        <form method="post" onsubmit="return window.confirm('Reject <?php echo e(addslashes($parentName)); ?> and remove this signup?');">
                                            <input type="hidden" name="action" value="delete_directory_user">
                                            <input type="hidden" name="user_id" value="<?php echo e((string) ($parentRecord['id'] ?? 0)); ?>">
                                            <input type="hidden" name="user_role" value="parent">
                                            <button class="action-btn danger" type="submit">Reject</button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <div class="person-actions">
                                        <a class="action-btn primary" href="chat.php?contact_role=Parent&amp;contact_name=<?php echo rawurlencode($parentName); ?>&amp;contact_email=<?php echo rawurlencode($parentEmail); ?>&amp;contact_meta=<?php echo rawurlencode('Children: ' . $childSummary); ?>">Message</a>
                                        <a class="action-btn" href="mailto:<?php echo e($parentEmail); ?>">Email</a>
                                        <form method="post" onsubmit="return window.confirm('Delete <?php echo e(addslashes($parentName)); ?> permanently?');">
                                            <input type="hidden" name="action" value="delete_directory_user">
                                            <input type="hidden" name="user_id" value="<?php echo e((string) ($parentRecord['id'] ?? 0)); ?>">
                                            <input type="hidden" name="user_role" value="parent">
                                            <button class="action-btn danger" type="submit">Delete</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>
    </div>

    <div class="toast" id="toast"></div>

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
        function countUp(element, target, duration = 1500) {
            let start = 0;
            const step = target / (duration / 16);
            const timer = window.setInterval(() => {
                start += step;
                if (start >= target) {
                    element.textContent = String(target);
                    window.clearInterval(timer);
                    return;
                }
                element.textContent = String(Math.floor(start));
            }, 16);
        }
        document.querySelectorAll('.stat-value[data-count]').forEach((element) => {
            const observer = new IntersectionObserver((entries) => {
                if (entries[0].isIntersecting) {
                    countUp(element, parseInt(element.dataset.count || '0', 10));
                    observer.disconnect();
                }
            });
            observer.observe(element);
        });
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const hamburger = document.getElementById('hamburger');
        const navButtons = document.querySelectorAll('[data-panel-target]');
        const panels = document.querySelectorAll('.panel');
        const todayDate = document.getElementById('todayDate');
        const teacherSearch = document.getElementById('teacherSearch');
        const toast = document.getElementById('toast');
        function showToast(message) {
            toast.textContent = message;
            toast.classList.add('show');
            window.clearTimeout(showToast.timer);
            showToast.timer = window.setTimeout(() => toast.classList.remove('show'), 2800);
        }
        function closeSidebar() {
            sidebar.classList.remove('open');
            sidebarOverlay.classList.remove('show');
        }
        function switchPanel(panelId) {
            panels.forEach((panel) => panel.classList.toggle('active', panel.id === panelId));
            navButtons.forEach((button) => button.classList.toggle('active', button.getAttribute('data-panel-target') === panelId));
            if (window.innerWidth <= 768) {
                closeSidebar();
            }
        }
        navButtons.forEach((button) => {
            button.addEventListener('click', () => switchPanel(button.getAttribute('data-panel-target')));
        });
        document.querySelectorAll('.action-link[data-panel-target]').forEach((link) => {
            link.addEventListener('click', (event) => {
                event.preventDefault();
                switchPanel(link.getAttribute('data-panel-target'));
            });
        });
        hamburger.addEventListener('click', () => {
            const willOpen = !sidebar.classList.contains('open');
            sidebar.classList.toggle('open', willOpen);
            sidebarOverlay.classList.toggle('show', willOpen);
        });
        sidebarOverlay.addEventListener('click', closeSidebar);
        if (todayDate) {
            todayDate.textContent = new Date().toLocaleDateString('en-US', { weekday:'long', year:'numeric', month:'long', day:'numeric' });
        }
        if (teacherSearch) {
            teacherSearch.addEventListener('input', function () {
                const keyword = this.value.trim().toLowerCase();
                document.querySelectorAll('#teacherGrid .person-card').forEach((card) => {
                    const haystack = card.getAttribute('data-search') || '';
                    card.classList.toggle('hidden', keyword !== '' && !haystack.includes(keyword));
                });
            });
        }
        document.querySelectorAll('[data-toast]').forEach((button) => {
            button.addEventListener('click', () => showToast(button.getAttribute('data-toast') || 'Profile details ready.'));
        });
        switchPanel(<?php echo json_encode($initialPrincipalPanel, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>);
    </script>
</body>
</html>
