<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';

$teacher = require_role('teacher');
$flash = get_flash();
$displayName = display_name_from_user($teacher, 'Teacher');
$teacherProfilePictureUrl = user_profile_picture_url($teacher);
$teachingClass = trim((string) ($teacher['teaching_class'] ?? ''));
$teacherId = (int) ($teacher['id'] ?? 0);
$initialPanel = in_array((string) ($_GET['panel'] ?? 'dashboard'), ['dashboard', 'chat', 'announcement', 'leave'], true)
    ? (string) ($_GET['panel'] ?? 'dashboard')
    : 'dashboard';
$initialContactId = (string) ($_GET['contact'] ?? 'principal-main');
$announcements = fetch_announcements_for_role('teacher');

if (is_post() && (string) ($_POST['action'] ?? '') === 'toggle_announcement_reaction') {
    $announcementId = (int) ($_POST['announcement_id'] ?? 0);
    $reaction = (string) ($_POST['reaction'] ?? '');
    $reactionError = null;

    if (save_announcement_reaction($announcementId, (int) ($teacher['id'] ?? 0), 'teacher', $reaction, $reactionError)) {
        set_flash('success', 'Announcement reaction updated.');
    } else {
        set_flash('error', $reactionError ?: 'Unable to update the reaction right now.');
    }

    redirect('teacher-dashboard.php?panel=announcement');
}

if (is_post() && (string) ($_POST['action'] ?? '') === 'teacher_check_in') {
    $attendanceError = null;

    if (record_teacher_check_in($teacherId, $attendanceError)) {
        set_flash('success', 'Check-in saved successfully and recorded for the principal.');
    } else {
        set_flash('error', $attendanceError ?: 'Unable to save your check-in right now.');
    }

    redirect('teacher-dashboard.php');
}

if (is_post() && (string) ($_POST['action'] ?? '') === 'teacher_check_out') {
    $attendanceError = null;

    if (record_teacher_check_out($teacherId, $attendanceError)) {
        set_flash('success', 'Check-out saved successfully and recorded for the principal.');
    } else {
        set_flash('error', $attendanceError ?: 'Unable to save your check-out right now.');
    }

    redirect('teacher-dashboard.php');
}

if (is_post() && (string) ($_POST['action'] ?? '') === 'submit_teacher_leave_request') {
    $leaveError = null;

    if (create_teacher_leave_request($teacherId, [
        'leave_type' => (string) ($_POST['leave_type'] ?? ''),
        'start_date' => (string) ($_POST['start_date'] ?? ''),
        'end_date' => (string) ($_POST['end_date'] ?? ''),
        'reason' => (string) ($_POST['reason'] ?? ''),
    ], $leaveError)) {
        set_flash('success', 'Leave request sent to the principal successfully.');
    } else {
        set_flash('error', $leaveError ?: 'Unable to send the leave request right now.');
    }

    redirect('teacher-dashboard.php?panel=leave');
}

$principal = fetch_first_user_by_role('principal');
$principalId = (int) ($principal['id'] ?? 0);
$principalName = $principal ? display_name_from_user($principal, 'The Principal') : 'The Principal';
$principalEmail = (string) ($principal['email'] ?? 'principal@samacetech.edu.ng');
$principalAvatar = $principal ? avatar_payload_from_user($principal, 'The Principal') : ['avatar' => 'P', 'avatar_image_url' => null];
$principalThreadId = null;
$principalMessages = [];
$chatError = null;
$principalUnreadCount = 0;

if ($principalId > 0) {
    $principalThreadId = find_or_create_support_thread($principalId, $teacherId, 'teacher', 'Teacher Support Chat');
    if ($principalThreadId !== null) {
        $principalMessages = fetch_support_messages($principalThreadId);
        foreach ($principalMessages as $message) {
            if (((string) ($message['sender_role'] ?? '')) !== 'teacher' && (int) ($message['is_read'] ?? 0) === 0) {
                $principalUnreadCount++;
            }
        }
    }
}

$parentRows = [];
$parentDirectoryIds = [];
$parentUnreadById = [];
if (db_ready() && $db instanceof mysqli) {
    $parentResult = $db->query("SELECT id, full_name, first_name, surname, email, child_name, profile_picture_path FROM users WHERE role = 'parent' ORDER BY full_name ASC, id ASC");
    if ($parentResult) {
        while ($row = $parentResult->fetch_assoc()) {
            $parentRows[] = $row;
        }
        $parentResult->free();
    }
}

$parentThreads = [];
$parentMessagesById = [];
foreach ($parentRows as $parentRow) {
    $parentId = (int) ($parentRow['id'] ?? 0);
    if ($parentId < 1 || $teacherId < 1) {
        continue;
    }

    $parentDirectoryIds[] = $parentId;

    $thread = fetch_direct_chat_thread($teacherId, 'teacher', $parentId, 'parent');
    if (!$thread) {
        continue;
    }

    $threadId = (int) ($thread['id'] ?? 0);
    if ($threadId < 1) {
        continue;
    }

    $parentThreads[$parentId] = $threadId;
    $parentMessagesById[$parentId] = fetch_direct_chat_messages($threadId);
    $parentUnreadById[$parentId] = 0;
    foreach ($parentMessagesById[$parentId] as $message) {
        if (((string) ($message['sender_role'] ?? '')) !== 'teacher' && (int) ($message['is_read'] ?? 0) === 0) {
            $parentUnreadById[$parentId]++;
        }
    }
}

if (is_post() && (string) ($_POST['action'] ?? '') === 'mark_teacher_chat_read') {
    $contactType = (string) ($_POST['contact_type'] ?? '');
    $contactUserId = (int) ($_POST['contact_user_id'] ?? 0);
    $marked = false;

    if ($contactType === 'principal' && $principalThreadId !== null) {
        mark_thread_messages_read($principalThreadId, 'teacher');
        $marked = true;
    } elseif ($contactType === 'parent' && $contactUserId > 0) {
        $parentThreadId = $parentThreads[$contactUserId] ?? null;
        if ($parentThreadId !== null) {
            mark_direct_chat_messages_read($parentThreadId, $teacherId, 'teacher');
            $marked = true;
        }
    }

    header('Content-Type: application/json');
    if ($marked) {
        echo json_encode(['ok' => true]);
    } else {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Unable to mark this chat as read.']);
    }
    exit;
}

if (is_post() && (string) ($_POST['action'] ?? '') === 'send_teacher_chat') {
    $contactType = (string) ($_POST['contact_type'] ?? '');
    $contactUserId = (int) ($_POST['contact_user_id'] ?? 0);
    $message = (string) ($_POST['message'] ?? '');

    if ($contactType === 'principal' && $principalThreadId !== null) {
        if (!send_support_message($principalThreadId, $teacherId, 'teacher', $message, $chatError)) {
            $flash = ['type' => 'error', 'message' => $chatError ?: 'Unable to send the message right now.'];
            $initialPanel = 'chat';
            $initialContactId = 'principal-main';
        } else {
            set_flash('success', 'Your message has been sent.');
            redirect('teacher-dashboard.php?panel=chat&contact=principal-main');
        }
    } elseif ($contactType === 'parent' && in_array($contactUserId, $parentDirectoryIds, true)) {
        $parentThreadId = $parentThreads[$contactUserId] ?? find_or_create_direct_chat_thread($teacherId, 'teacher', $contactUserId, 'parent', 'Teacher Parent Chat');
        if ($parentThreadId === null || !send_direct_chat_message($parentThreadId, $teacherId, 'teacher', $message, $chatError)) {
            $flash = ['type' => 'error', 'message' => $chatError ?: 'Unable to send the message right now.'];
            $initialPanel = 'chat';
            $initialContactId = 'parent-' . $contactUserId;
        } else {
            set_flash('success', 'Your message has been sent.');
            redirect('teacher-dashboard.php?panel=chat&contact=parent-' . $contactUserId);
        }
    } else {
        $flash = ['type' => 'error', 'message' => 'Please choose a valid chat contact.'];
        $initialPanel = 'chat';
    }
}

$principalPreview = 'No messages yet.';
$principalTime = '';
$principalConversation = [];
foreach ($principalMessages as $message) {
    $principalConversation[] = [
        'type' => ((string) ($message['sender_role'] ?? '')) === 'teacher' ? 'sent' : 'received',
        'text' => (string) ($message['message'] ?? ''),
        'time' => date('g:i A', strtotime((string) ($message['created_at'] ?? 'now'))),
    ];
}
if ($principalMessages) {
    $lastPrincipalMessage = end($principalMessages);
    if (is_array($lastPrincipalMessage)) {
        $principalPreview = (string) ($lastPrincipalMessage['message'] ?? $principalPreview);
        $principalTime = date('g:i A', strtotime((string) ($lastPrincipalMessage['created_at'] ?? 'now')));
    }
}

