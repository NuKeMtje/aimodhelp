<?php
namespace userbase\aimodhelp\service;

use userbase\aimodhelp\service\LogService;
use userbase\aimodhelp\service\LlmProvider; // Add LlmProvider

class LlmService
{
    /** @var string */
    protected $forumRulesContent;

    /** @var string */
    protected $tekoopRulesContent;

    /** @var LogService */
    protected $logService;

    /** @var LlmProvider */
    protected $llmProvider;

    /**
     * LlmService constructor.
     *
     * @param string $phpbb_root_path The root path of the phpBB installation.
     * @param string $php_ext The PHP file extension.
     * @param string $aiProvider The AI provider to use (e.g., 'gemini', 'openrouter').
     * @param string $aiApiKey The API key for the AI provider.
     * @param string $aiModel The specific AI model to use.
     * @param string $aiBaseUrl The base URL for the AI API.
     * @param LogService $logService The logging service.
     */
    public function __construct(
        string $phpbb_root_path,
        string $php_ext,
        string $aiProvider,
        string $aiApiKey,
        string $aiModel,
        string $aiBaseUrl,
        LogService $logService
    ) {
        $this->logService = $logService;
        $this->llmProvider = new LlmProvider($aiProvider, $aiApiKey, $aiModel, $aiBaseUrl, $logService);

        // Read forum rules from file
        $forumRulesFile = $phpbb_root_path . 'ext/userbase/aimodhelp/rules/forumregels.txt';
        $this->forumRulesContent = '';
        if (file_exists($forumRulesFile)) {
            $this->forumRulesContent = file_get_contents($forumRulesFile);
            $this->logService->log("Loaded forum rules from: $forumRulesFile");
        } else {
            $this->logService->log("Forum rules file not found: $forumRulesFile");
        }

        // Read te koop/geef rules from file
        $tekoopRulesFile = $phpbb_root_path . 'ext/userbase/aimodhelp/rules/tekoopgeefregels.txt';
        $this->tekoopRulesContent = '';
        if (file_exists($tekoopRulesFile)) {
            $this->tekoopRulesContent = file_get_contents($tekoopRulesFile);
            $this->logService->log("Loaded 'te koop/geef' rules from: $tekoopRulesFile");
        } else {
            $this->logService->log("'Te koop/geef' rules file not found: $tekoopRulesFile");
        }
    }

    /**
     * Evaluates content against general forum rules.
     *
     * @param string $content The content to evaluate (HTML).
     * @param string $contentType Type of the content ('single_post_html', 'topic_html').
     * @param string $outputType Desired output format ('html', 'bbcode').
     * @return array An array with evaluation results.
     */
    public function evaluateAgainstGeneralForumRulesContent(string $content, string $contentType, string $outputType = 'html'): array
    {
        $prompt = "Je bent een niet heel strenge moderator op het userbase.be community forum die enkel ingrijpt bij grove en duidelijke schendigen of ontsporende discussie, die bijvoorbeeld in een welles nietes discussie of schelden tussen personen zijn ontaard. De regels op het forum die een moderator moet bewaken worden hieronder gegeven tussen <rules></rules> tags.\n\n";

        if ($contentType === 'single_post_html') {
            $prompt .= "Onderstaande HTML-data bevat een enkele forumpost.\n\n";
            $this->logService->log("Evaluating single post against general forum rules.");
        } elseif ($contentType === 'topic_html') {
            $prompt .= "Onderstaande HTML-data bevat de laatste reacties op een thread waarop verschillende gebruikers hebben gereageerd.\n\n";
            $this->logService->log("Evaluating topic posts against general forum rules.");
        } else {
            $this->logService->log("evaluateAgainstGeneralForumRulesContent: Unknown contentType '$contentType'");
            // Fallback or error for unknown content type
            $prompt .= "Onderstaande data wordt ter evaluatie aangeboden.\n\n";
        }

        $prompt .= "De te evalueren data staat tussen <context></context>. Hou rekening met quotes in posts zodat je uitspraken aan de juiste gebruiker linkt (indien van toepassing op de data).\n\n";
        $prompt .= "Forum regels:\n<rules>" . $this->forumRulesContent . "</rules>\n\n";
        $prompt .= "Te evalueren context:\n<context>" . $content . "</context>\n\n";
        
        if ($outputType === 'bbcode') {
            $prompt .= "Formuleer een antwoord op de inhoud op basis van de beschikbare forumregels. Antwoord alsof je zelf een bericht plaatst in de discussie. Dat antwoord mag verschillende reacties naar en quotes van meerdere gebruikers bevatten of een algemene reactie zijn.\n";
            $prompt .= "Refereer in je antwoord eventueel naar welke regel(s) geschonden werden. Indien je expliciet verwijst naar bepaalde posts, gelieve dan een link naar die post toe te voegen (indien beschikbaar in de context).\n";
            $prompt .= "Je antwoord moet volledig in het Nederlands en in BB-code formaat zijn.\n";
            $prompt .= "Geef ALLEEN de BB-code met [] voor je reactie (geen code met <>), zonder enige extra uitleg, inleiding of analyse buiten de BB-code zelf. Starten met [bbcode] of afsluiten met [/bbcode] is niet nodig.\n\n";
            $prompt .= "Geef als antwoord/output jouw BB-code reactie. Belangrijk: Je reactie moet direct onder de discussie of post te plaatsen zijn (dus zonder analyse er rond)!\n";
            if ($contentType === 'topic_html') {
                $prompt .= "Je mag je in je reactie richten tot meerdere gebruikers of ook een algemene reactie geven.\n\n";
            }
        } else {
            // Default to html if unknown outputType
            $prompt .= "Vraag: Hoe zou je reageren op basis van de beschikbare forumregels? Zijn er gebruikers en reacties die zich niet aan de regels houden en zou je daarop reageren?\n\n";
            $prompt .= "Zoja, hoe? Refereer naar welke regel(s) geschonden werden. Indien je expliciet verwijst naar bepaalde posts, gelieve dan een link naar die post toe te voegen (indien beschikbaar in de context). De eventuele antwoorden die je naar gebruikers zou versturen moeten in BB-code formaat.\n\n";
            $prompt .= "Je algemene antwoord moet markup in HTML-formaat gebruiken met gebruik van linebreaks, headers en insprongen. Zorg ervoor dat de gegenereerde HTML zo compact mogelijk is, met minimale verticale witruimte tussen elementen.\n";
            $prompt .= "Gebruik GEEN <style> tags of inline stijlen (zoals style=\"margin: 0;\") in de gegenereerde HTML; de styling wordt extern afgehandeld. Vermijd overmatige marges en padding. \n";
            $prompt .= "Focus op een schone, semantische HTML-structuur die compact is zonder extra styling.\n\n";
        }
        $prompt .= "Indien er geen reden is tot moderatie, actie of antwoord, antwoord dan het volgende: Er is geen actie nodig.\n";
        
        $result = $this->llmProvider->getLlmResponse($prompt); 
        if ($result === null || strpos($result, 'CURL_ERROR:') === 0 || strpos($result, 'API_ERROR:') === 0) {
            $this->logService->log("Failed to generate general evaluation from AI API for contentType: $contentType. Error: " . $result);
            return [
                'message' => 'Failed to generate evaluation from AI API.' . ($result !== null ? ' ' . $result : ''),
                'content_length' => strlen($content)
            ];
        }
        return [
            'message' => $result,
            'content_length' => strlen($content)
        ];
    }

