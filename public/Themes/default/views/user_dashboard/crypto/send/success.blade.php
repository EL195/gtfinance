@extends('user_dashboard.layouts.app')

@section('content')
<section class="min-vh-100">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-xs-12">
                <div class="card">
                    <div class="card-header">
                        <h3>@lang('message.dashboard.right-table.crypto-send') {{ $walletCurrencyCode }}</h3>
                    </div>

                    <div class="card-body">
                        <div class="text-center">
                            <div class="confirm-btns"><i class="fa fa-check"></i></div>
                        </div>

                        <div class="text-center">
                            <div class="h3 mt6 text-success">@lang('message.dashboard.send-request.request.confirmation.success')!</div>
                        </div>
                        
                        <div class="text-center">
                            <p><strong>{{ $walletCurrencyCode }} @lang('message.dashboard.crypto.send.success.sent-successfully').</strong></p>
                        </div>

                        <div class="text-center mt10">
                            <p><strong>@lang('message.dashboard.crypto.send.success.amount-added') {{ $confirmations }} @lang('message.dashboard.crypto.send.success.confirmations').</strong></p>
                        </div>

                        <div class="text-center mt10">
                            <h4>
                            @lang('message.dashboard.crypto.send.success.address'):
                            </h4>
                            <strong>
                            {!! $receiverAddress !!}
                            </strong>
                        </div>

                        <h5 class="text-center mt10">
                            <p>@lang('message.dashboard.crypto.send.confirm.sent-amount'): {{ moneyFormat($currencySymbol, number_format($amount, 8, '.', '')) }}</p>
                        </h5>


                        <div class="mt-5">
                            <div class="text-center">
                                <a href="{{ url("transactions/crypto-sent-received-print/".encrypt($transactionId)) }}" target="_blank" class="btn btn-grad mr-2 mt-4">
                                    <strong>@lang('message.dashboard.vouchers.success.print')</strong>
                                </a>
                                <a href="{{ url("/crpto/send/".encrypt($walletCurrencyCode)."/".encrypt($walletId)) }}" class="btn btn-grad ml-2 mt-4">
                                    <strong>@lang('message.dashboard.right-table.crypto-send') {{ $walletCurrencyCode }} @lang('message.dashboard.crypto.send.success.again')</strong>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@section('js')
<script type="text/javascript">
    function printFunc(){
        window.print();
    }
    //window.history.forward(1);
    $(document).ready(function() {
        window.history.pushState(null, "", window.location.href);
        window.onpopstate = function() {
            window.history.pushState(null, "", window.location.href);
        };
    });

    //disabling F5
    function disable_f5(e)
    {
      if ((e.which || e.keyCode) == 116)
      {
          e.preventDefault();
      }
    }
    $(document).ready(function(){
        $(document).bind("keydown", disable_f5);
    });

    //disabling ctrl+r
    function disable_ctrl_r(e)
    {
      if(e.keyCode == 82 && e.ctrlKey)
      {
        e.preventDefault();
      }
    }
    $(document).ready(function(){
        $(document).bind("keydown", disable_ctrl_r);
    });
</script>
@endsection