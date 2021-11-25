@extends('user_dashboard.layouts.app')

@section('content')
<section class="min-vh-100">
    <div class="my-30">
        <div class="container-fluid">
            <!-- Page title start -->
            <div>
                <h3 class="page-title">{{ __('Send Money') }}</h3>
            </div>
            <!-- Page title end-->
            <div class="row mt-4">
                <div class="col-lg-4">
                    <!-- Sub title start -->
                    <div class="mt-5">
                        <h3 class="sub-title">{{ __('Confirmation') }}</h3>
                        <p class="text-gray-500 text-16 text-justify">{{ __('Take a look before you send. Don\'t worry, if the recipient does not have an account, we will get them set up for free.') }}</p>
                    </div>
                    <!-- Sub title end-->
                </div>

                <div class="col-lg-8">
                    <div class="row">
                        <div class="col-xl-10">
							<div class="d-flex w-100 mt-4">
                                <ol class="breadcrumb w-100">
                                    <li class="breadcrumb-active text-white">{{ __('Create') }}</li>
                                    <li class="breadcrumb-first text-white">{{ __('Confirmation') }}</li>
                                    <li class="active">{{ __('Success') }}</li>
                                </ol>
                            </div>

                            <div class="bg-secondary rounded mt-5 shadow py-4 p-35">
                                @include('user_dashboard.layouts.common.alert')
                                <div>
									<div class="d-flex flex-wrap">
										<div>
											<p class="font-weight-600">
												@lang('message.dashboard.send-request.send.confirmation.send-to')
												<strong>{{ isset($transInfo['receiver']) ? $transInfo['receiver'] : '' }}</strong>
											</p>
										</div>
									</div>

									<div class="mt-4">
										<p class="sub-title">@lang('message.dashboard.confirmation.details')</p>
									</div>

									<div>
										<div class="d-flex flex-wrap justify-content-between mt-2">
											<div>
												<p>@lang('message.dashboard.send-request.send.confirmation.transfer-amount')</p>
											</div>

											<div class="pl-2">
												<p><span id="montant">{{ moneyFormat($transInfo['currSymbol'], formatNumber($transInfo['amount'])) }}</span></p>
											</div>
										</div>
										<!-- Montant converti -->
										<div class="d-flex flex-wrap justify-content-between mt-2">
											<div>
												<p>@lang('message.dashboard.confirmation.mtRecu')</p>
											</div>

											<div class="pl-2">
												<p class="finalValue">500</p>
											</div>
										</div>
										<div class="d-flex flex-wrap justify-content-between mt-2">
											<div>
												<p>@lang('message.dashboard.deposit.payment-method')</p>
											</div>

											<div class="pl-2">
												<p class="finalValue">{{ isset($transInfo['pay']) ? $transInfo['pay'] : '' }}</p>
											</div>
										</div>
                                        <div class="d-flex flex-wrap justify-content-between mt-2">
											<div>
												<p>@lang('message.dashboard.confirmation.taux')</p>
											</div>

											<div class="pl-2">
												<p id="rate">500</p>
											</div>
										</div>
                                        <!-- Montant converti -->
										<div class="d-flex flex-wrap justify-content-between mt-2">
											<div>
												<p>@lang('message.dashboard.confirmation.fee')</p>
											</div>

											<div class="pl-2">
												<p>{{ moneyFormat($transInfo['currSymbol'], formatNumber($transInfo['fee'])) }}</p>
											</div>
										</div>
									</div>
									<hr class="mb-2">

									<div class="d-flex flex-wrap justify-content-between">
										<div>
											<p class="font-weight-600">@lang('message.dashboard.confirmation.total')</p>
										</div>

										<div class="pl-2">
											<p class="totall font-weight-600">{{ moneyFormat($transInfo['currSymbol'], formatNumber($transInfo['totalAmount'])) }}</p>
										</div>
									</div>


									<div class="row m-0 mt-4 justify-content-between">
										<div>
											<a href="#" class="send-money-confirm-back-link">
												<p class="text-active text-underline send-money-confirm-back-button mt-2"><u><i class="fas fa-long-arrow-alt-left"></i> @lang('message.dashboard.button.back')</u></p>
											</a>
										</div>


										<div>
											<a href="{{url('send-money-confirm')}}" class="sendMoneyPaymentConfirmLink">
												<button class="btn btn-primary px-4 py-2 ml-2 float-right sendMoneyConfirm">
													<i class="fa fa-spinner fa-spin" style="display: none;" id="spinner"></i>
													<strong>
														<span class="sendMoneyConfirmText">
															@lang('message.dashboard.button.confirm') &nbsp;
														</span>
													</strong>
												</button>
											</a>
										</div>
									</div>
								</div>
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

	$(document).on('click', '.sendMoneyConfirm', function (e)
    {
    	$(".fa-spin").show()
    	$('.sendMoneyConfirmText').text("{{ __('Confirming...') }}");
    	$(this).attr("disabled", true);
    	$('.sendMoneyPaymentConfirmLink').click(function (e)
        {
            e.preventDefault();
        });

        //Make back button disabled and prevent click
        $('.send-money-confirm-back-button').attr("disabled", true).click(function (e)
        {
            e.preventDefault();
        });

        //Make back anchor prevent click
    	$('.send-money-confirm-back-link').click(function (e)
        {
            e.preventDefault();
        });
    });

	//Only go back by back button, if submit button is not clicked
    $(document).on('click', '.send-money-confirm-back-button', function (e)
    {
    	e.preventDefault();
        window.history.back();
    });
    
    

    // for selecting different controls
    var str = document.querySelector("#montant");
    console.log(str.innerHTML);
    var search1 = str.innerHTML.substring(2, 65);
    console.log(search1);

    var search = parseInt(search1);  
    console.log(search);
    
    
    var fromCurrecy = "CAD";//from
    var toCurrecy = "XAF"; // to
    var finalValue = document.querySelector(".finalValue");
    var totall = document.querySelector(".totall")
    var resultFrom;
    var resultTo;
    var searchValue;
    var rate = document.querySelector("#rate");
      
    // Event when currency is change
        resultFrom = fromCurrecy;
    // Event when currency is changed
        resultTo = toCurrecy;
        searchValue = search;
    
        fetch("https://api.exchangerate-api.com/v4/latest/CAD", {mode: 'cors'})
            .then(currency => {
                return currency.json();
            }).then(displayResults);
      
    // display results after convertion
    function displayResults(currency) {

        let fromRate = currency.rates[resultFrom];

        console.log(fromRate);
        let toRate = currency.rates[resultTo];
        console.log(toRate);
        toRateWith = "F CFA "+toRate;
        rate.innerHTML = toRateWith;
        // Create our number formatter.
        var formatter = new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'XAF',

        // These options are needed to round to whole numbers if that's what you want.
        //minimumFractionDigits: 0, // (this suffices for whole numbers, but will print 2500.10 as $2,500.1)
        //maximumFractionDigits: 0, // (causes 2500.99 to be printed as $2,501)
        });
        var fin =toRate * searchValue;
        var fin2 = formatter.format(fin); /* $2,500.00 */

        finalValue.innerHTML = fin2;
        totall.innerHTML = fin2;
        //finalAmount.style.display = "block";
    }
  

</script>
@endsection
