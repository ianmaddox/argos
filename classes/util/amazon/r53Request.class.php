<?php

/**
 *
 * Copyright (c) 2011, Dan Myers.
 * Parts copyright (c) 2008, Donovan Schonknecht.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * - Redistributions of source code must retain the above copyright notice,
 *   this list of conditions and the following disclaimer.
 * - Redistributions in binary form must reproduce the above copyright
 *   notice, this list of conditions and the following disclaimer in the
 *   documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * This is a modified BSD license (the third clause has been removed).
 * The BSD license may be found here:
 * http://www.opensource.org/licenses/bsd-license.php
 *
 * Amazon Route 53 is a trademark of Amazon.com, Inc. or its affiliates.
 *
 * Route53 is based on Donovan Schonknecht's Amazon S3 PHP class, found here:
 * http://undesigned.org.za/2007/10/22/amazon-s3-php-class
 *
 */

/**
 * Amazon r53Request PHP class
 *
 * @link http://sourceforge.net/projects/php-r53/
 * version 0.9.0
 *
 * Modified by Argos framework.
 *
 */
final class util_amazon_r53Request {

    private $r53, $action, $verb, $data, $parameters = array();
    public $response;

    /**
     * Constructor
     *
     * @param string $r53 The Route53 object making this request
     * @param string $action SimpleDB action
     * @param string $verb HTTP verb
     * @param string $data For POST requests, the data being posted (optional)
     * @return mixed
     */
    function __construct($r53, $action, $verb, $data = '') {
        $this->r53 = $r53;
        $this->action = $action;
        $this->verb = $verb;
        $this->data = $data;
        $this->response = new STDClass;
        $this->response->body = '';
        $this->response->error = false;
    }

    /**
     * Set request parameter
     *
     * @param string  $key Key
     * @param string  $value Value
     * @param boolean $replace Whether to replace the key if it already exists (default true)
     * @return void
     */
    public function setParameter($key, $value, $replace = true) {
        if (!$replace && isset($this->parameters[$key])) {
            $temp = (array) ($this->parameters[$key]);
            $temp[] = $value;
            $this->parameters[$key] = $temp;
        } else {
            $this->parameters[$key] = $value;
        }
    }

    /**
     * Get the response
     *
     * @return object | false
     */
    public function getResponse() {

        $params = array();
        foreach ($this->parameters as $var => $value) {
            if (is_array($value)) {
                foreach ($value as $v) {
                    $params[] = $var . '=' . $this->__customUrlEncode($v);
                }
            } else {
                $params[] = $var . '=' . $this->__customUrlEncode($value);
            }
        }

        sort($params, SORT_STRING);

        $query = implode('&', $params);

        // must be in format 'Sun, 06 Nov 1994 08:49:37 GMT'
        $date = gmdate('D, d M Y H:i:s e');

        $headers = array();
        $headers[] = 'Date: ' . $date;
        $headers[] = 'Host: ' . $this->r53->getHost();

        $auth = 'AWS3-HTTPS AWSAccessKeyId=' . $this->r53->getAccessKey();
        $auth .= ',Algorithm=HmacSHA256,Signature=' . $this->__getSignature($date);
        $headers[] = 'X-Amzn-Authorization: ' . $auth;

        $url = 'https://' . $this->r53->getHost() . '/' . util_amazon_r53::API_VERSION . '/' . $this->action . '?' . $query;

        // Basic setup
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_USERAGENT, 'Route53/php');

        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, ($this->r53->verifyHost() ? 2 : 0));
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, ($this->r53->verifyPeer() ? 1 : 0));

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($curl, CURLOPT_WRITEFUNCTION, array(&$this, '__responseWriteCallback'));
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);

        // Request types
        switch ($this->verb) {
            case 'GET': break;
            case 'POST':
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $this->verb);
                if (strlen($this->data) > 0) {
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $this->data);
                    $headers[] = 'Content-Type: text/plain';
                    $headers[] = 'Content-Length: ' . strlen($this->data);
                }
                break;
            case 'DELETE':
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
            default: break;
        }
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_HEADER, false);

        // Execute, grab errors
        if (curl_exec($curl)) {
            $this->response->code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        } else {
            $this->response->error = array(
                'curl' => true,
                'code' => curl_errno($curl),
                'message' => curl_error($curl),
                'resource' => $this->resource
            );
        }

        @curl_close($curl);

        // Parse body into XML
        if ($this->response->error === false && isset($this->response->body)) {
            $this->response->body = simplexml_load_string($this->response->body);

            // Grab Route53 errors
            if (!in_array($this->response->code, array(200, 201, 202, 204))
                    && isset($this->response->body->Error)) {
                $error = $this->response->body->Error;
                $output = array();
                $output['curl'] = false;
                $output['Error'] = array();
                $output['Error']['Type'] = (string) $error->Type;
                $output['Error']['Code'] = (string) $error->Code;
                $output['Error']['Message'] = (string) $error->Message;
                $output['RequestId'] = (string) $this->response->body->RequestId;

                $this->response->error = $output;
                unset($this->response->body);
            }
        }

        return $this->response;
    }

    /**
     * CURL write callback
     *
     * @param resource &$curl CURL resource
     * @param string &$data Data
     * @return integer
     */
    private function __responseWriteCallback(&$curl, &$data) {
        $this->response->body .= $data;
        return strlen($data);
    }

    /**
     * Contributed by afx114
     * URL encode the parameters as per http://docs.amazonwebservices.com/AWSECommerceService/latest/DG/index.html?Query_QueryAuth.html
     * PHP's rawurlencode() follows RFC 1738, not RFC 3986 as required by Amazon. The only difference is the tilde (~), so convert it back after rawurlencode
     * See: http://www.morganney.com/blog/API/AWS-Product-Advertising-API-Requires-a-Signed-Request.php
     *
     * @param string $var String to encode
     * @return string
     */
    private function __customUrlEncode($var) {
        return str_replace('%7E', '~', rawurlencode($var));
    }

    /**
     * Generate the auth string using Hmac-SHA256
     *
     * @internal Used by self::getResponse()
     * @param string $string String to sign
     * @return string
     */
    private function __getSignature($string) {
        return base64_encode(hash_hmac('sha256', $string, $this->r53->getSecretKey(), true));
    }

}
