<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die('Unauthorized access');
}

require_once '../includes/db_connect.php';
require_once 'dashboard.php';

echo "<h2>Database Check Results</h2>";

// Check total orders
$stmt = $pdo->query("SELECT COUNT(*) FROM orders");
$totalOrders = $stmt->fetchColumn();
echo "Total orders: " . $totalOrders . "<br>";

if ($totalOrders > 0) {
    // Check orders with items
    $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE order_items IS NOT NULL AND order_items != ''");
    $ordersWithItems = $stmt->fetchColumn();
    echo "Orders with items: " . $ordersWithItems . "<br>";

    if ($ordersWithItems > 0) {
        // Get a sample order
        $stmt = $pdo->query("SELECT order_items FROM orders WHERE order_items IS NOT NULL AND order_items != '' LIMIT 1");
        $sampleOrder = $stmt->fetchColumn();
        echo "Sample order items: <pre>" . htmlspecialchars($sampleOrder) . "</pre><br>";
    }
}

// Check if best sellers calculation works
$bestSellers = calculateBestSellers($pdo);
echo "Best Sellers result: <pre>" . htmlspecialchars(json_encode($bestSellers, JSON_PRETTY_PRINT)) . "</pre>";

// Check error log
$errorLog = error_get_last();
if ($errorLog) {
    echo "<h3>Last Error:</h3>";
    echo "<pre>" . htmlspecialchars(print_r($errorLog, true)) . "</pre>";
}
?> 