<?php

declare(strict_types=1);

namespace Remind\BucketTransfer\Tests\Unit\Command;

use PHPUnit\Framework\TestCase;
use Remind\BucketTransfer\Command\TransferCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Description of TransferCommandTest
 */
final class TransferCommandTest extends TestCase
{
    public function testExecuteWithoutArguments(): void
    {
        $application = new Application();
        $application->add(new TransferCommand());

        $command = $application->find('run');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName()
        ]);

        $output = trim($commandTester->getDisplay());
        $this->assertEquals('Local path argument not set', $output);
    }
}
