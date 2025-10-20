-- Complete School Management System Database Schema
-- Drop database if exists and create new one
DROP DATABASE IF EXISTS school_management;
CREATE DATABASE school_management;
USE school_management;

-- Users table (for all roles)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'teacher', 'student') NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    gender ENUM('male', 'female', 'other') NOT NULL DEFAULT 'other',
    photo VARCHAR(255),
    date_of_birth DATE,
    address TEXT,
    city VARCHAR(50),
    state VARCHAR(50),
    country VARCHAR(50) DEFAULT 'Nigeria',
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Academic sessions
CREATE TABLE academic_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    current_term ENUM('first', 'second', 'third') NOT NULL,
    start_date DATE,
    end_date DATE,
    status ENUM('active', 'inactive', 'completed') DEFAULT 'inactive',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Classes/Grades
CREATE TABLE classes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    section ENUM('primary', 'junior', 'senior') NOT NULL,
    class_teacher_id INT,
    capacity INT DEFAULT 40,
    current_strength INT DEFAULT 0,
    room_number VARCHAR(20),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (class_teacher_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Subjects
CREATE TABLE subjects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) UNIQUE NOT NULL,
    class_id INT,
    teacher_id INT,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Students (extended user information)
CREATE TABLE students (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id VARCHAR(20) UNIQUE NOT NULL,
    user_id INT UNIQUE NOT NULL,
    class_id INT,
    session_id INT,
    admission_date DATE,
    admission_number VARCHAR(50),
    parent_name VARCHAR(100),
    parent_email VARCHAR(100),
    parent_phone VARCHAR(20),
    parent_occupation VARCHAR(100),
    emergency_contact VARCHAR(20),
    address TEXT,
    city VARCHAR(50),
    state VARCHAR(50),
    religion ENUM('christianity', 'islam', 'other') DEFAULT 'christianity',
    blood_group ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'),
    medical_conditions TEXT,
    allergies TEXT,
    previous_school VARCHAR(100),
    status ENUM('active', 'graduated', 'transferred', 'withdrawn', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL,
    FOREIGN KEY (session_id) REFERENCES academic_sessions(id) ON DELETE SET NULL
);

-- Staff details (extended user information for teachers)
CREATE TABLE staff (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE NOT NULL,
    staff_id VARCHAR(20) UNIQUE NOT NULL,
    qualification VARCHAR(100),
    specialization VARCHAR(100),
    employment_date DATE,
    salary_grade VARCHAR(20),
    bank_name VARCHAR(100),
    account_number VARCHAR(50),
    tax_id VARCHAR(50),
    emergency_contact VARCHAR(20),
    marital_status ENUM('single', 'married', 'divorced', 'widowed') DEFAULT 'single',
    status ENUM('active', 'inactive', 'retired', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Class subjects mapping (many-to-many relationship)
CREATE TABLE class_subjects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    class_id INT NOT NULL,
    subject_id INT NOT NULL,
    teacher_id INT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_class_subject (class_id, subject_id)
);

-- CBT Exams
CREATE TABLE cbt_exams (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    subject_id INT NOT NULL,
    class_id INT NOT NULL,
    session_id INT NOT NULL,
    term ENUM('first', 'second', 'third') NOT NULL,
    duration_minutes INT NOT NULL,
    total_questions INT NOT NULL,
    total_marks INT NOT NULL,
    passing_marks INT DEFAULT 50,
    instructions TEXT,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    status ENUM('draft', 'active', 'completed', 'cancelled') DEFAULT 'draft',
    shuffle_questions BOOLEAN DEFAULT TRUE,
    shuffle_options BOOLEAN DEFAULT TRUE,
    show_results_immediately BOOLEAN DEFAULT TRUE,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- CBT Questions
CREATE TABLE cbt_questions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    exam_id INT NOT NULL,
    question_type ENUM('multiple_choice', 'true_false', 'short_answer') DEFAULT 'multiple_choice',
    question TEXT NOT NULL,
    option_a VARCHAR(255),
    option_b VARCHAR(255),
    option_c VARCHAR(255),
    option_d VARCHAR(255),
    correct_answer VARCHAR(255) NOT NULL,
    marks INT DEFAULT 1,
    explanation TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_id) REFERENCES cbt_exams(id) ON DELETE CASCADE
);

-- CBT Results
CREATE TABLE cbt_results (
    id INT PRIMARY KEY AUTO_INCREMENT,
    exam_id INT NOT NULL,
    student_id INT NOT NULL,
    score INT NOT NULL,
    total_marks INT NOT NULL,
    percentage DECIMAL(5,2) NOT NULL,
    grade VARCHAR(5),
    time_taken INT DEFAULT 0,
    started_at DATETIME,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT,
    FOREIGN KEY (exam_id) REFERENCES cbt_exams(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    UNIQUE KEY unique_exam_student (exam_id, student_id)
);

-- Student Answers (to track individual question responses)
CREATE TABLE cbt_student_answers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    result_id INT NOT NULL,
    question_id INT NOT NULL,
    student_answer VARCHAR(255),
    is_correct BOOLEAN DEFAULT FALSE,
    marks_obtained INT DEFAULT 0,
    answered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (result_id) REFERENCES cbt_results(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES cbt_questions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_result_question (result_id, question_id)
);

-- Academic Results
CREATE TABLE academic_results (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    session_id INT NOT NULL,
    term ENUM('first', 'second', 'third') NOT NULL,
    ca_score DECIMAL(5,2) DEFAULT 0,
    exam_score DECIMAL(5,2) DEFAULT 0,
    total_score DECIMAL(5,2) DEFAULT 0,
    grade VARCHAR(5),
    remark VARCHAR(50),
    position_in_class INT,
    position_in_subject INT,
    teacher_comment TEXT,
    principal_comment TEXT,
    is_published BOOLEAN DEFAULT FALSE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_student_subject_term (student_id, subject_id, session_id, term)
);

-- Attendance
CREATE TABLE attendance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    class_id INT NOT NULL,
    session_id INT NOT NULL,
    date DATE NOT NULL,
    term ENUM('first', 'second', 'third') NOT NULL,
    status ENUM('present', 'absent', 'late', 'excused') NOT NULL,
    reason TEXT,
    recorded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_date (student_id, date)
);

-- Timetable
CREATE TABLE timetable (
    id INT PRIMARY KEY AUTO_INCREMENT,
    class_id INT NOT NULL,
    subject_id INT NOT NULL,
    teacher_id INT NOT NULL,
    day_of_week ENUM('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    room VARCHAR(20),
    session_id INT NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE
);

-- Notifications
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'danger') DEFAULT 'info',
    target_audience ENUM('all', 'students', 'teachers', 'parents', 'specific') DEFAULT 'all',
    specific_users JSON,
    is_published BOOLEAN DEFAULT FALSE,
    publish_at DATETIME,
    expires_at DATETIME,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- User Notifications (read status)
CREATE TABLE user_notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    notification_id INT NOT NULL,
    user_id INT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    read_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_notification (notification_id, user_id)
);

