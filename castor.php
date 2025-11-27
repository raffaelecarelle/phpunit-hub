<?php

declare(strict_types=1);

use function Castor\exit_code;
use function Castor\load_dot_env;

#[\Castor\Attribute\AsTask]
function install(): int
{
    return exit_code(dockerize('composer instal'));

}

#[\Castor\Attribute\AsTask]
function phpunit(): int
{
    return exit_code(dockerize('vendor/bin/phpunit'));

}

#[\Castor\Attribute\AsTask]
function jest(): int
{
    return exit_code(dockerize('npm test'));

}

#[\Castor\Attribute\AsTask]

function phpcs_fix(): int
{
    return exit_code(dockerize('vendor/bin/php-cs-fixer fix'));

}

#[\Castor\Attribute\AsTask]

function phpstan(): int
{
    return exit_code(dockerize('vendor/bin/phpstan --memory-limit=-1'));

}

#[\Castor\Attribute\AsTask]
function rector(): int
{
        return exit_code(dockerize('vendor/bin/rector'));
}

#[\Castor\Attribute\AsTask]
function pre_commit(): int
{
    return phpstan() + rector() + phpcs_fix() + phpunit() + jest();
}

function dockerize(string $command): string
{
    load_dot_env();

    $isDockerized = $_SERVER['DOCKER_ON'] === 'true' || $_SERVER['DOCKER_ON'] === '1';

    if ($isDockerized) {
        return 'docker compose run --rm phpunit-hub ' . $command;
    }

    return $command;
}