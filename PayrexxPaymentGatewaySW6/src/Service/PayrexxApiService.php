<?php

namespace PayrexxPaymentGateway\Service;

use Payrexx\Communicator;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class PayrexxApiService
{
    /**
     * @var EntityRepository
     */
    protected $customerRepository;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var ConfigService
     */
    protected $configService;

    /**
     * Constructor
     *
     * @param EntityRepository $customerRepository
     * @param LoggerInterface $logger
     */
    public function __construct(EntityRepository $customerRepository, LoggerInterface $logger, ConfigService $configService)
    {
        $this->customerRepository = $customerRepository;
        $this->logger = $logger;
        $this->configService = $configService;
    }

    /**
     * @return \Payrexx\Payrexx
     */
    private function getInterface($salesChannelId): \Payrexx\Payrexx
    {
        $config = $this->configService->getPluginConfiguration($salesChannelId);
        $platform = !empty($config['platform']) ? $config['platform'] : Communicator::API_URL_BASE_DOMAIN;
        return new \Payrexx\Payrexx($config['instanceName'], $config['apiKey'], '', $platform);
    }

    /**
     * Create a checkout page in Payrexx (Payrexx Gateway)
     *
     * @param $orderNumber
     * @param $amount
     * @param $currency
     * @param $paymentMean
     * @param $user
     * @param $urls
     * @param string $salesChannelId
     * @return Gateway
     *
     */
    public function createPayrexxGateway(string $orderNumber, float $amount, string $currency, string $paymentMean, array $customer, string $url, string $salesChannelId)
    {
        $payrexx = $this->getInterface($salesChannelId);
        $gateway = new \Payrexx\Models\Request\Gateway();
        $gateway->setAmount($amount * 100);
        $gateway->setCurrency($currency);
        $gateway->setSuccessRedirectUrl($url);
        $gateway->setFailedRedirectUrl($url);
        $gateway->setCancelRedirectUrl($url);
        $gateway->setSkipResultPage(true);

        $gateway->setPsp([]);
        $gateway->setPm([$paymentMean]);
        $gateway->setReferenceId($orderNumber);
        $gateway->setValidity(15);

        $gateway->addField('forename', $customer['forename']);
        $gateway->addField('surname', $customer['surname']);
        $gateway->addField('company', $customer['company']);
        $gateway->addField('street', $customer['street']);
        $gateway->addField('postcode', $customer['postcode']);
        $gateway->addField('place', $customer['place']);
        $gateway->addField('email', $customer['email']);
        $gateway->addField('custom_field_1', $orderNumber, [
            1 => 'Shopware Bestellnummer',
            2 => 'Shopware Order ID',
        ]);

        try {
            return $payrexx->create($gateway);
        } catch (\Payrexx\PayrexxException $e) {
            $this->logger->error($e->getMessage(), [$e]);
        }
        return null;
    }

    /**
     * @param $gatewayId
     * @param string $salesChannelId
     * @return \Payrexx\Models\Request\Gateway|bool
     */
    public function getPayrexxGateway($gatewayId, string $salesChannelId)
    {
        if (!$gatewayId) {
            return false;
        }
        $payrexx = $this->getInterface($salesChannelId);
        $gateway = new \Payrexx\Models\Request\Gateway();
        $gateway->setId($gatewayId);
        try {
            $payrexxGateway = $payrexx->getOne($gateway);
            return $payrexxGateway;
        } catch (\Payrexx\PayrexxException $e) {
        }
        return false;
    }

    /**
     * capture a Transaction
     *
     * @param integer $gatewayId The Payrexx Gateway ID
     * @param string $salesChannelId
     * @return string
     */
    public function captureTransaction($transactionId, $salesChannelId)
    {
        if (!$transactionId) {
            return false;
        }
        $payrexx = $this->getInterface($salesChannelId);

        $transaction = new \Payrexx\Models\Request\Transaction();
        $transaction->setId($transactionId);

        try {
            $response = $payrexx->capture($transaction);
            return $response;
        } catch (\Payrexx\PayrexxException $e) {
            return $e->getMessage();
        }
    }

    public function getTransactionByGateway($payrexxGateway, $salesChannelId): ?\Payrexx\Models\Response\Transaction
    {
        if (!in_array($payrexxGateway->getStatus(), ['confirmed', 'waiting'])) {
            return null;
        }
        $invoices = $payrexxGateway->getInvoices();

        if (!$invoices || !$invoice = $invoices[0]) {
            return null;
        }
        $transactions = $invoice['transactions'];
        if (!$transactions || !$transactions[0]['id']) {
            return null;
        }

        return $this->getPayrexxTransaction($transactions[0]['id'], $salesChannelId);
    }

    public function getPayrexxTransaction(int $payrexxTransactionId, $salesChannelId): ?\Payrexx\Models\Response\Transaction
    {
        $payrexx = $this->getInterface($salesChannelId);

        $payrexxTransaction = new \Payrexx\Models\Request\Transaction();
        $payrexxTransaction->setId($payrexxTransactionId);

        try {
            $response = $payrexx->getOne($payrexxTransaction);
            return $response;
        } catch(\Payrexx\PayrexxException $e) {
            return null;
        }
    }
}
