<?php
session_start();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'user') {
    header('Location: ../login.php');
    exit();
}

require_once '../includes/db_connect.php';

// Get products
$stmt = $pdo->prepare("SELECT * FROM products ORDER BY name");
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Process JSON fields for sizes and add_ons, keep flavors as string
foreach ($products as &$product) {
    $product['sizes'] = json_decode($product['sizes'], true);
    $product['add_ons'] = json_decode($product['add_ons'], true);
    // flavors is already a string, no need to decode
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" href="../images/Logo.png" type="image/png">
    <style>
        body {
            background-color: #f8f9fa;
        }

        .product-card {
            transition: transform 0.2s;
            cursor: pointer;
        }

        .product-card:hover {
            transform: translateY(-5px);
        }

        .cart-item {
            border-left: 4px solid #004600;
        }

        .navbar {
            background: linear-gradient(135deg, #004600 0%, #005C00 100%);
        }

        .btn-primary {
            background-color: #004600;
            border-color: #004600;
        }

        .btn-primary:hover {
            background-color: #005C00;
            border-color: #005C00;
        }

        body.dark-mode {
            background-color: #343a40;
            color: #fff;
        }

        .dark-mode .card {
            background-color: #343a40;
            color: #fff;
            border: 1px solid #6c757d;
        }

        .dark-mode .card-header {
            background-color: #23272b;
            color: #fff;
        }

        .dark-mode .cart-item {
            border-left: 4px solid #fff;
            background-color: #495057 !important;
        }

        .dark-mode .btn-outline-light {
            border-color: #fff;
            color: #fff;
        }

        .dark-mode .btn-outline-light:hover {
            background-color: #fff;
            color: #23272b;
        }

        .dark-mode .form-select {
            background-color: #495057;
            color: #fff;
            border-color: #6c757d;
        }

        .dark-mode .form-control {
            background-color: #495057;
            color: #fff;
            border-color: #6c757d;
        }

        .dark-mode .modal-content {
            background-color: #343a40;
            color: #fff;
        }

        .dark-mode .modal-header {
            border-bottom: 1px solid #6c757d;
        }

        .dark-mode .modal-footer {
            border-top: 1px solid #6c757d;
        }

        .dark-mode .text-muted {
            color: #adb5bd !important;
        }

        .dark-mode .product-card {
            background-color: #495057;
        }

        .dark-mode .product-card:hover {
            background-color: #6c757d;
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-dark">
        <div class="container">
            <span class="navbar-brand">
                <img src="../images/Logo.png" alt="Logo" class="mb-3" style="width: 50px; height: 50px;"> Order System
            </span>
            <div class="d-flex align-items-center">
                <span class="text-white me-3">Welcome, <?php echo $_SESSION['username']; ?></span>
                <div class="form-check form-switch me-3">
                    <input class="form-check-input" type="checkbox" id="darkModeToggle">
                    <label class="form-check-label text-white" for="darkModeToggle"><img src="../images/sun.png" alt="Theme Toggle" width="30" height="30" id="themeIcon"></label>
                </div>
                <button class="btn btn-outline-light me-2" onclick="showCart()">
                    <i class="fas fa-shopping-cart me-1"></i>Cart (<span id="cart-count">0</span>)
                </button>
                <a href="../logout.php" class="btn btn-outline-light">
                    <img src="../images/Logout.png" width="20" height="20" class="me-1">Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-lg-8">
                <h2 class="mb-4">Menu</h2>
                <div class="mb-4">
                    <select class="form-select" id="flavor-filter">
                        <option value="">All Flavors</option>
                    </select>
                </div>
                <div class="row" id="product-list">
                    <!-- Products will be populated here -->
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card sticky-top">
                    <div class="card-header">
                        <h5 class="mb-0">Current Order</h5>
                    </div>
                    <div class="card-body">
                        <div id="cart-items">
                            <p class="text-muted text-center py-4">No items in cart</p>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between">
                            <strong>Total: ₱<span id="cart-total">0.00</span></strong>
                        </div>
                        <button class="btn btn-success w-100 mt-3" onclick="proceedToPayment()" disabled
                            id="checkout-btn">
                            Proceed to Payment
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Product Customization Modal -->
    <div class="modal fade" id="productModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="productModalTitle">Customize Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-12 text-center">
                            <img id="productModalImage" src="" alt="Product Image" class="img-fluid rounded"
                                style="max-height: 200px; object-fit: contain; display: none;">
                        </div>
                    </div>
                    <form id="customizeForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Flavor</label>
                                    <select class="form-select" id="flavor" required>
                                        <option value="">Select flavor</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Size</label>
                                    <div id="size-options"></div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Sugar Level</label>
                                    <select class="form-select" id="sugar-level">
                                        <option value="0%">0%</option>
                                        <option value="25%">25%</option>
                                        <option value="50%" selected>50%</option>
                                        <option value="75%">75%</option>
                                        <option value="100%">100%</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Add-ons</label>
                                    <div id="addon-options"></div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Quantity</label>
                                    <div class="input-group">
                                        <button class="btn btn-outline-secondary" type="button"
                                            onclick="changeQuantity(-1)">-</button>
                                        <input type="number" class="form-control text-center" id="quantity" value="1"
                                            min="1">
                                        <button class="btn btn-outline-secondary" type="button"
                                            onclick="changeQuantity(1)">+</button>
                                    </div>
                                </div>

                                <div class="alert alert-info">
                                    <strong>Total: ₱<span id="item-total">0.00</span></strong>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="addToCart()">Add to Cart</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Order Summary</h6>
                            <div id="payment-summary"></div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Customer Name (Optional)</label>
                                <input type="text" class="form-control" id="customer-name"
                                    placeholder="Enter customer name">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Payment Method</label>
                                <div class="row">
                                    <div class="col-6">
                                        <button class="btn btn-outline-primary w-100 h-100"
                                            onclick="selectPaymentMethod('cash')">
                                            <img src="../images/Cash.png" width="60" height="50" class="me-1">Cash
                                        </button>
                                    </div>
                                    <div class="col-6">
                                        <button class="btn btn-outline-primary w-100 h-100"
                                            onclick="selectPaymentMethod('gcash')">
                                            <img src="../images/GCash.png" width="40" height="40" class="me-1">GCash
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div id="payment-details"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" onclick="processPayment()" id="process-payment-btn"
                        disabled>
                        Complete Payment
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let cart = [];
        let currentProduct = null;
        let selectedPaymentMethod = null;

        function selectProduct(product) {
            try {
                console.log("selectProduct called with product:", product);
                
                if (!product) {
                    console.error("Product data is null or undefined");
                    alert("Error: Unable to load product details. Please try again.");
                    return;
                }

                if (!product.name) {
                    console.error("Product name is missing:", product);
                    alert("Error: Product details are incomplete. Please try again.");
                    return;
                }

                currentProduct = product;
                
                // Set modal title and show product image
                document.getElementById('productModalTitle').textContent = product.name;
                const modalImageElement = document.getElementById('productModalImage');
                if (product.image) {
                    modalImageElement.src = `../${product.image}`;
                    modalImageElement.alt = product.name;
                    modalImageElement.style.display = 'block';
                } else {
                    modalImageElement.src = `https://via.placeholder.com/300x200?text=${encodeURIComponent(product.name)}`;
                    modalImageElement.alt = product.name;
                    modalImageElement.style.display = 'block';
                }

                // Clear previous options
                document.getElementById('flavor').innerHTML = '<option value="">Loading...</option>';
                document.getElementById('size-options').innerHTML = '';
                document.getElementById('addon-options').innerHTML = '';

                // Show the modal
                const modalElement = document.getElementById('productModal');
                if (!modalElement) {
                    console.error("Modal element not found");
                    return;
                }
                const productModalInstance = bootstrap.Modal.getOrCreateInstance(modalElement);
                productModalInstance.show();

                // Get the currently selected flavor from the filter
                const selectedFilterFlavor = document.getElementById('flavor-filter').value;

                // Set up flavors
                const flavorSelect = document.getElementById('flavor');
                flavorSelect.innerHTML = '';
                if (typeof product.flavors === 'string' && product.flavors.trim() !== '') {
                    const flavorsArray = product.flavors.split(',').map(f => f.trim()).filter(f => f);
                    if (flavorsArray.length > 0) {
                        flavorSelect.innerHTML = '<option value="">Select flavor</option>';
                        flavorsArray.forEach(flavor => {
                            const isSelected = selectedFilterFlavor && flavor === selectedFilterFlavor;
                            flavorSelect.innerHTML += `<option value="${flavor}" ${isSelected ? 'selected' : ''}>${flavor}</option>`;
                        });
                        
                        // If no flavor is selected in filter but there's only one flavor, select it by default
                        if (!selectedFilterFlavor && flavorsArray.length === 1) {
                            flavorSelect.value = flavorsArray[0];
                        }
                    }
                }

                // Set up sizes
                const sizeOptions = document.getElementById('size-options');
                if (product.sizes && Array.isArray(product.sizes)) {
                    product.sizes.forEach((size, index) => {
                        sizeOptions.innerHTML += `
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="size" 
                                    id="size-${index}" value="${size.name}" 
                                    data-price="${size.price}" ${index === 0 ? 'checked' : ''}>
                                <label class="form-check-label" for="size-${index}">
                                    ${size.name} - ₱${parseFloat(size.price).toFixed(2)}
                                </label>
                            </div>`;
                    });
                }

                // Set up add-ons
                const addonOptions = document.getElementById('addon-options');
                if (product.add_ons && Array.isArray(product.add_ons)) {
                    product.add_ons.forEach((addon, index) => {
                        addonOptions.innerHTML += `
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" 
                                    id="addon-${index}" value="${addon.name}" 
                                    data-price="${addon.price}">
                                <label class="form-check-label" for="addon-${index}">
                                    ${addon.name} (+₱${parseFloat(addon.price).toFixed(2)})
                                </label>
                            </div>`;
                    });
                }

                // Reset quantity and update total
                document.getElementById('quantity').value = 1;
                document.getElementById('sugar-level').value = '50%';
                updateItemTotal();

            } catch (error) {
                console.error("Error in selectProduct:", error);
                alert("An error occurred while preparing the product options. Please try again.");
            }
        }

        function changeQuantity(change) {
            const quantityInput = document.getElementById('quantity');
            const newValue = parseInt(quantityInput.value) + change;
            if (newValue >= 1) {
                quantityInput.value = newValue;
                updateItemTotal();
            }
        }

        function updateItemTotal() {
            if (!currentProduct) {
                document.getElementById('item-total').textContent = '0.00';
                return;
            }
            const selectedSize = document.querySelector('input[name="size"]:checked');
            let basePrice = 0;
            if (selectedSize) {
                basePrice = parseFloat(selectedSize.dataset.price);
            }
            let addonPrice = 0;
            document.querySelectorAll('#addon-options input[type="checkbox"]:checked').forEach(addon => {
                addonPrice += parseFloat(addon.dataset.price);
            });
            const quantity = parseInt(document.getElementById('quantity').value) || 1;
            const total = (basePrice + addonPrice) * quantity;
            document.getElementById('item-total').textContent = total.toFixed(2);
        }

        function addToCart() {
            const flavor = document.getElementById('flavor').value;
            const selectedSize = document.querySelector('input[name="size"]:checked');
            const sugarLevel = document.getElementById('sugar-level').value;
            const quantity = parseInt(document.getElementById('quantity').value);

            if (!flavor || flavor === "") {
                alert('Please select a flavor.');
                return;
            }
            if (!selectedSize) {
                alert('Please select a size.');
                return;
            }

            const addOns = [];
            document.querySelectorAll('#addon-options input[type="checkbox"]:checked').forEach(addon => {
                addOns.push({
                    name: addon.value,
                    price: parseFloat(addon.dataset.price)
                });
            });

            const basePrice = parseFloat(selectedSize.dataset.price);
            const addonPriceTotal = addOns.reduce((sum, addon) => sum + addon.price, 0);
            const totalPrice = (basePrice + addonPriceTotal) * quantity;

            const cartItem = {
                id: Date.now(),
                product: currentProduct, // Keep full product data if needed by order.php
                productName: currentProduct.name,
                image: currentProduct.image, // Include image path for order.php
                flavor: flavor,
                size: {
                    name: selectedSize.value,
                    price: basePrice
                },
                addOns: addOns,
                sugarLevel: sugarLevel,
                quantity: quantity,
                totalPrice: totalPrice
            };

            cart.push(cartItem);
            updateCartDisplay(); // Update the cart display on the current page briefly

            // ***** ADDED: Save cart to localStorage and redirect *****
            try {
                localStorage.setItem('orderSystemCart', JSON.stringify(cart));
                console.log("Cart saved to localStorage:", cart);
            } catch (e) {
                console.error("Error saving cart to localStorage:", e);
                alert("There was an issue preparing your order. Please try again.");
                return; // Stop if localStorage fails
            }

            const productModalInstance = bootstrap.Modal.getInstance(document.getElementById('productModal'));
            if (productModalInstance) {
                productModalInstance.hide();
            }

            window.location.href = 'order.php'; // Redirect to order page
            // ***** END: Save cart and redirect *****
        }

        function updateCartDisplay() {
            const cartItemsEl = document.getElementById('cart-items');
            const cartCountEl = document.getElementById('cart-count');
            const cartTotalEl = document.getElementById('cart-total');
            const checkoutBtn = document.getElementById('checkout-btn');

            if (cart.length === 0) {
                cartItemsEl.innerHTML = '<p class="text-muted text-center py-4">No items in cart</p>';
                checkoutBtn.disabled = true;
            } else {
                let html = '';
                cart.forEach((item, index) => {
                    html += `
                    <div class="cart-item p-3 mb-2 bg-light rounded">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <h6 class="mb-1">${item.productName}</h6>
                                <small class="text-muted">
                                    ${item.flavor} • ${item.size.name} • ${item.sugarLevel} sugar<br>
                                    ${item.addOns.length > 0 ? '+ ' + item.addOns.map(a => a.name).join(', ') + '<br>' : ''}
                                    Qty: ${item.quantity}
                                </small>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold">₱${item.totalPrice.toFixed(2)}</div>
                                <button class="btn btn-sm btn-outline-danger" onclick="removeFromCart(${index})">
                                <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>`;
                });
                cartItemsEl.innerHTML = html;
                checkoutBtn.disabled = false;
            }
            cartCountEl.textContent = cart.length;
            const total = cart.reduce((sum, item) => sum + item.totalPrice, 0);
            cartTotalEl.textContent = total.toFixed(2);
        }

        function removeFromCart(index) {
            cart.splice(index, 1);
            updateCartDisplay();
            // Also update localStorage if cart is modified on this page after initial load
            try {
                localStorage.setItem('orderSystemCart', JSON.stringify(cart));
            } catch (e) {
                console.error("Error updating cart in localStorage after removal:", e);
            }
        }

        // This function now opens the payment modal on the CURRENT page.
        // If order.php becomes the sole checkout, this function might also redirect.
        function proceedToPayment() {
            if (cart.length === 0) {
                alert("Your cart is empty. Add some items first!");
                return;
            }
            // Option 1: Keep payment modal on this page (current behavior)
            let summaryHtml = '';
            cart.forEach(item => {
                summaryHtml += `
                <div class="d-flex justify-content-between mb-2">
                    <div>
                        <div class="fw-bold">${item.productName}</div>
                        <small class="text-muted">
                            ${item.flavor} • ${item.size.name} • ${item.sugarLevel}<br>
                            ${item.addOns.length > 0 ? '+ ' + item.addOns.map(a => a.name).join(', ') + '<br>' : ''}
                            Qty: ${item.quantity}
                        </small>
                    </div>
                    <div class="fw-bold">₱${item.totalPrice.toFixed(2)}</div>
                </div>`;
            });
            const total = cart.reduce((sum, item) => sum + item.totalPrice, 0);
            summaryHtml += `<hr><div class="d-flex justify-content-between"><strong>Total: ₱${total.toFixed(2)}</strong></div>`;
            document.getElementById('payment-summary').innerHTML = summaryHtml;
            selectedPaymentMethod = null;
            document.getElementById('payment-details').innerHTML = '';
            document.querySelectorAll('#paymentModal .btn-outline-primary').forEach(btn => btn.classList.remove('active'));
            document.getElementById('process-payment-btn').disabled = true;
            document.getElementById('customer-name').value = '';
            const paymentModalInstance = bootstrap.Modal.getOrCreateInstance(document.getElementById('paymentModal'));
            paymentModalInstance.show();

            // Option 2: Redirect to order.php (if you want to unify checkout)
            // try {
            //     localStorage.setItem('orderSystemCart', JSON.stringify(cart));
            // } catch (e) {
            //     console.error("Error saving cart to localStorage for order.php:", e);
            //     alert("There was an issue proceeding to checkout. Please try again.");
            //     return;
            // }
            // window.location.href = 'order.php';
        }

        function selectPaymentMethod(method) {
            selectedPaymentMethod = method;
            const paymentButtons = document.querySelectorAll('#paymentModal .btn-outline-primary');
            paymentButtons.forEach(btn => btn.classList.remove('active'));
            event.currentTarget.classList.add('active');
            const paymentDetailsDiv = document.getElementById('payment-details');
            const processPaymentBtn = document.getElementById('process-payment-btn');
            const totalAmount = cart.reduce((sum, item) => sum + item.totalPrice, 0);
            paymentDetailsDiv.innerHTML = '';
            processPaymentBtn.disabled = true;
            if (method === 'cash') {
                paymentDetailsDiv.innerHTML = `
                <div class="mb-3">
                    <label class="form-label">Cash Amount</label>
                    <input type="number" class="form-control" id="cash-amount" placeholder="Enter cash amount" min="${totalAmount.toFixed(2)}" step="0.01">
                    <div id="change-display" class="mt-2"></div>
                </div>`;
                const cashAmountInput = document.getElementById('cash-amount');
                cashAmountInput.addEventListener('input', function () {
                    const cashGiven = parseFloat(this.value) || 0;
                    const changeDisplay = document.getElementById('change-display');
                    if (cashGiven >= totalAmount) {
                        const change = cashGiven - totalAmount;
                        changeDisplay.innerHTML = `<div class="alert alert-success mb-0">Change: ₱${change.toFixed(2)}</div>`;
                        processPaymentBtn.disabled = false;
                    } else {
                        changeDisplay.innerHTML = `<div class="alert alert-warning mb-0">Amount is less than total.</div>`;
                        processPaymentBtn.disabled = true;
                    }
                });
            } else if (method === 'gcash') {
                paymentDetailsDiv.innerHTML = `
                <div class="mb-3">
                    <label class="form-label">GCash Transaction Number</label>
                    <input type="text" class="form-control" id="gcash-transaction" placeholder="Enter 8-digit transaction number" maxlength="8">
                    <div class="invalid-feedback">Transaction number must be 8 digits.</div>
                </div>`;
                const gcashTransactionInput = document.getElementById('gcash-transaction');
                gcashTransactionInput.addEventListener('input', function () {
                    const value = this.value.trim();
                    const isValid = /^[0-9]{8}$/.test(value);
                    this.value = this.value.replace(/[^0-9]/g, '');
                    if (value.length > 8) {
                        this.value = value.slice(0, 8);
                    }
                    processPaymentBtn.disabled = !isValid;
                    this.classList.toggle('is-invalid', value !== '' && !isValid);
                });
            }
        }

        function processPayment() {
            const customerName = document.getElementById('customer-name').value.trim() || 'Walk-in Customer';
            const totalAmount = cart.reduce((sum, item) => sum + item.totalPrice, 0);
            let paymentData = {
                method: selectedPaymentMethod
            };
            if (selectedPaymentMethod === 'cash') {
                const cashAmountInput = document.getElementById('cash-amount');
                const cashAmount = parseFloat(cashAmountInput.value);
                if (isNaN(cashAmount) || cashAmount < totalAmount) {
                    alert('Invalid cash amount.');
                    cashAmountInput.focus();
                    return;
                }
                paymentData.cash_tendered = cashAmount;
                paymentData.change_given = cashAmount - totalAmount;
            } else if (selectedPaymentMethod === 'gcash') {
                const gcashTransactionInput = document.getElementById('gcash-transaction');
                const transactionNo = gcashTransactionInput.value.trim();
                if (!/^[0-9]{8}$/.test(transactionNo)) {
                    alert('Please enter a valid 8-digit GCash transaction number.');
                    gcashTransactionInput.focus();
                    return;
                }
                paymentData.transaction_no = transactionNo;
            } else {
                alert('Please select a payment method.');
                return;
            }
            const orderPayload = {
                customer_name: customerName,
                order_items: cart.map(item => ({
                    product: {
                        name: item.productName,
                        id: item.product.id
                    }, // Ensure product is an object with name
                    flavor: item.flavor,
                    size: item.size, // Already an object with name and price
                    add_ons: item.addOns,
                    sugar_level: item.sugarLevel,
                    quantity: item.quantity,
                    totalPrice: item.totalPrice // Match dashboard.php's expected key
                })),
                total_amount: totalAmount,
                payment_method: selectedPaymentMethod,
                payment_details: paymentData
            };
            document.getElementById('process-payment-btn').disabled = true;
            fetch('process_order.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(orderPayload)
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showReceipt(data.order_id, customerName, cart, totalAmount, selectedPaymentMethod, paymentData);
                    } else {
                        alert('Error processing order: ' + (data.message || 'Unknown error'));
                        document.getElementById('process-payment-btn').disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while processing the payment.');
                    document.getElementById('process-payment-btn').disabled = false;
                });
        }

        function showReceipt(orderId, customerName, orderItems, total, paymentMethod, paymentDetails) {
            let receiptHtml = `
            <div class="text-center mb-4"><h3>RECEIPT</h3><p>Transaction System</p><small>${new Date().toLocaleString()}</small></div>
            <div class="mb-3"><strong>Order ID:</strong> ${orderId}<br><strong>Customer:</strong> ${customerName}<br><strong>Payment Method:</strong> ${paymentMethod.toUpperCase()}
            ${paymentMethod === 'gcash' && paymentDetails.transaction_no ? '<br><strong>Transaction No:</strong> ' + paymentDetails.transaction_no : ''}</div><hr><div class="mb-3">`;
            orderItems.forEach(item => {
                receiptHtml += `
                <div class="d-flex justify-content-between mb-1"><div><div>${item.quantity}x ${item.productName} (${item.size.name})</div>
                <small class="text-muted" style="font-size: 0.8em;">${item.flavor} • ${item.sugarLevel}${item.addOns.length > 0 ? ' • Add-ons: ' + item.addOns.map(a => a.name).join(', ') : ''}</small>
                </div><div>₱${item.totalPrice.toFixed(2)}</div></div>`;
            });
            receiptHtml += `</div><hr><div class="mb-3"><div class="d-flex justify-content-between"><strong>TOTAL:</strong> <strong>₱${total.toFixed(2)}</strong></div>
            ${paymentMethod === 'cash' ? `<div class="d-flex justify-content-between">Cash Tendered: ₱${paymentDetails.cash_tendered.toFixed(2)}</div><div class="d-flex justify-content-between">Change: ₱${paymentDetails.change_given.toFixed(2)}</div>` : ''}
            </div><div class="text-center mt-3"><small>Thank you for your purchase!</small></div>
            <div class="mt-4 text-center d-print-none"><button class="btn btn-primary me-2" onclick="printReceiptContent()"><i class="fas fa-print me-1"></i>Print Receipt</button><button class="btn btn-success" onclick="newOrder()">New Order</button></div>`;
            const paymentModalBody = document.getElementById('paymentModal').querySelector('.modal-body');
            const paymentModalHeader = document.getElementById('paymentModal').querySelector('.modal-header h5');
            const paymentModalFooter = document.getElementById('paymentModal').querySelector('.modal-footer');
            paymentModalHeader.textContent = 'Receipt';
            paymentModalBody.innerHTML = receiptHtml;
            paymentModalFooter.style.display = 'none';
        }

        function printReceiptContent() {
            const receiptContent = document.getElementById('paymentModal').querySelector('.modal-body').innerHTML;
            const printWindow = window.open('', '_blank', 'height=600,width=800');
            printWindow.document.write('<html><head><title>Print Receipt</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"><style>body{margin:20px;font-family:sans-serif;}.d-print-none{display:none!important;}hr{margin:0.5rem 0;}small{font-size:0.85em;}h3{margin-bottom:0.25rem;}</style></head><body>');
            printWindow.document.write(receiptContent);
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.focus();
            setTimeout(() => {
                printWindow.print();
                printWindow.close();
            }, 250);
        }

        function newOrder() {
            cart = [];
            selectedPaymentMethod = null;
            updateCartDisplay();
            const paymentModalInstance = bootstrap.Modal.getInstance(document.getElementById('paymentModal'));
            if (paymentModalInstance) {
                const modalElement = document.getElementById('paymentModal');
                if (modalElement.classList.contains('show')) {
                    paymentModalInstance.hide();
                }
            }
            document.getElementById('paymentModal').querySelector('.modal-header h5').textContent = 'Payment';
            document.getElementById('paymentModal').querySelector('.modal-footer').style.display = 'flex';
            document.getElementById('process-payment-btn').disabled = true;
            document.getElementById('payment-summary').innerHTML = '';
            document.getElementById('payment-details').innerHTML = '';
            // Clear cart from localStorage to ensure it's truly a new order
            localStorage.removeItem('orderSystemCart');
        }

        document.addEventListener('DOMContentLoaded', () => {
            // Attempt to load cart from localStorage if user navigates back or reloads
            try {
                const savedCart = localStorage.getItem('orderSystemCart');
                if (savedCart) {
                    cart = JSON.parse(savedCart);
                    updateCartDisplay();
                }
            } catch (e) {
                console.error("Error loading cart from localStorage:", e);
                localStorage.removeItem('orderSystemCart'); // Clear corrupted data
            }

            document.addEventListener('change', function (e) {
                const target = e.target;
                if (target.name === 'size' || (target.type === 'checkbox' && target.closest('#addon-options')) || target.id === 'flavor' || target.id === 'sugar-level') {
                    updateItemTotal();
                }
            });
            document.getElementById('quantity').addEventListener('input', updateItemTotal);
        });

        function displayProducts(productsData, selectedFlavor = '') {
            // Get unique flavors for the filter dropdown
            const uniqueFlavors = new Set();
            productsData.forEach(product => {
                if (product.flavors) {
                    product.flavors.split(',').forEach(flavor => {
                        uniqueFlavors.add(flavor.trim());
                    });
                }
            });

            // Update flavor filter options
            const flavorFilter = document.getElementById('flavor-filter');
            flavorFilter.innerHTML = '<option value="">All Flavors</option>';
            [...uniqueFlavors].sort().forEach(flavor => {
                flavorFilter.innerHTML += `<option value="${flavor}">${flavor}</option>`;
            });
            flavorFilter.value = selectedFlavor;

            let productHtml = '';
            if (productsData && productsData.length > 0) {
                const filteredProducts = selectedFlavor
                    ? productsData.filter(product => product.flavors && product.flavors.split(',').map(f => f.trim()).includes(selectedFlavor))
                    : productsData;

                if (filteredProducts.length === 0) {
                    productHtml = '<div class="col-12"><p class="text-center text-muted">No products available with the selected flavor.</p></div>';
                } else {
                    filteredProducts.forEach(product => {
                        // Ensure all necessary product data is properly formatted
                        const safeProduct = {
                            id: product.id,
                            name: product.name,
                            image: product.image,
                            sizes: Array.isArray(product.sizes) ? product.sizes : [],
                            flavors: typeof product.flavors === 'string' ? product.flavors : '',
                            add_ons: Array.isArray(product.add_ons) ? product.add_ons : []
                        };

                        const imageSrc = safeProduct.image ? `../${safeProduct.image}` : `https://via.placeholder.com/300x200?text=${encodeURIComponent(safeProduct.name)}`;
                        const startingPrice = (safeProduct.sizes && safeProduct.sizes[0] && safeProduct.sizes[0].price) 
                            ? parseFloat(safeProduct.sizes[0].price).toFixed(2) 
                            : 'N/A';
                        
                        // Properly escape the JSON for HTML attributes
                        const safeProductJSON = JSON.stringify(safeProduct)
                            .replace(/&/g, '&amp;')
                            .replace(/"/g, '&quot;')
                            .replace(/'/g, '&#39;')
                            .replace(/</g, '&lt;')
                            .replace(/>/g, '&gt;');

                        productHtml += `
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card product-card h-100 shadow-sm">
                                <img src="${imageSrc}" class="card-img-top" alt="${safeProduct.name}" style="height: 200px; object-fit: cover;">
                                <div class="card-body d-flex flex-column">
                                    <h5 class="card-title">${safeProduct.name}</h5>
                                    <p class="card-text text-muted">Starting at ₱${startingPrice}</p>
                                    <button class="btn btn-primary mt-auto" onclick="selectProduct(JSON.parse('${safeProductJSON}'))">Customize Order</button>
                                </div>
                            </div>
                        </div>`;
                    });
                }
            } else {
                productHtml = '<div class="col-12"><p class="text-center text-muted">No products available at the moment.</p></div>';
            }
            document.getElementById('product-list').innerHTML = productHtml;
        }

        const productsData = <?php echo json_encode($products, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        displayProducts(productsData);

        // Add event listener for flavor filter
        document.getElementById('flavor-filter').addEventListener('change', function () {
            displayProducts(productsData, this.value);
        });

        function showCart() {
            const cartElement = document.querySelector('.col-lg-4 .card.sticky-top');
            if (cartElement) {
                cartElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        }
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const darkModeToggle = document.getElementById('darkModeToggle');
            const body = document.body;
            const themeIcon = document.getElementById('themeIcon');

            // Function to update theme icon
            const updateThemeIcon = (isDark) => {
                themeIcon.src = isDark ? '../images/moon.png' : '../images/sun.png';
            };

            // Check if dark mode is enabled in localStorage
            const isDarkMode = localStorage.getItem('darkMode') === 'enabled';
            if (isDarkMode) {
                body.classList.add('dark-mode');
                darkModeToggle.checked = true;
                updateThemeIcon(true);
            }

            // Toggle dark mode
            darkModeToggle.addEventListener('change', () => {
                body.classList.toggle('dark-mode');
                const isDark = body.classList.contains('dark-mode');
                // Update icon
                updateThemeIcon(isDark);
                // Save dark mode state to localStorage
                localStorage.setItem('darkMode', isDark ? 'enabled' : 'disabled');
            });
        });
    </script>
</body>

</html>