<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_Smtp
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\Smtp\Mail\Rse;

use Magento\Framework\App\ObjectManager;
use Mageplaza\Smtp\Helper\Data;

/**
 * Class Mail
 * @package Mageplaza\Smtp\Application\Rse
 */
class Mail extends \Zend_Mail
{
    /**
     * @var \Mageplaza\Smtp\Helper\Data
     */
    protected $smtpHelper;

    /**
     * @var boolean is module enable
     */
    protected $_moduleEnable;

    /**
     * @var boolean is developer mode
     */
    protected $_developerMode;

    /**
     * @var boolean is enable email log
     */
    protected $_emailLog = [];

    /**
     * @var string message body email
     */
    protected $_message;

    /**
     * @var array option by storeid
     */
    protected $_smtpOptions = [];

    /**
     * @var array
     */
    protected $_returnPath = [];

    /**
     * @var \Zend_Mail_Transport_Smtp
     */
    protected $_transport;

    /**
     * Mail constructor.
     * @param null $options
     */
    public function __construct($options = null)
    {
        $this->smtpHelper = ObjectManager::getInstance()->get(Data::class);

        parent::__construct($options);
    }

    /**
     * @param $storeId
     * @param array $options
     * @return $this
     */
    public function setSmtpOptions($storeId, $options = [])
    {
        if (isset($options['return_path'])) {
            $this->_returnPath[$storeId] = $options['return_path'];
            unset($options['return_path']);
        }

        if (isset($options['ignore_log']) && $options['ignore_log']) {
            $this->_emailLog[$storeId] = false;
            unset($options['ignore_log']);
        }

        if (isset($options['force_sent']) && $options['force_sent']) {
            $this->_moduleEnable[$storeId] = true;
            unset($options['force_sent']);
        }

        if (sizeof($options)) {
            $this->_smtpOptions[$storeId] = $options;
        }
        return $this;
    }

    /**
     * @param $storeId
     * @return \Zend_Mail_Transport_Smtp | \Zend\Mail\Transport\Smtp
     * @throws \Zend_Exception
     */
    public function getTransport($storeId)
    {
        if (null === $this->_transport) {
            if (!isset($this->_smtpOptions[$storeId])) {
                $configData = $this->smtpHelper->getSmtpConfig('', $storeId);
                $options = [
                    'host' => isset($configData['host']) ? $configData['host'] : '',
                    'port' => isset($configData['port']) ? $configData['port'] : ''
                ];

                if (isset($configData['authentication']) && $configData['authentication'] !== "") {
                    $options += [
                        'auth' => $configData['authentication'],
                        'username' => isset($configData['username']) ? $configData['username'] : '',
                        'password' => $this->smtpHelper->getPassword($storeId)
                    ];
                }

                if (isset($configData['protocol']) && $configData['protocol'] !== "") {
                    $options['ssl'] = $configData['protocol'];
                }

                $this->_smtpOptions[$storeId] = $options;
            }

            if (!isset($this->_smtpOptions[$storeId]['host']) || !$this->_smtpOptions[$storeId]['host']) {
                throw new \Zend_Exception('A host is necessary for smtp transport, but none was given');
            }

            if ($this->smtpHelper->versionCompare('2.3.0')) {
                $options = $this->_smtpOptions[$storeId];
                if (isset($options['auth'])) {
                    $options['connection_class'] = $options['auth'];
                    $options['connection_config'] = [
                        'username' => $options['username'],
                        'password' => $options['password']
                    ];
                    unset($options['auth']);
                    unset($options['username']);
                    unset($options['password']);
                }
                if (isset($options['ssl'])) {
                    $options['connection_config']['ssl'] = $options['ssl'];
                    unset($options['ssl']);
                }
                unset($options['type']);

                $options = new \Zend\Mail\Transport\SmtpOptions($options);

                $this->_transport = new \Zend\Mail\Transport\Smtp($options);
            } else {
                $this->_transport = new \Zend_Mail_Transport_Smtp($this->_smtpOptions[$storeId]['host'], $this->_smtpOptions[$storeId]);
            }
        }

        return $this->_transport;
    }

    /**
     * @param $message
     * @param $storeId
     * @return mixed
     */
    public function processMessage($message, $storeId)
    {
        if (!isset($this->_returnPath[$storeId])) {
            $this->_returnPath[$storeId] = $this->smtpHelper->getSmtpConfig('return_path_email', $storeId);
        }

        if ($this->_returnPath[$storeId]) {
            $message->setReturnPath($this->_returnPath[$storeId]);
        }

        return $message;
    }


    /**
     * @param $storeId
     * @return bool
     */
    public function isModuleEnable($storeId)
    {
        if (!isset($this->_moduleEnable[$storeId])) {
            $this->_moduleEnable[$storeId] = $this->smtpHelper->isEnabled($storeId);
        }

        return $this->_moduleEnable[$storeId];
    }

    /**
     * @param $storeId
     * @return bool|mixed
     */
    public function isDeveloperMode($storeId)
    {
        if (!isset($this->_developerMode[$storeId])) {
            $this->_developerMode[$storeId] = $this->smtpHelper->getDeveloperConfig('developer_mode', $storeId);
        }

        return $this->_developerMode[$storeId];
    }

    /**
     * @param $storeId
     * @return bool|mixed
     */
    public function isEnableEmailLog($storeId)
    {
        if (!isset($this->_emailLog[$storeId])) {
            $this->_emailLog[$storeId] = $this->smtpHelper->getConfigGeneral('log_email', $storeId);
        }

        return $this->_emailLog[$storeId];
    }

}
