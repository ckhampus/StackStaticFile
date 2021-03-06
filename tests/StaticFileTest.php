<?php

namespace Hampus\Tests\Stack;

use Hampus\Stack\StaticFile;
use Stack\CallableHttpKernel;
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
        $this->assertContains('php', $response->getContent());
    }

    public function test404ForKnowRoute()
    {
        $app = $this->getApp(array(
            'urls' => array('/static'),
            'root' => __DIR__,
        ));

        $request = Request::create("/static/foo");
        $response = $app->handle($request);

        $this->assertEquals(404, $response->getStatusCode());
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
            'urls' => array(''),
            'root' => __DIR__ . '/static',
            'index' => 'index.html'
        ));

        $request = Request::create("/");
        $response = $app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('index!', $response->getContent());

        $request = Request::create("/other/");
        $response = $app->handle($request);

        $this->assertEquals(404, $response->getStatusCode());

        $request = Request::create("/another/");
        $response = $app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('another index!', $response->getContent());
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
        $this->assertContains('php', $response->getContent());
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

    /**
     * @dataProvider httpHeaderProvider
     */
    public function testHeaderRules($options, $path, $expected, $mime)
    {
        $app = $this->getApp($options);

        $request = Request::create($path);
        $response = $app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($expected, $response->getMaxAge());
        $this->assertContains($mime, $response->headers->get('Content-Type'));
    }

    public function httpHeaderProvider()
    {
        $options = array(
            'urls' => array('/static'),
            'root' => __DIR__,
            'header_rules' => array(
                'all' => array('Cache-Control' => 'public, max-age=100'),
                'fonts' => array('Cache-Control' => 'public, max-age=200'),
                '/static/assets/images/' => array('Cache-Control' => 'public, max-age=300'),
                'static/assets/javascripts' => array('Cache-Control' => 'public, max-age=400'),
                '/\.(css|erb)\z/' => array('Cache-Control' => 'public, max-age=500'),
            ),
        );

        return array(
            array($options, '/static/assets/index.html', 100, 'text/html'),
            array($options, '/static/assets/fonts/font.eot', 200, 'application/vnd.ms-fontobject'),
            array($options, '/static/assets/images/image.png', 300, 'image/png'),
            array($options, '/static/assets/javascripts/app.js', 400, 'application/javascript'),
            array($options, '/static/assets/stylesheets/app.css', 500, 'text/css'),
        );
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
