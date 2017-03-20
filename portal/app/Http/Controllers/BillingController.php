<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateCreditCardRequest;
use App\Http\Requests\CreditCardPaymentRequest;
use App\Http\Requests\PaymentMethodDeleteRequest;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;

use App\Http\Requests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Inacho\CreditCard as CreditCardValidator;
use InvalidArgumentException;
use SonarSoftware\CustomerPortalFramework\Controllers\AccountBillingController;
use SonarSoftware\CustomerPortalFramework\Models\CreditCard;

class BillingController extends Controller
{
    private $accountBillingController;
    public function __construct()
    {
        $this->accountBillingController = new AccountBillingController();
    }
    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index()
    {
        $billingDetails = $this->getAccountBillingDetails();

        $values = [
            'amount_due' => $billingDetails->balance_due,
            'next_bill_date' => $billingDetails->next_bill_date,
            'next_bill_amount' => $billingDetails->next_recurring_charge_amount,
            'total_balance' => $billingDetails->total_balance,
            'available_funds' => $billingDetails->available_funds,
            'payment_past_due' => $this->isPaymentPastDue(),
            'balance_minus_funds' => bcsub($billingDetails->total_balance,$billingDetails->available_funds,2)
        ];

        $invoices = $this->getInvoices();
        $transactions = $this->getTransactions();
        $paymentMethods = $this->getPaymentMethods();

        return view("pages.billing.index", compact('values','invoices','transactions','paymentMethods'));
    }

    /**
     * Get an invoice PDF as base64, and decode it
     * @param $invoiceID
     * @return $this|\Illuminate\Http\Response
     */
    public function getInvoicePdf($invoiceID)
    {
        try {
            $data = $this->accountBillingController->getInvoicePdf(get_user()->account_id, $invoiceID);
        }
        catch (Exception $e)
        {
            Log::error($e->getMessage());
            return redirect()->back()->withErrors(trans("errors.failedToDownloadInvoice"));
        }

        return response()->make(base64_decode($data->base64),200,[
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=Invoice $invoiceID.pdf",
        ]);
    }
    
    /**
     * Make payment page
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function makePayment()
    {
        $billingDetails = $this->getAccountBillingDetails();
        $paymentMethods = $this->generatePaymentMethodListForPaymentPage();

        return view('pages.billing.make_payment',compact('billingDetails','paymentMethods'));
    }

    /**
     * Process a submitted payment
     * @param CreditCardPaymentRequest $request
     * @return $this
     */
    public function submitPayment(CreditCardPaymentRequest $request)
    {
        switch ($request->input('payment_method'))
        {
            case "new_card":
                try {
                    $result = $this->payWithNewCreditCard($request);
                }
                catch (Exception $e)
                {
                    Log::error($e->getMessage());
                    return redirect()->back()->withErrors($e->getMessage())->withInput();
                }
                break;
            case "paypal":
                $paypalController = new PayPalController();
                try {
                    $redirectLink = $paypalController->generateApprovalLink($request->input('amount'));
                }
                catch (Exception $e)
                {
                    return redirect()->back()->withErrors(trans("errors.paypalFailed"));
                }
                return redirect()->to($redirectLink);
                break;
            default:
                //If we've made it here, this is an existing payment method
                try {
                    $result = $this->payWithExistingCreditCard($request);
                }
                catch (Exception $e)
                {
                    Log::error($e->getMessage());
                    return redirect()->back()->withErrors($e->getMessage())->withInput();
                }
                break;
        }

        $this->clearBillingCache();
        return view("pages.billing.payment_success",compact('result'));
    }

    /**
     * Delete an existing payment method from a customer account.
     * @param $id
     * @return $this
     */
    public function deletePaymentMethod($id)
    {
        $paymentMethods = $this->getPaymentMethods();
        foreach ($paymentMethods as $paymentMethod)
        {
            if ((int)$paymentMethod->id === (int)$id)
            {
                try {
                    $this->accountBillingController->deletePaymentMethodByID(get_user()->account_id, $id);
                    $this->clearBillingCache();
                    return redirect()->action("BillingController@index")->with('success',trans("billing.creditCardDeleted"));
                }
                catch (Exception $e)
                {
                    //
                }
            }
        }
        
        return redirect()->back()->withErrors(trans("errors.paymentMethodNotFound"));
    }

    /**
     * Toggle the auto pay setting on a payment method
     * @param $id
     * @return $this|\Illuminate\Http\RedirectResponse
     */
    public function toggleAutoPay($id)
    {
        $paymentMethods = $this->getPaymentMethods();
        foreach ($paymentMethods as $paymentMethod)
        {
            if ((int)$paymentMethod->id === (int)$id)
            {
                try {
                    $existingAutoSetting = (boolean)$paymentMethod->auto;
                    $this->accountBillingController->setAutoOnPaymentMethod(get_user()->account_id, $paymentMethod->id, !$existingAutoSetting);
                    $this->clearBillingCache();
                    if ($existingAutoSetting == true)
                    {
                        return redirect()->action("BillingController@index")->with('success',trans("billing.autoPayDisabled"));
                    }
                    return redirect()->action("BillingController@index")->with('success',trans("billing.autoPayEnabled"));
                }
                catch (Exception $e)
                {
                    //
                }
            }
        }

        return redirect()->back()->withErrors(trans("errors.paymentMethodNotFound"));
    }

