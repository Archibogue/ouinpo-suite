<?php

namespace Ouinpo\Suite\Core;



final class Plugin

{

    public function __construct(private readonly ModuleRegistry $registry)

    {

    }

    public function modules(): array
    {
        return $this->registry->all();
    }

    public function activate(): void
    {
        // Source unique de vérité pour le schéma partagé.
        Installer::installOrUpgradeSharedSchema();

        foreach ($this->registry->all() as $module) {
            $module->activate();
        }

        // Les contraintes SegFault qui référencent les tables Exercices
        // doivent être ajoutées après l'activation des modules.
        Installer::ensureSegFaultConstraints();

        update_option('ouinpo_suite_version', OUINPO_SUITE_VERSION, false);
    }



    public function deactivate(): void

    {

        foreach ($this->registry->all() as $module) {

            $module->deactivate();

        }

    }



    public function boot(): void
    {
        if (is_admin()) {
            \Ouinpo\Suite\Core\Admin\SuiteAdmin::init();
        }

        foreach ($this->registry->all() as $module) {
            $moduleId = $module->id();

            // Le module Exercices est le socle : il doit toujours être chargé,
            // sinon les routes REST du front ne sont plus enregistrées.
            if ($moduleId !== 'exercises' && !ModuleSettings::isEnabled($moduleId)) {
                continue;
            }

            $module->boot();
        }

        Installer::maybeUpgrade();
    }

}