<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

try {
    $host = 'localhost';
    $dbname = 'transaction_system';
    $username = 'root';
    $password = '';
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
/**
 * Starts a session and ensures the user is an admin.
 * Redirects to login.php if not an admin.
 */
function initializeAdminSession()
{
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
        header('Location: ../login.php');
        exit();
    }
}

/**
 * Sets a flash message in the session.
 */
function setFlashMessage(string $key, string $type, string $text)
{
    $_SESSION[$key] = ['type' => $type, 'text' => $text];
}

/**
 * Redirects to a given location and exits.
 */
function redirectAndExit(string $location)
{
    header('Location: ' . $location);
    exit();
}

// --- STAFF MANAGEMENT ACTION HANDLERS ---
function handleAddStaff(PDO $pdo, array $data)
{
    $staffFullName = trim($data['staff_full_name']);
    $staffUsername = trim($data['staff_username']);
    $staffPassword = $data['staff_password'];
    $staffEmail = trim($data['staff_email']);
    $staffRole = $data['staff_role'];

    if (empty($staffFullName) || empty($staffUsername) || empty($staffPassword) || empty($staffEmail)) {
        setFlashMessage('staff_message', 'danger', 'All fields are required for adding staff.');
        $_SESSION['staff_message_reopen_modal'] = ['modal_id' => 'addStaffModal']; // For JS to reopen
    } elseif (strlen($staffPassword) < 6) {
        setFlashMessage('staff_message', 'danger', 'Password must be at least 6 characters long.');
        $_SESSION['staff_message_reopen_modal'] = ['modal_id' => 'addStaffModal'];
    } elseif (!filter_var($staffEmail, FILTER_VALIDATE_EMAIL)) {
        setFlashMessage('staff_message', 'danger', 'Invalid email format.');
        $_SESSION['staff_message_reopen_modal'] = ['modal_id' => 'addStaffModal'];
    } else {
        $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmtCheck->execute([$staffUsername, $staffEmail]);
        if ($stmtCheck->fetch()) {
            setFlashMessage('staff_message', 'danger', 'Username or Email already exists.');
            $_SESSION['staff_message_reopen_modal'] = ['modal_id' => 'addStaffModal'];
        } else {
            $hashedPassword = password_hash($staffPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (full_name, username, password, email, role) VALUES (?, ?, ?, ?, ?)");
            try {
                $stmt->execute([$staffFullName, $staffUsername, $hashedPassword, $staffEmail, $staffRole]);
                setFlashMessage('staff_message', 'success', 'Staff member added successfully!');
            } catch (PDOException $e) {
                setFlashMessage('staff_message', 'danger', 'Database error: ' . htmlspecialchars($e->getMessage()));
                $_SESSION['staff_message_reopen_modal'] = ['modal_id' => 'addStaffModal'];
            }
        }
    }
}

function handleEditStaff(PDO $pdo, array $data)
{
    $staffId = $data['edit_staff_id'];
    $staffFullName = trim($data['edit_staff_full_name']);
    $staffUsername = trim($data['edit_staff_username']);
    $staffPassword = $data['edit_staff_password']; // New password, if provided
    $staffEmail = trim($data['edit_staff_email']);
    $staffRole = $data['edit_staff_role'];

    if (empty($staffFullName) || empty($staffUsername) || empty($staffEmail) || empty($staffId)) {
        setFlashMessage('staff_message', 'danger', 'Full name, username, and email are required for editing.');
        $_SESSION['staff_message_reopen_modal'] = ['modal_id' => 'editStaffModal', 'user_data' => $data]; // Pass data to repopulate
    } elseif (!filter_var($staffEmail, FILTER_VALIDATE_EMAIL)) {
        setFlashMessage('staff_message', 'danger', 'Invalid email format.');
        $_SESSION['staff_message_reopen_modal'] = ['modal_id' => 'editStaffModal', 'user_data' => $data];
    } elseif (!empty($staffPassword) && strlen($staffPassword) < 6) {
        setFlashMessage('staff_message', 'danger', 'New password must be at least 6 characters long.');
        $_SESSION['staff_message_reopen_modal'] = ['modal_id' => 'editStaffModal', 'user_data' => $data];
    } else {
        $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $stmtCheck->execute([$staffUsername, $staffEmail, $staffId]);
        if ($stmtCheck->fetch()) {
            setFlashMessage('staff_message', 'danger', 'Username or Email already exists for another user.');
            $_SESSION['staff_message_reopen_modal'] = ['modal_id' => 'editStaffModal', 'user_data' => $data];
        } else {
            $params = [];
            if (!empty($staffPassword)) {
                $hashedPassword = password_hash($staffPassword, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET full_name = ?, username = ?, password = ?, email = ?, role = ? WHERE id = ?";
                $params = [$staffFullName, $staffUsername, $hashedPassword, $staffEmail, $staffRole, $staffId];
            } else {
                $sql = "UPDATE users SET full_name = ?, username = ?, email = ?, role = ? WHERE id = ?";
                $params = [$staffFullName, $staffUsername, $staffEmail, $staffRole, $staffId];
            }
            $stmt = $pdo->prepare($sql);
            try {
                $stmt->execute($params);
                setFlashMessage('staff_message', 'success', 'Staff member updated successfully!');
            } catch (PDOException $e) {
                setFlashMessage('staff_message', 'danger', 'Database error updating staff: ' . htmlspecialchars($e->getMessage()));
                $_SESSION['staff_message_reopen_modal'] = ['modal_id' => 'editStaffModal', 'user_data' => $data];
            }
        }
    }
}

function handleDeleteStaff(PDO $pdo, array $data)
{
    $staffIdToDelete = $data['delete_staff_id'];

    if ($staffIdToDelete == $_SESSION['user_id']) {
        setFlashMessage('staff_message', 'danger', 'You cannot delete your own account.');
    } else {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        try {
            $stmt->execute([$staffIdToDelete]);
            setFlashMessage('staff_message', 'success', 'Staff member deleted successfully!');
        } catch (PDOException $e) {
            setFlashMessage('staff_message', 'danger', 'Database error deleting staff: ' . htmlspecialchars($e->getMessage()));
        }
    }
}

// --- PRODUCT & TRANSACTION ACTION HANDLERS ---
function handleDeleteProduct(PDO $pdo, array $data)
{
    $productId = (int) $data['delete_product'];
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM products WHERE id = ?");
    $stmtCheck->execute([$productId]);
    if ($stmtCheck->fetchColumn()) {
        $stmtDel = $pdo->prepare("DELETE FROM products WHERE id = ?");
        if ($stmtDel->execute([$productId]) && $stmtDel->rowCount() > 0) {
            setFlashMessage('delete_message', 'success', 'Product deleted successfully!');
        } else {
            setFlashMessage('delete_message', 'danger', 'Failed to delete product or product already removed.');
        }
    } else {
        setFlashMessage('delete_message', 'danger', 'Product not found.');
    }
}

function handleAddProduct(PDO $pdo, array $postData, array $filesData)
{
    $productName = trim($postData['product_name']);
    $smallPrice = $postData['small_price'] !== '' ? $postData['small_price'] : null;
    $mediumPrice = $postData['medium_price'] !== '' ? $postData['medium_price'] : null;
    $largePrice = $postData['large_price'] !== '' ? $postData['large_price'] : null;
    $b1t1Price = $postData['b1t1_price'] !== '' ? $postData['b1t1_price'] : null;
    $bundleSmallPrice = $postData['bundle_small_price'] !== '' ? $postData['bundle_small_price'] : null;
    $bundleMediumPrice = $postData['bundle_medium_price'] !== '' ? $postData['bundle_medium_price'] : null;
    $bundleLargePrice = $postData['bundle_large_price'] !== '' ? $postData['bundle_large_price'] : null;
    $addOnNames = $postData['add_on_name'] ?? [];
    $addOnPrices = $postData['add_on_price'] ?? [];
    $imagePath = null;

    if (empty($productName)) {
        setFlashMessage('product_message', 'danger', 'Product name is required.');
        return;
    }

    // --- Modified Flavor Logic ---
    $selectedFlavorInput = $postData['flavor']; // This will be an ID (string from form) or 'add_new'
    $typedNewFlavorName = trim($postData['new_flavor'] ?? '');
    $finalFlavorNameForProductTable = ''; // This will store the actual flavor NAME for the products table

    if ($selectedFlavorInput === 'add_new') {
        if (empty($typedNewFlavorName)) {
            setFlashMessage('product_message', 'danger', 'New flavor name cannot be empty when "Add New Flavor" is selected.');
            // Consider session variable to reopen product form if it were in a modal
            return; // Stop processing this request
        }
        $finalFlavorNameForProductTable = $typedNewFlavorName;

        // Check if this new flavor name already exists in 'flavors' table
        $stmtFlavorCheck = $pdo->prepare("SELECT id FROM flavors WHERE name = ?");
        $stmtFlavorCheck->execute([$finalFlavorNameForProductTable]);
        if (!$stmtFlavorCheck->fetch()) {
            // It's a brand new flavor, add it to 'flavors' table
            $stmtAddFlavor = $pdo->prepare("INSERT INTO flavors (name) VALUES (?)");
            try {
                $stmtAddFlavor->execute([$finalFlavorNameForProductTable]);
                // Optional: setFlashMessage('info_message', 'info', 'New flavor "' . htmlspecialchars($finalFlavorNameForProductTable) . '" added to list.');
            } catch (PDOException $e) {
                setFlashMessage('product_message', 'danger', 'Database error adding new flavor: ' . htmlspecialchars($e->getMessage()));
                return; // Stop if we can't add the flavor
            }
        }
    } else { // An existing flavor ID was selected from the dropdown
        $selectedFlavorId = filter_var($selectedFlavorInput, FILTER_VALIDATE_INT); // Validate and sanitize ID
        if ($selectedFlavorId === false || $selectedFlavorId <= 0) {
            setFlashMessage('product_message', 'danger', 'Invalid flavor selected. Please choose a valid option.');
            return; // Stop processing
        }

        // Fetch the name of the flavor using the selected ID
        $stmtGetFlavorName = $pdo->prepare("SELECT name FROM flavors WHERE id = ?");
        $stmtGetFlavorName->execute([$selectedFlavorId]);
        $flavorData = $stmtGetFlavorName->fetch(PDO::FETCH_ASSOC);

        if ($flavorData && isset($flavorData['name'])) {
            $finalFlavorNameForProductTable = $flavorData['name'];
        } else {
            // This case means the ID sent from the form doesn't exist in the flavors table
            setFlashMessage('product_message', 'danger', 'Selected flavor could not be found. It might have been removed or an error occurred.');
            return; // Stop processing
        }
    }
    // --- End of Modified Flavor Logic ---

    // Image upload logic
    if (isset($filesData['product_image']) && $filesData['product_image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $fileName = $filesData['product_image']['name'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (in_array($fileExt, $allowed)) {
            $newFileName = uniqid('prod_', true) . '.' . $fileExt;
            $uploadDir = '../uploads/';
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                    setFlashMessage('product_message', 'danger', 'Failed to create upload directory.');
                    return;
                }
            }
            if (move_uploaded_file($filesData['product_image']['tmp_name'], $uploadDir . $newFileName)) {
                $imagePath = 'uploads/' . $newFileName;
            } else {
                setFlashMessage('product_message', 'danger', 'Failed to upload image. Check permissions or path.');
            }
        } else {
            setFlashMessage('product_message', 'danger', 'Invalid image file type. Allowed: jpg, jpeg, png, gif.');
        }
    }

    $sizes = [
        ['name' => 'Small', 'price' => $smallPrice],
        ['name' => 'Medium', 'price' => $mediumPrice],
        ['name' => 'Large', 'price' => $largePrice]
    ];

    // Add B1 T1 if price is provided
    if ($b1t1Price !== null) {
        $sizes[] = ['name' => 'B1 T1', 'price' => $b1t1Price];
    }

    // Add Bundle sizes if any price is provided
    if ($bundleSmallPrice !== null || $bundleMediumPrice !== null || $bundleLargePrice !== null) {
        if ($bundleSmallPrice !== null) {
            $sizes[] = ['name' => 'Bundle Small', 'price' => $bundleSmallPrice];
        }
        if ($bundleMediumPrice !== null) {
            $sizes[] = ['name' => 'Bundle Medium', 'price' => $bundleMediumPrice];
        }
        if ($bundleLargePrice !== null) {
            $sizes[] = ['name' => 'Bundle Large', 'price' => $bundleLargePrice];
        }
    }

    $sizes = json_encode($sizes);
    $addOnsData = [];
    for ($i = 0; $i < count($addOnNames); $i++) {
        if (!empty(trim($addOnNames[$i])) && $addOnPrices[$i] !== '') {
            $addOnsData[] = ['name' => trim($addOnNames[$i]), 'price' => $addOnPrices[$i]];
        }
    }
    $addOns = json_encode($addOnsData);

    $stmt = $pdo->prepare("INSERT INTO products (name, flavors, sizes, add_ons, image) VALUES (?, ?, ?, ?, ?)");
    try {
        // Use $finalFlavorNameForProductTable which now holds the correct flavor NAME
        $stmt->execute([$productName, $finalFlavorNameForProductTable, $sizes, $addOns, $imagePath]);
        setFlashMessage('product_message', 'success', 'Product added successfully!');
    } catch (PDOException $e) {
        setFlashMessage('product_message', 'danger', 'DB Error adding product: ' . htmlspecialchars($e->getMessage()));
    }
}

function handleEditProduct(PDO $pdo, array $postData, array $filesData)
{
    $productId = $postData['edit_product_id'];
    $productName = trim($postData['edit_product_name']);
    $selectedFlavorInput = $postData['edit_flavor'];
    $typedNewFlavorName = trim($postData['edit_new_flavor'] ?? '');
    $smallPrice = $postData['edit_small_price'] !== '' ? $postData['edit_small_price'] : null;
    $mediumPrice = $postData['edit_medium_price'] !== '' ? $postData['edit_medium_price'] : null;
    $largePrice = $postData['edit_large_price'] !== '' ? $postData['edit_large_price'] : null;
    $b1t1Price = $postData['edit_b1t1_price'] !== '' ? $postData['edit_b1t1_price'] : null;
    $bundleSmallPrice = $postData['edit_bundle_small_price'] !== '' ? $postData['edit_bundle_small_price'] : null;
    $bundleMediumPrice = $postData['edit_bundle_medium_price'] !== '' ? $postData['edit_bundle_medium_price'] : null;
    $bundleLargePrice = $postData['edit_bundle_large_price'] !== '' ? $postData['edit_bundle_large_price'] : null;
    $imagePath = null;

    if (empty($productName) || empty($productId)) {
        setFlashMessage('product_message', 'danger', 'Product name and ID are required for editing.');
        return;
    }

    // Flavor handling
    $finalFlavorNameForProductTable = '';
    if ($selectedFlavorInput === 'add_new') {
        if (empty($typedNewFlavorName)) {
            setFlashMessage('product_message', 'danger', 'New flavor name cannot be empty when "Add New Flavor" is selected.');
            return;
        }
        $finalFlavorNameForProductTable = $typedNewFlavorName;
        $stmtFlavorCheck = $pdo->prepare("SELECT id FROM flavors WHERE name = ?");
        $stmtFlavorCheck->execute([$finalFlavorNameForProductTable]);
        if (!$stmtFlavorCheck->fetch()) {
            $stmtAddFlavor = $pdo->prepare("INSERT INTO flavors (name) VALUES (?)");
            $stmtAddFlavor->execute([$finalFlavorNameForProductTable]);
        }
    } else {
        $selectedFlavorId = filter_var($selectedFlavorInput, FILTER_VALIDATE_INT);
        if ($selectedFlavorId === false || $selectedFlavorId <= 0) {
            setFlashMessage('product_message', 'danger', 'Invalid flavor selected.');
            return;
        }
        $stmtGetFlavorName = $pdo->prepare("SELECT name FROM flavors WHERE id = ?");
        $stmtGetFlavorName->execute([$selectedFlavorId]);
        $flavorData = $stmtGetFlavorName->fetch(PDO::FETCH_ASSOC);
        $finalFlavorNameForProductTable = $flavorData['name'] ?? '';
    }

    // Image handling
    $stmt = $pdo->prepare("SELECT image FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $currentImage = $stmt->fetchColumn();

    if (isset($filesData['edit_product_image']) && $filesData['edit_product_image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $fileName = $filesData['edit_product_image']['name'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (in_array($fileExt, $allowed)) {
            $newFileName = uniqid('prod_', true) . '.' . $fileExt;
            $uploadDir = '../uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            if (move_uploaded_file($filesData['edit_product_image']['tmp_name'], $uploadDir . $newFileName)) {
                // Delete old image if exists
                if ($currentImage && file_exists('../' . $currentImage)) {
                    unlink('../' . $currentImage);
                }
                $imagePath = 'uploads/' . $newFileName;
            } else {
                setFlashMessage('product_message', 'danger', 'Failed to upload new image. Check permissions or path.');
                return;
            }
        } else {
            setFlashMessage('product_message', 'danger', 'Invalid image file type. Allowed: jpg, jpeg, png, gif.');
            return;
        }
    } else {
        // Keep existing image if no new image is uploaded
        $imagePath = $currentImage;
    }

    $sizes = [
        ['name' => 'Small', 'price' => $smallPrice],
        ['name' => 'Medium', 'price' => $mediumPrice],
        ['name' => 'Large', 'price' => $largePrice]
    ];
    if ($b1t1Price !== null)
        $sizes[] = ['name' => 'B1 T1', 'price' => $b1t1Price];
    if ($bundleSmallPrice !== null || $bundleMediumPrice !== null || $bundleLargePrice !== null) {
        if ($bundleSmallPrice !== null)
            $sizes[] = ['name' => 'Bundle Small', 'price' => $bundleSmallPrice];
        if ($bundleMediumPrice !== null)
            $sizes[] = ['name' => 'Bundle Medium', 'price' => $bundleMediumPrice];
        if ($bundleLargePrice !== null)
            $sizes[] = ['name' => 'Bundle Large', 'price' => $bundleLargePrice];
    }
    $sizes = json_encode($sizes);

    // Process add-ons
    $addOnNames = $postData['edit_add_on_name'] ?? [];
    $addOnPrices = $postData['edit_add_on_price'] ?? [];
    $addOnsData = [];
    for ($i = 0; $i < count($addOnNames); $i++) {
        if (!empty(trim($addOnNames[$i])) && $addOnPrices[$i] !== '') {
            $addOnsData[] = ['name' => trim($addOnNames[$i]), 'price' => $addOnPrices[$i]];
        }
    }
    $addOns = json_encode($addOnsData);

    $stmt = $pdo->prepare("UPDATE products SET name = ?, flavors = ?, sizes = ?, add_ons = ?, image = ? WHERE id = ?");
    $params = [$productName, $finalFlavorNameForProductTable, $sizes, $addOns, $imagePath ?: null, $productId];
    try {
        $stmt->execute($params);
        setFlashMessage('product_message', 'success', 'Product updated successfully!');
    } catch (PDOException $e) {
        setFlashMessage('product_message', 'danger', 'Database error updating product: ' . htmlspecialchars($e->getMessage()));
    }
}

