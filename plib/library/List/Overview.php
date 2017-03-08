<?php
// Copyright 1999-2017. Parallels IP Holdings GmbH.

class Modules_GoogleUrlShortener_List_Overview extends pm_View_List_Simple
{
    public function __construct(Zend_View $view, Zend_Controller_Request_Abstract $request)
    {
        parent::__construct($view, $request);

        $data = $this->getData();
        $this->setData($data);
        $this->setColumns(array(
            'column-1' => array(
                'title'      => $this->lmsg('table_url_long'),
                'noEscape'   => true,
                'searchable' => true,
                'sortable'   => true,
            ),
            'column-2' => array(
                'title'    => $this->lmsg('table_url_id'),
                'noEscape' => true,
                'sortable' => true,
            ),
            'column-3' => array(
                'title'    => '',
                'noEscape' => true,
                'sortable' => false,
            )
        ));

        $this->setDataUrl(['action' => 'form-data']);
    }

    /**
     * Gets the stored URLs for the list
     *
     * @return array
     */
    private function getData()
    {
        $data = array();
        $api_key = pm_Settings::get('api_key', '');
        $urls_stored = Modules_GoogleUrlShortener_Helper::getUrlsStored($api_key);

        foreach ($urls_stored as $key => $value) {
            $url_long = $key;

            if (empty($value['id'])) {
                $url_long = '<a href="'.$key.'" target="_blank">'.$key.'</a>';
            }

            $url_id = '';

            if (!empty($value['id'])) {
                $url_id = '<a href="'.$value['id'].'" target="_blank">'.$value['id'].'</a>';
            }

            $action_link = '';

            if (!empty($value['id'])) {
                $analyze_link = str_replace('https://goo.gl/', '', $value['id']);
                $action_link .= '<a href="https://goo.gl/info/'.$analyze_link.'" target="_blank">'.pm_Locale::lmsg('action_analyze_url').'</a> - ';
            }

            $action_link .= '<a href="'.pm_Context::getActionUrl('index', 'remove').'?site_id='.$key.'">'.pm_Locale::lmsg('action_remove_url').'</a>';

            $data[] = array(
                'column-1' => $url_long,
                'column-2' => $url_id,
                'column-3' => $action_link,
            );
        }

        return $data;
    }
}