    /**
     * Show page to create a new payment method
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function createPaymentMethod()
    {
        return view("pages.billing.create_payment_method");
    }

    /**
     * Store a new credit card
     * @param CreateCreditCardRequest $request
     * @return $this|mixed
     */
    public function storePaymentMethod(CreateCreditCardRequest $request)
    {
        try {
            $card = $this->createCreditCardObjectFromRequest($request);
        }
        catch (Exception $e)
        {
            return redirect()->back()->withErrors($e->getMessage())->withInput();
        }

        try {
            $this->accountBillingController->createCreditCard(get_user()->account_id, $card, (bool)$request->input('auto'));
        }
        catch (Exception $e)
        {
            return redirect()->back()->withErrors(trans("errors.failedToCreateCard"))->withInput();
        }
        
        unset($creditCard);
        unset($request);

        $this->clearBillingCache();
        return redirect()->action("BillingController@index")->with('success',trans("billing.cardAdded"));
    }

    /**
     * Make a payment with an existing payment method
     * @param $request
     * @return mixed
     * @throws Exception
     */
    private function payWithExistingCreditCard($request)
    {
        
        try {
            $result = $this->accountBillingController->makePaymentUsingExistingPaymentMethod(get_user()->account_id, intval($request->input('payment_method')), trim($request->input('amount')));
        }
        catch (Exception $e)
        {
            Log::error($e->getMessage());
            throw new Exception(trans("billing.paymentFailedTryAnother"));
        }

        if ($result->success !== true)
        {
            throw new Exception(trans("billing.paymentFailedTryAnother"));
        }

        return $result;
    }

    /**
     * Make a payment with a new credit card
     * @param CreditCardPaymentRequest $request
     * @return mixed
     */
    private function payWithNewCreditCard(CreditCardPaymentRequest $request)
    {
        try {
            $creditCard = $this->createCreditCardObjectFromRequest($request);
        }
        catch (Exception $e)
        {
            return redirect()->back()->withErrors($e->getMessage());
        }

        try {
            $result = $this->accountBillingController->makeCreditCardPayment(get_user()->account_id, $creditCard, $request->input('amount'), (boolean)$request->input('makeAuto'));
        }
        catch (Exception $e)
        {
            throw new InvalidArgumentException(trans("billing.errorSubmittingPayment"));
        }

        if ($result->success !== true)
        {
            throw new InvalidArgumentException(trans("errors.paymentFailed"));
        }

        unset($creditCard);
        unset($request);

        return $result;
    }


    /**
     * Get account billing details
     * @return mixed
     */
    private function getAccountBillingDetails()
    {
        if (!Cache::tags("billing.details")->has(get_user()->account_id))
        {
            $billingDetails = $this->accountBillingController->getAccountBillingDetails(get_user()->account_id);
            Cache::tags("billing.details")->put(get_user()->account_id, $billingDetails, 10);
        }

        return Cache::tags("billing.details")->get(get_user()->account_id);
    }

    /**
     * Get the invoice list for a user. This will only retrieve the last 100.
     * @return mixed
     */
    private function getInvoices()
    {
        if (!Cache::tags("billing.invoices")->has(get_user()->account_id))
        {
            $invoicesToReturn = [];
            $invoices = $this->accountBillingController->getInvoices(get_user()->account_id);
            foreach ($invoices as $invoice)
            {
                //This check is here because this property did not exist prior to Sonar 0.6.6
                if (property_exists($invoice,"void"))
                {
                    if ($invoice->void != 1)
                    {
                        array_push($invoicesToReturn,$invoice);
                    }
                }
                else
                {
                    array_push($invoicesToReturn,$invoice);
                }
            }
            Cache::tags("billing.invoices")->put(get_user()->account_id, $invoicesToReturn, 10);
        }

        return Cache::tags("billing.invoices")->get(get_user()->account_id);
    }

