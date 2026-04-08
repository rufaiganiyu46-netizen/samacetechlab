<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';

$parent = require_role('parent');
$flash = get_flash();
$displayName = display_name_from_user($parent, 'Parent');
$parentProfilePictureUrl = user_profile_picture_url($parent);
$childName = trim((string) ($parent['child_name'] ?? ''));
$parentId = (int) ($parent['id'] ?? 0);
$initialPanel = in_array((string) ($_GET['panel'] ?? 'dashboard'), ['dashboard', 'chat', 'announcement'], true)
    ? (string) ($_GET['panel'] ?? 'dashboard')
    : 'dashboard';
$initialContactId = (string) ($_GET['contact'] ?? 'principal-main');
$announcements = fetch_announcements_for_role('parent');

if (is_post() && (string) ($_POST['action'] ?? '') === 'toggle_announcement_reaction') {
    $announcementId = (int) ($_POST['announcement_id'] ?? 0);
    $reaction = (string) ($_POST['reaction'] ?? '');
    $reactionError = null;

    if (save_announcement_reaction($announcementId, $parentId, 'parent', $reaction, $reactionError)) {
        set_flash('success', 'Announcement reaction updated.');
    } else {
        set_flash('error', $reactionError ?: 'Unable to update the reaction right now.');
    }

    redirect('parent-dashboard.php?panel=announcement');
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

if ($principalId > 0 && $parentId > 0) {
    $principalThreadId = find_or_create_support_thread($principalId, $parentId, 'parent', 'Parent Support Chat');
    if ($principalThreadId !== null) {
        $principalMessages = fetch_support_messages($principalThreadId);
        foreach ($principalMessages as $messageRow) {
            if (((string) ($messageRow['sender_role'] ?? '')) !== 'parent' && (int) ($messageRow['is_read'] ?? 0) === 0) {
                $principalUnreadCount++;
            }
        }
    }
}

$teacherRows = [];
$teacherDirectoryIds = [];
$teacherUnreadById = [];
if (db_ready() && $db instanceof mysqli) {
    $teacherResult = $db->query("SELECT id, full_name, first_name, surname, email, teaching_class, profile_picture_path FROM users WHERE role = 'teacher' AND approval_status = 'approved' ORDER BY full_name ASC, id ASC");
    if ($teacherResult) {
        while ($row = $teacherResult->fetch_assoc()) {
            $teacherRows[] = $row;
        }
        $teacherResult->free();
    }
}

$teacherThreads = [];
$teacherMessagesById = [];
foreach ($teacherRows as $teacherRow) {
    $teacherId = (int) ($teacherRow['id'] ?? 0);
    if ($teacherId < 1 || $parentId < 1) {
        continue;
    }

    $teacherDirectoryIds[] = $teacherId;

    $thread = fetch_direct_chat_thread($parentId, 'parent', $teacherId, 'teacher');
    if (!$thread) {
        continue;
    }

    $threadId = (int) ($thread['id'] ?? 0);
    if ($threadId < 1) {
        continue;
    }

    $teacherThreads[$teacherId] = $threadId;
    $teacherMessagesById[$teacherId] = fetch_direct_chat_messages($threadId);
    $teacherUnreadById[$teacherId] = 0;
    foreach ($teacherMessagesById[$teacherId] as $messageRow) {
        if (((string) ($messageRow['sender_role'] ?? '')) !== 'parent' && (int) ($messageRow['is_read'] ?? 0) === 0) {
            $teacherUnreadById[$teacherId]++;
        }
    }
}

if (is_post() && (string) ($_POST['action'] ?? '') === 'send_parent_chat') {
    $contactType = (string) ($_POST['contact_type'] ?? '');
    $contactUserId = (int) ($_POST['contact_user_id'] ?? 0);
    $message = (string) ($_POST['message'] ?? '');

    if ($contactType === 'principal' && $principalThreadId !== null) {
        if (!send_support_message($principalThreadId, $parentId, 'parent', $message, $chatError)) {
            $flash = ['type' => 'error', 'message' => $chatError ?: 'Unable to send the message right now.'];
            $initialPanel = 'chat';
            $initialContactId = 'principal-main';
        } else {
            set_flash('success', 'Your message has been sent.');
            redirect('parent-dashboard.php?panel=chat&contact=principal-main');
        }
    } elseif ($contactType === 'teacher' && in_array($contactUserId, $teacherDirectoryIds, true)) {
        $teacherThreadId = $teacherThreads[$contactUserId] ?? find_or_create_direct_chat_thread($parentId, 'parent', $contactUserId, 'teacher', 'Parent Teacher Chat');
        if ($teacherThreadId === null || !send_direct_chat_message($teacherThreadId, $parentId, 'parent', $message, $chatError)) {
            $flash = ['type' => 'error', 'message' => $chatError ?: 'Unable to send the message right now.'];
            $initialPanel = 'chat';
            $initialContactId = 'teacher-' . $contactUserId;
        } else {
            set_flash('success', 'Your message has been sent.');
            redirect('parent-dashboard.php?panel=chat&contact=teacher-' . $contactUserId);
        }
    } else {
        $flash = ['type' => 'error', 'message' => 'Please choose a valid chat contact.'];
        $initialPanel = 'chat';
    }
}

$principalPreview = 'No messages yet.';
$principalTime = '';
$principalConversation = [];
foreach ($principalMessages as $messageRow) {
    $principalConversation[] = [
        'type' => ((string) ($messageRow['sender_role'] ?? '')) === 'parent' ? 'sent' : 'received',
        'text' => (string) ($messageRow['message'] ?? ''),
        'time' => date('g:i A', strtotime((string) ($messageRow['created_at'] ?? 'now'))),
    ];
}

if ($principalMessages) {
    $lastPrincipalMessage = end($principalMessages);
    if (is_array($lastPrincipalMessage)) {
        $principalPreview = (string) ($lastPrincipalMessage['message'] ?? $principalPreview);
        $principalTime = date('g:i A', strtotime((string) ($lastPrincipalMessage['created_at'] ?? 'now')));
    }
}

$teacherContacts = [];
$teacherConversations = [];
$totalUnreadMessages = $principalUnreadCount;
$palette = ['#00D4B4', '#4A9EFF', '#F5A623', '#16A34A', '#9B6DFF', '#FF7A59'];
foreach ($teacherRows as $index => $teacherRow) {
    $teacherId = (int) ($teacherRow['id'] ?? 0);
    $name = display_name_from_user($teacherRow, 'Teacher');
    $className = trim((string) ($teacherRow['teaching_class'] ?? ''));
    $messages = $teacherMessagesById[$teacherId] ?? [];
    $preview = 'No messages yet.';
    $time = '';
    $conversation = [];

    foreach ($messages as $messageRow) {
        $conversation[] = [
            'type' => ((string) ($messageRow['sender_role'] ?? '')) === 'parent' ? 'sent' : 'received',
            'text' => (string) ($messageRow['message'] ?? ''),
            'time' => date('g:i A', strtotime((string) ($messageRow['created_at'] ?? 'now'))),
        ];
    }

    if ($messages) {
        $lastMessage = end($messages);
        if (is_array($lastMessage)) {
            $preview = (string) ($lastMessage['message'] ?? $preview);
            $time = date('g:i A', strtotime((string) ($lastMessage['created_at'] ?? 'now')));
        }
    }

    $contactId = 'teacher-' . $teacherId;
    $teacherAvatar = avatar_payload_from_user($teacherRow, 'Teacher');
    $teacherContacts[] = [
        'id' => $contactId,
        'user_id' => $teacherId,
        'name' => $name,
        'email' => (string) ($teacherRow['email'] ?? ''),
        'role' => 'Teacher',
        'meta' => $className !== '' ? 'Class: ' . $className : 'Teaching staff',
        'avatar' => $teacherAvatar['avatar'],
        'avatar_image_url' => $teacherAvatar['avatar_image_url'],
        'color' => $palette[$index % count($palette)],
        'preview' => $preview,
        'time' => $time,
        'unread' => (int) ($teacherUnreadById[$teacherId] ?? 0),
    ];
    $teacherConversations[$contactId] = $conversation;
    $totalUnreadMessages += (int) ($teacherUnreadById[$teacherId] ?? 0);
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
    'color' => '#F5A623',
    'preview' => $principalPreview,
    'time' => $principalTime,
    'unread' => $principalUnreadCount,
]], $teacherContacts);

