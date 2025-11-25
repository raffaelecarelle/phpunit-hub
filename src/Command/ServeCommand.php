<?php

namespace PhpUnitHub\Command;

use PhpUnitHub\Discoverer\TestDiscoverer;
use PhpUnitHub\Parser\JUnitParser;
use PhpUnitHub\TestRunner\TestRunner;
use PhpUnitHub\WebSocket\StatusHandler;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use React\ChildProcess\Process;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Socket\SocketServer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'serve', description: 'Starts the PHPUnit GUI server.')]
class ServeCommand extends Command
{
    public function __construct(
        private ?LoopInterface        $loop = null,
        private ?TestDiscoverer       $testDiscoverer = null,
        private ?StatusHandler        $statusHandler = null,
        private ?WsServer             $wsServer = null,
        private readonly ?JUnitParser $jUnitParser = new JUnitParser(),
        private ?TestRunner           $testRunner = null,
    ) {
        parent::__construct();

        $this->loop ??= Loop::get();
        $this->testDiscoverer ??= new TestDiscoverer(getcwd());
        $this->testRunner ??= new TestRunner($this->loop);
    }


    protected function configure(): void
    {
        $this->setDescription('Starts the PHPUnit GUI server.')
            ->addOption('watch', 'w', InputOption::VALUE_NONE, 'Enable file watching to re-run tests on changes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $port = 8080;
        $watch = $input->getOption('watch');

        $this->statusHandler ??= new StatusHandler($output);
        $this->wsServer ??= new WsServer($this->statusHandler);

        $router = new Router(
            $this->wsServer,
            $output,
            $this->statusHandler,
            $this->testRunner,
            $this->jUnitParser,
            $this->testDiscoverer
        );

        $httpServer = new HttpServer($router);
        $socketServer = new SocketServer('127.0.0.1:' . $port, [], $this->loop);
        $ioServer = new IoServer($httpServer, $socketServer, $this->loop);

        if ($watch) {
            $this->startFileWatcher($this->loop, $output, $router);
        }

        $output->writeln(sprintf('<info>Starting server on http://127.0.0.1:%d</info>', $port));
        $output->writeln("API endpoint available at GET /api/tests");
        $output->writeln("API endpoint available at POST /api/run");
        $output->writeln("API endpoint available at POST /api/run-failed");
        $output->writeln("API endpoint available at POST /api/stop");
        $output->writeln("WebSocket server listening on /ws/status");
        $output->writeln("Serving static files from 'public' directory");

        $ioServer->run();

        return Command::SUCCESS;
    }

    private function startFileWatcher(LoopInterface $loop, OutputInterface $output, RouterInterface $router): void
    {
        // Check for inotifywait availability
        $checkProcess = new Process('command -v inotifywait');
        $checkProcess->start($loop);

        $checkProcess->on('exit', function ($exitCode) use ($loop, $output, $router) {
            if ($exitCode !== 0) {
                $output->writeln('<error>Error: `inotifywait` command not found.</error>');
                $output->writeln('<error>Please install `inotify-tools` to use the --watch feature on Linux.</error>');
                $output->writeln('<error>Example: sudo apt-get install inotify-tools</error>');
                return;
            }

            $output->writeln('<info>Event-based file watcher enabled (inotify).</info>');

            // Dynamically find paths from composer.json
            $watchPaths = [];
            $composerJsonPath = getcwd() . '/composer.json';

            if (file_exists($composerJsonPath)) {
                $composerConfig = json_decode(file_get_contents($composerJsonPath), true);
                $psr4Paths = array_merge(
                    $composerConfig['autoload']['psr-4'] ?? [],
                    $composerConfig['autoload-dev']['psr-4'] ?? []
                );

                foreach ($psr4Paths as $psr4Path) {
                    if (is_array($psr4Path)) {
                        $watchPaths = array_merge($watchPaths, $psr4Path);
                    } else {
                        $watchPaths[] = $psr4Path;
                    }
                }
            }

            // Fallback to default if composer.json is not found or doesn't have paths
            if ($watchPaths === []) {
                $watchPaths = ['src', 'tests'];
            }

            $absolutePaths = array_map(fn ($path) => getcwd() . '/' . trim($path, '/\\'), $watchPaths);
            $uniquePaths = array_unique($absolutePaths);
            $existingWatchPaths = array_filter($uniquePaths, is_dir(...));

            if ($existingWatchPaths === []) {
                $output->writeln('<warning>Could not find any valid directories to watch from composer.json or defaults.</warning>');
                return;
            }

            $command = sprintf(
                'inotifywait -q -m -r -e modify,create,delete,move --format "%%e %%w%%f" %s',
                implode(' ', array_map(escapeshellarg(...), $existingWatchPaths))
            );

            $watcherProcess = new Process($command);
            $watcherProcess->start($loop);

            $debounceTimer = null;

            $watcherProcess->stdout->on('data', function ($chunk) use ($output, $loop, $router, &$debounceTimer) {
                $lines = explode("\n", trim($chunk));
                foreach ($lines as $line) {
                    if (trim($line) === '') {
                        continue;
                    }

                    if (!str_ends_with(strtolower($line), '.php')) {
                        continue;
                    }

                    $output->writeln(sprintf('<comment>File change detected: %s</comment>', $line));
                }

                if ($debounceTimer instanceof \React\EventLoop\TimerInterface) {
                    $loop->cancelTimer($debounceTimer);
                }

                $debounceTimer = $loop->addTimer(0.5, function () use ($router, $output) {
                    $output->writeln('<info>Re-running tests due to file changes...</info>');
                    $router->runTests($router->getLastFilters());
                });
            });

            $watcherProcess->stderr->on('data', function ($chunk) use ($output) {
                $output->writeln(sprintf('<error>Watcher error: %s</error>', $chunk));
            });

            foreach ($existingWatchPaths as $existingWatchPath) {
                $output->writeln(sprintf('<info>Watching for changes in %s</info>', $existingWatchPath));
            }
        });
    }
}
