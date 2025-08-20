<?php  

class ChatGPTClient {
    private $baseUrl;
    private $collection = "ai_logs";
    private $model;
    private $apiKeys;

    function __construct($client_details = [], $timeout = 60, $maxRetries = 3) {
        $this->apiKeys = $client_details['keys'];
    	$this->model = $client_details['model'];
        $this->baseUrl = rtrim($client_details['endpoint'], '/');
    }

    function logToMongo($doc = []) {
    	global $mongodb_con;
        $result = $mongodb_con->insert_v2($this->collection,$doc);
        if($result['status'] == "success") {
        	return $result['inserted_id'];
        }else {
        	return $result['error'];
        }
    }

    function currentKey(){
    	/* Need to add key random based on the lenght */
        return $this->apiKeys[0];
    }

    function generate($params = []){
        $payload = [
            'model' => $this->model ?? 'gpt-4o-mini',
            'instructions' => $params['system_instruction'] ?? [],
            'input' => $params['prompt'] ?? [],
        ];

        $optional = ['temperature','top_p','n','max_tokens','stop','presence_penalty','frequency_penalty','logit_bias','user'];
        foreach ($optional as $k) {
            if (array_key_exists($k, $params)) {
                $payload[$k] = $params[$k];
            }
        }

        $headers = [
            'Content-Type'=> 'application/json',
            'Authorization'=> 'Bearer ' . $this->currentKey(),
        ];

        $start = microtime(true);
        try {
            $apireqdata = [];
            $apireqdata["method"] = "POST";
            $apireqdata["url"] = $this->baseUrl;
            $apireqdata["action"] = 'chatgpt';
            $apireqdata["timeout"] = "30";
            $apireqdata["content-type"] = "application/json";
            $apireqdata["headers"] = $headers;
            $data = "";
            $data = json_encode($payload,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $curl_get_res = execute_curl_request($apireqdata,$data);
           $curl_response = json_decode($curl_get_res["response"],true);

            $status = $curl_get_res["http_code"];
            $content = $curl_response['output'][0]['content']['text'] ?? null;
            $usage = $curl_response['usage'] ?? [];
            $tokensIn  = $usage['input_tokens'] ?? null;
            $tokensOut = $usage['output_tokens'] ?? null;
            $totalTok  = $usage['total_tokens'] ?? (($tokensIn ?? 0) + ($tokensOut ?? 0));

            $doc = [
                'provider' => 'openai',
                'endpoint' => $this->baseUrl,
                'model'    => $payload['model'],
                'status'   => $status,
                'latency_s'=> $curl_get_res["total_time"],
                'request'  => [
                    'headers' => ['Authorization' => 'Bearer ***'],
                    'payload' => $payload,
                ],
                'response' => json_decode($curl_get_res["response"]),
                'raw'      => null, // set to $raw if you want raw stored
                'tokens'   => [
                    'input' => $tokensIn,
                    'output'=> $tokensOut,
                    'total' => $totalTok,
                ],
                'created_at' => date("Y-m-d H:i:s"),
            ];
            $logId = $this->logToMongo($doc);

            return [
                'api_status' => true,
                'provider' => 'openai',
                'model' => $payload['model'],
                'content' => $curl_response,
                'status' => $status,
            ];
        } catch (\Throwable $e) {
            $latency = microtime(true) - $start;
            $doc = [
                'provider' => 'openai',
                'endpoint' => $this->baseUrl,
                'model'    => $payload['model'] ?? null,
                'status'   => 0,
                'latency_s'=> $latency,
                'request'  => [
                    'headers' => ['Authorization' => 'Bearer ***'],
                    'payload' => $payload,
                ],
                'error'    => [
                    'message' => $e->getMessage(),
                    'trace'   => $e->getTraceAsString(),
                ],
                'created_at' => date("Y-m-d H:i:s"),
            ];
            $this->logToMongo($doc);

            return [
                'ok' => false,
                'provider' => 'openai',
                'error' => $e->getMessage(),
            ];
        }
    }
}
?>