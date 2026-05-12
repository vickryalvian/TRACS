<?php require '_bootstrap.php';
$conn->query("CREATE TABLE IF NOT EXISTS tracs_ticker_messages (
  id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL,
  text VARCHAR(500) NOT NULL, class ENUM('normal','info','urgent','critical') DEFAULT 'normal',
  enabled TINYINT(1) DEFAULT 1, created_at DATETIME DEFAULT NOW(), INDEX(user_id))");
$res=$conn->query("SELECT * FROM tracs_ticker_messages WHERE user_id=$uid AND enabled=1 ORDER BY created_at DESC");
$rows=[]; while($r=$res->fetch_assoc()) $rows[]=$r;
ok($rows);