    /**
     * Evaluates content against te koop forum rules.
     *
     * @param string $content The content to evaluate (HTML).
     * @param string $contentType Type of the content ('single_post_html', 'topic_html').
     * @param string $outputType Desired output format ('html', 'bbcode').
     * @return array An array with evaluation results.
     */
    public function evaluateAgainstTeKoopForumRules(string $content, string $contentType, string $outputType = 'html'): array
    {
        $prompt = "Je bent een moderator op het userbase.be community forum.\n\n";
        $prompt .= "De regels op het te koop/geef forum die een gebruiker moet volgen en een moderator moet bewaken worden hieronder gegeven tussen <rules></rules> tags.\n\n";

        if ($contentType === 'single_post_html') {
            $prompt .= "Een post van een gebruiker (in HTML formaat) wordt gegeven tussen <context></context> tags.\n\n";
            $this->logService->log("Evaluating single post against 'te koop/geef' forum rules.");
        } elseif ($contentType === 'topic_html') {
            $prompt .= "Meerdere posts van gebruikers (in HTML formaat) van een topic worden gegeven tussen <context></context> tags.\n\n";
            $this->logService->log("Evaluating topic posts against 'te koop/geef' forum rules.");
        } else {
            $this->logService->log("evaluateAgainstTeKoopForumRules: Unknown contentType '$contentType'");
            $prompt .= "De volgende data wordt ter evaluatie aangeboden tegen te koop regels.\n\n";
        }
        
        $prompt .= "Forum regels:\n<rules>" . $this->tekoopRulesContent . "</rules>\n\n";
        $prompt .= "Te evalueren context:\n<context>" . $content . "</context>\n\n";
        
        if ($outputType === 'bbcode') {
            $prompt .= "Formuleer een antwoord op de inhoud op basis van de beschikbare regels, alsof je zelf een bericht plaatst in de 'Te Koop/Geef' discussie. Dat antwoord mag verschillende reacties naar en quotes van meerdere gebruikers bevatten of een algemene reactie zijn.\n";

            $prompt .= "Formuleer een directe reactie op de inhoud op basis van de beschikbare regels, alsof je zelf een bericht plaatst in de 'Te Koop/Geef' discussie.\n";
            $prompt .= "Refereer in je antwoord eventueel naar welke regel(s) geschonden werden. Indien je expliciet verwijst naar bepaalde posts, gelieve dan een link naar die post toe te voegen (indien beschikbaar in de context).\n";
            $prompt .= "Je antwoord moet volledig in het Nederlands en in BB-code formaat zijn.\n";
            $prompt .= "Geef ALLEEN de BB-code met [] voor je reactie (geen code met <>), zonder enige extra uitleg, inleiding of analyse buiten de BB-code zelf. Starten met [bbcode] of afsluiten met [/bbcode] is niet nodig.\n\n";
            $prompt .= "Geef als antwoord/output jouw BB-code reactie. Belangrijk: Je reactie moet direct onder de discussie of post te plaatsen zijn (dus zonder analyse er rond)!:\n";
            if ($contentType === 'topic_html') {
                $prompt .= "Je reactie mag gericht zijn naar meerdere specifieke gebruikers of een algemene reactie.\n\n";
            }

        } else {
            $prompt .= "Vraag: Hoe zou je reageren op basis van de beschikbare forumregels? Schendt deze post/Schenden deze posts de regels en zou je daarop reageren? \n\n";
            $prompt .= "Zoja, hoe? Refereer naar welke regel(s) geschonden werden. Het eventuele antwoord dat je naar de gebruiker zou versturen moeten in BB-code formaat.\n\n";
            $prompt .= "Je algemene antwoord moet markup in HTML-formaat gebruiken met gebruik van linebreaks, headers en insprongen. Zorg ervoor dat de gegenereerde HTML zo compact mogelijk is, met minimale verticale witruimte tussen elementen.\n";
            $prompt .= "Gebruik GEEN <style> tags of inline stijlen (zoals style=\"margin: 0;\") in de gegenereerde HTML; de styling wordt extern afgehandeld. Vermijd overmatige marges en padding. \n";
            $prompt .= "Focus op een schone, semantische HTML-structuur die compact is zonder extra styling.\n\n";
        }

        $prompt .= "Indien er geen reden is tot moderatie, actie of antwoord, antwoord dan het volgende: Er is geen actie nodig.\n";
        
        $result = $this->llmProvider->getLlmResponse($prompt); 
        if ($result === null || strpos($result, 'CURL_ERROR:') === 0 || strpos($result, 'API_ERROR:') === 0) {
            $this->logService->log("Failed to generate te_koop evaluation from AI API for contentType: $contentType. Error: " . $result);
            return [
                'message' => 'Failed to generate evaluation from AI API.' . ($result !== null ? ' ' . $result : ''),
                'content_length' => strlen($content)
            ];
        }
        return [
            'message' => $result,
            'content_length' => strlen($content)
        ];
    }

