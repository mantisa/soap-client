<?php

namespace Phpro\SoapClient\Soap\HttpBinding\Builder;

use Interop\Http\Factory\RequestFactoryInterface;
use Interop\Http\Factory\StreamFactoryInterface;
use Phpro\SoapClient\Exception\RequestException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Class Psr7RequestBuilder
 *
 * @package Phpro\SoapClient\Soap\HttpBinding\Builder
 * @link https://github.com/meng-tian/soap-http-binding
 */
class Psr7RequestBuilder
{
    const SOAP11 = '1.1';
    const SOAP12 = '1.2';

    /**
     * @var string
     */
    private $endpoint;

    /**
     * @var string
     */
    private $soapVersion = self::SOAP11;

    /**
     * @var string
     */
    private $soapAction = '';

    /**
     * @var StreamInterface
     */
    private $soapMessage;

    /**
     * @var bool
     */
    private $hasSoapMessage = false;

    /**
     * @var string
     */
    private $httpMethod = 'POST';

    /**
     * @var RequestFactoryInterface
     */
    private $requestFactory;
    /**
     * @var StreamFactoryInterface
     */
    private $streamFactory;

    /**
     * Psr7RequestBuilder constructor.
     *
     * @param RequestFactoryInterface $requestFactory
     * @param StreamFactoryInterface  $streamFactory
     */
    public function __construct(RequestFactoryInterface $requestFactory, StreamFactoryInterface $streamFactory)
    {
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
    }

    /**
     * @return RequestInterface
     * @throws RequestException
     */
    public function getHttpRequest()
    {
        $this->validate();

        try {
            $request = $this->requestFactory->createRequest($this->httpMethod, $this->endpoint);
            $request->withBody($this->prepareMessage());
            foreach ($this->prepareHeaders() as $name => $value) {
                $request->withHeader($name, $value);
            }
        } catch (\InvalidArgumentException $e) {
            throw new RequestException($e->getMessage(), $e->getCode(), $e);
        }

        return $request;
    }

    /**
     * @param string $endpoint
     */
    public function setEndpoint($endpoint)
    {
        $this->endpoint = $endpoint;
    }

    /**
     * Mark as SOAP 1.1
     */
    public function isSOAP11()
    {
        $this->soapVersion = self::SOAP11;
    }

    /**
     * Mark as SOAP 1.2
     */
    public function isSOAP12()
    {
        $this->soapVersion = self::SOAP12;
    }


    /**
     * @param string $soapAction
     */
    public function setSoapAction($soapAction)
    {
        $this->soapAction = $soapAction;
    }

    /**
     * @param string $content
     */
    public function setSoapMessage($content)
    {
        $this->soapMessage = $this->streamFactory->createStream($content);
        $this->hasSoapMessage = true;
    }

    /**
     * @param string $method
     */
    public function setHttpMethod($method)
    {
        $this->httpMethod = $method;
    }

    /**
     * @return void
     * @throws \Phpro\SoapClient\Exception\RequestException
     */
    private function validate()
    {
        $isValid = true;

        if (!$this->endpoint) {
            $isValid = false;
        }

        if (!$this->hasSoapMessage && $this->httpMethod === 'POST') {
            $isValid = false;
        }

        /**
         * SOAP 1.1 only defines HTTP binding with POST method.
         * @link https://www.w3.org/TR/2000/NOTE-SOAP-20000508/#_Toc478383527
         */
        if ($this->soapVersion == self::SOAP11 && $this->httpMethod !== 'POST') {
            $isValid = false;
        }

        /**
         * SOAP 1.2 only defines HTTP binding with POST and GET methods.
         * @link https://www.w3.org/TR/2007/REC-soap12-part0-20070427/#L10309
         */
        if ($this->soapVersion == self::SOAP12 && !in_array($this->httpMethod, ['GET', 'POST'])) {
            $isValid = false;
        }

        if (!$isValid) {
            $this->unsetAll();
            throw new RequestException;
        }
    }

    /**
     * @return array
     */
    private function prepareHeaders()
    {
        if ($this->soapVersion === self::SOAP11) {
            return $this->prepareSoap11Headers();
        }

        return $this->prepareSoap12Headers();
    }

    /**
     * @link https://www.w3.org/TR/2000/NOTE-SOAP-20000508/#_Toc478383526
     * @return array
     */
    private function prepareSoap11Headers()
    {
        $headers = [];
        $headers['Content-Length'] = $this->soapMessage->getSize();
        $headers['SOAPAction'] = $this->soapAction;
        $headers['Content-Type'] = 'text/xml; charset="utf-8"';

        return $headers;
    }

    /**
     * SOSPAction header is removed in SOAP 1.2 and now expressed as a value of
     * an (optional) "action" parameter of the "application/soap+xml" media type.
     * @link https://www.w3.org/TR/sodap12-part0/#L4697
     * @return array
     */
    private function prepareSoap12Headers()
    {
        $headers = [];
        if ($this->httpMethod !== 'POST') {
            $headers['Accept'] = 'application/soap+xml';
            return $headers;
        }

        $headers['Content-Length'] = $this->soapMessage->getSize();
        $headers['Content-Type'] = 'application/soap+xml; charset="utf-8"' . '; action="' . $this->soapAction . '"';

        return $headers;
    }

    /**
     * @return StreamInterface
     */
    private function prepareMessage()
    {
        if ($this->httpMethod === 'POST') {
            return $this->soapMessage;
        }

        return $this->streamFactory->createStream('');
    }
}
