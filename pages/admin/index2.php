<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>Saloon Elegance | Premium Equipment & Care</title>
  <!-- Bootstrap 5 + Icons + Google Fonts -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,600;14..32,700&display=swap" rel="stylesheet">
  <!-- SweetAlert2 -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <!-- jQuery -->
  <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
  <style>
    * {
      font-family: 'Inter', sans-serif;
    }
    body {
      background: #faf6f0;
    }
    .navbar-brand {
      font-weight: 700;
      letter-spacing: -0.3px;
      font-size: 1.8rem;
      background: linear-gradient(135deg, #b85c1a, #e09d5e);
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
    }
    .hero {
      background: linear-gradient(120deg, #fff3e6 0%, #fae9db 100%);
      border-radius: 0 0 2rem 2rem;
      padding: 1.5rem 0;
      margin-bottom: 2rem;
    }
    .card-product {
      transition: transform 0.2s, box-shadow 0.2s;
      border: none;
      border-radius: 1.25rem;
      overflow: hidden;
      box-shadow: 0 5px 15px rgba(0,0,0,0.05);
      background: white;
      height: 100%;
    }
    .card-product:hover {
      transform: translateY(-6px);
      box-shadow: 0 20px 25px -12px rgba(0,0,0,0.15);
    }
    .product-img {
      height: 220px;
      object-fit: cover;
      background: #f9ede2;
    }
    .price {
      font-weight: 700;
      color: #b85c1a;
      font-size: 1.4rem;
    }
    .btn-elegance {
      background: #b85c1a;
      color: white;
      border-radius: 40px;
      padding: 8px 20px;
      font-weight: 600;
      transition: all 0.2s;
      border: none;
    }
    .btn-elegance:hover {
      background: #9b4512;
      transform: scale(1.02);
      color: white;
    }
    .btn-outline-elegance {
      border: 2px solid #b85c1a;
      color: #b85c1a;
      border-radius: 40px;
      font-weight: 600;
    }
    .btn-outline-elegance:hover {
      background: #b85c1a;
      color: white;
    }
    .cart-badge {
      background: #b85c1a;
      color: white;
      border-radius: 50%;
      padding: 0.2rem 0.5rem;
      font-size: 0.75rem;
      margin-left: 5px;
    }
    footer {
      background: #2d2a24;
      color: #cdc6bc;
      margin-top: 4rem;
    }
    .cart-sidebar {
      max-height: 400px;
      overflow-y: auto;
    }
    .cart-item-img {
      width: 50px;
      height: 50px;
      object-fit: cover;
      border-radius: 12px;
    }
    .empty-cart-msg {
      color: #a1826b;
    }
    .section-title {
      border-left: 6px solid #b85c1a;
      padding-left: 1rem;
      font-weight: 700;
    }
    /* Filter sidebar */
    .filter-card {
      background: white;
      border-radius: 1.25rem;
      border: none;
      box-shadow: 0 5px 15px rgba(0,0,0,0.05);
      margin-bottom: 1.5rem;
    }
    .filter-header {
      font-weight: 700;
      cursor: pointer;
      padding: 1rem 1.25rem;
      border-bottom: 1px solid #f0e3d8;
    }
    .filter-body {
      padding: 1rem 1.25rem;
    }
    .rating-stars {
      color: #ffb347;
      letter-spacing: 2px;
      font-size: 0.9rem;
    }
    .product-description {
      font-size: 0.85rem;
      color: #5c5a5a;
      margin: 0.5rem 0;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }
    .badge-offer {
      background: #e85d04;
      color: white;
      font-size: 0.7rem;
      border-radius: 20px;
      padding: 0.2rem 0.7rem;
    }
    .filter-tag {
      background: #f5ede5;
      border-radius: 30px;
      padding: 0.3rem 0.9rem;
      font-size: 0.8rem;
      margin-right: 0.5rem;
      margin-bottom: 0.5rem;
      display: inline-block;
      cursor: pointer;
      transition: all 0.2s;
    }
    .filter-tag.active, .filter-tag:hover {
      background: #b85c1a;
      color: white;
    }
    .price-range-slider {
      width: 100%;
    }
    .clear-filters {
      font-size: 0.8rem;
      cursor: pointer;
      color: #b85c1a;
    }
  </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg bg-white sticky-top shadow-sm py-2">
  <div class="container">
    <a class="navbar-brand" href="#"><i class="bi bi-scissors me-2"></i>Saloon Elegance</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarMain">
      <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-center">
        <li class="nav-item"><a class="nav-link active" href="#">Home</a></li>
        <li class="nav-item"><a class="nav-link" href="#products">Equipment & Care</a></li>
        <li class="nav-item">
          <button class="btn btn-outline-elegance position-relative" id="cartPreviewBtn" data-bs-toggle="offcanvas" data-bs-target="#cartOffcanvas">
            <i class="bi bi-bag"></i> Cart 
            <span id="cartCountBadge" class="cart-badge">0</span>
          </button>
        </li>
      </ul>
    </div>
  </div>
</nav>

<!-- HERO SECTION -->
<div class="hero">
  <div class="container py-4">
    <div class="row align-items-center g-4">
      <div class="col-lg-6">
        <h1 class="display-5 fw-bold">Elite <span style="color:#b85c1a;">Grooming Equipment</span></h1>
        <p class="lead">Discover professional-grade tools, organic care products & exclusive salon offers. Filter by category, price, and find your perfect match.</p>
        <button class="btn btn-elegance btn-lg mt-2" id="exploreBtn"><i class="bi bi-grid-3x3-gap-fill"></i> Browse Collections</button>
      </div>
      <div class="col-lg-6 text-center">
        <img src="https://placehold.co/600x400/F5E6D8/B85C1A?text=Saloon+Elegance+Equipment" class="img-fluid rounded-4 shadow" alt="Saloon Elegance">
      </div>
    </div>
  </div>
</div>

<!-- MAIN CONTENT WITH SIDEBAR FILTERS -->
<div class="container my-5" id="products">
  <div class="row g-4">
    <!-- LEFT SIDEBAR FILTERS -->
    <div class="col-lg-3">
      <div class="filter-card">
        <div class="filter-header d-flex justify-content-between align-items-center">
          <span><i class="bi bi-funnel-fill me-2"></i>Filter Products</span>
          <span class="clear-filters" id="clearAllFilters"><i class="bi bi-x-circle"></i> Reset</span>
        </div>
        <div class="filter-body">
          <!-- Category Filter -->
          <h6 class="fw-bold mb-2">Category</h6>
          <div id="categoryFilterGroup" class="mb-4">
            <div class="form-check mb-2">
              <input class="form-check-input category-filter" type="checkbox" value="body care" id="catBody">
              <label class="form-check-label" for="catBody">Body Care</label>
            </div>
            <div class="form-check mb-2">
              <input class="form-check-input category-filter" type="checkbox" value="face care" id="catFace">
              <label class="form-check-label" for="catFace">Face Care</label>
            </div>
            <div class="form-check mb-2">
              <input class="form-check-input category-filter" type="checkbox" value="foot care" id="catFoot">
              <label class="form-check-label" for="catFoot">Foot Care</label>
            </div>
            <div class="form-check mb-2">
              <input class="form-check-input category-filter" type="checkbox" value="hair care" id="catHair">
              <label class="form-check-label" for="catHair">Hair Care</label>
            </div>
            <div class="form-check mb-2">
              <input class="form-check-input category-filter" type="checkbox" value="equipment" id="catEquip">
              <label class="form-check-label" for="catEquip">Equipment / Tools</label>
            </div>
          </div>
          
          <!-- Price Range -->
          <h6 class="fw-bold mb-2">Price Range ($)</h6>
          <div class="mb-3">
            <div class="d-flex gap-2">
              <input type="number" id="minPrice" class="form-control form-control-sm" placeholder="Min" value="0">
              <span>-</span>
              <input type="number" id="maxPrice" class="form-control form-control-sm" placeholder="Max" value="300">
            </div>
            <button class="btn btn-sm btn-outline-secondary mt-2 w-100" id="applyPriceBtn">Apply Price</button>
          </div>
          
          <!-- Offer Tags / Product Tags -->
          <h6 class="fw-bold mb-2">Special Tags</h6>
          <div id="offerTagsContainer">
            <span class="filter-tag" data-tag="sale">🔥 Sale</span>
            <span class="filter-tag" data-tag="bestseller">⭐ Bestseller</span>
            <span class="filter-tag" data-tag="organic">🌿 Organic</span>
            <span class="filter-tag" data-tag="pro">🛠️ Pro Equipment</span>
          </div>
        </div>
      </div>
    </div>

    <!-- RIGHT PRODUCT GRID -->
    <div class="col-lg-9">
      <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
        <h2 class="section-title mb-0">Premium <span style="color:#b85c1a;">Equipment & Care</span></h2>
        <div class="mt-2 mt-sm-0">
          <span id="filteredResultCount" class="badge bg-secondary">0 products</span>
        </div>
      </div>
      <div class="row g-4" id="productGrid">
        <!-- products injected dynamically -->
      </div>
    </div>
  </div>
</div>

<!-- CART OFF-CANVAS (same as before) -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="cartOffcanvas" aria-labelledby="cartOffcanvasLabel">
  <div class="offcanvas-header bg-white border-bottom">
    <h5 class="offcanvas-title fw-bold" id="cartOffcanvasLabel"><i class="bi bi-bag-check me-2"></i>Your Cart</h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body p-0">
    <div id="cartItemsContainer" class="cart-sidebar p-3"></div>
    <div class="border-top p-3 bg-light">
      <div class="d-flex justify-content-between fw-bold fs-5 mb-3">
        <span>Total:</span>
        <span id="cartTotalPrice">$0.00</span>
      </div>
      <button class="btn btn-elegance w-100 py-2" id="checkoutBtn"><i class="bi bi-credit-card"></i> Proceed to Checkout</button>
      <button class="btn btn-outline-secondary w-100 mt-2" id="clearCartBtn"><i class="bi bi-trash3"></i> Clear Cart</button>
    </div>
  </div>
</div>

<script>
  // ---------- EXTENDED PRODUCT DATA with ratings, description, category, offerTag ----------
  const products = [
    { id: 1, name: "Ultra-Glide Face Polisher", price: 89.99, category: "face care", rating: 4.8, description: "Professional ultrasonic face scrubber with 3 brush heads. Removes dead skin & boosts glow.", image: "https://placehold.co/400x300/D9C2A7/8B5A2B?text=Face+Polisher", offerTag: "bestseller" },
    { id: 2, name: "Aromatherapy Foot Massager", price: 129.50, category: "foot care", rating: 4.9, description: "Shiatsu rolling massager with heat and remote control. Perfect after long day.", image: "https://placehold.co/400x300/C9A87C/5C3A1E?text=Foot+Massager", offerTag: "sale" },
    { id: 3, name: "Ionic Hair Dryer", price: 149.00, category: "hair care", rating: 4.7, description: "Far-infrared technology, reduces frizz and speeds drying time. Salon-grade motor.", image: "https://placehold.co/400x300/BF9A6A/FFFFFF?text=Hair+Dryer", offerTag: "pro" },
    { id: 4, name: "Organic Shea Body Butter", price: 34.90, category: "body care", rating: 4.6, description: "Deeply nourishing, 98% organic ingredients. Restores elasticity & softness.", image: "https://placehold.co/400x300/D9C2A7/4A2E1B?text=Body+Butter", offerTag: "organic" },
    { id: 5, name: "LED Light Therapy Mask", price: 199.99, category: "face care", rating: 5.0, description: "7 color light therapy for anti-aging, acne, and rejuvenation. FDA registered.", image: "https://placehold.co/400x300/E3CFB4/8B4513?text=LED+Mask", offerTag: "bestseller" },
    { id: 6, name: "Professional Hair Clipper Kit", price: 79.99, category: "equipment", rating: 4.8, description: "Cordless precision trimmer with titanium blades. 8 guide combs, barber grade.", image: "https://placehold.co/400x300/C6AB85/2C3E50?text=Clipper+Kit", offerTag: "pro" },
    { id: 7, name: "Volcanic Foot Scrub", price: 22.30, category: "foot care", rating: 4.5, description: "Exfoliating pumice cream with peppermint. Softens calluses instantly.", image: "https://placehold.co/400x300/BC9A6A/5A3E28?text=Foot+Scrub", offerTag: "sale" },
    { id: 8, name: "Keratin Hair Mask", price: 45.00, category: "hair care", rating: 4.9, description: "Restorative mask with keratin & argan oil. Repairs split ends, adds shine.", image: "https://placehold.co/400x300/D4B28C/6B4226?text=Hair+Mask", offerTag: "organic" },
    { id: 9, name: "Infrared Body Wrap", price: 279.00, category: "body care", rating: 4.7, description: "Detox and slim waist wrap, uses far-infrared technology for home spa.", image: "https://placehold.co/400x300/EED9C4/8B5A2B?text=Body+Wrap", offerTag: "sale" }
  ];

  let cart = [];

  // Helper functions for cart (identical logic but improved)
  function saveCartToLocal() { localStorage.setItem("saloonEleganceCart", JSON.stringify(cart)); }
  function loadCartFromLocal() {
    const stored = localStorage.getItem("saloonEleganceCart");
    cart = stored ? JSON.parse(stored) : [];
    updateCartUI();
  }

  function addToCart(product, quantity = 1) {
    const existingIndex = cart.findIndex(item => item.id === product.id);
    if(existingIndex !== -1) cart[existingIndex].quantity += quantity;
    else cart.push({ id: product.id, name: product.name, price: product.price, quantity, image: product.image });
    saveCartToLocal();
    updateCartUI();
    Swal.fire({ icon: 'success', title: 'Added', text: `${product.name} added to cart`, toast: true, position: 'top-end', showConfirmButton: false, timer: 1500 });
  }

  function updateCartItemQuantity(id, delta) {
    const idx = cart.findIndex(i => i.id === id);
    if(idx !== -1) {
      let newQty = cart[idx].quantity + delta;
      if(newQty <= 0) cart.splice(idx,1);
      else cart[idx].quantity = newQty;
      saveCartToLocal();
      updateCartUI();
    }
  }
  function removeCartItem(id) { cart = cart.filter(i => i.id !== id); saveCartToLocal(); updateCartUI(); Swal.fire({ icon: 'info', title: 'Removed', toast: true, timer: 1200, showConfirmButton: false });}
  function clearCart() {
    if(cart.length === 0) return Swal.fire('Cart empty', 'Nothing to clear', 'info');
    Swal.fire({ title: 'Clear cart?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#b85c1a', confirmButtonText: 'Yes' }).then(res => { if(res.isConfirmed) { cart = []; saveCartToLocal(); updateCartUI(); Swal.fire('Cleared!', '', 'success'); } });
  }

  function updateCartUI() {
    const totalItems = cart.reduce((sum, i) => sum + i.quantity, 0);
    $('#cartCountBadge').text(totalItems).toggleClass('d-none', totalItems === 0);
    if(cart.length === 0) {
      $('#cartItemsContainer').html(`<div class="text-center py-5 empty-cart-msg"><i class="bi bi-bag-x fs-1"></i><p class="mt-3">Cart is empty.</p></div>`);
      $('#cartTotalPrice').text('$0.00');
      return;
    }
    let cartHtml = `<ul class="list-group list-group-flush bg-transparent">`;
    let total = 0;
    cart.forEach(item => {
      const itemTotal = item.price * item.quantity;
      total += itemTotal;
      cartHtml += `<li class="list-group-item bg-transparent px-0 border-bottom">
          <div class="row g-2 align-items-center">
            <div class="col-3"><img src="${item.image}" class="cart-item-img w-100" style="max-width:60px;"></div>
            <div class="col-6"><h6 class="mb-0">${item.name}</h6><small>$${item.price.toFixed(2)}</small>
              <div><button class="btn btn-sm btn-outline-secondary qty-down" data-id="${item.id}">-</button><span class="mx-2">${item.quantity}</span>
              <button class="btn btn-sm btn-outline-secondary qty-up" data-id="${item.id}">+</button>
              <button class="btn btn-sm text-danger remove-item" data-id="${item.id}"><i class="bi bi-trash"></i></button></div>
            </div>
            <div class="col-3 text-end"><strong>$${itemTotal.toFixed(2)}</strong></div>
          </div></li>`;
    });
    cartHtml += `</ul>`;
    $('#cartItemsContainer').html(cartHtml);
    $('#cartTotalPrice').text(`$${total.toFixed(2)}`);
    $('.qty-up').off('click').on('click', function(){ updateCartItemQuantity(parseInt($(this).data('id')), 1); });
    $('.qty-down').off('click').on('click', function(){ updateCartItemQuantity(parseInt($(this).data('id')), -1); });
    $('.remove-item').off('click').on('click', function(){ removeCartItem(parseInt($(this).data('id'))); });
  }

  function checkoutProcess() {
    if(cart.length === 0) return Swal.fire({ icon: 'error', title: 'Empty Cart', text: 'Add products first', confirmButtonColor: '#b85c1a' });
    const totalAmount = cart.reduce((sum, i) => sum + (i.price * i.quantity), 0).toFixed(2);
    Swal.fire({ title: 'Elegant Checkout', html: `<p><strong>Order Total: $${totalAmount}</strong></p><p>Demo order would be processed via PHP/MySQL backend.</p>`, icon: 'info', showCancelButton: true, confirmButtonText: 'Place Order' }).then(res => {
      if(res.isConfirmed) {
        Swal.fire({ icon: 'success', title: 'Order Placed!', text: 'Thank you for shopping at Saloon Elegance.', confirmButtonColor: '#b85c1a' });
        cart = []; saveCartToLocal(); updateCartUI();
        const offcanvasEl = document.getElementById('cartOffcanvas');
        const bsOffcanvas = bootstrap.Offcanvas.getInstance(offcanvasEl);
        if(bsOffcanvas) bsOffcanvas.hide();
      }
    });
  }

  // FILTERING LOGIC
  let activeCategories = new Set();
  let minPriceVal = 0, maxPriceVal = 300;
  let activeTag = null;

  function renderFilteredProducts() {
    let filtered = [...products];
    // category filter
    if(activeCategories.size > 0) {
      filtered = filtered.filter(p => activeCategories.has(p.category));
    }
    // price filter
    filtered = filtered.filter(p => p.price >= minPriceVal && p.price <= maxPriceVal);
    // tag filter
    if(activeTag) {
      filtered = filtered.filter(p => p.offerTag === activeTag);
    }
    $('#filteredResultCount').text(`${filtered.length} products`);
    if(filtered.length === 0) {
      $('#productGrid').html(`<div class="col-12 text-center py-5"><i class="bi bi-emoji-frown fs-1"></i><h4 class="mt-3">No products match</h4><p>Try adjusting filters.</p></div>`);
      return;
    }
    let html = '';
    filtered.forEach(prod => {
      const stars = '★'.repeat(Math.floor(prod.rating)) + '☆'.repeat(5-Math.floor(prod.rating));
      let offerBadge = prod.offerTag ? `<span class="badge-offer ms-2">${prod.offerTag === 'sale' ? '🔥 Sale' : prod.offerTag === 'bestseller' ? '⭐ Bestseller' : prod.offerTag === 'organic' ? '🌿 Organic' : '🛠️ Pro'}</span>` : '';
      html += `
        <div class="col-md-6 col-xl-4">
          <div class="card card-product h-100">
            <img src="${prod.image}" class="card-img-top product-img" alt="${prod.name}">
            <div class="card-body d-flex flex-column">
              <div class="d-flex justify-content-between align-items-start">
                <h5 class="card-title fw-bold">${prod.name}</h5>
                ${offerBadge}
              </div>
              <div class="rating-stars mb-1">${stars} <span class="small text-muted">(${prod.rating})</span></div>
              <p class="product-description">${prod.description}</p>
              <div class="mt-auto d-flex justify-content-between align-items-center pt-2">
                <span class="price">$${prod.price.toFixed(2)}</span>
                <button class="btn btn-elegance addToCartBtn" data-id="${prod.id}"><i class="bi bi-cart-plus"></i> Add</button>
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
      if(product) addToCart(product, 1);
    });
  }

  function initFilters() {
    // category checkboxes
    $('.category-filter').on('change', function() {
      const val = $(this).val();
      if($(this).prop('checked')) activeCategories.add(val);
      else activeCategories.delete(val);
      renderFilteredProducts();
    });
    $('#applyPriceBtn').on('click', function() {
      let min = parseFloat($('#minPrice').val());
      let max = parseFloat($('#maxPrice').val());
      if(isNaN(min)) min = 0;
      if(isNaN(max)) max = 300;
      minPriceVal = min;
      maxPriceVal = max;
      renderFilteredProducts();
    });
    $('.filter-tag').on('click', function() {
      const tag = $(this).data('tag');
      if(activeTag === tag) activeTag = null;
      else activeTag = tag;
      $('.filter-tag').removeClass('active');
      if(activeTag) $(`.filter-tag[data-tag="${activeTag}"]`).addClass('active');
      renderFilteredProducts();
    });
    $('#clearAllFilters').on('click', function() {
      activeCategories.clear();
      $('.category-filter').prop('checked', false);
      $('#minPrice').val(0);
      $('#maxPrice').val(300);
      minPriceVal = 0; maxPriceVal = 300;
      activeTag = null;
      $('.filter-tag').removeClass('active');
      renderFilteredProducts();
    });
  }

  $(document).ready(function() {
    renderFilteredProducts();
    initFilters();
    loadCartFromLocal();
    $('#clearCartBtn').on('click', clearCart);
    $('#checkoutBtn').on('click', checkoutProcess);
    $('#exploreBtn').on('click', () => $('html, body').animate({ scrollTop: $('#products').offset().top - 80 }, 600));
  });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

<footer class="pt-5 pb-4">
  <div class="container">
    <div class="row">
      <div class="col-md-4"><h5 class="text-white"><i class="bi bi-scissors"></i> Saloon Elegance</h5><p>Professional equipment, premium care & luxury offers.</p></div>
      <div class="col-md-3"><h6>Quick Links</h6><ul class="list-unstyled"><li><a href="#" class="text-secondary text-decoration-none">New Equipment</a></li><li><a href="#" class="text-secondary text-decoration-none">Gift Sets</a></li></ul></div>
      <div class="col-md-3"><h6>Contact</h6><p class="text-secondary"><i class="bi bi-envelope"></i> care@salonelegance.com</p></div>
      <div class="col-md-2"><h6>Follow</h6><div class="fs-4"><i class="bi bi-instagram me-2 text-secondary"></i><i class="bi bi-facebook text-secondary"></i></div></div>
    </div>
    <hr class="bg-secondary"><div class="text-center text-secondary small">© 2025 Saloon Elegance — Refined Grooming Equipment & Care</div>
  </div>
</footer>
</body>
</html>