<?php

namespace Hampus\Tests\Stack;

use Hampus\Stack\File;
use Stack\CallableHttpKernel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Response;
use DateTime;

class FileTest extends \PHPUnit_Framework_TestCase
{
    public function testServeFiles()
    {
        $app = $this->getApp();

        $request = Request::create("/static/test");
        $response = $app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertContains('php', $response->getContent());
    }

    public function testLastModifiedHeader()
    {
        $app = $this->getApp();

        $request = Request::create("/static/test");
        $response = $app->handle($request);

        $path = realpath(implode('/', array(__DIR__, '/static/test')));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(DateTime::createFromFormat('U', filemtime($path)), $response->getLastModified());
    }

    public function testIfNotModifiedSince()
    {
        $app = $this->getApp();

        $path = realpath(implode('/', array(__DIR__, '/static/test')));
        $date = DateTime::createFromFormat('U', filemtime($path));

        $request = Request::create("/static/test");
        $request->headers->set('If-Modified-Since', $date->format('D, d M Y H:i:s').' GMT');
        $response = $app->handle($request);

        $this->assertEquals(304, $response->getStatusCode());
        $this->assertEmpty($response->getContent());
    }

    public function testIfModifiedSince()
    {
        $app = $this->getApp();

        $path = realpath(implode('/', array(__DIR__, '/static/test')));
        $date = DateTime::createFromFormat('U', filemtime($path) - 100);

        $request = Request::create("/static/test");
        $request->headers->set('If-Modified-Since', $date->format('D, d M Y H:i:s').' GMT');
        $response = $app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testUrlEncodedFilenames()
    {
        $app = $this->getApp();

        $request = Request::create("/static/%74%65%73%74");
        $response = $app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertContains('php', $response->getContent());
    }

    public function testSafeDirectoryTraversal()
    {
        $app = $this->getApp();

        $request = Request::create("/static/../static/test");
        $response = $app->handle($request);
        $this->assertEquals(200, $response->getStatusCode());

        $request = Request::create(".");
        $response = $app->handle($request);
        $this->assertEquals(404, $response->getStatusCode());

        $request = Request::create("test/..");
        $response = $app->handle($request);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testUnsafeDirectoryTraversal()
    {
        $app = $this->getApp();

        $request = Request::create("/../README.md");
        $response = $app->handle($request);
        $this->assertTrue($response->isClientError());

        $request = Request::create("/../tests/StaticFileTest.php");
        $response = $app->handle($request);
        $this->assertTrue($response->isClientError());

        $request = Request::create("../README.md");
        $response = $app->handle($request);
        $this->assertTrue($response->isClientError());
    }

    public function testAllowFilesWithDotDot()
    {
        $app = $this->getApp();

        $request = Request::create("/static/..test");
        $response = $app->handle($request);
        $this->assertTrue($response->isNotFound());

        $request = Request::create("/static/test..");
        $response = $app->handle($request);
        $this->assertTrue($response->isNotFound());

        $request = Request::create("/static../test..");
        $response = $app->handle($request);
        $this->assertTrue($response->isNotFound());
    }

    public function testUnsafeDirectoryTraversalWithEncodedPeriods()
    {
        $app = $this->getApp();

        $request = Request::create("/%2E%2E/README.md");
        $response = $app->handle($request);
        $this->assertTrue($response->isClientError());
        $this->assertTrue($response->isNotFound());
    }

    public function testFileNotFound()
    {
        $app = $this->getApp();

        $request = Request::create("/static/blubb");
        $response = $app->handle($request);
        $this->assertTrue($response->isNotFound());
    }

    public function testCorrectByteRangeInBody()
    {
        $app = $this->getApp();

        $request = Request::create("/static/test");
        $request->headers->set('Range', 'bytes=21-31');
        $response = $app->handle($request);

        $this->assertEquals(206, $response->getStatusCode());
        $this->assertEquals(11, $response->headers->get('Content-Length'));
        $this->assertEquals('bytes 21-31/61', $response->headers->get('Content-Range'));
        $this->assertEquals('-*- php -*-', $response->getContent());
    }

    public function testUnsatisfiableByteRange()
    {
        $app = $this->getApp();

        $request = Request::create("/static/test");
        $request->headers->set('Range', 'bytes=1234-5678');
        $response = $app->handle($request);

        $this->assertEquals(416, $response->getStatusCode());
        $this->assertEquals('bytes */61', $response->headers->get('Content-Range'));
    }

    public function testCustomHeaders()
    {
        $app = $this->getApp(array(
            'Cache-Control' => 'public, max-age=38',
            'Access-Control-Allow-Origin' => '*',
        ));

        $request = Request::create("/static/test");
        $response = $app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(38, $response->getMaxAge());
        $this->assertEquals('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    /**
     * @dataProvider verbsProvider
     */
    public function testAllowedVerbs($method, $successful)
    {
        $app = $this->getApp();

        $request = Request::create("/static/test", $method);
        $response = $app->handle($request);

        $this->assertEquals($successful, $response->isSuccessful());

        if (!$successful) {
            $this->assertEquals(Response::HTTP_METHOD_NOT_ALLOWED, $response->getStatusCode());
        }
    }

    public function testAllowHeader()
    {
        $app = $this->getApp();

        $request = Request::create("/static/test", 'OPTIONS');
        $response = $app->handle($request);

        $this->assertEquals(array('GET', 'HEAD', 'OPTIONS'), explode(', ', $response->headers->get('Allow')));
    }

    public function verbsProvider()
    {
        return array(
            array('POST', false),
            array('PUT', false),
            array('PATCH', false),
            array('DELETE', false),
            array('GET', true),
            array('HEAD', true),
            array('OPTIONS', true),
        );
    }

    /**
     * @dataProvider byteRangeProvider
     */
    public function testByteRanges($range, $size, $expected)
    {
        $app = $this->getMock('Symfony\Component\HttpKernel\HttpKernelInterface');
        $file = new File($app, __DIR__);
        $headers = new HeaderBag(array('Range' => $range));

        $ranges = $file->byteRanges($headers, $size);
        $this->assertEquals($expected, $ranges);
    }

    public function byteRangeProvider()
    {
        return array(
            // ignore missing or syntactically invalid byte ranges.
            array('foobar', 500, null),
            array('furlongs=123-456', 500, null),
            array('bytes=', 500, null),
            array('bytes=-', 500, null),
            array('bytes=123,456', 500, null),
            array('bytes=456-123', 500, null),
            array('bytes=456-455', 500, null),

            // parse simple byte ranges.
            array('bytes=123-456', 500, array(array(123, 456))),
            array('bytes=123-', 500, array(array(123, 499))),
            array('bytes=-100', 500, array(array(400, 499))),
            array('bytes=0-0', 500, array(array(0, 0))),
            array('bytes=499-499', 500, array(array(499, 499))),

            // parse several byte ranges.
            array('bytes=500-600,601-999', 1000, array(array(500, 600), array(601, 999))),

            // truncate byte ranges.
            array('bytes=123-999', 500, array(array(123, 499))),
            array('bytes=600-999', 500, array()),
            array('bytes=-999', 500, array(array(0, 499))),

            // ignore unsatisfiable byte ranges.
            array('bytes=500-501', 500, array()),
            array('bytes=500-', 500, array()),
            array('bytes=999-', 500, array()),
            array('bytes=-0', 500, array()),

            // handle byte ranges of empty files.
            array('bytes=bytes=123-456', 0, array()),
            array('bytes=bytes=0-', 0, array()),
            array('bytes=bytes=-100', 0, array()),
            array('bytes=bytes=0-0', 0, array()),
            array('bytes=bytes=-0', 0, array()),
        );
    }

    public function testHeadContentLength()
    {
        $app = $this->getApp();

        $request = Request::create("/static/test", 'HEAD');
        $response = $app->handle($request);

        $this->assertTrue($response->isSuccessful());
        $this->assertEquals('61', $response->headers->get('Content-Length'));
    }

    protected function getApp(array $headers = array())
    {
        $app = new CallableHttpKernel(function (Request $request) {
            return new Response('Hello World!');
        });

        $app = new File($app, __DIR__, $headers);

        return $app;
    }
}
