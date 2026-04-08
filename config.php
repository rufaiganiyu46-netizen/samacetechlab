<?php
session_start();

mysqli_report(MYSQLI_REPORT_OFF);

define('DB_HOST', '5as1d0.h.filess.io');
define('DB_USER', 'schoolportal_db_strangerso');
define('DB_PASS', '2da7c588d3861c4cc9871c506c892e2a5926a9fd');
define('DB_NAME', 'schoolportal_db_strangerso');
define('DB_PORT', 3306);

if (!class_exists('mysqli')) {
    $db_error = 'MySQLi is not enabled in this PHP environment.';
} else {
    $db = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, 3306);
    if ($db->connect_error) {
        $db_error = 'MySQL server connection failed. Please confirm your MySQL service is running and your credentials are correct.';
        $db = null;
    } else {
        if (!$db->select_db(DB_NAME)) {
            $db_error = 'Database "' . DB_NAME . '" was not found. Please import db_setup.sql first.';
            $db->close();
            $db = null;
        } else {
            $db->set_charset('utf8mb4');
            $db->query("CREATE TABLE IF NOT EXISTS users (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                full_name VARCHAR(150) NOT NULL,
                first_name VARCHAR(100) NULL,
                surname VARCHAR(100) NULL,
                email VARCHAR(150) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                role ENUM('teacher', 'parent', 'principal') NOT NULL,
                approval_status ENUM('pending', 'approved') NOT NULL DEFAULT 'approved',
                teaching_class VARCHAR(100) NULL,
                requested_teaching_class VARCHAR(100) NULL,
                child_name VARCHAR(150) NULL,
                child_count INT UNSIGNED NULL,
                child_details TEXT NULL,
                profile_picture_path VARCHAR(255) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $userColumns = [];
            $userColumnsResult = $db->query("SHOW COLUMNS FROM users");
            if ($userColumnsResult) {
                while ($column = $userColumnsResult->fetch_assoc()) {
                    $userColumns[] = (string) ($column['Field'] ?? '');
                }
                $userColumnsResult->free();
            }
            if (!in_array('approval_status', $userColumns, true)) {
                $db->query("ALTER TABLE users ADD COLUMN approval_status ENUM('pending', 'approved') NOT NULL DEFAULT 'approved' AFTER role");
                $db->query("UPDATE users SET approval_status = 'approved' WHERE approval_status IS NULL OR approval_status = ''");
            }
            if (!in_array('teaching_subject', $userColumns, true)) {
                $db->query("ALTER TABLE users ADD COLUMN teaching_subject VARCHAR(120) NULL AFTER approval_status");
            }
            if (!in_array('requested_teaching_class', $userColumns, true)) {
                $db->query("ALTER TABLE users ADD COLUMN requested_teaching_class VARCHAR(100) NULL AFTER teaching_class");
            }
            if (!in_array('profile_picture_path', $userColumns, true)) {
                $db->query("ALTER TABLE users ADD COLUMN profile_picture_path VARCHAR(255) NULL AFTER child_name");
            }
            if (!in_array('child_count', $userColumns, true)) {
                $db->query("ALTER TABLE users ADD COLUMN child_count INT UNSIGNED NULL AFTER child_name");
            }
            if (!in_array('child_details', $userColumns, true)) {
                $db->query("ALTER TABLE users ADD COLUMN child_details TEXT NULL AFTER child_count");
            }
            $db->query("CREATE TABLE IF NOT EXISTS announcements (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                principal_id INT UNSIGNED NOT NULL,
                title VARCHAR(180) NOT NULL,
                message TEXT NOT NULL,
                audience ENUM('teachers', 'parents', 'both') NOT NULL DEFAULT 'both',
                attachment_path VARCHAR(255) NULL,
                attachment_name VARCHAR(255) NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                edited_at DATETIME NULL,
                published_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_announcements_principal FOREIGN KEY (principal_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_announcements_principal_id (principal_id),
                INDEX idx_announcements_audience (audience),
                INDEX idx_announcements_active_published (is_active, published_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $announcementColumns = [];
            $announcementColumnsResult = $db->query("SHOW COLUMNS FROM announcements");
            if ($announcementColumnsResult) {
                while ($column = $announcementColumnsResult->fetch_assoc()) {
                    $announcementColumns[] = (string) ($column['Field'] ?? '');
                }
                $announcementColumnsResult->free();
            }
            if (!in_array('attachment_path', $announcementColumns, true)) {
                $db->query("ALTER TABLE announcements ADD COLUMN attachment_path VARCHAR(255) NULL AFTER audience");
            }
            if (!in_array('attachment_name', $announcementColumns, true)) {
                $db->query("ALTER TABLE announcements ADD COLUMN attachment_name VARCHAR(255) NULL AFTER attachment_path");
            }
            if (!in_array('edited_at', $announcementColumns, true)) {
                $db->query("ALTER TABLE announcements ADD COLUMN edited_at DATETIME NULL AFTER is_active");
            }
            $db->query("CREATE TABLE IF NOT EXISTS announcement_reactions (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                announcement_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                user_role ENUM('teacher', 'parent') NOT NULL,
                reaction ENUM('like', 'love', 'wow', 'sad') NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_announcement_reactions_announcement FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE,
                CONSTRAINT fk_announcement_reactions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE KEY uniq_announcement_user_role (announcement_id, user_id, user_role),
                INDEX idx_announcement_reactions_announcement (announcement_id),
                INDEX idx_announcement_reactions_user (user_id, user_role)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $db->query("CREATE TABLE IF NOT EXISTS direct_chat_threads (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                participant_one_id INT UNSIGNED NOT NULL,
                participant_one_role ENUM('principal', 'teacher', 'parent') NOT NULL,
                participant_two_id INT UNSIGNED NOT NULL,
                participant_two_role ENUM('principal', 'teacher', 'parent') NOT NULL,
                subject VARCHAR(255) NOT NULL DEFAULT 'Direct Chat',
                last_message_at TIMESTAMP NULL DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_direct_chat_threads_one FOREIGN KEY (participant_one_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_direct_chat_threads_two FOREIGN KEY (participant_two_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE KEY uniq_direct_chat_pair (participant_one_id, participant_one_role, participant_two_id, participant_two_role),
                INDEX idx_direct_chat_one (participant_one_id, participant_one_role),
                INDEX idx_direct_chat_two (participant_two_id, participant_two_role)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $db->query("CREATE TABLE IF NOT EXISTS direct_chat_messages (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                thread_id INT UNSIGNED NOT NULL,
                sender_id INT UNSIGNED NOT NULL,
                sender_role ENUM('principal', 'teacher', 'parent') NOT NULL,
                message TEXT NOT NULL,
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_direct_chat_messages_thread FOREIGN KEY (thread_id) REFERENCES direct_chat_threads(id) ON DELETE CASCADE,
                CONSTRAINT fk_direct_chat_messages_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_direct_chat_messages_thread (thread_id, created_at, id),
                INDEX idx_direct_chat_messages_sender (sender_id, sender_role)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $db->query("CREATE TABLE IF NOT EXISTS teacher_attendance (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                teacher_id INT UNSIGNED NOT NULL,
                attendance_date DATE NOT NULL,
                check_in_at DATETIME NULL,
                check_out_at DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_teacher_attendance_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE KEY uniq_teacher_attendance_day (teacher_id, attendance_date),
                INDEX idx_teacher_attendance_date (attendance_date),
                INDEX idx_teacher_attendance_teacher (teacher_id, attendance_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $db->query("CREATE TABLE IF NOT EXISTS teacher_leave_requests (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                teacher_id INT UNSIGNED NOT NULL,
                leave_type ENUM('leave', 'vacation') NOT NULL DEFAULT 'leave',
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                reason TEXT NOT NULL,
                status ENUM('pending', 'approved', 'declined') NOT NULL DEFAULT 'pending',
                principal_note TEXT NULL,
                reviewed_by INT UNSIGNED NULL,
                reviewed_at DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_teacher_leave_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_teacher_leave_principal FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
                INDEX idx_teacher_leave_teacher (teacher_id, created_at),
                INDEX idx_teacher_leave_status (status, start_date, end_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
    }
}

function db_ready(): bool
{
    global $db;

    return $db instanceof mysqli;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function get_flash(): ?array
{
    if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}

function normalize_email(string $email): string
{
    return strtolower(trim($email));
}

function current_user(): ?array
{
    return isset($_SESSION['user']) && is_array($_SESSION['user']) ? $_SESSION['user'] : null;
}

function login_user(array $user): void
{
    $_SESSION['user'] = [
        'id' => (int) ($user['id'] ?? 0),
        'full_name' => (string) ($user['full_name'] ?? ''),
        'first_name' => (string) ($user['first_name'] ?? ''),
        'surname' => (string) ($user['surname'] ?? ''),
        'email' => (string) ($user['email'] ?? ''),
        'role' => (string) ($user['role'] ?? ''),
        'approval_status' => (string) ($user['approval_status'] ?? 'approved'),
        'teaching_class' => (string) ($user['teaching_class'] ?? ''),
        'requested_teaching_class' => (string) ($user['requested_teaching_class'] ?? ''),
        'child_name' => (string) ($user['child_name'] ?? ''),
        'child_count' => (int) ($user['child_count'] ?? 0),
        'child_details' => (string) ($user['child_details'] ?? ''),
        'profile_picture_path' => (string) ($user['profile_picture_path'] ?? ''),
    ];
}

function normalize_child_details(string $details): string
{
    $lines = preg_split('/\r\n|\r|\n/', trim($details)) ?: [];
    $lines = array_values(array_filter(array_map(static fn(string $line): string => trim($line), $lines), static fn(string $line): bool => $line !== ''));

    return implode("\n", $lines);
}

function primary_child_name_from_details(string $details): string
{
    $normalized = normalize_child_details($details);
    if ($normalized === '') {
        return '';
    }

    $firstLine = trim((string) (explode("\n", $normalized)[0] ?? ''));
    if ($firstLine === '') {
        return '';
    }

    if (preg_match('/^(.+?)\s+in\s+/i', $firstLine, $matches) === 1) {
        return trim((string) ($matches[1] ?? ''));
    }

    return $firstLine;
}

function initials_from_name(string $name, string $fallback = 'U'): string
{
    $name = trim($name);
    if ($name === '') {
        return strtoupper(substr($fallback, 0, 2));
    }

    $parts = preg_split('/\s+/', $name) ?: [];
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $initials .= strtoupper(substr((string) $part, 0, 1));
    }

    return $initials !== '' ? $initials : strtoupper(substr($name, 0, 2));
}

function profile_picture_upload_directory(): string
{
    return __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'profile-pictures';
}

function normalize_profile_picture_path(?string $path): string
{
    $path = trim((string) $path);
    if ($path === '') {
        return '';
    }

    return str_replace('\\', '/', $path);
}

function user_profile_picture_url(array $user): ?string
{
    $path = normalize_profile_picture_path((string) ($user['profile_picture_path'] ?? ''));
    if ($path === '') {
        return null;
    }

    return rawurlencode_path($path);
}

function rawurlencode_path(string $path): string
{
    $segments = array_map('rawurlencode', array_filter(explode('/', $path), static fn(string $segment): bool => $segment !== ''));
    $relativePath = implode('/', $segments);
    if ($relativePath === '') {
        return app_base_url_path() . '/';
    }

    return app_base_url_path() . '/' . $relativePath;
}

function app_base_url_path(): string
{
    static $basePath = null;

    if ($basePath !== null) {
        return $basePath;
    }

    $documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath((string) $_SERVER['DOCUMENT_ROOT']) : false;
    $applicationRoot = realpath(__DIR__);

    if ($documentRoot === false || $applicationRoot === false) {
        $basePath = '';
        return $basePath;
    }

    $documentRoot = rtrim(str_replace('\\', '/', $documentRoot), '/');
    $applicationRoot = rtrim(str_replace('\\', '/', $applicationRoot), '/');

    if ($documentRoot !== '' && str_starts_with($applicationRoot, $documentRoot)) {
        $suffix = trim(substr($applicationRoot, strlen($documentRoot)), '/');
        $basePath = $suffix === '' ? '' : '/' . $suffix;
        return $basePath;
    }

    $basePath = '';
    return $basePath;
}

function announcement_attachment_is_image(?string $path): bool
{
    $normalizedPath = strtolower(trim((string) $path));
    if ($normalizedPath === '') {
        return false;
    }

    $extension = strtolower((string) pathinfo($normalizedPath, PATHINFO_EXTENSION));
    return in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
}

function render_announcement_attachment_html(?string $path, ?string $name, string $previewClass = 'announcement-image-preview', string $linkClass = 'announcement-attachment'): string
{
    $normalizedPath = trim((string) $path);
    if ($normalizedPath === '') {
        return '';
    }

    $encodedPath = e(rawurlencode_path($normalizedPath));
    $attachmentName = trim((string) $name) !== '' ? trim((string) $name) : 'Open attachment';

    if (announcement_attachment_is_image($normalizedPath)) {
        return '<a class="' . e($previewClass) . '" href="' . $encodedPath . '" target="_blank" rel="noopener noreferrer"><img src="' . $encodedPath . '" alt="' . e($attachmentName) . '"></a>';
    }

    return '<a class="' . e($linkClass) . '" href="' . $encodedPath . '" target="_blank" rel="noopener noreferrer">📎 ' . e($attachmentName) . '</a>';
}

function announcement_attachment_upload_directory(): string
{
    return __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'announcements';
}

function avatar_payload_from_user(array $user, string $fallback = 'User'): array
{
    $displayName = display_name_from_user($user, $fallback);

    return [
        'avatar' => initials_from_name($displayName, $fallback),
        'avatar_image_url' => user_profile_picture_url($user),
    ];
}

function render_avatar_html(array $user, string $className, string $fallback = 'User', string $background = '', string $alt = ''): string
{
    $displayName = display_name_from_user($user, $fallback);
    $imageUrl = user_profile_picture_url($user);
    $styleAttr = $background !== '' ? ' style="background:' . e($background) . ';"' : '';

    if ($imageUrl !== null) {
        $altText = $alt !== '' ? $alt : $displayName . ' profile picture';
        return '<div class="' . e($className) . ' has-photo"' . $styleAttr . '><img src="' . e($imageUrl) . '" alt="' . e($altText) . '"></div>';
    }

    return '<div class="' . e($className) . '"' . $styleAttr . '>' . e(initials_from_name($displayName, $fallback)) . '</div>';
}

function upload_profile_picture(array $file, ?string $existingPath, ?string &$error = null): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return normalize_profile_picture_path($existingPath);
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $error = 'Unable to upload the profile picture right now.';
        return null;
    }

    $tmpPath = (string) ($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        $error = 'Invalid profile picture upload.';
        return null;
    }

    $fileSize = (int) ($file['size'] ?? 0);
    if ($fileSize < 1 || $fileSize > 3 * 1024 * 1024) {
        $error = 'Profile picture must be 3MB or smaller.';
        return null;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = $finfo ? (string) finfo_file($finfo, $tmpPath) : '';
    if ($finfo) {
        finfo_close($finfo);
    }

    $allowedMimeTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    if (!isset($allowedMimeTypes[$mimeType])) {
        $error = 'Profile picture must be a JPG, PNG, WEBP, or GIF image.';
        return null;
    }

    $uploadDir = profile_picture_upload_directory();
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
        $error = 'Unable to prepare the profile picture folder.';
        return null;
    }

    $newFileName = 'profile_' . bin2hex(random_bytes(16)) . '.' . $allowedMimeTypes[$mimeType];
    $absoluteTarget = $uploadDir . DIRECTORY_SEPARATOR . $newFileName;
    if (!move_uploaded_file($tmpPath, $absoluteTarget)) {
        $error = 'Unable to save the uploaded profile picture.';
        return null;
    }

    $relativePath = normalize_profile_picture_path('assets/uploads/profile-pictures/' . $newFileName);
    $existingNormalized = normalize_profile_picture_path($existingPath);
    if ($existingNormalized !== '') {
        $oldAbsolute = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $existingNormalized);
        if (is_file($oldAbsolute)) {
            @unlink($oldAbsolute);
        }
    }

    return $relativePath;
}
function upload_announcement_attachment(?array $file, ?string $existingPath, ?string &$error = null): ?array
{
    if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        if (trim((string) $existingPath) === '') {
            return ['path' => null, 'name' => null];
        }

        return ['path' => normalize_profile_picture_path((string) $existingPath), 'name' => basename((string) $existingPath)];
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $error = 'Unable to upload the announcement attachment right now.';
        return null;
    }

    $tmpPath = (string) ($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        $error = 'Invalid announcement attachment upload.';
        return null;
    }

    $fileSize = (int) ($file['size'] ?? 0);
    if ($fileSize < 1 || $fileSize > 8 * 1024 * 1024) {
        $error = 'Announcement attachment must be 8MB or smaller.';
        return null;
    }

    $originalName = trim((string) ($file['name'] ?? 'attachment'));
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt'];
    if (!in_array($extension, $allowedExtensions, true)) {
        $error = 'Attachment must be an image, PDF, Word, Excel, PowerPoint, or TXT file.';
        return null;
    }

    $uploadDirectory = announcement_attachment_upload_directory();
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0775, true) && !is_dir($uploadDirectory)) {
        $error = 'Unable to create the announcement upload folder.';
        return null;
    }

    $safeBaseName = preg_replace('/[^A-Za-z0-9_-]+/', '-', pathinfo($originalName, PATHINFO_FILENAME)) ?: 'attachment';
    $fileName = sprintf('announcement-%s-%s.%s', date('YmdHis'), bin2hex(random_bytes(4)), $extension);
    $destination = $uploadDirectory . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($tmpPath, $destination)) {
        $error = 'Unable to save the announcement attachment.';
        return null;
    }

    $normalizedExistingPath = trim((string) $existingPath);
    if ($normalizedExistingPath !== '') {
        $existingAbsolutePath = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalizedExistingPath);
        if (is_file($existingAbsolutePath)) {
            @unlink($existingAbsolutePath);
        }
    }

    return [
        'path' => 'assets/uploads/announcements/' . $fileName,
        'name' => $originalName !== '' ? $originalName : ($safeBaseName . '.' . $extension),
    ];
}

function logout_user(): void
{
    unset($_SESSION['user']);
}

function require_role(string $role): array
{
    $user = current_user();

    if (!$user) {
        set_flash('error', 'Please log in first.');
        redirect($role . '-login.php');
    }

    if (($user['role'] ?? '') !== $role) {
        set_flash('error', 'You do not have access to that page.');
        redirect('index.php');
    }

    return $user;
}

function fetch_user_by_email(string $email): ?array
{
    global $db;

    if (!$db instanceof mysqli) {
        return null;
    }

    $query = 'SELECT * FROM users WHERE email = ? LIMIT 1';
    $stmt = $db->prepare($query);
    if (!$stmt) {
        return null;
    }

    $normalizedEmail = normalize_email($email);
    $stmt->bind_param('s', $normalizedEmail);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $user ?: null;
}

function fetch_user_by_id(int $userId): ?array
{
    global $db;

        if (!$db instanceof mysqli || $userId < 1) {
        return null;
    }

    $stmt = $db->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $user ?: null;
}

function approved_principal_exists(): bool
{
    global $db;

    if (!$db instanceof mysqli) {
        return false;
    }

    $result = $db->query("SELECT id FROM users WHERE role = 'principal' AND approval_status = 'approved' LIMIT 1");
    if (!$result) {
        return false;
    }

    $exists = (bool) $result->fetch_assoc();
    $result->free();

    return $exists;
}

function principal_account_exists(): bool
{
    global $db;

    if (!$db instanceof mysqli) {
        return false;
    }

    $result = $db->query("SELECT id FROM users WHERE role = 'principal' LIMIT 1");
    if (!$result) {
        return false;
    }

    $exists = (bool) $result->fetch_assoc();
    $result->free();

    return $exists;
}

function fetch_pending_principals_for_principal(): array
{
    global $db;

    if (!$db instanceof mysqli) {
        return [];
    }

    $result = $db->query("SELECT id, full_name, first_name, surname, email, approval_status, profile_picture_path, created_at FROM users WHERE role = 'principal' AND approval_status = 'pending' ORDER BY created_at ASC, id ASC");
    if (!$result) {
        return [];
    }

    $rows = $result->fetch_all(MYSQLI_ASSOC) ?: [];
    $result->free();

    return $rows;
}

function create_user(array $data, ?string &$error = null): bool
{
    global $db;

        if (!$db instanceof mysqli) {
        $error = 'Database is not ready. Import db_setup.sql first.';
        return false;
    }

    $firstName = trim((string) ($data['first_name'] ?? ''));
    $surname = trim((string) ($data['surname'] ?? ''));
    $email = normalize_email((string) ($data['email'] ?? ''));
    $password = (string) ($data['password'] ?? '');
    $role = (string) ($data['role'] ?? '');
    $teachingClass = trim((string) ($data['teaching_class'] ?? ''));
    $requestedTeachingClass = trim((string) ($data['requested_teaching_class'] ?? ''));
    $childName = trim((string) ($data['child_name'] ?? ''));
    $childCount = (int) ($data['child_count'] ?? 0);
    $childDetails = normalize_child_details((string) ($data['child_details'] ?? ''));

    if ($firstName === '' || $surname === '' || $email === '' || $password === '' || $role === '') {
        $error = 'Please fill in all required fields.';
        return false;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please provide a valid email address.';
        return false;
    }

    if (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
        return false;
    }

    if (fetch_user_by_email($email)) {
        $error = 'An account with that email already exists.';
        return false;
    }

    if ($role === 'principal' && principal_account_exists()) {
        $error = 'Principal registration is disabled. Only the existing admin account can access the principal portal.';
        return false;
    }

    if ($role === 'parent') {
        if ($childCount < 1) {
            $error = 'Please enter how many children you have in the school.';
            return false;
        }

        if ($childDetails === '') {
            $error = 'Please list your children and classes, for example: Akin in JSS1.';
            return false;
        }

        $childName = primary_child_name_from_details($childDetails);
    } else {
        $childCount = 0;
        $childDetails = '';
    }

    $fullName = trim($firstName . ' ' . $surname);
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $approvalStatus = 'approved';
    if ($role === 'teacher') {
        $approvalStatus = 'pending';
        $requestedTeachingClass = $requestedTeachingClass !== '' ? $requestedTeachingClass : $teachingClass;
        $teachingClass = '';
    } elseif ($role === 'parent') {
        $approvalStatus = 'pending';
    } elseif ($role === 'principal' && approved_principal_exists()) {
        $approvalStatus = 'pending';
    }

    $query = 'INSERT INTO users (full_name, first_name, surname, email, password, role, approval_status, teaching_class, requested_teaching_class, child_name, child_count, child_details) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
    $stmt = $db->prepare($query);
    if (!$stmt) {
        $error = 'Unable to create the account right now.';
        return false;
    }

    $stmt->bind_param('ssssssssssis', $fullName, $firstName, $surname, $email, $passwordHash, $role, $approvalStatus, $teachingClass, $requestedTeachingClass, $childName, $childCount, $childDetails);
    $created = $stmt->execute();
    $stmt->close();

    if (!$created) {
        $error = 'Unable to save the account details.';
        return false;
    }

    return true;
}

function fetch_teacher_attendance_for_date(int $teacherId, ?string $date = null): ?array
{
    global $db;

    if (!$db instanceof mysqli || $teacherId < 1) {
        return null;
    }

    $attendanceDate = $date !== null && trim($date) !== '' ? trim($date) : date('Y-m-d');
    $stmt = $db->prepare('SELECT ta.*, u.full_name, u.first_name, u.surname, u.email, u.profile_picture_path FROM teacher_attendance ta INNER JOIN users u ON u.id = ta.teacher_id WHERE ta.teacher_id = ? AND ta.attendance_date = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('is', $teacherId, $attendanceDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function record_teacher_check_in(int $teacherId, ?string &$error = null): bool
{
    global $db;

    if (!$db instanceof mysqli || $teacherId < 1) {
        $error = 'Database is not ready.';
        return false;
    }

    $teacher = fetch_user_by_id($teacherId);
    if ($teacher === null || (string) ($teacher['role'] ?? '') !== 'teacher') {
        $error = 'Teacher account was not found.';
        return false;
    }

    $today = date('Y-m-d');
    $now = date('Y-m-d H:i:s');
    $existing = fetch_teacher_attendance_for_date($teacherId, $today);

    if ($existing !== null && (string) ($existing['check_in_at'] ?? '') !== '') {
        $error = 'You have already checked in today.';
        return false;
    }

    if ($existing !== null) {
        $stmt = $db->prepare('UPDATE teacher_attendance SET check_in_at = ? WHERE teacher_id = ? AND attendance_date = ? LIMIT 1');
        if (!$stmt) {
            $error = 'Unable to save check-in right now.';
            return false;
        }

        $stmt->bind_param('sis', $now, $teacherId, $today);
        $saved = $stmt->execute();
        $stmt->close();

        if (!$saved) {
            $error = 'Unable to save check-in right now.';
            return false;
        }

        return true;
    }

    $stmt = $db->prepare('INSERT INTO teacher_attendance (teacher_id, attendance_date, check_in_at) VALUES (?, ?, ?)');
    if (!$stmt) {
        $error = 'Unable to save check-in right now.';
        return false;
    }

    $stmt->bind_param('iss', $teacherId, $today, $now);
    $saved = $stmt->execute();
    $stmt->close();

    if (!$saved) {
        $error = 'Unable to save check-in right now.';
        return false;
    }

    return true;
}

function record_teacher_check_out(int $teacherId, ?string &$error = null): bool
{
    global $db;

    if (!$db instanceof mysqli || $teacherId < 1) {
        $error = 'Database is not ready.';
        return false;
    }

    $today = date('Y-m-d');
    $attendance = fetch_teacher_attendance_for_date($teacherId, $today);
    if ($attendance === null || (string) ($attendance['check_in_at'] ?? '') === '') {
        $error = 'Check in first before checking out.';
        return false;
    }

    if ((string) ($attendance['check_out_at'] ?? '') !== '') {
        $error = 'You have already checked out today.';
        return false;
    }

    $now = date('Y-m-d H:i:s');
    $stmt = $db->prepare('UPDATE teacher_attendance SET check_out_at = ? WHERE teacher_id = ? AND attendance_date = ? LIMIT 1');
    if (!$stmt) {
        $error = 'Unable to save check-out right now.';
        return false;
    }

    $stmt->bind_param('sis', $now, $teacherId, $today);
    $saved = $stmt->execute();
    $stmt->close();

    if (!$saved) {
        $error = 'Unable to save check-out right now.';
        return false;
    }

    return true;
}

function fetch_recent_teacher_attendance_for_principal(int $limit = 12): array
{
    global $db;

    if (!$db instanceof mysqli) {
        return [];
    }

    $limit = max(1, min(100, $limit));
    $query = 'SELECT ta.*, u.full_name, u.first_name, u.surname, u.email, u.profile_picture_path
        FROM teacher_attendance ta
        INNER JOIN users u ON u.id = ta.teacher_id
        ORDER BY ta.attendance_date DESC, COALESCE(ta.check_in_at, ta.created_at) DESC
        LIMIT ' . $limit;
    $result = $db->query($query);
    if (!$result) {
        return [];
    }

    $rows = $result->fetch_all(MYSQLI_ASSOC) ?: [];
    $result->free();

    return $rows;
}

function fetch_teacher_attendance_history(int $teacherId, int $limit = 7): array
{
    global $db;

    if (!$db instanceof mysqli || $teacherId < 1) {
        return [];
    }

    $limit = max(1, min(60, $limit));
    $stmt = $db->prepare('SELECT * FROM teacher_attendance WHERE teacher_id = ? ORDER BY attendance_date DESC, COALESCE(check_in_at, created_at) DESC LIMIT ?');
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('ii', $teacherId, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    return $rows;
}

function fetch_teacher_attendance_summary_today(): array
{
    global $db;

    $summary = [
        'checked_in' => 0,
        'checked_out' => 0,
        'pending_checkout' => 0,
    ];

    if (!$db instanceof mysqli) {
        return $summary;
    }

    $today = date('Y-m-d');
    $stmt = $db->prepare('SELECT COUNT(*) AS total_checked_in, SUM(CASE WHEN check_out_at IS NOT NULL THEN 1 ELSE 0 END) AS total_checked_out, SUM(CASE WHEN check_in_at IS NOT NULL AND check_out_at IS NULL THEN 1 ELSE 0 END) AS total_pending_checkout FROM teacher_attendance WHERE attendance_date = ?');
    if (!$stmt) {
        return $summary;
    }

    $stmt->bind_param('s', $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if ($row) {
        $summary['checked_in'] = (int) ($row['total_checked_in'] ?? 0);
        $summary['checked_out'] = (int) ($row['total_checked_out'] ?? 0);
        $summary['pending_checkout'] = (int) ($row['total_pending_checkout'] ?? 0);
    }

    return $summary;
}

function create_teacher_leave_request(int $teacherId, array $data, ?string &$error = null): bool
{
    global $db;

    if (!$db instanceof mysqli || $teacherId < 1) {
        $error = 'Database is not ready.';
        return false;
    }

    $teacher = fetch_user_by_id($teacherId);
    if ($teacher === null || (string) ($teacher['role'] ?? '') !== 'teacher') {
        $error = 'Teacher account was not found.';
        return false;
    }

    $leaveType = (string) ($data['leave_type'] ?? 'leave');
    $startDate = trim((string) ($data['start_date'] ?? ''));
    $endDate = trim((string) ($data['end_date'] ?? ''));
    $reason = trim((string) ($data['reason'] ?? ''));

    if (!in_array($leaveType, ['leave', 'vacation'], true)) {
        $error = 'Please choose a valid request type.';
        return false;
    }

    if ($startDate === '' || $endDate === '' || $reason === '') {
        $error = 'Leave type, start date, end date, and reason are required.';
        return false;
    }

    $startTimestamp = strtotime($startDate);
    $endTimestamp = strtotime($endDate);
    if ($startTimestamp === false || $endTimestamp === false) {
        $error = 'Please provide valid leave dates.';
        return false;
    }

    if ($endTimestamp < $startTimestamp) {
        $error = 'End date cannot be before start date.';
        return false;
    }

    $stmt = $db->prepare('INSERT INTO teacher_leave_requests (teacher_id, leave_type, start_date, end_date, reason) VALUES (?, ?, ?, ?, ?)');
    if (!$stmt) {
        $error = 'Unable to send the leave request right now.';
        return false;
    }

    $stmt->bind_param('issss', $teacherId, $leaveType, $startDate, $endDate, $reason);
    $created = $stmt->execute();
    $stmt->close();

    if (!$created) {
        $error = 'Unable to send the leave request right now.';
        return false;
    }

    return true;
}

function fetch_teacher_leave_requests(int $teacherId, int $limit = 10): array
{
    global $db;

    if (!$db instanceof mysqli || $teacherId < 1) {
        return [];
    }

    $limit = max(1, min(100, $limit));
    $stmt = $db->prepare('SELECT tlr.*, reviewer.full_name AS reviewer_full_name, reviewer.first_name AS reviewer_first_name, reviewer.surname AS reviewer_surname FROM teacher_leave_requests tlr LEFT JOIN users reviewer ON reviewer.id = tlr.reviewed_by WHERE tlr.teacher_id = ? ORDER BY tlr.created_at DESC, tlr.id DESC LIMIT ?');
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('ii', $teacherId, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    return $rows;
}

function fetch_leave_requests_for_principal(?string $status = null, int $limit = 25): array
{
    global $db;

    if (!$db instanceof mysqli) {
        return [];
    }

    $limit = max(1, min(200, $limit));
    if ($status !== null && in_array($status, ['pending', 'approved', 'declined'], true)) {
        $stmt = $db->prepare('SELECT tlr.*, u.full_name, u.first_name, u.surname, u.email, u.profile_picture_path, reviewer.full_name AS reviewer_full_name, reviewer.first_name AS reviewer_first_name, reviewer.surname AS reviewer_surname FROM teacher_leave_requests tlr INNER JOIN users u ON u.id = tlr.teacher_id LEFT JOIN users reviewer ON reviewer.id = tlr.reviewed_by WHERE tlr.status = ? ORDER BY tlr.created_at DESC, tlr.id DESC LIMIT ?');
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('si', $status, $limit);
    } else {
        $stmt = $db->prepare('SELECT tlr.*, u.full_name, u.first_name, u.surname, u.email, u.profile_picture_path, reviewer.full_name AS reviewer_full_name, reviewer.first_name AS reviewer_first_name, reviewer.surname AS reviewer_surname FROM teacher_leave_requests tlr INNER JOIN users u ON u.id = tlr.teacher_id LEFT JOIN users reviewer ON reviewer.id = tlr.reviewed_by ORDER BY tlr.created_at DESC, tlr.id DESC LIMIT ?');
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('i', $limit);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    return $rows;
}

function review_teacher_leave_request(int $principalId, int $requestId, string $decision, ?string $principalNote = null, ?string &$error = null): bool
{
    global $db;

    if (!$db instanceof mysqli || $principalId < 1 || $requestId < 1) {
        $error = 'Database is not ready.';
        return false;
    }

    if (!in_array($decision, ['approved', 'declined'], true)) {
        $error = 'Invalid leave request decision.';
        return false;
    }

    $principal = fetch_user_by_id($principalId);
    if ($principal === null || (string) ($principal['role'] ?? '') !== 'principal') {
        $error = 'Only the principal can review leave requests.';
        return false;
    }

    $stmt = $db->prepare('UPDATE teacher_leave_requests SET status = ?, principal_note = ?, reviewed_by = ?, reviewed_at = ? WHERE id = ? AND status = "pending" LIMIT 1');
    if (!$stmt) {
        $error = 'Unable to review this leave request right now.';
        return false;
    }

    $reviewedAt = date('Y-m-d H:i:s');
    $note = trim((string) $principalNote);
    $stmt->bind_param('ssisi', $decision, $note, $principalId, $reviewedAt, $requestId);
    $updated = $stmt->execute();
    $affectedRows = $stmt->affected_rows;
    $stmt->close();

    if (!$updated || $affectedRows < 1) {
        $error = 'That leave request could not be updated. It may already have been reviewed.';
        return false;
    }

    return true;
}

function count_pending_teacher_leave_requests(): int
{
    global $db;

    if (!$db instanceof mysqli) {
        return 0;
    }

    $result = $db->query("SELECT COUNT(*) AS total FROM teacher_leave_requests WHERE status = 'pending'");
    if (!$result) {
        return 0;
    }

    $row = $result->fetch_assoc();
    $result->free();

    return (int) ($row['total'] ?? 0);
}

function attempt_login(string $email, string $password, string $role, ?string &$error = null): ?array
{
    $user = fetch_user_by_email($email);

    if (!$user || !password_verify($password, (string) ($user['password'] ?? ''))) {
        $error = 'Invalid email or password.';
        return null;
    }

    if (($user['role'] ?? '') !== $role) {
        $error = 'This account does not belong to the selected portal.';
        return null;
    }

    if ($role === 'teacher' && (string) ($user['approval_status'] ?? 'approved') !== 'approved') {
        $error = 'Your teacher account is pending principal approval and class assignment.';
        return null;
    }

    if ($role === 'principal' && (string) ($user['approval_status'] ?? 'approved') !== 'approved') {
        $error = 'Your principal account is pending approval from the current principal.';
        return null;
    }

    if ($role === 'parent' && (string) ($user['approval_status'] ?? 'approved') !== 'approved') {
        $error = 'Your parent account is waiting for principal approval.';
        return null;
    }

    login_user($user);

    return $user;
}

function update_user_profile(int $userId, array $data, ?string &$error = null, ?array $profilePictureFile = null): bool
{
    global $db;

        if (!$db instanceof mysqli || $userId < 1) {
        $error = 'Database is not ready.';
        return false;
    }

    $existingUser = fetch_user_by_id($userId);
    if ($existingUser === null) {
        $error = 'User account was not found.';
        return false;
    }

    $firstName = trim((string) ($data['first_name'] ?? ''));
    $surname = trim((string) ($data['surname'] ?? ''));
    $email = normalize_email((string) ($data['email'] ?? ''));
    $childName = trim((string) ($data['child_name'] ?? ($existingUser['child_name'] ?? '')));
    $childCount = (int) ($existingUser['child_count'] ?? 0);
    $childDetails = normalize_child_details((string) ($existingUser['child_details'] ?? ''));
    $requestedTeachingClass = trim((string) ($data['requested_teaching_class'] ?? ($existingUser['requested_teaching_class'] ?? '')));

    if ($firstName === '' || $surname === '' || $email === '') {
        $error = 'First name, last name, and email are required.';
        return false;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please provide a valid email address.';
        return false;
    }

    $duplicateStmt = $db->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
    if (!$duplicateStmt) {
        $error = 'Unable to validate your profile details.';
        return false;
    }

    $duplicateStmt->bind_param('si', $email, $userId);
    $duplicateStmt->execute();
    $duplicateResult = $duplicateStmt->get_result();
    $duplicateUser = $duplicateResult ? $duplicateResult->fetch_assoc() : null;
    $duplicateStmt->close();

    if ($duplicateUser) {
        $error = 'Another account already uses that email address.';
        return false;
    }

    $fullName = trim($firstName . ' ' . $surname);
    $role = (string) ($existingUser['role'] ?? '');

    if ($role !== 'teacher') {
        $requestedTeachingClass = (string) ($existingUser['requested_teaching_class'] ?? '');
    }

    if ($role !== 'parent') {
        $childName = (string) ($existingUser['child_name'] ?? '');
        $childCount = (int) ($existingUser['child_count'] ?? 0);
        $childDetails = (string) ($existingUser['child_details'] ?? '');
    }

    $profilePicturePath = normalize_profile_picture_path((string) ($existingUser['profile_picture_path'] ?? ''));
    if ($profilePictureFile !== null) {
        $uploadedPath = upload_profile_picture($profilePictureFile, $profilePicturePath, $error);
        if ($uploadedPath === null) {
            return false;
        }
        $profilePicturePath = $uploadedPath;
    }

    $stmt = $db->prepare('UPDATE users SET full_name = ?, first_name = ?, surname = ?, email = ?, child_name = ?, child_count = ?, child_details = ?, requested_teaching_class = ?, profile_picture_path = ? WHERE id = ? LIMIT 1');
    if (!$stmt) {
        $error = 'Unable to save profile changes right now.';
        return false;
    }

    $stmt->bind_param('sssssisssi', $fullName, $firstName, $surname, $email, $childName, $childCount, $childDetails, $requestedTeachingClass, $profilePicturePath, $userId);
    $updated = $stmt->execute();
    $stmt->close();

    if (!$updated) {
        $error = 'Unable to save profile changes right now.';
        return false;
    }

    $freshUser = fetch_user_by_id($userId);
    if ($freshUser) {
        login_user($freshUser);
    }

    return true;
}

function approve_teacher_account(int $teacherId, string $assignedClass, ?string &$error = null): bool
{
    global $db;

        if (!$db instanceof mysqli || $teacherId < 1) {
        $error = 'Database is not ready.';
        return false;
    }

    $assignedClass = trim($assignedClass);
    if ($assignedClass === '') {
        $error = 'Assigned class is required before approval.';
        return false;
    }

    $teacher = fetch_user_by_id($teacherId);
    if ($teacher === null || (string) ($teacher['role'] ?? '') !== 'teacher') {
        $error = 'Teacher account was not found.';
        return false;
    }

    $stmt = $db->prepare("UPDATE users SET approval_status = 'approved', teaching_class = ?, requested_teaching_class = COALESCE(NULLIF(requested_teaching_class, ''), ?) WHERE id = ? AND role = 'teacher' LIMIT 1");
    if (!$stmt) {
        $error = 'Unable to approve this teacher right now.';
        return false;
    }

    $stmt->bind_param('ssi', $assignedClass, $assignedClass, $teacherId);
    $updated = $stmt->execute();
    $stmt->close();

    if (!$updated) {
        $error = 'Unable to approve this teacher right now.';
        return false;
    }

    return true;
}

function approve_principal_account(int $principalId, ?string &$error = null): bool
{
    global $db;

    if (!$db instanceof mysqli || $principalId < 1) {
        $error = 'Database is not ready.';
        return false;
    }

    $principal = fetch_user_by_id($principalId);
    if ($principal === null || (string) ($principal['role'] ?? '') !== 'principal') {
        $error = 'Principal account was not found.';
        return false;
    }

    if ((string) ($principal['approval_status'] ?? 'approved') === 'approved') {
        return true;
    }

    $stmt = $db->prepare("UPDATE users SET approval_status = 'approved' WHERE id = ? AND role = 'principal' LIMIT 1");
    if (!$stmt) {
        $error = 'Unable to approve this principal right now.';
        return false;
    }

    $stmt->bind_param('i', $principalId);
    $updated = $stmt->execute();
    $stmt->close();

    if (!$updated) {
        $error = 'Unable to approve this principal right now.';
        return false;
    }

    return true;
}

function approve_parent_account(int $parentId, ?string &$error = null): bool
{
    global $db;

    if (!$db instanceof mysqli || $parentId < 1) {
        $error = 'Database is not ready.';
        return false;
    }

    $parent = fetch_user_by_id($parentId);
    if ($parent === null || (string) ($parent['role'] ?? '') !== 'parent') {
        $error = 'Parent account was not found.';
        return false;
    }

    if ((string) ($parent['approval_status'] ?? 'approved') === 'approved') {
        return true;
    }

    $stmt = $db->prepare("UPDATE users SET approval_status = 'approved' WHERE id = ? AND role = 'parent' LIMIT 1");
    if (!$stmt) {
        $error = 'Unable to approve this parent right now.';
        return false;
    }

    $stmt->bind_param('i', $parentId);
    $updated = $stmt->execute();
    $stmt->close();

    if (!$updated) {
        $error = 'Unable to approve this parent right now.';
        return false;
    }

    return true;
}

function database_table_exists(string $tableName): bool
{
    global $db;

    static $cache = [];

    if (!$db instanceof mysqli) {
        return false;
    }

    $tableName = trim($tableName);
    if ($tableName === '') {
        return false;
    }

    if (array_key_exists($tableName, $cache)) {
        return $cache[$tableName];
    }

    $stmt = $db->prepare('SHOW TABLES LIKE ?');
    if (!$stmt) {
        $cache[$tableName] = false;
        return false;
    }

    $stmt->bind_param('s', $tableName);
    $stmt->execute();
    $result = $stmt->get_result();
    $cache[$tableName] = (bool) ($result ? $result->fetch_row() : null);
    $stmt->close();

    return $cache[$tableName];
}

function delete_user_account_by_principal(int $principalId, int $userId, ?string &$error = null): bool
{
    global $db;

    if (!$db instanceof mysqli || $principalId < 1 || $userId < 1) {
        $error = 'Database is not ready.';
        return false;
    }

    $principal = fetch_user_by_id($principalId);
    if ($principal === null || (string) ($principal['role'] ?? '') !== 'principal') {
        $error = 'Only a principal can delete teacher or parent accounts.';
        return false;
    }

    $user = fetch_user_by_id($userId);
    if ($user === null) {
        $error = 'The selected account was not found.';
        return false;
    }

    $role = (string) ($user['role'] ?? '');
    if (!in_array($role, ['teacher', 'parent'], true)) {
        $error = 'Only teacher and parent accounts can be deleted here.';
        return false;
    }

    $db->begin_transaction();

    try {
        if (database_table_exists('support_messages')) {
            $supportMessages = $db->prepare('DELETE sm FROM support_messages sm INNER JOIN support_threads st ON st.id = sm.thread_id WHERE st.requester_id = ? AND st.requester_role = ?');
            if ($supportMessages) {
                $supportMessages->bind_param('is', $userId, $role);
                $supportMessages->execute();
                $supportMessages->close();
            }
        }

        if (database_table_exists('support_threads')) {
            $supportThreads = $db->prepare('DELETE FROM support_threads WHERE requester_id = ? AND requester_role = ?');
            if ($supportThreads) {
                $supportThreads->bind_param('is', $userId, $role);
                $supportThreads->execute();
                $supportThreads->close();
            }
        }

        $stmt = $db->prepare('DELETE FROM users WHERE id = ? AND role IN ("teacher", "parent") LIMIT 1');
        if (!$stmt) {
            throw new RuntimeException('Unable to delete the selected account right now.');
        }

        $stmt->bind_param('i', $userId);
        $deleted = $stmt->execute();
        $affectedRows = $stmt->affected_rows;
        $stmt->close();

        if (!$deleted || $affectedRows < 1) {
            throw new RuntimeException('Unable to delete the selected account right now.');
        }

        $db->commit();
        return true;
    } catch (Throwable $throwable) {
        $db->rollback();
        $error = $throwable->getMessage() !== '' ? $throwable->getMessage() : 'Unable to delete the selected account right now.';
        return false;
    }
}

function update_teacher_account_by_principal(int $teacherId, array $data, ?string &$error = null): bool
{
    global $db;

    if (!$db instanceof mysqli || $teacherId < 1) {
        $error = 'Database is not ready.';
        return false;
    }

    $teacher = fetch_user_by_id($teacherId);
    if ($teacher === null || (string) ($teacher['role'] ?? '') !== 'teacher') {
        $error = 'Teacher account was not found.';
        return false;
    }

    $firstName = trim((string) ($data['first_name'] ?? ''));
    $surname = trim((string) ($data['surname'] ?? ''));
    $teachingClass = trim((string) ($data['teaching_class'] ?? ''));
    $teachingSubject = trim((string) ($data['teaching_subject'] ?? ''));

    if ($firstName === '' || $surname === '') {
        $error = 'Teacher first name and last name are required.';
        return false;
    }

    if ($teachingClass === '') {
        $error = 'Teacher class is required.';
        return false;
    }

    $fullName = trim($firstName . ' ' . $surname);
    $stmt = $db->prepare('UPDATE users SET full_name = ?, first_name = ?, surname = ?, teaching_class = ?, teaching_subject = ? WHERE id = ? AND role = ? LIMIT 1');
    if (!$stmt) {
        $error = 'Unable to update the teacher profile right now.';
        return false;
    }

    $role = 'teacher';
    $stmt->bind_param('sssssis', $fullName, $firstName, $surname, $teachingClass, $teachingSubject, $teacherId, $role);
    $updated = $stmt->execute();
    $stmt->close();

    if (!$updated) {
        $error = 'Unable to update the teacher profile right now.';
        return false;
    }

    return true;
}

function fetch_teachers_for_principal(bool $includePending = true): array
{
    global $db;

        if (!$db instanceof mysqli) {
        return [];
    }

    $query = 'SELECT id, full_name, first_name, surname, email, teaching_subject, teaching_class, requested_teaching_class, approval_status, profile_picture_path, created_at FROM users WHERE role = "teacher"';
    if (!$includePending) {
        $query .= ' AND approval_status = "approved"';
    }
    $query .= ' ORDER BY approval_status ASC, full_name ASC, id ASC';

    $result = $db->query($query);
    if (!$result) {
        return [];
    }

    $rows = $result->fetch_all(MYSQLI_ASSOC) ?: [];
    $result->free();

    return $rows;
}

function create_announcement(int $principalId, string $title, string $message, string $audience, ?string &$error = null, ?array $attachmentFile = null): bool
{
    global $db;

        if (!$db instanceof mysqli) {
        $error = 'Database is not ready.';
        return false;
    }

    $title = trim($title);
    $message = trim($message);
    $allowedAudiences = ['teachers', 'parents', 'both'];

    if ($title === '' || $message === '') {
        $error = 'Title and message are required.';
        return false;
    }

    if (!in_array($audience, $allowedAudiences, true)) {
        $error = 'Invalid announcement audience.';
        return false;
    }

    $attachment = upload_announcement_attachment($attachmentFile, null, $error);
    if ($attachment === null) {
        return false;
    }

    $query = 'INSERT INTO announcements (principal_id, title, message, audience, attachment_path, attachment_name) VALUES (?, ?, ?, ?, ?, ?)';
    $stmt = $db->prepare($query);
    if (!$stmt) {
        $error = 'Unable to publish the announcement.';
        return false;
    }

    $attachmentPath = $attachment['path'];
    $attachmentName = $attachment['name'];
    $stmt->bind_param('isssss', $principalId, $title, $message, $audience, $attachmentPath, $attachmentName);
    $created = $stmt->execute();
    $stmt->close();

    if (!$created) {
        $error = 'Unable to save the announcement.';
        return false;
    }

    return true;
}

function fetch_announcement_by_id_for_principal(int $announcementId, int $principalId): ?array
{
    global $db;

    if (!$db instanceof mysqli || $announcementId < 1 || $principalId < 1) {
        return null;
    }

    $stmt = $db->prepare('SELECT * FROM announcements WHERE id = ? AND principal_id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('ii', $announcementId, $principalId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function update_announcement(int $announcementId, int $principalId, string $title, string $message, string $audience, ?string &$error = null, ?array $attachmentFile = null): bool
{
    global $db;

    if (!$db instanceof mysqli || $announcementId < 1 || $principalId < 1) {
        $error = 'Database is not ready.';
        return false;
    }

    $announcement = fetch_announcement_by_id_for_principal($announcementId, $principalId);
    if ($announcement === null) {
        $error = 'Announcement was not found.';
        return false;
    }

    $title = trim($title);
    $message = trim($message);
    $allowedAudiences = ['teachers', 'parents', 'both'];
    if ($title === '' || $message === '') {
        $error = 'Title and message are required.';
        return false;
    }

    if (!in_array($audience, $allowedAudiences, true)) {
        $error = 'Invalid announcement audience.';
        return false;
    }

    $attachment = upload_announcement_attachment($attachmentFile, (string) ($announcement['attachment_path'] ?? ''), $error);
    if ($attachment === null) {
        return false;
    }

    $attachmentPath = $attachment['path'];
    $attachmentName = $attachment['name'];
    if (($attachmentFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        $attachmentPath = trim((string) ($announcement['attachment_path'] ?? '')) !== '' ? (string) ($announcement['attachment_path'] ?? '') : null;
        $attachmentName = trim((string) ($announcement['attachment_name'] ?? '')) !== '' ? (string) ($announcement['attachment_name'] ?? '') : null;
    }

    $stmt = $db->prepare('UPDATE announcements SET title = ?, message = ?, audience = ?, attachment_path = ?, attachment_name = ?, edited_at = NOW() WHERE id = ? AND principal_id = ? LIMIT 1');
    if (!$stmt) {
        $error = 'Unable to update the announcement.';
        return false;
    }

    $stmt->bind_param('sssssii', $title, $message, $audience, $attachmentPath, $attachmentName, $announcementId, $principalId);
    $updated = $stmt->execute();
    $stmt->close();

    if (!$updated) {
        $error = 'Unable to save the announcement changes.';
        return false;
    }

    return true;
}

function delete_announcement(int $announcementId, int $principalId, ?string &$error = null): bool
{
    global $db;

    if (!$db instanceof mysqli || $announcementId < 1 || $principalId < 1) {
        $error = 'Database is not ready.';
        return false;
    }

    $announcement = fetch_announcement_by_id_for_principal($announcementId, $principalId);
    if ($announcement === null) {
        $error = 'Announcement was not found.';
        return false;
    }

    $attachmentPath = normalize_profile_picture_path((string) ($announcement['attachment_path'] ?? ''));

    $stmt = $db->prepare('DELETE FROM announcements WHERE id = ? AND principal_id = ? LIMIT 1');
    if (!$stmt) {
        $error = 'Unable to delete the announcement.';
        return false;
    }

    $stmt->bind_param('ii', $announcementId, $principalId);
    $deleted = $stmt->execute();
    $deletedRows = $stmt->affected_rows;
    $stmt->close();

    if (!$deleted) {
        $error = 'Unable to delete the announcement.';
        return false;
    }

    if ($deletedRows < 1) {
        $error = 'Announcement was not found.';
        return false;
    }

    if ($attachmentPath !== '') {
        $attachmentFullPath = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $attachmentPath);
        if (is_file($attachmentFullPath)) {
            @unlink($attachmentFullPath);
        }
    }

    return true;
}

function fetch_announcements_for_principal(int $principalId): array
{
    global $db;

        if (!$db instanceof mysqli) {
        return [];
    }

    $query = 'SELECT id, title, message, audience, attachment_path, attachment_name, is_active, edited_at, published_at, updated_at FROM announcements WHERE principal_id = ? AND is_active = 1 ORDER BY published_at DESC, id DESC';
    $stmt = $db->prepare($query);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $principalId);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    return $rows;
}

function fetch_announcements_for_role(string $role): array
{
    global $db;

        if (!$db instanceof mysqli) {
        return [];
    }

    $audience = $role === 'teacher' ? 'teachers' : 'parents';
    $query = 'SELECT a.id, a.title, a.message, a.audience, a.attachment_path, a.attachment_name, a.edited_at, a.published_at, u.full_name AS principal_name
        FROM announcements a
        INNER JOIN users u ON u.id = a.principal_id
        WHERE a.is_active = 1 AND (a.audience = ? OR a.audience = "both")
        ORDER BY a.published_at DESC, a.id DESC';
    $stmt = $db->prepare($query);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('s', $audience);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    return $rows;
}

function save_announcement_reaction(int $announcementId, int $userId, string $userRole, string $reaction, ?string &$error = null): bool
{
    global $db;

        if (!$db instanceof mysqli) {
        $error = 'Database is not ready.';
        return false;
    }

    $allowedRoles = ['teacher', 'parent'];
    $allowedReactions = ['like', 'love', 'wow', 'sad'];
    if (!in_array($userRole, $allowedRoles, true)) {
        $error = 'Invalid reaction role.';
        return false;
    }

    if (!in_array($reaction, $allowedReactions, true)) {
        $error = 'Invalid reaction selected.';
        return false;
    }

    $audience = $userRole === 'teacher' ? 'teachers' : 'parents';
    $visibility = $db->prepare('SELECT id FROM announcements WHERE id = ? AND is_active = 1 AND (audience = ? OR audience = "both") LIMIT 1');
    if (!$visibility) {
        $error = 'Unable to validate the announcement.';
        return false;
    }

    $visibility->bind_param('is', $announcementId, $audience);
    $visibility->execute();
    $visibleResult = $visibility->get_result();
    $visibleRow = $visibleResult ? $visibleResult->fetch_assoc() : null;
    $visibility->close();

    if (!$visibleRow) {
        $error = 'That announcement is no longer available.';
        return false;
    }

    $existing = $db->prepare('SELECT id, reaction FROM announcement_reactions WHERE announcement_id = ? AND user_id = ? AND user_role = ? LIMIT 1');
    if (!$existing) {
        $error = 'Unable to save your reaction right now.';
        return false;
    }

    $existing->bind_param('iis', $announcementId, $userId, $userRole);
    $existing->execute();
    $existingResult = $existing->get_result();
    $existingRow = $existingResult ? $existingResult->fetch_assoc() : null;
    $existing->close();

    if ($existingRow && (string) ($existingRow['reaction'] ?? '') === $reaction) {
        $delete = $db->prepare('DELETE FROM announcement_reactions WHERE id = ? LIMIT 1');
        if (!$delete) {
            $error = 'Unable to remove your reaction right now.';
            return false;
        }

        $reactionId = (int) ($existingRow['id'] ?? 0);
        $delete->bind_param('i', $reactionId);
        $deleted = $delete->execute();
        $delete->close();

        if (!$deleted) {
            $error = 'Unable to remove your reaction right now.';
            return false;
        }

        return true;
    }

    $upsert = $db->prepare('INSERT INTO announcement_reactions (announcement_id, user_id, user_role, reaction) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE reaction = VALUES(reaction), updated_at = CURRENT_TIMESTAMP');
    if (!$upsert) {
        $error = 'Unable to save your reaction right now.';
        return false;
    }

    $upsert->bind_param('iiss', $announcementId, $userId, $userRole, $reaction);
    $saved = $upsert->execute();
    $upsert->close();

    if (!$saved) {
        $error = 'Unable to save your reaction right now.';
        return false;
    }

    return true;
}

function fetch_announcement_reaction_summary(array $announcementIds): array
{
    global $db;

    if (!$db instanceof mysqli || $announcementIds === []) {
        return [];
    }

    $announcementIds = array_values(array_unique(array_map('intval', $announcementIds)));
    $placeholders = implode(',', array_fill(0, count($announcementIds), '?'));
    $types = str_repeat('i', count($announcementIds));
    $query = 'SELECT announcement_id, reaction, COUNT(*) AS total FROM announcement_reactions WHERE announcement_id IN (' . $placeholders . ') GROUP BY announcement_id, reaction';
    $stmt = $db->prepare($query);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param($types, ...$announcementIds);
    $stmt->execute();
    $result = $stmt->get_result();
    $summary = [];
    while ($result && ($row = $result->fetch_assoc())) {
        $announcementId = (int) ($row['announcement_id'] ?? 0);
        $reaction = (string) ($row['reaction'] ?? '');
        if ($announcementId < 1 || $reaction === '') {
            continue;
        }
        if (!isset($summary[$announcementId])) {
            $summary[$announcementId] = ['like' => 0, 'love' => 0, 'wow' => 0, 'sad' => 0];
        }
        $summary[$announcementId][$reaction] = (int) ($row['total'] ?? 0);
    }
    $stmt->close();

    return $summary;
}

function fetch_user_announcement_reactions(int $userId, string $userRole, array $announcementIds): array
{
    global $db;

        if (!$db instanceof mysqli || $announcementIds === []) {
        return [];
    }

    $allowedRoles = ['teacher', 'parent'];
    if (!in_array($userRole, $allowedRoles, true)) {
        return [];
    }

    $announcementIds = array_values(array_unique(array_map('intval', $announcementIds)));
    $placeholders = implode(',', array_fill(0, count($announcementIds), '?'));
    $types = 'is' . str_repeat('i', count($announcementIds));
    $query = 'SELECT announcement_id, reaction FROM announcement_reactions WHERE user_id = ? AND user_role = ? AND announcement_id IN (' . $placeholders . ')';
    $stmt = $db->prepare($query);
    if (!$stmt) {
        return [];
    }

    $params = array_merge([$userId, $userRole], $announcementIds);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $map = [];
    while ($result && ($row = $result->fetch_assoc())) {
        $map[(int) ($row['announcement_id'] ?? 0)] = (string) ($row['reaction'] ?? '');
    }
    $stmt->close();

    return $map;
}

function display_name_from_user(array $user, string $fallback = 'User'): string
{
    $fullName = trim((string) ($user['full_name'] ?? ''));
    if ($fullName !== '') {
        return $fullName;
    }

    $composed = trim((string) (($user['first_name'] ?? '') . ' ' . ($user['surname'] ?? '')));
    return $composed !== '' ? $composed : $fallback;
}

function fetch_first_user_by_role(string $role): ?array
{
    global $db;

    if (!$db instanceof mysqli) {
        return null;
    }

    $stmt = $db->prepare('SELECT * FROM users WHERE role = ? ORDER BY id ASC LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $role);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function normalize_direct_chat_participants(int $userAId, string $userARole, int $userBId, string $userBRole): ?array
{
    $allowedRoles = ['principal', 'teacher', 'parent'];
    if ($userAId < 1 || $userBId < 1 || !in_array($userARole, $allowedRoles, true) || !in_array($userBRole, $allowedRoles, true)) {
        return null;
    }

    if ($userAId === $userBId && $userARole === $userBRole) {
        return null;
    }

    $first = ['id' => $userAId, 'role' => $userARole];
    $second = ['id' => $userBId, 'role' => $userBRole];
    $firstKey = $userARole . ':' . str_pad((string) $userAId, 10, '0', STR_PAD_LEFT);
    $secondKey = $userBRole . ':' . str_pad((string) $userBId, 10, '0', STR_PAD_LEFT);

    return strcmp($firstKey, $secondKey) <= 0 ? [$first, $second] : [$second, $first];
}

function fetch_direct_chat_thread(int $userAId, string $userARole, int $userBId, string $userBRole): ?array
{
    global $db;

        if (!$db instanceof mysqli) {
        return null;
    }

    $participants = normalize_direct_chat_participants($userAId, $userARole, $userBId, $userBRole);
    if ($participants === null) {
        return null;
    }

    [$first, $second] = $participants;
    $stmt = $db->prepare('SELECT * FROM direct_chat_threads WHERE participant_one_id = ? AND participant_one_role = ? AND participant_two_id = ? AND participant_two_role = ? ORDER BY id DESC LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('isis', $first['id'], $first['role'], $second['id'], $second['role']);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function find_or_create_direct_chat_thread(int $userAId, string $userARole, int $userBId, string $userBRole, string $subject = 'Direct Chat'): ?int
{
    global $db;

        if (!$db instanceof mysqli) {
        return null;
    }

    $participants = normalize_direct_chat_participants($userAId, $userARole, $userBId, $userBRole);
    if ($participants === null) {
        return null;
    }

    [$first, $second] = $participants;
    $existing = fetch_direct_chat_thread($userAId, $userARole, $userBId, $userBRole);
    if ($existing && isset($existing['id'])) {
        return (int) $existing['id'];
    }

    $subject = trim($subject) !== '' ? trim($subject) : 'Direct Chat';
    $insert = $db->prepare('INSERT INTO direct_chat_threads (participant_one_id, participant_one_role, participant_two_id, participant_two_role, subject) VALUES (?, ?, ?, ?, ?)');
    if (!$insert) {
        return null;
    }

    $insert->bind_param('isiss', $first['id'], $first['role'], $second['id'], $second['role'], $subject);
    $created = $insert->execute();
    $threadId = $created ? (int) $db->insert_id : null;
    $insert->close();

    return $threadId;
}

function send_direct_chat_message(int $threadId, int $senderId, string $senderRole, string $message, ?string &$error = null): bool
{
    global $db;

        if (!$db instanceof mysqli) {
        $error = 'Database is not ready.';
        return false;
    }

    $message = trim($message);
    if ($message === '') {
        $error = 'Please type a message first.';
        return false;
    }

    $allowedRoles = ['principal', 'teacher', 'parent'];
    if ($senderId < 1 || !in_array($senderRole, $allowedRoles, true)) {
        $error = 'Invalid sender.';
        return false;
    }

    $thread = $db->prepare('SELECT participant_one_id, participant_one_role, participant_two_id, participant_two_role FROM direct_chat_threads WHERE id = ? LIMIT 1');
    if (!$thread) {
        $error = 'Unable to find the conversation.';
        return false;
    }

    $thread->bind_param('i', $threadId);
    $thread->execute();
    $threadResult = $thread->get_result();
    $threadRow = $threadResult ? $threadResult->fetch_assoc() : null;
    $thread->close();

    if (!$threadRow) {
        $error = 'Unable to find the conversation.';
        return false;
    }

    $isParticipant = (
        ((int) ($threadRow['participant_one_id'] ?? 0) === $senderId && (string) ($threadRow['participant_one_role'] ?? '') === $senderRole)
        || ((int) ($threadRow['participant_two_id'] ?? 0) === $senderId && (string) ($threadRow['participant_two_role'] ?? '') === $senderRole)
    );
    if (!$isParticipant) {
        $error = 'You cannot send messages in this conversation.';
        return false;
    }

    $insert = $db->prepare('INSERT INTO direct_chat_messages (thread_id, sender_id, sender_role, message) VALUES (?, ?, ?, ?)');
    if (!$insert) {
        $error = 'Unable to save the message.';
        return false;
    }

    $insert->bind_param('iiss', $threadId, $senderId, $senderRole, $message);
    $sent = $insert->execute();
    $insert->close();

    if (!$sent) {
        $error = 'Unable to save the message.';
        return false;
    }

    $update = $db->prepare('UPDATE direct_chat_threads SET last_message_at = NOW() WHERE id = ?');
    if ($update) {
        $update->bind_param('i', $threadId);
        $update->execute();
        $update->close();
    }

    return true;
}

function fetch_direct_chat_messages(int $threadId): array
{
    global $db;

        if (!$db instanceof mysqli) {
        return [];
    }

    $stmt = $db->prepare('SELECT id, sender_id, sender_role, message, is_read, created_at FROM direct_chat_messages WHERE thread_id = ? ORDER BY created_at ASC, id ASC');
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $threadId);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    return $rows;
}

function mark_direct_chat_messages_read(int $threadId, int $viewerId, string $viewerRole): void
{
    global $db;

        if (!$db instanceof mysqli || $viewerId < 1) {
        return;
    }

    $stmt = $db->prepare('UPDATE direct_chat_messages SET is_read = 1 WHERE thread_id = ? AND NOT (sender_id = ? AND sender_role = ?)');
    if (!$stmt) {
        return;
    }

    $stmt->bind_param('iis', $threadId, $viewerId, $viewerRole);
    $stmt->execute();
    $stmt->close();
}

function find_or_create_support_thread(int $principalId, int $requesterId, string $requesterRole, string $subject = 'Direct Chat'): ?int
{
    global $db;

        if (!$db instanceof mysqli) {
        return null;
    }

    $allowedRoles = ['teacher', 'parent'];
    if (!in_array($requesterRole, $allowedRoles, true)) {
        return null;
    }

    $select = $db->prepare('SELECT id FROM support_threads WHERE principal_id = ? AND requester_id = ? AND requester_role = ? ORDER BY id DESC LIMIT 1');
    if (!$select) {
        return null;
    }

    $select->bind_param('iis', $principalId, $requesterId, $requesterRole);
    $select->execute();
    $result = $select->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $select->close();

    if ($row && isset($row['id'])) {
        return (int) $row['id'];
    }

    $insert = $db->prepare('INSERT INTO support_threads (principal_id, requester_id, requester_role, subject) VALUES (?, ?, ?, ?)');
    if (!$insert) {
        return null;
    }

    $insert->bind_param('iiss', $principalId, $requesterId, $requesterRole, $subject);
    $created = $insert->execute();
    $threadId = $created ? (int) $db->insert_id : null;
    $insert->close();

    return $threadId;
}

function send_support_message(int $threadId, int $senderId, string $senderRole, string $message, ?string &$error = null): bool
{
    global $db;

        if (!$db instanceof mysqli) {
        $error = 'Database is not ready.';
        return false;
    }

    $message = trim($message);
    if ($message === '') {
        $error = 'Please type a message first.';
        return false;
    }

    $allowedRoles = ['teacher', 'parent', 'principal'];
    if (!in_array($senderRole, $allowedRoles, true)) {
        $error = 'Invalid sender role.';
        return false;
    }

    $thread = $db->prepare('SELECT principal_id, requester_id, requester_role FROM support_threads WHERE id = ? LIMIT 1');
    if (!$thread) {
        $error = 'Unable to find the conversation.';
        return false;
    }

    $thread->bind_param('i', $threadId);
    $thread->execute();
    $threadResult = $thread->get_result();
    $threadRow = $threadResult ? $threadResult->fetch_assoc() : null;
    $thread->close();

    if (!$threadRow) {
        $error = 'Unable to find the conversation.';
        return false;
    }

    $isPrincipalSender = $senderRole === 'principal' && (int) ($threadRow['principal_id'] ?? 0) === $senderId;
    $isRequesterSender = $senderRole === (string) ($threadRow['requester_role'] ?? '') && (int) ($threadRow['requester_id'] ?? 0) === $senderId;
    if (!$isPrincipalSender && !$isRequesterSender) {
        $error = 'You cannot send messages in this conversation.';
        return false;
    }

    $insert = $db->prepare('INSERT INTO support_messages (thread_id, sender_id, sender_role, message) VALUES (?, ?, ?, ?)');
    if (!$insert) {
        $error = 'Unable to save the message.';
        return false;
    }

    $insert->bind_param('iiss', $threadId, $senderId, $senderRole, $message);
    $sent = $insert->execute();
    $insert->close();

    if (!$sent) {
        $error = 'Unable to save the message.';
        return false;
    }

    $update = $db->prepare('UPDATE support_threads SET last_message_at = NOW() WHERE id = ?');
    if ($update) {
        $update->bind_param('i', $threadId);
        $update->execute();
        $update->close();
    }

    return true;
}

function fetch_support_messages(int $threadId): array
{
    global $db;

        if (!$db instanceof mysqli) {
        return [];
    }

    $stmt = $db->prepare('SELECT sm.id, sm.sender_id, sm.sender_role, sm.message, sm.is_read, sm.created_at
        FROM support_messages sm
        INNER JOIN support_threads st ON st.id = sm.thread_id
        WHERE sm.thread_id = ?
            AND (
                (sm.sender_role = "principal" AND sm.sender_id = st.principal_id)
                OR (sm.sender_role = st.requester_role AND sm.sender_id = st.requester_id)
            )
        ORDER BY sm.created_at ASC, sm.id ASC');
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $threadId);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    return $rows;
}

function fetch_support_thread_for_requester(int $principalId, int $requesterId, string $requesterRole): ?array
{
    global $db;

        if (!$db instanceof mysqli) {
        return null;
    }

    $stmt = $db->prepare('SELECT * FROM support_threads WHERE principal_id = ? AND requester_id = ? AND requester_role = ? ORDER BY id DESC LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('iis', $principalId, $requesterId, $requesterRole);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function fetch_support_threads_for_principal(int $principalId, ?string $requesterRole = null): array
{
    global $db;

        if (!$db instanceof mysqli) {
        return [];
    }

    $query = 'SELECT st.id, st.requester_id, st.requester_role, st.subject,
            (
                SELECT sm.created_at
                FROM support_messages sm
                WHERE sm.thread_id = st.id
                    AND (
                        (sm.sender_role = "principal" AND sm.sender_id = st.principal_id)
                        OR (sm.sender_role = st.requester_role AND sm.sender_id = st.requester_id)
                    )
                ORDER BY sm.created_at DESC, sm.id DESC
                LIMIT 1
            ) AS last_message_at,
            st.created_at,
            u.full_name, u.first_name, u.surname, u.email, u.child_name, u.teaching_subject,
            (
                SELECT sm.message
                FROM support_messages sm
                WHERE sm.thread_id = st.id
                    AND (
                        (sm.sender_role = "principal" AND sm.sender_id = st.principal_id)
                        OR (sm.sender_role = st.requester_role AND sm.sender_id = st.requester_id)
                    )
                ORDER BY sm.created_at DESC, sm.id DESC
                LIMIT 1
            ) AS last_message,
            (
                SELECT COUNT(*)
                FROM support_messages sm
                WHERE sm.thread_id = st.id
                    AND sm.sender_role = st.requester_role
                    AND sm.sender_id = st.requester_id
                    AND sm.is_read = 0
            ) AS unread_count
        FROM support_threads st
        INNER JOIN users u ON u.id = st.requester_id
        WHERE st.principal_id = ?';

    if ($requesterRole !== null) {
        $query .= ' AND st.requester_role = ?';
    }

    $query .= ' ORDER BY st.last_message_at DESC, st.id DESC';
    $stmt = $db->prepare($query);
    if (!$stmt) {
        return [];
    }

    if ($requesterRole !== null) {
        $stmt->bind_param('is', $principalId, $requesterRole);
    } else {
        $stmt->bind_param('i', $principalId);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    return $rows;
}

function mark_thread_messages_read(int $threadId, string $viewerRole): void
{
    global $db;

        if (!$db instanceof mysqli) {
        return;
    }

    $stmt = $db->prepare('UPDATE support_messages SET is_read = 1 WHERE thread_id = ? AND sender_role <> ?');
    if (!$stmt) {
        return;
    }

    $stmt->bind_param('is', $threadId, $viewerRole);
    $stmt->execute();
    $stmt->close();
}
