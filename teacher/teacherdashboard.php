<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';

$teacher    = require_role('teacher');
$flash      = get_flash();
$teacherId  = (int) ($teacher['id'] ?? 0);
$teacherFullName  = display_name_from_user($teacher, 'Teacher');
$teacherFirst     = (string) (($teacher['first_name'] ?? '') ?: explode(' ', $teacherFullName)[0]);
$teacherInitial   = strtoupper(substr($teacherFullName, 0, 1));
$teacherEmail     = (string) ($teacher['email'] ?? '');

/* ── Fetch DB announcements ── */
$dbAnnouncements = fetch_announcements_for_role('teacher');

/* ── Fetch parents from DB ── */
$dbParents = [];
if (db_ready()) {
    $res = $db->query("SELECT id, full_name, first_name, surname, email, child_name FROM users WHERE role = 'parent' ORDER BY full_name ASC");
    if ($res) { while ($r = $res->fetch_assoc()) { $dbParents[] = $r; } $res->free(); }
}

/* ── Find or create teacher→principal thread ── */
$principal = fetch_first_user_by_role('principal');
$principalId  = $principal ? (int) ($principal['id'] ?? 0) : 0;
$principalName= $principal ? display_name_from_user($principal, 'The Principal') : 'The Principal';

$principalThreadId = null;
$principalMessages = [];
if ($principalId > 0) {
    $principalThreadId = find_or_create_support_thread($principalId, $teacherId, 'teacher', 'Teacher ↔ Principal Chat');
    if ($principalThreadId) {
        $principalMessages = fetch_support_messages($principalThreadId);
        mark_thread_messages_read($principalThreadId, 'teacher');
    }
}

/* ── Parent threads ── */
$parentThreads = [];      // keyed by parent user id
$parentMessages = [];     // keyed by parent user id
foreach ($dbParents as $par) {
    $pid = (int) ($par['id'] ?? 0);
    if ($pid < 1 || $principalId < 1) continue;
    $tid = find_or_create_support_thread($principalId, $pid, 'parent', 'Parent ↔ Teacher Chat');
    if ($tid) {
        $parentThreads[$pid] = $tid;
        $parentMessages[$pid] = fetch_support_messages($tid);
    }
}

/* ── Handle send message (AJAX-like POST) ── */
$sendError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_msg'])) {
    $msgText   = trim((string)($_POST['msg_text'] ?? ''));
    $contactType = (string)($_POST['contact_type'] ?? '');
    $contactUserId = (int)($_POST['contact_user_id'] ?? 0);

    if ($msgText === '') {
        $sendError = 'Please type a message first.';
    } elseif ($contactType === 'principal' && $principalThreadId) {
        send_support_message($principalThreadId, $teacherId, 'teacher', $msgText);
        redirect('teacherdashboard.php?panel=chats&contact=principal');
    } elseif ($contactType === 'parent' && isset($parentThreads[$contactUserId])) {
        send_support_message($parentThreads[$contactUserId], $teacherId, 'teacher', $msgText);
        redirect('teacherdashboard.php?panel=chats&contact=parent&pid=' . $contactUserId);
    }
}

/* ── Build JS-safe contact data ── */
$parentPalette = ['#16A34A','#D97706','#7C3AED','#0F766E','#DC2626','#4F46E5'];
$parentContacts = [];
foreach ($dbParents as $i => $par) {
    $pid  = (int)($par['id'] ?? 0);
    $name = display_name_from_user($par, 'Parent');
    $child= trim((string)($par['child_name'] ?? ''));
    $msgs = $parentMessages[$pid] ?? [];
    $lastMsg = ''; $lastTime = '';
    if (!empty($msgs)) {
        $last = end($msgs);
        $lastMsg  = mb_substr((string)($last['message'] ?? ''), 0, 50);
        $lastTime = date('g:i A', strtotime((string)($last['created_at'] ?? 'now')));
    }
    $parentContacts[] = [
        'id'       => $pid,
        'name'     => $name,
        'email'    => (string)($par['email'] ?? ''),
        'child'    => $child !== '' ? $child : 'Not added yet',
        'initial'  => strtoupper(substr($name, 0, 1)),
        'color'    => $parentPalette[$i % count($parentPalette)],
        'online'   => false,
        'unread'   => 0,
        'preview'  => $lastMsg !== '' ? $lastMsg : 'No messages yet.',
        'timestamp'=> $lastTime,
    ];
}

$principalMsgData = [];
foreach ($principalMessages as $m) {
    $principalMsgData[] = [
        'from'    => ($m['sender_role'] ?? '') === 'teacher' ? 'me' : 'them',
        'text'    => (string)($m['message'] ?? ''),
        'time'    => date('g:i A', strtotime((string)($m['created_at'] ?? 'now'))),
        'date'    => date('Y-m-d', strtotime((string)($m['created_at'] ?? 'now'))),
    ];
}

$parentMsgData = [];
foreach ($dbParents as $par) {
    $pid = (int)($par['id'] ?? 0);
    $msgs= $parentMessages[$pid] ?? [];
    $arr = [];
    foreach ($msgs as $m) {
        $arr[] = [
            'from' => ($m['sender_id'] ?? 0) === $teacherId ? 'me' : 'them',
            'text' => (string)($m['message'] ?? ''),
            'time' => date('g:i A', strtotime((string)($m['created_at'] ?? 'now'))),
            'date' => date('Y-m-d', strtotime((string)($m['created_at'] ?? 'now'))),
        ];
    }
    $parentMsgData[$pid] = $arr;
}

/* ── Initial panel from URL ── */
$allowedPanels = ['dashboard','announcements','chats'];
$initialPanel  = in_array((string)($_GET['panel'] ?? ''), $allowedPanels, true) ? (string)$_GET['panel'] : 'dashboard';
$initialContact= (string)($_GET['contact'] ?? '');
$initialPid    = (int)($_GET['pid'] ?? 0);

/* ── Announcement stats ── */
$newAnnCount    = count($dbAnnouncements);
$unreadMsgCount = 0;
if ($principalThreadId) {
    $res2 = $db->prepare('SELECT COUNT(*) AS c FROM support_messages WHERE thread_id = ? AND sender_role <> ? AND is_read = 0');
    if ($res2) { $res2->bind_param('is', $principalThreadId, 'teacher'); $res2->execute(); $r2 = $res2->get_result(); if ($r2) { $row2 = $r2->fetch_assoc(); $unreadMsgCount = (int)($row2['c'] ?? 0); } $res2->close(); }
}
$parentCount = count($dbParents);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Teacher Dashboard | SAMACE TECH LAB</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">
<style>
/* ═══════════════════════════════════════════════
   CSS VARIABLES
═══════════════════════════════════════════════ */
:root {
    --teal:        #0D4B4B;
    --teal-light:  #1a6b6b;
    --teal-faint:  rgba(13,75,75,.09);
    --gold:        #F5A623;
    --gold-dark:   #d48c10;
    --bg:          #F5F7FA;
    --card-bg:     #ffffff;
    --text:        #1a2e2e;
    --text-soft:   #5a7070;
    --border:      rgba(13,75,75,.13);
    --shadow:      0 4px 24px rgba(13,75,75,.09);
    --shadow-card: 0 2px 12px rgba(13,75,75,.07);
    --sidebar-w:   250px;
    --radius:      14px;
    --radius-sm:   8px;
    --transition:  200ms ease;
    --font-body:   'Poppins', sans-serif;
    --font-head:   'Playfair Display', serif;
    --bubble-sent: #0D4B4B;
    --bubble-recv: #ffffff;
    --bubble-recv-border: #DCE4E4;
}

/* ═══════════════════════════════════════════════
   RESET & BASE
═══════════════════════════════════════════════ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; }
body {
    font-family: var(--font-body);
    background: var(--bg);
    color: var(--text);
    overflow: hidden;
    height: 100vh;
}
a { color: inherit; text-decoration: none; }
button { font-family: var(--font-body); cursor: pointer; border: none; background: none; }
input, textarea { font-family: var(--font-body); }
::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: rgba(13,75,75,.25); border-radius: 99px; }

/* ═══════════════════════════════════════════════
   LAYOUT SHELL
═══════════════════════════════════════════════ */
.shell { display: flex; height: 100vh; overflow: hidden; }

