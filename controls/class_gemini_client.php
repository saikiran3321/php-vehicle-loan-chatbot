<?php 

class GeminiClient{
    private $baseUrl;
    private $collection = "ai_logs";
    private $model;
    private $apiKeys;

    function __construct($client_details = [],$timeout = 60,$maxRetries = 3) {
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

    function generate($params = []) {
        $model = $this->model ?? 'gemini-1.5-pro';

        $contents = [];
        if (!empty($params['messages']) && is_array($params['messages'])) {
            foreach ($params['messages'] as $m) {
                $role = $m['role'] ?? 'user';
                $text = is_array($m['content']) ? json_encode($m['content']) : ($m['content'] ?? '');
                $contents[] = [
                    'role' => $role === 'assistant' ? 'model' : 'user',
                    'parts' => [['text' => (string)$text]],
                ];
            }
        } else {
            $prompt = $params['prompt'] ?? '';
            $contents[] = [
                'role' => 'user',
                'parts' => [['text' => (string)$prompt]],
            ];
        }

        $genConfig = [];
        foreach (['temperature','topP','topK','maxOutputTokens','stopSequences'] as $k) {
            if (array_key_exists($k, $params)) {
                $genConfig[$k] = $params[$k];
            }
        }

        $payload = ['contents' => $contents];
        if (!empty($genConfig)) {
            $payload['generationConfig'] = $genConfig;
        }
        if (isset($params['system_instruction'])) {
            $payload['systemInstruction'] = ['parts' => [['text' => (string)$params['system_instruction']]]];
        }

        $url = sprintf('%s/%s:generateContent?key=%s', $this->baseUrl, $model, urlencode($this->currentKey()));

        $headers = [
        	'Content-Type: application/json'
        ];

        $start = microtime(true);
        try {
            $apireqdata = [];
            $apireqdata["method"] = "POST";
            $apireqdata["url"] = $url;
            $apireqdata["action"] = 'geminiapi';
            $apireqdata["timeout"] = "30";
            $apireqdata["content-type"] = "application/json";
            $apireqdata["headers"] = ['Content-Type'=> 'application/json'];
            $data = "";
            $data = json_encode($payload,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $curl_get_res = execute_curl_request($apireqdata,$data);

           $curl_response = json_decode($curl_get_res["response"],true);

            $doc = [
                'provider' => 'gemini',
                'endpoint' => $url,
                'model'    => $model,
                'status'   => $curl_get_res["http_code"],
                'latency_s'=> $curl_get_res["total_time"],
                'request'  => [
                    'payload' => $payload,
                ],
                'response' => json_decode($curl_get_res["response"]),
                'responseid'=>$curl_response["responseId"],
                'tokens'   => [
                    'input' =>$curl_response["usageMetadata"]["promptTokenCount"],
                    'output'=>$curl_response["usageMetadata"]["candidatesTokenCount"],
                    'total' =>$curl_response["usageMetadata"]["totalTokenCount"],
                ],
                'created_at' => date("Y-m-d H:i:s"),
            ];
            $logId = $this->logToMongo($doc);
            return [
                'api_status' => true,
                'provider' => 'gemini',
                'model' => $model,
                'content' => json_decode($curl_get_res["response"],true),
                'status' => $curl_get_res["http_code"]
            ];
        } catch (\Throwable $e) {
            $latency = microtime(true) - $start;
            $doc = [
                'provider' => 'gemini',
                'endpoint' => $url,
                'model'    => $model,
                'status'   => 0,
                'latency_s'=> $latency,
                'request'  => [ 'payload' => $payload ],
                'error'    => [ 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString() ],
                'created_at' => date("Y-m-d H:i:s"),
            ];
            $this->logToMongo($doc);

            return [
                'api_status' => false,
                'provider' => 'gemini',
                'error' => $e->getMessage(),
            ];
        }
    }
}
?>