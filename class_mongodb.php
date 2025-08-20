<?php

if( file_exists("vendor84/vendor/autoload.php") ){
	require_once("vendor84/vendor/autoload.php");
}else if( file_exists("../vendor84/vendor/autoload.php") ){
	require_once("../vendor84/vendor/autoload.php");
}else if( file_exists("../../vendor84/vendor/autoload.php") ){
	require_once("../../vendor84/vendor/autoload.php");
}else if( file_exists("../../../vendor84/vendor/autoload.php") ){
	require_once("../../../vendor84/vendor/autoload.php");
}else{
	echo "Incorrect include path!";exit;
}

class mongodb_connection{
	public $database = false;
	public $connection;
	public $affected_count;

	function __construct( $hostname,$port, $database, $authdb='', $user = "", $pass = "",$ssl=false,$socket_timeout = 10000  ){
		global $config_global_mongo_auth_mechanism;
			//echo $hostname;exit;
		$options = [
			'retryWrites'=>false,
			'retryReads'=>false,
			'socketTimeoutMS' => 10000,
			'connectTimeoutMS'=> 3000,
			'maxIdleTimeMS'=> 600
		];

		if(is_numeric($socket_timeout)){
		  $options ['socketTimeoutMS'] = $socket_timeout;
		}else{
			$options ['socketTimeoutMS'] = 10000;
		}
		if( isset($config_global_mongo_auth_mechanism) && $config_global_mongo_auth_mechanism!='' ){
	      $options['authMechanism'] = $config_global_mongo_auth_mechanism;
	    }

		if($ssl==true){
			$options['ssl'] = 'true';
		}

		if( $user ){
			$options['authSource'] = $authdb;
			$options['user'] = $user;
			$options['pass'] = $pass;
			$auth = $user . ':' . $pass . '@';
		}
		$uri = "mongodb://localhost:27017/";
		$this->connection = new MongoDB\Client($uri);
		// $this->connection = new MongoDB\Client( "mongodb://". $hostname.":".$port,$options, [
		// 	'typeMap'=>[
		// 		'array'=>'array',
		// 		'root'=>'array',
		// 		'document'=>'array'
		// 	]
		// ] );
		$this->database = $this->connection->{ $database };
	}

	function log_query($data){
		global $config_store_query_logs; // refer control_config for this variable
		if(isset($config_store_query_logs) && $config_store_query_logs == true){
			$endtime = microtime(true);
			$data['time_taken'] = number_format($endtime - $data['starttime'],3);
			unset($data['starttime']);
			$data["m_i"]=date("Y-m-d H:i:s");

			try{
				$collection = 'z_log_all_queries';
				$col = $this->database->{$collection};
				$cur = $col->insertOne($data);
				$id =  (string)$cur->getInsertedId();
				return ["status"=>"success","inserted_id"=>$id];
			}catch(Exception $ex){
				return ["status"=>"fail","error"=>$ex->getMessage()];
			}
		}
	}

	function error($e){
		header("http/1.1 500 error");
		echo $e;
		exit;
	}

