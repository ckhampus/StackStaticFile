<?php

namespace Hampus\Tests\Stack;

use Hampus\Stack\StaticFile;
use Stack\CallableHttpKernel;
use Symfony\Component\HttpKernel\Client;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class StaticFileTest extends \PHPUnit_Framework_TestCase
{
    public function testServeFiles()
    {
        $app = $this->getApp(array(
            'urls' => array('/static'),
            'root' => __DIR__,
        ));

        $request = Request::create("/static/test");
        $response = $app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('PHP', $response->getContent());
    }

    /**
     * @expectedException Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function test404ForKnowRoute()
    {
        $app = $this->getApp(array(
            'urls' => array('/static'),
            'root' => __DIR__,
        ));

        $request = Request::create("/static/foo");
        $response = $app->handle($request);
    }

    public function testFallbackToNextStack()
    {
        $app = $this->getApp(array(
            'urls' => array('/static'),
            'root' => __DIR__,
        ));

        $request = Request::create("/foo/bar");
        $response = $app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Hello World!', $response->getContent());
    }

    public function testCallsIndexFileWhenRequestingRoot()
    {
        $app = $this->getApp(array(
            'urls' => array(),
            'root' => __DIR__ . '/static',
            'index' => 'index.html'
        ));

        $request = Request::create("/");
        $response = $app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('index!', $response->getContent());

        try {
            $request = Request::create("/other/");
            $response = $app->handle($request);
        } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e) {
            $request = Request::create("/another/");
            $response = $app->handle($request);

            $this->assertEquals(200, $response->getStatusCode());
            $this->assertEquals('another index!', $response->getContent());

            return;
        }

        $this->fail('An expected exception of "Symfony\Component\HttpKernel\Exception\NotFoundHttpException" has not been raised.');
    }

    public function testServeHiddenFiles()
    {
        $app = $this->getApp(array(
            'urls' => array('/static/secret' => '/static/test'),
            'root' => __DIR__,
        ));

        $request = Request::create("/static/secret");
        $response = $app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('PHP', $response->getContent());
    }

    public function testFallbackToNextStackIfURINotSpecified()
    {
        $app = $this->getApp(array(
            'urls' => array('/static/secret' => '/static/test'),
            'root' => __DIR__,
        ));

        $request = Request::create("/foo/bar");
        $response = $app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Hello World!', $response->getContent());
    }

    protected function getApp(array $options  = array())
    {
        $app = new CallableHttpKernel(function (Request $request) {
            return new Response('Hello World!');
        });

        $app = new StaticFile($app, $options);

        return $app;
    }
}