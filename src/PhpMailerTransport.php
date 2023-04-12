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

namespace TbPhpMailerModule;

use Configuration;
use Context;
use PrestaShopException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use TbPhpMailer;
use Thirtybees\Core\Mail\MailAddress;
use Thirtybees\Core\Mail\MailAttachement;
use Thirtybees\Core\Mail\MailTemplate;
use Thirtybees\Core\Mail\MailTransport;
use Thirtybees\Core\Mail\Template\SimpleMailTemplate;
use Tools;
use Translate;

class PhpMailerTransport implements MailTransport
{
    const TYPE_HTML = 1;
    const TYPE_TEXT = 2;
    const TYPE_BOTH = 3;

    public function getName(): string
    {
        return $this->l('PHPmailer');
    }

    public function l($string)
    {
        return Translate::getModuleTranslation('tbphpmailer', $string, 'tbphpmailer');
    }

    public function getDescription(): string
    {
        return $this->l('Sends email using PHPmailer library');
    }

    public function getConfigUrl()
    {
        return Context::getContext()->link->getAdminLink('AdminModules', true, ['configure' => 'tbphpmailer']);
    }

    public function sendMail(
        int         $idShop,
        int         $idLang,
        MailAddress $fromAddress,
        array       $toAddresses,
        array       $bccAddresses,
        MailAddress $replyTo,
        string      $subject,
        array       $templates,
        array       $templateVars,
        array       $attachements
    ): bool
    {
        $mailMethod = (int) $this->getConfig('PS_MAIL_METHOD', $idShop, TbPhpMailer::MAIL_METHOD_NONE);
        if ($mailMethod === TbPhpMailer::MAIL_METHOD_NONE) {
            return true;
        }
        $message = new PHPMailer(true);
        $message->isSMTP();
        $message->CharSet = 'UTF-8';
        $message->Host = $this->getConfig('PS_MAIL_SERVER', $idShop);
        $message->MessageID = $this->generateId();
        $message->SMTPAuth = true;
        $message->SMTPDebug = false;
        $message->Username = $this->getConfig('PS_MAIL_USER', $idShop);
        $message->Password = $this->getConfig('PS_MAIL_PASSWD', $idShop);
        $encryption = $this->getConfig('PS_MAIL_SMTP_ENCRYPTION', $idShop);
        if ($encryption == 'ssl') {
            $message->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($encryption == 'tls') {
            $message->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }
        $message->Port = (int) $this->getConfig('PS_MAIL_SMTP_PORT', $idShop);
        $message->setFrom($fromAddress->getAddress(), $fromAddress->getName());
        $message->Subject = $subject;
        $message->addReplyTo($replyTo->getAddress(), $replyTo->getName());
        foreach ($toAddresses as $ccAddress) {
            $message->addCC($ccAddress->getAddress(), $ccAddress->getName());
        }
        foreach ($bccAddresses as $bccAddress) {
            $message->addBCC($bccAddress->getAddress(), $bccAddress->getName());
        }
        $templateVars = $this->processTemplateVars($templateVars, $message);

        $htmlBody = null;
        $txtBody = null;

        foreach ($templates as $template) {
            $templateType = $template->getContentType();
            if ($templateType == 'text/html') {
                $htmlBody = $template->renderTemplate($templateVars);
            }
            if ($templateType == 'text/plain') {
                $txtBody = $template->renderTemplate($templateVars);
            }
        }

        if ($htmlBody) {
            $message->isHTML(true);
            $message->Body = $htmlBody;
            if ($txtBody) {
                $message->AltBody = $txtBody;
            }
        } elseif($txtBody) {
            $message->isHTML(false);
            $message->Body = $txtBody;
        }
        foreach ($attachements as $attachement) {
            $message->addStringAttachment($attachement->getContent(), $attachement->getName());
        }
        return $message->send();
    }

    private function getConfig($key, $idShop, $default = null)
    {
        $value = Configuration::get($key, null, null, $idShop);
        if ($value === false || $value === null) {
            return $default;
        }
        return $value;
    }

    private function processTemplateVars(array $templateVars, PHPMailer $message)
    {
        foreach ($templateVars as $key => &$parameter) {
            if (is_array($parameter) && isset($parameter['type']) && $parameter['type'] === 'imageFile') {
                $filePath = $parameter['filepath'];
                $cid = str_replace(['{', '}'], '', $key);
                if ($filePath && file_exists($filePath) && $message->addEmbeddedImage($filePath, $cid)) {
                    $parameter = "cid:$cid";
                } else {
                    $parameter = '';
                }
            }
        }
        return $templateVars;
    }

    protected static function generateId()
    {
        $params = [
            'utctime' => gmdate('YmdHis'),
            'randint' => mt_rand(),
            'customstr' => 'phpmailer',
            'hostname' => ((isset($_SERVER['SERVER_NAME']) && !empty($_SERVER['SERVER_NAME'])) ? $_SERVER['SERVER_NAME'] : php_uname('n')),
        ];
        return vsprintf("%s.%d.%s@%s", $params);
    }

}