	function get_id( $vid ){
		try{
			return new MongoDB\BSON\ObjectID( $vid );
		}catch(Exception $ex){

			echo $ex->getMessage();exit;
			return false;
		}
	}
	function insert_v2( $collection="", $insert_data=[], $ignore_duplicate = false ){
			if( ! is_string($collection)  || $collection ==""){
				return ["status"=>"fail","error"=>"Collection name required"];
			}
			if( !is_array($insert_data) ){
				return ["status"=>"fail","error"=>"Insert Data is not array"];
			}
			$col = $this->database->{$collection};
			try{
				if( isset($insert_data["_id"]) && is_string( $insert_data["_id"] ) && preg_match("/^[a-f0-9]{24}$/i",$insert_data["_id"]) ){
					$insert_data["_id"] = $this->get_id( $insert_data["_id"] );
				}
				$insert_data["m_i"]=date("Y-m-d H:i:s");
				$cur = $col->insertOne($insert_data);
				$id =  (string)$cur->getInsertedId();
				return ["status"=>"success","inserted_id"=>$id];
			}catch(Exception $ex){
				return ["status"=>"fail","error"=>$ex->getMessage()];
			}
			return false;
	}
	function insert( $collection, $insert_data, $ignore_duplicate = false ){
		$col = $this->database->{$collection};
		try{
			if( isset($insert_data["_id"]) && is_string( $insert_data["_id"] ) && preg_match("/^[a-f0-9]{24}$/i",$insert_data["_id"]) ){
				$insert_data["_id"] = $this->get_id( $insert_data["_id"] );
			}
			$insert_data["m_i"]=date("Y-m-d H:i:s");

			$log_data = [];
			$log_data['starttime'] = microtime(true);
			$log_data['collection'] = $collection;
			$log_data['cmd'] = 'insertOne';
			$log_data['query'] = [ 'insert_data' => $insert_data];

			$cur = $col->insertOne($insert_data);
			$id =  (string)$cur->getInsertedId();

			$log_data['status'] = 'success';
			$this->log_query($log_data);

			return $id;
		}catch(Exception $ex){
			$log_data['status'] = 'fail';
			$log_data['error'] = $ex->getMessage();
			$this->log_query($log_data);
			echo "class_db.Insert catch: " . $ex->getMessage();exit;
			return false;
		}
		return false;
	}

	function find($collection, $condition = array(), $option = array() ){

		if( !is_string($collection) || !is_array($condition) ){
			echo "<p>function find. parameter mismatch</p>";
			if( ! is_string($collection) ){
				echo "<p>collection not string</p>";
			}
			if( ! is_array($condition) ){
				echo "<p>condition is not array</p>";
			}
			exit;
		}
		$col = $this->database->{$collection};
		try{		
			if( isset($condition["_id"]) && is_string( $condition["_id"] ) && preg_match("/^[a-f0-9]{24}$/i",$condition["_id"]) ){
				$condition["_id"] = $this->get_id( $condition["_id"] );
			}
				/*foreach ($condition as $_field_name => $_d) {
					if( sizeof((array)  $_d ) == 2 && strpos($_field_name,'$')!=0){
						$condition[$_field_name] = $this->build_condition_sub( $_field_name, $_d )[$_field_name];
					}
				}*/
				if( !isset($option['maxTimeMS']) ) {
						$option['maxTimeMS'] = 3000;
				}

				$log_data = [];
				$log_data['starttime'] = microtime(true);
				$log_data['collection'] = $collection;
				$log_data['cmd'] = 'find';
				$log_data['query'] = [ 'condition' => $condition, 'option' => $option];

				$cur = [];
				$cur = $col->find($condition, $option)->toArray();

				$log_data['status'] = 'success';
				$this->log_query($log_data);

				foreach( $cur as $i=>$j ){
					$cur[$i]['_id'] = (string)$cur[$i]['_id'];
				}
			}catch(Exception $ex){
				$log_data['status'] = 'fail';
				$log_data['error'] = $ex->getMessage();
				$this->log_query($log_data);

				echo "Find error:" .$ex->getMessage();exit;
				return false;
			}
			return $cur;
		}

		function find_v2($collection, $condition = array(), $option = array() ){

			if (!is_string($collection)) {
				return ["status" => "fail", "error" => "collection name required"];
			}
			if (!is_array($condition)) {
				return ["status" => "fail", "error" => "condition is not array"];
			}
			$col = $this->database->{$collection};
			try{		
				if( isset($condition["_id"]) && is_string( $condition["_id"] ) && preg_match("/^[a-f0-9]{24}$/i",$condition["_id"]) ){
					$condition["_id"] = $this->get_id( $condition["_id"] );
				}
				if( !isset($option['maxTimeMS']) ) {
					$option['maxTimeMS'] = 3000;
				}

				$log_data = [];
				$log_data['starttime'] = microtime(true);
				$log_data['collection'] = $collection;
				$log_data['cmd'] = 'find';
				$log_data['query'] = [ 'condition' => $condition, 'option' => $option];

				$cur = [];
				$cur = $col->find($condition, $option)->toArray();

				$log_data['status'] = 'success';
				$this->log_query($log_data);

				foreach( $cur as $i=>$j ){
					$cur[$i]['_id'] = (string)$cur[$i]['_id'];
				}
				return ["status" => "success", "data" => $cur];
			}catch(Exception $ex){
				$log_data['status'] = 'fail';
				$log_data['error'] = $ex->getMessage();
				$this->log_query($log_data);
				return ["status" => "fail", "error" => $ex->getMessage()];
			}
			
		}


