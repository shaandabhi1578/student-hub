-- StudentHub Complete Database Schema & Seed Data
-- Practicals 8, 9, 10, 11, 12, 13, 14
-- Database: studenthub_db

CREATE DATABASE IF NOT EXISTS studenthub_db;
USE studenthub_db;

-- 1. Users Table (Authentication, RBAC, Sessions, Remember-Me, Status Toggle)
DROP TABLE IF EXISTS registrations;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'student') NOT NULL DEFAULT 'student',
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    remember_token_hash VARCHAR(64) DEFAULT NULL,
    remember_token_expiry DATETIME DEFAULT NULL,
    last_login DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Students Table (CRUD, Search, Filter, Soft Delete via status)
CREATE TABLE students (
    student_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    enrollment_no VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(15) NOT NULL,
    course VARCHAR(100) NOT NULL,
    semester INT DEFAULT 1,
    gender VARCHAR(10) DEFAULT 'Other',
    status ENUM('active', 'inactive', 'archived') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_student FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Events Table (CRUD, Poster Upload, Status & Category Filters)
CREATE TABLE events (
    event_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    category VARCHAR(50) NOT NULL,
    description TEXT DEFAULT NULL,
    event_date DATE NOT NULL,
    location VARCHAR(100) NOT NULL,
    seats INT DEFAULT 60,
    available_seats INT DEFAULT 60,
    status ENUM('Open', 'Closed', 'Cancelled') NOT NULL DEFAULT 'Open',
    poster_image VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Registrations Table (Student Event Enrollment)
CREATE TABLE registrations (
    reg_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    event_id INT NOT NULL,
    reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20) DEFAULT 'Confirmed',
    CONSTRAINT fk_student FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    CONSTRAINT fk_event FOREIGN KEY (event_id) REFERENCES events(event_id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_event (student_id, event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Audit Logs Table (Practical 9, 10, 12, 14 traceability)
CREATE TABLE audit_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(100) NOT NULL,
    performed_by VARCHAR(100) DEFAULT 'system',
    user_role VARCHAR(30) DEFAULT 'guest',
    details TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =======================================================
-- SEED DATA
-- =======================================================

-- Seed Users with Bcrypt Hashed Passwords
-- Admin password: Admin@123
-- Student passwords: Student@123 / Shaan@123 / Aarav@123
INSERT INTO users (user_id, username, email, password, role, status, last_login) VALUES
(1, 'admin', 'admin@studenthub.edu', '$2y$12$ULeNO/HkviM5Q2xMJU9CCuFO4.4m1vHTaoKOVVWX960frQ.LB59Le', 'admin', 'active', '2026-10-20 18:30:00'),
(2, 'shaan', 'shaan@studenthub.edu', '$2y$12$Se0atKlh6cAyvPSck/cI9eClk92btqmfA18e0qmXdpEFUXfS5Noze', 'student', 'active', '2026-10-20 19:15:00'),
(3, 'aarav', 'aarav.patel@studenthub.edu', '$2y$12$Se0atKlh6cAyvPSck/cI9eClk92btqmfA18e0qmXdpEFUXfS5Noze', 'student', 'active', '2026-10-19 14:20:00'),
(4, 'student1', 'student1@studenthub.edu', '$2y$12$Se0atKlh6cAyvPSck/cI9eClk92btqmfA18e0qmXdpEFUXfS5Noze', 'student', 'active', '2026-10-18 11:10:00'),
(5, 'inactive_user', 'inactive@studenthub.edu', '$2y$12$Se0atKlh6cAyvPSck/cI9eClk92btqmfA18e0qmXdpEFUXfS5Noze', 'student', 'inactive', NULL);

-- Seed Students Table with 22 Seeded Records (Practical 11 Requirement: Min 20 Records)
INSERT INTO students (student_id, user_id, enrollment_no, name, email, phone, course, semester, gender, status) VALUES
(1, 2, '23BECE001', 'Shaan Dabhi', 'shaan@studenthub.edu', '9408487768', 'B.Tech Computer Engineering', 6, 'Male', 'active'),
(2, 3, '23BEIT002', 'Aarav Patel', 'aarav.patel@studenthub.edu', '9876543210', 'B.Tech Information Technology', 4, 'Male', 'active'),
(3, NULL, '23BEAI003', 'Diya Sharma', 'diya.sharma@studenthub.edu', '9123456780', 'B.Tech AI & Data Science', 4, 'Female', 'active'),
(4, NULL, '23BECE004', 'Rohan Verma', 'rohan.verma@studenthub.edu', '9822334455', 'B.Tech Computer Engineering', 6, 'Male', 'active'),
(5, NULL, '23BEIT005', 'Ananya Iyer', 'ananya.iyer@studenthub.edu', '9711223344', 'B.Tech Information Technology', 2, 'Female', 'active'),
(6, NULL, '23BEME006', 'Kabir Mehta', 'kabir.mehta@studenthub.edu', '9633445566', 'B.Tech Mechanical Engineering', 8, 'Male', 'active'),
(7, NULL, '23BECE007', 'Ishita Desai', 'ishita.desai@studenthub.edu', '9544556677', 'B.Tech Computer Engineering', 4, 'Female', 'active'),
(8, NULL, '23BECD008', 'Aditya Joshi', 'aditya.joshi@studenthub.edu', '9455667788', 'B.Tech Civil Engineering', 6, 'Male', 'inactive'),
(9, NULL, '23BEEC009', 'Priya Nair', 'priya.nair@studenthub.edu', '9366778899', 'B.Tech Electronics & Comm.', 4, 'Female', 'active'),
(10, NULL, '23BECE010', 'Siddharth Shah', 'siddharth.shah@studenthub.edu', '9277889900', 'B.Tech Computer Engineering', 6, 'Male', 'active'),
(11, NULL, '23BEAI011', 'Tanvi Kulkarni', 'tanvi.k@studenthub.edu', '9188990011', 'B.Tech AI & Data Science', 2, 'Female', 'active'),
(12, NULL, '23BEIT012', 'Harsh Trivedi', 'harsh.trivedi@studenthub.edu', '9099001122', 'B.Tech Information Technology', 6, 'Male', 'active'),
(13, NULL, '23BECE013', 'Neha Gupta', 'neha.gupta@studenthub.edu', '9988112233', 'B.Tech Computer Engineering', 8, 'Female', 'active'),
(14, NULL, '23BEME014', 'Yash Singhania', 'yash.s@studenthub.edu', '9877223344', 'B.Tech Mechanical Engineering', 4, 'Male', 'active'),
(15, NULL, '23BEEC015', 'Ritu Parekh', 'ritu.parekh@studenthub.edu', '9766334455', 'B.Tech Electronics & Comm.', 6, 'Female', 'archived'),
(16, NULL, '23BECD016', 'Devendra Rathore', 'devendra.r@studenthub.edu', '9655445566', 'B.Tech Civil Engineering', 4, 'Male', 'active'),
(17, NULL, '23BEAI017', 'Pooja Bhatt', 'pooja.bhatt@studenthub.edu', '9544332211', 'B.Tech AI & Data Science', 4, 'Female', 'active'),
(18, NULL, '23BECE018', 'Aryan Malhotra', 'aryan.m@studenthub.edu', '9433221100', 'B.Tech Computer Engineering', 2, 'Male', 'active'),
(19, NULL, '23BEIT019', 'Sneha Reddy', 'sneha.reddy@studenthub.edu', '9322110099', 'B.Tech Information Technology', 4, 'Female', 'active'),
(20, NULL, '23BEEE020', 'Kunal Chawla', 'kunal.c@studenthub.edu', '9211009988', 'B.Tech Electrical Engineering', 6, 'Male', 'active'),
(21, NULL, '23BECE021', 'Meera Sen', 'meera.sen@studenthub.edu', '9100998877', 'B.Tech Computer Engineering', 4, 'Female', 'active'),
(22, 4, '23BEIT022', 'Varun Kapoor', 'varun.k@studenthub.edu', '9011223344', 'B.Tech Information Technology', 6, 'Male', 'active');

-- Seed Events Table (Practical 12)
INSERT INTO events (event_id, title, category, description, event_date, location, seats, available_seats, status, poster_image) VALUES
(1, 'Annual Tech Symposium 2026', 'Technical', 'Flagship engineering conclave featuring paper presentations, robotics showcases, and industry keynote speakers.', '2026-11-15', 'Main Auditorium', 120, 118, 'Open', 'poster_tech_symposium.jpg'),
(2, 'Web Development Hackathon', 'Technical', '24-hour sprint to build full-stack web applications using modern web APIs and databases.', '2026-11-22', 'Computer Lab 3', 60, 58, 'Open', 'poster_hackathon.jpg'),
(3, 'Inter-College Cricket Tournament', 'Sports', 'Annual inter-collegiate cricket tournament spanning 4 days of competitive matches.', '2026-11-28', 'College Sports Ground', 200, 199, 'Closed', 'poster_cricket.jpg'),
(4, 'Cultural Fiesta & Music Fest', 'Cultural', 'A vibrant celebration of music, dance, theater, and arts by student talent across departments.', '2026-12-05', 'Open Air Amphitheatre', 350, 348, 'Open', 'poster_cultural.jpg'),
(5, 'AI & Robotics Summit', 'Technical', 'Hands-on workshops on computer vision, generative AI pipelines, and autonomous rover systems.', '2026-12-12', 'Seminar Hall B', 90, 89, 'Open', 'poster_ai_summit.jpg'),
(6, 'Alumni Career Mentorship Webinar', 'Academic', 'Interactive panel with alumni working at Google, Microsoft, and leading startups sharing career guidance.', '2026-12-18', 'Online / Virtual Lab', 150, 150, 'Open', 'poster_webinar.jpg');

-- Seed Registrations
INSERT INTO registrations (student_id, event_id, status) VALUES
(1, 1, 'Confirmed'),
(1, 2, 'Confirmed'),
(2, 1, 'Confirmed'),
(2, 2, 'Confirmed'),
(3, 4, 'Confirmed'),
(4, 3, 'Confirmed'),
(5, 4, 'Confirmed'),
(7, 5, 'Confirmed');

-- Seed Initial Audit Logs (Practical 9, 10, 12, 14)
INSERT INTO audit_logs (action, performed_by, user_role, details, ip_address, user_agent) VALUES
('SYSTEM_INITIALIZE', 'System', 'admin', 'Database initialized and seeded with 22 students and 6 events', '127.0.0.1', 'StudentHub Installer'),
('USER_REGISTER', 'shaan', 'student', 'New student registered: Shaan Dabhi (shaan@studenthub.edu)', '127.0.0.1', 'Mozilla/5.0'),
('USER_LOGIN', 'admin', 'admin', 'Admin authenticated successfully. Session ID regenerated.', '127.0.0.1', 'Mozilla/5.0'),
('EVENT_CREATE', 'admin', 'admin', 'Created event: Annual Tech Symposium 2026 with 120 seats', '127.0.0.1', 'Mozilla/5.0'),
('STUDENT_CREATE', 'admin', 'admin', 'Added student record: Varun Kapoor (23BEIT022)', '127.0.0.1', 'Mozilla/5.0');