-- Fees Structure
CREATE TABLE fee_structure (
    id INT PRIMARY KEY AUTO_INCREMENT,
    class_id INT NOT NULL,
    session_id INT NOT NULL,
    term ENUM('first', 'second', 'third') NOT NULL,
    fee_type VARCHAR(100) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    due_date DATE,
    is_optional BOOLEAN DEFAULT FALSE,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES academic_sessions(id) ON DELETE CASCADE
);

-- Fee Payments
CREATE TABLE fee_payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    fee_structure_id INT NOT NULL,
    amount_paid DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_method ENUM('cash', 'bank_transfer', 'online', 'cheque') DEFAULT 'cash',
    transaction_id VARCHAR(100),
    receipt_number VARCHAR(100),
    status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'completed',
    notes TEXT,
    recorded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (fee_structure_id) REFERENCES fee_structure(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Library Books
CREATE TABLE library_books (
    id INT PRIMARY KEY AUTO_INCREMENT,
    isbn VARCHAR(20),
    title VARCHAR(255) NOT NULL,
    author VARCHAR(100),
    publisher VARCHAR(100),
    publication_year YEAR,
    edition VARCHAR(50),
    category VARCHAR(100),
    total_copies INT DEFAULT 1,
    available_copies INT DEFAULT 1,
    location VARCHAR(100),
    description TEXT,
    status ENUM('available', 'unavailable') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Book Issues
CREATE TABLE book_issues (
    id INT PRIMARY KEY AUTO_INCREMENT,
    book_id INT NOT NULL,
    student_id INT NOT NULL,
    issue_date DATE NOT NULL,
    due_date DATE NOT NULL,
    return_date DATE,
    fine_amount DECIMAL(8,2) DEFAULT 0,
    status ENUM('issued', 'returned', 'overdue') DEFAULT 'issued',
    issued_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (book_id) REFERENCES library_books(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Events
CREATE TABLE events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    event_date DATE NOT NULL,
    start_time TIME,
    end_time TIME,
    venue VARCHAR(100),
    event_type ENUM('academic', 'sports', 'cultural', 'holiday', 'other') DEFAULT 'academic',
    target_audience ENUM('all', 'students', 'teachers', 'parents') DEFAULT 'all',
    is_published BOOLEAN DEFAULT FALSE,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Contact Form Submissions
CREATE TABLE contact_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    status ENUM('new', 'read', 'replied', 'spam') DEFAULT 'new',
    assigned_to INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
);

-- System Settings
CREATE TABLE system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    is_public BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Audit Log
CREATE TABLE audit_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(100),
    record_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- =============================================================================
-- INSERT DEFAULT DATA
-- =============================================================================

-- Insert default admin user
INSERT INTO users (username, email, password, role, full_name, gender, status) 
VALUES ('admin', 'admin@school.edu.ng', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'System Administrator', 'male', 'active');

-- Insert sample academic session
INSERT INTO academic_sessions (name, current_term, start_date, end_date, status, created_by) 
VALUES ('2024/2025', 'first', '2024-09-10', '2025-07-15', 'active', 1);

-- Insert sample classes
INSERT INTO classes (name, section, capacity, room_number, status) VALUES 
('Primary 1', 'primary', 40, 'P1-01', 'active'),
('Primary 2', 'primary', 40, 'P2-01', 'active'),
('Primary 3', 'primary', 40, 'P3-01', 'active'),
('Primary 4', 'primary', 40, 'P4-01', 'active'),
('Primary 5', 'primary', 40, 'P5-01', 'active'),
('Primary 6', 'primary', 40, 'P6-01', 'active'),
('JSS 1', 'junior', 35, 'J1-01', 'active'),
('JSS 2', 'junior', 35, 'J2-01', 'active'),
('JSS 3', 'junior', 35, 'J3-01', 'active'),
('SSS 1', 'senior', 30, 'S1-01', 'active'),
('SSS 2', 'senior', 30, 'S2-01', 'active'),
('SSS 3', 'senior', 30, 'S3-01', 'active');

-- Insert sample subjects
INSERT INTO subjects (name, code, class_id, description) VALUES 
-- Primary subjects
('Mathematics', 'MATH', 1, 'Basic Mathematics for Primary 1'),
('English Language', 'ENG', 1, 'English Language for Primary 1'),
('Basic Science', 'BSC', 1, 'Basic Science for Primary 1'),
('Mathematics', 'MATH', 2, 'Basic Mathematics for Primary 2'),
('English Language', 'ENG', 2, 'English Language for Primary 2'),

-- Junior Secondary subjects
('Mathematics', 'MATH', 7, 'Mathematics for JSS 1'),
('English Language', 'ENG', 7, 'English Language for JSS 1'),
('Basic Science', 'BSC', 7, 'Basic Science for JSS 1'),
('Social Studies', 'SST', 7, 'Social Studies for JSS 1'),
('Business Studies', 'BUS', 7, 'Business Studies for JSS 1'),

-- Senior Secondary subjects
('Mathematics', 'MATH', 10, 'Mathematics for SSS 1'),
('English Language', 'ENG', 10, 'English Language for SSS 1'),
('Physics', 'PHY', 10, 'Physics for SSS 1'),
('Chemistry', 'CHEM', 10, 'Chemistry for SSS 1'),
('Biology', 'BIO', 10, 'Biology for SSS 1');

-- Insert system settings
INSERT INTO system_settings (setting_key, setting_value, setting_type, description, is_public) VALUES 
('school_name', 'Excel Schools', 'string', 'Name of the school', TRUE),
('school_address', '123 Education Road, Ikeja, Lagos', 'string', 'School physical address', TRUE),
('school_phone', '+234 801 234 5678', 'string', 'School phone number', TRUE),
('school_email', 'info@excelschools.edu.ng', 'string', 'School email address', TRUE),
('school_website', 'https://www.excelschools.edu.ng', 'string', 'School website', TRUE),
('school_logo', 'logo.png', 'string', 'School logo filename', TRUE),
('current_session_id', '1', 'number', 'Current active academic session', FALSE),
('ca_max_score', '30', 'number', 'Maximum CA score', FALSE),
('exam_max_score', '70', 'number', 'Maximum exam score', FALSE),
('passing_percentage', '50', 'number', 'Minimum percentage to pass', FALSE);

-- Insert sample notifications
INSERT INTO notifications (title, message, type, target_audience, is_published, created_by) VALUES 
('Welcome to Excel Schools', 'Welcome to the new academic session 2024/2025. We wish all students success in their studies.', 'info', 'all', TRUE, 1),
('Parent-Teacher Meeting', 'There will be a parent-teacher meeting on 15th October 2024. All parents are requested to attend.', 'warning', 'parents', TRUE, 1);

-- Insert sample events
INSERT INTO events (title, description, event_date, event_type, target_audience, is_published, created_by) VALUES 
('Independence Day Celebration', 'School will be closed for Independence Day celebration', '2024-10-01', 'holiday', 'all', TRUE, 1),
('Inter-house Sports Competition', 'Annual inter-house sports competition at school field', '2024-11-15', 'sports', 'all', TRUE, 1);

-- Insert sample library books
INSERT INTO library_books (isbn, title, author, publisher, category, total_copies, available_copies) VALUES 
('978-0141439518', 'Things Fall Apart', 'Chinua Achebe', 'Penguin Books', 'Literature', 5, 5),
('978-0435905255', 'The Lion and the Jewel', 'Wole Soyinka', 'Oxford University Press', 'Drama', 3, 3),
('978-0195739813', 'Mathematics for Primary Schools', 'Prof. Adebayo', 'Macmillan', 'Mathematics', 10, 10);

-- Insert sample fee structure
INSERT INTO fee_structure (class_id, session_id, term, fee_type, amount, due_date) VALUES 
(1, 1, 'first', 'Tuition Fee', 50000.00, '2024-09-30'),
(1, 1, 'first', 'Development Levy', 10000.00, '2024-09-30'),
(7, 1, 'first', 'Tuition Fee', 75000.00, '2024-09-30'),
(7, 1, 'first', 'Science Laboratory', 15000.00, '2024-09-30'),
(10, 1, 'first', 'Tuition Fee', 100000.00, '2024-09-30'),
(10, 1, 'first', 'Science Laboratory', 20000.00, '2024-09-30');

-- Create indexes for better performance
CREATE INDEX idx_students_class_id ON students(class_id);
CREATE INDEX idx_students_session_id ON students(session_id);
CREATE INDEX idx_attendance_student_date ON attendance(student_id, date);
CREATE INDEX idx_results_student_subject ON academic_results(student_id, subject_id);
CREATE INDEX idx_cbt_results_student_exam ON cbt_results(student_id, exam_id);
CREATE INDEX idx_users_role_status ON users(role, status);
CREATE INDEX idx_notifications_published ON notifications(is_published, publish_at);
CREATE INDEX idx_events_date ON events(event_date);

-- Create views for common queries
CREATE VIEW student_details AS
SELECT 
    s.id,
    s.student_id,
    u.full_name,
    u.gender,
    u.email,
    u.phone,
    u.date_of_birth,
    s.class_id,
    c.name as class_name,
    c.section as class_section,
    s.session_id,
    ses.name as session_name,
    s.parent_name,
    s.parent_phone,
    s.parent_email,
    s.admission_date,
    s.status
FROM students s
JOIN users u ON s.user_id = u.id
JOIN classes c ON s.class_id = c.id
JOIN academic_sessions ses ON s.session_id = ses.id;

CREATE VIEW teacher_subjects AS
SELECT 
    u.id as teacher_id,
    u.full_name as teacher_name,
    s.id as subject_id,
    s.name as subject_name,
    s.code as subject_code,
    c.id as class_id,
    c.name as class_name
FROM subjects s
JOIN users u ON s.teacher_id = u.id
JOIN classes c ON s.class_id = c.id
WHERE u.role = 'teacher' AND u.status = 'active';

CREATE VIEW exam_results_summary AS
SELECT 
    cr.exam_id,
    e.title as exam_title,
    s.id as student_id,
    u.full_name as student_name,
    c.name as class_name,
    sub.name as subject_name,
    cr.score,
    cr.total_marks,
    cr.percentage,
    cr.grade,
    cr.submitted_at
FROM cbt_results cr
JOIN cbt_exams e ON cr.exam_id = e.id
JOIN students s ON cr.student_id = s.id
JOIN users u ON s.user_id = u.id
JOIN classes c ON s.class_id = c.id
JOIN subjects sub ON e.subject_id = sub.id;