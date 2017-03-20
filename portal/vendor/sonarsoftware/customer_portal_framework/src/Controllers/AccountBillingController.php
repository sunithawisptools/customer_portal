<?php

namespace SonarSoftware\CustomerPortalFramework\Controllers;

use Exception;
use SonarSoftware\CustomerPortalFramework\Exceptions\ApiException;
use SonarSoftware\CustomerPortalFramework\Helpers\HttpHelper;
use SonarSoftware\CustomerPortalFramework\Models\CreditCard;

class AccountBillingController
{
    private $httpHelper;
    /**
     * AccountAuthenticationController constructor.
     */
    public function __construct()
    {
        $this->httpHelper = new HttpHelper();
    }

    /*
     * GET functions
     */

    /**
     * Get a list of the invoices for the account (see https://sonar.software/apidoc/#api-Account_Invoices-GetAccountInvoices)
     * @param $accountID
     * @param int $page
     * @return mixed
     * @throws ApiException
     */
    public function getInvoices($accountID, $page = 1)
    {
        return $this->httpHelper->get("accounts/" . intval($accountID) . "/invoices", $page);
    }

    /**
     * Returns an invoice as a base64 encoded string (see https://sonar.software/apidoc/index.html#api-Account_Invoices-GetInvoicePdf)
     * @param $accountID
     * @param $invoiceID
     * @return mixed
     * @throws ApiException
     */
    public function getInvoicePdf($accountID, $invoiceID)
    {
        return $this->httpHelper->get("accounts/" . intval($accountID) . "/invoices/" . intval($invoiceID) . "/pdf");
    }

    /**
     * Get a list of debits for the account (see https://sonar.software/apidoc/#api-Account_Transactions-GetAccountDebits)
     * @param $accountID
     * @param int $page
     * @return mixed
     * @throws ApiException
     */
    public function getDebits($accountID, $page = 1)
    {
        return $this->httpHelper->get("accounts/" . intval($accountID) . "/transactions/debits", $page);
    }

    /**
     * Get a list of discounts for the account (see https://sonar.software/apidoc/#api-Account_Transactions-GetAccountDiscounts)
     * @param $accountID
     * @param int $page
     * @return mixed
     * @throws ApiException
     */
    public function getDiscounts($accountID, $page = 1)
    {
        return $this->httpHelper->get("accounts/" . intval($accountID) . "/transactions/discounts", $page);
    }

    /**
     * Get a list of payments for the account (see https://sonar.software/apidoc/#api-Account_Transactions-GetAccountPayments)
     * @param $accountID
     * @param int $page
     * @return mixed
     * @throws ApiException
     */
    public function getPayments($accountID, $page = 1)
    {
        return $this->httpHelper->get("accounts/" . intval($accountID) . "/transactions/payments", $page);
    }

    /**
     * Get billing details for the account (balance due, next recurring charge amount, and next bill date.) It is possible for next_bill_date to be null.
     * @param $accountID
     * @return mixed
     * @throws ApiException
     */
    public function getAccountBillingDetails($accountID)
    {
        return $this->httpHelper->get("accounts/" . intval($accountID) . "/billing_details");
    }

    /**
     * Get a list of unexpired credit cards on the account (see https://sonar.software/apidoc/index.html#api-Account_Payment_Methods-GetAccountPaymentMethods). This does not return CreditCard objects, but just the raw API return. This is because the credit card objects
     * don't really match up - we can't access the credit card number, for example.
     * @param $accountID
     * @return array
     * @throws ApiException
     */
    public function getValidCreditCards($accountID)
    {
        $return = [];
        $result = $this->httpHelper->get("accounts/" . intval($accountID) . "/payment_methods");
        foreach ($result as $datum)
        {
            if ($datum->type != "credit card")
            {
                continue;
            }
            try {
                 if (\Inacho\CreditCard::validDate($datum->expiration_year, sprintf("%02d", $datum->expiration_month)) !== true)
                 {
                     continue;
                 }
            }
            catch (Exception $e)
            {
                continue;
            }

            array_push($return,$datum);
        }
        return $return;
    }

    /*
     * POST functions
     */

