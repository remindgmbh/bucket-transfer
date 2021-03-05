<?php

declare (strict_types=1);

namespace Remind\BucketTransfer\Command;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use Symfony\Component\Finder\Finder;

/**
 * The actual implementation
 *
 * @todo Implement command argument to exclude folders or files
 */
class TransferCommand extends Command
{
    /**
     * Name of the command.
     * @var string
     */
    protected const COMMAND_NAME = 'run';

    /**
     * Name of the local path parameter.
     * @var string
     */
    protected const ARGUMENT_LOCAL_PATH = 'local-path';

    /**
     * Name of the remote path parameter.
     * @var string
     */
    protected const ARGUMENT_REMOTE_PATH = 'remote-path';

    /**
     * Return value for success.
     * @var int
     */
    protected const EXIT_OK = 0;

    /**
     * Return value for an error.
     */
    protected const EXIT_ERROR = 1;

    /**
     * The actual S3 client instance.
     *
     * @var S3Client|null
     */
    protected ?S3Client $client = null;

    /**
     * The local path.
     *
     * @var string
     */
    protected string $localPath = '';

    /**
     * The remote path.
     *
     * @var string
     */
    protected string $remotePath = '';

    /**
     * The bucket name.
     *
     * @var string
     */
    protected string $bucket = '';

    /**
     * The symfony console output.
     *
     * @var OutputInterface|null
     */
    protected ?OutputInterface $output = null;

    /**
     * Configure the command.
     *
     * @return void
     */
    protected function configure(): void
    {
        parent::configure();

        $this->setName(self::COMMAND_NAME);
        $this->setDescription('Transfer files from a local path to an S3 bucket.');
        $this->setDefinition(
            new InputDefinition([
                new InputOption(
                    self::ARGUMENT_LOCAL_PATH,
                    null,
                    InputOption::VALUE_REQUIRED,
                    'The path to the local folder',
                    null
                ),
                new InputOption(
                    self::ARGUMENT_REMOTE_PATH,
                    null,
                    InputOption::VALUE_REQUIRED,
                    'Path inside the bucket',
                    null
                )
            ])
        );
    }

    /**
     * Execute the command.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /* Parse data from passed arguments */
        $this->localPath = $input->getOption(self::ARGUMENT_LOCAL_PATH) ?? '';
        $this->remotePath = $input->getOption(self::ARGUMENT_REMOTE_PATH) ?? '';

        /* Directly from the $_ENV superglobal. filter_input() did not work */
        $key = $_ENV['S3_KEY'] ?? '';
        $token = $_ENV['S3_TOKEN'] ?? '';
        $this->bucket = $_ENV['S3_BUCKET'] ?? '';
        $region = $_ENV['S3_REGION'] ?? '';

        /* Argument error checking */
        if ($this->localPath === '') {
            $output->writeln('<error>Local path argument not set</error>');
            return self::EXIT_ERROR;
        } else if ($this->remotePath === '') {
            $output->writeln('<error>Remote path argument not set</error>');
            return self::EXIT_ERROR;
        } else if ($key === '') {
            $output->writeln('<error>S3_KEY environment variable not set</error>');
            return self::EXIT_ERROR;
        } else if ($token === '') {
            $output->writeln('<error>S3_TOKEN environment variable not set</error>');
            return self::EXIT_ERROR;
        } else if ($this->bucket === '') {
            $output->writeln('<error>S3_BUCKET environment variable not set</error>');
            return self::EXIT_ERROR;
        } else if ($region === '') {
            $output->writeln('<error>S3_REGION environment variable not set</error>');
            return self::EXIT_ERROR;
        }

        /* Fix the remote path if required */
        if ($this->remotePath[strlen($this->remotePath) - 1] !== '/') {
            $this->remotePath .= '/';
        }

        $this->output = $output;

        /* Create a new client */
        $this->client = new S3Client([
            'region' => $region,
            'version' => 'latest',
            'credentials' => [
                'key' => $key,
                'secret' => $token
            ]
        ]);

        return $this->transfer();
    }

    /**
     *
     * @return bool
     */
    protected function transfer(): int
    {
        /* New symfony finder */
        $finder = new Finder();

        /* Get the real path of the given local path */
        $realPath = realpath($this->localPath);

        try {
            /* Find everything in the real local path */
            $finder->in($realPath);
        } catch (DirectoryNotFoundException $e) {
            $this->output->writeln('<error>' . $e->getMessage() . '</error>');
            return self::EXIT_ERROR;
        }

        /* No results must be an error */
        if (!$finder->hasResults()) {
            $this->output->writeln('<error>Nothing found in ' . $this->localPath . '</error>');
            return self::EXIT_ERROR;
        }

        $result = self::EXIT_OK;

        foreach ($finder as $file) {
            /* @var $file \SplFileInfo */

            /* Generate absolute path to current file */
            $absolutePath = $file->getPath() . DIRECTORY_SEPARATOR . $file->getFilename();

            /* Output info when command used with -vv */
            $this->output->writeln('Processing: ' . $absolutePath, OutputInterface::VERBOSITY_VERY_VERBOSE);

            /* Sanitize to relative path inside bucket */
            $relativeSanitized = str_replace([ $realPath, '\\' ], [ '', '/' ], $file->getPath());

            /* Final cleanup; remove leading slash */
            if ($relativeSanitized !== '' && $relativeSanitized[0] === '/') {
                $relativeSanitized = substr($relativeSanitized, 1) . '/';
            }

            /* Generated path for key field in putObject */
            $awsKey = $this->remotePath . $relativeSanitized . $file->getFilename();

            /* Generate bucket path (key) */
            $remotePathInfo = $this->bucket . '/' . $awsKey;

            /* Print info for -v option */
            $this->output->writeln('Storing: ' . $remotePathInfo, OutputInterface::VERBOSITY_VERBOSE);

            try {

                /* Prepare general options */
                $options = [
                    'Bucket' => $this->bucket,
                    'Key'    => $awsKey,
                    'ACL'    => 'public-read'
                ];

                /* If current object is a directory */
                if (!$file->isDir()) {

                    /* Perform upload magic */
                    $options['SourceFile'] = $absolutePath;
                }

                /* Put object, ignore result */
                $this->client->putObject($options);

            } catch (S3Exception $e) {
                /* Print error */
                $this->output->writeln('<error>' . $e->getMessage() . '</error>');

                /* Mark transfer as error */
                $result = self::EXIT_ERROR;
            }
        }

        return $result;
    }
}
