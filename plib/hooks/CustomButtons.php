<?php
// Copyright 1999-2017. Parallels IP Holdings GmbH.

class Modules_GoogleUrlShortener_CustomButtons extends pm_Hook_CustomButtons
{
    public function getButtons()
    {
        $buttons = [
            [
                'place'       => self::PLACE_DOMAIN,
                'title'       => pm_Locale::lmsg('title'),
                'description' => pm_Locale::lmsg('output_description'),
                'icon'        => pm_Context::getBaseUrl().'images/url-shortener-button-icon.png',
                'link'        => pm_Context::getBaseUrl(),
                'newWindow'   => false
            ]
        ];

        return $buttons;
    }
}