/* ═══════════════════════════════════════════════
   SIDEBAR
═══════════════════════════════════════════════ */
.sidebar {
    width: var(--sidebar-w);
    flex-shrink: 0;
    position: fixed;
    inset: 0 auto 0 0;
    z-index: 1100;
    background: linear-gradient(175deg, var(--teal) 0%, #083535 100%);
    color: #fff;
    display: flex;
    flex-direction: column;
    padding: 0;
    overflow-y: auto;
    transition: transform var(--transition);
}
.sidebar-top {
    padding: 28px 20px 20px;
    border-bottom: 1px solid rgba(255,255,255,.1);
    margin-bottom: 8px;
}
.sidebar-school-name {
    font-size: .68rem;
    font-weight: 700;
    letter-spacing: .18em;
    text-transform: uppercase;
    font-variant: small-caps;
    color: var(--gold);
    margin-bottom: 2px;
    font-family: var(--font-body);
}
.sidebar-title {
    font-family: var(--font-head);
    font-size: 1.15rem;
    color: #fff;
    font-weight: 700;
    line-height: 1.2;
}
.sidebar-nav { flex: 1; padding: 8px 14px; display: flex; flex-direction: column; gap: 4px; }
.nav-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    border-radius: var(--radius-sm);
    color: rgba(255,255,255,.8);
    font-size: .9rem;
    font-weight: 500;
    position: relative;
    transition: background var(--transition), color var(--transition);
    border-left: 3px solid transparent;
}
.nav-item:hover { background: rgba(255,255,255,.08); color: #fff; }
.nav-item.active {
    background: rgba(245,166,35,.12);
    color: var(--gold);
    border-left-color: var(--gold);
    font-weight: 600;
}
.nav-item .nav-icon { font-size: 1.1rem; width: 22px; text-align: center; flex-shrink: 0; }
.nav-item.logout:hover { background: rgba(220,38,38,.15); color: #fca5a5; }
.sidebar-footer {
    padding: 16px 20px 20px;
    border-top: 1px solid rgba(255,255,255,.1);
    display: flex;
    align-items: center;
    gap: 12px;
}
.sidebar-avatar {
    width: 44px; height: 44px;
    border-radius: 50%;
    background: rgba(245,166,35,.25);
    color: var(--gold);
    display: grid; place-items: center;
    font-weight: 700;
    font-size: 1.1rem;
    border: 2px solid rgba(245,166,35,.4);
    flex-shrink: 0;
}
.sidebar-footer-name { font-size: .88rem; font-weight: 600; color: #fff; }
.sidebar-footer-role { font-size: .76rem; color: rgba(255,255,255,.55); }

/* ═══════════════════════════════════════════════
   MOBILE HAMBURGER
═══════════════════════════════════════════════ */
.hamburger {
    display: none;
    position: fixed;
    top: 14px; left: 14px;
    z-index: 1200;
    width: 44px; height: 44px;
    border-radius: 10px;
    background: var(--teal);
    color: #fff;
    font-size: 1.25rem;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 12px rgba(13,75,75,.3);
}
.sidebar-overlay {
    display: none;
    position: fixed; inset: 0;
    background: rgba(0,0,0,.45);
    z-index: 1050;
    opacity: 0;
    transition: opacity var(--transition);
}

/* ═══════════════════════════════════════════════
   MAIN CONTENT
═══════════════════════════════════════════════ */
.main {
    flex: 1;
    margin-left: var(--sidebar-w);
    height: 100vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

/* ═══════════════════════════════════════════════
   PANELS
═══════════════════════════════════════════════ */
.panel {
    display: none;
    flex: 1;
    overflow-y: auto;
    height: 100%;
    animation: fadeIn .25s ease;
}
.panel.active { display: flex; flex-direction: column; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }

/* ═══════════════════════════════════════════════
   DASHBOARD PANEL
═══════════════════════════════════════════════ */
.dash-wrap { padding: 32px 36px 48px; }
.dash-header { margin-bottom: 28px; }
.dash-greeting {
    font-family: var(--font-head);
    font-size: clamp(1.5rem, 3vw, 2.1rem);
    color: var(--teal);
    font-weight: 700;
    line-height: 1.2;
    margin-bottom: 6px;
}
.dash-date { font-size: .92rem; color: var(--text-soft); }

.stat-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 18px;
    margin-bottom: 32px;
}
.stat-card {
    background: var(--card-bg);
    border-radius: var(--radius);
    padding: 20px 18px;
    box-shadow: var(--shadow-card);
    border-left: 4px solid var(--teal);
    display: flex;
    align-items: center;
    gap: 16px;
    transition: transform var(--transition), box-shadow var(--transition);
}
.stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow); }
.stat-icon { font-size: 1.8rem; flex-shrink: 0; }
.stat-label { font-size: .78rem; color: var(--text-soft); font-weight: 500; text-transform: uppercase; letter-spacing: .06em; margin-bottom: 4px; }
.stat-value { font-size: 1.7rem; font-weight: 700; color: var(--teal); font-family: var(--font-head); line-height: 1; }

.section-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
}
.section-title {
    font-family: var(--font-head);
    font-size: 1.2rem;
    color: var(--teal);
    font-weight: 700;
}
.link-action {
    font-size: .85rem;
    color: var(--teal);
    font-weight: 600;
    opacity: .75;
    transition: opacity var(--transition);
    cursor: pointer;
}
.link-action:hover { opacity: 1; }

