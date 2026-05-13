<?php
require_once 'config.php';
startSession();
$action=$_POST['action']??$_GET['action']??'stats';

switch($action){
    case 'stats':       adminStats();    break;
    case 'users':       listUsers();     break;
    case 'toggle_user': toggleUser();    break;
    case 'delete_user': deleteUser();    break;
    case 'ai_logs':     aiLogs();        break;
    default: jsonOut(['success'=>false,'message'=>'Unknown']);
}

function adminStats(){
    requireRole('admin');$db=getDB();
    $s=[];
    $s['buyers']      =$db->query("SELECT COUNT(*) c FROM users WHERE role='buyer'")->fetch_assoc()['c'];
    $s['sellers']     =$db->query("SELECT COUNT(*) c FROM users WHERE role='seller'")->fetch_assoc()['c'];
    $s['products']    =$db->query("SELECT COUNT(*) c FROM products")->fetch_assoc()['c'];
    $s['approved']    =$db->query("SELECT COUNT(*) c FROM products WHERE status='approved'")->fetch_assoc()['c'];
    $s['pending']     =$db->query("SELECT COUNT(*) c FROM products WHERE status='pending'")->fetch_assoc()['c'];
    $s['orders']      =$db->query("SELECT COUNT(*) c FROM orders")->fetch_assoc()['c'];
    $s['revenue']     =$db->query("SELECT COALESCE(SUM(total_amount),0) c FROM orders WHERE payment_status='paid'")->fetch_assoc()['c'];
    $s['ai_scans']    =$db->query("SELECT COUNT(*) c FROM ai_scan_logs")->fetch_assoc()['c'];
    $s['diseased']    =$db->query("SELECT COUNT(*) c FROM ai_scan_logs WHERE result='diseased'")->fetch_assoc()['c'];
    $s['fake_crops']  =$db->query("SELECT COUNT(*) c FROM ai_scan_logs WHERE result='fake'")->fetch_assoc()['c'];
    $s['recent_orders']=$db->query("SELECT o.*,u.name buyer_name FROM orders o JOIN users u ON o.buyer_id=u.id ORDER BY o.created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
    jsonOut(['success'=>true,'stats'=>$s]);
}

function listUsers(){
    requireRole('admin');$db=getDB();
    $role=clean($_GET['role']??'');
    if($role&&in_array($role,['buyer','seller'])){
        $st=$db->prepare("SELECT id,name,email,role,phone,address,is_active,created_at FROM users WHERE role=? ORDER BY created_at DESC");
        $st->bind_param('s',$role);$st->execute();
        $users=$st->get_result()->fetch_all(MYSQLI_ASSOC);
    }else{
        $users=$db->query("SELECT id,name,email,role,phone,address,is_active,created_at FROM users WHERE role!='admin' ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
    }
    jsonOut(['success'=>true,'users'=>$users]);
}

function toggleUser(){
    requireRole('admin');$db=getDB();$id=(int)($_POST['id']??0);
    $db->prepare("UPDATE users SET is_active=1-is_active WHERE id=?")->bind_param('i',$id)->execute();
    jsonOut(['success'=>true,'message'=>'User status toggled']);
}

function deleteUser(){
    requireRole('admin');$db=getDB();$id=(int)($_POST['id']??0);
    $db->prepare("DELETE FROM users WHERE id=? AND role!='admin'")->bind_param('i',$id)->execute();
    jsonOut(['success'=>true,'message'=>'User deleted']);
}

function aiLogs(){
    requireRole('admin');$db=getDB();
    $r=$db->query("SELECT s.*,u.name scanned_by_name,p.name product_name FROM ai_scan_logs s LEFT JOIN users u ON s.scanned_by=u.id LEFT JOIN products p ON s.product_id=p.id ORDER BY s.scanned_at DESC LIMIT 100");
    jsonOut(['success'=>true,'logs'=>$r->fetch_all(MYSQLI_ASSOC)]);
}
?>
