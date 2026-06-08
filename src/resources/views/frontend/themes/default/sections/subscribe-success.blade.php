@extends($themeManager->view('layouts.main'))

@section('content')
<div class="container-fluid container-wrapper my-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card text-center">
                <div class="card-body p-5">
                    @if(isset($logo))
                        <img src="{{ $logo }}" alt="Logo" class="mb-3" style="max-width: 150px;">
                    @endif
                    <h5 class="card-title">{{ $title ?? translate('Success') }}</h5>
                    <p class="card-text my-4">{{ $message ?? translate('Operation completed successfully.') }}</p>
                    <a href="{{ url('/') }}" class="i-btn btn--primary bg--gradient btn--xl pill w-max-content">{{ translate('Go to Homepage') }}</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
