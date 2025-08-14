-- Schema for School Portal

DROP TABLE IF EXISTS messages;
DROP TABLE IF EXISTS final_results;
DROP TABLE IF EXISTS assessments;
DROP TABLE IF EXISTS attendance;
DROP TABLE IF EXISTS enrollments;
DROP TABLE IF EXISTS subjects;
DROP TABLE IF EXISTS classes;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
	id INT AUTO_INCREMENT PRIMARY KEY,
	username VARCHAR(50) NOT NULL UNIQUE,
	full_name VARCHAR(100) NOT NULL,
	role ENUM('record_officer','subject_teacher','homeroom_teacher','admin') NOT NULL,
	password_hash VARCHAR(255) NOT NULL,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE students (
	id INT AUTO_INCREMENT PRIMARY KEY,
	student_number VARCHAR(30) NOT NULL UNIQUE,
	full_name VARCHAR(120) NOT NULL,
	date_of_birth DATE NULL,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE classes (
	id INT AUTO_INCREMENT PRIMARY KEY,
	name VARCHAR(50) NOT NULL UNIQUE,
	homeroom_teacher_id INT NULL,
	FOREIGN KEY (homeroom_teacher_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE subjects (
	id INT AUTO_INCREMENT PRIMARY KEY,
	name VARCHAR(100) NOT NULL,
	code VARCHAR(20) NOT NULL UNIQUE,
	teacher_id INT NULL,
	class_id INT NULL,
	FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE SET NULL,
	FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE enrollments (
	id INT AUTO_INCREMENT PRIMARY KEY,
	student_id INT NOT NULL,
	class_id INT NOT NULL,
	UNIQUE KEY uniq_student_class (student_id, class_id),
	FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
	FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE attendance (
	id INT AUTO_INCREMENT PRIMARY KEY,
	student_id INT NOT NULL,
	subject_id INT NULL,
	class_id INT NOT NULL,
	date DATE NOT NULL,
	status ENUM('present','absent','late','excused') NOT NULL,
	marked_by INT NOT NULL,
	UNIQUE KEY uniq_att (student_id, class_id, subject_id, date),
	FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
	FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL,
	FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
	FOREIGN KEY (marked_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE assessments (
	id INT AUTO_INCREMENT PRIMARY KEY,
	student_id INT NOT NULL,
	subject_id INT NOT NULL,
	class_id INT NOT NULL,
	title VARCHAR(120) NOT NULL,
	max_score DECIMAL(6,2) NOT NULL,
	score DECIMAL(6,2) NOT NULL,
	assessed_on DATE NOT NULL,
	assessed_by INT NOT NULL,
	FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
	FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
	FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
	FOREIGN KEY (assessed_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE final_results (
	id INT AUTO_INCREMENT PRIMARY KEY,
	student_id INT NOT NULL,
	subject_id INT NOT NULL,
	class_id INT NOT NULL,
	final_percentage DECIMAL(5,2) NOT NULL,
	grade CHAR(2) NOT NULL,
	term VARCHAR(20) NOT NULL,
	year INT NOT NULL,
	entered_by INT NOT NULL,
	UNIQUE KEY uniq_final (student_id, subject_id, class_id, term, year),
	FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
	FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
	FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
	FOREIGN KEY (entered_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE messages (
	id INT AUTO_INCREMENT PRIMARY KEY,
	sender_id INT NOT NULL,
	receiver_id INT NOT NULL,
	subject VARCHAR(150) NOT NULL,
	body TEXT NOT NULL,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
	FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;