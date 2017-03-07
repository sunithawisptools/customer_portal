@extends('layouts.no_nav')
@section('content')
    <div class="container">
        <div class="row">
            <div class="col-md-6 col-md-offset-3 give_bottom_room">
                @if(file_exists(base_path("/public/assets/images/logo.png")))
                    <img src="/assets/images/logo.png">
                @else
                    <img src="/assets/images/transparent_logo.png">
                @endif
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 col-md-offset-3">
                <div class="panel panel-default new_col">
                    <div class="panel-heading new_subhead">
                        <h3 class="panel-title">{{trans("headers.registerYourAccount")}}</h3>
                    </div>
                    <div class="panel-body">
                        <p>
                            {{trans("register.registrationDescription")}}
                        </p>
                        {!! Form::open(['action' => 'AuthenticationController@lookupEmail', 'id' => 'emailLookupForm', 'method' => 'post']) !!}
                        <div class="form-group">
                            <label for="email" class="new_tabs">{{trans("register.email")}}</label>
                            {!! Form::email("email",null,['id' => 'email', 'class' => 'form-control', 'placeholder' => trans("register.email")]) !!}
                        </div>
                        <button type="submit" class="btn btn-primary new_submit">{{trans("actions.lookupEmail")}}</button>
                        {!! Form::close() !!}
                    </div>
                </div>
                <p class="new_label">
                    <a href="{{action("AuthenticationController@index")}}">{{trans("register.back")}}</a>
                </p>
            </div>
        </div>
    </div>
@endsection
        @section('additionalJS')
    {!! JsValidator::formRequest('App\Http\Requests\LookupEmailRequest','#emailLookupForm') !!}
@endsection
@section('additionalCSS')
    <link rel="stylesheet" href="/assets/css/pages/index.css">
@endsection