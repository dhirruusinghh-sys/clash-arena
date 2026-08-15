<?php
// customer/dashboard.php
// Premium Customer Dashboard Workspace

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';

// Verify role access
checkRole(['customer']);

include_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-content">
    <!-- Breadcrumbs -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item text-muted">Portal</li>
            <li class="breadcrumb-item active" aria-current="page">Customer Home</li>
        </ol>
    </nav>

    <!-- Welcome Header -->
    <div class="d-flex align-items-center justify-content-between mb-4 pb-2">
        <div>
            <h1 class="fw-bold fs-3 text-dark mb-1">Customer Workspace</h1>
            <p class="text-secondary mb-0">Manage purchases, invoices, download links, and support tickets.</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-gradient-primary btn-sm"><i class="fa-solid fa-cart-shopping me-2"></i>New Order</button>
            <button class="btn btn-outline-secondary btn-sm" onclick="window.location.reload();"><i class="fa-solid fa-rotate"></i></button>
        </div>
    </div>

    <!-- Stats Cards Row -->
    <div class="row g-4 mb-5">
        <!-- Stat Card 1 -->
        <div class="col-md-6 col-lg-3">
            <div class="stat-card shadow-sm">
                <div class="stat-card-info">
                    <h3>My Orders</h3>
                    <p>8</p>
                </div>
                <div class="stat-card-icon primary"><i class="fa-solid fa-box-open"></i></div>
            </div>
        </div>
        
        <!-- Stat Card 2 -->
        <div class="col-md-6 col-lg-3">
            <div class="stat-card shadow-sm">
                <div class="stat-card-info">
                    <h3>Pending Orders</h3>
                    <p>1</p>
                </div>
                <div class="stat-card-icon warning"><i class="fa-solid fa-arrows-spin"></i></div>
            </div>
        </div>
        
        <!-- Stat Card 3 -->
        <div class="col-md-6 col-lg-3">
            <div class="stat-card shadow-sm">
                <div class="stat-card-info">
                    <h3>Completed</h3>
                    <p>7</p>
                </div>
                <div class="stat-card-icon success"><i class="fa-solid fa-check-double"></i></div>
            </div>
        </div>
        
        <!-- Stat Card 4 -->
        <div class="col-md-6 col-lg-3">
            <div class="stat-card shadow-sm">
                <div class="stat-card-info">
                    <h3>Wallet Balance</h3>
                    <p>$340.50</p>
                </div>
                <div class="stat-card-icon accent"><i class="fa-solid fa-wallet"></i></div>
            </div>
        </div>
    </div>

    <!-- Middle Section layout grid -->
    <div class="row g-4 mb-5">
        <!-- Section: Download Center -->
        <div class="col-lg-6">
            <div class="neu-card p-4 h-100 d-flex flex-column justify-content-between">
                <div>
                    <h5 class="fw-bold mb-3"><i class="fa-solid fa-cloud-arrow-down me-2 text-primary"></i>Premium Download Center</h5>
                    <p class="text-secondary fs-8 mb-4">Access software licenses, templates, and digital media files mapped to your subscription tier.</p>
                    
                    <div class="d-flex align-items-center justify-content-between p-3 border rounded-3 mb-2 hover-bg-light">
                        <div class="d-flex align-items-center gap-3">
                            <i class="fa-solid fa-file-pdf fs-4 text-danger"></i>
                            <div>
                                <div class="fw-bold text-dark fs-7">Partner License Agreement.pdf</div>
                                <div class="text-muted fs-8">PDF Documents &bull; 1.2 MB</div>
                            </div>
                        </div>
                        <a href="#" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-download"></i></a>
                    </div>

                    <div class="d-flex align-items-center justify-content-between p-3 border rounded-3 mb-2 hover-bg-light">
                        <div class="d-flex align-items-center gap-3">
                            <i class="fa-solid fa-file-zipper fs-4 text-warning"></i>
                            <div>
                                <div class="fw-bold text-dark fs-7">Swiss_UI_Framer_Assets.zip</div>
                                <div class="text-muted fs-8">ZIP Archive &bull; 48.6 MB</div>
                            </div>
                        </div>
                        <a href="#" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-download"></i></a>
                    </div>
                </div>

                <div class="mt-4 text-center">
                    <span class="fs-8 text-muted">Looking for older orders? <a href="#" class="fw-semibold">Request archive recovery</a>.</span>
                </div>
            </div>
        </div>

        <!-- Section: Profile Completion status & activity timeline -->
        <div class="col-lg-6">
            <div class="neu-card p-4 h-100">
                <h5 class="fw-bold mb-4">Portal Activity Timeline</h5>
                
                <div class="position-relative ps-4 border-start border-light-subtle">
                    <!-- Timeline Item 1 -->
                    <div class="position-relative mb-4">
                        <span class="position-absolute translate-middle rounded-circle bg-primary" style="width: 12px; height: 12px; left: -25px; top: 8px;"></span>
                        <div class="fw-bold text-dark fs-7">Session Authentication Registered</div>
                        <div class="text-muted fs-8">Signed in securely via common login gate.</div>
                        <small class="text-secondary" style="font-size: 0.7rem;">Today, 13:01</small>
                    </div>

                    <!-- Timeline Item 2 -->
                    <div class="position-relative mb-4">
                        <span class="position-absolute translate-middle rounded-circle bg-success" style="width: 12px; height: 12px; left: -25px; top: 8px;"></span>
                        <div class="fw-bold text-dark fs-7">Licensing Verification Setup</div>
                        <div class="text-muted fs-8">Database initialization validated successfully.</div>
                        <small class="text-secondary" style="font-size: 0.7rem;">June 28, 2026</small>
                    </div>

                    <!-- Timeline Item 3 -->
                    <div class="position-relative">
                        <span class="position-absolute translate-middle rounded-circle bg-secondary" style="width: 12px; height: 12px; left: -25px; top: 8px;"></span>
                        <div class="fw-bold text-dark fs-7">Profile Activated</div>
                        <div class="text-muted fs-8">Assigned Customer Role and active system metrics.</div>
                        <small class="text-secondary" style="font-size: 0.7rem;">June 25, 2026</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Orders Table -->
    <div class="neu-card p-4">
        <h5 class="fw-bold mb-4">Purchase History</h5>
        
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.9rem;">
                <thead>
                    <tr>
                        <th>Order Ref</th>
                        <th>Billing Date</th>
                        <th>Purchased Item</th>
                        <th>Subtotal</th>
                        <th>Status</th>
                        <th class="text-end">Receipt</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="fw-bold text-dark">#CH-92041</td>
                        <td>June 28, 2026</td>
                        <td>Professional SaaS Licensing Pack</td>
                        <td class="fw-semibold">$49.00</td>
                        <td><span class="badge bg-success bg-opacity-10 text-success">Paid</span></td>
                        <td class="text-end"><a href="#" class="btn btn-sm btn-outline-secondary py-1 px-2"><i class="fa-solid fa-file-invoice"></i> PDF</a></td>
                    </tr>
                    <tr>
                        <td class="fw-bold text-dark">#CH-89025</td>
                        <td>May 15, 2026</td>
                        <td>Swiss Design Editorial UI Kit</td>
                        <td class="fw-semibold">$19.00</td>
                        <td><span class="badge bg-success bg-opacity-10 text-success">Paid</span></td>
                        <td class="text-end"><a href="#" class="btn btn-sm btn-outline-secondary py-1 px-2"><i class="fa-solid fa-file-invoice"></i> PDF</a></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
include_once __DIR__ . '/../includes/footer.php';
?>
