<?php
require_once("config_global_settings.php");

$rawInput = file_get_contents('php://input');
$_POST = json_decode($rawInput, true);

if( !$config_mongo_host ){
    header ('HTTP/1.1 404 Not Found');
    echo "<html><body>Configuration Not Found</body></html>";
    exit;
}

/* Mongo DB connection */
require("class_mongodb.php");

$mongodb_con =  new mongodb_connection( $config_mongo_host, $config_mongo_port,$config_mongo_db,$config_mongo_auth_source,$config_mongo_user,$config_mongo_pass);
/* Mongo DB connection */

function execute_curl_request($apidata = array(),$postbody = ""){
	global $config_curl_timedout,$config_internal_mail_alerts,$config_curl_timedout_cron;
	$timeout = ((int)$apidata["timeout"]>0?$apidata["timeout"]:$config_curl_timedout);
	if($timeout>$config_curl_timedout_cron){
		error_log($apidata["action"]."__Curl connection timedout is :".$config_curl_timedout);
	}
	if(!$apidata["action"] || $apidata["action"] ==""){
		$return_result = ["status"=>"fail","data"=>"action parameter  is empty"];
		return $return_result ;
		exit;
	}
	if(!$apidata["headers"] || !is_array($apidata["headers"])){
		$return_result = ["status"=>"fail","data"=>"headers parameter  is empty"];
		return $return_result ;
		exit;
	}
	if(!$apidata["method"] || $apidata["method"] ==""){
		$return_result = ["status"=>"fail","data"=>"action parameter  is empty"];
		return $return_result ;
		exit;
	}
	if(!$apidata["url"] || $apidata["url"] ==""){
		$return_result = ["status"=>"fail","data"=>"url parameter  is empty"];
		return $return_result ;
		exit;
	}
	if(!$apidata["content-type"] || $apidata["content-type"] ==""){
		$return_result = ["status"=>"fail","data"=>"content-type parameter  is empty"];
		return $return_result ;
		exit;
	}
	
	$ch = curl_init();
	$options = array(
		CURLOPT_HEADER => 0,
		CURLOPT_URL => $apidata['url'],
			CURLOPT_CONNECTTIMEOUT_MS=> 5000, // 5000 ms connection timeout
			CURLOPT_TIMEOUT => (int)$timeout,
			CURLOPT_RETURNTRANSFER =>true,
			CURLOPT_AUTOREFERER=>true,
			CURLOPT_SSL_VERIFYHOST=>false,
			CURLOPT_SSL_VERIFYPEER=>false
		);
	$request_headers = [];
	$is_user_agent=false;
	$is_content_type=false;
	foreach( $apidata['headers'] as $i=>$j ){
		$request_headers[] = $i.": ". $j;
		if( strtolower((string)$i) == "user-agent" ){
			$is_user_agent=true;
		}
		if( strtolower((string)$i) == "content-type" ){
			$is_content_type=true;
		}
	}
	if( !$is_user_agent ){
		$request_headers[] = "User-Agent: CT Finance CRM Module";
	}
	$url_parts = parse_url($apidata['url']);

	if( $apidata['method'] == "POST" ){
		$options[CURLOPT_POST] = 1;
		$options[CURLOPT_POSTFIELDS] = $postbody;
		if( !$is_content_type ){
			if( $apidata['content-type'] ){
				if( $api['content-type']  == "multipart/form-data" ){
					return false;
				}else{
					$request_headers[] = "Content-Type: " . $apidata['content-type'];
				}
			}
		}
	}else if( $apidata['method'] == "GET" ){
		$options[CURLOPT_HTTPGET] =1;
	}else if( $apidata['method'] == "PUT" ){
		return ["status"=>"fail", "error"=>"Method not implemented"];
		$options[CURLOPT_PUT] =1;
	}
	if( sizeof($request_headers) ){
		$options[CURLOPT_HTTPHEADER] = $request_headers;
	}

	curl_setopt_array( $ch, $options );
	$result = curl_exec( $ch );
	$info = curl_getinfo( $ch );
	if( $info["content_type"] ){
		$content_type=explode(";",$info["content_type"])[0];
	}else{
		$content_type="text/plain";
	}
	
	$status = "ok";
	$errtxt = curl_error( $ch );
	$errno = curl_errno( $ch );
    $error = "";

	if( $errno ){
		$status = "CurlError";
		$error = $errno .":" .$errtxt;
	}else if($info["http_code"] == 0 && round($info["total_time"]) >= $timeout){
		$status = "timeout";

		mail($config_internal_mail_alerts, "Urgent:Time Out Exception", json_encode($postbody));
			//return false;

	}else if($info["http_code"] == 0){
		$status = "timeout";
		mail($config_internal_mail_alerts, "Urgent:Time Out Exception-2", json_encode($postbody));

	} 

	$return_result = [
		"status"=>$status,
		"curl_info"=>$info,
		"response"=>$result,
		"http_code"=>$info['http_code'],
		"error"=>$error,
		"content_type"=>$info['content_type'],
		"total_time"=>$info['total_time']
	];
	
	return $return_result;
}

?>