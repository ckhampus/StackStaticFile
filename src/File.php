<?php

namespace Hampus\Stack;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Dflydev\Canal\Analyzer\Analyzer;

use DateTime;

class File implements HttpKernelInterface
{
    protected $app;

    protected $root;

    protected $headers;

    public function __construct(HttpKernelInterface $app, $root, array $headers = array())
    {
        $this->app = $app;
        $this->root = $root;
        $this->headers = $headers;
    }

    public function cleanPathInfo($path)
    {
        $parts = explode('/', $path);

        $clean = array();

        foreach ($parts as $part) {
            if (empty($part) || $part == '.') continue;
            $part === '..' ? array_pop($clean) : $clean[] = $part;
        }

        if (empty($parts[0])) {
            array_unshift($clean, '/');
        }

        return implode('/', $clean);
    }

    public function byteRanges(HeaderBag $headers, $size)
    {
        $httpRange = $headers->get('Range');

        if (!empty($httpRange) && preg_match('/bytes=([^;]+)/', $httpRange, $matches) === 1) {
            $ranges = array();

            foreach (preg_split('/,\s*/', $matches[1]) as $spec) {
                if (preg_match('/(\d*)-(\d*)/', $spec, $matches) === 0) return null;

                array_shift($matches);
                list($r0, $r1) = $matches;

                if ($r0 === '') {
                    if ($r1 === '') return null;

                    $r0 = $size - $r1;
                    $r0 = $r0 < 0 ? 0 : $r0;
                    $r1 = $size - 1;
                } else {
                    if ($r1 === '') {
                        $r1 = $size - 1;
                    } else {
                        if ($r1 < $r0) return null;

                        $r1 = $r1 >= $size ? $size - 1 : $r1;
                    }
                }

                if ($r0 <= $r1)
                    $ranges[] = array($r0, $r1);
            }

            return $ranges;
        }

        return null;
    }

    public function readRange($path, $start, $end)
    {
        return file_get_contents($path, false, null, $start, $end - $start + 1);
    }

    public function handle(Request $request, $type = HttpKernelInterface::MASTER_REQUEST, $catch = true)
    {
        $allowedVerbs = array('GET', 'HEAD', 'OPTIONS');
        $allowHeader = implode(', ', $allowedVerbs);

        $method = $request->getMethod();

        if (!in_array($method, $allowedVerbs)) {
            return $this->fail(Response::HTTP_METHOD_NOT_ALLOWED, null, array('Allow' => $allowHeader));
        }

        $pathInfo = urldecode($request->getPathInfo());
        $pathInfo = $this->cleanPathInfo($pathInfo);
        $path = realpath(implode('/', array($this->root, $pathInfo)));

        if (strpos($path, $this->root) === 0 && file_exists($path) && is_readable($path) && !is_dir($path)) {
            if ($method === 'OPTIONS') {
                return new Response('', Response::HTTP_OK, array('Allow' => $allowHeader, 'Content-Length' => 0));
            }

            $response = new Response();
            $response->setEtag(sha1_file($path));
            $response->setLastModified(DateTime::createFromFormat('U', filemtime($path)));
            $response->setPublic();

            $analyzer = new Analyzer;
            $mimeType = $analyzer->detectFromFilename($path)->asString();
            $response->headers->set('Content-Type', $mimeType);

            // Set custom headers
            $response->headers->add($this->headers);

            $size = filesize($path);

            $ranges = $this->byteRanges($request->headers, $size);

            if ($ranges === null || count($ranges) > 1) {
                $start = 0;
                $end = $size - 1;
            } elseif (empty($ranges)) {
                $response = $this->fail(Response::HTTP_REQUESTED_RANGE_NOT_SATISFIABLE);
                $response->headers->set('Content-Range', "bytes */{$size}");

                return $response;
            } else {
                list($start, $end) = $ranges[0];

                $response->setStatusCode(Response::HTTP_PARTIAL_CONTENT);
                $response->headers->set('Content-Range', "bytes {$start}-{$end}/{$size}");
                $size = $end - $start + 1;
            }

            $response->headers->set('Content-Length', $size);

            if (!$response->isNotModified($request)) {
                $response->setContent($this->readRange($path, $start, $end));
                $response->prepare($request);
            }

            return $response;
        }

        return $this->fail(Response::HTTP_NOT_FOUND, "File not found: {$pathInfo}");
    }

    public function fail($code, $message = null, array $headers = array())
    {
        if ($message === null) {
            $message = Response::$statusTexts[$code];
        }

        return new Response($message, $code, $headers);
    }
}
