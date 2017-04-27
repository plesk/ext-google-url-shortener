<?php
/**
 * Copyright 1999-2017. Parallels IP Holdings GmbH.
 */

/**
 * Class IndexController
 */
class IndexController extends pm_Controller_Action
{
    protected $_accessLevel = 'admin';
    protected $api_key = '';

    public function init()
    {
        parent::init();
        $this->view->headLink()->appendStylesheet(pm_Context::getBaseUrl().'style.css');

        // Init title for all actions
        $this->view->pageTitle = $this->lmsg('title');
        $this->api_key = pm_Settings::get('api_key', '');
    }

    /**
     * Access entry of the extension
     */
    public function indexAction()
    {
        if (empty($this->api_key)) {
            $this->_forward('api');

            return;
        }

        $this->_forward('form');
    }

    /**
     * Default action which creates the form in the settings and processes the requests
     */
    public function formAction()
    {
        // Set the description text
        $this->view->output_description = Modules_GoogleUrlShortener_Helper::translateString('output_description', 'description-extension');

        $form = new pm_Form_Simple();
        $this->createUrlInput($form);

        $form_button_send = Modules_GoogleUrlShortener_Helper::translateString('form_button_send');

        if (empty($this->api_key)) {
            $form_button_send = Modules_GoogleUrlShortener_Helper::translateString('form_button_send_save');
        }

        $form->addControlButtons([
            'sendTitle'  => $form_button_send,
            'cancelLink' => pm_Context::getModulesListUrl(),
        ]);

        $this->view->list = $this->createUrlList();

        // Process the form submission
        if ($this->getRequest()->isPost() && $form->isValid($this->getRequest()->getPost())) {
            try {
                $this->processPostRequest($form);
            }
            catch (Exception $e) {
                $this->_status->addMessage('error', $e->getMessage());
            }
        }

        $this->view->form = $form;
        $this->view->change_api = Modules_GoogleUrlShortener_Helper::translateString('form_change_api_key', '', ['link' => pm_Context::getActionUrl('index', 'api')]);
    }

    /**
     * Creates the input form fields for new URLs
     *
     * @param $form
     */
    private function createUrlInput(&$form)
    {
        if (!empty($this->api_key)) {
            $form->addElement('text', 'create_url', [
                'label'    => $this->lmsg('form_create_url'),
                'value'    => '',
                'required' => false
            ]);

            $form->addElement('checkbox', 'private', [
                'label' => $this->lmsg('from_private'),
                'value' => '',
            ]);

            $form->addElement('hidden', 'context', [
                'value' => 'url',
            ]);
        }
    }

    /**
     * Creates the list of all stored URLs
     *
     * @return Modules_GoogleUrlShortener_List_Overview
     */
    private function createUrlList()
    {
        return new Modules_GoogleUrlShortener_List_Overview($this->view, $this->_request);
    }

    /**
     * Processes POST request - after form submission
     *
     * @param $form
     */
    private function processPostRequest($form)
    {
        $context = $form->getValue('context');

        if ($context == 'api') {
            $api_key = $form->getValue('api_key');

            if ($this->validateApiKey($api_key)) {
                if ($this->api_key != $api_key) {
                    pm_Settings::set('api_key', $api_key);
                    $this->_status->addMessage('info', $this->lmsg('message_api_stored_success'));
                }
            }
        } elseif ($context == 'url') {
            if (!empty($this->api_key)) {
                $create_url = $form->getValue('create_url');

                if (empty($create_url)) {
                    $this->_status->addMessage('error', Modules_GoogleUrlShortener_Helper::translateString('message_url_blank'));
                    $this->_helper->json(['redirect' => pm_Context::getBaseUrl()]);

                    return;
                }

                $private = $form->getValue('private');

                if (!empty($private)) {
                    if ($this->storeUrlPrivate($create_url)) {
                        $this->_status->addMessage('info', Modules_GoogleUrlShortener_Helper::translateString('message_url_stored'));
                        $this->_helper->json(['redirect' => pm_Context::getBaseUrl()]);

                        return;
                    }
                }

                if ($this->createUrl($create_url)) {
                    $this->_status->addMessage('info', Modules_GoogleUrlShortener_Helper::translateString('message_url_stored'));
                }
            }
        }

        $this->_helper->json(['redirect' => pm_Context::getBaseUrl()]);
    }

    /**
     * Validates the API Key
     *
     * @param string $api_key
     *
     * @return bool
     */
    private function validateApiKey($api_key)
    {
        $api_key = preg_replace('/[^A-Za-z0-9\-_]/', '', str_replace(' ', '-', $api_key));

        if (empty($api_key) OR strlen($api_key) < 39) {
            $this->_status->addMessage('error', Modules_GoogleUrlShortener_Helper::translateString('message_error_key'));

            return false;
        }

        return true;
    }

