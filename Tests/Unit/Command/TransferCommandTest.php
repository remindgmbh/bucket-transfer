<?php

declare(strict_types=1);

namespace Remind\BucketTransfer\Tests\Unit\Command;

use PHPUnit\Framework\TestCase;
use Remind\BucketTransfer\Command\TransferCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Dotenv\Dotenv;

/**
 * Description of TransferCommandTest
 */
final class TransferCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        /* Load config from dotenv files */
        $dotenv = new Dotenv();
        $dotenv->loadEnv(dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . '.env');
    }

    public function testCannotExecuteWithoutArguments(): void
    {
        $application = new Application();
        $application->add(new TransferCommand());

        $command = $application->find('run');

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            '--dry-run'
        ]);

        $output = trim($commandTester->getDisplay());
        $this->assertEquals('Local path argument not set', $output);
    }

    public function testCannotExecuteWithoutRemotePathArgument(): void
    {
        $application = new Application();
        $application->add(new TransferCommand());

        $command = $application->find('run');

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            '--local-path' => '/tmp',
            '--dry-run'
        ]);

        $output = trim($commandTester->getDisplay());
        $this->assertEquals('Remote path argument not set', $output);
    }
}
