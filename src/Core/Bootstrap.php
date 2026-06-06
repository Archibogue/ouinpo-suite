<?php
namespace Ouinpo\Suite\Core;

use Ouinpo\Suite\Modules\Exercises\Module as ExercisesModule;
use Ouinpo\Suite\Modules\Flashcards\Module as FlashcardsModule;
use Ouinpo\Suite\Modules\Submissions\Module as SubmissionsModule;
use Ouinpo\Suite\Modules\SegFault\Module as SegFaultModule;
use Ouinpo\Suite\Modules\Gate\Module as GateModule;
use Ouinpo\Suite\Modules\RechText\Module as RechTextModule;
use Ouinpo\Suite\Modules\Meta\Module as MetaModule;
use Ouinpo\Suite\Modules\Projects\Module as ProjectsModule;

final class Bootstrap
{
    public static function makePlugin(): Plugin
    {
        $registry = new ModuleRegistry();
        $registry->register(new ExercisesModule());
        $registry->register(new FlashcardsModule());
        $registry->register(new SubmissionsModule());
        $registry->register(new SegFaultModule());
        $registry->register(new GateModule());
        $registry->register(new RechTextModule());
        $registry->register(new MetaModule());
        $registry->register(new ProjectsModule());

        return new Plugin($registry);
    }
}
