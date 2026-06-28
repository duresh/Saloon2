<?php
session_start();
require_once '../includes/dbcon.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$userId = null;
$userName = '';
$userFname = '';
$userRole = '';
$userEmail = '';
$userPhone = '';

if ($isLoggedIn && isset($_SESSION['user_id'])) {
    try {
        $pdo = getPDOConnection();
        $stmt = $pdo->prepare("SELECT fName, lName, email, role, regID, contactNo FROM reg WHERE regID = ? AND cStatus = 1");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        if ($user) {
            $userId = $user['regID'];
            $userName = $user['fName'] . ' ' . $user['lName'];
            $userFname = $user['fName'];
            $userRole = $user['role'];
            $userEmail = $user['email'];
            $userPhone = $user['contactNo'] ?? '';
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['role'] = $user['role'];
        } else {
            session_destroy();
            $isLoggedIn = false;
        }
    } catch (Exception $e) {
        // Ignore
    }
}

// Fetch products from database
try {
    $pdo = getPDOConnection();
    $stmt = $pdo->prepare("SELECT id, name, description, price, category, rating, image_url as image, offer_tag as offerTag, stock_quantity FROM products WHERE status = 'active' ORDER BY category, name");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $products = [];
}

// Get product categories for filter
$categories = array_unique(array_column($products, 'category'));
sort($categories);

function getDashboardLink($role) {
    switch ($role) {
        case 'admin':
            return '../admin/dashboard.php';
        case 'staff':
            return '../admin/staff-dashboard.php';
        default:
            return '../pages/users/dashboard.php';
    }
}

