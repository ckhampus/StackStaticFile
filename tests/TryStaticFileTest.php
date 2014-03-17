<?php

namespace Hampus\Tests\Stack;

use Hampus\Stack\TryStaticFile;
use Stack\CallableHttpKernel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class TryStaticFileTest extends \PHPUnit_Framework_TestCase
{
    public function testCallNextStackWhenFileNotFound()
    {
        $app = $this->getApp(array(
            'try' => array('html')
        ));

        $request = Request::create("/documents");
        $response = $app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Hello World!', $response->getContent());
    }

    public function testServeFirstFound()
    {
        $app = $this->getApp(array(
            'try' => array('.html', '/index.html', '/index.htm')
        ));

        $request = Request::create("/documents");
        $response = $app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('index.html', trim($response->getContent()));
    }

    public function testServeExisting()
    {
        $app = $this->getApp(array(
            'try' => array('/index.html')
        ));

        $request = Request::create('/documents/existing.html');
        $response = $app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('existing.html', trim($response->getContent()));
    }

    protected function getApp(array $options  = array())
    {
        $app = new CallableHttpKernel(function (Request $request) {
            return new Response('Hello World!');
        });

        $app = new TryStaticFile($app, array_merge(array(
            'urls' => array('/'),
            'root' => __DIR__,
        ), $options));

        return $app;
    }
}
