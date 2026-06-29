<?php
session_start();
require_once '../includes/dbcon.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$userId = null;
$userFname = '';
$userRole = '';
$userPhone = '';
$userAddress = '';
$userShippingAddress = '';

if ($isLoggedIn && isset($_SESSION['user_id'])) {
    try {
        $pdo = getPDOConnection();
        
        // Get user details
        $stmt = $pdo->prepare("SELECT fName, lName, email, role, regID, contactNo FROM reg WHERE regID = ? AND cStatus = 1");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        if ($user) {
            $userId = $user['regID'];
            $userFname = $user['fName'];
            $userRole = $user['role'];
            $userPhone = $user['contactNo'] ?? '';
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['role'] = $user['role'];
        }
        
        // Get user profile for shipping details
        $stmt = $pdo->prepare("SELECT phone, address, shipping_address FROM user_profiles WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $profile = $stmt->fetch();
        if ($profile) {
            $userPhone = $profile['phone'] ?: $userPhone;
            $userAddress = $profile['address'] ?? '';
            $userShippingAddress = $profile['shipping_address'] ?? '';
        }
        
    } catch (Exception $e) {
        error_log("User profile fetch error: " . $e->getMessage());
    }
}

// Fetch products from database with error handling
$products = [];
$productMap = []; // For quick lookup by ID
try {
    $pdo = getPDOConnection();
    $stmt = $pdo->prepare("SELECT id, name, description, price, category, rating, image_url as image, offer_tag as offerTag, stock_quantity FROM products WHERE status = 'active' ORDER BY category, name");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create a map for quick product lookup by ID
    foreach ($products as $product) {
        $productMap[$product['id']] = $product;
    }
} catch (Exception $e) {
    error_log("Product fetch error: " . $e->getMessage());
    $products = [];
}

// Get product categories
$categories = array_unique(array_column($products, 'category'));
sort($categories);

function getDashboardLink($role) {
    switch ($role) {
        case 'admin': return '../admin/dashboard.php';
        case 'staff': return '../admin/staff-dashboard.php';
        default: return '../pages/users/dashboard.php';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saloon Elegance - Shop</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: #fefaf5; }
        
        .navbar-brand { display: flex; align-items: center; gap: 12px; text-decoration: none; }
        .logo-icon { font-size: 2.2rem; background: linear-gradient(135deg, #b85c1a, #e09d5e); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .logo-main { font-size: 1.6rem; font-weight: 800; background: linear-gradient(135deg, #b85c1a, #e09d5e); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .logo-tagline { font-size: 0.7rem; color: #a1826b; letter-spacing: 1px; }
        
        .btn-elegance { background: #b85c1a; color: white; border-radius: 40px; padding: 10px 28px; font-weight: 600; border: none; transition: all 0.3s; }
        .btn-elegance:hover { background: #9b4512; transform: translateY(-2px); color: white; }
        .btn-outline-elegance { border: 2px solid #b85c1a; color: #b85c1a; border-radius: 40px; font-weight: 600; background: transparent; transition: all 0.3s; }
        .btn-outline-elegance:hover { background: #b85c1a; color: white; }
        
        .card-product { transition: transform 0.2s; border: none; border-radius: 1.25rem; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.05); background: white; height: 100%; }
        .card-product:hover { transform: translateY(-6px); }
        .product-img { height: 220px; object-fit: cover; width: 100%; }
        .price { font-weight: 700; color: #b85c1a; font-size: 1.3rem; }
        .stock-badge { font-size: 0.7rem; padding: 0.2rem 0.6rem; border-radius: 20px; }
        .out-of-stock { opacity: 0.6; }
        .out-of-stock .btn-elegance { background: #ccc; cursor: not-allowed; }
        
        .filter-card { background: white; border-radius: 1.25rem; border: none; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .filter-header { font-weight: 700; padding: 1rem 1.25rem; border-bottom: 1px solid #f0e3d8; }
        .filter-body { padding: 1rem 1.25rem; }
        .filter-tag { background: #f5ede5; border-radius: 30px; padding: 0.3rem 0.9rem; font-size: 0.8rem; margin-right: 0.5rem; margin-bottom: 0.5rem; display: inline-block; cursor: pointer; transition: all 0.3s; }
        .filter-tag.active, .filter-tag:hover { background: #b85c1a; color: white; }
        
        .cart-sidebar { max-height: 400px; overflow-y: auto; }
        .cart-item-img { width: 50px; height: 50px; object-fit: cover; border-radius: 12px; }
        .cart-badge { background: #b85c1a; color: white; border-radius: 50%; padding: 0.2rem 0.5rem; font-size: 0.75rem; margin-left: 5px; }
        
        footer { background: #1e1b17; color: #cdc6bc; margin-top: 4rem; }
        .section-title { border-left: 6px solid #b85c1a; padding-left: 1rem; font-weight: 700; }
        .badge-offer { background: #e85d04; color: white; font-size: 0.7rem; border-radius: 20px; padding: 0.2rem 0.7rem; }
        
        .add-to-cart-btn { transition: all 0.3s ease; }
        .add-to-cart-btn:active { transform: scale(0.95); }
        
        .notification-area {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 350px;
        }
        
        .loader {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #b85c1a;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .checkout-field { position: relative; }
        .edit-hint { font-size: 0.75rem; color: #6c757d; margin-top: 4px; }
        .saved-info-badge {
            background: #e8f5e9;
            color: #2e7d32;
            font-size: 0.7rem;
            padding: 2px 10px;
            border-radius: 20px;
            display: inline-block;
            margin-left: 8px;
        }
        
        /* Debug console */
        .debug-console {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #1a1a2e;
            color: #00ff88;
            font-family: monospace;
            font-size: 12px;
            padding: 10px;
            max-height: 150px;
            overflow-y: auto;
            z-index: 99999;
            display: none;
            border-top: 2px solid #00ff88;
        }
        .debug-console.show {
            display: block;
        }
        .debug-toggle {
            position: fixed;
            bottom: 10px;
            right: 10px;
            z-index: 99999;
            background: #1a1a2e;
            color: #00ff88;
            border: 1px solid #00ff88;
            border-radius: 5px;
            padding: 5px 10px;
            font-size: 10px;
            cursor: pointer;
        }
    </style>
</head>
<body>

<!-- Debug Toggle Button -->
<button class="debug-toggle" onclick="toggleDebug()">🐛 Debug</button>
<div class="debug-console" id="debugConsole"></div>

<!-- Notification Area -->
<div class="notification-area" id="notificationArea"></div>

<nav class="navbar navbar-expand-lg bg-white sticky-top shadow-sm py-2">
    <div class="container">
        <a class="navbar-brand" href="../index.php">
            <i class="fas fa-cut logo-icon"></i>
            <div>
                <span class="logo-main">Saloon Elegance</span>
                <div class="logo-tagline">LUXURY GROOMING & SPA - SRI LANKA</div>
            </div>
        </a>
        <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-center gap-2">
                <li class="nav-item"><a class="nav-link fw-semibold" href="../index.php">Home</a></li>
                <li class="nav-item"><a class="nav-link fw-semibold active" href="#">Shop</a></li>
                <li class="nav-item">
                    <button class="btn btn-outline-elegance position-relative" data-bs-toggle="offcanvas" data-bs-target="#cartOffcanvas" id="cartButton">
                        <i class="fas fa-shopping-bag"></i> Cart <span id="cartCountBadge" class="cart-badge">0</span>
                    </button>
                </li>
                <li class="nav-item">
                    <?php if ($isLoggedIn): ?>
                        <div class="dropdown">
                            <button class="btn btn-outline-elegance dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($userFname); ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="<?php echo getDashboardLink($userRole); ?>">
                                    <i class="fas fa-tachometer-alt"></i> Dashboard
                                </a></li>
                                <li><a class="dropdown-item" href="../pages/users/orders.php">
                                    <i class="fas fa-shopping-bag"></i> My Orders
                                </a></li>
                                <li><a class="dropdown-item" href="../pages/users/notifications.php">
                                    <i class="fas fa-bell"></i> Notifications
                                </a></li>
                                <li><a class="dropdown-item" href="../pages/users/profile.php">
                                    <i class="fas fa-user-cog"></i> Profile Settings
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="../pages/logout.php">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a></li
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="../pages/login.php?redirect=index.php%23booking" class="btn btn-outline-elegance">
                            <i class="fas fa-user"></i> Sign In
                        </a>
                    <?php endif; ?>
                </li>
            </ul>
        </div>
    </div>
</nav>

<section class="py-4" style="background: linear-gradient(135deg, #fff5ec 0%, #fae6d4 100%);">
    <div class="container">
        <h1 class="display-6 fw-bold text-center">Premium <span style="color:#b85c1a;">Grooming Equipment & Care</span></h1>
        <p class="text-center text-muted">Professional-grade tools, organic products, and luxury salon essentials. Prices in Sri Lankan Rupees (LKR).</p>
    </div>
</section>

<div class="container my-5" id="products">
    <div class="row g-4">
        <div class="col-lg-3">
            <div class="filter-card">
                <div class="filter-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-filter me-2"></i>Filter Products</span>
                    <span class="clear-filters text-danger" id="clearAllFilters" style="cursor:pointer;">
                        <i class="fas fa-times-circle"></i> Reset
                    </span>
                </div>
                <div class="filter-body">
                    <h6 class="fw-bold mb-2">Category</h6>
                    <div class="mb-4">
                        <?php foreach ($categories as $cat): ?>
                        <div class="form-check mb-2">
                            <input class="form-check-input category-filter" type="checkbox" value="<?= htmlspecialchars($cat) ?>" id="cat<?= str_replace(' ', '', ucfirst($cat)) ?>">
                            <label for="cat<?= str_replace(' ', '', ucfirst($cat)) ?>"><?= ucfirst(htmlspecialchars($cat)) ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <h6 class="fw-bold mb-2">Price Range (LKR)</h6>
                    <div class="mb-3">
                        <div class="d-flex gap-2">
                            <input type="number" id="minPrice" class="form-control form-control-sm" placeholder="Min" value="0">
                            <span>-</span>
                            <input type="number" id="maxPrice" class="form-control form-control-sm" placeholder="Max" value="50000">
                        </div>
                        <button class="btn btn-sm btn-outline-secondary mt-2 w-100" id="applyPriceBtn">Apply Price</button>
                    </div>
                    
                    <h6 class="fw-bold mb-2">Special Tags</h6>
                    <div>
                        <span class="filter-tag" data-tag="sale">🔥 Sale</span>
                        <span class="filter-tag" data-tag="bestseller">⭐ Bestseller</span>
                        <span class="filter-tag" data-tag="organic">🌿 Organic</span>
                        <span class="filter-tag" data-tag="pro">🛠️ Pro Equipment</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-9">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="section-title mb-0">Premium <span style="color:#b85c1a;">Products</span></h2>
                <span id="filteredResultCount" class="badge bg-secondary">0 products</span>
            </div>
            <div class="row g-4" id="productGrid">
                <div class="col-12 text-center py-5">
                    <div class="loader"></div>
                    <p class="mt-2 text-muted">Loading products...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Cart Offcanvas -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="cartOffcanvas">
    <div class="offcanvas-header bg-white border-bottom">
        <h5 class="offcanvas-title fw-bold"><i class="fas fa-shopping-bag me-2"></i>Your Cart (LKR)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body p-0">
        <div id="cartItemsContainer" class="cart-sidebar p-3">
            <div class="text-center py-5">
                <i class="fas fa-shopping-bag fs-1 text-muted"></i>
                <p class="mt-2 text-muted">Cart is empty</p>
            </div>
        </div>
        <div class="border-top p-3 bg-light">
            <div class="d-flex justify-content-between fw-bold fs-5 mb-3">
                <span>Total:</span>
                <span id="cartTotalPrice">LKR 0.00</span>
            </div>
            <button class="btn btn-elegance w-100 py-2" id="checkoutBtn">
                <i class="fas fa-credit-card"></i> Proceed to Checkout
            </button>
            <button class="btn btn-outline-secondary w-100 mt-2" id="clearCartBtn">
                <i class="fas fa-trash"></i> Clear Cart
            </button>
        </div>
    </div>
</div>

<!-- Checkout Modal -->
<div class="modal fade" id="checkoutModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-credit-card"></i> Checkout</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="checkoutForm">
                <div class="modal-body">
                    <div class="mb-3 checkout-field">
                        <label class="form-label">
                            Shipping Address *
                            <?php if (!empty($userShippingAddress)): ?>
                                <span class="saved-info-badge">
                                    <i class="fas fa-check-circle"></i> Saved
                                </span>
                            <?php endif; ?>
                        </label>
                        <textarea class="form-control" name="shipping_address" id="shippingAddress" 
                                  required placeholder="Enter your full shipping address"
                                  rows="3"><?php echo htmlspecialchars($userShippingAddress ?: $userAddress); ?></textarea>
                        <?php if (!empty($userShippingAddress)): ?>
                            <div class="edit-hint">
                                <i class="fas fa-info-circle"></i> Edit this field to change your shipping address for this order.
                                <a href="../users/profile.php" class="text-primary">Update saved address</a>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3 checkout-field">
                        <label class="form-label">
                            Phone Number *
                            <?php if (!empty($userPhone)): ?>
                                <span class="saved-info-badge">
                                    <i class="fas fa-check-circle"></i> Saved
                                </span>
                            <?php endif; ?>
                        </label>
                        <input type="text" class="form-control" name="phone" id="phoneNumber" 
                               required placeholder="Enter phone number"
                               value="<?php echo htmlspecialchars($userPhone); ?>">
                        <?php if (!empty($userPhone)): ?>
                            <div class="edit-hint">
                                <i class="fas fa-info-circle"></i> Edit this field to change your phone number for this order.
                                <a href="../users/profile.php" class="text-primary">Update saved number</a>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Payment Method</label>
                        <select class="form-control" name="payment_method" id="paymentMethod">
                            <option value="cash_on_delivery">Cash on Delivery</option>
                            <option value="card">Credit/Debit Card</option>
                            <option value="bank_transfer">Bank Transfer</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Order Notes</label>
                        <textarea class="form-control" name="notes" placeholder="Any special instructions (optional)" rows="2"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="saveInfoCheck" checked>
                            <label class="form-check-label" for="saveInfoCheck">
                                <i class="fas fa-save"></i> Update my saved shipping information
                            </label>
                            <small class="text-muted d-block">This will update your profile with the shipping address and phone number entered above.</small>
                        </div>
                    </div>
                    
                    <div class="alert alert-info mt-3">
                        <strong>Order Summary:</strong><br>
                        <span id="checkoutTotal">LKR 0.00</span>
                        <br><small class="text-muted">Tax (10%) included in total</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-elegance" id="placeOrderBtn">
                        <i class="fas fa-check-circle"></i> Place Order
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- SCRIPTS -->
<!-- ============================================ -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// ============================================
// DEBUG CONSOLE
// ============================================
const debugLogs = [];

function debugLog(message, data = null) {
    const timestamp = new Date().toLocaleTimeString();
    const logEntry = { timestamp, message, data };
    debugLogs.push(logEntry);
    
    const consoleEl = document.getElementById('debugConsole');
    if (consoleEl) {
        const line = document.createElement('div');
        line.textContent = `[${timestamp}] ${message}`;
        if (data) {
            const dataSpan = document.createElement('span');
            dataSpan.style.color = '#ffaa00';
            dataSpan.textContent = ' ' + JSON.stringify(data);
            line.appendChild(dataSpan);
        }
        consoleEl.appendChild(line);
        consoleEl.scrollTop = consoleEl.scrollHeight;
    }
    console.log(`[${timestamp}]`, message, data || '');
}

function toggleDebug() {
    const consoleEl = document.getElementById('debugConsole');
    consoleEl.classList.toggle('show');
    if (consoleEl.classList.contains('show')) {
        debugLog('Debug console opened');
    }
}

// ============================================
// PRODUCT DATA
// ============================================
const products = <?php echo json_encode($products); ?>;
const productMap = <?php echo json_encode($productMap); ?>;
let cart = [];

debugLog('Shop page loaded', { 
    productsCount: products.length, 
    productMapKeys: Object.keys(productMap).length,
    isLoggedIn: <?php echo json_encode($isLoggedIn); ?>
});

// ============================================
// USER SAVED INFO
// ============================================
const userSavedInfo = {
    phone: '<?php echo addslashes($userPhone); ?>',
    shippingAddress: '<?php echo addslashes($userShippingAddress ?: $userAddress); ?>'
};

debugLog('User saved info', userSavedInfo);

// ============================================
// UTILITY FUNCTIONS
// ============================================
function formatLKR(amount) {
    return 'LKR ' + amount.toLocaleString('en-LK', { minimumFractionDigits: 2 });
}

function showNotification(message, type = 'success') {
    const alertClass = type === 'success' ? 'alert-success' : 
                       type === 'error' ? 'alert-danger' : 
                       type === 'warning' ? 'alert-warning' : 'alert-info';
    
    const icon = type === 'success' ? 'fa-check-circle' : 
                 type === 'error' ? 'fa-times-circle' : 
                 type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle';
    
    const html = `
        <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
            <i class="fas ${icon} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    $('#notificationArea').html(html);
    debugLog('Notification shown', { message, type });
    
    setTimeout(() => {
        $('#notificationArea .alert').alert('close');
    }, 4000);
}

// ============================================
// CART FUNCTIONS
// ============================================
function saveCart() {
    try {
        localStorage.setItem('saloonCart', JSON.stringify(cart));
        updateCartCount();
        debugLog('Cart saved', { items: cart.length });
    } catch (e) {
        console.error('Error saving cart:', e);
        debugLog('Error saving cart', { error: e.message });
    }
}

function loadCart() {
    try {
        const stored = localStorage.getItem('saloonCart');
        debugLog('Loading cart from localStorage', { stored: stored ? 'found' : 'not found' });
        
        if (stored) {
            cart = JSON.parse(stored);
            if (!Array.isArray(cart)) cart = [];
            
            // Validate cart items against current products
            cart = cart.filter(item => {
                const productExists = productMap[item.id];
                if (!productExists) {
                    debugLog('Removing invalid cart item', { id: item.id, name: item.name });
                    return false;
                }
                return true;
            });
        } else {
            cart = [];
        }
    } catch (e) {
        console.error('Error loading cart:', e);
        debugLog('Error loading cart', { error: e.message });
        cart = [];
    }
    
    debugLog('Cart loaded', { items: cart.length });
    updateCartUI();
}

function updateCartCount() {
    const totalItems = cart.reduce((sum, i) => sum + (i.quantity || 0), 0);
    $('#cartCountBadge').text(totalItems).toggleClass('d-none', totalItems === 0);
}

function addToCart(productId, quantity = 1) {
    debugLog('addToCart called', { productId, quantity });
    
    // Find product using the map
    const product = productMap[productId];
    
    if (!product) {
        debugLog('Product not found in map', { productId, availableIds: Object.keys(productMap) });
        showNotification('Product not found! Please refresh the page.', 'error');
        return false;
    }
    
    debugLog('Product found', { name: product.name, stock: product.stock_quantity });
    
    if (product.stock_quantity <= 0) {
        showNotification('This product is out of stock!', 'error');
        return false;
    }
    
    const existing = cart.find(item => item.id === productId);
    
    if (existing) {
        if ((existing.quantity + quantity) > product.stock_quantity) {
            showNotification(`Only ${product.stock_quantity} items available!`, 'warning');
            return false;
        }
        existing.quantity += quantity;
        debugLog('Updated quantity for existing item', { name: product.name, newQuantity: existing.quantity });
    } else {
        if (quantity > product.stock_quantity) {
            showNotification(`Only ${product.stock_quantity} items available!`, 'warning');
            return false;
        }
        cart.push({
            id: product.id,
            name: product.name,
            price: parseFloat(product.price),
            quantity: quantity,
            image: product.image || 'https://placehold.co/400x300/F5E6D8/B85C1A',
            max_stock: product.stock_quantity
        });
        debugLog('Added new item to cart', { name: product.name, quantity });
    }
    
    saveCart();
    updateCartUI();
    showNotification(`${product.name} added to cart!`, 'success');
    return true;
}

function updateCartUI() {
    updateCartCount();
    
    if (cart.length === 0) {
        $('#cartItemsContainer').html(`
            <div class="text-center py-5">
                <i class="fas fa-shopping-bag fs-1 text-muted"></i>
                <p class="mt-2 text-muted">Cart is empty</p>
                <small class="text-muted">Start adding items to your cart</small>
            </div>
        `);
        $('#cartTotalPrice').text(formatLKR(0));
        return;
    }
    
    let total = 0;
    let html = '<ul class="list-group list-group-flush">';
    
    cart.forEach((item, index) => {
        const itemTotal = (item.price || 0) * (item.quantity || 0);
        total += itemTotal;
        
        html += `
            <li class="list-group-item" data-index="${index}">
                <div class="row align-items-center">
                    <div class="col-3">
                        <img src="${item.image || 'https://placehold.co/400x300/F5E6D8/B85C1A'}" 
                             class="cart-item-img w-100" 
                             alt="${item.name}"
                             onerror="this.src='https://placehold.co/400x300/F5E6D8/B85C1A'">
                    </div>
                    <div class="col-5">
                        <h6 class="mb-0">${item.name}</h6>
                        <small>${formatLKR(item.price)}</small>
                        <br><small class="text-muted">Stock: ${item.max_stock}</small>
                    </div>
                    <div class="col-4 text-end">
                        <div class="btn-group btn-group-sm" role="group">
                            <button class="btn btn-outline-secondary qty-down" data-index="${index}">-</button>
                            <span class="px-2 py-1">${item.quantity}</span>
                            <button class="btn btn-outline-secondary qty-up" data-index="${index}" 
                                    ${item.quantity >= item.max_stock ? 'disabled' : ''}>+</button>
                        </div>
                        <button class="btn btn-sm text-danger remove-item mt-1 d-block" data-index="${index}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                <div class="text-end mt-1 fw-bold">${formatLKR(itemTotal)}</div>
            </li>
        `;
    });
    
    html += '</ul>';
    $('#cartItemsContainer').html(html);
    $('#cartTotalPrice').text(formatLKR(total));
    
    // Event handlers
    $('.qty-up').off('click').on('click', function() {
        const index = parseInt($(this).data('index'));
        if (index >= 0 && index < cart.length) {
            if (cart[index].quantity < cart[index].max_stock) {
                cart[index].quantity++;
                saveCart();
                updateCartUI();
            } else {
                showNotification('Maximum stock reached!', 'warning');
            }
        }
    });
    
    $('.qty-down').off('click').on('click', function() {
        const index = parseInt($(this).data('index'));
        if (index >= 0 && index < cart.length) {
            cart[index].quantity--;
            if (cart[index].quantity <= 0) {
                cart.splice(index, 1);
            }
            saveCart();
            updateCartUI();
        }
    });
    
    $('.remove-item').off('click').on('click', function() {
        const index = parseInt($(this).data('index'));
        if (index >= 0 && index < cart.length) {
            const itemName = cart[index].name;
            cart.splice(index, 1);
            saveCart();
            updateCartUI();
            showNotification(`${itemName} removed from cart`, 'info');
        }
    });
}

// ============================================
// PRODUCT DISPLAY FUNCTIONS
// ============================================
function renderProducts() {
    debugLog('Rendering products', { count: products.length });
    let filtered = [...products];
    
    const activeCategories = [];
    $('.category-filter:checked').each(function() {
        activeCategories.push($(this).val());
    });
    if (activeCategories.length > 0) {
        filtered = filtered.filter(p => activeCategories.includes(p.category));
    }
    
    const minPrice = parseFloat($('#minPrice').val()) || 0;
    const maxPrice = parseFloat($('#maxPrice').val()) || 50000;
    filtered = filtered.filter(p => p.price >= minPrice && p.price <= maxPrice);
    
    const activeTag = $('.filter-tag.active').data('tag');
    if (activeTag) {
        filtered = filtered.filter(p => p.offerTag === activeTag);
    }
    
    filtered.sort((a, b) => (b.rating || 0) - (a.rating || 0));
    
    $('#filteredResultCount').text(filtered.length + ' products');
    
    if (filtered.length === 0) {
        $('#productGrid').html(`
            <div class="col-12 text-center py-5">
                <i class="fas fa-search fs-1 text-muted"></i>
                <h4 class="mt-3">No products found</h4>
                <p class="text-muted">Try adjusting your filters</p>
            </div>
        `);
        return;
    }
    
    let html = '';
    filtered.forEach(p => {
        const stars = '★'.repeat(Math.floor(p.rating || 0)) + '☆'.repeat(5 - Math.floor(p.rating || 0));
        const offerBadge = p.offerTag ? 
            `<span class="badge-offer ms-2">${p.offerTag === 'sale' ? '🔥 Sale' : p.offerTag === 'bestseller' ? '⭐ Bestseller' : p.offerTag === 'organic' ? '🌿 Organic' : '🛠️ Pro'}</span>` : '';
        const stockStatus = (p.stock_quantity || 0) > 0 ? 
            `<span class="stock-badge text-success">In Stock (${p.stock_quantity})</span>` : 
            `<span class="stock-badge text-danger">Out of Stock</span>`;
        
        html += `
            <div class="col-md-6 col-xl-4 ${(p.stock_quantity || 0) <= 0 ? 'out-of-stock' : ''}">
                <div class="card card-product h-100">
                    <img src="${p.image || 'https://placehold.co/400x300/F5E6D8/B85C1A'}" 
                         class="product-img" 
                         alt="${p.name}"
                         onerror="this.src='https://placehold.co/400x300/F5E6D8/B85C1A'">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start">
                            <h5 class="card-title fw-bold">${p.name}</h5>
                            ${offerBadge}
                        </div>
                        <div class="rating-stars mb-1">${stars} (${p.rating || 0})</div>
                        <p class="small text-muted mb-2">${p.description || ''}</p>
                        <div class="mt-auto">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="price">${formatLKR(p.price)}</span>
                                    <br>${stockStatus}
                                </div>
                                ${(p.stock_quantity || 0) > 0 ? 
                                    `<button class="btn btn-elegance add-to-cart-btn" data-id="${p.id}">
                                        <i class="fas fa-cart-plus"></i> Add
                                    </button>` : 
                                    `<button class="btn btn-secondary" disabled>
                                        <i class="fas fa-times-circle"></i> Unavailable
                                    </button>`
                                }
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    $('#productGrid').html(html);
    
    // ============================================
    // ADD TO CART BUTTON HANDLER - FIXED with better error handling
    // ============================================
    $(document).off('click', '.add-to-cart-btn').on('click', '.add-to-cart-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const productId = parseInt($(this).data('id'));
        debugLog('Add to cart button clicked', { productId });
        
        if (isNaN(productId) || !productId) {
            debugLog('Invalid product ID', { productId });
            showNotification('Invalid product!', 'error');
            return;
        }
        
        <?php if ($isLoggedIn): ?>
            // Check if product exists in map first
            if (!productMap[productId]) {
                debugLog('Product not found in map', { productId });
                showNotification('Product not found! Please refresh the page.', 'error');
                return;
            }
            
            const result = addToCart(productId, 1);
            if (result) {
                const $btn = $(this);
                const originalHtml = $btn.html();
                $btn.html('<i class="fas fa-spinner fa-spin"></i> Adding...');
                setTimeout(() => {
                    $btn.html('<i class="fas fa-check"></i> Added!');
                    setTimeout(() => {
                        $btn.html(originalHtml);
                    }, 1500);
                }, 500);
            }
        <?php else: ?>
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Login Required',
                    text: 'Please login to add items to cart',
                    icon: 'info',
                    showCancelButton: true,
                    confirmButtonText: 'Login Now'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = '../pages/login.php?redirect=pages/shop.php';
                    }
                });
            } else {
                alert('Please login to add items to cart');
            }
        <?php endif; ?>
    });
}

// ============================================
// FILTER FUNCTIONS
// ============================================
function initFilters() {
    $('.category-filter').on('change', renderProducts);
    $('#applyPriceBtn').on('click', renderProducts);
    
    $('.filter-tag').on('click', function() {
        $(this).toggleClass('active');
        renderProducts();
    });
    
    $('#clearAllFilters').on('click', function() {
        $('.category-filter').prop('checked', false);
        $('#minPrice').val(0);
        $('#maxPrice').val(50000);
        $('.filter-tag').removeClass('active');
        renderProducts();
    });
    
    $('#minPrice, #maxPrice').on('keypress', function(e) {
        if (e.which === 13) {
            renderProducts();
        }
    });
}

// ============================================
// CHECKOUT FUNCTIONS
// ============================================
function openCheckout() {
    debugLog('Opening checkout', { cartItems: cart.length });
    
    if (cart.length === 0) {
        showNotification('Your cart is empty!', 'warning');
        return;
    }
    
    <?php if ($isLoggedIn): ?>
        const total = cart.reduce((sum, i) => sum + ((i.price || 0) * (i.quantity || 0)), 0);
        $('#checkoutTotal').text(formatLKR(total));
        
        if (userSavedInfo.shippingAddress) {
            $('#shippingAddress').val(userSavedInfo.shippingAddress);
        }
        if (userSavedInfo.phone) {
            $('#phoneNumber').val(userSavedInfo.phone);
        }
        
        const modal = new bootstrap.Modal(document.getElementById('checkoutModal'));
        modal.show();
        debugLog('Checkout modal opened');
    <?php else: ?>
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Login Required',
                text: 'Please login to complete your purchase',
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: 'Login Now'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../login.php?redirect=pages/shop.php';
                }
            });
        } else {
            alert('Please login to complete your purchase');
        }
    <?php endif; ?>
}

// ============================================
// SAVE USER PROFILE FUNCTION
// ============================================
function saveUserProfile(phone, address) {
    debugLog('Saving user profile', { phone, address });
    return $.ajax({
        url: 'save_profile.php',
        method: 'POST',
        data: {
            phone: phone,
            shipping_address: address
        },
        dataType: 'json'
    });
}

// ============================================
// DOCUMENT READY
// ============================================
$(document).ready(function() {
    debugLog('Document ready - Shop page');
    
    // Load cart first
    loadCart();
    
    // Render products
    renderProducts();
    
    // Initialize filters
    initFilters();
    
    // ============================================
    // CHECKOUT BUTTON
    // ============================================
    $('#checkoutBtn').on('click', function(e) {
        e.preventDefault();
        openCheckout();
    });
    
    // ============================================
    // CLEAR CART BUTTON
    // ============================================
    $('#clearCartBtn').on('click', function() {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Clear Cart?',
                text: 'This will remove all items from your cart',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Yes, clear it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    cart = [];
                    saveCart();
                    updateCartUI();
                    showNotification('Cart cleared!', 'info');
                    debugLog('Cart cleared');
                }
            });
        } else {
            if (confirm('Clear your cart?')) {
                cart = [];
                saveCart();
                updateCartUI();
            }
        }
    });
    
    // ============================================
    // CHECKOUT FORM SUBMISSION
    // ============================================
    $('#checkoutForm').on('submit', function(e) {
        e.preventDefault();
        
        debugLog('Checkout form submitted');
        
        if (cart.length === 0) {
            showNotification('Your cart is empty!', 'warning');
            return;
        }
        
        const shippingAddress = $('#shippingAddress').val().trim();
        const phone = $('#phoneNumber').val().trim();
        const saveInfo = $('#saveInfoCheck').is(':checked');
        
        if (!shippingAddress) {
            showNotification('Please enter your shipping address', 'error');
            $('#shippingAddress').focus();
            return;
        }
        
        if (!phone) {
            showNotification('Please enter your phone number', 'error');
            $('#phoneNumber').focus();
            return;
        }
        
        debugLog('Checkout validation passed', { shippingAddress, phone, saveInfo });
        
        const formData = new FormData(this);
        const total = cart.reduce((sum, i) => sum + ((i.price || 0) * (i.quantity || 0)), 0);
        
        formData.append('cart_data', JSON.stringify(cart));
        formData.append('total', total);
        formData.append('save_info', saveInfo ? '1' : '0');
        
        $('#placeOrderBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');
        
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Processing Order...',
                text: 'Please wait',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
        }
        
        debugLog('Sending order to server', { 
            total, 
            items: cart.length,
            saveInfo 
        });
        
        $.ajax({
            url: 'place_order.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                debugLog('Order response received', response);
                
                if (typeof Swal !== 'undefined') {
                    Swal.close();
                }
                $('#placeOrderBtn').prop('disabled', false).html('<i class="fas fa-check-circle"></i> Place Order');
                
                if (response.success) {
                    if (saveInfo && (phone !== userSavedInfo.phone || shippingAddress !== userSavedInfo.shippingAddress)) {
                        saveUserProfile(phone, shippingAddress).done(function(profileResponse) {
                            debugLog('Profile updated', profileResponse);
                        });
                    }
                    
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Order Placed!',
                            html: `Order #${response.order_number}<br>Total: ${formatLKR(total)}<br>We'll contact you shortly.`,
                            confirmButtonText: 'View Orders'
                        }).then(() => {
                            cart = [];
                            saveCart();
                            updateCartUI();
                            $('#checkoutModal').modal('hide');
                            window.location.href = '../users/orders.php';
                        });
                    } else {
                        alert('Order placed successfully!');
                        cart = [];
                        saveCart();
                        updateCartUI();
                        $('#checkoutModal').modal('hide');
                        window.location.href = '../users/orders.php';
                    }
                } else {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire('Error', response.message || 'Could not place order', 'error');
                    } else {
                        alert('Error: ' + response.message);
                    }
                    debugLog('Order placement failed', { message: response.message });
                }
            },
            error: function(xhr, status, error) {
                if (typeof Swal !== 'undefined') {
                    Swal.close();
                }
                $('#placeOrderBtn').prop('disabled', false).html('<i class="fas fa-check-circle"></i> Place Order');
                
                console.error('Order error:', error);
                debugLog('Order AJAX error', { status, error, response: xhr.responseText });
                
                let errorMsg = 'Could not place order. Please try again.';
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.message) errorMsg = response.message;
                } catch (e) {}
                
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Error', errorMsg, 'error');
                } else {
                    alert('Error: ' + errorMsg);
                }
            }
        });
    });
    
    debugLog('Shop page initialization complete');
});
</script>