function getDashboardName($role) {
    switch ($role) {
        case 'admin':
            return 'Admin Dashboard';
        case 'staff':
            return 'Staff Dashboard';
        default:
            return 'My Dashboard';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saloon Elegance | Premium Equipment & Care - Sri Lanka</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: #fefaf5; }
        
        .navbar-brand { display: flex; align-items: center; gap: 12px; text-decoration: none; }
        .logo-icon { font-size: 2.2rem; background: linear-gradient(135deg, #b85c1a, #e09d5e); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .logo-main { font-size: 1.6rem; font-weight: 800; background: linear-gradient(135deg, #b85c1a, #e09d5e); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .logo-tagline { font-size: 0.7rem; color: #a1826b; letter-spacing: 1px; }
        
        .btn-elegance { background: #b85c1a; color: white; border-radius: 40px; padding: 10px 28px; font-weight: 600; border: none; }
        .btn-elegance:hover { background: #9b4512; transform: translateY(-2px); }
        .btn-outline-elegance { border: 2px solid #b85c1a; color: #b85c1a; border-radius: 40px; font-weight: 600; background: transparent; }
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
        .filter-tag { background: #f5ede5; border-radius: 30px; padding: 0.3rem 0.9rem; font-size: 0.8rem; margin-right: 0.5rem; margin-bottom: 0.5rem; display: inline-block; cursor: pointer; }
        .filter-tag.active, .filter-tag:hover { background: #b85c1a; color: white; }
        
        .cart-sidebar { max-height: 400px; overflow-y: auto; }
        .cart-item-img { width: 50px; height: 50px; object-fit: cover; border-radius: 12px; }
        .cart-badge { background: #b85c1a; color: white; border-radius: 50%; padding: 0.2rem 0.5rem; font-size: 0.75rem; margin-left: 5px; }
        
        footer { background: #1e1b17; color: #cdc6bc; margin-top: 4rem; }
        .section-title { border-left: 6px solid #b85c1a; padding-left: 1rem; font-weight: 700; }
        
        .badge-offer { background: #e85d04; color: white; font-size: 0.7rem; border-radius: 20px; padding: 0.2rem 0.7rem; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg bg-white sticky-top shadow-sm py-2">
    <div class="container">
        <a class="navbar-brand" href="../index.php">
            <i class="bi bi-scissors logo-icon"></i>
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
                    <button class="btn btn-outline-elegance position-relative" data-bs-toggle="offcanvas" data-bs-target="#cartOffcanvas">
                        <i class="bi bi-bag"></i> Cart <span id="cartCountBadge" class="cart-badge">0</span>
                    </button>
                </li>
                <li class="nav-item">
                    <?php if ($isLoggedIn): ?>
                        <div class="dropdown">
                            <button class="btn btn-outline-elegance dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($userFname); ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="<?php echo getDashboardLink($userRole); ?>">
                                    <i class="bi bi-speedometer2"></i> <?php echo getDashboardName($userRole); ?>
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="../pages/users/orders.php"><i class="bi bi-bag"></i> My Orders</a></li>
                                <li><a class="dropdown-item" href="../index.php#booking"><i class="bi bi-calendar"></i> My Appointments</a></li>
                                <li><a class="dropdown-item" href="../pages/users/profile.php"><i class="bi bi-person"></i> My Profile</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="../../Saloon/pages/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="../login.php?redirect=<?php echo urlencode('shop/shop.php'); ?>" class="btn btn-outline-elegance"><i class="bi bi-person"></i> Sign In</a>
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
                    <span><i class="bi bi-funnel-fill me-2"></i>Filter Products</span>
                    <span class="clear-filters text-danger" id="clearAllFilters" style="cursor:pointer;"><i class="bi bi-x-circle"></i> Reset</span>
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
            <div class="row g-4" id="productGrid"></div>
        </div>
    </div>
</div>

<!-- Cart Offcanvas -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="cartOffcanvas">
    <div class="offcanvas-header bg-white border-bottom">
        <h5 class="offcanvas-title fw-bold"><i class="bi bi-bag-check me-2"></i>Your Cart (LKR)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body p-0">
        <div id="cartItemsContainer" class="cart-sidebar p-3"></div>
        <div class="border-top p-3 bg-light">
            <div class="d-flex justify-content-between fw-bold fs-5 mb-3">
                <span>Total:</span>
                <span id="cartTotalPrice">LKR 0.00</span>
            </div>
            <button class="btn btn-elegance w-100 py-2" id="checkoutBtn"><i class="bi bi-credit-card"></i> Proceed to Checkout</button>
            <button class="btn btn-outline-secondary w-100 mt-2" id="clearCartBtn"><i class="bi bi-trash3"></i> Clear Cart</button>
        </div>
    </div>
</div>

<!-- Checkout Modal -->
<div class="modal fade" id="checkoutModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-credit-card"></i> Checkout</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="checkoutForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Shipping Address *</label>
                        <textarea class="form-control" name="shipping_address" required placeholder="Enter your full address"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone Number *</label>
                        <input type="text" class="form-control" name="phone" required placeholder="Enter phone number" value="<?php echo htmlspecialchars($userPhone ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Method</label>
                        <select class="form-control" name="payment_method">
                            <option value="cash_on_delivery">Cash on Delivery</option>
                            <option value="card">Credit/Debit Card</option>
                            <option value="bank_transfer">Bank Transfer</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Order Notes</label>
                        <textarea class="form-control" name="notes" placeholder="Any special instructions"></textarea>
                    </div>
                    <div class="alert alert-info">
                        <strong>Order Summary:</strong><br>
                        <span id="checkoutTotal">LKR 0.00</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-elegance">
                        <i class="bi bi-check-circle"></i> Place Order
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Product data from PHP
const products = <?php echo json_encode($products); ?>;
let cart = [];

// Format currency
function formatLKR(amount) {
    return 'LKR ' + amount.toLocaleString('en-LK', { minimumFractionDigits: 2 });
}

// Cart functions
function saveCart() { 
    localStorage.setItem('saloonCartLKR', JSON.stringify(cart));
    updateCartCount();
}

function loadCart() { 
    const stored = localStorage.getItem('saloonCartLKR'); 
    if (stored) {
        try {
            cart = JSON.parse(stored);
            if (!Array.isArray(cart)) cart = [];
        } catch (e) {
            cart = [];
        }
    } else {
        cart = [];
    }
    updateCartUI();
}

function updateCartCount() {
    const totalItems = cart.reduce((sum, i) => sum + (i.quantity || 0), 0);
    $('#cartCountBadge').text(totalItems).toggleClass('d-none', totalItems === 0);
}

function addToCart(product, quantity = 1) {
    // Check stock
    if (product.stock_quantity <= 0) {
        Swal.fire('Out of Stock', 'This product is currently out of stock', 'error');
        return false;
    }
    
    const existing = cart.find(item => item.id === product.id);
    if (existing) {
        if ((existing.quantity + quantity) > product.stock_quantity) {
            Swal.fire('Stock Limit', `Only ${product.stock_quantity} items available`, 'warning');
            return false;
        }
        existing.quantity += quantity;
    } else {
        if (quantity > product.stock_quantity) {
            Swal.fire('Stock Limit', `Only ${product.stock_quantity} items available`, 'warning');
            return false;
        }
        cart.push({ 
            id: product.id, 
            name: product.name, 
            price: parseFloat(product.price), 
            quantity: quantity, 
            image: product.image,
            max_stock: product.stock_quantity
        });
    }
    saveCart();
    updateCartUI();
    Swal.fire({ 
        icon: 'success', 
        title: 'Added!', 
        text: `${product.name} added to cart`,
        toast: true, 
        position: 'top-end', 
        showConfirmButton: false, 
        timer: 1500 
    });
    return true;
}

function updateCartUI() {
    const totalItems = cart.reduce((sum, i) => sum + (i.quantity || 0), 0);
    $('#cartCountBadge').text(totalItems).toggleClass('d-none', totalItems === 0);
    
    if (cart.length === 0) {
        $('#cartItemsContainer').html(`<div class="text-center py-5"><i class="bi bi-bag-x fs-1"></i><p>Cart empty</p></div>`);
        $('#cartTotalPrice').text(formatLKR(0));
        return;
    }
    
    let total = 0;
    let html = '<ul class="list-group list-group-flush">';
    cart.forEach((item, index) => {
        const itemTotal = (item.price || 0) * (item.quantity || 0);
        total += itemTotal;
        html += `<li class="list-group-item" data-index="${index}">
            <div class="row align-items-center">
                <div class="col-3">
                    <img src="${item.image || 'https://placehold.co/400x300/F5E6D8/B85C1A'}" class="cart-item-img w-100" onerror="this.src='https://placehold.co/400x300/F5E6D8/B85C1A'">
                </div>
                <div class="col-5">
                    <h6 class="mb-0">${item.name}</h6>
                    <small>${formatLKR(item.price)}</small>
                    <br><small class="text-muted">Stock: ${item.max_stock}</small>
                </div>
                <div class="col-4 text-end">
                    <button class="btn btn-sm btn-outline-secondary qty-down" data-index="${index}">-</button>
                    <span class="mx-2">${item.quantity}</span>
                    <button class="btn btn-sm btn-outline-secondary qty-up" data-index="${index}" ${item.quantity >= item.max_stock ? 'disabled' : ''}>+</button>
                    <button class="btn btn-sm text-danger ms-1 remove-item" data-index="${index}"><i class="bi bi-trash"></i></button>
                </div>
            </div>
            <div class="text-end mt-1 fw-bold">${formatLKR(itemTotal)}</div>
        </li>`;
    });
    html += '</ul>';
    $('#cartItemsContainer').html(html);
    $('#cartTotalPrice').text(formatLKR(total));
    
    // Event handlers for cart items
    $('.qty-up').off('click').on('click', function() { 
        const index = parseInt($(this).data('index'));
        if (index >= 0 && index < cart.length) {
            if (cart[index].quantity < cart[index].max_stock) {
                cart[index].quantity++; 
                saveCart(); 
                updateCartUI();
            } else {
                Swal.fire('Stock Limit', 'Maximum stock reached', 'warning');
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
            cart.splice(index, 1);
            saveCart();
            updateCartUI();
        }
    });
}

function renderProducts() {
    let filtered = [...products];
    
    // Category filter
    const activeCategories = [];
    $('.category-filter:checked').each(function() {
        activeCategories.push($(this).val());
    });
    if (activeCategories.length > 0) {
        filtered = filtered.filter(p => activeCategories.includes(p.category));
    }
    
    // Price filter
    const minPrice = parseFloat($('#minPrice').val()) || 0;
    const maxPrice = parseFloat($('#maxPrice').val()) || 50000;
    filtered = filtered.filter(p => p.price >= minPrice && p.price <= maxPrice);
    
    // Tag filter
    const activeTag = $('.filter-tag.active').data('tag');
    if (activeTag) {
        filtered = filtered.filter(p => p.offerTag === activeTag);
    }
    
    // Sort by rating
    filtered.sort((a, b) => (b.rating || 0) - (a.rating || 0));
    
    $('#filteredResultCount').text(filtered.length + ' products');
    
    if (filtered.length === 0) {
        $('#productGrid').html('<div class="col-12 text-center py-5"><i class="bi bi-emoji-frown fs-1"></i><h4>No products found</h4></div>');
        return;
    }
    
    let html = '';
    filtered.forEach(p => {
        const stars = '★'.repeat(Math.floor(p.rating || 0)) + '☆'.repeat(5 - Math.floor(p.rating || 0));
        const offerBadge = p.offerTag ? `<span class="badge-offer ms-2">${p.offerTag === 'sale' ? '🔥 Sale' : p.offerTag === 'bestseller' ? '⭐ Bestseller' : p.offerTag === 'organic' ? '🌿 Organic' : '🛠️ Pro'}</span>` : '';
        const stockStatus = (p.stock_quantity || 0) > 0 ? 
            `<span class="stock-badge text-success">In Stock (${p.stock_quantity})</span>` : 
            `<span class="stock-badge text-danger">Out of Stock</span>`;
        
        html += `<div class="col-md-6 col-xl-4 ${(p.stock_quantity || 0) <= 0 ? 'out-of-stock' : ''}">
            <div class="card card-product h-100">
                <img src="${p.image || 'https://placehold.co/400x300/F5E6D8/B85C1A'}" class="product-img" alt="${p.name}" onerror="this.src='https://placehold.co/400x300/F5E6D8/B85C1A'">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex justify-content-between">
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
                                `<button class="btn btn-elegance addToCartBtn" data-id="${p.id}"><i class="bi bi-cart-plus"></i> Add</button>` : 
                                `<button class="btn btn-secondary" disabled><i class="bi bi-x-circle"></i> Unavailable</button>`
                            }
                        </div>
                    </div>
                </div>
            </div>
        </div>`;
    });
    $('#productGrid').html(html);
    
    $('.addToCartBtn').off('click').on('click', function() {
        const pid = parseInt($(this).data('id'));
        const product = products.find(p => p.id === pid);
        if (product) {
            <?php if ($isLoggedIn): ?>
                addToCart(product, 1);
            <?php else: ?>
                Swal.fire({
                    title: 'Login Required',
                    text: 'Please login to add items to cart',
                    icon: 'info',
                    showCancelButton: true,
                    confirmButtonText: 'Login Now'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = '../login.php?redirect=shop/shop.php';
                    }
                });
            <?php endif; ?>
        }
    });
}

// Initialize filters
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
    
    // Allow Enter key for price filters
    $('#minPrice, #maxPrice').on('keypress', function(e) {
        if (e.which === 13) {
            renderProducts();
        }
    });
}

// Checkout functions
function openCheckout() {
    // Get cart from localStorage
    const cartData = localStorage.getItem('saloonCartLKR');
    if (!cartData) {
        Swal.fire('Cart Empty', 'Please add products to your cart', 'warning');
        return;
    }
    
    let cartItems;
    try {
        cartItems = JSON.parse(cartData);
        if (!Array.isArray(cartItems) || cartItems.length === 0) {
            Swal.fire('Cart Empty', 'Please add products to your cart', 'warning');
            return;
        }
    } catch (e) {
        Swal.fire('Error', 'Invalid cart data', 'error');
        return;
    }
    
    <?php if ($isLoggedIn): ?>
        const total = cartItems.reduce((sum, i) => sum + ((i.price || 0) * (i.quantity || 0)), 0);
        $('#checkoutTotal').text(formatLKR(total));
        const modal = new bootstrap.Modal(document.getElementById('checkoutModal'));
        modal.show();
    <?php else: ?>
        Swal.fire({
            title: 'Login Required',
            text: 'Please login to complete your purchase',
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: 'Login Now'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '../login.php?redirect=shop/shop.php';
            }
        });
    <?php endif; ?>
}

$(document).ready(function() {
    renderProducts();
    initFilters();
    loadCart();
    
    // Clear cart
    $('#clearCartBtn').on('click', function() { 
        cart = []; 
        saveCart(); 
        updateCartUI(); 
        Swal.fire('Cleared!', 'Cart has been cleared', 'success');
    });
    
    // Checkout button
    $('#checkoutBtn').on('click', openCheckout);
    
    // Handle checkout form submission - FIXED VERSION
    $('#checkoutForm').on('submit', function(e) {
        e.preventDefault();
        
        // Get cart data from localStorage
        const cartData = localStorage.getItem('saloonCartLKR');
        if (!cartData) {
            Swal.fire('Error', 'Cart is empty', 'error');
            return;
        }
        
        let cartItems;
        try {
            cartItems = JSON.parse(cartData);
            if (!Array.isArray(cartItems) || cartItems.length === 0) {
                Swal.fire('Error', 'Cart is empty', 'error');
                return;
            }
        } catch (e) {
            Swal.fire('Error', 'Invalid cart data', 'error');
            return;
        }
        
        // Calculate total
        const total = cartItems.reduce((sum, item) => sum + ((item.price || 0) * (item.quantity || 0)), 0);
        
        // Get form data
        const formData = new FormData(this);
        formData.append('cart_data', JSON.stringify(cartItems));
        formData.append('total', total);
        
        // Show loading
        Swal.fire({
            title: 'Processing Order...',
            text: 'Please wait',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        // Submit order to server
        $.ajax({
            url: 'place_order.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Order Placed!',
                        html: `Order #${response.order_number}<br>Total: ${formatLKR(total)}<br>We'll contact you shortly.`,
                        confirmButtonText: 'View Orders'
                    }).then(() => {
                        // Clear cart
                        cart = [];
                        localStorage.removeItem('saloonCartLKR');
                        updateCartUI();
                        $('#checkoutModal').modal('hide');
                        window.location.href = '../pages/users/orders.php';
                    });
                } else {
                    Swal.fire('Error', response.message || 'Could not place order', 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Order error:', error);
                console.error('Response:', xhr.responseText);
                let errorMsg = 'Could not place order. Please try again.';
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.message) errorMsg = response.message;
                } catch (e) {}
                Swal.fire('Error', errorMsg, 'error');
            }
        });
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

<footer class="pt-5 pb-4 mt-5">
    <div class="container">
        <div class="row">
            <div class="col-md-5 mb-4">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <i class="bi bi-scissors fs-2" style="color: #b85c1a;"></i>
                    <div><h5 class="text-white mb-0">Saloon Elegance</h5><small class="text-secondary">LUXURY GROOMING & SPA - SRI LANKA</small></div>
                </div>
                <p class="text-secondary">Premium grooming equipment and organic care products. All prices in Sri Lankan Rupees (LKR).</p>
            </div>
            <div class="col-md-3 mb-4">
                <h6 class="text-white">Contact</h6>
                <ul class="list-unstyled text-secondary">
                    <li><i class="bi bi-telephone"></i> +94 11 234 5678</li>
                    <li><i class="bi bi-envelope"></i> info@salonelegance.lk</li>
                </ul>
            </div>
            <div class="col-md-4 mb-4">
                <h6 class="text-white">Follow Us</h6>
                <div class="fs-4">
                    <i class="bi bi-instagram me-3 text-secondary"></i>
                    <i class="bi bi-facebook me-3 text-secondary"></i>
                    <i class="bi bi-whatsapp text-secondary"></i>
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