    /**
     * Summarizes content.
     *
     * @param string $content The content to summarize (HTML).
     * @param string $contentType Type of the content ('single_post_html', 'topic_html').
     * @param string $outputType Desired output format ('html', 'bbcode').
     * @return array An array with summary results.
     */
    public function summarizeContent(string $content, string $contentType): array
    {
        $prompt = "Vat de volgende foruminhoud samen.\n";
        if ($contentType === 'single_post_html') {
            $prompt .= "De inhoud is een enkele forumpost in HTML-formaat:\n";
            $this->logService->log("Summarizing single post.");
        } elseif ($contentType === 'topic_html') {
            $prompt .= "De inhoud bestaat uit meerdere forumposts van een topic, in HTML-formaat:\n";
            $this->logService->log("Summarizing topic posts.");
        } else {
            $this->logService->log("summarizeContent: Unknown contentType '$contentType'");
            $prompt .= "De volgende data, tussen <content> en </content> wordt ter samenvatting aangeboden:\n\n";
        }
        $prompt .= "<content>" . $content . "</content>";
        $prompt .= "Probeer een algemene samenvatting te maken en eventueel conclussies te trekken. Indien meerdere visies aanwezig zijn, probeer je deze te groeperen en geef je daarbij aan wie de voor of tegenstanders zijn.\n";
        $prompt .= "Externe verwijzingen of links mag je ook groeperen en meegeven in je samenvatting.\n";
        $prompt .= "Je algemene antwoord moet markup in HTML-formaat gebruiken met gebruik van linebreaks, headers en insprongen. Zorg ervoor dat de gegenereerde HTML zo compact mogelijk is, met minimale verticale witruimte tussen elementen.\n";
        $prompt .= "Gebruik GEEN <style> tags of inline stijlen (zoals style=\"margin: 0;\") in de gegenereerde HTML; de styling wordt extern afgehandeld. Vermijd overmatige marges en padding. \n";
        $prompt .= "Focus op een schone, semantische HTML-structuur die compact is zonder extra styling.\n\n";
        $result = $this->llmProvider->getLlmResponse($prompt); 
        if ($result === null || strpos($result, 'CURL_ERROR:') === 0 || strpos($result, 'API_ERROR:') === 0) {
            $this->logService->log("Failed to generate summary from AI API for contentType: $contentType. Error: " . $result);
            return [
                'message' => 'Failed to generate summary from AI API.' . ($result !== null ? ' ' . $result : ''),
                'content_length' => strlen($content)
            ];
        }
        return [
            'message' => $result,
            'content_length' => strlen($content)
        ];
    }
}
