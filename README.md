# Transaction System
A PHP-based web application designed for managing transactions for both cashiers and administrators. The cashier can handle customer orders and process their payments, while the admin can manage the menu, such as updating item details and prices and generate revenue reports ranging from weekly to yearly.

## Process Before
The cashier would take customer orders by writing them down on pen and paper and then hand these notes to the chef for preparation. This manual process relied entirely on clear handwriting and careful tracking. During peak hours, the cashier often had to manage several orders at the same time, which made the process slower and more stressful. Since everything was recorded manually, there was a high chance of confusion or errors. Overall, the system was inefficient and prone to delays.

## Problems Encounterd
- Multiple customers ordering at the same time caused confusion.
- Handwritten notes were sometimes unclear or hard to read.
- Orders could easily be misplaced or lost.
- Delays often occurred in communicating orders to the chef.
- Mistakes in food preparation happened due to miscommunication.

## Features
This system primarily functions as an online ordering platform, built on a database-driven architecture to manage users, products, and transactions.

1. User Authentication:
- Developer: Implements login.php and logout.php with secure password hashing, session management, and input validation to control access.
- User: Provides a secure way to sign in and out, ensuring their account and activities are protected and accessible only to them.

2. User-Level Ordering:
- Developer: Uses user/order.php for displaying products and user/process_order.php for securely handling order submissions. This involves transactional database writes (to orders and order_items tables) and stock management.
- User: Allows customers to browse products, place orders, and receive confirmation, with the assurance that their selections are accurately processed.

3. Admin-Level Management:
- Developer: admin/dashboard.php provides an overview, while admin/check_orders.php allows authorized administrators to view, filter, and update order statuses. Access is restricted by user roles.
- User (Admin): Offers a centralized hub to monitor business activity and efficiently manage customer orders, track their progress, and update their status.

4. Database Foundation:
- Developer: The database/transaction_system.sql defines the schema for users, products, orders, and order_items tables, ensuring data integrity, relationships, and enabling robust transactional operations.
- User: Guarantees that all account information, product listings, and order history are reliably stored and consistently retrieved, providing a persistent and trustworthy experience.

## How to Use
1.  **Database Setup:** Import the database/transaction_system.sql file into your MySQL database.
2.  **Configuration:** You may need to configure the database connection details in the PHP files (likely within an includes directory or at the top of the main files).
3.  **Running the application:** Place the project folder in your web server's root directory (e.g., htdocs for XAMPP) and access it through your browser.

## Folder Structure
```text
project_root/
├── admin/
│   ├── pages/
│   │   ├── dashboard.php
│   │   ├── check_orders.php
│   │   └── reports.php
│   ├── includes/
│   │   ├── header.php
│   │   ├── sidebar.php
│   │   └── footer.php
│   └── assets/
│       ├── css/
│       ├── js/
│       └── images/
│
├── user/
│   ├── pages/
│   │   ├── order.php
│   │   └── process_order.php
│   ├── includes/
│   │   ├── header.php
│   │   ├── footer.php
│   │   └── navbar.php
│   └── assets/
│       ├── css/
│       ├── js/
│       └── images/
│
├── config/
│   ├── database.php
│   └── app_config.php
│
├── core/
│   ├── functions.php
│   ├── session_handler.php
│   └── auth.php
│
├── database/
│   └── transaction_system.sql
│
├── public/
│   ├── index.php
│   ├── login.php
│   ├── logout.php
│   └── register.php
│
└── assets/
    ├── css/
    ├── js/
    └── images/
```