		function find_assoc($collection, $condition = array(), $option = array() ){
			if( !is_string($collection) || !is_array($condition) ){
				echo "<p>function find. parameter mismatch</p>";
				if( ! is_string($collection) ){
					echo "<p>collection not string</p>";
				}
				if( ! is_array($condition) ){
					echo "<p>condition is not array</p>";
				}
				exit;
			}
			$col = $this->database->{$collection};
			try{
				if( isset($condition["_id"]) && is_string( $condition["_id"] ) && preg_match("/^[a-f0-9]{24}$/i",$condition["_id"]) ){
					$condition["_id"] = $this->get_id( $condition["_id"] );
				}
				/*foreach ($condition as $_field_name => $_d) {
					if( sizeof((array)  $_d ) == 2 && strpos($_field_name,'$')!=0){
						$condition[$_field_name] = $this->build_condition_sub( $_field_name, $_d )[$_field_name];
					}
				}*/
				if( !isset($option['maxTimeMS']) ) {
					$option['maxTimeMS'] = 3000;
				}

				$log_data = [];
				$log_data['starttime'] = microtime(true);
				$log_data['collection'] = $collection;
				$log_data['cmd'] = 'find';
				$log_data['query'] = [ 'condition' => $condition, 'option' => $option];

				$recs = [];

				$cur = [];
				$cur = $col->find($condition, $option);

				$log_data['status'] = 'success';
				$this->log_query($log_data);

				foreach( $cur as $i=>$j ){
					$j['_id'] = (string)$j['_id'];
					$recs[ $j['_id'] ] = $j;
				}
			}catch(Exception $ex){
				$log_data['status'] = 'fail';
				$log_data['error'] = $ex->getMessage();
				$this->log_query($log_data);
				echo "Find error:" .$ex->getMessage();exit;
				return false;
			}
			return $recs;
		}
		function find_one($collection, $condition = array(), $option = array() ){
			if( !is_string($collection) || !is_array($condition) ){
				echo "<p>function find_one. parameter mismatch</p>";
				if( !is_string($collection) ){
					echo "<p>collection not string</p>";
				}
				if( !is_array($condition) ){
					echo "<p>condition is not array</p>";
				}
				exit;
			}
			$col = $this->database->{$collection};
			try{
				if( isset($condition["_id"]) && is_string( $condition["_id"] ) && preg_match("/^[a-f0-9]{24}$/i",$condition["_id"]) ){
					$condition["_id"] = $this->get_id( $condition["_id"] );
				}
				if( !isset($option['maxTimeMS']) ) {
						$option['maxTimeMS'] = 3000;
				}

				$log_data = [];
				$log_data['starttime'] = microtime(true);
				$log_data['collection'] = $collection;
				$log_data['cmd'] = 'findOne';
				$log_data['query'] = [ 'condition' => $condition, 'option' => $option];

				$cur = (array)$col->findOne($condition,$option);

				$log_data['status'] = 'success';
				$this->log_query($log_data);

			}catch(Exception $ex){
				$log_data['status'] = 'fail';
				$log_data['error'] = $ex->getMessage();
				$this->log_query($log_data);
				echo "find error: " . $ex->getMessage(); exit;
				return false;
			}
			if(isset($cur['_id'])){
				$cur['_id'] = (string)$cur['_id'];
			}
			return $cur;
		}
		function find_one_v2($collection, $condition = array(), $option = array() ){
			if( ! is_string($collection) || $collection==""){
				return ["status"=>"fail","error"=>"collection name required"];
			}
			if( !is_array($condition) ){
				return ["status"=>"fail","error"=>"condition is not array"];
			}
			$col = $this->database->{$collection};
			try{
				if( isset($condition["_id"]) && is_string( $condition["_id"] ) && preg_match("/^[a-f0-9]{24}$/i",$condition["_id"]) ){
					$condition["_id"] = $this->get_id( $condition["_id"] );
				}
				if( !isset($option['maxTimeMS']) ) {
						$option['maxTimeMS'] = 3000;
				}

				$log_data = [];
				$log_data['starttime'] = microtime(true);
				$log_data['collection'] = $collection;
				$log_data['cmd'] = 'findOne';
				$log_data['query'] = [ 'condition' => $condition, 'option' => $option];

				$cur = (array)$col->findOne($condition,$option);

				$log_data['status'] = 'success';
				$this->log_query($log_data);
			}catch(Exception $ex){
				$log_data['status'] = 'fail';
				$log_data['error'] = $ex->getMessage();
				$this->log_query($log_data);
				return ["status"=>"fail","error"=>$ex->getMessage()];
			}
			if($cur['_id']){
				$cur['_id'] = (string)$cur['_id'];
			}
			return ["status"=>"success","data"=>$cur];
		}
		function count($collection, $filter = array(), $option = array() ){
			$col = $this->database->{$collection};
			try{
				/*foreach ((array)$filter as $_field_name => $_d) {
						if( sizeof((array)  $_d ) == 2 && strpos($_field_name,'$')!=0){
						$filter[$_field_name] = $this->build_condition_sub( $_field_name, $_d )[$_field_name];
					}
				}*/

				$log_data = [];
				$log_data['starttime'] = microtime(true);
				$log_data['collection'] = $collection;
				$log_data['cmd'] = 'count';
				$log_data['query'] = [ 'condition' => $filter, 'option' => $option];

				$cnt = $col->count( $filter, $option );

				$log_data['status'] = 'success';
				$this->log_query($log_data);
				return $cnt;
			}catch(Exception $ex){
				$log_data['status'] = 'fail';
				$log_data['error'] = $ex->getMessage();
				$this->log_query($log_data);
				echo "Count Error: " .  $ex->getMessage(); exit;
				return false;
			}
			return false;
		}


