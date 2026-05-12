<?php require '_bootstrap.php';
$conn->query("CREATE TABLE IF NOT EXISTS tracs_domains (id INT AUTO_INCREMENT PRIMARY KEY,user_id INT NOT NULL,domain VARCHAR(253) NOT NULL,registrar VARCHAR(200),expires_at DATE,ssl_active TINYINT(1) DEFAULT 0,auto_renew TINYINT(1) DEFAULT 0,notes VARCHAR(500),created_at DATETIME DEFAULT NOW(),updated_at DATETIME DEFAULT NOW(),INDEX(user_id))");
$id=(int)($body['id']??0); if(!$id) fail('ID required');
$row=$conn->query("SELECT domain FROM tracs_domains WHERE id=$id AND user_id=$uid")->fetch_assoc();
if(!$row) fail('Not found',404);
$conn->query("DELETE FROM tracs_domains WHERE id=$id AND user_id=$uid");
logAct($conn,$uid,'deleted','Domains',"Deleted domain: {$row['domain']}",$id);
ok(null,'Deleted');
