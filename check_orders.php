<?php
require_once 'includes/db_connect.php';

// Check total orders
$stmt = $pdo->query("SELECT COUNT(*) FROM orders");
$totalOrders = $stmt->fetchColumn();
echo "Total orders: " . $totalOrders . "\n";

if ($totalOrders > 0) {
    // Check orders with items
    $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE order_items IS NOT NULL AND order_items != ''");
    $ordersWithItems = $stmt->fetchColumn();
    echo "Orders with items: " . $ordersWithItems . "\n";

    if ($ordersWithItems > 0) {
        // Get a sample order
        $stmt = $pdo->query("SELECT order_items FROM orders WHERE order_items IS NOT NULL AND order_items != '' LIMIT 1");
        $sampleOrder = $stmt->fetchColumn();
        echo "Sample order items: " . $sampleOrder . "\n";
    }
}
?> 