		function update_many($collection,$updated_data,$condition){
			$col = $this->database->{$collection};
			try{
				$updated_data["m_u"]=date("Y-m-d H:i:s");
				if( isset($condition["_id"]) && is_string( $condition["_id"] ) && preg_match("/^[a-f0-9]{24}$/i",$condition["_id"]) ){
					$condition["_id"] = $this->get_id( $condition["_id"] );
				}
				$u = array("\$set"=>$updated_data);

				$log_data = [];
				$log_data['starttime'] = microtime(true);
				$log_data['collection'] = $collection;
				$log_data['cmd'] = 'updateMany';
				$log_data['query'] = [ 'condition' => $condition, 'updated_data' => $updated_data];

				$res=$col->updateMany($condition, $u);

				$log_data['status'] = 'success';
				$this->log_query($log_data);

				return array("matched_count"=>$res->getMatchedCount(),"modified_count"=>$res->getModifiedCount());
			}catch(Exception $ex){
				$log_data['status'] = 'fail';
				$log_data['error'] = $ex->getMessage();
				$this->log_query($log_data);
				echo "Update many Error: ". $ex->getMessage(); exit;
				return false;
			}
			return true;
		}

		function update_one($collection,$updated_data,$condition,$options=[]){
			$col = $this->database->{$collection};
			try{
				if(!$updated_data['$set']){
					$updated_data["m_u"]=date("Y-m-d H:i:s");
				}
				if( isset($condition["_id"]) && is_string( $condition["_id"] ) && preg_match("/^[a-f0-9]{24}$/i",$condition["_id"]) ){
					$condition["_id"] = $this->get_id( $condition["_id"] );
				}
				if($updated_data['$set'] || $updated_data['$unset'] || $updated_data['$inc']){
					$u = $updated_data;
				}else{
					$u = array("\$set"=>$updated_data);
				}

				$log_data = [];
				$log_data['starttime'] = microtime(true);
				$log_data['collection'] = $collection;
				$log_data['cmd'] = 'updateOne';
				$log_data['query'] = [ 'condition' => $condition, 'updated_data' => $updated_data, 'option' => $options];

				$res=$col->updateOne($condition, $u,$options);

				$log_data['status'] = 'success';
				$this->log_query($log_data);

				$u = $res->getModifiedCount();
				//echo "<pre>";print_r($col);print_r($condition);print_r($u);print_r($updated_data);//exit;
				$this->affected_count = $u;
				return array(
					"matched_count"=>$res->getMatchedCount(),
					"modified_count"=>$res->getModifiedCount(),
					'inserted_count'=>$res->getUpsertedCount()
				);
			}catch(Exception $ex ){
				$log_data['status'] = 'fail';
				$log_data['error'] = $ex->getMessage();
				$this->log_query($log_data);
				echo "Update Error: " . $ex->getMessage(); exit;
				return false;
			}
			return true;
		}
		function update_one_v2($collection,$updated_data,$condition,$options=[]){
			if( !is_string($collection) ){
				return ["status"=>"fail","error"=>"collection name required"];
			}
			if( !is_array($condition) ){
				return ["status"=>"fail","error"=>"condition is not array"];
			}
			if( !is_array($updated_data) ){
				return ["status"=>"fail","error"=>"data is not array"];
			}			
			$col = $this->database->{$collection};
			try{
				if(!$updated_data['$set'] && !$updated_data['$unset']){
					$updated_data["m_u"]=date("Y-m-d H:i:s");
				}
				if( isset($condition["_id"]) && is_string( $condition["_id"] ) && preg_match("/^[a-f0-9]{24}$/i",$condition["_id"])  ){ 
					$condition["_id"] = $this->get_id( $condition["_id"] );
				}
				if($updated_data['$set'] || $updated_data['$unset'] || $updated_data['$inc']){
					$u = $updated_data;
				}else{
					$u = array("\$set"=>$updated_data);
				}

				$log_data = [];
				$log_data['starttime'] = microtime(true);
				$log_data['collection'] = $collection;
				$log_data['cmd'] = 'updateOne';
				$log_data['query'] = [ 'condition' => $condition, 'updated_data' => $updated_data, 'option' => $options];

				$res=$col->updateOne($condition, $u,$options);

				$log_data['status'] = 'success';
				$this->log_query($log_data);

				$u = $res->getModifiedCount();
				$this->affected_count = $u;
				return [
					"status"=>"success", 
					"data"=>[
						"matched_count"=>$res->getMatchedCount(),
						"modified_count"=>$res->getModifiedCount(),
						"upserted_count"=>$res->getUpsertedCount()
					]
				];
			}catch(Exception $ex ){
				$log_data['status'] = 'fail';
				$log_data['error'] = $ex->getMessage();
				$this->log_query($log_data);
				return ["status"=>"fail","error"=>$ex->getMessage()];
			}
			return true;
		}

