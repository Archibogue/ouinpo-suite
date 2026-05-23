<?php
namespace Ouinpo\Exercises;

defined('ABSPATH') || exit;

final class PracticalAiBridge {

    public static function init(): void {
        add_filter('ouinpo_practical_ai_evaluate', [__CLASS__, 'evaluate'], 10, 2);
    }

    public static function evaluate($result, array $payload): array {
        $subject = $payload['subject'] ?? [];
        $call    = $payload['call'] ?? [];
        $answer  = trim((string) ($payload['answer'] ?? ''));

        if ($answer === '') {
            return [
                'verdict'             => 'incorrect',
                'feedback'            => 'Réponse vide.',
                'next_steps'          => ['Écris une réponse avant de demander la correction.'],
                'confidence'          => 0.99,
                'safe_to_mark_solved' => false,
            ];
        }

        $subject_title = (string) ($subject['title'] ?? 'Sujet pratique');
        $call_title    = (string) ($call['title'] ?? ('Appel ' . ($call['call_order'] ?? '')));
        $prompt_html   = (string) ($call['prompt_html'] ?? '');
        $ai_rubric     = (string) ($call['ai_rubric'] ?? '');
        $answer_mode   = (string) ($call['answer_mode'] ?? 'code');
        $parts = self::extract_answer_parts($answer, $answer_mode);

        $system_prompt = implode("\n\n", [
            "Tu es CodeBogue, une IA spécialisée dans la correction de code Python pour la spécialité NSI.",
            "Tu corriges un appel d'épreuve pratique NSI.",
            "Tu dois évaluer la réponse de l'élève avec prudence.",
            "Tu dois classer la réponse dans exactement une des catégories suivantes : correct, partial, incorrect.",
            "Tu ne dois jamais inventer un comportement absent du code fourni.",
            "Tu dois distinguer les erreurs de syntaxe, les erreurs logiques, les cas limites manquants, les mauvais types de retour et la confusion entre afficher et renvoyer.",
            "Tu dois respecter le niveau NSI attendu et ne pas exiger de notions hors programme.",
            "Lorsque la réponse contient des balises [code]...[/code], tu dois évaluer le code contenu dans ces blocs et utiliser le texte hors code seulement comme explication complémentaire.",
            "Pour une réponse en mode code, si aucun bloc [code]...[/code] n'est fourni mais que la réponse ressemble à du Python, considère la réponse comme du code brut.",
            "Si le code est correct pour l'idée principale mais fragile sur des cas limites, le verdict doit être partial.",
            "Si la réponse semble correcte mais que la validation officielle reste risquée, mets safe_to_mark_solved à false.",
            "Tu ne mets safe_to_mark_solved à true que si la réponse est correcte, cohérente avec la consigne, sans erreur logique visible, et suffisamment robuste sur les cas attendus.",
            "Tu dois donner un retour pédagogique court, utile, sans donner toute la solution si l'élève est proche.",
            "Tu dois répondre dans un français correct et naturel. N'utilise pas de mots anglais sauf s'ils apparaissent dans le code ou dans un nom technique imposé.",
            "Si le verdict est correct et que safe_to_mark_solved vaut true, next_steps doit être une liste vide, sauf si une consigne explicite demande encore une action.",
            "Réponds uniquement en JSON valide, sans texte autour."
        ]);

        $configured_prompt = trim((string) get_option('ouinpo_ai_practical_correction_prompt', ''));
        if ($configured_prompt !== '') {
            $system_prompt = $configured_prompt . "\n\nReponds uniquement en JSON valide, sans texte autour.";
        }

        $user_prompt_parts = [
            "Sujet : " . $subject_title,
            "Appel : " . $call_title,
            "Mode de réponse attendu : " . $answer_mode,
            "Consigne de l'appel :",
            wp_strip_all_tags($prompt_html),
        ];

        if ($ai_rubric !== '') {
            $user_prompt_parts[] = "Critères de correction :";
            $user_prompt_parts[] = $ai_rubric;
        }

        $user_prompt_parts[] = "Réponse de l'élève — texte hors code :";
        $user_prompt_parts[] = $parts['has_text'] ? $parts['text'] : "(aucun texte hors code)";
        
        if ($parts['has_code']) {
            $user_prompt_parts[] = "Bloc(s) de code fourni(s) par l'élève :";
        
            foreach ($parts['codes'] as $index => $code) {
                $user_prompt_parts[] = "Bloc de code " . ($index + 1) . " :\n" . $code;
            }
        } else {
            $user_prompt_parts[] = "Aucun bloc de code détecté.";
        }

$user_prompt_parts[] = <<<TXT
Réponds uniquement en JSON valide au format :
{
  "verdict": "correct|partial|incorrect",
  "feedback": "texte court",
  "next_steps": ["conseil court 1", "conseil court 2"],
  "confidence": 0.0,
  "safe_to_mark_solved": false
}
TXT;

        $user_prompt = implode("\n\n", $user_prompt_parts);

        /**
         * ==========================================================
         * ICI : branche ton vrai moteur SegFault / OpenAI existant
         * ==========================================================
         *
         * Remplace ce bloc par le même appel que pour les exos classiques.
         * Le résultat final attendu est un tableau PHP :
         * [
         *   'verdict' => 'correct'|'partial'|'incorrect',
         *   'feedback' => '...',
         *   'confidence' => 0.87,
         * ]
         */

        $raw = self::call_existing_segfault_engine($system_prompt, $user_prompt);

        if (!is_array($raw)) {
            return [
                'verdict'             => 'incorrect',
                'feedback'            => 'Le moteur d’évaluation n’a pas renvoyé de résultat exploitable.',
                'next_steps'          => ['Réessaie plus tard ou signale le problème au professeur.'],
                'confidence'          => 0.10,
                'safe_to_mark_solved' => false,
            ];
        }

        $verdict = (string) ($raw['verdict'] ?? 'incorrect');
        if (!in_array($verdict, ['correct', 'partial', 'incorrect'], true)) {
            $verdict = 'incorrect';
        }

        return [
            'verdict'             => $verdict,
            'feedback'            => (string) ($raw['feedback'] ?? 'Retour indisponible.'),
            'next_steps'          => is_array($raw['next_steps'] ?? null) ? $raw['next_steps'] : [],
            'confidence'          => isset($raw['confidence']) ? (float) $raw['confidence'] : null,
            'safe_to_mark_solved' => !empty($raw['safe_to_mark_solved']),
        ];
    }

