<?php
namespace MneSabre\DAV;

class Server extends \Sabre\DAV\Server
{

    function checkPreconditions (\Sabre\HTTP\RequestInterface $request, \Sabre\HTTP\ResponseInterface $response)
    {
        try {
            $return = parent::checkPreconditions($request, $response);
        }
        catch (\Exception $e) {
            error_log($e->getMessage());
            $return = false;
        }
        return true;
    }

    function exec ()
    {
        try {
            
             //if ($_SERVER ["REQUEST_METHOD"] == "PUT") {
             //error_log ( 'Start Request ===========================================' );
             //foreach ( $_SERVER as $ii => $vv )
             //error_log ( sprintf ( "%s: %s\n", $ii, $vv ) );
             //foreach ( $_REQUEST as $ii => $vv )
             //error_log ( sprintf ( "%s: %s\n", $ii, $vv ) );
             //error_log ( 'End Request ===========================================' );
             //}
            
            //error_log($_SERVER["REQUEST_URI"].' '.$_SERVER["REQUEST_METHOD"]);
            
            $this->httpResponse->setHTTPVersion($this->httpRequest->getHTTPVersion());
            
            // Setting the base url
            $this->httpRequest->setBaseUrl($this->getBaseUri());
            $this->invokeMethod($this->httpRequest, $this->httpResponse);
            
            if ($_SERVER ["REQUEST_METHOD"] == "PUT") {
             error_log($this->httpResponse);
            }
        }
        catch (\Exception $e) {
            if (substr($e->getMessage(), 0, 8) != 'MneAuth:') {
                error_log("EXCEPTION: ------------------------------------------------------");
                error_log('Start Request ===========================================');
                foreach ($_SERVER as $ii => $vv)
                    error_log(sprintf("%s: %s\n", $ii, $vv));
                foreach ($_REQUEST as $ii => $vv)
                    error_log(sprintf("%s: %s\n", $ii, $vv));
                error_log('End Request ===========================================');
                error_log("EXCEPTION: " . $e->getMessage());
                error_log("EXCEPTION: " . $e->getFile() . ":" . $e->getLine());
                $trace = $e->getTrace();
                foreach ($trace as $ii => $vv) {
                    if (! isset($vv['class']))
                        $vv['class'] = '';
                    if (! isset($vv['function']))
                        $vv['function'] = '';
                    if (! isset($vv['file']))
                        $vv['file'] = '';
                    if (! isset($vv['line']))
                        $vv['line'] = '';
                    try {
                        error_log(sprintf("%s::%s at %s:%s", $vv['class'], $vv['function'], $vv['file'], $vv['line']));
                    }
                    catch (\Exception $ignore) {
                        error_log("EXCEPTION: " . $ignore->getMessage());
                    }
                }
                
                error_log("EXCEPTION END: --------------------------------------------------");
            }
            
            try {
                $this->emit('exception', [
                        $e
                ]);
            }
            catch (\Exception $ignore) {}
            $DOM = new \DOMDocument('1.0', 'utf-8');
            $DOM->formatOutput = true;
            
            $error = $DOM->createElementNS('DAV:', 'd:error');
            $error->setAttribute('xmlns:s', self::NS_SABREDAV);
            $DOM->appendChild($error);
            
            $h = function  ($v)
            {
                
                return htmlspecialchars($v, ENT_NOQUOTES, 'UTF-8');
            };
            
            if (self::$exposeVersion) {
                $error->appendChild($DOM->createElement('s:sabredav-version', $h(\Sabre\DAV\Version::VERSION)));
            }
            
            $error->appendChild($DOM->createElement('s:exception', $h(get_class($e))));
            $error->appendChild($DOM->createElement('s:message', $h($e->getMessage())));
            if ($this->debugExceptions) {
                $error->appendChild($DOM->createElement('s:file', $h($e->getFile())));
                $error->appendChild($DOM->createElement('s:line', $h($e->getLine())));
                $error->appendChild($DOM->createElement('s:code', $h($e->getCode())));
                $error->appendChild($DOM->createElement('s:stacktrace', $h($e->getTraceAsString())));
            }
            
            if ($this->debugExceptions) {
                $previous = $e;
                while ($previous = $previous->getPrevious()) {
                    $xPrevious = $DOM->createElement('s:previous-exception');
                    $xPrevious->appendChild($DOM->createElement('s:exception', $h(get_class($previous))));
                    $xPrevious->appendChild($DOM->createElement('s:message', $h($previous->getMessage())));
                    $xPrevious->appendChild($DOM->createElement('s:file', $h($previous->getFile())));
                    $xPrevious->appendChild($DOM->createElement('s:line', $h($previous->getLine())));
                    $xPrevious->appendChild($DOM->createElement('s:code', $h($previous->getCode())));
                    $xPrevious->appendChild($DOM->createElement('s:stacktrace', $h($previous->getTraceAsString())));
                    $error->appendChild($xPrevious);
                }
            }
            
            if ($e instanceof \Sabre\DAV\Exception) {
                
                $httpCode = $e->getHTTPCode();
                $e->serialize($this, $error);
                $headers = $e->getHTTPHeaders($this);
            } else {
                
                $httpCode = 500;
                $headers = [];
            }
            $headers['Content-Type'] = 'application/xml; charset=utf-8';
            
            $this->httpResponse->setStatus($httpCode);
            $this->httpResponse->setHeaders($headers);
            $this->httpResponse->setBody($DOM->saveXML());
            $this->sapi->sendResponse($this->httpResponse);
        }
    }
}
