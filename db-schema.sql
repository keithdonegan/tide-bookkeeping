-- Database Schema for Tide Expense Tracker

-- Create users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create categories table
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create transactions table
CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id VARCHAR(100) NOT NULL UNIQUE,
    transaction_date DATE NOT NULL,
    description TEXT NOT NULL,
    paid_in DECIMAL(10,2) DEFAULT 0,
    paid_out DECIMAL(10,2) DEFAULT 0,
    category_id INT NULL,
    invoice_path VARCHAR(255) NULL,
    comments TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create index for faster filtering/searching
CREATE INDEX idx_transaction_date ON transactions(transaction_date);
CREATE INDEX idx_category_id ON transactions(category_id);

-- Insert some default categories (examples)
INSERT INTO categories (name) VALUES 
('Office Expenses'),
('Software & Subscriptions'),
('Marketing'),
('Travel'),
('Utilities'),
('Client Expenses'),
('Uncategorized');
