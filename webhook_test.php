<?php
function googleMapsURL($param, $location, $key = null){
  $url = "https://google.com/maps/";
  $req_param;
  $opt_param;
  $discription;
  switch($param){
    case 'pano':
      $discription = "目的地の周辺の様子をパノラマ画像で確認するにはこちら！\n";
      $req_param = "@?api=1&map_action=pano";
      $opt_param = "&viewpoint=".$location;
      break;
    case 'map':
      $discription = "目的地の周辺を地図で確認するにはこちら！\n";
      $req_param = "@?api=1&map_action=map";
      $opt_param = "&zoom=18&center=".$location;
      break;
    case 'search':
      $discription = $key."で検索した目的地周辺の様子を確認するにはこちら！\n";
      $req_param = "search/?api=1";
      $opt_param = "&query=".$location."+".$key;
      break;
    case 'pic':
      $url = 'https://maps.googleapis.com/maps/api/place/nearbysearch/json';
      $req_param = '?location='.$location.'&radius=1&key='.$key;
      $opt_param = '';
      $map_json = file_get_contents($url.$req_param.$opt_param);
      $map_json = mb_convert_encoding($map_json, 'UTF8', 'ASCII,JIS,UTF-8,EUC-JP,SJIS-WIN');
      $map_arr = json_decode($map_json,true);
      file_put_contents("log.txt", "gmap_responce:".$map_arr."\n", FILE_APPEND | LOCK_EX);
      $url = "https://maps.googleapis.com/maps/api/place/photo?maxwidth=400&photoreference=".$map_arr["results"][0]["photos"][0]["photo_reference"]."&key=".$key;
      $result = $url;
      return $result;
  }
  $result = $discription.$url.$req_param.$opt_param;
  return $result;
}

function apiai($clientAccessToken, $sessionId){
  $apiUrl = 'https://api.api.ai/v1/query?v=v=20150910';
  $reqBody = [
    'query' => $text,
    'sessionId' => $sessionId,
    'lang' => 'ja',
  ];

  $headers = [
    'Content-Type: application/json; charset=UTF-8',
    'Authorization: Bearer' . $clientAccessToken
  ];
  $options = [
    'http'=> [
      'method'  => 'POST',
      'header'  => implode('\r\n', $headers),
      'content' => json_encode($reqBody)
    ]
  ];
  return $options;
}

//フラグ
$flag = 0;

//json encode
$setting_json = file_get_contents('secret.json');
$setting_json = mb_convert_encoding($setting_json, 'UTF8', 'ASCII,JIS,UTF-8,EUC-JP,SJIS-WIN');
$json_arr = json_decode($setting_json,true);
//file_put_contents("log.txt","");

// LINE Messaging API Access Token
$accessToken = $json_arr["line"]["accessToken"];

//-------------------------------------------------
// 0. Webhook Event Objectの取り込み
//-------------------------------------------------
$eventJson = file_get_contents('php://input');
$eventObj = json_decode($eventJson);
$userId = $eventObj->events[0]->source->userId;
$type = $eventObj->events[0]->message->type;
$text = $eventObj->events[0]->message->text;
$latitude = $eventObj->events[0]->message->latitude;
$longitude = $eventObj->events[0]->message->longitude;
$replyToken = $eventObj->events[0]->replyToken;
$timeStamp = $eventObj->events[0]->timestamp;
$reply = "ぬるぽ";
file_put_contents("log.txt", "line_responce:".$eventJson."\n", FILE_APPEND | LOCK_EX);
print($text);

// typeでswitch
switch($type){
  case 'text':
    $options = apiai($json_arr["apiai"]["clientAccessToken"], $json_arr["apiai"]["sessionId"]);
    $stream = stream_context_create($options);
    $resApi = file_get_contents($apiUrl, false, $stream);
    $resApiJson = json_decode($resApi);
    $resText = $resApiJson->result->fulfillment->speech;

    if($resText){
      print("responce get"); 
      $reply = $resText;
    }else{
      print("responce failed");
      $reply = $text;
    }
    $flag = 0;
    break;

  case 'location':
    $flag=1;
    $location = $latitude.",".$longitude;
    $reply = googleMapsURL("map", $location)."\n".googleMapsURL("pano",$location);
    break;
}

//-------------------------------------------------
// Reply Message送信
//-------------------------------------------------
file_put_contents("log.txt", "reply_text:".($reply)."\n", FILE_APPEND | LOCK_EX);
if($flag==1){
  if($text=="画像"){
    $responce = [
      [
        'type' => 'image',
        'originalContentUrl' => googleMapsURL("pic", $location, $json_arr["googlemap"]["apikey"]),
        'previewImageUrl'=> googleMapsURL("pic", $location, $json_arr["googlemap"]["apikey"])
      ]
    ];
    $flag =0;
  }elseif($text=="横浜"){
$responce = [
      [
        'type' => 'image',
        'originalContentUrl' => "yokohama.png",
        'previewImageUrl'=> "yokohama.png"
      ]
    ];
  }
}else{
  $response = [
    [
      'type' => 'text',
      'text' => $reply
    ]
  ];
  $flag =1;
}

$postData = [
    'replyToken' => $replyToken,
    'messages' => $response
    ];

$ch = curl_init("https://api.line.me/v2/bot/message/reply");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/json; charser=UTF-8',
    'Authorization: Bearer ' . $accessToken
    ));
$result = curl_exec($ch);
curl_close($ch);
?>