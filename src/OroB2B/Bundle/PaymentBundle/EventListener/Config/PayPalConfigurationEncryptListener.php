<?php

namespace OroB2B\Bundle\PaymentBundle\EventListener\Config;

use Oro\Bundle\SecurityBundle\Encoder\Mcrypt;
use Oro\Bundle\ConfigBundle\Event\ConfigGetEvent;
use Oro\Bundle\ConfigBundle\Config\ConfigManager;
use Oro\Bundle\ConfigBundle\Event\ConfigSettingsUpdateEvent;

use OroB2B\Bundle\PaymentBundle\DependencyInjection\Configuration;
use OroB2B\Bundle\PaymentBundle\DependencyInjection\OroB2BPaymentExtension;

class PayPalConfigurationEncryptListener
{
    /** @var MCrypt */
    protected $encoder;

    /**
     * @param Mcrypt $encoder
     */
    public function __construct(Mcrypt $encoder)
    {
        $this->encoder = $encoder;
    }

    /**
     * @param ConfigGetEvent $event
     */
    public function loadConfig(ConfigGetEvent $event)
    {
        $key = $event->getKey();

        if (!$this->isRequiredEncrypt($key)) {
            return;
        }

        $value = $this->getDirectValueFromEvent($event);
        $value = $this->encoder->decryptData($value);

        $this->setDirectValueToEvent($event, $value);
    }

    /**
     * @param ConfigSettingsUpdateEvent $event
     */
    public function beforeSave(ConfigSettingsUpdateEvent $event)
    {
        $settings = $event->getSettings();

        foreach ($settings as $configKey => $setting) {
            if (!$this->isRequiredEncrypt($configKey)) {
                continue;
            }

            $settings[$configKey]['value'] = $this->encoder->encryptData($setting['value']);
        }

        $event->setSettings($settings);
    }

    /**
     * @param string $configFullKey Config model name
     * @return bool
     */
    protected function isRequiredEncrypt($configFullKey)
    {
        list($extensionAlias, $configKey) = $this->parseConfigKey($configFullKey);

        if ($extensionAlias !== OroB2BPaymentExtension::ALIAS) {
            return false;
        }

        return in_array($configKey, $this->getConfigKeysToEncrypt(), true);
    }

    /**
     * @param string $configFullKey
     * @return array An array with 2 elements: [extensionAlias, configKey]
     */
    protected function parseConfigKey($configFullKey)
    {
        return explode(ConfigManager::SECTION_MODEL_SEPARATOR, (string)$configFullKey, 2);
    }

    /**
     * Get direct value of config
     *
     * @param ConfigGetEvent $event
     * @return mixed
     */
    protected function getDirectValueFromEvent(ConfigGetEvent $event)
    {
        $eventValue = $event->getValue();

        return $event->isFull() ? $eventValue['value'] : $eventValue;
    }

    /**
     * Set direct value to event
     *
     * @param ConfigGetEvent $event
     * @param mixed $value
     */
    protected function setDirectValueToEvent(ConfigGetEvent $event, $value)
    {
        $eventValue = $event->getValue();
        if ($event->isFull()) {
            $eventValue['value'] = $value;
        } else {
            $eventValue = $value;
        }

        $event->setValue($eventValue);
    }

    /**
     * Keys to encrypt
     *
     * @return array
     */
    protected function getConfigKeysToEncrypt()
    {
        return [
            Configuration::PAYPAL_PAYMENTS_PRO_LABEL_KEY,
            Configuration::PAYPAL_PAYMENTS_PRO_SORT_ORDER_KEY,
            Configuration::PAYPAL_PAYMENTS_PRO_PARTNER_KEY,
            Configuration::PAYPAL_PAYMENTS_PRO_USER_KEY,
            Configuration::PAYPAL_PAYMENTS_PRO_VENDOR_KEY,
            Configuration::PAYPAL_PAYMENTS_PRO_PASSWORD_KEY,
            Configuration::PAYPAL_PAYMENTS_PRO_PAYMENT_ACTION_KEY,
            Configuration::PAYPAL_PAYMENTS_PRO_PROXY_HOST_KEY,
            Configuration::PAYPAL_PAYMENTS_PRO_PROXY_PORT_KEY,

            Configuration::PAYFLOW_GATEWAY_LABEL_KEY,
            Configuration::PAYFLOW_GATEWAY_SORT_ORDER_KEY,
            Configuration::PAYFLOW_GATEWAY_PARTNER_KEY,
            Configuration::PAYFLOW_GATEWAY_USER_KEY,
            Configuration::PAYFLOW_GATEWAY_VENDOR_KEY,
            Configuration::PAYFLOW_GATEWAY_PASSWORD_KEY,
            Configuration::PAYFLOW_GATEWAY_PAYMENT_ACTION_KEY,
            Configuration::PAYFLOW_GATEWAY_PROXY_HOST_KEY,
            Configuration::PAYFLOW_GATEWAY_PROXY_PORT_KEY,
        ];
    }
}