    /**
     * Stores private URLs that are not shortened by the Google Shortener API service
     *
     * @param $url
     *
     * @return bool
     */
    private function storeUrlPrivate($url)
    {
        $url_validated = Modules_GoogleUrlShortener_Helper::validateUrl($url, $this->api_key);

        if (empty($url_validated)) {
            $this->_status->addMessage('error', Modules_GoogleUrlShortener_Helper::translateString('error_url_already_stored'));

            return false;
        }

        $urls_stored = Modules_GoogleUrlShortener_Helper::getUrlsStored($this->api_key);

        $urls_stored[$url_validated] = array(
            'id' => '',
        );

        Modules_GoogleUrlShortener_Helper::storeUrls($urls_stored, $this->api_key);

        return true;
    }

    /**
     * Creates short URLs that are shortened by the Google Shortener API service
     *
     * @param $url
     *
     * @return bool
     */
    private function createUrl($url)
    {
        $url_validated = Modules_GoogleUrlShortener_Helper::validateUrl($url, $this->api_key);

        if (empty($url_validated)) {
            $this->_status->addMessage('error', Modules_GoogleUrlShortener_Helper::translateString('error_url_already_stored'));

            return false;
        }

        $url_new = Modules_GoogleUrlShortener_Helper::createUrlApi($url_validated, $this->api_key);

        if (is_string($url_new)) {
            $this->_status->addMessage('error', Modules_GoogleUrlShortener_Helper::translateString($url_new));

            return false;
        }

        $urls_stored = Modules_GoogleUrlShortener_Helper::getUrlsStored($this->api_key);

        $urls_stored[$url_new->longUrl] = array(
            'id' => $url_new->id,
        );

        Modules_GoogleUrlShortener_Helper::storeUrls($urls_stored, $this->api_key);

        return true;
    }

    /**
     * API action to add or modify an API key
     */
    public function apiAction()
    {
        // Set the description text
        $this->view->output_description = Modules_GoogleUrlShortener_Helper::translateString('output_description', 'description-extension');
        $this->setDescriptionLink();

        $form = new pm_Form_Simple();
        $this->createApiInput($form);

        $form_button_send = $this->lmsg('form_button_send_save');

        $form->addControlButtons([
            'sendTitle'  => $form_button_send,
            'cancelLink' => pm_Context::getBaseUrl(),
        ]);

        // Process the form submission
        if ($this->getRequest()->isPost() && $form->isValid($this->getRequest()->getPost())) {
            try {
                $this->processPostRequest($form);
            }
            catch (Exception $e) {
                $this->_status->addMessage('error', $e->getMessage());
            }
        }

        $this->view->form = $form;
    }

    /**
     * Sets the intro description depending on the API key value
     */
    private function setDescriptionLink()
    {
        $this->view->output_description_api = Modules_GoogleUrlShortener_Helper::translateString('output_description_api_exist', 'description-extension');

        if (empty($this->api_key)) {
            $this->view->output_description_api = Modules_GoogleUrlShortener_Helper::translateString('output_description_api', 'description-extension');
        }
    }

    /**
     * Creates the API key input field
     *
     * @param $form
     */
    private function createApiInput(&$form)
    {
        $form->addElement('text', 'api_key', [
            'label'      => $this->lmsg('form_api_key'),
            'value'      => pm_Settings::get('api_key'),
            'required'   => true,
            'validators' => [
                [
                    'NotEmpty',
                    true
                ],
            ],
        ]);

        $form->addElement('hidden', 'context', [
            'value' => 'api',
        ]);
    }

    /**
     * Required action to update the list after sorting has been changed by the user
     */
    public function formDataAction()
    {
        $list = new Modules_GoogleUrlShortener_List_Overview($this->view, $this->_request);
        $this->_helper->json($list->fetchData());
    }

    /**
     * Removes a selected URL from the list
     */
    public function removeAction()
    {
        $get_global = $this->getRequest()->getQuery();

        if (empty($get_global['site_id']) OR empty($this->api_key)) {
            $this->_status->addMessage('error', Modules_GoogleUrlShortener_Helper::translateString('message_remove_error'));
            $this->redirect(pm_Context::getBaseUrl());
        }

        $urls_stored = Modules_GoogleUrlShortener_Helper::getUrlsStored($this->api_key);

        if (isset($urls_stored[$get_global['site_id']])) {
            unset($urls_stored[$get_global['site_id']]);
            Modules_GoogleUrlShortener_Helper::storeUrls($urls_stored, $this->api_key);
            $this->_status->addMessage('info', Modules_GoogleUrlShortener_Helper::translateString('message_remove_success'));
        }

        $this->_redirect(pm_Context::getBaseUrl());
    }
}
