@extends($themeManager->view('layouts.main'))

@section('content')
<div class="container-fluid container-wrapper my-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card text-center shadow-sm">
                <div class="card-body p-5">
                    @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif
                    <h5 class="card-title mb-4">{{ $title }}</h5>
                    <p class="card-text mb-4">
                        {{ translate('Subscribe to receive updates from ') }} {{ $user->name }}.
                        @if($group)
                            <br><strong>{{ translate('List:') }} {{ $group->name }}</strong>
                        @endif
                    </p>
                    <form action="{{ route('subscribe.post') }}" method="POST">
                        @csrf
                        <input type="hidden" name="user_uid" value="{{ $user->uid }}">
                        @if($group)
                            <input type="hidden" name="group_uid" value="{{ $group->uid }}">
                        @endif
                        <div class="mb-3 text-start">
                            <label for="first_name" class="form-label">{{ translate('First Name') }}</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" placeholder="{{ translate('Enter your first name') }}">
                        </div>
                        <div class="mb-3 text-start">
                            <label for="last_name" class="form-label">{{ translate('Last Name') }}</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" placeholder="{{ translate('Enter your last name') }}">
                        </div>
                        <div class="mb-3 text-start">
                            <label for="email" class="form-label">{{ translate('Email address') }} <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" required placeholder="{{ translate('Enter your email address') }}">
                        </div>
                        <button type="submit" class="i-btn btn--primary bg--gradient btn--xl pill w-100">{{ translate('Subscribe') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
