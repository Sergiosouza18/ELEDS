<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');
if (empty($_SESSION['admin_ok']) || empty($_SESSION['admin_user'])) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'Faça login para salvar orçamentos.']); exit; }
$file=__DIR__.'/data/orcamentos.json';
function qload($f){$a=is_file($f)?json_decode((string)file_get_contents($f),true):[];return is_array($a)?$a:[];}
function qsave($f,$a){return file_put_contents($f,json_encode(array_values($a),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX)!==false;}
function qs($v,$n=180){return mb_substr(trim((string)$v),0,$n,'UTF-8');}
function qstatus($v){$ok=['rascunho','enviado','negociacao','aprovado','perdido','cancelado'];return in_array($v,$ok,true)?$v:'rascunho';}
function qnumber($quotes){$y=date('Y');$max=0;foreach($quotes as $q)if(preg_match('/^ORC-'.$y.'-(\d+)$/',(string)($q['number']??''),$m))$max=max($max,(int)$m[1]);return 'ORC-'.$y.'-'.str_pad((string)($max+1),5,'0',STR_PAD_LEFT);}
$user=(string)$_SESSION['admin_user']; $action=$_GET['action']??''; $quotes=qload($file);
if($action==='load'){$id=(string)($_GET['id']??'');foreach($quotes as $q)if(($q['id']??'')===$id && strcasecmp((string)($q['user']??''),$user)===0){echo json_encode(['ok'=>true,'quote'=>$q],JSON_UNESCAPED_UNICODE);exit;}http_response_code(404);echo json_encode(['ok'=>false,'error'=>'Orçamento não encontrado.']);exit;}
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);echo json_encode(['ok'=>false,'error'=>'Método inválido.']);exit;}
$in=json_decode((string)file_get_contents('php://input'),true)?:[];
if($action==='save'){
 $items=$in['items']??[];if(!is_array($items)||!count($items)){http_response_code(422);echo json_encode(['ok'=>false,'error'=>'Adicione pelo menos um item.']);exit;}
 $clean=[];foreach(array_slice($items,0,50) as $i){$code=qs($i['code']??'',80);if($code==='')continue;$price=$i['price']??null;$clean[]=['code'=>$code,'name'=>qs($i['name']??'',180),'qty'=>max(1,min(999,(int)($i['qty']??1))),'price'=>is_numeric($price)&&$price>=0?(float)$price:null];}
 $customer=['name'=>qs($in['customer']['name']??'',160),'document'=>qs($in['customer']['document']??'',30),'phone'=>qs($in['customer']['phone']??'',30),'email'=>qs($in['customer']['email']??'',120),'vehicle'=>qs($in['customer']['vehicle']??'',120),'plate'=>qs($in['customer']['plate']??'',20)];
 $commercial=['validity'=>max(1,min(365,(int)($in['commercial']['validity']??7))),'payment'=>qs($in['commercial']['payment']??'',160),'return_date'=>qs($in['commercial']['return_date']??'',10)];
 $id=qs($in['id']??'',100);$now=date('c');$found=false;
 if($id!=='')foreach($quotes as &$q)if(($q['id']??'')===$id&&strcasecmp((string)($q['user']??''),$user)===0){$old=qstatus($q['status']??'rascunho');$new=qstatus($in['status']??$old);$q['items']=$clean;$q['discount']=max(0,min(100,(float)($in['discount']??0)));$q['note']=qs($in['note']??'',500);$q['customer']=$customer;$q['commercial']=$commercial;$q['status']=$new;$q['updated_at']=$now;if($new!==$old)$q['history'][]=['at'=>$now,'user'=>$user,'event'=>'status','from'=>$old,'to'=>$new];else $q['history'][]=['at'=>$now,'user'=>$user,'event'=>'updated'];$found=true;break;}unset($q);
 if(!$found){$id='orc_'.date('YmdHis').'_'.bin2hex(random_bytes(3));$num=qnumber($quotes);$status=qstatus($in['status']??'rascunho');$quotes[]=['id'=>$id,'number'=>$num,'user'=>$user,'items'=>$clean,'discount'=>max(0,min(100,(float)($in['discount']??0))),'note'=>qs($in['note']??'',500),'customer'=>$customer,'commercial'=>$commercial,'status'=>$status,'created_at'=>$now,'updated_at'=>$now,'history'=>[['at'=>$now,'user'=>$user,'event'=>'created']]];}
 if(!qsave($file,$quotes)){http_response_code(500);echo json_encode(['ok'=>false,'error'=>'Não foi possível gravar o orçamento.']);exit;}foreach($quotes as $q)if(($q['id']??'')===$id){echo json_encode(['ok'=>true,'id'=>$id,'number'=>$q['number']??''],JSON_UNESCAPED_UNICODE);exit;}
}
http_response_code(400);echo json_encode(['ok'=>false,'error'=>'Ação inválida.']);
