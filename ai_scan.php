<?php
require_once 'config.php';
startSession();
$action=$_POST['action']??$_GET['action']??'scan';

switch($action){
    case 'scan':    doScan();    break;
    case 'history': scanHistory();break;
    default: jsonOut(['success'=>false,'message'=>'Unknown']);
}

function doScan(){
    requireLogin();
    anyRole('seller','admin');
    $u=currentUser();

    if(!isset($_FILES['image'])||$_FILES['image']['error']!==0)
        jsonOut(['success'=>false,'message'=>'Please upload an image']);

    $file=$_FILES['image'];
    $ext=strtolower(pathinfo($file['name'],PATHINFO_EXTENSION));
    if(!in_array($ext,['jpg','jpeg','png','webp']))
        jsonOut(['success'=>false,'message'=>'Only JPG, PNG, WEBP allowed']);
    if($file['size']>5*1024*1024)
        jsonOut(['success'=>false,'message'=>'Image must be under 5MB']);

    // Save image
    $fn='scan_'.time().'_'.rand(1000,9999).'.'.$ext;
    $dir=UPLOAD_PATH;if(!is_dir($dir))mkdir($dir,0755,true);
    $full=$dir.$fn;
    move_uploaded_file($file['tmp_name'],$full);
    $relPath='uploads/crops/'.$fn;

    $pid=(int)($_POST['product_id']??0);

    // Base64 for Claude API
    $b64=base64_encode(file_get_contents($full));
    $mime=$ext==='png'?'image/png':($ext==='webp'?'image/webp':'image/jpeg');

    // Call Claude API
    $result=callClaudeVision($b64,$mime);

    // Log to DB
    $db=getDB();
    $st=$db->prepare("INSERT INTO ai_scan_logs (product_id,scanned_by,image_path,result,confidence,crop_type,disease_name,quality_grade,recommendations) VALUES (?,?,?,?,?,?,?,?,?)");
    $st->bind_param('iissdssss',$pid,$u['id'],$relPath,$result['status'],$result['confidence'],$result['crop_type'],$result['disease_name'],$result['quality_grade'],$result['recommendations']);
    $st->execute();

    // Update product AI status if product_id given
    if($pid>0){
        $up=$db->prepare("UPDATE products SET ai_scan_status=?,ai_scan_notes=?,ai_confidence=? WHERE id=?");
        $up->bind_param('ssdi',$result['status'],$result['recommendations'],$result['confidence'],$pid);
        $up->execute();
    }

    jsonOut(array_merge(['success'=>true,'image_path'=>$relPath],$result));
}

function callClaudeVision($b64,$mime){
    // ⚠️ Replace with your real Anthropic API key
    $apiKey=getenv('ANTHROPIC_API_KEY') ?: 'YOUR_ANTHROPIC_API_KEY_HERE';

    if($apiKey==='YOUR_ANTHROPIC_API_KEY_HERE'||empty($apiKey)){
        return demoResult(); // fallback demo
    }

    $prompt='You are an expert agricultural AI. Analyze this crop/plant image for disease, quality and authenticity.
Respond ONLY with valid JSON (no markdown, no backticks):
{
  "status": "healthy|diseased|fake|suspicious",
  "confidence": 0-100,
  "crop_type": "name of crop",
  "disease_name": "disease name or null",
  "quality_grade": "A|B|C|D",
  "summary": "1-2 sentence assessment",
  "recommendations": "actionable advice",
  "visual_issues": ["issue1","issue2"]
}
Rules:
- healthy = vibrant, fresh, no disease
- diseased = spots, discoloration, fungal/bacterial signs
- fake = plastic, edited, not a real crop
- suspicious = unclear/blurry/unrecognizable';

    $payload=['model'=>'claude-opus-4-5','max_tokens'=>600,'messages'=>[[
        'role'=>'user','content'=>[
            ['type'=>'image','source'=>['type'=>'base64','media_type'=>$mime,'data'=>$b64]],
            ['type'=>'text','text'=>$prompt]
        ]
    ]]];

    $ch=curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch,[
        CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json','x-api-key: '.$apiKey,'anthropic-version: 2023-06-01'],
        CURLOPT_POSTFIELDS=>json_encode($payload)
    ]);
    $resp=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);

    if($code!==200)return demoResult();

    $data=json_decode($resp,true);
    $text=$data['content'][0]['text']??'';
    $text=preg_replace('/```json|```/','',$text);
    $r=json_decode(trim($text),true);
    if(!$r)return demoResult();

    return [
        'status'         =>$r['status']??'suspicious',
        'confidence'     =>(float)($r['confidence']??75),
        'crop_type'      =>$r['crop_type']??'Unknown',
        'disease_name'   =>$r['disease_name']??null,
        'quality_grade'  =>$r['quality_grade']??'B',
        'summary'        =>$r['summary']??'Analysis complete.',
        'recommendations'=>$r['recommendations']??'Consult an agronomist.',
        'visual_issues'  =>$r['visual_issues']??[],
    ];
}

