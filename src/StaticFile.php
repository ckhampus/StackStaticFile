<?php

namespace Hampus\Stack;

use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class StaticFile implements HttpKernelInterface
{
    protected $app;

    protected $urls;

    protected $index;

    protected $root;

    protected $headerRules;

    protected $fileProvider;

    public function __construct(HttpKernelInterface $app, array $options = array())
    {
        $this->app = $app;
        $this->urls = isset($options['urls']) ? $options['urls'] : array('/favicon.ico');
        $this->index = isset($options['index']) ? $options['index'] : '';
        $this->root = isset($options['root']) ? $options['root'] : '';

        $this->headerRules = isset($options['header_rules']) ? $options['header_rules'] : array();

        $this->fileProvider = isset($options['file_provider']) && is_callable($options['file_provider']) ? $options['file_provider'] : $this->defaultFileProvider();
    }

    public function defaultFileProvider()
    {
        return function ($root, $path) {
            $path = realpath(implode('/', array($root, $path)));

            try {
                $file = new File($path, true);
                $content = file_get_contents($path);

                $response = new Response($content, Response::HTTP_OK);
                $response->setLastModified(\DateTime::createFromFormat('U', $file->getMTime()));
                $response->setEtag(sha1_file($file->getPathname()));

                if (!$response->headers->has('Content-Type')) {
                    $response->headers->set('Content-Type', $file->getMimeType() ?: 'application/octet-stream');
                }

                return $response;
            } catch (FileNotFoundException $e) { }

            return false;
        };
    }

    public function overwriteFilePath($path)
    {
        return array_key_exists($path, $this->urls) || (!empty($this->index) && preg_match('/\/$/', $path) === 1);
    }

    public function routeFile($path)
    {
        return count(array_filter($this->urls, function ($url) use ($path) { return strpos($path, $url) === 0; })) > 0;
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
            }

            $response = call_user_func_array($this->fileProvider, array($this->root, $path));

            if ($response !== false) {
                foreach ($this->applicationRules($path) as $rule => $newHeaders) {
                    foreach ($newHeaders as $field => $content) {
                        $response->headers->set($field, $content);
                    }
                }

                return $response;
            }

            throw new NotFoundHttpException();
        }

        return $this->app->handle($request, $type, $catch);
    }
}