		function getnextseq($collection, $condition = array(),$updated_data = array()){
			if( !is_string($collection) || !is_array($condition) ){
				echo "<p>function find_one. parameter mismatch</p>";
				if( !is_string($collection) ){
					echo "<p>collection not string</p>";
				}
				if( !is_array($condition) ){
					echo "<p>condition is not array</p>";
				}
				if( !is_array($updated_data) ){
					echo "<p>updated data is not array</p>";
				}
				exit;
			}
			$col = $this->database->{$collection};
			try{

				$option =[
					'upsert'=> true,
					'new' => true,
					'returnDocument' => MongoDB\Operation\FindOneAndUpdate::RETURN_DOCUMENT_AFTER,
				];

				$log_data = [];
				$log_data['starttime'] = microtime(true);
				$log_data['collection'] = $collection;
				$log_data['cmd'] = 'findOneAndUpdate';
				$log_data['query'] = [ 'condition' => $condition, 'updated_data' => $updated_data, 'option' => $option];

				$cur =$col->findOneAndUpdate($condition,$updated_data,$option);

				$log_data['status'] = 'success';
				$this->log_query($log_data);
			}catch(Exception $ex){
				$log_data['status'] = 'fail';
				$log_data['error'] = $ex->getMessage();
				$this->log_query($log_data);
				$this->error( "find error: " . $ex->getMessage() );
				return false;
			}
			return $cur;
		}