$parentContacts = [];
$parentConversations = [];
$totalUnreadMessages = $principalUnreadCount;
$palette = ['#0F766E', '#2563EB', '#D97706', '#2F855A', '#7C3AED', '#DC2626'];
foreach ($parentRows as $index => $parentRow) {
    $parentId = (int) ($parentRow['id'] ?? 0);
    $name = display_name_from_user($parentRow, 'Parent');
    $childName = trim((string) ($parentRow['child_name'] ?? ''));
    $messages = $parentMessagesById[$parentId] ?? [];
    $preview = 'No messages yet.';
    $time = '';
    $conversation = [];

    foreach ($messages as $message) {
        $conversation[] = [
            'type' => ((string) ($message['sender_role'] ?? '')) === 'teacher' ? 'sent' : 'received',
            'text' => (string) ($message['message'] ?? ''),
            'time' => date('g:i A', strtotime((string) ($message['created_at'] ?? 'now'))),
        ];
    }

    if ($messages) {
        $lastMessage = end($messages);
        if (is_array($lastMessage)) {
            $preview = (string) ($lastMessage['message'] ?? $preview);
            $time = date('g:i A', strtotime((string) ($lastMessage['created_at'] ?? 'now')));
        }
    }

    $contactId = 'parent-' . $parentId;
    $parentAvatar = avatar_payload_from_user($parentRow, 'Parent');
    $parentContacts[] = [
        'id' => $contactId,
        'user_id' => $parentId,
        'name' => $name,
        'email' => (string) ($parentRow['email'] ?? ''),
        'role' => 'Parent',
        'meta' => $childName !== '' ? 'Child: ' . $childName : 'Parent account',
        'avatar' => $parentAvatar['avatar'],
        'avatar_image_url' => $parentAvatar['avatar_image_url'],
        'color' => $palette[$index % count($palette)],
        'preview' => $preview,
        'time' => $time,
        'unread' => (int) ($parentUnreadById[$parentId] ?? 0),
    ];
    $parentConversations[$contactId] = $conversation;
    $totalUnreadMessages += (int) ($parentUnreadById[$parentId] ?? 0);
}

$chatContacts = array_merge([[
    'id' => 'principal-main',
    'user_id' => $principalId,
    'name' => $principalName,
    'email' => $principalEmail,
    'role' => 'Principal',
    'meta' => 'School Administration',
    'avatar' => $principalAvatar['avatar'],
    'avatar_image_url' => $principalAvatar['avatar_image_url'],
    'color' => '#0D4B4B',
    'preview' => $principalPreview,
    'time' => $principalTime,
    'unread' => $principalUnreadCount,
]], $parentContacts);

$announcementIds = array_map(static fn(array $announcement): int => (int) ($announcement['id'] ?? 0), $announcements);
$announcementReactionSummary = fetch_announcement_reaction_summary($announcementIds);
$announcementUserReactions = fetch_user_announcement_reactions((int) ($teacher['id'] ?? 0), 'teacher', $announcementIds);
$announcementCount = count($announcements);
$parentCount = count($parentContacts);
$chatCount = count($chatContacts);
$todayAttendance = fetch_teacher_attendance_for_date($teacherId);
$attendanceHistory = fetch_teacher_attendance_history($teacherId, 7);
$hasCheckedInToday = $todayAttendance !== null && (string) ($todayAttendance['check_in_at'] ?? '') !== '';
$hasCheckedOutToday = $todayAttendance !== null && (string) ($todayAttendance['check_out_at'] ?? '') !== '';
$leaveRequests = fetch_teacher_leave_requests($teacherId, 10);

function audience_label_teacher(string $audience): string
{
    return match ($audience) {
        'teachers' => 'Teachers Only',
        'parents' => 'Parents Only',
        'both' => 'Everyone',
        default => ucfirst($audience),
    };
}

function announcement_reaction_total_teacher(array $counts): int
{
    return (int) array_sum($counts);
}

if ($initialPanel === 'chat') {
    if ($initialContactId === 'principal-main' && $principalThreadId !== null) {
        mark_thread_messages_read($principalThreadId, 'teacher');
        $totalUnreadMessages -= $principalUnreadCount;
        if (isset($chatContacts[0])) {
            $chatContacts[0]['unread'] = 0;
        }
    } elseif (str_starts_with($initialContactId, 'parent-')) {
        $selectedParentId = (int) substr($initialContactId, 7);
        $selectedThreadId = $parentThreads[$selectedParentId] ?? null;
        if ($selectedThreadId !== null) {
            mark_direct_chat_messages_read($selectedThreadId, $teacherId, 'teacher');
            $selectedUnreadCount = (int) ($parentUnreadById[$selectedParentId] ?? 0);
            $totalUnreadMessages -= $selectedUnreadCount;
            foreach ($chatContacts as &$chatContact) {
                if (($chatContact['id'] ?? '') === $initialContactId) {
                    $chatContact['unread'] = 0;
                    break;
                }
            }
            unset($chatContact);
        }
    }
}

