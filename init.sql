-- Consolidated Database Blueprint for Sanity Homebased Tuition Academy (S.H.T.A)
-- 1. ROLE-SPECIFIC USER TABLES
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id VARCHAR(50) UNIQUE NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20) NOT NULL,
    password VARCHAR(255) NOT NULL,
    must_change_password TINYINT(1) DEFAULT 1,
    security_question VARCHAR(255) NULL,
    security_answer VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id VARCHAR(50) UNIQUE NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20) NOT NULL,
    password VARCHAR(255) NOT NULL,
    must_change_password TINYINT(1) DEFAULT 1,
    security_question VARCHAR(255) NULL,
    security_answer VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS parents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id VARCHAR(50) UNIQUE NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20) NOT NULL,
    nationality VARCHAR(100) NULL,
    password VARCHAR(255) NOT NULL,
    must_change_password TINYINT(1) DEFAULT 1,
    security_question VARCHAR(255) NULL,
    security_answer VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id VARCHAR(50) UNIQUE NULL,
    admission_no VARCHAR(50) UNIQUE NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20) NOT NULL,
    password VARCHAR(255) NOT NULL,
    must_change_password TINYINT(1) DEFAULT 1,
    security_question VARCHAR(255) NULL,
    security_answer VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS timetablers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id VARCHAR(50) UNIQUE NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20) NOT NULL,
    password VARCHAR(255) NOT NULL,
    must_change_password TINYINT(1) DEFAULT 1,
    security_question VARCHAR(255) NULL,
    security_answer VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS accounts_officers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id VARCHAR(50) UNIQUE NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20) NOT NULL,
    password VARCHAR(255) NOT NULL,
    must_change_password TINYINT(1) DEFAULT 1,
    security_question VARCHAR(255) NULL,
    security_answer VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 1.1 LOGIN BRUTE-FORCE RATE LIMITING
