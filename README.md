# Tide Expense Tracker

A web-based expense tracking application designed to help businesses manage their financial transactions from Tide bank accounts. This application allows you to import transaction data, categorize expenses, attach invoices, and maintain proper financial records.

## Features

- **CSV Import**: Easily import transaction data from Tide bank statements
- **Transaction Management**: View and filter all your financial transactions
- **Categorization**: Assign categories to transactions for better financial reporting
- **Invoice Attachment**: Attach digital copies of invoices or receipts to transactions
- **Comments**: Add notes to transactions for additional context
- **Financial Year Filtering**: Quickly view transactions within specific financial years
- **Data Filtering**: Filter transactions by date, description, amount, category, and more
- **User Authentication**: Secure access control with user authentication

## Technology Stack

- **Backend**: PHP 7.4+ with PDO for database operations
- **Database**: MySQL/MariaDB
- **Frontend**: HTML, CSS, JavaScript (Vanilla JS)
- **Libraries**: 
  - Flatpickr for date picking
  - No heavyweight frameworks for faster performance

## Getting Started

### Prerequisites

- PHP 7.4 or higher
- MySQL or MariaDB database
- Web server (Apache/Nginx)

### Project Structure
```tide-expense-tracker/
│── db-schema.sql                   # Complete database schema
├── includes/                        # Core PHP includes
│   ├── auth.php                     # Authentication handling
│   └── db_config.php                # Database configuration
├── uploads/                         # File upload directory
│   └── invoices/                    # Invoice storage with year/month structure
├── add_category.php                 # API for adding new categories
├── add_comment.php                  # Logic for adding transaction comments
├── delete_category.php              # API for removing categories
├── delete_invoice.php               # Logic for deleting attached invoices
├── error_log                        # Application error logs
├── index.php                        # Main application dashboard
├── login.php                        # User login interface
├── logout.php                       # Session termination
├── manage_categories.php            # Category management interface
├── save_category.php                # API for updating transaction categories
├── save_single_comment.php          # API for saving transaction comments
├── save_transaction_category.php    # Alternative category saving endpoint
├── upload_csv.php                   # CSV import functionality
├── upload_invoice.php               # Invoice upload handler
├── view_invoice.php                 # Invoice viewer
└── README.md                        # This file
```

This structure includes all the PHP files from your application, showing the complete picture of how the components work together. You can replace the previous structure section with this more comprehensive one in the README.md.RetryClaude can make mistakes. Please double-check responses.
```

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/your-username/tide-expense-tracker.git
   cd tide-expense-tracker

Create the database
sqlCREATE DATABASE tide_expense_tracker;

Import the database schema
bashmysql -u your_username -p tide_expense_tracker < database/schema.sql
The schema.sql file creates all necessary tables:

users - For authentication
categories - For transaction categorization (with default categories)
transactions - For storing all financial transactions


Configure database connection

Copy includes/db_config.sample.php to includes/db_config.php
Edit the file with your database credentials:
phpdefine('DB_HOST', 'localhost');
define('DB_NAME', 'tide_expense_tracker');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
define('DB_CHARSET', 'utf8mb4');



Set up upload directories
bashmkdir -p uploads/invoices
chmod 755 uploads/invoices

Create an admin user
Create a file called create_admin.php with the following code:
php<?php
require_once __DIR__ . '/includes/db_config.php';

$username = 'admin'; // Change this to your preferred admin username
$password = 'your_secure_password'; // Change this to a secure password

$password_hash = password_hash($password, PASSWORD_BCRYPT);

try {
    $sql = "INSERT INTO users (username, password_hash) VALUES (:username, :password_hash)";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':username', $username, PDO::PARAM_STR);
    $stmt->bindParam(':password_hash', $password_hash, PDO::PARAM_STR);
    $stmt->execute();
    
    echo "Admin user created successfully!";
} catch (\PDOException $e) {
    echo "Error creating admin user: " . $e->getMessage();
}
Then run this script from your browser or command line to create the admin user, and delete it afterward for security:
bashphp -f create_admin.php

Configure your web server

Point your web server's document root to the project directory
Ensure PHP is properly configured



Usage
Importing Transactions

Export transactions from your Tide bank account as a CSV file
Log in to the Tide Expense Tracker
Click "Upload Bank Statement CSV" and select your CSV file
The system will import unique transactions, avoiding duplicates

Managing Transactions

Filter Transactions: Use the filter icons to search and filter transactions
Categorize: Select categories from dropdown menus for each transaction
Attach Invoices: Click "Attach" to upload invoice PDFs or images
Add Comments: Enter notes in the comments field (auto-saves on blur)

Creating Categories

Select "Add New Category" from any category dropdown
Enter the category name when prompted
The new category will be available for all transactions

Financial Year Filtering

Use the Financial Year dropdown to quickly view transactions for specific fiscal periods
Select "Show All Dates" to see the complete transaction history

Database Structure
The application uses a simple database schema:

users: Authentication data for secure login
transactions: Main transaction records with amounts, dates, and references
categories: Transaction categories for expense classification

The included schema.sql file contains the complete database structure with:

All necessary tables with appropriate relationships
Default categories to get you started
Indexes for optimized performance
Comments explaining each table's purpose

Security Considerations
This application implements:

PDO prepared statements to prevent SQL injection
Input validation and sanitization
Authentication requirements for all pages
Path traversal prevention for file access
Session-based authentication with CSRF protection

## Contributing

Fork the repository
- Create your feature branch (`git checkout -b feature/amazing-feature`)
- Commit your changes (`git commit -m 'Add some amazing feature'`)
- Push to the branch (`git push origin feature/amazing-feature`)
- Open a Pull Request

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Acknowledgments

Flatpickr for the date picker functionality
Tide Bank for the financial services that this tool complements