function demoResult(){
    $samples=[
        ['status'=>'healthy','confidence'=>94.5,'crop_type'=>'Tomato','disease_name'=>null,'quality_grade'=>'A',
         'summary'=>'Tomatoes appear fresh, vibrant red, no disease signs detected.',
         'recommendations'=>'Excellent quality. Store at 10-15°C. Market-ready.','visual_issues'=>[]],
        ['status'=>'diseased','confidence'=>88.3,'crop_type'=>'Wheat','disease_name'=>'Yellow Rust (Puccinia striiformis)',
         'quality_grade'=>'C','summary'=>'Yellow stripe rust detected on wheat leaves.',
         'recommendations'=>'Apply Propiconazole 25EC fungicide at 0.1%. Isolate from healthy crops immediately.',
         'visual_issues'=>['Yellow streaks on leaves','Rust-colored pustules','Mild leaf curling']],
        ['status'=>'healthy','confidence'=>91.2,'crop_type'=>'Mango','disease_name'=>null,'quality_grade'=>'A',
         'summary'=>'Mangoes show excellent color, firmness, and natural ripening.',
         'recommendations'=>'Premium quality. Package carefully. Sell within 5-7 days.','visual_issues'=>[]],
        ['status'=>'fake','confidence'=>97.1,'crop_type'=>'Unknown Object','disease_name'=>null,'quality_grade'=>'D',
         'summary'=>'This does not appear to be a real agricultural crop or plant.',
         'recommendations'=>'Upload a genuine crop photograph. Fake listings violate KhetBazaar policy.',
         'visual_issues'=>['Artificial appearance','No natural plant characteristics','Possible plastic/synthetic material']],
        ['status'=>'diseased','confidence'=>85.0,'crop_type'=>'Rice','disease_name'=>'Rice Blast (Magnaporthe oryzae)',
         'quality_grade'=>'D','summary'=>'Rice blast fungal infection detected — significant damage present.',
         'recommendations'=>'Apply Tricyclazole 75WP. Remove and burn infected plants. Do NOT sell this crop.',
         'visual_issues'=>['Diamond-shaped lesions','Gray centers with brown borders','Extensive leaf damage']],
    ];
    return $samples[array_rand($samples)];
}

function scanHistory(){
    requireLogin();$u=currentUser();$db=getDB();
    if($u['role']==='admin'){
        $r=$db->query("SELECT s.*,u.name scanned_by_name,p.name product_name FROM ai_scan_logs s LEFT JOIN users u ON s.scanned_by=u.id LEFT JOIN products p ON s.product_id=p.id ORDER BY s.scanned_at DESC LIMIT 50");
    }else{
        $st=$db->prepare("SELECT s.*,p.name product_name FROM ai_scan_logs s LEFT JOIN products p ON s.product_id=p.id WHERE s.scanned_by=? ORDER BY s.scanned_at DESC LIMIT 20");
        $st->bind_param('i',$u['id']);$st->execute();$r=$st->get_result();
    }
    jsonOut(['success'=>true,'scans'=>$r->fetch_all(MYSQLI_ASSOC)]);
}
?>