// Update POST handling
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_staff_action'])) {
        handleAddStaff($pdo, $_POST);
        redirectAndExit('dashboard.php#staff');
    } elseif (isset($_POST['edit_staff_action'])) {
        handleEditStaff($pdo, $_POST);
        redirectAndExit('dashboard.php#staff');
    } elseif (isset($_POST['delete_staff_id'])) {
        handleDeleteStaff($pdo, $_POST);
        redirectAndExit('dashboard.php#staff');
    } elseif (isset($_POST['delete_product'])) {
        handleDeleteProduct($pdo, $_POST);
        redirectAndExit('dashboard.php#product-list');
    } elseif (isset($_POST['product_name'])) { // Assuming product_name indicates add product form
        handleAddProduct($pdo, $_POST, $_FILES);
        redirectAndExit('dashboard.php#product-list');
    } elseif (isset($_POST['edit_product_id'])) {
        handleEditProduct($pdo, $_POST, $_FILES);
        redirectAndExit('dashboard.php#product-list');
    } else if (isset($_POST['clear_summary'])) {
        handleClearSummary($pdo);
        redirectAndExit('dashboard.php#summary');
    }
}


function handleClearSummary(PDO $pdo)
{
    $stmt = $pdo->prepare("TRUNCATE TABLE orders");
    $stmt->execute();
    setFlashMessage('summary_message', 'success', 'Summary data cleared successfully!');
}

// --- DATA FETCHING FUNCTIONS ---
function getOverallStats(PDO $pdo)
{
    $today = date('Y-m-d');
    $stmtToday = $pdo->prepare("SELECT COUNT(*) as orders, SUM(total_amount) as sales FROM orders WHERE DATE(order_date) = ?");
    $stmtToday->execute([$today]);
    $todayStats = $stmtToday->fetch(PDO::FETCH_ASSOC);

    $thisMonth = date('Y-m');
    $stmtMonth = $pdo->prepare("SELECT COUNT(*) as orders, SUM(total_amount) as sales FROM orders WHERE DATE_FORMAT(order_date, '%Y-%m') = ?");
    $stmtMonth->execute([$thisMonth]);
    $monthStats = $stmtMonth->fetch(PDO::FETCH_ASSOC);

    return ['today' => $todayStats ?: ['orders' => 0, 'sales' => 0], 'month' => $monthStats ?: ['orders' => 0, 'sales' => 0]];
}