<noscript>
    <div class="container mt-3">
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i> 
            JavaScript is disabled. Please enable JavaScript to use the shopping features.
        </div>
    </div>
</noscript>

<footer class="pt-5 pb-4 mt-5">
    <div class="container">
        <div class="row">
            <div class="col-md-5 mb-4">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <i class="fas fa-cut fs-2" style="color: #b85c1a;"></i>
                    <div>
                        <h5 class="text-white mb-0">Saloon Elegance</h5>
                        <small class="text-secondary">LUXURY GROOMING & SPA - SRI LANKA</small>
                    </div>
                </div>
                <p class="text-secondary">Premium grooming equipment and organic care products. All prices in Sri Lankan Rupees (LKR).</p>
            </div>
            <div class="col-md-3 mb-4">
                <h6 class="text-white">Contact</h6>
                <ul class="list-unstyled text-secondary">
                    <li><i class="fas fa-phone me-2"></i> +94 11 234 5678</li>
                    <li><i class="fas fa-envelope me-2"></i> info@salonelegance.lk</li>
                </ul>
            </div>
            <div class="col-md-4 mb-4">
                <h6 class="text-white">Follow Us</h6>
                <div class="fs-4">
                    <i class="fab fa-instagram me-3 text-secondary"></i>
                    <i class="fab fa-facebook me-3 text-secondary"></i>
                    <i class="fab fa-whatsapp text-secondary"></i>
                </div>
                <div class="mt-3">
                    <a href="../index.php" class="text-secondary text-decoration-none">Back to Home</a>
                </div>
            </div>
        </div>
        <hr class="bg-secondary">
        <div class="text-center text-secondary small">© 2025 Saloon Elegance — Sri Lanka</div>
    </div>
</footer>
</body>
</html>