<?php
/**
 * Copyright (C) 2017-2024 thirty bees
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
 * @copyright 2017-2024 thirty bees
 * @license   Academic Free License (AFL 3.0)
 */

if (!defined('_TB_VERSION_')) {
    exit;
}

class TbPhpMailer extends Module
{
    const SUBMIT = 'submitSettings';
    const ENCRYPTION_NONE = 'none';
    const ENCRYPTION_SSL = 'ssl';
    const ENCRYPTION_TLS = 'tls';

    const CONFIG_MAIL_SERVER = 'TBPHPMAILER_MAIL_SERVER';
    const CONFIG_MAIL_USER = 'TBPHPMAILER_MAIL_USER';
    const CONFIG_MAIL_PASSWD = 'TBPHPMAILER_MAIL_PASSWD';
    const CONFIG_MAIL_SMTP_ENCRYPTION = 'TBPHPMAILER_MAIL_SMTP_ENCRYPTION';
    const CONFIG_MAIL_SMTP_PORT = 'TBPHPMAILER_MAIL_SMTP_PORT';
    const CONFIG_SSL_ALLOW_SELF_SIGN = 'TBPHPMAILER_SSL_ALLOW_SELF_SIGN';
    const CONFIG_SSL_VERIFY_PEER = 'TBPHPMAILER_SSL_VERIFY_PEER' ;
    const CONFIG_SSL_PEER_NAME = 'TBPHPMAILER_SSL_PEER_NAME';
    const CONFIG_SSL_CA_FILE = 'TBPHPMAILER_SSL_CA_FILE';
    const CONFIG_SSL_VERIFY_PEER_NAME = 'TBPHPMAILER_SSL_VERIFY_PEER_NAME';