    /**
     * Get the transaction list for a user. This will only display the last 100 currently.
     * @return mixed
     */
    private function getTransactions()
    {
        
        if (!Cache::tags("billing.transactions")->has(get_user()->account_id))
        {
            $transactions = [];
            $debits = $this->accountBillingController->getDebits(get_user()->account_id);
            foreach ($debits as $debit)
            {
                array_push($transactions,[
                    'type' => 'debit',
                    'amount' => $debit->amount,
                    'date' => $debit->date,
                    'taxes' => $debit->taxes,
                    'description' => $debit->description,
                ]);
            }

            $discounts = $this->accountBillingController->getDiscounts(get_user()->account_id);
            foreach ($discounts as $discount)
            {
                array_push($transactions,[
                    'type' => 'discount',
                    'amount' => $discount->amount,
                    'date' => $discount->date,
                    'taxes' => $discount->taxes,
                    'description' => $discount->description,
                ]);
            }

            $payments = $this->accountBillingController->getPayments(get_user()->account_id);
            foreach ($payments as $payment)
            {
                if ($payment->success === true && $payment->reversed === false)
                {
                    array_push($transactions,[
                        'type' => 'payment',
                        'amount' => $payment->amount,
                        'payment_type' => $payment->type,
                        'date' => $payment->date,
                    ]);
                }
            }

            /**
             * After sorting, limit transactions to 100. You can change this number if you want to show more,
             * but bear in mind that the queries above are limited to 100 items each, so this will never be
             * more than 300 at the most.
             */
            usort($transactions, function($a, $b) {
                return strtotime($b['date']) - strtotime($a['date']);
            });
            $transactions = array_slice($transactions, 0, 100);

            Cache::tags("billing.transactions")->put(get_user()->account_id, $transactions, 10);
        }

        return Cache::tags("billing.transactions")->get(get_user()->account_id);
    }

    /**
     * Check if the payment is past due
     * @return bool
     */
    private function isPaymentPastDue()
    {
        $invoices = $this->getInvoices();
        $now = Carbon::now(Config::get("app.timezone"));

        foreach ($invoices as $invoice)
        {
            if ($invoice->remaining_due <= 0)
            {
                continue;
            }
            $invoiceDueDate = new Carbon($invoice->due_date);
            if ($invoiceDueDate->lte($now))
            {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all the payment method options
     * @return mixed
     */
    private function getPaymentMethods()
    {
        if (!Cache::tags("billing.payment_methods")->has(get_user()->account_id))
        {
            $validAccountMethods = $this->accountBillingController->getValidCreditCards(get_user()->account_id);
            Cache::tags("billing.payment_methods")->put(get_user()->account_id, $validAccountMethods, 10);
        }
        return Cache::tags("billing.payment_methods")->get(get_user()->account_id);
    }

    /**
     * Generate a formatted list of payment methods for the make payment page
     * @return array
     */
    private function generatePaymentMethodListForPaymentPage()
    {
        $paymentMethods = [];
        $validAccountMethods = $this->getPaymentMethods();
        foreach ($validAccountMethods as $validAccountMethod)
        {
            $paymentMethods[$validAccountMethod->id] = trans("billing.payUsingExistingCard", ['card' => "****" . $validAccountMethod->identifier . " (" . sprintf("%02d", $validAccountMethod->expiration_month) . " / " . $validAccountMethod->expiration_year . ")"]);
        }

        if (Config::get("customer_portal.paypal_enabled") === true)
        {
            $paymentMethods['paypal'] = trans("billing.payWithPaypal");
        }

        $paymentMethods['new_card'] = trans("billing.payWithNewCard");

        $paymentMethods = array_reverse($paymentMethods,true);

        return $paymentMethods;
    }

    /**
     * Clear all the cached billing items.
     */
    public function clearBillingCache()
    {
        Cache::tags("billing.details")->forget(get_user()->account_id);
        Cache::tags("billing.invoices")->forget(get_user()->account_id);
        Cache::tags("billing.transactions")->forget(get_user()->account_id);
        Cache::tags("billing.payment_methods")->forget(get_user()->account_id);
    }

    /**
     * Create a credit card object from a request containing cc-number, name, and expirationDate
     * @param $request
     * @return CreditCard
     */
    private function createCreditCardObjectFromRequest($request)
    {
        $card = CreditCardValidator::validCreditCard(trim(str_replace(" ","",$request->input('cc-number'))));
        if ($card['valid'] !== true)
        {
            throw new InvalidArgumentException(trans("errors.invalidCreditCardNumber"));
        }

        $expiration = $request->input('expirationDate');
        $boom = explode(" / ",$expiration);
        $month = ltrim(trim($boom[0]),0);
        $year = trim($boom[1]);
        if (strlen($year) == 2)
        {
            $now = Carbon::now(Config::get("app.timezone"));
            $year = substr($now->year,0,2) . $year;
        }

        if (CreditCardValidator::validDate($year, $month) !== true)
        {
            throw new InvalidArgumentException(trans("errors.invalidExpirationDate"));
        }

        $creditCard = new CreditCard([
            'name' => $request->input('name'),
            'number' => $card['number'],
            'expiration_month' => intval($month),
            'expiration_year' => $year,
            'line1' => $request->input('line1'),
            'city' => $request->input('city'),
            'state' => $request->input('state'),
            'zip' => $request->input('zip'),
            'country' => $request->input('country'),
        ]);

        return $creditCard;
    }
}