    private static function call_existing_segfault_engine(string $system_prompt, string $user_prompt): ?array {
        if (!class_exists('\OuInPo\SegFault\OpenAI')) {
            return null;
        }
    
        try {
            \Ouinpo\Suite\Core\AiSettings::debug_log('Practical correction AI call', ['usage' => 'practical_correction', 'purpose' => 'code']);
            $raw = \OuInPo\SegFault\OpenAI::respond([
                ['role' => 'system', 'content' => $system_prompt],
                ['role' => 'user',   'content' => $user_prompt],
            ], [
                'temperature' => (float) get_option('ouinpo_ai_temperature', 0.0),
                'max_tokens'  => \Ouinpo\Suite\Core\AiSettings::maxTokens('ouinpo_ai_practical_ai_max_tokens'),
                'albert_purpose' => 'code',
                'response_format' => [
                    'type' => 'json_object',
                ],
            ]);
        } catch (\Throwable $e) {
            \Ouinpo\Suite\Core\AiSettings::debug_log('Practical correction AI error', ['usage' => 'practical_correction', 'error' => $e->getMessage()]);
            return null;
        }
    
        $parsed = self::extract_json((string) $raw);
        if (!is_array($parsed)) {
            \Ouinpo\Suite\Core\AiSettings::debug_log('Practical correction invalid JSON', ['usage' => 'practical_correction']);
            return null;
        }
    
        $verdict = (string) ($parsed['verdict'] ?? 'incorrect');
        if (!in_array($verdict, ['correct', 'partial', 'incorrect'], true)) {
            $verdict = 'incorrect';
        }
    
        $feedback = trim((string) ($parsed['feedback'] ?? ''));
        if ($feedback === '') {
            $feedback = ($verdict === 'correct')
                ? 'Ta réponse est correcte.'
                : (($verdict === 'partial')
                    ? 'Ta réponse va dans la bonne direction, mais elle reste incomplète.'
                    : 'Ta réponse est à revoir.');
        }
    
        $confidence = isset($parsed['confidence']) ? (float) $parsed['confidence'] : 0.0;
        $confidence = max(0.0, min(1.0, $confidence));
    
        $next_steps = [];
        
        if (!empty($parsed['next_steps']) && is_array($parsed['next_steps'])) {
            foreach ($parsed['next_steps'] as $step) {
                $step = trim((string) $step);
                if ($step !== '') {
                    $next_steps[] = $step;
                }
            }
        }
        
        $next_steps = array_values(array_slice($next_steps, 0, 3));
        
        $safe_to_mark_solved = !empty($parsed['safe_to_mark_solved']);
        
        /*
         * Si la réponse est officiellement correcte, on évite d'afficher
         * des conseils génériques du type "vérifie les tests", qui donnent
         * l'impression que la réponse n'est pas vraiment validée.
         */
        if ($verdict === 'correct' && $safe_to_mark_solved) {
            $next_steps = [];
        }
        
        return [
            'verdict'             => $verdict,
            'feedback'            => $feedback,
            'next_steps'          => $next_steps,
            'confidence'          => $confidence,
            'safe_to_mark_solved' => $safe_to_mark_solved,
        ];
    }

    private static function extract_answer_parts(string $raw, string $answer_mode = 'code'): array {
        $raw = trim($raw);
        $answer_mode = strtolower(trim($answer_mode));
    
        preg_match_all('/\[code\](.*?)\[\/code\]/is', $raw, $matches);
    
        $codes = [];
        if (!empty($matches[1])) {
            foreach ($matches[1] as $block) {
                $code = trim((string) $block);
                if ($code !== '') {
                    $codes[] = $code;
                }
            }
        }
    
        $text = preg_replace('/\[code\].*?\[\/code\]/is', '', $raw);
        $text = trim((string) $text);
    
        /*
         * Pour les appels pratiques en mode code :
         * si l'élève colle directement du code sans balises [code],
         * on le traite quand même comme du code.
         */
        if (empty($codes) && $raw !== '' && $answer_mode === 'code') {
            $codes[] = $raw;
            $text = '';
        }
    
        return [
            'raw'          => $raw,
            'text'         => $text,
            'codes'        => $codes,
            'primary_code' => $codes ? $codes[0] : '',
            'has_text'     => ($text !== ''),
            'has_code'     => !empty($codes),
        ];
    }

    private static function extract_json(string $raw): ?array {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
    
        $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw);
        $raw = preg_replace('/\s*```$/', '', $raw);
        $raw = trim((string) $raw);
    
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    
        if (preg_match('/\{[\s\S]*\}/', $raw, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
    
        return null;
    }
    
}
