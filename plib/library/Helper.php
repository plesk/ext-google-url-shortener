<?php

// Copyright 1999-2017. Parallels IP Holdings GmbH.

class Modules_GoogleUrlShortener_Helper
{
    /**
     * Makes a cURL request to the Google URL Shortener API
     *
     * @param $url
     *
     * @return mixed|string
     */
    public static function createUrlApi($url, $api_key)
    {
        if (!empty($url)) {
            $pagespeed_api_url = 'https://www.googleapis.com/urlshortener/v1/url';

            // Set API Key
            $pagespeed_api_url .= '?key='.$api_key;

            $client = new Zend_Http_Client($pagespeed_api_url);
            $client->setHeaders(array('Content-Type: application/json'));
            $client->setRawData('{"longUrl": "'.$url.'"}');

            try {
                $pagespeed_response = $client->request(Zend_Http_Client::POST);
            }
            catch (Exception $e) {
                return $e->getMessage();
            }

            $pagespeed_result = json_decode($pagespeed_response->getBody());

            if ($pagespeed_response->isError() AND $pagespeed_result->error == true) {
                if (!empty($pagespeed_result->error->message)) {
                    return (string) $pagespeed_result->error->message;
                }

                return 'error_api_request';
            }

            return $pagespeed_result;
        }
    }

    /**
     * Removes square brackets from not provided language strings, needed for status response from API call
     *
     * @param string $language_string
     * @param string $class_name
     * @param array  $language_string_params
     *
     * @return string
     */
    public static function translateString($language_string, $class_name = '', $language_string_params = array())
    {
        $translated_string = pm_Locale::lmsg($language_string, $language_string_params);

        if ($translated_string == '[['.$language_string.']]') {
            $translated_string = $language_string;
        }

        if (!empty($class_name)) {
            return '<span class="'.$class_name.'">'.$translated_string.'</span>';
        }

        return $translated_string;
    }

    /**
     * Validates the entered URL (format, not availability)
     *
     * @param $url
     * @param $api_key
     *
     * @return bool|string
     */
    public static function validateUrl($url, $api_key)
    {
        if (!preg_match('@http.?://@', $url)) {
            $url = 'http://'.$url;
        }

        if (substr($url, -1, 1) != '/') {
            $url .= '/';
        }

        $url = strtolower($url);

        $urls_stored = self::getUrlsStored($api_key);

        if (isset($urls_stored[$url])) {
            return false;
        }

        return $url;
    }

    /**
     * Gets all stored URLs for the transferred API key
     *
     * @param bool $api_key
     *
     * @return array
     */
    public static function getUrlsStored($api_key = false)
    {
        $urls_stored = self::loadUrlsFile();

        if (!empty($urls_stored)) {
            if (!empty($api_key)) {
                if (!empty($urls_stored[$api_key])) {
                    return $urls_stored[$api_key];
                }
            }
        }

        return array();
    }

    /**
     * Loads the URLs storage file from the var directory
     *
     * @return bool|mixed
     */
    private static function loadUrlsFile()
    {
        $urls_stored = file_get_contents(pm_Context::getVarDir().'urls');

        if (!empty($urls_stored)) {
            return json_decode($urls_stored, true);
        }

        return false;
    }

    /**
     * Stores all URLs for the specified API key
     *
     * @param $urls
     * @param $api_key
     */
    public static function storeUrls($urls, $api_key)
    {
        $urls = array($api_key => $urls);
        self::saveUrlsFile($urls);
    }

    /**
     * Saves the URLs to the storage file in the var directory
     *
     * @param $data
     */
    private static function saveUrlsFile($data)
    {
        if (!empty($data)) {
            file_put_contents(pm_Context::getVarDir().'urls', json_encode($data));
        }
    }
}
