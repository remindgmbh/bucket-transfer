<?php

declare(strict_types=1);

namespace Remind\BucketTransfer\Command;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use Symfony\Component\Finder\Finder;

/**
 * The actual implementation
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
    protected const OPTION_LOCAL_PATH = 'local-path';

    /**
     * Name of the remote path parameter.
     * @var string
     */
    protected const OPTION_REMOTE_PATH = 'remote-path';

    /**
     * Name of the parameter to exclude directories by name.
     * @var string
     */
    protected const OPTION_EXCLUDE = 'exclude-dir';

    /**
     * Name of the dry run parameter.
     * @var string
     */
    protected const OPTION_DRY_RUN = 'dry-run';

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
     *
     * @var bool
     */
    protected bool $isDryRun = false;

    /**
     * Array of strings that each will be excluded by the symfony finder.
     *
     * @var array
     */
    protected array $excludes = [];

    /**
     * The bucket name.
     *
     * @var string
     */
    protected string $bucket = '';

    /**
     * The symfony console input.
     *
     * @var InputInterface|null
     */
    protected ?InputInterface $input = null;

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
                    self::OPTION_LOCAL_PATH,
                    null,
                    InputOption::VALUE_REQUIRED,
                    'The path to the local folder',
                    null
                ),
                new InputOption(
                    self::OPTION_REMOTE_PATH,
                    null,
                    InputOption::VALUE_REQUIRED,
                    'Path inside the bucket',
                    null
                ),
                new InputOption(
                    self::OPTION_DRY_RUN,
                    null,
                    InputOption::VALUE_NONE,
                    'Simulate the process without actual upload',
                    null
                ),
                new InputOption(
                    self::OPTION_EXCLUDE,
                    null,
                    InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                    'Exclude directories',
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
        $this->input = $input;
        $this->output = $output;

        try {
            $this->processOptions();
            $this->prepareClient();
        } catch (InvalidArgumentException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return self::EXIT_ERROR;
        }

        /* Fix the remote path if required */
        if ($this->remotePath[strlen($this->remotePath) - 1] !== '/') {
            $this->remotePath .= '/';
        }

        return $this->transfer();
    }

    /**
     * Processes the given input options into the class members and performs
     * a validation on the values.
     * Will exit the process on error.
     *
     * @return void
     */
    protected function processOptions(): void
    {
        /* Parse data from passed arguments */
        $this->localPath = $this->input->getOption(self::OPTION_LOCAL_PATH) ?? '';
        $this->remotePath = $this->input->getOption(self::OPTION_REMOTE_PATH) ?? '';
        $this->excludes = $this->input->getOption(self::OPTION_EXCLUDE);
        $this->isDryRun = $this->input->getOption(self::OPTION_DRY_RUN);

        $this->bucket = $_ENV['S3_BUCKET'] ?? '';

        /* Argument error checking */
        if ($this->localPath === '') {
            throw new InvalidArgumentException('Local path argument not set');
        } elseif ($this->remotePath === '') {
            throw new InvalidArgumentException('Remote path argument not set');
        } elseif ($this->bucket === '') {
            throw new InvalidArgumentException('S3_BUCKET environment variable not set');
        }
    }

    /**
     * Create a new S3Client instance from the values given in the
     * environment variables.
     * Will exit the process on error.
     *
     * @return void
     */
    protected function prepareClient(): void
    {
        /* Directly from the $_ENV superglobal. filter_input() did not work */
        $key = $_ENV['S3_KEY'] ?? '';
        $token = $_ENV['S3_TOKEN'] ?? '';
        $region = $_ENV['S3_REGION'] ?? '';

        if ($key === '') {
            throw new InvalidArgumentException('S3_KEY environment variable not set');
        } elseif ($token === '') {
            throw new InvalidArgumentException('S3_TOKEN environment variable not set');
        } elseif ($region === '') {
            throw new InvalidArgumentException('S3_REGION environment variable not set');
        }

        /* Create a new client */
        $this->client = new S3Client([
            'region' => $region,
            'version' => 'latest',
            'credentials' => [
                'key' => $key,
                'secret' => $token
            ]
        ]);
    }

    /**
     * Run the actual transfer.
     *
     * @return bool True on success; false in case any error occured
     */
    protected function transfer(): int
    {
        /* New symfony finder */
        $finder = new Finder();

        /* Apply excludes if given */
        if (!empty($this->excludes)) {
            $finder->exclude($this->excludes);
        }

        /*
         * Get the real path of the given local path
         * so the relative path can be created by cutting of this path string
         * from the absolute path of the currently processed file
         */
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

            /* Append final slash for directories */
            if ($file->isDir()) {
                $awsKey .= '/';
            }

            /* Generate bucket path (key) */
            $remotePathInfo = $this->bucket . '/' . $awsKey;

            /* Print info for -v option */
            $this->output->writeln('Storing: ' . $remotePathInfo, OutputInterface::VERBOSITY_VERBOSE);

            /* Skip actual upload and continue */
            if ($this->isDryRun) {
                continue;
            }

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