.preview-grid { display: grid; gap: 14px; margin-bottom: 32px; }
.preview-card {
    background: var(--card-bg);
    border-radius: var(--radius);
    padding: 18px 20px;
    box-shadow: var(--shadow-card);
    border-left: 4px solid var(--teal);
}
.preview-card-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 8px; }
.preview-card-title { font-weight: 600; color: var(--teal); font-size: .97rem; }
.preview-card-meta { font-size: .8rem; color: var(--text-soft); margin-top: 6px; }
.preview-card-excerpt { font-size: .88rem; color: var(--text-soft); margin-top: 6px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

.badge-pill {
    display: inline-flex;
    align-items: center;
    padding: 3px 10px;
    border-radius: 99px;
    font-size: .7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
}
.badge-urgent { background: #fee2e2; color: #b91c1c; }
.badge-normal { background: #f1f5f9; color: #64748b; }
.badge-everyone { background: #dbeafe; color: #1d4ed8; }
.badge-teachers { background: #ccfbf1; color: #0f766e; }
.badge-parents { background: #dcfce7; color: #15803d; }

.quick-actions { display: flex; gap: 14px; flex-wrap: wrap; }
.btn-action {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 22px;
    border-radius: var(--radius-sm);
    background: var(--gold);
    color: #1a1a1a;
    font-weight: 600;
    font-size: .9rem;
    transition: background var(--transition), transform var(--transition);
    cursor: pointer;
    border: none;
    font-family: var(--font-body);
}
.btn-action:hover { background: var(--gold-dark); transform: translateY(-2px); }

/* ═══════════════════════════════════════════════
   ANNOUNCEMENTS PANEL
═══════════════════════════════════════════════ */
.ann-wrap { display: flex; flex-direction: column; height: 100%; }
.ann-sticky {
    position: sticky;
    top: 0;
    z-index: 10;
    background: var(--bg);
    padding: 20px 36px 14px;
    border-bottom: 1px solid var(--border);
}
.ann-sticky-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 14px;
    gap: 12px;
    flex-wrap: wrap;
}
.ann-panel-title {
    font-family: var(--font-head);
    font-size: 1.4rem;
    color: var(--teal);
    font-weight: 700;
}
.ann-result-count { font-size: .85rem; color: var(--text-soft); }
.ann-controls { display: flex; gap: 10px; flex-wrap: wrap; }
.ann-search-wrap { position: relative; }
.ann-search-wrap input {
    padding: 9px 14px 9px 36px;
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    background: var(--card-bg);
    font-size: .88rem;
    width: 240px;
    outline: none;
    transition: border-color var(--transition);
}
.ann-search-wrap input:focus { border-color: var(--teal); }
.ann-search-icon { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--text-soft); font-size: .95rem; pointer-events: none; }
.ann-filter {
    padding: 9px 12px;
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    background: var(--card-bg);
    font-family: var(--font-body);
    font-size: .88rem;
    color: var(--text);
    outline: none;
    cursor: pointer;
}

.ann-list { flex: 1; overflow-y: auto; padding: 20px 36px 40px; display: flex; flex-direction: column; gap: 20px; }
.ann-card {
    background: var(--card-bg);
    border-radius: var(--radius);
    box-shadow: var(--shadow-card);
    overflow: hidden;
    border: 1px solid var(--border);
    transition: box-shadow var(--transition);
}
.ann-card:hover { box-shadow: var(--shadow); }
.ann-card-header {
    padding: 18px 20px 14px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}
.ann-title {
    font-family: var(--font-head);
    font-size: 1.08rem;
    font-weight: 700;
    color: var(--teal);
    margin-bottom: 8px;
}
.ann-badges { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
.ann-header-right { text-align: right; flex-shrink: 0; }
.ann-date { font-size: .78rem; color: var(--text-soft); margin-bottom: 4px; }
.ann-from { display: flex; align-items: center; gap: 6px; justify-content: flex-end; font-size: .8rem; color: var(--text-soft); }
.ann-from-avatar {
    width: 24px; height: 24px;
    border-radius: 50%;
    background: var(--teal);
    color: #fff;
    display: grid; place-items: center;
    font-size: .65rem;
    font-weight: 700;
    flex-shrink: 0;
}

.ann-card-body { padding: 16px 20px; }
.ann-body-text { font-size: .92rem; color: var(--text); line-height: 1.65; }
.ann-body-inner { max-height: 5.4em; overflow: hidden; transition: max-height .35s ease; }
.ann-body-inner.expanded { max-height: 1000px; }
.ann-toggle-btn {
    background: none; border: none; color: var(--teal); font-size: .82rem; font-weight: 600; padding: 4px 0; cursor: pointer; margin-top: 6px; display: inline-flex; align-items: center; gap: 4px;
}

.ann-reaction-bar {
    padding: 10px 20px 10px;
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
    border-top: 1px solid var(--border);
}
.reaction-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 10px;
    border-radius: 99px;
    border: 1.5px solid var(--border);
    background: transparent;
    font-size: .88rem;
    cursor: pointer;
    transition: all var(--transition);
    color: var(--text-soft);
    font-family: var(--font-body);
}
.reaction-btn:hover { border-color: var(--teal); background: var(--teal-faint); color: var(--teal); }
.reaction-btn.active { border-color: var(--teal); background: var(--teal-faint); color: var(--teal); font-weight: 600; }
.reaction-btn.active .reaction-emoji { transform: scale(1.3); display: inline-block; }
.reaction-count { font-size: .78rem; font-weight: 600; }
.reaction-total { margin-left: auto; font-size: .8rem; color: var(--text-soft); font-style: italic; }

.ann-comment-section { border-top: 1px solid var(--border); }
.comment-toggle-btn {
    width: 100%;
    padding: 11px 20px;
    background: none;
    border: none;
    text-align: left;
    font-family: var(--font-body);
    font-size: .88rem;
    font-weight: 600;
    color: var(--text-soft);
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: background var(--transition), color var(--transition);
}
.comment-toggle-btn:hover { background: var(--teal-faint); color: var(--teal); }
.comment-thread {
    max-height: 0;
    overflow: hidden;
    transition: max-height .35s ease;
}
.comment-thread.open { max-height: 2000px; }
.comment-thread-inner { padding: 12px 20px 16px; display: flex; flex-direction: column; gap: 12px; }
.comment-item { display: flex; gap: 10px; }
.comment-avatar {
    width: 32px; height: 32px;
    border-radius: 50%;
    display: grid; place-items: center;
    font-size: .78rem; font-weight: 700; color: #fff;
    flex-shrink: 0;
}
.comment-content { flex: 1; }
.comment-name { font-size: .82rem; font-weight: 700; color: var(--teal); }
.comment-text { font-size: .87rem; color: var(--text); margin-top: 2px; line-height: 1.5; }
.comment-meta { font-size: .75rem; color: var(--text-soft); margin-top: 4px; display: flex; align-items: center; gap: 8px; }
.comment-like-btn { background: none; border: none; font-size: .75rem; color: var(--text-soft); cursor: pointer; display: inline-flex; align-items: center; gap: 3px; transition: color var(--transition); padding: 0; font-family: var(--font-body); }
.comment-like-btn:hover { color: var(--teal); }
.comment-like-btn.liked { color: var(--teal); font-weight: 600; }

.comment-input-wrap { padding: 0 20px 16px; }
.comment-textarea-box { position: relative; }
.comment-textarea {
    width: 100%;
    padding: 10px 14px;
    border: 1.5px solid var(--border);
    border-radius: var(--radius-sm);
    font-family: var(--font-body);
    font-size: .88rem;
    resize: none;
    min-height: 72px;
    transition: border-color var(--transition);
    outline: none;
    overflow: hidden;
}
.comment-textarea:focus { border-color: var(--teal); }
.comment-footer { display: flex; align-items: center; justify-content: space-between; margin-top: 8px; }
.comment-char-count { font-size: .75rem; color: var(--text-soft); }
.btn-post-comment {
    padding: 8px 18px;
    background: var(--teal);
    color: #fff;
    border-radius: var(--radius-sm);
    font-family: var(--font-body);
    font-size: .85rem;
    font-weight: 600;
    cursor: pointer;
    border: none;
    transition: background var(--transition);
}
.btn-post-comment:hover { background: var(--teal-light); }

/* ═══════════════════════════════════════════════
   CHAT PANEL
═══════════════════════════════════════════════ */
.chat-shell {
    display: flex;
    height: 100%;
    overflow: hidden;
}

/* Contact List */
.contact-list {
    width: 320px;
    flex-shrink: 0;
    border-right: 1px solid var(--border);
    background: var(--card-bg);
    display: flex;
    flex-direction: column;
    height: 100%;
    overflow: hidden;
}
.cl-header {
    padding: 20px 18px 12px;
    border-bottom: 1px solid var(--border);
    flex-shrink: 0;
}
.cl-title-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
.cl-title { font-family: var(--font-head); font-size: 1.2rem; font-weight: 700; color: var(--teal); }
.cl-unread-badge {
    background: var(--gold);
    color: #1a1a1a;
    font-size: .72rem;
    font-weight: 700;
    padding: 3px 9px;
    border-radius: 99px;
}
.cl-search-wrap { position: relative; }
.cl-search-wrap input {
    width: 100%;
    padding: 9px 14px 9px 34px;
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    font-family: var(--font-body);
    font-size: .85rem;
    outline: none;
    background: var(--bg);
    transition: border-color var(--transition);
}
.cl-search-wrap input:focus { border-color: var(--teal); }
.cl-search-icon { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--text-soft); font-size: .9rem; pointer-events: none; }

.cl-body { flex: 1; overflow-y: auto; }
.cl-section { }
.cl-section-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 18px 6px;
    cursor: pointer;
    user-select: none;
    position: sticky;
    top: 0;
    background: var(--card-bg);
    z-index: 2;
}
.cl-section-label { font-size: .72rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--text-soft); }
.cl-section-chevron { font-size: .8rem; color: var(--text-soft); transition: transform var(--transition); }
.cl-section.collapsed .cl-section-chevron { transform: rotate(-90deg); }
.cl-contacts { transition: max-height .3s ease; overflow: hidden; }
.cl-section.collapsed .cl-contacts { max-height: 0 !important; }
.cl-no-results { padding: 20px 18px; text-align: center; color: var(--text-soft); font-size: .88rem; display: none; }

.contact-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 18px;
    cursor: pointer;
    border-left: 3px solid transparent;
    transition: background var(--transition), border-color var(--transition);
    position: relative;
}
.contact-row:hover { background: var(--teal-faint); }
.contact-row.active { background: rgba(13,75,75,.07); border-left-color: var(--teal); }
.contact-avatar {
    width: 42px; height: 42px;
    border-radius: 50%;
    display: grid; place-items: center;
    font-weight: 700; color: #fff;
    font-size: .95rem;
    flex-shrink: 0;
    position: relative;
}
.online-dot {
    position: absolute;
    bottom: 1px; right: 1px;
    width: 10px; height: 10px;
    border-radius: 50%;
    border: 2px solid #fff;
}
.online-dot.online { background: #22c55e; }
.online-dot.offline { background: #9ca3af; }
.contact-info { flex: 1; min-width: 0; }
.contact-name { font-weight: 600; font-size: .9rem; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.contact-sub { font-size: .76rem; color: var(--text-soft); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 2px; }
.contact-child-tag {
    display: inline-flex;
    align-items: center;
    padding: 2px 7px;
    border-radius: 99px;
    background: #dcfce7;
    color: #15803d;
    font-size: .7rem;
    font-weight: 600;
    margin-top: 3px;
}
.contact-role-tag { display: inline-flex; align-items: center; padding: 2px 7px; border-radius: 99px; background: #e0f2fe; color: #0369a1; font-size: .7rem; font-weight: 600; margin-top: 3px; }
.contact-meta { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; flex-shrink: 0; }
.contact-time { font-size: .72rem; color: var(--text-soft); }
.contact-unread { background: #dc2626; color: #fff; font-size: .68rem; font-weight: 700; min-width: 18px; height: 18px; border-radius: 99px; display: grid; place-items: center; padding: 0 4px; }
.contact-preview { font-size: .76rem; color: var(--text-soft); font-style: italic; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 140px; }
mark { background: rgba(245,166,35,.35); border-radius: 2px; }

/* Chat Window */
.chat-window {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: var(--bg);
    overflow: hidden;
}
.chat-empty {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: var(--text-soft);
    gap: 14px;
    text-align: center;
    padding: 40px;
}
.chat-empty-icon { font-size: 4rem; }
.chat-empty h3 { font-family: var(--font-head); color: var(--teal); font-size: 1.3rem; }
.chat-empty p { font-size: .9rem; max-width: 320px; }

.chat-topbar {
    padding: 14px 20px;
    background: var(--card-bg);
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 14px;
    flex-shrink: 0;
    position: relative;
}
.chat-topbar-avatar { width: 42px; height: 42px; border-radius: 50%; display: grid; place-items: center; font-weight: 700; color: #fff; font-size: .95rem; flex-shrink: 0; }
.chat-topbar-name { font-weight: 700; color: var(--teal); font-size: .97rem; }
.chat-topbar-sub { font-size: .78rem; color: var(--text-soft); margin-top: 2px; }
.chat-topbar-sub a { color: var(--teal); text-decoration: underline; }
.chat-topbar-status { display: flex; align-items: center; gap: 5px; font-size: .78rem; }
.chat-topbar-status .dot { width: 8px; height: 8px; border-radius: 50%; }
.chat-topbar-status .dot.online { background: #22c55e; }
.chat-topbar-status .dot.offline { background: #9ca3af; }
.chat-topbar-actions { margin-left: auto; display: flex; gap: 8px; }
.topbar-icon-btn {
    width: 36px; height: 36px;
    border-radius: 50%;
    background: var(--bg);
    border: 1px solid var(--border);
    color: var(--text-soft);
    display: grid; place-items: center;
    cursor: pointer;
    font-size: .95rem;
    transition: background var(--transition), color var(--transition);
}
.topbar-icon-btn:hover { background: var(--teal-faint); color: var(--teal); }
.three-dot-menu { position: relative; }
.dropdown-menu {
    display: none;
    position: absolute;
    right: 0; top: calc(100% + 6px);
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    box-shadow: var(--shadow);
    z-index: 100;
    min-width: 160px;
    overflow: hidden;
}
.dropdown-menu.open { display: block; }
.dropdown-item {
    display: block;
    padding: 10px 16px;
    font-size: .85rem;
    color: var(--text);
    cursor: pointer;
    transition: background var(--transition);
}
.dropdown-item:hover { background: var(--teal-faint); }

.in-chat-search {
    padding: 10px 20px;
    background: var(--card-bg);
    border-bottom: 1px solid var(--border);
    display: none;
    align-items: center;
    gap: 10px;
}
.in-chat-search.open { display: flex; }
.in-chat-search input {
    flex: 1;
    padding: 8px 12px;
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    font-family: var(--font-body);
    font-size: .88rem;
    outline: none;
}
.in-chat-search input:focus { border-color: var(--teal); }
.in-chat-search-count { font-size: .8rem; color: var(--text-soft); white-space: nowrap; }
.in-chat-search-close { cursor: pointer; color: var(--text-soft); font-size: 1.1rem; }

.chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 20px 24px;
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.date-divider {
    text-align: center;
    margin: 12px 0;
    position: relative;
}
.date-divider::before, .date-divider::after {
    content: '';
    position: absolute;
    top: 50%;
    width: 30%;
    height: 1px;
    background: var(--border);
}
.date-divider::before { left: 0; }
.date-divider::after { right: 0; }
.date-divider span {
    display: inline-block;
    padding: 3px 12px;
    background: var(--bg);
    border-radius: 99px;
    font-size: .76rem;
    color: var(--text-soft);
    border: 1px solid var(--border);
    position: relative;
    z-index: 1;
}

.msg-group { display: flex; flex-direction: column; gap: 2px; margin-bottom: 6px; }
.msg-group.sent { align-items: flex-end; }
.msg-group.received { align-items: flex-start; }
.msg-sender-label { font-size: .72rem; color: var(--text-soft); padding: 0 2px; margin-bottom: 2px; font-weight: 600; }

.bubble {
    max-width: 68%;
    padding: 10px 14px;
    border-radius: 16px;
    font-size: .9rem;
    line-height: 1.5;
    word-break: break-word;
    position: relative;
}
.bubble.sent {
    background: var(--bubble-sent);
    color: #fff;
    border-bottom-right-radius: 4px;
}
.bubble.received {
    background: var(--bubble-recv);
    color: var(--text);
    border: 1.5px solid var(--bubble-recv-border);
    border-bottom-left-radius: 4px;
}
.bubble-meta { display: flex; align-items: center; gap: 5px; margin-top: 3px; justify-content: flex-end; font-size: .7rem; }
.bubble-meta.recv { justify-content: flex-start; color: var(--text-soft); }
.bubble-time { color: rgba(255,255,255,.65); }
.bubble-time.dark { color: var(--text-soft); }
.checkmarks { color: rgba(255,255,255,.5); font-size: .75rem; transition: color .3s; }
.checkmarks.read { color: #60d0d0; }

.typing-indicator { display: none; align-items: center; gap: 6px; padding: 2px 0; }
.typing-indicator.show { display: flex; }
.typing-dots { display: flex; gap: 3px; align-items: center; }
.typing-dot {
    width: 7px; height: 7px;
    background: var(--text-soft);
    border-radius: 50%;
    animation: bounce 1.2s infinite;
}
.typing-dot:nth-child(2) { animation-delay: .2s; }
.typing-dot:nth-child(3) { animation-delay: .4s; }
@keyframes bounce { 0%, 80%, 100% { transform: translateY(0); } 40% { transform: translateY(-6px); } }

.chat-input-bar {
    padding: 12px 20px;
    background: var(--card-bg);
    border-top: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}
.chat-input-icon { color: var(--text-soft); font-size: 1.1rem; cursor: pointer; transition: color var(--transition); flex-shrink: 0; }
.chat-input-icon:hover { color: var(--teal); }
.chat-input {
    flex: 1;
    padding: 10px 14px;
    border: 1.5px solid var(--border);
    border-radius: 99px;
    font-family: var(--font-body);
    font-size: .9rem;
    outline: none;
    background: var(--bg);
    transition: border-color var(--transition);
}
.chat-input:focus { border-color: var(--teal); background: var(--card-bg); }
.chat-send-btn {
    width: 40px; height: 40px;
    border-radius: 50%;
    background: var(--gold);
    color: var(--teal);
    border: none;
    display: grid; place-items: center;
    cursor: pointer;
    font-size: 1.05rem;
    flex-shrink: 0;
    transition: background var(--transition), transform var(--transition);
}
.chat-send-btn:hover { background: var(--gold-dark); transform: scale(1.08); }

/* ═══════════════════════════════════════════════
   TOAST
═══════════════════════════════════════════════ */
.toast-container {
    position: fixed;
    bottom: 28px;
    right: 28px;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    gap: 10px;
    pointer-events: none;
}
.toast {
    padding: 12px 20px;
    border-radius: var(--radius-sm);
    background: var(--teal);
    color: #fff;
    font-size: .88rem;
    font-weight: 500;
    box-shadow: var(--shadow);
    opacity: 0;
    transform: translateY(12px);
    transition: all .3s ease;
    pointer-events: none;
    max-width: 340px;
}
.toast.show { opacity: 1; transform: translateY(0); }
.toast.warn { background: #d97706; }
.toast.success { background: #15803d; }

/* ═══════════════════════════════════════════════
   RESPONSIVE
═══════════════════════════════════════════════ */
@media (max-width: 1024px) {
    .stat-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 768px) {
    body { overflow: auto; }
    .shell { flex-direction: column; }
    .hamburger { display: flex; }
    .sidebar { transform: translateX(-100%); }
    body.sidebar-open .sidebar { transform: translateX(0); }
    .sidebar-overlay { display: block; }
    body.sidebar-open .sidebar-overlay { opacity: 1; pointer-events: auto; }
    .main { margin-left: 0; height: auto; overflow: visible; }
    .panel { height: auto; overflow: visible; }
    .chat-shell { flex-direction: column; height: auto; }
    .contact-list { width: 100%; height: auto; max-height: 350px; border-right: none; border-bottom: 1px solid var(--border); }
    .chat-window { min-height: 500px; }
    .dash-wrap { padding: 80px 20px 40px; }
    .ann-sticky { padding: 80px 20px 14px; }
    .ann-list { padding: 16px 20px 40px; }
    .contact-list.mobile-hidden { display: none; }
    .chat-window.mobile-hidden { display: none; }
}
@media (max-width: 480px) {
    .stat-grid { grid-template-columns: 1fr; }
    .quick-actions { flex-direction: column; }
    .ann-controls { flex-direction: column; }
    .ann-search-wrap input { width: 100%; }
    .bubble { max-width: 88%; }
}
</style>
<?php require_once dirname(__DIR__) . '/theme-shared.php'; ?>
</head>
<body>

<!-- Hamburger -->
<button class="hamburger" id="hamburger" aria-label="Toggle sidebar">☰</button>

<!-- Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Shell -->
<div class="shell">

    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-top">
            <div class="sidebar-school-name">SAMACE TECH LAB</div>
            <div class="sidebar-title">Teacher Portal</div>
        </div>
        <nav class="sidebar-nav">
            <a class="nav-item<?php echo $initialPanel === 'dashboard' ? ' active' : ''; ?>" href="#" data-panel="dashboard" id="nav-dashboard">
                <span class="nav-icon">🏠</span><span>Dashboard</span>
            </a>
            <a class="nav-item<?php echo $initialPanel === 'chats' ? ' active' : ''; ?>" href="#" data-panel="chats" id="nav-chats">
                <span class="nav-icon">💬</span><span>Chats</span>
            </a>
            <a class="nav-item<?php echo $initialPanel === 'announcements' ? ' active' : ''; ?>" href="#" data-panel="announcements" id="nav-announcements">
                <span class="nav-icon">📢</span><span>Announcements</span>
            </a>
            <a class="nav-item logout" href="logout.php">
                <span class="nav-icon">🚪</span><span>Logout</span>
            </a>
        </nav>
        <div class="sidebar-footer">
            <div class="sidebar-avatar"><?php echo e($teacherInitial); ?></div>
            <div>
                <div class="sidebar-footer-name"><?php echo e($teacherFirst); ?></div>
                <div class="sidebar-footer-role">Teacher</div>
            </div>
        </div>
    </aside>

    <!-- MAIN -->
    <main class="main" id="mainArea">

        <!-- ══════════════════════════════
             PANEL: DASHBOARD
        ══════════════════════════════ -->
        <section class="panel<?php echo $initialPanel === 'dashboard' ? ' active' : ''; ?>" id="panel-dashboard">
            <div class="dash-wrap">
                <div class="dash-header">
                    <div class="dash-greeting" id="greetingMsg">Good morning, <?php echo e($teacherFirst); ?> 👋 — Ready to inspire today?</div>
                    <div class="dash-date" id="dashDate"></div>
                </div>

                <!-- Stat Cards -->
                <div class="stat-grid">
                    <div class="stat-card">
                        <div class="stat-icon">📢</div>
                        <div>
                            <div class="stat-label">New Announcements</div>
                            <div class="stat-value"><?php echo $newAnnCount; ?></div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">💬</div>
                        <div>
                            <div class="stat-label">Unread Messages</div>
                            <div class="stat-value" id="statUnread"><?php echo $unreadMsgCount; ?></div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">👨‍👩‍👧</div>
                        <div>
                            <div class="stat-label">Parents in My Class</div>
                            <div class="stat-value"><?php echo $parentCount; ?></div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">💬</div>
                        <div>
                            <div class="stat-label">Unread Comments</div>
                            <div class="stat-value" id="statComments">0</div>
                        </div>
                    </div>
                </div>

                <!-- Recent Announcements Preview -->
                <div class="section-head">
                    <div class="section-title">Recent Announcements</div>
                    <a class="link-action" href="#" data-panel="announcements">View All Announcements →</a>
                </div>
                <div class="preview-grid" id="recentAnnouncementsPreview">
                    <?php
                    $previewAnns = array_slice($dbAnnouncements, 0, 2);
                    if (empty($previewAnns)):
                    ?>
                        <!-- sample previews if no DB data -->
                        <div class="preview-card">
                            <div class="preview-card-head">
                                <div class="preview-card-title">School Resumption Date</div>
                                <span class="badge-pill badge-normal">Normal</span>
                            </div>
                            <div class="preview-card-excerpt">All students are to resume on the 10th of April. Parents are advised to ensure timely preparation.</div>
                            <div class="preview-card-meta">From: The Principal · Mar 28, 2026</div>
                        </div>
                        <div class="preview-card">
                            <div class="preview-card-head">
                                <div class="preview-card-title">Urgent: Early Dismissal Tomorrow</div>
                                <span class="badge-pill badge-urgent">Urgent</span>
                            </div>
                            <div class="preview-card-excerpt">Due to maintenance work, all students will be dismissed at 12 noon tomorrow.</div>
                            <div class="preview-card-meta">From: The Principal · Apr 2, 2026</div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($previewAnns as $pa): ?>
                        <div class="preview-card">
                            <div class="preview-card-head">
                                <div class="preview-card-title"><?php echo e((string)($pa['title'] ?? '')); ?></div>
                                <span class="badge-pill badge-normal"><?php echo e(ucfirst((string)($pa['audience'] ?? ''))); ?></span>
                            </div>
                            <div class="preview-card-excerpt"><?php echo e(mb_substr((string)($pa['message'] ?? ''), 0, 120)); ?></div>
                            <div class="preview-card-meta">From: The Principal · <?php echo e(date('M j, Y', strtotime((string)($pa['published_at'] ?? 'now')))); ?></div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Quick Actions -->
                <div class="section-head" style="margin-top:28px;">
                    <div class="section-title">Quick Actions</div>
                </div>
                <div class="quick-actions">
                    <button class="btn-action" data-quick="principal">💬 Message Principal</button>
                    <button class="btn-action" data-quick="parent">💬 Message a Parent</button>
                </div>

                <?php if ($flash): ?>
                <div style="margin-top:20px;padding:12px 16px;border-radius:var(--radius-sm);background:<?php echo $flash['type']==='error'?'#fee2e2':'#dcfce7'; ?>;color:<?php echo $flash['type']==='error'?'#b91c1c':'#15803d'; ?>;font-weight:600;font-size:.9rem;">
                    <?php echo e((string)($flash['message'] ?? '')); ?>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- ══════════════════════════════
             PANEL: ANNOUNCEMENTS
        ══════════════════════════════ -->
        <section class="panel<?php echo $initialPanel === 'announcements' ? ' active' : ''; ?>" id="panel-announcements">
            <div class="ann-wrap">
                <div class="ann-sticky">
                    <div class="ann-sticky-head">
                        <div class="ann-panel-title">📢 Announcements</div>
                        <div class="ann-result-count" id="annResultCount">Showing <span id="annCount">4</span> announcements</div>
                    </div>
                    <div class="ann-controls">
                        <div class="ann-search-wrap">
                            <span class="ann-search-icon">🔍</span>
                            <input type="text" id="annSearch" placeholder="Search announcements…" autocomplete="off">
                        </div>
                        <select class="ann-filter" id="annFilter">
                            <option value="all">All</option>
                            <option value="urgent">Urgent</option>
                            <option value="normal">Normal</option>
                            <option value="today">Today</option>
                            <option value="week">This Week</option>
                        </select>
                    </div>
                </div>
                <div class="ann-list" id="annList">
                    <!-- Announcement cards injected by JS -->
                </div>
            </div>
        </section>

        <!-- ══════════════════════════════
             PANEL: CHATS
        ══════════════════════════════ -->
        <section class="panel<?php echo $initialPanel === 'chats' ? ' active' : ''; ?>" id="panel-chats">
            <div class="chat-shell">

                <!-- Contact List -->
                <div class="contact-list" id="contactList">
                    <div class="cl-header">
                        <div class="cl-title-row">
                            <div class="cl-title">Messages</div>
                            <span class="cl-unread-badge" id="clUnreadBadge"><?php echo max(0,$unreadMsgCount); ?> unread</span>
                        </div>
                        <div class="cl-search-wrap">
                            <span class="cl-search-icon">🔍</span>
                            <input type="text" id="clSearch" placeholder="Search by name or email…" autocomplete="off">
                        </div>
                    </div>
                    <div class="cl-body" id="clBody">
                        <!-- Sections injected by JS -->
                    </div>
                    <div class="cl-no-results" id="clNoResults">No contacts found</div>
                </div>

                <!-- Chat Window -->
                <div class="chat-window" id="chatWindow">
                    <div class="chat-empty" id="chatEmpty">
                        <div class="chat-empty-icon">💬</div>
                        <h3>Select a contact to start chatting</h3>
                        <p>Search for the Principal or a Parent using the search bar on the left.</p>
                    </div>

                    <div id="chatActive" style="display:none;flex-direction:column;height:100%;overflow:hidden;">
                        <!-- Top Bar -->
                        <div class="chat-topbar" id="chatTopbar">
                            <div class="chat-topbar-avatar" id="ctAvatar">P</div>
                            <div>
                                <div class="chat-topbar-name" id="ctName">Contact</div>
                                <div class="chat-topbar-sub" id="ctSub"></div>
                            </div>
                            <div class="chat-topbar-status" id="ctStatus">
                                <span class="dot offline"></span><span>Offline</span>
                            </div>
                            <div class="chat-topbar-actions">
                                <button class="topbar-icon-btn" id="btnInChatSearch" title="Search in chat">🔍</button>
                                <div class="three-dot-menu" id="threeDotMenu">
                                    <button class="topbar-icon-btn" id="btnThreeDot" title="More options">⋮</button>
                                    <div class="dropdown-menu" id="chatDropdown">
                                        <div class="dropdown-item" id="ddClearChat">Clear Chat</div>
                                        <div class="dropdown-item" id="ddMarkUnread">Mark Unread</div>
                                        <div class="dropdown-item" id="ddViewProfile">View Profile</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- In-chat search -->
                        <div class="in-chat-search" id="inChatSearch">
                            <input type="text" id="inChatSearchInput" placeholder="Search in conversation…">
                            <span class="in-chat-search-count" id="inChatSearchCount"></span>
                            <span class="in-chat-search-close" id="inChatSearchClose">✕</span>
                        </div>

                        <!-- Messages -->
                        <div class="chat-messages" id="chatMessages"></div>

                        <!-- Typing indicator -->
                        <div style="padding:0 24px 4px;">
                            <div class="typing-indicator" id="typingIndicator">
                                <div class="typing-dots">
                                    <div class="typing-dot"></div>
                                    <div class="typing-dot"></div>
                                    <div class="typing-dot"></div>
                                </div>
                                <span style="font-size:.78rem;color:var(--text-soft)" id="typingLabel">typing…</span>
                            </div>
                        </div>

                        <!-- Input Bar -->
                        <div class="chat-input-bar" id="chatInputBar">
                            <span class="chat-input-icon" id="attachIcon" title="Attach file">📎</span>
                            <input type="text" class="chat-input" id="chatInput" placeholder="Type a message…" autocomplete="off">
                            <span class="chat-input-icon" id="emojiIcon" title="Emoji">😊</span>
                            <button class="chat-send-btn" id="chatSendBtn" title="Send">➤</button>
                        </div>
                    </div>
                </div>

            </div>
        </section>

    </main>
</div>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<!-- ═══════════════════════════════════════════════
     PHP DATA → JS VARIABLES
═══════════════════════════════════════════════ -->
<script>
/* ── PHP Data ── */
const TEACHER_NAME    = <?php echo json_encode($teacherFullName, JSON_HEX_TAG); ?>;
const TEACHER_FIRST   = <?php echo json_encode($teacherFirst, JSON_HEX_TAG); ?>;
const TEACHER_INITIAL = <?php echo json_encode($teacherInitial, JSON_HEX_TAG); ?>;
const PRINCIPAL_NAME  = <?php echo json_encode($principalName, JSON_HEX_TAG); ?>;
const PRINCIPAL_MSGS  = <?php echo json_encode($principalMsgData, JSON_HEX_TAG|JSON_HEX_APOS); ?>;
const PARENT_CONTACTS = <?php echo json_encode(array_values($parentContacts), JSON_HEX_TAG|JSON_HEX_APOS); ?>;
const PARENT_MSGS     = <?php echo json_encode($parentMsgData, JSON_HEX_TAG|JSON_HEX_APOS); ?>;
const DB_ANNS         = <?php echo json_encode(array_values($dbAnnouncements), JSON_HEX_TAG|JSON_HEX_APOS); ?>;
const INITIAL_PANEL   = <?php echo json_encode($initialPanel, JSON_HEX_TAG); ?>;
const INITIAL_CONTACT = <?php echo json_encode($initialContact, JSON_HEX_TAG); ?>;
const INITIAL_PID     = <?php echo (int)$initialPid; ?>;
</script>

<script>
/* ════════════════════════════════════════════════
   UTILITY
════════════════════════════════════════════════ */
function esc(str) {
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}
function showToast(msg, type = 'success') {
    const tc = document.getElementById('toastContainer');
    const t = document.createElement('div');
    t.className = 'toast ' + type;
    t.textContent = msg;
    tc.appendChild(t);
    requestAnimationFrame(() => { requestAnimationFrame(() => t.classList.add('show')); });
    setTimeout(() => {
        t.classList.remove('show');
        setTimeout(() => t.remove(), 350);
    }, 3000);
}
function formatDate(ds) {
    const d = new Date(ds + 'T00:00:00');
    const today = new Date(); today.setHours(0,0,0,0);
    const yest  = new Date(today); yest.setDate(yest.getDate()-1);
    if (d.getTime()===today.getTime()) return 'Today';
    if (d.getTime()===yest.getTime())  return 'Yesterday';
    return d.toLocaleDateString('en-NG',{month:'short',day:'numeric',year:'numeric'});
}
function highlight(text, query) {
    if (!query) return esc(text);
    const re = new RegExp('(' + query.replace(/[.*+?^${}()|[\]\\]/g,'\\$&') + ')', 'gi');
    return esc(text).replace(re, '<mark>$1</mark>');
}
function strToColor(str) {
    let hash = 0;
    for (let i = 0; i < str.length; i++) hash = str.charCodeAt(i) + ((hash<<5)-hash);
    const colors = ['#2C7A7B','#8B5CF6','#2563EB','#D97706','#0EA5E9','#16A34A','#DC2626','#4F46E5','#0F766E'];
    return colors[Math.abs(hash) % colors.length];
}

/* ════════════════════════════════════════════════
   GREETING & DATE
════════════════════════════════════════════════ */
(function() {
    const h = new Date().getHours();
    const greet = h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening';
    const el = document.getElementById('greetingMsg');
    if (el) el.textContent = greet + ', ' + TEACHER_FIRST + ' 👋 — Ready to inspire today?';
    const dd = document.getElementById('dashDate');
    if (dd) dd.textContent = new Date().toLocaleDateString('en-NG',{weekday:'long',year:'numeric',month:'long',day:'numeric'});
})();

/* ════════════════════════════════════════════════
   SIDEBAR / MOBILE
════════════════════════════════════════════════ */
const hamburger     = document.getElementById('hamburger');
const sidebarFull   = document.getElementById('sidebar');
const overlay       = document.getElementById('sidebarOverlay');
hamburger.addEventListener('click', () => document.body.classList.toggle('sidebar-open'));
overlay.addEventListener('click',   () => document.body.classList.remove('sidebar-open'));

/* ════════════════════════════════════════════════
   PANEL SWITCHING
════════════════════════════════════════════════ */
function switchPanel(panelId) {
    document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
    const target = document.getElementById('panel-' + panelId);
    if (target) target.classList.add('active');
    document.querySelectorAll('.nav-item[data-panel]').forEach(n => {
        n.classList.toggle('active', n.dataset.panel === panelId);
    });
    document.body.classList.remove('sidebar-open');
    sessionStorage.setItem('td_panel', panelId);
}
document.querySelectorAll('[data-panel]').forEach(el => {
    el.addEventListener('click', e => {
        e.preventDefault();
        switchPanel(el.dataset.panel);
    });
});
// Quick action buttons
document.querySelectorAll('[data-quick]').forEach(btn => {
    btn.addEventListener('click', () => {
        switchPanel('chats');
        setTimeout(() => {
            if (btn.dataset.quick === 'principal') selectContact('principal', null);
            else openFirstParent();
        }, 80);
    });
});

/* ════════════════════════════════════════════════
   ANNOUNCEMENTS
════════════════════════════════════════════════ */
// Sample + DB announcements merged
const SAMPLE_ANNS = [
    {
        id: 'sample-1', title: 'School Resumption Date', audience: 'both', priority: 'normal',
        message: 'All students are expected to resume on the 10th of April 2026. Parents should ensure children arrive before 7:45 AM. Lateness will not be tolerated. Uniforms must be complete and neat.',
        date: '2026-03-28 09:00:00', reactions: {like:2, love:1, wow:0, sad:0}, myReaction: null, comments: [
            {id:'sc1a', name:'Mrs. Johnson', text:'Thank you for the heads up!', time:'Mar 28, 9:15 AM', likes:1, liked:false, initial:'J', color:'#2C7A7B'},
            {id:'sc1b', name:'Mr. Adeyemi', text:'Noted. Will pass this to parents.', time:'Mar 28, 10:02 AM', likes:0, liked:false, initial:'A', color:'#8B5CF6'},
        ]
    },
    {
        id: 'sample-2', title: 'Urgent: Early Dismissal Tomorrow', audience: 'both', priority: 'urgent',
        message: 'Due to urgent maintenance work in the school premises, all pupils will be dismissed at 12 noon tomorrow. Parents are advised to make adequate pickup arrangements in advance. Buses will depart by 11:50 AM.',
        date: '2026-04-02 14:30:00', reactions: {like:3, love:2, wow:0, sad:1}, myReaction: null, comments: [
            {id:'sc2a', name:'Miss Okeke', text:'Will inform parents in my class immediately.', time:'Apr 2, 2:45 PM', likes:2, liked:false, initial:'O', color:'#D97706'},
            {id:'sc2b', name:'Mr. Bello', text:'Got it. Thank you.', time:'Apr 2, 3:10 PM', likes:0, liked:false, initial:'B', color:'#0EA5E9'},
            {id:'sc2c', name:'Mrs. Lawal', text:'What about the after-school club students?', time:'Apr 2, 3:22 PM', likes:1, liked:false, initial:'L', color:'#4F46E5'},
            {id:'sc2d', name:'Mr. Adeyemi', text:'The club is also suspended tomorrow.', time:'Apr 2, 3:35 PM', likes:0, liked:false, initial:'A', color:'#8B5CF6'},
        ]
    },
    {
        id: 'sample-3', title: 'Staff Meeting This Friday', audience: 'teachers', priority: 'normal',
        message: 'All teaching staff are required to attend the monthly staff meeting this Friday at 2:00 PM in the school hall. Attendance is compulsory. Please bring your term review notes.',
        date: '2026-04-01 10:00:00', reactions: {like:2, love:0, wow:0, sad:0}, myReaction: null, comments: [
            {id:'sc3a', name:'Miss Okeke', text:'Will be there. Should we bring attendance records too?', time:'Apr 1, 10:30 AM', likes:0, liked:false, initial:'O', color:'#D97706'},
        ]
    },
    {
        id: 'sample-4', title: 'Mid-Term Break Notice', audience: 'both', priority: 'normal',
        message: 'Mid-term break will commence on the 14th of April and students are expected to resume on the 22nd of April. Teachers should submit mark sheets before break commences.',
        date: '2026-04-03 08:00:00', reactions: {like:4, love:2, wow:0, sad:0}, myReaction: null, comments: [
            {id:'sc4a', name:'Mr. Bello', text:'Thanks for the notice. Will prepare mark sheets.', time:'Apr 3, 8:20 AM', likes:0, liked:false, initial:'B', color:'#0EA5E9'},
            {id:'sc4b', name:'Mrs. Lawal', text:'When is the deadline for mark sheets?', time:'Apr 3, 9:00 AM', likes:1, liked:false, initial:'L', color:'#4F46E5'},
            {id:'sc4c', name:'Miss Okeke', text:'I believe it\'s Friday before we leave.', time:'Apr 3, 9:15 AM', likes:0, liked:false, initial:'O', color:'#D97706'},
        ]
    }
];

// Merge DB announcements before samples
function buildAnnData() {
    const data = [];
    if (DB_ANNS && DB_ANNS.length > 0) {
        DB_ANNS.forEach((a,i) => {
            data.push({
                id: 'db-' + i,
                title: a.title || 'Announcement',
                audience: a.audience || 'both',
                priority: 'normal',
                message: a.message || '',
                date: a.published_at || new Date().toISOString(),
                reactions: {like:0, love:0, wow:0, sad:0},
                myReaction: null,
                comments: []
            });
        });
    } else {
        SAMPLE_ANNS.forEach(a => data.push(Object.assign({}, a, {
            reactions: Object.assign({}, a.reactions),
            comments: a.comments.map(c => Object.assign({}, c))
        })));
    }
    return data;
}

let annData      = buildAnnData();
let annSearchVal = '';
let annFilterVal = 'all';

// Load reactions from sessionStorage
function loadReactionState() {
    try {
        const saved = JSON.parse(sessionStorage.getItem('td_reactions') || '{}');
        annData.forEach(a => {
            if (saved[a.id]) {
                a.myReaction = saved[a.id].myReaction || null;
                if (saved[a.id].reactions) {
                    Object.keys(saved[a.id].reactions).forEach(k => {
                        if (a.reactions.hasOwnProperty(k)) a.reactions[k] = saved[a.id].reactions[k];
                    });
                }
            }
        });
    } catch(e) {}
}
function saveReactionState(annId) {
    try {
        const saved = JSON.parse(sessionStorage.getItem('td_reactions') || '{}');
        const ann = annData.find(a => a.id === annId);
        if (ann) saved[ann.id] = { myReaction: ann.myReaction, reactions: ann.reactions };
        sessionStorage.setItem('td_reactions', JSON.stringify(saved));
    } catch(e) {}
}

loadReactionState();

function isPriorityUrgent(a) { return a.priority === 'urgent'; }
function isToday(dateStr) {
    const d = new Date(dateStr); const t = new Date();
    return d.getFullYear()===t.getFullYear() && d.getMonth()===t.getMonth() && d.getDate()===t.getDate();
}
function isThisWeek(dateStr) {
    const d = new Date(dateStr); const t = new Date();
    const weekAgo = new Date(t); weekAgo.setDate(weekAgo.getDate()-7);
    return d >= weekAgo && d <= t;
}
function audienceLabel(aud) {
    if (aud==='both' || aud==='everyone') return {text:'Everyone', cls:'badge-everyone'};
    if (aud==='teachers') return {text:'Teachers Only', cls:'badge-teachers'};
    if (aud==='parents')  return {text:'Parents Only', cls:'badge-parents'};
    return {text: aud, cls:'badge-everyone'};
}

function renderAnnouncements() {
    const list  = document.getElementById('annList');
    const q     = annSearchVal.trim().toLowerCase();
    const f     = annFilterVal;

    let visible = annData.filter(a => {
        const matchQ = !q || a.title.toLowerCase().includes(q) || a.message.toLowerCase().includes(q);
        const matchF = f==='all' ? true
            : f==='urgent' ? isPriorityUrgent(a)
            : f==='normal' ? !isPriorityUrgent(a)
            : f==='today'  ? isToday(a.date)
            : f==='week'   ? isThisWeek(a.date)
            : true;
        return matchQ && matchF;
    });

    document.getElementById('annCount').textContent = visible.length;
    list.innerHTML = '';

    if (visible.length === 0) {
        list.innerHTML = '<p style="color:var(--text-soft);text-align:center;padding:40px 0;">No announcements match your search.</p>';
        return;
    }

    visible.forEach(ann => {
        const aud   = audienceLabel(ann.audience);
        const total = Object.values(ann.reactions).reduce((s,v)=>s+v,0);
        const dateD = new Date(ann.date);
        const dStr  = dateD.toLocaleDateString('en-NG',{day:'numeric',month:'short',year:'numeric'}) + ' · ' + dateD.toLocaleTimeString('en-NG',{hour:'2-digit',minute:'2-digit'});

        const card  = document.createElement('div');
        card.className = 'ann-card';
        card.dataset.annId = ann.id;

        const titleH = q ? highlight(ann.title, annSearchVal.trim()) : esc(ann.title);
        const msgH   = q ? highlight(ann.message, annSearchVal.trim()) : esc(ann.message).replace(/\n/g,'<br>');

        card.innerHTML = `
        <div class="ann-card-header">
            <div>
                <div class="ann-title">${titleH}</div>
                <div class="ann-badges">
                    <span class="badge-pill ${aud.cls}">${esc(aud.text)}</span>
                    <span class="badge-pill ${ann.priority==='urgent'?'badge-urgent':'badge-normal'}">${ann.priority==='urgent'?'🔴 Urgent':'⚪ Normal'}</span>
                </div>
            </div>
            <div class="ann-header-right">
                <div class="ann-date">${esc(dStr)}</div>
                <div class="ann-from"><div class="ann-from-avatar">P</div>From: The Principal</div>
            </div>
        </div>
        <div class="ann-card-body">
            <div class="ann-body-inner" id="body-${ann.id}">
                <div class="ann-body-text">${msgH}</div>
            </div>
            <button class="ann-toggle-btn" id="toggle-${ann.id}" onclick="toggleAnnBody('${ann.id}')">Read more ▾</button>
        </div>
        <div class="ann-reaction-bar">
            ${['like','love','wow','sad'].map(r => {
                const emojis = {like:'👍',love:'❤️',wow:'😮',sad:'😢'};
                const cnt    = ann.reactions[r] || 0;
                const active = ann.myReaction === r ? ' active' : '';
                return `<button class="reaction-btn${active}" data-ann="${ann.id}" data-r="${r}">
                    <span class="reaction-emoji">${emojis[r]}</span>
                    <span class="reaction-count">${cnt}</span>
                </button>`;
            }).join('')}
            <span class="reaction-total">${total > 0 ? total + ' reaction'+(total!==1?'s':'') : ''}</span>
        </div>
        <div class="ann-comment-section">
            <button class="comment-toggle-btn" onclick="toggleComments('${ann.id}')">
                💬 Comments (${ann.comments.length})
            </button>
            <div class="comment-thread" id="thread-${ann.id}">
                <div class="comment-thread-inner" id="comments-${ann.id}">
                    ${(ann.comments || []).map(c => renderComment(c)).join('')}
                </div>
                <div class="comment-input-wrap">
                    <div class="comment-textarea-box">
                        <textarea class="comment-textarea" id="cta-${ann.id}" placeholder="Write a comment…" maxlength="300" rows="2"
                            oninput="updateCharCount('${ann.id}',this.value.length); autoResize(this);"
                            onkeydown="commentKeydown(event,'${ann.id}')"></textarea>
                    </div>
                    <div class="comment-footer">
                        <span class="comment-char-count" id="cc-${ann.id}">0 / 300</span>
                        <button class="btn-post-comment" onclick="postComment('${ann.id}')">Post</button>
                    </div>
                </div>
            </div>
        </div>
        `;
        list.appendChild(card);

        // Check if body needs toggle
        requestAnimationFrame(() => {
            const bodyEl = document.getElementById('body-' + ann.id);
            const toggleEl = document.getElementById('toggle-' + ann.id);
            if (bodyEl && toggleEl) {
                if (bodyEl.scrollHeight <= bodyEl.clientHeight + 4) {
                    toggleEl.style.display = 'none';
                }
            }
        });
    });

    // Reaction click
    document.querySelectorAll('.reaction-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const annId = btn.dataset.ann;
            const r     = btn.dataset.r;
            const ann   = annData.find(a => a.id === annId);
            if (!ann) return;
            if (ann.myReaction === r) {
                ann.reactions[r] = Math.max(0, (ann.reactions[r]||0) - 1);
                ann.myReaction = null;
            } else {
                if (ann.myReaction) ann.reactions[ann.myReaction] = Math.max(0,(ann.reactions[ann.myReaction]||0)-1);
                ann.reactions[r] = (ann.reactions[r]||0)+1;
                ann.myReaction = r;
            }
            saveReactionState(annId);
            renderAnnouncements();
            showToast('✅ Reaction saved', 'success');
        });
    });
}

function toggleAnnBody(annId) {
    const body = document.getElementById('body-' + annId);
    const btn  = document.getElementById('toggle-' + annId);
    if (!body || !btn) return;
    const expanded = body.classList.toggle('expanded');
    btn.textContent = expanded ? 'Show less ▴' : 'Read more ▾';
}
function toggleComments(annId) {
    const thread = document.getElementById('thread-' + annId);
    if (thread) thread.classList.toggle('open');
}
function renderComment(c) {
    return `<div class="comment-item" id="cmt-${esc(c.id)}">
        <div class="comment-avatar" style="background:${esc(c.color||'#2C7A7B')}">${esc(c.initial||'T')}</div>
        <div class="comment-content">
            <div class="comment-name">${esc(c.name)}</div>
            <div class="comment-text">${esc(c.text)}</div>
            <div class="comment-meta">
                <span>${esc(c.time)}</span>
                <button class="comment-like-btn${c.liked?' liked':''}" data-cmt="${esc(c.id)}" onclick="likeComment('${esc(c.id)}')">
                    👍 <span class="cmt-like-count">${c.likes||0}</span>
                </button>
            </div>
        </div>
    </div>`;
}
function likeComment(cid) {
    for (const ann of annData) {
        const c = ann.comments.find(x => x.id === cid);
        if (c) {
            c.liked = !c.liked;
            c.likes = Math.max(0, (c.liked ? (c.likes||0)+1 : (c.likes||0)-1));
            renderAnnouncements();
            return;
        }
    }
}
function updateCharCount(annId, len) {
    const el = document.getElementById('cc-' + annId);
    if (el) el.textContent = len + ' / 300';
}
function autoResize(ta) {
    ta.style.height = 'auto';
    ta.style.height = Math.min(ta.scrollHeight, 180) + 'px';
}
function commentKeydown(e, annId) {
    if (e.ctrlKey && e.key === 'Enter') { e.preventDefault(); postComment(annId); }
}
let commentSeq = 100;
function postComment(annId) {
    const ta  = document.getElementById('cta-' + annId);
    if (!ta) return;
    const txt = ta.value.trim();
    if (!txt) { showToast('⚠️ Comment cannot be empty', 'warn'); return; }
    const ann = annData.find(a => a.id === annId);
    if (!ann) return;
    const newC = {
        id:     'new-' + (++commentSeq),
        name:   TEACHER_NAME,
        text:   txt,
        time:   'Just now',
        likes:  0,
        liked:  false,
        initial: TEACHER_INITIAL,
        color:  strToColor(TEACHER_NAME),
    };
    ann.comments.unshift(newC);
    ta.value = '';
    ta.style.height = '';
    updateCharCount(annId, 0);
    renderAnnouncements();
    // Open thread
    const thread = document.getElementById('thread-' + annId);
    if (thread) thread.classList.add('open');
    showToast('✅ Comment posted', 'success');
}

// Search & Filter
document.getElementById('annSearch').addEventListener('input', function() {
    annSearchVal = this.value;
    renderAnnouncements();
});
document.getElementById('annFilter').addEventListener('change', function() {
    annFilterVal = this.value;
    renderAnnouncements();
});

renderAnnouncements();


/* ════════════════════════════════════════════════
   CHAT
════════════════════════════════════════════════ */
const PARENT_PALETTE = ['#16A34A','#D97706','#7C3AED','#0F766E','#DC2626','#4F46E5'];

// Sample parents when DB empty
const SAMPLE_PARENTS = [
    {id:'sp-1',name:'Mrs. Bello',email:'bello@gmail.com',child:'Amina Bello',online:true, initial:'B',color:'#16A34A',unread:2,preview:'Please when is the next PTA?',timestamp:'9:12 AM'},
    {id:'sp-2',name:'Mr. Okonkwo',email:'okonkwo@gmail.com',child:'Chidi Okonkwo',online:false,initial:'O',color:'#D97706',unread:0,preview:'Thank you teacher.',timestamp:'Yesterday'},
    {id:'sp-3',name:'Mrs. Fadipe',email:'fadipepar@gmail.com',child:'Tolu Fadipe',online:true, initial:'F',color:'#7C3AED',unread:1,preview:'Noted. We will comply.',timestamp:'Mon'},
    {id:'sp-4',name:'Mr. Musa',email:'musa@gmail.com',child:'Yusuf Musa',online:false,initial:'M',color:'#0F766E',unread:0,preview:'Good morning.',timestamp:''},
    {id:'sp-5',name:'Mrs. Eze',email:'eze@gmail.com',child:'Ngozi Eze',online:true, initial:'E',color:'#DC2626',unread:1,preview:'My daughter will be absent tomorrow.',timestamp:'3:44 PM'},
    {id:'sp-6',name:'Mr. Suleiman',email:'suleiman@gmail.com',child:'Aisha Suleiman',online:false,initial:'S',color:'#4F46E5',unread:0,preview:'No messages yet.',timestamp:''},
];

// Sample messages per contact
const SAMPLE_PARENT_MSGS = {
    'sp-1': [
        {from:'them',text:'Good morning Teacher! How is Amina performing?',time:'9:00 AM',date:'2026-04-04'},
        {from:'me',  text:'Good morning Mrs. Bello! Amina is doing very well. She is attentive and participates actively.',time:'9:05 AM',date:'2026-04-04'},
        {from:'them',text:'That is great to hear! Please when is the next PTA meeting?',time:'9:12 AM',date:'2026-04-04'},
    ],
    'sp-2': [
        {from:'them',text:'Good day Teacher. I wanted to check on Chidi\'s progress.',time:'8:30 AM',date:'2026-04-03'},
        {from:'me',  text:'Hello Mr. Okonkwo. Chidi is improving. We should work a bit more on his reading.',time:'8:45 AM',date:'2026-04-03'},
        {from:'them',text:'Thank you teacher.',time:'9:00 AM',date:'2026-04-03'},
    ],
    'sp-3': [
        {from:'me',  text:'Dear Mrs. Fadipe, Tolu has been performing well in class. Keep it up!',time:'2:00 PM',date:'2026-04-02'},
        {from:'them',text:'Noted. We will comply.',time:'2:30 PM',date:'2026-04-02'},
    ],
    'sp-4': [
        {from:'them',text:'Good morning.',time:'8:00 AM',date:'2026-04-01'},
        {from:'me',  text:'Good morning Mr. Musa! How can I help you today?',time:'8:10 AM',date:'2026-04-01'},
    ],
    'sp-5': [
        {from:'them',text:'Good afternoon Teacher. My daughter will be absent tomorrow due to medical appointment.',time:'3:44 PM',date:'2026-04-04'},
        {from:'me',  text:'Noted, Mrs. Eze. Please send a written excuse when she returns.',time:'3:50 PM',date:'2026-04-04'},
    ],
    'sp-6': [],
};

const SAMPLE_PRINCIPAL_MSGS = [
    {from:'them',text:'Good morning! Please ensure all mark sheets are submitted before the break.',time:'8:00 AM',date:'2026-04-03'},
    {from:'me',  text:'Good morning, I will have them ready by Thursday.',time:'8:20 AM',date:'2026-04-03'},
    {from:'them',text:'Thank you. Also, staff meeting is this Friday at 2 PM.',time:'8:25 AM',date:'2026-04-03'},
    {from:'me',  text:'Understood. I\'ll be there.',time:'8:30 AM',date:'2026-04-03'},
    {from:'them',text:'Great. Have a productive day!',time:'8:32 AM',date:'2026-04-03'},
];

// Resolve data sources
const useDBParents = PARENT_CONTACTS && PARENT_CONTACTS.length > 0;
const useDBPrincipalMsgs = PRINCIPAL_MSGS && PRINCIPAL_MSGS.length > 0;

let contacts = {
    principal: {
        id: 'principal',
        name: PRINCIPAL_NAME,
        email: 'principal@samacetech.edu.ng',
        role: 'Principal',
        initial: 'P',
        color: '#0D4B4B',
        online: true,
        unread: 0,
        preview: useDBPrincipalMsgs && PRINCIPAL_MSGS.length > 0 ? PRINCIPAL_MSGS[PRINCIPAL_MSGS.length-1].text.substring(0,50) : 'No messages yet.',
        timestamp: '',
        messages: (useDBPrincipalMsgs && PRINCIPAL_MSGS.length > 0) ? PRINCIPAL_MSGS : SAMPLE_PRINCIPAL_MSGS,
    },
    parents: []
};

if (useDBParents) {
    PARENT_CONTACTS.forEach((p,i) => {
        const pid = String(p.id);
        const dbMsgs = PARENT_MSGS && PARENT_MSGS[pid] ? PARENT_MSGS[pid] : [];
        contacts.parents.push({
            id: pid,
            name: p.name,
            email: p.email,
            child: p.child,
            initial: p.initial,
            color: p.color || PARENT_PALETTE[i % PARENT_PALETTE.length],
            online: p.online || false,
            unread: p.unread || 0,
            preview: dbMsgs.length > 0 ? dbMsgs[dbMsgs.length-1].text.substring(0,50) : (p.preview || 'No messages yet.'),
            timestamp: p.timestamp || '',
            messages: dbMsgs.length > 0 ? dbMsgs : [],
        });
    });
} else {
    SAMPLE_PARENTS.forEach(p => {
        const msgs = SAMPLE_PARENT_MSGS[p.id] || [];
        contacts.parents.push(Object.assign({}, p, {messages: msgs}));
    });
}

let activeContact = null;
let clSearchQuery = '';

function buildContactList(query) {
    const body = document.getElementById('clBody');
    const noRes = document.getElementById('clNoResults');
    body.innerHTML = '';
    const q = query ? query.toLowerCase().trim() : '';

    let totalVisible = 0;

    // Principal section
    const princMatch = !q || contacts.principal.name.toLowerCase().includes(q) || contacts.principal.email.toLowerCase().includes(q);
    const princSection = buildSection('principal-sec', '🏫 PRINCIPAL (1)', [contacts.principal], q, ['principal'], princMatch);
    if (princMatch) totalVisible++;

    // Parents section
    const filteredParents = contacts.parents.filter(p => !q || p.name.toLowerCase().includes(q) || p.email.toLowerCase().includes(q) || (p.child||'').toLowerCase().includes(q));
    totalVisible += filteredParents.length;
    const parentsSection = buildSection('parents-sec', '👨‍👩‍👧 PARENTS (' + contacts.parents.length + ')', filteredParents, q, [], true);

    if (princMatch) body.appendChild(princSection);
    if (filteredParents.length > 0) body.appendChild(parentsSection);

    noRes.style.display = totalVisible === 0 ? 'block' : 'none';
}

function buildSection(secId, label, list, q, types, show) {
    const sec  = document.createElement('div');
    sec.className = 'cl-section';
    sec.id = secId;

    const head = document.createElement('div');
    head.className = 'cl-section-head';
    head.innerHTML = `<span class="cl-section-label">${esc(label)}</span><span class="cl-section-chevron">▾</span>`;
    head.addEventListener('click', () => { sec.classList.toggle('collapsed'); });

    const cont = document.createElement('div');
    cont.className = 'cl-contacts';
    cont.style.maxHeight = '9999px';

    list.forEach((c, idx) => {
        const row = buildContactRow(c, q);
        cont.appendChild(row);
    });

    sec.appendChild(head);
    sec.appendChild(cont);
    return sec;
}

function buildContactRow(c, q) {
    const row = document.createElement('div');
    row.className = 'contact-row' + (activeContact && activeContact.id === c.id ? ' active' : '');
    row.dataset.id = c.id;

    const nameH = q ? highlight(c.name, q) : esc(c.name);
    const emailH = q ? highlight(c.email||'', q) : esc(c.email||'');
    const tagHtml = c.child
        ? `<span class="contact-child-tag">Child: ${q ? highlight(c.child,q) : esc(c.child)}</span>`
        : (c.role ? `<span class="contact-role-tag">${esc(c.role)}</span>` : '');

    row.innerHTML = `
        <div class="contact-avatar" style="background:${esc(c.color||'#0D4B4B')}">
            ${esc(c.initial||'?')}
            <span class="online-dot ${c.online?'online':'offline'}"></span>
        </div>
        <div class="contact-info">
            <div class="contact-name">${nameH}</div>
            <div class="contact-sub">${emailH}</div>
            ${tagHtml}
        </div>
        <div class="contact-meta">
            ${c.timestamp ? `<span class="contact-time">${esc(c.timestamp)}</span>` : ''}
            ${c.unread > 0 ? `<span class="contact-unread">${c.unread}</span>` : ''}
            <div class="contact-preview">${esc((c.preview||'').substring(0,40))}</div>
        </div>
    `;
    row.addEventListener('click', () => selectContact(c.id, null));
    return row;
}

function selectContact(contactId, parentId) {
    let c = null;
    if (contactId === 'principal') {
        c = contacts.principal;
    } else {
        c = contacts.parents.find(p => p.id === String(contactId || parentId));
    }
    if (!c) {
        // fallback: try parents by index
        if (contacts.parents.length > 0) c = contacts.parents[0];
        else return;
    }

    activeContact = c;
    c.unread = 0;

    sessionStorage.setItem('td_last_contact', c.id);

    // Rebuild list to update active state
    buildContactList(clSearchQuery);

    // Show chat window
    document.getElementById('chatEmpty').style.display = 'none';
    const chatAct = document.getElementById('chatActive');
    chatAct.style.display = 'flex';

    // Top bar
    const av = document.getElementById('ctAvatar');
    av.textContent = c.initial || '?';
    av.style.background = c.color || '#0D4B4B';

    document.getElementById('ctName').textContent = c.name;
    const sub = document.getElementById('ctSub');
    if (c.child) sub.innerHTML = `Child: <strong>${esc(c.child)}</strong> · <a href="mailto:${esc(c.email||'')}">${esc(c.email||'')}</a>`;
    else sub.innerHTML = `<a href="mailto:${esc(c.email||'')}">${esc(c.email||'')}</a>`;

    const st = document.getElementById('ctStatus');
    st.innerHTML = `<span class="dot ${c.online?'online':'offline'}"></span><span>${c.online?'Online':'Offline'}</span>`;

    // Input placeholder
    document.getElementById('chatInput').placeholder = 'Type a message to ' + c.name + '…';

    renderMessages();

    // Mobile: hide contact list
    if (window.innerWidth <= 768) {
        document.getElementById('contactList').classList.add('mobile-hidden');
        document.getElementById('chatWindow').classList.remove('mobile-hidden');
    }
}

function openFirstParent() {
    if (contacts.parents.length > 0) selectContact(contacts.parents[0].id, null);
}

function renderMessages(searchStr) {
    if (!activeContact) return;
    const area = document.getElementById('chatMessages');
    area.innerHTML = '';

    const msgs = activeContact.messages || [];
    if (msgs.length === 0) {
        area.innerHTML = '<p style="text-align:center;color:var(--text-soft);padding:40px 0;font-size:.9rem;">No messages yet. Start the conversation!</p>';
        return;
    }

    let lastDate = '';
    let lastFrom = '';

    msgs.forEach((m, idx) => {
        // Date divider
        const dateLabel = formatDate(m.date || new Date().toISOString().split('T')[0]);
        if (dateLabel !== lastDate) {
            const div = document.createElement('div');
            div.className = 'date-divider';
            div.innerHTML = `<span>${esc(dateLabel)}</span>`;
            area.appendChild(div);
            lastDate = dateLabel;
            lastFrom = '';
        }

        const isSent = m.from === 'me';
        const senderName = isSent ? 'You' : activeContact.name;
        const showLabel  = m.from !== lastFrom;
        lastFrom = m.from;

        const grp = document.createElement('div');
        grp.className = 'msg-group ' + (isSent ? 'sent' : 'received');

        if (showLabel) {
            const lbl = document.createElement('div');
            lbl.className = 'msg-sender-label';
            lbl.textContent = senderName;
            grp.appendChild(lbl);
        }

        const bubble = document.createElement('div');
        bubble.className = 'bubble ' + (isSent ? 'sent' : 'received');
        bubble.dataset.msgIdx = idx;

        let textContent = m.text;
        if (searchStr) {
            const re = new RegExp('(' + searchStr.replace(/[.*+?^${}()|[\]\\]/g,'\\$&') + ')', 'gi');
            if (!re.test(textContent)) {
                grp.style.opacity = '0.2';
            } else {
                bubble.innerHTML = highlight(textContent, searchStr);
            }
        }
        if (!searchStr || bubble.innerHTML === '') {
            bubble.textContent = textContent;
        }

        const meta = document.createElement('div');
        meta.className = 'bubble-meta' + (isSent ? '' : ' recv');
        if (isSent) {
            const tickId = 'tick-' + idx;
            meta.innerHTML = `<span class="bubble-time">${esc(m.time||'')}</span><span class="checkmarks" id="${tickId}">✓✓</span>`;
            // Turn ticks blue after 2s
            setTimeout(() => { const t = document.getElementById(tickId); if(t) t.classList.add('read'); }, 2000);
        } else {
            meta.innerHTML = `<span class="bubble-time dark">${esc(m.time||'')}</span>`;
        }
        bubble.appendChild(meta);

        grp.appendChild(bubble);
        area.appendChild(grp);
    });

    // Scroll to bottom
    area.scrollTop = area.scrollHeight;
}

// Send message
function sendMessage() {
    if (!activeContact) { showToast('⚠️ Select a contact first', 'warn'); return; }
    const input = document.getElementById('chatInput');
    const text  = input.value.trim();
    if (!text) { showToast('⚠️ Please type a message first', 'warn'); return; }

    const now = new Date();
    const timeStr = now.toLocaleTimeString('en-NG',{hour:'2-digit',minute:'2-digit'});
    const dateStr = now.toISOString().split('T')[0];

    activeContact.messages = activeContact.messages || [];
    activeContact.messages.push({from:'me', text, time:timeStr, date:dateStr});
    activeContact.preview = text;
    activeContact.timestamp = timeStr;

    input.value = '';
    renderMessages();

    // Show typing indicator
    document.getElementById('typingIndicator').classList.add('show');
    document.getElementById('typingLabel').textContent = activeContact.name + ' is typing…';

    // Simulated reply
    const replyPool = [
        'Thank you for letting me know.',
        'Understood! I will follow up shortly.',
        'Noted. We appreciate your dedication.',
        'Thanks for the update!',
        'I see, I will look into this.',
        'Message received. Have a great day!',
        'Thank you, Teacher!',
    ];
    const reply = replyPool[Math.floor(Math.random() * replyPool.length)];
    setTimeout(() => {
        document.getElementById('typingIndicator').classList.remove('show');
        const t2 = new Date().toLocaleTimeString('en-NG',{hour:'2-digit',minute:'2-digit'});
        activeContact.messages.push({from:'them', text:reply, time:t2, date:new Date().toISOString().split('T')[0]});
        renderMessages();
    }, 1500);

    buildContactList(clSearchQuery);
}

document.getElementById('chatSendBtn').addEventListener('click', sendMessage);
document.getElementById('chatInput').addEventListener('keydown', e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); } });
document.getElementById('attachIcon').addEventListener('click', () => showToast('📎 File attachment coming soon', 'success'));
document.getElementById('emojiIcon').addEventListener('click', () => showToast('😊 Emoji picker coming soon', 'success'));

// Contact list search
document.getElementById('clSearch').addEventListener('input', function() {
    clSearchQuery = this.value;
    buildContactList(this.value);
});

// In-chat search
document.getElementById('btnInChatSearch').addEventListener('click', () => {
    const bar = document.getElementById('inChatSearch');
    bar.classList.toggle('open');
    if (bar.classList.contains('open')) document.getElementById('inChatSearchInput').focus();
    else { document.getElementById('inChatSearchInput').value = ''; renderMessages(); document.getElementById('inChatSearchCount').textContent = ''; }
});
document.getElementById('inChatSearchInput').addEventListener('input', function() {
    const q = this.value.trim();
    if (!activeContact) return;
    if (!q) { renderMessages(); document.getElementById('inChatSearchCount').textContent = ''; return; }
    // Count matches
    let count = 0;
    (activeContact.messages||[]).forEach(m => { if (m.text.toLowerCase().includes(q.toLowerCase())) count++; });
    document.getElementById('inChatSearchCount').textContent = count + ' result' + (count!==1?'s':'');
    renderMessages(q);
});
document.getElementById('inChatSearchClose').addEventListener('click', () => {
    document.getElementById('inChatSearch').classList.remove('open');
    document.getElementById('inChatSearchInput').value = '';
    document.getElementById('inChatSearchCount').textContent = '';
    renderMessages();
});

// Three-dot dropdown
document.getElementById('btnThreeDot').addEventListener('click', e => {
    e.stopPropagation();
    document.getElementById('chatDropdown').classList.toggle('open');
});
document.addEventListener('click', () => document.getElementById('chatDropdown').classList.remove('open'));
document.getElementById('ddClearChat').addEventListener('click', () => {
    if (activeContact) {
        activeContact.messages = [];
        renderMessages();
        buildContactList(clSearchQuery);
        showToast('🗑️ Chat cleared', 'success');
    }
    document.getElementById('chatDropdown').classList.remove('open');
});
document.getElementById('ddMarkUnread').addEventListener('click', () => {
    showToast('📬 Marked as unread', 'success');
    document.getElementById('chatDropdown').classList.remove('open');
});
document.getElementById('ddViewProfile').addEventListener('click', () => {
    if (activeContact) showToast('👤 Profile: ' + activeContact.name, 'success');
    document.getElementById('chatDropdown').classList.remove('open');
});

// Mobile back button via contact list click
window.addEventListener('resize', () => {
    if (window.innerWidth > 768) {
        document.getElementById('contactList').classList.remove('mobile-hidden');
        document.getElementById('chatWindow').classList.remove('mobile-hidden');
    }
});

// Init contact list
buildContactList('');

// Auto-open contact if URL param
(function() {
    if (INITIAL_CONTACT === 'principal') {
        selectContact('principal', null);
    } else if (INITIAL_CONTACT === 'parent') {
        if (INITIAL_PID > 0) selectContact(String(INITIAL_PID), null);
        else openFirstParent();
    } else {
        // Restore last contact from sessionStorage
        const last = sessionStorage.getItem('td_last_contact');
        if (last) selectContact(last, null);
    }
})();

// Update stat
document.getElementById('statComments').textContent = annData.reduce((s,a)=>s+(a.comments.length),0);

// Restore panel on load (URL takes priority over sessionStorage)
(function() {
    const saved = sessionStorage.getItem('td_panel');
    if (!INITIAL_PANEL || INITIAL_PANEL === 'dashboard') {
        if (saved && ['dashboard','announcements','chats'].includes(saved)) {
            switchPanel(saved);
        } else {
            switchPanel('dashboard');
        }
    } else {
        switchPanel(INITIAL_PANEL);
    }
})();
</script>
</body>
</html>