CREATE TABLE IF NOT EXISTS login_attempts (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    ip_address  VARCHAR(45) NOT NULL,
    attempts    SMALLINT    NOT NULL DEFAULT 1,
    last_attempt TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ip (ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 1.2 CURRICULUMS REGISTRY
CREATE TABLE IF NOT EXISTS curriculums (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    is_approved TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. PUBLIC PORTAL INTAKE STAGING LEDGER
CREATE TABLE IF NOT EXISTS enrollment_inquiries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parent_name VARCHAR(150) NOT NULL,
    parent_phone VARCHAR(20) NOT NULL,
    parent_email VARCHAR(100) NOT NULL,
    parent_nationality VARCHAR(100) NULL,
    student_name VARCHAR(150) NOT NULL,
    student_grade VARCHAR(50) NOT NULL,
    students_json TEXT NULL,
    learning_needs TEXT NULL,
    venue_preference ENUM('school', 'home_visit') NOT NULL,
    loc_place VARCHAR(100) NULL,
    loc_estate VARCHAR(100) NULL,
    loc_link VARCHAR(255) NULL,
    curriculum_id INT NULL,
    study_type ENUM('tuition', 'homeschooling') NOT NULL DEFAULT 'tuition',
    status ENUM('pending', 'contacted', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (curriculum_id) REFERENCES curriculums(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. ACTIVE STUDENT ARCHIVE
CREATE TABLE IF NOT EXISTS student_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL, -- Links to students table
    parent_id INT NOT NULL, -- Links to parents table
    grade_level VARCHAR(50) NOT NULL,
    dob DATE NULL,
    nationality VARCHAR(100) NULL,
    first_language VARCHAR(100) NULL,
    learning_notes TEXT NULL,
    loc_place VARCHAR(100) NULL,
    loc_estate VARCHAR(100) NULL,
    loc_link VARCHAR(255) NULL,
    curriculum_id INT NULL,
    study_type ENUM('tuition', 'homeschooling') NOT NULL DEFAULT 'tuition',
    FOREIGN KEY (user_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE CASCADE,
    FOREIGN KEY (curriculum_id) REFERENCES curriculums(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. TIMETABLER SCHEDULING ENGINE & GEOGRAPHIC LAYOUTS
CREATE TABLE IF NOT EXISTS timetable_slots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    teacher_id INT NOT NULL,
    day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    venue_type ENUM('school', 'home_visit', 'online_meet', 'online_zoom') NOT NULL,
    student_address TEXT NULL,
    FOREIGN KEY (student_id) REFERENCES student_profiles(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. ACTIVE LESSON ATTENDANCE & VERIFICATION RECORD
CREATE TABLE IF NOT EXISTS lessons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slot_id INT NOT NULL,
    lesson_date DATE NOT NULL,
    current_otp VARCHAR(6) NULL,
    session_status ENUM('scheduled', 'in_progress', 'completed') DEFAULT 'scheduled',
    check_in_time TIMESTAMP NULL,
    check_out_time TIMESTAMP NULL,
    topics_covered TEXT NULL,
    progress_notes TEXT NULL,
    homework_assigned TEXT NULL,
    FOREIGN KEY (slot_id) REFERENCES timetable_slots(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. FORMAL SCHOOL EXAMINATIONS DIRECTORY
CREATE TABLE IF NOT EXISTS school_exams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_name VARCHAR(150) NOT NULL,
    academic_year VARCHAR(10) NOT NULL,
    term_identifier VARCHAR(20) NOT NULL,
    submission_deadline DATETIME NOT NULL,
    automated_alerts_enabled BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. EXAM EXECUTIONS & INVIGILATION BALANCER
CREATE TABLE IF NOT EXISTS exam_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id INT NOT NULL,
    subject VARCHAR(100) NOT NULL,
    exam_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    room_number VARCHAR(50) NOT NULL,
    invigilator_teacher_id INT NOT NULL,
    FOREIGN KEY (exam_id) REFERENCES school_exams(id) ON DELETE CASCADE,
    FOREIGN KEY (invigilator_teacher_id) REFERENCES teachers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. CONSOLIDATED ACADEMIC GRADE BOOKS
CREATE TABLE IF NOT EXISTS exam_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_session_id INT NOT NULL,
    student_id INT NOT NULL,
    marks_obtained DECIMAL(5,2) NOT NULL,
    teacher_remarks TEXT NULL,
    is_published BOOLEAN DEFAULT FALSE,
    UNIQUE KEY unique_result (exam_session_id, student_id),
    FOREIGN KEY (exam_session_id) REFERENCES exam_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES student_profiles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8.1 GRADING SCALES
CREATE TABLE IF NOT EXISTS grading_scales (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    grade_level VARCHAR(100) NOT NULL,
    letter_grade VARCHAR(10) NOT NULL,
    min_mark    DECIMAL(5,2) NOT NULL,
    max_mark    DECIMAL(5,2) NOT NULL,
    remarks_template TEXT NULL,
    UNIQUE KEY unique_grade_letter (grade_level, letter_grade)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. CONTINUOUS HOMEWORK & ASSIGNMENTS DIRECTORY
CREATE TABLE IF NOT EXISTS student_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    teacher_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    due_date DATETIME NOT NULL,
    score_obtained DECIMAL(5,2) NULL,
    status ENUM('pending', 'submitted', 'graded') DEFAULT 'pending',
    FOREIGN KEY (student_id) REFERENCES student_profiles(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. MODERATED WEEKLY & TERMINAL REPORT CARDS
CREATE TABLE IF NOT EXISTS academic_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    teacher_id INT NOT NULL,
    report_type ENUM('weekly', 'terminal') NOT NULL,
    period_identifier VARCHAR(50) NOT NULL,
    topics_covered TEXT NOT NULL,
    student_performance_notes TEXT NOT NULL,
    teacher_recommendations TEXT NOT NULL,
    status ENUM('pending', 'approved') DEFAULT 'pending',
    approved_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES student_profiles(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES admins(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. CENTRALIZED CONTENT DELIVERY WAREHOUSE & SUBJECTS
CREATE TABLE IF NOT EXISTS subject_areas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO subject_areas (name) VALUES 
('Mathematics'), ('Chemistry'), ('Biology'), ('Physics'), ('English'), ('Swahili')
ON DUPLICATE KEY UPDATE name=name;

CREATE TABLE IF NOT EXISTS curriculum_subjects (
    curriculum_id INT NOT NULL,
    subject_id INT NOT NULL,
    PRIMARY KEY (curriculum_id, subject_id),
    FOREIGN KEY (curriculum_id) REFERENCES curriculums(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subject_areas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS student_subjects (
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    PRIMARY KEY (student_id, subject_id),
    FOREIGN KEY (student_id) REFERENCES student_profiles(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subject_areas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS learning_resources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_type VARCHAR(50) NULL,
    subject VARCHAR(100) NOT NULL,
    grade_level VARCHAR(50) NOT NULL,
    material_type ENUM('past_paper', 'marking_scheme', 'notes', 'other') DEFAULT 'past_paper',
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12. TEACHER SUBJECTS RELATIONSHIP MAP
CREATE TABLE IF NOT EXISTS teacher_subjects (
    teacher_id INT NOT NULL,
    subject_id INT NOT NULL,
    PRIMARY KEY (teacher_id, subject_id),
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subject_areas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 13. EXTRA EXPENSES LEDGER
CREATE TABLE IF NOT EXISTS extra_expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category ENUM('inventory', 'utility', 'general_repairs', 'petty_cash') NOT NULL,
    item_name VARCHAR(200) NOT NULL,
    description TEXT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    expense_date DATE NOT NULL,
    recorded_by INT NOT NULL,
    reference VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 14. STUDENT SESSION PRICING REGISTRY
CREATE TABLE IF NOT EXISTS student_pricing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL UNIQUE,
    price_online_meet  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    price_online_zoom  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    price_school       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    price_home_visit   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES student_profiles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 15. STUDENT INVOICES
CREATE TABLE IF NOT EXISTS student_invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    balance DECIMAL(12,2) GENERATED ALWAYS AS (total_amount - amount_paid) STORED,
    status ENUM('draft','sent','partial','paid','overdue') NOT NULL DEFAULT 'draft',
    notes TEXT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES student_profiles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 16. STUDENT PAYMENTS LEDGER
CREATE TABLE IF NOT EXISTS student_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    invoice_id INT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_method ENUM('mpesa','bank_transfer','cash','cheque','other') NOT NULL DEFAULT 'mpesa',
    reference_number VARCHAR(100) NULL,
    notes TEXT NULL,
    recorded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES student_profiles(id) ON DELETE CASCADE,
    FOREIGN KEY (invoice_id) REFERENCES student_invoices(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 17. TEACHER PAY RATE REGISTRY
CREATE TABLE IF NOT EXISTS teacher_pricing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL UNIQUE,
    pay_online_meet  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    pay_online_zoom  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    pay_school       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    pay_home_visit   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 18. TEACHER DISBURSEMENTS LEDGER
CREATE TABLE IF NOT EXISTS teacher_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_date DATE NOT NULL,
    reference VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

