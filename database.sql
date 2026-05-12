CREATE DATABASE IF NOT EXISTS ba_tool CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ba_tool;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('lead', 'ba') NOT NULL DEFAULT 'ba',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Bảng tasks
CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    -- Thông tin public requester
    requester_name VARCHAR(100) NOT NULL,
    requester_dept VARCHAR(100) NOT NULL,
    system_name VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    task_type VARCHAR(100) NOT NULL,
    priority_requester VARCHAR(50) NOT NULL,
    start_date DATE NOT NULL,
    expected_end_date DATE NOT NULL,
    attachment_url TEXT NULL,
    
    -- Thông tin BA nội bộ
    priority_ba VARCHAR(50) NULL,
    office_link TEXT NULL,
    status VARCHAR(50) DEFAULT 'Chờ tiếp nhận',
    actual_end_date DATE NULL,
    assignee_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assignee_id) REFERENCES users(id) ON DELETE SET NULL
);


