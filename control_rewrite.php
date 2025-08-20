<?php
$request_uri = $_REQUEST['request_url'] ;
$parts = parse_url( (string)$request_uri ) ;
$qstr = preg_split( "/\&/",(string)$parts['query'] ) ;
if( preg_match( "/(.*).php/", (string)$parts['path'] ) )
{
	if( file_exists( $parts['path'] ) ){include( $parts['path'] );exit;}
}
//exit;
$paths = explode( "/" , (string)$parts['path'] );

$config_page=$paths[0];
$config_page = strtolower( (string)$config_page );
$config_param1=trim((string)$paths[1]);
$config_param2=trim((string)$paths[2]);
$config_param3=trim((string)$paths[3]);
$config_param4=trim((string)$paths[4]);
$config_param5=trim((string)$paths[5]);
$config_param6=trim((string)$paths[6]);
$config_param7=trim((string)$paths[7]);
$config_param8=trim((string)$paths[8]);
$config_param9=trim((string)$paths[9]);

$config_page = $config_page?$config_page:'home';   
?>