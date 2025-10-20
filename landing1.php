<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Excel Schools - Quality Education in Nigeria</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --accent: #e74c3c;
            --light: #ecf0f1;
            --dark: #2c3e50;
        }
        
        /* Smooth scrolling */
        html {
            scroll-behavior: smooth;
        }
        
        /* Navigation */
        .navbar {
            padding: 1rem 0;
            transition: all 0.3s ease;
        }
        
        .navbar.scrolled {
            background: white !important;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 0.5rem 0;
        }
        
        .navbar-brand {
            font-weight: bold;
            font-size: 1.5rem;
        }
        
        .nav-link {
            font-weight: 500;
            margin: 0 0.5rem;
            transition: color 0.3s ease;
        }
        
        .nav-link:hover {
            color: var(--secondary) !important;
        }
        
        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 120px 0 80px;
            position: relative;
            overflow: hidden;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><polygon fill="rgba(255,255,255,0.05)" points="0,1000 1000,0 1000,1000"/></svg>');
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
        }
        
        /* Sections */
        .section-padding {
            padding: 80px 0;
        }
        
        /* Feature Cards */
        .feature-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            height: 100%;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }
        
        .feature-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        /* Buttons */
        .btn-primary {
            background: var(--secondary);
            border-color: var(--secondary);
            padding: 12px 30px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background: var(--primary);
            border-color: var(--primary);
            transform: translateY(-2px);
        }
        
        .btn-outline-light:hover {
            background: white;
            color: var(--primary);
        }
        
        /* Testimonials */
        .testimonial-card {
            background: var(--light);
            border-radius: 15px;
            padding: 30px;
            margin: 20px 0;
            border-left: 4px solid var(--secondary);
        }
        
        /* Footer */
        .footer {
            background: var(--dark);
            color: white;
            padding: 50px 0 20px;
        }
        
        .footer a {
            color: var(--light);
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .footer a:hover {
            color: var(--secondary);
        }
        
        .social-links a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            margin-right: 10px;
            transition: all 0.3s ease;
        }
        
        .social-links a:hover {
            background: var(--secondary);
            transform: translateY(-3px);
        }
        
        /* Program Cards */
        .program-card {
            border: none;
            border-radius: 15px;
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .program-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .hero-section {
                padding: 100px 0 60px;
                text-align: center;
            }
            
            .hero-section h1 {
                font-size: 2rem;
            }
            
            .section-padding {
                padding: 60px 0;
            }
            
            .feature-card, .program-card {
                margin-bottom: 20px;
            }
            
            .navbar-collapse {
                background: white;
                padding: 1rem;
                border-radius: 10px;
                margin-top: 10px;
                box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            }
        }
        
        /* Loading animation */
        .fade-in {
            animation: fadeIn 1s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white fixed-top">
        <div class="container">
            <a class="navbar-brand" href="landing.php">
                <i class="fas fa-graduation-cap text-primary"></i> Excel Schools
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" 
                    aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link text-dark" href="#home">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-dark" href="#about">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-dark" href="#programs">Programs</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-dark" href="#contact">Contact</a>
                    </li>
                    <li class="nav-item ms-2">
                        <a class="nav-link btn btn-primary text-white" href="auth/login.php">
                            <i class="fas fa-sign-in-alt me-1"></i> Login
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 fade-in">
                    <h1 class="display-4 fw-bold mb-4">Excellence in Education for Nigerian Youth</h1>
                    <p class="lead mb-4">Providing quality primary and secondary education with modern teaching methods and digital learning tools. Empowering students for success in a rapidly changing world.</p>
                    <div class="d-flex flex-wrap gap-3">
                        <a href="#contact" class="btn btn-light btn-lg">Enroll Now</a>
                        <a href="#about" class="btn btn-outline-light btn-lg">Learn More</a>
                    </div>
                </div>
                <div class="col-lg-6 fade-in">
                    <div class="text-center">
                        <img src="https://images.unsplash.com/photo-1523050854058-8df90110c9f1?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=1000&q=80" 
                             alt="Students learning" class="img-fluid rounded-3 shadow-lg">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="section-padding bg-light">
        <div class="container">
            <div class="row">
                <div class="col-lg-6 mb-5 mb-lg-0">
                    <h2 class="fw-bold mb-4">About Excel Schools</h2>
                    <p class="lead text-muted">Established in 1995, Excel Schools has been at the forefront of providing quality education in Nigeria for over 25 years.</p>
                    <p class="mb-4">We combine traditional values with modern educational techniques to prepare students for success in a rapidly changing world. Our commitment to excellence has made us one of the most trusted educational institutions in the region.</p>
                    
                    <div class="mt-4">
                        <div class="d-flex align-items-start mb-4">
                            <i class="fas fa-bullseye text-primary fa-2x me-3 mt-1"></i>
                            <div>
                                <h5>Our Mission</h5>
                                <p class="mb-0">To provide quality education that develops students' intellectual, physical, and moral capabilities, preparing them for leadership and service.</p>
                            </div>
                        </div>
                        
                        <div class="d-flex align-items-start mb-4">
                            <i class="fas fa-eye text-primary fa-2x me-3 mt-1"></i>
                            <div>
                                <h5>Our Vision</h5>
                                <p class="mb-0">To be the leading educational institution in Nigeria, producing future leaders, innovators, and responsible global citizens.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="feature-card card text-center p-4 h-100">
                                <i class="fas fa-users feature-icon"></i>
                                <h5>Qualified Teachers</h5>
                                <p class="mb-0">Certified and experienced teaching staff with passion for education</p>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="feature-card card text-center p-4 h-100">
                                <i class="fas fa-laptop feature-icon"></i>
                                <h5>Digital Learning</h5>
                                <p class="mb-0">Modern computer labs and e-learning platforms</p>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="feature-card card text-center p-4 h-100">
                                <i class="fas fa-book feature-icon"></i>
                                <h5>Rich Curriculum</h5>
                                <p class="mb-0">Comprehensive academic programs aligned with national standards</p>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="feature-card card text-center p-4 h-100">
                                <i class="fas fa-shield-alt feature-icon"></i>
                                <h5>Safe Environment</h5>
                                <p class="mb-0">Secure and conducive learning space for all students</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Programs Section -->
    <section id="programs" class="section-padding">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold">Academic Programs</h2>
                <p class="lead text-muted">Comprehensive education from primary to secondary level</p>
            </div>
            
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="program-card card h-100">
                        <div class="card-body text-center p-4">
                            <i class="fas fa-child fa-3x text-primary mb-3"></i>
                            <h4 class="card-title">Primary Education</h4>
                            <p class="text-muted">Primary 1 - 6</p>
                            <ul class="list-unstyled text-start">
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Basic literacy & numeracy</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Creative arts & music</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Physical education</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Moral instruction</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Basic science & technology</li>
                            </ul>
                            <div class="mt-3">
                                <span class="badge bg-primary">Ages 6-11</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="program-card card h-100">
                        <div class="card-body text-center p-4">
                            <i class="fas fa-user-graduate fa-3x text-primary mb-3"></i>
                            <h4 class="card-title">Junior Secondary</h4>
                            <p class="text-muted">JSS 1 - 3</p>
                            <ul class="list-unstyled text-start">
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Basic science & technology</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Business studies</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Creative & cultural arts</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>French language</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Computer studies</li>
                            </ul>
                            <div class="mt-3">
                                <span class="badge bg-success">Ages 12-14</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="program-card card h-100">
                        <div class="card-body text-center p-4">
                            <i class="fas fa-user-tie fa-3x text-primary mb-3"></i>
                            <h4 class="card-title">Senior Secondary</h4>
                            <p class="text-muted">SSS 1 - 3</p>
                            <ul class="list-unstyled text-start">
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Sciences (Physics, Chemistry, Biology)</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Arts & Humanities</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Commercial studies</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Technology education</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i>WAEC & NECO preparation</li>
                            </ul>
                            <div class="mt-3">
                                <span class="badge bg-info">Ages 15-17</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="section-padding bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold">What Parents Say</h2>
                <p class="lead text-muted">Hear from our satisfied parents and guardians</p>
            </div>
            
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="testimonial-card">
                        <div class="d-flex align-items-center mb-3">
                            <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center" 
                                 style="width: 50px; height: 50px;">
                                <i class="fas fa-user text-white"></i>
                            </div>
                            <div class="ms-3">
                                <h6 class="mb-0">Mr. Johnson Ade</h6>
                                <small class="text-muted">Parent of JSS 2 Student</small>
                            </div>
                        </div>
                        <p class="mb-0">"Excel Schools has transformed my daughter's learning experience. The teachers are dedicated and the facilities are excellent."</p>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="testimonial-card">
                        <div class="d-flex align-items-center mb-3">
                            <div class="rounded-circle bg-success d-flex align-items-center justify-content-center" 
                                 style="width: 50px; height: 50px;">
                                <i class="fas fa-user text-white"></i>
                            </div>
                            <div class="ms-3">
                                <h6 class="mb-0">Mrs. Bola Ahmed</h6>
                                <small class="text-muted">Parent of Primary 4 Student</small>
                            </div>
                        </div>
                        <p class="mb-0">"The digital learning approach has made my son more engaged with his studies. He actually looks forward to going to school!"</p>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="testimonial-card">
                        <div class="d-flex align-items-center mb-3">
                            <div class="rounded-circle bg-warning d-flex align-items-center justify-content-center" 
                                 style="width: 50px; height: 50px;">
                                <i class="fas fa-user text-white"></i>
                            </div>
                            <div class="ms-3">
                                <h6 class="mb-0">Dr. Chinedu Okoro</h6>
                                <small class="text-muted">Parent of SSS 1 Student</small>
                            </div>
                        </div>
                        <p class="mb-0">"The academic standards at Excel Schools are impressive. My daughter is well-prepared for her university entrance exams."</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="section-padding">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="text-center mb-5">
                        <h2 class="fw-bold">Admission Inquiry</h2>
                        <p class="lead text-muted">Interested in enrolling your child? Contact us today!</p>
                    </div>
                    
                    <div class="card shadow-lg border-0">
                        <div class="card-body p-4 p-md-5">
                            <form id="contactForm">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Full Name *</label>
                                        <input type="text" class="form-control" name="name" required 
                                               placeholder="Enter your full name">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Email Address *</label>
                                        <input type="email" class="form-control" name="email" required 
                                               placeholder="Enter your email address">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Phone Number *</label>
                                        <input type="tel" class="form-control" name="phone" required 
                                               placeholder="Enter your phone number">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Interest Level *</label>
                                        <select class="form-select" name="interest_level" required>
                                            <option value="">Select Level</option>
                                            <option value="primary">Primary School</option>
                                            <option value="junior">Junior Secondary</option>
                                            <option value="senior">Senior Secondary</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label">Message *</label>
                                    <textarea class="form-control" name="message" rows="5" required 
                                              placeholder="Tell us about your inquiry..."></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary btn-lg w-100">
                                    <i class="fas fa-paper-plane me-2"></i>Submit Inquiry
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 col-md-6 mb-4">
                    <h5 class="mb-3">
                        <i class="fas fa-graduation-cap me-2"></i>Excel Schools
                    </h5>
                    <p class="mb-3">Providing quality education since 1995. Committed to academic excellence and character development for Nigerian youth.</p>
                    <div class="social-links">
                        <a href="#" class="text-white"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
                <div class="col-lg-2 col-md-6 mb-4">
                    <h5 class="mb-3">Quick Links</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#home">Home</a></li>
                        <li class="mb-2"><a href="#about">About Us</a></li>
                        <li class="mb-2"><a href="#programs">Programs</a></li>
                        <li class="mb-2"><a href="#contact">Contact</a></li>
                        <li class="mb-2"><a href="auth/login.php">Login</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <h5 class="mb-3">Programs</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#programs">Primary Education</a></li>
                        <li class="mb-2"><a href="#programs">Junior Secondary</a></li>
                        <li class="mb-2"><a href="#programs">Senior Secondary</a></li>
                        <li class="mb-2"><a href="#programs">Extra Curricular</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <h5 class="mb-3">Contact Info</h5>
                    <ul class="list-unstyled">
                        <li class="mb-3">
                            <i class="fas fa-map-marker-alt me-2"></i>
                            <span>123 Education Road, Ikeja, Lagos</span>
                        </li>
                        <li class="mb-3">
                            <i class="fas fa-phone me-2"></i>
                            <span>+234 801 234 5678</span>
                        </li>
                        <li class="mb-3">
                            <i class="fas fa-envelope me-2"></i>
                            <span>info@excelschools.edu.ng</span>
                        </li>
                        <li class="mb-3">
                            <i class="fas fa-clock me-2"></i>
                            <span>Mon - Fri: 7:30 AM - 4:30 PM</span>
                        </li>
                    </ul>
                </div>
            </div>
            <hr class="bg-white my-4">
            <div class="text-center">
                <p class="mb-0">&copy; 2024 Excel Schools. All rights reserved. | Designed with <i class="fas fa-heart text-danger"></i> for Nigerian Education</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap & JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 100) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    // Close mobile menu when clicking a link
                    const navbarToggler = document.querySelector('.navbar-toggler');
                    const navbarCollapse = document.querySelector('.navbar-collapse');
                    if (navbarCollapse.classList.contains('show')) {
                        navbarToggler.click();
                    }
                    
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Contact form handling
        document.getElementById('contactForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitButton = this.querySelector('button[type="submit"]');
            const originalText = submitButton.innerHTML;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sending...';
            submitButton.disabled = true;
            
            const formData = new FormData(this);
            formData.append('action', 'contact_submission');
            
            fetch('includes/handle_contact.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    const alert = document.createElement('div');
                    alert.className = 'alert alert-success alert-dismissible fade show';
                    alert.innerHTML = `
                        <i class="fas fa-check-circle me-2"></i>
                        Thank you for your inquiry! We will contact you soon.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    document.getElementById('contactForm').prepend(alert);
                    
                    // Reset form
                    this.reset();
                } else {
                    throw new Error(data.message || 'Error submitting form');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                const alert = document.createElement('div');
                alert.className = 'alert alert-danger alert-dismissible fade show';
                alert.innerHTML = `
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Error submitting form. Please try again.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                document.getElementById('contactForm').prepend(alert);
            })
            .finally(() => {
                submitButton.innerHTML = originalText;
                submitButton.disabled = false;
            });
        });

        // Add loading animation to elements when they come into view
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('fade-in');
                }
            });
        }, observerOptions);

        // Observe all sections for animation
        document.querySelectorAll('section').forEach(section => {
            observer.observe(section);
        });

        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>
</body>
</html>