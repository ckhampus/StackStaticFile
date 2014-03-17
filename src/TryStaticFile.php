<?php

namespace Hampus\Stack;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class TryStaticFile implements HttpKernelInterface
{
    protected $app;

    public function __construct(HttpKernelInterface $app, array $options = array())
    {
        $this->app = $app;

        $this->try = array_merge(array('') ,isset($options['try']) ? $options['try'] : array());

        $this->staticFile = new StaticFile($app, $options);
    }

    public function handle(Request $request, $type = HttpKernelInterface::MASTER_REQUEST, $catch = true)
    {
        $origPath = $request->getPathInfo();

        foreach ($this->try as $path) {
            $server = array_merge($request->server->all(), array('REQUEST_URI' => $origPath . $path));
            $request = $request->duplicate(null, null, null, null, null, $server);
            $response = $this->staticFile->handle($request, $type, $catch);
            if (!$response->isNotFound()) break;
        }

        if ($response->isNotFound()) {
            $server = array_merge($request->server->all(), array('REQUEST_URI' => $origPath));
            $request = $request->duplicate(null, null, null, null, null, $server);
            $response = $this->app->handle($request, $type, $catch);
        }

        return $response;
    }
}
