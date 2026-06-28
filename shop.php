<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saloon Elegance | Premium Equipment & Care - Sri Lanka</title>
    <!-- Bootstrap 5 + Icons + Google Fonts -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: #fefaf5; }
        
        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }
        .logo-icon { font-size: 2.2rem; background: linear-gradient(135deg, #b85c1a, #e09d5e); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .logo-main { font-size: 1.6rem; font-weight: 800; background: linear-gradient(135deg, #b85c1a, #e09d5e); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .logo-tagline { font-size: 0.7rem; color: #a1826b; letter-spacing: 1px; }
        
        .btn-elegance { background: #b85c1a; color: white; border-radius: 40px; padding: 10px 28px; font-weight: 600; border: none; }
        .btn-elegance:hover { background: #9b4512; transform: translateY(-2px); }
        .btn-outline-elegance { border: 2px solid #b85c1a; color: #b85c1a; border-radius: 40px; font-weight: 600; background: transparent; }
        .btn-outline-elegance:hover { background: #b85c1a; color: white; }
        
        .card-product { transition: transform 0.2s; border: none; border-radius: 1.25rem; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.05); background: white; height: 100%; }
        .card-product:hover { transform: translateY(-6px); box-shadow: 0 20px 25px -12px rgba(0,0,0,0.15); }
        .product-img { height: 220px; object-fit: cover; width: 100%; }
        .price { font-weight: 700; color: #b85c1a; font-size: 1.3rem; }
        .product-description { font-size: 0.85rem; color: #5c5a5a; margin: 0.5rem 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .rating-stars { color: #ffb347; }
        .badge-offer { background: #e85d04; color: white; font-size: 0.7rem; border-radius: 20px; padding: 0.2rem 0.7rem; }
        
        .filter-card { background: white; border-radius: 1.25rem; border: none; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .filter-header { font-weight: 700; padding: 1rem 1.25rem; border-bottom: 1px solid #f0e3d8; }
        .filter-body { padding: 1rem 1.25rem; }
        .filter-tag { background: #f5ede5; border-radius: 30px; padding: 0.3rem 0.9rem; font-size: 0.8rem; margin-right: 0.5rem; margin-bottom: 0.5rem; display: inline-block; cursor: pointer; }
        .filter-tag.active, .filter-tag:hover { background: #b85c1a; color: white; }
        
        .cart-sidebar { max-height: 400px; overflow-y: auto; }
        .cart-item-img { width: 50px; height: 50px; object-fit: cover; border-radius: 12px; }
        .cart-badge { background: #b85c1a; color: white; border-radius: 50%; padding: 0.2rem 0.5rem; font-size: 0.75rem; margin-left: 5px; }
        .user-greeting { background: #b85c1a; color: white; padding: 5px 15px; border-radius: 30px; font-size: 0.9rem; }
        
        footer { background: #1e1b17; color: #cdc6bc; margin-top: 4rem; }
        .section-title { border-left: 6px solid #b85c1a; padding-left: 1rem; font-weight: 700; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg bg-white sticky-top shadow-sm py-2">
    <div class="container">
        <a class="navbar-brand" href="#">
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
                <li class="nav-item"><a class="nav-link fw-semibold" href="#home">Home</a></li>
                <li class="nav-item"><a class="nav-link fw-semibold" href="#products">Products</a></li>
                <li class="nav-item">
                    <button class="btn btn-outline-elegance position-relative" data-bs-toggle="offcanvas" data-bs-target="#cartOffcanvas">
                        <i class="bi bi-bag"></i> Cart <span id="cartCountBadge" class="cart-badge">0</span>
                    </button>
                </li>
                <li class="nav-item" id="authNavItem">
                    <a href="http://localhost/Saloon/pages/login.php" class="btn btn-outline-elegance"><i class="bi bi-person"></i> Sign In</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<section id="home" class="py-5" style="background: linear-gradient(135deg, #fff5ec 0%, #fae6d4 100%);">
    <div class="container py-4">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h1 class="display-5 fw-bold">Premium <span style="color:#b85c1a;">Grooming Equipment</span> & Care</h1>
                <p class="lead">Professional-grade tools, organic products, and luxury salon essentials. Prices in Sri Lankan Rupees (LKR).</p>
                <a href="#products" class="btn btn-elegance btn-lg mt-3"><i class="bi bi-grid-3x3-gap-fill"></i> Shop Now</a>
            </div>
            <div class="col-lg-6 text-center">
                <img src="https://placehold.co/600x400/F5E6D8/B85C1A?text=Saloon+Elegance" class="img-fluid rounded-4 shadow" alt="Saloon Elegance">
            </div>
        </div>
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
                        <div class="form-check mb-2"><input class="form-check-input category-filter" type="checkbox" value="hair care" id="catHair"><label for="catHair">Hair Care</label></div>
                        <div class="form-check mb-2"><input class="form-check-input category-filter" type="checkbox" value="face care" id="catFace"><label for="catFace">Face Care</label></div>
                        <div class="form-check mb-2"><input class="form-check-input category-filter" type="checkbox" value="body care" id="catBody"><label for="catBody">Body Care</label></div>
                        <div class="form-check mb-2"><input class="form-check-input category-filter" type="checkbox" value="foot care" id="catFoot"><label for="catFoot">Foot Care</label></div>
                        <div class="form-check mb-2"><input class="form-check-input category-filter" type="checkbox" value="equipment" id="catEquip"><label for="catEquip">Equipment / Tools</label></div>
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
                <h2 class="section-title mb-0">Premium <span style="color:#b85c1a;">Equipment & Care</span></h2>
                <span id="filteredResultCount" class="badge bg-secondary">0 products</span>
            </div>
            <div class="row g-4" id="productGrid"></div>
        </div>
    </div>
</div>

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

<script>
let products = [];
let cart = [];

function formatLKR(amount) {
    return 'LKR ' + amount.toLocaleString('en-LK', { minimumFractionDigits: 2 });
}

function saveCart() {
    localStorage.setItem('saloonCartLKR', JSON.stringify(cart));
}

function loadCart() {
    const stored = localStorage.getItem('saloonCartLKR');
    cart = stored ? JSON.parse(stored) : [];
    updateCartUI();
}

function addToCart(product, quantity = 1) {
    const existing = cart.find(item => item.id === product.id);
    if (existing) existing.quantity += quantity;
    else cart.push({ id: product.id, name: product.name, price: parseFloat(product.price), quantity, image: product.image });
    saveCart();
    updateCartUI();
    Swal.fire({ icon: 'success', title: 'Added!', text: `${product.name} added to cart`, toast: true, position: 'top-end', showConfirmButton: false, timer: 1500 });
}

function updateCartItemQuantity(id, delta) {
    const idx = cart.findIndex(i => i.id === id);
    if (idx !== -1) {
        let newQty = cart[idx].quantity + delta;
        if (newQty <= 0) cart.splice(idx, 1);
        else cart[idx].quantity = newQty;
        saveCart();
        updateCartUI();
    }
}

function removeCartItem(id) {
    cart = cart.filter(i => i.id !== id);
    saveCart();
    updateCartUI();
}

function clearCart() {
    if (cart.length === 0) return Swal.fire('Cart empty', '', 'info');
    Swal.fire({ title: 'Clear cart?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#b85c1a' }).then(res => {
        if (res.isConfirmed) { cart = []; saveCart(); updateCartUI(); Swal.fire('Cleared!', '', 'success'); }
    });
}

function updateCartUI() {
    const totalItems = cart.reduce((sum, i) => sum + i.quantity, 0);
    $('#cartCountBadge').text(totalItems).toggleClass('d-none', totalItems === 0);
    
    if (cart.length === 0) {
        $('#cartItemsContainer').html(`<div class="text-center py-5"><i class="bi bi-bag-x fs-1"></i><p>Cart empty</p></div>`);
        $('#cartTotalPrice').text(formatLKR(0));
        return;
    }
    
    let total = 0;
    let html = '<ul class="list-group list-group-flush">';
    cart.forEach(item => {
        const itemTotal = item.price * item.quantity;
        total += itemTotal;
        html += `<li class="list-group-item">
            <div class="row align-items-center">
                <div class="col-3"><img src="${item.image}" class="cart-item-img w-100" onerror="this.src='https://placehold.co/50x50/F5E6D8/B85C1A'"></div>
                <div class="col-5"><h6 class="mb-0">${item.name}</h6><small>${formatLKR(item.price)}</small></div>
                <div class="col-4 text-end">
                    <button class="btn btn-sm btn-outline-secondary qty-down" data-id="${item.id}">-</button>
                    <span class="mx-2">${item.quantity}</span>
                    <button class="btn btn-sm btn-outline-secondary qty-up" data-id="${item.id}">+</button>
                    <button class="btn btn-sm text-danger ms-1 remove-item" data-id="${item.id}"><i class="bi bi-trash"></i></button>
                </div>
            </div>
            <div class="text-end mt-1 fw-bold">${formatLKR(itemTotal)}</div>
        </li>`;
    });
    html += '</ul>';
    $('#cartItemsContainer').html(html);
    $('#cartTotalPrice').text(formatLKR(total));
    
    $('.qty-up').off('click').on('click', function() { updateCartItemQuantity(parseInt($(this).data('id')), 1); });
    $('.qty-down').off('click').on('click', function() { updateCartItemQuantity(parseInt($(this).data('id')), -1); });
    $('.remove-item').off('click').on('click', function() { removeCartItem(parseInt($(this).data('id'))); });
}

function checkoutProcess() {
    if (cart.length === 0) {
        Swal.fire('Cart Empty', 'Please add products', 'warning');
        return;
    }
    
    const totalAmount = cart.reduce((sum, i) => sum + (i.price * i.quantity), 0);
    
    $.ajax({
        url: 'check_session_api.php',
        type: 'GET',
        dataType: 'json',
        success: function(sessionRes) {
            if (sessionRes.logged_in) {
                processOrder(sessionRes.user.regID, totalAmount);
            } else {
                Swal.fire({
                    title: 'Login Required',
                    text: 'Please login to complete purchase',
                    icon: 'info',
                    showCancelButton: true,
                    confirmButtonText: 'Login Now'
                }).then(res => {
                    if (res.isConfirmed) window.location.href = 'http://localhost/Saloon/pages/login.php';
                    else processOrder(null, totalAmount);
                });
            }
        },
        error: function() { processOrder(null, totalAmount); }
    });
}

function processOrder(userId, totalAmount) {
    const orderData = {
        user_id: userId,
        total_amount: totalAmount,
        items: cart.map(i => ({ id: i.id, name: i.name, quantity: i.quantity, price: i.price }))
    };
    
    $.ajax({
        url: 'process_order_api.php',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(orderData),
        success: function(response) {
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Order Placed!',
                    html: `Order #${response.order_number}<br>Total: ${formatLKR(totalAmount)}`,
                    confirmButtonColor: '#b85c1a'
                }).then(() => {
                    cart = [];
                    saveCart();
                    updateCartUI();
                    const offcanvas = bootstrap.Offcanvas.getInstance(document.getElementById('cartOffcanvas'));
                    if (offcanvas) offcanvas.hide();
                });
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        },
        error: function() {
            Swal.fire('Error', 'Failed to process order', 'error');
        }
    });
}

function loadProducts() {
    $.ajax({
        url: 'get_products_api.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                products = response.products;
                renderProducts();
            }
        },
        error: function() {
            // Fallback to static data if API fails
            products = [
                { id: 1, name: "Professional Hair Dryer", price: 12500, category: "equipment", rating: 4.8, description: "Professional ionic hair dryer", image: "https://images.unsplash.com/photo-1522338140262-f46f5913618a?w=400&h=300&fit=crop", offerTag: "pro" },
                { id: 2, name: "Face Polisher", price: 8900, category: "face care", rating: 4.7, description: "Ultrasonic face scrubber", image: "https://images.unsplash.com/photo-1596462502278-27bfdc6e39db?w=400&h=300&fit=crop", offerTag: "bestseller" }
            ];
            renderProducts();
        }
    });
}

let activeCategories = new Set();
let minPrice = 0, maxPrice = 50000;
let activeTag = null;

function renderProducts() {
    let filtered = [...products];
    if (activeCategories.size > 0) filtered = filtered.filter(p => activeCategories.has(p.category));
    filtered = filtered.filter(p => p.price >= minPrice && p.price <= maxPrice);
    if (activeTag) filtered = filtered.filter(p => p.offerTag === activeTag);
    
    $('#filteredResultCount').text(filtered.length + ' products');
    
    if (filtered.length === 0) {
        $('#productGrid').html('<div class="col-12 text-center py-5"><i class="bi bi-emoji-frown fs-1"></i><h4>No products found</h4></div>');
        return;
    }
    
    let html = '';
    filtered.forEach(p => {
        const stars = '★'.repeat(Math.floor(p.rating)) + '☆'.repeat(5 - Math.floor(p.rating));
        const offerBadge = p.offerTag ? `<span class="badge-offer ms-2">${p.offerTag === 'sale' ? '🔥 Sale' : p.offerTag === 'bestseller' ? '⭐ Bestseller' : p.offerTag === 'organic' ? '🌿 Organic' : '🛠️ Pro'}</span>` : '';
        html += `
            <div class="col-md-6 col-xl-4">
                <div class="card card-product h-100">
                    <img src="${p.image}" class="product-img" alt="${p.name}" onerror="this.src='https://placehold.co/400x300/F5E6D8/B85C1A'">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between">
                            <h5 class="card-title fw-bold">${p.name}</h5>
                            ${offerBadge}
                        </div>
                        <div class="rating-stars mb-1">${stars} (${p.rating})</div>
                        <p class="product-description">${p.description}</p>
                        <div class="mt-auto">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="price">${formatLKR(p.price)}</span>
                                <button class="btn btn-elegance addToCartBtn" data-id="${p.id}"><i class="bi bi-cart-plus"></i> Add</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    $('#productGrid').html(html);
    
    $('.addToCartBtn').off('click').on('click', function() {
        const pid = parseInt($(this).data('id'));
        const product = products.find(p => p.id === pid);
        if (product) addToCart(product, 1);
    });
}

function initFilters() {
    $('.category-filter').on('change', function() {
        if ($(this).prop('checked')) activeCategories.add($(this).val());
        else activeCategories.delete($(this).val());
        renderProducts();
    });
    
    $('#applyPriceBtn').on('click', function() {
        minPrice = parseFloat($('#minPrice').val()) || 0;
        maxPrice = parseFloat($('#maxPrice').val()) || 50000;
        renderProducts();
    });
    
    $('.filter-tag').on('click', function() {
        const tag = $(this).data('tag');
        activeTag = activeTag === tag ? null : tag;
        $('.filter-tag').removeClass('active');
        if (activeTag) $(`.filter-tag[data-tag="${activeTag}"]`).addClass('active');
        renderProducts();
    });
    
    $('#clearAllFilters').on('click', function() {
        activeCategories.clear();
        $('.category-filter').prop('checked', false);
        $('#minPrice').val(0);
        $('#maxPrice').val(50000);
        minPrice = 0;
        maxPrice = 50000;
        activeTag = null;
        $('.filter-tag').removeClass('active');
        renderProducts();
    });
}

function checkUserSession() {
    $.ajax({
        url: 'check_session_api.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.logged_in && response.user) {
                $('#authNavItem').html(`<span class="user-greeting"><i class="bi bi-person-circle"></i> ${response.user.fName}</span>`);
            }
        }
    });
}

$(document).ready(function() {
    loadProducts();
    initFilters();
    loadCart();
    checkUserSession();
    $('#clearCartBtn').on('click', clearCart);
    $('#checkoutBtn').on('click', checkoutProcess);
    
    $('a[href^="#"]').on('click', function(e) {
        const target = $(this.getAttribute('href'));
        if (target.length) { e.preventDefault(); $('html, body').animate({ scrollTop: target.offset().top - 80 }, 600); }
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
                    <li><i class="bi bi-geo-alt"></i> Colombo 03, Sri Lanka</li>
                </ul>
            </div>
            <div class="col-md-4 mb-4">
                <h6 class="text-white">Follow Us</h6>
                <div class="fs-4"><i class="bi bi-instagram me-3 text-secondary"></i><i class="bi bi-facebook me-3 text-secondary"></i><i class="bi bi-whatsapp text-secondary"></i></div>
            </div>
        </div>
        <hr class="bg-secondary">
        <div class="text-center text-secondary small">© 2025 Saloon Elegance — Premium Grooming Equipment & Care, Sri Lanka</div>
    </div>
</footer>
</body>
</html>