		function aggregate( $collection, $options ){
			$col = $this->database->{$collection};
			try{
				$log_data = [];
				$log_data['starttime'] = microtime(true);
				$log_data['collection'] = $collection;
				$log_data['cmd'] = 'aggregate';
				$log_data['query'] = ['option' => $options];

				$res=$col->aggregate($options)->toArray();

				$log_data['status'] = 'success';
				$this->log_query($log_data);

				return $res;
			}catch(Exception $ex){
				$log_data['status'] = 'fail';
				$log_data['error'] = $ex->getMessage();
				$this->log_query($log_data);
				echo "Aggregate Error: " . $ex->getMessage(); exit;
				return false;
			}
			return false;
		}

		function drop_collection( $collection ){
			$col = $this->databases[  $this->default_db ]->{$collection};
			try{

				$log_data = [];
				$log_data['starttime'] = microtime(true);
				$log_data['collection'] = $collection;
				$log_data['cmd'] = 'drop';
				$log_data['query'] = [];

				$col->drop();

				$log_data['status'] = 'success';
				$this->log_query($log_data);

				return true;
			}catch(Exception $ex){
				$log_data['status'] = 'fail';
				$log_data['error'] = $ex->getMessage();
				$this->log_query($log_data);
				$this->status = $ex->getMessage();
				return false;
			}
			return false;
		}

		function delete_one( $collection, $condition ){
			$col = $this->database->{$collection};
			try{
				if( isset($condition["_id"]) && is_string( $condition["_id"] ) && preg_match("/^[a-f0-9]{24}$/i",$condition["_id"]) ){
					$condition["_id"] = $this->get_id( $condition["_id"] );
				}

				$log_data = [];
				$log_data['starttime'] = microtime(true);
				$log_data['collection'] = $collection;
				$log_data['cmd'] = 'deleteOne';
				$log_data['query'] = [ 'condition' => $condition];

				$res = $col->deleteOne($condition);

				$log_data['status'] = 'success';
				$this->log_query($log_data);

				return array("deleted_count"=>$res->getDeletedCount());
				return true;
			}catch(Exception $ex){
				$log_data['status'] = 'fail';
				$log_data['error'] = $ex->getMessage();
				$this->log_query($log_data);
				echo "Delete One Error: " . $ex->getMessage();exit;
				return false;
			}
			return true;
		}

		function delete_many($collection, $condition){
			$col = $this->database->{$collection};
			try{
				if( isset($condition["_id"]) && is_string( $condition["_id"] ) && preg_match("/^[a-f0-9]{24}$/i",$condition["_id"]) ){
					$condition["_id"] = $this->get_id( $condition["_id"] );
				}

				$log_data = [];
				$log_data['starttime'] = microtime(true);
				$log_data['collection'] = $collection;
				$log_data['cmd'] = 'deletemany';
				$log_data['query'] = [ 'condition' => $condition ];

				$res = $col->deletemany($condition);

				$log_data['status'] = 'success';
				$this->log_query($log_data);

				return array("deleted_count"=>$res->getDeletedCount());
				return true;
			}catch(Exception $ex){
				$log_data['status'] = 'fail';
				$log_data['error'] = $ex->getMessage();
				$this->log_query($log_data);
				echo "delete many: " .  $ex->getMessage();exit;
				return false;
			}
			return true;
		}
		function delete_many_v2($collection, $condition){
			if( !is_string($collection) ){
				return ["status"=>"fail","error"=>"collection name required"];
			}
			if( !is_array($condition) ){
				return ["status"=>"fail","error"=>"condition is not array"];
			}
			$col = $this->database->{$collection};
			try{
				if( isset($condition["_id"]) && is_string( $condition["_id"] ) && preg_match("/^[a-f0-9]{24}$/i",$condition["_id"]) ){
					$condition["_id"] = $this->get_id( $condition["_id"] );
				}

				$log_data = [];
				$log_data['starttime'] = microtime(true);
				$log_data['collection'] = $collection;
				$log_data['cmd'] = 'deletemany';
				$log_data['query'] = [ 'condition' => $condition ];

				$res = $col->deletemany($condition);

				$log_data['status'] = 'success';
				$this->log_query($log_data);
				return [ "status"=>"success", "deleted_count"=>$res->getDeletedCount() ];
			}catch(Exception $ex){
				$log_data['status'] = 'fail';
				$log_data['error'] = $ex->getMessage();
				$this->log_query($log_data);
				return ["status"=>"fail","error"=>$ex->getMessage()];
			}
			return true;
		}	

