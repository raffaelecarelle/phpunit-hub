# PHPUnit Hub

![PHP Version](https://img.shields.io/badge/php-8.2%2B-blue.svg)
![License](https://img.shields.io/badge/license-MIT-brightgreen.svg)

PHPUnit Hub is a modern, real-time web interface for running and analyzing PHPUnit tests. It provides a local, self-contained server that discovers your tests, runs them, and displays the results in a clean and interactive web UI.

The entire application is powered by a PHP backend using the high-performance [ReactPHP](https://reactphp.org/) event-loop, with a frontend built on [Vue.js](https://vuejs.org/) and [Tailwind CSS](https://tailwindcss.com/).

## Features

- **Real-Time Feedback**: Watch your tests run in real-time via WebSockets. Test results appear immediately as each test completes, providing instant feedback during long test runs.
- **Event-Based Architecture**: Uses a custom PHPUnit extension that streams test events in real-time, eliminating the need for post-processing or file parsing.
- **Test Explorer**: The test list on the left is automatically populated by running `phpunit --list-tests`. You can click on a specific test to run it.
- **Interactive Filtering**:
    - **Test Suite**: The available test suites are automatically populated by running `phpunit --list-suites`.
    - **Group**: The available groups are automatically populated by running `phpunit --list-groups`. You can select multiple groups to run.
    - **Filter**: You can write your own filter pattern.
- **Automatic Test Re-run**: Automatically re-run your tests on file changes with the efficient `--watch` mode.
- **Live Results Display**: The "Results" tab populates progressively as tests execute:
    - **Execution Summary**: A top-level summary shows total tests, assertions, and duration (updated in real-time).
    - **Status Breakdown**: Get immediate counts for passed, failed, errors, skipped, warnings, and deprecations.
    - **Grouped by TestCase**: Tests are grouped by their parent TestCase for clarity.
    - **Prioritized Issues**: Tests with failures, errors, or other issues are displayed first. You can expand them to see detailed stack traces.
    - **Clean UI**: Passed tests are collapsed into a single summary line per group, reducing noise.
- **Modern UI**: A clean, responsive interface built with Vue.js and Tailwind CSS.
- **Self-Contained**: Runs with a simple command, no need for a separate web server like Nginx or Apache.

## Installation

PHPUnit Hub is designed to be included as a development dependency in your PHP project.

1.  **Require with Composer**:
    Navigate to your project's root directory and run:
    ```sh
    composer require --dev raffaelecarelle/phpunit-hub
    ```

2.  **Configure PHPUnit**:
    PHPUnit Hub requires a `phpunit.xml` or `phpunit.xml.dist` file in your project's root directory. If you don't have one yet, create it:

    ```xml
    <?xml version="1.0" encoding="UTF-8"?>
    <phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
             xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
             bootstrap="vendor/autoload.php"
             colors="true">
        <testsuites>
            <testsuite name="default">
                <directory>tests</directory>
            </testsuite>
        </testsuites>
    </phpunit>
    ```

3.  **Enable Real-Time Event Streaming**:
    For the best experience with live test results, add the PHPUnit Hub extension to your `phpunit.xml` or `phpunit.xml.dist`:

    ```xml
    <extensions>
        <bootstrap class="PhpUnitHub\PHPUnit\PhpUnitHubExtension"/>
    </extensions>
    ```

    **What does this extension do?**
    - Streams test events (test started, passed, failed, etc.) in real-time via STDERR
    - Enables progressive result updates in the UI as tests execute
    - No temporary files or post-processing required
    - Zero impact on test execution performance

    **Note**: Without the extension, PHPUnit Hub will still work, but results will only appear after all tests complete.

That's it! All dependencies will be installed automatically.

## Usage

1.  **Start the Server**:
    From your project's root directory, run the `serve` command:
    ```sh
    vendor/bin/phpunit-hub
    ```
    The server will start, and you will see a confirmation message:
    ```
    Starting server on http://127.0.0.1:8080
    API endpoint available at GET /api/tests
    API endpoint available at POST /api/run
    WebSocket server listening on /ws/status
    Serving static files from 'public' directory
    ```

2.  **Open the Web UI**:
    Open your web browser and navigate to **[http://127.0.0.1:8080](http://127.0.0.1:8080)**.

3.  **Run Your Tests**:
    - The **Test Explorer** on the left will show all the test suites and methods found in your project.
    - You can click on a specific test method to run it individually.
    - Alternatively, you can use the filters panel to select test suites, groups, or custom patterns.
    - Click the **"Run All"** button to start the test execution.
    - The **"Results"** tab will populate in real-time as tests execute, showing you immediate feedback on test status, failures, and errors.
    - Tests are grouped by their TestCase class, with failures and errors displayed prominently at the top.

### File Watching (Auto Re-run)

To automatically re-run tests whenever a source or test file changes, start the server with the `--watch` option:

```sh
vendor/bin/phpunit-hub --watch
```

When you save a `.php` file in your `src/` or `tests/` directories, the test suite will automatically execute again using the last applied filters.

**System Requirement for `--watch` on Linux:**

The watch mode on Linux relies on `inotify` for high-performance, event-driven file monitoring. You must have `inotify-tools` installed.

On Debian-based distributions (like Ubuntu), you can install it with:
```sh
sudo apt-get install -y inotify-tools
```
This ensures that file watching has a negligible impact on CPU performance, even on very large projects.

## Technical Architecture

PHPUnit Hub uses a modern, event-driven architecture to provide real-time test feedback:

### Real-Time Event Streaming

The core of PHPUnit Hub's real-time capabilities is the `PhpUnitHubExtension` - a custom PHPUnit extension that hooks into PHPUnit's event system:

1. **PHPUnit Events**: The extension subscribes to PHPUnit's native events (test started, passed, failed, etc.)
2. **Event Streaming**: Each event is immediately serialized to JSON and written to STDERR
3. **Backend Processing**: The ReactPHP-based backend reads the event stream in real-time
4. **WebSocket Broadcasting**: Events are instantly broadcast to all connected browsers via WebSockets
5. **Progressive UI Updates**: The Vue.js frontend updates the results table as each test completes

### Key Components

- **PhpUnitHubExtension** (`src/PHPUnit/PhpUnitHubExtension.php`): PHPUnit extension that captures and streams test events
- **Router** (`src/Command/Router.php`): HTTP/WebSocket router that manages test execution and event broadcasting
- **StatusHandler** (`src/WebSocket/StatusHandler.php`): WebSocket handler for real-time communication with the frontend
- **TestRunner** (`src/TestRunner/TestRunner.php`): Spawns PHPUnit processes and captures their event streams
- **Frontend** (`public/index.html`): Vue.js SPA that displays test results in real-time

### Benefits of Event-Based Architecture

- **No Temporary Files**: Events stream directly from PHPUnit to the browser
- **Instant Feedback**: See test results as they happen, not after completion
- **Minimal Overhead**: Event serialization adds negligible performance impact
- **Scalable**: Handles large test suites efficiently without buffering all results

## Contributing

Contributions are welcome! Whether it's a bug report, a new feature, or a documentation improvement, your help is appreciated. Please follow these steps to contribute:

1.  **Fork the Repository**:
    Create your own fork of the project on GitHub.

2.  **Create a Branch**:
    Create a new branch for your feature or bug fix.
    ```sh
    git checkout -b feature/my-awesome-feature
    ```

3.  **Make Your Changes**:
    Implement your changes and additions.

4.  **Run Tests**:
    Ensure that the existing test suite passes with your changes.
    ```sh
    # Install development dependencies
    composer install

    # Run the test suite
    vendor/bin/phpunit
    ```

5.  **Commit and Push**:
    Commit your changes with a clear message and push them to your forked repository.
    ```sh
    git commit -am "feat: Add my awesome feature"
    git push origin feature/my-awesome-feature
    ```

6.  **Create a Pull Request**:
    Open a pull request from your branch to the main repository's `main` branch. Provide a clear title and description of your changes.

## License

This project is open-source software licensed under the **MIT License**. See the [LICENSE](LICENSE) file for more details.

---

Copyright (c) 2025 - Raffaele Carelle
