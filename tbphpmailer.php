<?php
/**
 * Copyright (C) 2022-2022 thirty bees
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
 * @author    thirty bees <modules@thirtybees.com>
 * @copyright 2022 - 2022 thirty bees
 * @license   Academic Free License (AFL 3.0)
 */

if (!defined('_TB_VERSION_')) {
    exit;
}

class TbPhpMailer extends Module
{
    const MAIL_METHOD_MAIL = 1;
    const MAIL_METHOD_SMTP = 2;
    const MAIL_METHOD_NONE = 3;
    const SUBMIT = 'submitSettings';

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
        return $html . $helper->generateOptions([
            'settings' => [
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs',
                'fields' => [
                    'PS_MAIL_METHOD' => [
                        'title' => 'Mail method',
                        'validation' => 'isGenericName',
                        'type' => 'radio',
                        'required' => true,
                        'choices' => [
                            static::MAIL_METHOD_SMTP => $this->l('SMTP'),
                            static::MAIL_METHOD_MAIL => $this->l('PHP mail() function'),
                            static::MAIL_METHOD_NONE => $this->l('Never send emails'),
                        ],
                    ],
                    'PS_MAIL_DOMAIN' => [
                        'title' => $this->l('Mail domain name'),
                        'hint' => $this->l('Fully qualified domain name (keep this field empty if you don\'t know).'),
                        'empty' => true,
                        'validation' => 'isUrl',
                        'type' => 'text',
                        'class' => 'fixed-width-xxl',
                    ],
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
                        'type' => 'text',
                        'autocomplete' => false,
                        'class' => 'fixed-width-xxl',
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

    private function updateOptions()
    {
        Configuration::updateValue('PS_MAIL_METHOD', (int) Tools::getValue('PS_MAIL_METHOD'));
        Configuration::updateValue('PS_MAIL_DOMAIN', Tools::getValue('PS_MAIL_DOMAIN'));
        Configuration::updateValue('PS_MAIL_SERVER', Tools::getValue('PS_MAIL_SERVER'));
        Configuration::updateValue('PS_MAIL_USER', Tools::getValue('PS_MAIL_USER'));
        Configuration::updateValue('PS_MAIL_SMTP_ENCRYPTION', Tools::getValue('PS_MAIL_SMTP_ENCRYPTION'));
        Configuration::updateValue('PS_MAIL_SMTP_PORT', (int) Tools::getValue('PS_MAIL_SMTP_PORT'));
        Configuration::updateValue('PS_MAIL_PASSWD', Tools::getValue('PS_MAIL_PASSWD'));
    }
}
