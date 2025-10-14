<?php

declare(strict_types=1);

namespace Multilotka\Tests\Core;

use Multilotka\Core\SessionManager;
use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 */
final class SessionManagerTest extends TestCase
{
    public function testFlashLifecycle(): void
    {
        session_save_path(sys_get_temp_dir());
        $session = new SessionManager();

        $session->flash('notice', 'Hello');
        self::assertSame('Hello', $session->getFlash('notice'));
        self::assertNull($session->getFlash('notice'));
    }

    public function testAllFlashesClearsStorage(): void
    {
        session_save_path(sys_get_temp_dir());
        $session = new SessionManager();
        $session->flash('one', '1');
        $session->flash('two', '2');

        $flashes = $session->allFlashes();

        self::assertSame(['one' => '1', 'two' => '2'], $flashes);
        self::assertSame([], $session->allFlashes());
    }
}
