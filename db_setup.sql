CREATE DATABASE IF NOT EXISTS schoolportal_db;
USE schoolportal_db;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    first_name VARCHAR(100) NULL,
    surname VARCHAR(100) NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('teacher', 'parent', 'principal') NOT NULL,
    approval_status ENUM('pending', 'approved') NOT NULL DEFAULT 'approved',
    teaching_subject VARCHAR(120) NULL,
    teaching_class VARCHAR(100) NULL,
    requested_teaching_class VARCHAR(100) NULL,
    child_name VARCHAR(150) NULL,
    child_count INT UNSIGNED NULL,
    child_details TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS announcements (
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
);

CREATE TABLE IF NOT EXISTS announcement_reactions (
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
);