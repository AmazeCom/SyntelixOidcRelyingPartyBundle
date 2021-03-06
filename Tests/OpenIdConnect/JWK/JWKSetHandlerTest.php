<?php

namespace Syntelix\Bundle\OidcRelyingPartyBundle\OpenIdConnect\Tests\JWK;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Syntelix\Bundle\OidcRelyingPartyBundle\OpenIdConnect\JWK\JWKSetHandler;
use Syntelix\Bundle\OidcRelyingPartyBundle\Tests\Mocks\HttpClientMock;
use Buzz\Message\RequestInterface;

/**
 * JWKSetHandler.
 *
 * @author valérian Girard <valerian.girard@educagri.fr>
 */
class JWKSetHandlerTest extends TestCase
{
    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        self::clearCache();
    }

    public static function tearDownAfterClass()
    {
        parent::tearDownAfterClass();
        self::clearCache();
    }

    private static function clearCache()
    {
        $folder = sys_get_temp_dir().'/syntelix/OIC/jwk-cache/';

        $fs = new Filesystem();

        if (is_file($folder.'op.jwk')) {
            unlink($folder.'op.jwk');
        }

        $fs->remove(sys_get_temp_dir().'/syntelix');
    }

    public function testGetJwkShouldReturnFalse()
    {
        $httpClient = new HttpClientMock();
        $jWKSetHandler = new JWKSetHandler(null, 1, '', $httpClient);

        $this->assertFalse($jWKSetHandler->getJwk());
    }

    public function testGetJwk()
    {
        $expected = array('text' => 'some content');

        $httpClient = new HttpClientMock();
        $httpClient->setResponseContent(true,
                array(
                    'HTTP/1.1 200 OK',
                    'Content-Type: application/json',
                ),
                json_encode($expected));
        $jWKSetHandler = new JWKSetHandler('http://some.where', 1, sys_get_temp_dir(), $httpClient);

        $res = (array) $jWKSetHandler->getJwk();

        $this->assertEquals('http://some.where', $httpClient->getRequest()->getResource());
        $this->assertEquals(RequestInterface::METHOD_GET, $httpClient->getRequest()->getMethod());
        $this->assertEquals($expected, $res);
    }

    /**
     * @depends testGetJwk
     */
    public function testGetJwkCacheExist()
    {
        $expected = array('text' => 'some content');

        $httpClient = new HttpClientMock();
        $httpClient->setResponseContent(true,
                array(
                    'HTTP/1.1 200 OK',
                    'Content-Type: application/json',
                ),
                json_encode($expected));
        $jWKSetHandler = new JWKSetHandler('http://some.where', 30000, sys_get_temp_dir(), $httpClient);

        $res = (array) $jWKSetHandler->getJwk();

        $this->assertNull($httpClient->getRequest());
        $this->assertEquals($expected, $res);
    }
}
