<?php
require_once 'config.php';
startSession();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch($action) {
    case 'login':    doLogin();    break;
    case 'register': doRegister(); break;
    case 'logout':   doLogout();   break;
    case 'me':       doMe();       break;
    default: jsonOut(['success'=>false,'message'=>'Unknown action']);
}

function doLogin() {
    $email = clean($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $role  = clean($_POST['role'] ?? 'buyer');

    if (!$email || !$pass) jsonOut(['success'=>false,'message'=>'Email and password required']);

    $db = getDB();
    $st = $db->prepare("SELECT * FROM users WHERE email=? AND role=? AND is_active=1 LIMIT 1");
    $st->bind_param('ss',$email,$role);
    $st->execute();
    $user = $st->get_result()->fetch_assoc();

    if (!$user || !checkPw($pass,$user['password']))
        jsonOut(['success'=>false,'message'=>'Invalid credentials or role mismatch']);

    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user_name']  = $user['name'];
    $_SESSION['user_role']  = $user['role'];
    $_SESSION['user_email'] = $user['email'];

    $dest = ['admin'=>'../admin.html','seller'=>'../seller.html','buyer'=>'../marketplace.html'];
    jsonOut(['success'=>true,'message'=>'Welcome back, '.$user['name'].'!','role'=>$user['role'],'name'=>$user['name'],'redirect'=>$dest[$user['role']]]);
}

function doRegister() {
    $name    = clean($_POST['name']    ?? '');
    $email   = clean($_POST['email']   ?? '');
    $pass    = $_POST['password']      ?? '';
    $role    = clean($_POST['role']    ?? 'buyer');
    $phone   = clean($_POST['phone']   ?? '');
    $address = clean($_POST['address'] ?? '');

    if (!$name||!$email||!$pass) jsonOut(['success'=>false,'message'=>'Name, email, password required']);
    if (!filter_var($email,FILTER_VALIDATE_EMAIL)) jsonOut(['success'=>false,'message'=>'Invalid email']);
    if (strlen($pass)<6) jsonOut(['success'=>false,'message'=>'Password min 6 characters']);
    if (!in_array($role,['buyer','seller'])) jsonOut(['success'=>false,'message'=>'Invalid role']);

    $db = getDB();
    $chk = $db->prepare("SELECT id FROM users WHERE email=?");
    $chk->bind_param('s',$email); $chk->execute();
    if ($chk->get_result()->num_rows>0) jsonOut(['success'=>false,'message'=>'Email already registered']);

    $h = hashPw($pass);
    $st = $db->prepare("INSERT INTO users (name,email,password,role,phone,address) VALUES (?,?,?,?,?,?)");
    $st->bind_param('ssssss',$name,$email,$h,$role,$phone,$address);
    $st->execute();
    jsonOut(['success'=>true,'message'=>'Account created! Please login.']);
}

function doLogout() {
    session_destroy();
    jsonOut(['success'=>true,'redirect'=>'../index.html']);
}

function doMe() {
    if (!isLoggedIn()) jsonOut(['success'=>false,'logged_in'=>false]);
    jsonOut(['success'=>true,'logged_in'=>true,'user'=>currentUser()]);
}
?>
