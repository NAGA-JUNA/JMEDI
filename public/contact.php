<?php
$pageTitle = 'Contact Us';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div class="container">
        <h1>Contact Us</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="/">Home</a></li>
                <li class="breadcrumb-item active">Contact</li>
            </ol>
        </nav>
    </div>
</div>

<section class="py-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="bg-white rounded-3 p-4 shadow-sm mb-4">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center me-3" style="width:50px;height:50px;">
                            <i class="fas fa-map-marker-alt text-white"></i>
                        </div>
                        <div>
                            <h6 class="mb-1">Address</h6>
                            <p class="text-muted small mb-0"><?= e($settings['address'] ?? '') ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-3 p-4 shadow-sm mb-4">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-success d-flex align-items-center justify-content-center me-3" style="width:50px;height:50px;">
                            <i class="fas fa-phone text-white"></i>
                        </div>
                        <div>
                            <h6 class="mb-1">Phone</h6>
                            <p class="text-muted small mb-0"><?= e($settings['phone'] ?? '') ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-3 p-4 shadow-sm mb-4">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-info d-flex align-items-center justify-content-center me-3" style="width:50px;height:50px;">
                            <i class="fas fa-envelope text-white"></i>
                        </div>
                        <div>
                            <h6 class="mb-1">Email</h6>
                            <p class="text-muted small mb-0"><?= e($settings['email'] ?? '') ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-primary text-white rounded-3 p-4 text-center">
                    <i class="fas fa-ambulance fs-1 mb-3"></i>
                    <h5>Emergency</h5>
                    <p class="h4 mb-1"><?= e($settings['emergency_phone'] ?? '') ?></p>
                    <small class="opacity-75">Available 24/7</small>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="bg-white rounded-3 p-4 shadow-sm">
                    <h4 class="mb-4">Send us a Message</h4>
                    <form>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Your Name</label>
                                <input type="text" class="form-control" placeholder="Enter your name">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email Address</label>
                                <input type="email" class="form-control" placeholder="Enter your email">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input type="tel" class="form-control" placeholder="Your phone number">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Subject</label>
                                <input type="text" class="form-control" placeholder="Message subject">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Message</label>
                                <textarea class="form-control" rows="5" placeholder="Type your message here..."></textarea>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary btn-lg px-5">Send Message</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
