<?php
require_once 'config.php';
startSession();
$action = $_POST['action'] ?? $_GET['action'] ?? 'list';

switch($action){
    case 'list':          listProducts();   break;
    case 'get':           getProduct();     break;
    case 'add':           addProduct();     break;
    case 'mine':          myProducts();     break;
    case 'delete':        delProduct();     break;
    case 'set_status':    setStatus();      break;
    case 'pending':       getPending();     break;
    case 'categories':    getCategories();  break;
    default: jsonOut(['success'=>false,'message'=>'Unknown']);
}

function listProducts(){
    $db  = getDB();
    $cat = (int)($_GET['cat']??0);
    $q   = clean($_GET['q']??'');
    $org = $_GET['organic']??'';

    $where=['p.status="approved"'];$params=[];$types='';
    if($cat){$where[]='p.category_id=?';$params[]=$cat;$types.='i';}
    if($q){$like="%$q%";$where[]='(p.name LIKE ? OR p.description LIKE ?)';$params[]=$like;$params[]=$like;$types.='ss';}
    if($org==='1'){$where[]='p.is_organic=1';}

    $sql="SELECT p.*,u.name seller_name,u.phone seller_phone,c.name cat_name
          FROM products p JOIN users u ON p.seller_id=u.id LEFT JOIN categories c ON p.category_id=c.id
          WHERE ".implode(' AND ',$where)." ORDER BY p.created_at DESC LIMIT 80";
    $st=$db->prepare($sql);
    if($params){$st->bind_param($types,...$params);}
    $st->execute();
    jsonOut(['success'=>true,'products'=>$st->get_result()->fetch_all(MYSQLI_ASSOC)]);
}

function getProduct(){
    $id=(int)($_GET['id']??0);
    if(!$id)jsonOut(['success'=>false,'message'=>'ID required']);
    $db=getDB();
    $st=$db->prepare("SELECT p.*,u.name seller_name,u.phone seller_phone,u.address seller_addr,c.name cat_name
        FROM products p JOIN users u ON p.seller_id=u.id LEFT JOIN categories c ON p.category_id=c.id WHERE p.id=?");
    $st->bind_param('i',$id);$st->execute();
    $p=$st->get_result()->fetch_assoc();
    if(!$p)jsonOut(['success'=>false,'message'=>'Not found']);
    $r=$db->prepare("SELECT r.*,u.name buyer_name FROM reviews r JOIN users u ON r.buyer_id=u.id WHERE r.product_id=? ORDER BY r.created_at DESC LIMIT 5");
    $r->bind_param('i',$id);$r->execute();
    $p['reviews']=$r->get_result()->fetch_all(MYSQLI_ASSOC);
    jsonOut(['success'=>true,'product'=>$p]);
}

function addProduct(){
    anyRole('seller');
    $u=currentUser();
    $name=clean($_POST['name']??'');$desc=clean($_POST['description']??'');
    $price=(float)($_POST['price']??0);$qty=(int)($_POST['quantity']??0);
    $unit=clean($_POST['unit']??'kg');$cat=(int)($_POST['category_id']??0);
    $loc=clean($_POST['location']??'');$org=(int)($_POST['is_organic']??0);
    if(!$name||!$price||!$qty)jsonOut(['success'=>false,'message'=>'Name, price, quantity required']);
    $img='';
    if(isset($_FILES['image'])&&$_FILES['image']['error']===0){
        $ext=strtolower(pathinfo($_FILES['image']['name'],PATHINFO_EXTENSION));
        if(!in_array($ext,['jpg','jpeg','png','webp']))jsonOut(['success'=>false,'message'=>'Only JPG/PNG/WEBP']);
        $fn='crop_'.time().'_'.rand(100,999).'.'.$ext;
        $dir=UPLOAD_PATH;if(!is_dir($dir))mkdir($dir,0755,true);
        move_uploaded_file($_FILES['image']['tmp_name'],$dir.$fn);
        $img='uploads/crops/'.$fn;
    }
    $db=getDB();
    $st=$db->prepare("INSERT INTO products (seller_id,category_id,name,description,price,quantity,unit,location,is_organic,image,status) VALUES (?,?,?,?,?,?,?,?,?,?,'pending')");
    $st->bind_param('iissdissis',$u['id'],$cat,$name,$desc,$price,$qty,$unit,$loc,$org,$img);
    $st->execute();
    jsonOut(['success'=>true,'message'=>'Product submitted for admin approval!','id'=>$db->insert_id]);
}

function myProducts(){
    anyRole('seller');$u=currentUser();$db=getDB();
    $st=$db->prepare("SELECT p.*,c.name cat_name FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.seller_id=? ORDER BY p.created_at DESC");
    $st->bind_param('i',$u['id']);$st->execute();
    jsonOut(['success'=>true,'products'=>$st->get_result()->fetch_all(MYSQLI_ASSOC)]);
}

function delProduct(){
    anyRole('seller','admin');$u=currentUser();$db=getDB();
    $id=(int)($_POST['id']??0);
    if($u['role']==='seller'){
        $st=$db->prepare("DELETE FROM products WHERE id=? AND seller_id=?");$st->bind_param('ii',$id,$u['id']);
    }else{
        $st=$db->prepare("DELETE FROM products WHERE id=?");$st->bind_param('i',$id);
    }
    $st->execute();jsonOut(['success'=>true,'message'=>'Deleted']);
}

function setStatus(){
    requireRole('admin');$db=getDB();
    $id=(int)($_POST['id']??0);$status=clean($_POST['status']??'');
    if(!in_array($status,['approved','rejected','pending']))jsonOut(['success'=>false,'message'=>'Invalid status']);
    $st=$db->prepare("UPDATE products SET status=? WHERE id=?");$st->bind_param('si',$status,$id);$st->execute();
    jsonOut(['success'=>true,'message'=>'Status updated to '.$status]);
}

function getPending(){
    requireRole('admin');$db=getDB();
    $st=$db->query("SELECT p.*,u.name seller_name,c.name cat_name FROM products p JOIN users u ON p.seller_id=u.id LEFT JOIN categories c ON p.category_id=c.id WHERE p.status='pending' ORDER BY p.created_at DESC");
    jsonOut(['success'=>true,'products'=>$st->fetch_all(MYSQLI_ASSOC)]);
}

function getCategories(){
    $db=getDB();
    $r=$db->query("SELECT * FROM categories ORDER BY name");
    jsonOut(['success'=>true,'categories'=>$r->fetch_all(MYSQLI_ASSOC)]);
}
?>
