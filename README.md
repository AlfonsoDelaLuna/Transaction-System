# Transaction System

A PHP-based web application for managing transactions. The system have separate functionalities for regular users/cashier and administrators.

## Process Before
The cashier would take customer orders by writing them down on pen and paper and then hand these notes to the chef for preparation. This manual process relied entirely on clear handwriting and careful tracking. During peak hours, the cashier often had to manage several orders at the same time, which made the process slower and more stressful. Since everything was recorded manually, there was a high chance of confusion or errors. Overall, the system was inefficient and prone to delays.

## Problems Encounterd
- Multiple customers ordering at the same time caused confusion.
- Handwritten notes were sometimes unclear or hard to read.
- Orders could easily be misplaced or lost.
- Delays often occurred in communicating orders to the chef.
- Mistakes in food preparation happened due to miscommunication.

## Features

Based on the file structure, the system likely includes the following features:

*   **User Authentication:** login.php and logout.php suggest a user login and logout system.
*   **User-level actions:**
    *   Placing orders (user/order.php)
    *   Processing orders (user/process_order.php)
*   **Admin-level actions:**
    *   A dashboard for viewing system activity (admin/dashboard.php)
    *   Checking and managing orders (admin/check_orders.php)
*   **Database Integration:** The database/transaction_system.sql file indicates that the application uses a MySQL or similar database to store data.

## Folder Structure

.├── admin
│   ├── check_orders.php
│   └── dashboard.php
├── database
│   └── transaction_system.sql
├── includes
├── user
│   ├── order.php
│   └── process_order.php
├── check_orders.php
├── hash.php
├── login.php
└── logout.php


## How to Use

1.  **Database Setup:** Import the database/transaction_system.sql file into your MySQL database.
2.  **Configuration:** You may need to configure the database connection details in the PHP files (likely within an includes directory or at the top of the main files).
3.  **Running the application:** Place the project folder in your web server's root directory (e.g., htdocs for XAMPP) and access it through your browser.
