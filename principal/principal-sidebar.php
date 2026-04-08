<?php
declare(strict_types=1);

$principalSidebarActive = $principalSidebarActive ?? 'dashboard';
$chatUnreadCount = isset($chatUnreadCount) ? (int) $chatUnreadCount : 0;
$pendingTeacherCount = isset($pendingTeacherCount) ? (int) $pendingTeacherCount : 0;
$pendingParentCount = isset($pendingParentCount) ? (int) $pendingParentCount : 0;
$pendingLeaveRequestCount = isset($pendingLeaveRequestCount) ? (int) $pendingLeaveRequestCount : 0;
$principalDisplayName = isset($principalName) && trim((string) $principalName) !== ''
    ? trim((string) $principalName)
    : display_name_from_user($principal ?? [], 'Principal');
?>
<aside class="sidebar" id="sidebar">
    <div>
        <div class="sidebar-logo">
            <span class="sidebar-school-name">SAMACE TECH LAB</span>
            <span class="sidebar-school-sub">Nursery &amp; Primary School</span>
            <span class="sidebar-role-badge">🎓 Principal Portal</span>
        </div>

        <div class="nav-section-label">Navigation</div>
        <nav>
            <a class="<?php echo $principalSidebarActive === 'dashboard' ? 'active' : ''; ?>" href="principal-dashboard.php"><span class="nav-icon">🏠</span><span class="nav-label">Dashboard</span></a>
            <a class="<?php echo $principalSidebarActive === 'attendance' ? 'active' : ''; ?>" href="principal-dashboard.php?panel=attendancePanel"><span class="nav-icon">🕒</span><span class="nav-label">Teachers Attendance</span></a>
            <a class="<?php echo $principalSidebarActive === 'leave' ? 'active' : ''; ?>" href="principal-dashboard.php?panel=leavePanel"><span class="nav-icon">🗓️</span><span class="nav-label">Leave Requests</span><?php if ($pendingLeaveRequestCount > 0): ?><span class="nav-badge"><?php echo e((string) $pendingLeaveRequestCount); ?></span><?php endif; ?></a>
            <a class="<?php echo $principalSidebarActive === 'chats' ? 'active' : ''; ?>" href="chat.php"><span class="nav-icon">💬</span><span class="nav-label">Chats</span><?php if ($chatUnreadCount > 0): ?><span class="nav-badge"><?php echo e((string) $chatUnreadCount); ?></span><?php endif; ?></a>
            <a class="<?php echo $principalSidebarActive === 'announcements' ? 'active' : ''; ?>" href="principalent.php"><span class="nav-icon">📢</span><span class="nav-label">Announcements</span></a>
            <a class="<?php echo $principalSidebarActive === 'teachers' ? 'active' : ''; ?>" href="principal-dashboard.php?panel=teachersPanel"><span class="nav-icon">👩‍🏫</span><span class="nav-label">Teachers</span><?php if ($pendingTeacherCount > 0): ?><span class="nav-badge">+<?php echo e((string) $pendingTeacherCount); ?></span><?php endif; ?></a>
            <a class="<?php echo $principalSidebarActive === 'parents' ? 'active' : ''; ?>" href="principal-dashboard.php?panel=parentsPanel"><span class="nav-icon">👨‍👩‍👧</span><span class="nav-label">Parents</span><?php if ($pendingParentCount > 0): ?><span class="nav-badge">+<?php echo e((string) $pendingParentCount); ?></span><?php endif; ?></a>
            <a class="<?php echo $principalSidebarActive === 'profile' ? 'active' : ''; ?>" href="profile.php"><span class="nav-icon">👤</span><span class="nav-label">Profile</span></a>
            <a class="logout-link" href="logout.php"><span class="nav-icon">🚪</span><span class="nav-label">Logout</span></a>
        </nav>
    </div>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <?php echo render_avatar_html($principal, 'sidebar-avatar', 'Principal'); ?>
            <div>
                <span class="sidebar-user-name"><?php echo e($principalDisplayName); ?></span>
                <span class="sidebar-user-role">Principal</span>
            </div>
        </div>
    </div>
</aside>