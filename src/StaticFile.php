<?php

namespace Hampus\Stack;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class StaticFile implements HttpKernelInterface
{
    protected $app;

    protected $urls;

    protected $index;

    protected $root;

    protected $disable404;

    protected $headerRules;

    protected $fileServer;

    public function __construct(HttpKernelInterface $app, array $options = array())
    {
        $this->app = $app;
        $this->urls = isset($options['urls']) ? $options['urls'] : array('/favicon.ico');
        $this->index = isset($options['index']) ? $options['index'] : '';
        $this->root = isset($options['root']) ? $options['root'] : '';
        $this->disable404 = isset($options['disable_404']) ? $options['disable_404'] : false;

        $this->headerRules = isset($options['header_rules']) ? $options['header_rules'] : array();

        $this->fileServer = new File($app, $this->root);

        // $this->fileProvider = isset($options['file_provider']) && is_callable($options['file_provider']) ? $options['file_provider'] : $this->defaultFileProvider();
    }

    public function overwriteFilePath($path)
    {
        return array_key_exists($path, $this->urls) || (!empty($this->index) && preg_match('/\/$/', $path) === 1);
    }

    public function routeFile($path)
    {
        return count(array_filter($this->urls, function ($url) use ($path) { return !empty($url) && strpos($path, $url) === 0; })) > 0;
    }

    public function canServe($path)
    {
        return $this->routeFile($path) || $this->overwriteFilePath($path);
    }

    public function applicationRules($path)
    {
        $newHeaders = array();

        foreach ($this->headerRules as $rule => $headers) {
            $keep = ($rule == 'all') ||
                    ($rule == 'fonts' && preg_match('/\.(?:ttf|otf|eot|woff|svg)\z/', $path) === 1) ||
                    (strpos($path, $rule) === 0 || strpos($path, '/'.$rule) === 0) ||
                    // (is_array($rule) && preg_match('/\.('.implode('|', $rule).')\z/', $path) === 1) ||
                    (@preg_match($rule, $path) === 1);

            if ($keep) {
                $newHeaders[$rule] = $headers;
            }
        }

        return $newHeaders;
    }

    public function handle(Request $request, $type = HttpKernelInterface::MASTER_REQUEST, $catch = true)
    {
        $path = $request->getPathInfo();

        if ($this->canServe($path)) {
            if ($this->overwriteFilePath($path)) {
                $path = preg_match('/\/$/', $path) === 1 ? $path . $this->index : $this->urls[$path];
                $server = array_merge($request->server->all(), array('REQUEST_URI' => $path));
                $request = $request->duplicate(null, null, null, null, null, $server);
            }

            $response = $this->fileServer->handle($request, $type, $catch);

            if ($response->isSuccessful()) {
                foreach ($this->applicationRules($path) as $rule => $newHeaders) {
                    foreach ($newHeaders as $field => $content) {
                        $response->headers->set($field, $content);
                    }
                }

                return $response;
            }

            if (!$this->disable404) {
                return $response;
            }

        }

        return $this->app->handle($request, $type, $catch);
    }
}
