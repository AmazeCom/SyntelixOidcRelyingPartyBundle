<?php

namespace Syntelix\Bundle\OidcRelyingPartyBundle\OpenIdConnect\JWK;

use Buzz\Client\AbstractCurl;
use Buzz\Message\Request as HttpClientRequest;
use Buzz\Message\Response as HttpClientResponse;
use Buzz\Message\RequestInterface;
use DateInterval;
use DateTime;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;

/**
 * JWKSetHandler.
 *
 *
 * @author valérian Girard <valerian.girard@educagri.fr>
 */
class JWKSetHandler
{
    /**
     * @var string
     */
    private $jwkUrl;

    /**
     * @var int
     */
    private $jwkCacheTtl;

    /**
     * @var string
     */
    private $cacheDir;

    /**
     * @var AbstractCurl
     */
    private $httpClient;

    /**
     * @var string
     */
    private $jwkFileName = 'op.jwk';

    /**
     * @var string
     */
    private $jwkFileFolder = '/syntelix/OIC/jwk-cache/';

    /**
     * JWKSetHandler constructor.
     *
     * @param $jwkUrl
     * @param $jwkCacheTtl
     * @param $cacheDir
     * @param AbstractCurl $httpClient
     */
    public function __construct($jwkUrl, $jwkCacheTtl, $cacheDir, AbstractCurl $httpClient)
    {
        $this->jwkUrl = $jwkUrl;
        $this->jwkCacheTtl = $jwkCacheTtl;
        $this->cacheDir = $cacheDir;
        $this->httpClient = $httpClient;
    }

    /**
     * @param null $jku
     *
     * @return bool|mixed|string
     */
    public function getJwk($jku = null)
    {
        if (null === $jku && null === $this->jwkUrl) {
            return false;
        } elseif (null === $jku && null !== $this->jwkUrl) {
            $jku = $this->jwkUrl;
        }

        $this->refreshCache($jku);

        $content = file_get_contents($this->cacheDir.$this->jwkFileFolder.$this->jwkFileName);

        $jsonDecode = new JsonDecode();
        $content = $jsonDecode->decode($content, JsonEncoder::FORMAT);

        return $content;
    }

    /**
     * @param $url
     */
    private function refreshCache($url)
    {
        $fs = new Filesystem();

        $this->jwkFileName = md5($url);

        if (!$fs->exists($this->cacheDir.$this->jwkFileFolder.$this->jwkFileName)) {
            $fs->mkdir($this->cacheDir.$this->jwkFileFolder);
            $this->makeCache();

            return;
        }

        $finder = new Finder();
        $files = $finder->files()->in($this->cacheDir.$this->jwkFileFolder)
                ->name($this->jwkFileName);

        $needToBeUpdate = false;

        $now = new DateTime('Now');

        /* @var $file SplFileInfo */
        foreach ($files as $file) {
            $ctime = new DateTime();
            $ctime->setTimestamp($file->getCTime());
            $ctime->add(new DateInterval(sprintf('PT%dS', $this->jwkCacheTtl)));

            $needToBeUpdate |= $ctime < $now;
        }

        if (true === (bool) $needToBeUpdate) {
            $this->makeCache();

            return;
        }

        return;
    }

    private function makeCache()
    {
        $request = new HttpClientRequest(RequestInterface::METHOD_GET, $this->jwkUrl);
        $response = new HttpClientResponse();
        $this->httpClient->send($request, $response);

        if ($response->isOk()) {
            file_put_contents($this->cacheDir.$this->jwkFileFolder.$this->jwkFileName, $response->getContent());
        }
    }
}
