@extends($themeManager->view('layouts.main'))

@section('content')
<style>
    .subscribe-wrapper {
        min-height: 80vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 40px 20px;
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    }
    .subscribe-card {
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid rgba(255, 255, 255, 0.4);
        border-radius: 24px;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
        padding: 50px;
        max-width: 500px;
        width: 100%;
        text-align: center;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .subscribe-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.12);
    }
    .subscribe-title {
        font-family: 'Inter', sans-serif;
        font-weight: 700;
        font-size: 1.8rem;
        color: #2d3748;
        margin-bottom: 10px;
    }
    .subscribe-subtitle {
        font-family: 'Inter', sans-serif;
        font-weight: 400;
        font-size: 1rem;
        color: #718096;
        margin-bottom: 35px;
    }
    .subscribe-subtitle strong {
        color: #4a5568;
        font-weight: 600;
    }
    .form-floating {
        position: relative;
        margin-bottom: 20px;
        text-align: left;
    }
    .form-control-custom {
        width: 100%;
        padding: 16px 20px;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        font-size: 1rem;
        background: #f8fafc;
        transition: all 0.3s ease;
        color: #2d3748;
    }
    .form-control-custom:focus {
        outline: none;
        border-color: #667eea;
        background: #ffffff;
        box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
    }
    .form-label-custom {
        display: block;
        font-weight: 500;
        margin-bottom: 8px;
        color: #4a5568;
        font-size: 0.95rem;
    }
    .btn-subscribe {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        border-radius: 12px;
        padding: 16px 30px;
        font-size: 1.1rem;
        font-weight: 600;
        width: 100%;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        margin-top: 10px;
    }
    .btn-subscribe:hover {
        transform: translateY(-2px);
        box-shadow: 0 15px 25px rgba(102, 126, 234, 0.4);
        background: linear-gradient(135deg, #5a6fd6 0%, #6b4494 100%);
    }
    .alert-custom {
        background: #c6f6d5;
        border: 1px solid #9ae6b4;
        color: #276749;
        padding: 15px;
        border-radius: 12px;
        margin-bottom: 25px;
        font-weight: 500;
    }
</style>

<div class="subscribe-wrapper">
    <div class="subscribe-card">
        @if(session('success'))
            <div class="alert-custom">
                {{ session('success') }}
            </div>
        @endif
        
        <h2 class="subscribe-title">{{ $title }}</h2>
        <p class="subscribe-subtitle">
            {{ translate('Subscribe to receive updates from') }} {{ $user->name }}.
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
            
            <div class="form-floating">
                <label for="first_name" class="form-label-custom">{{ translate('First Name') }}</label>
                <input type="text" class="form-control-custom" id="first_name" name="first_name" placeholder="{{ translate('Enter your first name') }}">
            </div>
            
            <div class="form-floating">
                <label for="last_name" class="form-label-custom">{{ translate('Last Name') }}</label>
                <input type="text" class="form-control-custom" id="last_name" name="last_name" placeholder="{{ translate('Enter your last name') }}">
            </div>
            
            <div class="form-floating">
                <label for="email" class="form-label-custom">{{ translate('Email Address') }} <span style="color: #e53e3e;">*</span></label>
                <input type="email" class="form-control-custom" id="email" name="email" required placeholder="{{ translate('Enter your best email') }}">
            </div>
            
            <button type="submit" class="btn-subscribe">
                {{ translate('Join the List') }}
            </button>
        </form>
    </div>
</div>
@endsection
