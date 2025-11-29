<?php

namespace PhpUnitHub\Http;

use Ratchet\Http\HttpServer;
use Ratchet\Http\HttpServerInterface;

class CustomHttpServer extends HttpServer
{
    public function __construct(HttpServerInterface $component, int $maxSize = 4096)
    {
        parent::__construct($component);
        $this->_reqParser->maxSize = $maxSize;
    }
}
