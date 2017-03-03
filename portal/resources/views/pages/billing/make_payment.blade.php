@extends('layouts.full')
@section('content')
    <div class="container">
        <div class="row">
            <div class="col-md-6 col-md-offset-3">
                <div class="panel panel-default">
                    <div class="panel-heading new_subhead">
                        <h3 class="panel-title">
                            {{trans("headers.makeAPayment")}}
                        </h3>
                    </div>
                    <div class="panel-body">
                        {!! Form::open(['action' => 'BillingController@submitPayment', 'id' => 'paymentForm', 'class' => 'form', 'autocomplete' => 'on']) !!}
                        <div class="form-group">
                            <label for="payment_method">{{trans("billing.paymentMethod")}}</label>
                            <div class="form-group">
                                {!! Form::select("payment_method",$paymentMethods,'new_card',['id' => 'payment_method','class' => 'form-control']) !!}
                            </div>
                        </div>
                        <div class="form-group new_card">
                            <label for="cc_number">{{trans("billing.creditCardNumber")}}</label>
                            <div class="input-group">
                                {!! Form::tel("cc-number",null,['id' => 'cc-number', 'autocomplete' => 'cc-number', 'class' => 'cc-number form-control', 'placeholder' => trans("billing.creditCardNumber")]) !!}
                                <span class="input-group-addon"><i class="fa fa-cc" id="ccIcon" style="width: 25px;"></i></span>
                            </div>
                        </div>
                        <div class="form-group new_card">
                            <label for="name">{{trans("billing.nameOnCard")}}</label>
                            {!! Form::text("name",null,['id' => 'name', 'class' => 'form-control', 'placeholder' => trans("billing.nameOnCard")]) !!}
                        </div>
                        <div class="form-group new_card">
                            <label for="name">{{trans("billing.expirationDate")}}</label>
                            {!! Form::tel("expirationDate",null,['id' => 'expirationDate', 'class' => 'form-control', 'placeholder' => trans("billing.expirationDate")]) !!}
                        </div>
                        <div class="form-group">
                            <label for="amount">{{trans("billing.amountToPay")}}</label>
                            {!! Form::number("amount",number_format($billingDetails->balance_due,2,".",""),['id' => 'amount', 'class' => 'form-control', 'placeholder' => trans("billing.amountToPay"), 'steps' => 'any']) !!}
                        </div>
                        <div class="form-group new_card">
                            <label>
                                {!! Form::checkbox("makeAuto",1,false,['id' => 'makeAuto']) !!}
                                {{trans("billing.saveAsAutoPayMethod")}}
                            </label>
                            <p style="display: none;" id="autoPayDescription" class="help-block">{{trans("billing.autoPayDescription")}}</p>
                        </div>
                        <button type="submit" class="btn btn-primary new_submit">{{trans("billing.submitPayment")}}</button>
                        {!! Form::close() !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('additionalJS')
    <script src="/assets/js/vendor/jquery.payment/jquery.payment.min.js"></script>
    <script src="/assets/js/pages/billing/payment/page.js"></script>
    {!! JsValidator::formRequest('App\Http\Requests\CreditCardPaymentRequest','#paymentForm') !!}
@endsection