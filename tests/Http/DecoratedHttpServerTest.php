<?php

namespace PhpUnitHub\Tests\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use PhpUnitHub\Http\DecoratedHttpServer;
use Ratchet\Http\HttpServerInterface;
use ReflectionProperty;

#[CoversClass(DecoratedHttpServer::class)]
class DecoratedHttpServerTest extends TestCase
{
    public function testConstructorSetsMaxSize(): void
    {
        $httpServer = $this->createMock(HttpServerInterface::class);
        $decoratedHttpServer = new DecoratedHttpServer($httpServer, 8192);

        $reflectionProperty = new ReflectionProperty(DecoratedHttpServer::class, '_reqParser');
        $reqParser = $reflectionProperty->getValue($decoratedHttpServer);

        $this->assertEquals(8192, $reqParser->maxSize);
    }
}
