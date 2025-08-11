-- Database: maternal_health_app

-- Users table with role-based access
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('mother','physician','admin') NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Appointments between mothers and physicians
CREATE TABLE IF NOT EXISTS appointments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  mother_id INT NOT NULL,
  physician_id INT NOT NULL,
  appointment_time DATETIME NOT NULL,
  status ENUM('pending','approved','completed','cancelled') NOT NULL DEFAULT 'pending',
  notes TEXT,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_appointments_mother FOREIGN KEY (mother_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_appointments_physician FOREIGN KEY (physician_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Simple direct messages for chat
CREATE TABLE IF NOT EXISTS messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sender_id INT NOT NULL,
  receiver_id INT NOT NULL,
  content TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_messages_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_messages_receiver FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_messages_pair (sender_id, receiver_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Health records / information uploads
CREATE TABLE IF NOT EXISTS health_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  mother_id INT NOT NULL,
  uploaded_by INT NULL,
  title VARCHAR(200) NOT NULL,
  content TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_health_records_mother FOREIGN KEY (mother_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_health_records_uploader FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Emergency alerts from mothers
CREATE TABLE IF NOT EXISTS emergencies (
  id INT AUTO_INCREMENT PRIMARY KEY,
  mother_id INT NOT NULL,
  description TEXT,
  status ENUM('open','acknowledged','resolved') NOT NULL DEFAULT 'open',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_emergencies_mother FOREIGN KEY (mother_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- User alarms/reminders
CREATE TABLE IF NOT EXISTS alarms (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  title VARCHAR(200) NOT NULL,
  alarm_time DATETIME NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_alarms_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_alarms_user_time (user_id, alarm_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed a demo physician and mother (change passwords in production)
INSERT INTO users (name, email, password_hash, role) VALUES
  ('Demo Mother', 'mother@example.com', '$2y$10$6mxT7xwVZ0Jc2x56n8Yz8e9q2E4l6bW7zTgT6CkYdV6sYz7h0i0yS', 'mother'),
  ('Demo Physician', 'physician@example.com', '$2y$10$6mxT7xwVZ0Jc2x56n8Yz8e9q2E4l6bW7zTgT6CkYdV6sYz7h0i0yS', 'physician');
-- Password for both seeds is: Password@123