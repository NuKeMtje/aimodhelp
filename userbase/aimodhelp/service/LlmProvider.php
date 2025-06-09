<?php
namespace userbase\aimodhelp\service;

use userbase\aimodhelp\service\LogService;

class LlmProvider
{
    /** @var string */
    protected $aiProvider;

    /** @var string */
    protected $aiApiKey;

    /** @var string */
    protected $aiModel;

    /** @var string */
    protected $aiBaseUrl;

    /** @var LogService */
    protected $logService;

    /**
     * LlmProvider constructor.
     *
     * @param string $aiProvider The AI provider to use (e.g., 'gemini', 'openrouter').
     * @param string $aiApiKey The API key for the AI provider.
     * @param string $aiModel The specific AI model to use.
     * @param string $aiBaseUrl The base URL for the AI API.
     * @param LogService $logService The logging service.
     */
    public function __construct(
        string $aiProvider,
        string $aiApiKey,
        string $aiModel,
        string $aiBaseUrl,
        LogService $logService
    ) {
        $this->aiProvider = $aiProvider;
        $this->aiApiKey = $aiApiKey;
        $this->aiModel = $aiModel;
        $this->aiBaseUrl = $aiBaseUrl;
        $this->logService = $logService;
    }

    /**
     * Sends a prompt to the AI API and returns the generated content.
     *
     * @param string $textPrompt The prompt text to send.
     * @param string $provider Optional. The AI provider to use (e.g., 'gemini', 'openrouter'). Defaults to configured provider.
     * @param string $model Optional. The specific model to use. Defaults to configured model.
     * @return string|null The generated text response, or null on failure.
     */
    public function getLlmResponse(string $textPrompt, string $provider = '', string $model = ''): ?string
    {
        $provider = $provider ?: $this->aiProvider;
        $model = $model ?: $this->aiModel;

        $url = '';
        $headers = ['Content-Type: application/json'];
        $postData = [];

        if ($provider === 'openrouter') {
            $url = $this->aiBaseUrl . "/chat/completions";
            $headers[] = "Authorization: Bearer " . $this->aiApiKey;
            $postData = [
                "model" => $model,
                "messages" => [
                    [
                        "role" => "user",
                        "content" => $textPrompt
                    ]
                ],
                "reasoning" => [
                    "effort" => "low",
                    "exclude" => true
                ]
            ];
            $this->logService->log("Sending request to OpenRouter API. Model: $model");
        } else { // Default to Gemini
            $url = sprintf("%s/models/%s:generateContent?key=%s", $this->aiBaseUrl, $model, $this->aiApiKey);
            $postData = [
                "contents" => [
                    [
                        "parts" => [
                            [
                                "text" => $textPrompt
                            ]
                        ]
                    ]
                ]
            ];
            $this->logService->log("Sending request to Gemini API. Model: $model");
        }

        $jsonData = json_encode($postData);
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120); // Set timeout for curl request

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $error_message = curl_error($ch);
            $this->logService->log("Curl error: " . $error_message);
            curl_close($ch);
            return "CURL_ERROR: " . $error_message; // Return error message
        }

        curl_close($ch);

        $responseObj = json_decode($response);

        if ($provider === 'openrouter') {
            if (isset($responseObj->choices[0]->message->content)) {
                $this->logService->log("Received successful response from OpenRouter API.");
                return $responseObj->choices[0]->message->content;
            } else {
                $error_message = "OpenRouter API response error or unexpected format: " . $response;
                $this->logService->log($error_message);
                return "API_ERROR: " . $error_message; // Return error message
            }
        } else { // Gemini
            if (isset($responseObj->candidates[0]->content->parts[0]->text)) {
                $this->logService->log("Received successful response from Gemini API.");
                return $responseObj->candidates[0]->content->parts[0]->text;
            } else {
                $error_message = "AI API response error or unexpected format: " . $response;
                $this->logService->log($error_message);
                return "API_ERROR: " . $error_message; // Return error message
            }
        }
    }
}