		function find_and_delete($collection,$condition){
			$col = $this->database->{$collection};
			try{
				if( isset($condition["_id"]) && is_string( $condition["_id"] ) && preg_match("/^[a-f0-9]{24}$/i",$condition["_id"]) ){
					$condition["_id"] = $this->get_id( $condition["_id"] );
				}
				$log_data = [];
				$log_data['starttime'] = microtime(true);
				$log_data['collection'] = $collection;
				$log_data['cmd'] = 'findOneAndDelete';
				$log_data['query'] = [ 'condition' => $condition];

				$col->findOneAndDelete($condition);

				$log_data['status'] = 'success';
				$this->log_query($log_data);

				return true;
			}catch(Exception $ex){
				$log_data['status'] = 'fail';
				$log_data['error'] = $ex->getMessage();
				$this->log_query($log_data);
				echo "Delete One Error: " . $ex->getMessage();exit;
				return false;
			}
			return true;
		}

		function build_condition_sub( $field_name, $d ){
			//print_r( $d );
			$cond = [];
			if( $this->cond_debug ){
				echo "<div>build condition sub: " . json_encode([$field_name, $d]) . "</div>";
			}

			if( array_keys($d)[0] === 0 ){
				//echo "<BR>YES</BR>";
				if( $d[0] == "=" ){
					$cond[ $field_name ] = $d[1];
				}elseif( $d[0] == "!=" ){
					$cond[ $field_name ] = ["\$ne"=>$d[1]];
				}elseif( $d[0] == "like" ){
					$d[1] = str_replace("\%", ".*", $d[1]);
					$cond[ $field_name ] = "/".$d[1]."/";
				}elseif( $d[0] == ">" ){
					$cond[ $field_name ] = ["\$gt"=>$d[1]];
				}elseif( $d[0] == ">=" ){
					$cond[ $field_name ] = ["\$gte"=>$d[1]];
				}elseif( $d[0] == "<" ){
					$cond[ $field_name ] = ["\$lt"=>$d[1]];
				}elseif( $d[0] == "<=" ){
					$cond[ $field_name ] = ["\$lte"=>$d[1]];
				}else if( $d[0] == "in" ){
					$cond[ $field_name ] = ["\$in"=>$d[1]];
				}else if( $d[0] == "notin" ){
					$cond[ $field_name ] = ["\$nin"=>$d[1]];
				}else{
					$cond[ $field_name ] = [$d[0]=>$d[1]];
				}
			}else{
				$cond[ $field_name ] = $d;
			}
			//print_r( $cond );
			return $cond;
		}
		
		function list_collections(){
			$vv = array();
			$list = $this->database->listCollections();
			foreach($list as $i) {
				$vv[] = $i['name'];
			}
			return $vv;
		}
		function get_mongodb_default_datetime(){
			$utcDateTime = new MongoDB\BSON\UTCDateTime();
			return 	$utcDateTime;
		}

		function is_field_indexed($collection,$fieldName) {
			$col = $this->database->{$collection};
			$indexes =  $col->listIndexes();
			foreach ($indexes as $index) {				
				if (isset($index['key'][$fieldName])) {
							return true; 
					}
			}		
			return false; 
		}
		
		function is_field_indexed_v2($collection,$fieldName) {
			$col = $this->database->{$collection};
			try{
				$log_data = [];
				$log_data['starttime'] = microtime(true);
				$log_data['collection'] = $collection;
				$log_data['cmd'] = 'listIndexes';
				$log_data['query'] = [];

				$indexes =  $col->listIndexes();		

				$log_data['status'] = 'success';
				$this->log_query($log_data);

			}catch(Exception $ex){
				$log_data['status'] = 'fail';
				$log_data['error'] = $ex->getMessage();
				$this->log_query($log_data);
				return ["status"=>"fail","error"=>$ex->getMessage()];
			}
			
			foreach ($indexes as $index) {				
				if (isset($index['key'][$fieldName])) {
						return ["status"=>"success","data"=>true];
				}
			}		
			return ["status"=>"success","data"=>false];
		}
	}