$totalUnreadMessages = max(0, $totalUnreadMessages);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="dark">
    <title>Teacher Dashboard | SAMACE TECH LAB</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@400;500;700&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
    <style>
        :root { --role-accent:#4A9EFF; --text-primary:#fff; --text-secondary:#A0A8B8; --text-muted:#5A6070; --sidebar-width:250px; --font-display:'Bebas Neue',cursive; --font-heading:'Playfair Display',serif; --font-body:'DM Sans',sans-serif; }
        * { box-sizing:border-box; margin:0; padding:0; }
        html, body { height:100%; overflow:hidden; }
        body { background:#0A0A0F; font-family:var(--font-body); color:#fff; display:flex; overflow:hidden; position:relative; }
        a { color:inherit; text-decoration:none; }
        button, input { font:inherit; }
        .orb { position:fixed; border-radius:50%; filter:blur(140px); z-index:3; pointer-events:none; animation:orbDrift 20s ease-in-out infinite alternate; }
        .orb-gold { width:700px; height:700px; background:radial-gradient(circle, rgba(245,166,35,0.1), transparent 70%); top:-200px; left:50px; }
        .orb-teal { width:500px; height:500px; background:radial-gradient(circle, rgba(0,212,180,0.07), transparent 70%); bottom:-100px; right:100px; animation-direction:alternate-reverse; animation-duration:26s; }
        .orb-blue { width:420px; height:420px; background:radial-gradient(circle, rgba(74,158,255,0.08), transparent 70%); top:38%; right:-50px; animation-duration:32s; }
        #particles-container { position:fixed; inset:0; z-index:2; pointer-events:none; overflow:hidden; }
        @keyframes orbDrift { from { transform:translate(0,0) scale(1); } to { transform:translate(40px,30px) scale(1.06); } }
        @keyframes floatDot { 0% { transform:translate(0,0) scale(1); opacity:.5; } 33% { transform:translate(10px,-15px) scale(1.2); opacity:1; } 66% { transform:translate(-8px,8px) scale(.8); opacity:.3; } 100% { transform:translate(15px,-20px) scale(1); opacity:.6; } }
        ::-webkit-scrollbar { width:5px; height:5px; }
        ::-webkit-scrollbar-track { background:#0A0A0F; }
        ::-webkit-scrollbar-thumb { background:rgba(74,158,255,0.35); border-radius:3px; }
        ::-webkit-scrollbar-thumb:hover { background:#4A9EFF; }
        ::selection { background:rgba(74,158,255,0.2); color:#fff; }
        .shell { min-height:100vh; display:flex; position:relative; z-index:4; }
        .mobile-toggle { display:none; position:fixed; top:16px; left:16px; z-index:1200; width:48px; height:48px; border:0; border-radius:14px; background:rgba(15,15,26,0.95); color:#4A9EFF; box-shadow:0 18px 36px rgba(0,0,0,0.35); align-items:center; justify-content:center; cursor:pointer; backdrop-filter:blur(14px); }
        .sidebar-overlay { position:fixed; inset:0; background:rgba(0,0,0,0.6); opacity:0; pointer-events:none; transition:opacity .25s ease; z-index:1050; }
        .sidebar { width:var(--sidebar-width); position:fixed; inset:0 auto 0 0; z-index:1100; display:flex; flex-direction:column; justify-content:space-between; background:rgba(13,13,24,0.95); border-right:1px solid rgba(255,255,255,0.06); backdrop-filter:blur(24px); -webkit-backdrop-filter:blur(24px); overflow-y:auto; transition:transform .25s ease; }
        .sidebar-logo { padding:28px 24px 20px; border-bottom:1px solid rgba(255,255,255,0.06); position:relative; }
        .sidebar-logo::after { content:''; position:absolute; bottom:0; left:24px; right:24px; height:1px; background:linear-gradient(90deg, transparent, rgba(74,158,255,0.32), transparent); }
        .sidebar-school-name { font-family:var(--font-display); font-size:17px; letter-spacing:2.5px; color:#4A9EFF; display:block; line-height:1; }
        .sidebar-school-sub { font-size:9px; color:#5A6070; letter-spacing:2px; text-transform:uppercase; display:block; margin-top:4px; }
        .sidebar-role-badge { display:inline-flex; align-items:center; gap:5px; background:rgba(74,158,255,0.1); border:1px solid rgba(74,158,255,0.2); color:#4A9EFF; font-size:10px; font-weight:600; letter-spacing:1px; text-transform:uppercase; padding:4px 10px; border-radius:50px; margin-top:10px; }
        .nav-section-label { font-size:9px; font-weight:700; letter-spacing:2.5px; text-transform:uppercase; color:#5A6070; padding:20px 24px 8px; }
        .nav-list { display:grid; gap:0; padding:8px 0 18px; }
        .nav-item { display:flex; align-items:center; gap:12px; width:calc(100% - 12px); margin-right:12px; padding:13px 24px; border:0; background:transparent; color:#A0A8B8; text-align:left; font-size:14px; font-weight:500; border-left:3px solid transparent; border-radius:0 12px 12px 0; cursor:pointer; transition:all .3s cubic-bezier(0.23,1,0.32,1); position:relative; }
        .nav-item:hover, .nav-item.active { color:#fff; background:rgba(255,255,255,0.05); border-left-color:rgba(74,158,255,0.4); transform:translateX(3px); }
        .nav-item.active { color:#4A9EFF; background:rgba(74,158,255,0.08); border-left-color:#4A9EFF; font-weight:600; }
        .nav-item.active::before { content:''; position:absolute; right:16px; top:50%; transform:translateY(-50%); width:6px; height:6px; border-radius:50%; background:#4A9EFF; box-shadow:0 0 8px rgba(74,158,255,0.8); }
        .nav-item.logout:hover { background:rgba(255,71,87,0.08); border-left-color:#FF4757; color:#FF4757; }
        .sidebar-attendance-card { margin:10px 16px 18px; padding:16px; border-radius:18px; background:linear-gradient(180deg, rgba(74,158,255,0.12), rgba(255,255,255,0.03)); border:1px solid rgba(74,158,255,0.14); box-shadow:0 18px 36px rgba(0,0,0,0.22); }
        .sidebar-attendance-card h3 { font-family:var(--font-heading); font-size:1rem; color:#fff; margin-bottom:8px; }
        .sidebar-attendance-copy { color:#A0A8B8; font-size:.84rem; line-height:1.5; margin-bottom:12px; }
        .sidebar-attendance-pill { display:inline-flex; align-items:center; justify-content:center; padding:7px 12px; border-radius:999px; background:rgba(74,158,255,0.16); border:1px solid rgba(74,158,255,0.2); color:#dff0ff; font-size:.74rem; font-weight:800; letter-spacing:.02em; }
        .sidebar-attendance-meta { display:grid; gap:8px; margin-top:12px; }
        .sidebar-attendance-row { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:10px 12px; border-radius:12px; background:rgba(255,255,255,0.04); color:#d6deea; font-size:.84rem; }
        .sidebar-attendance-row strong { color:#fff; font-size:.82rem; }
        .sidebar-attendance-actions { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:14px; }
        .sidebar-attendance-actions form { min-width:0; }
        .sidebar-attendance-btn { width:100%; min-height:42px; border-radius:12px; border:1px solid rgba(255,255,255,0.08); font-size:.84rem; font-weight:800; cursor:pointer; }
        .sidebar-attendance-btn.primary { background:linear-gradient(135deg, #4A9EFF, #2D7DD2); color:#fff; }
        .sidebar-attendance-btn.secondary { background:linear-gradient(135deg, rgba(0,212,180,0.18), rgba(74,158,255,0.12)); color:#dffbff; }
        .sidebar-attendance-btn:disabled { opacity:.45; cursor:not-allowed; }
        .sidebar-footer { display:flex; align-items:center; gap:12px; padding:16px 24px 24px; border-top:1px solid rgba(255,255,255,0.06); }
        .avatar { width:42px; height:42px; border-radius:50%; overflow:hidden; display:grid; place-items:center; background:linear-gradient(135deg, #4A9EFF, rgba(74,158,255,0.4)); color:#0A0A0F; font-family:var(--font-display); font-size:18px; box-shadow:0 0 18px rgba(74,158,255,0.3); }
        .avatar img, .contact-avatar img { width:100%; height:100%; object-fit:cover; display:block; border-radius:inherit; }
        .main { position:fixed; top:0; left:var(--sidebar-width); right:0; bottom:0; min-width:0; overflow-y:auto; overflow-x:hidden; z-index:4; }
        .main-inner { padding:32px 20px 40px; position:relative; z-index:4; min-height:100%; }
        .panel { display:none; }
        .panel.active { display:block !important; opacity:1 !important; visibility:visible !important; animation:panelFade .35s ease both; }
        @keyframes panelFade { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }
        .page-head { display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap; margin-bottom:32px; padding-bottom:24px; border-bottom:1px solid rgba(255,255,255,0.06); position:relative; }
        .page-head::after { content:''; position:absolute; left:0; bottom:-1px; width:80px; height:2px; background:linear-gradient(90deg, #4A9EFF, transparent); border-radius:2px; }
        .page-head h1, .section-card h2, .section-card h3, .announcement-card h3, .chat-header h3 { font-family:var(--font-heading); color:#fff; }
        .page-head h1 { font-size:clamp(2rem,3.6vw,2.5rem); margin-bottom:8px; }
        .page-head p, .muted, .meta, .contact-preview, .message-time { color:var(--text-secondary); }
        .page-head-main { display:flex; align-items:center; gap:18px; flex:1; min-width:280px; }
        .welcome-profile-circle { width:96px; height:96px; border-radius:50%; overflow:hidden; flex-shrink:0; display:grid; place-items:center; background:linear-gradient(135deg, rgba(74,158,255,0.28), rgba(0,212,180,0.22)); border:1px solid rgba(74,158,255,0.24); box-shadow:0 18px 44px rgba(8,16,30,0.34); color:#e8f5ff; font-family:var(--font-display); font-size:34px; }
        .welcome-profile-circle img { width:100%; height:100%; object-fit:cover; object-position:center; display:block; border-radius:inherit; }
        .page-head-copy { min-width:0; }
        .today-pill { padding:10px 18px; border-radius:12px; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); font-weight:500; color:#A0A8B8; backdrop-filter:blur(10px); white-space:nowrap; }
        .flash { border-radius:16px; margin-bottom:18px; padding:14px 18px; border:1px solid rgba(255,255,255,0.08); background:rgba(255,255,255,0.04); color:#fff; }
        .flash.error { background:rgba(255,71,87,0.12); color:#ffb0b8; }
        .flash.success { background:rgba(0,212,180,0.12); color:#aaf7ea; }
        .stats-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:20px; margin-bottom:28px; }
        .stat-card, .section-card, .chat-shell, .announcement-card { background:linear-gradient(180deg, rgba(34,36,49,0.9), rgba(18,20,31,0.92)); border:1px solid rgba(255,255,255,0.08); border-radius:22px; backdrop-filter:blur(10px); box-shadow:0 24px 60px rgba(0,0,0,0.34); position:relative; z-index:4; }
        .stat-card, .section-card { transition:transform .22s ease, box-shadow .22s ease, border-color .22s ease; }
        .stat-card:hover, .section-card:hover { transform:translateY(-4px); border-color:rgba(74,158,255,0.2); box-shadow:0 30px 70px rgba(0,0,0,0.42); }
        .stat-card { padding:24px; position:relative; overflow:hidden; isolation:isolate; }
        .stat-card::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; }
        .stat-card::after { content:''; position:absolute; inset:auto -12% 38% auto; width:120px; height:120px; border-radius:50%; background:radial-gradient(circle, rgba(255,255,255,0.12), transparent 68%); opacity:.5; pointer-events:none; z-index:-1; }
        .stat-card:nth-child(1)::before { background:linear-gradient(90deg, #4A9EFF, rgba(74,158,255,0.3)); }
        .stat-card:nth-child(2)::before { background:linear-gradient(90deg, #00D4B4, rgba(0,212,180,0.3)); }
        .stat-card:nth-child(3)::before { background:linear-gradient(90deg, #F5A623, rgba(245,166,35,0.3)); }
        .stat-label { display:block; color:#5A6070; font-size:11px; font-weight:700; letter-spacing:1.5px; text-transform:uppercase; margin-bottom:12px; }
        .stat-value { font-family:var(--font-display); font-size:50px; line-height:1; color:#fff; }
        .grid-two { display:grid; grid-template-columns:1.2fr 1fr; gap:24px; }
        .section-card { padding:26px; overflow:hidden; }
        .section-card::before { content:''; position:absolute; inset:0; background:linear-gradient(135deg, rgba(74,158,255,0.08), transparent 38%, rgba(0,212,180,0.05) 100%); opacity:.9; pointer-events:none; }
        .section-card > * { position:relative; z-index:1; }
        .section-card h2, .section-card h3 { margin-bottom:14px; }
        .preview-list, .announcement-list { display:grid; gap:16px; }
        .preview-item, .announcement-card { padding:18px; border-radius:18px; background:linear-gradient(180deg, rgba(255,255,255,0.05), rgba(255,255,255,0.025)); border:1px solid rgba(255,255,255,0.07); box-shadow:inset 0 1px 0 rgba(255,255,255,0.04); }
        .preview-top, .announcement-top, .contact-head { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; margin-bottom:10px; }
        .badge { display:inline-flex; align-items:center; justify-content:center; padding:6px 10px; border-radius:999px; background:rgba(74,158,255,0.12); color:#4A9EFF; border:1px solid rgba(74,158,255,0.18); font-size:.78rem; font-weight:800; white-space:nowrap; }
        .info-card { padding:18px; border-radius:18px; background:linear-gradient(135deg, rgba(74,158,255,0.13), rgba(0,212,180,0.1)); border:1px solid rgba(255,255,255,0.08); margin-top:12px; box-shadow:inset 0 1px 0 rgba(255,255,255,0.06); }
        .info-card strong { display:block; font-size:1.12rem; color:#fff; margin-top:6px; }
        .profile-card { display:grid; gap:16px; }
        .profile-card-top { display:flex; align-items:center; gap:14px; }
        .profile-card-avatar { width:72px; height:72px; border-radius:50%; overflow:hidden; flex-shrink:0; display:grid; place-items:center; background:linear-gradient(135deg, #4A9EFF, rgba(74,158,255,0.38)); color:#0A0A0F; font-family:var(--font-display); font-size:28px; box-shadow:0 0 24px rgba(74,158,255,0.24); }
        .profile-card-avatar img { width:100%; height:100%; object-fit:cover; object-position:center; display:block; border-radius:inherit; }
        .profile-photo-action { display:inline-flex; align-items:center; justify-content:center; gap:8px; min-height:44px; padding:0 16px; border-radius:14px; background:linear-gradient(135deg, rgba(74,158,255,0.22), rgba(0,212,180,0.18)); border:1px solid rgba(74,158,255,0.26); color:#dff1ff; font-weight:800; text-decoration:none; box-shadow:0 12px 30px rgba(8,16,30,0.25); }
        .profile-photo-action:hover { background:linear-gradient(135deg, rgba(74,158,255,0.3), rgba(0,212,180,0.24)); }
        .attendance-panel { margin-top:24px; }
        .attendance-shell { display:grid; grid-template-columns:minmax(280px,360px) minmax(0,1fr); gap:20px; }
        .attendance-status-card, .attendance-history-card { padding:22px; border-radius:22px; background:rgba(30,30,40,0.82); border:1px solid rgba(255,255,255,0.08); box-shadow:0 24px 60px rgba(0,0,0,0.34); }
        .attendance-state-pill { display:inline-flex; align-items:center; gap:8px; padding:8px 12px; border-radius:999px; background:rgba(74,158,255,0.12); border:1px solid rgba(74,158,255,0.18); color:#d9ecff; font-size:.82rem; font-weight:800; }
        .attendance-meta { display:grid; gap:10px; margin-top:16px; }
        .attendance-meta-row { display:flex; justify-content:space-between; gap:12px; padding:12px 14px; border-radius:14px; background:rgba(255,255,255,0.04); color:#d6deea; }
        .attendance-actions { display:flex; gap:12px; flex-wrap:wrap; margin-top:18px; }
        .attendance-btn { min-height:46px; padding:0 18px; border-radius:14px; border:1px solid rgba(255,255,255,0.08); font-weight:800; cursor:pointer; }
        .attendance-btn.primary { background:linear-gradient(135deg, #4A9EFF, #2D7DD2); color:#fff; }
        .attendance-btn.secondary { background:linear-gradient(135deg, rgba(0,212,180,0.18), rgba(74,158,255,0.12)); color:#dffbff; }
        .attendance-btn:disabled { opacity:.45; cursor:not-allowed; }
        .attendance-history-list { display:grid; gap:12px; margin-top:14px; }
        .attendance-history-item { display:flex; justify-content:space-between; gap:14px; padding:14px 16px; border-radius:16px; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.06); }
        .attendance-history-item strong { color:#fff; display:block; margin-bottom:4px; }
        .attendance-history-times { text-align:right; color:#cfd7e5; font-size:.88rem; }
        .leave-shell { display:grid; grid-template-columns:minmax(320px,380px) minmax(0,1fr); gap:22px; }
        .leave-form-card, .leave-history-card { padding:24px; border-radius:22px; background:rgba(30,30,40,0.82); border:1px solid rgba(255,255,255,0.08); box-shadow:0 24px 60px rgba(0,0,0,0.34); }
        .leave-form { display:grid; gap:14px; margin-top:16px; }
        .leave-form textarea, .leave-form select, .leave-form input { width:100%; border:1px solid rgba(255,255,255,0.1); border-radius:14px; padding:13px 14px; background:rgba(255,255,255,0.06); color:#fff; outline:none; }
        .leave-form textarea { min-height:120px; resize:vertical; }
        .leave-history-list { display:grid; gap:14px; margin-top:16px; }
        .leave-history-item { padding:16px; border-radius:16px; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.06); }
        .leave-history-top { display:flex; justify-content:space-between; gap:12px; align-items:flex-start; margin-bottom:10px; }
        .leave-history-item strong { color:#fff; }
        .leave-note { margin-top:10px; padding-top:10px; border-top:1px solid rgba(255,255,255,0.08); color:#cfd7e5; }
        #chatPanel { width:100%; }
        .chat-shell { width:100%; display:grid; grid-template-columns:minmax(290px,340px) minmax(0,1fr); height:calc(100vh - 250px); min-height:560px; background:rgba(30,30,40,0.82); border:1px solid rgba(255,255,255,0.08); border-radius:22px; overflow:hidden; min-width:0; box-shadow:0 24px 60px rgba(0,0,0,0.34); backdrop-filter:blur(10px); }
        .contacts-pane { border-right:1px solid rgba(255,255,255,0.08); background:rgba(255,255,255,0.03); display:flex; flex-direction:column; min-height:0; overflow:hidden; }
        .contacts-search-wrap { padding:18px; border-bottom:1px solid rgba(255,255,255,0.06); background:rgba(255,255,255,0.02); }
        .search-field, .chat-compose-input { width:100%; border:1px solid rgba(255,255,255,0.1); border-radius:14px; padding:13px 14px; background:rgba(255,255,255,0.06); color:#fff; outline:none; transition:border-color .22s ease, box-shadow .22s ease, background .22s ease; }
        .search-field::placeholder, .chat-compose-input::placeholder { color:#5A6070; }
        .search-field:focus, .chat-compose-input:focus { border-color:#4A9EFF; box-shadow:0 0 0 3px rgba(74,158,255,0.12); background:rgba(255,255,255,0.08); }
        .contact-list { display:block; margin-top:0; overflow-y:auto; flex:1; min-height:0; }
        .contact-row { display:flex; align-items:center; gap:12px; width:100%; border:0; border-left:3px solid transparent; border-bottom:1px solid rgba(255,255,255,0.04); padding:14px 16px; background:transparent; cursor:pointer; text-align:left; transition:all .22s ease; color:#fff; border-radius:0; }
        .contact-row:hover { background:rgba(255,255,255,0.05); border-left-color:rgba(74,158,255,0.3); }
        .contact-row.active { background:rgba(74,158,255,0.10); border-left-color:#4A9EFF; }
        .contact-avatar { width:44px; height:44px; border-radius:50%; overflow:hidden; display:grid; place-items:center; color:#fff; font-weight:800; flex-shrink:0; }
        .contact-body { min-width:0; flex:1; }
        .contact-title { font-weight:800; color:#fff; }
        .contact-meta { font-size:.84rem; color:#A0A8B8; margin-top:2px; }
        .contact-preview { font-size:.82rem; margin-top:7px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; color:#5A6070; }
        .contact-side { display:flex; flex-direction:column; align-items:flex-end; gap:6px; flex-shrink:0; }
        .contact-time { font-size:10px; color:#5A6070; }
        .contact-unread { background:#FF4757; color:#fff; font-size:10px; font-weight:700; min-width:18px; height:18px; border-radius:50px; padding:0 5px; display:inline-flex; align-items:center; justify-content:center; }
        .chat-pane { display:flex; flex-direction:column; background:rgba(255,255,255,0.02); position:relative; min-width:0; min-height:0; }
        .chat-pane::before { content:''; position:absolute; inset:0; background-image:none; pointer-events:none; }
        .chat-header { padding:18px 20px; border-bottom:1px solid rgba(255,255,255,0.08); display:flex; justify-content:space-between; gap:14px; align-items:center; background:rgba(255,255,255,0.03); position:relative; z-index:1; }
        .chat-user { display:flex; align-items:center; gap:12px; }
        .chat-header-status { font-size:11px; letter-spacing:1.2px; text-transform:uppercase; color:#5A6070; }
        .messages-area { flex:1; padding:22px; overflow-y:auto; background:transparent; display:flex; flex-direction:column; gap:14px; position:relative; z-index:1; min-height:0; overscroll-behavior:contain; }
        .empty-chat { flex:1; display:grid; place-items:center; text-align:center; color:#A0A8B8; }
        .message-wrap { max-width:76%; display:flex; flex-direction:column; }
        .message-wrap.sent { align-self:flex-end; }
        .message-wrap.received { align-self:flex-start; }
        .message-bubble { padding:12px 16px; border-radius:18px; line-height:1.55; box-shadow:0 10px 20px rgba(0,0,0,0.2); animation:bubbleIn .24s ease both; }
        .message-wrap.sent .message-bubble { background:linear-gradient(135deg, #4A9EFF, #2D7DD2); color:#fff; border-bottom-right-radius:6px; }
        .message-wrap.received .message-bubble { background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.08); color:#e6edf8; border-bottom-left-radius:6px; }
        .message-time { margin-top:5px; font-size:.76rem; }
        .chat-input { display:flex; gap:12px; padding:18px 20px; border-top:1px solid rgba(255,255,255,0.08); background:rgba(255,255,255,0.03); position:relative; z-index:1; flex-shrink:0; }
        .send-btn { min-width:56px; min-height:50px; border:0; border-radius:16px; background:linear-gradient(135deg, #4A9EFF, #2D7DD2); color:#fff; font-weight:800; cursor:pointer; transition:transform .22s ease, box-shadow .22s ease; }
        .send-btn:hover { transform:scale(1.06); box-shadow:0 0 22px rgba(74,158,255,0.38); }
        .send-btn:active { transform:scale(.97); }
        .reaction-bar { display:flex; flex-wrap:wrap; gap:10px; align-items:center; margin-top:14px; }
        .reaction-btn { border:1px solid rgba(255,255,255,0.08); background:rgba(255,255,255,0.04); border-radius:999px; padding:10px 14px; font-weight:700; color:#fff; cursor:pointer; }
        .reaction-btn.active { border-color:rgba(74,158,255,0.45); background:rgba(74,158,255,0.12); color:#4A9EFF; }
        .announcement-attachment { display:inline-flex; align-items:center; gap:8px; margin-top:14px; padding:10px 14px; border-radius:14px; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.08); color:#dff1ff; font-weight:700; }
        .announcement-meta-line { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
        @keyframes bubbleIn { from { opacity:0; transform:translateY(8px) scale(.97); } to { opacity:1; transform:translateY(0) scale(1); } }
        @media (max-width:1024px) { .stats-grid, .grid-two, .attendance-shell, .leave-shell { grid-template-columns:1fr; } }
        @media (max-width:768px) {
            body.sidebar-open { overflow:hidden; }
            body .mobile-toggle { display:inline-flex !important; position:fixed !important; top:14px !important; left:14px !important; z-index:1201 !important; }
            body .sidebar { transform:translateX(-100%) !important; opacity:0 !important; visibility:hidden !important; pointer-events:none !important; box-shadow: 0 0 40px rgba(0,0,0,0.45) !important; position:fixed !important; left:0 !important; top:0 !important; height:100vh !important; width:min(84vw, 320px) !important; max-width:320px !important; min-width:0 !important; }
            body .sidebar.open { transform:translateX(0) !important; opacity:1 !important; visibility:visible !important; pointer-events:auto !important; }
            body .sidebar-overlay.show { opacity:1 !important; pointer-events:auto !important; }
            body .sidebar-overlay { display:block !important; }
            body .main { left:0 !important; }
            body .main-inner { padding:84px 16px 24px !important; }
            body .page-head { gap:14px !important; margin-bottom:24px !important; }
            body .page-head-main { flex-direction:column !important; align-items:flex-start !important; min-width:0 !important; }
            body .page-head h1 { font-size:1.8rem !important; }
            body .welcome-profile-circle { width:82px !important; height:82px !important; font-size:30px !important; }
            body .today-pill { width:100% !important; white-space:normal !important; }
            body .stats-grid { grid-template-columns:1fr !important; gap:14px !important; }
            body .section-card, body .stat-card, body .announcement-card, body .attendance-status-card, body .attendance-history-card, body .leave-form-card, body .leave-history-card { padding:18px !important; border-radius:18px !important; }
            body .sidebar-attendance-actions { grid-template-columns:1fr !important; }
            body .chat-shell { grid-template-columns:1fr !important; grid-template-rows:minmax(180px, 34dvh) minmax(360px, 1fr) !important; height:min(calc(100dvh - 170px), 820px) !important; min-height:620px !important; }
            body .contacts-pane { border-right:0 !important; border-bottom:1px solid rgba(255,255,255,0.08) !important; }
            body .messages-area { padding:16px !important; }
            body .chat-input { padding:14px 16px !important; }
            body .message-wrap { max-width:92% !important; }
            body .floating-btn, body .theme-toggle, body .theme-toggle-wrap, body .fixed-btn, body .fab, body .right-btn {
                display: none !important;
            }
        }
        @media (max-width:480px) {
            body .main-inner { padding:78px 14px 18px !important; }
            body .sidebar { width:90vw !important; }
            body .page-head h1 { font-size:1.6rem !important; }
            body .chat-shell { height:min(calc(100dvh - 160px), 760px) !important; min-height:560px !important; }
            body .chat-input { flex-direction:column !important; }
            body .send-btn { width:100% !important; }
            body .attendance-meta-row, body .attendance-history-item, body .leave-history-top { flex-direction:column !important; align-items:flex-start !important; }
            body .attendance-history-times { text-align:left !important; }
            body .message-wrap { max-width:100% !important; }
        }
    </style>
</head>
<body>
    <div class="orb orb-gold"></div>
    <div class="orb orb-teal"></div>
    <div class="orb orb-blue"></div>
    <div id="particles-container"></div>
    <button class="mobile-toggle" id="sidebarToggle" type="button" aria-label="Toggle sidebar">☰</button>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="shell">
        <aside class="sidebar" id="sidebar">
            <div>
                <div class="sidebar-logo">
                    <span class="sidebar-school-name">SAMACE TECH LAB</span>
                    <span class="sidebar-school-sub">Nursery &amp; Primary School</span>
                    <span class="sidebar-role-badge">👩‍🏫 Teacher Portal</span>
                </div>
                <div class="nav-section-label">Navigation</div>
                <nav class="nav-list">
                    <button class="nav-item<?php echo $initialPanel === 'dashboard' ? ' active' : ''; ?>" data-panel-target="dashboardPanel" type="button"><span>🏠</span><span>Dashboard</span></button>
                    <button class="nav-item<?php echo $initialPanel === 'chat' ? ' active' : ''; ?>" data-panel-target="chatPanel" type="button"><span>💬</span><span>Chat</span><?php if ($totalUnreadMessages > 0): ?><span class="badge" id="chatUnreadBadge" style="background:rgba(220,38,38,.16);color:#ffb4b4;"><?php echo e((string) $totalUnreadMessages); ?></span><?php endif; ?></button>
                    <button class="nav-item<?php echo $initialPanel === 'announcement' ? ' active' : ''; ?>" data-panel-target="announcementPanel" type="button"><span>📢</span><span>Announcement</span></button>
                    <button class="nav-item<?php echo $initialPanel === 'leave' ? ' active' : ''; ?>" data-panel-target="leavePanel" type="button"><span>🗓️</span><span>Leave Request</span></button>
                    <a class="nav-item" href="profile.php"><span>👤</span><span>Profile</span></a>
                    <a class="nav-item logout" href="logout.php"><span>🚪</span><span>Logout</span></a>
                </nav>
                <section class="sidebar-attendance-card">
                    <h3>Attendance</h3>
                    <p class="sidebar-attendance-copy">Use the sidebar to record today&rsquo;s check-in or check-out quickly.</p>
                    <div class="sidebar-attendance-pill"><?php echo e($hasCheckedOutToday ? 'Checked Out Today' : ($hasCheckedInToday ? 'Checked In Today' : 'Not Checked In Yet')); ?></div>
                    <div class="sidebar-attendance-meta">
                        <div class="sidebar-attendance-row"><span>Check In</span><strong><?php echo e($hasCheckedInToday ? (string) date('g:i A', strtotime((string) $todayAttendance['check_in_at'])) : 'Not yet'); ?></strong></div>
                        <div class="sidebar-attendance-row"><span>Check Out</span><strong><?php echo e($hasCheckedOutToday ? (string) date('g:i A', strtotime((string) $todayAttendance['check_out_at'])) : 'Not yet'); ?></strong></div>
                    </div>
                    <div class="sidebar-attendance-actions">
                        <form method="post">
                            <input type="hidden" name="action" value="teacher_check_in">
                            <button class="sidebar-attendance-btn primary" type="submit"<?php echo $hasCheckedInToday ? ' disabled' : ''; ?>>Check In</button>
                        </form>
                        <form method="post">
                            <input type="hidden" name="action" value="teacher_check_out">
                            <button class="sidebar-attendance-btn secondary" type="submit"<?php echo !$hasCheckedInToday || $hasCheckedOutToday ? ' disabled' : ''; ?>>Check Out</button>
                        </form>
                    </div>
                </section>
            </div>
            <div class="sidebar-footer">
                <?php echo render_avatar_html($teacher, 'avatar', 'Teacher'); ?>
                <div><strong><?php echo e($displayName); ?></strong><div style="color:rgba(255,255,255,0.68);font-size:0.88rem;">Teacher Account</div></div>
            </div>
        </aside>
        <main class="main">
            <div class="main-inner">
                <section class="panel<?php echo $initialPanel === 'dashboard' ? ' active' : ''; ?>" id="dashboardPanel">
                    <div class="page-head">
                        <div class="page-head-main">
                            <div class="welcome-profile-circle">
                                <?php if ($teacherProfilePictureUrl !== null): ?>
                                    <img src="<?php echo e($teacherProfilePictureUrl); ?>" alt="<?php echo e($displayName); ?> profile picture">
                                <?php else: ?>
                                    <?php echo e(initials_from_name($displayName, 'Teacher')); ?>
                                <?php endif; ?>
                            </div>
                            <div class="page-head-copy"><h1>Welcome, <?php echo e($displayName); ?></h1><p>Manage school communication, review principal announcements, and follow parent conversations from one workspace.</p></div>
                        </div>
                        <div class="today-pill" id="todayDate">Loading date...</div>
                    </div>
                    <?php if ($flash): ?><div class="flash <?php echo e((string) ($flash['type'] ?? 'success')); ?>"><?php echo e((string) $flash['message']); ?></div><?php endif; ?>
                    <div class="stats-grid">
                        <article class="stat-card"><span class="stat-label">📢 Principal Announcements</span><div class="stat-value"><?php echo e((string) $announcementCount); ?></div></article>
                        <article class="stat-card"><span class="stat-label">👨‍👩‍👧 Parent Contacts</span><div class="stat-value"><?php echo e((string) $parentCount); ?></div></article>
                        <article class="stat-card"><span class="stat-label">💬 Chat Contacts</span><div class="stat-value"><?php echo e((string) $chatCount); ?></div></article>
                    </div>
                    <div class="grid-two">
                        <div class="section-card">
                            <h2>Latest Announcements</h2>
                            <div class="preview-list">
                                <?php if (!$announcements): ?><p class="muted">No announcements have been published for teachers yet.</p><?php endif; ?>
                                <?php foreach (array_slice($announcements, 0, 3) as $announcement): ?>
                                    <article class="preview-item">
                                        <div class="preview-top"><div><h3><?php echo e((string) $announcement['title']); ?></h3><div class="announcement-meta-line"><div class="meta"><?php echo e((string) substr((string) $announcement['published_at'], 0, 10)); ?></div><?php if (!empty($announcement['edited_at'])): ?><span class="badge">Edited</span><?php endif; ?></div></div><span class="badge"><?php echo e(audience_label_teacher((string) $announcement['audience'])); ?></span></div>
                                        <p><?php echo e((string) mb_strimwidth((string) $announcement['message'], 0, 150, '...')); ?></p>
                                        <?php if (!empty($announcement['attachment_path'])): ?><a class="announcement-attachment" href="<?php echo e(rawurlencode_path((string) $announcement['attachment_path'])); ?>" target="_blank" rel="noopener noreferrer">📎 <?php echo e((string) ($announcement['attachment_name'] ?? 'Open attachment')); ?></a><?php endif; ?>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="section-card">
                            <h2>Teacher Profile</h2>
                            <p class="muted">This portal only lets teachers receive and react to announcements. Only the principal can publish them.</p>
                            <div class="info-card profile-card">
                                <div class="profile-card-top">
                                    <div class="profile-card-avatar">
                                        <?php if ($teacherProfilePictureUrl !== null): ?>
                                            <img src="<?php echo e($teacherProfilePictureUrl); ?>" alt="<?php echo e($displayName); ?> profile picture">
                                        <?php else: ?>
                                            <?php echo e(initials_from_name($displayName, 'Teacher')); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <span class="badge">Profile Picture</span>
                                        <strong><?php echo e($displayName); ?></strong>
                                        <div class="meta" style="margin-top:6px;">Add or change your photo from your profile page.</div>
                                    </div>
                                </div>
                                <div><a class="profile-photo-action" href="profile.php">👤 Update Profile Photo</a></div>
                                <div><span class="badge">Class Information</span><strong><?php echo e($teachingClass !== '' ? $teachingClass : 'No class assigned yet'); ?></strong><div class="meta" style="margin-top:10px;">Email: <?php echo e((string) ($teacher['email'] ?? '')); ?></div><div class="meta">Principal contact: <?php echo e($principalName); ?></div></div>
                            </div>
                        </div>
                    </div>
                    <div class="attendance-panel">
                        <div class="attendance-shell">
                            <section class="attendance-status-card">
                                <h2>Daily Attendance</h2>
                                <p class="muted">Teachers should check in and check out every day. Each record is saved for the principal.</p>
                                <div class="attendance-state-pill"><?php echo e($hasCheckedOutToday ? 'Checked Out Today' : ($hasCheckedInToday ? 'Checked In Today' : 'Not Checked In Yet')); ?></div>
                                <div class="attendance-meta">
                                    <div class="attendance-meta-row"><span>Today</span><strong><?php echo e((string) date('l, j F Y')); ?></strong></div>
                                    <div class="attendance-meta-row"><span>Check In</span><strong><?php echo e($hasCheckedInToday ? (string) date('g:i A', strtotime((string) $todayAttendance['check_in_at'])) : 'Not yet'); ?></strong></div>
                                    <div class="attendance-meta-row"><span>Check Out</span><strong><?php echo e($hasCheckedOutToday ? (string) date('g:i A', strtotime((string) $todayAttendance['check_out_at'])) : 'Not yet'); ?></strong></div>
                                </div>
                                <div class="attendance-actions">
                                    <form method="post">
                                        <input type="hidden" name="action" value="teacher_check_in">
                                        <button class="attendance-btn primary" type="submit"<?php echo $hasCheckedInToday ? ' disabled' : ''; ?>>Check In</button>
                                    </form>
                                    <form method="post">
                                        <input type="hidden" name="action" value="teacher_check_out">
                                        <button class="attendance-btn secondary" type="submit"<?php echo !$hasCheckedInToday || $hasCheckedOutToday ? ' disabled' : ''; ?>>Check Out</button>
                                    </form>
                                </div>
                            </section>
                            <section class="attendance-history-card">
                                <h2>Recent Attendance</h2>
                                <p class="muted">Your latest attendance records saved in the system.</p>
                                <div class="attendance-history-list">
                                    <?php if (!$attendanceHistory): ?>
                                        <div class="attendance-history-item"><div><strong>No attendance yet</strong><div class="meta">Your check-in and check-out records will appear here.</div></div></div>
                                    <?php endif; ?>
                                    <?php foreach ($attendanceHistory as $attendanceItem): ?>
                                        <div class="attendance-history-item">
                                            <div>
                                                <strong><?php echo e((string) date('D, j M Y', strtotime((string) ($attendanceItem['attendance_date'] ?? 'now')))); ?></strong>
                                                <div class="meta"><?php echo e((string) (((string) ($attendanceItem['check_out_at'] ?? '')) !== '' ? 'Completed day' : (((string) ($attendanceItem['check_in_at'] ?? '')) !== '' ? 'Awaiting check-out' : 'No activity'))); ?></div>
                                            </div>
                                            <div class="attendance-history-times">
                                                <div>In: <?php echo e((string) (((string) ($attendanceItem['check_in_at'] ?? '')) !== '' ? date('g:i A', strtotime((string) $attendanceItem['check_in_at'])) : '--')); ?></div>
                                                <div>Out: <?php echo e((string) (((string) ($attendanceItem['check_out_at'] ?? '')) !== '' ? date('g:i A', strtotime((string) $attendanceItem['check_out_at'])) : '--')); ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        </div>
                    </div>
                </section>

                <section class="panel<?php echo $initialPanel === 'chat' ? ' active' : ''; ?>" id="chatPanel">
                    <div class="page-head"><div><h1>Chat</h1><p>Message the principal and follow parent communication threads from here.</p></div></div>
                    <?php if ($flash && $initialPanel === 'chat'): ?><div class="flash <?php echo e((string) ($flash['type'] ?? 'success')); ?>"><?php echo e((string) $flash['message']); ?></div><?php endif; ?>
                    <div class="chat-shell">
                        <aside class="contacts-pane">
                            <div class="contacts-search-wrap"><input class="search-field" id="chatSearch" type="text" placeholder="Search principal or parents by name or email..."></div>
                            <div class="contact-list" id="contactList"></div>
                        </aside>
                        <section class="chat-pane">
                            <div class="chat-header" id="chatHeader">
                                <div class="chat-user">
                                    <div class="avatar">?</div>
                                    <div>
                                        <h3>Select a contact</h3>
                                        <div class="meta">Choose the principal or a parent thread to start chatting.</div>
                                    </div>
                                </div>
                                <div class="chat-header-status">Conversation</div>
                            </div>
                            <div class="messages-area" id="messagesArea"><div class="empty-chat" id="emptyChat">Select a contact to start a conversation.</div></div>
                            <form class="chat-input" id="chatForm" method="post"><input type="hidden" name="action" value="send_teacher_chat"><input type="hidden" name="contact_type" id="contactTypeInput" value="principal"><input type="hidden" name="contact_user_id" id="contactUserIdInput" value="<?php echo e((string) $principalId); ?>"><input class="chat-compose-input" id="messageInput" name="message" type="text" placeholder="Type a message..." autocomplete="off"><button class="send-btn" type="submit">➤</button></form>
                        </section>
                    </div>
                </section>

                <section class="panel<?php echo $initialPanel === 'announcement' ? ' active' : ''; ?>" id="announcementPanel">
                    <div class="page-head"><div><h1>Announcements</h1><p>Teachers receive announcements from the principal here. You can react, but you cannot post or reply.</p></div></div>
                    <?php if ($flash && $initialPanel === 'announcement'): ?><div class="flash <?php echo e((string) ($flash['type'] ?? 'success')); ?>"><?php echo e((string) $flash['message']); ?></div><?php endif; ?>
                    <div class="announcement-list">
                        <?php if (!$announcements): ?><div class="announcement-card"><p class="muted">No announcements are available for your account yet.</p></div><?php endif; ?>
                        <?php foreach ($announcements as $announcement): ?>
                            <?php
                                $announcementId = (int) ($announcement['id'] ?? 0);
                                $reactionCounts = $announcementReactionSummary[$announcementId] ?? ['like' => 0, 'love' => 0, 'wow' => 0, 'sad' => 0];
                                $currentReaction = (string) ($announcementUserReactions[$announcementId] ?? '');
                                $reactionTotal = announcement_reaction_total_teacher($reactionCounts);
                            ?>
                            <article class="announcement-card">
                                <div class="announcement-top"><div><h3><?php echo e((string) $announcement['title']); ?></h3><div class="announcement-meta-line"><div class="meta">From <?php echo e((string) $announcement['principal_name']); ?> • <?php echo e((string) substr((string) $announcement['published_at'], 0, 16)); ?></div><?php if (!empty($announcement['edited_at'])): ?><span class="badge">Edited</span><?php endif; ?></div></div><span class="badge"><?php echo e(audience_label_teacher((string) $announcement['audience'])); ?></span></div>
                                <p><?php echo nl2br(e((string) $announcement['message'])); ?></p>
                                <?php if (!empty($announcement['attachment_path'])): ?><a class="announcement-attachment" href="<?php echo e(rawurlencode_path((string) $announcement['attachment_path'])); ?>" target="_blank" rel="noopener noreferrer">📎 <?php echo e((string) ($announcement['attachment_name'] ?? 'Open attachment')); ?></a><?php endif; ?>
                                <div class="meta" style="margin-top:14px;">Reaction only. Teachers cannot reply to announcements.</div>
                                <form class="reaction-bar" method="post">
                                    <input type="hidden" name="action" value="toggle_announcement_reaction">
                                    <input type="hidden" name="announcement_id" value="<?php echo e((string) $announcementId); ?>">
                                    <?php foreach (['like' => '👍 Like', 'love' => '❤️ Love', 'wow' => '😮 Wow', 'sad' => '😢 Sad'] as $reactionKey => $reactionLabel): ?>
                                        <button class="reaction-btn<?php echo $currentReaction === $reactionKey ? ' active' : ''; ?>" type="submit" name="reaction" value="<?php echo e($reactionKey); ?>"><?php echo e($reactionLabel); ?> · <?php echo e((string) ($reactionCounts[$reactionKey] ?? 0)); ?></button>
                                    <?php endforeach; ?>
                                    <span class="meta"><?php echo $reactionTotal > 0 ? e((string) $reactionTotal . ' total reaction' . ($reactionTotal === 1 ? '' : 's')) : 'No reactions yet'; ?></span>
                                </form>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="panel<?php echo $initialPanel === 'leave' ? ' active' : ''; ?>" id="leavePanel">
                    <div class="page-head"><div><h1>Leave Requests</h1><p>Request leave or vacation days for principal approval.</p></div></div>
                    <?php if ($flash && $initialPanel === 'leave'): ?><div class="flash <?php echo e((string) ($flash['type'] ?? 'success')); ?>"><?php echo e((string) $flash['message']); ?></div><?php endif; ?>
                    <div class="leave-shell">
                        <section class="leave-form-card">
                            <h2>Submit Request</h2>
                            <p class="muted">Send a leave or vacation request to the principal. You will see the decision here once it is reviewed.</p>
                            <form class="leave-form" method="post">
                                <input type="hidden" name="action" value="submit_teacher_leave_request">
                                <label class="field">
                                    <span class="label">Request Type</span>
                                    <select name="leave_type" required>
                                        <option value="leave">Leave</option>
                                        <option value="vacation">Vacation</option>
                                    </select>
                                </label>
                                <label class="field">
                                    <span class="label">Start Date</span>
                                    <input type="date" name="start_date" required>
                                </label>
                                <label class="field">
                                    <span class="label">End Date</span>
                                    <input type="date" name="end_date" required>
                                </label>
                                <label class="field">
                                    <span class="label">Reason</span>
                                    <textarea name="reason" placeholder="Explain the reason for this leave or vacation request..." required></textarea>
                                </label>
                                <button class="attendance-btn primary" type="submit">Send Request</button>
                            </form>
                        </section>
                        <section class="leave-history-card">
                            <h2>Your Requests</h2>
                            <p class="muted">Track your latest leave and vacation requests.</p>
                            <div class="leave-history-list">
                                <?php if (!$leaveRequests): ?>
                                    <div class="leave-history-item"><strong>No requests yet</strong><div class="meta" style="margin-top:6px;">Your leave and vacation requests will show here.</div></div>
                                <?php endif; ?>
                                <?php foreach ($leaveRequests as $leaveRequest): ?>
                                    <?php
                                        $reviewerName = trim((string) (($leaveRequest['reviewer_full_name'] ?? '') !== '' ? $leaveRequest['reviewer_full_name'] : (($leaveRequest['reviewer_first_name'] ?? '') . ' ' . ($leaveRequest['reviewer_surname'] ?? ''))));
                                        $leaveStatus = (string) ($leaveRequest['status'] ?? 'pending');
                                    ?>
                                    <article class="leave-history-item">
                                        <div class="leave-history-top">
                                            <div>
                                                <strong><?php echo e(ucfirst((string) ($leaveRequest['leave_type'] ?? 'leave'))); ?></strong>
                                                <div class="meta"><?php echo e((string) date('j M Y', strtotime((string) ($leaveRequest['start_date'] ?? 'now')))); ?> to <?php echo e((string) date('j M Y', strtotime((string) ($leaveRequest['end_date'] ?? 'now')))); ?></div>
                                            </div>
                                            <span class="badge <?php echo $leaveStatus === 'approved' ? 'badge-teal' : ($leaveStatus === 'declined' ? 'badge-blue' : 'badge-gold'); ?>"><?php echo e(ucfirst($leaveStatus)); ?></span>
                                        </div>
                                        <div class="meta"><?php echo nl2br(e((string) ($leaveRequest['reason'] ?? ''))); ?></div>
                                        <?php if ($reviewerName !== '' || (string) ($leaveRequest['principal_note'] ?? '') !== ''): ?>
                                            <div class="leave-note">
                                                <?php if ($reviewerName !== ''): ?><div>Reviewed by: <?php echo e($reviewerName); ?></div><?php endif; ?>
                                                <?php if ((string) ($leaveRequest['principal_note'] ?? '') !== ''): ?><div style="margin-top:6px;">Note: <?php echo nl2br(e((string) $leaveRequest['principal_note'])); ?></div><?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <script>
        (function () {
            const particlesContainer = document.getElementById('particles-container');
            const navItems = document.querySelectorAll('[data-panel-target]');
            const panels = document.querySelectorAll('.panel');
            const sidebar = document.getElementById('sidebar');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            const todayDate = document.getElementById('todayDate');
            const contactList = document.getElementById('contactList');
            const chatSearch = document.getElementById('chatSearch');
            const chatHeader = document.getElementById('chatHeader');
            const messagesArea = document.getElementById('messagesArea');
            const emptyChat = document.getElementById('emptyChat');
            const contactTypeInput = document.getElementById('contactTypeInput');
            const contactUserIdInput = document.getElementById('contactUserIdInput');
            const chatNavButton = document.querySelector('[data-panel-target="chatPanel"]');
            const contacts = <?php echo json_encode($chatContacts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
            const conversations = Object.assign({ 'principal-main': <?php echo json_encode($principalConversation, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?> }, <?php echo json_encode($parentConversations, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>);
            let activeContactId = <?php echo json_encode($initialContactId, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?> || (contacts.length ? contacts[0].id : null);
            let totalUnreadMessages = <?php echo json_encode($totalUnreadMessages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

            if (particlesContainer) {
                const count = window.innerWidth < 768 ? 25 : 55;
                const colors = ['rgba(245,166,35,0.45)', 'rgba(0,212,180,0.35)', 'rgba(74,158,255,0.3)', 'rgba(255,255,255,0.2)'];
                for (let index = 0; index < count; index += 1) {
                    const dot = document.createElement('span');
                    const size = Math.random() * 3 + 1.5;
                    dot.style.cssText = `position:absolute; width:${size}px; height:${size}px; border-radius:50%; background:${colors[Math.floor(Math.random() * colors.length)]}; top:${Math.random() * 100}%; left:${Math.random() * 100}%; animation:floatDot ${Math.random() * 14 + 8}s ease-in-out ${Math.random() * 5}s infinite alternate;`;
                    particlesContainer.appendChild(dot);
                }
            }

            function closeSidebar() {
                if (sidebar) {
                    sidebar.classList.remove('open');
                }
                if (sidebarOverlay) {
                    sidebarOverlay.classList.remove('show');
                }
                document.body.classList.remove('sidebar-open');
            }

            function switchPanel(panelId) {
                panels.forEach((panel) => panel.classList.toggle('active', panel.id === panelId));
                navItems.forEach((item) => item.classList.toggle('active', item.dataset.panelTarget === panelId));
                closeSidebar();
            }

            navItems.forEach((item) => {
                item.addEventListener('click', () => switchPanel(item.dataset.panelTarget));
            });

            if (sidebarToggle && sidebar && sidebarOverlay) {
                sidebarToggle.addEventListener('click', () => {
                    const willOpen = !sidebar.classList.contains('open');
                    sidebar.classList.toggle('open', willOpen);
                    sidebarOverlay.classList.toggle('show', willOpen);
                    document.body.classList.toggle('sidebar-open', willOpen);
                });
                sidebarOverlay.addEventListener('click', closeSidebar);
            }

            if (todayDate) {
                todayDate.textContent = new Date().toLocaleDateString('en-NG', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
            }

            function renderContacts(query = '') {
                if (!contactList) {
                    return;
                }

                const filter = query.trim().toLowerCase();
                const filtered = contacts.filter((contact) => {
                    return !filter
                        || String(contact.name).toLowerCase().includes(filter)
                        || String(contact.email || '').toLowerCase().includes(filter)
                        || String(contact.role).toLowerCase().includes(filter)
                        || String(contact.meta).toLowerCase().includes(filter);
                });

                contactList.innerHTML = '';
                if (!filtered.length) {
                    contactList.innerHTML = '<p class="muted">No contacts match your search.</p>';
                    return;
                }

                filtered.forEach((contact) => {
                    const row = document.createElement('button');
                    row.type = 'button';
                    row.className = 'contact-row' + (contact.id === activeContactId ? ' active' : '');
                    row.innerHTML = avatarMarkup(contact, 'contact-avatar') + '<div class="contact-body"><div class="contact-head"><div><div class="contact-title">' + contact.name + '</div><div class="contact-meta">' + contact.role + ' • ' + contact.meta + '</div></div><div class="contact-side"><div class="contact-time">' + (contact.time || '') + '</div>' + (contact.unread > 0 ? '<span class="contact-unread">' + contact.unread + '</span>' : '') + '</div></div><div class="contact-preview">' + contact.preview + '</div></div>';
                    row.addEventListener('click', () => {
                        activeContactId = contact.id;
                        markActiveContactRead();
                        renderContacts(chatSearch ? chatSearch.value : '');
                        renderConversation();
                    });
                    contactList.appendChild(row);
                });
            }

            function avatarMarkup(contact, className) {
                if (contact.avatar_image_url) {
                    return '<div class="' + className + '" style="background:' + contact.color + ';"><img src="' + contact.avatar_image_url + '" alt="' + contact.name.replace(/"/g, '&quot;') + ' profile picture"></div>';
                }

                return '<div class="' + className + '" style="background:' + contact.color + ';">' + contact.avatar + '</div>';
            }

            function updateChatBadge() {
                if (!chatNavButton) {
                    return;
                }

                let badge = document.getElementById('chatUnreadBadge');
                if (totalUnreadMessages > 0) {
                    if (!badge) {
                        badge = document.createElement('span');
                        badge.id = 'chatUnreadBadge';
                        badge.className = 'badge';
                        badge.style.background = 'rgba(220,38,38,.16)';
                        badge.style.color = '#ffb4b4';
                        chatNavButton.appendChild(badge);
                    }
                    badge.textContent = String(totalUnreadMessages);
                } else if (badge) {
                    badge.remove();
                }
            }

            function markActiveContactRead() {
                const activeContact = contacts.find((contact) => contact.id === activeContactId);
                if (!activeContact || activeContact.unread < 1) {
                    return;
                }

                totalUnreadMessages = Math.max(0, totalUnreadMessages - activeContact.unread);
                activeContact.unread = 0;
                updateChatBadge();

                const payload = new URLSearchParams();
                payload.set('action', 'mark_teacher_chat_read');
                payload.set('contact_type', activeContact.id === 'principal-main' ? 'principal' : 'parent');
                payload.set('contact_user_id', String(activeContact.user_id || 0));

                fetch('teacher-dashboard.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: payload.toString()
                }).catch(() => {
                    // Keep the UI responsive even if the network request fails.
                });
            }

            function renderConversation() {
                const activeContact = contacts.find((contact) => contact.id === activeContactId);
                if (!activeContact || !chatHeader || !messagesArea || !emptyChat) {
                    return;
                }

                chatHeader.innerHTML = '<div class="chat-user">' + avatarMarkup(activeContact, 'contact-avatar') + '<div><h3>' + activeContact.name + '</h3><div class="meta">' + activeContact.role + ' • ' + activeContact.meta + '</div></div></div><div class="chat-header-status">' + (activeContact.unread > 0 ? activeContact.unread + ' unread' : 'Conversation') + '</div>';
                messagesArea.innerHTML = '';

                const items = Array.isArray(conversations[activeContact.id]) ? conversations[activeContact.id] : [];
                if (!items.length) {
                    messagesArea.appendChild(emptyChat);
                    emptyChat.textContent = 'No messages yet for this contact.';
                } else {
                    items.forEach((message) => {
                        const wrap = document.createElement('div');
                        wrap.className = 'message-wrap ' + message.type;
                        wrap.innerHTML = '<div class="message-bubble">' + String(message.text).replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>') + '</div><div class="message-time">' + message.time + '</div>';
                        messagesArea.appendChild(wrap);
                    });
                }

                contactTypeInput.value = activeContact.id === 'principal-main' ? 'principal' : 'parent';
                contactUserIdInput.value = String(activeContact.user_id || 0);
                messagesArea.scrollTop = messagesArea.scrollHeight;
            }

            if (chatSearch) {
                chatSearch.addEventListener('input', () => renderContacts(chatSearch.value));
            }

            updateChatBadge();
            renderContacts();
            renderConversation();
        })();
    </script>
</body>
</html>
