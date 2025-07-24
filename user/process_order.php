<?php
session_start();
require_once '../includes/db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit();
}

if (
    $input['payment_method'] === 'gcash' &&
    (!isset($input['payment_details']['transaction_no']) ||
        !preg_match('/^[0-9]{8}$/', $input['payment_details']['transaction_no']))
) {
    echo json_encode(['success' => false, 'message' => 'Invalid GCash transaction number. It must be 8 digits.']);
    exit();
}

try {
    $pdo->beginTransaction();

    // Insert order with user_id
    $stmt = $pdo->prepare("INSERT INTO orders (user_id, customer_name, order_items, total_amount, payment_method, payment_details) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $_SESSION['user_id'], // Add the logged-in user's ID
        $input['customer_name'],
        json_encode($input['order_items']),
        $input['total_amount'],
        $input['payment_method'],
        json_encode($input['payment_details'])
    ]);

    $orderId = $pdo->lastInsertId();

    // Insert transaction
    $transactionNo = $input['payment_method'] === 'gcash' ? $input['payment_details']['transaction_no'] : null;
    $stmt = $pdo->prepare("INSERT INTO transactions (order_id, transaction_no, amount, payment_method) VALUES (?, ?, ?, ?)");
    $stmt->execute([
        $orderId,
        $transactionNo,
        $input['total_amount'],
        $input['payment_method']
    ]);

    $pdo->commit();

    echo json_encode(['success' => true, 'order_id' => $orderId]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>