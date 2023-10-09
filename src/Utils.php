<?php

namespace Ledc\Mark;

use Ledc\Mark\Http\Response;
use SimpleXMLElement;

/**
 * 工具类
 */
class Utils
{
    /**
     * Response
     * @param int $status
     * @param array $headers
     * @param string $body
     * @return Response
     */
    public static function response(string $body = '', int $status = 200, array $headers = []): Response
    {
        return new Response($status, $headers, $body);
    }

    /**
     * Json response
     * @param $data
     * @param int $options
     * @return Response
     */
    public static function json($data, int $options = JSON_UNESCAPED_UNICODE): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode($data, $options));
    }

    /**
     * Xml response
     * @param $xml
     * @return Response
     */
    public static function xml($xml): Response
    {
        if ($xml instanceof SimpleXMLElement) {
            $xml = $xml->asXML();
        }
        return new Response(200, ['Content-Type' => 'text/xml'], $xml);
    }

    /**
     * Jsonp response
     * @param $data
     * @param string $callbackName
     * @return Response
     */
    public static function jsonp($data, string $callbackName = 'callback'): Response
    {
        if (!is_scalar($data) && null !== $data) {
            $data = json_encode($data);
        }
        return new Response(200, [], "$callbackName($data)");
    }

    /**
     * Redirect response
     * @param string $location
     * @param int $status
     * @param array $headers
     * @return Response
     */
    public static function redirect(string $location, int $status = 302, array $headers = []): Response
    {
        $response = new Response($status, ['Location' => $location]);
        if (!empty($headers)) {
            $response->withHeaders($headers);
        }
        return $response;
    }
}
