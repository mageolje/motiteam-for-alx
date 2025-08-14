-- Seed data for School Portal

-- Password `password` bcrypt hash
SET @pass := '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

INSERT INTO users (username, full_name, role, password_hash) VALUES
('officer1', 'Record Officer One', 'record_officer', @pass),
('teacher1', 'Subject Teacher One', 'subject_teacher', @pass),
('homeroom1', 'Homeroom Teacher One', 'homeroom_teacher', @pass);

INSERT INTO classes (name, homeroom_teacher_id) VALUES
('Grade 7 A', (SELECT id FROM users WHERE username='homeroom1')),
('Grade 7 B', NULL);

INSERT INTO students (student_number, full_name, date_of_birth) VALUES
('STU001', 'Alice Johnson', '2010-03-14'),
('STU002', 'Bob Smith', '2010-07-22'),
('STU003', 'Charlie Lee', '2010-11-30');

-- Enroll all students into Grade 7 A
INSERT INTO enrollments (student_id, class_id)
SELECT s.id, c.id FROM students s CROSS JOIN (SELECT id FROM classes WHERE name='Grade 7 A') c;

-- Subjects taught by teacher1 for Grade 7 A
INSERT INTO subjects (name, code, teacher_id, class_id) VALUES
('Mathematics', 'MATH7', (SELECT id FROM users WHERE username='teacher1'), (SELECT id FROM classes WHERE name='Grade 7 A')),
('English', 'ENG7', (SELECT id FROM users WHERE username='teacher1'), (SELECT id FROM classes WHERE name='Grade 7 A'));

-- Example message
INSERT INTO messages (sender_id, receiver_id, subject, body)
VALUES ((SELECT id FROM users WHERE username='teacher1'), (SELECT id FROM users WHERE username='officer1'), 'Welcome', 'Hello officer, the term setup is ready.');