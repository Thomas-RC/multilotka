<?php

declare(strict_types=1);

namespace Multilotka\Core;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class TwigFactory
{
    public static function create(): Environment
    {
        $loader = new FilesystemLoader(__DIR__ . '/../../templates');

        return new Environment($loader, [
            'cache' => false,
            'autoescape' => 'html',
        ]);
    }
}
