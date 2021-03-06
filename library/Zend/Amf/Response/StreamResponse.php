<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @package   Zend_Amf
 */

namespace Zend\Amf\Response;

use Zend\Amf;
use Zend\Amf\Parser;
use Zend\Amf\Parser\Amf0;

/**
 * Handles converting the PHP object ready for response back into AMF
 *
 * @package    Zend_Amf
 */
class StreamResponse implements ResponseInterface
{
    /**
     * @var int Object encoding for response
     */
    protected $_objectEncoding = 0;

    /**
     * Array of Zend_Amf_Value_MessageBody objects
     * @var array
     */
    protected $_bodies = array();

    /**
     * Array of Zend_Amf_Value_MessageHeader objects
     * @var array
     */
    protected $_headers = array();

    /**
     * @var \Zend\Amf\Parser\OutputStream
     */
    protected $_outputStream;

    /**
     * Instantiate new output stream and start serialization
     *
     * @return \Zend\Amf\Response\StreamResponse
     */
    public function finalize()
    {
        $this->_outputStream = new Parser\OutputStream();
        $this->writeMessage($this->_outputStream);
        return $this;
    }

    /**
     * Serialize the PHP data types back into Actionscript and
     * create and AMF stream.
     *
     * @param  \Zend\Amf\Parser\OutputStream $stream
     * @return \Zend\Amf\Response\StreamResponse
     */
    public function writeMessage(Parser\OutputStream $stream)
    {
        $objectEncoding = $this->_objectEncoding;

        //Write encoding to start of stream. Preamble byte is written of two byte Unsigned Short
        $stream->writeByte(0x00);
        $stream->writeByte($objectEncoding);

        // Loop through the AMF Headers that need to be returned.
        $headerCount = count($this->_headers);
        $stream->writeInt($headerCount);
        foreach ($this->getAmfHeaders() as $header) {
            $serializer = new Amf0\Serializer($stream);
            $stream->writeUTF($header->name);
            $stream->writeByte($header->mustRead);
            $stream->writeLong(Amf\Constants::UNKNOWN_CONTENT_LENGTH);
            if (is_object($header->data)) {
                // Workaround for PHP5 with E_STRICT enabled complaining about
                // "Only variables should be passed by reference"
                $placeholder = null;
                $serializer->writeTypeMarker($placeholder, null, $header->data);
            } else {
                $serializer->writeTypeMarker($header->data);
            }
        }

        // loop through the AMF bodies that need to be returned.
        $bodyCount = count($this->_bodies);
        $stream->writeInt($bodyCount);
        foreach ($this->_bodies as $body) {
            $serializer = new Amf0\Serializer($stream);
            $stream->writeUTF($body->getTargetURI());
            $stream->writeUTF($body->getResponseURI());
            $stream->writeLong(Amf\Constants::UNKNOWN_CONTENT_LENGTH);
            $bodyData   = $body->getData();
            $markerType = ($this->_objectEncoding == Amf\Constants::AMF0_OBJECT_ENCODING) ? null : Amf\Constants::AMF0_AMF3;
            if (is_object($bodyData)) {
                // Workaround for PHP5 with E_STRICT enabled complaining about
                // "Only variables should be passed by reference"
                $placeholder = null;
                $serializer->writeTypeMarker($placeholder, $markerType, $bodyData);
            } else {
                $serializer->writeTypeMarker($bodyData, $markerType);
            }
        }

        return $this;
    }

    /**
     * Return the output stream content
     *
     * @return string The contents of the output stream
     */
    public function getResponse()
    {
        return $this->_outputStream->getStream();
    }

    /**
     * Return the output stream content
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getResponse();
    }

    /**
     * Add an AMF body to be sent to the Flash Player
     *
     * @param  \Zend\Amf\Value\MessageBody $body
     * @return \Zend\Amf\Response\StreamResponse
     */
    public function addAmfBody(Amf\Value\MessageBody $body)
    {
        $this->_bodies[] = $body;
        return $this;
    }

    /**
     * Return an array of AMF bodies to be serialized
     *
     * @return array
     */
    public function getAmfBodies()
    {
        return $this->_bodies;
    }

    /**
     * Add an AMF Header to be sent back to the flash player
     *
     * @param  \Zend\Amf\Value\MessageHeader $header
     * @return \Zend\Amf\Response\StreamResponse
     */
    public function addAmfHeader(Amf\Value\MessageHeader $header)
    {
        $this->_headers[] = $header;
        return $this;
    }

    /**
     * Retrieve attached AMF message headers
     *
     * @return array Array of \Zend\Amf\Value\MessageHeader objects
     */
    public function getAmfHeaders()
    {
        return $this->_headers;
    }

    /**
     * Set the AMF encoding that will be used for serialization
     *
     * @param  int $encoding
     * @return \Zend\Amf\Response\StreamResponse
     */
    public function setObjectEncoding($encoding)
    {
        $this->_objectEncoding = $encoding;
        return $this;
    }
}
