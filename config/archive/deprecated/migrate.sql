-- Ticker events: auto-generated operational events with 1h expiry
CREATE TABLE IF NOT EXISTS tracs_ticker_events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  message VARCHAR(500) NOT NULL,
  type ENUM('info','success','warning','critical') DEFAULT 'info',
  module VARCHAR(50) DEFAULT NULL,
  reference_id INT DEFAULT NULL,
  created_at DATETIME DEFAULT NOW(),
  expires_at DATETIME DEFAULT NULL,
  INDEX(user_id, expires_at)
);

-- Shift handover reports
CREATE TABLE IF NOT EXISTS tracs_shift_reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  shift_name VARCHAR(50) NOT NULL DEFAULT 'Shift 1',
  title VARCHAR(255) NOT NULL,
  details TEXT DEFAULT NULL,
  priority ENUM('low','medium','high','critical') DEFAULT 'medium',
  status ENUM('active','resolved') DEFAULT 'active',
  active_date DATE DEFAULT (CURRENT_DATE),
  created_by INT NOT NULL,
  created_at DATETIME DEFAULT NOW(),
  updated_at DATETIME DEFAULT NOW() ON UPDATE NOW(),
  resolved_at DATETIME DEFAULT NULL,
  INDEX(active_date, status),
  INDEX(created_by)
);
