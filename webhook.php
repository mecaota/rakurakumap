<?php
//json encode
$setting_json = file_get_contents('secret.json');
$setting_json = mb_convert_encoding($setting_json, 'UTF8', 'ASCII,JIS,UTF-8,EUC-JP,SJIS-WIN');
$json_arr = json_decode($setting_json,true);
print($setting_json);

// LINE Messaging API Access Token
$accessToken = $json_arr["line"]["accessToken"];
//$accessToken = "IibzzLYaKUjrL9A+twqwvrstb1FJ3xFcXlMkBoJZiGP7+vW5/eb0jGGXgomHHSE42Hif84hsS/Bmll1OF5cD2Kpl5i44/GufXRBJV7HqC/TFXF7pQZOiDk4oK4oLaYooJEGh0DbbDYa26bbAVUjUugdB04t89/1O/w1cDnyilFU=";

//-------------------------------------------------
// 0. Webhook Event Objectの取り込み
//-------------------------------------------------
$eventJson = file_get_contents('php://input');
$eventObj = json_decode($eventJson);
$userId = $eventObj->events[0]->source->userId;
$type = $eventObj->events[0]->message->type;
$text = $eventObj->events[0]->message->text;
$replyToken = $eventObj->events[0]->replyToken;
$timeStamp = $eventObj->events[0]->timestamp;

file_put_contents("log.txt",(string)$eventJson);

// Text以外は終了
if($type != 'text') {
  print("responce ok");
  exit;
  }

//-------------------------------------------------
// 2. 形態素解析
//-------------------------------------------------
// 英文字を小文字化
$text = strtolower($text);

// 英数字の全角→半角変換
$text	= mb_convert_kana($text, "aKHsV", "utf-8");

// mecabによる形態素解析
$mecab = new \MeCab\Tagger();
$nodes = $mecab->parseToNode($text);
foreach ($nodes as $n)
{
    $oWakati = $oWakati . $n->getSurface() . " ";
}

// 最後の空白を削除
$oTail   = substr( $oWakati , -1 , 1 );
if( $oTail === ' ' ){
    /* 末尾の文字の手前までを取り出して、単語とする */
    $oLength = strlen( $oWakati );
    $oWakati   = substr( $oWakati , 0 , $oLength - 1 );
} 


//-------------------------------------------------
// 3. api.ai 自然対話処理
//-------------------------------------------------
$clientAccessToken = $json_arr["apiai"]["clientAccessToken"];
$apiUrl = 'https://api.api.ai/v1/query?v=v=20150910';
$reqBody = [
  'query' => $oWakati,
  'sessionId' => $json_arr["apiai"]["sessionId"],
  'lang' => 'ja',
];

$headers = [
  'Content-Type: application/json; charset=UTF-8',
  'Authorization: Bearer' . $clientAccessToken
];
$options = [
  'http'=> [
    'method'  => 'POST',
    'header'  => implode("\r\n", $headers),
    'content' => json_encode($reqBody)
  ]
];
$stream = stream_context_create($options);
$resApi = file_get_contents($apiUrl, false, $stream);
$resApiJson = json_decode($resApi);

$resText = $resApiJson->result->fulfillment->speech;

if($resText){
  print("responce get"); 
  $response = [
    [
      'type' => 'text',
      'text' => $resText
    ]
  ];
}else{
  print("responce failed");
  $response = [
    [
      'type' => 'text',
      'text' => 'Hello'
    ]
  ];
}

//-------------------------------------------------
// Reply Message送信
//-------------------------------------------------
$response = [
    [
      'type' => 'text',
      'text' => 'Hello'
    ]
  ];
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