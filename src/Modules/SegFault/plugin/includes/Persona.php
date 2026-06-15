<?php

namespace OuInPo\SegFault;

use Ouinpo\Suite\Core\AiSettings;

defined('ABSPATH') || exit;

class Persona {

  static function system(): string {

    if (class_exists(AiSettings::class)) {
      return AiSettings::persona('chatbox', 'ouinpo_ai_persona_general');
    }

    return 'Tu es SegFault, assistant pedagogique NSI/SNT du site OuInPo. Tu guides par etapes, sans inventer de ressources.';

  }

}