function getSalesChartData(PDO $pdo, string $selectedPeriod)
{
    $chartData = [];
    $chartTitle = '';

    switch ($selectedPeriod) {
        case 'daily':
            $stmt = $pdo->query("
                SELECT DATE(order_date) as period_key, COALESCE(SUM(total_amount), 0) as sales, COUNT(*) as orders
                FROM orders WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                GROUP BY DATE(order_date) ORDER BY period_key ASC
            ");
            $rawSales = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $salesByDate = array_column($rawSales, null, 'period_key');

            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-$i days"));
                $chartData[] = [
                    'period' => date('M j', strtotime($date)),
                    'sales' => $salesByDate[$date]['sales'] ?? 0,
                    'orders' => $salesByDate[$date]['orders'] ?? 0,
                ];
            }
            $chartTitle = 'Daily Sales (Last 7 Days)';
            break;

        case 'weekly':
            $stmt = $pdo->query("
                SELECT YEARWEEK(order_date, 1) as week_year_key, MIN(DATE(order_date)) as week_start, MAX(DATE(order_date)) as week_end,
                       COALESCE(SUM(total_amount), 0) as sales, COUNT(*) as orders
                FROM orders WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 8 WEEK) GROUP BY week_year_key ORDER BY week_year_key ASC
            ");
            $salesData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $salesByWeekYear = array_column($salesData, null, 'week_year_key');
            for ($i = 7; $i >= 0; $i--) {
                $currentWeekStart = date('Y-m-d', strtotime("monday this week -$i weeks"));
                $currentWeekEnd = date('Y-m-d', strtotime("sunday this week -$i weeks"));
                $weekYearKey = date('oW', strtotime($currentWeekStart));
                $chartData[] = [
                    'period' => date('M j', strtotime($currentWeekStart)) . ' - ' . date('M j', strtotime($currentWeekEnd)),
                    'sales' => $salesByWeekYear[$weekYearKey]['sales'] ?? 0,
                    'orders' => $salesByWeekYear[$weekYearKey]['orders'] ?? 0,
                ];
            }
            $chartTitle = 'Weekly Sales (Last 8 Weeks)';
            break;

        case 'monthly':
            $stmt = $pdo->query("
                SELECT DATE_FORMAT(order_date, '%Y-%m') as month_key, COALESCE(SUM(total_amount), 0) as sales, COUNT(*) as orders
                FROM orders WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY month_key ORDER BY month_key ASC
            ");
            $salesData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $salesByMonthKey = array_column($salesData, null, 'month_key');
            for ($i = 11; $i >= 0; $i--) {
                $monthDate = date('Y-m-01', strtotime("-$i months"));
                $monthKey = date('Y-m', strtotime($monthDate));
                $chartData[] = [
                    'period' => date('M Y', strtotime($monthDate)),
                    'sales' => $salesByMonthKey[$monthKey]['sales'] ?? 0,
                    'orders' => $salesByMonthKey[$monthKey]['orders'] ?? 0,
                ];
            }
            $chartTitle = 'Monthly Sales (Last 12 Months)';
            break;
    }
    $totalSales = array_sum(array_column($chartData, 'sales'));
    $totalOrders = array_sum(array_column($chartData, 'orders'));

    return [
        'data' => $chartData,
        'title' => $chartTitle,
        'totalSales' => $totalSales,
        'totalOrders' => $totalOrders,
        'selectedPeriod' => $selectedPeriod
    ];
}

function calculateBestSellers(PDO $pdo, int $limit = 10)
{
    error_log("Starting calculateBestSellers");
    try {
        $stmt = $pdo->query("SELECT order_items FROM orders WHERE order_items IS NOT NULL AND order_items != '[]'");
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Fetched " . count($orders) . " orders from database.");

        if (empty($orders)) {
            error_log("No orders found in the database.");
            return [];
        }

        $productAggregates = [];
        $processedOrders = 0;

        foreach ($orders as $order) {
            $orderItems = json_decode($order['order_items'], true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("JSON decode error for order_items: " . json_last_error_msg());
                continue;
            }

            if (!is_array($orderItems)) {
                error_log("Decoded order_items is not an array for an order.");
                continue;
            }

            $processedOrders++;

            foreach ($orderItems as $item) {
                // More robust product name extraction
                $productName = $item['product']['name'] ?? $item['name'] ?? $item['product_name'] ?? null;

                // More robust size extraction
                $size = $item['size']['name'] ?? $item['size'] ?? 'Regular';

                if ($productName === null) {
                    error_log("Skipping item due to missing product name: " . json_encode($item));
                    continue;
                }

                $key = $productName . ' - ' . $size;
                $quantity = isset($item['quantity']) ? (int) $item['quantity'] : 0;
                $revenue = isset($item['totalPrice']) ? (float) $item['totalPrice'] : (isset($item['price']) ? (float) $item['price'] * $quantity : 0.0);

                if ($quantity <= 0 || $revenue <= 0) {
                    error_log("Skipping item with zero or negative quantity/revenue: " . json_encode($item));
                    continue;
                }

                if (!isset($productAggregates[$key])) {
                    $productAggregates[$key] = [
                        'name' => $key,
                        'sales_count' => 0,
                        'revenue' => 0.0,
                    ];
                }

                $productAggregates[$key]['sales_count'] += $quantity;
                $productAggregates[$key]['revenue'] += $revenue;
            }
        }

        error_log("Processed " . $processedOrders . " orders. Aggregated " . count($productAggregates) . " products.");

        if (empty($productAggregates)) {
            error_log("No product aggregates were generated.");
            return [];
        }

        // Sort by revenue in descending order
        uasort($productAggregates, function ($a, $b) {
            return $b['revenue'] <=> $a['revenue'];
        });

        $result = array_values(array_slice($productAggregates, 0, $limit, true));

        // Format the numbers for display
        foreach ($result as &$item) {
            $item['revenue'] = round($item['revenue'], 2);
        }

        error_log("Returning " . count($result) . " best selling products.");
        error_log("Final result: " . json_encode($result));

        return $result;
    } catch (PDOException $e) {
        error_log("Error in calculateBestSellers: " . $e->getMessage());
        return [];
    }
}

function getRecentOrders(PDO $pdo, int $limit = 10, int $offset = 0)
{
    $stmt = $pdo->prepare("
        SELECT o.*, u.full_name as cashier_name, u.username as cashier_username 
        FROM orders o 
        LEFT JOIN users u ON o.user_id = u.id 
        ORDER BY o.id DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalStmt = $pdo->query("SELECT COUNT(*) FROM orders");
    $totalOrders = $totalStmt->fetchColumn();

    // Fetch user performance data
    $userPerformance = getUserPerformance($pdo);
    $userMap = [];
    foreach ($userPerformance as $user) {
        $userMap[$user['user_id']] = [
            'full_name' => $user['full_name'],
            'username' => $user['username']
        ];
    }

    foreach ($orders as &$order) {
        $order['cashier_name'] = $userMap[$order['user_id']]['full_name'] ?? 'Unknown Cashier';
        $order['cashier_username'] = $userMap[$order['user_id']]['username'] ?? 'unknown';
        $orderItems = json_decode($order['order_items'], true) ?? [];
        $itemNames = array_map(function ($item) {
            return $item['product_name'] ?? $item['name'] ?? 'Unknown Product';
        }, $orderItems);
        $order['description'] = implode(', ', $itemNames);
    }

    return [
        'orders' => $orders,
        'total' => $totalOrders
    ];
}

function getPaymentMethodsDistribution(PDO $pdo)
{
    $stmt = $pdo->query("SELECT payment_method, COUNT(*) as count FROM orders GROUP BY payment_method");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getUserPerformance(PDO $pdo)
{
    $stmt = $pdo->query("
        SELECT u.id as user_id, u.full_name, u.username, u.email, u.role,
               COUNT(o.id) as total_orders,
               COALESCE(SUM(o.total_amount), 0) as total_sales,
               COALESCE(AVG(o.total_amount), 0) as avg_order_value
        FROM users u
        LEFT JOIN orders o ON u.id = o.user_id 
        GROUP BY u.id, u.full_name, u.username, u.email, u.role
        ORDER BY u.role ASC, total_sales DESC
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAllProductsForListing(PDO $pdo)
{
    $stmt = $pdo->query("SELECT * FROM products ORDER BY id ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAllFlavorsForForm(PDO $pdo)
{
    // Fetches id and name, ordered by id
    $stmt = $pdo->query("SELECT id, name FROM flavors ORDER BY id ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getSummaryReport(PDO $pdo, $startDate = null, $endDate = null)
{
    if (!$startDate)
        $startDate = date('Y-m-d', strtotime('-30 days'));
    if (!$endDate)
        $endDate = date('Y-m-d');

    $stmt = $pdo->prepare("
        SELECT 
            o.id,
            o.customer_name,
            o.order_date,
            o.order_items,
            o.total_amount,
            o.payment_method,
            u.full_name as cashier_name,
            u.username as cashier_username
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        WHERE DATE(o.order_date) BETWEEN ? AND ?
        ORDER BY o.order_date DESC
    ");

    try {
        $stmt->execute([$startDate, $endDate]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $summaryData = [];
        $totalSales = 0;
        $totalQuantity = 0;

        foreach ($orders as $order) {
            $date = date('Y-m-d', strtotime($order['order_date']));
            $items = json_decode($order['order_items'], true) ?: [];

            foreach ($items as $item) {
                // Extract product name
                $productName = '';
                if (isset($item['product']) && is_array($item['product'])) {
                    $productName = $item['product']['name'];
                } else {
                    $productName = $item['name'] ?? $item['product_name'] ?? $item['product'] ?? 'Unknown';
                }

                // Extract size
                $size = '';
                if (isset($item['size']) && is_array($item['size'])) {
                    $size = $item['size']['name'];
                } else {
                    $size = $item['size'] ?? 'N/A';
                }

                // Extract quantity and price
                $quantity = intval($item['quantity'] ?? 1);
                $price = floatval($item['totalPrice'] ?? $item['price'] ?? 0);
                $unitPrice = $quantity > 0 ? $price / $quantity : 0;

                $key = $date . '_' . $productName . '_' . $size;

                if (!isset($summaryData[$key])) {
                    $summaryData[$key] = [
                        'date' => $date,
                        'product' => $productName,
                        'size' => $size,
                        'quantity' => 0,
                        'unit_price' => $unitPrice,
                        'total_sales' => 0,
                        'cashier' => $order['cashier_name'] ?? 'Unknown',
                        'payment_method' => strtoupper($order['payment_method'])
                    ];
                }

                $summaryData[$key]['quantity'] += $quantity;
                $summaryData[$key]['total_sales'] += $price;

                $totalSales += $price;
                $totalQuantity += $quantity;
            }
        }

        // Sort by date descending
        uasort($summaryData, function ($a, $b) {
            return strcmp($b['date'], $a['date']);
        });

        return [
            'items' => array_values($summaryData),
            'total_sales' => $totalSales,
            'total_quantity' => $totalQuantity,
            'success' => true
        ];
    } catch (PDOException $e) {
        error_log("Error in getSummaryReport: " . $e->getMessage());
        return [
            'items' => [],
            'total_sales' => 0,
            'total_quantity' => 0,
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// --- INITIALIZATION AND REQUEST HANDLING ---
initializeAdminSession();
require_once '../includes/db_connect.php'; // $pdo is now available

// Handle POST requests
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_staff_action'])) {
        handleAddStaff($pdo, $_POST);
        redirectAndExit('dashboard.php#staff');
    } elseif (isset($_POST['edit_staff_action'])) {
        handleEditStaff($pdo, $_POST);
        redirectAndExit('dashboard.php#staff');
    } elseif (isset($_POST['delete_staff_id'])) {
        handleDeleteStaff($pdo, $_POST);
        redirectAndExit('dashboard.php#staff');
    } elseif (isset($_POST['delete_product'])) {
        handleDeleteProduct($pdo, $_POST);
        redirectAndExit('dashboard.php#product-list');
    } elseif (isset($_POST['product_name'])) { // Assuming product_name indicates add product form
        handleAddProduct($pdo, $_POST, $_FILES);
        redirectAndExit('dashboard.php#product-list');
    } elseif (isset($_POST['edit_product_id'])) {
        handleEditProduct($pdo, $_POST, $_FILES);
        redirectAndExit('dashboard.php#product-list');
    } else if (isset($_POST['clear_summary'])) {
        handleClearSummary($pdo);
        redirectAndExit('dashboard.php#summary');
    }
}

// Update data fetching for page display
$limit = 10;
$page = isset($_GET['transactions_page']) ? max(1, (int) $_GET['transactions_page']) : 1;
$offset = ($page - 1) * $limit;
$recentOrdersData = getRecentOrders($pdo, $limit, $offset);
$recentOrders = $recentOrdersData['orders'];
$totalOrders = $recentOrdersData['total'];
$totalPages = ceil($totalOrders / $limit);

// --- FETCH DATA FOR PAGE DISPLAY ---
$overallStats = getOverallStats($pdo);
$selectedPeriod = $_GET['period'] ?? 'daily';
$chartInfo = getSalesChartData($pdo, $selectedPeriod);
$totalStmt = $pdo->query("SELECT COUNT(*) FROM orders");
$paymentMethods = getPaymentMethodsDistribution($pdo);
$userPerformance = getUserPerformance($pdo);
$productList = getAllProductsForListing($pdo);
$flavorList = getAllFlavorsForForm($pdo); // This now returns id and name

// Ensure $flavorList is available for JavaScript
$jsFlavorList = json_encode($flavorList);

// Add this near where other data is fetched for page display
$summaryStartDate = $_GET['summary_start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$summaryEndDate = $_GET['summary_end_date'] ?? date('Y-m-d');
$summaryReport = getSummaryReport($pdo, $summaryStartDate, $summaryEndDate);

// Add this near the other PHP data fetching code (before the HTML)
$bestSellers = calculateBestSellers($pdo);

// Handle dark mode state from URL
$isDarkMode = isset($_GET['darkMode']) && $_GET['darkMode'] === '1';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" href="../images/Logo.png" type="image/png">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>

    <style>
        :root {
            --primary-color: #004d00;
            --secondary-color: #006400;
            --success-color: #198754;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #0dcaf0;
            --light-bg: #f8f9fa;
            --dark-bg: #121212;
            --dark-card: #1e1e1e;
            --dark-border: #333333;
        }

        /* Light Mode (Default) */
        body.light-mode {
            background-color: var(--light-bg);
            color: #212529;
        }

        body.light-mode .navbar {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        }

        body.light-mode .text-white {
            color: #ffffff !important;
        }

        body.light-mode .btn-outline-light {
            color: #ffffff;
            border-color: #ffffff;
        }

        body.light-mode .btn-outline-light:hover {
            background-color: #ffffff;
            color: #006400;
        }

        .navbar {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        }

        .card {
            border: none;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .stat-card {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .nav-pills .nav-link {
            color: #495057;
            transition: all 0.3s ease;
        }

        .nav-pills .nav-link:hover {
            background-color: #e9ecef;
        }

        .nav-pills .nav-link.active {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
            transition: all 0.3s ease;
            color: #ffffff;
            font-weight: 500;
            padding: 0.5rem 1.25rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--primary-color) 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .btn-primary:active {
            transform: translateY(0);
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        .period-selector {
            background: white;
            border-radius: 10px;
            padding: 0.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .period-btn {
            border: none;
            background: transparent;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            color: #6b7280;
            font-weight: 500;
        }

        .period-btn:hover {
            background: #f3f4f6;
            color: var(--primary-color);
        }

        .period-btn.active {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
        }

        /* Dark Mode Styles */
        body.dark-mode {
            background-color: var(--dark-bg);
            color: #ffffff;
        }

        body.dark-mode .navbar {
            background: linear-gradient(135deg, #002600 0%, #004000 100%);
        }

        body.dark-mode .stat-card {
            background: linear-gradient(135deg, #002600 0%, #004000 100%);
            border: 1px solid var(--dark-border);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
        }

        body.dark-mode .stat-card h3,
        body.dark-mode .stat-card p,
        body.dark-mode .stat-card .text-muted {
            color: #ffffff !important;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.2);
        }

        body.dark-mode .stat-card:hover {
            background: linear-gradient(135deg, #002800 0%, #003300 100%);
            border-color: #004400;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.5);
        }

        body.dark-mode .card {
            background-color: var(--dark-card);
            border-color: var(--dark-border);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        body.dark-mode .card:hover {
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4);
        }

        body.dark-mode .table {
            color: #e0e0e0 !important;
            background-color: #1e1e1e;
        }

        body.dark-mode .table td,
        body.dark-mode .table th {
            color: #e0e0e0 !important;
            border-color: #333333;
            background-color: transparent;
        }

        body.dark-mode .table-striped>tbody>tr:nth-of-type(odd) {
            background-color: rgba(255, 255, 255, 0.03);
        }

        body.dark-mode .table-striped>tbody>tr:nth-of-type(even) {
            background-color: transparent;
        }

        body.dark-mode .table-hover tbody tr:hover {
            background-color: rgba(0, 204, 0, 0.1) !important;
            color: #ffffff;
            transition: all 0.3s ease;
        }

        body.dark-mode .table-hover tbody tr:hover td {
            color: #ffffff !important;
        }

        body.dark-mode .table-success {
            background-color: rgba(0, 204, 0, 0.15) !important;
        }

        body.dark-mode .table-success td {
            color: #ffffff !important;
        }

        body.dark-mode .badge.bg-success {
            background-color: #00cc00 !important;
        }

        body.dark-mode .badge.bg-primary {
            background-color: #3399ff !important;
        }

        body.dark-mode .period-selector {
            background-color: #1e1e1e;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }

        body.dark-mode .period-btn {
            color: #e0e0e0;
            background-color: transparent;
        }

        body.dark-mode .period-btn:hover {
            background-color: #002800;
            color: #ffffff;
        }

        body.dark-mode .period-btn.active {
            background: linear-gradient(135deg, #001a00 0%, #003300 100%);
            color: #ffffff;
        }

        body.dark-mode .modal-content {
            background-color: #1e1e1e;
            border-color: #333333;
        }

        body.dark-mode .modal-header,
        body.dark-mode .modal-footer {
            border-color: #333333;
            background-color: #252525;
        }

        body.dark-mode .form-control,
        body.dark-mode .form-select {
            background-color: rgba(255, 255, 255, 0.1);
            border-color: var(--dark-border);
            color: #ffffff;
        }

        body.dark-mode .form-control:focus,
        body.dark-mode .form-select:focus {
            background-color: rgba(255, 255, 255, 0.15);
            border-color: var(--secondary-color);
            color: #ffffff;
            box-shadow: 0 0 0 0.2rem rgba(0, 100, 0, 0.25);
        }

        body.dark-mode .badge.bg-success {
            background-color: #004400 !important;
        }

        body.dark-mode .badge.bg-warning {
            background-color: #856404 !important;
            color: #ffffff !important;
        }

        body.dark-mode .badge.bg-info {
            background-color: #0dcaf0 !important;
            color: #000000 !important;
        }

        body.dark-mode .text-muted,
        body.dark-mode .form-text,
        body.dark-mode small,
        body.dark-mode .text-dark {
            color: #ffffff !important;
        }

        body.dark-mode h1,
        body.dark-mode h2,
        body.dark-mode h3,
        body.dark-mode h4,
        body.dark-mode h5,
        body.dark-mode h6,
        body.dark-mode .h1,
        body.dark-mode .h2,
        body.dark-mode .h3,
        body.dark-mode .h4,
        body.dark-mode .h5,
        body.dark-mode .h6 {
            color: #ffffff;
        }

        body.dark-mode .card-title,
        body.dark-mode .modal-title {
            color: #ffffff;
        }

        body.dark-mode .table {
            color: #ffffff !important;
            background-color: var(--dark-card);
        }

        body.dark-mode .table td,
        body.dark-mode .table th {
            color: #ffffff !important;
            border-color: var(--dark-border);
            background-color: transparent;
        }

        body.dark-mode .table-striped>tbody>tr:nth-of-type(odd) {
            background-color: rgba(255, 255, 255, 0.05);
        }

        body.dark-mode .table-hover tbody tr:hover {
            background-color: rgba(0, 77, 0, 0.3);
            color: #ffffff;
            transition: background-color 0.2s ease;
        }

        body.dark-mode .dropdown-menu {
            background-color: #2d2d2d;
            border-color: #404040;
        }

        body.dark-mode .dropdown-item {
            color: #ffffff;
        }

        body.dark-mode .dropdown-item:hover {
            background-color: #404040;
            color: #ffffff;
        }

        body.dark-mode .form-label {
            color: #ffffff;
        }

        body.dark-mode .form-control::placeholder {
            color: #999999;
        }

        body.dark-mode .chart-container {
            background-color: #2d2d2d;
        }

        body.dark-mode .sales-summary h4,
        body.dark-mode .sales-summary small {
            color: #ffffff;
        }

        /* Chart text colors for dark mode */
        body.dark-mode .chartjs-render-monitor text {
            fill: #ffffff !important;
        }

        body.dark-mode .period-btn {
            color: #ffffff;
        }

        body.dark-mode .period-btn:hover {
            background-color: #404040;
            color: #ffffff;
        }

        body.dark-mode .period-btn.active {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: #ffffff;
        }

        /* Make sure links are visible in dark mode */
        body.dark-mode a:not(.btn) {
            color: #4da6ff;
        }

        body.dark-mode a:not(.btn):hover {
            color: #80bfff;
        }

        /* Ensure modal close button is visible */
        body.dark-mode .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
        }

        /* Update the stat cards text color */
        body.dark-mode .stat-card {
            color: #ffffff;
        }

        /* Ensure badge text is visible */
        body.dark-mode .badge {
            color: #ffffff;
        }

        body.dark-mode .badge.bg-light {
            background-color: #404040 !important;
            color: #e0e0e0 !important;
        }

        body.dark-mode .badge.bg-secondary {
            background-color: #6c757d !important;
            color: #ffffff !important;
        }

        body.dark-mode .text-dark {
            color: #e0e0e0 !important;
        }

        body.dark-mode .text-muted {
            color: #a0a0a0 !important;
        }

        /* Make form validation messages visible */
        body.dark-mode .invalid-feedback,
        body.dark-mode .valid-feedback {
            color: #ffffff;
        }

        body.dark-mode .navbar {
            background: linear-gradient(135deg, #002300 0%, #004d00 100%);
        }

        body.dark-mode .card {
            background-color: #2d2d2d;
            border-color: #404040;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }

        body.dark-mode .stat-card {
            background: linear-gradient(135deg, #003300 0%, #006600 100%);
        }

        body.dark-mode .card-header {
            background-color: #333333;
            border-bottom-color: #404040;
            color: #e0e0e0;
        }

        body.dark-mode .table-striped>tbody>tr {
            color: #e0e0e0;
        }

        body.dark-mode .nav-pills .nav-link {
            color: #e0e0e0;
        }

        body.dark-mode .nav-pills .nav-link:hover {
            background-color: #404040;
        }

        body.dark-mode .nav-pills .nav-link.active {
            background-color: var(--primary-color);
        }

        body.dark-mode .period-selector {
            background-color: #2d2d2d;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }

        body.dark-mode .btn-outline-secondary {
            border-color: #404040;
            color: #e0e0e0;
        }

        body.dark-mode .btn-outline-secondary:hover {
            background-color: #404040;
        }

        body.dark-mode .text-muted {
            color: #ffffff;
        }

        /* Alert Colors */
        .alert-success {
            background-color: rgba(25, 135, 84, 0.1);
            border-color: var(--success-color);
            color: var(--success-color);
        }

        .alert-warning {
            background-color: rgba(255, 193, 7, 0.1);
            border-color: var(--warning-color);
            color: #856404;
        }

        .alert-danger {
            background-color: rgba(220, 53, 69, 0.1);
            border-color: var(--danger-color);
            color: var(--danger-color);
        }

        body.dark-mode .alert-success {
            background-color: rgba(25, 135, 84, 0.2);
            color: #98ff98;
        }

        body.dark-mode .alert-warning {
            background-color: rgba(255, 193, 7, 0.2);
            color: #ffe169;
        }

        body.dark-mode .alert-danger {
            background-color: rgba(220, 53, 69, 0.2);
            color: #ff8b8b;
        }

        /* Badge Colors */
        .badge.bg-success {
            background-color: var(--success-color) !important;
        }

        .badge.bg-warning {
            background-color: var(--warning-color) !important;
            color: #000;
        }

        .badge.bg-danger {
            background-color: var(--danger-color) !important;
        }

        .badge.bg-info {
            background-color: var(--info-color) !important;
            color: #000;
        }

        /* Dark mode switch styles */
        .dark-mode-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 30px;
            margin: 0 10px;
        }

        .dark-mode-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .dark-mode-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 30px;
        }

        .dark-mode-slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked+.dark-mode-slider {
            background-color: #404040;
        }

        input:checked+.dark-mode-slider:before {
            transform: translateX(30px);
        }

        .mode-icon {
            font-size: 1.2rem;
            color: #ffffff;
        }

        /* Hover effects */
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .card:hover {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        /* Transitions */
        .card,
        .btn,
        .form-control,
        .nav-link,
        .period-btn {
            transition: all 0.3s ease;
        }

        /* Dark Mode Modal Styles */
        body.dark-mode .modal-content {
            background-color: #2d2d2d;
            border-color: #404040;
        }

        body.dark-mode .modal-header,
        body.dark-mode .modal-footer {
            border-color: #404040;
        }

        body.dark-mode .modal-header .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
        }

        body.dark-mode .form-control,
        body.dark-mode .form-select {
            background-color: #333333;
            border-color: #404040;
            color: #e0e0e0;
        }

        body.dark-mode .form-control:focus,
        body.dark-mode .form-select:focus {
            background-color: #404040;
            border-color: #505050;
            color: #ffffff;
        }

        body.dark-mode .form-control::placeholder {
            color: #888888;
        }

        body.dark-mode .form-label {
            color: #e0e0e0;
        }

        body.dark-mode .form-text {
            color: #a0a0a0 !important;
        }

        body.dark-mode .modal .btn-secondary {
            background-color: #505050;
            border-color: #606060;
            color: #e0e0e0;
        }

        body.dark-mode .modal .btn-secondary:hover {
            background-color: #606060;
            border-color: #707070;
            color: #ffffff;
        }

        body.dark-mode .input-group-text {
            background-color: #404040;
            border-color: #505050;
            color: #e0e0e0;
        }

        body.dark-mode .form-select option {
            background-color: #333333;
            color: #e0e0e0;
        }

        /* Required field asterisk */
        body.dark-mode .text-danger {
            color: #ff8080 !important;
        }

        /* Add custom CSS for square modal */
        #editProductModal .modal-dialog {
            width: 1200px;
            /* Set fixed width */
            max-width: none;
            /* Override max-width */
            height: 900px;
            /* Set equal height for square appearance */
        }

        #editProductModal .modal-content {
            height: 100%;
            /* Ensure content fills the square dialog */
            overflow-y: auto;
            /* Allow scrolling if content overflows */
        }

        body.dark-mode .table-success {
            background-color: rgb(0, 77, 10) !important;
            color: #ffffff !important;
        }

        body.dark-mode .table-success.fw-bold {
            background-color: #006600 !important;
            color: #ffffff !important;
        }

        body.dark-mode .table-success:hover {
            background-color: #008000 !important;
            color: #ffffff !important;
        }

        /* Add hover effect for Total Sales row */
        body.dark-mode .table-success.fw-bold:hover {
            background-color: #004d00 !important;
        }

        .table-success.fw-bold:hover {
            background-color: #198754 !important;
            color: #ffffff !important;
        }

        body.dark-mode .payment-chart {
            color: #ffffff !important;
        }


        /* Chart text colors for dark mode */
        body.dark-mode .chart-container {
            background-color: #1e1e1e !important;
        }

        body.dark-mode .sales-summary h4,
        body.dark-mode .sales-summary small,
        body.dark-mode .card-header h5 {
            color: #ffffff !important;
        }

        body.dark-mode .text-muted {
            color: #cccccc !important;
        }

        body.dark-mode .text-primary {
            color: #4da6ff !important;
        }

        body.dark-mode .text-success {
            color: #4dff4d !important;
        }

        body.dark-mode .text-warning {
            color: #ffcc00 !important;
        }

        /* Ensure chart text is visible in dark mode */
        body.dark-mode .chart-container {
            background-color: #1e1e1e;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }

        /* Chart text colors for dark mode */
        body.dark-mode .chartjs-render-monitor text,
        body.dark-mode .chart-container text {
            fill: #ffffff !important;
            color: #ffffff !important;
        }

        /* Chart axis and grid lines */
        body.dark-mode .chartjs-render-monitor .chart-grid-line {
            stroke: rgba(255, 255, 255, 0.1) !important;
        }

        /* Chart legends */
        body.dark-mode .chartjs-render-monitor .chart-legend text {
            fill: #ffffff !important;
        }

        /* Statistics text in dark mode */
        body.dark-mode .stat-card {
            background: linear-gradient(135deg, #001a00 0%, #002800 100%);
            border: 1px solid #003300;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.4);
            color: #ffffff !important;
        }

        body.dark-mode .stat-card h3,
        body.dark-mode .stat-card p,
        body.dark-mode .stat-card .text-muted {
            color: #ffffff !important;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.2);
        }

        /* Payment methods chart text */
        body.dark-mode #paymentMethodsChart text {
            fill: #ffffff !important;
            font-weight: 500;
        }

        /* Ensure all text elements are visible */
        body.dark-mode .text-muted,
        body.dark-mode .form-text,
        body.dark-mode small,
        body.dark-mode .text-dark,
        body.dark-mode .card-text,
        body.dark-mode .stats-text {
            color: rgba(255, 255, 255, 0.9) !important;
        }

        /* Headers and titles */
        body.dark-mode h1,
        body.dark-mode h2,
        body.dark-mode h3,
        body.dark-mode h4,
        body.dark-mode h5,
        body.dark-mode h6,
        body.dark-mode .card-title,
        body.dark-mode .modal-title {
            color: #ffffff !important;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.2);
        }

        /* Table text in dark mode */
        body.dark-mode .table {
            color: #e0e0e0 !important;
            background-color: #1e1e1e;
        }

        body.dark-mode .table td,
        body.dark-mode .table th {
            color: #e0e0e0 !important;
            border-color: #333333;
            background-color: transparent;
        }

        /* Light mode text adjustments */
        .stat-card,
        .card-title,
        .table,
        .chart-container text,
        .chartjs-render-monitor text {
            color: #000000 !important;
        }

        .text-muted {
            color: #666666 !important;
        }

        /* Chart text in light mode */
        .chartjs-render-monitor text,
        .chart-container text {
            fill: #000000 !important;
        }

        /* Ensure contrast for both modes */
        .stats-value {
            font-weight: 600;
        }

        /* Chart.js specific dark mode styles */
        body.dark-mode canvas {
            background-color: #1e1e1e !important;
        }

        /* Ensure chart container has proper background in dark mode */
        body.dark-mode .chart-container {
            background-color: #1e1e1e;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #333;
        }

        /* Override Chart.js text colors for dark mode */
        body.dark-mode .chartjs-render-monitor {
            color: #ffffff !important;
        }

        /* Best Sellers Dark Mode Styles */
        body.dark-mode #best-sellers .card {
            background-color: #1e1e1e;
            border-color: #333333;
        }

        body.dark-mode #best-sellers .card-header {
            background-color: #252525;
            border-bottom-color: #333333;
        }

        body.dark-mode #best-sellers .table {
            color: #e0e0e0;
        }

        body.dark-mode #best-sellers .table thead th {
            background-color: #252525;
            color: #ffffff;
            border-bottom-color: #333333;
        }

        body.dark-mode #best-sellers .table tbody td {
            color: #e0e0e0;
            border-color: #333333;
        }

        body.dark-mode #best-sellers .table-hover tbody tr:hover {
            background-color: #383838;
            color: #ffffff;
        }

        body.dark-mode #best-sellers .chart-container {
            background-color: #1e1e1e;
            border: 1px solid #333333;
        }

        body.dark-mode #bestSellersTable td,
        body.dark-mode #bestSellersTable tr {
            color: #ffffff !important;
        }

        /* Chart Colors */
        :root {
            --chart-green-light: rgba(0, 77, 0, 0.8);
            --chart-green-dark: rgba(0, 179, 0, 0.8);
            --chart-blue-light: rgba(59, 130, 246, 0.8);
            --chart-blue-dark: rgba(102, 179, 255, 0.8);
        }
    </style>
</head>

<body class="light-mode">
    <nav class="navbar navbar-dark sticky-top">
        <div class="container">
            <span class="navbar-brand"><img src="../images/Logo.png" width="30" height="30" class="me-2">Admin
                Dashboard</span>
            <div class="d-flex align-items-center">
                <span class="text-white me-3">Welcome, <?php echo $_SESSION['username']; ?></span>
                <button onclick="showLogoutConfirmation()" class="btn btn-outline-light">
                    <img src="../images/Logout.png" width="20" height="20" class="me-1">Logout
                </button>
            </div>
        </div>
    </nav>

    <!-- Logout Confirmation Modal -->
    <div class="modal fade" id="logoutConfirmModal" tabindex="-1" aria-labelledby="logoutConfirmModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="logoutConfirmModalLabel">Confirm Logout</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to logout from the admin dashboard?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="../logout.php" class="btn btn-danger">Logout</a>
                </div>
            </div>
        </div>
    </div>

    <div class="container mt-4">
        <?php
        $message_keys = ['delete_message', 'product_message', 'staff_message', 'transaction_message', 'info_message'];
        foreach ($message_keys as $msg_key) {
            if (isset($_SESSION[$msg_key])) {
                $message_data = $_SESSION[$msg_key];
                $alertType = 'info';
                $text = '';
                if (is_array($message_data)) {
                    $alertType = htmlspecialchars($message_data['type']);
                    $text = htmlspecialchars($message_data['text']);
                } else {
                    $alertType = (strpos($message_data, 'successfully') !== false || $msg_key === 'info_message') ? 'success' : 'danger';
                    $text = htmlspecialchars($message_data);
                }
                echo "<div class=\"alert alert-{$alertType} alert-dismissible fade show\" role=\"alert\">{$text}
                      <button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\" aria-label=\"Close\"></button></div>";
                unset($_SESSION[$msg_key]);
            }
        }
        ?>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card stat-card">
                    <div class="card-body text-center"><img src="../images/Pesos.png" width="40" height="40"
                            class="me-1">
                        <h3>₱<?php echo number_format($overallStats['today']['sales'] ?? 0, 2); ?></h3>
                        <p class="mb-0">Today's Sales</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <img src="../images/Cart.png" width="40" height="40" class="me-1">
                        <h3><?php echo $overallStats['today']['orders'] ?? 0; ?></h3>
                        <p class="mb-0">Today's Orders</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <img src="../images/Calendar.png" width="40" height="40" class="me-1">
                        <h3>₱<?php echo number_format($overallStats['month']['sales'] ?? 0, 2); ?></h3>
                        <p class="mb-0">This Month</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card">
                    <div class="card-body text-center"> <img src="../images/Orders.png" width="40" height="40"
                            class="me-1">
                        <h3><?php echo $overallStats['month']['orders'] ?? 0; ?></h3>
                        <p class="mb-0">Monthly Orders</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <ul class="nav nav-pills mb-4" id="dashboardTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="overview-tab" data-bs-toggle="pill" data-bs-target="#overview">
                    <img src="../images/Overview_b.png" id="overview-icon" width="20" height="18" class="me-1">Overview
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="best-sellers-tab" data-bs-toggle="pill" data-bs-target="#best-sellers">
                    <img src="../images/Best_Seller_b.png" id="best-sellers-icon" width="20" height="18"
                        class="me-1">Best Sellers
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="summary-tab" data-bs-toggle="pill" data-bs-target="#summary">
                    <img src="../images/Summary_b.png" id="summary-icon" width="20" height="18" class="me-1">Summary
                    Report
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="product-list-tab" data-bs-toggle="pill" data-bs-target="#product-list">
                    <img src="../images/Product_list_b.png" id="product-list-icon" width="20" height="18"
                        class="me-1">Product List
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="staff-tab" data-bs-toggle="pill" data-bs-target="#staff">
                    <img src="../images/Staff_b.png" id="staff-icon" width="20" height="18" class="me-1">Staff
                </button>
            </li>

            <script>
                // Function to update all icons based on theme
                function updateTabIcons(isDarkMode) {
                    const iconMappings = {
                        'overview-icon': ['Overview_b.png', 'Overview_w.png'],
                        'best-sellers-icon': ['Best_Seller_b.png', 'Best_Seller_w.png'],
                        'summary-icon': ['Summary_b.png', 'Summary_w.png'],
                        'product-list-icon': ['Product_list_b.png', 'Product_list_w.png'],
                        'staff-icon': ['Staff_b.png', 'Staff_w.png']
                    };

                    for (const [iconId, [lightIcon, darkIcon]] of Object.entries(iconMappings)) {
                        const iconElement = document.getElementById(iconId);
                        if (iconElement) {
                            iconElement.src = `../images/${isDarkMode ? darkIcon : lightIcon}`;
                        }
                    }
                }

                // Update icons when dark mode changes
                document.getElementById('darkModeToggle').addEventListener('change', function () {
                    updateTabIcons(this.checked);
                });

                // Set initial icon states
                document.addEventListener('DOMContentLoaded', function () {
                    const isDarkMode = document.body.classList.contains('dark-mode');
                    updateTabIcons(isDarkMode);
                });
            </script>
        </ul>

        <div class="tab-content" id="dashboardTabContent">
            <!-- Overview Tab -->
            <div class="tab-pane fade show active" id="overview">
                <div class="row">
                    <!-- Sales Chart Section -->
                    <div class="col-lg-8 mb-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><?php echo htmlspecialchars($chartInfo['title']); ?></h5>
                                <div class="period-selector">
                                    <button
                                        class="period-btn <?php echo $chartInfo['selectedPeriod'] === 'daily' ? 'active' : ''; ?>"
                                        onclick="changePeriod('daily')">
                                        <img src="../images/Daily.png" width="20" height="19" class="me-1">Daily
                                    </button>
                                    <button
                                        class="period-btn <?php echo $chartInfo['selectedPeriod'] === 'weekly' ? 'active' : ''; ?>"
                                        onclick="changePeriod('weekly')">
                                        <img src="../images/Weekly.png" width="20" height="19" class="me-1">Weekly
                                    </button>
                                    <button
                                        class="period-btn <?php echo $chartInfo['selectedPeriod'] === 'monthly' ? 'active' : ''; ?>"
                                        onclick="changePeriod('monthly')">
                                        <img src="../images/Monthly.png" width="20" height="19" class="me-1">Monthly
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="sales-summary mb-3">
                                    <div class="row text-center">
                                        <div class="col-md-4">
                                            <h4 class="text-primary mb-1">
                                                ₱<?php echo number_format($chartInfo['totalSales'], 2); ?></h4>
                                            <small class="text-muted">Total Sales</small>
                                        </div>
                                        <div class="col-md-4">
                                            <h4 class="text-success mb-1"><?php echo $chartInfo['totalOrders']; ?></h4>
                                            <small class="text-muted">Total Orders</small>
                                        </div>
                                        <div class="col-md-4">
                                            <h4 class="text-warning mb-1">
                                                ₱<?php echo $chartInfo['totalOrders'] > 0 ? number_format($chartInfo['totalSales'] / $chartInfo['totalOrders'], 2) : '0.00'; ?>
                                            </h4>
                                            <small class="text-muted">Average Order</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="chart-container" style="height:350px;">
                                    <canvas id="salesChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Methods Section -->
                    <div class="col-lg-4 mb-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Payment Methods</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container" style="height:300px;">
                                    <canvas id="paymentChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Staff Performance Tab -->
            <div class="tab-pane fade" id="staff" role="tabpanel">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Staff Management & Performance</h5>
                        <button class="btn btn-success btn-sm" data-bs-toggle="modal"
                            data-bs-target="#addStaffModal"><img src="../images/Add_Staff.png" width="15" height="15"
                                class="me-1">Add Staff</button>
                    </div>
                    <div class="card-body">
                        <?php if (empty($userPerformance)): ?>
                            <p class="text-muted text-center">No staff data available.</p> <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Full Name</th>
                                            <th>Username</th>
                                            <th>Email</th>
                                            <th>Role</th>
                                            <th>Orders</th>
                                            <th>Sales</th>
                                            <th>Avg. Order</th>
                                            <th>Performance</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($userPerformance as $user): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td><span
                                                        class="badge bg-secondary"><?php echo ucfirst(htmlspecialchars($user['role'])); ?></span>
                                                </td>
                                                <td><?php echo $user['total_orders']; ?></td>
                                                <td>₱<?php echo number_format($user['total_sales'] ?? 0, 2); ?></td>
                                                <td>₱<?php echo number_format($user['avg_order_value'] ?? 0, 2); ?></td>
                                                <td>
                                                    <?php $performanceVal = $user['total_orders'];
                                                    if ($user['role'] !== 'user') {
                                                        echo '<span class="badge bg-light text-dark">N/A</span>';
                                                    } elseif ($performanceVal >= 50) {
                                                        echo '<span class="badge bg-success">Excellent</span>';
                                                    } elseif ($performanceVal >= 20) {
                                                        echo '<span class="badge bg-warning text-dark">Good</span>';
                                                    } elseif ($performanceVal > 0) {
                                                        echo '<span class="badge bg-info text-dark">Average</span>';
                                                    } else {
                                                        echo '<span class="badge bg-light text-dark">No Orders</span>';
                                                    } ?>
                                                </td>
                                                <td class="action-btns">
                                                    <button class="btn btn-sm btn-outline-primary edit-staff-btn"
                                                        data-bs-toggle="modal" data-bs-target="#editStaffModal"
                                                        data-id="<?php echo $user['user_id']; ?>"
                                                        data-fullname="<?php echo htmlspecialchars($user['full_name']); ?>"
                                                        data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                                        data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                                        data-role="<?php echo htmlspecialchars($user['role']); ?>"
                                                        title="Edit Staff"><i class="fas fa-edit"></i></button>
                                                    <?php if ($_SESSION['user_id'] != $user['user_id']): ?> <button
                                                            class="btn btn-sm btn-outline-danger delete-staff-btn"
                                                            data-staff-id="<?php echo $user['user_id']; ?>"
                                                            data-staff-name="<?php echo htmlspecialchars($user['full_name']); ?>"
                                                            title="Delete Staff"><i class="fas fa-trash"></i></button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Product List Tab -->

            <div class="tab-pane fade" id="product-list" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Product List & Management</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Name</th>
                                        <th>Flavor</th>
                                        <th>S Price</th>
                                        <th>M Price</th>
                                        <th>L Price</th>
                                        <th>Image</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $displayRowNumber = 1;
                                    foreach ($productList as $row):
                                        $sizes = json_decode($row['sizes'], true);
                                        $smallPrice = $sizes[0]['price'] ?? 'N/A';
                                        $mediumPrice = $sizes[1]['price'] ?? 'N/A';
                                        $largePrice = $sizes[2]['price'] ?? 'N/A';
                                        ?>
                                        <tr>
                                            <td><?php echo $displayRowNumber++; ?></td>
                                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['flavors']); ?></td>
                                            <td><?php echo is_numeric($smallPrice) ? '₱' . number_format($smallPrice, 2) : htmlspecialchars($smallPrice); ?>
                                            </td>
                                            <td><?php echo is_numeric($mediumPrice) ? '₱' . number_format($mediumPrice, 2) : htmlspecialchars($mediumPrice); ?>
                                            </td>
                                            <td><?php echo is_numeric($largePrice) ? '₱' . number_format($largePrice, 2) : htmlspecialchars($largePrice); ?>
                                            </td>
                                            <td><?php echo ($row['image'] ? "<img src='../" . htmlspecialchars($row['image']) . "' width='50' alt='" . htmlspecialchars($row['name']) . "'>" : 'No Image'); ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary edit-product-btn"
                                                    data-bs-toggle="modal" data-bs-target="#editProductModal"
                                                    data-id="<?php echo $row['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($row['name']); ?>"
                                                    data-flavor="<?php echo htmlspecialchars($row['flavors']); ?>"
                                                    data-sizes='<?php echo htmlspecialchars(json_encode($sizes)); ?>'
                                                    data-addons='<?php echo htmlspecialchars($row['add_ons']); ?>'
                                                    data-image="<?php echo htmlspecialchars($row['image']); ?>">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-danger btn-sm delete-product-btn"
                                                    data-product-id="<?php echo $row['id']; ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($productList)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center">No products found. Add one below!</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer">
                        <h5 class="mb-3">Add New Product</h5>
                        <form method="post" action="dashboard.php#product-list" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-6 mb-3"><label for="productName" class="form-label">Product
                                        Name</label><input type="text" class="form-control" id="productName"
                                        name="product_name" required></div>
                                <div class="col-md-6 mb-3"><label for="productImage" class="form-label">Product
                                        Image</label><input type="file" class="form-control" id="productImage"
                                        name="product_image" accept="image/*"></div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="flavor" class="form-label">Flavor</label>
                                    <select class="form-select" id="flavor" name="flavor">
                                        <?php foreach ($flavorList as $flavorRow): ?>
                                            <option value="<?php echo htmlspecialchars($flavorRow['id']); ?>">
                                                <?php echo htmlspecialchars(ucfirst($flavorRow['name'])); ?>
                                            </option>
                                        <?php endforeach; ?>
                                        <option value="add_new">Add New Flavor...</option>
                                    </select>
                                    <input type="text" class="form-control mt-2" id="newFlavor" name="new_flavor"
                                        style="display: none;" placeholder="Enter new flavor name">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Sizes & Prices</label>
                                    <div class="input-group mb-1"><span class="input-group-text"
                                            style="width:80px;">Small</span><input type="number" step="0.01"
                                            class="form-control" name="small_price" placeholder="Price"></div>
                                    <div class="input-group mb-1"><span class="input-group-text"
                                            style="width:80px;">Medium</span><input type="number" step="0.01"
                                            class="form-control" name="medium_price" placeholder="Price"></div>
                                    <div class="input-group mb-1"><span class="input-group-text"
                                            style="width:80px;">Large</span><input type="number" step="0.01"
                                            class="form-control" name="large_price" placeholder="Price"></div>
                                    <hr>
                                    <div class="input-group mb-1"><span class="input-group-text" style="width:80px;">B1
                                            T1</span><input type="number" step="0.01" class="form-control"
                                            name="b1t1_price" placeholder="Price (Optional)"></div>
                                    <hr>
                                    <label class="form-label mt-2">Bundle Sizes (Optional)</label>
                                    <div class="input-group mb-1"><span class="input-group-text"
                                            style="width:80px;">B-Small</span><input type="number" step="0.01"
                                            class="form-control" name="bundle_small_price" placeholder="Price"></div>
                                    <div class="input-group mb-1"><span class="input-group-text"
                                            style="width:80px;">B-Medium</span><input type="number" step="0.01"
                                            class="form-control" name="bundle_medium_price" placeholder="Price"></div>
                                    <div class="input-group"><span class="input-group-text"
                                            style="width:80px;">B-Large</span><input type="number" step="0.01"
                                            class="form-control" name="bundle_large_price" placeholder="Price"></div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Add-ons</label>
                                <div id="add-ons-container"></div>
                                <button type="button" class="btn btn-outline-success btn-sm mt-2 add-add-on-button"><i
                                        class="fas fa-plus"></i> Add Add-on</button>
                            </div>
                            <button type="submit" class="btn btn-primary">Add Product</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Modals -->
            <div class="modal fade" id="orderDetailsModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Order Details</h5><button type="button" class="btn-close"
                                data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body" id="orderDetailsContent"></div>
                        <div class="modal-footer"><button type="button" class="btn btn-secondary"
                                data-bs-dismiss="modal">Close</button></div>
                    </div>
                </div>
            </div>
            <div class="modal fade" id="addStaffModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Add New Staff</h5><button type="button" class="btn-close"
                                data-bs-dismiss="modal"></button>
                        </div>
                        <form method="post" action="dashboard.php#staff"><input type="hidden" name="add_staff_action"
                                value="1">
                            <div class="modal-body">
                                <div class="mb-3"><label for="staffFullName" class="form-label">Full Name <span
                                            class="text-danger">*</span></label><input type="text" class="form-control"
                                        id="staffFullName" name="staff_full_name" required
                                        value="<?php echo isset($_SESSION['staff_message_reopen_modal']['user_data']['staff_full_name']) ? htmlspecialchars($_SESSION['staff_message_reopen_modal']['user_data']['staff_full_name']) : ''; ?>">
                                </div>
                                <div class="mb-3"><label for="staffUsername" class="form-label">Username <span
                                            class="text-danger">*</span></label><input type="text" class="form-control"
                                        id="staffUsername" name="staff_username" required
                                        value="<?php echo isset($_SESSION['staff_message_reopen_modal']['user_data']['staff_username']) ? htmlspecialchars($_SESSION['staff_message_reopen_modal']['user_data']['staff_username']) : ''; ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="staffPassword" class="form-label">Password <span
                                            class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="staffPassword"
                                            name="staff_password" required minlength="6">
                                        <button class="btn btn-outline-secondary" type="button"
                                            onclick="togglePasswordVisibility('staffPassword', 'staffPasswordIcon')">
                                            <img src="../images/password_hide_icon.png" alt="Toggle Password"
                                                id="staffPasswordIcon" style="width: 20px; height: 20px;">
                                        </button>
                                    </div>
                                    <div class="form-text">Min. 6 characters.</div>
                                </div>
                                <div class="mb-3"><label for="staffEmail" class="form-label">Email <span
                                            class="text-danger">*</span></label><input type="email" class="form-control"
                                        id="staffEmail" name="staff_email" required
                                        value="<?php echo isset($_SESSION['staff_message_reopen_modal']['user_data']['staff_email']) ? htmlspecialchars($_SESSION['staff_message_reopen_modal']['user_data']['staff_email']) : ''; ?>">
                                </div>
                                <div class="mb-3"><label for="staffRole" class="form-label">Role</label><select
                                        class="form-select" id="staffRole" name="staff_role">
                                        <option value="user" <?php echo (isset($_SESSION['staff_message_reopen_modal']['user_data']['staff_role']) && $_SESSION['staff_message_reopen_modal']['user_data']['staff_role'] == 'user') ? 'selected' : ''; ?>>User (Cashier/Staff)</option>
                                        <option value="admin" <?php echo (isset($_SESSION['staff_message_reopen_modal']['user_data']['staff_role']) && $_SESSION['staff_message_reopen_modal']['user_data']['staff_role'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                                    </select></div>
                            </div>
                            <div class="modal-footer"><button type="button" class="btn btn-secondary"
                                    data-bs-dismiss="modal">Close</button><button type="submit"
                                    class="btn btn-primary">Add Staff Member</button></div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="modal fade" id="editStaffModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Edit Staff Member</h5><button type="button" class="btn-close"
                                data-bs-dismiss="modal"></button>
                        </div>
                        <form method="post" action="dashboard.php#staff"><input type="hidden" name="edit_staff_action"
                                value="1"><input type="hidden" id="editStaffId" name="edit_staff_id">
                            <div class="modal-body">
                                <div class="mb-3"><label for="editStaffFullName" class="form-label">Full Name <span
                                            class="text-danger">*</span></label><input type="text" class="form-control"
                                        id="editStaffFullName" name="edit_staff_full_name" required></div>
                                <div class="mb-3"><label for="editStaffUsername" class="form-label">Username <span
                                            class="text-danger">*</span></label><input type="text" class="form-control"
                                        id="editStaffUsername" name="edit_staff_username" required></div>
                                <div class="mb-3">
                                    <label for="editStaffPassword" class="form-label">New Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="editStaffPassword"
                                            name="edit_staff_password" minlength="6">
                                        <button class="btn btn-outline-secondary" type="button"
                                            onclick="togglePasswordVisibility('editStaffPassword', 'editStaffPasswordIcon')">
                                            <img src="../images/password_hide_icon.png" alt="Toggle Password"
                                                id="editStaffPasswordIcon" style="width: 20px; height: 20px;">
                                        </button>
                                    </div>
                                    <div class="form-text text-warning">
                                        <i class="fas fa-info-circle"></i> For security reasons, existing passwords
                                        cannot be viewed.
                                        Leave this field empty to keep the current password, or enter a new password to
                                        change it.
                                    </div>
                                </div>
                                <div class="mb-3"><label for="editStaffEmail" class="form-label">Email <span
                                            class="text-danger">*</span></label><input type="email" class="form-control"
                                        id="editStaffEmail" name="edit_staff_email" required></div>
                                <div class="mb-3"><label for="editStaffRole" class="form-label">Role</label><select
                                        class="form-select" id="editStaffRole" name="edit_staff_role">
                                        <option value="user">User (Cashier/Staff)</option>
                                        <option value="admin">Admin</option>
                                    </select></div>
                            </div>
                            <div class="modal-footer"><button type="button" class="btn btn-secondary"
                                    data-bs-dismiss="modal">Close</button><button type="submit"
                                    class="btn btn-primary">Save Changes</button></div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Add Edit Product Modal -->
            <div class="modal fade" id="editProductModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Edit Product</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="post" action="dashboard.php#product-list" enctype="multipart/form-data">
                            <input type="hidden" name="edit_product_id" id="editProductId">
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label for="editProductName" class="form-label">Product Name <span
                                            class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="editProductName"
                                        name="edit_product_name" required>
                                </div>
                                <div class="mb-3">
                                    <label for="editProductImage" class="form-label">Product Image</label>
                                    <div class="current-image-container mb-2" style="max-width: 200px;">
                                        <img id="editProductImagePreview" src="" alt="Current Image"
                                            style="display:none; width: 100%; height: auto; border-radius: 4px;">
                                    </div>
                                    <input type="file" class="form-control" id="editProductImage"
                                        name="edit_product_image" accept="image/*">
                                    <small class="text-muted">Leave empty to keep current image. Upload new image to
                                        replace.</small>
                                </div>
                                <div class="mb-3">
                                    <label for="editFlavor" class="form-label">Flavor</label>
                                    <select class="form-select" id="editFlavor" name="edit_flavor">
                                        <?php foreach ($flavorList as $flavorRow): ?>
                                            <option value="<?php echo htmlspecialchars($flavorRow['id']); ?>">
                                                <?php echo htmlspecialchars(ucfirst($flavorRow['name'])); ?>
                                            </option>
                                        <?php endforeach; ?>
                                        <option value="add_new">Add New Flavor...</option>
                                    </select>
                                    <input type="text" class="form-control mt-2" id="editNewFlavor"
                                        name="edit_new_flavor" style="display: none;"
                                        placeholder="Enter new flavor name">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Sizes & Prices</label>
                                    <div class="input-group mb-1"><span class="input-group-text"
                                            style="width:80px;">Small</span><input type="number" step="0.01"
                                            class="form-control" id="editSmallPrice" name="edit_small_price"
                                            placeholder="Price"></div>
                                    <div class="input-group mb-1"><span class="input-group-text"
                                            style="width:80px;">Medium</span><input type="number" step="0.01"
                                            class="form-control" id="editMediumPrice" name="edit_medium_price"
                                            placeholder="Price"></div>
                                    <div class="input-group mb-1"><span class="input-group-text"
                                            style="width:80px;">Large</span><input type="number" step="0.01"
                                            class="form-control" id="editLargePrice" name="edit_large_price"
                                            placeholder="Price"></div>
                                    <hr>
                                    <div class="input-group mb-1"><span class="input-group-text" style="width:80px;">B1
                                            T1</span><input type="number" step="0.01" class="form-control"
                                            id="editB1t1Price" name="edit_b1t1_price" placeholder="Price (Optional)">
                                    </div>
                                    <hr>
                                    <label class="form-label mt-2">Bundle Sizes (Optional)</label>
                                    <div class="input-group mb-1"><span class="input-group-text"
                                            style="width:80px;">B-Small</span><input type="number" step="0.01"
                                            class="form-control" id="editBundleSmallPrice"
                                            name="edit_bundle_small_price" placeholder="Price"></div>
                                    <div class="input-group mb-1"><span class="input-group-text"
                                            style="width:80px;">B-Medium</span><input type="number" step="0.01"
                                            class="form-control" id="editBundleMediumPrice"
                                            name="edit_bundle_medium_price" placeholder="Price"></div>
                                    <div class="input-group"><span class="input-group-text"
                                            style="width:80px;">B-Large</span><input type="number" step="0.01"
                                            class="form-control" id="editBundleLargePrice"
                                            name="edit_bundle_large_price" placeholder="Price"></div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Add-ons</label>
                                    <div id="edit-add-ons-container"></div>
                                    <button type="button"
                                        class="btn btn-outline-success btn-sm mt-2 add-edit-add-on-button"><i
                                            class="fas fa-plus"></i> Add Add-on</button>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Summary Report Tab -->
            <div class="tab-pane fade" id="summary" role="tabpanel">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Summary Report</h5>
                        <div class="d-flex gap-2">
                            <form class="d-flex gap-2" method="get" action="dashboard.php#summary">
                                <input type="date" class="form-control form-control-sm" name="summary_start_date"
                                    value="<?php echo htmlspecialchars($summaryStartDate); ?>" required>
                                <input type="date" class="form-control form-control-sm" name="summary_end_date"
                                    value="<?php echo htmlspecialchars($summaryEndDate); ?>" required>
                                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                            </form>
                            <?php if (!empty($summaryReport['items'])): ?>
                                <button class="btn btn-sm btn-success" onclick="downloadSummaryPdf()">
                                    <img src="../images/download.png" width="15" height="15" class="me-1">Download PDF
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="clearSummaryData()">
                                    <img src="../images/Remove.png" width="15" height="15" class="me-1">Clear Data
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($summaryReport['items'])): ?>
                            <div class="alert alert-info">
                                No data available for the selected period
                                (<?php echo date('M j, Y', strtotime($summaryStartDate)); ?> -
                                <?php echo date('M j, Y', strtotime($summaryEndDate)); ?>)
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover" id="summaryTable">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Product</th>
                                            <th>Size</th>
                                            <th>Quantity</th>
                                            <th>Unit Price</th>
                                            <th>Sales</th>
                                            <th>Cashier</th>
                                            <th>Payment</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($summaryReport['items'] as $row): ?>
                                            <tr>
                                                <td><?php echo date('M j, Y', strtotime($row['date'])); ?></td>
                                                <td><?php echo htmlspecialchars($row['product']); ?></td>
                                                <td><?php echo htmlspecialchars($row['size']); ?></td>
                                                <td><?php echo number_format($row['quantity']); ?></td>
                                                <td>₱<?php echo number_format($row['unit_price'], 2); ?></td>
                                                <td>₱<?php echo number_format($row['total_sales'], 2); ?></td>
                                                <td><?php echo htmlspecialchars($row['cashier']); ?></td>
                                                <td><span
                                                        class="badge bg-<?php echo $row['payment_method'] === 'CASH' ? 'success' : 'primary'; ?>"><?php echo htmlspecialchars($row['payment_method']); ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr class="table-success fw-bold">
                                            <td colspan="3">Total Sales</td>
                                            <td><?php echo number_format($summaryReport['total_quantity']); ?></td>
                                            <td>-</td>
                                            <td>₱<?php echo number_format($summaryReport['total_sales'], 2); ?></td>
                                            <td colspan="2"></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Remove the Best Sellers section from overview and add it as a new tab content -->
            <div class="tab-pane fade" id="best-sellers" role="tabpanel">
                <div class="container-fluid">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h3 class="mb-0">Best Selling Products</h3>
                        <button onclick="downloadBestSellersPDF()" class="btn btn-success">
                            <i class="fas fa-download me-2"></i>Download PDF
                        </button>
                    </div>

                    <div class="row">
                        <!-- Chart Section -->
                        <div class="col-lg-8 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">Top Products by Revenue and Units Sold</h5>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container" style="height:400px;">
                                        <canvas id="bestSellersChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Table Section -->
                        <div class="col-lg-4 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">Detailed Statistics</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Product</th>
                                                    <th>Revenue</th>
                                                    <th>Units</th>
                                                </tr>
                                            </thead>
                                            <tbody id="bestSellersTable">
                                                <!-- Will be populated by JavaScript -->
                                                <?php if (empty($bestSellers)): ?>
                                                    <tr>
                                                        <td colspan="3" class="text-center">No data available</td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php
                                                    $totalRevenue = 0;
                                                    $totalQuantity = 0;
                                                    foreach ($bestSellers as $item):
                                                        $totalRevenue += $item['revenue'];
                                                        $totalQuantity += $item['sales_count'];
                                                        ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                                                            <td class="text-end">
                                                                ₱<?php echo number_format($item['revenue'], 2); ?></td>
                                                            <td class="text-end">
                                                                <?php echo number_format($item['sales_count']); ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                    <tr class="table-success fw-bold">
                                                        <td>Total</td>
                                                        <td class="text-end">₱<?php echo number_format($totalRevenue, 2); ?>
                                                        </td>
                                                        <td class="text-end"><?php echo number_format($totalQuantity); ?>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div> <!-- /container -->

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            // Define flavorList for JavaScript usage
            const flavorList = <?php echo json_encode($flavorList); ?>;

            document.addEventListener('DOMContentLoaded', function () {
                const darkModeToggle = document.getElementById('darkModeToggle');
                const body = document.body;

                // Check if user has a saved preference
                const darkMode = localStorage.getItem('darkMode') === 'enabled';

                // Set initial state
                if (darkMode) {
                    body.classList.add('dark-mode');
                    darkModeToggle.checked = true;
                }

                // Toggle dark mode
                darkModeToggle.addEventListener('change', () => {
                    body.classList.toggle('dark-mode');
                    const isDark = body.classList.contains('dark-mode');
                    localStorage.setItem('darkMode', isDark ? 'true' : 'false');

                    // Update URL parameter
                    const url = new URL(window.location.href);
                    url.searchParams.set('darkMode', isDark ? '1' : '0');
                    history.replaceState(null, '', url);
                });

                // Add toggle event listener
                darkModeToggle.addEventListener('change', function () {
                    if (this.checked) {
                        body.classList.add('dark-mode');
                        body.classList.remove('light-mode');
                        localStorage.setItem('darkMode', 'true');
                    } else {
                        body.classList.add('light-mode');
                        body.classList.remove('dark-mode');
                        localStorage.setItem('darkMode', 'false');
                    }

                    // Refresh charts if they exist
                    if (typeof salesChart !== 'undefined') {
                        salesChart.update();
                    }
                    if (typeof paymentChart !== 'undefined') {
                        paymentChart.update();
                    }
                });

                // Handle URL hash for tab activation
                const currentHash = window.location.hash;
                if (currentHash) {
                    const tabElement = document.querySelector(
                        `.nav-pills .nav-link[data-bs-target="${currentHash}"]`,
                    );
                    if (tabElement) new bootstrap.Tab(tabElement).show();
                }

                <?php if (isset($_SESSION['staff_message_reopen_modal'])): ?>
                    const modalInfo = <?php echo json_encode($_SESSION['staff_message_reopen_modal']); ?>;
                    const staffModal = new bootstrap.Modal(document.getElementById(modalInfo.modal_id));
                    if (modalInfo.modal_id === 'editStaffModal' && modalInfo.user_data) {
                        document.getElementById('editStaffId').value =
                            modalInfo.user_data.edit_staff_id || '';
                        document.getElementById('editStaffFullName').value =
                            modalInfo.user_data.edit_staff_full_name || '';
                        document.getElementById('editStaffUsername').value =
                            modalInfo.user_data.edit_staff_username || '';
                        document.getElementById('editStaffEmail').value =
                            modalInfo.user_data.edit_staff_email || '';
                        document.getElementById('editStaffRole').value =
                            modalInfo.user_data.edit_staff_role || 'user';
                    }
                    staffModal.show();
                    <?php unset($_SESSION['staff_message_reopen_modal']); ?>
                <?php endif; ?>

                // Update URL hash when a tab is shown
                document.querySelectorAll('#dashboardTabs .nav-link').forEach(pill => {
                    pill.addEventListener('shown.bs.tab', event => {
                        if (history.pushState)
                            history.pushState(
                                null,
                                null,
                                event.target.getAttribute('data-bs-target'),
                            );
                        else
                            window.location.hash =
                                event.target.getAttribute('data-bs-target');
                    });
                });

                // Delete Product confirmation and form submission
                document
                    .querySelectorAll('.delete-product-btn')
                    .forEach(btn =>
                        btn.addEventListener('click', function () {
                            if (confirm(`Do you want to delete this product?`)) {
                                const form = document.createElement('form');
                                form.method = 'POST';
                                form.action = 'dashboard.php#product-list';
                                const input = document.createElement('input');
                                input.type = 'hidden';
                                input.name = 'delete_product';
                                input.value = this.dataset.productId;
                                form.appendChild(input);
                                document.body.appendChild(form);
                                form.submit();
                            }
                        }),
                    );

                // Delete Staff confirmation and form submission
                document
                    .querySelectorAll('.delete-staff-btn')
                    .forEach(btn =>
                        btn.addEventListener('click', function () {
                            const staffName = this.dataset.staffName || 'this staff member';
                            if (
                                confirm(
                                    `Are you sure you want to delete ${staffName}? This action cannot be undone.`,
                                )
                            ) {
                                const form = document.createElement('form');
                                form.method = 'POST';
                                form.action = 'dashboard.php#staff';
                                const input = document.createElement('input');
                                input.type = 'hidden';
                                input.name = 'delete_staff_id';
                                input.value = this.dataset.staffId;
                                form.appendChild(input);
                                document.body.appendChild(form);
                                form.submit();
                            }
                        }),
                    );

                // Populate Edit Staff Modal on show
                const editStaffModalEl = document.getElementById('editStaffModal');
                if (editStaffModalEl) {
                    editStaffModalEl.addEventListener('show.bs.modal', function (event) {
                        const button = event.relatedTarget;
                        if (!button) return;
                        document.getElementById('editStaffId').value = button.dataset.id;
                        document.getElementById('editStaffFullName').value =
                            button.dataset.fullname;
                        document.getElementById('editStaffUsername').value =
                            button.dataset.username;
                        document.getElementById('editStaffEmail').value =
                            button.dataset.email;
                        document.getElementById('editStaffRole').value = button.dataset.role;
                        document.getElementById('editStaffPassword').value = '';
                    });
                }

                // --- Common functions for Add-ons and Flavor Selectors ---
                const setupFlavorSelect = (flavorSelectId, newFlavorInputId) => {
                    const flavorSelect = document.getElementById(flavorSelectId);
                    const newFlavorInput = document.getElementById(newFlavorInputId);
                    if (flavorSelect && newFlavorInput) {
                        const toggleNewFlavorInput = () => {
                            const isAddNew = flavorSelect.value === 'add_new';
                            newFlavorInput.style.display = isAddNew ? 'block' : 'none';
                            newFlavorInput.required = isAddNew;
                            if (isAddNew) newFlavorInput.focus();
                            else newFlavorInput.value = '';
                        };

                        flavorSelect.addEventListener('change', toggleNewFlavorInput);
                        // Set initial state based on current value
                        toggleNewFlavorInput();
                    }
                };

                const setupAddOnInputs = (containerId, addButtonClass) => {
                    const addOnsContainer = document.getElementById(containerId);
                    const addAddOnButton = document.querySelector(addButtonClass);

                    const addAddOnInputRow = () => {
                        if (!addOnsContainer) return;
                        const div = document.createElement('div');
                        div.classList.add('row', 'mb-2', 'align-items-center');
                        div.innerHTML = `<div class="col-md-5"><input type="text" class="form-control form-control-sm" name="${containerId === 'edit-add-ons-container' ? 'edit_add_on_name[]' : 'add_on_name[]'}" placeholder="Add-on Name"></div><div class="col-md-5"><input type="number" step="0.01" class="form-control form-control-sm" name="${containerId === 'edit-add-ons-container' ? 'edit_add_on_price[]' : 'add_on_price[]'}" placeholder="Price"></div><div class="col-md-2"><button type="button" class="btn btn-danger btn-sm remove-add-on-row w-100"><i class="fas fa-times"></i></button></div>`;
                        addOnsContainer.appendChild(div);
                        div
                            .querySelector('.remove-add-on-row')
                            .addEventListener('click', () => div.remove());
                    };

                    if (addAddOnButton)
                        addAddOnButton.addEventListener('click', addAddOnInputRow);
                    // Add an initial row if the container is empty
                    if (addOnsContainer && addOnsContainer.children.length === 0)
                        addAddOnInputRow();
                };

                // Initialize flavor selectors and add-on sections
                setupFlavorSelect('flavor', 'newFlavor');
                setupFlavorSelect('editFlavor', 'editNewFlavor');
                setupAddOnInputs('add-ons-container', '.add-add-on-button');
                setupAddOnInputs('edit-add-ons-container', '.add-edit-add-on-button');

                // Populate Edit Product Modal on show
                document.querySelectorAll('.edit-product-btn').forEach(btn => {
                    btn.addEventListener('click', function () {
                        const id = this.dataset.id;
                        const name = this.dataset.name;
                        const flavor = this.dataset.flavor;
                        const sizes = JSON.parse(this.dataset.sizes || '[]');
                        const addons = JSON.parse(this.dataset.addons || '[]');
                        const image = this.dataset.image;

                        // Populate modal fields
                        document.getElementById('editProductId').value = id;
                        document.getElementById('editProductName').value = name;

                        // Set flavor select value
                        const flavorSelect = document.getElementById('editFlavor');
                        const flavorInput = document.getElementById('editNewFlavor');
                        const matchingFlavor = flavorList.find(
                            f => f.name.toLowerCase() === flavor.toLowerCase(),
                        );
                        if (matchingFlavor) {
                            flavorSelect.value = matchingFlavor.id;
                            flavorInput.style.display = 'none';
                            flavorInput.value = '';
                        } else {
                            flavorSelect.value = 'add_new';
                            flavorInput.style.display = 'block';
                            flavorInput.value = flavor;
                        }

                        // Populate sizes
                        document.getElementById('editSmallPrice').value =
                            sizes.find(s => s.name === 'Small')?.price || '';
                        document.getElementById('editMediumPrice').value =
                            sizes.find(s => s.name === 'Medium')?.price || '';
                        document.getElementById('editLargePrice').value =
                            sizes.find(s => s.name === 'Large')?.price || '';
                        document.getElementById('editB1t1Price').value =
                            sizes.find(s => s.name === 'B1 T1')?.price || '';
                        document.getElementById('editBundleSmallPrice').value =
                            sizes.find(s => s.name === 'Bundle Small')?.price || '';
                        document.getElementById('editBundleMediumPrice').value =
                            sizes.find(s => s.name === 'Bundle Medium')?.price || '';
                        document.getElementById('editBundleLargePrice').value =
                            sizes.find(s => s.name === 'Bundle Large')?.price || '';

                        // Show current image if exists
                        const imagePreview = document.getElementById('editProductImagePreview');
                        const imageInput = document.getElementById('editProductImage');

                        if (image) {
                            imagePreview.src = '../' + image;
                            imagePreview.style.display = 'block';
                        } else {
                            imagePreview.style.display = 'none';
                        }

                        // Add preview for newly selected images
                        imageInput.addEventListener('change', function (e) {
                            if (this.files && this.files[0]) {
                                const reader = new FileReader();
                                reader.onload = function (e) {
                                    imagePreview.src = e.target.result;
                                    imagePreview.style.display = 'block';
                                };
                                reader.readAsDataURL(this.files[0]);
                            }
                        });

                        // Clear existing add-on rows and populate with current add-ons
                        const editAddOnsContainer = document.getElementById(
                            'edit-add-ons-container',
                        );
                        while (editAddOnsContainer.firstChild) {
                            editAddOnsContainer.removeChild(editAddOnsContainer.firstChild);
                        }

                        // Populate existing add-ons
                        if (addons && addons.length > 0) {
                            addons.forEach(addon => {
                                const div = document.createElement('div');
                                div.classList.add('row', 'mb-2', 'align-items-center');
                                div.innerHTML = `
                                    <div class="col-md-5">
                                        <input type="text" class="form-control form-control-sm" name="edit_add_on_name[]" placeholder="Add-on Name" value="${addon.name || ''}">
                                    </div>
                                    <div class="col-md-5">
                                        <input type="number" step="0.01" class="form-control form-control-sm" name="edit_add_on_price[]" placeholder="Price" value="${addon.price || ''}">
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" class="btn btn-danger btn-sm remove-add-on-row w-100">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>`;
                                editAddOnsContainer.appendChild(div);
                                div.querySelector('.remove-add-on-row').addEventListener('click', () => div.remove());
                            });
                        } else {
                            // Add one empty add-on row if no existing add-ons
                            const addAddOnButton = document.querySelector('.add-edit-add-on-button');
                            if (addAddOnButton) {
                                addAddOnButton.click();
                            }
                        }
                        setupAddOnInputs('edit-add-ons-container', '.add-edit-add-on-button');
                    });
                });

                // --- Chart Initialization ---
                function getChartColors() {
                    const isDarkMode = document.body.classList.contains('dark-mode');
                    return {
                        textColor: isDarkMode ? '#ffffff' : '#666666',
                        gridColor: isDarkMode ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)',
                        backgroundColor: isDarkMode ? '#1e1e1e' : '#ffffff',
                        borderColor: isDarkMode ? '#404040' : '#ddd',
                        legendTextColor: isDarkMode ? '#ffffff' : '#666666'
                    };
                }

                // Initialize colors once for all charts
                const chartColors = getChartColors();

                // Configure default Chart.js options for dark mode
                function updateChartTheme(isDarkMode) {
                    const chartColors = getChartColors();
                    Chart.defaults.color = chartColors.textColor;
                    Chart.defaults.borderColor = chartColors.borderColor;
                    Chart.defaults.scale.grid.color = chartColors.gridColor;

                    // Update existing charts
                    if (window.bestSellersChart) {
                        bestSellersChart.options.scales.x.grid.color = chartColors.gridColor;
                        bestSellersChart.options.scales.x.ticks.color = chartColors.textColor;
                        bestSellersChart.options.scales.y.ticks.color = chartColors.textColor;
                        bestSellersChart.options.plugins.legend.labels.color = chartColors.textColor;
                        bestSellersChart.options.plugins.title.color = chartColors.textColor;
                        bestSellersChart.data.datasets[0].backgroundColor = isDarkMode ? 'rgba(0, 150, 0, 0.8)' : 'rgba(0, 70, 0, 0.8)';
                        bestSellersChart.data.datasets[0].borderColor = isDarkMode ? 'rgba(0, 200, 0, 1)' : 'rgba(0, 70, 0, 1)';
                        bestSellersChart.update('none');
                    }

                    if (window.salesChart) {
                        salesChart.options.scales.x.ticks.color = chartColors.textColor;
                        salesChart.options.scales.y.ticks.color = chartColors.textColor;
                        salesChart.options.plugins.legend.labels.color = chartColors.textColor;
                        // Update dataset colors
                        salesChart.data.datasets[0].borderColor = isDarkMode ? '#00b300' : '#004d00';
                        salesChart.data.datasets[0].backgroundColor = isDarkMode ? 'var(--chart-green-dark)' : 'var(--chart-green-light)';
                        salesChart.data.datasets[1].borderColor = isDarkMode ? '#4d94ff' : '#3b82f6';
                        salesChart.data.datasets[1].backgroundColor = isDarkMode ? 'var(--chart-blue-dark)' : 'var(--chart-blue-light)';
                        salesChart.update('none');
                    }

                    if (window.paymentMethodsChart) {
                        paymentMethodsChart.options.plugins.legend.labels.color = chartColors.textColor;
                        paymentMethodsChart.update('none');
                    }
                }

                // Call updateChartTheme on page load
                document.addEventListener('DOMContentLoaded', function () {
                    // Check URL parameter first, then localStorage
                    const urlParams = new URLSearchParams(window.location.search);
                    const isDarkMode = urlParams.get('darkMode') === '1' || localStorage.getItem('darkMode') === 'true';

                    // Apply dark mode if needed
                    if (isDarkMode) {
                        document.body.classList.add('dark-mode');
                        document.getElementById('darkModeToggle').checked = true;
                    }

                    // Update chart theme
                    updateChartTheme(isDarkMode);
                });

                // Update theme when dark mode toggle changes
                document.getElementById('darkModeToggle').addEventListener('change', function () {
                    const isDarkMode = this.checked;
                    updateChartTheme(isDarkMode);
                });

                const salesChartCtx = document.getElementById('salesChart')?.getContext('2d');
                if (salesChartCtx && typeof Chart !== 'undefined') {
                    const salesChartData = <?php echo json_encode($chartInfo['data']); ?>;

                    const isDarkMode = document.body.classList.contains('dark-mode');
                    window.salesChart = new Chart(salesChartCtx, {
                        type: 'line',
                        data: {
                            labels: salesChartData.map(item => item.period),
                            datasets: [{
                                label: 'Sales (₱)',
                                data: salesChartData.map(item => parseFloat(item.sales)),
                                borderColor: isDarkMode ? '#00cc00' : '#004600',
                                backgroundColor: isDarkMode ? 'rgba(0,204,0,0.2)' : 'rgba(0,70,0,0.1)',
                                tension: 0.3,
                                fill: true,
                                yAxisID: 'y',
                                borderWidth: 2
                            }, {
                                label: 'Orders',
                                data: salesChartData.map(item => parseInt(item.orders)),
                                borderColor: isDarkMode ? '#3399ff' : '#3b82f6',
                                backgroundColor: isDarkMode ? 'rgba(51,153,255,0.2)' : 'rgba(59,130,246,0.1)',
                                tension: 0.3,
                                fill: true,
                                yAxisID: 'y1',
                                borderWidth: 2
                            },],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: {
                                mode: 'index',
                                intersect: false,
                            },
                            scales: {
                                x: {
                                    title: {
                                        display: true,
                                        text: 'Period',
                                        color: chartColors.textColor,
                                        font: {
                                            size: 12,
                                            weight: 'bold'
                                        }
                                    },
                                    grid: {
                                        color: chartColors.gridColor,
                                        drawBorder: true,
                                        borderDash: [5, 5]
                                    },
                                    ticks: {
                                        color: chartColors.textColor,
                                        font: {
                                            size: 11
                                        }
                                    },
                                },
                                y: {
                                    position: 'left',
                                    title: {
                                        display: true,
                                        text: 'Sales (₱)',
                                        color: chartColors.textColor,
                                        font: {
                                            size: 12,
                                            weight: 'bold'
                                        }
                                    },
                                    ticks: {
                                        callback: v => '₱' + v.toLocaleString(),
                                        color: chartColors.textColor,
                                        font: {
                                            size: 11
                                        }
                                    },
                                    grid: {
                                        color: chartColors.gridColor,
                                        drawBorder: true,
                                        borderDash: [5, 5]
                                    },
                                },
                                y1: {
                                    position: 'right',
                                    title: {
                                        display: true,
                                        text: 'Orders',
                                        color: chartColors.textColor,
                                        font: {
                                            size: 12,
                                            weight: 'bold'
                                        }
                                    },
                                    grid: {
                                        drawOnChartArea: false,
                                        color: chartColors.gridColor,
                                        drawBorder: true,
                                        borderDash: [5, 5]
                                    },
                                    ticks: {
                                        color: chartColors.textColor,
                                        font: {
                                            size: 11
                                        }
                                    },
                                },
                            },
                            plugins: {
                                legend: {
                                    labels: {
                                        color: chartColors.textColor,
                                        font: {
                                            size: 12,
                                            weight: 'bold'
                                        },
                                        usePointStyle: true,
                                        pointStyle: 'circle'
                                    },
                                    align: 'center',
                                },
                                tooltip: {
                                    backgroundColor: isDarkMode ? 'rgba(0, 0, 0, 0.8)' : 'rgba(255, 255, 255, 0.8)',
                                    titleColor: chartColors.textColor,
                                    bodyColor: chartColors.textColor,
                                    borderColor: chartColors.borderColor,
                                    borderWidth: 1,
                                    padding: 10,
                                    cornerRadius: 4,
                                    displayColors: true,
                                    callbacks: {
                                        label: ctx =>
                                            ctx.datasetIndex === 0 ?
                                                `Sales: ₱${ctx.parsed.y.toLocaleString()}` :
                                                `Orders: ${ctx.parsed.y}`,
                                        labelTextColor: function (context) {
                                            return chartColors.textColor;
                                        }
                                    },
                                },
                            },
                        },
                    });
                }

                const paymentChartCtx = document
                    .getElementById('paymentChart')
                    ?.getContext('2d');
                if (
                    paymentChartCtx &&
                    typeof Chart !== 'undefined' &&
                    <?php echo !empty($paymentMethods) ? 'true' : 'false'; ?>
                ) {
                    const paymentData = <?php echo json_encode($paymentMethods); ?>;
                    const isDarkMode = document.body.classList.contains('dark-mode');

                    window.paymentChart = new Chart(paymentChartCtx, {
                        type: 'doughnut',
                        data: {
                            labels: paymentData.map(item => item.payment_method.toUpperCase()),
                            datasets: [{
                                data: paymentData.map(item => parseInt(item.count)),
                                backgroundColor: ['#004600', '#3b82f6'],
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        color: isDarkMode ? '#ffffff' : '#000000',
                                        font: {
                                            size: 14,
                                            weight: 'bold'
                                        },
                                        padding: 20,
                                        usePointStyle: true
                                    }
                                }
                            }
                        }
                    });

                    // Update chart colors when theme changes
                    document.getElementById('darkModeToggle').addEventListener('change', function () {
                        const isDarkMode = this.checked;
                        if (window.paymentChart) {
                            window.paymentChart.options.plugins.legend.labels.color = isDarkMode ? '#ffffff' : '#000000';
                            window.paymentChart.update();
                        }
                    });
                }

                // Best Sellers Chart
                const bestSellersCtx = document.getElementById('bestSellersChart').getContext('2d');
                const bestSellersData = <?php echo json_encode($bestSellers); ?>;

                // Debug: Log the best sellers data
                console.log('Best Sellers Data:', bestSellersData);

                // Destroy existing chart if it exists
                if (window.bestSellersChart instanceof Chart) {
                    window.bestSellersChart.destroy();
                }

                // Check if bestSellersData is valid and not empty
                if (!Array.isArray(bestSellersData) || bestSellersData.length === 0) {
                    // Display "No data available" message in the chart area
                    const noDataMessage = {
                        type: 'bar',
                        data: {
                            labels: ['No Data'],
                            datasets: [{
                                label: 'Revenue (₱)',
                                data: [0],
                                backgroundColor: '#004600'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                title: {
                                    display: true,
                                    text: 'No sales data available',
                                    color: chartColors.textColor,
                                    font: {
                                        size: 16,
                                        weight: 'bold'
                                    }
                                }
                            }
                        }
                    };
                    window.bestSellersChart = new Chart(bestSellersCtx, noDataMessage);
                } else {
                    window.bestSellersChart = new Chart(bestSellersCtx, {
                        type: 'bar',
                        data: {
                            labels: bestSellersData.map(item => item.name),
                            datasets: [{
                                label: 'Revenue (₱)',
                                data: bestSellersData.map(item => item.revenue),
                                backgroundColor: document.body.classList.contains('dark-mode') ? 'rgba(0, 150, 0, 0.8)' : 'rgba(0, 70, 0, 0.8)',
                                borderColor: document.body.classList.contains('dark-mode') ? 'rgba(0, 200, 0, 1)' : 'rgba(0, 70, 0, 1)',
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            indexAxis: 'y',
                            scales: {
                                x: {
                                    grid: {
                                        color: chartColors.gridColor
                                    },
                                    ticks: {
                                        color: chartColors.textColor,
                                        font: {
                                            size: 12
                                        },
                                        callback: function (value) {
                                            return '₱' + value.toLocaleString();
                                        }
                                    }
                                },
                                y: {
                                    grid: {
                                        display: false
                                    },
                                    ticks: {
                                        color: chartColors.textColor,
                                        font: {
                                            size: 12
                                        }
                                    }
                                }
                            },
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'top',
                                    labels: {
                                        color: chartColors.textColor,
                                        font: {
                                            size: 14
                                        },
                                        usePointStyle: true,
                                        padding: 20
                                    }
                                },
                                title: {
                                    display: true,
                                    text: 'Top Products by Revenue',
                                    color: chartColors.textColor,
                                    font: {
                                        size: 16,
                                        weight: 'bold'
                                    },
                                    padding: {
                                        top: 10,
                                        bottom: 30
                                    }
                                },
                                tooltip: {
                                    backgroundColor: chartColors.backgroundColor,
                                    titleColor: chartColors.textColor,
                                    bodyColor: chartColors.textColor,
                                    borderColor: chartColors.borderColor,
                                    borderWidth: 1,
                                    padding: 12,
                                    displayColors: true,
                                    callbacks: {
                                        label: function (context) {
                                            return '₱' + context.parsed.x.toLocaleString(undefined, {
                                                minimumFractionDigits: 2,
                                                maximumFractionDigits: 2
                                            });
                                        }
                                    }
                                }
                            }
                        }
                    });
                }

                // Update the updateChartColors function to include bestSellersChart
                function updateChartColors() {
                    const colors = getThemeColors();

                    if (window.salesChart) {
                        // ... existing salesChart color updates ...
                    }

                    if (window.paymentChart) {
                        // ... existing paymentChart color updates ...
                    }

                    if (window.bestSellersChart) {
                        window.bestSellersChart.options.scales.x.grid.color = colors.grid;
                        window.bestSellersChart.options.scales.x.ticks.color = colors.text;
                        window.bestSellersChart.options.scales.y.ticks.color = colors.text;
                        window.bestSellersChart.options.scales.revenue.ticks.color = colors.text;
                        window.bestSellersChart.options.plugins.title.color = colors.text;
                        window.bestSellersChart.options.plugins.legend.labels.color = colors.text;
                        window.bestSellersChart.update();
                    }
                }
            }); // End of DOMContentLoaded

            // --- Global Functions (accessible outside DOMContentLoaded) ---

            function changePeriod(period) {
                const url = new URL(window.location.href);
                url.searchParams.set('period', period);
                // Preserve dark mode state
                const isDarkMode = document.body.classList.contains('dark-mode');
                url.searchParams.set('darkMode', isDarkMode ? '1' : '0');
                window.location.href = url.pathname + url.search + (window.location.hash || '#overview');
            }

            function viewOrderDetails(order) {
                const orderItems = JSON.parse(order.order_items || '[]');
                const paymentDetails = JSON.parse(order.payment_details || '{}');
                let itemsHtml = orderItems
                    .map(item => {
                        const productName =
                            item.product?.name ||
                            item.name ||
                            item.product_name ||
                            item.product ||
                            'Unknown';
                        const flavor = item.flavor || 'N/A';
                        const size = item.size?.name || item.size || 'N/A';
                        const quantity = item.quantity || 1;
                        const price = parseFloat(item.totalPrice || item.price || 0).toFixed(2);
                        const addOns =
                            (item.addOns || []).map(a => a.name || a).join(', ') || 'None';
                        return `<div class="border-bottom pb-2 mb-2"><h6>${productName}</h6><small>Flavor: ${flavor}, Size: ${size}, Qty: ${quantity}, Add-ons: ${addOns}</small><br><small class="fw-bold">Price: ₱${price}</small></div>`;
                    })
                    .join('');
                if (!itemsHtml) itemsHtml = '<p>No items found in this order.</p>';
                const content = `<div class="row"><div class="col-md-6"><h6>Order Info</h6><p><strong>ID:</strong> #${order.id}</p><p><strong>Customer:</strong> ${order.customer_name || 'N/A'}</p><p><strong>Cashier:</strong> ${order.cashier_name || 'N/A'} (${order.cashier_username || 'N/A'})</p><p><strong>Date:</strong> ${new Date(order.order_date).toLocaleString()}</p></div><div class="col-md-6"><h6>Payment Info</h6><p><strong>Method:</strong> ${order.payment_method.toUpperCase()}</p><p><strong>Total:</strong> ₱${parseFloat(order.total_amount).toFixed(2)}</p>${order.payment_method === 'cash' ?
                    `<p><strong>Cash:</strong> ₱${parseFloat(paymentDetails.cash_tendered || 0).toFixed(2)}</p><p><strong>Change:</strong> ₱${parseFloat(paymentDetails.change_given || 0).toFixed(2)}</p>` :
                    `<p><strong>Ref No:</strong> ${paymentDetails.transaction_no || 'N/A'}</p>`
                    }</div></div><hr><h6>Order Items</h6>${itemsHtml}`;
                document.getElementById('orderDetailsContent').innerHTML = content;
                new bootstrap.Modal(document.getElementById('orderDetailsModal')).show();
            }

            function clearTransactions() {
                if (confirm('Are you sure you want to clear ALL transactions? This cannot be undone.')) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'dashboard.php#transactions';
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'clear_transactions';
                    input.value = '1';
                    form.appendChild(input);
                    document.body.appendChild(form);
                    form.submit();
                }
            }

            function togglePasswordVisibility(inputId, iconId) {
                const passwordInput = document.getElementById(inputId);
                const passwordIcon = document.getElementById(iconId);
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    passwordIcon.src = '../images/password_show_icon.png';
                } else {
                    passwordInput.type = 'password';
                    passwordIcon.src = '../images/password_hide_icon.png';
                }
            }

            function downloadSummaryPdf() {
                try {
                    // Helper function to parse numeric values
                    function parseNumericValue(value) {
                        if (!value) return 0;
                        // Remove currency symbols and any other non-numeric characters except decimal point
                        const cleanValue = value.replace(/[^0-9.-]/g, '');
                        return parseFloat(cleanValue) || 0;
                    }

                    // Check if jsPDF is loaded
                    if (typeof window.jspdf === 'undefined') {
                        throw new Error('jsPDF library not found. Please check your internet connection and refresh the page.');
                    }

                    // Get the table first to validate data
                    const table = document.getElementById('summaryTable');
                    if (!table) {
                        throw new Error('Summary table not found. Please make sure you have data to export.');
                    }

                    // Get tbody and validate
                    const tbody = table.querySelector('tbody');
                    if (!tbody) {
                        throw new Error('Table body not found. Please make sure you have data to export.');
                    }

                    // Initialize jsPDF
                    const doc = new window.jspdf.jsPDF('portrait');

                    // Set title and date range
                    doc.setFontSize(18);
                    doc.setTextColor(0, 0, 0);
                    doc.text('Summary Report', 15, 15);

                    doc.setFontSize(12);
                    doc.setTextColor(0, 0, 0);
                    const startDate = document.querySelector('input[name="summary_start_date"]')?.value || '';
                    const endDate = document.querySelector('input[name="summary_end_date"]')?.value || '';
                    const formattedStartDate = startDate ? new Date(startDate).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '';
                    const formattedEndDate = endDate ? new Date(endDate).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '';
                    doc.text(`Period: ${formattedStartDate} to ${formattedEndDate}`, 15, 25);

                    // Safely get cell content
                    const getCellContent = (row, index) => {
                        try {
                            return row.cells[index]?.textContent?.trim() || '';
                        } catch (e) {
                            console.error(`Error getting cell content at index ${index}:`, e);
                            return '';
                        }
                    };

                    // Get all rows
                    const rows = Array.from(tbody.querySelectorAll('tr:not(:last-child)'));
                    if (rows.length === 0) {
                        throw new Error('No data available in the table to export.');
                    }

                    // Process regular rows
                    const summaryData = rows.map(row => {
                        const quantity = parseNumericValue(getCellContent(row, 3));
                        const unitPrice = parseNumericValue(getCellContent(row, 4));
                        const totalSales = parseNumericValue(getCellContent(row, 5));
                        const rawDate = getCellContent(row, 0) || 'N/A';
                        const formattedDate = rawDate !== 'N/A' ? new Date(rawDate).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 'N/A';

                        return [
                            formattedDate,  // Date
                            getCellContent(row, 1) || 'N/A',  // Product
                            getCellContent(row, 2) || 'N/A',  // Size
                            quantity.toString(),              // Quantity
                            `P ${unitPrice.toFixed(2)}`,      // Unit Price
                            `P ${totalSales.toFixed(2)}`,     // Total Sales
                            getCellContent(row, 6) || 'N/A',  // Cashier
                            getCellContent(row, 7) || 'N/A'   // Payment Method
                        ];
                    });

                    // Calculate totals
                    const totalQuantity = summaryData.reduce((sum, row) => sum + parseFloat(row[3] || 0), 0);
                    const totalSales = summaryData.reduce((sum, row) => sum + parseFloat(row[5].replace('P', '') || 0), 0);

                    const grandTotal = [
                        'Total Sales',
                        '',
                        '',
                        totalQuantity.toString(),           // Total Quantity
                        '-',                                // Unit Price shows "-"
                        `P ${totalSales.toFixed(2)}`,       // Total Sales
                        '',
                        ''
                    ];

                    // Generate PDF table
                    doc.autoTable({
                        head: [['Date', 'Product', 'Size', 'Quantity', 'Unit Price', 'Total Sales', 'Cashier', 'Payment']],
                        body: [...summaryData, grandTotal],
                        startY: 30,
                        theme: 'grid',
                        styles: {
                            fontSize: 8,
                            cellPadding: 2,
                            textColor: [0, 0, 0]
                        },
                        headStyles: {
                            fillColor: [0, 100, 0],
                            textColor: [255, 255, 255],
                            fontSize: 9,
                            fontStyle: 'bold',
                            halign: 'center'
                        },
                        columnStyles: {
                            0: { cellWidth: 20 }, // Date
                            1: { cellWidth: 30 }, // Product
                            2: { cellWidth: 15 }, // Size
                            3: { cellWidth: 25, halign: 'center' }, // Quantity
                            4: { cellWidth: 25, halign: 'center' }, // Unit Price
                            5: { cellWidth: 25, halign: 'center' }, // Total Sales
                            6: { cellWidth: 25 }, // Cashier
                            7: { cellWidth: 25, halign: 'center' } // Payment
                        },
                        alternateRowStyles: {
                            fillColor: [245, 245, 245]
                        },
                        footStyles: {
                            fillColor: [0, 0, 0],
                            textColor: [255, 255, 255],
                            fontStyle: 'bold'
                        }
                    });

                    // Add timestamp
                    const timestamp = new Date().toLocaleString();
                    doc.setFontSize(8);
                    doc.setTextColor(0, 0, 0);
                    doc.text(`Report during: ${timestamp}`, 15, doc.internal.pageSize.height - 10);
                    doc.save(`summary_report_${startDate}_to_${endDate}.pdf`);

                } catch (error) {
                    console.error('Error generating PDF:', error);
                    alert(`Error generating PDF: ${error.message}\nPlease try again or contact support if the issue persists.`);
                }
            }

            function clearSummaryData() {
                if (confirm('Are you sure you want to clear all summary data? This action cannot be undone.')) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'dashboard.php#summary';
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'clear_summary';
                    input.value = '1';
                    form.appendChild(input);
                    document.body.appendChild(form);
                    form.submit();
                }
            }

            // Add this function to populate the best sellers table
            function populateBestSellersTable(data) {
                const tableBody = document.getElementById('bestSellersTable');
                if (!Array.isArray(data) || data.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="3" class="text-center">No data available</td></tr>';
                    return;
                }

                // Sort data by revenue in descending order
                const sortedData = [...data].sort((a, b) => b.revenue - a.revenue);

                tableBody.innerHTML = sortedData.map(item => `
                    <tr>
                        <td>${item.name}</td>
                        <td class="text-end">₱${parseFloat(item.revenue).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                        <td class="text-end">${parseInt(item.quantity).toLocaleString()}</td>
                    </tr>
                `).join('');

                // Add a total row
                const totalRevenue = sortedData.reduce((sum, item) => sum + parseFloat(item.revenue), 0);
                const totalQuantity = sortedData.reduce((sum, item) => sum + parseInt(item.quantity), 0);

                tableBody.innerHTML += `
                    <tr class="table-success fw-bold">
                        <td>Total</td>
                        <td class="text-end">₱${totalRevenue.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                        <td class="text-end">${totalQuantity.toLocaleString()}</td>
                    </tr>
                `;
            }

            // Call this function when the data is available
            if (typeof bestSellersData !== 'undefined') {
                populateBestSellersTable(bestSellersData);
            }

            // Modify chart creation code
            function createSalesChart(data) {
                const isDarkMode = document.body.classList.contains('dark-mode');
                const textColor = isDarkMode ? '#ffffff' : '#666666';

                const ctx = document.getElementById('salesChart').getContext('2d');
                window.salesChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            label: 'Sales (₱)',
                            data: data.salesData,
                            borderColor: '#198754',
                            backgroundColor: 'rgba(25, 135, 84, 0.1)',
                            borderWidth: 2,
                            tension: 0.4,
                            fill: true
                        }, {
                            label: 'Orders',
                            data: data.ordersData,
                            borderColor: '#0d6efd',
                            backgroundColor: 'rgba(13, 110, 253, 0.1)',
                            borderWidth: 2,
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            x: {
                                grid: {
                                    color: isDarkMode ? '#333333' : '#ddd'
                                },
                                ticks: {
                                    color: textColor,
                                    font: {
                                        size: 12
                                    }
                                }
                            },
                            y: {
                                grid: {
                                    color: isDarkMode ? '#333333' : '#ddd'
                                },
                                ticks: {
                                    color: textColor,
                                    font: {
                                        size: 12
                                    }
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                labels: {
                                    color: textColor,
                                    font: {
                                        size: 14
                                    },
                                    usePointStyle: true,
                                    padding: 20
                                }
                            },
                            tooltip: {
                                backgroundColor: isDarkMode ? '#1e1e1e' : '#ffffff',
                                titleColor: textColor,
                                bodyColor: textColor,
                                borderColor: isDarkMode ? '#404040' : '#ddd',
                                borderWidth: 1,
                                padding: 12,
                                displayColors: true,
                                intersect: false,
                                mode: 'index'
                            }
                        }
                    }
                });
            }

            function createPaymentMethodsChart(data) {
                const isDarkMode = document.body.classList.contains('dark-mode');

                const ctx = document.getElementById('paymentMethodsChart').getContext('2d');
                window.paymentMethodsChart = new Chart(ctx, {
                    type: 'doughnut',
                    data: data,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    color: isDarkMode ? '#ffffff' : '#000000',
                                    font: {
                                        size: 14,
                                        weight: 'bold'
                                    },
                                    padding: 20,
                                    usePointStyle: true
                                }
                            },
                            tooltip: {
                                backgroundColor: isDarkMode ? '#1e1e1e' : '#ffffff',
                                titleColor: isDarkMode ? '#ffffff' : '#000000',
                                bodyColor: isDarkMode ? '#ffffff' : '#000000',
                                borderColor: isDarkMode ? '#404040' : '#ddd',
                                borderWidth: 1,
                                padding: 12
                            }
                        }
                    }
                });
            }

            // Update chart colors when theme changes
            document.getElementById('darkModeToggle').addEventListener('change', function () {
                const isDarkMode = this.checked;
                const chartColors = getChartColors();

                if (window.paymentMethodsChart) {
                    // Update legend labels color based on theme
                    paymentMethodsChart.options.plugins.legend.labels.color = isDarkMode ? '#ffffff' : '#000000';
                    // Update tooltip colors
                    paymentMethodsChart.options.plugins.tooltip.backgroundColor = isDarkMode ? '#1e1e1e' : '#ffffff';
                    paymentMethodsChart.options.plugins.tooltip.titleColor = isDarkMode ? '#ffffff' : '#000000';
                    paymentMethodsChart.options.plugins.tooltip.bodyColor = isDarkMode ? '#ffffff' : '#000000';
                    paymentMethodsChart.options.plugins.tooltip.borderColor = isDarkMode ? '#404040' : '#ddd';
                    paymentMethodsChart.update('none');
                }
            });

            // Make sure to update charts when page loads
            document.addEventListener('DOMContentLoaded', function () {
                const isDarkMode = document.body.classList.contains('dark-mode');
                const chartColors = getChartColors(); // Get initial colors based on current theme

                // Update all charts on page load
                [window.salesChart, window.paymentMethodsChart, window.bestSellersChart].forEach(chart => {
                    if (chart) {
                        if (chart.options.scales) {
                            Object.values(chart.options.scales).forEach(scale => {
                                if (scale.ticks) {
                                    scale.ticks.color = chartColors.textColor;
                                }
                                if (scale.grid) {
                                    scale.grid.color = chartColors.gridColor;
                                }
                            });
                        }
                        if (chart.options.plugins) {
                            if (chart.options.plugins.legend?.labels) {
                                chart.options.plugins.legend.labels.color = chartColors.textColor;
                            }
                            if (chart.options.plugins.title) {
                                chart.options.plugins.title.color = chartColors.textColor;
                            }
                            if (chart.options.plugins.tooltip) {
                                chart.options.plugins.tooltip.backgroundColor = chartColors.backgroundColor;
                                chart.options.plugins.tooltip.titleColor = chartColors.textColor;
                                chart.options.plugins.tooltip.bodyColor = chartColors.textColor;
                                chart.options.plugins.tooltip.borderColor = chartColors.borderColor;
                            }
                        }
                        // Update dataset colors for best sellers chart
                        if (chart === window.bestSellersChart) {
                            chart.data.datasets[0].backgroundColor = isDarkMode ? 'rgba(0, 150, 0, 0.8)' : 'rgba(0, 70, 0, 0.8)';
                            chart.data.datasets[0].borderColor = isDarkMode ? 'rgba(0, 200, 0, 1)' : 'rgba(0, 70, 0, 1)';
                        }
                        chart.update('none');
                    }
                });
            });

            function createBestSellersChart(data) {
                const isDarkMode = document.body.classList.contains('dark-mode');
                const textColor = isDarkMode ? '#ffffff' : '#666666';

                const ctx = document.getElementById('bestSellersChart').getContext('2d');
                window.bestSellersChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            label: 'Revenue (₱)',
                            data: data.revenue,
                            backgroundColor: isDarkMode ? 'rgba(0, 150, 0, 0.8)' : 'rgba(25, 135, 84, 0.8)',
                            borderColor: isDarkMode ? 'rgba(0, 200, 0, 1)' : '#198754',
                            borderWidth: 1,
                            color: textColor
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            x: {
                                grid: {
                                    color: isDarkMode ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)'
                                },
                                ticks: {
                                    color: textColor,
                                    font: {
                                        size: 12
                                    }
                                },
                                title: {
                                    display: true,
                                    text: 'Revenue (₱)',
                                    color: textColor,
                                    font: {
                                        size: 12,
                                        weight: 'bold'
                                    }
                                }
                            },
                            y: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    color: textColor,
                                    font: {
                                        size: 12
                                    }
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top',
                                labels: {
                                    color: textColor,
                                    font: {
                                        size: 14
                                    },
                                    usePointStyle: true,
                                    padding: 20
                                }
                            },
                            title: {
                                display: true,
                                text: 'Top Products by Revenue',
                                color: textColor,
                                font: {
                                    size: 16,
                                    weight: 'bold'
                                },
                                padding: {
                                    top: 10,
                                    bottom: 30
                                }
                            },
                            tooltip: {
                                backgroundColor: isDarkMode ? '#1e1e1e' : '#ffffff',
                                titleColor: textColor,
                                bodyColor: textColor,
                                borderColor: isDarkMode ? '#404040' : '#ddd',
                                borderWidth: 1,
                                padding: 12,
                                displayColors: true,
                                callbacks: {
                                    label: function (context) {
                                        return '₱' + context.parsed.x.toFixed(2);
                                    }
                                }
                            }
                        }
                    }
                });
            }

            // Add this to your theme change listener
            document.getElementById('darkModeToggle').addEventListener('change', function () {
                const isDarkMode = this.checked;
                const textColor = isDarkMode ? '#ffffff' : '#666666';

                // ... existing chart updates ...

                if (window.bestSellersChart) {
                    bestSellersChart.options.scales.x.ticks.color = textColor;
                    bestSellersChart.options.scales.y.ticks.color = textColor;
                    bestSellersChart.options.plugins.legend.labels.color = textColor;
                    bestSellersChart.options.plugins.title.color = textColor;
                    bestSellersChart.options.plugins.tooltip.backgroundColor = isDarkMode ? '#1e1e1e' : '#ffffff';
                    bestSellersChart.options.plugins.tooltip.titleColor = textColor;
                    bestSellersChart.options.plugins.tooltip.bodyColor = textColor;
                    bestSellersChart.options.plugins.tooltip.borderColor = isDarkMode ? '#404040' : '#ddd';
                    // Update dataset colors
                    bestSellersChart.data.datasets[0].backgroundColor = isDarkMode ? 'rgba(0, 150, 0, 0.8)' : 'rgba(0, 70, 0, 0.8)';
                    bestSellersChart.data.datasets[0].borderColor = isDarkMode ? 'rgba(0, 200, 0, 1)' : 'rgba(0, 70, 0, 1)';
                    bestSellersChart.update();
                }
            });

            // Make sure to call this on initial load
            function updateAllChartsTheme() {
                const isDarkMode = document.body.classList.contains('dark-mode');
                const textColor = isDarkMode ? '#ffffff' : '#666666';

                // Update all charts
                [window.salesChart, window.paymentMethodsChart, window.bestSellersChart].forEach(chart => {
                    if (chart) {
                        if (chart.options.scales) {
                            Object.values(chart.options.scales).forEach(scale => {
                                if (scale.ticks) {
                                    scale.ticks.color = textColor;
                                }
                                if (scale.grid) {
                                    scale.grid.color = isDarkMode ? '#333333' : '#ddd';
                                }
                            });
                        }
                        if (chart.options.plugins) {
                            if (chart.options.plugins.legend?.labels) {
                                chart.options.plugins.legend.labels.color = textColor;
                            }
                            if (chart.options.plugins.title) {
                                chart.options.plugins.title.color = textColor;
                            }
                            if (chart.options.plugins.tooltip) {
                                chart.options.plugins.tooltip.backgroundColor = isDarkMode ? '#1e1e1e' : '#ffffff';
                                chart.options.plugins.tooltip.titleColor = textColor;
                                chart.options.plugins.tooltip.bodyColor = textColor;
                                chart.options.plugins.tooltip.borderColor = isDarkMode ? '#404040' : '#ddd';
                            }
                        }
                        chart.update();
                    }
                });
            }

            // Call this when the page loads
            document.addEventListener('DOMContentLoaded', function () {
                updateAllChartsTheme();
            });

            document.addEventListener('DOMContentLoaded', function () {
                const darkModeToggle = document.getElementById('darkModeToggle');
                const body = document.body;

                // Function to update chart colors and styles
                function updateChartTheme(isDarkMode) {
                    const chartColors = {
                        textColor: isDarkMode ? '#e0e0e0' : '#666666',
                        gridColor: isDarkMode ? 'rgba(255, 255, 255, 0.15)' : 'rgba(0, 0, 0, 0.1)',
                        borderColor: isDarkMode ? '#404040' : '#ddd',
                        backgroundColor: isDarkMode ? '#1e1e1e' : '#ffffff',
                        greenColor: isDarkMode ? '#00cc00' : 'rgba(0, 70, 0, 0.8)',
                        greenBorder: isDarkMode ? '#00ff00' : 'rgba(0, 70, 0, 1)',
                        blueColor: isDarkMode ? '#3399ff' : 'rgba(59, 130, 246, 0.8)',
                        grayColor: isDarkMode ? 'rgba(255, 255, 255, 0.1)' : 'rgba(128, 128, 128, 0.1)',
                        labelColor: isDarkMode ? '#e0e0e0' : '#666666'
                    };

                    const charts = [
                        { chart: window.salesChart, type: 'sales' },
                        { chart: window.bestSellersChart, type: 'bestSellers' },
                        { chart: window.paymentMethodsChart, type: 'payment' }
                    ];

                    charts.forEach(({ chart, type }) => {
                        if (!chart) return;

                        // Update scales
                        if (chart.options.scales) {
                            Object.values(chart.options.scales).forEach(scale => {
                                if (scale.grid) scale.grid.color = chartColors.gridColor;
                                if (scale.ticks) scale.ticks.color = chartColors.textColor;
                            });
                        }

                        // Update plugins
                        if (chart.options.plugins) {
                            // Update legend
                            if (chart.options.plugins.legend?.labels) {
                                chart.options.plugins.legend.labels.color = chartColors.labelColor; // Always black for payment methods
                            }

                            // Update tooltip
                            if (chart.options.plugins.tooltip) {
                                chart.options.plugins.tooltip.backgroundColor = chartColors.backgroundColor;
                                chart.options.plugins.tooltip.titleColor = chartColors.textColor;
                                chart.options.plugins.tooltip.bodyColor = chartColors.textColor;
                                chart.options.plugins.tooltip.borderColor = chartColors.borderColor;
                            }
                        }

                        // Update specific chart types
                        if (type === 'sales') {
                            chart.data.datasets[0].borderColor = chartColors.greenColor;
                            chart.data.datasets[0].backgroundColor = chartColors.grayColor; // Use gray for stats area
                            chart.data.datasets[1].borderColor = chartColors.blueColor;
                            chart.data.datasets[1].backgroundColor = chartColors.grayColor; // Use gray for stats area
                        } else if (type === 'bestSellers') {
                            chart.data.datasets[0].backgroundColor = chartColors.greenColor;
                            chart.data.datasets[0].borderColor = chartColors.greenBorder;
                        }

                        chart.update('none');
                    });
                }

                // Function to handle theme change
                function handleThemeChange(isDarkMode) {
                    // Update body class
                    body.classList.toggle('dark-mode', isDarkMode);

                    // Update charts
                    updateChartTheme(isDarkMode);

                    // Update icons
                    updateTabIcons(isDarkMode);

                    // Save preference
                    localStorage.setItem('darkMode', isDarkMode ? 'enabled' : 'disabled');

                    // Update URL without reload
                    const url = new URL(window.location.href);
                    url.searchParams.set('darkMode', isDarkMode ? '1' : '0');
                    history.replaceState(null, '', url);
                }

                // Initialize theme
                const urlParams = new URLSearchParams(window.location.search);
                const isDarkMode = urlParams.get('darkMode') === '1' || localStorage.getItem('darkMode') === 'enabled';

                // Set initial state
                darkModeToggle.checked = isDarkMode;
                handleThemeChange(isDarkMode);

                // Handle toggle changes
                darkModeToggle.addEventListener('change', function () {
                    handleThemeChange(this.checked);
                });
            });

            // Add this function to generate Best Sellers PDF
            function downloadBestSellersPDF() {
                try {
                    const bestSellersData = <?php echo json_encode($bestSellers); ?>;
                    if (!bestSellersData || bestSellersData.length === 0) {
                        alert('No data available to generate PDF.');
                        return;
                    }

                    // Initialize jsPDF
                    const { jsPDF } = window.jspdf;
                    const doc = new jsPDF();

                    // Set title
                    doc.setFontSize(18);
                    doc.text('Best Selling Products Report', 15, 20);

                    // Add timestamp
                    doc.setFontSize(10);
                    const timestamp = new Date().toLocaleString();
                    doc.text(`Generated on: ${timestamp}`, 15, 30);

                    // Add table headers
                    doc.setFontSize(12);
                    doc.setTextColor(0, 102, 0); // Dark green color for headers
                    const headers = ['Product Name', 'Revenue (P)', 'Units Sold'];
                    let y = 40;
                    doc.text(headers[0], 15, y);
                    doc.text(headers[1], 110, y);
                    doc.text(headers[2], 160, y);

                    // Add horizontal line
                    y += 2;
                    doc.setDrawColor(0, 102, 0);
                    doc.line(15, y, 195, y);

                    // Reset text color to black
                    doc.setTextColor(0, 0, 0);

                    // Add data rows
                    y += 8;
                    let totalRevenue = 0;
                    let totalUnits = 0;

                    bestSellersData.forEach((item, index) => {
                        if (y > 270) { // Check if we need a new page
                            doc.addPage();
                            y = 20;
                        }

                        doc.text(item.name.substring(0, 50), 15, y); // Limit product name length
                        doc.text('P ' + parseFloat(item.revenue).toLocaleString(undefined, {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        }), 110, y, { align: 'left' });
                        doc.text(item.sales_count.toString(), 160, y, { align: 'left' });

                        totalRevenue += parseFloat(item.revenue);
                        totalUnits += parseInt(item.sales_count);
                        y += 10;
                    });

                    // Add horizontal line before totals
                    y += 2;
                    doc.setDrawColor(0, 102, 0);
                    doc.line(15, y, 195, y);
                    y += 8;

                    // Add totals
                    doc.setFont(undefined, 'bold');
                    doc.text('Total:', 15, y);
                    doc.text('P ' + totalRevenue.toLocaleString(undefined, {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    }), 110, y);
                    doc.text(totalUnits.toString(), 160, y);

                    // Add footer
                    doc.setFont(undefined, 'normal');
                    doc.setFontSize(8);
                    doc.text('© Transaction System - Best Sellers Report', 15, doc.internal.pageSize.height - 10);

                    // Save the PDF
                    doc.save('best_sellers_report.pdf');

                } catch (error) {
                    console.error('Error generating PDF:', error);
                    alert('Error generating PDF. Please try again.');
                }
            }

            function showLogoutConfirmation() {
                const logoutModal = new bootstrap.Modal(document.getElementById('logoutConfirmModal'));
                logoutModal.show();
            }
        </script>
</body>

</html>