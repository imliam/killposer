<?php

namespace Killposer\Commands;

use Illuminate\Support\Number;
use Killposer\Scanner\ProjectResult;
use Killposer\Scanner\ProjectScanner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\spin;

#[AsCommand(name: 'scan', description: 'Scan for Composer vendor directories and delete selected ones')]
class ScanCommand extends Command
{
    public function __construct(private readonly ProjectScanner $scanner = new ProjectScanner())
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('path', InputArgument::OPTIONAL, 'The directory to scan', null)
            ->addOption('depth', null, InputOption::VALUE_REQUIRED, 'Maximum directory depth to scan', 2)
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip the confirmation prompt before deleting');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rawPath = $input->getArgument('path') ?? getcwd();
        $path = realpath((string) $rawPath);

        if ($path === false || ! is_dir($path)) {
            $output->writeln("<error>Path '{$rawPath}' does not exist or is not a directory.</error>");

            return Command::FAILURE;
        }

        $maxDepth = (int) $input->getOption('depth');
        $force = (bool) $input->getOption('force');

        /** @var ProjectResult[] $results */
        $results = spin(
            callback: fn () => $this->scanner->scan($path, $maxDepth),
            message: 'Scanning for Composer projects...',
        );

        if ($this->scanner->permissionErrors() > 0) {
            $count = $this->scanner->permissionErrors();
            $output->writeln('<comment>' . $count . ' director' . ($count === 1 ? 'y' : 'ies') . ' could not be read due to permission errors.</comment>');
        }

        if (empty($results)) {
            $output->writeln('No Composer projects with vendor directories found.');

            return Command::SUCCESS;
        }

        $options = [];

        foreach ($results as $result) {
            $relativePath = $result->relativePath($path);
            $size = Number::fileSize($result->vendorSize);
            $date = $result->vendorModifiedAt->format('Y-m-d');

            $options[$result->path] = "{$relativePath} · {$size} · {$date}";
        }

        $selected = $this->selectProjects($options);

        if (empty($selected)) {
            $output->writeln('No directories selected. Nothing to do.');

            return Command::SUCCESS;
        }

        if (! $force) {
            $selectedResults = array_filter($results, fn (ProjectResult $r) => in_array($r->path, $selected, strict: true));
            $totalSize = (int) array_sum(array_map(fn (ProjectResult $r) => $r->vendorSize, $selectedResults));
            $count = count($selected);
            $noun = $count === 1 ? 'directory' : 'directories';

            $confirmed = $this->confirmDeletion("Delete {$count} vendor {$noun} totalling " . Number::fileSize($totalSize) . '?');

            if (! $confirmed) {
                $output->writeln('Aborted. Nothing was deleted.');

                return Command::SUCCESS;
            }
        }

        foreach ($selected as $projectPath) {
            $vendorPath = $projectPath . '/vendor';
            $this->deleteDirectory($vendorPath);
            $output->writeln('<info>Deleted</info> ' . $vendorPath);
        }

        return Command::SUCCESS;
    }

    /**
     * @param  array<string, string>  $options
     * @return array<string>
     */
    protected function selectProjects(array $options): array
    {
        return multiselect(
            label: 'Select vendor directories to delete',
            options: $options,
            hint: 'Space to toggle, ↑↓ to navigate, Enter to confirm',
            scroll: 15,
        );
    }

    protected function confirmDeletion(string $label): bool
    {
        return confirm(label: $label, default: false);
    }

    private function deleteDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $entries = scandir($path);

        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $entryPath = "{$path}/{$entry}";

            if (is_dir($entryPath) && ! is_link($entryPath)) {
                $this->deleteDirectory($entryPath);
            } else {
                unlink($entryPath);
            }
        }

        rmdir($path);
    }
}