    /**
     * Make a one time payment with a credit card and, optionally, save it as a future automatic payment method. This should not be used to pay with an existing payment method.
     * @param $accountID - The account ID in Sonar
     * @param CreditCard $creditCard - A CreditCard object
     * @param $amount - The amount in the currency used in Sonar as a float
     * @param bool $saveAndMakeAuto - If this is true, save the card if it successfully runs
     * @return mixed
     * @throws ApiException
     */
    public function makeCreditCardPayment($accountID, CreditCard $creditCard, $amount, $saveAndMakeAuto = false)
    {
        $result = $this->httpHelper->post("/accounts/" . intval($accountID) . "/transactions/one_time_credit_card_payment", [
            'number' => $creditCard->getNumber(),
            'expiration_month' => $creditCard->getExpirationMonth(),
            'expiration_year' => $creditCard->getExpirationYear(),
            'amount' => trim($amount),
            'name_on_account' => $creditCard->getName(),
            'line1' => $creditCard->getLine1(),
            'city' => $creditCard->getCity(),
            'state' => $creditCard->getState(),
            'zip' => $creditCard->getZip(),
            'country' => $creditCard->getCountry(),
        ]);

        if ($result->success === true && $saveAndMakeAuto === true)
        {
            try {
                $this->createCreditCard($accountID, $creditCard);
            }
            catch (Exception $e)
            {
                //Not much we can do here, the payment has already been run. Very unlikely payment will work and saving will fail.
            }
        }
        unset($creditCard);

        return $result;
    }

    /**
     * Make a payment using an existing payment method ID (see https://sonar.software/apidoc/index.html#api-Account_Transactions-PostAccountPayment)
     * @param $accountID
     * @param $paymentMethodID
     * @param $amount
     * @return mixed
     * @throws ApiException
     */
    public function makePaymentUsingExistingPaymentMethod($accountID, $paymentMethodID, $amount)
    {
        return $this->httpHelper->post("/accounts/" . intval($accountID) . "/transactions/payments", [
            'payment_method_id' => intval($paymentMethodID),
            'amount' => trim($amount),
            'auto_apply' => true,
        ]);
    }

    /**
     * Add a new credit card to a customer account (see https://sonar.software/apidoc/#api-Account_Payment_Methods-PostAccountPaymentMethod)
     * @param $accountID
     * @param CreditCard $creditCard
     * @param bool $auto - Whether or not the card is set for auto pay
     * @return mixed
     * @throws ApiException
     */
    public function createCreditCard($accountID, CreditCard $creditCard, $auto = true)
    {
        return $this->httpHelper->post("/accounts/" . intval($accountID) . "/payment_methods", [
            'type' => 'credit card',
            'account_number' => $creditCard->getNumber(),
            'expiration_month' => $creditCard->getExpirationMonth(),
            'expiration_year' => $creditCard->getExpirationYear(),
            'name_on_account' => $creditCard->getName(),
            'line1' => $creditCard->getLine1(),
            'city' => $creditCard->getCity(),
            'state' => $creditCard->getState(),
            'zip' => $creditCard->getZip(),
            'country' => $creditCard->getCountry(),
            'auto' => (bool)$auto,
        ]);
    }

    /**
     * Store an externally made PayPal payment into Sonar. The payment must have been run using the same API credentials as configured in Sonar, and
     * you must have PayPal enabled in Sonar (see https://sonar.software/apidoc/#api-Account_Transactions-PostExternalPayPal)
     * @param $accountID
     * @param $amount
     * @param $transactionID
     * @return mixed
     * @throws ApiException
     */
    public function storePayPalPayment($accountID, $amount, $transactionID)
    {
        return $this->httpHelper->post("/accounts/" . intval($accountID) . "/transactions/paypal_payments", [
            'amount' => number_format($amount,2,".",""),
            'transaction_id' => trim($transactionID)
        ]);
    }

    /*
     * PATCH functions
     */

    /**
     * Set the 'auto' state on a payment method (see https://sonar.software/apidoc/#api-Account_Payment_Methods-PatchAccountPaymentMethod)
     * @param $accountID
     * @param $paymentMethodID
     * @param $auto
     * @return mixed
     * @throws ApiException
     */
    public function setAutoOnPaymentMethod($accountID, $paymentMethodID, $auto)
    {
        return $this->httpHelper->patch("/accounts/" . intval($accountID) . "/payment_methods/" . intval($paymentMethodID) . "/toggle_auto", [
            'auto' => (boolean)$auto
        ]);
    }

    /*
     * DELETE functions
     */

    /**
     * Delete a payment method (see https://sonar.software/apidoc/#api-Account_Payment_Methods-DeleteAccountPaymentMethod)
     * @param $accountID
     * @param $paymentMethodID
     * @return mixed
     * @throws ApiException
     */
    public function deletePaymentMethodByID($accountID, $paymentMethodID)
    {
        return $this->httpHelper->delete("/accounts/" . intval($accountID) . "/payment_methods/" . intval($paymentMethodID));
    }
}