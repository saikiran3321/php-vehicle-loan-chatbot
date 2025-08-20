<?php  
class AIClient {
	public $openai;
    public $provider;
    function __construct() {
    	global $config_ai_model,$config_gemini_data, $config_chatgpt_data;
        $this->provider = $config_ai_model;
        if ($config_ai_model == "chatgpt") {
            require("class_chatgpt_client.php");
            $this->openai = new ChatGPTClient(
                $config_chatgpt_data,
            );
        }
        else{
            require("class_gemini_client.php");
            $this->openai = new GeminiClient(
                $config_gemini_data,
            );
        }
    }

    function generate($params){
        $provider = $this->provider;
        switch (strtolower($provider)) {
            case 'chatgpt':
                if (!$this->openai) throw new \RuntimeException('OpenAI not configured');
                return $this->openai->generate($params);
            case 'gemini':
                if (!$this->openai) throw new \RuntimeException('Gemini not configured');
                return $this->openai->generate($params);
            default:
                throw new \InvalidArgumentException('Unknown provider: ' . $provider);
        }
    }
}
?>