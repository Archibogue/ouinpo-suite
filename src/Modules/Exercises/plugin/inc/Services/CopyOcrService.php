<?php

namespace Ouinpo\Exercises\Services;

defined('ABSPATH') || exit;

final class CopyOcrService
{
    public static function extract(string $path, string $mime_type, string $manual_text = ''): string
    {
        $manual_text = trim(wp_kses((string) $manual_text, []));
        if ($manual_text !== '') {
            return $manual_text;
        }

        /*
         * Fallback volontaire : les hébergements WordPress n’ont pas tous OCR/PDF tools.
         * L’enseignant peut coller le texte extrait manuellement, sinon l’analyse IA est bloquée
         * avec un message explicite.
         */
        return '';
    }

    public static function unavailable_message(): string
    {
        return 'OCR/vision indisponible : colle un texte extrait manuellement avant l’analyse IA.';
    }
}
