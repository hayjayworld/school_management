🏫 Excel Schools - School Management System
A comprehensive PHP-based School Management Application designed for primary and secondary schools in Nigeria. This MVP provides a modern, responsive platform for administrators, teachers, and students to manage academic activities efficiently.

🌟 Features
🎯 Core Modules
👨‍💼 Admin Panel
Dashboard: Overview of students, staff, classes, and exams

Student Management: Register, edit, and manage student profiles

Staff Management: Add and manage teachers and administrators

Class & Subject Management: Create classes and assign subjects

Academic Sessions: Manage terms and academic years

CBT Exam Management: Create and monitor computer-based tests

🧑‍🏫 Teacher Portal
Dashboard: Overview of assigned classes and subjects

Student Management: View and manage class students

Result Management: Enter CA and exam scores with auto-grading

CBT Exams: Create and manage computer-based tests

Attendance: Record and track student attendance

👩‍🎓 Student Portal
Dashboard: Personal overview and quick stats

CBT Exams: Take timed computer-based tests

Results: View academic and CBT exam results

Profile: Personal information and academic progress

🌐 Public Landing Page
Responsive Design: Mobile-first approach

School Information: About, programs, and contact details

Admission Inquiry: Contact form with email notifications

🛠 Technology Stack
Backend
PHP 7.4+ (Object-Oriented with MVC structure)

MySQL 5.7+ (PDO with prepared statements)

Apache/Nginx web server

Frontend
HTML5 with semantic markup

CSS3 with Bootstrap 5.3.0

JavaScript (Vanilla ES6+)

Font Awesome 6.0 for icons

Security
Password Hashing (bcrypt)

CSRF Protection with tokens

Input Validation & Sanitization

Role-Based Access Control (RBAC)

Session Management

📦 Installation
Prerequisites
PHP 7.4 or higher

MySQL 5.7 or higher

Apache/Nginx web server

Composer (optional)

Step-by-Step Setup
Clone or Download the Project

bash
git clone <repository-url>
cd school_management
Create Database

sql
CREATE DATABASE school_management;
Import Database Schema

bash
mysql -u your_username -p school_management < sql/school_management.sql
Configure Database Connection
Edit includes/config.php with your database credentials:

php
private $host = "localhost";
private $db_name = "school_management";
private $username = "your_username";
private $password = "your_password";
Set File Permissions

bash
chmod 755 uploads/
chmod 644 includes/config.php
Configure Web Server

Point your web server document root to the project directory

Ensure mod_rewrite is enabled (for clean URLs)

Access the Application

Open your browser and navigate to the project URL

Default admin login credentials:

Username: admin

Password: password

🗄 Database Structure
Main Tables
users - All system users (admin, teachers, students)

students - Student-specific information

academic_sessions - School terms and sessions

classes - Class levels (Primary 1-6, JSS 1-3, SSS 1-3)

subjects - Subjects with teacher assignments

cbt_exams - Computer-based test configurations

cbt_questions - Exam questions and options

cbt_results - Student exam results

academic_results - Termly academic results

attendance - Student attendance records

contact_messages - Public inquiry submissions

👥 User Roles & Permissions
Administrator
Full system access and configuration

Manage all users (staff and students)

Create academic sessions and classes

Oversee exams and results

System monitoring and reports

Teacher
Manage assigned classes and subjects

Enter student results and attendance

Create and manage CBT exams

View student progress and performance

Student
Take CBT exams with timed interface

View personal results and grades

Access academic information

Track attendance and performance

🎨 UI/UX Features
Responsive Design: Works seamlessly on desktop, tablet, and mobile

Modern Interface: Clean, professional design with Bootstrap 5

Interactive Elements: Dynamic forms with real-time validation

Accessibility: WCAG compliant with proper ARIA labels

Loading States: Smooth transitions and feedback

🔒 Security Features
Password Security: Bcrypt hashing with salt

CSRF Protection: Token-based form validation

SQL Injection Prevention: PDO prepared statements

XSS Protection: Input sanitization and output escaping

Session Security: Secure session management

Role-Based Access: Route protection and permission checks

📱 Mobile Responsiveness
The application is built with a mobile-first approach:

Flexible Grid System: Bootstrap's responsive grid

Touch-Friendly: Larger buttons and form elements

Optimized Navigation: Collapsible sidebar for mobile

Fast Loading: Optimized assets and efficient queries

🚀 Usage Guide
For Administrators
Initial Setup

Start by creating academic sessions

Add classes and subjects

Register teaching staff

Student Management

Register new students with auto-generated IDs

Assign students to appropriate classes

Manage student information and parent contacts

System Monitoring

View dashboard for quick overview

Monitor exam activities and results

Generate reports as needed

For Teachers
Class Management

View assigned classes and subjects

Access student lists and profiles

Academic Activities

Enter continuous assessment scores

Record exam results with auto-grading

Track student attendance

CBT Exams

Create exams with multiple-choice questions

Set time limits and scheduling

Monitor student participation and results

For Students
Taking Exams

Access available CBT exams

Complete exams within time limits

Receive immediate results for some tests

Academic Tracking

View term results and grades

Monitor academic progress

Access attendance records

🛠 Development
Project Structure
text
school_management/
├── auth/          # Authentication pages
├── admin/         # Administrator modules
├── teacher/       # Teacher portal
├── student/       # Student portal
├── includes/      # Core PHP classes and functions
├── assets/        # CSS, JS, and images
├── uploads/       # File storage
└── sql/          # Database schema
Code Standards
PSR-12 coding standards

MVC architecture pattern

Object-oriented PHP

Semantic HTML5

Modular CSS with Bootstrap

Adding New Features
Create database tables if needed

Develop backend PHP logic

Implement frontend interface

Add role-based access controls

Test across different user roles

📞 Support
For technical support or feature requests, please contact:

Email: support@excelschools.edu.ng

Phone: +234 801 234 5678

📄 License
This project is licensed under the MIT License - see the LICENSE.md file for details.

🔮 Future Enhancements
Fees management module

Parent portal with notifications

SMS integration for alerts

Advanced reporting and analytics

Mobile app companion

Bulk data import/export

Multi-school support

API for third-party integrations

🤝 Contributing
We welcome contributions from the community! Please feel free to:

Report bugs and issues

Suggest new features

Submit pull requests

Improve documentation

Built with ❤️ for Nigerian Schools