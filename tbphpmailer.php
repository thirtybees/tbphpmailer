<?php
/**
 * Copyright (C) 2023-2023 thirty bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * @author    E-Com <e-com@presta.eu.org>
 * @author    thirty bees <modules@thirtybees.com>
 * @copyright 2023 - 2023 thirty bees
 * @license   Academic Free License (AFL 3.0)
 */

if (!defined('_TB_VERSION_')) {
    exit;
}

class TbPhpMailer extends Module
{
    const SUBMIT = 'submitSettings';

    /**
     * @throws PrestaShopException
     */
    public function __construct()
    {
        $this->name = 'tbphpmailer';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'thirty bees';
        $this->controllers = [];
        $this->bootstrap = true;
        parent::__construct();
        $this->displayName = $this->l('Mail via PHPmailer');
        $this->description = $this->l('This module implements mail functionality using PHPmailer library.');
        $this->need_instance = 0;
        $this->tb_versions_compliancy = '> 1.4.0';
        $this->tb_min_version = '1.5.0';
    }

    /**
     * @return bool
     *
     * @throws PrestaShopException
     */
    public function install()
    {
        return (
            parent::install() &&
            $this->registerHook('actionRegisterMailTransport')
        );
    }

    public function hookActionRegisterMailTransport($params)
    {
        require_once(__DIR__ . '/vendor/autoload.php');
        return new TbPhpMailerModule\PhpMailerTransport();
    }

    /**
     * @throws PrestaShopException
     * @throws SmartyException
     */
    public function getContent()
    {
        $html = '';
        if (Tools::isSubmit(static::SUBMIT)) {
            $this->updateOptions();
            $html .= $this->displayConfirmation($this->l('Configuration updated'));
        }
        $helper = new HelperOptions();
        $helper->module = $this;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
        $helper->title = $this->displayName;
        $helper->show_toolbar = false;
        $hasPassword = !!Configuration::get('PS_MAIL_PASSWD');
        return $html . $helper->generateOptions([
            'settings' => [
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs',
                'fields' => [
                    'PS_MAIL_SERVER' => [
                        'title' => $this->l('SMTP server'),
                        'hint' => $this->l('IP address or server name (e.g. smtp.mydomain.com).'),
                        'validation' => 'isGenericName',
                        'type' => 'text',
                        'class' => 'fixed-width-xxl',
                    ],
                    'PS_MAIL_USER' => [
                        'title' => $this->l('SMTP username'),
                        'validation' => 'isGenericName',
                        'type' => 'text',
                        'class' => 'fixed-width-xxl',
                    ],
                    'PS_MAIL_PASSWD' => [
                        'title' => $this->l('SMTP password'),
                        'validation' => 'isAnything',
                        'type' => 'password',
                        'autocomplete' => false,
                        'class' => 'fixed-width-xxl',
                        'placeholder' => $hasPassword ? $this->l('Use saved password') : null,
                        'hint' => $hasPassword
                            ? $this->l('Leave this field empty to keep using saved password')
                            : $this->l('Leave blank if not applicable.')
                    ],
                    'PS_MAIL_SMTP_ENCRYPTION' => [
                        'title' => $this->l('Encryption'),
                        'type' => 'select',
                        'cast' => 'strval',
                        'identifier' => 'mode',
                        'list' => [
                            [
                                'mode' => 'ssl',
                                'name' => $this->l('SSL'),
                            ],
                            [
                                'mode' => 'tls',
                                'name' => $this->l('TLS'),
                            ],
                            [
                                'mode' => 'none',
                                'name' => $this->l('None'),
                            ],
                        ],
                    ],
                    'PS_MAIL_SMTP_PORT' => [
                        'title' => $this->l('TCP port'),
                        'desc' => $this->l('The most commonly used ports: for SSL 465, for TLS 587, for none 25.'),
                        'validation' => 'isInt',
                        'type' => 'text',
                        'cast' => 'intval',
                        'class' => 'fixed-width-sm',
                    ],
                ],
                'submit' => [
                    'name' => static::SUBMIT,
                    'title' => $this->l('Save'),
                    'class' => 'button',
                ],
            ],
        ]);
    }

    /**
     * @throws PrestaShopException
     */
    private function updateOptions()
    {
        Configuration::updateValue('PS_MAIL_SERVER', Tools::getValue('PS_MAIL_SERVER'));
        Configuration::updateValue('PS_MAIL_USER', Tools::getValue('PS_MAIL_USER'));
        Configuration::updateValue('PS_MAIL_SMTP_ENCRYPTION', Tools::getValue('PS_MAIL_SMTP_ENCRYPTION'));
        Configuration::updateValue('PS_MAIL_SMTP_PORT', (int) Tools::getValue('PS_MAIL_SMTP_PORT'));

        // update password only when set
        $password = Tools::getValue('PS_MAIL_PASSWD');
        if ($password !== '' && $password !== false) {
            Configuration::updateValue('PS_MAIL_PASSWD', $password);
        }
    }
}
