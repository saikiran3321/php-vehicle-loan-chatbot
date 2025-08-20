<?php
if( $_SERVER['HTTP_USER_AGENT'] == "" ){
	header("http/1.1 400 Bad Request" );
	header("stage: 66");
	exit;
}

if( !$config_mongo_host || !$config_secret_key ){
	header("http/1.1 400 Bad Request" );
	header("stage: 55");
	exit;
}

if( !$config_session_timeout ){
	header("http/1.1 400 Bad Request" );
	exit;
}
// session_start();
if( $_COOKIE['PHPSESSID'] ){
	if( isset( $_SESSION['ua']) ){
		if( $_SESSION['ua'] != $_SERVER['HTTP_USER_AGENT'] || $_SESSION['ip'] != $_SERVER['REMOTE_ADDR'] ){
			session_destroy();
			session_regenerate_id();
			if( $_GET['ajax_type'] == "json" || $_POST['ajax_type'] == "json" ){
				header("content-type: application/json", true);
    			echo json_encode(array("status"=>"fail2", "details"=>"Admin Session Expired"));
			}else{

				header("Location: /admin/?event=Session_unRecognised");
			exit;
			}

		}
	}
	if( !isset($_POST['action']) && !isset($_GET['action']) ){
		setcookie( "PHPSESSID", session_id(), time()+$config_session_timeout, "/",true,true );
	}
}else{
	unset($_GET['PHPSESSID']);
	$_SESSION['ua'] = $_SERVER['HTTP_USER_AGENT'];
	$_SESSION['ip'] = $_SERVER['REMOTE_ADDR'];
}
?>