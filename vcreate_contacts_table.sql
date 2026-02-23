-- Drop table if exists
DROP TABLE IF EXISTS contacts;

-- Create contacts table
CREATE TABLE contacts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(255) NULL,
    last_name VARCHAR(255) NULL,
    email VARCHAR(255) NULL,
    phone VARCHAR(255) NULL,
    company VARCHAR(255) NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    user_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX user_email_idx (user_id, email),
    INDEX user_status_idx (user_id, status),
    INDEX created_at_idx (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample data
INSERT INTO contacts (first_name, last_name, email, phone, company, user_id, status) VALUES
('John', 'Doe', 'john@example.com', '1234567890', 'ABC Corp', 1, 'active'),
('Jane', 'Smith', 'jane@example.com', '0987654321', 'XYZ Ltd', 1, 'active'),
('Mike', 'Johnson', 'mike@example.com', '1122334455', 'Tech Solutions', 1, 'active');
