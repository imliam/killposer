<?php

use Killposer\Commands\ScanCommand;
use Killposer\Scanner\ProjectScanner;
use Symfony\Component\Console\Tester\CommandTester;

// ── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Create a testable ScanCommand subclass that returns predetermined prompt responses.
 *
 * @param  array<string>   $selectedPaths  Paths the multiselect should return.
 * @param  bool            $confirmed      Whether the confirmation prompt returns true.
 */
function makeCommand(
    array $selectedPaths = [],
    bool $confirmed = true,
    ProjectScanner $scanner = new ProjectScanner(),
): CommandTester {
    $command = new class($selectedPaths, $confirmed, $scanner) extends ScanCommand {
        public function __construct(
            private readonly array $selectedPaths,
            private readonly bool $confirmed,
            ProjectScanner $scanner,
        ) {
            parent::__construct($scanner);
        }

        protected function selectProjects(array $options): array
        {
            return $this->selectedPaths;
        }

        protected function confirmDeletion(string $label): bool
        {
            return $this->confirmed;
        }
    };

    // Anonymous classes don't inherit #[AsCommand] attributes, so set name explicitly.
    $command->setName('scan');

    return new CommandTester($command);
}

// ── Validation ────────────────────────────────────────────────────────────────

it('returns a failure exit code when the path does not exist', function () {
    $tester = makeCommand();

    $exitCode = $tester->execute(['path' => '/this/path/does/not/exist']);

    expect($exitCode)->toBe(1);
    expect($tester->getDisplay())->toContain('does not exist');
});

// ── No results ────────────────────────────────────────────────────────────────

it('exits successfully when the scan finds no projects', function () {
    $tmpDir = createTempDir();

    $tester = makeCommand();
    $exitCode = $tester->execute(['path' => $tmpDir], ['decorated' => false]);

    expect($exitCode)->toBe(0);

    deleteDir($tmpDir);
});

// ── Nothing selected ──────────────────────────────────────────────────────────

it('does nothing when the user selects no directories', function () {
    $tmpDir = createTempDir();
    $projectPath = createComposerProject($tmpDir, 'myapp');

    $tester = makeCommand(selectedPaths: []);
    $exitCode = $tester->execute(['path' => $tmpDir]);

    expect($exitCode)->toBe(0);
    expect(is_dir($projectPath . '/vendor'))->toBeTrue();

    deleteDir($tmpDir);
});

// ── Deletion with --force ─────────────────────────────────────────────────────

it('deletes a selected vendor directory when --force is passed', function () {
    $tmpDir = createTempDir();
    $projectPath = createComposerProject($tmpDir, 'myapp');

    $tester = makeCommand(selectedPaths: [$projectPath]);
    $exitCode = $tester->execute(['path' => $tmpDir, '--force' => true]);

    expect($exitCode)->toBe(0);
    expect(is_dir($projectPath . '/vendor'))->toBeFalse();
    expect($tester->getDisplay())->toContain('Deleted');

    deleteDir($tmpDir);
});

it('deletes multiple vendor directories when --force is passed', function () {
    $tmpDir = createTempDir();
    $project1 = createComposerProject($tmpDir, 'app1');
    $project2 = createComposerProject($tmpDir, 'app2');

    $tester = makeCommand(selectedPaths: [$project1, $project2]);
    $exitCode = $tester->execute(['path' => $tmpDir, '--force' => true]);

    expect($exitCode)->toBe(0);
    expect(is_dir($project1 . '/vendor'))->toBeFalse();
    expect(is_dir($project2 . '/vendor'))->toBeFalse();

    deleteDir($tmpDir);
});

// ── Deletion with confirmation ────────────────────────────────────────────────

it('deletes vendor directories after the user confirms', function () {
    $tmpDir = createTempDir();
    $projectPath = createComposerProject($tmpDir, 'myapp');

    $tester = makeCommand(selectedPaths: [$projectPath], confirmed: true);
    $exitCode = $tester->execute(['path' => $tmpDir]);

    expect($exitCode)->toBe(0);
    expect(is_dir($projectPath . '/vendor'))->toBeFalse();

    deleteDir($tmpDir);
});

it('aborts when the user declines the confirmation', function () {
    $tmpDir = createTempDir();
    $projectPath = createComposerProject($tmpDir, 'myapp');

    $tester = makeCommand(selectedPaths: [$projectPath], confirmed: false);
    $exitCode = $tester->execute(['path' => $tmpDir]);

    expect($exitCode)->toBe(0);
    expect(is_dir($projectPath . '/vendor'))->toBeTrue();
    expect($tester->getDisplay())->toContain('Aborted');

    deleteDir($tmpDir);
});

// ── Permission warning ────────────────────────────────────────────────────────

it('shows a warning when the scanner encounters permission errors', function () {
    if (posix_getuid() === 0) {
        $this->markTestSkipped('Cannot test permissions as root.');
    }

    $tmpDir = createTempDir();
    $locked = $tmpDir . '/locked';
    mkdir($locked, 0000, true);

    $tester = makeCommand(selectedPaths: []);
    $tester->execute(['path' => $tmpDir], ['decorated' => false]);

    expect($tester->getDisplay())->toContain('permission');

    chmod($locked, 0755);
    rmdir($locked);
    deleteDir($tmpDir);
});

// ── Custom depth ─────────────────────────────────────────────────────────────

it('passes the --depth option through to the scanner', function () {
    $tmpDir = createTempDir();
    $capturedDepth = null;

    $customScanner = new class($capturedDepth) extends ProjectScanner {
        public function __construct(public ?int &$capturedDepth) {}

        public function scan(string $rootPath, int $maxDepth = 5): array
        {
            $this->capturedDepth = $maxDepth;

            return [];
        }
    };

    $tester = makeCommand(selectedPaths: [], scanner: $customScanner);
    $tester->execute(['path' => $tmpDir, '--depth' => '2']);

    expect($customScanner->capturedDepth)->toBe(2);

    deleteDir($tmpDir);
});
