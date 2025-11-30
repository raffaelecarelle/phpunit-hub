<?php

namespace PhpUnitHub\Http;

use Ratchet\Http\HttpServer;
use Ratchet\Http\HttpServerInterface;


/**
 * Decorator for HttpServer that allows increasing the maximum POST body size.
 * This is useful when handling larger POST requests that exceed the default limit.
 */
class DecoratedHttpServer extends HttpServer
{
    public function __construct(HttpServerInterface $component, int $maxSize = 4096)
    {
        parent::__construct($component);
        $this->_reqParser->maxSize = $maxSize;
    }
}