$announcementIds = array_map(static fn(array $announcement): int => (int) ($announcement['id'] ?? 0), $announcements);
$announcementReactionSummary = fetch_announcement_reaction_summary($announcementIds);
$announcementUserReactions = fetch_user_announcement_reactions($parentId, 'parent', $announcementIds);
$announcementCount = count($announcements);
$teacherCount = count($teacherContacts);
$chatCount = count($chatContacts);

function audience_label_parent(string $audience): string
{
    return match ($audience) {
        'teachers' => 'Teachers Only',
        'parents' => 'Parents Only',
        'both' => 'Everyone',
        default => ucfirst($audience),
    };
}

function announcement_reaction_total_parent(array $counts): int
{
    return (int) array_sum($counts);
}

if ($initialPanel === 'chat') {
    if ($initialContactId === 'principal-main' && $principalThreadId !== null) {
        mark_thread_messages_read($principalThreadId, 'parent');
        $totalUnreadMessages -= $principalUnreadCount;
        $principalUnreadCount = 0;
        if (isset($chatContacts[0])) {
            $chatContacts[0]['unread'] = 0;
        }
    } elseif (str_starts_with($initialContactId, 'teacher-')) {
        $selectedTeacherId = (int) substr($initialContactId, 8);
        $selectedThreadId = $teacherThreads[$selectedTeacherId] ?? null;
        if ($selectedThreadId !== null) {
            mark_direct_chat_messages_read($selectedThreadId, $parentId, 'parent');
            $selectedUnreadCount = (int) ($teacherUnreadById[$selectedTeacherId] ?? 0);
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
    <title>Parent Dashboard | SAMACE TECH LAB</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Playfair+Display:wght@700;800&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #0A0A0F;
            --bg-secondary: rgba(13,13,24,0.95);
            --glass: rgba(255,255,255,0.04);
            --glass-soft: rgba(255,255,255,0.03);
            --line: rgba(255,255,255,0.08);
            --text-primary: #FFFFFF;
            --text-secondary: #A0A8B8;
            --text-muted: #5A6070;
            --accent: #00D4B4;
            --accent-glow: rgba(0,212,180,0.35);
            --danger: #FF4757;
            --font-display: 'Bebas Neue', cursive;
            --font-heading: 'Playfair Display', serif;
            --font-body: 'DM Sans', sans-serif;
            --sidebar-width: 250px;
            --transition: all 0.35s cubic-bezier(0.23,1,0.32,1);
        }

        * { margin:0; padding:0; box-sizing:border-box; }
        html, body { height:100%; overflow:hidden; }

        body {
            background: #0A0A0F;
            font-family: var(--font-body);
            color: #fff;
            display: flex;
            overflow: hidden;
            position: relative;
        }

        a { color: inherit; text-decoration: none; }
        button, input { font: inherit; }

        .orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(140px);
            z-index: 0;
            pointer-events: none;
            animation: orbDrift 20s ease-in-out infinite alternate;
        }

        .orb-gold { width:700px; height:700px; background:radial-gradient(circle, rgba(245,166,35,0.1), transparent 70%); top:-200px; left:50px; animation-duration:20s; }
        .orb-teal { width:500px; height:500px; background:radial-gradient(circle, rgba(0,212,180,0.07), transparent 70%); bottom:-100px; right:100px; animation-direction:alternate-reverse; animation-duration:26s; }
        .orb-blue { width:400px; height:400px; background:radial-gradient(circle, rgba(74,158,255,0.06), transparent 70%); top:40%; right:-50px; animation-duration:32s; }

        #particles-container {
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            overflow: hidden;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            height: 100vh;
            background: rgba(13,13,24,0.95);
            border-right: 1px solid rgba(255,255,255,0.06);
            display: flex;
            flex-direction: column;
            z-index: 100;
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            overflow-y: auto;
            overflow-x: hidden;
            transition: transform 0.4s cubic-bezier(0.23,1,0.32,1);
        }

        .sidebar-logo {
            padding: 28px 24px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            position: relative;
        }

        .sidebar-logo::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 24px;
            right: 24px;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(0,212,180,0.3), transparent);
        }

        .sidebar-school-name {
            font-family: var(--font-display);
            font-size: 17px;
            letter-spacing: 2.5px;
            color: var(--accent);
            display: block;
            line-height: 1;
        }

        .sidebar-school-sub {
            font-size: 9px;
            color: #5A6070;
            letter-spacing: 2px;
            text-transform: uppercase;
            display: block;
            margin-top: 4px;
        }

        .sidebar-role-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(0,212,180,0.1);
            border: 1px solid rgba(0,212,180,0.2);
            color: var(--accent);
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            padding: 4px 10px;
            border-radius: 50px;
            margin-top: 10px;
        }

        .nav-section-label {
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 2.5px;
            text-transform: uppercase;
            color: #5A6070;
            padding: 20px 24px 8px;
        }

        .sidebar nav { padding: 8px 0; flex: 1; }

        .sidebar nav .nav-item,
        .sidebar nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 13px 24px;
            color: #A0A8B8;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            border-left: 3px solid transparent;
            transition: all 0.3s cubic-bezier(0.23,1,0.32,1);
            position: relative;
            margin: 1px 12px 1px 0;
            border-radius: 0 12px 12px 0;
            background: transparent;
            border-top: none;
            border-right: none;
            border-bottom: none;
            cursor: pointer;
            text-align: left;
            width: calc(100% - 12px);
        }

        .sidebar nav .nav-icon { font-size: 18px; width: 22px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .sidebar nav .nav-label { flex: 1; }
        .nav-badge { background:#FF4757; color:#fff; font-size:10px; font-weight:700; min-width:18px; height:18px; border-radius:50px; padding:0 5px; display:flex; align-items:center; justify-content:center; }

        .sidebar nav .nav-item:hover,
        .sidebar nav a:hover {
            color: #fff;
            background: rgba(255,255,255,0.05);
            border-left-color: rgba(0,212,180,0.4);
            transform: translateX(3px);
        }

        .sidebar nav .nav-item.active {
            color: #00D4B4;
            background: rgba(0,212,180,0.08);
            border-left-color: #00D4B4;
            font-weight: 600;
        }

        .sidebar nav .nav-item.active::before {
            content: '';
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #00D4B4;
            box-shadow: 0 0 8px rgba(0,212,180,0.8);
        }

        .sidebar nav a.logout-link { color: #FF4757 !important; margin-top: 4px; }
        .sidebar nav a.logout-link:hover { background: rgba(255,71,87,0.08); border-left-color: #FF4757; color: #FF4757 !important; }

        .sidebar-footer {
            padding: 16px 24px 24px;
            border-top: 1px solid rgba(255,255,255,0.06);
            position: relative;
        }

        .sidebar-footer::before {
            content: '';
            position: absolute;
            top: 0;
            left: 24px;
            right: 24px;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.08), transparent);
        }

        .sidebar-user {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            border-radius: 14px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.06);
            transition: all 0.3s;
        }

        .sidebar-user:hover {
            background: rgba(0,212,180,0.06);
            border-color: rgba(0,212,180,0.15);
        }

        .sidebar-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            overflow: hidden;
            background: linear-gradient(135deg, #00D4B4, rgba(0,212,180,0.4));
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: var(--font-display);
            font-size: 17px;
            color: #0A0A0F;
            flex-shrink: 0;
            box-shadow: 0 0 15px rgba(0,212,180,0.3);
        }

        .sidebar-user-name { font-size: 13px; font-weight: 600; color: #fff; display: block; }
        .sidebar-user-role { font-size: 11px; color: #5A6070; display: block; }

        .hamburger {
            display: none;
            position: fixed;
            top: 16px;
            left: 16px;
            z-index: 200;
            background: rgba(15,15,26,0.9);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            padding: 10px;
            cursor: pointer;
            backdrop-filter: blur(12px);
            color: #00D4B4;
            font-size: 20px;
            transition: all 0.3s;
        }

        .hamburger:hover { border-color: rgba(0,212,180,0.3); box-shadow: 0 0 15px rgba(0,212,180,0.2); }

        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.6);
            z-index: 99;
            backdrop-filter: blur(4px);
        }

        .main-content {
            position: fixed;
            top: 0;
            left: 250px;
            right: 0;
            bottom: 0;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 32px 20px 40px;
            z-index: 1;
        }

        .panel { display: none; }
        .panel.active { display: block; }

        #chatPanel.active {
            min-height: calc(100vh - 72px);
            display: flex;
            flex-direction: column;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 36px;
            padding-bottom: 28px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            position: relative;
            gap: 18px;
            flex-wrap: wrap;
        }

        .page-header::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 80px;
            height: 2px;
            background: linear-gradient(90deg, #00D4B4, transparent);
            border-radius: 2px;
        }

        .page-header-main {
            display: flex;
            align-items: center;
            gap: 18px;
            min-width: 0;
            flex: 1 1 520px;
            max-width: 100%;
        }

        .welcome-profile-circle {
            width: 96px;
            height: 96px;
            min-width: 96px;
            border-radius: 50%;
            overflow: hidden;
            flex-shrink: 0;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, rgba(0,212,180,0.28), rgba(74,158,255,0.16));
            border: 1px solid rgba(0,212,180,0.24);
            box-shadow: 0 18px 44px rgba(8,16,30,0.34);
            color: #e8fffb;
            font-family: var(--font-display);
            font-size: 34px;
        }

        .welcome-profile-circle img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            display: block;
            border-radius: inherit;
        }

        .page-header-copy {
            min-width: 0;
            flex: 1 1 auto;
        }

        .welcome-title {
            font-family: var(--font-heading);
            font-size: 36px;
            font-weight: 700;
            color: #fff;
            line-height: 1.2;
        }

        .welcome-subtitle { font-size: 14px; color: #A0A8B8; margin-top: 6px; font-weight: 400; }

        .date-badge {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            padding: 10px 18px;
            font-size: 13px;
            color: #A0A8B8;
            backdrop-filter: blur(10px);
            white-space: nowrap;
            font-weight: 500;
        }

        .flash {
            padding: 14px 16px;
            border-radius: 16px;
            margin-bottom: 20px;
            border: 1px solid rgba(255,255,255,0.08);
            background: rgba(255,255,255,0.04);
        }

        .flash.success { color: #9ff4e7; background: rgba(0,212,180,0.12); }
        .flash.error { color: #ff9aa5; background: rgba(255,71,87,0.12); }

        .section-title {
            font-family: var(--font-heading);
            font-size: 20px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title::after {
            content: '';
            flex: 1;
            height: 1px;
            background: linear-gradient(90deg, rgba(255,255,255,0.08), transparent);
        }

        .stats-grid { display:grid; grid-template-columns:repeat(3, 1fr); gap:20px; margin-bottom:36px; }

        .stat-card {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 20px;
            padding: 24px;
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(12px);
            transition: all 0.4s cubic-bezier(0.23,1,0.32,1);
            animation: cardReveal 0.6s cubic-bezier(0.23,1,0.32,1) both;
        }

        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }
        .stat-card::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; border-radius:20px 20px 0 0; }
        .stat-card.teal::before { background: linear-gradient(90deg, #00D4B4, rgba(0,212,180,0.3)); }
        .stat-card.blue::before { background: linear-gradient(90deg, #4A9EFF, rgba(74,158,255,0.3)); }
        .stat-card.gold::before { background: linear-gradient(90deg, #F5A623, rgba(245,166,35,0.3)); }
        .stat-card:hover { transform: translateY(-6px); border-color: rgba(255,255,255,0.14); box-shadow: 0 20px 60px rgba(0,0,0,0.4); }
        .stat-icon { font-size: 28px; margin-bottom: 14px; display:block; }
        .stat-label { font-size: 11px; font-weight: 600; letter-spacing: 1.5px; text-transform: uppercase; color: #5A6070; margin-bottom: 8px; display:block; }
        .stat-value { font-family: var(--font-display); font-size: 52px; line-height: 1; color: #fff; display:block; }

        .content-grid { display:grid; grid-template-columns:1.1fr 0.9fr; gap:24px; }

        .glass-panel {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 20px;
            padding: 28px;
            backdrop-filter: blur(12px);
            position: relative;
            overflow: hidden;
            margin-bottom: 24px;
            animation: cardReveal 0.7s cubic-bezier(0.23,1,0.32,1) both;
        }

        .glass-panel::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.08), transparent);
        }

        .announcement-card {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 16px;
            padding: 22px;
            margin-bottom: 16px;
            transition: all 0.35s cubic-bezier(0.23,1,0.32,1);
            position: relative;
            overflow: hidden;
        }

        .announcement-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 3px;
            background: linear-gradient(180deg, #00D4B4, rgba(0,212,180,0.2));
            border-radius: 3px 0 0 3px;
        }

        .announcement-card:hover { background: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.12); transform: translateX(4px); }
        .announcement-title { font-family: var(--font-heading); font-size: 17px; font-weight: 700; color: #fff; margin-bottom: 8px; }
        .announcement-body, .muted, .meta { font-size: 14px; color: #A0A8B8; line-height: 1.6; }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.5px;
            background: rgba(0,212,180,0.12);
            color: #00D4B4;
            border: 1px solid rgba(0,212,180,0.2);
        }

        .reaction-bar { display:flex; gap:8px; margin-top:16px; padding-top:14px; border-top:1px solid rgba(255,255,255,0.06); flex-wrap:wrap; align-items:center; }
        .announcement-attachment { display:inline-flex; align-items:center; gap:8px; margin-top:14px; padding:10px 14px; border-radius:14px; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.08); color:#dff1ff; font-weight:700; }
        .announcement-image-preview { display:block; width:100%; margin-top:14px; border-radius:18px; overflow:hidden; border:1px solid rgba(255,255,255,0.08); background:rgba(255,255,255,0.03); box-shadow:0 18px 36px rgba(0,0,0,0.2); }
        .announcement-image-preview img { display:block; width:100%; max-height:360px; object-fit:cover; object-position:center; }
        .announcement-meta-line { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
        .reaction-btn {
            display:flex;
            align-items:center;
            gap:5px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.09);
            border-radius: 50px;
            padding: 6px 14px;
            font-size: 14px;
            color: #A0A8B8;
            cursor: pointer;
            transition: all 0.25s;
            font-family: var(--font-body);
        }

        .reaction-btn:hover { background: rgba(0,212,180,0.1); border-color: rgba(0,212,180,0.2); transform: scale(1.06); }
        .reaction-btn.active { background: rgba(0,212,180,0.15); border-color: rgba(0,212,180,0.3); color: #00D4B4; box-shadow: 0 0 12px rgba(0,212,180,0.2); }

        .chat-container {
            display: grid;
            grid-template-columns: 320px 1fr;
            height: calc(100vh - 250px);
            min-height: 520px;
            background: rgba(255,255,255,0.02);
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 20px;
            overflow: hidden;
            min-width: 0;
        }

        .contact-list-pane {
            border-right: 1px solid rgba(255,255,255,0.07);
            display:flex;
            flex-direction:column;
            background: rgba(255,255,255,0.02);
            overflow:hidden;
            min-height: 0;
        }
        .contact-search { padding:16px; border-bottom:1px solid rgba(255,255,255,0.06); }

        .contact-search input,
        .chat-input {
            width: 100%;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 11px 16px;
            color: #fff;
            font-family: var(--font-body);
            font-size: 13px;
            outline: none;
            transition: all 0.3s;
        }

        .contact-search input:focus,
        .chat-input:focus { border-color: #00D4B4; box-shadow: 0 0 0 3px rgba(0,212,180,0.1); }
        .contact-search input::placeholder,
        .chat-input::placeholder { color: #5A6070; }

        .contact-list {
            overflow-y: auto;
            flex: 1;
            min-height: 0;
        }

        .contact-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            border-left: 3px solid transparent;
            border-bottom: 1px solid rgba(255,255,255,0.04);
            cursor: pointer;
            transition: all 0.25s;
            background: transparent;
            width: 100%;
            text-align: left;
            color: inherit;
            border-top: none;
            border-right: none;
            border-bottom-color: rgba(255,255,255,0.04);
        }

        .contact-item:hover { background: rgba(255,255,255,0.04); border-left-color: rgba(0,212,180,0.3); }
        .contact-item.active { background: rgba(0,212,180,0.07); border-left-color: #00D4B4; }

        .contact-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: var(--font-display);
            font-size: 16px;
            color: #0A0A0F;
            flex-shrink: 0;
        }
        .sidebar-avatar img, .contact-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            border-radius: inherit;
        }

        .contact-info { flex: 1; min-width: 0; }
        .contact-name { font-size: 13px; font-weight: 600; color: #fff; display: block; }
        .contact-preview { font-size: 11px; color: #5A6070; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .contact-time { font-size: 10px; color: #5A6070; flex-shrink: 0; }
        .contact-unread { background:#FF4757; color:#fff; font-size:10px; font-weight:700; min-width:18px; height:18px; border-radius:50px; padding:0 5px; display:inline-flex; align-items:center; justify-content:center; margin-top:6px; }

        .chat-window {
            display:flex;
            flex-direction:column;
            background: rgba(255,255,255,0.01);
            position:relative;
            min-width: 0;
            min-height: 0;
        }
        .chat-window::before { content:''; position:absolute; inset:0; background-image: radial-gradient(rgba(255,255,255,0.025) 1px, transparent 1px); background-size:24px 24px; pointer-events:none; }
        .chat-top-bar { padding:16px 20px; border-bottom:1px solid rgba(255,255,255,0.07); display:flex; align-items:center; gap:12px; background: rgba(255,255,255,0.02); position:relative; z-index:1; }
        .chat-messages {
            flex:1;
            overflow-y:auto;
            padding:20px;
            display:flex;
            flex-direction:column;
            gap:12px;
            position:relative;
            z-index:1;
            min-height: 0;
            overscroll-behavior: contain;
        }
        .empty-chat { color:#5A6070; text-align:center; margin:auto; }

        .bubble {
            max-width: 65%;
            padding: 12px 16px;
            border-radius: 18px;
            font-size: 14px;
            line-height: 1.5;
            position: relative;
            animation: bubbleIn 0.3s ease both;
        }

        .bubble.received { background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1); color: #e0e0e0; border-radius: 18px 18px 18px 4px; align-self:flex-start; }
        .bubble.sent { background: rgba(0,212,180,0.15); border: 1px solid rgba(0,212,180,0.2); color: #fff; border-radius: 18px 18px 4px 18px; align-self:flex-end; }
        .bubble-time { font-size: 10px; color: #5A6070; margin-top: 4px; text-align: right; display:block; }

        .chat-input-bar {
            padding:16px 20px;
            border-top:1px solid rgba(255,255,255,0.07);
            display:flex;
            gap:12px;
            align-items:center;
            background: rgba(255,255,255,0.02);
            position:relative;
            z-index:1;
            flex-shrink: 0;
        }
        .send-btn {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            background: linear-gradient(135deg,#00D4B4,#009d87);
            border: none;
            color: #0A0A0F;
            cursor: pointer;
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            flex-shrink: 0;
        }

        .send-btn:hover { transform: scale(1.08); box-shadow: 0 0 20px rgba(0,212,180,0.4); }
        .send-btn:active { transform: scale(0.96); }

        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: #0A0A0F; }
        ::-webkit-scrollbar-thumb { background: rgba(245,166,35,0.35); border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #F5A623; }
        ::selection { background: rgba(245,166,35,0.2); color: #fff; }

        @keyframes orbDrift {
            from { transform: translate(0,0) scale(1); }
            to { transform: translate(40px, 30px) scale(1.06); }
        }

        @keyframes floatDot {
            0% { transform: translate(0,0) scale(1); opacity:0.5; }
            33% { transform: translate(10px,-15px) scale(1.2); opacity:1; }
            66% { transform: translate(-8px,8px) scale(0.8); opacity:0.3; }
            100% { transform: translate(15px,-20px) scale(1); opacity:0.6; }
        }

        @keyframes cardReveal {
            from { opacity:0; transform: translateY(20px); }
            to { opacity:1; transform: translateY(0); }
        }

        @keyframes bubbleIn {
            from { opacity:0; transform: translateY(8px) scale(0.97); }
            to { opacity:1; transform: translateY(0) scale(1); }
        }

        @media (max-width: 1024px) {
            .stats-grid { grid-template-columns: repeat(2,1fr); }
            .content-grid { grid-template-columns: 1fr; }
            .chat-container { grid-template-columns: 280px 1fr; }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                opacity: 0;
                visibility: hidden;
                pointer-events: none;
                width: min(84vw, 320px);
                max-width: 320px;
                box-shadow: 0 0 40px rgba(0,0,0,0.45);
            }
            .sidebar.open {
                transform: translateX(0);
                opacity: 1;
                visibility: visible;
                pointer-events: auto;
            }
            .sidebar-overlay {
                display: block;
                opacity: 0;
                pointer-events: none;
                transition: opacity 0.25s ease;
            }
            .sidebar-overlay.show {
                opacity: 1;
                pointer-events: auto;
            }
            .hamburger { display: flex; }
            .main-content { left: 0; padding: 84px 16px 24px; }
            .stats-grid { grid-template-columns: 1fr; gap: 12px; }
            #chatPanel.active { min-height: auto; }
            .chat-container {
                grid-template-columns: 1fr;
                grid-template-rows: minmax(180px, 34dvh) minmax(360px, 1fr);
                height: min(calc(100dvh - 170px), 820px);
                min-height: 620px;
            }
            .contact-list-pane { border-right: none; border-bottom: 1px solid rgba(255,255,255,0.07); }
            .page-header { flex-direction: column; gap: 12px; margin-bottom: 24px; }
            .page-header-main { flex-direction: column; align-items: flex-start; gap: 14px; flex-basis: auto; }
            .welcome-profile-circle { width: 84px; height: 84px; min-width: 84px; }
            .welcome-title { font-size: 26px; }
            .date-badge { width: 100%; white-space: normal; }
            .glass-panel, .stat-card, .announcement-card { padding: 18px; border-radius: 18px; }
            .chat-messages { padding: 16px; }
            .chat-input-bar { padding: 14px 16px; }
        }

        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
            .stat-value { font-size: 40px; }
            .chat-container { min-height: 560px; }
            .hamburger { top: 14px; left: 14px; }
            .main-content { padding: 78px 14px 18px; }
            .chat-input-bar { flex-direction: column; }
            .send-btn { width: 100%; }
            .contact-item { padding: 12px 14px; }
        }
    </style>
</head>
<body>
    <div class="orb orb-gold"></div>
    <div class="orb orb-teal"></div>
    <div class="orb orb-blue"></div>
    <div id="particles-container"></div>

    <button class="hamburger" id="sidebarToggle" type="button" aria-label="Toggle sidebar">☰</button>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <aside class="sidebar" id="sidebar">
        <div>
            <div class="sidebar-logo">
                <span class="sidebar-school-name">SAMACE TECH LAB</span>
                <span class="sidebar-school-sub">Nursery &amp; Primary School</span>
                <span class="sidebar-role-badge">👨‍👩‍👧 Parent Portal</span>
            </div>
            <div class="nav-section-label">Workspace</div>
            <nav>
                <button class="nav-item<?php echo $initialPanel === 'dashboard' ? ' active' : ''; ?>" data-panel-target="dashboardPanel" type="button"><span class="nav-icon">🏠</span><span class="nav-label">Dashboard</span></button>
                <button class="nav-item<?php echo $initialPanel === 'chat' ? ' active' : ''; ?>" data-panel-target="chatPanel" type="button"><span class="nav-icon">💬</span><span class="nav-label">Chat</span><?php if ($totalUnreadMessages > 0): ?><span class="nav-badge"><?php echo e((string) $totalUnreadMessages); ?></span><?php endif; ?></button>
                <button class="nav-item<?php echo $initialPanel === 'announcement' ? ' active' : ''; ?>" data-panel-target="announcementPanel" type="button"><span class="nav-icon">📢</span><span class="nav-label">Announcements</span></button>
                <a class="nav-item" href="profile.php"><span class="nav-icon">👤</span><span class="nav-label">Profile</span></a>
                <a class="logout-link" href="logout.php"><span class="nav-icon">🚪</span><span class="nav-label">Logout</span></a>
            </nav>
        </div>
        <div class="sidebar-footer">
            <div class="sidebar-user">
                <?php echo render_avatar_html($parent, 'sidebar-avatar', 'Parent'); ?>
                <div>
                    <span class="sidebar-user-name"><?php echo e($displayName); ?></span>
                    <span class="sidebar-user-role">Parent Account</span>
                </div>
            </div>
        </div>
    </aside>

    <main class="main-content">
        <section class="panel<?php echo $initialPanel === 'dashboard' ? ' active' : ''; ?>" id="dashboardPanel">
            <div class="page-header">
                <div class="page-header-main">
                    <div class="welcome-profile-circle">
                        <?php if ($parentProfilePictureUrl !== null): ?>
                            <img src="<?php echo e($parentProfilePictureUrl); ?>" alt="<?php echo e($displayName); ?> profile picture">
                        <?php else: ?>
                            <?php echo e(initials_from_name($displayName, 'Parent')); ?>
                        <?php endif; ?>
                    </div>
                    <div class="page-header-copy">
                        <h1 class="welcome-title">Welcome back, <?php echo e($displayName); ?></h1>
                        <p class="welcome-subtitle">Stay connected with school communication, follow announcements, and chat with teachers from one space.</p>
                    </div>
                </div>
                <div class="date-badge" id="todayDate">Loading date...</div>
            </div>

            <?php if ($flash): ?>
                <div class="flash <?php echo e((string) ($flash['type'] ?? 'success')); ?>"><?php echo e((string) $flash['message']); ?></div>
            <?php endif; ?>

            <div class="stats-grid">
                <article class="stat-card teal"><span class="stat-icon">📢</span><span class="stat-label">Announcements</span><span class="stat-value" data-count="<?php echo e((string) $announcementCount); ?>">0</span></article>
                <article class="stat-card blue"><span class="stat-icon">👩‍🏫</span><span class="stat-label">Teacher Contacts</span><span class="stat-value" data-count="<?php echo e((string) $teacherCount); ?>">0</span></article>
                <article class="stat-card gold"><span class="stat-icon">🧒</span><span class="stat-label">Child Profile</span><span class="stat-value"><?php echo e($childName !== '' ? '1' : '0'); ?></span></article>
            </div>

            <div class="content-grid">
                <div class="glass-panel">
                    <h2 class="section-title">Latest Announcements</h2>
                    <?php if (!$announcements): ?>
                        <p class="muted">No announcements are available for your account yet.</p>
                    <?php endif; ?>
                    <?php foreach (array_slice($announcements, 0, 3) as $announcement): ?>
                        <article class="announcement-card">
                            <div style="display:flex; justify-content:space-between; gap:12px; margin-bottom:10px;">
                                <div>
                                    <h3 class="announcement-title"><?php echo e((string) $announcement['title']); ?></h3>
                                    <div class="announcement-meta-line"><div class="meta"><?php echo e((string) substr((string) $announcement['published_at'], 0, 16)); ?></div><?php if (!empty($announcement['edited_at'])): ?><span class="badge">Edited</span><?php endif; ?></div>
                                </div>
                                <span class="badge"><?php echo e(audience_label_parent((string) $announcement['audience'])); ?></span>
                            </div>
                            <p class="announcement-body"><?php echo e((string) mb_strimwidth((string) $announcement['message'], 0, 180, '...')); ?></p>
                            <?php echo render_announcement_attachment_html((string) ($announcement['attachment_path'] ?? ''), (string) ($announcement['attachment_name'] ?? '')); ?>
                        </article>
                    <?php endforeach; ?>
                </div>

                <div class="glass-panel">
                    <h2 class="section-title">Parent Profile</h2>
                    <p class="muted">Use this portal to receive school updates, respond to announcements, and keep in touch with staff.</p>
                    <div style="margin-top:16px; display:grid; gap:12px;">
                        <div class="badge">Child Information</div>
                        <div class="announcement-body"><strong style="color:#fff;">Name:</strong> <?php echo e($displayName); ?></div>
                        <div class="announcement-body"><strong style="color:#fff;">Email:</strong> <?php echo e((string) ($parent['email'] ?? '')); ?></div>
                        <div class="announcement-body"><strong style="color:#fff;">Child:</strong> <?php echo e($childName !== '' ? $childName : 'Not added yet'); ?></div>
                        <div class="announcement-body"><strong style="color:#fff;">Principal:</strong> <?php echo e($principalName); ?></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="panel<?php echo $initialPanel === 'chat' ? ' active' : ''; ?>" id="chatPanel">
            <div class="page-header">
                <div>
                    <h1 class="welcome-title">Chat</h1>
                    <p class="welcome-subtitle">Message the principal or a teacher from your parent workspace.</p>
                </div>
            </div>

            <?php if ($flash && $initialPanel === 'chat'): ?>
                <div class="flash <?php echo e((string) ($flash['type'] ?? 'success')); ?>"><?php echo e((string) $flash['message']); ?></div>
            <?php endif; ?>

            <div class="chat-container">
                <aside class="contact-list-pane">
                    <div class="contact-search"><input id="chatSearch" type="text" placeholder="Search principal or teachers by name or email..."></div>
                    <div class="contact-list" id="contactList"></div>
                </aside>
                <section class="chat-window">
                    <div class="chat-top-bar" id="chatHeader">
                        <div class="contact-avatar" style="background:#00D4B4;">?</div>
                        <div>
                            <div class="contact-name">Select a contact</div>
                            <div class="meta">Choose the principal or a teacher to start chatting.</div>
                        </div>
                    </div>
                    <div class="chat-messages" id="messagesArea">
                        <div class="empty-chat" id="emptyChat">Select a contact to start a conversation.</div>
                    </div>
                    <form class="chat-input-bar" method="post" id="chatForm">
                        <input type="hidden" name="action" value="send_parent_chat">
                        <input type="hidden" name="contact_type" id="contactTypeInput" value="principal">
                        <input type="hidden" name="contact_user_id" id="contactUserIdInput" value="<?php echo e((string) $principalId); ?>">
                        <input class="chat-input" id="messageInput" name="message" type="text" placeholder="Type a message..." autocomplete="off">
                        <button class="send-btn" type="submit">➤</button>
                    </form>
                </section>
            </div>
        </section>

        <section class="panel<?php echo $initialPanel === 'announcement' ? ' active' : ''; ?>" id="announcementPanel">
            <div class="page-header">
                <div>
                    <h1 class="welcome-title">Announcements</h1>
                    <p class="welcome-subtitle">Parents receive school announcements here and can react to them.</p>
                </div>
            </div>

            <?php if ($flash && $initialPanel === 'announcement'): ?>
                <div class="flash <?php echo e((string) ($flash['type'] ?? 'success')); ?>"><?php echo e((string) $flash['message']); ?></div>
            <?php endif; ?>

            <?php if (!$announcements): ?>
                <div class="glass-panel"><p class="muted">No announcements are available for your account yet.</p></div>
            <?php endif; ?>

            <?php foreach ($announcements as $announcement): ?>
                <?php
                    $announcementId = (int) ($announcement['id'] ?? 0);
                    $reactionCounts = $announcementReactionSummary[$announcementId] ?? ['like' => 0, 'love' => 0, 'wow' => 0, 'sad' => 0];
                    $currentReaction = (string) ($announcementUserReactions[$announcementId] ?? '');
                    $reactionTotal = announcement_reaction_total_parent($reactionCounts);
                ?>
                <article class="announcement-card">
                    <div style="display:flex; justify-content:space-between; gap:12px; margin-bottom:10px;">
                        <div>
                            <h3 class="announcement-title"><?php echo e((string) $announcement['title']); ?></h3>
                            <div class="announcement-meta-line"><div class="meta">From <?php echo e((string) $announcement['principal_name']); ?> • <?php echo e((string) substr((string) $announcement['published_at'], 0, 16)); ?></div><?php if (!empty($announcement['edited_at'])): ?><span class="badge">Edited</span><?php endif; ?></div>
                        </div>
                        <span class="badge"><?php echo e(audience_label_parent((string) $announcement['audience'])); ?></span>
                    </div>
                    <p class="announcement-body"><?php echo nl2br(e((string) $announcement['message'])); ?></p>
                    <?php echo render_announcement_attachment_html((string) ($announcement['attachment_path'] ?? ''), (string) ($announcement['attachment_name'] ?? '')); ?>
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
        </section>
    </main>

    <script>
        (function initParticles() {
            const container = document.getElementById('particles-container');
            if (!container) return;
            const count = window.innerWidth < 768 ? 25 : 55;
            const colors = [
                'rgba(245,166,35,0.45)', 'rgba(0,212,180,0.35)',
                'rgba(74,158,255,0.3)', 'rgba(255,255,255,0.2)'
            ];
            for (let index = 0; index < count; index += 1) {
                const dot = document.createElement('span');
                const size = Math.random() * 3 + 1.5;
                dot.style.cssText = `
                    position:absolute; width:${size}px; height:${size}px;
                    border-radius:50%;
                    background:${colors[Math.floor(Math.random()*colors.length)]};
                    top:${Math.random()*100}%; left:${Math.random()*100}%;
                    animation:floatDot ${Math.random()*14+8}s ease-in-out ${Math.random()*5}s infinite alternate;
                `;
                container.appendChild(dot);
            }
        })();

        function countUp(element, target, duration = 1500) {
            let start = 0;
            const step = target / (duration / 16);
            const timer = setInterval(() => {
                start += step;
                if (start >= target) {
                    element.textContent = target;
                    clearInterval(timer);
                    return;
                }
                element.textContent = Math.floor(start);
            }, 16);
        }

        document.querySelectorAll('.stat-value[data-count]').forEach((element) => {
            const observer = new IntersectionObserver((entries) => {
                if (entries[0].isIntersecting) {
                    countUp(element, parseInt(element.dataset.count, 10));
                    observer.disconnect();
                }
            });
            observer.observe(element);
        });

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
        const contacts = <?php echo json_encode($chatContacts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const conversations = Object.assign({ 'principal-main': <?php echo json_encode($principalConversation, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?> }, <?php echo json_encode($teacherConversations, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>);
        let activeContactId = <?php echo json_encode($initialContactId, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?> || (contacts.length ? contacts[0].id : null);

        function switchPanel(panelId) {
            panels.forEach((panel) => panel.classList.toggle('active', panel.id === panelId));
            navItems.forEach((item) => item.classList.toggle('active', item.dataset.panelTarget === panelId));
            if (sidebar && sidebarOverlay && window.innerWidth <= 768) {
                sidebar.classList.remove('open');
                sidebarOverlay.classList.remove('show');
            }
        }

        navItems.forEach((item) => {
            item.addEventListener('click', () => switchPanel(item.dataset.panelTarget));
        });

        if (sidebarToggle && sidebar && sidebarOverlay) {
            sidebarToggle.addEventListener('click', () => {
                sidebar.classList.toggle('open');
                sidebarOverlay.classList.toggle('show');
            });
            sidebarOverlay.addEventListener('click', () => {
                sidebar.classList.remove('open');
                sidebarOverlay.classList.remove('show');
            });
        }

        if (todayDate) {
            todayDate.textContent = new Date().toLocaleDateString('en-NG', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }

        function renderContacts(query = '') {
            if (!contactList) return;

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
                contactList.innerHTML = '<div class="empty-chat" style="padding:20px;">No contacts match your search.</div>';
                return;
            }

            filtered.forEach((contact) => {
                const row = document.createElement('button');
                row.type = 'button';
                row.className = 'contact-item' + (contact.id === activeContactId ? ' active' : '');
                row.innerHTML = `
                    ${renderAvatar(contact, 'contact-avatar')}
                    <div class="contact-info">
                        <span class="contact-name">${contact.name}</span>
                        <span class="contact-preview">${contact.role} • ${contact.meta}</span>
                        <span class="contact-preview">${contact.preview || 'No messages yet.'}</span>
                        ${contact.unread > 0 ? `<span class="contact-unread">${contact.unread}</span>` : ''}
                    </div>
                    <span class="contact-time">${contact.time || ''}</span>
                `;
                row.addEventListener('click', () => {
                    activeContactId = contact.id;
                    renderContacts(chatSearch ? chatSearch.value : '');
                    renderConversation();
                });
                contactList.appendChild(row);
            });
        }

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function renderAvatar(contact, className) {
            if (contact.avatar_image_url) {
                return `<div class="${className}" style="background:${contact.color};"><img src="${escapeHtml(contact.avatar_image_url)}" alt="${escapeHtml(contact.name)} profile picture"></div>`;
            }

            return `<div class="${className}" style="background:${contact.color};">${escapeHtml(contact.avatar)}</div>`;
        }

        function renderConversation() {
            const activeContact = contacts.find((contact) => contact.id === activeContactId);
            if (!activeContact || !chatHeader || !messagesArea || !emptyChat) return;

            chatHeader.innerHTML = `
                ${renderAvatar(activeContact, 'contact-avatar')}
                <div>
                    <div class="contact-name">${activeContact.name}</div>
                    <div class="meta">${activeContact.role} • ${activeContact.meta}</div>
                </div>
            `;

            messagesArea.innerHTML = '';
            const items = Array.isArray(conversations[activeContact.id]) ? conversations[activeContact.id] : [];
            if (!items.length) {
                emptyChat.textContent = 'No messages yet for this contact.';
                messagesArea.appendChild(emptyChat);
            } else {
                items.forEach((message) => {
                    const bubble = document.createElement('div');
                    bubble.className = 'bubble ' + message.type;
                    bubble.innerHTML = `${escapeHtml(message.text).replace(/\n/g, '<br>')}<span class="bubble-time">${escapeHtml(message.time)}</span>`;
                    messagesArea.appendChild(bubble);
                });
            }

            contactTypeInput.value = activeContact.id === 'principal-main' ? 'principal' : 'teacher';
            contactUserIdInput.value = String(activeContact.user_id || 0);
            messagesArea.scrollTop = messagesArea.scrollHeight;

            const messageInput = document.getElementById('messageInput');
            if (messageInput) {
                messageInput.disabled = !activeContact.user_id;
                messageInput.placeholder = activeContact.user_id ? 'Type a message...' : 'No contact available';
            }
        }

        if (chatSearch) {
            chatSearch.addEventListener('input', () => renderContacts(chatSearch.value));
        }

        renderContacts();
        renderConversation();
    </script>
</body>
</html>