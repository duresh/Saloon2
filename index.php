<?php
session_start();
require_once 'includes/dbcon.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$userName = '';
$userEmail = '';
$userRole = '';
$userFname = '';

if ($isLoggedIn && isset($_SESSION['user_id'])) {
    try {
        $pdo = getPDOConnection();
        $stmt = $pdo->prepare("SELECT fName, lName, email, role FROM reg WHERE regID = ? AND cStatus = 1");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        if ($user) {
            $userName = $user['fName'] . ' ' . $user['lName'];
            $userEmail = $user['email'];
            $userRole = $user['role'];
            $userFname = $user['fName'];
            
            // Update session with latest role
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['role'] = $user['role'];
        } else {
            // User not found in database, clear session
            session_destroy();
            $isLoggedIn = false;
        }
    } catch (Exception $e) {
        // Ignore error
    }
}

// Handle logout if requested
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit();
}

// Get dashboard link based on role
function getDashboardLink($role) {
    switch ($role) {
        case 'admin':
            return 'pages/admin/dashboard.php';
        case 'staff':
            return 'pages/admin/staff-dashboard.php';
        default:
            return 'pages/users/dashboard.php';
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
    <title>Saloon Elegance | Luxury Grooming & Spa - Sri Lanka</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;600;700;800&display=swap" rel="stylesheet">
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
            padding: 8px 0;
        }
        .logo-icon { font-size: 2.2rem; background: linear-gradient(135deg, #b85c1a, #e09d5e); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .logo-main { font-size: 1.6rem; font-weight: 800; background: linear-gradient(135deg, #b85c1a, #e09d5e); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .logo-tagline { font-size: 0.7rem; color: #a1826b; letter-spacing: 1px; }
        
        .btn-elegance { background: #b85c1a; color: white; border-radius: 40px; padding: 10px 28px; font-weight: 600; border: none; }
        .btn-elegance:hover { background: #9b4512; transform: translateY(-2px); }
        .btn-outline-elegance { border: 2px solid #b85c1a; color: #b85c1a; border-radius: 40px; font-weight: 600; background: transparent; }
        .btn-outline-elegance:hover { background: #b85c1a; color: white; }
        
        .hero-full {
            background: linear-gradient(135deg, #fff5ec 0%, #fae6d4 100%);
            min-height: 85vh;
            display: flex;
            align-items: center;
        }
        .hero-title { font-size: 3.5rem; font-weight: 800; line-height: 1.2; }
        .highlight { color: #b85c1a; position: relative; display: inline-block; }
        .highlight::after {
            content: '';
            position: absolute;
            bottom: 5px;
            left: 0;
            width: 100%;
            height: 8px;
            background: #f0c9ae;
            z-index: -1;
            border-radius: 10px;
        }
        
        .service-card {
            background: white;
            border-radius: 1.5rem;
            padding: 2rem 1.5rem;
            text-align: center;
            transition: all 0.3s;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            height: 100%;
        }
        .service-card:hover { transform: translateY(-8px); }
        .service-icon { font-size: 3rem; color: #b85c1a; margin-bottom: 1rem; }
        .section-title {
            position: relative;
            display: inline-block;
            font-weight: 800;
            margin-bottom: 2rem;
        }
        .section-title:after {
            content: '';
            position: absolute;
            bottom: -12px;
            left: 50%;
            transform: translateX(-50%);
            width: 70px;
            height: 4px;
            background: #b85c1a;
            border-radius: 4px;
        }
        
        .testimonial-card {
            background: white;
            border-radius: 1.5rem;
            padding: 1.8rem;
            box-shadow: 0 8px 20px rgba(0,0,0,0.05);
            height: 100%;
        }
        .customer-img {
            width: 65px;
            height: 65px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid #b85c1a;
        }
        .rating-stars { color: #ffb347; }
        
        .gallery-img {
            border-radius: 1.2rem;
            transition: all 0.4s;
            width: 100%;
            height: 250px;
            object-fit: cover;
        }
        .gallery-img:hover { transform: scale(1.02); }
        
        .booking-card {
            background: white;
            border-radius: 1.8rem;
            box-shadow: 0 20px 40px rgba(0,0,0,0.08);
            padding: 2rem;
        }
        footer { background: #1e1b17; color: #cdc6bc; margin-top: 4rem; }
        .user-greeting { background: #b85c1a; color: white; padding: 5px 15px; border-radius: 30px; font-size: 0.9rem; }
        .cart-badge { background: #b85c1a; color: white; border-radius: 50%; padding: 0.2rem 0.5rem; font-size: 0.75rem; margin-left: 5px; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg bg-white sticky-top shadow-sm py-2">
    <div class="container">
        <a class="navbar-brand" href="index.php">
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
                <li class="nav-item"><a class="nav-link fw-semibold" href="#services">Services</a></li>
                <li class="nav-item"><a class="nav-link fw-semibold" href="#testimonials">Clients</a></li>
                <li class="nav-item"><a class="nav-link fw-semibold" href="#gallery">Moments</a></li>
                <li class="nav-item"><a class="nav-link fw-semibold" href="pages/shop.php">Shop</a></li>
                <li class="nav-item"><a class="btn btn-elegance" href="#booking">Book Now</a></li>
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
                                <li><a class="dropdown-item" href="pages/shop.php"><i class="bi bi-bag"></i> Shop Products</a></li>
                                <li><a class="dropdown-item" href="#booking"><i class="bi bi-calendar"></i> My Appointments</a></li>
                                <li><a class="dropdown-item" href="pages/shop.php?view=orders"><i class="bi bi-receipt"></i> My Orders</a></li>
                                <li><a class="dropdown-item" href="#"><i class="bi bi-person"></i> My Profile</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="?logout=1"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="pages/login.php?redirect=<?php echo urlencode('index.php#booking'); ?>" class="btn btn-outline-elegance"><i class="bi bi-person"></i> Sign In</a>
                    <?php endif; ?>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Hero Section -->
<section id="home" class="hero-full">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <span class="badge bg-warning text-dark mb-3 py-2 px-3 rounded-pill"><i class="bi bi-trophy-fill"></i> Award-Winning Salon 2025</span>
                <h1 class="hero-title mb-4">Where <span class="highlight">Elegance</span> Meets <br>Art of Grooming</h1>
                <p class="lead mb-4">Experience the pinnacle of luxury hair, face, body treatments and barbering.</p>
                <div class="d-flex gap-3 flex-wrap">
                    <a href="#booking" class="btn btn-elegance btn-lg"><i class="bi bi-calendar-check"></i> Book Appointment</a>
                    <a href="pages/shop.php" class="btn btn-outline-elegance btn-lg"><i class="bi bi-bag"></i> Shop Products</a>
                </div>
                <div class="row mt-5 g-3">
                    <div class="col-4"><i class="bi bi-star-fill text-warning"></i> 500+ 5-star reviews</div>
                    <div class="col-4"><i class="bi bi-scissors"></i> 15+ Expert stylists</div>
                    <div class="col-4"><i class="bi bi-cup-hot"></i> Complimentary refreshments</div>
                </div>
            </div>
            <div class="col-lg-6 text-center">
                <img src="https://placehold.co/600x550/F5E6D8/B85C1A?text=Luxury+Saloon+Ambience" class="img-fluid rounded-4 shadow-lg" alt="Saloon Elegance">
            </div>
        </div>
    </div>
</section>

<!-- Services Section -->
<section id="services" class="py-5">
    <div class="container py-4">
        <div class="text-center mb-5">
            <h2 class="section-title">Our Signature <span style="color:#b85c1a;">Services</span></h2>
            <p class="text-muted mx-auto" style="max-width: 650px;">Premium treatments tailored to bring out your best version.</p>
        </div>
        <div class="row g-4">
            <div class="col-md-6 col-lg-4">
                <div class="service-card">
                    <div class="service-icon"><i class="bi bi-scissors"></i></div>
                    <h4>Precision Haircut & Styling</h4>
                    <p class="text-muted">Expert scissor work, modern fades, and bespoke styling.</p>
                    <span class="badge bg-dark">From LKR 4,500</span>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="service-card">
                    <div class="service-icon"><i class="bi bi-droplet"></i></div>
                    <h4>Luxury Facial & Skin Renewal</h4>
                    <p class="text-muted">Deep cleansing, organic masks, and anti-aging therapy.</p>
                    <span class="badge bg-dark">From LKR 6,500</span>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="service-card">
                    <div class="service-icon"><i class="bi bi-gem"></i></div>
                    <h4>Royal Shave Experience</h4>
                    <p class="text-muted">Hot towel ritual, straight razor shave with premium oils.</p>
                    <span class="badge bg-dark">From LKR 5,500</span>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="service-card">
                    <div class="service-icon"><i class="bi bi-flower1"></i></div>
                    <h4>Aromatherapy Massage</h4>
                    <p class="text-muted">Full body relaxation using essential oils and reflexology.</p>
                    <span class="badge bg-dark">From LKR 8,500</span>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="service-card">
                    <div class="service-icon"><i class="bi bi-palette"></i></div>
                    <h4>Hair Coloring & Balayage</h4>
                    <p class="text-muted">Ammonia-free colors, highlights, ombre effects.</p>
                    <span class="badge bg-dark">From LKR 12,000</span>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="service-card">
                    <div class="service-icon"><i class="bi bi-hand-index-thumb"></i></div>
                    <h4>Manicure & Pedicure</h4>
                    <p class="text-muted">Spa mani-pedi with paraffin wax and gel polish options.</p>
                    <span class="badge bg-dark">From LKR 5,000</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Testimonials -->
<section id="testimonials" class="py-5" style="background: #fef4ea;">
    <div class="container py-4">
        <div class="text-center mb-5">
            <h2 class="section-title">What Our <span style="color:#b85c1a;">Guests Say</span></h2>
        </div>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="testimonial-card">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <img src="https://randomuser.me/api/portraits/women/68.jpg" class="customer-img" alt="client">
                        <div><h6 class="mb-0 fw-bold">Emily Rodriguez</h6><small class="text-muted">Regular client</small></div>
                    </div>
                    <div class="rating-stars mb-2">★★★★★</div>
                    <p class="fst-italic">"Absolutely divine! The royal shave and facial transformed my skin."</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="testimonial-card">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <img src="https://randomuser.me/api/portraits/men/32.jpg" class="customer-img" alt="client">
                        <div><h6 class="mb-0 fw-bold">Michael Chen</h6><small class="text-muted">since 2023</small></div>
                    </div>
                    <div class="rating-stars mb-2">★★★★★</div>
                    <p class="fst-italic">"Best haircut experience in town. Highly recommend the signature cut!"</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="testimonial-card">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <img src="https://randomuser.me/api/portraits/women/45.jpg" class="customer-img" alt="client">
                        <div><h6 class="mb-0 fw-bold">Sophia Laurent</h6><small class="text-muted">Spa lover</small></div>
                    </div>
                    <div class="rating-stars mb-2">★★★★★</div>
                    <p class="fst-italic">"The aromatherapy massage is pure heaven. Already booked my next session."</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Gallery -->
<section id="gallery" class="py-5">
    <div class="container py-4">
        <div class="text-center mb-5">
            <h2 class="section-title">Moments of <span style="color:#b85c1a;">Elegance</span></h2>
        </div>
        <div class="row g-3">
            <div class="col-md-4"><img src="https://placehold.co/600x400/F0DCC6/B85C1A?text=Hair+Styling+Zone" class="gallery-img" alt="Salon interior"></div>
            <div class="col-md-4"><img src="https://placehold.co/600x400/EED9C4/B85C1A?text=Relaxation+Room" class="gallery-img" alt="Spa area"></div>
            <div class="col-md-4"><img src="https://placehold.co/600x400/F5E6D8/B85C1A?text=Premium+Tools" class="gallery-img" alt="Professional tools"></div>
        </div>
    </div>
</section>

<!-- Booking -->
<section id="booking" class="py-5" style="background: #fef4ea;">
    <div class="container py-4">
        <div class="row g-5">
            <div class="col-lg-7">
                <div class="booking-card">
                    <h3 class="fw-bold mb-3"><i class="bi bi-calendar-heart me-2" style="color:#b85c1a;"></i> Schedule Your Experience</h3>
                    <form id="appointmentForm" action="pages/book_appointment.php" method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Full Name</label>
                            <input type="text" class="form-control" name="fullname" id="apptName" required placeholder="John Doe" value="<?php echo $isLoggedIn ? htmlspecialchars($userName) : ''; ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Email Address</label>
                            <input type="email" class="form-control" name="email" id="apptEmail" required placeholder="hello@example.com" value="<?php echo $isLoggedIn ? htmlspecialchars($userEmail) : ''; ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Select Service</label>
                            <select class="form-select" name="service" id="apptService">
                                <option value="Precision Haircut & Styling">Precision Haircut & Styling (LKR 4,500+)</option>
                                <option value="Luxury Facial & Skin Renewal">Luxury Facial & Skin Renewal (LKR 6,500+)</option>
                                <option value="Royal Shave Experience">Royal Shave Experience (LKR 5,500+)</option>
                                <option value="Aromatherapy Massage">Aromatherapy Massage (LKR 8,500+)</option>
                                <option value="Hair Coloring & Balayage">Hair Coloring & Balayage (LKR 12,000+)</option>
                                <option value="Manicure & Pedicure">Manicure & Pedicure (LKR 5,000+)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Preferred Date</label>
                            <input type="date" class="form-control" name="date" id="apptDate" required>
                        </div>
                        <button type="submit" class="btn btn-elegance w-100 py-2 fw-bold"><i class="bi bi-check-circle"></i> Confirm Appointment</button>
                    </form>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="booking-card bg-white h-100 d-flex flex-column justify-content-center">
                    <h4 class="fw-bold mb-3"><i class="bi bi-person-check-fill me-2" style="color:#b85c1a;"></i> Customer Access</h4>
                    <?php if ($isLoggedIn): ?>
                        <div class="alert alert-success mb-3">
                            <i class="bi bi-check-circle-fill"></i> Welcome back, <strong><?php echo htmlspecialchars($userName); ?></strong>!<br>
                            <small>You get 10% off on all services.</small>
                        </div>
                        <div class="d-grid gap-2">
                            <a href="<?php echo getDashboardLink($userRole); ?>" class="btn btn-elegance"><i class="bi bi-speedometer2"></i> Go to Dashboard</a>
                            <a href="pages/shop.php" class="btn btn-outline-elegance"><i class="bi bi-bag"></i> Shop Products</a>
                        </div>
                    <?php else: ?>
                        <p>Already have an account? Login to get 10% off on all services & express booking.</p>
                        <div class="d-grid gap-2">
                            <a href="pages/login.php?redirect=<?php echo urlencode('index.php#booking'); ?>" class="btn btn-elegance"><i class="bi bi-box-arrow-in-right"></i> Login to Existing Account</a>
                            <a href="pages/register.php?redirect=<?php echo urlencode('index.php#booking'); ?>" class="btn btn-outline-elegance"><i class="bi bi-person-plus"></i> Create New Account</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
    let today = new Date().toISOString().split('T')[0];
    $('#apptDate').attr('min', today);
    
    $('a[href^="#"]').on('click', function(e) {
        let target = $(this.getAttribute('href'));
        if(target.length) {
            e.preventDefault();
            $('html, body').animate({ scrollTop: target.offset().top - 80 }, 600);
        }
    });
    
    $('#appointmentForm').on('submit', function(e) {
        e.preventDefault();
        let name = $('#apptName').val().trim();
        let email = $('#apptEmail').val().trim();
        let service = $('#apptService').val();
        let date = $('#apptDate').val();
        
        if(!name || !email || !date) {
            Swal.fire('Missing Info', 'Please fill all fields', 'warning');
            return;
        }
        
        Swal.fire({
            icon: 'success',
            title: 'Booking Request Sent!',
            html: `Thank you ${name}! We'll confirm your ${service} appointment on ${date}.`,
            confirmButtonColor: '#b85c1a'
        }).then(() => {
            $('#appointmentForm')[0].reset();
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
                <p class="text-secondary">Premium grooming services and products. Book your appointment or shop online.</p>
            </div>
            <div class="col-md-3 mb-4">
                <h6 class="text-white">Opening Hours</h6>
                <ul class="list-unstyled text-secondary">
                    <li>Mon-Fri: 9am - 8pm</li>
                    <li>Saturday: 10am - 6pm</li>
                    <li>Sunday: 11am - 5pm</li>
                </ul>
            </div>
            <div class="col-md-4 mb-4">
                <h6 class="text-white">Quick Links</h6>
                <ul class="list-unstyled text-secondary">
                    <li><a href="pages/shop.php" class="text-secondary text-decoration-none">Shop Products</a></li>
                    <li><a href="#services" class="text-secondary text-decoration-none">Our Services</a></li>
                    <li><a href="#booking" class="text-secondary text-decoration-none">Book Appointment</a></li>
                </ul>
            </div>
        </div>
        <hr class="bg-secondary"><div class="text-center text-secondary small">© 2025 Saloon Elegance — Sri Lanka</div>
    </div>
</footer>
</body>
</html>