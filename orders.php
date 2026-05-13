<?php
require_once 'config.php';
startSession();
$action=$_POST['action']??$_GET['action']??'';

switch($action){
    case 'cart_add':    cartAdd();    break;
    case 'cart_get':    cartGet();    break;
    case 'cart_rm':     cartRm();     break;
    case 'cart_clear':  cartClear();  break;
    case 'checkout':    checkout();   break;
    case 'pay':         fakePayment();break;
    case 'my_orders':   myOrders();   break;
    case 'all_orders':  allOrders();  break;
    case 'upd_order':   updOrder();   break;
    default: jsonOut(['success'=>false,'message'=>'Unknown']);
}

function cartAdd(){
    requireLogin();$u=currentUser();$db=getDB();
    $pid=(int)($_POST['product_id']??0);$qty=(int)($_POST['quantity']??1);
    $chk=$db->prepare("SELECT id,quantity FROM cart WHERE buyer_id=? AND product_id=?");
    $chk->bind_param('ii',$u['id'],$pid);$chk->execute();
    $ex=$chk->get_result()->fetch_assoc();
    if($ex){$nq=$ex['quantity']+$qty;$up=$db->prepare("UPDATE cart SET quantity=? WHERE id=?");$up->bind_param('ii',$nq,$ex['id']);$up->execute();}
    else{$ins=$db->prepare("INSERT INTO cart (buyer_id,product_id,quantity) VALUES (?,?,?)");$ins->bind_param('iii',$u['id'],$pid,$qty);$ins->execute();}
    jsonOut(['success'=>true,'message'=>'Added to cart!']);
}

function cartGet(){
    requireLogin();$u=currentUser();$db=getDB();
    $st=$db->prepare("SELECT c.id cart_id,c.quantity,p.id pid,p.name,p.price,p.unit,p.image,p.quantity stock,u.name seller FROM cart c JOIN products p ON c.product_id=p.id JOIN users u ON p.seller_id=u.id WHERE c.buyer_id=?");
    $st->bind_param('i',$u['id']);$st->execute();
    $items=$st->get_result()->fetch_all(MYSQLI_ASSOC);
    $total=array_sum(array_map(fn($i)=>$i['price']*$i['quantity'],$items));
    jsonOut(['success'=>true,'items'=>$items,'total'=>$total,'count'=>count($items)]);
}

function cartRm(){
    requireLogin();$u=currentUser();$db=getDB();
    $cid=(int)($_POST['cart_id']??0);
    $st=$db->prepare("DELETE FROM cart WHERE id=? AND buyer_id=?");$st->bind_param('ii',$cid,$u['id']);$st->execute();
    jsonOut(['success'=>true]);
}

function cartClear(){
    requireLogin();$u=currentUser();$db=getDB();
    $db->prepare("DELETE FROM cart WHERE buyer_id=?")->bind_param('i',$u['id'])->execute();
    jsonOut(['success'=>true]);
}

function checkout(){
    requireLogin();$u=currentUser();$db=getDB();
    $pm=clean($_POST['payment_method']??'cod');
    $addr=clean($_POST['address']??'');
    $notes=clean($_POST['notes']??'');

    $st=$db->prepare("SELECT c.quantity,p.id pid,p.price,p.quantity stock FROM cart c JOIN products p ON c.product_id=p.id WHERE c.buyer_id=?");
    $st->bind_param('i',$u['id']);$st->execute();
    $items=$st->get_result()->fetch_all(MYSQLI_ASSOC);
    if(empty($items))jsonOut(['success'=>false,'message'=>'Cart is empty']);

    $total=array_sum(array_map(fn($i)=>$i['price']*$i['quantity'],$items));
    $os=$db->prepare("INSERT INTO orders (buyer_id,total_amount,payment_method,shipping_address,notes) VALUES (?,?,?,?,?)");
    $os->bind_param('idsss',$u['id'],$total,$pm,$addr,$notes);$os->execute();
    $oid=$db->insert_id;
    foreach($items as $it){
        $is=$db->prepare("INSERT INTO order_items (order_id,product_id,quantity,price) VALUES (?,?,?,?)");
        $is->bind_param('iiid',$oid,$it['pid'],$it['quantity'],$it['price']);$is->execute();
    }
    jsonOut(['success'=>true,'order_id'=>$oid,'total'=>$total,'message'=>'Order created!']);
}

function fakePayment(){
    requireLogin();$db=getDB();
    $oid=(int)($_POST['order_id']??0);
    $method=clean($_POST['method']??'upi');

    // Simulate bank delay — 95% success rate
    usleep(800000); // fake processing time 0.8s
    $ok=(rand(1,100)<=95);
    $txn=fakeTxn();

    if($ok){
        $st=$db->prepare("UPDATE orders SET payment_status='paid',payment_txn_id=?,payment_method=?,status='confirmed' WHERE id=?");
        $st->bind_param('ssi',$txn,$method,$oid);$st->execute();

        // Get total for receipt
        $tot=$db->prepare("SELECT total_amount FROM orders WHERE id=?");
        $tot->bind_param('i',$oid);$tot->execute();
        $amount=$tot->get_result()->fetch_assoc()['total_amount']??0;

        // Clear cart
        $u=currentUser();
        $db->prepare("DELETE FROM cart WHERE buyer_id=?")->bind_param('i',$u['id'])->execute();

        jsonOut(['success'=>true,'txn_id'=>$txn,'amount'=>$amount,'method'=>$method,
            'message'=>'Payment Successful!','timestamp'=>date('d M Y, H:i:s')]);
    }else{
        $st=$db->prepare("UPDATE orders SET payment_status='failed' WHERE id=?");
        $st->bind_param('i',$oid);$st->execute();
        jsonOut(['success'=>false,'message'=>'Payment declined. Try again.']);
    }
}

function myOrders(){
    requireLogin();$u=currentUser();$db=getDB();
    $st=$db->prepare("SELECT o.*,COUNT(oi.id) items FROM orders o LEFT JOIN order_items oi ON o.id=oi.order_id WHERE o.buyer_id=? GROUP BY o.id ORDER BY o.created_at DESC");
    $st->bind_param('i',$u['id']);$st->execute();
    jsonOut(['success'=>true,'orders'=>$st->get_result()->fetch_all(MYSQLI_ASSOC)]);
}

function allOrders(){
    requireRole('admin');$db=getDB();
    $r=$db->query("SELECT o.*,u.name buyer_name,u.email buyer_email,COUNT(oi.id) items FROM orders o JOIN users u ON o.buyer_id=u.id LEFT JOIN order_items oi ON o.id=oi.order_id GROUP BY o.id ORDER BY o.created_at DESC LIMIT 200");
    jsonOut(['success'=>true,'orders'=>$r->fetch_all(MYSQLI_ASSOC)]);
}

function updOrder(){
    requireRole('admin');$db=getDB();
    $id=(int)($_POST['id']??0);$status=clean($_POST['status']??'');
    if(!in_array($status,['pending','confirmed','shipped','delivered','cancelled']))jsonOut(['success'=>false,'message'=>'Invalid']);
    $st=$db->prepare("UPDATE orders SET status=? WHERE id=?");$st->bind_param('si',$status,$id);$st->execute();
    jsonOut(['success'=>true,'message'=>'Order updated']);
}
?>
