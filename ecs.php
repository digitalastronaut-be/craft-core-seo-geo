<?php

declare(strict_types=1);

use craft\ecs\SetList;
use PhpCsFixer\Fixer\Basic\BracesFixer;
use PhpCsFixer\Fixer\ClassNotation\ClassDefinitionFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;

return static function(ECSConfig $ecsConfig): void {
    $ecsConfig->paths([
        __DIR__ . '/src',
        __FILE__,
    ]);

    $ecsConfig->sets([
        SetList::CRAFT_CMS_4,
    ]);

    // CRAFT_CMS_4 pulls in PSR_12's BracesFixer/ClassDefinitionFixer, which force an opening
    // brace onto its own new line for every class/method and wrap every single-statement
    // control structure body in braces - neither matches this codebase's actual house style
    // (same-line opening braces everywhere, brace-less single-line early returns), and
    // BracesFixer's brace-wrapping behavior has no configuration toggle to turn off. Removing
    // both outright is the only way to stop `fix-cs` from rewriting every file to a style
    // nobody here uses.
    $services = $ecsConfig->services();
    $services->remove(BracesFixer::class);
    $services->remove(ClassDefinitionFixer::class);
};
