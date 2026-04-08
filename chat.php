<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$principal = require_role('principal');
$principalName = (string) (($principal['first_name'] ?? '') ?: ($principal['full_name'] ?? 'Principal'));
$principalInitials = strtoupper(substr($principalName, 0, 1));
$principalAvatarUrl = user_profile_picture_url($principal);
$chatError = null;
$selectedContactId = (string) ($_POST['contact_id'] ?? $_GET['contact_id'] ?? '');
$pendingTeacherCount = 0;
$pendingParentCount = 0;
$pendingLeaveRequestCount = count_pending_teacher_leave_requests();

$teachers = [];
$parents = [];
$messageTemplates = [];
$dbParentThreadMap = [];
$dbTeacherThreadMap = [];

if (db_ready() && $db instanceof mysqli) {
    $teacherPalette = ['#2C7A7B', '#8B5CF6', '#2563EB', '#D97706', '#0EA5E9', '#EC4899'];
    $parentPalette = ['#16A34A', '#D97706', '#7C3AED', '#0F766E', '#DC2626', '#4F46E5'];

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

    $teacherResult = $db->query("SELECT id, full_name, first_name, surname, email, teaching_subject, teaching_class, profile_picture_path FROM users WHERE role = 'teacher' ORDER BY full_name ASC, id ASC");
    if ($teacherResult) {
        while ($row = $teacherResult->fetch_assoc()) {
            $teacherName = display_name_from_user($row, 'Teacher');
            $subject = trim((string) ($row['teaching_subject'] ?? ''));
            $className = trim((string) ($row['teaching_class'] ?? ''));
            $teacherIndex = count($teachers);
            $teachers[] = [
                'id' => 'teacher-db-' . (string) ($row['id'] ?? $teacherIndex + 1),
                'user_id' => (int) ($row['id'] ?? 0),
                'name' => $teacherName,
                'email' => (string) ($row['email'] ?? ''),
                'role' => 'Teacher',
                'meta' => $subject !== '' ? $subject : ($className !== '' ? 'Class ' . $className : 'Teacher'),
                'tag' => $subject !== '' ? $subject : ($className !== '' ? $className : 'Teacher'),
                'online' => false,
                'unread' => 0,
                'timestamp' => '',
                'preview' => 'No messages yet.',
                'avatar' => initials_from_name($teacherName, 'Teacher'),
                'avatar_image_url' => user_profile_picture_url($row),
                'color' => $teacherPalette[$teacherIndex % count($teacherPalette)],
                'replyPool' => [],
            ];
        }
        $teacherResult->free();
    }

    $parentResult = $db->query("SELECT id, full_name, first_name, surname, email, child_name, profile_picture_path FROM users WHERE role = 'parent' ORDER BY full_name ASC, id ASC");
    if ($parentResult) {
        while ($row = $parentResult->fetch_assoc()) {
            $parentName = display_name_from_user($row, 'Parent');
            $childName = trim((string) ($row['child_name'] ?? ''));
            $parentIndex = count($parents);
            $parents[] = [
                'id' => 'parent-db-' . (string) ($row['id'] ?? $parentIndex + 1),
                'user_id' => (int) ($row['id'] ?? 0),
                'name' => $parentName,
                'email' => (string) ($row['email'] ?? ''),
                'role' => 'Parent',
                'meta' => 'Child: ' . ($childName !== '' ? $childName : 'Not added yet'),
                'tag' => $childName !== '' ? $childName : 'Parent',
                'online' => false,
                'unread' => 0,
                'timestamp' => '',
                'preview' => 'No messages yet.',
                'avatar' => initials_from_name($parentName, 'Parent'),
                'avatar_image_url' => user_profile_picture_url($row),
                'color' => $parentPalette[$parentIndex % count($parentPalette)],
                'replyPool' => [],
            ];
        }
        $parentResult->free();
    }

    $teacherThreads = fetch_support_threads_for_principal((int) $principal['id'], 'teacher');
    foreach ($teacherThreads as $thread) {
        $contactId = 'teacher-db-' . (string) ($thread['requester_id'] ?? 0);
        $threadId = (int) ($thread['id'] ?? 0);
        $unreadCount = (int) ($thread['unread_count'] ?? 0);

        if ($selectedContactId !== '' && $selectedContactId === $contactId && $threadId > 0 && $unreadCount > 0) {
            mark_thread_messages_read($threadId, 'principal');
            $unreadCount = 0;
        }

        $dbTeacherThreadMap[$contactId] = [
            'thread_id' => $threadId,
            'email' => (string) ($thread['email'] ?? ''),
        ];

        foreach ($teachers as $index => $teacherContact) {
            if ($teacherContact['id'] !== $contactId) {
                continue;
            }

            $teachers[$index]['unread'] = $unreadCount;
            $teachers[$index]['timestamp'] = !empty($thread['last_message_at']) ? date('g:i A', strtotime((string) $thread['last_message_at'])) : '';
            $teachers[$index]['preview'] = (string) ($thread['last_message'] ?? 'No messages yet.');
            break;
        }

        $threadMessages = fetch_support_messages($threadId);
        $formattedMessages = [];
        $currentDateLabel = '';
        foreach ($threadMessages as $message) {
            $dateLabel = date('M j, Y', strtotime((string) ($message['created_at'] ?? 'now')));
            if ($dateLabel !== $currentDateLabel) {
                $formattedMessages[] = ['type' => 'date', 'label' => $dateLabel];
                $currentDateLabel = $dateLabel;
            }

            $formattedMessages[] = [
                'type' => ((string) ($message['sender_role'] ?? '')) === 'principal' ? 'sent' : 'received',
                'text' => (string) ($message['message'] ?? ''),
                'time' => date('g:i A', strtotime((string) ($message['created_at'] ?? 'now'))),
                'status' => ((string) ($message['sender_role'] ?? '')) === 'principal' ? (((int) ($message['is_read'] ?? 0) === 1) ? 'read' : 'sent') : null,
            ];
        }

        if ($formattedMessages) {
            $messageTemplates[$contactId] = $formattedMessages;
        }
    }

    $parentThreads = fetch_support_threads_for_principal((int) $principal['id'], 'parent');

    foreach ($parentThreads as $thread) {
        $contactId = 'parent-db-' . (string) ($thread['requester_id'] ?? count($dbParents) + 1);
        $threadId = (int) ($thread['id'] ?? 0);
        $unreadCount = (int) ($thread['unread_count'] ?? 0);

        if ($selectedContactId !== '' && $selectedContactId === $contactId && $threadId > 0 && $unreadCount > 0) {
            mark_thread_messages_read($threadId, 'principal');
            $unreadCount = 0;
        }

        $dbParentThreadMap[$contactId] = [
            'thread_id' => $threadId,
            'email' => (string) ($thread['email'] ?? ''),
        ];

        foreach ($parents as $index => $parentContact) {
            if ($parentContact['id'] !== $contactId) {
                continue;
            }

            $parents[$index]['unread'] = $unreadCount;
            $parents[$index]['timestamp'] = !empty($thread['last_message_at']) ? date('g:i A', strtotime((string) $thread['last_message_at'])) : '';
            $parents[$index]['preview'] = (string) ($thread['last_message'] ?? 'No messages yet.');
            break;
        }

        $threadMessages = fetch_support_messages($threadId);
        $formattedMessages = [];
        $currentDateLabel = '';
        foreach ($threadMessages as $message) {
            $dateLabel = date('M j, Y', strtotime((string) ($message['created_at'] ?? 'now')));
            if ($dateLabel !== $currentDateLabel) {
                $formattedMessages[] = ['type' => 'date', 'label' => $dateLabel];
                $currentDateLabel = $dateLabel;
            }

            $formattedMessages[] = [
                'type' => ((string) ($message['sender_role'] ?? '')) === 'principal' ? 'sent' : 'received',
                'text' => (string) ($message['message'] ?? ''),
                'time' => date('g:i A', strtotime((string) ($message['created_at'] ?? 'now'))),
                'status' => ((string) ($message['sender_role'] ?? '')) === 'principal' ? (((int) ($message['is_read'] ?? 0) === 1) ? 'read' : 'sent') : null,
            ];
        }

        if ($formattedMessages) {
            $messageTemplates[$contactId] = $formattedMessages;
        }
    }

    if (is_post() && (string) ($_POST['action'] ?? '') === 'mark_principal_chat_read') {
        $targetContactId = (string) ($_POST['contact_id'] ?? '');
        $threadId = null;

        if ($targetContactId !== '') {
            if (isset($dbTeacherThreadMap[$targetContactId])) {
                $threadId = (int) $dbTeacherThreadMap[$targetContactId]['thread_id'];
            } elseif (isset($dbParentThreadMap[$targetContactId])) {
                $threadId = (int) $dbParentThreadMap[$targetContactId]['thread_id'];
            }
        }

        header('Content-Type: application/json');

        if ($threadId !== null && $threadId > 0) {
            mark_thread_messages_read($threadId, 'principal');
            echo json_encode(['ok' => true]);
        } else {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Invalid chat thread.']);
        }

        exit;
    }

    if (is_post() && (string) ($_POST['action'] ?? '') === 'send_principal_chat') {
        $targetContactId = (string) ($_POST['contact_id'] ?? '');
        $messageBody = (string) ($_POST['message'] ?? '');
        $threadId = null;

        if ($targetContactId !== '') {
            if (isset($dbTeacherThreadMap[$targetContactId])) {
                $threadId = (int) $dbTeacherThreadMap[$targetContactId]['thread_id'];
            } elseif (isset($dbParentThreadMap[$targetContactId])) {
                $threadId = (int) $dbParentThreadMap[$targetContactId]['thread_id'];
            } elseif (str_starts_with($targetContactId, 'teacher-db-')) {
                $teacherUserId = (int) substr($targetContactId, 11);
                if ($teacherUserId > 0) {
                    $threadId = find_or_create_support_thread((int) $principal['id'], $teacherUserId, 'teacher', 'Teacher Support Chat');
                }
            } elseif (str_starts_with($targetContactId, 'parent-db-')) {
                $parentUserId = (int) substr($targetContactId, 10);
                if ($parentUserId > 0) {
                    $threadId = find_or_create_support_thread((int) $principal['id'], $parentUserId, 'parent', 'Parent Support Chat');
                }
            }
        }

        if ($threadId !== null && send_support_message($threadId, (int) $principal['id'], 'principal', $messageBody, $chatError)) {
            redirect('chat.php?contact_id=' . rawurlencode($targetContactId));
        }

        if ($chatError === null) {
            $chatError = 'Please choose a valid teacher or parent contact.';
        }
    }
}

