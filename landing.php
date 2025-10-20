<?php
require_once 'includes/config.php';

$database = new Database();
$db = $database->getConnection();

// Get school information from settings
$settings_query = "SELECT setting_key, setting_value FROM system_settings WHERE is_public = 1";
$settings_result = $db->query($settings_query);
$school_settings = [];
while ($row = $settings_result->fetch(PDO::FETCH_ASSOC)) {
    $school_settings[$row['setting_key']] = $row['setting_value'];
}

// Get recent events
$events = $db->query("SELECT title, event_date, event_type, description 
                     FROM events 
                     WHERE is_published = 1 AND event_date >= CURDATE() 
                     ORDER BY event_date 
                     LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$total_students = $db->query("SELECT COUNT(*) FROM students WHERE status = 'active'")->fetchColumn();
$total_teachers = $db->query("SELECT COUNT(*) FROM users WHERE role = 'teacher' AND status = 'active'")->fetchColumn();
$total_classes = $db->query("SELECT COUNT(*) FROM classes WHERE status = 'active'")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $school_settings['school_name'] ?? 'Excel Schools'; ?> - <?php echo $school_settings['school_motto'] ?? 'Excellence in Education'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            line-height: 1.6;
            color: #333;
        }
        
        /* Header & Navigation */
        .navbar {
            background: rgba(44, 62, 80, 0.95) !important;
            backdrop-filter: blur(10px);
            padding: 1rem 0;
            transition: all 0.3s ease;
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
        }
        
        .navbar-brand i {
            color: #3498db;
        }
        
        .nav-link {
            font-weight: 500;
            margin: 0 0.5rem;
            transition: color 0.3s ease;
        }
        
        .nav-link:hover {
            color: #3498db !important;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3498db, #2980b9);
            border: none;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4);
        }
        
        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 120px 0 80px;
            position: relative;
            overflow: hidden;
        }
        
        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><polygon fill="rgba(255,255,255,0.05)" points="0,1000 1000,0 1000,1000"/></svg>');
            background-size: cover;
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
        }
        
        .hero h1 {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        
        .hero p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }
        
        /* Statistics */
        .stats {
            background: var(--light-color);
            padding: 60px 0;
        }
        
        .stat-card {
            text-align: center;
            padding: 2rem;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-number {
            font-size: 3rem;
            font-weight: 700;
            color: var(--secondary-color);
            display: block;
        }
        
        .stat-label {
            color: var(--dark-color);
            font-weight: 600;
        }
        
        /* Sections */
        .section {
            padding: 80px 0;
        }
        
        .section-title {
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .section-title h2 {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 1rem;
        }
        
        .section-title p {
            color: #666;
            font-size: 1.1rem;
        }
        
        /* Programs */
        .program-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            height: 100%;
            border: 1px solid #f8f9fa;
        }
        
        .program-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .program-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #3498db, #2980b9);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
        }
        
        .program-icon i {
            font-size: 2rem;
            color: white;
        }
        
        /* Events */
        .event-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .event-card:hover {
            transform: translateY(-5px);
        }
        
        .event-date {
            background: var(--secondary-color);
            color: white;
            padding: 1rem;
            text-align: center;
        }
        
        .event-date .day {
            font-size: 2rem;
            font-weight: 700;
            display: block;
        }
        
        .event-date .month {
            font-size: 0.9rem;
            text-transform: uppercase;
        }
        
        /* Contact Form */
        .contact-form {
            background: white;
            border-radius: 15px;
            padding: 3rem;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        
        /* Footer */
        .footer {
            background: var(--dark-color);
            color: white;
            padding: 60px 0 0;
        }
        
        .footer h5 {
            color: var(--secondary-color);
            margin-bottom: 1.5rem;
        }
        
        .footer-links a {
            color: #bdc3c7;
            text-decoration: none;
            display: block;
            margin-bottom: 0.5rem;
            transition: color 0.3s ease;
        }
        
        .footer-links a:hover {
            color: var(--secondary-color);
        }
        
        .footer-bottom {
            background: rgba(0,0,0,0.2);
            padding: 1.5rem 0;
            margin-top: 3rem;
        }
        
        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .fade-in-up {
            animation: fadeInUp 0.8s ease-out;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2.5rem;
            }
            
            .section {
                padding: 60px 0;
            }
            
            .section-title h2 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-graduation-cap me-2"></i>
                <?php echo $school_settings['school_name'] ?? 'Excel Schools'; ?>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link text-white" href="#home">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="#about">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="#programs">Programs</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="#events">Events</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="#contact">Contact</a>
                    </li>
                    <li class="nav-item ms-2">
                        <a class="btn btn-primary" href="auth/login.php">Portal Login</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 hero-content fade-in-up">
                    <h1><?php echo $school_settings['school_motto'] ?? 'Excellence in Education'; ?></h1>
                    <p>Welcome to <?php echo $school_settings['school_name'] ?? 'Excel Schools'; ?>, where we nurture young minds for a brighter future through quality education and character development.</p>
                    <div class="d-flex gap-3 flex-wrap">
                        <a href="#programs" class="btn btn-primary btn-lg">Explore Programs</a>
                        <a href="#contact" class="btn btn-outline-light btn-lg">Contact Us</a>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="text-center">
                        <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 600 400'%3E%3Crect fill='%23ffffff' opacity='0.1' width='600' height='400'/%3E%3Ccircle fill='%23ffffff' opacity='0.2' cx='300' cy='200' r='150'/%3E%3Cpath fill='%23ffffff' opacity='0.3' d='M300,100 L400,300 L200,300 Z'/%3E%3C/svg%3E" 
                             alt="Education Illustration" class="img-fluid" style="max-height: 400px;">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Statistics -->
    <section class="stats">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-3 col-6">
                    <div class="stat-card">
                        <span class="stat-number"><?php echo $total_students; ?></span>
                        <span class="stat-label">Students</span>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card">
                        <span class="stat-number"><?php echo $total_teachers; ?></span>
                        <span class="stat-label">Qualified Teachers</span>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card">
                        <span class="stat-number"><?php echo $total_classes; ?></span>
                        <span class="stat-label">Classes</span>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card">
                        <span class="stat-number">15+</span>
                        <span class="stat-label">Years Experience</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="section bg-light">
        <div class="container">
            <div class="section-title">
                <h2>About Our School</h2>
                <p>Committed to providing quality education and holistic development</p>
            </div>
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h3 class="mb-4">Welcome to <?php echo $school_settings['school_name'] ?? 'Excel Schools'; ?></h3>
                    <p class="mb-4">We are dedicated to creating an environment where students can excel academically while developing strong character and leadership skills.</p>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="d-flex align-items-center">
                                <div class="bg-primary rounded-circle p-2 me-3">
                                    <i class="fas fa-check text-white"></i>
                                </div>
                                <span>Quality Education</span>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="d-flex align-items-center">
                                <div class="bg-primary rounded-circle p-2 me-3">
                                    <i class="fas fa-check text-white"></i>
                                </div>
                                <span>Modern Facilities</span>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="d-flex align-items-center">
                                <div class="bg-primary rounded-circle p-2 me-3">
                                    <i class="fas fa-check text-white"></i>
                                </div>
                                <span>Experienced Staff</span>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="d-flex align-items-center">
                                <div class="bg-primary rounded-circle p-2 me-3">
                                    <i class="fas fa-check text-white"></i>
                                </div>
                                <span>Safe Environment</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 600 400'%3E%3Crect fill='%23f8f9fa' width='600' height='400'/%3E%3Cpath fill='%233498db' opacity='0.1' d='M0,0 L600,400 L0,400 Z'/%3E%3Ccircle fill='%233498db' opacity='0.2' cx='450' cy='100' r='80'/%3E%3C/svg%3E" 
                         alt="School Campus" class="img-fluid rounded-3">
                </div>
            </div>
        </div>
    </section>

    <!-- Programs Section -->
    <section id="programs" class="section">
        <div class="container">
            <div class="section-title">
                <h2>Academic Programs</h2>
                <p>Comprehensive educational programs for all age groups</p>
            </div>
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="program-card">
                        <div class="program-icon">
                            <i class="fas fa-child"></i>
                        </div>
                        <h4>Primary Education</h4>
                        <p>Foundational education for young learners with focus on literacy, numeracy, and social skills development.</p>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-primary me-2"></i>Primary 1 - 6</li>
                            <li><i class="fas fa-check text-primary me-2"></i>Age 5-11 years</li>
                            <li><i class="fas fa-check text-primary me-2"></i>Comprehensive curriculum</li>
                        </ul>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="program-card">
                        <div class="program-icon">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <h4>Junior Secondary</h4>
                        <p>Transitional program preparing students for senior secondary with diverse subject offerings.</p>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-primary me-2"></i>JSS 1 - 3</li>
                            <li><i class="fas fa-check text-primary me-2"></i>Age 12-14 years</li>
                            <li><i class="fas fa-check text-primary me-2"></i>Broad-based curriculum</li>
                        </ul>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="program-card">
                        <div class="program-icon">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <h4>Senior Secondary</h4>
                        <p>Specialized education tracks preparing students for university and professional careers.</p>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-primary me-2"></i>SSS 1 - 3</li>
                            <li><i class="fas fa-check text-primary me-2"></i>Age 15-17 years</li>
                            <li><i class="fas fa-check text-primary me-2"></i>Science & Arts tracks</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Events Section -->
    <section id="events" class="section bg-light">
        <div class="container">
            <div class="section-title">
                <h2>Upcoming Events</h2>
                <p>Stay updated with our school activities and events</p>
            </div>
            <div class="row g-4">
                <?php if (count($events) > 0): ?>
                    <?php foreach ($events as $event): ?>
                        <div class="col-lg-4 col-md-6">
                            <div class="event-card">
                                <div class="event-date">
                                    <span class="day"><?php echo date('d', strtotime($event['event_date'])); ?></span>
                                    <span class="month"><?php echo date('M', strtotime($event['event_date'])); ?></span>
                                </div>
                                <div class="p-3">
                                    <h5><?php echo $event['title']; ?></h5>
                                    <p class="text-muted small"><?php echo $event['description']; ?></p>
                                    <span class="badge bg-<?php echo $event['event_type'] == 'academic' ? 'primary' : ($event['event_type'] == 'sports' ? 'success' : 'warning'); ?>">
                                        <?php echo ucfirst($event['event_type']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12 text-center">
                        <p class="text-muted">No upcoming events scheduled. Check back later!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="section">
        <div class="container">
            <div class="section-title">
                <h2>Contact Us</h2>
                <p>Get in touch with us for more information</p>
            </div>
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="contact-form">
                        <form id="contactForm">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" class="form-control" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" class="form-control" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Subject</label>
                                <input type="text" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Message</label>
                                <textarea class="form-control" rows="5" required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Send Message</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4">
                    <h5><?php echo $school_settings['school_name'] ?? 'Excel Schools'; ?></h5>
                    <p class="mt-3">Committed to providing quality education and nurturing future leaders through innovative teaching methods and comprehensive development programs.</p>
                    <div class="d-flex gap-3 mt-4">
                        <a href="#" class="text-white"><i class="fab fa-facebook fa-lg"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-twitter fa-lg"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-instagram fa-lg"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-linkedin fa-lg"></i></a>
                    </div>
                </div>
                <div class="col-lg-2">
                    <h5>Quick Links</h5>
                    <div class="footer-links">
                        <a href="#home">Home</a>
                        <a href="#about">About</a>
                        <a href="#programs">Programs</a>
                        <a href="#events">Events</a>
                        <a href="#contact">Contact</a>
                    </div>
                </div>
                <div class="col-lg-3">
                    <h5>Contact Info</h5>
                    <div class="footer-links">
                        <p><i class="fas fa-map-marker-alt me-2"></i> <?php echo $school_settings['school_address'] ?? '123 Education Road, Ikeja, Lagos'; ?></p>
                        <p><i class="fas fa-phone me-2"></i> <?php echo $school_settings['school_phone'] ?? '+234 801 234 5678'; ?></p>
                        <p><i class="fas fa-envelope me-2"></i> <?php echo $school_settings['school_email'] ?? 'info@excelschools.edu.ng'; ?></p>
                    </div>
                </div>
                <div class="col-lg-3">
                    <h5>School Portal</h5>
                    <div class="footer-links">
                        <a href="auth/login.php">Student Login</a>
                        <a href="auth/login.php">Teacher Login</a>
                        <a href="auth/login.php">Admin Login</a>
                        <a href="cbt/login.php">CBT Portal</a>
                    </div>
                </div>
            </div>
            <div class="footer-bottom text-center">
                <p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo $school_settings['school_name'] ?? 'Excel Schools'; ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Navbar background on scroll
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 100) {
                navbar.style.background = '#2c3e50';
                navbar.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
            } else {
                navbar.style.background = 'rgba(44, 62, 80, 0.95)';
                navbar.style.boxShadow = 'none';
            }
        });

        // Smooth scrolling
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
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
            alert('Thank you for your message! We will get back to you soon.');
            this.reset();
        });

        // Animation on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe elements for animation
        document.querySelectorAll('.program-card, .event-card, .stat-card').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(30px)';
            el.style.transition = 'all 0.6s ease-out';
            observer.observe(el);
        });
    </script>
</body>
</html>