    /**
     * @throws PrestaShopException
     */
    public function __construct()
    {
        $this->name = 'tbphpmailer';
        $this->tab = 'administration';
        $this->version = '1.0.1';
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
            $this->ensureConfiguration() &&
            $this->registerHook('actionRegisterMailTransport')
        );
    }

    /**
     * @return bool
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function uninstall()
    {
        $this->removeConfiguration();
        return parent::uninstall();
    }


    /**
     * @return TbPhpMailerModule\PhpMailerTransport
     */
    public function hookActionRegisterMailTransport()
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
        $this->ensureConfiguration();

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
        $hasPassword = !!Configuration::get(static::CONFIG_MAIL_PASSWD);
        $settingsForm = [
            'title' => $this->l('Settings'),
            'icon' => 'icon-cogs',
            'fields' => [
                static::CONFIG_MAIL_SERVER => [
                    'title' => $this->l('SMTP server'),
                    'hint' => $this->l('IP address or server name (e.g. smtp.mydomain.com).'),
                    'validation' => 'isGenericName',
                    'type' => 'text',
                    'class' => 'fixed-width-xxl',
                ],
                static::CONFIG_MAIL_USER => [
                    'title' => $this->l('SMTP username'),
                    'validation' => 'isGenericName',
                    'type' => 'text',
                    'class' => 'fixed-width-xxl',
                ],
                static::CONFIG_MAIL_PASSWD => [
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
                static::CONFIG_MAIL_SMTP_ENCRYPTION => [
                    'title' => $this->l('Encryption'),
                    'type' => 'select',
                    'cast' => 'strval',
                    'identifier' => 'mode',
                    'list' => [
                        [
                            'mode' => static::ENCRYPTION_SSL,
                            'name' => $this->l('SSL'),
                        ],
                        [
                            'mode' => static::ENCRYPTION_TLS,
                            'name' => $this->l('TLS'),
                        ],
                        [
                            'mode' => static::ENCRYPTION_NONE,
                            'name' => $this->l('None'),
                        ],
                    ],
                ],
                static::CONFIG_MAIL_SMTP_PORT => [
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
        ];

        $sslForm = [
            'title' => $this->l('SSL settings'),
            'description' => $this->l('You can fine-tune SSL options that will be used for SSL or TLS encryption. The default settings works in most cases. Change these values only if you have issues with connecting to your SMTP server.'),
            'icon' => 'icon-cogs',
            'fields' => [
                static::CONFIG_SSL_VERIFY_PEER => [
                    'title' => $this->l('Verify peer'),
                    'hint' => $this->l('Require verification of SSL certificate used.'),
                    'cast'  => 'boolval',
                    'type'  => 'bool',
                ],
                static::CONFIG_SSL_VERIFY_PEER_NAME => [
                    'title' => $this->l('Verify peer name'),
                    'hint' => $this->l('Require verification of peer name.'),
                    'cast'  => 'boolval',
                    'type'  => 'bool',
                ],
                static::CONFIG_SSL_ALLOW_SELF_SIGN => [
                    'title' => $this->l('Allow self-signed certificats'),
                    'hint' => $this->l('When enabled, mail server can use self-signed certificates. Requires "Verify peer"'),
                    'cast'  => 'boolval',
                    'type'  => 'bool',
                ],
                static::CONFIG_SSL_PEER_NAME => [
                    'title' => $this->l('Peer name'),
                    'hint' => $this->l('Peer name used for certificate authentication. If not set, then the name is guessed based on the hostname'),
                    'type'  => 'text',
                    'class' => 'fixed-width-xxl',
                ],
                static::CONFIG_SSL_CA_FILE => [
                    'title' => $this->l('CA File Path'),
                    'hint' => $this->l('Location of Certificate Authority file on your server filesystem which should be used with the verify_peer context option to authenticate the identity of the remote peer'),
                    'type'  => 'text',
                ],
            ],
            'submit' => [
                'name' => static::SUBMIT,
                'title' => $this->l('Save'),
                'class' => 'button',
            ],
        ];
        return $html . $helper->generateOptions([
            $settingsForm,
            $sslForm
        ]);
    }

    /**
     * @throws PrestaShopException
     */
    private function updateOptions()
    {
        // Update general settings options
        Configuration::updateValue(static::CONFIG_MAIL_SERVER, Tools::getValue(static::CONFIG_MAIL_SERVER));
        Configuration::updateValue(static::CONFIG_MAIL_USER, Tools::getValue(static::CONFIG_MAIL_USER));
        Configuration::updateValue(static::CONFIG_MAIL_SMTP_ENCRYPTION, Tools::getValue(static::CONFIG_MAIL_SMTP_ENCRYPTION));
        Configuration::updateValue(static::CONFIG_MAIL_SMTP_PORT, (int)Tools::getValue(static::CONFIG_MAIL_SMTP_PORT));
        // update password only when set
        $password = Tools::getValue(static::CONFIG_MAIL_PASSWD);
        if ($password !== '' && $password !== false) {
            Configuration::updateValue(static::CONFIG_MAIL_PASSWD, $password);
        }

        // Update SSL options
        Configuration::updateValue(static::CONFIG_SSL_PEER_NAME, Tools::getValue(static::CONFIG_SSL_PEER_NAME));
        Configuration::updateValue(static::CONFIG_SSL_CA_FILE, Tools::getValue(static::CONFIG_SSL_CA_FILE));
        Configuration::updateValue(static::CONFIG_SSL_ALLOW_SELF_SIGN, (int)Tools::getValue(static::CONFIG_SSL_ALLOW_SELF_SIGN));
        Configuration::updateValue(static::CONFIG_SSL_VERIFY_PEER, (int)Tools::getValue(static::CONFIG_SSL_VERIFY_PEER));
        Configuration::updateValue(static::CONFIG_SSL_VERIFY_PEER_NAME, (int)Tools::getValue(static::CONFIG_SSL_VERIFY_PEER_NAME));
    }

    /**
     * @return bool
     *
     * @throws PrestaShopException
     */
    private function ensureConfiguration()
    {
        $this->ensureConfigKey(static::CONFIG_MAIL_SERVER, (string)Configuration::get('PS_MAIL_SERVER'));
        $this->ensureConfigKey(static::CONFIG_MAIL_USER, (string)Configuration::get('PS_MAIL_USER'));
        $this->ensureConfigKey(static::CONFIG_MAIL_PASSWD, (string)Configuration::get('PS_MAIL_PASSWD'));
        $this->ensureConfigKey(static::CONFIG_MAIL_SMTP_ENCRYPTION, static::getEncryptionValue((string)Configuration::get('PS_MAIL_SMTP_ENCRYPTION')));
        $this->ensureConfigKey(static::CONFIG_MAIL_SMTP_PORT, (string)Configuration::get('PS_MAIL_SMTP_PORT'));

        $this->ensureConfigKey(static::CONFIG_SSL_ALLOW_SELF_SIGN, '0');
        $this->ensureConfigKey(static::CONFIG_SSL_VERIFY_PEER, '1');
        $this->ensureConfigKey(static::CONFIG_SSL_PEER_NAME, '');
        $this->ensureConfigKey(static::CONFIG_SSL_CA_FILE, '');
        $this->ensureConfigKey(static::CONFIG_SSL_VERIFY_PEER_NAME, '1');
        return true;
    }

    /**
     * @param string $key
     * @param string $defaultValue
     *
     * @return void
     *
     * @throws PrestaShopException
     */
    private function ensureConfigKey(string $key, string $defaultValue)
    {
        if (! Configuration::hasKey($key)) {
            Configuration::updateValue($key, $defaultValue);
        }
    }

    /**
     * @return void
     *
     * @throws PrestaShopException
     */
    private function removeConfiguration()
    {
        Configuration::deleteByName(static::CONFIG_MAIL_SERVER);
        Configuration::deleteByName(static::CONFIG_MAIL_USER);
        Configuration::deleteByName(static::CONFIG_MAIL_PASSWD);
        Configuration::deleteByName(static::CONFIG_MAIL_SMTP_ENCRYPTION);
        Configuration::deleteByName(static::CONFIG_MAIL_SMTP_PORT);
        Configuration::deleteByName(static::CONFIG_SSL_ALLOW_SELF_SIGN);
        Configuration::deleteByName(static::CONFIG_SSL_VERIFY_PEER);
        Configuration::deleteByName(static::CONFIG_SSL_PEER_NAME);
        Configuration::deleteByName(static::CONFIG_SSL_CA_FILE);
        Configuration::deleteByName(static::CONFIG_SSL_VERIFY_PEER_NAME);
    }

    /**
     * @param string $key
     * @return string
     */
    public static function getEncryptionValue(string $key): string
    {
        switch (strtolower($key)) {
            case static::ENCRYPTION_SSL:
                return static::ENCRYPTION_SSL;
            case static::ENCRYPTION_TLS:
                return static::ENCRYPTION_TLS;
            case static::ENCRYPTION_NONE:
            default:
                return static::ENCRYPTION_NONE;
        }
    }
}