$contacts = array_merge($teachers, $parents);
$unreadTotal = 0;
foreach ($contacts as $contact) {
    $unreadTotal += (int) $contact['unread'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="dark">
    <title>Principal Chats | SAMACE TECH LAB</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@400;500;700;800&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <style>
        :root {
            --sidebar-bg: #0F2444;
            --sidebar-bg-strong: #0B1B34;
            --contact-bg: #FFFFFF;
            --contact-hover: #F0F4F8;
            --chat-bg: #EEF2F7;
            --chat-pattern: rgba(15, 36, 68, 0.035);
            --sent-bg: #1A3C6E;
            --received-bg: #FFFFFF;
            --received-border: #DEE4ED;
            --accent: #F5A623;
            --accent-soft: rgba(245, 166, 35, 0.16);
            --text: #223047;
            --text-soft: #6C7A90;
            --line: #D7E0EA;
            --danger: #E11D48;
            --success: #16A34A;
            --shadow: 0 22px 48px rgba(15, 36, 68, 0.12);
            --radius-lg: 24px;
            --radius-md: 18px;
            --radius-sm: 14px;
            --sidebar-width: 250px;
            --contacts-width: 320px;
            --transition: 220ms ease;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html,
        body {
            height: 100%;
        }

        body {
            overflow: hidden;
            font-family: 'DM Sans', sans-serif;
            background: var(--chat-bg);
            color: var(--text);
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        button,
        input {
            font: inherit;
        }

        .app-shell {
            display: flex;
            height: 100vh;
            width: 100%;
            overflow: hidden;
        }

        .mobile-toggle {
            position: fixed;
            top: 18px;
            left: 18px;
            z-index: 1500;
            width: 48px;
            height: 48px;
            border: 0;
            border-radius: 14px;
            background: var(--sidebar-bg);
            color: #fff;
            box-shadow: var(--shadow);
            display: none;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .sidebar-overlay {
            position: fixed;
            inset: 0;
            background: rgba(6, 15, 29, 0.48);
            opacity: 0;
            pointer-events: none;
            transition: opacity var(--transition);
            z-index: 1400;
        }

        .sidebar {
            width: var(--sidebar-width);
            min-width: var(--sidebar-width);
            height: 100vh;
            background: linear-gradient(180deg, var(--sidebar-bg) 0%, var(--sidebar-bg-strong) 100%);
            color: rgba(255, 255, 255, 0.88);
            padding: 24px 18px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1450;
            overflow-y: auto;
            transition: transform var(--transition);
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 8px 8px 24px;
        }

        .sidebar-brand-mark {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.08);
            display: grid;
            place-items: center;
            padding: 8px;
        }

        .sidebar-brand-mark img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .sidebar-brand-text small {
            display: block;
            color: rgba(245, 166, 35, 0.88);
            font-size: 0.76rem;
            text-transform: uppercase;
            letter-spacing: 0.16em;
            font-weight: 800;
            margin-bottom: 4px;
        }

        .sidebar-brand-text strong {
            display: block;
            color: var(--accent);
            font-family: 'Fraunces', serif;
            line-height: 1.15;
            font-size: 1.18rem;
        }

        .nav-list {
            display: grid;
            gap: 8px;
        }

        .nav-item {
            position: relative;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            border-radius: 16px;
            color: inherit;
            transition: background var(--transition), color var(--transition);
        }

        .nav-item::before {
            content: '';
            position: absolute;
            left: -18px;
            top: 10px;
            bottom: 10px;
            width: 4px;
            border-radius: 999px;
            background: transparent;
            transition: background var(--transition);
        }

        .nav-item:hover,
        .nav-item.active {
            background: rgba(255, 255, 255, 0.08);
            color: #FFFFFF;
        }

        .nav-item.active::before {
            background: var(--accent);
        }

        .nav-item.logout:hover {
            background: rgba(225, 29, 72, 0.2);
        }

        .sidebar-footer {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 18px 8px 6px;
            margin-top: 18px;
            border-top: 1px solid rgba(255, 255, 255, 0.12);
        }

        .principal-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            overflow: hidden;
            display: grid;
            place-items: center;
            background: rgba(245, 166, 35, 0.16);
            color: var(--accent);
            font-weight: 800;
        }
        .principal-avatar img, .avatar img, .message-avatar img { width: 100%; height: 100%; object-fit: cover; display: block; border-radius: inherit; }

        .main-area {
            position: fixed;
            top: 0;
            left: var(--sidebar-width);
            right: 0;
            bottom: 0;
            display: grid;
            grid-template-columns: var(--contacts-width) minmax(0, 1fr);
            overflow: hidden;
        }

        body.principal-chat-page .main-area.ui-motion-stage,
        body.principal-chat-page .contacts-panel.ui-motion-stage,
        body.principal-chat-page .chat-panel.ui-motion-stage,
        body.principal-chat-page .contacts-header.ui-motion-stage,
        body.principal-chat-page .contacts-search.ui-motion-stage,
        body.principal-chat-page .chat-topbar.ui-motion-stage,
        body.principal-chat-page .chat-search-bar.ui-motion-stage,
        body.principal-chat-page .chat-input-bar.ui-motion-stage,
        body.principal-chat-page .contacts-header.ui-float,
        body.principal-chat-page .contacts-search.ui-float,
        body.principal-chat-page .chat-topbar.ui-float,
        body.principal-chat-page .chat-search-bar.ui-float,
        body.principal-chat-page .chat-input-bar.ui-float,
        body.principal-chat-page .contacts-panel.ui-float,
        body.principal-chat-page .chat-panel.ui-float {
            animation: none !important;
            transform: none !important;
        }

        body.principal-chat-page .main-area.ui-reveal,
        body.principal-chat-page .contacts-panel.ui-reveal,
        body.principal-chat-page .chat-panel.ui-reveal,
        body.principal-chat-page .contacts-header.ui-reveal,
        body.principal-chat-page .contacts-search.ui-reveal,
        body.principal-chat-page .chat-topbar.ui-reveal,
        body.principal-chat-page .chat-search-bar.ui-reveal,
        body.principal-chat-page .chat-input-bar.ui-reveal {
            opacity: 1 !important;
            transform: none !important;
            transition: none !important;
        }

        .contacts-panel {
            height: 100vh;
            background: var(--contact-bg);
            border-right: 1px solid var(--line);
            display: grid;
            grid-template-rows: auto auto minmax(0, 1fr);
            overflow: hidden;
        }

        .contacts-header,
        .contacts-search,
        .chat-topbar,
        .chat-search-bar,
        .chat-input-bar {
            flex-shrink: 0;
        }

        .contacts-header {
            position: relative;
            z-index: 3;
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(215, 224, 234, 0.85);
            padding: 28px 22px 18px;
        }

        .contacts-title-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 16px;
        }

        .contacts-title h1 {
            font-family: 'Fraunces', serif;
            font-size: 1.75rem;
            color: #142843;
        }

        .contacts-title p {
            color: var(--text-soft);
            font-size: 0.92rem;
            margin-top: 4px;
        }

        .badge-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 30px;
            padding: 0 12px;
            border-radius: 999px;
            background: var(--accent-soft);
            color: #9A5A00;
            font-size: 0.8rem;
            font-weight: 800;
            white-space: nowrap;
        }

        .new-chat-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 0;
            border-radius: 999px;
            padding: 10px 14px;
            background: #152C50;
            color: #FFFFFF;
            cursor: pointer;
            box-shadow: 0 10px 20px rgba(15, 36, 68, 0.15);
        }

        .contacts-search {
            position: relative;
            z-index: 2;
            background: rgba(255, 255, 255, 0.96);
            padding: 0 22px 18px;
            border-bottom: 1px solid rgba(215, 224, 234, 0.85);
        }

        .search-wrap {
            position: relative;
        }

        .search-wrap span {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-soft);
        }

        .search-input {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 12px 14px 12px 40px;
            background: #F8FBFE;
            outline: none;
            transition: border-color var(--transition), box-shadow var(--transition);
        }

        .search-input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 4px rgba(245, 166, 35, 0.12);
        }

        .contacts-scroll {
            flex: 1;
            overflow: auto;
            min-height: 0;
            padding: 12px 0 18px;
        }

        .contact-section {
            padding: 0 14px 12px;
        }

        .section-toggle {
            width: 100%;
            border: 0;
            background: transparent;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 10px 10px;
            color: var(--text-soft);
            font-size: 0.82rem;
            font-weight: 800;
            letter-spacing: 0.12em;
            cursor: pointer;
        }

        .section-toggle .chevron {
            transition: transform var(--transition);
        }

        .contact-section.collapsed .chevron {
            transform: rotate(-90deg);
        }

        .section-body {
            overflow: hidden;
            transition: max-height 260ms ease, opacity 220ms ease;
        }

        .contact-section.collapsed .section-body {
            max-height: 0 !important;
            opacity: 0;
        }

        .contact-list {
            display: grid;
            gap: 6px;
        }

        .contact-row {
            position: relative;
            display: grid;
            grid-template-columns: auto 1fr auto;
            align-items: start;
            gap: 12px;
            width: 100%;
            border: 0;
            background: transparent;
            padding: 14px 12px;
            border-radius: 18px;
            text-align: left;
            cursor: pointer;
            transition: background var(--transition), transform var(--transition), box-shadow var(--transition);
        }

        .contact-row::before {
            content: '';
            position: absolute;
            left: 0;
            top: 10px;
            bottom: 10px;
            width: 4px;
            border-radius: 999px;
            background: transparent;
            transition: background var(--transition);
        }

        .contact-row:hover {
            background: var(--contact-hover);
        }

        .contact-row.active {
            background: rgba(245, 166, 35, 0.11);
            box-shadow: inset 0 0 0 1px rgba(245, 166, 35, 0.18);
        }

        .contact-row.active::before {
            background: var(--accent);
        }

        .avatar {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            overflow: hidden;
            display: grid;
            place-items: center;
            color: #FFFFFF;
            font-weight: 800;
            font-size: 0.95rem;
        }

        .contact-main {
            min-width: 0;
        }

        .contact-head {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 4px;
        }

        .contact-name {
            font-weight: 800;
            font-size: 0.92rem;
            color: #18263E;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .contact-email {
            font-size: 0.72rem;
            color: var(--text-soft);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 6px;
        }

        .contact-meta {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 8px;
            border-radius: 999px;
            background: #ECF7F5;
            color: #166365;
            font-size: 0.7rem;
            font-weight: 700;
        }

        .contact-meta.parent {
            background: #ECFDF3;
            color: #1E7A43;
        }

        .contact-preview {
            color: var(--text-soft);
            font-size: 0.75rem;
            font-style: italic;
            margin-top: 8px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .contact-side {
            display: grid;
            gap: 8px;
            justify-items: end;
            min-width: 44px;
        }

        .timestamp {
            font-size: 0.7rem;
            color: var(--text-soft);
        }

        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #111827;
            box-shadow: 0 0 0 3px rgba(17, 24, 39, 0.08);
        }

        .status-dot.online {
            background: var(--success);
            box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.14);
        }

        .unread-badge {
            min-width: 22px;
            height: 22px;
            padding: 0 7px;
            border-radius: 999px;
            display: inline-grid;
            place-items: center;
            background: var(--danger);
            color: #FFFFFF;
            font-size: 0.72rem;
            font-weight: 800;
        }

        .empty-search {
            display: none;
            padding: 36px 24px 20px;
            text-align: center;
            color: var(--text-soft);
        }

        .empty-search.visible {
            display: block;
        }

        .empty-search-emoji {
            font-size: 2.8rem;
            margin-bottom: 10px;
        }

        .chat-panel {
            position: relative;
            height: 100vh;
            display: flex;
            flex-direction: column;
            background:
                repeating-linear-gradient(135deg, transparent 0, transparent 18px, var(--chat-pattern) 18px, var(--chat-pattern) 19px),
                var(--chat-bg);
            overflow: hidden;
        }

        .chat-empty,
        .chat-content {
            height: 100%;
        }

        .chat-empty {
            display: grid;
            place-items: center;
            padding: 32px;
        }

        .chat-empty-card {
            text-align: center;
            max-width: 420px;
            color: #516174;
        }

        .chat-empty-emoji {
            font-size: 4.2rem;
            margin-bottom: 14px;
        }

        .chat-empty h2 {
            font-family: 'Fraunces', serif;
            font-size: 2rem;
            color: #1A2D46;
            margin-bottom: 10px;
        }

        .chat-content {
            display: none;
            flex-direction: column;
            min-height: 0;
        }

        .chat-content.active {
            display: flex;
        }

        .chat-topbar {
            position: sticky;
            top: 0;
            z-index: 6;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 18px 24px;
            border-bottom: 1px solid rgba(215, 224, 234, 0.88);
            background: rgba(246, 249, 252, 0.95);
            backdrop-filter: blur(12px);
        }

        .chat-contact {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
        }

        .back-chat {
            display: none;
            border: 0;
            background: #FFFFFF;
            width: 40px;
            height: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 20px rgba(15, 36, 68, 0.08);
            cursor: pointer;
        }

        .chat-contact-text {
            min-width: 0;
        }

        .chat-contact-text h2 {
            font-size: 1rem;
            color: #17263F;
            margin-bottom: 3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .chat-role {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-soft);
            font-size: 0.82rem;
            margin-bottom: 2px;
        }

        .chat-email {
            color: var(--text-soft);
            font-size: 0.76rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .chat-email a:hover {
            color: #173B72;
        }

        .chat-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .icon-btn {
            width: 42px;
            height: 42px;
            border: 0;
            border-radius: 14px;
            background: #FFFFFF;
            color: #1D2F4F;
            box-shadow: 0 10px 20px rgba(15, 36, 68, 0.08);
            cursor: pointer;
        }

        .menu-wrap {
            position: relative;
        }

        .menu-dropdown {
            position: absolute;
            right: 0;
            top: calc(100% + 8px);
            min-width: 180px;
            background: #FFFFFF;
            border: 1px solid rgba(215, 224, 234, 0.9);
            border-radius: 16px;
            box-shadow: 0 18px 30px rgba(15, 36, 68, 0.14);
            padding: 8px;
            display: none;
        }

        .menu-dropdown.open {
            display: block;
        }

        .menu-action {
            width: 100%;
            border: 0;
            background: transparent;
            padding: 11px 12px;
            border-radius: 12px;
            text-align: left;
            color: #23334C;
            cursor: pointer;
        }

        .menu-action:hover {
            background: #F5F8FC;
        }

        .chat-search-bar {
            display: none;
            align-items: center;
            gap: 12px;
            padding: 12px 24px;
            background: rgba(255, 255, 255, 0.88);
            border-bottom: 1px solid rgba(215, 224, 234, 0.88);
        }

        .chat-search-bar.open {
            display: flex;
        }

        .chat-search-bar .search-input {
            background: #FFFFFF;
        }

        .chat-search-count {
            color: var(--text-soft);
            font-size: 0.8rem;
            white-space: nowrap;
        }

        .close-search {
            border: 0;
            background: transparent;
            color: var(--text-soft);
            cursor: pointer;
            font-size: 1rem;
        }

        .message-stream {
            flex: 1;
            min-height: 0;
            overflow: auto;
            padding: 24px 24px 18px;
            display: grid;
            gap: 14px;
            align-content: start;
        }

        .date-chip {
            justify-self: center;
            padding: 6px 14px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.86);
            border: 1px solid rgba(215, 224, 234, 0.9);
            color: var(--text-soft);
            font-size: 0.74rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .message-row {
            display: flex;
            gap: 10px;
            align-items: flex-end;
            transition: opacity 180ms ease;
        }

        .message-row.sent {
            justify-content: flex-end;
        }

        .message-row.dimmed {
            opacity: 0.2;
        }

        .message-row.hidden {
            display: none;
        }

        .message-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            overflow: hidden;
            display: grid;
            place-items: center;
            color: #FFFFFF;
            font-size: 0.75rem;
            font-weight: 800;
            flex-shrink: 0;
        }

        .message-stack {
            max-width: min(72%, 580px);
        }

        .sender-label {
            display: block;
            margin: 0 0 5px 6px;
            font-size: 0.74rem;
            font-weight: 700;
        }

        .sender-label.you {
            color: #183566;
            text-align: right;
            margin-right: 8px;
        }

        .sender-label.them {
            color: #4F647E;
        }

        .bubble {
            position: relative;
            padding: 14px 16px;
            line-height: 1.55;
            box-shadow: 0 10px 24px rgba(15, 36, 68, 0.08);
            word-wrap: break-word;
        }

        .message-row.sent .bubble {
            background: var(--sent-bg);
            color: #FFFFFF;
            border-radius: 18px 18px 4px 18px;
        }

        .message-row.received .bubble {
            background: var(--received-bg);
            color: #2D3748;
            border: 1px solid var(--received-border);
            border-radius: 18px 18px 18px 4px;
        }

        .message-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 5px;
            font-size: 0.68rem;
            color: var(--text-soft);
        }

        .message-row.sent .message-meta {
            justify-content: flex-end;
        }

        .read-receipt {
            font-weight: 800;
            color: #94A3B8;
            transition: color 160ms ease;
        }

        .read-receipt.read {
            color: #3B82F6;
        }

        .typing-indicator {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 12px 14px;
            background: #FFFFFF;
            border: 1px solid var(--received-border);
            border-radius: 18px 18px 18px 4px;
            box-shadow: 0 10px 24px rgba(15, 36, 68, 0.08);
        }

        .typing-indicator span {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #9AA6B6;
            animation: bounce 1.1s infinite ease-in-out;
        }

        .typing-indicator span:nth-child(2) {
            animation-delay: 0.15s;
        }

        .typing-indicator span:nth-child(3) {
            animation-delay: 0.3s;
        }

        @keyframes bounce {
            0%, 80%, 100% {
                transform: translateY(0);
                opacity: 0.5;
            }
            40% {
                transform: translateY(-5px);
                opacity: 1;
            }
        }

        .chat-input-bar {
            position: sticky;
            bottom: 0;
            z-index: 6;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 18px 24px 20px;
            border-top: 1px solid rgba(215, 224, 234, 0.9);
            background: rgba(248, 251, 254, 0.96);
            backdrop-filter: blur(12px);
        }

        .input-icon {
            width: 44px;
            height: 44px;
            border: 0;
            border-radius: 14px;
            background: #FFFFFF;
            color: #22334E;
            cursor: pointer;
            box-shadow: 0 8px 16px rgba(15, 36, 68, 0.08);
        }

        .message-input {
            flex: 1;
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 13px 16px;
            background: #FFFFFF;
            outline: none;
        }

        .message-input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 4px rgba(245, 166, 35, 0.12);
        }

        .send-btn {
            width: 50px;
            height: 50px;
            border: 0;
            border-radius: 16px;
            background: var(--accent);
            color: #112846;
            font-size: 1.05rem;
            font-weight: 900;
            cursor: pointer;
            box-shadow: 0 14px 24px rgba(245, 166, 35, 0.3);
        }

        mark {
            background: rgba(245, 166, 35, 0.38);
            color: inherit;
            padding: 0 2px;
            border-radius: 4px;
        }

        .toast {
            position: fixed;
            right: 22px;
            bottom: 22px;
            background: #132847;
            color: #FFFFFF;
            padding: 14px 18px;
            border-radius: 14px;
            box-shadow: 0 20px 30px rgba(15, 36, 68, 0.2);
            transform: translateY(24px);
            opacity: 0;
            pointer-events: none;
            transition: transform 180ms ease, opacity 180ms ease;
            z-index: 1600;
        }

        .toast.visible {
            transform: translateY(0);
            opacity: 1;
        }

        .contacts-panel,
        .chat-panel,
        .contact-row,
        .chat-content,
        .chat-empty {
            animation: fadeIn 220ms ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 1024px) {
            .main-area {
                grid-template-columns: minmax(290px, 320px) minmax(0, 1fr);
            }

            .chat-topbar,
            .chat-search-bar,
            .chat-input-bar,
            .message-stream {
                padding-left: 20px;
                padding-right: 20px;
            }
        }

        @media (max-width: 768px) {
            .mobile-toggle {
                display: inline-flex;
            }

            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar-overlay.open {
                opacity: 1;
                pointer-events: auto;
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .main-area {
                left: 0;
                display: flex;
                width: 200vw;
                transform: translateX(0);
                transition: transform 240ms ease;
            }

            .main-area.chat-open {
                transform: translateX(-100vw);
            }

            .contacts-panel,
            .chat-panel {
                width: 100vw;
                min-width: 100vw;
            }

            .contacts-header {
                padding-top: 84px;
            }

            .back-chat {
                display: inline-grid;
                place-items: center;
            }

            .message-stack {
                max-width: 82%;
            }
        }

        @media (max-width: 480px) {
            .contacts-header,
            .contacts-search,
            .chat-topbar,
            .chat-search-bar,
            .chat-input-bar,
            .message-stream {
                padding-left: 16px;
                padding-right: 16px;
            }

            .contacts-title h1 {
                font-size: 1.5rem;
            }

            .chat-empty h2 {
                font-size: 1.6rem;
            }

            .message-stack {
                max-width: 88%;
            }

            .chat-input-bar {
                gap: 8px;
            }

            .input-icon,
            .send-btn {
                width: 42px;
                height: 42px;
            }
        }

        body {
            background: #0A0A0F;
            color: #FFFFFF;
            position: relative;
        }

        .orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(140px);
            z-index: 0;
            pointer-events: none;
            animation: orbDrift 20s ease-in-out infinite alternate;
        }

        .orb-gold { width: 700px; height: 700px; background: radial-gradient(circle, rgba(245,166,35,0.11), transparent 70%); top: -200px; left: 50px; }
        .orb-teal { width: 500px; height: 500px; background: radial-gradient(circle, rgba(0,212,180,0.08), transparent 70%); bottom: -100px; right: 100px; animation-direction: alternate-reverse; animation-duration: 26s; }
        .orb-blue { width: 400px; height: 400px; background: radial-gradient(circle, rgba(74,158,255,0.07), transparent 70%); top: 40%; right: -50px; animation-duration: 32s; }
        @keyframes orbDrift { from { transform: translate(0, 0) scale(1); } to { transform: translate(40px, 30px) scale(1.06); } }
        #particles-container { position: fixed; inset: 0; z-index: 0; pointer-events: none; overflow: hidden; }
        @keyframes floatDot {
            0% { transform: translate(0,0) scale(1); opacity: 0.5; }
            33% { transform: translate(10px,-15px) scale(1.2); opacity: 1; }
            66% { transform: translate(-8px,8px) scale(0.8); opacity: 0.3; }
            100% { transform: translate(15px,-20px) scale(1); opacity: 0.6; }
        }

        .app-shell,
        .toast { position: relative; z-index: 1; }
        .sidebar {
            background: rgba(13,13,24,0.95);
            border-right: 1px solid rgba(255,255,255,0.06);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
        }
        .sidebar-brand-text small { color: #5A6070; letter-spacing: 0.18em; font-family: 'DM Sans', sans-serif; }
        .sidebar-brand-text strong { color: #F5A623; font-family: 'Bebas Neue', cursive; font-size: 1.3rem; letter-spacing: 0.08em; }
        .sidebar-role-badge { display: inline-flex; align-items: center; gap: 5px; background: rgba(245,166,35,0.1); border: 1px solid rgba(245,166,35,0.2); color: #F5A623; font-size: 10px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; padding: 4px 10px; border-radius: 50px; margin: 0 8px 20px; }
        .nav-item { color: #A0A8B8; }
        .nav-item:hover, .nav-item.active { background: rgba(255,255,255,0.05); color: #FFFFFF; }
        .nav-item.active::before { background: #F5A623; }
        .sidebar-footer { border-top: 1px solid rgba(255,255,255,0.06); }
        .principal-avatar { background: linear-gradient(135deg, #F5A623, rgba(245,166,35,0.4)); color: #0A0A0F; box-shadow: 0 0 15px rgba(245,166,35,0.3); }
        .main-area {
            position: fixed;
            top: 0;
            left: var(--sidebar-width);
            right: 0;
            bottom: 0;
            z-index: 1;
        }
        .contacts-panel,
        .chat-panel {
            background: rgba(255,255,255,0.03);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
        }
        .contacts-panel { border-right: 1px solid rgba(255,255,255,0.06); }
        .contacts-header,
        .contacts-search,
        .chat-topbar,
        .chat-search-bar,
        .chat-input-bar {
            background: rgba(10,10,15,0.78);
            border-color: rgba(255,255,255,0.08);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
        }
        .contacts-title h1,
        .chat-empty h2,
        .chat-contact-text h2 { color: #FFFFFF; font-family: 'Playfair Display', serif; }
        .contacts-title p,
        .chat-empty-card,
        .chat-role,
        .chat-email,
        .chat-search-count,
        .timestamp,
        .contact-email,
        .contact-preview,
        .contact-section,
        .section-toggle,
        .message-meta,
        .sender-label.them,
        .sender-label.you,
        .empty-search,
        .date-chip { color: #A0A8B8; }
        .badge-pill { background: rgba(245,166,35,0.14); color: #F5A623; }
        .new-chat-btn,
        .send-btn { background: linear-gradient(135deg, #F5A623, #d99212); color: #0A0A0F; box-shadow: 0 14px 24px rgba(245,166,35,0.28); }
        .search-input,
        .message-input,
        .icon-btn,
        .input-icon,
        .back-chat,
        .menu-dropdown,
        .typing-indicator,
        .message-row.received .bubble { background: rgba(255,255,255,0.06); color: #FFFFFF; border-color: rgba(255,255,255,0.1); }
        .search-input:focus,
        .message-input:focus { border-color: #F5A623; box-shadow: 0 0 0 4px rgba(245,166,35,0.12); }
        .contact-row:hover { background: rgba(255,255,255,0.05); }
        .contact-row.active { background: rgba(245,166,35,0.08); box-shadow: inset 0 0 0 1px rgba(245,166,35,0.18); }
        .contact-name { color: #FFFFFF; }
        .contact-meta { background: rgba(74,158,255,0.12); color: #8bc1ff; }
        .contact-meta.parent { background: rgba(0,212,180,0.12); color: #7ef2df; }
        .status-dot { background: rgba(255,255,255,0.2); box-shadow: 0 0 0 3px rgba(255,255,255,0.04); }
        .chat-panel { background: linear-gradient(180deg, rgba(14,14,23,0.9) 0%, rgba(10,10,15,0.94) 100%); }
        .message-row.sent .bubble { background: linear-gradient(135deg, #F5A623, #cf8d14); color: #0A0A0F; }
        .message-row.received .bubble { color: #FFFFFF; }
        .menu-action { color: #FFFFFF; }
        .menu-action:hover { background: rgba(255,255,255,0.06); }
        .toast { background: rgba(15,15,26,0.95); border: 1px solid rgba(255,255,255,0.1); }
    </style>
    <?php require_once __DIR__ . '/theme-shared.php'; ?>
    <?php require_once __DIR__ . '/principal-shared.php'; ?>
</head>
<body class="principal-chat-page principal-page">
    <div class="orb orb-gold"></div>
    <div class="orb orb-teal"></div>
    <div class="orb orb-blue"></div>
    <div id="particles-container"></div>
    <button class="mobile-toggle" id="mobileToggle" type="button" aria-label="Toggle sidebar">☰</button>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="app-shell">
        <?php
            $principalSidebarActive = 'chats';
            $chatUnreadCount = $unreadTotal;
            require __DIR__ . '/principal-sidebar.php';
        ?>

        <div class="main-area" id="mainArea">
            <section class="contacts-panel" aria-label="Contacts">
                <div class="contacts-header">
                    <div class="contacts-title-row">
                        <div class="contacts-title">
                            <h1>Messages</h1>
                            <p>Search teachers and parents instantly.</p>
                        </div>
                        <div style="display: grid; gap: 10px; justify-items: end;">
                            <span class="badge-pill" id="unreadBadge"><?php echo e((string) $unreadTotal); ?> unread</span>
                            <button class="new-chat-btn" id="newChatBtn" type="button">＋ New Chat</button>
                        </div>
                    </div>
                </div>

                <div class="contacts-search">
                    <label class="search-wrap" for="contactSearch">
                        <span>🔍</span>
                        <input class="search-input" id="contactSearch" type="text" placeholder="Search by name or email…" autocomplete="off">
                    </label>
                </div>

                <div class="contacts-scroll">
                    <div class="contact-section" data-section="teachers">
                        <button class="section-toggle" type="button" data-toggle-section="teachers">
                            <span>👩‍🏫 TEACHERS (<?php echo e((string) count($teachers)); ?>)</span>
                            <span class="chevron">▾</span>
                        </button>
                        <div class="section-body" id="teachersSectionBody">
                            <div class="contact-list" id="teachersList"></div>
                        </div>
                    </div>

                    <div class="contact-section" data-section="parents">
                        <button class="section-toggle" type="button" data-toggle-section="parents">
                            <span>👨‍👩‍👧 PARENTS (<?php echo e((string) count($parents)); ?>)</span>
                            <span class="chevron">▾</span>
                        </button>
                        <div class="section-body" id="parentsSectionBody">
                            <div class="contact-list" id="parentsList"></div>
                        </div>
                    </div>

                    <div class="empty-search" id="emptySearchState">
                        <div class="empty-search-emoji">🔎</div>
                        <strong>No contacts match your search</strong>
                        <p style="margin-top: 8px;">Try another name or email address.</p>
                    </div>
                </div>
            </section>

            <section class="chat-panel" aria-label="Chat window">
                <div class="chat-empty" id="chatEmptyState">
                    <div class="chat-empty-card">
                        <div class="chat-empty-emoji">💬</div>
                        <h2>Select a teacher or parent to start a conversation</h2>
                        <p>Use the search bar to find anyone by name or email.</p>
                    </div>
                </div>

                <div class="chat-content" id="chatContent">
                    <div class="chat-topbar">
                        <div class="chat-contact">
                            <button class="back-chat" id="backChatBtn" type="button" aria-label="Back to contacts">←</button>
                            <div class="avatar" id="chatAvatar"></div>
                            <div class="chat-contact-text">
                                <h2 id="chatName"></h2>
                                <div class="chat-role" id="chatRole"></div>
                                <div class="chat-email"><a href="#" id="chatEmail"></a></div>
                            </div>
                        </div>

                        <div class="chat-actions">
                            <button class="icon-btn" id="toggleMessageSearch" type="button" aria-label="Search messages">🔍</button>
                            <div class="menu-wrap">
                                <button class="icon-btn" id="menuToggle" type="button" aria-label="Conversation actions">⋮</button>
                                <div class="menu-dropdown" id="menuDropdown">
                                    <button class="menu-action" type="button" data-menu-action="View Profile">View Profile</button>
                                    <button class="menu-action" type="button" data-menu-action="Clear Chat">Clear Chat</button>
                                    <button class="menu-action" type="button" data-menu-action="Mark as Unread">Mark as Unread</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="chat-search-bar" id="chatSearchBar">
                        <label class="search-wrap" style="flex: 1;" for="messageSearch">
                            <span>🔍</span>
                            <input class="search-input" id="messageSearch" type="text" placeholder="Search in conversation…" autocomplete="off">
                        </label>
                        <span class="chat-search-count" id="messageSearchCount">0 of 0 results</span>
                        <button class="close-search" id="closeMessageSearch" type="button" aria-label="Close message search">✕</button>
                    </div>

                    <div class="message-stream" id="messageStream"></div>

                    <form class="chat-input-bar" id="principalChatForm" method="post">
                        <input type="hidden" name="action" value="send_principal_chat">
                        <input type="hidden" name="contact_id" id="principalContactIdInput" value="<?php echo e($selectedContactId); ?>">
                        <button class="input-icon" id="attachBtn" type="button" aria-label="Attachment placeholder">📎</button>
                        <input class="message-input" id="messageInput" name="message" type="text" placeholder="Type a message..." autocomplete="off">
                        <button class="input-icon" id="emojiBtn" type="button" aria-label="Emoji placeholder">😊</button>
                        <button class="send-btn" id="sendBtn" type="submit" aria-label="Send message">➤</button>
                    </form>
                </div>
            </section>
        </div>
    </div>

    <div class="toast" id="toast"></div>

    <?php if ($chatError): ?>
        <script>window.__principalChatError = <?php echo json_encode($chatError, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;</script>
    <?php endif; ?>

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

        const teachers = <?php echo json_encode($teachers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const parents = <?php echo json_encode($parents, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const contacts = [...teachers, ...parents];
        const messageTemplates = <?php echo json_encode($messageTemplates, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

        const teachersList = document.getElementById('teachersList');
        const parentsList = document.getElementById('parentsList');
        const contactSearch = document.getElementById('contactSearch');
        const emptySearchState = document.getElementById('emptySearchState');
        const unreadBadge = document.getElementById('unreadBadge');
        const mobileToggle = document.getElementById('mobileToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const mainArea = document.getElementById('mainArea');
        const chatEmptyState = document.getElementById('chatEmptyState');
        const chatContent = document.getElementById('chatContent');
        const chatAvatar = document.getElementById('chatAvatar');
        const chatName = document.getElementById('chatName');
        const chatRole = document.getElementById('chatRole');
        const chatEmail = document.getElementById('chatEmail');
        const messageStream = document.getElementById('messageStream');
        const messageInput = document.getElementById('messageInput');
        const principalChatForm = document.getElementById('principalChatForm');
        const principalContactIdInput = document.getElementById('principalContactIdInput');
        const sendBtn = document.getElementById('sendBtn');
        const attachBtn = document.getElementById('attachBtn');
        const emojiBtn = document.getElementById('emojiBtn');
        const toast = document.getElementById('toast');
        const toggleMessageSearch = document.getElementById('toggleMessageSearch');
        const chatSearchBar = document.getElementById('chatSearchBar');
        const messageSearch = document.getElementById('messageSearch');
        const messageSearchCount = document.getElementById('messageSearchCount');
        const closeMessageSearch = document.getElementById('closeMessageSearch');
        const menuToggle = document.getElementById('menuToggle');
        const menuDropdown = document.getElementById('menuDropdown');
        const newChatBtn = document.getElementById('newChatBtn');
        const backChatBtn = document.getElementById('backChatBtn');

        function buildInitials(name) {
            const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
            if (parts.length === 0) {
                return 'CT';
            }
            return parts.slice(0, 2).map((part) => part.charAt(0).toUpperCase()).join('');
        }

        function stringToColor(value) {
            let hash = 0;
            for (let index = 0; index < value.length; index += 1) {
                hash = value.charCodeAt(index) + ((hash << 5) - hash);
            }

            const palette = ['#2563EB', '#0F766E', '#D97706', '#7C3AED', '#16A34A', '#DC2626', '#0EA5E9', '#EC4899'];
            return palette[Math.abs(hash) % palette.length];
        }

        function ensureRequestedContact() {
            const params = new URLSearchParams(window.location.search);
            const requestedContactId = params.get('contact_id');
            const requestedEmail = params.get('contact_email');
            const requestedName = params.get('contact_name');
            const requestedRole = params.get('contact_role');
            const requestedMeta = params.get('contact_meta');

            if (requestedContactId && contacts.some((contact) => contact.id === requestedContactId)) {
                return requestedContactId;
            }

            if (!requestedEmail && !requestedName) {
                return '';
            }

            const existing = contacts.find((contact) => {
                if (requestedEmail) {
                    return String(contact.email || '').toLowerCase() === requestedEmail.toLowerCase();
                }
                return String(contact.name || '').toLowerCase() === String(requestedName || '').toLowerCase();
            });

            if (existing) {
                return existing.id;
            }

            const role = requestedRole === 'Teacher' ? 'Teacher' : 'Parent';
            const name = requestedName || requestedEmail || 'New Contact';
            const email = requestedEmail || '';
            const meta = requestedMeta || (role === 'Teacher' ? 'Teacher' : 'Child: Not added yet');
            const id = `${role.toLowerCase()}-${(email || name).toLowerCase().replace(/[^a-z0-9]+/g, '-')}`;
            const contact = {
                id,
                name,
                email,
                role,
                meta,
                tag: role === 'Teacher' ? meta : meta.replace(/^Child:\s*/i, ''),
                online: false,
                unread: 0,
                timestamp: 'Now',
                preview: role === 'Teacher' ? 'Start a new conversation with this teacher.' : 'Start a new conversation with this parent.',
                avatar: buildInitials(name),
                color: stringToColor(email || name),
                replyPool: [
                    'Thank you for the message. I will respond properly shortly.',
                    'Received. I appreciate the update.',
                    'Noted. I will follow up as soon as possible.',
                ],
            };

            if (role === 'Teacher') {
                teachers.push(contact);
            } else {
                parents.push(contact);
            }
            contacts.push(contact);
            messageTemplates[id] = [
                { type: 'date', label: 'Today' },
                { type: 'received', text: `Hello Principal, this is ${name}.`, time: '9:00 AM' },
                { type: 'sent', text: 'Thank you. This conversation is now open.', time: '9:02 AM', status: 'read' },
            ];

            return id;
        }

        const requestedContactId = ensureRequestedContact();

        const uiState = {
            activeContactId: requestedContactId || sessionStorage.getItem('principal-chat-last-contact') || '',
            mobileChatOpen: false,
            unreadTotal: contacts.reduce((sum, contact) => sum + Number(contact.unread || 0), 0),
        };

        const conversations = structuredClone(messageTemplates);

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function escapeRegExp(value) {
            return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }

        function highlightMatch(text, query) {
            const safeText = escapeHtml(text);
            if (!query.trim()) {
                return safeText;
            }

            const pattern = new RegExp(`(${escapeRegExp(query.trim())})`, 'ig');
            return safeText.replace(pattern, '<mark>$1</mark>');
        }

        function showToast(message) {
            toast.textContent = message;
            toast.classList.add('visible');
            window.clearTimeout(showToast.timer);
            showToast.timer = window.setTimeout(() => {
                toast.classList.remove('visible');
            }, 2200);
        }

        function contactMatches(contact, query) {
            const normalized = query.trim().toLowerCase();
            if (!normalized) {
                return true;
            }

            return [contact.name, contact.email, contact.meta].some((value) => value.toLowerCase().includes(normalized));
        }

        function renderAvatarHtml(contact, className) {
            if (contact.avatar_image_url) {
                return `<div class="${className}" style="background:${contact.color};"><img src="${escapeHtml(contact.avatar_image_url)}" alt="${escapeHtml(contact.name)} profile picture"></div>`;
            }

            return `<div class="${className}" style="background:${contact.color};">${escapeHtml(contact.avatar)}</div>`;
        }

        function renderContactRow(contact, query) {
            const roleClass = contact.role === 'Parent' ? 'parent' : '';
            const active = contact.id === uiState.activeContactId ? 'active' : '';
            const unread = Number(contact.unread || 0);
            return `
                <button class="contact-row ${active}" type="button" data-contact-id="${contact.id}">
                    ${renderAvatarHtml(contact, 'avatar')}
                    <div class="contact-main">
                        <div class="contact-head">
                            <span class="contact-name">${highlightMatch(contact.name, query)}</span>
                        </div>
                        <div class="contact-email">${highlightMatch(contact.email, query)}</div>
                        <span class="contact-meta ${roleClass}">${highlightMatch(contact.tag, query)}</span>
                        <div class="contact-preview">${escapeHtml(contact.preview)}</div>
                    </div>
                    <div class="contact-side">
                        <span class="timestamp">${escapeHtml(contact.timestamp)}</span>
                        <span class="status-dot ${contact.online ? 'online' : ''}"></span>
                        ${unread > 0 ? `<span class="unread-badge">${unread}</span>` : ''}
                    </div>
                </button>
            `;
        }

        function renderContacts() {
            const query = contactSearch.value;
            const visibleTeachers = teachers.filter((contact) => contactMatches(contact, query));
            const visibleParents = parents.filter((contact) => contactMatches(contact, query));

            teachersList.innerHTML = visibleTeachers.map((contact) => renderContactRow(contact, query)).join('');
            parentsList.innerHTML = visibleParents.map((contact) => renderContactRow(contact, query)).join('');

            emptySearchState.classList.toggle('visible', visibleTeachers.length === 0 && visibleParents.length === 0);
            bindContactRows();
            updateSectionHeights();
            unreadBadge.textContent = `${uiState.unreadTotal} unread`;
        }

        function bindContactRows() {
            document.querySelectorAll('.contact-row').forEach((row) => {
                row.addEventListener('click', () => {
                    selectContact(row.dataset.contactId || '');
                });
            });
        }

        function formatRoleText(contact) {
            const statusText = contact.online ? '🟢 Online' : '⚫ Last seen 3h ago';
            return `${contact.role} — ${contact.meta} • ${statusText}`;
        }

        function currentTimeString() {
            return new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
        }

        function getActiveContact() {
            return contacts.find((contact) => contact.id === uiState.activeContactId) || null;
        }

        function buildMessageRow(message, contact, index) {
            if (message.type === 'date') {
                return `<div class="date-chip">${escapeHtml(message.label)}</div>`;
            }

            const isSent = message.type === 'sent';
            const senderLabel = isSent ? 'You' : contact.name;
            const senderClass = isSent ? 'you' : 'them';
            const rowClass = isSent ? 'sent' : 'received';
            const avatarHtml = isSent ? '' : renderAvatarHtml(contact, 'message-avatar');
            const receiptHtml = isSent ? `<span class="read-receipt ${message.status === 'read' ? 'read' : ''}" data-receipt-index="${index}">✓✓</span>` : '';
            return `
                <div class="message-row ${rowClass}" data-message-index="${index}">
                    ${avatarHtml}
                    <div class="message-stack">
                        <span class="sender-label ${senderClass}">${escapeHtml(senderLabel)}</span>
                        <div class="bubble" data-message-text="${escapeHtml(message.text)}">${escapeHtml(message.text)}</div>
                        <div class="message-meta">
                            <span>${escapeHtml(message.time || '')}</span>
                            ${receiptHtml}
                        </div>
                    </div>
                </div>
            `;
        }

        function renderConversation() {
            const contact = getActiveContact();
            if (!contact) {
                principalContactIdInput.value = '';
                chatEmptyState.style.display = 'grid';
                chatContent.classList.remove('active');
                return;
            }

            principalContactIdInput.value = contact.id;
            chatEmptyState.style.display = 'none';
            chatContent.classList.add('active');
            chatAvatar.style.background = contact.color;
            chatAvatar.innerHTML = contact.avatar_image_url
                ? `<img src="${escapeHtml(contact.avatar_image_url)}" alt="${escapeHtml(contact.name)} profile picture">`
                : escapeHtml(contact.avatar);
            chatName.textContent = contact.name;
            chatRole.textContent = formatRoleText(contact);
            chatEmail.textContent = contact.email;
            chatEmail.href = `mailto:${contact.email}`;
            messageInput.placeholder = `Type a message to ${contact.name}…`;

            const conversation = conversations[contact.id] || [];
            messageStream.innerHTML = conversation.map((message, index) => buildMessageRow(message, contact, index)).join('');
            applyMessageSearch();
            scrollToBottom();
        }

        function updateActiveRows() {
            document.querySelectorAll('.contact-row').forEach((row) => {
                row.classList.toggle('active', row.dataset.contactId === uiState.activeContactId);
            });
        }

        function selectContact(contactId) {
            if (!contacts.some((contact) => contact.id === contactId)) {
                return;
            }

            uiState.activeContactId = contactId;
            sessionStorage.setItem('principal-chat-last-contact', contactId);
            const url = new URL(window.location.href);
            url.searchParams.set('contact_id', contactId);
            window.history.replaceState({}, '', url);

            const contact = getActiveContact();
            if (contact && Number(contact.unread || 0) > 0) {
                uiState.unreadTotal = Math.max(0, uiState.unreadTotal - Number(contact.unread || 0));
                contact.unread = 0;
                markContactRead(contact.id);
            }

            updateActiveRows();
            renderContacts();
            renderConversation();

            if (window.innerWidth <= 768) {
                uiState.mobileChatOpen = true;
                mainArea.classList.add('chat-open');
            }
        }

        function scrollToBottom() {
            requestAnimationFrame(() => {
                messageStream.scrollTop = messageStream.scrollHeight;
            });
        }

        function appendMessage(contactId, message) {
            if (!conversations[contactId]) {
                conversations[contactId] = [];
            }
            conversations[contactId].push(message);
            if (contactId === uiState.activeContactId) {
                renderConversation();
            }
        }

        function markLatestReceiptRead(contactId) {
            window.setTimeout(() => {
                const conversation = conversations[contactId] || [];
                for (let index = conversation.length - 1; index >= 0; index -= 1) {
                    if (conversation[index].type === 'sent') {
                        conversation[index].status = 'read';
                        break;
                    }
                }

                if (contactId === uiState.activeContactId) {
                    renderConversation();
                }
            }, 2000);
        }

        async function markContactRead(contactId) {
            try {
                const response = await fetch('chat.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: new URLSearchParams({
                        action: 'mark_principal_chat_read',
                        contact_id: contactId,
                    }),
                });

                if (!response.ok) {
                    throw new Error('Unable to mark chat as read.');
                }
            } catch (error) {
                console.error(error);
            }
        }

        function simulateReply(contact) {
            const indicatorId = `typing-${Date.now()}`;
            const indicatorMarkup = `
                <div class="message-row received" id="${indicatorId}">
                    <div class="message-avatar" style="background:${contact.color};">${contact.avatar}</div>
                    <div class="message-stack">
                        <span class="sender-label them">${escapeHtml(contact.name)}</span>
                        <div class="typing-indicator"><span></span><span></span><span></span></div>
                    </div>
                </div>
            `;
            if (contact.id === uiState.activeContactId) {
                messageStream.insertAdjacentHTML('beforeend', indicatorMarkup);
                scrollToBottom();
            }

            window.setTimeout(() => {
                const typingNode = document.getElementById(indicatorId);
                if (typingNode) {
                    typingNode.remove();
                }

                const replies = contact.replyPool || ['Thank you for the message.'];
                const replyText = replies[Math.floor(Math.random() * replies.length)];
                appendMessage(contact.id, {
                    type: 'received',
                    text: replyText,
                    time: currentTimeString(),
                });
            }, 1500);
        }

        function sendMessage() {
            const contact = getActiveContact();
            if (!contact) {
                showToast('⚠️ Select a contact first');
                return;
            }

            const text = messageInput.value.trim();
            if (!text) {
                showToast('⚠️ Please type a message first');
                return;
            }

            if (contact.id.startsWith('parent-db-') || contact.id.startsWith('teacher-db-')) {
                principalContactIdInput.value = contact.id;
                principalChatForm.requestSubmit();
                return;
            }

            appendMessage(contact.id, {
                type: 'sent',
                text,
                time: currentTimeString(),
                status: 'sent',
            });

            messageInput.value = '';
            messageInput.focus();
            markLatestReceiptRead(contact.id);
            simulateReply(contact);
        }

        function applyMessageSearch() {
            const query = messageSearch.value.trim();
            const rows = [...messageStream.querySelectorAll('.message-row')];
            let matches = 0;

            rows.forEach((row) => {
                const bubble = row.querySelector('.bubble');
                if (!bubble) {
                    row.classList.remove('dimmed', 'hidden');
                    return;
                }

                const rawText = bubble.dataset.messageText || bubble.textContent || '';
                bubble.innerHTML = highlightMatch(rawText, query);

                if (!query) {
                    row.classList.remove('dimmed', 'hidden');
                    return;
                }

                const isMatch = rawText.toLowerCase().includes(query.toLowerCase());
                row.classList.toggle('dimmed', !isMatch);
                row.classList.toggle('hidden', false);
                if (isMatch) {
                    matches += 1;
                }
            });

            const total = rows.filter((row) => row.querySelector('.bubble')).length;
            messageSearchCount.textContent = `${matches} of ${total} results`;
        }

        function updateSectionHeights() {
            document.querySelectorAll('.contact-section').forEach((section) => {
                const body = section.querySelector('.section-body');
                if (!body || section.classList.contains('collapsed')) {
                    return;
                }
                body.style.maxHeight = `${body.scrollHeight}px`;
            });
        }

        function closeSidebar() {
            sidebar.classList.remove('open');
            sidebarOverlay.classList.remove('open');
        }

        mobileToggle.addEventListener('click', () => {
            sidebar.classList.toggle('open');
            sidebarOverlay.classList.toggle('open');
        });

        sidebarOverlay.addEventListener('click', closeSidebar);

        contactSearch.addEventListener('input', renderContacts);
        messageSearch.addEventListener('input', applyMessageSearch);

        sendBtn.addEventListener('click', sendMessage);
        principalChatForm.addEventListener('submit', (event) => {
            const contact = getActiveContact();
            if (!contact) {
                event.preventDefault();
                showToast('⚠️ Select a contact first');
                return;
            }

            principalContactIdInput.value = contact.id;

            if (!contact.id.startsWith('parent-db-') && !contact.id.startsWith('teacher-db-')) {
                event.preventDefault();
                sendMessage();
                return;
            }

            if (!messageInput.value.trim()) {
                event.preventDefault();
                showToast('⚠️ Please type a message first');
            }
        });
        messageInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                sendMessage();
            }
        });

        attachBtn.addEventListener('click', () => {
            showToast('📎 Attachments are not enabled in this demo yet');
        });

        emojiBtn.addEventListener('click', () => {
            showToast('😊 Emoji picker coming soon');
        });

        toggleMessageSearch.addEventListener('click', () => {
            chatSearchBar.classList.toggle('open');
            if (chatSearchBar.classList.contains('open')) {
                messageSearch.focus();
            } else {
                messageSearch.value = '';
                applyMessageSearch();
            }
        });

        closeMessageSearch.addEventListener('click', () => {
            chatSearchBar.classList.remove('open');
            messageSearch.value = '';
            applyMessageSearch();
        });

        menuToggle.addEventListener('click', () => {
            menuDropdown.classList.toggle('open');
        });

        document.addEventListener('click', (event) => {
            if (!menuToggle.contains(event.target) && !menuDropdown.contains(event.target)) {
                menuDropdown.classList.remove('open');
            }
        });

        document.querySelectorAll('[data-menu-action]').forEach((button) => {
            button.addEventListener('click', () => {
                const action = button.dataset.menuAction || 'Action';
                menuDropdown.classList.remove('open');
                showToast(`${action} is a placeholder action`);
            });
        });

        newChatBtn.addEventListener('click', () => {
            contactSearch.focus();
            contactSearch.select();
            if (window.innerWidth <= 768) {
                mainArea.classList.remove('chat-open');
                uiState.mobileChatOpen = false;
            }
        });

        backChatBtn.addEventListener('click', () => {
            uiState.mobileChatOpen = false;
            mainArea.classList.remove('chat-open');
        });

        document.querySelectorAll('[data-toggle-section]').forEach((button) => {
            button.addEventListener('click', () => {
                const section = button.closest('.contact-section');
                const body = section ? section.querySelector('.section-body') : null;
                if (!section || !body) {
                    return;
                }

                section.classList.toggle('collapsed');
                if (!section.classList.contains('collapsed')) {
                    body.style.maxHeight = `${body.scrollHeight}px`;
                }
            });
        });

        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                mainArea.classList.remove('chat-open');
                uiState.mobileChatOpen = false;
                closeSidebar();
            }
            updateSectionHeights();
        });

        renderContacts();
        updateSectionHeights();

        if (uiState.activeContactId && contacts.some((contact) => contact.id === uiState.activeContactId)) {
            selectContact(uiState.activeContactId);
        }

        if (window.__principalChatError) {
            showToast(window.__principalChatError);
        }
    </script>
</body>
</html>